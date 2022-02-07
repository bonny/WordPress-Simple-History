<?php

defined( 'ABSPATH' ) || die();

/**
 * Logs WordPress theme edits
 */
class SimpleThemeLogger extends SimpleLogger {

	public $slug = __CLASS__;

	// When switching themes, this will contain info about the theme we are switching from
	private $prev_theme_data;

	/**
	 * Used to collect information about a theme before it is deleted.
	 * Theme info is stored with css file as the key.
	 *
	 * @var array
	 */
	protected $themes_data = array();

	/**
	 * Get array with information about this logger
	 *
	 * @return array
	 */
	public function getInfo() {

		$arr_info = array(
			'name'        => __( 'Theme Logger', 'simple-history' ),
			'description' => __( 'Logs theme edits', 'simple-history' ),
			'capability'  => 'edit_theme_options',
			'messages'    => array(
				'theme_switched'            => __( 'Switched theme to "{theme_name}" from "{prev_theme_name}"', 'simple-history' ),
				'theme_installed'           => __( 'Installed theme "{theme_name}" by {theme_author}', 'simple-history' ),
				'theme_deleted'             => __( 'Deleted theme "{theme_name}"', 'simple-history' ),
				'theme_updated'             => __( 'Updated theme "{theme_name}"', 'simple-history' ),
				'appearance_customized'     => __( 'Customized theme appearance "{setting_id}"', 'simple-history' ),
				'widget_removed'            => __( 'Removed widget "{widget_id_base}" from sidebar "{sidebar_id}"', 'simple-history' ),
				'widget_added'              => __( 'Added widget "{widget_id_base}" to sidebar "{sidebar_id}"', 'simple-history' ),
				'widget_order_changed'      => __( 'Changed widget order "{widget_id_base}" in sidebar "{sidebar_id}"', 'simple-history' ),
				'widget_edited'             => __( 'Changed widget "{widget_id_base}" in sidebar "{sidebar_id}"', 'simple-history' ),
				'custom_background_changed' => __( 'Changed settings for the theme custom background', 'simple-history' ),
			),
			'labels'      => array(
				'search' => array(
					'label'     => _x( 'Themes & Widgets', 'Theme logger: search', 'simple-history' ),
					'label_all' => _x( 'All theme activity', 'Theme logger: search', 'simple-history' ),
					'options'   => array(
						_x( 'Updated themes', 'Theme logger: search', 'simple-history' ) => array(
							'theme_updated',
						),
						_x( 'Deleted themes', 'Theme logger: search', 'simple-history' ) => array(
							'theme_deleted',
						),
						_x( 'Installed themes', 'Theme logger: search', 'simple-history' ) => array(
							'theme_installed',
						),
						_x( 'Switched themes', 'Theme logger: search', 'simple-history' ) => array(
							'theme_switched',
						),
						_x( 'Changed appearance of themes', 'Theme logger: search', 'simple-history' ) => array(
							'appearance_customized',
						),
						_x( 'Added widgets', 'Theme logger: search', 'simple-history' ) => array(
							'widget_added',
						),
						_x( 'Removed widgets', 'Theme logger: search', 'simple-history' ) => array(
							'widget_removed',
						),
						_x( 'Changed widgets order', 'Theme logger: search', 'simple-history' ) => array(
							'widget_order_changed',
						),
						_x( 'Edited widgets', 'Theme logger: search', 'simple-history' ) => array(
							'widget_edited',
						),
						_x( 'Background of themes changed', 'Theme logger: search', 'simple-history' ) => array(
							'custom_background_changed',
						),
					),
				), // end search array
			), // end labels

		);

		return $arr_info;
	}

	public function loaded() {

		/**
		 * Fires after the theme is switched.
		 *
		 * @param string   $new_name  Name of the new theme.
		 * @param WP_Theme $new_theme WP_Theme instance of the new theme.
		 */
		add_action( 'switch_theme', array( $this, 'on_switch_theme' ), 10, 2 );
		add_action( 'load-themes.php', array( $this, 'on_page_load_themes' ) );

		add_action( 'customize_save', array( $this, 'on_action_customize_save' ) );

		add_action( 'sidebar_admin_setup', array( $this, 'on_action_sidebar_admin_setup__detect_widget_delete' ) );
		add_action( 'sidebar_admin_setup', array( $this, 'on_action_sidebar_admin_setup__detect_widget_add' ) );
		// add_action("wp_ajax_widgets-order", array( $this, "on_action_sidebar_admin_setup__detect_widget_order_change"), 1 );
		// add_action("sidebar_admin_setup", array( $this, "on_action_sidebar_admin_setup__detect_widget_edit") );
		add_filter( 'widget_update_callback', array( $this, 'on_widget_update_callback' ), 10, 4 );

		add_action( 'load-appearance_page_custom-background', array( $this, 'on_page_load_custom_background' ) );

		add_action( 'upgrader_process_complete', array( $this, 'on_upgrader_process_complete_theme_install' ), 10, 2 );
		add_action( 'upgrader_process_complete', array( $this, 'on_upgrader_process_complete_theme_update' ), 10, 2 );

		// Log theme deletion.
		add_action( 'delete_theme', array( $this, 'on_action_delete_theme' ), 10, 1 );
		add_action( 'deleted_theme', array( $this, 'on_action_deleted_theme' ), 10, 2 );
		/*
		$this->infoMessage(
			'theme_deleted',
			array(
				'theme_slug' => $theme_deleted_slug,
			)
		);
		wp_get_theme( $one_updated_theme );
		*/
	}

