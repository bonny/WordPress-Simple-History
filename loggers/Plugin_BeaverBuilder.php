<?php

defined('ABSPATH') or die();

/**
 * Logger for Beaver Builder
 */
if (!class_exists('Plugin_BeaverBuilder')) {
    class Plugin_BeaverBuilder extends SimpleLogger
    {
        public $slug = __CLASS__;

        function getInfo()
        {
            $arr_info = array(
                'name' => 'Plugin Beaver Builder',
                'description' => _x(
                    'Logs various things in Beaver Builder',
                    'Logger: Plugin Beaver Builder',
                    'simple-history'
                ),
                'name_via' => _x(
                    'Using plugin Beaver Builder',
                    'Logger: Plugin Beaver Builder',
                    'simple-history'
                ),
                'capability' => 'manage_options',
                'messages' => array(
                    'layout_saved' => __(
                        'Layout "{layout_name}" updated',
                        'simple-history'
                    ),
                    'template_saved' => __(
                        'Template "{layout_name}" updated',
                        'simple-history'
                    ),
                    'draft_saved' => __(
                        'Draft "{layout_name}" updated',
                        'simple-history'
                    ),
                    'admin_saved' => __(
                        'Beaver Builder settings saved',
                        'simple-history'
                    )
                )
            );

            return $arr_info;
        }

        function loaded()
        {
            if (!class_exists('FLBuilder')) {
                return;
            }

            add_action(
                'fl_builder_after_save_layout',
                array($this, 'save_layout'),
                10,
                4
            );
            add_action(
                'fl_builder_after_save_user_template',
                array($this, 'save_template'),
                10,
                1
            );
            add_action(
                'fl_builder_after_save_draft',
                array($this, 'save_draft'),
                10,
                2
            );
            add_action('fl_builder_admin_settings_save', array(
                $this,
                'save_admin'
            ));
        }
        
        function save_template($post_id)
		{
            $post = get_post($post_id);
			$context = array(
				'layout_name' => $post->post_name
			);
			$this->noticeMessage('template_saved', $context);
		}
        
        function save_draft($post_id, $publish)
		{
			$context = array(
				'layout_name' => $post_id
			);
			$this->noticeMessage('draft_saved', $context);
		}

        function save_layout($post_id, $publish, $data, $settings)
        {
            $post = get_post($post_id);
            $context = array(
                'layout_name' => $post->post_name
            );
            if ( $publish ) {
                $this->noticeMessage('layout_saved', $context);
            }
        }

        function save_admin()
        {
            $this->noticeMessage('admin_saved');
        }
    } // class
} // End if().
