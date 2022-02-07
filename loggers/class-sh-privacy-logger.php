<?php

/**
 * Logger for privacy/GDPR related things.
 *
 * @package SimpleHistory
 */

defined( 'ABSPATH' ) || die();

/**
 * Logger for WordPress privacy/GDPR related things
 *
 * List of interesting filters:
 * https://developer.wordpress.org/plugins/privacy/privacy-related-options-hooks-and-capabilities/
 *
 * Possible enhancements:
 * - Log when status changes to "Retry".
 *   Something has status "request-failed".
 *   Set in _wp_personal_data_cleanup_requests()
 * - Log when _wp_privacy_resend_request() is called
 */
class SH_Privacy_Logger extends SimpleLogger {


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
			'name'        => _x( 'Privacy Logger', 'Logger: privacy', 'simple-history' ),
			'description' => _x( 'Log WordPress privacy related things', 'Logger: Privacy', 'simple-history' ),
			'capability'  => 'manage_options',
			'messages'    => array(
				'privacy_page_created'                  => _x( 'Created a new privacy page "{new_post_title}"', 'Logger: Privacy', 'simple-history' ),
				'privacy_page_set'                      => _x( 'Set privacy page to page "{new_post_title}"', 'Logger: Privacy', 'simple-history' ),
				'privacy_data_export_requested'         => _x( 'Requested a privacy data export for user "{user_email}"', 'Logger: Privacy', 'simple-history' ),
				'privacy_data_export_admin_downloaded'  => _x( 'Downloaded personal data export file for user "{user_email}"', 'Logger: Privacy', 'simple-history' ),
				'privacy_data_export_emailed'           => _x( 'Sent email with personal data export download info for user "{user_email}"', 'Logger: Privacy', 'simple-history' ),
				'privacy_data_export_request_confirmed' => _x( 'Confirmed data export request for "{user_email}"', 'Logger: Privacy', 'simple-history' ),
				'privacy_data_export_removed'           => _x( 'Removed data export request for "{user_email}"', 'Logger: Privacy', 'simple-history' ),
				'data_erasure_request_sent'             => _x( 'Sent data erasure request for "{user_email}"', 'Logger: Privacy', 'simple-history' ),
				'data_erasure_request_confirmed'        => _x( 'Confirmed data erasure request for "{user_email}"', 'Logger: Privacy', 'simple-history' ),
				'data_erasure_request_handled'          => _x( 'Erased personal data for "{user_email}"', 'Logger: Privacy', 'simple-history' ),
				'data_erasure_request_removed'          => _x( 'Removed personal data removal request for "{user_email}"', 'Logger: Privacy', 'simple-history' ),
			),
		);

		return $arr_info;
	}

	/**
	 * Called when logger is loaded.
	 */
	public function loaded() {
		// Exclude the post types used by the data export from the regular post logger updates.
		add_filter( 'simple_history/post_logger/skip_posttypes', array( $this, 'remove_post_types_from_postlogger' ) );

		// Add filters to detect when a privacy page is created and when a privacy page is set..
		// We only add the filters when the privacy page is loaded.
		add_action( 'load-privacy.php', array( $this, 'on_load_privacy_page' ) );

		// Add filters to detect data export related functions.
		// We only add the filters when the tools page for export personal data is loaded.
		add_action( 'load-tools_page_export_personal_data', array( $this, 'on_load_export_personal_data_page' ) );

		// Request to export or remove data is stored in posts with post type "user_request",
		// so we add a hook to catch all saves to that post.
		add_action( 'save_post_user_request', array( $this, 'on_save_post_user_request' ), 10, 3 );

		// Actions fired when user confirms an request action, like exporting their data.
		add_action( 'user_request_action_confirmed', array( $this, 'on_user_request_action_confirmed' ), 10, 1 );

		// Add filters to detect misc things that happen on export page.
		add_action( 'load-tools_page_remove_personal_data', array( $this, 'on_load_page_remove_personal_data' ) );
	}

	/*
	Erase Personal Data
	- send request
	- approve request
	-
	*/

	/**
	 * Remove privacy post types from the post types that the usual postlogger logs.
	 *
	 * @param array $skip_posttypes Posttypes to skip.
	 */
	public function remove_post_types_from_postlogger( $skip_posttypes ) {
		array_push(
			$skip_posttypes,
			'user_request'
		);

		return $skip_posttypes;
	}

	/**
	 * User gets email and clicks in the confirm data export link.
	 * Ends up on page that looks kinda like the login page and with message
	 * "Tack för att du bekräftar din begäran om dataexport."
	 * "Webbplatsens administratör har informerats. Du kommer via e-post att få en länk för nedladdning av din dataexport när begäran har behandlats."
	 * URL is like
	 * http://wp-playground.localhost/wp/wp-login.php?action=confirmaction&request_id=806confirm_key=kOQEq4xkEI2DJdNZ
	 *
	 * Http://wp-playground.localhost/wp/wp-login.php?action=confirmaction&request_id=807&confirm_key=DXdqHbidLZGLB9uW0rTo
	 * Tack för att du bekräftar din begäran om att ta bort data.
	 * Webbplatsens administratör har informerats. Du kommer via e-post att få en notifiering när dina uppgifter har raderats.
	 *
	 * Fired when user confirms data export or data erasure.
	 *
	 * @param int $request_id Request ID.
	 */
	public function on_user_request_action_confirmed( $request_id ) {
		// Load user.php because we use functions from there.
		if ( ! function_exists( 'get_editable_roles' ) ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
		}

		// Check that new function since 4.9.6 exist before trying to use functions.
		if ( ! function_exists( 'wp_get_user_request_data' ) ) {
			return;
		}

		$user_request = wp_get_user_request_data( $request_id );
		if ( ! $user_request ) {
			return;
		}

		// User approved data export.
		if ( 'export_personal_data' === $user_request->action_name && 'request-confirmed' === $user_request->status ) {
			$this->infoMessage(
				'privacy_data_export_request_confirmed',
				array(
					'user_email' => $user_request->email,
				)
			);
		} elseif ( 'remove_personal_data' === $user_request->action_name && 'request-confirmed' === $user_request->status ) {
			$this->infoMessage(
				'data_erasure_request_confirmed',
				array(
					'user_email' => $user_request->email,
				)
			);
		}
	}

	/**
	 * Fires once a post of post type "user_request" has been saved.
	 * Used to catch data export request actions.
	 *
	 * @param int     $post_ID Post ID.
	 * @param WP_Post $post    Post object.
	 * @param bool    $update  Whether this is an existing post being updated or not.
	 */
	public function on_save_post_user_request( $post_ID, $post, $update ) {
		if ( empty( $post_ID ) ) {
			return;
		}

		if ( ! is_a( $post, 'WP_Post' ) ) {
			return;
		}

		// Check that new function since 4.9.6 exist before trying to use functions.
		if ( ! function_exists( 'wp_get_user_request_data' ) ) {
			return;
		}

		// Post id should be a of type WP_User_Request.
		$user_request = wp_get_user_request_data( $post_ID );

		if ( ! $user_request ) {
			return;
		}

		$is_doing_ajax = defined( 'DOING_AJAX' ) && DOING_AJAX;

		if ( ! $update && 'export_personal_data' === $user_request->action_name && 'request-pending' && $user_request->status ) {
			// Add Data Export Request.
			// An email will be sent to the user at this email address asking them to verify the request.
			// Notice message in admin is "Confirmation request initiated successfully.".
			$this->infoMessage(
				'privacy_data_export_requested',
				array(
					'user_email' => $user_request->email,
				)
			);
		} elseif ( $update && is_user_logged_in() && $is_doing_ajax && 'export_personal_data' === $user_request->action_name && 'request-completed' && $user_request->status ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$send_as_email = isset( $_POST['sendAsEmail'] ) ? 'true' === $_POST['sendAsEmail'] : false;

			if ( $send_as_email ) {
				// If send as email = true then email a link with the export to a user.
				$this->infoMessage(
					'privacy_data_export_emailed',
					array(
						'user_email' => $user_request->email,
					)
				);
			} elseif ( ! $send_as_email ) {
				// If send as email = false then export file is downloaded by admin user in admin.
				// Probably by clicking the download link near the user name.
				$this->infoMessage(
					'privacy_data_export_admin_downloaded',
					array(
						'user_email' => $user_request->email,
					)
				);
			}
		} elseif ( ! $update && 'remove_personal_data' === $user_request->action_name && 'request-pending' === $user_request->status ) {
			// Send request to user to remove user data.
			$this->infoMessage(
				'data_erasure_request_sent',
				array(
					'user_email' => $user_request->email,
				)
			);
		} elseif ( $update && 'remove_personal_data' === $user_request->action_name && 'request-completed' === $user_request->status ) {
			// Admin clicked "Erase user data" in admin, after user has approved remove request.
			$this->infoMessage(
				'data_erasure_request_handled',
				array(
					'user_email' => $user_request->email,
				)
			);
		}
	}

	/**
	 * Fires before a post is deleted, at the start of wp_delete_post().
	 * We use this to detect if a post with post type 'user_request' is going to be deleted,
	 * meaning that a user request is removed.
	 *
	 * @param int $postid Post ID.
	 */
	public function on_before_delete_post_on_remove_personal_data_page( $postid ) {
		if ( empty( $postid ) ) {
			return;
		}

		$post = get_post( $postid );

		if ( ! is_a( $post, 'WP_Post' ) ) {
			return;
		}

		if ( get_post_type( $post ) !== 'user_request' ) {
			return;
		}

		if ( ! function_exists( 'wp_get_user_request_data' ) ) {
			return;
		}

		$user_request = wp_get_user_request_data( $postid );

		if ( ! $user_request ) {
			return;
		}

		$action = isset( $_REQUEST['action'] ) ? $_REQUEST['action'] : null;

		if ( $user_request && 'delete' === $action ) {
			// Looks like "Remove request" action.
			$this->infoMessage(
				'data_erasure_request_removed',
				array(
					'user_email' => $user_request->email,
				)
			);
		}
	}

	/**
	 * Fires before a post is deleted, at the start of wp_delete_post().
	 * We use this to detect if a post with post type 'user_request' is going to be deleted,
	 * meaning that a user request is removed.
	 *
	 * @param int $postid Post ID.
	 */
	public function on_before_delete_post_on_personal_data_page( $postid ) {
		if ( empty( $postid ) ) {
			return;
		}

		$post = get_post( $postid );

		if ( ! is_a( $post, 'WP_Post' ) ) {
			return;
		}

		if ( get_post_type( $post ) !== 'user_request' ) {
			return;
		}

		if ( ! function_exists( 'wp_get_user_request_data' ) ) {
			return;
		}

		$user_request = wp_get_user_request_data( $postid );

		if ( ! $user_request ) {
			return;
		}

		$action = isset( $_REQUEST['action'] ) ? $_REQUEST['action'] : null;

		if ( $user_request && 'delete' === $action ) {
			// Looks like "Remove request" action.
			$this->infoMessage(
				'privacy_data_export_removed',
				array(
					'user_email' => $user_request->email,
				)
			);
		}
	}

	/**
	 * Fired when the tools page for export data is loaded.
	 */
	public function on_load_export_personal_data_page() {
		// When requests are removed posts are removed.
		add_action( 'before_delete_post', array( $this, 'on_before_delete_post_on_personal_data_page' ), 10, 1 );
	}

	/**
	 * Fired when the tools page for user data removal is loaded.
	 */
	public function on_load_page_remove_personal_data() {
		// When requests are removed posts are removed.
		add_action( 'before_delete_post', array( $this, 'on_before_delete_post_on_remove_personal_data_page' ), 10, 1 );
	}

	/**
	 * Fired when the privacy admin page is loaded.
	 */
	public function on_load_privacy_page() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$action = isset( $_POST['action'] ) ? $_POST['action'] : '';
		$option_name = 'wp_page_for_privacy_policy';

		if ( 'create-privacy-page' === $action ) {
			add_action( "update_option_{$option_name}", array( $this, 'on_update_option_create_privacy_page' ), 10, 3 );
		} elseif ( 'set-privacy-page' === $action ) {
			add_action( "update_option_{$option_name}", array( $this, 'on_update_option_set_privacy_page' ), 10, 3 );
		}
	}

	/**
	 * Fires after the value of a specific option has been successfully updated.
	 *
	 * @param mixed  $old_value The old option value.
	 * @param mixed  $value     The new option value.
	 * @param string $option    Option name.
	 */
	public function on_update_option_create_privacy_page( $old_value, $value, $option ) {
		$post = get_post( $value );

		if ( is_a( $post, 'WP_Post' ) ) {
			$new_post_title = $post->post_title;
		}

		$this->infoMessage(
			'privacy_page_created',
			array(
				'prev_post_id' => $old_value,
				'new_post_id' => $value,
				'new_post_title' => $new_post_title,
			)
		);
	}

	/**
	 * Fires after the value of a specific option has been successfully updated.
	 *
	 * @param mixed  $old_value The old option value.
	 * @param mixed  $value     The new option value.
	 * @param string $option    Option name.
	 */
	public function on_update_option_set_privacy_page( $old_value, $value, $option ) {

		$post = get_post( $value );

		if ( is_a( $post, 'WP_Post' ) ) {
			$new_post_title = $post->post_title;
		}

		$this->infoMessage(
			'privacy_page_set',
			array(
				'prev_post_id' => $old_value,
				'new_post_id' => $value,
				'new_post_title' => $new_post_title,
			)
		);
	}
}