	/**
	 * Store information about a theme before the theme is deleted.
	 *
	 * @param string $stylesheet Stylesheet of the theme to delete.
	 * @return void
	 */
	public function on_action_delete_theme( $stylesheet ) {
		$theme = wp_get_theme( $stylesheet );

		$this->themes_data[ $stylesheet ] = array(
			'name' => $theme->get( 'Name' ),
			'version' => $theme->get( 'Version' ),
			'author' => $theme->get( 'Author' ),
			'description' => $theme->get( 'Description' ),
			'themeuri' => $theme->get( 'ThemeURI' ),
		);
	}

	/**
	 * Log theme deletion.
	 *
	 * @param string $stylesheet Stylesheet of the theme to delete.
	 * @param bool   $deleted    Whether the theme deletion was successful.
	 * @return void
	 */
	public function on_action_deleted_theme( $stylesheet, $deleted ) {
		if ( ! $deleted || ! $stylesheet ) {
			return;
		}

		if ( empty( $this->themes_data[ $stylesheet ] ) ) {
			return;
		}

		$theme_data = $this->themes_data[ $stylesheet ];

		$this->infoMessage(
			'theme_deleted',
			array(
				'theme_slug' => $stylesheet,
				'theme_name' => $theme_data['name'],
				'theme_version' => $theme_data['version'],
				'theme_author' => $theme_data['author'],
				'theme_description' => $theme_data['description'],
			)
		);
	}

	public function on_upgrader_process_complete_theme_update( $upgrader_instance = null, $arr_data = null ) {

		/*
		For theme updates $arr_data looks like

		// From core update/bulk
		Array
		(
			[action] => update
			[type] => theme
			[bulk] => 1
			[themes] => Array
				(
					[0] => baskerville
				)

		)

		// From themes.php/single
		Array
		(
			[action] => update
			[theme] => baskerville
			[type] => theme
		)
		*/

		// Both args must be set
		if ( empty( $upgrader_instance ) || empty( $arr_data ) ) {
			return;
		}

		// Must be type theme and action install
		if ( $arr_data['type'] !== 'theme' || $arr_data['action'] !== 'update' ) {
			return;
		}

		// Skin contains the nice info
		if ( empty( $upgrader_instance->skin ) ) {
			return;
		}

		$skin = $upgrader_instance->skin;

		$arr_themes = array();

		// If single install make an array so it look like bulk and we can use same code
		if ( isset( $arr_data['bulk'] ) && $arr_data['bulk'] && isset( $arr_data['themes'] ) ) {
			$arr_themes = (array) $arr_data['themes'];
		} else {
			$arr_themes = array(
				$arr_data['theme'],
			);
		}

		/*
		ob_start();
		print_r($skin);
		$skin_str = ob_get_clean();
		echo "<pre>";
		print_r($arr_data);
		print_r($skin);
		// */

		// $one_updated_theme is the theme slug
		foreach ( $arr_themes as $one_updated_theme ) {
			$theme_info_object = wp_get_theme( $one_updated_theme );

			if ( ! is_a( $theme_info_object, 'WP_Theme' ) ) {
				continue;
			}

			$theme_name = $theme_info_object->get( 'Name' );
			$theme_version = $theme_info_object->get( 'Version' );

			if ( ! $theme_name || ! $theme_version ) {
				continue;
			}

			$this->infoMessage(
				'theme_updated',
				array(
					'theme_name' => $theme_name,
					'theme_version' => $theme_version,
				)
			);
		}
	}

	/**
	 * Log theme installation.
	 *
	 * @param mixed $upgrader_instance
	 * @param mixed $arr_data
	 * @return void
	 */
	public function on_upgrader_process_complete_theme_install( $upgrader_instance = null, $arr_data = null ) {
		// Both args must be set.
		if ( empty( $upgrader_instance ) || empty( $arr_data ) ) {
			return;
		}

		// Must be type 'theme' and action 'install'.
		if ( $arr_data['type'] !== 'theme' || $arr_data['action'] !== 'install' ) {
			return;
		}

		if ( empty( $upgrader_instance->new_theme_data ) ) {
			return;
		}

		// $destination_name is the slug (folder name) of the theme.
		$destination_name = $upgrader_instance->result['destination_name'];
		$theme = wp_get_theme( $destination_name );

		if ( ! $theme->exists() ) {
			return;
		}

		$new_theme_data = $upgrader_instance->new_theme_data;

		$this->infoMessage(
			'theme_installed',
			array(
				'theme_slug' => $destination_name,
				'theme_name' => $new_theme_data['Name'],
				'theme_version' => $new_theme_data['Version'],
				'theme_author' => $new_theme_data['Author'],
				'theme_description' => $theme->get( 'Description' ),
			)
		);
	}

