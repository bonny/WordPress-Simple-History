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
				'appearance_customized' => __('Customized theme appearance "{setting_id}"', "simple-history")
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

		// Theme customization from front page
		//add_action("wp_ajax_customize_save", array( $this, "on_customize_save" ));
		
		add_action("customize_save", array( $this, "on_action_customize_save" ));
		// do_action( 'customize_save', $this );
		// do_action( 'customize_save_after', $this );

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
	 * Ajax called when theme customization is saved from frontend
	 */
	function on_customize_save() {

		$this->infoMessage(
			"appearance_customized"
		);
		//sf_d($_REQUEST, '$_REQUEST');exit;
		/*
		$_REQUEST:
		Array
		(
		    [wp_customize] =&gt; on
		    [theme] =&gt; make
		    [customized] =&gt; {\&quot;widget_pages[1]\&quot;:{\&quot;encoded_serialized_instance\&quot;:\&quot;YTowOnt9\&quot;,\&quot;title\&quot;:\&quot;\&quot;,\&quot;is_widget_customizer_js_value\&quot;:true,\&quot;instance_hash_key\&quot;:\&quot;1eb11012ea65a32e655e37f998225608\&quot;},\&quot;widget_calendar[1]\&quot;:{\&quot;encoded_serialized_instance\&quot;:\&quot;YTowOnt9\&quot;,\&quot;title\&quot;:\&quot;\&quot;,\&quot;is_widget_customizer_js_value\&quot;:true,\&quot;instance_hash_key\&quot;:\&quot;1eb11012ea65a32e655e37f998225608\&quot;},\&quot;widget_archives[2]\&quot;:{\&quot;encoded_serialized_instance\&quot;:\&quot;YTozOntzOjU6InRpdGxlIjtzOjA6IiI7czo1OiJjb3VudCI7aTowO3M6ODoiZHJvcGRvd24iO2k6MDt9\&quot;,\&quot;title\&quot;:\&quot;\&quot;,\&quot;is_widget_customizer_js_value\&quot;:true,\&quot;instance_hash_key\&quot;:\&quot;3480afa3934342872c740122c4988ab5\&quot;},\&quot;widget_meta[2]\&quot;:{\&quot;encoded_serialized_instance\&quot;:\&quot;YToxOntzOjU6InRpdGxlIjtzOjA6IiI7fQ==\&quot;,\&quot;title\&quot;:\&quot;\&quot;,\&quot;is_widget_customizer_js_value\&quot;:true,\&quot;instance_hash_key\&quot;:\&quot;b518e607928dcfc07867f25e07a3a875\&quot;},\&quot;widget_search[2]\&quot;:{\&quot;encoded_serialized_instance\&quot;:\&quot;YToxOntzOjU6InRpdGxlIjtzOjA6IiI7fQ==\&quot;,\&quot;title\&quot;:\&quot;\&quot;,\&quot;is_widget_customizer_js_value\&quot;:true,\&quot;instance_hash_key\&quot;:\&quot;b518e607928dcfc07867f25e07a3a875\&quot;},\&quot;widget_text[1]\&quot;:{\&quot;encoded_serialized_instance\&quot;:\&quot;YTowOnt9\&quot;,\&quot;title\&quot;:\&quot;\&quot;,\&quot;is_widget_customizer_js_value\&quot;:true,\&quot;instance_hash_key\&quot;:\&quot;1eb11012ea65a32e655e37f998225608\&quot;},\&quot;widget_categories[2]\&quot;:{\&quot;encoded_serialized_instance\&quot;:\&quot;YTo0OntzOjU6InRpdGxlIjtzOjA6IiI7czo1OiJjb3VudCI7aTowO3M6MTI6ImhpZXJhcmNoaWNhbCI7aTowO3M6ODoiZHJvcGRvd24iO2k6MDt9\&quot;,\&quot;title\&quot;:\&quot;\&quot;,\&quot;is_widget_customizer_js_value\&quot;:true,\&quot;instance_hash_key\&quot;:\&quot;e578c90cab347a6903f41fa6b0056c5c\&quot;},\&quot;widget_recent-posts[2]\&quot;:{\&quot;encoded_serialized_instance\&quot;:\&quot;YToyOntzOjU6InRpdGxlIjtzOjA6IiI7czo2OiJudW1iZXIiO2k6NTt9\&quot;,\&quot;title\&quot;:\&quot;\&quot;,\&quot;is_widget_customizer_js_value\&quot;:true,\&quot;instance_hash_key\&quot;:\&quot;847dac37aea911af1c22e480eae1ffc2\&quot;},\&quot;widget_recent-comments[2]\&quot;:{\&quot;encoded_serialized_instance\&quot;:\&quot;YToyOntzOjU6InRpdGxlIjtzOjA6IiI7czo2OiJudW1iZXIiO2k6NTt9\&quot;,\&quot;title\&quot;:\&quot;\&quot;,\&quot;is_widget_customizer_js_value\&quot;:true,\&quot;instance_hash_key\&quot;:\&quot;847dac37aea911af1c22e480eae1ffc2\&quot;},\&quot;widget_rss[1]\&quot;:{\&quot;encoded_serialized_instance\&quot;:\&quot;YTowOnt9\&quot;,\&quot;title\&quot;:\&quot;\&quot;,\&quot;is_widget_customizer_js_value\&quot;:true,\&quot;instance_hash_key\&quot;:\&quot;1eb11012ea65a32e655e37f998225608\&quot;},\&quot;widget_tag_cloud[1]\&quot;:{\&quot;encoded_serialized_instance\&quot;:\&quot;YTowOnt9\&quot;,\&quot;title\&quot;:\&quot;\&quot;,\&quot;is_widget_customizer_js_value\&quot;:true,\&quot;instance_hash_key\&quot;:\&quot;1eb11012ea65a32e655e37f998225608\&quot;},\&quot;widget_nav_menu[1]\&quot;:{\&quot;encoded_serialized_instance\&quot;:\&quot;YTowOnt9\&quot;,\&quot;title\&quot;:\&quot;\&quot;,\&quot;is_widget_customizer_js_value\&quot;:true,\&quot;instance_hash_key\&quot;:\&quot;1eb11012ea65a32e655e37f998225608\&quot;},\&quot;widget_caldera_forms_widget[1]\&quot;:{\&quot;encoded_serialized_instance\&quot;:\&quot;YTowOnt9\&quot;,\&quot;title\&quot;:\&quot;\&quot;,\&quot;is_widget_customizer_js_value\&quot;:true,\&quot;instance_hash_key\&quot;:\&quot;1eb11012ea65a32e655e37f998225608\&quot;},\&quot;widget_edd_cart_widget[1]\&quot;:{\&quot;encoded_serialized_instance\&quot;:\&quot;YTowOnt9\&quot;,\&quot;title\&quot;:\&quot;\&quot;,\&quot;is_widget_customizer_js_value\&quot;:true,\&quot;instance_hash_key\&quot;:\&quot;1eb11012ea65a32e655e37f998225608\&quot;},\&quot;widget_edd_categories_tags_widget[1]\&quot;:{\&quot;encoded_serialized_instance\&quot;:\&quot;YTowOnt9\&quot;,\&quot;title\&quot;:\&quot;\&quot;,\&quot;is_widget_customizer_js_value\&quot;:true,\&quot;instance_hash_key\&quot;:\&quot;1eb11012ea65a32e655e37f998225608\&quot;},\&quot;widget_edd_product_details[1]\&quot;:{\&quot;encoded_serialized_instance\&quot;:\&quot;YTowOnt9\&quot;,\&quot;title\&quot;:\&quot;\&quot;,\&quot;is_widget_customizer_js_value\&quot;:true,\&quot;instance_hash_key\&quot;:\&quot;1eb11012ea65a32e655e37f998225608\&quot;},\&quot;widget_icl_lang_sel_widget[1]\&quot;:{\&quot;encoded_serialized_instance\&quot;:\&quot;YTowOnt9\&quot;,\&quot;title\&quot;:\&quot;\&quot;,\&quot;is_widget_customizer_js_value\&quot;:true,\&quot;instance_hash_key\&quot;:\&quot;1eb11012ea65a32e655e37f998225608\&quot;},\&quot;sidebars_widgets[wp_inactive_widgets]\&quot;:[],\&quot;sidebars_widgets[sidebar-left]\&quot;:[\&quot;search-2\&quot;,\&quot;recent-posts-2\&quot;,\&quot;recent-comments-2\&quot;,\&quot;archives-2\&quot;,\&quot;categories-2\&quot;,\&quot;meta-2\&quot;],\&quot;sidebars_widgets[sidebar-right]\&quot;:[],\&quot;sidebars_widgets[footer-1]\&quot;:[],\&quot;sidebars_widgets[footer-2]\&quot;:[],\&quot;sidebars_widgets[footer-3]\&quot;:[],\&quot;sidebars_widgets[footer-4]\&quot;:[],\&quot;blogname\&quot;:\&quot;Root Playground b\&quot;,\&quot;blogdescription\&quot;:\&quot;Just another WordPress site\&quot;,\&quot;header_textcolor\&quot;:false,\&quot;background_color\&quot;:\&quot;#dd3333\&quot;,\&quot;header_image\&quot;:false,\&quot;header_image_data\&quot;:\&quot;\&quot;,\&quot;background_image\&quot;:\&quot;\&quot;,\&quot;background_image_thumb\&quot;:\&quot;\&quot;,\&quot;background_repeat\&quot;:\&quot;repeat\&quot;,\&quot;background_position_x\&quot;:\&quot;left\&quot;,\&quot;background_attachment\&quot;:\&quot;fixed\&quot;,\&quot;nav_menu_locations[primary]\&quot;:\&quot;\&quot;,\&quot;nav_menu_locations[social]\&quot;:\&quot;\&quot;,\&quot;show_on_front\&quot;:\&quot;posts\&quot;,\&quot;page_on_front\&quot;:\&quot;0\&quot;,\&quot;page_for_posts\&quot;:\&quot;0\&quot;,\&quot;general-layout\&quot;:\&quot;full-width\&quot;,\&quot;general-sticky-label\&quot;:\&quot;Featured\&quot;,\&quot;font-site-title\&quot;:\&quot;sans-serif\&quot;,\&quot;font-header\&quot;:\&quot;sans-serif\&quot;,\&quot;font-body\&quot;:\&quot;Open Sans\&quot;,\&quot;font-site-title-size\&quot;:\&quot;30\&quot;,\&quot;font-site-tagline-size\&quot;:\&quot;12\&quot;,\&quot;font-nav-size\&quot;:14,\&quot;font-header-size\&quot;:50,\&quot;font-widget-size\&quot;:13,\&quot;font-body-size\&quot;:17,\&quot;font-subset\&quot;:\&quot;latin\&quot;,\&quot;color-primary\&quot;:\&quot;#2fce6f\&quot;,\&quot;color-secondary\&quot;:\&quot;#eaecee\&quot;,\&quot;color-text\&quot;:\&quot;#171717\&quot;,\&quot;color-detail\&quot;:\&quot;#b9bcbf\&quot;,\&quot;header-layout\&quot;:1,\&quot;header-branding-position\&quot;:\&quot;left\&quot;,\&quot;header-bar-content-layout\&quot;:\&quot;default\&quot;,\&quot;header-text\&quot;:\&quot;\&quot;,\&quot;header-bar-text-color\&quot;:\&quot;#ffffff\&quot;,\&quot;header-bar-border-color\&quot;:\&quot;#171717\&quot;,\&quot;header-bar-background-color\&quot;:\&quot;#171717\&quot;,\&quot;header-text-color\&quot;:\&quot;#171717\&quot;,\&quot;header-background-color\&quot;:\&quot;#ffffff\&quot;,\&quot;header-background-image\&quot;:\&quot;\&quot;,\&quot;header-background-repeat\&quot;:\&quot;no-repeat\&quot;,\&quot;header-background-position\&quot;:\&quot;center\&quot;,\&quot;header-background-size\&quot;:\&quot;cover\&quot;,\&quot;header-show-social\&quot;:0,\&quot;header-show-search\&quot;:1,\&quot;logo-regular\&quot;:\&quot;\&quot;,\&quot;logo-retina\&quot;:\&quot;\&quot;,\&quot;logo-favicon\&quot;:\&quot;\&quot;,\&quot;logo-apple-touch\&quot;:\&quot;\&quot;,\&quot;main-background-color\&quot;:\&quot;#ffffff\&quot;,\&quot;main-background-image\&quot;:\&quot;\&quot;,\&quot;main-background-repeat\&quot;:\&quot;repeat\&quot;,\&quot;main-background-position\&quot;:\&quot;left\&quot;,\&quot;main-background-size\&quot;:\&quot;auto\&quot;,\&quot;main-content-link-underline\&quot;:0,\&quot;layout-blog-hide-header\&quot;:0,\&quot;layout-blog-hide-footer\&quot;:0,\&quot;layout-blog-sidebar-left\&quot;:0,\&quot;layout-blog-sidebar-right\&quot;:1,\&quot;layout-blog-featured-images\&quot;:\&quot;post-header\&quot;,\&quot;layout-blog-post-date\&quot;:\&quot;absolute\&quot;,\&quot;layout-blog-post-author\&quot;:\&quot;avatar\&quot;,\&quot;layout-blog-auto-excerpt\&quot;:0,\&quot;layout-blog-show-categories\&quot;:1,\&quot;layout-blog-show-tags\&quot;:1,\&quot;layout-archive-hide-header\&quot;:0,\&quot;layout-archive-hide-footer\&quot;:0,\&quot;layout-archive-sidebar-left\&quot;:0,\&quot;layout-archive-sidebar-right\&quot;:1,\&quot;layout-archive-featured-images\&quot;:\&quot;post-header\&quot;,\&quot;layout-archive-post-date\&quot;:\&quot;absolute\&quot;,\&quot;layout-archive-post-author\&quot;:\&quot;avatar\&quot;,\&quot;layout-archive-auto-excerpt\&quot;:0,\&quot;layout-archive-show-categories\&quot;:1,\&quot;layout-archive-show-tags\&quot;:1,\&quot;layout-search-hide-header\&quot;:0,\&quot;layout-search-hide-footer\&quot;:0,\&quot;layout-search-sidebar-left\&quot;:0,\&quot;layout-search-sidebar-right\&quot;:1,\&quot;layout-search-featured-images\&quot;:\&quot;thumbnail\&quot;,\&quot;layout-search-post-date\&quot;:\&quot;absolute\&quot;,\&quot;layout-search-post-author\&quot;:\&quot;name\&quot;,\&quot;layout-search-auto-excerpt\&quot;:1,\&quot;layout-search-show-categories\&quot;:1,\&quot;layout-search-show-tags\&quot;:1,\&quot;layout-post-hide-header\&quot;:0,\&quot;layout-post-hide-footer\&quot;:0,\&quot;layout-post-sidebar-left\&quot;:0,\&quot;layout-post-sidebar-right\&quot;:0,\&quot;layout-post-featured-images\&quot;:\&quot;post-header\&quot;,\&quot;layout-post-post-date\&quot;:\&quot;absolute\&quot;,\&quot;layout-post-post-author\&quot;:\&quot;avatar\&quot;,\&quot;layout-post-show-categories\&quot;:1,\&quot;layout-post-show-tags\&quot;:1,\&quot;layout-page-hide-header\&quot;:0,\&quot;layout-page-hide-footer\&quot;:0,\&quot;layout-page-sidebar-left\&quot;:0,\&quot;layout-page-sidebar-right\&quot;:0,\&quot;layout-page-hide-title\&quot;:1,\&quot;layout-page-featured-images\&quot;:\&quot;none\&quot;,\&quot;layout-page-post-date\&quot;:\&quot;none\&quot;,\&quot;layout-page-post-author\&quot;:\&quot;none\&quot;,\&quot;footer-layout\&quot;:1,\&quot;footer-text\&quot;:\&quot;\&quot;,\&quot;footer-text-color\&quot;:\&quot;#464849\&quot;,\&quot;footer-border-color\&quot;:\&quot;#b9bcbf\&quot;,\&quot;footer-background-color\&quot;:\&quot;#eaecee\&quot;,\&quot;footer-background-image\&quot;:\&quot;\&quot;,\&quot;footer-background-repeat\&quot;:\&quot;no-repeat\&quot;,\&quot;footer-background-position\&quot;:\&quot;center\&quot;,\&quot;footer-background-size\&quot;:\&quot;cover\&quot;,\&quot;footer-widget-areas\&quot;:3,\&quot;footer-show-social\&quot;:1,\&quot;social-facebook\&quot;:\&quot;\&quot;,\&quot;social-twitter\&quot;:\&quot;\&quot;,\&quot;social-google-plus-square\&quot;:\&quot;\&quot;,\&quot;social-linkedin\&quot;:\&quot;\&quot;,\&quot;social-instagram\&quot;:\&quot;\&quot;,\&quot;social-flickr\&quot;:\&quot;\&quot;,\&quot;social-youtube\&quot;:\&quot;\&quot;,\&quot;social-vimeo-square\&quot;:\&quot;\&quot;,\&quot;social-pinterest\&quot;:\&quot;\&quot;,\&quot;social-email\&quot;:\&quot;\&quot;,\&quot;social-hide-rss\&quot;:0,\&quot;social-custom-rss\&quot;:\&quot;\&quot;,\&quot;background_size\&quot;:\&quot;auto\&quot;,\&quot;navigation-mobile-label\&quot;:\&quot;Menu\&quot;,\&quot;color-site-title\&quot;:\&quot;#171717\&quot;,\&quot;hide-site-title\&quot;:0,\&quot;hide-tagline\&quot;:0}
		    [nonce] =&gt; 928d98f761
		    [action] =&gt; customize_save
		)
		*/

		//print_r( json_decode( stripslashes( $_POST["customized"] ) ) );exit;
		/*
		stdClass Object
		(
		    [widget_pages[1]] => stdClass Object
		        (
		            [encoded_serialized_instance] => YTowOnt9
		            [title] => 
		            [is_widget_customizer_js_value] => 1
		            [instance_hash_key] => 1eb11012ea65a32e655e37f998225608
		        )

		    [widget_calendar[1]] => stdClass Object
		        (
		            [encoded_serialized_instance] => YTowOnt9
		            [title] => 
		            [is_widget_customizer_js_value] => 1
		            [instance_hash_key] => 1eb11012ea65a32e655e37f998225608
		        )

		    [widget_archives[2]] => stdClass Object
		        (
		            [encoded_serialized_instance] => YTozOntzOjU6InRpdGxlIjtzOjA6IiI7czo1OiJjb3VudCI7aTowO3M6ODoiZHJvcGRvd24iO2k6MDt9
		            [title] => 
		            [is_widget_customizer_js_value] => 1
		            [instance_hash_key] => 3480afa3934342872c740122c4988ab5
		        )

		    [widget_meta[2]] => stdClass Object
		        (
		            [encoded_serialized_instance] => YToxOntzOjU6InRpdGxlIjtzOjA6IiI7fQ==
		            [title] => 
		            [is_widget_customizer_js_value] => 1
		            [instance_hash_key] => b518e607928dcfc07867f25e07a3a875
		        )

		    [widget_search[2]] => stdClass Object
		        (
		            [encoded_serialized_instance] => YToxOntzOjU6InRpdGxlIjtzOjA6IiI7fQ==
		            [title] => 
		            [is_widget_customizer_js_value] => 1
		            [instance_hash_key] => b518e607928dcfc07867f25e07a3a875
		        )

		    [widget_text[1]] => stdClass Object
		        (
		            [encoded_serialized_instance] => YTowOnt9
		            [title] => 
		            [is_widget_customizer_js_value] => 1
		            [instance_hash_key] => 1eb11012ea65a32e655e37f998225608
		        )

		    [widget_categories[2]] => stdClass Object
		        (
		            [encoded_serialized_instance] => YTo0OntzOjU6InRpdGxlIjtzOjA6IiI7czo1OiJjb3VudCI7aTowO3M6MTI6ImhpZXJhcmNoaWNhbCI7aTowO3M6ODoiZHJvcGRvd24iO2k6MDt9
		            [title] => 
		            [is_widget_customizer_js_value] => 1
		            [instance_hash_key] => e578c90cab347a6903f41fa6b0056c5c
		        )

		    [widget_recent-posts[2]] => stdClass Object
		        (
		            [encoded_serialized_instance] => YToyOntzOjU6InRpdGxlIjtzOjA6IiI7czo2OiJudW1iZXIiO2k6NTt9
		            [title] => 
		            [is_widget_customizer_js_value] => 1
		            [instance_hash_key] => 847dac37aea911af1c22e480eae1ffc2
		        )

		    [widget_recent-comments[2]] => stdClass Object
		        (
		            [encoded_serialized_instance] => YToyOntzOjU6InRpdGxlIjtzOjA6IiI7czo2OiJudW1iZXIiO2k6NTt9
		            [title] => 
		            [is_widget_customizer_js_value] => 1
		            [instance_hash_key] => 847dac37aea911af1c22e480eae1ffc2
		        )

		    [widget_rss[1]] => stdClass Object
		        (
		            [encoded_serialized_instance] => YTowOnt9
		            [title] => 
		            [is_widget_customizer_js_value] => 1
		            [instance_hash_key] => 1eb11012ea65a32e655e37f998225608
		        )

		    [widget_tag_cloud[1]] => stdClass Object
		        (
		            [encoded_serialized_instance] => YTowOnt9
		            [title] => 
		            [is_widget_customizer_js_value] => 1
		            [instance_hash_key] => 1eb11012ea65a32e655e37f998225608
		        )

		    [widget_nav_menu[1]] => stdClass Object
		        (
		            [encoded_serialized_instance] => YTowOnt9
		            [title] => 
		            [is_widget_customizer_js_value] => 1
		            [instance_hash_key] => 1eb11012ea65a32e655e37f998225608
		        )

		    [widget_caldera_forms_widget[1]] => stdClass Object
		        (
		            [encoded_serialized_instance] => YTowOnt9
		            [title] => 
		            [is_widget_customizer_js_value] => 1
		            [instance_hash_key] => 1eb11012ea65a32e655e37f998225608
		        )

		    [widget_edd_cart_widget[1]] => stdClass Object
		        (
		            [encoded_serialized_instance] => YTowOnt9
		            [title] => 
		            [is_widget_customizer_js_value] => 1
		            [instance_hash_key] => 1eb11012ea65a32e655e37f998225608
		        )

		    [widget_edd_categories_tags_widget[1]] => stdClass Object
		        (
		            [encoded_serialized_instance] => YTowOnt9
		            [title] => 
		            [is_widget_customizer_js_value] => 1
		            [instance_hash_key] => 1eb11012ea65a32e655e37f998225608
		        )

		    [widget_edd_product_details[1]] => stdClass Object
		        (
		            [encoded_serialized_instance] => YTowOnt9
		            [title] => 
		            [is_widget_customizer_js_value] => 1
		            [instance_hash_key] => 1eb11012ea65a32e655e37f998225608
		        )

		    [widget_icl_lang_sel_widget[1]] => stdClass Object
		        (
		            [encoded_serialized_instance] => YTowOnt9
		            [title] => 
		            [is_widget_customizer_js_value] => 1
		            [instance_hash_key] => 1eb11012ea65a32e655e37f998225608
		        )

		    [sidebars_widgets[wp_inactive_widgets]] => Array
		        (
		        )

		    [sidebars_widgets[sidebar-left]] => Array
		        (
		            [0] => search-2
		            [1] => recent-posts-2
		            [2] => recent-comments-2
		            [3] => archives-2
		            [4] => categories-2
		            [5] => meta-2
		        )

		    [sidebars_widgets[sidebar-right]] => Array
		        (
		        )

		    [sidebars_widgets[footer-1]] => Array
		        (
		        )

		    [sidebars_widgets[footer-2]] => Array
		        (
		        )

		    [sidebars_widgets[footer-3]] => Array
		        (
		        )

		    [sidebars_widgets[footer-4]] => Array
		        (
		        )

		    [blogname] => Root Playground i
		    [blogdescription] => Just another WordPress site
		    [header_textcolor] => 
		    [background_color] => #dd3333
		    [header_image] => 
		    [header_image_data] => 
		    [background_image] => 
		    [background_image_thumb] => 
		    [background_repeat] => repeat
		    [background_position_x] => left
		    [background_attachment] => fixed
		    [nav_menu_locations[primary]] => 
		    [nav_menu_locations[social]] => 
		    [show_on_front] => posts
		    [page_on_front] => 0
		    [page_for_posts] => 0
		    [general-layout] => full-width
		    [general-sticky-label] => Featured
		    [font-site-title] => sans-serif
		    [font-header] => sans-serif
		    [font-body] => Open Sans
		    [font-site-title-size] => 30
		    [font-site-tagline-size] => 12
		    [font-nav-size] => 14
		    [font-header-size] => 50
		    [font-widget-size] => 13
		    [font-body-size] => 17
		    [font-subset] => latin
		    [color-primary] => #2fce6f
		    [color-secondary] => #eaecee
		    [color-text] => #171717
		    [color-detail] => #b9bcbf
		    [header-layout] => 1
		    [header-branding-position] => left
		    [header-bar-content-layout] => default
		    [header-text] => 
		    [header-bar-text-color] => #ffffff
		    [header-bar-border-color] => #171717
		    [header-bar-background-color] => #171717
		    [header-text-color] => #171717
		    [header-background-color] => #ffffff
		    [header-background-image] => 
		    [header-background-repeat] => no-repeat
		    [header-background-position] => center
		    [header-background-size] => cover
		    [header-show-social] => 0
		    [header-show-search] => 1
		    [logo-regular] => 
		    [logo-retina] => 
		    [logo-favicon] => 
		    [logo-apple-touch] => 
		    [main-background-color] => #ffffff
		    [main-background-image] => 
		    [main-background-repeat] => repeat
		    [main-background-position] => left
		    [main-background-size] => auto
		    [main-content-link-underline] => 0
		    [layout-blog-hide-header] => 0
		    [layout-blog-hide-footer] => 0
		    [layout-blog-sidebar-left] => 0
		    [layout-blog-sidebar-right] => 1
		    [layout-blog-featured-images] => post-header
		    [layout-blog-post-date] => absolute
		    [layout-blog-post-author] => avatar
		    [layout-blog-auto-excerpt] => 0
		    [layout-blog-show-categories] => 1
		    [layout-blog-show-tags] => 1
		    [layout-archive-hide-header] => 0
		    [layout-archive-hide-footer] => 0
		    [layout-archive-sidebar-left] => 0
		    [layout-archive-sidebar-right] => 1
		    [layout-archive-featured-images] => post-header
		    [layout-archive-post-date] => absolute
		    [layout-archive-post-author] => avatar
		    [layout-archive-auto-excerpt] => 0
		    [layout-archive-show-categories] => 1
		    [layout-archive-show-tags] => 1
		    [layout-search-hide-header] => 0
		    [layout-search-hide-footer] => 0
		    [layout-search-sidebar-left] => 0
		    [layout-search-sidebar-right] => 1
		    [layout-search-featured-images] => thumbnail
		    [layout-search-post-date] => absolute
		    [layout-search-post-author] => name
		    [layout-search-auto-excerpt] => 1
		    [layout-search-show-categories] => 1
		    [layout-search-show-tags] => 1
		    [layout-post-hide-header] => 0
		    [layout-post-hide-footer] => 0
		    [layout-post-sidebar-left] => 0
		    [layout-post-sidebar-right] => 0
		    [layout-post-featured-images] => post-header
		    [layout-post-post-date] => absolute
		    [layout-post-post-author] => avatar
		    [layout-post-show-categories] => 1
		    [layout-post-show-tags] => 1
		    [layout-page-hide-header] => 0
		    [layout-page-hide-footer] => 0
		    [layout-page-sidebar-left] => 0
		    [layout-page-sidebar-right] => 0
		    [layout-page-hide-title] => 1
		    [layout-page-featured-images] => none
		    [layout-page-post-date] => none
		    [layout-page-post-author] => none
		    [footer-layout] => 1
		    [footer-text] => 
		    [footer-text-color] => #464849
		    [footer-border-color] => #b9bcbf
		    [footer-background-color] => #eaecee
		    [footer-background-image] => 
		    [footer-background-repeat] => no-repeat
		    [footer-background-position] => center
		    [footer-background-size] => cover
		    [footer-widget-areas] => 3
		    [footer-show-social] => 1
		    [social-facebook] => 
		    [social-twitter] => 
		    [social-google-plus-square] => 
		    [social-linkedin] => 
		    [social-instagram] => 
		    [social-flickr] => 
		    [social-youtube] => 
		    [social-vimeo-square] => 
		    [social-pinterest] => 
		    [social-email] => 
		    [social-hide-rss] => 0
		    [social-custom-rss] => 
		    [background_size] => auto
		    [navigation-mobile-label] => Menu
		    [color-site-title] => #171717
		    [hide-site-title] => 0
		    [hide-tagline] => 0
		)
		*/

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
