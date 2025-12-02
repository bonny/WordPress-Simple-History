<?php

namespace Simple_History\Loggers;

use Simple_History\Helpers;

/**
 * Logs changes to WordPress options
 */
class Options_Logger extends Logger {
	/** @var string Logger slug */
	public $slug = 'SimpleOptionsLogger';

	/**
	 * Get array with information about this logger
	 *
	 * @return array
	 */
	public function get_info() {
		return [
			'name'        => __( 'Options Logger', 'simple-history' ),
			'description' => __( 'Logs updates to WordPress settings', 'simple-history' ),
			'capability'  => 'manage_options',
			'messages'    => array(
				'option_updated' => __( 'Updated setting "{option}" on the "{option_page}" settings page', 'simple-history' ),
			),
			'labels'      => array(
				'search' => array(
					'label'   => _x( 'Options', 'Options logger: search', 'simple-history' ),
					'options' => array(
						_x( 'Changed options', 'Options logger: search', 'simple-history' ) => array(
							'option_updated',
						),
					),
				),
			),
		];
	}

	/**
	 * Called when logger is loaded.
	 */
	public function loaded() {
		// When WP posts the options page it's done to options.php or options-permalink.php.
		add_action( 'load-options.php', array( $this, 'on_load_options_page' ) );
		add_action( 'load-options-permalink.php', array( $this, 'on_load_options_page' ) );
	}

	/**
	 * Called when the options pages are loaded.
	 */
	public function on_load_options_page() {
		add_action( 'updated_option', array( $this, 'on_updated_option' ), 10, 3 );
	}

