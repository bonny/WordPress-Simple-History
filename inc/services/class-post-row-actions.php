<?php

namespace Simple_History\Services;

use Simple_History\Helpers;

/**
 * Add a "View history" action link to post/page list tables
 * that links to Simple History filtered by that post's ID.
 *
 * Only active when experimental features are enabled.
 * Reuses Post_History_Column's batch query to avoid duplicate database queries.
 */
class Post_Row_Actions extends Service {
	/** @inheritdoc */
	public function loaded() {
		if ( ! Helpers::experimental_features_is_enabled() ) {
			return;
		}

		add_filter( 'post_row_actions', array( $this, 'add_view_history_link' ), 10, 2 );
		add_filter( 'page_row_actions', array( $this, 'add_view_history_link' ), 10, 2 );
	}

	/**
	 * Add "View history" link to post/page row actions,
	 * only for posts that have logged events.
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

		// Reuse the column service's batch query instead of running a separate one.
		$column_service = $this->simple_history->get_service( Post_History_Column::class );
		if ( ! $column_service || ! $column_service->post_has_history( $post->ID ) ) {
			return $actions;
		}

		$url = Post_History_Column::get_post_history_url( $post->ID );

		$actions['simple_history'] = sprintf(
			'<a href="%s" aria-label="%s">%s</a>',
			esc_url( $url ),
			esc_attr(
				sprintf(
					// translators: %s is the post title.
					__( 'View history for "%s"', 'simple-history' ),
					$post->post_title
				)
			),
			esc_html__( 'View history', 'simple-history' )
		);

		return $actions;
	}
}
