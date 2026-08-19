<?php

namespace Simple_History\Loggers;

use Simple_History\Event_Details\Event_Details_Group;
use Simple_History\Event_Details\Event_Details_Group_Table_Formatter;
use Simple_History\Event_Details\Event_Details_Item;

/**
 * Logs changes made in the Site Editor (Full Site Editing):
 * templates, template parts, site-wide styles (global styles),
 * patterns, navigation menus, and fonts.
 */
class Site_Editor_Logger extends Logger {
	/** @var string Logger slug */
	public $slug = 'SiteEditorLogger';

	/**
	 * Post types used by the Site Editor that this logger handles.
	 *
	 * @var array<string>
	 */
	private const FSE_POST_TYPES = [
		'wp_template',
		'wp_template_part',
		'wp_global_styles',
		'wp_block',
		'wp_navigation',
		'wp_font_family',
		'wp_font_face',
	];

	/**
	 * IDs of font families being deleted during the current request,
	 * used to skip logging the cascading deletion of their font faces.
	 *
	 * @var array<int,bool>
	 */
	private $deleted_font_family_ids = [];

	/**
	 * How deeply nested the current REST dispatch is, if any.
	 *
	 * Saves made while serving a REST request are logged from
	 * rest_after_insert_* instead of save_post, because meta and terms
	 * are not stored yet when save_post runs. Tracked with a counter
	 * rather than the REST_REQUEST constant, which is only defined when
	 * WordPress serves an HTTP request and not when code dispatches a
	 * request internally with rest_do_request().
	 *
	 * @var int
	 */
	private $rest_dispatch_depth = 0;

	/**
	 * Whether a post is currently being restored from the trash.
	 *
	 * Restoring updates the post status, which fires save_post. That save
	 * is not an edit the user made, so it is skipped in favour of the
	 * restore message.
	 *
	 * @var bool
	 */
	private $is_untrashing = false;

	/**
	 * Term and meta data captured just before a post is deleted,
	 * because WordPress removes terms and meta before the delete_post
	 * action fires. Keyed by post ID.
	 *
	 * @var array<int,array<string,string>>
	 */
	private $pre_delete_data = [];