	/**
	 * Get a list of all built in WordPress options.
	 *
	 * @return array
	 */
	protected function get_wordpress_built_in_options() {
		return [
			'general'    => [
				'translation'               => __( 'General', 'simple-history' ),
				'translation_settings_page' => __( 'General Settings Page', 'simple-history' ),
				'options'                   => [
					'siteurl'            => [ 'translation' => __( 'WordPress Address (URL)', 'simple-history' ) ],
					'home'               => [ 'translation' => __( 'Site Address (URL)', 'simple-history' ) ],
					'blogname'           => [ 'translation' => __( 'Site Title', 'simple-history' ) ],
					'blogdescription'    => [ 'translation' => __( 'Tagline', 'simple-history' ) ],
					'site_icon'          => [ 'translation' => __( 'Site Icon', 'simple-history' ) ],
					'admin_email'        => [ 'translation' => __( 'Administration Email Address', 'simple-history' ) ],
					'new_admin_email'    => [ 'translation' => __( 'New Email Address', 'simple-history' ) ],
					'users_can_register' => [
						'translation' => __( 'Anyone can register', 'simple-history' ),
						'type'        => 'onoff',
					],
					'default_role'       => [ 'translation' => __( 'New User Default Role', 'simple-history' ) ],
					'timezone_string'    => [ 'translation' => __( 'Timezone', 'simple-history' ) ],
					'date_format'        => [ 'translation' => __( 'Date Format', 'simple-history' ) ],
					'time_format'        => [ 'translation' => __( 'Time Format', 'simple-history' ) ],
					'start_of_week'      => [ 'translation' => __( 'Week Starts On', 'simple-history' ) ],
					'WPLANG'             => [ 'translation' => __( 'Site Language', 'simple-history' ) ],
				],
			],
			'writing'    => [
				'translation'               => __( 'Writing', 'simple-history' ),
				'translation_settings_page' => __( 'Writing Settings Page', 'simple-history' ),
				'options'                   => [
					'default_category'       => [ 'translation' => __( 'Default Post Category', 'simple-history' ) ],
					'default_post_format'    => [ 'translation' => __( 'Default Post Format', 'simple-history' ) ],
					'post_by_email'          => [ 'translation' => __( 'Post via Email settings (legacy)', 'simple-history' ) ],
					'mailserver_url'         => [ 'translation' => __( 'Mail Server', 'simple-history' ) ],
					'mailserver_login'       => [ 'translation' => __( 'Login Name', 'simple-history' ) ],
					'mailserver_pass'        => [ 'translation' => __( 'Password', 'simple-history' ) ],
					'mailserver_port'        => [ 'translation' => __( 'Default Mail Server Port', 'simple-history' ) ],
					'default_pingback_flag'  => [
						'translation' => __( 'Attempt to notify any blogs linked to from the article', 'simple-history' ),
						'type'        => 'onoff',
					],
					'default_ping_status'    => [ 'translation' => __( 'Allow link notifications from other blogs (pingbacks and trackbacks)', 'simple-history' ) ],
					'default_comment_status' => [ 'translation' => __( 'Allow people to submit comments on new posts', 'simple-history' ) ],
					'ping_sites'             => [ 'translation' => __( 'Update Services', 'simple-history' ) ],
				],
			],
			'reading'    => [
				'translation'               => __( 'Reading', 'simple-history' ),
				'translation_settings_page' => __( 'Reading Settings Page', 'simple-history' ),
				'options'                   => [
					'posts_per_page'  => [ 'translation' => __( 'Blog pages show at most', 'simple-history' ) ],
					'posts_per_rss'   => [ 'translation' => __( 'Syndication feeds show the most recent', 'simple-history' ) ],
					'rss_use_excerpt' => [ 'translation' => __( 'For each article in a feed, show', 'simple-history' ) ],
					'show_on_front'   => [ 'translation' => __( 'Front page displays', 'simple-history' ) ],
					'page_on_front'   => [ 'translation' => __( 'Front page', 'simple-history' ) ],
					'page_for_posts'  => [ 'translation' => __( 'Posts page', 'simple-history' ) ],
					'blog_public'     => [
						'translation' => __( 'Discourage search engines from indexing this site', 'simple-history' ),
						'type'        => 'reversed_onoff',
					],
				],
			],
			'discussion' => [
				'translation'               => __( 'Discussion', 'simple-history' ),
				'translation_settings_page' => __( 'Discussion Settings Page', 'simple-history' ),
				'options'                   => [
					'default_article_visibility'   => [ 'translation' => __( 'Default article visibility', 'simple-history' ) ],
					'default_comment_status'       => [ 'translation' => __( 'Allow people to submit comments on new posts', 'simple-history' ) ],
					'require_name_email'           => [
						'translation' => __( 'Comment author must fill out name and email', 'simple-history' ),
						'type'        => 'onoff',
					],
					'comment_registration'         => [
						'translation' => __( 'Users must be registered and logged in to comment', 'simple-history' ),
						'type'        => 'onoff',
					],
					'close_comments_for_old_posts' => [
						'translation' => __( 'Automatically close comments on posts older than', 'simple-history' ),
						'type'        => 'onoff',
					],
					'close_comments_days_old'      => [ 'translation' => __( 'Days before comments are closed', 'simple-history' ) ],
					'show_comments_cookies_opt_in' => [
						'translation' => __( 'Show comments cookies opt-in checkbox', 'simple-history' ),
						'type'        => 'onoff',
					],
					'thread_comments'              => [
						'translation' => __( 'Enable threaded (nested) comments', 'simple-history' ),
						'type'        => 'onoff',
					],
					'thread_comments_depth'        => [ 'translation' => __( 'Max depth for threaded comments', 'simple-history' ) ],
					'page_comments'                => [
						'translation' => __( 'Break comments into pages', 'simple-history' ),
						'type'        => 'onoff',
					],
					'comments_per_page'            => [ 'translation' => __( 'Top level comments per page', 'simple-history' ) ],
					'default_comments_page'        => [ 'translation' => __( 'Comments should be displayed with the', 'simple-history' ) ],
					'comment_order'                => [ 'translation' => __( 'Comments order', 'simple-history' ) ],
					'comment_previously_approved'  => [ 'translation' => __( 'Comment author must have a previously approved comment', 'simple-history' ) ],
					'comment_max_links'            => [ 'translation' => __( 'Hold a comment in the queue if it contains', 'simple-history' ) ],
					'moderation_keys'              => [ 'translation' => __( 'Comment Moderation', 'simple-history' ) ],
					'blacklist_keys'               => [ 'translation' => __( 'Disallowed Comment Keys', 'simple-history' ) ],
					'disallowed_keys'              => [ 'translation' => __( 'Disallowed Comment Keys', 'simple-history' ) ],
					'comment_moderation'           => [ 'translation' => __( 'Comment must be manually approved', 'simple-history' ) ],
					'comment_whitelist'            => [ 'translation' => __( 'Comment author must have a previously approved comment', 'simple-history' ) ],
					'comments_notify'              => [
						'translation' => __( 'Email me whenever anyone posts a comment', 'simple-history' ),
						'type'        => 'onoff',
					],
					'comment_notify'               => [ 'translation' => __( 'Email me whenever anyone posts a comment', 'simple-history' ) ],
					'moderation_notify'            => [
						'translation' => __( 'Email me whenever a comment is held for moderation', 'simple-history' ),
						'type'        => 'onoff',
					],
					'show_avatars'                 => [
						'translation' => __( 'Show Avatars', 'simple-history' ),
						'type'        => 'onoff',
					],
					'avatar_rating'                => [ 'translation' => __( 'Maximum Rating', 'simple-history' ) ],
					'avatar_default'               => [ 'translation' => __( 'Default Avatar', 'simple-history' ) ],
				],
			],
			'media'      => [
				'translation'               => __( 'Media', 'simple-history' ),
				'translation_settings_page' => __( 'Media Settings Page', 'simple-history' ),
				'options'                   => [
					'thumbnail_size_w'              => [ 'translation' => __( 'Thumbnail size width', 'simple-history' ) ],
					'thumbnail_size_h'              => [ 'translation' => __( 'Thumbnail size height', 'simple-history' ) ],
					'thumbnail_crop'                => [ 'translation' => __( 'Crop thumbnail to exact dimensions', 'simple-history' ) ],
					'medium_size_w'                 => [ 'translation' => __( 'Medium size width', 'simple-history' ) ],
					'medium_size_h'                 => [ 'translation' => __( 'Medium size height', 'simple-history' ) ],
					'large_size_w'                  => [ 'translation' => __( 'Large size width', 'simple-history' ) ],
					'large_size_h'                  => [ 'translation' => __( 'Large size height', 'simple-history' ) ],
					'uploads_use_yearmonth_folders' => [
						'translation' => __( 'Organize my uploads into month- and year-based folders', 'simple-history' ),
						'type'        => 'onoff',
					],
				],
			],
			'permalinks' => [
				'translation'               => __( 'Permalinks', 'simple-history' ),
				'translation_settings_page' => __( 'Permalink Settings Page', 'simple-history' ),
				'options'                   => [
					'permalink_structure' => [ 'translation' => __( 'Custom Structure', 'simple-history' ) ],
					'category_base'       => [ 'translation' => __( 'Category base', 'simple-history' ) ],
					'tag_base'            => [ 'translation' => __( 'Tag base', 'simple-history' ) ],
				],
			],
		];
	}

