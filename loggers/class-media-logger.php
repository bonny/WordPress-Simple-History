<?php

namespace Simple_History\Loggers;

use Simple_History\Helpers;
use Simple_History\Event_Details\Event_Details_Container;
use Simple_History\Event_Details\Event_Details_Container_Interface;
use Simple_History\Event_Details\Event_Details_Group;
use Simple_History\Event_Details\Event_Details_Group_Inline_Formatter;
use Simple_History\Event_Details\Event_Details_Item;

/**
 * Logs media uploads
 */
class Media_Logger extends Logger {
	/** @var string Logger slug */
	public $slug = 'SimpleMediaLogger';

	/** @var array Array with prev attachment values, before save. */
	protected array $prev_attachment_values = [];

	/** @var array<int,array{prev:string,new:string}> Pending alt-text diffs awaiting shutdown flush, keyed by attachment id. */
	protected array $pending_alt_text_changes = [];

	/** @var array<int,bool> Attachment ids that already produced an attachment_updated event this request. */
	protected array $attachment_updated_logged = [];

	/**
	 * Get array with information about this logger
	 *
	 * @return array
	 */
	public function get_info() {

		return array(
			'name'        => __( 'Media/Attachments Logger', 'simple-history' ),
			'description' => __( 'Logs media uploads and edits', 'simple-history' ),
			'capability'  => 'edit_pages',
			'messages'    => array(
				'attachment_created'      => __( 'Created {post_type} "{attachment_title}"', 'simple-history' ),
				'attachment_updated'      => __( 'Edited attachment "{attachment_title}"', 'simple-history' ),
				'attachment_image_edited' => __( 'Edited image "{attachment_title}"', 'simple-history' ),
				'attachment_deleted'      => __( 'Deleted {post_type} "{attachment_title}" ("{attachment_filename}")', 'simple-history' ),
			),
			'labels'      => array(
				'search' => array(
					'label'     => _x( 'Media', 'Media logger: search', 'simple-history' ),
					'label_all' => _x( 'All media activity', 'Media logger: search', 'simple-history' ),
					'options'   => array(
						_x( 'Added media', 'Media logger: search', 'simple-history' ) => array(
							'attachment_created',
						),
						_x( 'Updated media', 'Media logger: search', 'simple-history' ) => array(
							'attachment_updated',
							'attachment_image_edited',
						),
						_x( 'Deleted media', 'Media logger: search', 'simple-history' ) => array(
							'attachment_deleted',
						),
					),
				),
			),
		);
	}

	/**
	 * @inheritdoc
	 */
	public function loaded() {
		add_action( 'add_attachment', array( $this, 'on_add_attachment' ) );
		add_action( 'attachment_updated', array( $this, 'on_attachment_updated' ), 10, 3 );
		add_action( 'delete_attachment', array( $this, 'on_delete_attachment' ) );
		add_action( 'xmlrpc_call_success_mw_newMediaObject', array( $this, 'on_mw_new_media_object' ), 10, 2 );
		add_filter( 'simple_history/rss_item_link', array( $this, 'filter_rss_item_link' ), 10, 2 );
		add_filter( 'wp_save_image_editor_file', array( $this, 'on_save_image_editor_file' ), 10, 5 );
		add_action( 'load-post.php', [ $this, 'on_load_post_store_attachment_alt_text' ] );

		// Capture alt text before any write — fires for WP-CLI and other non-REST contexts.
		// The filter runs before the meta is written, so get_post_meta() returns the old value.
		add_filter( 'update_post_metadata', [ $this, 'on_update_post_metadata_capture_alt_text' ], 10, 5 );

		// For REST API: alt text meta is updated AFTER attachment_updated fires (by the REST controller),
		// so we need a dedicated hook pair to capture before and append diff after.
		add_filter( 'rest_pre_insert_attachment', [ $this, 'on_rest_pre_insert_attachment_capture_alt_text' ], 10, 2 );
		add_action( 'rest_after_insert_attachment', [ $this, 'on_rest_after_insert_attachment_append_alt_text' ], 10, 3 );

		// Catch bare update_post_meta() writes that never trigger attachment_updated
		// (e.g. `wp post meta update <id> _wp_attachment_image_alt "..."`). The shutdown
		// flusher is registered lazily — only after the first pending entry is queued —
		// so the hook isn't paid for on every request.
		add_action( 'updated_post_meta', [ $this, 'on_updated_post_meta_capture_alt_text' ], 10, 4 );
	}

