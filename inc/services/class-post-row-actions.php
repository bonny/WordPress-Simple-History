<?php

namespace Simple_History\Services;

use Simple_History\Helpers;

/**
 * Add a "View history" action link to post/page list tables
 * that links to Simple History filtered by that post's ID.
 */
class Post_Row_Actions extends Service {
	/** @inheritdoc */
	public function loaded() {
		add_filter( 'post_row_actions', array( $this, 'add_view_history_link' ), 10, 2 );
		add_filter( 'page_row_actions', array( $this, 'add_view_history_link' ), 10, 2 );
	}

	/**
	 * Add "View history" link to post/page row actions.
	 *
	 * @param array    $actions Array of row action links.
	 * @param \WP_Post $post    The post object.
	 * @return array Modified array of row action links.
	 */
	public function add_view_history_link( $actions, $post ) {
		// phpcs:ignore WordPress.WP.Capabilities.Undetermined -- Capability is filterable, defaults to 'edit_pages'.
		if ( ! current_user_can( Helpers::get_view_history_capability() ) ) {
			return $actions;
		}

		$url = add_query_arg(
			array(
				'context'      => 'post_id:' . $post->ID,
				'show-filters' => '1',
			),
			Helpers::get_history_admin_url()
		);

		$actions['simple_history'] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( $url ),
			esc_html__( 'View history', 'simple-history' )
		);

		return $actions;
	}
}
