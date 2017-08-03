<?php

defined( 'ABSPATH' ) || die();

// Only enable in development mode.
if ( ! defined( 'SIMPLE_HISTORY_DEV' ) || ! SIMPLE_HISTORY_DEV ) {
	return;
}

/**
 * Logger for the Advanced Custom Fields (ACF) plugin
 * https://sv.wordpress.org/plugins/advanced-custom-fields/
 *
 * @package SimpleHistory
 * @since 2.x
 */
if (! class_exists("Plugin_ACF")) {

    class Plugin_ACF extends SimpleLogger
    {
        public $slug = __CLASS__;

        public function getInfo()
        {
            $arr_info = array(
                "name" => "Plugin ACF",
                "description" => _x("Logs ACF stuff", "Logger: Plugin Duplicate Post", "simple-history"),
                "name_via" => _x("Using plugin ACF", "Logger: Plugin Duplicate Post", "simple-history"),
                "capability" => "manage_options",
                "messages" => array(
                    'post_duplicated' => _x('Cloned "{duplicated_post_title}" to a new post', "Logger: Plugin Duplicate Post", 'simple-history')
                ),
            );

            return $arr_info;
        }

        public function loaded()
        {
        	$this->remove_acf_from_postlogger();

        	/*
			possible filters to use
			do_action('acf/update_field_group', $field_group);
			apply_filters( "acf/update_field", $field); (return field)
			do_action('acf/trash_field_group', $field_group);
        	*/
        	add_action('acf/update_field_group', array($this, 'on_update_field_group'), 10);

        	// Create ACF field group

        	// Update/edit ACF field group

        	// Trash ACF field group
        	// Untrash ACF field group

        	// Delete field grouo

        }

        public function on_update_field_group($field_group) {
        	echo '<pre>on_update_field_group';
        	print_r($field_group);
        	print_r(acf_get_fields($field_group));
        	exit;
        }

        /**
         * Add the post types that ACF uses to the array of post types
         * that the post logger should not log
         */
        public function remove_acf_from_postlogger() {
        	add_filter('simple_history/post_logger/skip_posttypes', function($skip_posttypes) {
        		array_push(
        			$skip_posttypes,
        			'acf-field-group',
        			'acf-field'
        		);

        		return $skip_posttypes;
        	}, 10);
        }

    } // class
} // class exists
