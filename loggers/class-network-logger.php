<?php

namespace Simple_History\Loggers;

/**
 * Logs network-level events on WordPress Multisite:
 * site lifecycle, super admin changes, network user management,
 * site status transitions, theme network enable/disable, and network settings.
 *
 * @since 5.6.0
 * @package SimpleHistory
 */
class Network_Logger extends Logger {
	/** @var string Logger slug, max 30 characters. */
	public $slug = 'SimpleNetworkLogger';

	/**
	 * Always write to network tables.
	 *
	 * @var bool
	 */
	protected $is_network_logger = true;

	/**
	 * Network settings that we log with human-readable labels.
	 *
	 * @var array<string,string>
	 */
	private $tracked_network_settings = [];

	/**
	 * Return logger info.
	 *
	 * @return array<string,mixed>
	 */
	public function get_info() {
		return [
			'name'        => _x( 'Network Logger', 'NetworkLogger', 'simple-history' ),
			'type'        => 'core',
			'description' => __( 'Logs network-level events on WordPress Multisite', 'simple-history' ),
			'capability'  => 'manage_network',
			'messages'    => [
				// Site lifecycle.
				'site_created'            => _x( 'Created site "{site_name}" ({site_url})', 'NetworkLogger', 'simple-history' ),
				'site_deleted'            => _x( 'Deleted site "{site_name}" ({site_url})', 'NetworkLogger', 'simple-history' ),

				// Site status transitions.
				'site_archived'           => _x( 'Archived site "{site_name}" ({site_url})', 'NetworkLogger', 'simple-history' ),
				'site_unarchived'         => _x( 'Unarchived site "{site_name}" ({site_url})', 'NetworkLogger', 'simple-history' ),
				'site_spam'               => _x( 'Marked site "{site_name}" ({site_url}) as spam', 'NetworkLogger', 'simple-history' ),
				'site_unspam'             => _x( 'Removed spam status from site "{site_name}" ({site_url})', 'NetworkLogger', 'simple-history' ),
				'site_mature'             => _x( 'Marked site "{site_name}" ({site_url}) as mature', 'NetworkLogger', 'simple-history' ),
				'site_unmature'           => _x( 'Removed mature status from site "{site_name}" ({site_url})', 'NetworkLogger', 'simple-history' ),
				'site_deleted_status'     => _x( 'Marked site "{site_name}" ({site_url}) as deleted', 'NetworkLogger', 'simple-history' ),
				'site_undeleted'          => _x( 'Restored site "{site_name}" ({site_url}) from deleted status', 'NetworkLogger', 'simple-history' ),
				'site_deactivated'        => _x( 'Deactivated site "{site_name}" ({site_url})', 'NetworkLogger', 'simple-history' ),
				'site_activated'          => _x( 'Activated site "{site_name}" ({site_url})', 'NetworkLogger', 'simple-history' ),
				'site_public_changed'     => _x( 'Changed public status of site "{site_name}" ({site_url}) to "{site_public_status}"', 'NetworkLogger', 'simple-history' ),

				// Super admin.
				'super_admin_granted'     => _x( 'Granted super admin to user "{user_login}" ({user_email})', 'NetworkLogger', 'simple-history' ),
				'super_admin_revoked'     => _x( 'Revoked super admin from user "{user_login}" ({user_email})', 'NetworkLogger', 'simple-history' ),

				// Network user management.
				'network_user_created'    => _x( 'Created network user "{user_login}" ({user_email})', 'NetworkLogger', 'simple-history' ),
				'network_user_deleted'    => _x( 'Deleted network user "{user_login}" ({user_email})', 'NetworkLogger', 'simple-history' ),
				'network_user_spam'       => _x( 'Marked user "{user_login}" as spam', 'NetworkLogger', 'simple-history' ),
				'network_user_unspam'     => _x( 'Removed spam status from user "{user_login}"', 'NetworkLogger', 'simple-history' ),

				// User-site management.
				'user_added_to_site'      => _x( 'Added user "{user_login}" to site "{site_name}" with role "{user_role}"', 'NetworkLogger', 'simple-history' ),
				'user_removed_from_site'  => _x( 'Removed user "{user_login}" from site "{site_name}"', 'NetworkLogger', 'simple-history' ),

				// Theme network management.
				'theme_network_enabled'   => _x( 'Network enabled theme "{theme_name}"', 'NetworkLogger', 'simple-history' ),
				'theme_network_disabled'  => _x( 'Network disabled theme "{theme_name}"', 'NetworkLogger', 'simple-history' ),

				// Network settings.
				'network_setting_updated' => _x( 'Updated network setting "{setting_label}"', 'NetworkLogger', 'simple-history' ),
			],
			'labels'      => [
				'search' => [
					'label'     => _x( 'Network', 'NetworkLogger: search', 'simple-history' ),
					'label_all' => _x( 'All network events', 'NetworkLogger: search', 'simple-history' ),
					'options'   => [
						_x( 'Sites', 'NetworkLogger: search', 'simple-history' ) => [
							'site_created',
							'site_deleted',
							'site_archived',
							'site_unarchived',
							'site_spam',
							'site_unspam',
							'site_mature',
							'site_unmature',
							'site_deleted_status',
							'site_undeleted',
							'site_deactivated',
							'site_activated',
							'site_public_changed',
						],
						_x( 'Super admin', 'NetworkLogger: search', 'simple-history' ) => [
							'super_admin_granted',
							'super_admin_revoked',
						],
						_x( 'Users', 'NetworkLogger: search', 'simple-history' ) => [
							'network_user_created',
							'network_user_deleted',
							'network_user_spam',
							'network_user_unspam',
							'user_added_to_site',
							'user_removed_from_site',
						],
						_x( 'Themes', 'NetworkLogger: search', 'simple-history' ) => [
							'theme_network_enabled',
							'theme_network_disabled',
						],
						_x( 'Settings', 'NetworkLogger: search', 'simple-history' ) => [
							'network_setting_updated',
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
		// Only load hooks on multisite.
		if ( ! is_multisite() ) {
			return;
		}

		$this->tracked_network_settings = $this->get_tracked_network_settings();

		// Site lifecycle.
		add_action( 'wp_initialize_site', [ $this, 'on_site_created' ], 10, 2 );
		add_action( 'wp_delete_site', [ $this, 'on_site_deleted' ] );

		// Site status transitions.
		add_action( 'archive_blog', [ $this, 'on_site_archived' ] );
		add_action( 'unarchive_blog', [ $this, 'on_site_unarchived' ] );
		add_action( 'make_spam_blog', [ $this, 'on_site_spam' ] );
		add_action( 'make_ham_blog', [ $this, 'on_site_unspam' ] );
		add_action( 'mature_blog', [ $this, 'on_site_mature' ] );
		add_action( 'unmature_blog', [ $this, 'on_site_unmature' ] );
		add_action( 'make_delete_blog', [ $this, 'on_site_deleted_status' ] );
		add_action( 'make_undelete_blog', [ $this, 'on_site_undeleted' ] );
		add_action( 'deactivate_blog', [ $this, 'on_site_deactivated' ] );
		add_action( 'activate_blog', [ $this, 'on_site_activated' ] );
		add_action( 'update_blog_public', [ $this, 'on_site_public_changed' ], 10, 2 );

		// Super admin.
		add_action( 'granted_super_admin', [ $this, 'on_super_admin_granted' ] );
		add_action( 'revoked_super_admin', [ $this, 'on_super_admin_revoked' ] );

		// Network user management.
		// `wpmu_new_user` fires when a network user is created (admin-created or
		// signup-activated). For networks that require email activation, the
		// signup itself fires `after_signup_user` and then `wpmu_new_user` fires
		// only when the user clicks the activation link. We log creation, not
		// signup, on purpose — pending signups are noise for super admins.
		add_action( 'wpmu_new_user', [ $this, 'on_network_user_created' ] );
		add_action( 'wpmu_delete_user', [ $this, 'on_network_user_deleted' ] );
		add_action( 'make_spam_user', [ $this, 'on_network_user_spam' ] );
		add_action( 'make_ham_user', [ $this, 'on_network_user_unspam' ] );

		// User-site management.
		add_action( 'add_user_to_blog', [ $this, 'on_user_added_to_site' ], 10, 3 );
		add_action( 'remove_user_from_blog', [ $this, 'on_user_removed_from_site' ], 10, 2 );

		// Theme network management.
		add_action( 'update_site_option_allowedthemes', [ $this, 'on_allowed_themes_updated' ], 10, 4 );

		// Network settings.
		add_action( 'update_site_option', [ $this, 'on_network_setting_updated' ], 10, 4 );
	}

	/**
	 * Get the tracked network settings with human-readable labels.
	 *
	 * @return array<string,string> Option name => label.
	 */
	private function get_tracked_network_settings() {
		return [
			'registration'          => __( 'Registration', 'simple-history' ),
			'add_new_users'         => __( 'Allow site admins to add new users', 'simple-history' ),
			'banned_email_domains'  => __( 'Banned email domains', 'simple-history' ),
			'limited_email_domains' => __( 'Limited email domains', 'simple-history' ),
			'illegal_names'         => __( 'Banned usernames', 'simple-history' ),
			'blog_upload_space'     => __( 'Site upload space', 'simple-history' ),
			'upload_filetypes'      => __( 'Upload file types', 'simple-history' ),
			'fileupload_maxk'       => __( 'Max upload file size', 'simple-history' ),
			'site_name'             => __( 'Network name', 'simple-history' ),
			'admin_email'           => __( 'Network admin email', 'simple-history' ),
			'first_post'            => __( 'First post content', 'simple-history' ),
			'first_page'            => __( 'First page content', 'simple-history' ),
			'first_comment'         => __( 'First comment content', 'simple-history' ),
			'first_comment_author'  => __( 'First comment author', 'simple-history' ),
			'WPLANG'                => __( 'Network language', 'simple-history' ),
		];
	}

	/**
	 * Get site context for a given blog ID.
	 *
	 * Memoized per-request — bulk operations on the same site shouldn't
	 * re-hit get_site() each time.
	 *
	 * @param int $blog_id Blog ID.
	 * @return array<string,string|int> Site context.
	 */
	private function get_site_context( $blog_id ) {
		static $cache = [];

		if ( isset( $cache[ $blog_id ] ) ) {
			return $cache[ $blog_id ];
		}

		$site = get_site( $blog_id );

		if ( ! $site ) {
			$cache[ $blog_id ] = [
				'site_id'   => $blog_id,
				'site_name' => '',
				'site_url'  => '',
			];
		} else {
			$cache[ $blog_id ] = [
				'site_id'   => $blog_id,
				'site_name' => ! empty( $site->blogname ) ? $site->blogname : $site->domain . $site->path,
				'site_url'  => $site->domain . $site->path,
			];
		}

		return $cache[ $blog_id ];
	}

	/**
	 * Get user context for a given user ID.
	 *
	 * Memoized per-request — bulk operations (e.g. adding the same user to
	 * many sites in one request) shouldn't re-hit get_userdata() each time.
	 *
	 * @param int $user_id User ID.
	 * @return array<string,string|int> User context.
	 */
	private function get_user_context( $user_id ) {
		static $cache = [];

		if ( isset( $cache[ $user_id ] ) ) {
			return $cache[ $user_id ];
		}

		$user = get_userdata( $user_id );

		if ( ! $user ) {
			$cache[ $user_id ] = [
				'affected_user_id' => $user_id,
				'user_login'       => '',
				'user_email'       => '',
			];
		} else {
			$cache[ $user_id ] = [
				'affected_user_id' => $user_id,
				'user_login'       => $user->user_login,
				'user_email'       => $user->user_email,
			];
		}

		return $cache[ $user_id ];
	}

	// -------------------------------------------------------------------------
	// Site lifecycle.
	// -------------------------------------------------------------------------

	/**
	 * Fired when a new site is fully initialized.
	 *
	 * @param \WP_Site $new_site New site object.
	 * @param array    $args     Arguments for the initialization.
	 */
	public function on_site_created( $new_site, $args ) {
		$context = $this->get_site_context( $new_site->blog_id );

		// Store extended context for premium.
		$context['_ext_admin_email'] = $args['options']['admin_email'] ?? '';
		$context['_ext_network_id']  = $new_site->network_id ?? '';
		$context['_ext_site_status'] = $new_site->public ? 'public' : 'private';
		$context['_ext_registered']  = $new_site->registered ?? '';

		$this->info_message( 'site_created', $context );
	}

	/**
	 * Fired when a site is deleted.
	 *
	 * @param \WP_Site $old_site Deleted site object.
	 */
	public function on_site_deleted( $old_site ) {
		$context = [
			'site_id'   => $old_site->blog_id,
			'site_name' => ! empty( $old_site->blogname ) ? $old_site->blogname : $old_site->domain . $old_site->path,
			'site_url'  => $old_site->domain . $old_site->path,
		];

		$this->warning_message( 'site_deleted', $context );
	}

	// -------------------------------------------------------------------------
	// Site status transitions.
	// -------------------------------------------------------------------------

	/**
	 * @param int $blog_id Blog ID.
	 */
	public function on_site_archived( $blog_id ) {
		$this->notice_message( 'site_archived', $this->get_site_context( $blog_id ) );
	}

	/**
	 * @param int $blog_id Blog ID.
	 */
	public function on_site_unarchived( $blog_id ) {
		$this->info_message( 'site_unarchived', $this->get_site_context( $blog_id ) );
	}

	/**
	 * @param int $blog_id Blog ID.
	 */
	public function on_site_spam( $blog_id ) {
		$this->warning_message( 'site_spam', $this->get_site_context( $blog_id ) );
	}

	/**
	 * @param int $blog_id Blog ID.
	 */
	public function on_site_unspam( $blog_id ) {
		$this->info_message( 'site_unspam', $this->get_site_context( $blog_id ) );
	}

	/**
	 * @param int $blog_id Blog ID.
	 */
	public function on_site_mature( $blog_id ) {
		$this->notice_message( 'site_mature', $this->get_site_context( $blog_id ) );
	}

	/**
	 * @param int $blog_id Blog ID.
	 */
	public function on_site_unmature( $blog_id ) {
		$this->info_message( 'site_unmature', $this->get_site_context( $blog_id ) );
	}

	/**
	 * @param int $blog_id Blog ID.
	 */
	public function on_site_deleted_status( $blog_id ) {
		$this->warning_message( 'site_deleted_status', $this->get_site_context( $blog_id ) );
	}

	/**
	 * @param int $blog_id Blog ID.
	 */
	public function on_site_undeleted( $blog_id ) {
		$this->info_message( 'site_undeleted', $this->get_site_context( $blog_id ) );
	}

	/**
	 * @param int $blog_id Blog ID.
	 */
	public function on_site_deactivated( $blog_id ) {
		$this->warning_message( 'site_deactivated', $this->get_site_context( $blog_id ) );
	}

	/**
	 * @param int $blog_id Blog ID.
	 */
	public function on_site_activated( $blog_id ) {
		$this->info_message( 'site_activated', $this->get_site_context( $blog_id ) );
	}

	/**
	 * @param int    $blog_id Blog ID.
	 * @param string $value   New public value ('0' or '1').
	 */
	public function on_site_public_changed( $blog_id, $value ) {
		$context                       = $this->get_site_context( $blog_id );
		$context['site_public_status'] = $value ? __( 'public', 'simple-history' ) : __( 'private', 'simple-history' );
		$this->notice_message( 'site_public_changed', $context );
	}

	// -------------------------------------------------------------------------
	// Super admin.
	// -------------------------------------------------------------------------

	/**
	 * @param int $user_id User ID.
	 */
	public function on_super_admin_granted( $user_id ) {
		$context = $this->get_user_context( $user_id );
		$this->warning_message( 'super_admin_granted', $context );
	}

	/**
	 * @param int $user_id User ID.
	 */
	public function on_super_admin_revoked( $user_id ) {
		$context = $this->get_user_context( $user_id );
		$this->warning_message( 'super_admin_revoked', $context );
	}

	// -------------------------------------------------------------------------
	// Network user management.
	// -------------------------------------------------------------------------

	/**
	 * @param int $user_id User ID.
	 */
	public function on_network_user_created( $user_id ) {
		$this->info_message( 'network_user_created', $this->get_user_context( $user_id ) );
	}

	/**
	 * @param int $user_id User ID.
	 */
	public function on_network_user_deleted( $user_id ) {
		$context = $this->get_user_context( $user_id );
		$this->warning_message( 'network_user_deleted', $context );
	}

	/**
	 * @param int $user_id User ID.
	 */
	public function on_network_user_spam( $user_id ) {
		$context = $this->get_user_context( $user_id );
		$this->warning_message( 'network_user_spam', $context );
	}

	/**
	 * @param int $user_id User ID.
	 */
	public function on_network_user_unspam( $user_id ) {
		$context = $this->get_user_context( $user_id );
		$this->info_message( 'network_user_unspam', $context );
	}

	// -------------------------------------------------------------------------
	// User-site management.
	// -------------------------------------------------------------------------

	/**
	 * @param int    $user_id User ID.
	 * @param string $role    Role assigned.
	 * @param int    $blog_id Blog ID.
	 */
	public function on_user_added_to_site( $user_id, $role, $blog_id ) {
		$context              = $this->get_user_context( $user_id );
		$site_context         = $this->get_site_context( $blog_id );
		$context['site_id']   = $site_context['site_id'];
		$context['site_name'] = $site_context['site_name'];
		$context['site_url']  = $site_context['site_url'];
		$context['user_role'] = $role;

		$this->info_message( 'user_added_to_site', $context );
	}

	/**
	 * @param int $user_id User ID.
	 * @param int $blog_id Blog ID.
	 */
	public function on_user_removed_from_site( $user_id, $blog_id ) {
		$context              = $this->get_user_context( $user_id );
		$site_context         = $this->get_site_context( $blog_id );
		$context['site_id']   = $site_context['site_id'];
		$context['site_name'] = $site_context['site_name'];
		$context['site_url']  = $site_context['site_url'];

		$this->warning_message( 'user_removed_from_site', $context );
	}

	// -------------------------------------------------------------------------
	// Theme network management.
	// -------------------------------------------------------------------------

	/**
	 * Handle allowed themes option update.
	 *
	 * @param string $option     Option name.
	 * @param mixed  $new_value  New value.
	 * @param mixed  $old_value  Old value.
	 * @param int    $network_id Network ID.
	 */
	public function on_allowed_themes_updated( $option, $new_value, $old_value, $network_id ) {
		if ( ! is_array( $new_value ) || ! is_array( $old_value ) ) {
			return;
		}

		// Find newly enabled themes.
		$enabled = array_diff_key( $new_value, $old_value );
		foreach ( $enabled as $theme_slug => $active ) {
			$theme = wp_get_theme( $theme_slug );
			$this->info_message(
				'theme_network_enabled',
				[
					'theme_name'    => $theme->exists() ? $theme->get( 'Name' ) : $theme_slug,
					'theme_slug'    => $theme_slug,
					'theme_version' => $theme->exists() ? $theme->get( 'Version' ) : '',
				]
			);
		}

		// Find newly disabled themes.
		$disabled = array_diff_key( $old_value, $new_value );
		foreach ( $disabled as $theme_slug => $active ) {
			$theme = wp_get_theme( $theme_slug );
			$this->notice_message(
				'theme_network_disabled',
				[
					'theme_name'    => $theme->exists() ? $theme->get( 'Name' ) : $theme_slug,
					'theme_slug'    => $theme_slug,
					'theme_version' => $theme->exists() ? $theme->get( 'Version' ) : '',
				]
			);
		}
	}

	// -------------------------------------------------------------------------
	// Network settings.
	// -------------------------------------------------------------------------

	/**
	 * Handle generic network option updates.
	 * Skips allowedthemes since it has its own handler.
	 *
	 * @param string $option     Option name.
	 * @param mixed  $new_value  New value.
	 * @param mixed  $old_value  Old value.
	 * @param int    $network_id Network ID.
	 */
	public function on_network_setting_updated( $option, $new_value, $old_value, $network_id ) {
		// Skip theme changes (handled separately).
		if ( $option === 'allowedthemes' ) {
			return;
		}

		// Only log tracked settings.
		if ( ! isset( $this->tracked_network_settings[ $option ] ) ) {
			return;
		}

		// Skip if values are the same.
		if ( $new_value === $old_value ) {
			return;
		}

		$label = $this->tracked_network_settings[ $option ];

		$context = [
			'setting_name'  => $option,
			'setting_label' => $label,
		];

		// Store old and new values for premium diff display.
		if ( is_scalar( $old_value ) ) {
			$context['setting_value_prev'] = (string) $old_value;
		} else {
			$context['setting_value_prev'] = wp_json_encode( $old_value );
		}

		if ( is_scalar( $new_value ) ) {
			$context['setting_value_new'] = (string) $new_value;
		} else {
			$context['setting_value_new'] = wp_json_encode( $new_value );
		}

		$this->notice_message( 'network_setting_updated', $context );
	}
}
