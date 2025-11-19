<?php

namespace Simple_History\Loggers;

use Simple_History\Helpers;

/**
 * Logger for the Yoast Duplicate Post plugin
 * (https://sv.wordpress.org/plugins/duplicate-post/)
 *
 * @package SimpleHistory
 * @since 2.13
 */
class Plugin_Duplicate_Post_Logger extends Logger {
	/** @var string Logger slug */
	public $slug = 'Plugin_DuplicatePost';

	/**
	 * Return info about this logger
	 *
	 * @return array
	 */
	public function get_info() {
		$arr_info = array(
			'name'        => _x( 'Plugin: Duplicate Posts Logger', 'Logger: Plugin Duplicate Post', 'simple-history' ),
			'description' => _x(
				'Logs posts and pages cloned using plugin Duplicate Post',
				'Logger: Plugin Duplicate Post',
				'simple-history'
			),
			'name_via'    => _x( 'Using plugin Yoast Duplicate Post', 'Logger: Plugin Duplicate Post', 'simple-history' ),
			'capability'  => 'manage_options',
			'messages'    => array(
				'post_duplicated' => _x(
					'Cloned "{duplicated_post_title}" to a new post',
					'Logger: Plugin Duplicate Post',
					'simple-history'
				),
			),
		);

		return $arr_info;
	}

	/**
	 * @inheritdoc
	 */
	public function loaded() {
		$isPluginActive = Helpers::is_plugin_active( 'duplicate-post/duplicate-post.php' );

		if ( ! $isPluginActive ) {
			return;
		}

		// When a copy have been made of a post or page
		// the action 'dp_duplicate_page' or 'dp_duplicate_post'
		// is fired with args $new_post_id, $post, $status.
		// We add actions with priority 20 so we probably run after
		// the plugins own.
		add_action( 'dp_duplicate_post', array( $this, 'onDpDuplicatePost' ), 100, 3 );
		add_action( 'dp_duplicate_page', array( $this, 'onDpDuplicatePost' ), 100, 3 );
	}

	/**
	 * A post or page was duplicated
	 *
	 * @param int      $new_post_id id of new post that was created.
	 * @param \WP_Post $post old post that a copy was made of.
	 * @param string   $status status of new post.
	 */
	public function onDpDuplicatePost( $new_post_id, $post, $status ) {
		$new_post = get_post( $new_post_id );

		$context = array(
			'new_post_title'        => $new_post->post_title,
			'new_post_id'           => $new_post->ID,
			'duplicated_post_title' => $post->post_title,
			'duplicated_post_id'    => $post->ID,
		);

		$this->info_message( 'post_duplicated', $context );
	}

	/**
	 * Modify plain output to include link to post
	 *
	 * @param object $row Log row.
	 */
	public function get_log_row_plain_text_output( $row ) {
		$context            = $row->context;
		$new_post_id        = $context['new_post_id'] ?? null;
		$duplicated_post_id = $context['duplicated_post_id'] ?? null;

		$message = $row->message;

		// Check if post still is available
		// It will return a WP_Post Object if post still is in system
		// If post is deleted from trash (not just moved there), then null is returned.
		$postDuplicated    = get_post( $duplicated_post_id );
		$post_is_available = is_a( $postDuplicated, 'WP_Post' );

		// Try to get singular name.
		$post_type     = $postDuplicated->post_type ?? '';
		$post_type_obj = get_post_type_object( $post_type );

		if ( ! is_null( $post_type_obj ) && ! empty( $post_type_obj->labels->singular_name ) ) {
			$context['duplicated_post_post_type_singular_name'] = strtolower(
				$post_type_obj->labels->singular_name
			);
		}

		$context['duplicated_post_edit_link'] = get_edit_post_link( $duplicated_post_id );
		$context['new_post_edit_link']        = get_edit_post_link( $new_post_id );

		// If post is not available any longer then we can't link to it, so keep plain message then
		// Also keep plain format if user is not allowed to edit post (edit link is empty).
		if ( $post_is_available && $context['duplicated_post_edit_link'] ) {
			$message = _x(
				'Cloned {duplicated_post_post_type_singular_name} <a href="{duplicated_post_edit_link}">"{duplicated_post_title}"</a> to <a href="{new_post_edit_link}">a new {duplicated_post_post_type_singular_name}</a>',
				'Logger: Plugin Duplicate Post',
				'simple-history'
			);
		}

		$context['new_post_edit_link'] = isset( $context['new_post_edit_link'] )
			? esc_html( $context['new_post_edit_link'] )
			: '';

		$context['duplicated_post_edit_link'] = isset( $context['duplicated_post_edit_link'] )
			? esc_html( $context['duplicated_post_edit_link'] )
			: '';

		$context['duplicated_post_title'] = isset( $context['duplicated_post_title'] )
			? esc_html( $context['duplicated_post_title'] )
			: '';

		$context['duplicated_post_title'] = isset( $context['duplicated_post_title'] )
			? esc_html( $context['duplicated_post_title'] )
			: '';

		$context['duplicated_post_post_type_singular_name'] = isset(
			$context['duplicated_post_post_type_singular_name']
		)
			? esc_html( $context['duplicated_post_post_type_singular_name'] )
			: '';

		return helpers::interpolate( $message, $context, $row );
	}
}
