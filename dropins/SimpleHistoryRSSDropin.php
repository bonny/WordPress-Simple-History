<?php

// defined('ABSPATH') or die();
/*
Dropin Name: Global RSS Feed
Dropin URI: http://simple-history.com/
Author: Pär Thernström
*/

/**
 * Simple History RSS Feed drop-in
 */
class SimpleHistoryRSSDropin
{


    public function __construct($sh)
    {

        $this->sh = $sh;

        if (! function_exists('get_editable_roles')) {
            require_once(ABSPATH . '/wp-admin/includes/user.php');
        }

        // Check the status of the RSS feed
        $this->isRssEnabled();

        // Generate a rss secret, if it does not exist
        if (! get_option('simple_history_rss_secret')) {
            $this->updateRssSecret();
        }

        add_action('init', array( $this, 'checkForRssFeedRequest' ));

        // Add settings with prio 11 so it' added after the main Simple History settings
        add_action('admin_menu', array( $this, 'addSettings' ), 11);
    }

    /**
     * Add settings for the RSS feed
     * + also regenerates the secret if requested
     */
    public function addSettings()
    {

        // we register a setting to keep track of the RSS feed status (enabled/disabled)
        register_setting(
            SimpleHistory::SETTINGS_GENERAL_OPTION_GROUP,
            'simple_history_enable_rss_feed',
            array(
            $this,
            'public updateRssStatus',
                )
        );
        /**
         * Start new section for RSS feed
         */
        $settings_section_rss_id = 'simple_history_settings_section_rss';

        add_settings_section(
            $settings_section_rss_id,
            _x('RSS feed', 'rss settings headline', 'simple-history'), // No title __("General", "simple-history"),
            array( $this, 'settingsSectionOutput' ),
            SimpleHistory::SETTINGS_MENU_SLUG // same slug as for options menu page
        );

        // Enable/Disabled RSS feed
        add_settings_field(
            'simple_history_enable_rss_feed',
            __('Enable', 'simple-history'),
            array( $this, 'settingsFieldRssEnable' ),
            SimpleHistory::SETTINGS_MENU_SLUG,
            $settings_section_rss_id
        );

        // if RSS is activated we display other fields
        if ($this->isRssEnabled()) {
            // RSS address
            add_settings_field(
                'simple_history_rss_feed',
                __('Address', 'simple-history'),
                array( $this, 'settingsFieldRss' ),
                SimpleHistory::SETTINGS_MENU_SLUG,
                $settings_section_rss_id
            );

            // Regnerate address
            add_settings_field(
                'simple_history_rss_feed_regenerate_secret',
                __('Regenerate', 'simple-history'),
                array( $this, 'settingsFieldRssRegenerate' ),
                SimpleHistory::SETTINGS_MENU_SLUG,
                $settings_section_rss_id
            );
        }

        // Create new RSS secret
        $create_new_secret = false;
        $create_secret_nonce_name = 'simple_history_rss_secret_regenerate_nonce';
        $createNonceOk = isset($_GET[ $create_secret_nonce_name ]) && wp_verify_nonce($_GET[ $create_secret_nonce_name ], 'simple_history_rss_update_secret');

        if ($createNonceOk) {
            $create_new_secret = true;
            $this->updateRssSecret();

            // Add updated-message and store in transient and then redirect
            // This is the way options.php does it.
            $msg = __('Created new secret RSS address', 'simple-history');
            add_settings_error('simple_history_rss_feed_regenerate_secret', 'simple_history_rss_feed_regenerate_secret', $msg, 'updated');
            set_transient('settings_errors', get_settings_errors(), 30);

            $goback = esc_url_raw(add_query_arg('settings-updated', 'true', wp_get_referer()));
            wp_redirect($goback);
            exit;
        }
    } // settings

    /**
     * Check if RSS feed is enabled or disabled
     */
    public function isRssEnabled()
    {

        // User has never used the plugin we disable RSS feed
        if (get_option('simple_history_rss_secret') === false && get_option('simple_history_enable_rss_feed') === false) {
            // We disable RSS by default, we use 0/1 to prevent fake disabled with bools from functions returning false for unset
            update_option('simple_history_enable_rss_feed', '0');
        } elseif (get_option('simple_history_enable_rss_feed') === false) {
            // User was using the plugin before RSS feed became disabled by default
            // We activate RSS to prevent a "breaking change"
            update_option('simple_history_enable_rss_feed', '1');
            return true;
        } elseif (get_option('simple_history_enable_rss_feed') === '1') {
            return true;
        }

        return false;
    }

