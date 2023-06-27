<?php

namespace Simple_History\Loggers;

use Simple_History\Log_Initiators;

/**
 * Logger for the (old but still) very popular plugin Limit Login Attempts
 * https://sv.wordpress.org/plugins/limit-login-attempts/
 */
class Plugin_Limit_Login_Attempts_Logger extends Logger {
    public $slug = 'Plugin_LimitLoginAttempts';

    public function get_info() {

        $arr_info = array(
            'name'        => _x( 'Plugin: Limit Login Attempts Logger', 'Logger: Plugin Limit Login Attempts', 'simple-history' ),
            'description' => _x( 'Logs failed login attempts, lockouts, and configuration changes made in the plugin Limit Login Attempts', 'Logger: Plugin Limit Login Attempts', 'simple-history' ),
            'name_via'    => _x( 'Using plugin Limit Login Attempts', 'Logger: Plugin Limit Login Attempts', 'simple-history' ),
            'capability'  => 'manage_options',
            'messages'    => array(
                // 'user_locked_out' => _x( 'User locked out', "Logger: Plugin Limit Login Attempts", "simple-history" ),
                'failed_login_whitelisted' => _x( 'Failed login attempt from whitelisted IP', 'Logger: Plugin Limit Login Attempts', 'simple-history' ),
                'failed_login'             => _x( 'Was locked out because too many failed login attempts', 'Logger: Plugin Limit Login Attempts', 'simple-history' ),
                'cleared_ip_log'           => _x( 'Cleared IP log', 'Logger: Plugin Limit Login Attempts', 'simple-history' ),
                'reseted_lockout_count'    => _x( 'Reset lockout count', 'Logger: Plugin Limit Login Attempts', 'simple-history' ),
                'cleared_current_lockouts' => _x( 'Cleared current lockouts', 'Logger: Plugin Limit Login Attempts', 'simple-history' ),
                'updated_options'          => _x( 'Updated options', 'Logger: Plugin Limit Login Attempts', 'simple-history' ),
            ),
            /*
            "labels" => array(
                "search" => array(
                    "label" => _x( "Limit Login Attempts", "Logger: Plugin Limit Login Attempts", "simple-history" ),
                    "options" => array(
                        _x( "xxxPages not found", "User logger: 404", "simple-history" ) => array(
                            "page_not_found",
                        ),
                    ),
                ), // end search
            ),*/  // end labels
        );

        return $arr_info;
    }