	/**
	 * Capture the current alt text before a write for WP-CLI and other non-REST contexts.
	 * The filter runs before the meta value is written, so get_post_meta() still returns the old value.
	 *
	 * For REST API, alt text is updated AFTER attachment_updated fires, so a separate hook pair
	 * (rest_pre_insert_attachment + rest_after_insert_attachment) handles that flow.
	 *
	 * @param mixed|null $check      Normally null; returning non-null short-circuits the write.
	 * @param int        $object_id  Post ID.
	 * @param string     $meta_key   Meta key being written.
	 * @param mixed      $meta_value New value.
	 * @param mixed      $prev_value Previous value passed by the caller (not the DB value — unused).
	 * @return mixed|null Always returns null to let the write proceed normally.
	 */
	public function on_update_post_metadata_capture_alt_text( $check, $object_id, $meta_key, $meta_value, $prev_value ) {
		if ( $meta_key !== '_wp_attachment_image_alt' ) {
			return $check;
		}

		if ( get_post_type( $object_id ) !== 'attachment' ) {
			return $check;
		}

		$this->prev_attachment_values[ $object_id ] = [
			'alt_text' => get_post_meta( $object_id, '_wp_attachment_image_alt', true ),
		];

		return $check;
	}

	/**
	 * Queue an alt-text diff after a meta write so it can be logged on shutdown
	 * if no attachment_updated event covered it.
	 *
	 * Logging is deferred so that flows which also call wp_update_post() (admin,
	 * REST, the existing WP-CLI post-update path) log via attachment_updated first
	 * and mark the id as already-logged; the shutdown flusher then skips them.
	 *
	 * @param int    $meta_id    Meta row id (unused).
	 * @param int    $object_id  Post ID the meta was written to.
	 * @param string $meta_key   Meta key being written.
	 * @param mixed  $meta_value New meta value.
	 */
	public function on_updated_post_meta_capture_alt_text( $meta_id, $object_id, $meta_key, $meta_value ) {
		if ( $meta_key !== '_wp_attachment_image_alt' ) {
			return;
		}

		if ( get_post_type( $object_id ) !== 'attachment' ) {
			return;
		}

		$prev_alt_text = $this->prev_attachment_values[ $object_id ]['alt_text'] ?? null;

		if ( $prev_alt_text === null ) {
			return;
		}

		$new_alt_text = (string) $meta_value;

		if ( $prev_alt_text === $new_alt_text ) {
			return;
		}

		// Register the shutdown flusher only when there's actually something to flush.
		if ( $this->pending_alt_text_changes === [] ) {
			add_action( 'shutdown', [ $this, 'on_shutdown_log_pending_alt_text_changes' ] );
		}

		$this->pending_alt_text_changes[ $object_id ] = [
			'prev' => $prev_alt_text,
			'new'  => $new_alt_text,
		];
	}

	/**
	 * Flush pending alt-text changes at end of request.
	 *
	 * Emits one attachment_updated event per queued change UNLESS an
	 * attachment_updated action already logged for that id this request
	 * (admin and REST flows).
	 */
	public function on_shutdown_log_pending_alt_text_changes() {
		foreach ( $this->pending_alt_text_changes as $attachment_id => $diff ) {
			if ( isset( $this->attachment_updated_logged[ $attachment_id ] ) ) {
				continue;
			}

			$attachment_post = get_post( $attachment_id );

			// Post may have been deleted between the meta write and shutdown.
			if ( ! $attachment_post instanceof \WP_Post ) {
				continue;
			}

			$this->info_message(
				'attachment_updated',
				[
					'attachment_id'            => $attachment_id,
					'attachment_title'         => $attachment_post->post_title,
					'attachment_mime'          => $attachment_post->post_mime_type,
					'post_type'                => 'attachment',
					'attachment_alt_text_prev' => $diff['prev'],
					'attachment_alt_text_new'  => $diff['new'],
				]
			);
		}

		$this->pending_alt_text_changes  = [];
		$this->attachment_updated_logged = [];
		$this->prev_attachment_values    = [];
	}

