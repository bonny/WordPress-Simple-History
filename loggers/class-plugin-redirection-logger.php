<?php

namespace Simple_History\Loggers;

use Simple_History\Event_Details\Event_Details_Group;
use Simple_History\Event_Details\Event_Details_Group_Table_Formatter;
use Simple_History\Event_Details\Event_Details_Item;
use Simple_History\Helpers;

/**
 * Logger for the Redirection plugin
 * https://wordpress.org/plugins/redirection/
 */
class Plugin_Redirection_Logger extends Logger {
	/**
	 * Logger slug.
	 *
	 * @var string
	 */
	public $slug = 'Plugin_Redirection';

	/**
	 * REST callback class names Redirection has used, mapped to the entity each
	 * one handles. Both the legacy classes (Redirection <= 5.9) and the
	 * namespaced ones introduced in 5.10.0 map to the same three entities.
	 *
	 * @var array<string, string>
	 */
	const CLASS_NAMES_TO_ENTITY = array(
		'Redirection_Api_Redirect'       => 'redirect',
		'Redirection\Api\Route\Redirect' => 'redirect',
		'Redirection_Api_Group'          => 'group',
		'Redirection\Api\Route\Group'    => 'group',
		'Redirection_Api_Settings'       => 'settings',
		'Redirection\Api\Route\Settings' => 'settings',
	);

	/**
	 * REST callback method names, resolving to the logging action for each
	 * entity that supports the method.
	 *
	 * @var array<string, array<string, string>>
	 */
	const METHOD_NAMES_TO_ACTIONS = array(
		'route_bulk'          => array(
			'redirect' => 'redirect_bulk',
			'group'    => 'group_bulk',
		),
		'route_create'        => array(
			'redirect' => 'redirect_create',
			'group'    => 'group_create',
		),
		'route_update'        => array(
			'redirect' => 'redirect_update',
			'group'    => 'group_update',
		),
		'route_save_settings' => array(
			'settings' => 'settings_save',
		),
	);

	/**
	 * Redirection option keys that make up the single `redirection_options` option,
	 * plus `location` (the .htaccess path, derived from the Apache module in
	 * `\Red_Options::get()`). Verified against Redirection 5.10.0
	 * `models/options.php:260-295` (`get_default_options()`) plus the four
	 * `flag_*` defaults it merges in from `Red_Source_Flags::get_json()`.
	 *
	 * `last_group_id` is deliberately left out: Redirection bumps it whenever a
	 * group is added, which has nothing to do with a settings change.
	 *
	 * @var string[]
	 */
	const OPTION_KEYS = array(
		'monitor_post',
		'monitor_types',
		'associated_redirect',
		'auto_target',
		'expire_redirect',
		'expire_404',
		'log_external',
		'log_header',
		'track_hits',
		'modules',
		'redirect_cache',
		'cache_key',
		'ip_logging',
		'ip_headers',
		'ip_proxy',
		'rest_api',
		'https',
		'headers',
		'database',
		'relocate',
		'preferred_domain',
		'aliases',
		'permalinks',
		'support',
		'token',
		'plugin_update',
		'update_notice',
		'flag_query',
		'flag_case',
		'flag_trailing',
		'flag_regex',
		'location',
	);

	/**
	 * Option keys whose value is a secret. Only the fact that they changed is
	 * ever recorded, never the value itself, on either side of the change.
	 *
	 * @var string[]
	 */
	const REDACTED_OPTION_KEYS = array(
		'token',
	);

	/**
	 * Option keys whose value is an array of arrays, so a comma-joined list
	 * would be unreadable. Stored as an item count instead.
	 *
	 * @var string[]
	 */
	const NESTED_ARRAY_OPTION_KEYS = array(
		'headers',
		'modules',
	);

	/**
	 * Redirection option keys that are on/off switches. Stored as the raw
	 * "1"/"0" (or "" for false) so the record stays factual; shown as
	 * On/Off in the event details.
	 *
	 * @var string[]
	 */
	const BOOLEAN_OPTION_KEYS = array(
		'log_external',
		'log_header',
		'track_hits',
		'https',
		'support',
		'flag_case',
		'flag_trailing',
		'flag_regex',
	);

	/**
	 * Maximum length, in characters, of a single stored prev/new value.
	 *
	 * @var int
	 */
	const MAX_STORED_VALUE_LENGTH = 200;

	/**
	 * Message keys used for a global ("apply to everything matching the current
	 * filter") bulk action, per entity and bulk action.
	 *
	 * Redirection 5.10 sends `global: true` with no `items` when the user picks
	 * "select all" on the redirects or groups list. Groups have no `reset`
	 * action, which is why that row is missing below.
	 *
	 * @var array<string, array<string, string>>
	 */
	const GLOBAL_BULK_MESSAGE_KEYS = array(
		'redirect' => array(
			'enable'  => 'redirection_redirection_enabled_all',
			'disable' => 'redirection_redirection_disabled_all',
			'delete'  => 'redirection_redirection_deleted_all',
			'reset'   => 'redirection_redirection_reset_all',
		),
		'group'    => array(
			'enable'  => 'redirection_group_enabled_all',
			'disable' => 'redirection_group_disabled_all',
			'delete'  => 'redirection_group_deleted_all',
		),
	);