    /**
     * Output for settings field that show current RSS address
     */
    public function settingsFieldRssEnable()
    {
        ?>
        <input value="1" type="checkbox" id="simple_history_enable_rss_feed" name="simple_history_enable_rss_feed" <?php checked($this->isRssEnabled(), 1); ?> />
        <label for="simple_history_enable_rss_feed"><?php _e('Enable RSS feed', 'simple-history') ?></label>
        <?php
    }

    /**
     * Sanitize RSS enabled/disabled status on update settings
     */
    public function updateRssStatus($field)
    {

        if ($field === '1') {
            return '1';
        }

        return '0';
    }


    /**
     * Check if current request is a request for the RSS feed
     */
    public function checkForRssFeedRequest()
    {
        // check for RSS
        // don't know if this is the right way to do this, but it seems to work!
        if (isset($_GET['simple_history_get_rss'])) {
            $this->outputRss();
            exit;
        }
    }

    /**
     * Modify capability check so all users reading rss feed (logged in or not) can read all loggers
     */
    public function onCanReadSingleLogger($user_can_read_logger, $logger_instance, $user_id)
    {
        $user_can_read_logger = true;

        return $user_can_read_logger;
    }

    /**
     * Output RSS
     */
    public function outputRss()
    {

        $rss_secret_option = get_option('simple_history_rss_secret');
        $rss_secret_get = isset($_GET['rss_secret']) ? $_GET['rss_secret'] : '';

        if (empty($rss_secret_option) || empty($rss_secret_get)) {
            die();
        }

        $rss_show = true;
        $rss_show = apply_filters('simple_history/rss_feed_show', $rss_show);
        if (! $rss_show || ! $this->isRssEnabled()) {
            wp_die('Nothing here.');
        }

        header('Content-Type: text/xml; charset=utf-8');
        echo '<?xml version="1.0" encoding="UTF-8"?>';
        $self_link = $this->getRssAddress();

        if ($rss_secret_option === $rss_secret_get) {
            ?>
            <rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
                <channel>
                    <title><![CDATA[<?php printf(__('History for %s', 'simple-history'), get_bloginfo('name')) ?>]]></title>
                    <description><![CDATA[<?php printf(__('WordPress History for %s', 'simple-history'), get_bloginfo('name')) ?>]]></description>
                    <link><?php echo get_bloginfo('url') ?></link>
                    <atom:link href="<?php echo $self_link; ?>" rel="self" type="application/atom+xml" />
                    <?php

                    // Override capability check: if you have a valid rss_secret_key you can read it all
                    $action_tag = 'simple_history/loggers_user_can_read/can_read_single_logger';
                    add_action($action_tag, array( $this, 'onCanReadSingleLogger' ), 10, 3);

                    // Modify header time output so it does not show relative date or time ago-format
                    // Because we don't know when a user reads the RSS feed, time ago format may be very inaccurate
                    add_action('simple_history/header_just_now_max_time', '__return_zero');
                    add_action('simple_history/header_time_ago_max_time', '__return_zero');

                    // Get log rows
                    $args = array(
                        'posts_per_page' => 10,
                    );

                    $args = apply_filters('simple_history/rss_feed_args', $args);

                    $logQuery = new SimpleHistoryLogQuery();
                    $queryResults = $logQuery->query($args);

                    // Remove capability override after query is done
                    // remove_action( $action_tag, array($this, "onCanReadSingleLogger") );
                    foreach ($queryResults['log_rows'] as $row) {
                        $header_output = $this->sh->getLogRowHeaderOutput($row);
                        $text_output = $this->sh->getLogRowPlainTextOutput($row);
                        $details_output = $this->sh->getLogRowDetailsOutput($row);

                        // http://cyber.law.harvard.edu/rss/rss.html#ltguidgtSubelementOfLtitemgt
                        // $item_guid = home_url() . "?SimpleHistoryGuid=" . $row->id;
                        $item_guid = esc_url(add_query_arg('SimpleHistoryGuid', $row->id, home_url()));
                        $item_link = esc_url(add_query_arg('SimpleHistoryGuid', $row->id, home_url()));

                        /**
                         * Filter the guid/link URL used in RSS feed.
                         * Link will be esc_url'ed by simple history, so no need to do that in your filter
                         *
                         * @since 2.0.23
                         *
                         * @param string $item_guid link.
                         * @param array $row
                         */
                        $item_link = apply_filters('simple_history/rss_item_link', $item_link, $row);
                        $item_link = esc_url($item_link);

                        $item_title = sprintf(
                            '%2$s',
                            $this->sh->getLogLevelTranslated($row->level),
                            wp_kses($text_output, array())
                        );

                        $level_output = sprintf(__('Severity level: %1$s'), $this->sh->getLogLevelTranslated($row->level));

                        ?>
                        <item>
                            <title><![CDATA[<?php echo $item_title; ?>]]></title>
                            <description><![CDATA[
                                <p><?php echo $header_output ?></p>
                                <p><?php echo $text_output ?></p>
                                <div><?php echo $details_output ?></div>
                                <p><?php echo $level_output ?></p>
                                <?php
                                $occasions = $row->subsequentOccasions - 1;
                                if ($occasions) {
                                    printf(
                                        _n('+%1$s occasion', '+%1$s occasions', $occasions, 'simple-history'),
                                        $occasions
                                    );
                                }
                                ?>
                            ]]></description>
                            <?php
                            // author must be email to validate, but the field is optional, so we skip it
                            /* <author><?php echo $row->initiator ?></author> */
                            ?>
                            <pubDate><?php echo date('D, d M Y H:i:s', strtotime($row->date)) ?> GMT</pubDate>
                            <guid isPermaLink="false"><![CDATA[<?php echo $item_guid ?>]]></guid>
                            <link><![CDATA[<?php echo $item_link ?>]]></link>
                        </item>
                        <?php
                    } // End foreach().

                    ?>
                </channel>
            </rss>
            <?php
        } else {
            // RSS secret was not ok
            ?>
            <rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
                <channel>
                    <title><?php printf(__('History for %s', 'simple-history'), get_bloginfo('name')) ?></title>
                    <description><?php printf(__('WordPress History for %s', 'simple-history'), get_bloginfo('name')) ?></description>
                    <link><?php echo home_url() ?></link>
                    <item>
                        <title><?php _e('Wrong RSS secret', 'simple-history')?></title>
                        <description><?php _e('Your RSS secret for Simple History RSS feed is wrong. Please see WordPress settings for current link to the RSS feed.', 'simple-history')?></description>
                        <pubDate><?php echo date('D, d M Y H:i:s', time()) ?> GMT</pubDate>
                        <guid><?php echo home_url() . '?SimpleHistoryGuid=wrong-secret' ?></guid>
                    </item>
                </channel>
            </rss>
            <?php
        }// End if().
    } // rss