	/**
	 * Capture the old alt text before a REST API attachment update.
	 * Fires before wp_update_post() is called (which triggers attachment_updated).
	 *
	 * @param \stdClass        $prepared_post Prepared post data for DB insert/update.
	 * @param \WP_REST_Request $request       Request object.
	 * @return \stdClass $prepared_post Unchanged.
	 */
	public function on_rest_pre_insert_attachment_capture_alt_text( $prepared_post, $request ) {
		if ( empty( $prepared_post->ID ) ) {
			return $prepared_post;
		}

		$this->prev_attachment_values[ $prepared_post->ID ] = [
			'alt_text' => get_post_meta( $prepared_post->ID, '_wp_attachment_image_alt', true ),
		];

		return $prepared_post;
	}

	/**
	 * After a REST API attachment update completes, append the alt text diff to the logged event.
	 * At this point, alt text meta has been updated by the REST controller.
	 *
	 * @param \WP_Post         $attachment Updated attachment post.
	 * @param \WP_REST_Request $request    Request object.
	 * @param bool             $creating   True when creating, false when updating.
	 */
	public function on_rest_after_insert_attachment_append_alt_text( $attachment, $request, $creating ) {
		if ( $creating || ! $this->last_insert_id ) {
			return;
		}

		if ( ! isset( $request['alt_text'] ) ) {
			return;
		}

		$old_alt_text = $this->prev_attachment_values[ $attachment->ID ]['alt_text'] ?? null;
		unset( $this->prev_attachment_values[ $attachment->ID ] );

		if ( $old_alt_text === null ) {
			return;
		}

		$new_alt_text = get_post_meta( $attachment->ID, '_wp_attachment_image_alt', true );

		if ( $old_alt_text === $new_alt_text ) {
			return;
		}

		$this->append_context(
			$this->last_insert_id,
			[
				'attachment_alt_text_prev' => $old_alt_text,
				'attachment_alt_text_new'  => $new_alt_text,
			]
		);
	}

	/**
	 * Store the previous alt text of an attachment when editing it.
	 * Fired when loading admin page post.php.
	 */
	public function on_load_post_store_attachment_alt_text() {
		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
			return;
		}

		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.NonceVerification.Missing
		$post_id   = $_POST['post_ID'] ?? null;
		$post_type = $_POST['post_type'] ?? null;
		$action    = $_POST['action'] ?? null;
		// phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.NonceVerification.Missing

		if ( ! $post_id || $post_type !== 'attachment' || $action !== 'editpost' ) {
			return;
		}