	/**
	 * Previous state captured on `rest_request_before_callbacks`, keyed by the
	 * request object's `spl_object_id()`.
	 *
	 * One PHP request can dispatch several REST requests — a `/batch/v1`
	 * envelope, or any `rest_do_request()` call — so this cannot be a single
	 * scalar property. Entries are removed again in
	 * `on_rest_request_after_callbacks()`.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private $captured_request_state = array();

	/**
	 * Return info about logger.
	 *
	 * @return array Array with plugin info.
	 */
	public function get_info() {

		return array(
			'name'        => _x( 'Plugin: Redirection Logger', 'Logger: Redirection', 'simple-history' ),
			'description' => _x( 'Logs edits in the Redirection plugin', 'Logger: Redirection', 'simple-history' ),
			'name_via'    => _x( 'In plugin Redirection', 'Logger: Redirection', 'simple-history' ),
			'capability'  => 'manage_options',
			'messages'    => array(
				'redirection_redirection_added'        => _x( 'Added a redirection for URL "{source_url}"', 'Logger: Redirection', 'simple-history' ),
				'redirection_redirection_edited'       => _x( 'Edited redirection for URL "{prev_source_url}"', 'Logger: Redirection', 'simple-history' ),
				'redirection_redirection_enabled'      => _x( 'Enabled redirection for {items_count} URL(s)', 'Logger: Redirection', 'simple-history' ),
				'redirection_redirection_disabled'     => _x( 'Disabled redirection for {items_count} URL(s)', 'Logger: Redirection', 'simple-history' ),
				'redirection_redirection_deleted'      => _x( 'Deleted redirection for {items_count} URL(s)', 'Logger: Redirection', 'simple-history' ),
				'redirection_redirection_enabled_all'  => _x( 'Enabled all redirections matching the current filter', 'Logger: Redirection', 'simple-history' ),
				'redirection_redirection_disabled_all' => _x( 'Disabled all redirections matching the current filter', 'Logger: Redirection', 'simple-history' ),
				'redirection_redirection_deleted_all'  => _x( 'Deleted all redirections matching the current filter', 'Logger: Redirection', 'simple-history' ),
				'redirection_redirection_reset_all'    => _x( 'Reset hit statistics for all redirections matching the current filter', 'Logger: Redirection', 'simple-history' ),
				'redirection_options_saved'            => _x( 'Updated redirection options', 'Logger: Redirection', 'simple-history' ),
				'redirection_options_saved_count'      => _x( 'Updated {settings_changed_count} redirection setting(s)', 'Logger: Redirection', 'simple-history' ),
				'redirection_options_removed_all'      => _x( 'Removed all redirection options and deactivated plugin', 'Logger: Redirection', 'simple-history' ),
				'redirection_group_added'              => _x( 'Added redirection group "{group_name}"', 'Logger: Redirection', 'simple-history' ),
				'redirection_group_edited'             => _x( 'Edited redirection group "{prev_group_name}"', 'Logger: Redirection', 'simple-history' ),
				'redirection_group_enabled'            => _x( 'Enabled {items_count} redirection group(s)', 'Logger: Redirection', 'simple-history' ),
				'redirection_group_disabled'           => _x( 'Disabled {items_count} redirection group(s)', 'Logger: Redirection', 'simple-history' ),
				'redirection_group_deleted'            => _x( 'Deleted {items_count} redirection group(s)', 'Logger: Redirection', 'simple-history' ),
				'redirection_group_enabled_all'        => _x( 'Enabled all redirection groups matching the current filter', 'Logger: Redirection', 'simple-history' ),
				'redirection_group_disabled_all'       => _x( 'Disabled all redirection groups matching the current filter', 'Logger: Redirection', 'simple-history' ),
				'redirection_group_deleted_all'        => _x( 'Deleted all redirection groups matching the current filter', 'Logger: Redirection', 'simple-history' ),
			),
			'labels'      => array(
				'search' => array(
					'label'     => _x( 'Redirection', 'Redirection logger: search', 'simple-history' ),
					'label_all' => _x( 'All Redirection activity', 'Redirection logger: search', 'simple-history' ),
					'options'   => array(
						_x( 'Redirections added', 'Redirection logger: search', 'simple-history' ) => array(
							'redirection_redirection_added',
						),
						_x( 'Redirections modified', 'Redirection logger: search', 'simple-history' ) => array(
							'redirection_redirection_edited',
							'redirection_redirection_enabled',
							'redirection_redirection_disabled',
							'redirection_redirection_enabled_all',
							'redirection_redirection_disabled_all',
							'redirection_redirection_reset_all',
						),
						_x( 'Redirections deleted', 'Redirection logger: search', 'simple-history' ) => array(
							'redirection_redirection_deleted',
							'redirection_redirection_deleted_all',
						),
						_x( 'Redirection groups', 'Redirection logger: search', 'simple-history' ) => array(
							'redirection_group_added',
							'redirection_group_edited',
							'redirection_group_enabled',
							'redirection_group_disabled',
							'redirection_group_deleted',
							'redirection_group_enabled_all',
							'redirection_group_disabled_all',
							'redirection_group_deleted_all',
						),
						_x( 'Redirection options', 'Redirection logger: search', 'simple-history' ) => array(
							'redirection_options_saved',
							'redirection_options_saved_count',
							'redirection_options_removed_all',
						),
					),
				),
			),
		);
	}

	/**
	 * Called when logger is loaded.
	 */
	public function loaded() {

		// Check that Redirection class exists.
		if ( ! class_exists( 'Red_Item' ) ) {
			return;
		}

		/*
		 * Redirection plugin uses the WP REST API, so catch when requests to
		 * the API are done. Two hooks, with different jobs:
		 *
		 * - `rest_request_before_callbacks` only *captures* the state that is
		 *   about to be overwritten (the redirect's old URL, the group's old
		 *   name, the stored options). It logs nothing, because it runs before
		 *   the route's `permission_callback` — logging there let any logged-in
		 *   user write false audit entries just by POSTing to the route.
		 * - `rest_request_after_callbacks` runs once Redirection's own callback
		 *   has returned, so it can tell an accepted request from a rejected
		 *   one, and read back what was actually stored.
		 */
		add_filter( 'rest_request_before_callbacks', array( $this, 'on_rest_request_before_callbacks' ), 10, 3 );
		add_filter( 'rest_request_after_callbacks', array( $this, 'on_rest_request_after_callbacks' ), 10, 3 );
	}

	/**
	 * Capture the state a Redirection REST request is about to overwrite.
	 *
	 * Nothing is logged here: this filter runs before the route's
	 * `permission_callback` and before Redirection's own callback, so at this
	 * point it is not yet known whether the request will be allowed, let alone
	 * succeed.
	 *
	 * @param \WP_HTTP_Response|\WP_Error|mixed $response Result to send to the client. Usually a WP_REST_Response.
	 * @param array                             $handler  Route handler used for the request.
	 * @param \WP_REST_Request                  $request  Request used to generate the response.
	 *
	 * @return \WP_HTTP_Response|\WP_Error|mixed Passthrough of $response.
	 */
	public function on_rest_request_before_callbacks( $response, $handler, $request ) {
		// Callback must be set.
		if ( ! isset( $handler['callback'] ) || ! $request instanceof \WP_REST_Request ) {
			return $response;
		}

		$callable_name = Helpers::get_callable_name( $handler['callback'] );

		$redirection_action = self::get_redirection_action_for_callable( $callable_name );

		// Bail directly if this is not a Redirection API call.
		if ( $redirection_action === null ) {
			return $response;
		}

		// A request whose callback fatals never reaches the after-callbacks
		// release below. In a long-running process (WP-CLI, tests) that
		// entry would linger and could be picked up by a later request that
		// reuses the object id, so never keep more than a handful.
		if ( count( $this->captured_request_state ) > 10 ) {
			$this->captured_request_state = array();
		}

		$this->captured_request_state[ spl_object_id( $request ) ] = $this->capture_previous_state( $redirection_action, $request );

		return $response;
	}