	/**
	 * Check if the option page is a built in WordPress options page.
	 *
	 * @param string $option_page Option page name.
	 * @return bool
	 */
	protected function is_wordpress_built_in_options_page( $option_page ) {
		$valid_option_pages = [
			'general',
			'discussion',
			'media',
			'reading',
			'writing',
		];

		return in_array( $option_page, $valid_option_pages, true );
	}

	/**
	 * Check if the form was submitted from the permalink settings page.
	 *
	 * @return bool
	 */
	protected function is_form_submitted_from_permalink_page() {
		return strpos( wp_get_referer(), 'options-permalink.php' ) !== false;
	}

	/**
	 * Check if the option name is a built in WordPress option.
	 *
	 * @param string $option_name Option name.
	 */
	protected function is_built_in_wordpress_options_name( $option_name ) {
		return in_array( $option_name, $this->get_wordpress_options_keys(), true );
	}

	/**
	 * When an option is updated from the options page.
	 *
	 * @param string $option Option name.
	 * @param mixed  $old_value Old value.
	 * @param mixed  $new_value New value.
	 */
	public function on_updated_option( $option, $old_value, $new_value ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$option_page = sanitize_text_field( wp_unslash( $_REQUEST['option_page'] ?? '' ) ); // general | discussion | ...

		if ( ! $this->is_wordpress_built_in_options_page( $option_page ) && ! $this->is_form_submitted_from_permalink_page() ) {
			return;
		}

		if ( ! $this->is_built_in_wordpress_options_name( $option ) ) {
			return;
		}

		// If new value is null then store as empty string.
		if ( is_null( $new_value ) ) {
			$new_value = '';
		}

		// Add "option" page manually for permalink screen.
		if ( $this->is_form_submitted_from_permalink_page() ) {
			$option_page = 'permalink';
		}

		$context = [
			'option'      => $option,
			'old_value'   => $old_value,
			'new_value'   => $new_value,
			'option_page' => $option_page,
		];

		// Store a bit more about some options
		// Like "page_on_front" we also store post title
		// Check for a method for current option in this class and calls it automagically.
		$methodname = 'add_context_for_option_' . strtolower( $option );
		if ( method_exists( $this, $methodname ) ) {
			$context = $this->$methodname( $context, $old_value, $new_value, $option, $option_page );
		}

		$this->info_message( 'option_updated', $context );
	}

