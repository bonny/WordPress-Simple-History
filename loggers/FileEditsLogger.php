<?php

/**
 * Logs edits to theme or plugin files done from Appearance -> Editor or Plugins -> Editor
 */
class FileEditsLogger extends SimpleLogger
{

    public $slug = __CLASS__;

    function getInfo()
    {

        $arr_info = array(
            'name' => 'FileEditsLogger',
            'description' => 'Logs edits to theme and plugin files',
            'capability' => 'manage_options',
            'messages' => array(
                'theme_file_edited' => __('Edited file "{file_name}" in theme "{theme_name}"', 'simple-history'),
                'plugin_file_edited' => __('Edited file "{file_name}" in plugin "{plugin_name}"', 'simple-history'),
            ),
            'labels' => array(
                'search' => array(
                    'label' => _x('Edited theme and plugin files', 'Plugin logger: file edits', 'simple-history'),
                    'label_all' => _x('All file edits', 'Plugin logger: file edits', 'simple-history'),
                    'options' => array(
                        _x('Edited theme files', 'Plugin logger: file edits', 'simple-history') => array(
                            'theme_file_edited'
                        ),
                        _x('Edited plugin files', 'Plugin logger: file edits', 'simple-history') => array(
                            'plugin_file_edited',
                        ),
                    ),
                ),// search array
            ),// labels
        );

        return $arr_info;
    }

    function loaded()
    {
        add_action('load-theme-editor.php', array( $this, 'on_load_theme_editor' ), 10, 1);
        add_action('load-plugin-editor.php', array( $this, 'on_load_plugin_editor' ), 10, 1);
    }