	/**
	 * Log a Redirection REST request, once Redirection itself has accepted it.
	 *
	 * @param \WP_HTTP_Response|\WP_Error|mixed $response Result to send to the client. Usually a WP_REST_Response.
	 * @param array                             $handler  Route handler used for the request.
	 * @param \WP_REST_Request                  $request  Request used to generate the response.
	 *
	 * @return \WP_HTTP_Response|\WP_Error|mixed Passthrough of $response.
	 */
	public function on_rest_request_after_callbacks( $response, $handler, $request ) {
		if ( ! $request instanceof \WP_REST_Request ) {
			return $response;
		}

		$request_key = spl_object_id( $request );

		// Not a Redirection request we matched on the way in.
		if ( ! isset( $this->captured_request_state[ $request_key ] ) ) {
			return $response;
		}

		$captured_state = $this->captured_request_state[ $request_key ];

		// Always release the captured state, also for a rejected request.
		unset( $this->captured_request_state[ $request_key ] );

		// A failed permission check or a failed save must not produce an event.
		if ( self::is_failed_response( $response ) ) {
			return $response;
		}

		$this->log_redirection_action( $captured_state['action'], $request, $captured_state );

		return $response;
	}

	/**
	 * Whether a REST response says the request was rejected or failed.
	 *
	 * At `rest_request_after_callbacks` time a failed permission check is still
	 * a `WP_Error` — it is only turned into a response object afterwards — and a
	 * route callback can return either a `WP_Error` or a response carrying an
	 * error status.
	 *
	 * @param \WP_HTTP_Response|\WP_Error|mixed $response Response as passed to the filter.
	 * @return bool
	 */
	private static function is_failed_response( $response ) {
		if ( is_wp_error( $response ) ) {
			return true;
		}

		return $response instanceof \WP_HTTP_Response && $response->get_status() >= 400;
	}

	/**
	 * Read the values a request is about to replace, so they can be logged as
	 * the "previous" side of the change once the request has gone through.
	 *
	 * @param string           $redirection_action Action from get_redirection_action_for_callable().
	 * @param \WP_REST_Request $request            Request used to generate the response.
	 * @return array<string, mixed> Captured state, always holding at least an `action` key.
	 */
	private function capture_previous_state( $redirection_action, $request ) {
		$captured_state = array( 'action' => $redirection_action );

		if ( $redirection_action === 'settings_save' ) {
			// `\Red_Options::get()` still returns the previous values here, and
			// caches them internally — the cache is cleared by its own save(),
			// so reading it again afterwards gives the stored new values.
			if ( class_exists( 'Red_Options' ) ) {
				$captured_state['previous_options'] = \Red_Options::get();
			}

			return $captured_state;
		}

		if ( $redirection_action === 'redirect_update' ) {
			$redirection_id = $request->get_param( 'id' );

			$redirection_item = $redirection_id === null ? false : \Red_Item::get_by_id( $redirection_id );

			if ( is_object( $redirection_item ) ) {
				$captured_state['prev_source_url'] = $redirection_item->get_url();
				$captured_state['prev_target']     = $this->unserialize_action_data( $redirection_item->get_action_data() );
			}

			return $captured_state;
		}

		if ( $redirection_action === 'group_update' ) {
			$group_id = $request->get_param( 'id' );

			$group_item = $group_id === null ? false : \Red_Group::get( $group_id );

			if ( is_object( $group_item ) ) {
				$captured_state['prev_group_name']      = $group_item->get_name();
				$captured_state['prev_group_module_id'] = $group_item->get_module_id();
			}

			return $captured_state;
		}

		return $captured_state;
	}

	/**
	 * Route an accepted Redirection request to the method that logs it.
	 *
	 * @param string               $redirection_action Action from get_redirection_action_for_callable().
	 * @param \WP_REST_Request     $request            Request used to generate the response.
	 * @param array<string, mixed> $captured_state     State captured before the request ran.
	 */
	private function log_redirection_action( $redirection_action, $request, array $captured_state ) {
		if ( $redirection_action === 'redirect_create' ) {
			$this->log_redirection_add( $request );
		} elseif ( $redirection_action === 'redirect_update' ) {
			$this->log_redirection_edit( $request, $captured_state );
		} elseif ( $redirection_action === 'redirect_bulk' ) {
			$this->log_redirect_bulk( $request );
		} elseif ( $redirection_action === 'group_create' ) {
			$this->log_group_add( $request );
		} elseif ( $redirection_action === 'group_update' ) {
			$this->log_group_edit( $request, $captured_state );
		} elseif ( $redirection_action === 'group_bulk' ) {
			$this->log_group_bulk( $request );
		} elseif ( $redirection_action === 'settings_save' ) {
			$this->log_options_save( $captured_state );
		}
	}

	/**
	 * Log a bulk action on redirects.
	 *
	 * Mirrors Redirection's own precedence in `Redirect::route_bulk()`: an
	 * explicit, non-empty `items` list wins, and only when there is none does
	 * the request's `global` flag ("apply to everything matching the current
	 * filter") come into play.
	 *
	 * @param \WP_REST_Request $request Request used to generate the response.
	 */
	private function log_redirect_bulk( $request ) {
		$bulk_action = $request->get_param( 'bulk' );
		$bulk_items  = $request->get_param( 'items' );

		if ( ! empty( $bulk_items ) ) {
			if ( ! is_array( $bulk_items ) ) {
				$bulk_items = explode( ',', $bulk_items );
			}

			$bulk_items = array_map( 'intval', $bulk_items );

			if ( $bulk_action === 'enable' || $bulk_action === 'disable' ) {
				$this->log_redirection_enable_or_disable( $request, $bulk_items );
			} elseif ( $bulk_action === 'delete' ) {
				$this->log_redirection_delete( $request, $bulk_items );
			}

			return;
		}

		$this->log_global_bulk( 'redirect', $bulk_action, $request );
	}