	/**
	 * Get array with information about this logger.
	 *
	 * @return array
	 */
	public function get_info() {
		return [
			'name'        => __( 'Site Editor Logger', 'simple-history' ),
			'description' => __( 'Logs changes made in the Site Editor: templates, template parts, site-wide styles, patterns, navigation menus, and fonts', 'simple-history' ),
			// Site Editor post types were previously logged by Post_Logger,
			// which uses edit_pages. Keep the same capability so users who
			// could read these events before can still read them.
			'capability'  => 'edit_pages',
			'messages'    => [
				'template_created'          => __( 'Created template "{post_title}"', 'simple-history' ),
				'template_updated'          => __( 'Updated template "{post_title}"', 'simple-history' ),
				'template_deleted'          => __( 'Deleted template "{post_title}"', 'simple-history' ),
				'template_reset'            => __( 'Reset template "{post_title}" to theme default', 'simple-history' ),

				'template_part_created'     => __( 'Created template part "{post_title}"', 'simple-history' ),
				'template_part_updated'     => __( 'Updated template part "{post_title}"', 'simple-history' ),
				'template_part_deleted'     => __( 'Deleted template part "{post_title}"', 'simple-history' ),
				'template_part_reset'       => __( 'Reset template part "{post_title}" to theme default', 'simple-history' ),

				'global_styles_updated'     => __( 'Updated site-wide styles (global styles)', 'simple-history' ),

				'synced_pattern_created'    => __( 'Created synced pattern "{post_title}"', 'simple-history' ),
				'synced_pattern_updated'    => __( 'Updated synced pattern "{post_title}"', 'simple-history' ),
				'synced_pattern_deleted'    => __( 'Deleted synced pattern "{post_title}"', 'simple-history' ),
				'synced_pattern_trashed'    => __( 'Moved synced pattern "{post_title}" to the trash', 'simple-history' ),
				'synced_pattern_restored'   => __( 'Restored synced pattern "{post_title}" from the trash', 'simple-history' ),

				'unsynced_pattern_created'  => __( 'Created pattern "{post_title}"', 'simple-history' ),
				'unsynced_pattern_updated'  => __( 'Updated pattern "{post_title}"', 'simple-history' ),
				'unsynced_pattern_deleted'  => __( 'Deleted pattern "{post_title}"', 'simple-history' ),
				'unsynced_pattern_trashed'  => __( 'Moved pattern "{post_title}" to the trash', 'simple-history' ),
				'unsynced_pattern_restored' => __( 'Restored pattern "{post_title}" from the trash', 'simple-history' ),

				'navigation_menu_created'   => __( 'Created navigation menu "{post_title}"', 'simple-history' ),
				'navigation_menu_updated'   => __( 'Updated navigation menu "{post_title}"', 'simple-history' ),
				'navigation_menu_deleted'   => __( 'Deleted navigation menu "{post_title}"', 'simple-history' ),
				'navigation_menu_trashed'   => __( 'Moved navigation menu "{post_title}" to the trash', 'simple-history' ),
				'navigation_menu_restored'  => __( 'Restored navigation menu "{post_title}" from the trash', 'simple-history' ),

				'font_family_created'       => __( 'Installed font family "{font_family_name}"', 'simple-history' ),
				'font_family_updated'       => __( 'Updated font family "{font_family_name}"', 'simple-history' ),
				'font_family_deleted'       => __( 'Deleted font family "{font_family_name}"', 'simple-history' ),

				'font_face_created'         => __( 'Added font face "{font_face_name}" to font family "{font_family_name}"', 'simple-history' ),
				'font_face_deleted'         => __( 'Removed font face "{font_face_name}" from font family "{font_family_name}"', 'simple-history' ),
			],
			'labels'      => [
				'search' => [
					'label'     => _x( 'Site Editor', 'Site Editor logger: search', 'simple-history' ),
					'label_all' => _x( 'All Site Editor activity', 'Site Editor logger: search', 'simple-history' ),
					'options'   => [
						_x( 'Templates changed', 'Site Editor logger: search', 'simple-history' ) => [
							'template_created',
							'template_updated',
							'template_deleted',
							'template_reset',
							'template_part_created',
							'template_part_updated',
							'template_part_deleted',
							'template_part_reset',
						],
						_x( 'Site-wide styles changed', 'Site Editor logger: search', 'simple-history' ) => [
							'global_styles_updated',
						],
						_x( 'Patterns changed', 'Site Editor logger: search', 'simple-history' ) => [
							'synced_pattern_created',
							'synced_pattern_updated',
							'synced_pattern_deleted',
							'synced_pattern_trashed',
							'unsynced_pattern_created',
							'unsynced_pattern_updated',
							'unsynced_pattern_deleted',
							'unsynced_pattern_trashed',
							'synced_pattern_restored',
							'unsynced_pattern_restored',
						],
						_x( 'Navigation menus changed', 'Site Editor logger: search', 'simple-history' ) => [
							'navigation_menu_created',
							'navigation_menu_updated',
							'navigation_menu_deleted',
							'navigation_menu_trashed',
							'navigation_menu_restored',
						],
						_x( 'Fonts changed', 'Site Editor logger: search', 'simple-history' ) => [
							'font_family_created',
							'font_family_updated',
							'font_family_deleted',
							'font_face_created',
							'font_face_deleted',
						],
					],
				],
			],
		];
	}

	/**
	 * Called when logger is loaded.
	 */
	public function loaded() {
		// Tell Post_Logger to not log Site Editor post types, this logger handles them.
		add_filter( 'simple_history/post_logger/skip_posttypes', [ $this, 'on_post_logger_skip_posttypes' ] );

		// The Site Editor saves everything through the REST API, but the same
		// post types can also be changed by WP-CLI, an importer or plugin code,
		// so listen for both and let the handlers agree on which one logs.
		foreach ( self::FSE_POST_TYPES as $post_type ) {
			add_action( "rest_after_insert_{$post_type}", [ $this, 'on_rest_after_insert' ], 10, 3 );
			add_action( "save_post_{$post_type}", [ $this, 'on_save_post' ], 10, 3 );
		}

		add_filter( 'rest_pre_dispatch', [ $this, 'on_rest_pre_dispatch' ] );
		add_filter( 'rest_post_dispatch', [ $this, 'on_rest_post_dispatch' ] );

		add_action( 'trashed_post', [ $this, 'on_trashed_post' ] );
		add_action( 'untrash_post', [ $this, 'on_untrash_post' ] );
		add_action( 'untrashed_post', [ $this, 'on_untrashed_post' ] );
		add_action( 'before_delete_post', [ $this, 'on_before_delete_post' ], 10, 2 );
		add_action( 'delete_post', [ $this, 'on_delete_post' ], 10, 2 );
	}