    public function loaded() {

        require_once( ABSPATH . 'wp-admin/includes/plugin.php' );

        $pluginFilePath = 'limit-login-attempts/limit-login-attempts.php';
        $isPluginActive = is_plugin_active( $pluginFilePath );

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
     */
    public function on_load_settings_page( $a ) {

        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if ( $_POST && wp_verify_nonce( $_POST['_wpnonce'], 'limit-login-attempts-options' ) ) {
            // Settings saved
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
                    'client_type' => sanitize_text_field( $_POST['client_type'] ),
                    'allowed_retries' => sanitize_text_field( $_POST['allowed_retries'] ),
                    'lockout_duration' => sanitize_text_field( $_POST['lockout_duration'] ) * 60,
                    'valid_duration' => sanitize_text_field( $_POST['valid_duration'] ) * 3600,
                    'allowed_lockouts' => sanitize_text_field( $_POST['allowed_lockouts'] ),
                    'long_duration' => sanitize_text_field( $_POST['long_duration'] ) * 3600,
                    'email_after' => sanitize_text_field( $_POST['email_after'] ),
                    'cookies' => ( isset( $_POST['cookies'] ) && $_POST['cookies'] == '1' ) ? 'yes' : 'no',
                );

                $v = array();
                if ( isset( $_POST['lockout_notify_log'] ) ) {
                    $v[] = 'log';
                }
                if ( isset( $_POST['lockout_notify_email'] ) ) {
                    $v[] = 'email';
                }
                $lockout_notify = implode( ',', $v );
                $options['lockout_notify'] = $lockout_notify;

                $this->notice_message(
                    'updated_options',
                    array(
                        'options' => $options,
                    )
                );
            }
        }// End if().
    }

    /**
     * When option value is updated
     * do same checks as plugin itself does
     * and log if we match something
     */
    public function on_option_limit_login_lockouts_total( $value ) {

        global $limit_login_just_lockedout;

        if ( ! $limit_login_just_lockedout ) {
            return $value;
        }

        $ip = limit_login_get_address();
        $whitelisted = is_limit_login_ip_whitelisted( $ip );

        $retries = get_option( 'limit_login_retries' );
        if ( ! is_array( $retries ) ) {
            $retries = array();
        }
        if ( ! isset( $retries[ $ip ] ) ) {
            /* longer lockout */
            $lockout_type = 'longer';
            $count = limit_login_option( 'allowed_retries' ) * limit_login_option( 'allowed_lockouts' );
            $lockouts = limit_login_option( 'allowed_lockouts' );
            $time = round( limit_login_option( 'long_duration' ) / 3600 );
            // $when = sprintf( _n( '%d hour', '%d hours', $time, "Logger: Plugin Limit Login Attempts", 'limit-login-attempts' ), $time );
        } else {
            /* normal lockout */
            $lockout_type = 'normal';
            $count = $retries[ $ip ];
            $lockouts = floor( $count / limit_login_option( 'allowed_retries' ) );
            $time = round( limit_login_option( 'lockout_duration' ) / 60 );
            // $when = sprintf( _n( '%d minute', '%d minutes', $time, 'limit-login-attempts' ), $time );
        }

        if ( $whitelisted ) {
            // $subject = __( "Failed login attempts from whitelisted IP", 'limit-login-attempts' );
            $message_key = 'failed_login_whitelisted';
        } else {
            // $subject = __( "Too many failed login attempts", 'limit-login-attempts' );
            $message_key = 'failed_login';
        }

        $this->notice_message(
            $message_key,
            array(
                '_initiator' => Log_Initiators::WEB_USER,
                'value' => $value,
                'limit_login_just_lockedout' => $limit_login_just_lockedout,
                'count' => $count, // num of failed login attempts before block
                'time' => $time, // duration in minutes for block
                'lockouts' => $lockouts,
                'ip' => $ip,
                'lockout_type' => $lockout_type,
            )
        );

        return $value;
    }


    /**
     * Add some extra info
     */
    public function get_log_row_details_output( $row ) {

        $when = null;
        $output = '';

        $context = $row->context ?? array();

        $message_key = $row->context_message_key;

        if ( 'failed_login' == $message_key ) {
            $count = $context['count'];
            $lockouts = $context['lockouts'];
            $ip = $context['ip'];
            // $whitelisted = $context["whitelisted"];
            $lockout_type = $context['lockout_type'];
            $time = $context['time'];

            $output .= sprintf(
                '<p>' . _x( '%1$d failed login attempts (%2$d lockout(s)) from IP: %3$s', 'Logger: Plugin Limit Login Attempts', 'simple-history' ) . '</p>',
                $count, // 1
                $lockouts,  // 2
                $ip // 3
            );

            if ( 'longer' == $lockout_type ) {
                $when = sprintf( _nx( '%d hour', '%d hours', $time, 'Logger: Plugin Limit Login Attempts', 'simple-history' ), $time );
            } elseif ( 'normal' == $lockout_type ) {
                $when = sprintf( _nx( '%d minute', '%d minutes', $time, 'Logger: Plugin Limit Login Attempts', 'simple-history' ), $time );
            }

            $output .= '<p>' . sprintf(
                _x( 'IP was blocked for %1$s', 'Logger: Plugin Limit Login Attempts', 'simple-history' ),
                $when // 1
            ) . '</p>';
        }

        return $output;
    }
}

