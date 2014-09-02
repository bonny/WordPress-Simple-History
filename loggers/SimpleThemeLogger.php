<?php

/**
 * Logs WordPress theme edits
 */
class SimpleThemeLogger extends SimpleLogger
{

	public $slug = __CLASS__;

	// When swithing themes, this will contain info about the theme we are switching from
	private $prev_theme_data;

	/**
	 * Get array with information about this logger
	 * 
	 * @return array
	 */
	function getInfo() {

		$arr_info = array(			
			//"name" => __("Theme Logger", "simple-history"),
			"name" => "Theme Logger",
			//"description" => __("Logs theme edits", "simple-history"),
			"description" => "Logs theme edits",
			"capability" => "edit_theme_options",
			"messages" => array(
				'theme_switched' => __('Switched theme to from "{prev_theme_name}" to "{theme_name}"', "simple-history"),
			)
		);
		
		return $arr_info;

	}

	function loaded() {

		/**
		 * Fires after the theme is switched.
		 * @param string   $new_name  Name of the new theme.
		 * @param WP_Theme $new_theme WP_Theme instance of the new theme.
		 */
		add_action( 'switch_theme', array( $this, "on_switch_theme" ), 10, 2 );
		add_action( 'load-themes.php', array( $this, "on_page_load_themes" ) );

	}

	/**
	 * When a new theme is about to get switched to
	 * we save info about the old one
	 */
	function on_page_load_themes() {

		//sf_d($_REQUEST, "request");exit;
		/*
		request:
		Array
		(
		    [action] => activate
		    [stylesheet] => wp-theme-bonny-starter
		    [_wpnonce] => 31b033ba59
		)
		*/

		if ( ! isset($_GET["action"]) || $_GET["action"] != "activate") {
			return;
		}

		// Get current theme / the theme we are switching from
		$current_theme = wp_get_theme();
		/*
		$current_theme:
		WP_Theme Object
		(
		    [theme_root:WP_Theme:private] => /Users/bonny/Documents/Sites/playground-root/assets/themes
		    [headers:WP_Theme:private] => Array
		        (
		            [Name] => Twenty Eleven
		            [ThemeURI] => http://wordpress.org/themes/twentyeleven
		            [Description] => The 2011 theme for WordPress is sophisticated, lightweight, and adaptable. Make it yours with a custom menu, header image, and background -- then go further with available theme options for light or dark color scheme, custom link colors, and three layout choices. Twenty Eleven comes equipped with a Showcase page template that transforms your front page into a showcase to show off your best content, widget support galore (sidebar, three footer areas, and a Showcase page widget area), and a custom "Ephemera" widget to display your Aside, Link, Quote, or Status posts. Included are styles for print and for the admin editor, support for featured images (as custom header images on posts and pages and as large images on featured "sticky" posts), and special styles for six different post formats.
		            [Author] => the WordPress team
		            [AuthorURI] => http://wordpress.org/
		            [Version] => 1.8
		            [Template] => 
		            [Status] => 
		            [Tags] => dark, light, white, black, gray, one-column, two-columns, left-sidebar, right-sidebar, fixed-layout, responsive-layout, custom-background, custom-colors, custom-header, custom-menu, editor-style, featured-image-header, featured-images, flexible-header, full-width-template, microformats, post-formats, rtl-language-support, sticky-post, theme-options, translation-ready
		            [TextDomain] => twentyeleven
		            [DomainPath] => 
		        )

		    [headers_sanitized:WP_Theme:private] => 
		    [name_translated:WP_Theme:private] => 
		    [errors:WP_Theme:private] => 
		    [stylesheet:WP_Theme:private] => twentyeleven
		    [template:WP_Theme:private] => twentyeleven
		    [parent:WP_Theme:private] => 
		    [theme_root_uri:WP_Theme:private] => 
		    [textdomain_loaded:WP_Theme:private] => 
		    [cache_hash:WP_Theme:private] => 797bf456e43982f41d5477883a6815da
		)

		*/
		if ( ! is_a($current_theme, "WP_Theme") ) {
			return;
		}
		#sf_d($current_theme);

		$this->prev_theme_data = array(
			"name" => $current_theme->name,
			"version" => $current_theme->version
		);

	}

	function on_switch_theme($new_name, $new_theme) {

		$prev_theme_data = $this->prev_theme_data;
		
		$this->infoMessage(
			"theme_switched",
			array(
				"theme_name" => $new_name,
				"theme_version" => $new_theme->version,
				"prev_theme_name" => $prev_theme_data["name"],
				"prev_theme_version" => $prev_theme_data["version"]
			)
		);

	}

}