	/**
	 * Modify plain output to include link to option page and make option in cleartext.
	 *
	 * @param object $row Row data.
	 */
	public function get_log_row_plain_text_output( $row ) {
		$context     = $row->context;
		$message_key = $context['_message_key'] ?? null;
		$option      = $context['option'] ?? null;
		$option_page = $context['option_page'] ?? null;
		$message     = $row->message;

		// Update message to include link to option page.
		if ( $message_key === 'option_updated' && $option_page && $option ) {

			// Show option translated name.
			$option_info        = $this->get_option_info( $option );
			$option_translation = $option_info['translation'] ?? $option;

			$context['option_translated'] = $option_translation;
			$context['option_page_link']  = admin_url( "options-{$option_page}.php" );

			// Show option page translated name.
			$options_page_info                 = $this->get_option_page_info( $option_page );
			$options_page_translation          = $options_page_info['translation_settings_page'] ?? $option_page;
			$context['option_page_translated'] = $options_page_translation;

			$message = sprintf(
				__( 'Updated setting "{option_translated}" on the <a href="{option_page_link}">{option_page_translated}</a>', 'simple-history' ),
				$context['option'],
				$option_page
			);
		}

		return Helpers::interpolate( $message, $context, $row );
	}

	/**
	 * Get detailed output
	 *
	 * @param object $row Log row object.
	 */
	public function get_log_row_details_output( $row ) {
		$context     = $row->context;
		$message_key = $context['_message_key'];
		$output      = '';

		// Bail if not option_updated message.
		if ( 'option_updated' !== $message_key ) {
			return $output;
		}

		$option      = $context['option'] ?? null;
		$option_page = $context['option_page'] ?? null;
		$new_value   = $context['new_value'] ?? null;
		$old_value   = $context['old_value'] ?? null;

		$tmpl_row = '
			<tr>
				<td>%1$s</td>
				<td>%2$s</td>
			</tr>
		';

		$output .= "<table class='SimpleHistoryLogitem__keyValueTable'>";

		// Output old and new values.
		if ( $context['new_value'] || $context['old_value'] ) {
			$option_custom_output = '';
			$methodname           = 'get_details_output_for_option_' . strtolower( $option );

			if ( method_exists( $this, $methodname ) ) {
				$option_custom_output = $this->$methodname( $context, $old_value, $new_value, $option, $option_page, $tmpl_row );
			} else {
				$option_custom_output = $this->get_output_for_option_with_type_option( $option, $new_value, $old_value, $option_custom_output, $tmpl_row );
			}

			if ( empty( $option_custom_output ) ) {
				// All other options or fallback if custom output did not find all it's stuff.
				$trimmed_new_value = $this->excerptify( $new_value );
				$trimmed_old_value = $this->excerptify( $old_value );

				$output .= sprintf(
					$tmpl_row,
					__( 'New value', 'simple-history' ),
					esc_html( $trimmed_new_value )
				);

				$output .= sprintf(
					$tmpl_row,
					__( 'Old value', 'simple-history' ),
					esc_html( $trimmed_old_value )
				);
			} else {
				$output .= $option_custom_output;
			}
		}

		$output .= '</table>';

		return $output;
	}

