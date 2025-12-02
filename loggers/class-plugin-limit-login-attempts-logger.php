<?php

namespace Simple_History\Loggers;

use Simple_History\Helpers;
use Simple_History\Log_Initiators;

/**
 * Logger for the (old but still) very popular plugin Limit Login Attempts
 * https://sv.wordpress.org/plugins/limit-login-attempts/
 */
class Plugin_Limit_Login_Attempts_Logger extends Logger {
	/** @var string Logger slug */
	public $slug = 'Plugin_LimitLoginAttempts';

	/**
	 * @inheritdoc
	 */
	public function get_info() {

		$arr_info = array(
			'name'        => _x( 'Plugin: Limit Login Attempts Logger', 'Logger: Plugin Limit Login Attempts', 'simple-history' ),
			'description' => _x( 'Logs failed login attempts, lockouts, and configuration changes made in the plugin Limit Login Attempts', 'Logger: Plugin Limit Login Attempts', 'simple-history' ),
			'name_via'    => _x( 'Using plugin Limit Login Attempts', 'Logger: Plugin Limit Login Attempts', 'simple-history' ),
			'capability'  => 'manage_options',
			'messages'    => array(
				'failed_login_whitelisted' => _x( 'Failed login attempt from whitelisted IP', 'Logger: Plugin Limit Login Attempts', 'simple-history' ),
				'failed_login'             => _x( 'Was locked out because too many failed login attempts', 'Logger: Plugin Limit Login Attempts', 'simple-history' ),
				'cleared_ip_log'           => _x( 'Cleared IP log', 'Logger: Plugin Limit Login Attempts', 'simple-history' ),
				'reseted_lockout_count'    => _x( 'Reset lockout count', 'Logger: Plugin Limit Login Attempts', 'simple-history' ),
				'cleared_current_lockouts' => _x( 'Cleared current lockouts', 'Logger: Plugin Limit Login Attempts', 'simple-history' ),
				'updated_options'          => _x( 'Updated options', 'Logger: Plugin Limit Login Attempts', 'simple-history' ),
			),
		);

		return $arr_info;
	}

	/**
	 * @inheritdoc
	 */
	public function loaded() {
		$pluginFilePath = 'limit-login-attempts/limit-login-attempts.php';
		$isPluginActive = Helpers::is_plugin_active( $pluginFilePath );

		// Only continue to add filters if plugin is active.
		// This minimise the risk of plugin errors, because plugin
		// has been forked to new versions.
		if ( ! $isPluginActive ) {
			return;
		}

		add_filter( 'pre_option_limit_login_lockouts_total', array( $this, 'on_option_limit_login_lockouts_total' ), 10, 1 );
		add_action( 'load-settings_page_limit-login-attempts', array( $this, 'on_load_settings_page' ), 10, 1 );
	}