    /**
     * Called when /wp/wp-admin/plugin-editor.php is loaded
     * Both using regular GET and during POST with updated file data
     *
     * todo:
     * - log edits
     * - log failed edits that result in error and plugin deactivation
     */
    public function on_load_plugin_editor()
    {
        if (isset($_POST) && isset($_POST['action'])) {
            $action = isset($_POST['action']) ? $_POST['action'] : null;
            $file = isset($_POST['file']) ? $_POST['file'] : null;
            $plugin_file = isset($_POST['plugin']) ? $_POST['plugin'] : null;
            $fileNewContents = isset($_POST['newcontent']) ? wp_unslash($_POST['newcontent']) : null;
            $scrollto = isset($_POST['scrollto']) ? (int) $_POST['scrollto'] : 0;

            // if 'phperror' is set then there was an error and an edit is done and wp tries to activate the plugin again
            // $phperror = isset($_POST["phperror"]) ? $_POST["phperror"] : null;
            // Get info about the edited plugin
            $pluginInfo = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin_file);
            $pluginName = isset($pluginInfo['Name']) ? $pluginInfo['Name'] : null;
            $pluginVersion = isset($pluginInfo['Version']) ? $pluginInfo['Version'] : null;

            // Get contents before save
            $fileContentsBeforeEdit = file_get_contents(WP_PLUGIN_DIR . '/' . $file);

            $context = array(
                'file_name' => $plugin_file,
                'plugin_name' => $pluginName,
                'plugin_version' => $pluginVersion,
                'old_file_contents' => $fileContentsBeforeEdit,
                'new_file_contents' => $fileNewContents,
                '_occasionsID' => __CLASS__ . '/' . __FUNCTION__ . "/file-edit/$plugin_file/$file",
            );

            $loggerInstance = $this;
            add_filter('wp_redirect', function ($location, $status) use ($context, $loggerInstance) {
                $locationParsed = parse_url($location);

                if ($locationParsed === false || empty($locationParsed['query'])) {
                    return $location;
                }

                parse_str($locationParsed['query'], $queryStringParsed);
                // ddd($_POST, $context, $queryStringParsed, $location);
                if (empty($queryStringParsed)) {
                    return $location;
                }

                // If query string "a=te" exists or "liveupdate=1" then plugin file was updated
                $teIsSet = isset($queryStringParsed['a']) && $queryStringParsed['a'] === 'te';
                $liveUpdateIsSet = isset($queryStringParsed['liveupdate']) && $queryStringParsed['liveupdate'] === '1';
                if ($teIsSet || $liveUpdateIsSet) {
                    // File was updated
                    $loggerInstance->infoMessage('plugin_file_edited', $context);
                }

                return $location;

                // location when successful edit to non-active plugin
                // http://wp-playground.dev/wp/wp-admin/plugin-editor.php?file=akismet/akismet.php&plugin=akismet/akismet.php&a=te&scrollto=0
                // locations when activated plugin edited successfully
                // plugin-editor.php?file=akismet%2Fakismet.php&plugin=akismet%2Fakismet.php&liveupdate=1&scrollto=0&networkwide&_wpnonce=b3f399fe94
                // plugin-editor.php?file=akismet%2Fakismet.php&phperror=1&_error_nonce=63511c266d
                // http://wp-playground.dev/wp/wp-admin/plugin-editor.php?file=akismet/akismet.php&plugin=akismet/akismet.php&a=te&scrollto=0
                // locations when editing active plugin and error occurs
                // plugin-editor.php?file=akismet%2Fakismet.php&plugin=akismet%2Fakismet.php&liveupdate=1&scrollto=0&networkwide&_wpnonce=b3f399fe94
                // plugin-editor.php?file=akismet%2Fakismet.php&phperror=1&_error_nonce=63511c266d
                // locations when error edit is fixed and saved and plugin is activated again
                // plugin-editor.php?file=akismet%2Fakismet.php&plugin=akismet%2Fakismet.php&liveupdate=1&scrollto=0&networkwide&_wpnonce=b3f399fe94
                // plugin-editor.php?file=akismet%2Fakismet.php&phperror=1&_error_nonce=63511c266d
                // http://wp-playground.dev/wp/wp-admin/plugin-editor.php?file=akismet/akismet.php&plugin=akismet/akismet.php&a=te&scrollto=0
            }, 10, 2);
        }// End if().
        /*
        <?php if (isset($_GET['a'])) : ?>
         <div id="message" class="updated notice is-dismissible"><p><?php _e('File edited successfully.') ?></p></div>
        <?php elseif (isset($_GET['phperror'])) : ?>
         <div id="message" class="updated"><p><?php _e('This plugin has been deactivated because your changes resulted in a <strong>fatal error</strong>.') ?></p>
        */
    }

    /**
     * Called when /wp/wp-admin/theme-editor.php is loaded
     * Both using regular GET and during POST with updated file data
     *
     * When this action is fired we don't know if a file will be successfully saved or not.
     * There are no filters/actions fired when the edit is saved. On the end wp_redirect() is
     * called however and we know the location for the redirect and wp_redirect() has filters
     * so we hook onto that to save the edit.
     */
    public function on_load_theme_editor()
    {
        // Only continue if method is post and action is update
        if (isset($_POST) && isset($_POST['action']) && $_POST['action'] === 'update') {
            /*
            POST data is like
                array(8)
                    '_wpnonce' => string(10) "9b5e46634f"
                    '_wp_http_referer' => string(88) "/wp/wp-admin/theme-editor.php?file=style.css&theme=twentyfifteen&scrollto=0&upda…"
                    'newcontent' => string(104366) "/* Theme Name: Twenty Fifteen Theme URI: https://wordpress.org/themes/twentyfift…"
                    'action' => string(6) "update"
                    'file' => string(9) "style.css"
                    'theme' => string(13) "twentyfifteen"
                    'scrollto' => string(3) "638"
                    'submit' => string(11) "Update File"
            */

            $action = isset($_POST['action']) ? $_POST['action'] : null;
            $file = isset($_POST['file']) ? $_POST['file'] : null;
            $theme = isset($_POST['theme']) ? $_POST['theme'] : null;
            $fileNewContents = isset($_POST['newcontent']) ? wp_unslash($_POST['newcontent']) : null;
            $scrollto = isset($_POST['scrollto']) ? (int) $_POST['scrollto'] : 0;

            // Same code as in theme-editor.php
            if ($theme) {
                $stylesheet = $theme;
            } else {
                $stylesheet = get_stylesheet();
            }

            $theme = wp_get_theme($stylesheet);

            if (! is_a($theme, 'WP_Theme')) {
                return;
            }

            // Same code as in theme-editor.php
            $relative_file = $file;
            $file = $theme->get_stylesheet_directory() . '/' . $relative_file;

            // Get file contents, so we have something to compare with later
            $fileContentsBeforeEdit = file_get_contents($file);

            $context = array(
                'theme_name' => $theme->name,
                'theme_stylesheet_path' => $theme->get_stylesheet(),
                'theme_stylesheet_dir' => $theme->get_stylesheet_directory(),
                'file_name' => $relative_file,
                'file_dir' => $file,
                'old_file_contents' => $fileContentsBeforeEdit,
                'new_file_contents' => $fileNewContents,
                '_occasionsID' => __CLASS__ . '/' . __FUNCTION__ . "/file-edit/$file",
            );

            // Hook into wp_redirect
            // This hook is only added when we know a POST is done from theme-editor.php
            $loggerInstance = $this;
            add_filter('wp_redirect', function ($location, $status) use ($context, $loggerInstance) {
                $locationParsed = parse_url($location);

                if ($locationParsed === false || empty($locationParsed['query'])) {
                    return $location;
                }

                parse_str($locationParsed['query'], $queryStringParsed);

                if (empty($queryStringParsed)) {
                    return $location;
                }

                if (isset($queryStringParsed['updated']) && $queryStringParsed['updated']) {
                    // File was updated
                    $loggerInstance->infoMessage('theme_file_edited', $context);
                } else {
                    // File was not updated. Unknown reason, but probably because could not be written.
                }

                return $location;
            }, 10, 2); // add_filter
        } // End if().
    }


    public function getLogRowDetailsOutput($row)
    {

        $context = $row->context;
        $message_key = isset($context['_message_key']) ? $context['_message_key'] : null;

        if (! $message_key) {
            return;
        }

        $out = '';

        $diff_table_output = '';

        if (! empty($context['new_file_contents']) && ! empty($context['old_file_contents'])) {
            if ($context['new_file_contents'] !== $context['old_file_contents']) {
                $diff_table_output .= sprintf(
                    '<tr><td>%1$s</td><td>%2$s</td></tr>',
                    __('File contents', 'simple-history'),
                    simple_history_text_diff($context['old_file_contents'], $context['new_file_contents'])
                );
            }
        }

        if ($diff_table_output) {
            $diff_table_output = '<table class="SimpleHistoryLogitem__keyValueTable">' . $diff_table_output . '</table>';
        }

        $out .= $diff_table_output;

        return $out;
    }
} // class
