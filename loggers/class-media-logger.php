<?php

namespace Simple_History\Loggers;

use Simple_History\Helpers;
use Simple_History\Event_Details\Event_Details_Simple_Container;
use Simple_History\Event_Details\Event_Details_Container_Interface;
use Simple_History\Event_Details\Event_Details_Group;
use Simple_History\Event_Details\Event_Details_Item;

/**
 * Logs media uploads
 */
class Media_Logger extends Logger {
	/** @var string Logger slug */
	public $slug = 'SimpleMediaLogger';

	/** @var array Array with prev attachment values, before save. */
	protected array $prev_attachment_values = [];

	/**
	 * Get array with information about this logger
	 *
	 * @return array
	 */
	public function get_info() {

		$arr_info = array(
			'name'        => __( 'Media/Attachments Logger', 'simple-history' ),
			'description' => __( 'Logs media uploads and edits', 'simple-history' ),
			'capability'  => 'edit_pages',
			'messages'    => array(
				'attachment_created' => __( 'Created {post_type} "{attachment_title}"', 'simple-history' ),
				'attachment_updated' => __( 'Edited attachment "{attachment_title}"', 'simple-history' ),
				'attachment_deleted' => __( 'Deleted {post_type} "{attachment_title}" ("{attachment_filename}")', 'simple-history' ),
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
						),
						_x( 'Deleted media', 'Media logger: search', 'simple-history' ) => array(
							'attachment_deleted',
						),
					),
				),
			),
		);

		return $arr_info;
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
		add_action( 'load-post.php', [ $this, 'on_load_post_store_attachment_alt_text' ] );
	}

	/**
	 * Store the previous alt text of an attachment when editing it.
	 * Fired when loading admin page post.php.
	 */
	public function on_load_post_store_attachment_alt_text() {
		if ( 'POST' !== $_SERVER['REQUEST_METHOD'] ?? '' ) {
			return;
		}

		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.NonceVerification.Missing
		$post_id   = $_POST['post_ID'] ?? null;
		$post_type = $_POST['post_type'] ?? null;
		$action    = $_POST['action'] ?? null;
		// phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.NonceVerification.Missing

		if ( ! $post_id || 'attachment' !== $post_type || 'editpost' !== $action ) {
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
			if ( 'attachment_updated' === $message_key ) {
				$message = __( 'Edited attachment <a href="{edit_link}">"{attachment_title}"</a>', 'simple-history' );
			} elseif ( 'attachment_created' === $message_key ) {

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

		// Attachment is created/uploaded = show details with image thumbnail.
		$attachment_id = $context['attachment_id'];
		$filetype      = wp_check_filetype( $context['attachment_filename'] );
		$file_url      = wp_get_attachment_url( $attachment_id );
		$edit_link     = get_edit_post_link( $attachment_id );
		$attached_file = get_attached_file( $attachment_id );
		$message       = '';
		$full_src      = false;

		// Is true if attachment is an image. But for example PDFs can have thumbnail images, but they are not considered to be image.
		$is_image = wp_attachment_is_image( $attachment_id );

		$is_video = strpos( $filetype['type'], 'video/' ) !== false;
		$is_audio = strpos( $filetype['type'], 'audio/' ) !== false;

		$full_image_width  = null;
		$full_image_height = null;

		if ( $is_image ) {
			$thumb_src = wp_get_attachment_image_src( $attachment_id, 'medium' );
			$full_src  = wp_get_attachment_image_src( $attachment_id, 'full' );

			$full_image_width  = $full_src[1];
			$full_image_height = $full_src[2];

			// is_image is also true for mime types that WP can't create thumbs for
			// so we need to check that wp got an resized version.
			if ( $full_image_width && $full_image_height ) {
				$context['full_image_width']  = $full_image_width;
				$context['full_image_height'] = $full_image_height;

				// Only output thumb if file exists
				// For example images deleted on file system but not in WP cause broken images (rare case, but has happened to me.).
				if ( file_exists( $attached_file ) && $thumb_src ) {
					$context['attachment_thumb'] = sprintf( '<div class="SimpleHistoryLogitemThumbnail"><img src="%1$s" alt=""></div>', $thumb_src[0] );
				}
			}
		} elseif ( $is_audio ) {
			$content                     = sprintf( '[audio src="%1$s"]', $file_url );
			$context['attachment_thumb'] = do_shortcode( $content );
		} elseif ( $is_video ) {
			$content                     = sprintf( '[video src="%1$s"]', $file_url );
			$context['attachment_thumb'] = do_shortcode( $content );
		} elseif ( $attachment_is_available ) {
			// Use WordPress icon for other media types.
			$context['attachment_thumb'] = sprintf(
				'%1$s',
				wp_get_attachment_image( $attachment_id, array( 350, 500 ), true ) // Placeholder 1.
			);
		}

		// Only set attachment_size_format if we have a valid filesize.
		if ( ! empty( $row->context['attachment_filesize'] ) ) {
			$context['attachment_size_format'] = size_format( $row->context['attachment_filesize'] );
		}

		$context['attachment_filetype_extension'] = strtoupper( $filetype['ext'] );

		if ( ! empty( $context['attachment_thumb'] ) ) {
			if ( $is_image ) {
				$message .= "<a class='SimpleHistoryLogitemThumbnailLink' href='" . $edit_link . "'>";
			}

			$message .= __( '{attachment_thumb}', 'simple-history' );

			if ( $is_image ) {
				$message .= '</a>';
			}
		}

		$message .= "<p class='SimpleHistoryLogitem--logger-SimpleMediaLogger--attachment-meta'>";

		// Only show file size if we have one.
		if ( ! empty( $context['attachment_size_format'] ) ) {
			$message .= "<span class='SimpleHistoryLogitem__inlineDivided'>" . __( '{attachment_size_format}', 'simple-history' ) . '</span> ';
		}

		$message .= "<span class='SimpleHistoryLogitem__inlineDivided'>" . __( '{attachment_filetype_extension}', 'simple-history' ) . '</span>';

		if ( $full_image_width && $full_image_height ) {
			$message .= " <span class='SimpleHistoryLogitem__inlineDivided'>" . __( '{full_image_width} × {full_image_height}', 'simple-history' ) . '</span>';
		}

		$message .= '</p>';

		return helpers::interpolate( $message, $context, $row );
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
	 * Get output for detailed log section
	 *
	 * @param object $row Row.
	 * @return string|Event_Details_Container_Interface|Event_Details_Group
	 */
	public function get_log_row_details_output( $row ) {
		$message_key = $row->context['_message_key'];

		if ( 'attachment_created' === $message_key ) {
			return $this->get_details_output_for_created_attachment( $row );
		} elseif ( 'attachment_updated' === $message_key ) {
			return $this->get_details_output_for_updated_attachment( $row );
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
		if ( isset( $this->prev_attachment_values[ $attachment_id ]['alt_text'] ) ) {
			$context['attachment_alt_text_new']  = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
			$context['attachment_alt_text_prev'] = $this->prev_attachment_values[ $attachment_id ]['alt_text'];
		}

		$context['attachment_new']  = $post_new;
		$context['attachment_prev'] = $post_prev;

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