	/**
	 * Create a possible excerpt of a string, with ... appended.
	 *
	 * @param string $string_value String to create excerpt from.
	 * @param int    $length Length of excerpt.
	 * @return string Excerpt with ... added if the string was long.
	 */
	protected function excerptify( $string_value, $length = 250 ) {
		$more    = __( '&hellip;', 'simple-history' );
		$trimmed = substr( $string_value, 0, $length );

		if ( strlen( $string_value ) > $length ) {
			$trimmed .= $more;
		}

		return $trimmed;
	}

	/**
	 * Page on front = "Front page displays" -> Your latest posts / A static page
	 * value 0 = Your latest post
	 * value int n = A static page
	 *
	 * @param array  $context context.
	 * @param mixed  $old_value old value.
	 * @param mixed  $new_value new value.
	 * @param string $option option name.
	 * @param string $option_page option page name.
	 * @return array context
	 */
	protected function add_context_for_option_page_on_front( $context, $old_value, $new_value, $option, $option_page ) {
		if ( ! empty( $old_value ) && is_numeric( $old_value ) ) {
			$old_post = get_post( $old_value );

			if ( $old_post instanceof \WP_Post ) {
				$context['old_post_title'] = $old_post->post_title;
			}
		}

		if ( ! empty( $new_value ) && is_numeric( $new_value ) ) {
			$new_post = get_post( $new_value );

			if ( $new_post instanceof \WP_Post ) {
				$context['new_post_title'] = $new_post->post_title;
			}
		}

		return $context;
	}

	/**
	 * Add context for option page_on_front for posts page.
	 *
	 * @param array $context context.
	 * @param mixed $old_value old value.
	 * @param mixed $new_value new value.
	 * @param mixed $option option name.
	 * @param mixed $option_page option page name.
	 * @return array Updated context.
	 */
	protected function add_context_for_option_page_for_posts( $context, $old_value, $new_value, $option, $option_page ) {
		// Get same info as for page_on_front.
		return call_user_func_array( array( $this, 'add_context_for_option_page_on_front' ), func_get_args() );
	}

	/**
	 * Get detailed output for page_on_front for posts page.
	 *
	 * @param array  $context context.
	 * @param mixed  $old_value old value.
	 * @param mixed  $new_value new value.
	 * @param string $option option name.
	 * @param string $option_page option page name.
	 * @return string output
	 */
	protected function get_details_output_for_option_page_for_posts( $context, $old_value, $new_value, $option, $option_page ) {
		return call_user_func_array( array( $this, 'get_details_output_for_option_page_on_front' ), func_get_args() );
	}