	/**
	 * Log a bulk action on redirection groups.
	 *
	 * @param \WP_REST_Request $request Request used to generate the response.
	 */
	private function log_group_bulk( $request ) {
		$bulk_action = $request->get_param( 'bulk' );
		$bulk_items  = $request->get_param( 'items' );

		if ( ! empty( $bulk_items ) ) {
			$bulk_items = array_map( 'intval', (array) $bulk_items );

			if ( $bulk_action === 'enable' || $bulk_action === 'disable' ) {
				$this->log_group_enable_or_disable( $request, $bulk_items );
			} elseif ( $bulk_action === 'delete' ) {
				$this->log_group_delete( $request, $bulk_items );
			}

			return;
		}

		$this->log_global_bulk( 'group', $bulk_action, $request );
	}

	/**
	 * Log a global ("select all") bulk action, which carries no item ids.
	 *
	 * @param string           $entity      One of 'redirect' or 'group'.
	 * @param string|null      $bulk_action Bulk action from the request.
	 * @param \WP_REST_Request $request     Request used to generate the response.
	 */
	private function log_global_bulk( $entity, $bulk_action, $request ) {
		if ( ! $request->get_param( 'global' ) ) {
			return;
		}

		$message_key = self::get_global_bulk_message_key( $entity, $bulk_action );

		if ( $message_key === null ) {
			return;
		}

		$this->info_message( $message_key );
	}

	/**
	 * Map an entity and bulk action to the message key for a global bulk action.
	 *
	 * Public and static so the key selection can be unit tested without a real
	 * REST request.
	 *
	 * @param string      $entity      One of 'redirect' or 'group'.
	 * @param string|null $bulk_action One of 'enable', 'disable', 'delete' or, for redirects, 'reset'.
	 * @return string|null Message key, or null when the combination is not one we log.
	 */
	public static function get_global_bulk_message_key( $entity, $bulk_action ) {
		if ( ! is_string( $bulk_action ) ) {
			return null;
		}

		return self::GLOBAL_BULK_MESSAGE_KEYS[ $entity ][ $bulk_action ] ?? null;
	}

	/**
	 * Map a REST callback's fully qualified callable name to the Redirection action it represents.
	 *
	 * Redirection 5.10.0 moved its REST callbacks from legacy classes like
	 * `Redirection_Api_Redirect` into namespaced ones like `Redirection\Api\Route\Redirect`.
	 * This matches both spellings for the same method names, so the logger keeps working
	 * on Redirection 5.10.0+ while still supporting sites on older versions (plugin
	 * auto-updates are off by default, so both are in the wild).
	 *
	 * Public and static so the matching logic can be unit tested without the Redirection
	 * plugin installed.
	 *
	 * @param string $callable_name Fully qualified callable name, e.g. "Redirection_Api_Redirect::route_create"
	 *                              or "Redirection\Api\Route\Redirect::route_create".
	 * @return string|null One of 'redirect_bulk', 'redirect_create', 'redirect_update',
	 *                     'group_bulk', 'group_create', 'group_update', 'settings_save',
	 *                     or null if the callable is not a Redirection API call we handle.
	 */
	public static function get_redirection_action_for_callable( $callable_name ) {
		if ( ! is_string( $callable_name ) || strpos( $callable_name, '::' ) === false ) {
			return null;
		}

		list( $class_name, $method_name ) = explode( '::', $callable_name, 2 );

		$entity = self::CLASS_NAMES_TO_ENTITY[ $class_name ] ?? null;

		if ( $entity === null ) {
			return null;
		}

		return self::METHOD_NAMES_TO_ACTIONS[ $method_name ][ $entity ] ?? null;
	}

	/**
	 * Log when a Redirection group is deleted.
	 *
	 * @param \WP_REST_Request $req Request.
	 * @param array            $bulk_items Array with item ids.
	 */
	public function log_group_delete( $req, $bulk_items ) {
		$context = array(
			'items'       => $bulk_items,
			'items_count' => count( $bulk_items ),
		);

		$this->info_message(
			'redirection_group_deleted',
			$context
		);
	}

	/**
	 * Log when a Redirection group is added.
	 *
	 * @param \WP_REST_Request $req Request.
	 */
	public function log_group_add( $req ) {
		$group_name = $req->get_param( 'name' );

		if ( ! $group_name ) {
			return;
		}

		$context = array(
			'group_name' => $group_name,
		);

		$this->info_message(
			'redirection_group_added',
			$context
		);
	}

	/**
	 * Log when a Redirection group is edited.
	 *
	 * @param \WP_REST_Request     $req            Request.
	 * @param array<string, mixed> $captured_state State captured before the request ran.
	 */
	public function log_group_edit( $req, array $captured_state = array() ) {
		$group_id = $req->get_param( 'id' );

		if ( $group_id === null ) {
			return;
		}

		$context = array(
			'group_id'            => $group_id,
			'new_group_name'      => $req->get_param( 'name' ),
			'new_group_module_id' => $req->get_param( 'moduleId' ),
		);

		// Old values, read before the group was saved.
		if ( array_key_exists( 'prev_group_name', $captured_state ) ) {
			$context['prev_group_name']      = $captured_state['prev_group_name'];
			$context['prev_group_module_id'] = $captured_state['prev_group_module_id'];
		}

		$this->info_message(
			'redirection_group_edited',
			$context
		);
	}

	/**
	 * Log enabling and disabling of redirection groups.
	 *
	 * @param object $req Request.
	 * @param array  $bulk_items Array with item ids.
	 */
	public function log_group_enable_or_disable( $req, $bulk_items ) {
		$bulk_action = $req->get_param( 'bulk' );

		$message_key = $bulk_action === 'enable' ? 'redirection_group_enabled' : 'redirection_group_disabled';

		$context = array(
			'items'       => $bulk_items,
			'items_count' => count( $bulk_items ),
		);

		$this->info_message(
			$message_key,
			$context
		);
	}

