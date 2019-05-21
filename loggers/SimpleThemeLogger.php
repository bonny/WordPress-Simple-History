<?php

defined('ABSPATH') or die();

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
    function getInfo()
    {

        $arr_info = array(
            'name' => 'Theme Logger',
            'description' => 'Logs theme edits',
            'capability' => 'edit_theme_options',
            'messages' => array(
                'theme_switched' => __('Switched theme to "{theme_name}" from "{prev_theme_name}"', 'simple-history'),
                'theme_installed' => __('Installed theme "{theme_name}"', 'simple-history'),
                'theme_deleted' => __('Deleted theme with slug "{theme_slug}"', 'simple-history'),
                'theme_updated' => __('Updated theme "{theme_name}"', 'simple-history'),
                'appearance_customized' => __('Customized theme appearance "{setting_id}"', 'simple-history'),
                'widget_removed' => __('Removed widget "{widget_id_base}" from sidebar "{sidebar_id}"', 'simple-history'),
                'widget_added' => __('Added widget "{widget_id_base}" to sidebar "{sidebar_id}"', 'simple-history'),
                'widget_order_changed' => __('Changed widget order "{widget_id_base}" in sidebar "{sidebar_id}"', 'simple-history'),
                'widget_edited' => __('Changed widget "{widget_id_base}" in sidebar "{sidebar_id}"', 'simple-history'),
                'custom_background_changed' => __('Changed settings for the theme custom background', 'simple-history'),
            ),
            'labels' => array(
                'search' => array(
                    'label' => _x('Themes & Widgets', 'Theme logger: search', 'simple-history'),
                    'label_all' => _x('All theme activity', 'Theme logger: search', 'simple-history'),
                    'options' => array(
                        _x('Updated themes', 'Theme logger: search', 'simple-history') => array(
                            'theme_updated'
                        ),
                        _x('Deleted themes', 'Theme logger: search', 'simple-history') => array(
                            'theme_deleted'
                        ),
                        _x('Installed themes', 'Theme logger: search', 'simple-history') => array(
                            'theme_installed'
                        ),
                        _x('Switched themes', 'Theme logger: search', 'simple-history') => array(
                            'theme_switched'
                        ),
                        _x('Changed appearance of themes', 'Theme logger: search', 'simple-history') => array(
                            'appearance_customized'
                        ),
                        _x('Added widgets', 'Theme logger: search', 'simple-history') => array(
                            'widget_added'
                        ),
                        _x('Removed widgets', 'Theme logger: search', 'simple-history') => array(
                            'widget_removed'
                        ),
                        _x('Changed widgets order', 'Theme logger: search', 'simple-history') => array(
                            'widget_order_changed'
                        ),
                        _x('Edited widgets', 'Theme logger: search', 'simple-history') => array(
                            'widget_edited'
                        ),
                        _x('Background of themes changed', 'Theme logger: search', 'simple-history') => array(
                            'custom_background_changed'
                        ),
                    ),
                ),// end search array
            ),// end labels

        );

        return $arr_info;
    }

    function loaded()
    {

        /**
         * Fires after the theme is switched.
         *
         * @param string   $new_name  Name of the new theme.
         * @param WP_Theme $new_theme WP_Theme instance of the new theme.
         */
        add_action('switch_theme', array( $this, 'on_switch_theme' ), 10, 2);
        add_action('load-themes.php', array( $this, 'on_page_load_themes' ));

        add_action('customize_save', array( $this, 'on_action_customize_save' ));

        add_action('sidebar_admin_setup', array( $this, 'on_action_sidebar_admin_setup__detect_widget_delete' ));
        add_action('sidebar_admin_setup', array( $this, 'on_action_sidebar_admin_setup__detect_widget_add' ));
        // add_action("wp_ajax_widgets-order", array( $this, "on_action_sidebar_admin_setup__detect_widget_order_change"), 1 );
        // add_action("sidebar_admin_setup", array( $this, "on_action_sidebar_admin_setup__detect_widget_edit") );
        add_filter('widget_update_callback', array( $this, 'on_widget_update_callback' ), 10, 4);

        add_action('load-appearance_page_custom-background', array( $this, 'on_page_load_custom_background' ));

        add_action('upgrader_process_complete', array( $this, 'on_upgrader_process_complete_theme_install' ), 10, 2);
        add_action('upgrader_process_complete', array( $this, 'on_upgrader_process_complete_theme_update' ), 10, 2);

        // delete_site_transient( 'update_themes' );
        // do_action( 'deleted_site_transient', $transient );
        add_action('deleted_site_transient', array( $this, 'on_deleted_site_transient_theme_deleted' ), 10, 1);
    }

    /*
    * Fires after a transient is deleted.
    * WP function delete_theme() does not have any actions or filters we can use to detect
    * a theme deletion, but the last thing that is done in delete_theme() is that the
    * "update_themes" transient is deleted. So use that info to catch theme deletions.
    *
    * @param string $transient Deleted transient name.
    */
    function on_deleted_site_transient_theme_deleted($transient = null)
    {

        if ('update_themes' !== $transient) {
            return;
        }

        /*
        When a theme is deleted we have this info:

        $_GET:
        {
            "action": "delete",
            "stylesheet": "CherryFramework",
            "_wpnonce": "1c1571004e"
        }


        */

        if (empty($_GET['action']) || $_GET['action'] !== 'delete') {
            return;
        }

        if (empty($_GET['stylesheet'])) {
            return;
        }

        $theme_deleted_slug = (string) $_GET['stylesheet'];

        $this->infoMessage(
            'theme_deleted',
            array(
                'theme_slug' => $theme_deleted_slug,
            )
        );
    }


    function on_upgrader_process_complete_theme_update($upgrader_instance = null, $arr_data = null)
    {

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
        if (empty($upgrader_instance) || empty($arr_data)) {
            return;
        }

        // Must be type theme and action install
        if ($arr_data['type'] !== 'theme' || $arr_data['action'] !== 'update') {
            return;
        }

        // Skin contains the nice info
        if (empty($upgrader_instance->skin)) {
            return;
        }

        $skin = $upgrader_instance->skin;

        $arr_themes = array();

        // If single install make an array so it look like bulk and we can use same code
        if (isset($arr_data['bulk']) && $arr_data['bulk']  && isset($arr_data['themes'])) {
            $arr_themes = (array) $arr_data['themes'];
        } else {
            $arr_themes = array(
                $arr_data['theme']
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
        foreach ($arr_themes as $one_updated_theme) {
            $theme_info_object = wp_get_theme($one_updated_theme);

            if (! is_a($theme_info_object, 'WP_Theme')) {
                continue;
            }

            $theme_name = $theme_info_object->get('Name');
            $theme_version = $theme_info_object->get('Version');

            if (! $theme_name || ! $theme_version) {
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

    function on_upgrader_process_complete_theme_install($upgrader_instance = null, $arr_data = null)
    {

        /*
        For theme installs $arr_data looks like:

            Array
            (
                [type] => theme
                [action] => install
            )

        */

        // Both args must be set
        if (empty($upgrader_instance) || empty($arr_data)) {
            return;
        }

        // Must be type theme and action install
        if ($arr_data['type'] !== 'theme' || $arr_data['action'] !== 'install') {
            return;
        }

        // Skin contains the nice info
        if (empty($upgrader_instance->skin)) {
            return;
        }

        $skin = $upgrader_instance->skin;

        /*
        ob_start();
        print_r($skin);
        $skin_str = ob_get_clean();
        */

        /*
        Interesting parts in $skin:

        // type can be "web" or nnn
        [type] => web

        // api seems to contains theme info and description
        [api] => stdClass Object
            (
                [name] => Hemingway
                [slug] => hemingway
                [version] => 1.56
                [preview_url] => https://wp-themes.com/hemingway
                [author] => anlino
                [screenshot_url] => //ts.w.org/wp-content/themes/hemingway/screenshot.png?ver=1.56
                [rating] => 94
                [num_ratings] => 35
                [downloaded] => 282236
                [last_updated] => 2016-07-03
                [homepage] => https://wordpress.org/themes/hemingway/
                [download_link] => https://downloads.wordpress.org/theme/hemingway.1.56.zip
            )

        */

        $type = empty($skin->type) ? null : $skin->type ;
        $theme_name = empty($skin->api->name) ? null : $skin->api->name;
        $theme_slug = empty($skin->api->slug) ? null : $skin->api->slug;
        $theme_version = empty($skin->api->version) ? null : $skin->api->version;
        // $theme_screenshot_url = $skin->api->screenshot_url;
        // $theme_last_updated = $skin->api->last_updated;
        // $theme_last_homepage = $skin->api->last_homepage;
        // $theme_download_link = $skin->api->last_download_link;
        $this->infoMessage(
            'theme_installed',
            array(
                'theme_name' => $theme_name,
                'theme_version' => $theme_version,
                // "debug_skin" => $skin_str
            )
        );
    }

    function on_page_load_custom_background()
    {

        if (empty($_POST)) {
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

        $valid_post_key_exists = array_intersect_key($arr_valid_post_keys, $_POST);

        if (! empty($valid_post_key_exists)) {
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
    function on_action_customize_save($customize_manager)
    {

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

        $customized = json_decode(wp_unslash($_REQUEST['customized']));

        foreach ($customized as $setting_id => $posted_values) {
            foreach ($settings as $one_setting) {
                if ($one_setting->id == $setting_id) {
                    // sf_d("MATCH");
                    $old_value = $one_setting->value();
                    $new_value = $one_setting->post_value();

                    if ($old_value != $new_value) {
                        $context = array(
                            'setting_id' => $one_setting->id,
                            'setting_old_value' => $old_value,
                            'setting_new_value' => $new_value,
                            // "control_id" => $section_control->id,
                            // "control_label" => $section_control->label,
                            // "control_type" => $section_control->type,
                            // "section_id" => $section->id,
                            // "section_title" => $section->title,
                        );

                        // value is changed
                        // find which control it belongs to
                        // foreach ($sections as $section) {
                        foreach ($controls as $one_control) {
                            foreach ($one_control->settings as $section_control_setting) {
                                if ($section_control_setting->id == $setting_id) {
                                    // echo "\n" . $one_control->id;
                                    // echo "\n" . $one_control->label;
                                    // echo "\n" . $one_control->type;
                                    $context['control_id'] = $one_control->id;
                                    $context['control_label'] = $one_control->label;
                                    $context['control_type'] = $one_control->type;
                                }
                            }
                        }
                        // }
                        $this->infoMessage(
                            'appearance_customized',
                            $context
                        );
                    }// End if().
                }// End if().
            }// End foreach().
        }// End foreach().

        return;
        // print_r( json_decode( $customized ) );
        // exit;
        // Set to true to echo some info about stuff
        // that can be views in console when saving
        $debug = 0;

        $arr_changed_settings = array();
        $arr_changed_settings_ids = array();

        /*
        foreach ($settings as $setting) {

        #echo "\n\nsetting";
        #sf_d( $setting->id );
        #sf_d( $setting->value() );
        #sf_d( $setting->post_value() );

        // Get control for this settings
        foreach ($sections as $section) {
        foreach ($section->controls as $control) {
            foreach ($control->settings as $one_setting) {
                sf_d( $one_setting->id );
            }
        }
        }


        }
        return;
        */
        foreach ($sections as $section) {
            // echo "Section: " . $section->id . " (".$section->title.")";
            // Id is unique slug
            // Can't use title because that's translated
            if ($debug) {
                echo "\n-------\n";
                echo 'Section: ' . $section->id . ' (' . $section->title . ')';
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
                    echo $section_control->id . ' (' . $section_control->label . ')';
                    // echo "\ncontrol setting: " . $section_control->setting;
                    // echo "\nSettings:";
                }

                /*
                Control ttfmake_stylekit-info ()
                har underlig setting
                setting = blogname = uppdateras som en tok!
                */
                /*
                if ( $section_control->id == "ttfmake_stylekit-info" ) {
                    print_r( sizeof($section_control_settings) );
                    echo "\nid: " . $section_control_settings[0]->id;
                    echo "\ntitle: " . $section_control_settings[0]->title;
                    exit;
                }*/

                foreach ($section_control_settings as $one_setting) {
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
                    if ($old_value != $new_value && ! in_array($one_setting_id, $arr_changed_settings_ids)) {
                        // if ( $old_value != $new_value ) {
                        if ($debug) {
                            echo "\nSetting with id ";
                            echo $one_setting->id;
                            echo ' changed';
                            echo "\nold value $old_value";
                            echo "\nnew value $new_value";
                        }

                        $arr_changed_settings[] = array(
                            'setting_id' => $one_setting->id,
                            'setting_old_value' => $old_value,
                            'setting_new_value' => $new_value,
                            'control_id' => $section_control->id,
                            'control_label' => $section_control->label,
                            'control_type' => $section_control->type,
                            'section_id' => $section->id,
                            'section_title' => $section->title,
                        );

                        $arr_changed_settings_ids[] = $one_setting->id;
                    }
                }// End foreach().
            }// End foreach().
        } // End foreach().

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
                print_r($arr_changed_settings);
            }

            // Store each changed settings as one log entry
            // We could store all in just one, but then we would get
            // problems when outputing formatted output (too many things to show at once)
            // or when gathering stats
            foreach ($arr_changed_settings as $one_changed_setting) {
                $this->infoMessage(
                    'appearance_customized',
                    $one_changed_setting
                );
            }
        }
    }

    /**
     * When a new theme is about to get switched to
     * we save info about the old one
     */
    function on_page_load_themes()
    {

        // sf_d($_REQUEST, "request");exit;
        /*
        request:
        Array
        (
            [action] => activate
            [stylesheet] => wp-theme-bonny-starter
            [_wpnonce] => 31b033ba59
        )
        */

        if (! isset($_GET['action']) || $_GET['action'] != 'activate') {
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
        if (! is_a($current_theme, 'WP_Theme')) {
            return;
        }
        // sf_d($current_theme);
        $this->prev_theme_data = array(
            'name' => $current_theme->name,
            'version' => $current_theme->version,
        );
    }

    function on_switch_theme($new_name, $new_theme)
    {

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

    function getLogRowDetailsOutput($row)
    {

        $context = $row->context;
        $message_key = $context['_message_key'];
        $output = '';

        // Theme customizer
        if ('appearance_customized' == $message_key) {
            // if ( ! class_exists("WP_Customize_Manager") ) {
            // require_once( ABSPATH . WPINC . '/class-wp-customize-manager.php' );
            // $wp_customize = new WP_Customize_Manager;
            // }
            // $output .= "<pre>" . print_r($context, true);
            if (isset($context['setting_old_value']) && isset($context['setting_new_value'])) {
                $output .= "<table class='SimpleHistoryLogitem__keyValueTable'>";

                // Output section, if saved
                if (! empty($context['section_id'])) {
                    $output .= sprintf(
                        '
						<tr>
							<td>%1$s</td>
							<td>%2$s</td>
						</tr>
						',
                        __('Section', 'simple-history'),
                        esc_html($context['section_id'])
                    );
                }

                // Don't output prev and new value if none exist
                if (empty($context['setting_old_value']) && empty($context['setting_new_value'])) {
                    // empty, so skip
                } else {
                    // if control is color let's be fancy and output as color
                    $control_type = isset($context['control_type']) ? $context['control_type'] : '';
                    $str_old_value_prepend = '';
                    $str_new_value_prepend = '';

                    if ('color' == $control_type) {
                        $str_old_value_prepend .= sprintf(
                            '<span style="background-color: #%1$s; width: 1em; display: inline-block;">&nbsp;</span> ',
                            esc_attr(ltrim($context['setting_old_value'], ' #'))
                        );

                        $str_new_value_prepend .= sprintf(
                            '<span style="background-color: #%1$s; width: 1em; display: inline-block;">&nbsp;</span> ',
                            esc_attr(ltrim($context['setting_new_value'], '#'))
                        );
                    }

                    $output .= sprintf(
                        '
						<tr>
							<td>%1$s</td>
							<td>%3$s%2$s</td>
						</tr>
						',
                        __('New value', 'simple-history'),
                        esc_html($context['setting_new_value']),
                        $str_new_value_prepend
                    );

                    $output .= sprintf(
                        '
						<tr>
							<td>%1$s</td>
							<td>%3$s%2$s</td>
						</tr>
						',
                        __('Old value', 'simple-history'),
                        esc_html($context['setting_old_value']),
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
    function getLogRowPlainTextOutput($row)
    {

        $context = $row->context;
        $message_key = $context['_message_key'];
        $message = $row->message;
        $output = '';

        // Widget changed or added or removed
        // Simple replace widget_id_base and sidebar_id with widget name and sidebar name
        if (in_array($message_key, array( 'widget_added', 'widget_edited', 'widget_removed' ))) {
            $widget = $this->getWidgetByIdBase($context['widget_id_base']);
            $sidebar = $this->getSidebarById($context['sidebar_id']);

            if ($widget && $sidebar) {
                // Translate message first
                $message = $this->messages[ $message_key ]['translated_text'];

                $message = $this->interpolate($message, array(
                    'widget_id_base' => $widget->name,
                    'sidebar_id' => $sidebar['name'],
                ), $row);

                $output .= $message;
            }
        }

        // Fallback to default/parent output if nothing was added to output
        if (! $output) {
            $output .= parent::getLogRowPlainTextOutput($row);
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
    function on_widget_update_callback($instance, $new_instance, $old_instance, $widget_instance)
    {

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
        if (empty($old_instance)) {
            return $instance;
        }

        $widget_id_base = $widget_instance->id_base;

        $context = array();

        // Add widget info.
        $context['widget_id_base'] = $widget_id_base;
        $widget = $this->getWidgetByIdBase($widget_id_base);
        if ($widget) {
            $context['widget_name_translated'] = $widget->name;
        }

        // Add sidebar info.
        $sidebar_id = isset($_POST['sidebar']) ? $_POST['sidebar'] : null;
        $context['sidebar_id'] = $sidebar_id;
        $sidebar = $this->getSidebarById($sidebar_id);
        if ($sidebar) {
            $context['sidebar_name_translated'] = $sidebar['name'];
        }

        // Calculate changes.
        $context['old_instance'] = $this->simpleHistory->json_encode($old_instance);
        $context['new_instance'] = $this->simpleHistory->json_encode($new_instance);

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
    function on_action_sidebar_admin_setup__detect_widget_add()
    {

        if (isset($_POST['add_new']) && ! empty($_POST['add_new']) && isset($_POST['sidebar']) && isset($_POST['id_base'])) {
            // Add widget info
            $widget_id_base = $_POST['id_base'];
            $context['widget_id_base'] = $widget_id_base;
            $widget = $this->getWidgetByIdBase($widget_id_base);
            if ($widget) {
                $context['widget_name_translated'] = $widget->name;
            }

            // Add sidebar info
            $sidebar_id = $_POST['sidebar'];
            $context['sidebar_id'] = $sidebar_id;
            $sidebar = $this->getSidebarById($sidebar_id);
            if ($sidebar) {
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
    function on_action_sidebar_admin_setup__detect_widget_delete()
    {

        // Widget was deleted
        if (isset($_POST['delete_widget'])) {
            $context = array();

            // Add widget info
            $widget_id_base = $_POST['id_base'];
            $context['widget_id_base'] = $widget_id_base;
            $widget = $this->getWidgetByIdBase($widget_id_base);
            if ($widget) {
                $context['widget_name_translated'] = $widget->name;
            }

            // Add sidebar info
            $sidebar_id = $_POST['sidebar'];
            $context['sidebar_id'] = $sidebar_id;
            $sidebar = $this->getSidebarById($sidebar_id);
            if ($sidebar) {
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
    function getSidebarById($sidebar_id)
    {

        if (empty($sidebar_id)) {
            return false;
        }

        $sidebars = isset($GLOBALS['wp_registered_sidebars']) ? $GLOBALS['wp_registered_sidebars'] : false;

        if (! $sidebars) {
            return false;
        }

        // Add sidebar info.
        if (isset($sidebars[ $sidebar_id ])) {
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
    function getWidgetByIdBase($widget_id_base)
    {

        $widget_factory = isset($GLOBALS['wp_widget_factory']) ? $GLOBALS['wp_widget_factory'] : false;

        if (! $widget_factory) {
            return false;
        }

        foreach ($widget_factory->widgets as $one_widget) {
            if ($one_widget->id_base == $widget_id_base) {
                return $one_widget;
            }
        }

        return false;
    }
}
