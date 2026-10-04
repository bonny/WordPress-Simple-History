<?php

namespace Simple_History\Loggers;

/**
 * Support for the Two Factor plugin.
 * Plugin URL: https://wordpress.org/plugins/two-factor/
 *
 * Two Factor lets core's `wp_login` fire when the password is correct, then
 * destroys the session and shows its challenge. This logger tells the User
 * logger to wait, and hands it the login once the second factor passes, so
 * the login stays a regular `user_logged_in` event.
 *
 * Wrong codes are logged by User_Logger::on_wp_login_failed(), which also
 * covers other two-factor plugins.
 *
 * @since 5.35.0
 */
class Plugin_Two_Factor_Logger extends Logger {
	/** @var string Logger slug */
	public $slug = 'PluginTwoFactorLogger';

	/** @var string wordpress.org slug of the plugin. */
	private const PLUGIN_SLUG = 'two-factor';

	/**
	 * Get array with information about this logger
	 *
	 * @return array
	 */
	public function get_info() {
		return array(
			'name'        => _x( 'Plugin: Two Factor Logger', 'PluginTwoFactorLogger', 'simple-history' ),
			'description' => _x( 'Logs logins when the two-factor code is accepted', 'PluginTwoFactorLogger', 'simple-history' ),
			'name_via'    => _x( 'Using plugin Two Factor', 'PluginTwoFactorLogger', 'simple-history' ),
			'capability'  => 'edit_users',
			'messages'    => array(),
		);
	}

	/**
	 * Called when logger is loaded.
	 */
	public function loaded() {
		add_filter( 'simple_history/user_logger/two_factor_plugin', array( $this, 'on_two_factor_plugin' ) );
		add_filter( 'simple_history/user_logger/login_pending_second_factor', array( $this, 'on_login_pending_second_factor' ), 10, 2 );
		add_action( 'two_factor_user_authenticated', array( $this, 'on_two_factor_user_authenticated' ), 10, 2 );
	}

	/**
	 * Report Two Factor as the site's two-factor plugin.
	 *
	 * @param string $plugin wordpress.org slug of the two-factor plugin.
	 * @return string
	 */
	public function on_two_factor_plugin( $plugin ) {
		if ( ! class_exists( 'Two_Factor_Core' ) ) {
			return $plugin;
		}

		return self::PLUGIN_SLUG;
	}

	/**
	 * Tell the User logger to wait when Two Factor will challenge this login.
	 *
	 * Uses the same check as Two Factor's own `wp_login` handler, so we skip
	 * exactly the logins that Two Factor intercepts.
	 *
	 * @param bool     $is_pending Whether the login waits for a second factor.
	 * @param \WP_User $user       User whose password was just accepted.
	 * @return bool
	 */
	public function on_login_pending_second_factor( $is_pending, $user ) {
		$is_using_two_factor = array( 'Two_Factor_Core', 'is_user_using_two_factor' );

		if ( $is_pending || ! is_a( $user, 'WP_User' ) || ! is_callable( $is_using_two_factor ) ) {
			return $is_pending;
		}

		return (bool) call_user_func( $is_using_two_factor, $user->ID );
	}

	/**
	 * Log the login when the second factor passes.
	 *
	 * @param \WP_User $user     The authenticated user.
	 * @param object   $provider The Two_Factor_Provider used.
	 */
	public function on_two_factor_user_authenticated( $user, $provider = null ) {
		$user_logger = $this->simple_history->get_instantiated_logger_by_slug( 'SimpleUserLogger' );

		if ( ! $user_logger instanceof User_Logger ) {
			return;
		}

		$method_label = is_object( $provider ) && method_exists( $provider, 'get_label' ) ? $provider->get_label() : '';

		$user_logger->log_two_factor_login( $user, self::PLUGIN_SLUG, $method_label );
	}
}