	/**
	 * Log when options are saved.
	 *
	 * Runs on `rest_request_after_callbacks`, so the diff is between what
	 * `\Red_Options::get()` returned before `Settings::route_save_settings()`
	 * ran and what it returns now. Diffing stored-before against stored-after,
	 * rather than against the raw request params, means the event records what
	 * Redirection actually persisted: keys the user's capabilities did not
	 * allow, values Redirection sanitized or ignored, and derived values such
	 * as `location` all take care of themselves.
	 *
	 * @param array<string, mixed> $captured_state State captured before the request ran.
	 */
	protected function log_options_save( array $captured_state ) {
		if ( ! array_key_exists( 'previous_options', $captured_state ) || ! class_exists( 'Red_Options' ) ) {
			$this->info_message( 'redirection_options_saved' );

			return;
		}

		$previous_options = $captured_state['previous_options'];
		$current_options  = \Red_Options::get();

		$changed_settings = self::get_changed_settings( $current_options, $previous_options );

		if ( empty( $changed_settings ) ) {
			$this->info_message( 'redirection_options_saved' );

			return;
		}

		$context = array(
			'settings_changed_count' => count( $changed_settings ),
		);

		foreach ( $changed_settings as $option_key => $values ) {
			// A redacted value is identical on both sides, and the details
			// container drops items whose new and prev values are the same —
			// which would hide the change while still counting it. Store only
			// a "(changed)" new value, as class-simple-history-logger.php does
			// for its own redacted settings.
			if ( in_array( $option_key, self::REDACTED_OPTION_KEYS, true ) ) {
				$context[ "redirection_option_{$option_key}_new" ] = __( '(changed)', 'simple-history' );

				continue;
			}

			$context[ "redirection_option_{$option_key}_prev" ] = $values['prev'];
			$context[ "redirection_option_{$option_key}_new" ]  = $values['new'];
		}

		$this->info_message( 'redirection_options_saved_count', $context );
	}

	/**
	 * Compare two sets of Redirection option values and return only the ones
	 * that actually changed, formatted for storage.
	 *
	 * Public and static so the diffing logic can be unit tested without the
	 * Redirection plugin installed or a real REST request.
	 *
	 * @param array<string, mixed> $current  Current option values, i.e. the return value
	 *                                        of `\Red_Options::get()` after the save.
	 *                                        Only keys that are known Redirection option
	 *                                        keys are considered; everything else is ignored.
	 * @param array<string, mixed> $previous Previous option values, i.e. the return value
	 *                                        of `\Red_Options::get()` before the save.
	 * @return array<string, array{prev: string, new: string}> Changed option keys mapped
	 *                                                          to their formatted prev/new values.
	 */
	public static function get_changed_settings( array $current, array $previous ) {
		$changed_settings = array();

		foreach ( $current as $option_key => $new_value ) {
			if ( ! in_array( $option_key, self::OPTION_KEYS, true ) ) {
				continue;
			}

			$prev_value = $previous[ $option_key ] ?? '';

			$is_changed = self::normalize_value_for_compare( $new_value ) !== self::normalize_value_for_compare( $prev_value );

			if ( ! $is_changed ) {
				continue;
			}

			$changed_settings[ $option_key ] = array(
				'prev' => self::format_value_for_storage( $option_key, $prev_value ),
				'new'  => self::format_value_for_storage( $option_key, $new_value ),
			);
		}

		return $changed_settings;
	}

	/**
	 * Normalize a setting value for change comparison only.
	 *
	 * Scalars are cast to strings, so `1` and `true` compare equal. Arrays are
	 * stringified element by element (nested arrays as JSON) and sorted before
	 * being joined, so array order never counts as a change.
	 *
	 * @param mixed $value Value to normalize.
	 * @return string Normalized value, for comparison only, never for display.
	 */
	private static function normalize_value_for_compare( $value ) {
		if ( is_array( $value ) ) {
			return self::stringify_array( $value, ',' );
		}

		return (string) $value;
	}

	/**
	 * Turn an array setting into one sorted, glued string, so comparison and
	 * storage agree on what an array "looks like" regardless of item order.
	 *
	 * @param array  $value Array value.
	 * @param string $glue  Separator between items.
	 * @return string
	 */
	private static function stringify_array( array $value, $glue ) {
		$stringified_items = array_map(
			function ( $item ) {
				return is_array( $item ) ? wp_json_encode( $item ) : (string) $item;
			},
			$value
		);

		sort( $stringified_items );

		return implode( $glue, $stringified_items );
	}

	/**
	 * Format a setting value for storage in the event context.
	 *
	 * Redacts the `token` key, counts nested arrays (`headers`, `modules`)
	 * instead of listing them, comma-joins other arrays, and caps the result
	 * at `MAX_STORED_VALUE_LENGTH` characters.
	 *
	 * @param string $option_key Redirection option key.
	 * @param mixed  $value      Value to format.
	 * @return string Formatted value, safe to store in the event context.
	 */
	private static function format_value_for_storage( $option_key, $value ) {
		if ( in_array( $option_key, self::REDACTED_OPTION_KEYS, true ) ) {
			return '[redacted]';
		}

		if ( in_array( $option_key, self::NESTED_ARRAY_OPTION_KEYS, true ) && is_array( $value ) ) {
			$items_count = count( $value );

			return sprintf(
				/* translators: %d: number of items. */
				_n( '%d item', '%d items', $items_count, 'simple-history' ),
				$items_count
			);
		}

		$value = is_array( $value ) ? self::stringify_array( $value, ', ' ) : (string) $value;

		if ( strlen( $value ) > self::MAX_STORED_VALUE_LENGTH ) {
			$value = substr( $value, 0, self::MAX_STORED_VALUE_LENGTH );
		}

		return $value;
	}

	/**
	 * Format a stored setting value for display in the event details table.
	 *
	 * Storage keeps Redirection's raw values ("1", "0", "2") so the record is
	 * exact; this maps the ones a reader cannot decode at a glance. Unknown
	 * keys and values pass through unchanged.
	 *
	 * @param string      $option_key Redirection option key.
	 * @param string|null $value      Stored value.
	 * @return string|null Display value.
	 */
	public static function format_value_for_display( $option_key, $value ) {
		if ( $value === null ) {
			return null;
		}

		if ( in_array( $option_key, self::BOOLEAN_OPTION_KEYS, true ) ) {
			$is_on = in_array( $value, array( '1', 'true' ), true );

			return $is_on
				? _x( 'On', 'Logger: Redirection, setting value', 'simple-history' )
				: _x( 'Off', 'Logger: Redirection, setting value', 'simple-history' );
		}

		if ( $option_key === 'ip_logging' ) {
			$ip_logging_labels = array(
				'0' => _x( 'Off', 'Logger: Redirection, setting value', 'simple-history' ),
				'1' => _x( 'Full IP', 'Logger: Redirection, setting value', 'simple-history' ),
				'2' => _x( 'Anonymized IP', 'Logger: Redirection, setting value', 'simple-history' ),
			);

			return $ip_logging_labels[ $value ] ?? $value;
		}

		if ( $option_key === 'flag_query' ) {
			// Wording taken from Redirection's own options screen, so the event
			// reads the same as the setting the user just changed.
			$flag_query_labels = array(
				'exact'      => _x( 'Exact match in any order', 'Logger: Redirection, setting value', 'simple-history' ),
				'exactorder' => _x( 'Exact match', 'Logger: Redirection, setting value', 'simple-history' ),
				'ignore'     => _x( 'Ignore all query parameters', 'Logger: Redirection, setting value', 'simple-history' ),
				'pass'       => _x( 'Ignore and pass all query parameters', 'Logger: Redirection, setting value', 'simple-history' ),
			);

			return $flag_query_labels[ $value ] ?? $value;
		}

		return $value;
	}

