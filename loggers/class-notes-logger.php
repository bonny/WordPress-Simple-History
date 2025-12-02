<?php

namespace Simple_History\Loggers;

use Simple_History\Event_Details\Event_Details_Group;
use Simple_History\Event_Details\Event_Details_Item;

/**
 * Logger for WordPress 6.9+ Notes feature (block comments).
 *
 * Logs collaborative notes that can be added to blocks in the WordPress editor.
 * Notes are stored as comments with comment_type='note' and linked to blocks
 * via the block's metadata.noteId attribute.
 *
 * @package SimpleHistory
 * @since 5.0.0
 */
class Notes_Logger extends Logger {
	/**
	 * Logger slug.
	 *
	 * @var string
	 */
	public $slug = 'NotesLogger';

	/**
	 * Return info about logger.
	 *
	 * @return array Array with logger info.
	 */
	public function get_info() {
		$arr_info = [
			'name'        => _x( 'Notes Logger', 'Logger: Notes', 'simple-history' ),
			'description' => _x( 'Logs WordPress block notes (collaborative comments)', 'Logger: Notes', 'simple-history' ),
			'capability'  => 'edit_posts',
			'messages'    => [
				'note_added'       => _x( 'Added a note to {post_type} "{post_title}"', 'Logger: Notes', 'simple-history' ),
				'note_reply_added' => _x( 'Replied to a note in {post_type} "{post_title}"', 'Logger: Notes', 'simple-history' ),
				'note_edited'      => _x( 'Edited a note in {post_type} "{post_title}"', 'Logger: Notes', 'simple-history' ),
				'note_deleted'     => _x( 'Deleted a note from {post_type} "{post_title}"', 'Logger: Notes', 'simple-history' ),
				'note_resolved'    => _x( 'Marked a note as resolved in {post_type} "{post_title}"', 'Logger: Notes', 'simple-history' ),
				'note_reopened'    => _x( 'Reopened a resolved note in {post_type} "{post_title}"', 'Logger: Notes', 'simple-history' ),
			],
			'labels'      => [
				'search' => [
					'label'   => _x( 'Notes', 'Notes logger: search', 'simple-history' ),
					'options' => [
						_x( 'Added notes', 'Notes logger: search', 'simple-history' ) => [
							'note_added',
						],
						_x( 'Replied to notes', 'Notes logger: search', 'simple-history' ) => [
							'note_reply_added',
						],
						_x( 'Edited notes', 'Notes logger: search', 'simple-history' ) => [
							'note_edited',
						],
						_x( 'Deleted notes', 'Notes logger: search', 'simple-history' ) => [
							'note_deleted',
						],
						_x( 'Resolved notes', 'Notes logger: search', 'simple-history' ) => [
							'note_resolved',
						],
						_x( 'Reopened notes', 'Notes logger: search', 'simple-history' ) => [
							'note_reopened',
						],
					],
				],
			],
		];

		return $arr_info;
	}

	/**
	 * Called when logger is loaded.
	 */
	public function loaded() {
		// Hook into comment actions to track notes.
		add_action( 'wp_insert_comment', [ $this, 'on_wp_insert_comment' ], 10, 2 );
		add_action( 'edit_comment', [ $this, 'on_edit_comment' ], 10, 1 );
		add_action( 'updated_comment_meta', [ $this, 'on_updated_comment_meta' ], 10, 4 );
		add_action( 'added_comment_meta', [ $this, 'on_updated_comment_meta' ], 10, 4 );
		add_action( 'delete_comment', [ $this, 'on_delete_comment' ], 10, 2 );
		add_action( 'trash_comment', [ $this, 'on_delete_comment' ], 10, 2 );
	}

	/**
	 * Get output for detailed log section.
	 *
	 * @param object $row Log row.
	 * @return Event_Details_Group
	 */
	public function get_log_row_details_output( $row ) {
		return ( new Event_Details_Group() )
			->add_items(
				[
					new Event_Details_Item(
						'note_content',
						_x( 'Content', 'Notes logger - detailed output', 'simple-history' ),
					),
				]
			);
	}

