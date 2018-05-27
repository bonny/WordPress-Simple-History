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
 */

/**
 * Class to log things from the Redirection plugin.
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
			'name' => 'Privacy',
			'description' => _x( 'Log WordPress privacy related things', 'Logger: Privacy', 'simple-history' ),
			'capability' => 'manage_options',
			'messages' => array(
				'privacy_page_created' => _x( 'Created a new privacy page "{new_post_title}"', 'Logger: Privacy', 'simple-history' ),
				'privacy_page_set' => _x( 'Set privacy page to page "{new_post_title}"', 'Logger: Privacy', 'simple-history' ),
				'privacy_data_export_requested' => _x( 'Requested a privacy data export for user "{user_email}"', 'Logger: Privacy', 'simple-history' ),
			),
		);

		return $arr_info;
	}

	/**
	 * Called when logger is loaded.
	 */
	public function loaded() {

		// Check that new function since 4.9.6 exist before trying to use this logger.
		if ( ! function_exists( 'wp_get_user_request_data' ) ) {
			return;
		}

		// Exclude the post types used by the data export from the regular post logger updates.
		add_filter( 'simple_history/post_logger/skip_posttypes', array( $this, 'remove_post_types_from_postlogger' ) );

		// Add filters to detect when a privacy page is created and when a privacy page is set..
		// We only Aadd the filters when the privacy page is loaded.
		add_action( 'load-privacy.php', array( $this, 'on_load_privacy_page' ) );

		// Add filters to detect data export related functions.
		// We only add the filters when the tools page for export personal data is loaded.
		add_action( 'load-tools_page_export_personal_data', array( $this, 'on_load_export_personal_data_page' ) );
	}

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

		if ( $update ) {
			return;
		}

		// Post id should be a of type WP_User_Request.
		$user_request = wp_get_user_request_data( $post_ID );

		if ( ! $user_request ) {
			return;
		}

		// Add Data Export Request.
		// An email will be sent to the user at this email address asking them to verify the request.
		// Notice message in admin is "Confirmation request initiated successfully.".
		if ( 'export_personal_data' === $user_request->action_name ) {
			$this->infoMessage(
				'privacy_data_export_requested',
				array(
					'user_email' => $user_request->email,
				)
			);
		}

		/*
		post id will be valid WP_User_Request
		$post->post_title, // email@domain.tld
		$post->post_status, // request-pending
		$post->post_password empty, is set and causing another call to save_post, so only save one of them

		Download Personal Data i admin som inloggad
		[email] => par+q@earthpeople.se
	    [action_name] => export_personal_data
	    [status] => request-completed
	    $_POST[sendAsEmail] = false

		Klickar ladda-hem-länk i mail
		http://wp-playground.localhost/wp/wp-login.php?action=confirmaction&request_id=798&confirm_key=w7yQEx41bbqzabWnZz4S
		Kommer till typ wp-login med meddelande "Tack för att du bekräftar din begäran om dataexport."
		logga att "User approved Data Export Request"
		[email] => par+z@earthpeople.se
		[action_name] => export_personal_data
		[status] => request-confirmed

		Klickar på "Remove request" i admin
	 	[email] => par+q@earthpeople.se
	    [action_name] => export_personal_data
	    [status] => request-completed
	    $_POST[action] = delete

	    Klickar på "email data" i admin som inloggad
	    mail skickas till användare med länk till uploads-mappen
		[email] => par+r@eskapism.se
		[action_name] => export_personal_data
		[status] => request-completed
		$_POST[sendAsEmail] = true

		*/

		sh_error_log(
			'---',
			'on_save_post_user_request',
			// $post->post_type, user_request
			#$post->post_name, // export_personal_data
			#$post->post_content,
			#$post->post_title, // email@domain.tld
			#$post->post_status, // request-pending
			#$post->post_password,
			wp_get_user_request_data( $post->ID ),
			$_GET,
			$_POST,
			$_SERVER['SCRIPT_FILENAME']
		);
	}

	public function on_before_delete_post( $postid ) {
		$post = get_post( $postid );

		if ( ! is_a( $post, 'WP_Post' ) ) {
			return;
		}

		sh_error_log(
			'---',
			'on_before_delete_post',
			// $post->post_type, user_request
			$post->post_content,
			$post->post_title,
			$post->post_status,
			$post->post_password,
			wp_get_user_request_data( $post->ID ),
			$_GET,
			$_POST,
			$_SERVER['SCRIPT_FILENAME']
		);
	}

	/**
	 * Fired when the tools page for export data is loaded.
	 */
	public function on_load_export_personal_data_page() {
		// Request to export or remove data is stored in posts with post type "user_request",
		// so we add a hook to catch all saves to that post
		add_action( 'save_post_user_request', array( $this, 'on_save_post_user_request' ), 10, 3 );

		// When requests are removed posts are removed.
		add_action( 'before_delete_post', array( $this, 'on_before_delete_post' ), 10, 1 );

	}

	/**
	 * Fired when the privacy admin page is loaded.
	 */
	public function on_load_privacy_page() {
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

} // class


