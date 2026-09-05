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
	 * plus `location` (the .htaccess path, validated separately in the settings route).
	 * Verified against Redirection 5.10.0 `models/options.php:260-295`.
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
		'location',
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
		'monitor_post',
		'log_external',
		'log_header',
		'track_hits',
		'https',
		'support',
	);

	/**
	 * Maximum length, in characters, of a single stored prev/new value.
	 *
	 * @var int
	 */
	const MAX_STORED_VALUE_LENGTH = 200;

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
				'redirection_redirection_added'    => _x( 'Added a redirection for URL "{source_url}"', 'Logger: Redirection', 'simple-history' ),
				'redirection_redirection_edited'   => _x( 'Edited redirection for URL "{prev_source_url}"', 'Logger: Redirection', 'simple-history' ),
				'redirection_redirection_enabled'  => _x( 'Enabled redirection for {items_count} URL(s)', 'Logger: Redirection', 'simple-history' ),
				'redirection_redirection_disabled' => _x( 'Disabled redirection for {items_count} URL(s)', 'Logger: Redirection', 'simple-history' ),
				'redirection_redirection_deleted'  => _x( 'Deleted redirection for {items_count} URL(s)', 'Logger: Redirection', 'simple-history' ),
				'redirection_options_saved'        => _x( 'Updated redirection options', 'Logger: Redirection', 'simple-history' ),
				'redirection_options_saved_count'  => _x( 'Updated {settings_changed_count} redirection settings', 'Logger: Redirection', 'simple-history' ),
				'redirection_options_removed_all'  => _x( 'Removed all redirection options and deactivated plugin', 'Logger: Redirection', 'simple-history' ),
				'redirection_group_added'          => _x( 'Added redirection group "{group_name}"', 'Logger: Redirection', 'simple-history' ),
				'redirection_group_edited'         => _x( 'Edited redirection group "{prev_group_name}"', 'Logger: Redirection', 'simple-history' ),
				'redirection_group_enabled'        => _x( 'Enabled {items_count} redirection group(s)', 'Logger: Redirection', 'simple-history' ),
				'redirection_group_disabled'       => _x( 'Disabled {items_count} redirection group(s)', 'Logger: Redirection', 'simple-history' ),
				'redirection_group_deleted'        => _x( 'Deleted {items_count} redirection group(s)', 'Logger: Redirection', 'simple-history' ),
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
						),
						_x( 'Redirections deleted', 'Redirection logger: search', 'simple-history' ) => array(
							'redirection_redirection_deleted',
						),
						_x( 'Redirection groups', 'Redirection logger: search', 'simple-history' ) => array(
							'redirection_group_added',
							'redirection_group_edited',
							'redirection_group_enabled',
							'redirection_group_disabled',
							'redirection_group_deleted',
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

		// Redirection plugin uses the WP REST API, so catch when requests do the API is done.
		// We use filter *_before_callbacks so we can access the old title
		// of the Redirection object, i.e. before new values are saved.
		add_filter( 'rest_request_before_callbacks', array( $this, 'on_rest_request_before_callbacks' ), 10, 3 );
	}

	/**
	 * Fired when WP REST API call is done.
	 *
	 * @param \WP_HTTP_Response $response Result to send to the client. Usually a WP_REST_Response.
	 * @param \WP_REST_Server   $handler  ResponseHandler instance (usually WP_REST_Server).
	 * @param \WP_REST_Request  $request  Request used to generate the response.
	 *
	 * @return \WP_HTTP_Response $response
	 */
	public function on_rest_request_before_callbacks( $response, $handler, $request ) {
		// Callback must be set.
		if ( ! isset( $handler['callback'] ) ) {
			return $response;
		}

		$callback = $handler['callback'];

		$callable_name = Helpers::get_callable_name( $callback );

		$redirection_action = self::get_redirection_action_for_callable( $callable_name );

		// Bail directly if this is not a Redirection API call.
		if ( $redirection_action === null ) {
			return $response;
		}

		if ( $redirection_action === 'redirect_create' ) {
			$this->log_redirection_add( $request );
		} elseif ( $redirection_action === 'redirect_update' ) {
			$this->log_redirection_edit( $request );
		} elseif ( $redirection_action === 'redirect_bulk' ) {
			$bulk_action = $request->get_param( 'bulk' );
			$bulk_items  = $request->get_param( 'items' );

			// Redirection 5.10 supports a global bulk action ("apply to all
			// redirects") that omits `items` entirely, so bail before the
			// explode() below, which would otherwise turn a null into [ '' ]
			// and log a bogus item id 0.
			if ( empty( $bulk_items ) ) {
				return $response;
			}

			if ( ! is_array( $bulk_items ) ) {
				$bulk_items = explode( ',', $bulk_items );
			}

			$bulk_items = array_map( 'intval', $bulk_items );

			if ( empty( $bulk_items ) ) {
				return $response;
			}

			if ( $bulk_action === 'enable' ) {
				$this->log_redirection_enable_or_disable( $request, $bulk_items );
			} elseif ( $bulk_action === 'disable' ) {
				$this->log_redirection_enable_or_disable( $request, $bulk_items );
			} elseif ( $bulk_action === 'delete' ) {
				$this->log_redirection_delete( $request, $bulk_items );
			}
		} elseif ( $redirection_action === 'group_create' ) {
			$this->log_group_add( $request );
		} elseif ( $redirection_action === 'group_update' ) {
			$this->log_group_edit( $request );
		} elseif ( $redirection_action === 'group_bulk' ) {
			$bulk_action = $request->get_param( 'bulk' );
			$bulk_items  = (array) $request->get_param( 'items' );

			$bulk_items = array_map( 'intval', $bulk_items );

			if ( empty( $bulk_items ) ) {
				return $response;
			}

			if ( $bulk_action === 'enable' ) {
				$this->log_group_enable_or_disable( $request, $bulk_items );
			} elseif ( $bulk_action === 'disable' ) {
				$this->log_group_enable_or_disable( $request, $bulk_items );
			} elseif ( $bulk_action === 'delete' ) {
				$this->log_group_delete( $request, $bulk_items );
			}
		} elseif ( $redirection_action === 'settings_save' ) {
			$this->log_options_save( $request );
		}

		return $response;
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
	 * @param \WP_REST_Request $req Request.
	 */
	public function log_group_edit( $req ) {
		$group_id = $req->get_param( 'id' );

		if ( $group_id === null ) {
			return;
		}

		$context = array(
			'group_id'            => $group_id,
			'new_group_name'      => $req->get_param( 'name' ),
			'new_group_module_id' => $req->get_param( 'moduleId' ),
		);

		// Get old values.
		$redirection_item = \Red_Group::get( $group_id );
		if ( $redirection_item !== false ) {
			$prev_group_name      = $redirection_item->get_name();
			$prev_group_module_id = $redirection_item->get_module_id();

			$context['prev_group_name']      = $prev_group_name;
			$context['prev_group_module_id'] = $prev_group_module_id;
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
	 * We hook `rest_request_before_callbacks`, so this runs before
	 * `Settings::route_save_settings()` saves, meaning `\Red_Options::get()`
	 * still returns the previous values here.
	 *
	 * @param \WP_REST_Request $req Request.
	 */
	protected function log_options_save( $req ) {
		$params = $req->get_params();

		if ( ! is_array( $params ) || empty( $params ) || ! class_exists( 'Red_Options' ) ) {
			$this->info_message( 'redirection_options_saved' );

			return;
		}

		$params = self::filter_params_by_capability( $params );

		$previous_options = \Red_Options::get();

		$changed_settings = self::get_changed_settings( $params, $previous_options );

		if ( empty( $changed_settings ) ) {
			$this->info_message( 'redirection_options_saved' );

			return;
		}

		$context = array(
			'settings_changed_count' => count( $changed_settings ),
		);

		foreach ( $changed_settings as $option_key => $values ) {
			$context[ "redirection_option_{$option_key}_prev" ] = $values['prev'];
			$context[ "redirection_option_{$option_key}_new" ]  = $values['new'];
		}

		$this->info_message( 'redirection_options_saved_count', $context );
	}

	/**
	 * Filter settings-save request params down to only the ones the current user's
	 * Redirection capabilities allow saving.
	 *
	 * `Settings::route_save_settings()` runs the request params through
	 * `\Red_Options::filter_by_capability()` and only ever persists what survives
	 * (`includes/api/route/class-settings.php:94-95`) — a user holding only the
	 * site or only the option capability has the other bucket's keys silently
	 * dropped. Diffing the unfiltered params would then record a "change" for a
	 * key Redirection never actually saved, so filter first.
	 *
	 * Guarded with `method_exists()`: older Redirection versions that predate
	 * this capability split don't have the method, and everything is saved as
	 * before, so params pass through unchanged. Phpstan resolves `\Red_Options`
	 * against the one Redirection version installed on this machine, where the
	 * method always exists, so it flags the check as always true — real sites
	 * can and do run older versions, so the guard stays.
	 *
	 * Public and static so it can be unit tested independently of a real REST request.
	 *
	 * @param array<string, mixed> $params Raw request params.
	 * @return array<string, mixed> Params filtered the same way Redirection itself filters them.
	 */
	public static function filter_params_by_capability( array $params ) {
		if ( method_exists( '\Red_Options', 'filter_by_capability' ) ) { // @phpstan-ignore-line function.alreadyNarrowedType
			return \Red_Options::filter_by_capability( $params );
		}

		return $params;
	}

	/**
	 * Compare Redirection settings-save request params against the previous option
	 * values and return only the ones that actually changed, formatted for storage.
	 *
	 * Public and static so the diffing logic can be unit tested without the
	 * Redirection plugin installed or a real REST request.
	 *
	 * @param array<string, mixed> $params   Request params from the settings-save request.
	 *                                        Only keys that are known Redirection option
	 *                                        keys are considered; everything else is ignored.
	 * @param array<string, mixed> $previous Previous option values, i.e. the return value
	 *                                        of `\Red_Options::get()` before the save.
	 * @return array<string, array{prev: string, new: string}> Changed option keys mapped
	 *                                                          to their formatted prev/new values.
	 */
	public static function get_changed_settings( array $params, array $previous ) {
		$changed_settings = array();

		foreach ( $params as $option_key => $new_value ) {
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
		if ( $option_key === 'token' ) {
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
			'monitor_post'        => _x( 'Log post/page redirects', 'Logger: Redirection', 'simple-history' ),
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
	 * @param \WP_REST_Request $req Request.
	 */
	protected function log_redirection_edit( $req ) {
		$action_data = $req->get_param( 'action_data' );

		if ( ! $action_data || ! is_array( $action_data ) ) {
			return false;
		}

		$redirection_id = $req->get_param( 'id' );

		$context = array(
			'new_source_url' => $req->get_param( 'url' ),
			'new_target'     => $action_data['url'],
			'redirection_id' => $redirection_id,
		);

		// Get old values.
		$redirection_item = \Red_Item::get_by_id( $redirection_id );

		if ( $redirection_item !== false ) {
			$context['prev_source_url'] = $redirection_item->get_url();
			$context['prev_target']     = $this->unserialize_action_data( $redirection_item->get_action_data() );
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
}