	/**
	 * Get a human readable label for a Redirection option key, for use in the
	 * event details table. Falls back to the raw key when no label is mapped.
	 *
	 * @param string $option_key Redirection option key.
	 * @return string Human readable label.
	 */
	private static function get_option_label( $option_key ) {
		// Built once per request: this runs once per changed setting, for
		// every settings event in a REST response.
		static $labels = null;

		if ( $labels !== null ) {
			return $labels[ $option_key ] ?? $option_key;
		}

		$labels = array(
			'monitor_post'        => _x( 'Group for monitored post redirects', 'Logger: Redirection', 'simple-history' ),
			'monitor_types'       => _x( 'Monitored post types', 'Logger: Redirection', 'simple-history' ),
			'associated_redirect' => _x( 'Associated redirect action', 'Logger: Redirection', 'simple-history' ),
			'auto_target'         => _x( 'Auto target', 'Logger: Redirection', 'simple-history' ),
			'expire_redirect'     => _x( 'Redirect log expiry (days)', 'Logger: Redirection', 'simple-history' ),
			'expire_404'          => _x( '404 log expiry (days)', 'Logger: Redirection', 'simple-history' ),
			'log_external'        => _x( 'Log external redirects', 'Logger: Redirection', 'simple-history' ),
			'log_header'          => _x( 'Log HTTP headers', 'Logger: Redirection', 'simple-history' ),
			'track_hits'          => _x( 'Track hits', 'Logger: Redirection', 'simple-history' ),
			'modules'             => _x( 'Modules', 'Logger: Redirection', 'simple-history' ),
			'redirect_cache'      => _x( 'Redirect cache', 'Logger: Redirection', 'simple-history' ),
			'cache_key'           => _x( 'Redirect cache key', 'Logger: Redirection', 'simple-history' ),
			'ip_logging'          => _x( 'IP logging', 'Logger: Redirection', 'simple-history' ),
			'ip_headers'          => _x( 'IP headers', 'Logger: Redirection', 'simple-history' ),
			'ip_proxy'            => _x( 'IP proxy headers', 'Logger: Redirection', 'simple-history' ),
			'rest_api'            => _x( 'REST API mode', 'Logger: Redirection', 'simple-history' ),
			'https'               => _x( 'Force HTTPS', 'Logger: Redirection', 'simple-history' ),
			'headers'             => _x( 'Extra headers', 'Logger: Redirection', 'simple-history' ),
			'database'            => _x( 'Database', 'Logger: Redirection', 'simple-history' ),
			'relocate'            => _x( 'Relocate', 'Logger: Redirection', 'simple-history' ),
			'preferred_domain'    => _x( 'Preferred domain', 'Logger: Redirection', 'simple-history' ),
			'aliases'             => _x( 'Aliases', 'Logger: Redirection', 'simple-history' ),
			'permalinks'          => _x( 'Permalinks', 'Logger: Redirection', 'simple-history' ),
			'support'             => _x( 'Support access', 'Logger: Redirection', 'simple-history' ),
			'token'               => _x( 'REST API token', 'Logger: Redirection', 'simple-history' ),
			'plugin_update'       => _x( 'Plugin update channel', 'Logger: Redirection', 'simple-history' ),
			'update_notice'       => _x( 'Update notice dismissed', 'Logger: Redirection', 'simple-history' ),
			'flag_query'          => _x( 'Query parameter handling by default', 'Logger: Redirection', 'simple-history' ),
			'flag_case'           => _x( 'Ignore case by default', 'Logger: Redirection', 'simple-history' ),
			'flag_trailing'       => _x( 'Ignore trailing slash by default', 'Logger: Redirection', 'simple-history' ),
			'flag_regex'          => _x( 'Regular expression by default', 'Logger: Redirection', 'simple-history' ),
			'location'            => _x( '.htaccess location', 'Logger: Redirection', 'simple-history' ),
		);

		return $labels[ $option_key ] ?? $option_key;
	}

	/**
	 * Log the deletion of a redirection.
	 *
	 * @param object $req Request.
	 * @param array  $bulk_items Array with item ids.
	 */
	protected function log_redirection_delete( $req, $bulk_items ) {
		$context = array(
			'items'       => $bulk_items,
			'items_count' => count( $bulk_items ),
		);

		$message_key = 'redirection_redirection_deleted';

		$this->info_message(
			$message_key,
			$context
		);
	}

	/**
	 * Log enable or disable of items.
	 *
	 * @param object $req Req.
	 * @param array  $bulk_items Array.
	 */
	protected function log_redirection_enable_or_disable( $req, $bulk_items ) {
		$bulk_action = $req->get_param( 'bulk' );

		$message_key = $bulk_action === 'enable' ? 'redirection_redirection_enabled' : 'redirection_redirection_disabled';

		$context = array(
			'items'       => $bulk_items,
			'items_count' => count( $bulk_items ),
		);

		$this->info_message(
			$message_key,
			$context
		);
	}

	/**
	 * Log when a Redirection is added.
	 *
	 * @param \WP_REST_Request $req Request.
	 */
	protected function log_redirection_add( $req ) {
		$action_data = $req->get_param( 'action_data' );

		if ( ! $action_data || ! is_array( $action_data ) ) {
			return false;
		}

		$context = array(
			'source_url' => $req->get_param( 'url' ),
			'target_url' => $action_data['url'],
		);

		$this->info_message( 'redirection_redirection_added', $context );
	}

	/**
	 * Log when a Redirection is changed.
	 *
	 * @param \WP_REST_Request     $req            Request.
	 * @param array<string, mixed> $captured_state State captured before the request ran.
	 */
	protected function log_redirection_edit( $req, array $captured_state = array() ) {
		$action_data = $req->get_param( 'action_data' );

		if ( ! $action_data || ! is_array( $action_data ) ) {
			return false;
		}

		$context = array(
			'new_source_url' => $req->get_param( 'url' ),
			'new_target'     => $action_data['url'],
			'redirection_id' => $req->get_param( 'id' ),
		);

		// Old values, read before the redirect was saved.
		if ( array_key_exists( 'prev_source_url', $captured_state ) ) {
			$context['prev_source_url'] = $captured_state['prev_source_url'];
			$context['prev_target']     = $captured_state['prev_target'];
		}

		$this->info_message(
			'redirection_redirection_edited',
			$context
		);
	}