	/**
	 * Add detailed output for page_on_front
	 *
	 * @param array  $context context.
	 * @param mixed  $old_value old value.
	 * @param mixed  $new_value new value.
	 * @param string $option option name.
	 * @param string $option_page option page name.
	 * @param string $tmpl_row template row.
	 * @return string output
	 */
	protected function get_details_output_for_option_page_on_front( $context, $old_value, $new_value, $option, $option_page, $tmpl_row ) {
		$output = '';

		if ( $new_value && ! empty( $context['new_post_title'] ) ) {
			if ( get_post_status( $new_value ) ) {
				$post_title_with_link = sprintf( '<a href="%1$s">%2$s</a>', get_edit_post_link( $new_value ), esc_html( $context['new_post_title'] ) );
			} else {
				$post_title_with_link = esc_html( $context['new_post_title'] );
			}

			$output .= sprintf(
				$tmpl_row,
				__( 'New value', 'simple-history' ),
				sprintf(
					/* translators: %s post title with link. */
					__( 'Page %s', 'simple-history' ),
					$post_title_with_link
				)
			);
		}
		if ( (int) $new_value === 0 ) {
			$output .= sprintf(
				$tmpl_row,
				__( 'New value', 'simple-history' ),
				__( 'Your latest posts', 'simple-history' )
			);
		}

		if ( $old_value && ! empty( $context['old_post_title'] ) ) {
			if ( get_post_status( $old_value ) ) {
				$post_title_with_link = sprintf( '<a href="%1$s">%2$s</a>', get_edit_post_link( $old_value ), esc_html( $context['old_post_title'] ) );
			} else {
				$post_title_with_link = esc_html( $context['old_post_title'] );
			}

			$output .= sprintf(
				$tmpl_row,
				__( 'Old value', 'simple-history' ),
				sprintf(
					/* translators: %s post title with link. */
					__( 'Page %s', 'simple-history' ),
					$post_title_with_link
				)
			);
		}

		if ( (int) $old_value === 0 ) {
			$output .= sprintf(
				$tmpl_row,
				__( 'Old value', 'simple-history' ),
				__( 'Your latest posts', 'simple-history' )
			);
		}

		return $output;
	}

	/**
	 * "default_category" = Writing Settings » Default Post Category
	 *
	 * @param array  $context context.
	 * @param mixed  $old_value old value.
	 * @param mixed  $new_value new value.
	 * @param string $option option name.
	 * @param string $option_page option page name.
	 */
	protected function add_context_for_option_default_category( $context, $old_value, $new_value, $option, $option_page ) {
		if ( ! empty( $old_value ) && is_numeric( $old_value ) ) {
			$old_category_name = get_the_category_by_ID( $old_value );

			if ( ! is_wp_error( $old_category_name ) ) {
				$context['old_category_name'] = $old_category_name;
			}
		}

		if ( ! empty( $new_value ) && is_numeric( $new_value ) ) {
			$new_category_name = get_the_category_by_ID( $new_value );

			if ( ! is_wp_error( $new_category_name ) ) {
				$context['new_category_name'] = $new_category_name;
			}
		}

		return $context;
	}

	/**
	 * Add context for option default_category for default_email_category.
	 *
	 * @param array $context context.
	 * @param mixed $old_value old value.
	 * @param mixed $new_value new value.
	 * @param mixed $option option name.
	 * @param mixed $option_page option page name.
	 * @return array Updated context.
	 */
	protected function add_context_for_option_default_email_category( $context, $old_value, $new_value, $option, $option_page ) {
		return call_user_func_array( array( $this, 'add_context_for_option_default_category' ), func_get_args() );
	}

	/**
	 * Modify context for WPLANG option.
	 * If any value is empty then we set it to "en_US" because that is the default value.
	 *
	 * @param array  $context context.
	 * @param mixed  $old_value old value.
	 * @param mixed  $new_value new value.
	 * @param string $option option name.
	 * @param string $option_page option page name.
	 * @return array Updated context.
	 */
	protected function add_context_for_option_wplang( $context, $old_value, $new_value, $option, $option_page ) {
		if ( empty( $old_value ) ) {
			$context['old_value'] = 'en_US';
		}

		if ( empty( $new_value ) ) {
			$context['new_value'] = 'en_US';
		}

		return $context;
	}