	/**
	 * Fired when plugin options screen is loaded
	 *
	 * @param string $a Hook name.
	 */
	public function on_load_settings_page( $a ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		if ( $_POST && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'limit-login-attempts-options' ) ) {
			// Settings saved..
			if ( isset( $_POST['clear_log'] ) ) {
				$this->notice_message( 'cleared_ip_log' );
			}

			if ( isset( $_POST['reset_total'] ) ) {
				$this->notice_message( 'reseted_lockout_count' );
			}

			if ( isset( $_POST['reset_current'] ) ) {
				$this->notice_message( 'cleared_current_lockouts' );
			}

			if ( isset( $_POST['update_options'] ) ) {
				$options = array(
					// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotValidated
					'client_type'      => sanitize_text_field( wp_unslash( $_POST['client_type'] ) ),
					'allowed_retries'  => sanitize_text_field( wp_unslash( $_POST['allowed_retries'] ) ),
					'lockout_duration' => sanitize_text_field( wp_unslash( $_POST['lockout_duration'] ) ) * 60, // @phpstan-ignore-line
					'valid_duration'   => sanitize_text_field( wp_unslash( $_POST['valid_duration'] ) ) * 3600, // @phpstan-ignore-line
					'allowed_lockouts' => sanitize_text_field( wp_unslash( $_POST['allowed_lockouts'] ) ),
					'long_duration'    => sanitize_text_field( wp_unslash( $_POST['long_duration'] ) ) * 3600, // @phpstan-ignore-line
					'email_after'      => sanitize_text_field( wp_unslash( $_POST['email_after'] ) ),
					'cookies'          => ( isset( $_POST['cookies'] ) && sanitize_text_field( wp_unslash( $_POST['cookies'] ) ) === '1' ) ? 'yes' : 'no',
					// phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotValidated
				);

				$v = array();
				if ( isset( $_POST['lockout_notify_log'] ) ) {
					$v[] = 'log';
				}
				if ( isset( $_POST['lockout_notify_email'] ) ) {
					$v[] = 'email';
				}
				$lockout_notify            = implode( ',', $v );
				$options['lockout_notify'] = $lockout_notify;

				$this->notice_message(
					'updated_options',
					array(
						'options' => $options,
					)
				);
			}
		}
	}

	/**
	 * When option value is updated
	 * do same checks as plugin itself does
	 * and log if we match something
	 *
	 * @param mixed $value Option value.
	 */
	public function on_option_limit_login_lockouts_total( $value ) {

		global $limit_login_just_lockedout;

		if ( ! $limit_login_just_lockedout ) {
			return $value;
		}

		$ip          = limit_login_get_address();
		$whitelisted = is_limit_login_ip_whitelisted( $ip );

		$retries = get_option( 'limit_login_retries' );
		if ( ! is_array( $retries ) ) {
			$retries = array();
		}
		if ( ! isset( $retries[ $ip ] ) ) {
			/* longer lockout */
			$lockout_type = 'longer';
			$count        = limit_login_option( 'allowed_retries' ) * limit_login_option( 'allowed_lockouts' );
			$lockouts     = limit_login_option( 'allowed_lockouts' );
			$time         = round( limit_login_option( 'long_duration' ) / 3600 );
		} else {
			/* normal lockout */
			$lockout_type = 'normal';
			$count        = $retries[ $ip ];
			$lockouts     = floor( $count / limit_login_option( 'allowed_retries' ) );
			$time         = round( limit_login_option( 'lockout_duration' ) / 60 );
		}

		$message_key = $whitelisted ? 'failed_login_whitelisted' : 'failed_login';

		$this->notice_message(
			$message_key,
			array(
				'_initiator'                 => Log_Initiators::WEB_USER,
				'value'                      => $value,
				'limit_login_just_lockedout' => $limit_login_just_lockedout,
				'count'                      => $count, // num of failed login attempts before block.
				'time'                       => $time, // duration in minutes for block.
				'lockouts'                   => $lockouts,
				'ip'                         => $ip,
				'lockout_type'               => $lockout_type,
			)
		);

		return $value;
	}

	/**
	 * Add some extra info
	 *
	 * @param object $row Log row.
	 */
	public function get_log_row_details_output( $row ) {

		$when   = null;
		$output = '';

		$context = $row->context ?? array();

		$message_key = $row->context_message_key;

		if ( 'failed_login' === $message_key ) {
			$count        = $context['count'];
			$lockouts     = $context['lockouts'];
			$ip           = $context['ip'];
			$lockout_type = $context['lockout_type'];
			$time         = $context['time'];

			$message_string = sprintf(
				/* translators: 1: number of login attempts, 2: number of lockouts, 3: IP that caused lockout. */
				_x( '%1$d failed login attempts (%2$d lockout(s)) from IP: %3$s', 'Logger: Plugin Limit Login Attempts', 'simple-history' ),
				$count,
				$lockouts,
				$ip
			);

			$output .= '<p>' . $message_string . '</p>';

			if ( 'longer' === $lockout_type ) {
				$when = sprintf(
					/* translators: %d number of hours. */
					_nx( '%d hour', '%d hours', $time, 'Logger: Plugin Limit Login Attempts', 'simple-history' ),
					$time
				);
			} elseif ( 'normal' === $lockout_type ) {
				$when = sprintf(
					/* translators: %d number of minutes. */
					_nx( '%d minute', '%d minutes', $time, 'Logger: Plugin Limit Login Attempts', 'simple-history' ),
					$time
				);
			}

			$output .= '<p>' . sprintf(
				/* translators: %s time the IP was blocked, e.g. 2 hours. */
				_x( 'IP was blocked for %s', 'Logger: Plugin Limit Login Attempts', 'simple-history' ),
				$when
			) . '</p>';
		}

		return $output;
	}
}