		$this->prev_attachment_values[ $post_id ] = [
			'alt_text' => get_post_meta( $post_id, '_wp_attachment_image_alt', true ),
		];
	}

	/**
	 * Filter that fires after a new attachment has been added via the XML-RPC MovableType API.
	 *
	 * @since 2.0.21
	 *
	 * @param int   $attachment_id ID of the new attachment.
	 * @param array $args          An array of arguments to add the attachment.
	 */
	public function on_mw_new_media_object( $attachment_id, $args ) {
		$attachment_post = get_post( $attachment_id );
		$filename        = esc_html( wp_basename( $attachment_post->guid ) );
		$mime            = get_post_mime_type( $attachment_post );
		$file            = get_attached_file( $attachment_id );
		$file_size       = false;

		if ( file_exists( $file ) ) {
			$file_size = filesize( $file );
		}

		$this->info_message(
			'attachment_created',
			array(
				'post_type'           => get_post_type( $attachment_post ),
				'attachment_id'       => $attachment_id,
				'attachment_title'    => get_the_title( $attachment_post ),
				'attachment_filename' => $filename,
				'attachment_mime'     => $mime,
				'attachment_filesize' => $file_size,
			)
		);
	}

	/**
	 * Log when an image is edited using the WordPress image editor
	 * (crop, rotate, flip, scale).
	 *
	 * Fired from filter 'wp_save_image_editor_file'.
	 *
	 * @param bool|null        $override  Value to return instead of saving. Default null.
	 * @param string           $filename  Name of the file to be saved.
	 * @param \WP_Image_Editor $image     The image editor instance.
	 * @param string           $mime_type The mime type of the image.
	 * @param int              $post_id   Attachment post ID.
	 * @return bool|null The unmodified $override value so normal saving proceeds.
	 */
	public function on_save_image_editor_file( $override, $filename, $image, $mime_type, $post_id ) {
		$attachment_post = get_post( $post_id );

		if ( ! $attachment_post instanceof \WP_Post ) {
			return $override;
		}

		$context = array(
			'attachment_id'       => $post_id,
			'attachment_title'    => get_the_title( $attachment_post ),
			'attachment_mime'     => $mime_type,
			'attachment_filename' => wp_basename( $filename ),
			'post_type'           => get_post_type( $attachment_post ),
		);

		// Detect which edit operations were performed from the request history.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		$history = ! empty( $_REQUEST['history'] ) ? $_REQUEST['history'] : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		$do_action = ! empty( $_REQUEST['do'] ) ? $_REQUEST['do'] : '';

		$operations = [];

		if ( $do_action === 'scale' ) {
			$operations[] = 'scale';
		} elseif ( ! empty( $history ) ) {
			$changes = json_decode( wp_unslash( $history ) );

			if ( is_array( $changes ) ) {
				foreach ( $changes as $change ) {
					if ( isset( $change->r ) ) {
						$operations[] = 'rotate';
					} elseif ( isset( $change->f ) ) {
						$operations[] = 'flip';
					} elseif ( isset( $change->c ) ) {
						$operations[] = 'crop';
					}
				}

				$operations = array_unique( $operations );
			}
		}

		if ( ! empty( $operations ) ) {
			$context['edit_operations'] = implode( ', ', $operations );
		}

		$this->info_message( 'attachment_image_edited', $context );

		return $override;
	}

	/**
	 * Modify plain output to include link to post
	 *
	 * @param object $row Log row.
	 */
	public function get_log_row_plain_text_output( $row ) {
		$message     = $row->message;
		$context     = $row->context;
		$message_key = $context['_message_key'];

		$attachment_id           = $context['attachment_id'];
		$attachment_post         = get_post( $attachment_id );
		$attachment_is_available = $attachment_post instanceof \WP_Post;

		// Only link to attachment if attachment post is still available.
		if ( $attachment_is_available ) {
			if ( $message_key === 'attachment_updated' ) {
				$message = __( 'Edited attachment <a href="{edit_link}">"{attachment_title}"</a>', 'simple-history' );
			} elseif ( $message_key === 'attachment_image_edited' ) {
				$message = __( 'Edited image <a href="{edit_link}">"{attachment_title}"</a>', 'simple-history' );
			} elseif ( $message_key === 'attachment_created' ) {

				if ( isset( $context['attachment_parent_id'] ) ) {
					// Attachment was uploaded to a post. Link to it, if still available.
					$attachment_parent_post      = get_post( $context['attachment_parent_id'] );
					$attachment_parent_available = $attachment_parent_post instanceof \WP_Post;

					$context['attachment_parent_post_type'] = esc_html( $context['attachment_parent_post_type'] ?? '' );
					$context['attachment_parent_title']     = esc_html( $context['attachment_parent_title'] ?? '' );

					if ( $attachment_parent_available ) {
						// Include link to parent post.
						$context['attachment_parent_edit_link'] = get_edit_post_link( $context['attachment_parent_id'] );
						$message                                = __( 'Uploaded {post_type} <a href="{edit_link}">"{attachment_title}"</a> to {attachment_parent_post_type} <a href="{attachment_parent_edit_link}">"{attachment_parent_title}"</a>', 'simple-history' );
					} else {
						// Include only title to parent post.
						$message = __( 'Uploaded {post_type} <a href="{edit_link}">"{attachment_title}"</a> to {attachment_parent_post_type} "{attachment_parent_title}"', 'simple-history' );
					}
				} else {
					$message = __( 'Uploaded {post_type} <a href="{edit_link}">"{attachment_title}"</a>', 'simple-history' );
				}
			}

			$context['post_type']           = esc_html( $context['post_type'] ?? 'attachment' );
			$context['attachment_filename'] = esc_html( $context['attachment_filename'] ?? '' );
			$context['edit_link']           = get_edit_post_link( $attachment_id );

			$message = helpers::interpolate( $message, $context, $row );
		} else {
			// Attachment post is not available, attachment has probably been deleted.
			$message = parent::get_log_row_plain_text_output( $row );
		}

		return $message;
	}

	/**
	 * Get details output for created attachments.
	 *
	 * @param object $row Log row.
	 * @return string|Event_Details_Container_Interface|Event_Details_Group
	 */
	protected function get_details_output_for_created_attachment( $row ) {
		$context                 = $row->context;
		$attachment_id           = $context['attachment_id'];
		$attachment_post         = get_post( $attachment_id );
		$attachment_is_available = is_a( $attachment_post, 'WP_Post' );

		$filetype      = wp_check_filetype( $context['attachment_filename'] );
		$file_url      = wp_get_attachment_url( $attachment_id );
		$edit_link     = get_edit_post_link( $attachment_id );
		$attached_file = get_attached_file( $attachment_id );

		$is_image = wp_attachment_is_image( $attachment_id );
		$is_video = strpos( $filetype['type'], 'video/' ) !== false;
		$is_audio = strpos( $filetype['type'], 'audio/' ) !== false;

		$groups            = [];
		$thumb_html        = '';
		$full_image_width  = null;
		$full_image_height = null;

		// Build thumbnail/media preview HTML.
		if ( $is_image ) {
			$thumb_src = wp_get_attachment_image_src( $attachment_id, 'medium' );
			$full_src  = wp_get_attachment_image_src( $attachment_id, 'full' );

			$full_image_width  = $full_src[1] ?? null;
			$full_image_height = $full_src[2] ?? null;

			if ( $full_image_width && $full_image_height && file_exists( $attached_file ) && $thumb_src ) {
				$thumb_html = sprintf(
					'<a class="SimpleHistoryLogitemThumbnailLink" href="%1$s"><div class="SimpleHistoryLogitemThumbnail"><img src="%2$s" alt=""></div></a>',
					esc_url( (string) $edit_link ),
					esc_url( $thumb_src[0] )
				);
			}
		} elseif ( $is_audio ) {
			$thumb_html = '<div style="max-width: 500px;">'
				. do_shortcode( sprintf( '[audio src="%1$s"]', $file_url ) )
				. '</div>';
		} elseif ( $is_video ) {
			$thumb_html = do_shortcode( sprintf( '[video src="%1$s" width="250" height="150"]', $file_url ) );
		} elseif ( $attachment_is_available ) {
			$thumb_html = sprintf(
				'<div class="SimpleHistoryLogitemThumbnail">%1$s</div>',
				wp_get_attachment_image( $attachment_id, array( 350, 500 ), true )
			);
		}

		// Thumbnail group (RAW).
		if ( ! empty( $thumb_html ) ) {
			$groups[] = Event_Details_Group::create_raw(
				$thumb_html,
				[
					'type'          => 'media_preview',
					'attachment_id' => (int) $attachment_id,
					'media_type'    => $is_image ? 'image' : ( $is_audio ? 'audio' : ( $is_video ? 'video' : 'file' ) ),
				]
			);
		}

		// Metadata group (inline).
		$meta_group = ( new Event_Details_Group() )
			->set_formatter( new Event_Details_Group_Inline_Formatter() );

		if ( ! empty( $row->context['attachment_filesize'] ) ) {
			$meta_group->add_item(
				( new Event_Details_Item( null, __( 'Size', 'simple-history' ) ) )
					->set_new_value( size_format( $row->context['attachment_filesize'] ) )
			);
		}

		$meta_group->add_item(
			( new Event_Details_Item( null, __( 'Type', 'simple-history' ) ) )
				->set_new_value( strtoupper( $filetype['ext'] ) )
		);

		if ( $full_image_width && $full_image_height ) {
			$meta_group->add_item(
				( new Event_Details_Item( null, __( 'Dimensions', 'simple-history' ) ) )
					->set_new_value( "{$full_image_width} × {$full_image_height}" )
			);
		}

		$groups[] = $meta_group;

		return Event_Details_Container::create_from( $groups );
	}

	/**
	 * Get details output for updated attachments.
	 *
	 * @param object $row Log row.
	 */
	protected function get_details_output_for_updated_attachment( $row ) {
		return ( new Event_Details_Group() )
			->set_title( __( 'Changed values', 'simple-history' ) )
			->add_items(
				[
					new Event_Details_Item(
						[ 'attachment_title' ],
						__( 'Title', 'simple-history' ),
					),
					new Event_Details_Item(
						[ 'attachment_alt_text' ],
						__( 'Alternative text', 'simple-history' ),
					),
					new Event_Details_Item(
						[ 'attachment_excerpt' ],
						__( 'Caption', 'simple-history' ),
					),
					new Event_Details_Item(
						[ 'attachment_content' ],
						__( 'Description', 'simple-history' ),
					),
					new Event_Details_Item(
						[ 'attachment_name' ],
						__( 'Slug', 'simple-history' ),
					),
				]
			);
	}

	/**
	 * Get details output for image editing events.
	 *
	 * @param object $row Log row.
	 * @return Event_Details_Group|Event_Details_Container|string
	 */
	protected function get_details_output_for_image_edited( $row ) {
		$context       = $row->context;
		$attachment_id = (int) ( $context['attachment_id'] ?? 0 );
		$groups        = [];

		// Show thumbnail if the image attachment is still available.
		if ( $attachment_id && wp_attachment_is_image( $attachment_id ) ) {
			$attached_file = get_attached_file( $attachment_id );
			$thumb_src     = wp_get_attachment_image_src( $attachment_id, 'medium' );
			$edit_link     = get_edit_post_link( $attachment_id );

			if ( $attached_file && file_exists( $attached_file ) && $thumb_src ) {
				$thumb_html = sprintf(
					'<a class="SimpleHistoryLogitemThumbnailLink" href="%1$s"><div class="SimpleHistoryLogitemThumbnail SimpleHistoryLogitemThumbnail--small"><img src="%2$s" alt=""></div></a>',
					esc_url( (string) $edit_link ),
					esc_url( $thumb_src[0] )
				);

				$groups[] = Event_Details_Group::create_raw(
					$thumb_html,
					[
						'type'          => 'image_thumbnail',
						'attachment_id' => $attachment_id,
					]
				);
			}
		}

		// Show edit operations.
		if ( ! empty( $context['edit_operations'] ) ) {
			$operation_labels = [
				'crop'   => __( 'Cropped', 'simple-history' ),
				'rotate' => __( 'Rotated', 'simple-history' ),
				'flip'   => __( 'Flipped', 'simple-history' ),
				'scale'  => __( 'Scaled', 'simple-history' ),
			];

			$operations = array_map( 'trim', explode( ',', $context['edit_operations'] ) );
			$labels     = [];

			foreach ( $operations as $operation ) {
				if ( ! isset( $operation_labels[ $operation ] ) ) {
					continue;
				}

				$labels[] = $operation_labels[ $operation ];
			}

			if ( ! empty( $labels ) ) {
				$ops_group = ( new Event_Details_Group() )
					->set_formatter( new Event_Details_Group_Inline_Formatter() );
				$ops_group->add_item(
					( new Event_Details_Item( null, __( 'Operations', 'simple-history' ) ) )
						->set_new_value( implode( ', ', $labels ) )
				);
				$groups[] = $ops_group;
			}
		}

		if ( empty( $groups ) ) {
			return '';
		}

		return Event_Details_Container::create_from( $groups );
	}

	/**
	 * Get action links for a log row.
	 *
	 * @param object $row Log row object.
	 * @return array Array of action link arrays.
	 */
	public function get_action_links( $row ) {
		$context       = $row->context;
		$message_key   = $context['_message_key'] ?? '';
		$attachment_id = isset( $context['attachment_id'] ) ? (int) $context['attachment_id'] : 0;

		$action_links = [];

		if ( $attachment_id && $message_key !== 'attachment_deleted' ) {
			$attachment = get_post( $attachment_id );

			if ( $attachment instanceof \WP_Post ) {
				if ( current_user_can( 'edit_post', $attachment_id ) ) {
					$edit_link = get_edit_post_link( $attachment_id, 'raw' );
					if ( $edit_link ) {
						$action_links[] = [
							'url'    => $edit_link,
							'label'  => __( 'Edit attachment', 'simple-history' ),
							'action' => 'edit',
						];
					}
				}

				$permalink = wp_get_attachment_url( $attachment_id );
				if ( $permalink ) {
					$action_links[] = [
						'url'    => $permalink,
						'label'  => __( 'View attachment', 'simple-history' ),
						'action' => 'view',
					];
				}
			}
		}

		// Overview link survives on attachment_deleted events where the
		// per-attachment links above are suppressed.
		if ( current_user_can( 'upload_files' ) ) {
			$action_links[] = [
				'url'    => admin_url( 'upload.php' ),
				'label'  => __( 'All media', 'simple-history' ),
				'action' => 'view',
			];
		}

		return $action_links;
	}

	/**
	 * Get output for detailed log section
	 *
	 * @param object $row Row.
	 * @return string|Event_Details_Container_Interface|Event_Details_Group
	 */
	public function get_log_row_details_output( $row ) {
		$message_key = $row->context['_message_key'];

		if ( $message_key === 'attachment_created' ) {
			return $this->get_details_output_for_created_attachment( $row );
		}

		if ( $message_key === 'attachment_updated' ) {
			return $this->get_details_output_for_updated_attachment( $row );
		}

		if ( $message_key === 'attachment_image_edited' ) {
			return $this->get_details_output_for_image_edited( $row );
		}

		return '';
	}

	/**
	 * Check if we should log this request.
	 * We don't want to log requests to the plugin or theme install pages,
	 * where a ZIP file is uploaded and then deleted.
	 *
	 * @return bool
	 */
	protected function is_plugin_or_theme_install() {
		$install_referrers = [
			'/wp-admin/plugin-install.php',
			'/wp-admin/theme-install.php',
		];

		return in_array( wp_get_raw_referer(), $install_referrers, true );
	}

	/**
	 * Called when an attachment is added.
	 * Fired from filter 'add_attachment'.
	 * It is not fired when image is added in Block Editor.
	 * It is fired when a plugin is installed using a ZIP file.
	 *
	 * @param int $attachment_id Attachment ID.
	 */
	public function on_add_attachment( $attachment_id ) {
		if ( $this->is_plugin_or_theme_install() ) {
			return;
		}

		$attachment_post = get_post( $attachment_id );
		$filename        = esc_html( wp_basename( $attachment_post->guid ) );
		$mime            = get_post_mime_type( $attachment_post );
		$file            = get_attached_file( $attachment_id );
		$file_size       = file_exists( $file ) ? filesize( $file ) : null;

		$context = array(
			'post_type'           => get_post_type( $attachment_post ),
			'attachment_id'       => $attachment_id,
			'attachment_title'    => get_the_title( $attachment_post ),
			'attachment_filename' => $filename,
			'attachment_mime'     => $mime,
			'attachment_filesize' => $file_size,
		);

		// Add information about possible parent.
		$attachment_parent_id        = wp_get_post_parent_id( $attachment_post );
		$attachment_parent_title     = $attachment_parent_id ? get_the_title( $attachment_parent_id ) : null;
		$attachment_parent_post_type = $attachment_parent_id ? get_post_type( $attachment_parent_id ) : null;

		if ( $attachment_parent_id ) {
			$context['attachment_parent_id']        = $attachment_parent_id;
			$context['attachment_parent_title']     = $attachment_parent_title;
			$context['attachment_parent_post_type'] = $attachment_parent_post_type;
		}

		$this->info_message(
			'attachment_created',
			$context
		);
	}

	/**
	 * Fires once an existing attachment has been updated.
	 *
	 * @param int      $attachment_id      Post ID.
	 * @param \WP_Post $post_new   Post object following the update.
	 * @param \WP_Post $post_prev  Post object before the update.
	 */
	public function on_attachment_updated( $attachment_id, $post_new, $post_prev ) {
		if ( ! $post_new instanceof \WP_Post || ! $post_prev instanceof \WP_Post ) {
			return;
		}

		$context = [
			'attachment_id'    => $attachment_id,
			'attachment_title' => $post_new->post_title,
			'attachment_mime'  => $post_new->post_mime_type,
			'post_type'        => $post_new->post_type,
		];

		// Post name is the slug.
		if ( $post_new->post_name !== $post_prev->post_name ) {
			$context['attachment_name_new']  = $post_new->post_name;
			$context['attachment_name_prev'] = $post_prev->post_name;
		}

		if ( $post_new->post_title !== $post_prev->post_title ) {
			$context['attachment_title_new']  = $post_new->post_title;
			$context['attachment_title_prev'] = $post_prev->post_title;
		}

		if ( $post_new->post_excerpt !== $post_prev->post_excerpt ) {
			$context['attachment_excerpt_new']  = $post_new->post_excerpt;
			$context['attachment_excerpt_prev'] = $post_prev->post_excerpt;
		}

		if ( $post_new->post_content !== $post_prev->post_content ) {
			$context['attachment_content_new']  = $post_new->post_content;
			$context['attachment_content_prev'] = $post_prev->post_content;
		}

		if ( $post_new->post_author !== $post_prev->post_author ) {
			$context['attachment_author_new']  = $post_new->post_author;
			$context['attachment_author_prev'] = $post_prev->post_author;
		}

		// Alt text is not included in hook. Is set in post meta field '_wp_attachment_image_alt'.
		// For REST API, alt text meta is updated AFTER this hook fires (by the REST controller),
		// so the diff is appended later by on_rest_after_insert_attachment_append_alt_text().
		if ( ! Helpers::is_rest_request() && isset( $this->prev_attachment_values[ $attachment_id ]['alt_text'] ) ) {
			$context['attachment_alt_text_new']  = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
			$context['attachment_alt_text_prev'] = $this->prev_attachment_values[ $attachment_id ]['alt_text'];
		}

		$context['attachment_new']  = $post_new;
		$context['attachment_prev'] = $post_prev;

		$this->attachment_updated_logged[ $attachment_id ] = true;

		$this->info_message( 'attachment_updated', $context );
	}

	/**
	 * Called when an attachment is deleted.
	 *
	 * @param int $attachment_id Attachment ID.
	 */
	public function on_delete_attachment( $attachment_id ) {
		if ( $this->is_plugin_or_theme_install() ) {
			return;
		}

		$attachment_post = get_post( $attachment_id );
		$filename        = esc_html( wp_basename( $attachment_post->guid ) );
		$mime            = get_post_mime_type( $attachment_post );

		$this->info_message(
			'attachment_deleted',
			array(
				'post_type'           => get_post_type( $attachment_post ),
				'attachment_id'       => $attachment_id,
				'attachment_title'    => get_the_title( $attachment_post ),
				'attachment_filename' => $filename,
				'attachment_mime'     => $mime,
			)
		);
	}

	/**
	 * Modify RSS links so they go directly to the correct media in WP admin.
	 *
	 * @since 2.0.23
	 * @param string $link Link to the log item.
	 * @param object $row Log item.
	 */
	public function filter_rss_item_link( $link, $row ) {
		if ( $row->logger !== $this->get_slug() ) {
			return $link;
		}

		if ( isset( $row->context['attachment_id'] ) ) {
			$link = add_query_arg(
				array(
					'action' => 'edit',
					'post'   => $row->context['attachment_id'],
				),
				admin_url( 'post.php' )
			);
		}

		return $link;
	}
}
