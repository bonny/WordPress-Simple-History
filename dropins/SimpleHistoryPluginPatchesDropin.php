<?php
defined('ABSPATH') or die();

/*
Dropin Name: Plugin Patches
Dropin Description: Used to patch plugins that behave wierd
Dropin URI: http://simple-history.com/
Author: Pär Thernström
*/

class SimpleHistoryPluginPatchesDropin
{

    private $sh;

    function __construct($sh)
    {

        $this->sh = $sh;

        $this->patch_captcha_on_login();

        add_filter(
            'simple_history/post_logger/skip_posttypes',
            array( $this, 'woocommerce_skip_scheduled_actions_posttype' )
        );
    }

    /**
     * Skip logging of WooCommerce scheduled actions/cron related things,
     * stored in the scheduled-action"post type. If not disabled the log can be filled with
     * a large amount of actions for this postype.
     *
     * @since 2.3
     */
    public function woocommerce_skip_scheduled_actions_posttype($skip_posttypes)
    {
        $skip_posttypes[] = 'scheduled-action';
        return $skip_posttypes;
    }

    /**
     * Captcha on Login
     *
     * Calls wp_logut() wrongly when
     *  - a user IP is blocked
     *  - when max num of tries is reached
     *  - or when the capcha is not correct
     *
     * So the event logged will be logged_out but should be user_login_failed or user_unknown_login_failed.
     * Wrong events logged reported here:
     * https://wordpress.org/support/topic/many-unknown-logged-out-entries
     *
     * Plugin also gives lots of errors, reported by me here:
     * https://wordpress.org/support/topic/errors-has_cap-deprecated-strict-standards-warning
     */
    function patch_captcha_on_login()
    {

        add_action('simple_history/log/do_log', array( $this, 'patch_captcha_on_login_on_log' ), 10, 5);
    }

    // Detect that this log message is being called from Captha on login
    // and that the message is "user_logged_out"
    function patch_captcha_on_login_on_log($doLog, $level = null, $message = null, $context = null, $loggerInstance = null)
    {

        if (empty($context) || ! isset($context['_message_key']) || 'user_logged_out' != $context['_message_key']) {
            // Message key did not exist or was not "user_logged_out"
            return $doLog;
        }

        // 22 nov 2015: disabled this check beacuse for example robots/scripts don't pass all args
        // instead they only post "log" and "pwd"
        // codiga is the input with the captcha
        /*
        if ( ! isset( $_POST["log"], $_POST["pwd"], $_POST["wp-submit"], $_POST["codigo"] ) ) {
            // All needed post variables was not set
            return $doLog;
        }
        */

        // The Captcha on login uses a class called 'Anderson_Makiyama_Captcha_On_Login'
        // and also a global variable called $global $anderson_makiyama
        global $anderson_makiyama;
        if (! class_exists('Anderson_Makiyama_Captcha_On_Login') || ! isset($anderson_makiyama)) {
            return $doLog;
        }

        // We must come from wp-login
        // Disabled 22 nov 2015 because robots/scripts dont send referer
        /*
        $wp_referer = wp_get_referer();
        if ( ! $wp_referer || ! "wp-login.php" == basename( $wp_referer ) ) {
            return $doLog;
        }
        */

        if (! isset($_SERVER['REQUEST_URI'])) {
            return $doLog;
        }

        // File must be wp-login.php (can it even be another?)
        $request_uri = basename(wp_unslash($_SERVER['REQUEST_URI']));
        if ('wp-login.php' !== $request_uri) {
            return $doLog;
        }

        $anderson_makiyama_indice = Anderson_Makiyama_Captcha_On_Login::PLUGIN_ID;
        $capcha_on_login_class_name = $anderson_makiyama[ $anderson_makiyama_indice ]::CLASS_NAME;

        $capcha_on_login_options = (array) get_option($capcha_on_login_class_name . '_options', array());
        $last_100_logins = isset($capcha_on_login_options['last_100_logins']) ? (array) $capcha_on_login_options['last_100_logins'] : array();
        $last_100_logins = array_reverse($last_100_logins);

        // Possible messages
        // - Failed: IP already blocked
        // - Failed: exceeded max number of tries
        // - Failed: image code did not match
        // - Failed: Login or Password did not match
        // - Success
        $last_login_status = isset($last_100_logins[0][2]) ? $last_100_logins[0][2] : '';

        // If we get here we're pretty sure we come from Captcha on login
        // and that we should cancel the wp_logout message and log an failed login instead
        // Get the user logger
        $userLogger = $this->sh->getInstantiatedLoggerBySlug('SimpleUserLogger');

        if (! $userLogger) {
            return $doLog;
        }

        // $userLogger->warningMessage("user_unknown_login_failed", $context);
        // Same context as in SimpleUserLogger
        $context = array(
            '_initiator' => SimpleLoggerLogInitiators::WEB_USER,
            'server_http_user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : null,
            '_occasionsID' => 'SimpleUserLogger' . '/failed_user_login',
            'patch_using_patch' => true,
            'patch_name' => 'captcha_on_login',
        );

        // Append capcha message
        if ($last_login_status) {
            $context['patch_last_login_status'] = $last_login_status;
        }

        // Get user id and email and login
        // Not passed to filter, but we have it in $_POST
        $login_username = isset($_POST['log']) ? $_POST['log'] : null;

        if ($login_username) {
            $context['login_user_login'] = $login_username;

            $user = get_user_by('login', $login_username);

            if (is_a($user, 'WP_User')) {
                $context['login_user_id'] = $user->ID;
                $context['login_user_email'] = $user->user_email;
            }
        }

        $userLogger->warningMessage('user_login_failed', $context);

        // Cancel original log event
        $doLog = false;

        return $doLog;
    }
} // end class
