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
				// Appearance" → "Customize
				'appearance_customized' => __('Customized theme appearance "{setting_id}"', "simple-history"),
				'widget_deleted' => __("Deleted widget {widget_id_base} from sidebar {sidebar_id}", "simple-history")
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
	
		add_action("customize_save", array( $this, "on_action_customize_save" ));
		// do_action( 'customize_save', $this );
		// do_action( 'customize_save_after', $this );

		add_action("sidebar_admin_setup", array( $this, "on_action_sidebar_admin_setup") );

	}

	/*
	WP_Customize_Manager $this WP_Customize_Manager instance.
	*/
	function on_action_customize_save($customize_manager) {
		
		/*
		- Loop through all sections
		  - And then through all controls in section
		    - Foreach control get it's setting
		      - Get each settings prev value
		      - Get each settings new value
		      - Store changed values

		*/

		/*
		The following sections are built-in:
		------------------------------------
		title_tagline - Site Title & Tagline
		colors - Colors
		header_image - Header Image
		background_image - Background Image
		nav - Navigation
		static_front_page - Static Front Page
		*/

		// Set to true to echo some info about stuff 
		// that can be views in console when saving
		$debug = false;

		$arr_changed_settings = array();
		$arr_changed_settings_ids = array();

		// Needed to get sections and controls in sorted order
		$customize_manager->prepare_controls();

		$sections = $customize_manager->sections();
	
		foreach ($sections as $section ) {

			// Id is unique slug
			// Can't use title because that's translated
			if ($debug) {

				echo "\n-------\n";
				echo "Section: " . $sectionion->id . " (".$section->title.")";

			}

			$section_controls = $section->controls;
			foreach ($section_controls as $section_control) {

				/*
				if ( ! $section_control->check_capabilities() ) {
					echo "\n\n\nno access to control";
				} else {
					echo "\n have access to control";
				}
				*/

				// Settings is always array, but mostly with just one setting in it it seems like
				$section_control_settings = $section_control->settings;

				if ($debug) {
					echo "\n\nControl ";
					echo $section_control->id . " (". $section_control->label . ")";
					//echo "\ncontrol setting: " . $section_control->setting;
					// echo "\nSettings:";
				}

				/*
				Control ttfmake_stylekit-info ()
				har underlig setting
				setting = blogname = uppdateras som en tok!
				*/
				/*if ( $section_control->id == "ttfmake_stylekit-info" ) {
					print_r( sizeof($section_control_settings) );
					echo "\nid: " . $section_control_settings[0]->id;
					echo "\ntitle: " . $section_control_settings[0]->title;
					exit;
				}*/

				foreach ( $section_control_settings as $one_setting ) {

					if ($debug) {
						// setting id is supposed to be unique, but some themes
						// seems to register "blogname" for example
						// which messes things up...
						// solution:
						// order sections so built-in sections always are added first
						// and then only store a seting id once (the first time = for the built in setting)
						// hm.. nope, does not work. in this case theme "make" is using blogname in their own control
						echo "\nsetting id " . $one_setting->id;
					}
				
					$old_value = $one_setting->value();
					$new_value = $one_setting->post_value();

					// If old and new value is different then we log
					if ($old_value != $new_value && ! in_array($one_setting_id, $arr_changed_settings_ids) ) {
					#if ( $old_value != $new_value ) {

						if ($debug) {
							echo "\nSetting with id ";
							echo $one_setting->id;
							echo " changed";
							echo "\nold value $old_value";
							echo "\nnew value $new_value";
						}

						$arr_changed_settings[] = array(
							"setting_id" => $one_setting->id,
							"setting_old_value" => $old_value,
							"setting_new_value" => $new_value,
							"control_id" => $section_control->id,
							"control_label" => $section_control->label,
							"control_type" => $section_control->type,
							"section_id" => $section->id,
							"section_title" => $section->title,
						);

						$arr_changed_settings_ids[] = $one_setting->id;

					} // if settings changed

				} // foreach setting

			} // foreach control

		} // foreach section

		/*
		arr_changed_settingsArray
		(
		    [0] => Array
		        (
		            [setting_id] => background_color
		            [setting_old_value] => 31d68e
		            [setting_new_value] => 2f37ce
		            [control_id] => background_color
		            [control_label] => Background Color
		            [control_type] => color
		            [section_id] => background_image
		            [section_title] => Background
		        )

		    [1] => Array
		        (
		            [setting_id] => font-header
		            [setting_old_value] => sans-serif
		            [setting_new_value] => monospace
		            [control_id] => ttfmake_font-header
		            [control_label] => Headers
		            [control_type] => select
		            [section_id] => ttfmake_font
		            [section_title] => Fonts
		        )

		)
		*/
		if ($arr_changed_settings) {

			if ($debug) {
				echo "\narr_changed_settings";
				print_r( $arr_changed_settings );
			}

			// Store each changed settings as one log entry
			// We could store all in just one, but then we would get
			// problems when outputing formatted output (too many things to show at once)
			// or when gathering stats
			foreach ( $arr_changed_settings as $one_changed_setting ) {
				
				$this->infoMessage(
					"appearance_customized",
					$one_changed_setting
				);

			}
			
		}

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

	function getLogRowDetailsOutput($row) {

		$context = $row->context;
		$message_key = $context["_message_key"];
		$output = "";

		if ( "appearance_customized" == $message_key ) {
			
			//$output .= "<pre>" . print_r($context, true);
			if ( isset( $context["setting_old_value"] ) && isset( $context["setting_new_value"] ) ) {

				$output .= "<table class='SimpleHistoryLogitem__keyValueTable'>";

				$output .= sprintf(
					'
					<tr>
						<td>%1$s</td>
						<td>%2$s</td>
					</tr>
					',
					__("Section", "simple-history"),
					esc_html( $context["section_id"] )
				);

				// Don't output prev and new value if none exist
				if ( empty( $context["setting_old_value"] ) && empty( $context["setting_new_value"] ) ) {
				} else {

					// if control is color let's be fancy and output as color
					$control_type = isset( $context["control_type"] ) ? $context["control_type"] : "";
					$str_old_value_prepend = "";
					$str_new_value_prepend = "";

					if ("color" == $control_type) {

						$str_old_value_prepend .= sprintf(
							'<span style="background-color: #%1$s; width: 1em; display: inline-block;">&nbsp;</span> ',
							esc_attr( $context["setting_old_value"] )
						);

						$str_new_value_prepend .= sprintf(
							'<span style="background-color: #%1$s; width: 1em; display: inline-block;">&nbsp;</span> ',
							esc_attr( $context["setting_new_value"] )
						);

					}

					$output .= sprintf(
						'
						<tr>
							<td>%1$s</td>
							<td>%3$s%2$s</td>
						</tr>
						',
						__("New value", "simple-history"),
						esc_html( $context["setting_new_value"] ),
						$str_new_value_prepend
					);

					$output .= sprintf(
						'
						<tr>
							<td>%1$s</td>
							<td>%3$s%2$s</td>
						</tr>
						',
						__("Old value", "simple-history"),
						esc_html( $context["setting_old_value"] ),
						$str_old_value_prepend
					);

				
				}
				
				$output .= "</table>";

			}
			


		}

		return $output;

	}


	/*
	Log Widget Changes in Apperance » Widgets
	
	# adding widget:
	only 1 widget

	widget-archives[5][title]:
	widget-id:archives-5
	id_base:archives
	widget-width:250
	widget-height:200
	widget_number:2
	multi_number:5
	add_new:multi
	action:save-widget
	savewidgets:b4b438fa4f
	sidebar:sidebar-3


	# saving widget
	only 1 widget

	widget-archives[5][title]:xxxxxxxx
	widget-id:archives-5
	id_base:archives
	widget-width:250
	widget-height:200
	widget_number:2
	multi_number:5
	add_new:
	action:save-widget
	savewidgets:b4b438fa4f
	sidebar:sidebar-3

	
	# changing order
	action:widgets-order
	savewidgets:b4b438fa4f
	sidebars[wp_inactive_widgets]:
	sidebars[sidebar-1]:widget-19_recent-posts-2,widget-20_search-2,widget-21_recent-comments-2,widget-22_archives-2,widget-23_categories-2,widget-24_meta-2
	sidebars[sidebar-2]:
	sidebars[sidebar-3]:widget-3_calendar-2,widget-1_archives-5,widget-3_calendar-3,widget-7_icl_lang_sel_widget-2

	

	*/


	// widget_deleted
	function on_action_sidebar_admin_setup() {

		/*
	
		# deleting widget
		widget-meta[3][title]:
		widget-id:meta-3
		id_base:meta
		widget-width:250
		widget-height:200
		widget_number:2
		multi_number:3
		add_new:
		action:save-widget
		savewidgets:b4b438fa4f
		sidebar:sidebar-3
		delete_widget:1

		*/

		// Widget was deleted
		if ( isset( $_POST["delete_widget"] ) ) {
			
			$context = array();

			// Add widget info
			$widget_id_base = $_POST["id_base"];
			$context["widget_id_base"] = $widget_id_base;
			$widget = $this->getWidgetByIdBase( $widget_id_base );
			if ($widget) {
				$context["widget_name_translated"] = $widget->name;
			}

			// Add sidebar info
			$sidebar_id = $_POST["sidebar"];
			$context["sidebar_id"] = $sidebar_id;
			$sidebar = $this->getSidebarById( $sidebar_id );
			if ($sidebar) {
				$context["sidebar_name_translated"] = $sidebar["name"];
			}
			
			$this->infoMessage(
				"widget_deleted",
				$context
			);

		}

	}

	/**
	 * Get a sidebar by id
	 *
	 * @param string $sidebar_id
	 * @return sidebar info or false on failure
	 */
	function getSidebarById($sidebar_id) {

		$sidebars = isset( $GLOBALS['wp_registered_sidebars'] ) ? $GLOBALS['wp_registered_sidebars'] : false;

		if ( ! $sidebars ) {
			return false;
		}
				
		// Add sidebar info
		if ( isset( $sidebars[ $sidebar_id ] ) ) {
			
			return $sidebars[ $sidebar_id ];
		
		}

		return false;

	}

	/**
	 * Get an widget by id's id_base
	 *
	 * @param string $id_base
	 * @return wp_widget object or false on failure
	 */
	function getWidgetByIdBase($widget_id_base) {

		$widget_factory = isset( $GLOBALS["wp_widget_factory"] ) ? $GLOBALS["wp_widget_factory"] : false;

		if ( ! $widget_factory ) {
			return false;
		}

		foreach ($widget_factory->widgets as $one_widget) {
			
			if ( $one_widget->id_base == $widget_id_base ) {

				return $one_widget;

			}

		}

		return false;

	}

}
