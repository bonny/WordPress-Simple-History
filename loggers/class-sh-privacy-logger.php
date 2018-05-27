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
			'description' => _x( 'Log WordPress privacy related things', 'Logger: Redirection', 'simple-history' ),
			'capability' => 'manage_options',
			'messages' => array(
				'privacy_page_created' => _x( 'Created a new privacy page "{new_post_title}"', 'Logger: Redirection', 'simple-history' ),
				'privacy_page_set' => _x( 'Set privacy page to page "{new_post_title}"', 'Logger: Redirection', 'simple-history' ),
			),
		);

		return $arr_info;
	}

	/**
	 * Called when logger is loaded.
	 */
	public function loaded() {

		// Add our filters to detect create privacy page and set privacy page.
		// Add the filters only on the privacy page.
		add_action( 'load-privacy.php', array( $this, 'on_load_privacy_page' ) );

		// Log when export request is sent to user.
		// http://wp-playground.localhost/wp/wp-admin/tools.php?page=export_personal_data
		// "Confirmation request initiated successfully.
		// $content = apply_filters( 'user_request_action_email_content', $email_text, $email_data );"
		// Filter called when sending email after creating request. But also when re-sending request!

		// Delete request POST data
		/*
		page: export_personal_data
		action: delete
		request_id[0]: 784
		_wpnonce: 41672c730a
		*/

		// Download Personal Data in admin
		/*
		AJAX
		action: wp-privacy-export-personal-data
		exporter: 3
		id: 785
		page: 1
		security: 1cc184a3f8
		sendAsEmail: false
		*/


		// user_request_action_confirmed – fired when a user confirms a privacy request

		// wp_privacy_personal_data_export_file_created – fires after a personal data export file has been created

		// Export personal data.
		// Done on page
		// /wp-admin/tools.php?page=export_personal_data
		// email posted with action=add_export_personal_data_request
		// kör först $request_id = wp_create_user_request( $email_address, $action_type );
		// sen wp_send_user_request( $request_id );
		// type of action: export_personal_data, remove_personal_data
		/*
		Infogar en post när det skapas
	$request_id = wp_insert_post( array(
		'post_author'   => $user_id,
		'post_name'     => $action_name,
		'post_title'    => $email_address,
		'post_content'  => wp_json_encode( $request_data ),
		'post_status'   => 'request-pending',
		'post_type'     => 'user_request',
		'post_date'     => current_time( 'mysql', false ),
		'post_date_gmt' => current_time( 'mysql', true ),
	), true );

	eller denna
	 * Create and log a user request to perform a specific action.
 *
 * Requests are stored inside a post type named `user_request` since they can apply to both
 * users on the site, or guests without a user account.

		$request_id = wp_insert_post( array(
		'post_author'   => $user_id,
		'post_name'     => $action_name,
		'post_title'    => $email_address,
		'post_content'  => wp_json_encode( $request_data ),
		'post_status'   => 'request-pending',
		'post_type'     => 'user_request',
		'post_date'     => current_time( 'mysql', false ),
		'post_date_gmt' => current_time( 'mysql', true ),
	), true );


		*/

		// Request to export or remove data is stored in posts with post type
		// 'user_request'.
		add_action( 'save_post_user_request', array( $this, 'on_save_post_user_request' ), 10, 3 );

		// When requests are removed posts are removed.
		add_action( 'before_delete_post', array( $this, 'on_before_delete_post' ), 10, 1 );
	}

	/**
	 *
	 */
	public function on_save_post_user_request( $post_ID, $post, $update ) {
		if ( ! is_a( $post, 'WP_Post' ) ) {
			return;
		}

		/*
		Add Data Export Request
		An email will be sent to the user at this email address asking them to verify the request.
		post id will be valid WP_User_Request
		$post->post_title, // email@domain.tld
		$post->post_status, // request-pending
		$post->post_password empty, is set and causing another call to save_post, so only save one of them
		WP_User_Request Object
		(
		    [ID] => 802
		    [user_id] => 0
		    [email] => par+u@eskapism.se
		    [action_name] => export_personal_data
		    [status] => request-completed
		    [created_timestamp] => 1527107451
		    [modified_timestamp] => 1527107720
		    [confirmed_timestamp] => 0
		    [completed_timestamp] => 1527107720
		    [request_data] => Array
		        (
		        )

		    [confirm_key] => $P$BPju6ceKU..rfGNShVXneUyids3P2V/
		)

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