	/**
	 * Log when a note is created.
	 *
	 * @param int              $comment_id The comment ID.
	 * @param \WP_Comment|null $comment    Comment object.
	 */
	public function on_wp_insert_comment( $comment_id, $comment = null ) {
		if ( empty( $comment_id ) ) {
			return;
		}

		if ( ! $comment ) {
			$comment = get_comment( $comment_id );
		}

		if ( ! $this->is_note_comment( $comment ) ) {
			return;
		}

		$comment_content = trim( $comment->comment_content );

		// Skip if this comment has no content.
		// Empty comments are status markers (resolved/reopened) that will be logged
		// separately by on_updated_comment_meta when the _wp_note_status meta is added.
		if ( empty( $comment_content ) ) {
			return;
		}

		$post = get_post( $comment->comment_post_ID );
		if ( ! $post ) {
			return;
		}

		$is_reply   = $comment->comment_parent > 0;
		$block_info = $this->get_block_info_for_note( $comment_id, $comment->comment_post_ID );

		$context = [
			'note_id'      => $comment_id,
			'post_id'      => $comment->comment_post_ID,
			'post_type'    => get_post_type( $post ),
			'post_title'   => $post->post_title,
			'note_content' => $comment_content,
			'is_reply'     => $is_reply,
		];

		// Add block information if available.
		if ( $block_info ) {
			$context['block_type']            = $block_info['block_type'];
			$context['block_content_preview'] = $block_info['content_preview'];
			$context['block_count']           = $block_info['block_count'];
		}

		// Choose appropriate message key.
		$message = $is_reply ? 'note_reply_added' : 'note_added';

		$this->info_message( $message, $context );
	}

	/**
	 * Log when a note is edited.
	 *
	 * @param int $comment_id The comment ID.
	 */
	public function on_edit_comment( $comment_id ) {
		if ( empty( $comment_id ) ) {
			return;
		}

		$comment = get_comment( $comment_id );

		if ( ! $this->is_note_comment( $comment ) ) {
			return;
		}

		$post = get_post( $comment->comment_post_ID );
		if ( ! $post ) {
			return;
		}

		$context = [
			'note_id'      => $comment_id,
			'post_id'      => $comment->comment_post_ID,
			'post_type'    => get_post_type( $post ),
			'post_title'   => $post->post_title,
			'note_content' => $comment->comment_content,
		];

		$this->info_message( 'note_edited', $context );
	}

	/**
	 * Log when a note is resolved or reopened.
	 *
	 * Fires when the _wp_note_status comment meta is added or updated.
	 * The status can be 'resolved' (note marked as done) or 'reopen' (note reopened).
	 *
	 * @param int    $meta_id    ID of updated metadata entry.
	 * @param int    $comment_id Comment ID.
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value Meta value (either 'resolved' or 'reopen').
	 */
	public function on_updated_comment_meta( $meta_id, $comment_id, $meta_key, $meta_value ) {
		if ( empty( $comment_id ) ) {
			return;
		}

		if ( $meta_key !== '_wp_note_status' ) {
			return;
		}

		$comment = get_comment( $comment_id );

		if ( ! $this->is_note_comment( $comment ) ) {
			return;
		}

		$post = get_post( $comment->comment_post_ID );
		if ( ! $post ) {
			return;
		}

		$context = [
			'note_id'    => $comment_id,
			'post_id'    => $comment->comment_post_ID,
			'post_type'  => get_post_type( $post ),
			'post_title' => $post->post_title,
		];

		// Determine message based on new status.
		if ( $meta_value === 'resolved' ) {
			$this->info_message( 'note_resolved', $context );
		} elseif ( $meta_value === 'reopen' ) {
			$this->info_message( 'note_reopened', $context );
		}
	}

	/**
	 * Log when a note is deleted or trashed.
	 *
	 * Handles both permanent deletion and trashing (e.g., via REST API).
	 *
	 * @param int              $comment_id The comment ID.
	 * @param \WP_Comment|null $comment    Comment object (optional, added in WP 6.2).
	 */
	public function on_delete_comment( $comment_id, $comment = null ) {
		if ( empty( $comment_id ) ) {
			return;
		}

		// The $comment parameter was added in WordPress 6.2.
		// For backwards compatibility, fetch it if not provided.
		if ( ! $comment ) {
			$comment = get_comment( $comment_id );
		}

		if ( ! $this->is_note_comment( $comment ) ) {
			return;
		}

		$post = get_post( $comment->comment_post_ID );
		if ( ! $post ) {
			return;
		}

		$block_info = $this->get_block_info_for_note( $comment_id, $comment->comment_post_ID );

		$context = [
			'note_id'    => $comment_id,
			'post_id'    => $comment->comment_post_ID,
			'post_type'  => get_post_type( $post ),
			'post_title' => $post->post_title,
		];

		// Add block information if available.
		if ( $block_info ) {
			$context['block_type']            = $block_info['block_type'];
			$context['block_content_preview'] = $block_info['content_preview'];
		}

		$this->info_message( 'note_deleted', $context );
	}