	/**
	 * Modify option mailserver_pass to remove the new and old value from the context,
	 * because we don't want to log the password.
	 *
	 * @param array  $context context.
	 * @param mixed  $old_value old value.
	 * @param mixed  $new_value new value.
	 * @param string $option option name.
	 * @param string $option_page option page name.
	 * @return array Updated context.
	 */
	protected function add_context_for_option_mailserver_pass( $context, $old_value, $new_value, $option, $option_page ) {
		$context['old_value'] = '';
		$context['new_value'] = '';

		return $context;
	}

	/**
	 * Add detailed output for default_category
	 *
	 * @param array  $context context.
	 * @param mixed  $old_value old value.
	 * @param mixed  $new_value new value.
	 * @param string $option option name.
	 * @param string $option_page option page name.
	 * @param string $tmpl_row template row.
	 * @return string output
	 */
	protected function get_details_output_for_option_default_category( $context, $old_value, $new_value, $option, $option_page, $tmpl_row ) {
		$old_category_name = $context['old_category_name'] ?? null;
		$new_category_name = $context['new_category_name'] ?? null;
		$output            = '';

		if ( $old_category_name ) {
			$output .= sprintf(
				$tmpl_row,
				__( 'Old value', 'simple-history' ),
				esc_html( $old_category_name )
			);
		}

		if ( $new_category_name ) {
			$output .= sprintf(
				$tmpl_row,
				__( 'New value', 'simple-history' ),
				esc_html( $new_category_name )
			);
		}

		return $output;
	}

	/**
	 * Get detailed output for default_category for default_email_category.
	 *
	 * @param array  $context context.
	 * @param mixed  $old_value old value.
	 * @param mixed  $new_value new value.
	 * @param string $option option name.
	 * @param string $option_page option page name.
	 * @param string $tmpl_row template row.
	 * @return string output
	 */
	protected function get_details_output_for_option_default_email_category( $context, $old_value, $new_value, $option, $option_page, $tmpl_row ) {
		return call_user_func_array( array( $this, 'get_details_output_for_option_default_category' ), func_get_args() );
	}

	/**
	 * Get detailed output for start_of_week option.
	 *
	 * @param array  $context context.
	 * @param mixed  $old_value old value.
	 * @param mixed  $new_value new value.
	 * @param string $option option name.
	 * @param string $option_page option page name.
	 * @param string $tmpl_row template row.
	 */
	protected function get_details_output_for_option_start_of_week( $context, $old_value, $new_value, $option, $option_page, $tmpl_row ) {
		/** @var \WP_Locale Logger slug */
		global $wp_locale;

		if ( ! ( $wp_locale instanceof \WP_Locale ) ) {
			return '';
		}

		$output = '';

		$prev_weekday_name = $wp_locale->get_weekday( $old_value );
		$new_weekday_name  = $wp_locale->get_weekday( $new_value );

		$output .= sprintf(
			$tmpl_row,
			__( 'New value', 'simple-history' ),
			esc_html( $new_weekday_name )
		);

		$output .= sprintf(
			$tmpl_row,
			__( 'Old value', 'simple-history' ),
			esc_html( $prev_weekday_name )
		);

		return $output;
	}

