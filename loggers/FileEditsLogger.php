<?php

/**
 * Logs edits to theme or plugin files done from Appearance -> Editor or Plugins -> Editor
 */

/*

location efter theme update
theme-editor.php?file=style.css&theme=twentyfifteen&scrollto=52&updated=true

Filter/actions vi kan hooka på:

// adminaction_update
// var_dump('admin_action' . $_REQUEST['action']);exit;

Redigera tema: - Appearance -> Editor
- http://wp-playground.dev/wp/wp-admin/theme-editor.php?file=style.css&theme=twentyfifteen&scrollto=301
- Redigerar ett tema + en fil i temat
- do_action( "load-{$pagenow}" ); == "load-theme-editor.php"


Redigera plugin: - Plugins -> Editor
- http://wp-playground.dev/wp/wp-admin/plugin-editor.php
- Redigerar ett plugin + en fil i pluginen


*/


class FileEditsLogger extends SimpleLogger {

    public $slug = __CLASS__;

    function getInfo() {

        $arr_info = array(
            "name" => "FileEditsLogger",
            "description" => "Logs edits to theme or plugin files",
            "capability" => "manage_options",
            "messages" => array(
                "theme_file_edited" => __( 'Edited file "{file_name}" in theme "{theme_name}"', "simple-history" ),
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
        add_action( 'load-theme-editor.php', array( $this, "on_load_theme_editor" ), 10, 1 );
        add_action( 'shutdown', array( $this, "on_shutdown" ), 10, 1 );

    }

    /*
     * Fird just before PHP shuts down execution.
     */
    public function on_shutdown() {
    	// ddd('shutdown!');
    	#exit;
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
    public function on_load_theme_editor() {
    	// Only continue if method is post and action is update
    	if (isset($_POST) && isset($_POST["action"]) && $_POST["action"] === "update") {
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

			$action = isset($_POST["action"]) ? $_POST["action"] : null;
			$file = isset($_POST["file"]) ? $_POST["file"] : null;
			$theme = isset($_POST["theme"]) ? $_POST["theme"] : null;
			$scrollto = isset($_POST["scrollto"]) ? (int) $_POST["scrollto"] : 0;

			// Same code as in theme-editor.php
			if ( $theme ) {
				$stylesheet = $theme;
			} else {
				$stylesheet = get_stylesheet();
			}

			$theme = wp_get_theme( $stylesheet );

			if (! is_a($theme, 'WP_Theme')) {
				return;
			}

			// Same code as in theme-editor.php
			$relative_file = $file;
			$file = $theme->get_stylesheet_directory() . '/' . $relative_file;

			$context = array(
				"action" => $action,
				"theme_name" => $theme->name,
				"theme_stylesheet_path" => $theme->get_stylesheet(),
				"theme_stylesheet_dir" => $theme->get_stylesheet_directory(),
				"file_name" => $relative_file,
				"file_dir" => $file
			);

			// Hook into wp_redirect
			// This hook is only added when we know a POST is done from theme-editor.php
			$loggerInstance = $this;
			add_filter( 'wp_redirect', function ($location, $status) use ($context, $loggerInstance) {
				$locationParsed = parse_url( $location );

				if ( $locationParsed === false || empty( $locationParsed['query'] ) ) {
					return $location;
				}

				parse_str( $locationParsed['query'], $queryStringParsed);

				if (empty($queryStringParsed)) {
					return $location;
				}

				if (isset($queryStringParsed["updated"]) && $queryStringParsed["updated"]) {
					// File was updated
					$this->infoMessage('theme_file_edited', $context);
					// ddd("file was updated", $locationParsed, $location, $queryStringParsed, $context, $loggerInstance);
				} else {
					// File was not updated. Unknown reason, but probably because could not be written.
				}

				return $location;

			}, 10, 2);
    	} // if post action update
    }

    /*public function on_wp_redirect($location, $status) {
    	ddd($location);
    }*/

} // class
