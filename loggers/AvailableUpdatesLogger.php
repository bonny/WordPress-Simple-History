<?php

/**
 * Logs available updates to themes, plugins and WordPress core
 */
if ( ! class_exists("AvailableUpdatesLogger") ) {

    class AvailableUpdatesLogger extends SimpleLogger {

        public $slug = __CLASS__;

        function getInfo() {

            $arr_info = array(
                "name" => "AvailableUpdatesLogger",
                "description" => "Logs found updates to WordPress, plugins, and themes",
                "capability" => "manage_options",
                "messages" => array(
                    "core_update_available" => __( 'Found an update to WordPress.', "simple-history" ),
                    "plugin_update_available" => __( 'Found an update to plugin "{plugin_name}"', "simple-history" ),
                    "theme_update_available" => __( 'Found an update to theme "{theme_name}"', "simple-history" ),
                ),
                "labels" => array(
    				"search" => array(
    					"label" => _x("WordPress and plugins updates found", "Plugin logger: updates found", "simple-history"),
    					"label_all" => _x("All found updates", "Plugin logger: updates found", "simple-history"),
    					"options" => array(
    						_x("WordPress updates found", "Plugin logger: updates found", "simple-history") => array(
    							'core_update_available'
    						),
    						_x("Plugin updates found", "Plugin logger: updates found", "simple-history") => array(
    							'plugin_update_available',
    						),
    						_x("Theme updates found", "Plugin logger: updates found", "simple-history") => array(
    							'theme_update_available'
    						),
    					)
    				) // search array
    			) // labels
            );

            return $arr_info;

        }

        function loaded() {

            // When WP is done checking for core updates it sets a site transient called "update_core"
            // set_site_transient( 'update_core', null ); // Uncomment to test
            add_action( "set_site_transient_update_core", array( $this, "on_setted_update_core_transient" ), 10, 1 );

            // Dito for plugins
            // set_site_transient( 'update_plugins', null ); // Uncomment to test
            add_action( "set_site_transient_update_plugins", array( $this, "on_setted_update_plugins_transient" ), 10, 1 );

            add_action( "set_site_transient_update_themes", array( $this, "on_setted_update_update_themes" ), 10, 1 );

        }

        function on_setted_update_core_transient( $updates ) {

            global $wp_version;

            $last_version_checked = get_option( "simplehistory_{$this->slug}_wp_core_version_available" );

            // During update of network sites this was not set, so make sure to check
            if ( empty( $updates->updates[0]->current ) ) {
                return;
            }

            $new_wp_core_version = $updates->updates[0]->current; // The new WP core version

            // Some plugins can mess with version, so get fresh from the version file.
            require_once ABSPATH . WPINC . '/version.php';

            // If found version is same version as we have logged about before then don't continue
            if ( $last_version_checked == $new_wp_core_version ) {
                return;
            }

            // is WP core update available?
            if ( 'upgrade' == $updates->updates[0]->response ) {

                $this->noticeMessage( "core_update_available", array(
                    "wp_core_current_version" => $wp_version,
                    "wp_core_new_version" => $new_wp_core_version,
                    "_initiator" => SimpleLoggerLogInitiators::WORDPRESS
                ) );

                // Store updated version available, so we don't log that version again
                update_option( "simplehistory_{$this->slug}_wp_core_version_available", $new_wp_core_version );

            }

        }


        function on_setted_update_plugins_transient( $updates ) {

            if ( empty( $updates->response ) || ! is_array( $updates->response ) ) {
                return;
            }

            // If we only want to notify about active plugins
            /*
            $active_plugins = get_option( 'active_plugins' );
            $active_plugins = array_flip( $active_plugins ); // find which plugins are active
            $plugins_need_update = array_intersect_key( $plugins_need_update, $active_plugins ); // only keep plugins that are active
            */

            //update_option( "simplehistory_{$this->slug}_wp_core_version_available", $new_wp_core_version );
            $option_key = "simplehistory_{$this->slug}_plugin_updates_available";
            $checked_updates = get_option( $option_key );

            if ( ! is_array( $checked_updates ) ) {
                $checked_updates = array();
            }

            // File needed plugin API
            if ( ! function_exists("get_plugin_data") ) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }

            // For each available update
            foreach ( $updates->response as $key => $data ) {

                $plugin_info = get_plugin_data( WP_PLUGIN_DIR . "/" . $key, true, false );

                $plugin_new_version = isset( $data->new_version ) ? $data->new_version : "";

                // check if this plugin and this version has been checked/logged already
                if ( ! array_key_exists( $key, $checked_updates ) ) {
                    $checked_updates[ $key ] = array(
                        "checked_version" => null
                    );
                }

                if ( $checked_updates[ $key ]["checked_version"] == $plugin_new_version ) {
                    // This version has been checked/logged already
                    continue;
                }

                $checked_updates[ $key ]["checked_version"] = $plugin_new_version;

                $this->noticeMessage( "plugin_update_available", array(
                    "plugin_name" => isset( $plugin_info['Name'] ) ? $plugin_info['Name'] : "",
                    "plugin_current_version" => isset( $plugin_info['Version'] ) ? $plugin_info['Version'] : "",
                    "plugin_new_version" => $plugin_new_version,
                    "_initiator" => SimpleLoggerLogInitiators::WORDPRESS,
                    //"plugin_info" => $plugin_info,
                    // "remote_plugin_info" => $remote_plugin_info,
                    // "active_plugins" => $active_plugins,
                    // "updates" => $updates
                ) );

            } // foreach

            update_option( $option_key, $checked_updates );

        } // function


        function on_setted_update_update_themes( $updates ) {

            if ( empty( $updates->response ) || ! is_array( $updates->response ) ) {
                return;
            }

            //update_option( "simplehistory_{$this->slug}_wp_core_version_available", $new_wp_core_version );
            $option_key = "simplehistory_{$this->slug}_theme_updates_available";
            $checked_updates = get_option( $option_key );

            if ( ! is_array( $checked_updates ) ) {
                $checked_updates = array();
            }

            // For each available update
            foreach ( $updates->response as $key => $data ) {

                $theme_info = wp_get_theme( $key );

                #$message .= "\n" . sprintf( __( "Theme: %s is out of date. Please update from version %s to %s", "wp-updates-notifier" ), $theme_info['Name'], $theme_info['Version'], $data['new_version'] ) . "\n";

                $settings['notified']['theme'][$key] = $data['new_version']; // set theme version we are notifying about

                $theme_new_version = isset( $data["new_version"] ) ? $data["new_version"] : "";

                // check if this plugin and this version has been checked/logged already
                if ( ! array_key_exists( $key, $checked_updates ) ) {
                    $checked_updates[ $key ] = array(
                        "checked_version" => null
                    );
                }

                if ( $checked_updates[ $key ]["checked_version"] == $theme_new_version ) {
                    // This version has been checked/logged already
                    continue;
                }

                $checked_updates[ $key ]["checked_version"] = $theme_new_version;

                $this->noticeMessage( "theme_update_available", array(
                    "theme_name" => isset( $theme_info['Name'] ) ? $theme_info['Name'] : "" ,
                    "theme_current_version" => isset( $theme_info['Version'] ) ? $theme_info['Version'] : "",
                    "theme_new_version" => $theme_new_version,
                    "_initiator" => SimpleLoggerLogInitiators::WORDPRESS
                    //"plugin_info" => $plugin_info,
                    // "remote_plugin_info" => $remote_plugin_info,
                    // "active_plugins" => $active_plugins,
                    // "updates" => $updates,
                ) );

            } // foreach

            update_option( $option_key, $checked_updates );

        } // function

        /**
         * Append prev and current version of update object as details in the output
         */
        function getLogRowDetailsOutput( $row ) {

            $output = "";

            $current_version = null;
            $new_version = null;

            $context = isset( $row->context ) ? $row->context : array();

            switch ( $row->context_message_key ) {

                case "core_update_available":
                    $current_version = isset( $context["wp_core_current_version"] ) ? $context["wp_core_current_version"] : null;
                    $new_version = isset( $context["wp_core_new_version"] ) ? $context["wp_core_new_version"] : null;
                    break;

                case "plugin_update_available":
                    $current_version = isset( $context["plugin_current_version"] ) ? $context["plugin_current_version"] : null;
                    $new_version = isset( $context["plugin_new_version"] ) ? $context["plugin_new_version"] : null;
                    break;

                case "theme_update_available":
                    $current_version = isset( $context["theme_current_version"] ) ? $context["theme_current_version"] : null;
                    $new_version = isset( $context["theme_new_version"] ) ? $context["theme_new_version"] : null;
                    break;

            }

            if ( $current_version && $new_version  ) {

                $output .= '<p>';
                $output .= '<span class="SimpleHistoryLogitem__inlineDivided">';
                $output .= "<em>" . __( 'Available version', "simple-history" ) . "</em> " . esc_html( $new_version );
                $output .= '</span> ';

                $output .= '<span class="SimpleHistoryLogitem__inlineDivided">';
                $output .= "<em>" . __( 'Installed version', "simple-history" ) . "</em> " . esc_html( $current_version );
                $output .= '</span>';

                $output .= '</p>';

                // Add link to update-page
                $output .= sprintf( '<p><a href="%1$s">', admin_url("update-core.php") );
                $output .= __( 'View all updates', "simple-history" );
                $output .= '</a></p>';

            }

            return $output;

        }

    } // class

} // class exists