	/**
	 * Check if a comment is a note.
	 *
	 * @param \WP_Comment|null $comment The comment object.
	 * @return bool True if this is a note, false otherwise.
	 */
	private function is_note_comment( $comment ) {
		return $comment && 'note' === $comment->comment_type;
	}

	/**
	 * Get the root note ID by walking up the parent chain.
	 *
	 * WordPress stores only the root note ID in block metadata.noteId.
	 * For threaded notes (replies), we need to find the root.
	 *
	 * @param int $note_id The note (comment) ID.
	 * @return int The root note ID.
	 */
	private function get_root_note_id( $note_id ) {
		$comment = get_comment( $note_id );
		if ( ! $comment ) {
			return $note_id;
		}

		// Walk up the parent chain until we find the root.
		while ( $comment->comment_parent > 0 ) {
			$parent = get_comment( $comment->comment_parent );
			if ( ! $parent ) {
				break;
			}
			$comment = $parent;
		}

		return (int) $comment->comment_ID;
	}

	/**
	 * Get block information for a note.
	 *
	 * Parses the post content to find the block(s) that reference this note
	 * via their metadata.noteId attribute. For threaded notes (replies), walks
	 * up the parent chain to find the root note ID, since only the root note
	 * ID is stored in the block metadata.
	 *
	 * @param int $note_id The note (comment) ID.
	 * @param int $post_id The post ID.
	 * @return array|null Array with block info, or null if block not found.
	 */
	private function get_block_info_for_note( $note_id, $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return null;
		}

		// Find the root note ID by walking up the parent chain.
		// Only the root note ID is stored in block metadata.
		$root_note_id = $this->get_root_note_id( $note_id );

		$blocks       = parse_blocks( $post->post_content );
		$found_blocks = $this->find_blocks_by_note_id( $blocks, $root_note_id );

		if ( empty( $found_blocks ) ) {
			return null; // Block might have been deleted.
		}

		$block = $found_blocks[0];

		// Extract block type (remove 'core/' prefix if present).
		$block_type = $block['blockName'];
		if ( strpos( $block_type, 'core/' ) === 0 ) {
			$block_type = substr( $block_type, 5 );
		}

		// Get content preview.
		$content         = wp_strip_all_tags( $block['innerHTML'] );
		$content         = trim( $content );
		$content_preview = $content;

		// Truncate to 100 characters.
		if ( strlen( $content ) > 100 ) {
			$content_preview = substr( $content, 0, 100 ) . '...';
		}

		return [
			'block_type'      => $block_type,
			'block_name'      => $block['blockName'],
			'content_preview' => $content_preview,
			'full_content'    => $content,
			'block_count'     => count( $found_blocks ),
			'attrs'           => $block['attrs'],
		];
	}

	/**
	 * Recursively find blocks with a specific noteId.
	 *
	 * @param array $blocks  Array of parsed blocks.
	 * @param int   $note_id The note ID to search for.
	 * @return array Array of blocks that reference this note.
	 */
	private function find_blocks_by_note_id( $blocks, $note_id ) {
		$found = [];

		foreach ( $blocks as $block ) {
			// Check if this block has the noteId in its metadata.
			if ( isset( $block['attrs']['metadata']['noteId'] ) &&
				$block['attrs']['metadata']['noteId'] === $note_id ) {
				$found[] = $block;
			}

			// Recursively search inner blocks.
			if ( ! empty( $block['innerBlocks'] ) ) {
				$found = array_merge(
					$found,
					$this->find_blocks_by_note_id( $block['innerBlocks'], $note_id )
				);
			}
		}

		return $found;
	}
}