	/**
	 * Add Site Editor post types to the list of post types
	 * that the regular post logger should not log.
	 *
	 * @param array<string> $skip_posttypes Post types to skip.
	 * @return array<string>
	 */
	public function on_post_logger_skip_posttypes( $skip_posttypes ) {
		return array_merge( $skip_posttypes, self::FSE_POST_TYPES );
	}

	/**
	 * Remember that a REST request started being dispatched.
	 *
	 * @param mixed $result Dispatch result, passed through untouched.
	 * @return mixed
	 */
	public function on_rest_pre_dispatch( $result ) {
		++$this->rest_dispatch_depth;

		return $result;
	}

	/**
	 * Remember that a REST request finished being dispatched.
	 *
	 * @param mixed $response Dispatch response, passed through untouched.
	 * @return mixed
	 */
	public function on_rest_post_dispatch( $response ) {
		if ( $this->rest_dispatch_depth > 0 ) {
			--$this->rest_dispatch_depth;
		}

		return $response;
	}

	/**
	 * Fired before a post is restored from the trash.
	 *
	 * @param int $post_id Post ID.
	 */
	public function on_untrash_post( $post_id ) {
		$this->is_untrashing = true;
	}

	/**
	 * Fired after a Site Editor post is created or updated via the REST API.
	 *
	 * This is the path the Site Editor itself uses. It runs after meta and
	 * terms have been saved, so the area of a template part and the sync
	 * status of a pattern are readable here but not yet during save_post.
	 *
	 * @param \WP_Post         $post     Inserted or updated post object.
	 * @param \WP_REST_Request $request  Request object.
	 * @param bool             $creating True when creating a post, false when updating.
	 */
	public function on_rest_after_insert( $post, $request, $creating ) {
		$this->log_post_change( $post, $creating );
	}

