<?php

namespace Simple_History\Loggers;

/**
 * Logger for Beaver Builder
 */

// phpcs:ignore Squiz.Classes.ValidClassName.NotCamelCaps
class Plugin_Beaver_Builder_Logger extends Logger {

    public $slug = 'Plugin_BeaverBuilder';

    public function get_info() {
        $arr_info = array(
            'name'        => _x( 'Plugin: Beaver Builder Logger', 'Logger: Plugin Beaver Builder', 'simple-history' ),
            'description' => _x(
                'Logs various things in Beaver Builder',
                'Logger: Plugin Beaver Builder',
                'simple-history'
            ),
            'name_via'    => _x(
                'Using plugin Beaver Builder',
                'Logger: Plugin Beaver Builder',
                'simple-history'
            ),
            'capability' => 'manage_options',
            'messages'   => array(
                'layout_saved'   => __(
                    'Layout "{layout_name}" updated',
                    'simple-history'
                ),
                'template_saved' => __(
                    'Template "{layout_name}" updated',
                    'simple-history'
                ),
                'draft_saved'    => __(
                    'Draft "{layout_name}" updated',
                    'simple-history'
                ),
                'admin_saved'    => __(
                    'Beaver Builder settings saved',
                    'simple-history'
                ),
            ),
        );

        return $arr_info;
    }

    public function loaded() {
        if ( ! class_exists( 'FLBuilder' ) ) {
            return;
        }

        add_action(
            'fl_builder_after_save_layout',
            array( $this, 'saveLayout' ),
            10,
            4
        );
        add_action(
            'fl_builder_after_save_user_template',
            array( $this, 'saveTemplate' ),
            10,
            1
        );
        add_action(
            'fl_builder_after_save_draft',
            array( $this, 'saveDraft' ),
            10,
            2
        );
        add_action(
            'fl_builder_admin_settings_save',
            array(
                $this,
                'saveAdmin',
            )
        );
    }

    public function saveTemplate( $post_id ) {
        $post = get_post( $post_id );
        $context = array(
            'layout_name' => $post->post_name,
        );
        $this->notice_message( 'template_saved', $context );
    }

    public function saveDraft( $post_id, $publish ) {
        $context = array(
            'layout_name' => $post_id,
        );
        $this->notice_message( 'draft_saved', $context );
    }

    public function saveLayout( $post_id, $publish, $data, $settings ) {
        $post = get_post( $post_id );
        $context = array(
            'layout_name' => $post->post_name,
        );
        if ( $publish ) {
            $this->notice_message( 'layout_saved', $context );
        }
    }

    public function saveAdmin() {
        $this->notice_message( 'admin_saved' );
    }
}

