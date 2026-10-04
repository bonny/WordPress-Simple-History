<?php

namespace Simple_History\Loggers;

/**
 * Support for two-factor logins in Wordfence.
 * Plugin URL: https://wordpress.org/plugins/wordfence/
 *
 * Wordfence checks the two-factor code inside `authenticate`, so `wp_login`
 * only fires once the whole login has succeeded. This logger tells the User
 * logger how it succeeded: with a passkey, a code, a remembered device, or
 * without a second factor.
 *
 * Wrong codes are logged by User_Logger::on_wp_login_failed().
 *
 * The same module ships in the closed Wordfence Login Security plugin, which
 * is reported as Wordfence too.
 *
 * @since 5.35.0
 */
class Plugin_Wordfence_Logger extends Logger {
	/** @var string Logger slug */
	public $slug = 'PluginWordfenceLogger';

	/** @var string wordpress.org slug of the plugin. */
	private const PLUGIN_SLUG = 'wordfence';

	/** @var int|null ID of the user who just logged in with a passkey in this request. */
	private $passkey_user_id = null;

	/** @var int|null ID of the user that passed `authenticate`, including Wordfence's check, in this request. */
	private $authenticated_user_id = null;

	/**
	 * Get array with information about this logger
	 *
	 * @return array
	 */
	public function get_info() {
		return array(
			'name'        => _x( 'Plugin: Wordfence Logger', 'PluginWordfenceLogger', 'simple-history' ),
			'description' => _x( 'Logs how Wordfence two-factor logins were completed', 'PluginWordfenceLogger', 'simple-history' ),
			'name_via'    => _x( 'Using plugin Wordfence', 'PluginWordfenceLogger', 'simple-history' ),
			'capability'  => 'edit_users',
			'messages'    => array(),
		);
	}

	/**
	 * Called when logger is loaded.
	 */
	public function loaded() {
		add_action( 'wordfence_ls_passkey_login_succeeded', array( $this, 'on_passkey_login_succeeded' ), 10, 1 );
		add_filter( 'authenticate', array( $this, 'on_authenticate' ), PHP_INT_MAX, 1 );
		add_filter( 'simple_history/user_logger/two_factor_login', array( $this, 'on_two_factor_login' ), 10, 2 );
	}

	/**
	 * Remember a passkey login. Wordfence fires `wp_login` right after this.
	 *
	 * @param \WP_User $user User who logged in with a passkey.
	 */
	public function on_passkey_login_succeeded( $user ) {
		if ( ! is_a( $user, 'WP_User' ) ) {
			return;
		}

		$this->passkey_user_id = $user->ID;
	}

	/**
	 * Remember who passed `authenticate`, which is where Wordfence checks the code.
	 *
	 * A login that fires `wp_login` without `authenticate`, for example from a
	 * single sign-on plugin, never went through Wordfence's check.
	 *
	 * @param \WP_User|\WP_Error|null $user Result of the authentication.
	 * @return \WP_User|\WP_Error|null Unchanged.
	 */
	public function on_authenticate( $user ) {
		if ( is_a( $user, 'WP_User' ) ) {
			$this->authenticated_user_id = $user->ID;
		}

		return $user;
	}

	/**
	 * Tell the User logger how Wordfence handled this login.
	 *
	 * Follows the order of Wordfence's own `authenticate` check: passkey,
	 * allowlisted IP, user without two-factor, remembered device, code.
	 *
	 * @param array|null $two_factor Two-factor details from another plugin.
	 * @param \WP_User   $user       User logging in.
	 * @return array|null
	 */
	public function on_two_factor_login( $two_factor, $user ) {
		if ( $two_factor !== null || ! is_a( $user, 'WP_User' ) || ! class_exists( 'WordfenceLS\Controller_Users' ) ) {
			return $two_factor;
		}

		if ( $this->passkey_user_id === $user->ID ) {
			$this->passkey_user_id = null;

			return $this->two_factor_details( true, __( 'Passkey', 'simple-history' ) );
		}

		// Wordfence did not check this login, so it cannot say anything about it.
		if ( $this->authenticated_user_id !== $user->ID ) {
			return $two_factor;
		}

		$this->authenticated_user_id = null;

		// Wordfence skips two-factor for IP addresses on its allowlist.
		if ( $this->is_ip_allowlisted() ) {
			return $this->two_factor_details( false );
		}

		if ( $this->call_wordfence( 'Controller_Users', 'has_2fa_active', $user ) !== true ) {
			return $this->two_factor_details( false );
		}

		// The device was verified with a code earlier and remembered.
		if ( $this->call_wordfence( 'Controller_Users', 'has_remembered_2fa', $user ) === true ) {
			return $this->two_factor_details( true, __( 'Remembered device', 'simple-history' ) );
		}

		// Only the format of the code is read, to tell an authenticator code from
		// a recovery code. Wordfence has already verified it.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		$code = isset( $_POST['wfls-token'] ) && is_string( $_POST['wfls-token'] ) ? trim( $_POST['wfls-token'] ) : '';

		// Without a code field the code was typed after the password, which
		// Wordfence also accepts. The method is unknown then.
		if ( $code === '' ) {
			return $this->two_factor_details( true );
		}

		if ( preg_match( '/^(?:[a-f0-9]{4}\s*){4}$/i', $code ) ) {
			return $this->two_factor_details( true, __( 'Recovery code', 'simple-history' ) );
		}

		return $this->two_factor_details( true, __( 'Authenticator app', 'simple-history' ) );
	}

	/**
	 * Build the details array for the `two_factor_login` filter.
	 *
	 * @param bool   $used   Whether the login used a second factor.
	 * @param string $method Label of the method used.
	 * @return array
	 */
	private function two_factor_details( $used, $method = '' ) {
		return array(
			'plugin' => self::PLUGIN_SLUG,
			'used'   => $used,
			'method' => $method,
		);
	}

	/**
	 * Check if the current request's IP address is on Wordfence's allowlist.
	 *
	 * @return bool
	 */
	private function is_ip_allowlisted() {
		$current_request = array( 'WordfenceLS\Model_Request', 'current' );

		if ( ! is_callable( $current_request ) ) {
			return false;
		}

		$request = call_user_func( $current_request );

		if ( ! is_object( $request ) || ! is_callable( array( $request, 'ip' ) ) ) {
			return false;
		}

		return $this->call_wordfence( 'Controller_Whitelist', 'is_whitelisted', $request->ip() ) === true;
	}

	/**
	 * Call a method on one of Wordfence's shared controllers.
	 *
	 * Every call checks that the class and method exist, so a Wordfence
	 * update that renames them leaves the login logged without details
	 * instead of breaking it.
	 *
	 * @param string $controller Class name in the WordfenceLS namespace.
	 * @param string $method     Method to call.
	 * @param mixed  ...$args    Arguments.
	 * @return mixed|null Return value, or null when the method is not available.
	 */
	private function call_wordfence( $controller, $method, ...$args ) {
		$shared = array( 'WordfenceLS\\' . $controller, 'shared' );

		if ( ! is_callable( $shared ) ) {
			return null;
		}

		$instance = call_user_func( $shared );

		if ( ! is_object( $instance ) || ! is_callable( array( $instance, $method ) ) ) {
			return null;
		}

		return call_user_func_array( array( $instance, $method ), $args );
	}
}
