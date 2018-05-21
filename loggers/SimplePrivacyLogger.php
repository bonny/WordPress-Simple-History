<?php

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
class SimplePrivacyLogger extends SimpleLogger {

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

		// Add our filters only on the privacy page.
		add_action( 'load-privacy.php', array( $this, 'on_load_privacy_page' ) );

		// user_request_action_confirmed – fired when a user confirms a privacy request

		// wp_privacy_personal_data_export_file_created – fires after a personal data export file has been created

	}

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