	/**
	 * Get detailed output for start_of_week option.
	 *
	 * @param array  $context context.
	 * @param mixed  $old_value old value.
	 * @param mixed  $new_value new value.
	 * @param string $option option name.
	 * @param string $option_page option page name.
	 * @param string $tmpl_row template row.
	 */
	protected function get_details_output_for_option_rss_use_excerpt( $context, $old_value, $new_value, $option, $option_page, $tmpl_row ) {
		$output = '';

		// 0 full text, 1 excerpt.
		// phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual -- Value may be string '0' or int 0 from database.
		if ( $old_value == 0 ) {
			$old_value = __( 'Full text', 'simple-history' );
			$new_value = __( 'Excerpt', 'simple-history' );
		} else {
			$old_value = __( 'Excerpt', 'simple-history' );
			$new_value = __( 'Full text', 'simple-history' );
		}

		$output .= sprintf(
			$tmpl_row,
			__( 'New value', 'simple-history' ),
			esc_html( $new_value )
		);

		$output .= sprintf(
			$tmpl_row,
			__( 'Old value', 'simple-history' ),
			esc_html( $old_value )
		);

		return $output;
	}

	/**
	 * Get all keys for built in WordPress options.
	 *
	 * @return array
	 */
	protected function get_wordpress_options_keys() {
		$keys = [];

		foreach ( $this->get_wordpress_built_in_options() as $option_page => $options_page ) {
			foreach ( $options_page['options'] as $option_name => $option_info ) {
				$keys[] = $option_name;
			}
		}

		return $keys;
	}

	/**
	 * Get option information array.
	 *
	 * @param string $option_name Option name.
	 * @return array|false Option info if found or false if not found.
	 */
	protected function get_option_info( $option_name ) {
		$all_options = $this->get_wordpress_built_in_options();

		// Check for option in all option groups.
		foreach ( $all_options as $option_group_info ) {
			$option_group = $option_group_info['options'];
			if ( array_key_exists( $option_name, $option_group_info['options'] ) ) {
				return $option_group[ $option_name ];
			}
		}

		return false;
	}

	/**
	 * Get option page information array.
	 *
	 * @param string $option_page Option page name.
	 * @return array|false Option page info if found or false if not found.
	 */
	protected function get_option_page_info( $option_page ) {
		$all_options = $this->get_wordpress_built_in_options();

		// Check for option in all option groups.
		foreach ( $all_options as $option_group_name => $option_group_info ) {
			if ( $option_group_name === $option_page ) {
				return $option_group_info;
			}
		}

		return false;
	}

	/**
	 * Many options store values as 0 or 1, but we want to show them as for example "yes" or "no", or "Full text" or "Excerpt".
	 * 'type' => 'onoff', = Show 0 as "Off" and 1 as "On".
	 *
	 * @param string $option_name Option name.
	 * @param mixed  $new_value New value.
	 * @param mixed  $old_value Old value.
	 * @param string $option_custom_output Custom output.
	 * @param string $tmpl_row Template row.
	 * @return string Custom output.
	 */
	protected function get_output_for_option_with_type_option( $option_name, $new_value, $old_value, $option_custom_output, $tmpl_row ) {
		$option_info = $this->get_option_info( $option_name );
		$option_type = $option_info['type'] ?? '';

		if ( ! $option_type ) {
			return '';
		}

		$option_custom_output = '';

		switch ( $option_type ) {
			case 'onoff':
			case 'reversed_onoff':
				$true_value  = '';
				$false_value = '';

				if ( $option_type === 'onoff' ) {
					// 1 is on, 0 is off.
					$true_value  = __( 'On', 'simple-history' );
					$false_value = __( 'Off', 'simple-history' );
				} elseif ( $option_type === 'reversed_onoff' ) {
					// 1 is off, 0 is on.
					// Used on for example "blog_public".
					$true_value  = __( 'Off', 'simple-history' );
					$false_value = __( 'On', 'simple-history' );
				}

				$old_value = $old_value ? $true_value : $false_value;
				$new_value = $new_value ? $true_value : $false_value;

				$option_custom_output = sprintf(
					$tmpl_row,
					__( 'New value', 'simple-history' ),
					esc_html( $new_value )
				);

				$option_custom_output .= sprintf(
					$tmpl_row,
					__( 'Old value', 'simple-history' ),
					esc_html( $old_value )
				);

				break;
		}

		return $option_custom_output;
	}
}