	/**
	 * Fired when a Site Editor post is saved outside the REST API,
	 * for example by WP-CLI, an importer or plugin code.
	 *
	 * REST saves are skipped here because on_rest_after_insert() logs those
	 * with fuller context. Without this hook such changes would go unlogged
	 * entirely, since Post_Logger no longer handles these post types.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @param bool     $update  True when updating an existing post.
	 */
	public function on_save_post( $post_id, $post, $update ) {
		if ( $this->rest_dispatch_depth > 0 ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		// Trashing and restoring both change the post status, which fires
		// save_post. Those are logged as trash and restore instead.
		if ( $post->post_status === 'trash' || $this->is_untrashing ) {
			return;
		}

		$this->log_post_change( $post, ! $update );
	}

	/**
	 * Log the creation or update of a Site Editor post.
	 *
	 * @param \WP_Post $post     Inserted or updated post object.
	 * @param bool     $creating True when the post was just created.
	 */
	private function log_post_change( $post, $creating ) {
		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		if ( in_array( $post->post_status, [ 'auto-draft', 'inherit' ], true ) ) {
			return;
		}

		switch ( $post->post_type ) {
			case 'wp_template':
				$this->info_message(
					$creating ? 'template_created' : 'template_updated',
					$this->get_template_context( $post )
				);
				break;

			case 'wp_template_part':
				$this->info_message(
					$creating ? 'template_part_created' : 'template_part_updated',
					$this->get_template_context( $post )
				);
				break;

			case 'wp_global_styles':
				// Global styles posts are auto-created by WordPress,
				// so the only user action is an update.
				$this->info_message(
					'global_styles_updated',
					[
						'post_id' => $post->ID,
						'theme'   => $this->get_theme_name_for_post( $post ),
					]
				);
				break;

			case 'wp_block':
				$sync_status = $this->get_pattern_sync_status( $post );

				$message_prefix = $sync_status === 'synced' ? 'synced_pattern' : 'unsynced_pattern';

				$this->info_message(
					$message_prefix . ( $creating ? '_created' : '_updated' ),
					[
						'post_id'             => $post->ID,
						'post_title'          => $post->post_title,
						'pattern_sync_status' => $sync_status,
					]
				);
				break;

			case 'wp_navigation':
				$this->info_message(
					$creating ? 'navigation_menu_created' : 'navigation_menu_updated',
					[
						'post_id'    => $post->ID,
						'post_title' => $post->post_title,
					]
				);
				break;

			case 'wp_font_family':
				$this->info_message(
					$creating ? 'font_family_created' : 'font_family_updated',
					[
						'post_id'          => $post->ID,
						'font_family_name' => $this->get_font_family_name( $post ),
					]
				);
				break;

			case 'wp_font_face':
				if ( $creating ) {
					$this->info_message( 'font_face_created', $this->get_font_face_context( $post ) );
				}
				break;
		}
	}

	/**
	 * Fired after a post is moved to the trash.
	 *
	 * Patterns and navigation menus support trash,
	 * the other Site Editor post types are deleted directly.
	 *
	 * @param int $post_id Post ID.
	 */
	public function on_trashed_post( $post_id ) {
		$post = get_post( $post_id );

		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		if ( $post->post_type === 'wp_block' ) {
			$sync_status = $this->get_pattern_sync_status( $post );

			$message_prefix = $sync_status === 'synced' ? 'synced_pattern' : 'unsynced_pattern';

			$this->info_message(
				$message_prefix . '_trashed',
				[
					'post_id'             => $post->ID,
					'post_title'          => $post->post_title,
					'pattern_sync_status' => $sync_status,
				]
			);
		} elseif ( $post->post_type === 'wp_navigation' ) {
			$this->info_message(
				'navigation_menu_trashed',
				[
					'post_id'    => $post->ID,
					'post_title' => $post->post_title,
				]
			);
		}
	}

	/**
	 * Fired after a post is restored from the trash.
	 *
	 * Mirrors on_trashed_post() so a restore is visible in the log,
	 * the way the trashing of the same post is.
	 *
	 * @param int $post_id Post ID.
	 */
	public function on_untrashed_post( $post_id ) {
		$this->is_untrashing = false;

		$post = get_post( $post_id );

		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		if ( $post->post_type === 'wp_block' ) {
			$sync_status = $this->get_pattern_sync_status( $post );

			$message_prefix = $sync_status === 'synced' ? 'synced_pattern' : 'unsynced_pattern';

			$this->info_message(
				$message_prefix . '_restored',
				[
					'post_id'             => $post->ID,
					'post_title'          => $post->post_title,
					'pattern_sync_status' => $sync_status,
				]
			);
		} elseif ( $post->post_type === 'wp_navigation' ) {
			$this->info_message(
				'navigation_menu_restored',
				[
					'post_id'    => $post->ID,
					'post_title' => $post->post_title,
				]
			);
		}
	}

	/**
	 * Capture term and meta data for a Site Editor post before deletion starts,
	 * because WordPress removes terms and meta before the delete_post action fires.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 */
	public function on_before_delete_post( $post_id, $post ) {
		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		if ( in_array( $post->post_type, [ 'wp_template', 'wp_template_part' ], true ) ) {
			$this->pre_delete_data[ $post_id ] = [
				'theme_slug'         => $this->get_post_term_slug( $post, 'wp_theme' ),
				'template_part_area' => $post->post_type === 'wp_template_part' ? $this->get_post_term_slug( $post, 'wp_template_part_area' ) : '',
			];
		} elseif ( $post->post_type === 'wp_block' ) {
			$sync_status = get_post_meta( $post_id, 'wp_pattern_sync_status', true );

			$this->pre_delete_data[ $post_id ] = [
				'pattern_sync_status' => $sync_status === 'unsynced' ? 'unsynced' : 'synced',
			];
		}
	}

	/**
	 * Fired just before a post is deleted from the database.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 */
	public function on_delete_post( $post_id, $post ) {
		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		if ( ! in_array( $post->post_type, self::FSE_POST_TYPES, true ) ) {
			return;
		}

		if ( in_array( $post->post_status, [ 'auto-draft', 'inherit' ], true ) ) {
			return;
		}

		switch ( $post->post_type ) {
			case 'wp_template':
			case 'wp_template_part':
				$this->log_template_deletion( $post );
				break;

			case 'wp_global_styles':
				// Global styles posts are only deleted by WordPress itself,
				// for example during theme cleanup, so skip logging.
				break;

			case 'wp_block':
				$sync_status = $this->get_pattern_sync_status( $post );

				$message_prefix = $sync_status === 'synced' ? 'synced_pattern' : 'unsynced_pattern';

				$this->info_message(
					$message_prefix . '_deleted',
					[
						'post_id'             => $post->ID,
						'post_title'          => $post->post_title,
						'pattern_sync_status' => $sync_status,
					]
				);
				break;

			case 'wp_navigation':
				$this->info_message(
					'navigation_menu_deleted',
					[
						'post_id'    => $post->ID,
						'post_title' => $post->post_title,
					]
				);
				break;

			case 'wp_font_family':
				// Remember the ID so the cascading deletion of the
				// font faces that belong to this family is not logged.
				$this->deleted_font_family_ids[ $post->ID ] = true;

				$this->info_message(
					'font_family_deleted',
					[
						'post_id'          => $post->ID,
						'font_family_name' => $this->get_font_family_name( $post ),
					]
				);
				break;

			case 'wp_font_face':
				// When a whole font family is deleted WordPress also deletes
				// its font faces. Skip those to avoid logging one event per face.
				if ( ! isset( $this->deleted_font_family_ids[ $post->post_parent ] ) ) {
					$this->info_message( 'font_face_deleted', $this->get_font_face_context( $post ) );
				}
				break;
		}
	}

	/**
	 * Log deletion of a template or template part.
	 *
	 * Deleting a customized template that the theme provides a default for
	 * means the template is reset to the theme default, not removed from the site.
	 *
	 * @param \WP_Post $post Template or template part post object.
	 */
	private function log_template_deletion( $post ) {
		$is_template_part = $post->post_type === 'wp_template_part';

		if ( $this->is_theme_provided_template( $post ) ) {
			$message_key = $is_template_part ? 'template_part_reset' : 'template_reset';
		} else {
			$message_key = $is_template_part ? 'template_part_deleted' : 'template_deleted';
		}

		$this->info_message( $message_key, $this->get_template_context( $post ) );
	}

	/**
	 * Check if the active theme provides a default file for a template or template part,
	 * meaning a deletion of the post is a "reset to theme default".
	 *
	 * @param \WP_Post $post Template or template part post object.
	 * @return bool
	 */
	private function is_theme_provided_template( $post ) {
		if ( ! function_exists( '_get_block_template_file' ) ) {
			return false;
		}

		// Templates are connected to a theme via the wp_theme taxonomy.
		// Only the active theme's files can provide a default.
		$theme_slug = $this->get_theme_slug_for_post( $post );

		if ( $theme_slug !== '' && get_stylesheet() !== $theme_slug ) {
			return false;
		}

		return _get_block_template_file( $post->post_type, $post->post_name ) !== null;
	}

	/**
	 * Get log context for a template or template part.
	 *
	 * @param \WP_Post $post Template or template part post object.
	 * @return array<string,mixed>
	 */
	private function get_template_context( $post ) {
		$context = [
			'post_id'       => $post->ID,
			'post_title'    => $post->post_title,
			'template_slug' => $post->post_name,
			'theme'         => $this->get_theme_name_for_post( $post ),
		];

		if ( $post->post_type === 'wp_template_part' ) {
			$area = $this->pre_delete_data[ $post->ID ]['template_part_area'] ?? $this->get_post_term_slug( $post, 'wp_template_part_area' );

			if ( $area !== '' ) {
				$context['template_part_area'] = $area;
			}
		}

		return $context;
	}

	/**
	 * Get log context for a font face, including the parent font family name
	 * and a readable variant name like "400 italic".
	 *
	 * @param \WP_Post $post Font face post object.
	 * @return array<string,mixed>
	 */
	private function get_font_face_context( $post ) {
		$settings = json_decode( $post->post_content, true );

		$weight = is_array( $settings ) && isset( $settings['fontWeight'] ) ? (string) $settings['fontWeight'] : '';
		$style  = is_array( $settings ) && isset( $settings['fontStyle'] ) ? (string) $settings['fontStyle'] : '';

		$font_face_name = trim( "{$weight} {$style}" );

		if ( $font_face_name === '' ) {
			$font_face_name = $post->post_title;
		}

		$parent_font_family = get_post( $post->post_parent );

		$context = [
			'post_id'          => $post->ID,
			'font_face_name'   => $font_face_name,
			'font_family_name' => $parent_font_family instanceof \WP_Post ? $this->get_font_family_name( $parent_font_family ) : '',
		];

		if ( $weight !== '' ) {
			$context['font_face_weight'] = $weight;
		}

		if ( $style !== '' ) {
			$context['font_face_style'] = $style;
		}

		return $context;
	}

	/**
	 * Get the readable name of a font family.
	 *
	 * The post title holds the name, with the settings JSON
	 * in post content as fallback.
	 *
	 * @param \WP_Post $post Font family post object.
	 * @return string
	 */
	private function get_font_family_name( $post ) {
		if ( $post->post_title !== '' ) {
			return $post->post_title;
		}

		$settings = json_decode( $post->post_content, true );

		if ( is_array( $settings ) && ! empty( $settings['name'] ) ) {
			return (string) $settings['name'];
		}

		return $post->post_name;
	}

	/**
	 * Get the readable theme name for a Site Editor post,
	 * based on its wp_theme taxonomy term.
	 *
	 * @param \WP_Post $post Post object.
	 * @return string
	 */
	private function get_theme_name_for_post( $post ) {
		$theme_slug = $this->get_theme_slug_for_post( $post );

		if ( $theme_slug === '' ) {
			$theme_slug = get_stylesheet();
		}

		$theme = wp_get_theme( $theme_slug );

		if ( $theme->exists() ) {
			return $theme->get( 'Name' );
		}

		return $theme_slug;
	}

	/**
	 * Get the theme slug (stylesheet) a Site Editor post belongs to,
	 * preferring data captured before deletion.
	 *
	 * @param \WP_Post $post Post object.
	 * @return string Theme slug or empty string.
	 */
	private function get_theme_slug_for_post( $post ) {
		if ( isset( $this->pre_delete_data[ $post->ID ]['theme_slug'] ) ) {
			return $this->pre_delete_data[ $post->ID ]['theme_slug'];
		}

		return $this->get_post_term_slug( $post, 'wp_theme' );
	}

	/**
	 * Get the slug of the first term of a taxonomy for a post.
	 *
	 * @param \WP_Post $post     Post object.
	 * @param string   $taxonomy Taxonomy name.
	 * @return string Term slug or empty string.
	 */
	private function get_post_term_slug( $post, $taxonomy ) {
		$terms = get_the_terms( $post, $taxonomy );

		if ( ! is_array( $terms ) || empty( $terms ) ) {
			return '';
		}

		return $terms[0]->slug;
	}

	/**
	 * Get the sync status for a pattern (wp_block post).
	 *
	 * Patterns are synced unless the wp_pattern_sync_status meta is "unsynced".
	 *
	 * @param \WP_Post $post Pattern post object.
	 * @return string "synced" or "unsynced".
	 */
	private function get_pattern_sync_status( $post ) {
		if ( isset( $this->pre_delete_data[ $post->ID ]['pattern_sync_status'] ) ) {
			return $this->pre_delete_data[ $post->ID ]['pattern_sync_status'];
		}

		$sync_status = get_post_meta( $post->ID, 'wp_pattern_sync_status', true );

		return $sync_status === 'unsynced' ? 'unsynced' : 'synced';
	}

	/**
	 * Get output for detailed log section.
	 *
	 * @param object $row Log row.
	 * @return Event_Details_Group
	 */
	public function get_log_row_details_output( $row ) {
		$group = new Event_Details_Group();
		$group->set_formatter( new Event_Details_Group_Table_Formatter() );

		$group->add_items(
			[
				new Event_Details_Item( 'template_slug', __( 'Slug', 'simple-history' ) ),
				new Event_Details_Item( 'template_part_area', __( 'Area', 'simple-history' ) ),
				new Event_Details_Item( 'theme', __( 'Theme', 'simple-history' ) ),
				new Event_Details_Item( 'pattern_sync_status', __( 'Sync status', 'simple-history' ) ),
				new Event_Details_Item( 'font_face_weight', __( 'Font weight', 'simple-history' ) ),
				new Event_Details_Item( 'font_face_style', __( 'Font style', 'simple-history' ) ),
			]
		);

		return $group;
	}
}