	public function on_page_load_custom_background() {

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( empty( $_POST ) ) {
			return;
		}

		$arr_valid_post_keys = array(
			'reset-background' => 1,
			'remove-background' => 1,
			'background-repeat' => 1,
			'background-position-x' => 1,
			'background-attachment' => 1,
			'background-color' => 1,
		);

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$valid_post_key_exists = array_intersect_key( $arr_valid_post_keys, $_POST );

		if ( ! empty( $valid_post_key_exists ) ) {
			$context = array();
			// $context["POST"] = $this->simpleHistory->json_encode( $_POST );
			$this->infoMessage(
				'custom_background_changed',
				$context
			);
		}
	}

	/*
	WP_Customize_Manager $this WP_Customize_Manager instance.
	*/
	public function on_action_customize_save( $customize_manager ) {

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
		/*
		Array
		(
		[wp_customize] => on
		[theme] => make
		[customized] => {\"widget_pages[1]\":{\"encoded_serialized_instance\":\"YTowOnt9\",\"title\":\"\",\"is_widget_customizer_js_value\":true,\"instance_hash_key\":\"1eb11012ea65a32e655e37f998225608\"},\"widget_calendar[1]\":{\"encoded_serialized_instance\":\"YTowOnt9\",\"title\":\"\",\"is_widget_customizer_js_value\":true,\"instance_hash_key\":\"1eb11012ea65a32e655e37f998225608\"},\"widget_calendar[6]\":{\"encoded_serialized_instance\":\"YToxOntzOjU6InRpdGxlIjtzOjE3OiJTZWUgd2hhdCBoYXBwZW5zISI7fQ==\",\"title\":\"See what happens!\",\"is_widget_customizer_js_value\":true,\"instance_hash_key\":\"ca37c1913982fa69bce33f77cef871cd\"},\"widget_calendar[7]\":{\"encoded_serialized_instance\":\"YToxOntzOjU6InRpdGxlIjtzOjg6IkthbGVuZGVyIjt9\",\"title\":\"Kalender\",\"is_widget_customizer_js_value\":true,\"instance_hash_key\":\"c602d6d891e3f7addb11ca21ac142b49\"},\"widget_archives[4]\":{\"encoded_serialized_instance\":\"YTozOntzOjU6InRpdGxlIjtzOjA6IiI7czo1OiJjb3VudCI7aTowO3M6ODoiZHJvcGRvd24iO2k6MDt9\",\"title\":\"\",\"is_widget_customizer_js_value\":true,\"instance_hash_key\":\"3480afa3934342872c740122c4988ab5\"},\"widget_archives[6]\":{\"encoded_serialized_instance\":\"YTowOnt9\",\"title\":\"\",\"is_widget_customizer_js_value\":true,\"instance_hash_key\":\"1eb11012ea65a32e655e37f998225608\"},\"widget_meta[2]\":{\"encoded_serialized_instance\":\"YToxOntzOjU6InRpdGxlIjtzOjA6IiI7fQ==\",\"title\":\"\",\"is_widget_customizer_js_value\":true,\"instance_hash_key\":\"b518e607928dcfc07867f25e07a3a875\"},\"widget_search[1]\":{\"encoded_serialized_instance\":\"YTowOnt9\",\"title\":\"\",\"is_widget_customizer_js_value\":true,\"instance_hash_key\":\"1eb11012ea65a32e655e37f998225608\"},\"widget_text[1]\":{\"encoded_serialized_instance\":\"YTowOnt9\",\"title\":\"\",\"is_widget_customizer_js_value\":true,\"instance_hash_key\":\"1eb11012ea65a32e655e37f998225608\"},\"widget_categories[1]\":{\"encoded_serialized_instance\":\"YTowOnt9\",\"title\":\"\",\"is_widget_customizer_js_value\":true,\"instance_hash_key\":\"1eb11012ea65a32e655e37f998225608\"},\"widget_recent-posts[1]\":{\"encoded_serialized_instance\":\"YTowOnt9\",\"title\":\"\",\"is_widget_customizer_js_value\":true,\"instance_hash_key\":\"1eb11012ea65a32e655e37f998225608\"},\"widget_recent-comments[1]\":{\"encoded_serialized_instance\":\"YTowOnt9\",\"title\":\"\",\"is_widget_customizer_js_value\":true,\"instance_hash_key\":\"1eb11012ea65a32e655e37f998225608\"},\"widget_rss[1]\":{\"encoded_serialized_instance\":\"YTowOnt9\",\"title\":\"\",\"is_widget_customizer_js_value\":true,\"instance_hash_key\":\"1eb11012ea65a32e655e37f998225608\"},\"widget_tag_cloud[1]\":{\"encoded_serialized_instance\":\"YTowOnt9\",\"title\":\"\",\"is_widget_customizer_js_value\":true,\"instance_hash_key\":\"1eb11012ea65a32e655e37f998225608\"},\"widget_nav_menu[1]\":{\"encoded_serialized_instance\":\"YTowOnt9\",\"title\":\"\",\"is_widget_customizer_js_value\":true,\"instance_hash_key\":\"1eb11012ea65a32e655e37f998225608\"},\"widget_caldera_forms_widget[1]\":{\"encoded_serialized_instance\":\"YTowOnt9\",\"title\":\"\",\"is_widget_customizer_js_value\":true,\"instance_hash_key\":\"1eb11012ea65a32e655e37f998225608\"},\"widget_edd_cart_widget[1]\":{\"encoded_serialized_instance\":\"YTowOnt9\",\"title\":\"\",\"is_widget_customizer_js_value\":true,\"instance_hash_key\":\"1eb11012ea65a32e655e37f998225608\"},\"widget_edd_categories_tags_widget[1]\":{\"encoded_serialized_instance\":\"YTowOnt9\",\"title\":\"\",\"is_widget_customizer_js_value\":true,\"instance_hash_key\":\"1eb11012ea65a32e655e37f998225608\"},\"widget_edd_categories_tags_widget[2]\":{\"encoded_serialized_instance\":\"YTo0OntzOjU6InRpdGxlIjtzOjA6IiI7czo4OiJ0YXhvbm9teSI7czoxNzoiZG93bmxvYWRfY2F0ZWdvcnkiO3M6NToiY291bnQiO3M6MDoiIjtzOjEwOiJoaWRlX2VtcHR5IjtzOjA6IiI7fQ==\",\"title\":\"\",\"is_widget_customizer_js_value\":true,\"instance_hash_key\":\"afb767ddd896180593a758ba3228a6a4\"},\"widget_edd_product_details[1]\":{\"encoded_serialized_instance\":\"YTowOnt9\",\"title\":\"\",\"is_widget_customizer_js_value\":true,\"instance_hash_key\":\"1eb11012ea65a32e655e37f998225608\"},\"widget_icl_lang_sel_widget[1]\":{\"encoded_serialized_instance\":\"YTowOnt9\",\"title\":\"\",\"is_widget_customizer_js_value\":true,\"instance_hash_key\":\"1eb11012ea65a32e655e37f998225608\"},\"sidebars_widgets[wp_inactive_widgets]\":[\"calendar-6\",\"calendar-7\",\"archives-4\",\"archives-6\",\"edd_categories_tags_widget-2\"],\"sidebars_widgets[sidebar-left]\":[\"meta-2\"],\"sidebars_widgets[sidebar-right]\":[],\"sidebars_widgets[footer-1]\":[],\"sidebars_widgets[footer-2]\":[],\"sidebars_widgets[footer-3]\":[],\"sidebars_widgets[footer-4]\":[],\"blogname\":\"My test sitexxxxx\",\"blogdescription\":\"hej\",\"header_textcolor\":false,\"background_color\":\"#30d132\",\"header_image\":false,\"header_image_data\":\"\",\"background_image\":\"http://playground-root.ep/assets/uploads/2014/09/small-image.gif\",\"background_image_thumb\":\"\",\"background_repeat\":\"repeat-y\",\"background_position_x\":\"right\",\"background_attachment\":\"scroll\",\"nav_menu_locations[primary]\":\"31\",\"nav_menu_locations[social]\":0,\"nav_menu_locations[header-bar]\":\"32\",\"show_on_front\":\"page\",\"page_on_front\":\"24851\",\"page_for_posts\":\"25253\",\"logo-regular\":\"\",\"logo-retina\":\"\",\"logo-favicon\":\"\",\"logo-apple-touch\":\"\",\"social-facebook\":\"\",\"social-twitter\":\"\",\"social-google-plus-square\":\"\",\"social-linkedin\":\"\",\"social-instagram\":\"\",\"social-flickr\":\"\",\"social-youtube\":\"\",\"social-vimeo-square\":\"\",\"social-pinterest\":\"\",\"social-email\":\"\",\"social-hide-rss\":0,\"social-custom-rss\":\"\",\"font-subset\":\"latin\",\"font-family-site-title\":\"Dawning of a New Day\",\"font-size-site-title\":32,\"font-family-site-tagline\":\"Open Sans\",\"font-size-site-tagline\":12,\"font-family-nav\":\"Open Sans\",\"font-size-nav\":14,\"font-family-subnav\":\"Open Sans\",\"font-size-subnav\":13,\"font-subnav-mobile\":1,\"font-family-widget\":\"Open Sans\",\"font-size-widget\":13,\"font-family-h1\":\"monospace\",\"font-size-h1\":50,\"font-family-h2\":\"monospace\",\"font-size-h2\":37,\"font-family-h3\":\"monospace\",\"font-size-h3\":26,\"font-family-h4\":\"monospace\",\"font-size-h4\":26,\"font-family-h5\":\"monospace\",\"font-size-h5\":18,\"font-family-h6\":\"monospace\",\"font-size-h6\":15,\"font-family-body\":\"Open Sans\",\"font-size-body\":17,\"color-primary\":\"#2fce6f\",\"color-secondary\":\"#35c904\",\"color-text\":\"#969696\",\"color-detail\":\"#b9bcbf\",\"main-background-color\":\"#ffffff\",\"header-bar-background-color\":\"#171717\",\"header-bar-text-color\":\"#16dd66\",\"header-bar-border-color\":\"#171717\",\"header-background-color\":\"#ffffff\",\"header-text-color\":\"#171717\",\"color-site-title\":\"#1a6aba\",\"footer-background-color\":\"#eaecee\",\"footer-text-color\":\"#464849\",\"footer-border-color\":\"#b9bcbf\",\"header-background-image\":\"\",\"header-background-repeat\":\"no-repeat\",\"header-background-position\":\"center\",\"header-background-size\":\"cover\",\"header-layout\":1,\"header-branding-position\":\"left\",\"header-bar-content-layout\":\"flipped\",\"header-text\":\"text\",\"header-show-social\":0,\"header-show-search\":1,\"general-layout\":\"boxed\",\"general-sticky-label\":\"sticky name\",\"main-content-link-underline\":0,\"layout-blog-hide-header\":0,\"layout-blog-hide-footer\":0,\"layout-blog-sidebar-left\":0,\"layout-blog-sidebar-right\":1,\"layout-blog-featured-images\":\"post-header\",\"layout-blog-featured-images-alignment\":\"center\",\"layout-blog-post-date\":\"absolute\",\"layout-blog-post-date-location\":\"top\",\"layout-blog-post-author\":\"avatar\",\"layout-blog-post-author-location\":\"post-footer\",\"layout-blog-auto-excerpt\":0,\"layout-blog-show-categories\":1,\"layout-blog-show-tags\":1,\"layout-blog-comment-count\":\"none\",\"layout-blog-comment-count-location\":\"before-content\",\"layout-archive-hide-header\":0,\"layout-archive-hide-footer\":0,\"layout-archive-sidebar-left\":0,\"layout-archive-sidebar-right\":1,\"layout-archive-featured-images\":\"post-header\",\"layout-archive-featured-images-alignment\":\"center\",\"layout-archive-post-date\":\"absolute\",\"layout-archive-post-date-location\":\"top\",\"layout-archive-post-author\":\"avatar\",\"layout-archive-post-author-location\":\"post-footer\",\"layout-archive-auto-excerpt\":0,\"layout-archive-show-categories\":1,\"layout-archive-show-tags\":1,\"layout-archive-comment-count\":\"none\",\"layout-archive-comment-count-location\":\"before-content\",\"layout-search-hide-header\":0,\"layout-search-hide-footer\":0,\"layout-search-sidebar-left\":true,\"layout-search-sidebar-right\":1,\"layout-search-featured-images\":\"thumbnail\",\"layout-search-featured-images-alignment\":\"center\",\"layout-search-post-date\":\"absolute\",\"layout-search-post-date-location\":\"top\",\"layout-search-post-author\":\"name\",\"layout-search-post-author-location\":\"post-footer\",\"layout-search-auto-excerpt\":1,\"layout-search-show-categories\":1,\"layout-search-show-tags\":1,\"layout-search-comment-count\":\"none\",\"layout-search-comment-count-location\":\"before-content\",\"layout-post-hide-header\":0,\"layout-post-hide-footer\":0,\"layout-post-sidebar-left\":0,\"layout-post-sidebar-right\":0,\"layout-post-featured-images\":\"post-header\",\"layout-post-featured-images-alignment\":\"center\",\"layout-post-post-date\":\"absolute\",\"layout-post-post-date-location\":\"top\",\"layout-post-post-author\":\"name\",\"layout-post-post-author-location\":\"post-footer\",\"layout-post-show-categories\":0,\"layout-post-show-tags\":0,\"layout-post-comment-count\":\"none\",\"layout-post-comment-count-location\":\"before-content\",\"layout-page-hide-header\":0,\"layout-page-hide-footer\":0,\"layout-page-sidebar-left\":0,\"layout-page-sidebar-right\":0,\"layout-page-hide-title\":1,\"layout-page-featured-images\":\"none\",\"layout-page-featured-images-alignment\":\"center\",\"layout-page-post-date\":\"none\",\"layout-page-post-date-location\":\"top\",\"layout-page-post-author\":\"none\",\"layout-page-post-author-location\":\"post-footer\",\"layout-page-comment-count\":\"none\",\"layout-page-comment-count-location\":\"before-content\",\"footer-background-image\":\"\",\"footer-background-repeat\":\"no-repeat\",\"footer-background-position\":\"center\",\"footer-background-size\":\"cover\",\"footer-widget-areas\":3,\"footer-layout\":1,\"footer-text\":\"\",\"footer-show-social\":1,\"background_size\":\"auto\",\"main-background-image\":\"\",\"main-background-repeat\":\"repeat\",\"main-background-position\":\"left\",\"main-background-size\":\"auto\",\"navigation-mobile-label\":\"Menuxx\",\"hide-site-title\":0,\"hide-tagline\":0}
		[nonce] => e983bc7d41
		[action] => customize_save
		)
		*/

		/*
		keys in customized = settings id
		*/
		// print_r($_REQUEST);
		// Needed to get sections and controls in sorted order
		$customize_manager->prepare_controls();

		$settings = $customize_manager->settings();
		$sections = $customize_manager->sections();
		$controls = $customize_manager->controls();

		$customized = json_decode( wp_unslash( $_REQUEST['customized'] ) );

		foreach ( $customized as $setting_id => $posted_values ) {
			foreach ( $settings as $one_setting ) {
				if ( $one_setting->id == $setting_id ) {
					$old_value = $one_setting->value();
					$new_value = $one_setting->post_value();

					if ( $old_value != $new_value ) {
						$context = array(
							'setting_id' => $one_setting->id,
							'setting_old_value' => $old_value,
							'setting_new_value' => $new_value,
						);

						// value is changed
						// find which control it belongs to
						foreach ( $controls as $one_control ) {
							foreach ( $one_control->settings as $section_control_setting ) {
								if ( $section_control_setting->id == $setting_id ) {
									$context['control_id'] = $one_control->id;
									$context['control_label'] = $one_control->label;
									$context['control_type'] = $one_control->type;
								}
							}
						}

						$this->infoMessage(
							'appearance_customized',
							$context
						);
					}// End if().
				}// End if().
			}// End foreach().
		}// End foreach().
	}

