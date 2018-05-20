<?php

/*
// Nya filter

// Ny redirect
$data = apply_filters( 'redirection_create_redirect', $data );

// Uppdatera redirect
$data = apply_filters( 'redirection_update_redirect', $data );

// Get Redirection item
Red_Item::get_by_id();

*/


defined( 'ABSPATH' ) or die();

/**
 * Logger for the Redirection plugin
 * https://wordpress.org/plugins/redirection/
 */
if ( ! class_exists( 'Plugin_Redirection' ) ) {

	/**
	 * Class to log things from the Redirection plugin.
	 */
	class Plugin_Redirection extends SimpleLogger {

		/**
		 * Logger slug.
		 *
		 * @var string
		 */
		public $slug = __CLASS__;

		/**
		 * Return info about logger.
		 *
		 * @return array Array with plugin info.
		 */
		public function getInfo() {

			$arr_info = array(
				'name' => 'Redirection',
				'description' => _x( 'Text', 'Logger: Redirection', 'simple-history' ),
				'name_via' => _x( 'In plugin Redirection', 'Logger: Redirection', 'simple-history' ),
				'capability' => 'manage_options',
				'messages' => array(
					'redirection_redirection_added' => _x( 'Added a redirection for URL "{source_url}"', 'Logger: Redirection', 'simple-history' ),
					'redirection_redirection_edited' => _x( 'Edited the redirection for URL "{prev_source_url}"', 'Logger: Redirection', 'simple-history' ),
					'redirection_redirection_enabled' => _x( 'Enabled the redirection for {items_count} URL(s)', 'Logger: Redirection', 'simple-history' ),
					'redirection_redirection_disabled' => _x( 'Disabled the redirection for {items_count} URL(s)', 'Logger: Redirection', 'simple-history' ),
					'redirection_redirection_deleted' => _x( 'Deleted redirection for {items_count} URL(s)', 'Logger: Redirection', 'simple-history' ),
					'redirection_options_saved' => _x( 'Updated options', 'Logger: Redirection', 'simple-history' ),
					'redirection_options_removed_all' => _x( 'Removed all options and deactivated plugin', 'Logger: Redirection', 'simple-history' ),
					'redirection_group_added' => _x( 'Added group "{group_name}"', 'Logger: Redirection', 'simple-history' ),
					'redirection_group_deleted' => _x( 'Deleted {items_count} group(s)', 'Logger: Redirection', 'simple-history' ),
				),
				/*
				"labels" => array(
					"search" => array(
						"label" => _x("Plugin Redirection", "Logger: Redirection", "simple-history"),
						"label_all" => _x("All posts & pages activity", "Logger: Redirection", "simple-history"),
						"options" => array(
							_x("Posts created", "Logger: Redirection", "simple-history") => array(
								"post_created"
							),
							_x("Posts updated", "Logger: Redirection", "simple-history") => array(
								"post_updated"
							),
							_x("Posts trashed", "Logger: Redirection", "simple-history") => array(
								"post_trashed"
							),
							_x("Posts deleted", "Logger: Redirection", "simple-history") => array(
								"post_deleted"
							),
							_x("Posts restored", "Logger: Redirection", "simple-history") => array(
								"post_restored"
							),
						)
					) // end search array
				) // end labels
				*/

			);

			return $arr_info;

		}

		/**
		 * Called when logger is loaded.
		 */
		public function loaded() {
			// Redirection plugin uses the WP REST API, so catch when requests do the API is done.
			// We use filter *_before_callbacks so we can access the old title
			// of the Redirection object, i.e. before new values are saved.
			add_filter( 'rest_request_before_callbacks', array( $this, 'on_rest_request_before_callbacks' ), 10, 3 );
		}

		/**
		 * Fired when WP REST API call is done.
		 *
		 * @param WP_HTTP_Response $response Result to send to the client. Usually a WP_REST_Response.
		 * @param WP_REST_Server   $handler  ResponseHandler instance (usually WP_REST_Server).
		 * @param WP_REST_Request  $request  Request used to generate the response.
		 *
		 * @return WP_HTTP_Response $response
		 */
		public function on_rest_request_before_callbacks( $response, $handler, $request ) {
			/*
			sh_error_log(
				'rest_request_after_callbacks:',
				$handler['callback'][0],
				$handler['callback'][1],
				$request->get_params()
			);
			*/

			// API route callback object, for example "Redirection_Api_Redirect" Object.
			$route_callback_object = isset( $handler['callback'][0] ) ? $handler['callback'][0] : false;

			// Method name to call on callback class, for example "route_bulk".
			$route_callback_method = isset( $handler['callback'][1] ) ? $handler['callback'][1] : false;

			// Bail directly if this is not a Redirection API call.
			if ( 'Redirection_Api_Redirect' !== get_class( $route_callback_object ) ) {
				return $response;
			}

			if ( 'route_create' === $route_callback_method ) {
				$this->log_redirection_add( $request );
			} elseif ( 'route_update' === $route_callback_method ) {
				$this->log_redirection_edit( $request );
			}

			if ( 'route_bulk' === $route_callback_method ) {

				$bulk_action = $request->get_param( 'bulk' );

				$bulk_items = $request->get_param( 'items' );
				$bulk_items = explode( ',', $bulk_items );

				if ( is_array( $bulk_items ) ) {
					$bulk_items = array_map( 'intval', $bulk_items );
				}

				if ( empty( $bulk_items ) ) {
					return $response;
				}

				if ( 'enable' === $bulk_action ) {
					$this->log_redirection_enable_or_disable( $request, $bulk_items );
				} elseif ( 'disable' === $bulk_action ) {
					$this->log_redirection_enable_or_disable( $request, $bulk_items );
				} elseif ( 'delete' === $bulk_action ) {
					$this->log_redirection_delete( $request, $bulk_items );
				}

				// Old things that is not logged yet again after Redirection changed to using the REST API.
				// $this->log_options_delete_all( $_REQUEST );
				// $this->log_options_save( $_REQUEST );
				// $this->log_group_add( $_REQUEST );
				// $this->log_group_delete( $_REQUEST );
				// $this->log_group_enable_or_disable( $_REQUEST );
				// $this->log_group_enable_or_disable( $_REQUEST );
			}

			return $response;
		}

		function log_group_delete( $req ) {

			$items = isset( $req['item'] ) ? (array) $req['item'] : array();

			$context = array(
				'items' => $items,
				'items_count' => count( $items ),
			);

			$this->infoMessage(
				'redirection_group_deleted',
				$context
			);

		}

		function log_group_add( $req ) {

			$group_name = isset( $req['name'] ) ? $req['name'] : null;

			if ( ! $group_name ) {
				return;
			}

			$context = array(
				'group_name' => $group_name,
			);

			$this->infoMessage(
				'redirection_group_added',
				$context
			);

		}

		function log_options_save( $req ) {

			$this->infoMessage( 'redirection_options_saved' );

		}

		function log_options_delete_all( $req ) {

			$this->infoMessage( 'redirection_options_removed_all' );

		}

		/**
		 * Log the deletion of a redirection.
		 *
		 * @param object $req Request.
		 * @param array  $bulk_items Array with item ids.
		 */
		protected function log_redirection_delete( $req, $bulk_items ) {
			$context = array(
				'items' => $bulk_items,
				'items_count' => count( $bulk_items ),
			);

			$message_key = 'redirection_redirection_deleted';

			$this->infoMessage(
				$message_key,
				$context
			);
		}

		/**
		 * Log enable or disable of items.
		 *
		 * @param Object $req Req.
		 * @param Array  $bulk_items Array.
		 */
		protected function log_redirection_enable_or_disable( $req, $bulk_items ) {
			$bulk_action = $req->get_param( 'bulk' );

			$message_key = 'enable' === $bulk_action ? 'redirection_redirection_enabled' : 'redirection_redirection_disabled';

			$context = array(
				'items' => $bulk_items,
				'items_count' => count( $bulk_items ),
			);

			$this->infoMessage(
				$message_key,
				$context
			);
		}

		/**
		 * Log when a Redirection is added.
		 *
		 * @param WP_REST_Request $req Request.
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

			$this->infoMessage( 'redirection_redirection_added', $context );
		}

		/**
		 * Log when a Redirection is changed.
		 *
		 * @param WP_REST_Request $req Request.
		 */
		protected function log_redirection_edit( $req ) {
			$action_data = $req->get_param( 'action_data' );

			if ( ! $action_data || ! is_array( $action_data ) ) {
				return false;
			}

			$message_key = 'redirection_redirection_edited';

			$redirection_id = $req->get_param( 'id' );

			$context = array(
				'new_source_url' => $req->get_param( 'url' ),
				'new_target_url' => $action_data['url'],
				'redirection_id' => $redirection_id,
			);

			// Get old values.
			$redirection_item = Red_Item::get_by_id( $redirection_id );

			if ( false !== $redirection_item ) {
				$context['prev_source_url'] = $redirection_item->get_url();
				$context['prev_target_url'] = $redirection_item->get_action_data();
			}

			$this->infoMessage(
				$message_key,
				$context
			);
		}

		/**
		 * Return more info about an logged redirection event.
		 *
		 * @param array $row Row with info.
		 */
		public function getLogRowDetailsOutput( $row ) {
			$context = $row->context;
			$message_key = $context['_message_key'];

			$out = '';

			if ( 'redirection_redirection_edited' === $message_key ) {
				if ( $context['new_source_url'] !== $context['prev_source_url'] ) {
					$diff_table_output = sprintf(
						'<tr>
		                    <td>%1$s</td>
		                    <td>
								<ins class="SimpleHistoryLogitem__keyValueTable__addedThing">%2$s</ins>
								<del class="SimpleHistoryLogitem__keyValueTable__removedThing">%3$s</del>
		                    </td>
		                </tr>',
						esc_html_x( 'Source URL', 'Logger: Redirection', 'simple-history' ), // 1
						esc_html( $context['new_source_url'] ), // 2
						esc_html( $context['prev_source_url'] ) // 3
					);

					$out .= '<table class="SimpleHistoryLogitem__keyValueTable">' . $diff_table_output . '</table>';
				}

				if ( $context['new_target_url'] !== $context['prev_target_url'] ) {
					$diff_table_output = sprintf(
						'<tr>
		                    <td>%1$s</td>
		                    <td>
								<ins class="SimpleHistoryLogitem__keyValueTable__addedThing">%2$s</ins>
								<del class="SimpleHistoryLogitem__keyValueTable__removedThing">%3$s</del>
		                    </td>
		                </tr>',
						esc_html_x( 'Target URL', 'Logger: Redirection', 'simple-history' ), // 1
						esc_html( $context['new_target_url'] ), // 2
						esc_html( $context['prev_target_url'] ) // 3
					);

					$out .= '<table class="SimpleHistoryLogitem__keyValueTable">' . $diff_table_output . '</table>';
				}
			}

			return $out;
		}

	} // class

} // End if().