    /**
     * Create a new RSS secret
     *
     * @return string new secret
     */
    public function updateRssSecret()
    {

        $rss_secret = '';

        for ($i = 0; $i < 20; $i++) {
            $rss_secret .= chr(rand(97, 122));
        }

        update_option('simple_history_rss_secret', $rss_secret);

        return $rss_secret;
    }

    /**
     * Output for settings field that show current RSS address
     */
    public function settingsFieldRss()
    {

        $rss_address = $this->getRssAddress();

        echo "<p><code><a href='$rss_address'>$rss_address</a></code></p>";
    }

    /**
     * Output for settings field that regenerates the RSS adress/secret
     */
    public function settingsFieldRssRegenerate()
    {

        $update_link = esc_url(add_query_arg('', ''));
        $update_link = wp_nonce_url($update_link, 'simple_history_rss_update_secret', 'simple_history_rss_secret_regenerate_nonce');

        echo '<p>';
        _e('You can generate a new address for the RSS feed. This is useful if you think that the address has fallen into the wrong hands.', 'simple-history');
        echo '</p>';

        echo '<p>';
        printf(
            '<a class="button" href="%1$s">%2$s</a>',
            $update_link, // 1
            __('Generate new address', 'simple-history') // 2
        );

        echo '</p>';
    }

    /**
     * Get the URL to the RSS feed
     *
     * @return string URL
     */
    public function getRssAddress()
    {

        $rss_secret = get_option('simple_history_rss_secret');
        $rss_address = add_query_arg(
            array(
                'simple_history_get_rss' => '1',
                'rss_secret' => $rss_secret,
            ),
            get_bloginfo('url') . '/'
        );
        $rss_address = esc_url($rss_address);
        // $rss_address = htmlspecialchars($rss_address, ENT_COMPAT, "UTF-8");
        return $rss_address;
    }

    /**
     * Content for section intro. Leave it be, even if empty.
     * Called from add_sections_setting.
     */
    public function settingsSectionOutput()
    {

        echo '<p>';
        _e('Simple History has a RSS feed which you can subscribe to and receive log updates. Make sure you only share the feed with people you trust, since it can contain sensitive or confidential information.', 'simple-history');
        echo '</p>';
    }
} // end rss class
