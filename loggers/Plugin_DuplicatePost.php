<?php

defined( 'ABSPATH' ) or die();

/**
 * Logger for the Duplicate Post plugin
 * Post Duplicator (https://sv.wordpress.org/plugins/duplicate-post/)
 */
if ( ! class_exists("Plugin_DuplicatePost") ) {

    class Plugin_DuplicatePost extends SimpleLogger {

        public $slug = __CLASS__;

    	function getInfo() {

    		$arr_info = array(
    			"name" => "Plugin Duplicate Posts",
    			"description" => _x("Logs post and page duplication created using plugin Duplicate Post", "Logger: Plugin Duplicate Post", "simple-history"),
                "name_via" => _x("Using plugin Duplicate Posts", "Logger: Plugin Duplicate Post", "simple-history"),
    			"capability" => "manage_options",
    			"messages" => array(
                    'post_duplicated' => _x( 'Created a duplicate of post "{duplicated_post_title}"', "Logger: Plugin Duplicate Post", 'simple-history' )
    			),
    		);

    		return $arr_info;

    	}

        public function loaded() {
            require_once(ABSPATH . 'wp-admin/includes/plugin.php');

            $pluginFilePath = 'duplicate-post/duplicate-post.php';
            $isPluginActive = is_plugin_active('duplicate-post/duplicate-post.php');

            if (!$isPluginActive) {
                return;
            }

            // When a copy have been made of a post or page
            // the action 'dp_duplicate_page' or 'dp_duplicate_post'
            // is fired with args $new_post_id, $post, $status.
            // We add actions with prio 20 so we probably run after
            // the plugins own
            add_action('dp_duplicate_post', array($this, 'onDpDuplicatePost'), 100, 3);
            add_action('dp_duplicate_page', array($this, 'onDpDuplicatePost'), 100, 3);
        }

        /**
         * @param $new_post_id
         * @param $post
         * @param $status
         */
        public function onDpDuplicatePost($newPostID, $post, $status)
        {
            $new_post = get_post($newPostID);

            $context = [
                "new_post_title" => $new_post->post_title,
                "new_post_id" => $new_post->ID,
                "duplicated_post_title" => $post->post_title,
                "duplicated_post_id" => $post->ID,
                // "duplicate_new_post_id" => $newPostID,
                // "status" => $status
            ];

            $this->infoMessage(
                "post_duplicated",
                $context
            );
        }

    } // class

} // class exists