	/**
	 * When a new theme is about to get switched to
	 * we save info about the old one
	 *
	 * Request looks like:
	 *  Array
	 *  (
	 *    [action] => activate
	 *    [stylesheet] => wp-theme-bonny-starter
	 *    [_wpnonce] => 31b033ba59
	 *  )
	 */
	public function on_page_load_themes() {

		if ( ! isset( $_GET['action'] ) || $_GET['action'] != 'activate' ) {
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
					[Description] => The 2011 theme for WordPress is sophisticated, lightweight...
					[Author] => the WordPress team
					[AuthorURI] => http://wordpress.org/
					[Version] => 1.8
					[Template] =>
					[Status] =>
					[Tags] => dark, light, white, black, gray, one-column...
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
		if ( ! is_a( $current_theme, 'WP_Theme' ) ) {
			return;
		}

		$this->prev_theme_data = array(
			'name' => $current_theme->name,
			'version' => $current_theme->version,
		);
	}

	public function on_switch_theme( $new_name, $new_theme ) {

		$prev_theme_data = $this->prev_theme_data;

		$this->infoMessage(
			'theme_switched',
			array(
				'theme_name' => $new_name,
				'theme_version' => $new_theme->version,
				'prev_theme_name' => $prev_theme_data['name'],
				'prev_theme_version' => $prev_theme_data['version'],
			)
		);
	}

	public function getLogRowDetailsOutput( $row ) {
		$context = $row->context;
		$message_key = $context['_message_key'];
		$output = '';

		// Theme customizer
		if ( 'appearance_customized' == $message_key ) {
			// if ( ! class_exists("WP_Customize_Manager") ) {
			// require_once( ABSPATH . WPINC . '/class-wp-customize-manager.php' );
			// $wp_customize = new WP_Customize_Manager;
			// }
			// $output .= "<pre>" . print_r($context, true);
			if ( isset( $context['setting_old_value'] ) && isset( $context['setting_new_value'] ) ) {
				$output .= "<table class='SimpleHistoryLogitem__keyValueTable'>";

				// Output section, if saved
				if ( ! empty( $context['section_id'] ) ) {
					$output .= sprintf(
						'
						<tr>
							<td>%1$s</td>
							<td>%2$s</td>
						</tr>
						',
						__( 'Section', 'simple-history' ),
						esc_html( $context['section_id'] )
					);
				}

				// Don't output prev and new value if none exist
				// phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedIf
				if ( empty( $context['setting_old_value'] ) && empty( $context['setting_new_value'] ) ) {
					// Empty, so skip.
				} else {
					// if control is color let's be fancy and output as color
					$control_type = isset( $context['control_type'] ) ? $context['control_type'] : '';
					$str_old_value_prepend = '';
					$str_new_value_prepend = '';

					if ( 'color' == $control_type ) {
						$str_old_value_prepend .= sprintf(
							'<span style="background-color: #%1$s; width: 1em; display: inline-block;">&nbsp;</span> ',
							esc_attr( ltrim( $context['setting_old_value'], ' #' ) )
						);

						$str_new_value_prepend .= sprintf(
							'<span style="background-color: #%1$s; width: 1em; display: inline-block;">&nbsp;</span> ',
							esc_attr( ltrim( $context['setting_new_value'], '#' ) )
						);
					}

					$output .= sprintf(
						'
						<tr>
							<td>%1$s</td>
							<td>%3$s%2$s</td>
						</tr>
						',
						__( 'New value', 'simple-history' ),
						esc_html( $context['setting_new_value'] ),
						$str_new_value_prepend
					);

					$output .= sprintf(
						'
						<tr>
							<td>%1$s</td>
							<td>%3$s%2$s</td>
						</tr>
						',
						__( 'Old value', 'simple-history' ),
						esc_html( $context['setting_old_value'] ),
						$str_old_value_prepend
					);
				}// End if().

				$output .= '</table>';
			}// End if().
		}// End if().

		return $output;
	}

	/**
	 * Add widget name and sidebar name to output
	 */
	public function getLogRowPlainTextOutput( $row ) {

		$context = $row->context;
		$message_key = $context['_message_key'];
		$message = $row->message;
		$output = '';

		// Widget changed or added or removed
		// Simple replace widget_id_base and sidebar_id with widget name and sidebar name
		if ( in_array( $message_key, array( 'widget_added', 'widget_edited', 'widget_removed' ) ) ) {
			$widget = $this->getWidgetByIdBase( $context['widget_id_base'] );
			$sidebar = $this->getSidebarById( $context['sidebar_id'] );

			if ( $widget && $sidebar ) {
				// Translate message first
				$message = $this->messages[ $message_key ]['translated_text'];

				$message = $this->interpolate(
					$message,
					array(
						'widget_id_base' => $widget->name,
						'sidebar_id' => $sidebar['name'],
					),
					$row
				);

				$output .= $message;
			}
		}

		// Fallback to default/parent output if nothing was added to output
		if ( ! $output ) {
			$output .= parent::getLogRowPlainTextOutput( $row );
		}

		return $output;
	}

	/*
	function on_action_sidebar_admin_setup__detect_widget_edit() {

		if ( isset( $_REQUEST["action"] ) && ( $_REQUEST["action"] == "save-widget" ) && isset( $_POST["sidebar"] ) && isset( $_POST["id_base"] ) ) {

			$widget_id_base = $_POST["id_base"];

			// a key with widget-{$widget_id_base} exists if we are saving
			if ( ! isset( $_POST["widget-{$widget_id_base}"] ) ) {
				return;
			}

			$context = array();

			$widget_save_data = $_POST["widget-{$widget_id_base}"];
			$context["widget_save_data"] = $this->simpleHistory->json_encode( $widget_save_data );

			// Add widget info
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
				"widget_edited",
				$context
			);

		}

	}
		*/

	/**
	 * A widget is changed, i.e. new values are saved
	 *
	 * @TODO: first time a widget is added it seems to call this and we get double edit logs that are confusing
	 */
	public function on_widget_update_callback( $instance, $new_instance, $old_instance, $widget_instance ) {

		// sf_d($instance);
		/*
		Array
		(
			[title] => Custom menu I am abc
			[nav_menu] => 0
		)

		*/

		// sf_d($new_instance);
		/*
		Custom Menu
		Array
		(
			[title] => Custom menu I am abc
			[nav_menu] => 0
		)
		*/

		// sf_d($old_instance);
		/*
		Array
		(
			[title] => Custom menu I am
			[nav_menu] => 0
		)
		*/

		// sf_d($widget_instance);
		/*
		WP_Nav_Menu_Widget Object
		(
			[id_base] => nav_menu
			[name] => Custom Menu
			[widget_options] => Array
				(
					[classname] => widget_nav_menu
					[description] => Add a custom menu to your sidebar.
				)

			[control_options] => Array
				(
					[id_base] => nav_menu
				)

			[number] => 2
			[id] => nav_menu-2
			[updated] =>
			[option_name] => widget_nav_menu
		)
		*/

		// If old_instance is empty then this widget has just been added
		// and we log that as "Added" not "Edited"
		if ( empty( $old_instance ) ) {
			return $instance;
		}

		$widget_id_base = $widget_instance->id_base;

		$context = array();

		// Add widget info.
		$context['widget_id_base'] = $widget_id_base;
		$widget = $this->getWidgetByIdBase( $widget_id_base );
		if ( $widget ) {
			$context['widget_name_translated'] = $widget->name;
		}

		// Add sidebar info.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$sidebar_id = isset( $_POST['sidebar'] ) ? $_POST['sidebar'] : null;
		$context['sidebar_id'] = $sidebar_id;
		$sidebar = $this->getSidebarById( $sidebar_id );
		if ( $sidebar ) {
			$context['sidebar_name_translated'] = $sidebar['name'];
		}

		// Calculate changes.
		$context['old_instance'] = $this->simpleHistory->json_encode( $old_instance );
		$context['new_instance'] = $this->simpleHistory->json_encode( $new_instance );

		$this->infoMessage(
			'widget_edited',
			$context
		);

		return $instance;
	}

	/**
	 * Change Widgets order
	 * action=widgets-order is also called after deleting a widget
	 * to many log entries with changed, just confusing.
	 * need to rethink this
	 */
	 /*
	function on_action_sidebar_admin_setup__detect_widget_order_change() {

		if ( isset( $_REQUEST["action"] ) && ( $_REQUEST["action"] == "widgets-order" ) ) {

			$context = array();

			// Get old order
			$sidebars = isset( $GLOBALS['wp_registered_sidebars'] ) ? $GLOBALS['wp_registered_sidebars'] : false;
			if ($sidebars) {
				$context["sidebars_from"] = $this->simpleHistory->json_encode( $sidebars );
			}

			$new_sidebars = $_POST["sidebars"];
			$context["sidebars_to"] = $this->simpleHistory->json_encode( $new_sidebars );

			$widget_factory = isset( $GLOBALS["wp_widget_factory"] ) ? $GLOBALS["wp_widget_factory"] : false;
			$context["widgets_from"] = $this->simpleHistory->json_encode( $widget_factory->widgets );

			//$wp_registered_widgets, $wp_registered_sidebars, $sidebars_widgets;
			$sidebars_widgets = isset( $GLOBALS["sidebars_widgets"] ) ? $GLOBALS["sidebars_widgets"] : false;
			$context["sidebars_widgets"] = $this->simpleHistory->json_encode( $sidebars_widgets );

			$this->infoMessage(
				"widget_order_changed",
				$context
			);

		}

	}
	*/

	/**
	 * Widget added
	 */
	public function on_action_sidebar_admin_setup__detect_widget_add() {

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST['add_new'] ) && ! empty( $_POST['add_new'] ) && isset( $_POST['sidebar'] ) && isset( $_POST['id_base'] ) ) {
			// Add widget info
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$widget_id_base = $_POST['id_base'];
			$context['widget_id_base'] = $widget_id_base;
			$widget = $this->getWidgetByIdBase( $widget_id_base );
			if ( $widget ) {
				$context['widget_name_translated'] = $widget->name;
			}

			// Add sidebar info
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$sidebar_id = $_POST['sidebar'];
			$context['sidebar_id'] = $sidebar_id;
			$sidebar = $this->getSidebarById( $sidebar_id );
			if ( $sidebar ) {
				$context['sidebar_name_translated'] = $sidebar['name'];
			}

			$this->infoMessage(
				'widget_added',
				$context
			);
		}
	}
	/*
	 * widget deleted
	 */
	public function on_action_sidebar_admin_setup__detect_widget_delete() {

		// Widget was deleted
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST['delete_widget'] ) ) {
			$context = array();

			// Add widget info.
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$widget_id_base = $_POST['id_base'];
			$context['widget_id_base'] = $widget_id_base;
			$widget = $this->getWidgetByIdBase( $widget_id_base );
			if ( $widget ) {
				$context['widget_name_translated'] = $widget->name;
			}

			// Add sidebar info
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$sidebar_id = $_POST['sidebar'];
			$context['sidebar_id'] = $sidebar_id;
			$sidebar = $this->getSidebarById( $sidebar_id );
			if ( $sidebar ) {
				$context['sidebar_name_translated'] = $sidebar['name'];
			}

			$this->infoMessage(
				'widget_removed',
				$context
			);
		}
	}

	/**
	 * Get a sidebar by id
	 *
	 * @param string $sidebar_id ID of sidebar.
	 * @return sidebar info or false on failure.
	 */
	public function getSidebarById( $sidebar_id ) {

		if ( empty( $sidebar_id ) ) {
			return false;
		}

		$sidebars = isset( $GLOBALS['wp_registered_sidebars'] ) ? $GLOBALS['wp_registered_sidebars'] : false;

		if ( ! $sidebars ) {
			return false;
		}

		// Add sidebar info.
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
	public function getWidgetByIdBase( $widget_id_base ) {

		$widget_factory = isset( $GLOBALS['wp_widget_factory'] ) ? $GLOBALS['wp_widget_factory'] : false;

		if ( ! $widget_factory ) {
			return false;
		}

		foreach ( $widget_factory->widgets as $one_widget ) {
			if ( $one_widget->id_base == $widget_id_base ) {
				return $one_widget;
			}
		}

		return false;
	}
}