	/**
	 * Unserialize action data from the Redirection plugin, without allowing objects.
	 *
	 * The value comes from Redirection's own database table and is only ever
	 * expected to hold scalars and arrays. Allowing objects would turn this into
	 * a PHP object injection sink if any active plugin or theme ships a usable
	 * gadget chain.
	 *
	 * Mirrors maybe_unserialize(), including the trim() and the suppressed
	 * warning: is_serialized() trims before deciding, so whitespace padded data
	 * passes the check but fails a raw unserialize(), and corrupt data can slip
	 * past the check entirely. This runs inside Redirection's own REST request,
	 * where an unsuppressed notice would corrupt the JSON response.
	 *
	 * @param mixed $action_data Raw action data from Redirection.
	 * @return mixed Unserialized data, the value as-is if it is not serialized,
	 *               or false if it looked serialized but could not be decoded.
	 */
	private function unserialize_action_data( $action_data ) {
		if ( ! is_string( $action_data ) || ! is_serialized( $action_data ) ) {
			return $action_data;
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize, WordPress.PHP.NoSilencedErrors.Discouraged -- Objects are explicitly disallowed; matches maybe_unserialize() behaviour.
		return @unserialize( trim( $action_data ), [ 'allowed_classes' => false ] );
	}

	/**
	 * Return more info about an logged redirection event.
	 *
	 * @param object $row Row with info.
	 */
	public function get_log_row_details_output( $row ) {
		$context     = $row->context;
		$message_key = $context['_message_key'];

		if ( $message_key === 'redirection_options_saved_count' ) {
			return $this->get_options_saved_details_output( $context );
		}

		if ( $message_key !== 'redirection_redirection_edited' ) {
			return '';
		}

		$group = new Event_Details_Group();
		$group->set_formatter( new Event_Details_Group_Table_Formatter() );

		$group->add_items(
			[
				new Event_Details_Item(
					[ 'new_source_url', 'prev_source_url' ],
					_x( 'Source URL', 'Logger: Redirection', 'simple-history' ),
				),
				new Event_Details_Item(
					[ 'new_target', 'prev_target' ],
					_x( 'Target', 'Logger: Redirection', 'simple-history' ),
				),
			]
		);

		return $group;
	}

	/**
	 * Build the before/after details table for a "settings changed" event.
	 *
	 * @param array $context Event context, holding a `redirection_option_<key>_prev`
	 *                        and `redirection_option_<key>_new` pair per changed setting.
	 * @return Event_Details_Group
	 */
	private function get_options_saved_details_output( $context ) {
		$group = new Event_Details_Group();

		// The compact table (new value, old value struck through) suits these
		// short scalar settings; the wide diff table is kept for redirect edits,
		// where a source and target URL benefit from side-by-side reading.
		$group->set_formatter( new Event_Details_Group_Table_Formatter() );

		$items = array();

		// Iterate the known option keys, in a fixed order, rather than the
		// context array, so the table renders in a stable, predictable order.
		foreach ( self::OPTION_KEYS as $option_key ) {
			$new_key = "redirection_option_{$option_key}_new";

			if ( ! array_key_exists( $new_key, $context ) ) {
				continue;
			}

			$prev_key = "redirection_option_{$option_key}_prev";

			$item = new Event_Details_Item(
				[ $new_key, $prev_key ],
				self::get_option_label( $option_key )
			);

			// Values set here are kept by the details container, which only
			// fills in values from context when none is set.
			$item->set_values(
				self::format_value_for_display( $option_key, $context[ $new_key ] ),
				self::format_value_for_display( $option_key, $context[ $prev_key ] ?? null )
			);

			$items[] = $item;
		}

		$group->add_items( $items );

		return $group;
	}

	/**
	 * Get action links for a log row.
	 *
	 * Every Redirection destination lives behind the plugin's own admin
	 * screens, so there is nothing to link to once it's inactive — mirrors
	 * the `class_exists( 'Red_Item' )` guard `loaded()` uses to skip hooking
	 * in at all.
	 *
	 * @param object $row Log row object.
	 * @return array Array of action link arrays.
	 */
	public function get_action_links( $row ) {
		if ( ! class_exists( 'Red_Item' ) ) {
			return [];
		}

		$context     = $row->context;
		$message_key = $context['_message_key'] ?? '';

		$redirect_message_keys = [
			'redirection_redirection_edited',
			'redirection_redirection_enabled',
			'redirection_redirection_disabled',
			'redirection_redirection_deleted',
			'redirection_redirection_added',
			'redirection_redirection_enabled_all',
			'redirection_redirection_disabled_all',
			'redirection_redirection_deleted_all',
			'redirection_redirection_reset_all',
		];

		if ( in_array( $message_key, $redirect_message_keys, true ) ) {
			return $this->get_redirect_action_links( $message_key, $context );
		}

		if ( strpos( $message_key, 'redirection_group_' ) === 0 ) {
			return $this->get_group_action_links( $message_key, $context );
		}

		if ( in_array( $message_key, [ 'redirection_options_saved', 'redirection_options_saved_count' ], true ) ) {
			return $this->get_options_action_links();
		}

		// `redirection_options_removed_all` deactivates the plugin as part of
		// the same request, so every destination above is already gone by the
		// time this renders. Anything else is an unmapped message key.
		return [];
	}

	/**
	 * Action links for the redirect-item message keys: a per-redirect link
	 * (when the event carries enough to build one) followed by the redirects
	 * list overview.
	 *
	 * @param string               $message_key Event message key.
	 * @param array<string, mixed> $context     Event context.
	 * @return array Array of action link arrays.
	 */
	private function get_redirect_action_links( $message_key, array $context ) {
		if ( ! self::current_user_can_manage( 'redirects' ) ) {
			return [];
		}

		$action_links = [];

		$per_redirect_link = $this->get_per_redirect_action_link( $message_key, $context );

		if ( $per_redirect_link !== null ) {
			$action_links[] = $per_redirect_link;
		}

		$action_links[] = [
			'url'    => admin_url( 'tools.php?page=redirection.php' ),
			'label'  => __( 'All redirects', 'simple-history' ),
			'action' => 'view',
		];

		return $action_links;
	}

	/**
	 * Build the per-redirect deep link for a redirect-item message key, or
	 * null when the message key has no usable per-item destination.
	 *
	 * @param string               $message_key Event message key.
	 * @param array<string, mixed> $context     Event context.
	 * @return array{url: string, label: string, action: string}|null
	 */
	private function get_per_redirect_action_link( $message_key, array $context ) {
		if ( $message_key === 'redirection_redirection_edited' ) {
			// The redirect id is stored, but the redirects list ignores
			// `filterby[id]` (see build_redirect_filter_link()), so filter on
			// the source URL instead. The new one first: that is what the row
			// looks like now, which is what the list is being filtered against.
			$source_url = (string) ( $context['new_source_url'] ?? '' );

			if ( $source_url === '' ) {
				$source_url = (string) ( $context['prev_source_url'] ?? '' );
			}

			if ( $source_url === '' ) {
				return null;
			}

			return $this->build_redirect_filter_link( 'url', $source_url );
		}

		if ( $message_key === 'redirection_redirection_added' ) {
			$source_url = $context['source_url'] ?? '';

			if ( $source_url === '' ) {
				return null;
			}

			// No id is stored for "added" (Redirection's own callback creates
			// the row, and we only read the request params), but the redirects
			// list's own `filterby[url]` query param is honoured on load.
			return $this->build_redirect_filter_link( 'url', $source_url );
		}

		// Everything else — deleted, enabled, disabled and the global bulk
		// actions — either has no single item, or the item is gone. The
		// redirects list overview is the only useful destination.
		return null;
	}

	/**
	 * Build a link to the redirects list filtered the same way Redirection's
	 * own UI does it: `tools.php?page=redirection.php&filterby[url]=/old-url/`.
	 * `add_query_arg()` with a nested array value encodes the key as
	 * `filterby%5Burl%5D=…`, matching what Redirection's own links build in
	 * `build/redirection.js`.
	 *
	 * Only the filters on the target list's own `allowedFilters` are usable.
	 * For the redirects page (Redirection 5.10.0 `build/redirection.js`) that is
	 * `url`, `url-exact`, `target`, `title`, `group`, `status`, `match` and
	 * `action` — notably *not* `id`, which is why a redirect is linked by its
	 * source URL rather than by its id. `url` matches server-side with a `LIKE`
	 * (`includes/redirect/class-filters.php`), so a URL that is a prefix of
	 * another can match more than one row.
	 *
	 * `add_query_arg()` does not encode array values (only the `filterby[…]`
	 * key), so a source URL with `&`, `#`, `%`, or spaces would otherwise
	 * corrupt the query string — `rawurlencode()` it ourselves; a plain
	 * numeric group id round-trips through that unchanged.
	 *
	 * Labelled and typed as `edit`, not `view`: the redirects list has no
	 * separate read-only view for a single redirect, so landing on the
	 * filtered list is the entry point into editing that row inline.
	 *
	 * @param string      $filter_key One of the redirects list's filters: 'url' or 'group'.
	 * @param int|string  $value      Value to filter by.
	 * @param string|null $label      Link label; defaults to "Edit redirect".
	 * @param string      $action     Action type; defaults to 'edit'.
	 * @return array{url: string, label: string, action: string}
	 */
	private function build_redirect_filter_link( $filter_key, $value, $label = null, $action = 'edit' ) {
		return [
			'url'    => add_query_arg(
				[ 'filterby' => [ $filter_key => rawurlencode( (string) $value ) ] ],
				admin_url( 'tools.php?page=redirection.php' )
			),
			'label'  => $label ?? __( 'Edit redirect', 'simple-history' ),
			'action' => $action,
		];
	}

	/**
	 * Action links for the group message keys: for `redirection_group_edited`,
	 * a first link to the redirects in that group (the redirects list's own
	 * `filterby[group]` filter — `group` is in its `allowedFilters`), followed
	 * by the groups list overview. Every other group message key gets only
	 * the overview; no other per-group deep link exists in Redirection's UI.
	 *
	 * @param string               $message_key Event message key.
	 * @param array<string, mixed> $context     Event context.
	 * @return array Array of action link arrays.
	 */
	private function get_group_action_links( $message_key, array $context ) {
		$action_links = [];

		if ( $message_key === 'redirection_group_edited' ) {
			$group_id = $context['group_id'] ?? null;

			if ( $group_id !== null && $group_id !== '' && self::current_user_can_manage( 'redirects' ) ) {
				$action_links[] = $this->build_redirect_filter_link( 'group', $group_id, __( 'Redirects in group', 'simple-history' ), 'view' );
			}
		}

		if ( self::current_user_can_manage( 'groups' ) ) {
			$action_links[] = [
				'url'    => add_query_arg( [ 'sub' => 'groups' ], admin_url( 'tools.php?page=redirection.php' ) ),
				'label'  => __( 'All groups', 'simple-history' ),
				'action' => 'view',
			];
		}

		return $action_links;
	}

	/**
	 * Action link for the options message keys: the options screen itself.
	 *
	 * @return array Array of action link arrays.
	 */
	private function get_options_action_links() {
		if ( ! self::current_user_can_manage( 'options' ) ) {
			return [];
		}

		return [
			[
				'url'    => add_query_arg( [ 'sub' => 'options' ], admin_url( 'tools.php?page=redirection.php' ) ),
				'label'  => __( 'Redirection options', 'simple-history' ),
				'action' => 'view',
			],
		];
	}

	/**
	 * Whether the current user may see action links for a Redirection area.
	 *
	 * `Redirection_Capabilities` is only defined once `redirection-admin.php`
	 * has loaded, which happens on `is_admin()` or WP-CLI requests — not
	 * guaranteed in every context this can run in (e.g. wpunit, where
	 * Redirection is loaded but never "activated"). Falls back to
	 * `manage_options`, Redirection's own default capability, when the class
	 * isn't there.
	 *
	 * @param string $area One of 'redirects', 'groups', 'options'.
	 * @return bool
	 */
	private static function current_user_can_manage( $area ) {
		$capability_constants = array(
			'redirects' => 'CAP_REDIRECT_MANAGE',
			'groups'    => 'CAP_GROUP_MANAGE',
			'options'   => 'CAP_OPTION_MANAGE',
		);

		if ( class_exists( 'Redirection_Capabilities' ) ) {
			return \Redirection_Capabilities::has_access( constant( '\Redirection_Capabilities::' . $capability_constants[ $area ] ) );
		}

		return current_user_can( 'manage_options' );
	}
}
