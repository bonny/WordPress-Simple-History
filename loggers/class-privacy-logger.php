<?php
namespace Simple_History\Loggers;

/**
 *
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
 *
 * @package SimpleHistory
 */
class Privacy_Logger extends Logger {
	/**
	 * Logger slug.
	 *
	 * @var string
	 */
	public $slug = 'SH_Privacy_Logger';

	/**
	 * Return info about logger.
	 *
	 * @return array Array with plugin info.
	 */
	public function get_info() {
		$arr_info = array(
			'name'        => _x( 'Privacy Logger', 'Logger: privacy', 'simple-history' ),
			'description' => _x( 'Log WordPress privacy related things', 'Logger: Privacy', 'simple-history' ),
			'capability'  => 'manage_options',
			'messages'    => array(
				'privacy_page_created'                  => _x( 'Created a new privacy page "{new_post_title}"', 'Logger: Privacy', 'simple-history' ),
				'privacy_page_set'                      => _x( 'Set privacy page to page "{new_post_title}"', 'Logger: Privacy', 'simple-history' ),
				'privacy_data_export_requested'         => _x( 'Requested a personal privacy data export for user "{user_email}"', 'Logger: Privacy', 'simple-history' ),
				'privacy_data_export_admin_downloaded'  => _x( 'Downloaded personal data export file for user "{user_email}"', 'Logger: Privacy', 'simple-history' ),
				'privacy_data_export_emailed'           => _x( 'Sent email with personal data export download info for user "{user_email}"', 'Logger: Privacy', 'simple-history' ),
				'privacy_data_export_request_confirmed' => _x( 'Confirmed personal data export request for "{user_email}"', 'Logger: Privacy', 'simple-history' ),
				'privacy_data_export_completed'         => _x( 'Marked personal data export request as complete for "{request_email}"', 'Logger: Privacy', 'simple-history' ),
				'privacy_data_export_removed'           => _x( 'Removed data export request for "{user_email}"', 'Logger: Privacy', 'simple-history' ),
				'data_erasure_request_added'            => _x( 'Added personal data erasure request for "{user_email}"', 'Logger: Privacy', 'simple-history' ),
				'data_erasure_request_confirmed'        => _x( 'Confirmed personal data erasure request for "{user_email}"', 'Logger: Privacy', 'simple-history' ),
				'data_erasure_request_completed'        => _x( 'Marked personal data erasure request as complete for "{user_email}"', 'Logger: Privacy', 'simple-history' ),
				'data_erasure_request_removed'          => _x( 'Removed personal data removal request for "{user_email}"', 'Logger: Privacy', 'simple-history' ),
				'data_erasure_erasure_erased'           => _x( 'Erased personal data for "{user_email}"', 'Logger: Privacy', 'simple-history' ),
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
		add_action( 'load-options-privacy.php', array( $this, 'on_load_privacy_page' ) );

		// Add filters to detect data export related functions.
		// We only add the filters when the tools page for export personal data is loaded.
		add_action( 'load-export-personal-data.php', array( $this, 'on_load_export_personal_data_page' ) );

		// Request to export or remove data is stored in posts with post type "user_request",
		// so we add a hook to catch all saves to that post.
		add_action( 'save_post_user_request', array( $this, 'on_save_post_user_request' ), 10, 3 );

		// Actions fired when user confirms an request action, like exporting their data.
		add_action( 'user_request_action_confirmed', array( $this, 'on_user_request_action_confirmed' ), 10, 1 );

		// Add filters to detect misc things that happen on export page.
		add_action( 'load-erase-personal-data.php', array( $this, 'on_load_page_remove_personal_data' ) );

		add_action( 'wp_privacy_personal_data_export_file_created', array( $this, 'on_wp_privacy_personal_data_export_file_created' ), 10, 5 );

		add_action( 'wp_privacy_personal_data_erased', array( $this, 'on_wp_privacy_personal_data_erased' ), 10, 1 );
	}

	/**
	 * Log when Personal Data is erased by admin
	 * clicking "Force erase personal data"
	 * in admin (but also mayby using link in email).
	 *
	 * @param int $request_id The privacy request post ID associated with this request.
	 */
	public function on_wp_privacy_personal_data_erased( $request_id ) {
		$user_request = wp_get_user_request( $request_id );

		if ( ! $user_request ) {
			return;
		}

		$this->info_message(
			'data_erasure_erasure_erased',
			array(
				'user_email' => $user_request->email,
				'user_id'    => $user_request->user_id,
				'request_id' => $request_id,
			)
		);
	}

	/**
	 * Log when a Data Export is emailed or downloaded.
	 *
	 * If send as email = false then export file is downloaded by admin user in admin,
	 * Probably by clicking the download link near the user name.
	 *
	 * How can a user send the export via email?
	 * "Tack för att du bekräftar din begäran om dataexport.
	 *  Webbplatsens administratör har informerats.
	 *  Du kommer få en länk för nedladdning av din dataexport
	 *  via e-post när begäran har behandlats."
	 *
	 * Visit Tools > Export Personal Data and click "Send export link".
	 *
	 * @param string $archive_pathname Path to archive.
	 * @param string $archive_url      URL to archive.
	 * @param string $html_report_pathname Path to HTML report.
	 * @param int    $request_id       The privacy request post ID associated with this request.
	 * @param string $json_report_pathname Path to JSON report.
	 */
	public function on_wp_privacy_personal_data_export_file_created( $archive_pathname, $archive_url, $html_report_pathname, $request_id, $json_report_pathname ) {
		// phpcs:ignore Squiz.PHP.CommentedOutCode.Found, Squiz.Commenting.BlockComment.NoEmptyLineBefore
		/*
		{
			"ID": 55,
			"user_id": "1",
			"email": "par.thernstrom@gmail.com",
			"action_name": "export_personal_data",
			"status": "request-confirmed",
			"created_timestamp": 1657042509,
			"modified_timestamp": 1657042509,
			"confirmed_timestamp": 0,
			"completed_timestamp": 0,
			"request_data": [],
			"confirm_key": ""
		}
		*/
		$user_request = wp_get_user_request( $request_id );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$send_as_email = isset( $_POST['sendAsEmail'] ) && $_POST['sendAsEmail'] === 'true' ? 1 : 0;

		$message_key = $send_as_email !== 0
						? 'privacy_data_export_emailed'
						: 'privacy_data_export_admin_downloaded';

		$this->info_message(
			$message_key,
			array(
				'send_as_email' => $send_as_email,
				'request_id'    => $request_id,
				'user_email'    => $user_request->email,
			)
		);
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
		$skip_posttypes[] = 'user_request';

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

		$user_request = wp_get_user_request( $request_id );
		if ( ! $user_request ) {
			return;
		}

		// User approved data export.
		if ( 'export_personal_data' === $user_request->action_name && 'request-confirmed' === $user_request->status ) {
			$this->info_message(
				'privacy_data_export_request_confirmed',
				array(
					'user_email' => $user_request->email,
				)
			);
		} elseif ( 'remove_personal_data' === $user_request->action_name && 'request-confirmed' === $user_request->status ) {
			$this->info_message(
				'data_erasure_request_confirmed',
				array(
					'user_email' => $user_request->email,
				)
			);
		}
	}

	/**
	 * Fires once a post of post type "user_request" has been saved.
	 *
	 * Used to catch data export request actions from screen at
	 * /wp-admin/export-personal-data.php
	 *
	 * @param int      $post_ID Post ID.
	 * @param \WP_Post $post    Post object.
	 * @param bool     $update  Whether this is an existing post being updated or not.
	 */
	public function on_save_post_user_request( $post_ID, $post, $update ) {
		if ( empty( $post_ID ) ) {
			return;
		}

		if ( ! is_a( $post, 'WP_Post' ) ) {
			return;
		}

		// Post id should be a of type WP_User_Request.
		$user_request = wp_get_user_request( $post_ID );

		if ( ! $user_request ) {
			return;
		}

		if ( ! $update && 'export_personal_data' === $user_request->action_name && 'request-pending' && $user_request->status ) {
			// Add Data Export Request.
			// An email will be sent to the user at this email address asking them to verify the request.
			// Notice message in admin is "Confirmation request initiated successfully.".
			$this->info_message(
				'privacy_data_export_requested',
				array(
					'user_email'              => $user_request->email,
					// phpcs:ignore WordPress.Security.NonceVerification.Missing
					'send_confirmation_email' => isset( $_POST['send_confirmation_email'] ) ? 1 : 0,
				)
			);
		} elseif ( ! $update && 'remove_personal_data' === $user_request->action_name && 'request-pending' === $user_request->status ) {
			// Send request to user to remove user data.
			$this->info_message(
				'data_erasure_request_added',
				array(
					'user_email'              => $user_request->email,
					// phpcs:ignore WordPress.Security.NonceVerification.Missing
					'send_confirmation_email' => isset( $_POST['send_confirmation_email'] ) ? 1 : 0,
				)
			);
		} elseif ( $update && 'remove_personal_data' === $user_request->action_name && 'request-completed' === $user_request->status ) {
			// Admin clicked "Complete request" in admin.
			$this->info_message(
				'data_erasure_request_completed',
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

		$user_request = wp_get_user_request( $postid );

		if ( ! $user_request ) {
			return;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Recommended
		$action = wp_unslash( $_REQUEST['action'] ?? null );

		if ( $user_request && 'delete' === $action ) {
			// Looks like "Remove request" action.
			$this->info_message(
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

		$user_request = wp_get_user_request( $postid );

		if ( ! $user_request ) {
			return;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Recommended
		$action = wp_unslash( $_REQUEST['action'] ?? null );

		if ( $user_request && 'delete' === $action ) {
			// Looks like "Remove request" action.
			$this->info_message(
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

		// When a request is marked as complete the hook "admin_action_complete" is fired.
		add_action( 'admin_action_complete', array( $this, 'on_admin_action_complete' ), 10, 1 );
	}

	/**
	 * Fired when page is like
	 * /wp-admin/export-personal-data.php?action=complete&request_id%5B0%5D=57&_wpnonce=ec34ef990
	 * i.e. a Data Export Request is marked as completed from the admin area.
	 *
	 * @return void
	 */
	public function on_admin_action_complete() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$request_ids = isset( $_REQUEST['request_id'] ) ? wp_parse_id_list( wp_unslash( $_REQUEST['request_id'] ) ) : array();

		foreach ( $request_ids as $request_id ) {
			$request = wp_get_user_request( $request_id );

			if ( false === $request ) {
				continue;
			}

			$this->info_message(
				'privacy_data_export_completed',
				array(
					'request_id'     => $request_id,
					'request_email'  => $request->email,
					'request_status' => $request->status,
				)
			);
		}
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
	 * Page is something like
	 * http://wordpress-stable.test/wp-admin/options-privacy.php
	 */
	public function on_load_privacy_page() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$action      = wp_unslash( $_POST['action'] ?? '' );
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
		$post           = get_post( $value );
		$new_post_title = '';

		if ( is_a( $post, 'WP_Post' ) ) {
			$new_post_title = $post->post_title;
		}

		$this->info_message(
			'privacy_page_created',
			array(
				'prev_post_id'   => $old_value,
				'new_post_id'    => $value,
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

		$post           = get_post( $value );
		$new_post_title = '';

		if ( is_a( $post, 'WP_Post' ) ) {
			$new_post_title = $post->post_title;
		}

		$this->info_message(
			'privacy_page_set',
			array(
				'prev_post_id'   => $old_value,
				'new_post_id'    => $value,
				'new_post_title' => $new_post_title,
			)
		);
	}
}
