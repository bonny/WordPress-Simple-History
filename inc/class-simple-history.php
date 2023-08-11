<?php
namespace Simple_History;

use Simple_History\Loggers;
use Simple_History\Loggers\Logger;
use Simple_History\Loggers\Simple_Logger;
use Simple_History\Loggers\Plugin_Logger;
use Simple_History\Dropins;
use Simple_History\Dropins\Dropin;
use Simple_History\Helpers;

/**
 * Main class for Simple History.
 *
 * This is used to init the plugin.
 */
class Simple_History {
	public const NAME = 'Simple History';

	/**
	 * For singleton.
	 *
	 * @see get_instance()
	 */
	private static ?\Simple_History\Simple_History $instance = null;

	/** Array with external logger classnames to load. */
	private array $external_loggers = [];

	/** Array with external dropins to load. */
	private array $external_dropins = [];

	/** Array with all instantiated loggers. */
	private array $instantiated_loggers = [];

	/** Array with all instantiated dropins. */
	private array $instantiated_dropins = [];

	/** @var array<int,mixed>  Registered settings tabs. */
	private $arr_settings_tabs = [];

	public const DBTABLE = 'simple_history';
	public const DBTABLE_CONTEXTS = 'simple_history_contexts';

	/** @var string $dbtable Full database name with prefix, i.e. wp_simple_history */
	public static $dbtable;

	/** @var string $dbtable Full database name with prefix for contexts, i.e. wp_simple_history_contexts */
	public static $dbtable_contexts;

	/** @var string $plugin_basename */
	public $plugin_basename = SIMPLE_HISTORY_BASENAME;

	/** Slug for the settings menu */
	public const SETTINGS_MENU_SLUG = 'simple_history_settings_menu_slug';

	/** Slug for the settings menu */
	public const SETTINGS_GENERAL_OPTION_GROUP = 'simple_history_settings_group';

	/** ID for the general settings section */
	public const SETTINGS_SECTION_GENERAL_ID = 'simple_history_settings_section_general';

	public function __construct() {
		$this->init();
	}

	/**
	 * @since 2.5.2
	 */
	public function init() {
		/**
		 * Fires before Simple History does it's init stuff
		 *
		 * @since 2.0
		 *
		 * @param Simple_History $instance This class.
		 */
		do_action( 'simple_history/before_init', $this );

		$this->setup_variables();

		// Actions and filters, ordered by order specified in codex: http://codex.wordpress.org/Plugin_API/Action_Reference
		add_action( 'after_setup_theme', array( $this, 'load_plugin_textdomain' ) );

		new \Simple_History\Setup_Settings_Page( $this );
		new \Simple_History\Loggers_Loader( $this );
		new \Simple_History\Dropins_Loader( $this );
		new \Simple_History\Setup_Log_Filters( $this );
		new \Simple_History\Setup_Purge_DB_Cron( $this );

		// Run before loading of loggers and before menu items are added.
		add_action( 'after_setup_theme', array( $this, 'check_for_upgrade' ), 5 );

		$this->add_pause_and_resume_actions();

		if ( is_admin() ) {
			$this->add_admin_actions();
		}

		/**
		 * Fires after Simple History has done it's init stuff
		 *
		 * @since 2.0
		 *
		 * @param Simple_History $instance This class.
		 */
		do_action( 'simple_history/after_init', $this );
	}

	/**
	 * Actions to disable and enable logging.
	 * Useful for example when importing many things using PHP because then
	 * the log can be overwhelmed with data.
	 *
	 * @since 4.0.2
	 */
	protected function add_pause_and_resume_actions() {
		add_action(
			'simple_history/pause',
			function() {
				add_filter( 'simple_history/log/do_log', '__return_false' );
			}
		);

		add_action(
			'simple_history/resume',
			function () {
				remove_filter( 'simple_history/log/do_log', '__return_false' );
			}
		);
	}

	/**
	 * @since 2.5.2
	 */
	private function add_admin_actions() {
		add_action( 'admin_menu', array( $this, 'add_admin_pages' ) );

		add_action( 'admin_footer', array( $this, 'add_js_templates' ) );

		add_action( 'wp_dashboard_setup', array( $this, 'add_dashboard_widget' ) );

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );

		add_action( 'admin_head', array( $this, 'on_admin_head' ) );
		add_action( 'admin_footer', array( $this, 'on_admin_footer' ) );

		add_action( 'simple_history/history_page/before_gui', array( $this, 'output_quick_stats' ) );
		add_action( 'simple_history/dashboard/before_gui', array( $this, 'output_quick_stats' ) );

		add_action( 'wp_ajax_simple_history_api', array( $this, 'api' ) );

		add_filter( 'plugin_action_links_simple-history/index.php', array( $this, 'plugin_action_links' ), 10, 4 );

		add_action( 'admin_bar_menu', array( $this, 'add_admin_bar_network_menu_item' ), 40 );
		add_action( 'admin_bar_menu', array( $this, 'add_admin_bar_menu_item' ), 40 );
	}

	/**
	 * Adds a "View history" item/shortcut to the network admin, on blogs where Simple History is installed
	 *
	 * Useful because Simple History is something at least the author of this plugin often use on a site :)
	 *
	 * @since 2.7.1
	 */
	public function add_admin_bar_network_menu_item( $wp_admin_bar ) {
		/**
		 * Filter to control if admin bar shortcut should be added
		 *
		 * @since 2.7.1
		 *
		 * @param bool $add_item Add item
		 */
		$add_item = apply_filters( 'simple_history/add_admin_bar_network_menu_item', true );

		if ( ! $add_item ) {
			return;
		}

		// Don't show for logged out users or single site mode.
		if ( ! is_user_logged_in() || ! is_multisite() ) {
			return;
		}

		// Show only when the user has at least one site, or they're a super admin.
		if ( ( is_countable( $wp_admin_bar->user->blogs ) ? count( $wp_admin_bar->user->blogs ) : 0 ) < 1 && ! is_super_admin() ) {
			return;
		}

		// Setting to show as page must be true
		if ( ! $this->setting_show_as_page() ) {
			return;
		}

		// User must have capability to view the history page
		if ( ! current_user_can( $this->get_view_history_capability() ) ) {
			return;
		}

		foreach ( (array) $wp_admin_bar->user->blogs as $blog ) {
			switch_to_blog( $blog->userblog_id );

			if ( Helpers::is_plugin_active( SIMPLE_HISTORY_BASENAME ) ) {
				$menu_id = 'simple-history-blog-' . $blog->userblog_id;
				$parent_menu_id = 'blog-' . $blog->userblog_id;
				$url = admin_url(
					apply_filters( 'simple_history/admin_location', 'index' ) . '.php?page=simple_history_page'
				);

				// Each network site is added by WP core with id "blog-1", "blog-2" ... "blog-n"
				// https://codex.wordpress.org/Function_Reference/add_node
				$args = array(
					'id' => $menu_id,
					'parent' => $parent_menu_id,
					'title' => _x( 'View History', 'Admin bar network name', 'simple-history' ),
					'href' => $url,
					'meta' => array(
						'class' => 'ab-item--simplehistory',
					),
				);

				$wp_admin_bar->add_node( $args );
			} // End if().

			restore_current_blog();
		} // End foreach().
	}

	/**
	 * Adds a "View history" item/shortcut to the admin bar
	 *
	 * Useful because Simple History is something at least the author of this plugin often use on a site :)
	 *
	 * @since 2.7.1
	 */
	public function add_admin_bar_menu_item( $wp_admin_bar ) {
		/**
		 * Filter to control if admin bar shortcut should be added
		 *
		 * @since 2.7.1
		 *
		 * @param bool $add_item Add item
		 */
		$add_item = apply_filters( 'simple_history/add_admin_bar_menu_item', true );

		if ( ! $add_item ) {
			return;
		}

		// Don't show for logged out users
		if ( ! is_user_logged_in() ) {
			return;
		}

		// Setting to show as page must be true
		if ( ! $this->setting_show_as_page() ) {
			return;
		}

		// User must have capability to view the history page
		if ( ! current_user_can( $this->get_view_history_capability() ) ) {
			return;
		}

		$menu_id = 'simple-history-view-history';
		$parent_menu_id = 'site-name';
		$url = admin_url( apply_filters( 'simple_history/admin_location', 'index' ) . '.php?page=simple_history_page' );

		$args = array(
			'id' => $menu_id,
			'parent' => $parent_menu_id,
			'title' => _x( 'View History', 'Admin bar name', 'simple-history' ),
			'href' => $url,
			'meta' => array(
				'class' => 'ab-item--simplehistory',
			),
		);

		$wp_admin_bar->add_node( $args );
	}

	/**
	 * Get singleton instance.
	 *
	 * @return Simple_History instance
	 */
	public static function get_instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new Simple_History();
		}

		return self::$instance;
	}

	/**
	 * Function fired from action `admin_head`.
	 *
	 * @return void
	 */
	public function on_admin_head() {
		if ( $this->is_on_our_own_pages() ) {
			/**
			 * Similar to action WordPress action `admin_head`,
			 * but only fired from pages with Simple History.
			 *
			 * @param Simple_History $instance This class.
			 */
			do_action( 'simple_history/admin_head', $this );
		}
	}

	/**
	 * Function fired from action `admin_footer`.
	 *
	 * @return void
	 */
	public function on_admin_footer() {
		if ( $this->is_on_our_own_pages() ) {
			/**
			 * Similar to action WordPress action `admin_footer`,
			 * but only fired from pages with Simple History.
			 *
			 * @param Simple_History $instance This class.
			 */
			do_action( 'simple_history/admin_footer', $this );
		}
	}

	/**
	 * Output JS templated into footer
	 */
	public function add_js_templates( $hook ) {
		if ( $this->is_on_our_own_pages() ) {
			?>
			<script type="text/html" id="tmpl-simple-history-base">

				<div class="SimpleHistory__waitingForFirstLoad">
					<img src="<?php echo esc_url( admin_url( '/images/spinner.gif' ) ); ?>" alt="" width="20" height="20">
					<?php
					echo esc_html_x(
						'Loading history...',
						'Message visible while waiting for log to load from server the first time',
						'simple-history'
					);
					?>
				</div>

				<div class="SimpleHistoryLogitemsWrap">
					<div class="SimpleHistoryLogitems__beforeTopPagination"></div>
					<div class="SimpleHistoryLogitems__above"></div>
					<ul class="SimpleHistoryLogitems"></ul>
					<div class="SimpleHistoryLogitems__below"></div>
					<div class="SimpleHistoryLogitems__pagination"></div>
					<div class="SimpleHistoryLogitems__afterBottomPagination"></div>
				</div>

				<div class="SimpleHistoryLogitems__debug"></div>

			</script>

			<script type="text/html" id="tmpl-simple-history-logitems-pagination">

				<!-- this uses the (almost) the same html as WP does -->
				<div class="SimpleHistoryPaginationPages">
					<!--
					{{ data.page_rows_from }}–{{ data.page_rows_to }}
					<span class="SimpleHistoryPaginationDisplayNum"> of {{ data.total_row_count }} </span>
					-->
					<span class="SimpleHistoryPaginationLinks">
						<a
							data-direction="first"
							class="button SimpleHistoryPaginationLink SimpleHistoryPaginationLink--firstPage <# if ( data.api_args.paged <= 1 ) { #> disabled <# } #>"
							title="{{ data.strings.goToTheFirstPage }}"
							href="#">«</a>
						<a
							data-direction="prev"
							class="button SimpleHistoryPaginationLink SimpleHistoryPaginationLink--prevPage <# if ( data.api_args.paged <= 1 ) { #> disabled <# } #>"
							title="{{ data.strings.goToThePrevPage }}"
							href="#">‹</a>
						<span class="SimpleHistoryPaginationInput">
							<input class="SimpleHistoryPaginationCurrentPage" title="{{ data.strings.currentPage }}" type="text" name="paged" value="{{ data.api_args.paged }}" size="4">
							<?php _x( 'of', 'page n of n', 'simple-history' ); ?>
							<span class="total-pages">{{ data.pages_count }}</span>
						</span>
						<a
							data-direction="next"
							class="button SimpleHistoryPaginationLink SimpleHistoryPaginationLink--nextPage <# if ( data.api_args.paged >= data.pages_count ) { #> disabled <# } #>"
							title="{{ data.strings.goToTheNextPage }}"
							href="#">›</a>
						<a
							data-direction="last"
							class="button SimpleHistoryPaginationLink SimpleHistoryPaginationLink--lastPage <# if ( data.api_args.paged >= data.pages_count ) { #> disabled <# } #>"
							title="{{ data.strings.goToTheLastPage }}"
							href="#">»</a>
					</span>
				</div>

			</script>

			<script type="text/html" id="tmpl-simple-history-logitems-modal">

				<div class="SimpleHistory-modal">
					<div class="SimpleHistory-modal__background"></div>
					<div class="SimpleHistory-modal__content">
						<div class="SimpleHistory-modal__contentInner">
							<img class="SimpleHistory-modal__contentSpinner" src="<?php echo esc_url( admin_url( '/images/spinner.gif' ) ); ?>" alt="">
						</div>
						<div class="SimpleHistory-modal__contentClose">
							<button class="button">✕</button>
						</div>
					</div>
				</div>

			</script>

			<script type="text/html" id="tmpl-simple-history-occasions-too-many">
				<li
					class="SimpleHistoryLogitem
						   SimpleHistoryLogitem--occasion
						   SimpleHistoryLogitem--occasion-tooMany
						   ">
					<div class="SimpleHistoryLogitem__firstcol"></div>
					<div class="SimpleHistoryLogitem__secondcol">
						<div class="SimpleHistoryLogitem__text">
							<?php esc_html_e( 'Sorry, but there are too many similar events to show.', 'simple-history' ); ?>
						</div>
					</div>
				</li>
			</script>

			<?php
			// Call plugins so they can add their js.
			foreach ( $this->instantiated_loggers as $one_logger ) {
				$one_logger['instance']->admin_js();
			}
		}
	}

	// TODO: move api to own class, inject simple history instance.
	/**
	 * Base url is:
	 * /wp-admin/admin-ajax.php?action=simple_history_api
	 *
	 * Examples:
	 * http://playground-root.ep/wp-admin/admin-ajax.php?action=simple_history_api&posts_per_page=5&paged=1&format=html
	 */
	public function api() {
		// Fake slow answers
		// sleep(2);
		// sleep(rand(0,3));
		$args = $_GET;
		unset( $args['action'] );

		// Type = overview | ...
		$type = $_GET['type'] ?? null;

		if ( empty( $args ) || ! $type ) {
			wp_send_json_error(
				array(
					_x( 'Not enough args specified', 'API: not enough arguments passed', 'simple-history' ),
				)
			);
		}

		// User must have capability to view the history page
		if ( ! current_user_can( $this->get_view_history_capability() ) ) {
			wp_send_json_error(
				array(
					'error' => 'CAPABILITY_ERROR',
				)
			);
		}

		if ( isset( $args['id'] ) ) {
			$args['post__in'] = array( $args['id'] );
		}

		$data = [];

		switch ( $type ) {
			case 'overview':
			case 'occasions':
			case 'single':
				// API use SimpleHistoryLogQuery, so simply pass args on to that
				$logQuery = new Log_Query();

				$data = $logQuery->query( $args );

				$data['api_args'] = $args;

				// Output can be array or HTML
				if ( isset( $args['format'] ) && 'html' === $args['format'] ) {
					$data['log_rows_raw'] = [];

					foreach ( $data['log_rows'] as $key => $oneLogRow ) {
						$args = [];
						if ( $type == 'single' ) {
							$args['type'] = 'single';
						}

						$data['log_rows'][ $key ] = $this->get_log_row_html_output( $oneLogRow, $args );
					}

					$data['num_queries'] = get_num_queries();
					$data['cached_result'] = $data['cached_result'] ?? false;
				}

				break;

			default:
				$data[] = 'Nah.';
		} // End switch().

		wp_send_json_success( $data );
	}

	/**
	 * Load language files.
	 * Uses the method described at URL:
	 * http://geertdedeckere.be/article/loading-wordpress-language-files-the-right-way
	 *
	 * @since 2.0
	 */
	public function load_plugin_textdomain() {
		$domain = 'simple-history';

		// The "plugin_locale" filter is also used in load_plugin_textdomain()
		$locale = apply_filters( 'plugin_locale', get_locale(), $domain ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		load_textdomain( $domain, WP_LANG_DIR . '/simple-history/' . $domain . '-' . $locale . '.mo' );
		load_plugin_textdomain( $domain, false, dirname( $this->plugin_basename ) . '/languages/' );
	}

	/**
	 * Setup variables and things.
	 */
	public function setup_variables() {
		global $wpdb;
		$this::$dbtable = $wpdb->prefix . self::DBTABLE;
		$this::$dbtable_contexts = $wpdb->prefix . self::DBTABLE_CONTEXTS;

		/**
		 * Filter db table used for simple history events
		 *
		 * @since 2.0
		 *
		 * @param string $db_table
		 */
		$this::$dbtable = apply_filters( 'simple_history/db_table', $this::$dbtable );

		/**
		 * Filter table name for contexts.
		 *
		 * @since 2.0
		 *
		 * @param string $db_table_contexts
		 */
		$this::$dbtable_contexts = apply_filters(
			'simple_history/logger_db_table_contexts',
			$this::$dbtable_contexts
		);
	}

	/**
	 * Return capability required to view history = for who will the History page be added.
	 * Default capability is "edit_pages".
	 *
	 * @since 2.1.5
	 * @return string capability
	 */
	public function get_view_history_capability() {
		$view_history_capability = 'edit_pages';

		/**
		 * Deprecated, use filter `simple_history/view_history_capability` instead.
		 */
		$view_history_capability = apply_filters( 'simple_history_view_history_capability', $view_history_capability );

		/**
		 * Filter the capability required to view main simple history page, with the activity feed.
		 * Default capability is "edit_pages".
		 *
		 * @example Change the capability required to view the log to "manage options", so only allow admins are allowed to view the history log page.
		 *
		 * ```php
		 *  add_filter(
		 *      'simple_history/view_history_capability',
		 *      function ( $capability ) {
		 *          $capability = 'manage_options';
		 *          return $capability;
		 *      }
		 *  );
		 * ```
		 *
		 * @param string $view_history_capability
		 */
		$view_history_capability = apply_filters( 'simple_history/view_history_capability', $view_history_capability );

		return $view_history_capability;
	}

	/**
	 * Return capability required to view settings.
	 * Default capability is "manage_options",
	 * but can be modified using filter.
	 *
	 * @since 2.1.5
	 * @return string capability
	 */
	public function get_view_settings_capability() {
		$view_settings_capability = 'manage_options';

		/**
		 * Old filter name, use `simple_history/view_settings_capability` instead.
		 */
		$view_settings_capability = apply_filters( 'simple_history_view_settings_capability', $view_settings_capability );

		/**
		 * Filters the capability required to view the settings page.
		 *
		 * @example Change capability required to view the
		 *
		 * ```php
		 *  add_filter(
		 *      'simple_history/view_settings_capability',
		 *      function ( $capability ) {
		 *
		 *          $capability = 'manage_options';
		 *          return $capability;
		 *      }
		 *  );
		 * ```
		 *
		 * @param string $view_settings_capability
		 */
		$view_settings_capability = apply_filters( 'simple_history/view_settings_capability', $view_settings_capability );

		return $view_settings_capability;
	}

	/**
	 * Check if the current user can clear the log.
	 *
	 * @since 2.19
	 * @return bool
	 */
	public function user_can_clear_log() {
		/**
		 * Allows controlling who can manually clear the log.
		 * When this is true then the "Clear"-button in shown in the settings.
		 * When this is false then no button is shown.
		 *
		 * @example
		 * ```php
		 *  // Remove the "Clear log"-button, so a user with admin access can not clear the log
		 *  // and wipe their mischievous behavior from the log.
		 *  add_filter(
		 *      'simple_history/user_can_clear_log',
		 *      function ( $user_can_clear_log ) {
		 *          $user_can_clear_log = false;
		 *          return $user_can_clear_log;
		 *      }
		 *  );
		 * ```
		 *
		 * @param bool $allow Whether the current user is allowed to clear the log.
		*/
		return apply_filters( 'simple_history/user_can_clear_log', true );
	}

	/**
	 * Register an external logger so Simple History knows about it.
	 * Does not load the logger, so file with logger class must be loaded already.
	 *
	 * See example-logger.php for an example on how to use this.
	 *
	 * @since 2.1
	 */
	public function register_logger( $loggerClassName ) {
		$this->external_loggers[] = $loggerClassName;
	}

	/**
	 * Register an external dropin so Simple History knows about it.
	 * Does not load the dropin, so file with dropin class must be loaded already.
	 *
	 * See example-dropin.php for an example on how to use this.
	 *
	 * @since 2.1
	 */
	public function register_dropin( $dropinClassName ) {
		$this->external_dropins[] = $dropinClassName;
	}

	/**
	 * Get array with classnames of all external dropins.
	 *
	 * @return array
	 */
	public function get_external_loggers() {
		return $this->external_loggers;
	}

	/**
	 * Get array with classnames of all core (built-in) loggers.
	 *
	 * @return array
	 */
	public function get_core_loggers() {
		$loggers = array(
			Loggers\Available_Updates_Logger::class,
			Loggers\File_Edits_Logger::class,
			Loggers\Plugin_ACF_Logger::class,
			Loggers\Plugin_Beaver_Builder_Logger::class,
			Loggers\Plugin_Duplicate_Post_Logger::class,
			Loggers\Plugin_Limit_Login_Attempts_Logger::class,
			Loggers\Plugin_Redirection_Logger::class,
			Loggers\Plugin_Enable_Media_Replace_Logger::class,
			Loggers\Plugin_User_Switching_Logger::class,
			Loggers\Plugin_WP_Crontrol_Logger::class,
			Loggers\Plugin_Jetpack_Logger::class,
			Loggers\Privacy_Logger::class,
			Loggers\Translations_Logger::class,
			Loggers\Categories_Logger::class,
			Loggers\Comments_Logger::class,
			Loggers\Core_Updates_Logger::class,
			Loggers\Export_Logger::class,
			Loggers\Simple_Logger::class,
			Loggers\Media_Logger::class,
			Loggers\Menu_Logger::class,
			Loggers\Options_Logger::class,
			Loggers\Plugin_Logger::class,
			Loggers\Post_Logger::class,
			Loggers\Theme_Logger::class,
			Loggers\User_Logger::class,
			Loggers\Simple_History_Logger::class,
		);

		/**
		 * Filter the array with class names of core loggers.
		 *
		 * @since 4.0
		 *
		 * @param array $logger Array with class names.
		 */
		$loggers = apply_filters( 'simple_history/core_loggers', $loggers );

		return $loggers;
	}

	/**
	 * Get array with classnames of all core (built-in) dropins.
	 *
	 * @return array
	 */
	public function get_core_dropins() {
		$dropins = array(
			Dropins\Debug_Dropin::class,
			Dropins\Donate_Dropin::class,
			Dropins\Export_Dropin::class,
			Dropins\Filter_Dropin::class,
			Dropins\IP_Info_Dropin::class,
			Dropins\New_Rows_Notifier_Dropin::class,
			Dropins\Plugin_Patches_Dropin::class,
			Dropins\RSS_Dropin::class,
			Dropins\Settings_Debug_Tab_Dropin::class,
			Dropins\Sidebar_Stats_Dropin::class,
			Dropins\Sidebar_Dropin::class,
			Dropins\Sidebar_Settings_Dropin::class,
			Dropins\WP_CLI_Dropin::class,
			Dropins\Development_Dropin::class,
		);

		/**
		 * Filter the array with class names of core dropins.
		 *
		 * @since 4.0
		 *
		 * @param array $logger Array with class names.
		 */
		$dropins = apply_filters( 'simple_history/core_dropins', $dropins );

		return $dropins;
	}

	/**
	 * Get external dropins.
	 *
	 * @return array
	 */
	public function get_external_dropins() {
		return $this->external_dropins;
	}

	/**
	 * Gets the pager size,
	 * i.e. the number of items to show on each page in the history
	 *
	 * @return int
	 */
	public function get_pager_size() {
		$pager_size = get_option( 'simple_history_pager_size', 20 );

		/**
		 * Filter the pager size setting
		 *
		 * @since 2.0
		 *
		 * @param int $pager_size
		 */
		$pager_size = apply_filters( 'simple_history/pager_size', $pager_size );

		return $pager_size;
	}

	/**
	 * Gets the pager size for the dashboard widget,
	 * i.e. the number of items to show on each page in the history
	 *
	 * @since 2.12
	 * @return int
	 */
	public function get_pager_size_dashboard() {
		$pager_size = get_option( 'simple_history_pager_size_dashboard', 5 );

		/**
		 * Filter the pager size setting for the dashboard.
		 *
		 * @since 2.0
		 *
		 * @param int $pager_size
		 */
		$pager_size = apply_filters( 'simple_history/dashboard_pager_size', $pager_size );

		/**
		 * Filter the pager size setting
		 *
		 * @since 2.12
		 *
		 * @param int $pager_size
		 */
		$pager_size = apply_filters( 'simple_history/pager_size_dashboard', $pager_size );

		return $pager_size;
	}

	/**
	 * Show a link to our settings page on the Plugins -> Installed Plugins screen
	 */
	public function plugin_action_links( $actions, $b, $c, $d ) {
		// Only add link if user has the right to view the settings page
		if ( ! current_user_can( $this->get_view_settings_capability() ) ) {
			return $actions;
		}

		$settings_page_url = menu_page_url( self::SETTINGS_MENU_SLUG, 0 );

		if ( empty( $actions ) ) {
			// Create array if actions is empty (and therefore is assumed to be a string by PHP & results in PHP 7.1+ fatal error due to trying to make array modifications on what's assumed to be a string)
			$actions = [];
		} elseif ( is_string( $actions ) ) {
			// Convert the string (which it might've been retrieved as) to an array for future use as an array
			$actions = [ $actions ];
		}
		$actions[] = "<a href='$settings_page_url'>" . __( 'Settings', 'simple-history' ) . '</a>';

		return $actions;
	}

	/**
	 * Maybe add a dashboard widget,
	 * requires current user to have view history capability
	 * and a setting to show dashboard to be set
	 */
	public function add_dashboard_widget() {
		if ( $this->setting_show_on_dashboard() && current_user_can( $this->get_view_history_capability() ) ) {
			/**
			 * Filter to determine if history page should be added to page below dashboard or not
			 *
			 * @since 2.0.23
			 *
			 * @param bool $show_dashboard_widget Show the page or not
			 */
			$show_dashboard_widget = apply_filters( 'simple_history/show_dashboard_widget', true );

			if ( $show_dashboard_widget ) {
				wp_add_dashboard_widget(
					'simple_history_dashboard_widget',
					__( 'Simple History', 'simple-history' ),
					array(
						$this,
						'dashboard_widget_output',
					)
				);
			}
		}
	}

	/**
	 * Output html for the dashboard widget
	 */
	public function dashboard_widget_output() {
		$pager_size = $this->get_pager_size_dashboard();

		do_action( 'simple_history/dashboard/before_gui', $this );
		?>
		<div class="SimpleHistoryGui"
			 data-pager-size='<?php echo esc_attr( $pager_size ); ?>'
			 ></div>
		<?php
	}

	/**
	 * Check if the current page is any of the pages that belong
	 * to Simple History.
	 *
	 * @param string $hook The current page hook.
	 * @return bool
	 */
	public function is_on_our_own_pages( $hook = '' ) {
		$current_screen = get_current_screen();

		$basePrefix = apply_filters( 'simple_history/admin_location', 'index' );
		$basePrefix = $basePrefix === 'index' ? 'dashboard' : $basePrefix;

		if ( $current_screen && $current_screen->base == 'settings_page_' . self::SETTINGS_MENU_SLUG ) {
			return true;
		} elseif ( $current_screen && $current_screen->base === $basePrefix . '_page_simple_history_page' ) {
			return true;
		} elseif (
			$hook == 'settings_page_' . self::SETTINGS_MENU_SLUG ||
			( $this->setting_show_on_dashboard() && $hook == 'index.php' ) ||
			( $this->setting_show_as_page() && $hook == $basePrefix . '_page_simple_history_page' )
		) {
			return true;
		} elseif ( $current_screen && $current_screen->base == 'dashboard' && $this->setting_show_on_dashboard() ) {
			return true;
		}

		return false;
	}

	/**
	 * Enqueue styles and scripts for Simple History but only to our own pages.
	 *
	 * Only adds scripts to pages where the log is shown or the settings page.
	 */
	public function enqueue_admin_scripts( $hook ) {
		if ( $this->is_on_our_own_pages() ) {
			add_thickbox();

			wp_enqueue_style(
				'simple_history_styles',
				SIMPLE_HISTORY_DIR_URL . 'css/styles.css',
				false,
				SIMPLE_HISTORY_VERSION
			);

			wp_enqueue_script(
				'simple_history_script',
				SIMPLE_HISTORY_DIR_URL . 'js/scripts.js',
				array( 'jquery', 'backbone', 'wp-util' ),
				SIMPLE_HISTORY_VERSION,
				true
			);

			wp_enqueue_script( 'select2', SIMPLE_HISTORY_DIR_URL . 'js/select2/select2.full.min.js', array( 'jquery' ), SIMPLE_HISTORY_VERSION );
			wp_enqueue_style( 'select2', SIMPLE_HISTORY_DIR_URL . 'js/select2/select2.min.css', array(), SIMPLE_HISTORY_VERSION );

			// Translations that we use in JavaScript
			wp_localize_script(
				'simple_history_script',
				'simple_history_script_vars',
				array(
					'settingsConfirmClearLog' => __( 'Remove all log items?', 'simple-history' ),
					'pagination' => array(
						'goToTheFirstPage' => __( 'Go to the first page', 'simple-history' ),
						'goToThePrevPage' => __( 'Go to the previous page', 'simple-history' ),
						'goToTheNextPage' => __( 'Go to the next page', 'simple-history' ),
						'goToTheLastPage' => __( 'Go to the last page', 'simple-history' ),
						'currentPage' => __( 'Current page', 'simple-history' ),
					),
					'loadLogAPIError' => __( 'Oups, the log could not be loaded right now.', 'simple-history' ),
					'ajaxLoadError' => __(
						'Hm, the log could not be loaded right now. Perhaps another plugin is giving some errors. Anyway, below is the output I got from the server.',
						'simple-history'
					),
					'logNoHits' => __( 'Your search did not match any history events.', 'simple-history' ),
				)
			);

			// Call plugins admin_css-method, so they can add CSS.
			foreach ( $this->instantiated_loggers as $one_logger ) {
				$one_logger['instance']->admin_css();
			}

			// Add timeago.js
			wp_enqueue_script(
				'timeago',
				SIMPLE_HISTORY_DIR_URL . 'js/timeago/jquery.timeago.js',
				array( 'jquery' ),
				'1.5.2',
				true
			);

			// Determine current locale to load timeago and Select 2locale.
			$user_locale = strtolower( substr( get_user_locale(), 0, 2 ) ); // en_US

			$locale_url_path = SIMPLE_HISTORY_DIR_URL . 'js/timeago/locales/jquery.timeago.%s.js';
			$locale_dir_path = SIMPLE_HISTORY_PATH . 'js/timeago/locales/jquery.timeago.%s.js';

			// Only enqueue if locale-file exists on file system
			if ( file_exists( sprintf( $locale_dir_path, $user_locale ) ) ) {
				wp_enqueue_script( 'timeago-locale', sprintf( $locale_url_path, $user_locale ), array( 'jquery' ), '1.5.2', true );
			} else {
				wp_enqueue_script( 'timeago-locale', sprintf( $locale_url_path, 'en' ), array( 'jquery' ), '1.5.2', true );
			}

			// end add timeago
			// Load Select2 locale
			$locale_url_path = SIMPLE_HISTORY_DIR_URL . 'js/select2/i18n/%s.js';
			$locale_dir_path = SIMPLE_HISTORY_PATH . 'js/select2/i18n/%s.js';

			if ( file_exists( sprintf( $locale_dir_path, $user_locale ) ) ) {
				wp_enqueue_script( 'select2-locale', sprintf( $locale_url_path, $user_locale ), array( 'jquery' ), '3.5.1', true );
			}

			/**
			 * Fires when the admin scripts have been enqueued.
			 * Only fires on any of the pages where Simple History is used
			 *
			 * @since 2.0
			 *
			 * @param Simple_History $instance This class.
			 */
			do_action( 'simple_history/enqueue_admin_scripts', $this );
		} // End if().
	}

	public function filter_option_page_capability( $capability ) {
		return $capability;
	}

	/**
	 * Check if plugin version have changed, i.e. has been upgraded
	 * If upgrade is detected then maybe modify database and so on for that version
	 */
	public function check_for_upgrade() {
		global $wpdb;

		$db_version = get_option( 'simple_history_db_version' );
		$table_name = $wpdb->prefix . self::DBTABLE;
		$table_name_contexts = $wpdb->prefix . self::DBTABLE_CONTEXTS;
		$first_install = false;

		// If no db_version is set then this
		// is a version of Simple History < 0.4
		// or it's a first install
		// Fix database not using UTF-8
		if ( false === $db_version || (int) $db_version == 0 ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';

			// Table creation, used to be in register_activation_hook
			// We change the varchar size to add one num just to force update of encoding. dbdelta didn't see it otherwise.
			$sql =
				'CREATE TABLE ' .
				$table_name .
				' (
              id bigint(20) NOT NULL AUTO_INCREMENT,
              date datetime NOT NULL,
              PRIMARY KEY  (id)
            ) CHARACTER SET=utf8;';

			// Upgrade db / fix utf for varchars
			dbDelta( $sql );

			// Fix UTF-8 for table
			$sql = sprintf( 'alter table %1$s charset=utf8;', $table_name );
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->query( $sql );

			$db_version = 1;

			update_option( 'simple_history_db_version', $db_version );

			// We are not 100% sure that this is a first install,
			// but it is at least a very old version that is being updated
			$first_install = true;
		} // End if().

		// If db version is 1 then upgrade to 2
		// Version 2 added the action_description column
		if ( 1 == (int) $db_version ) {
			// V2 used to add column "action_description"
			// but it's not used any more so don't do i
			$db_version = 2;

			update_option( 'simple_history_db_version', $db_version );
		}

		// Check that all options we use are set to their defaults, if they miss value
		// Each option that is missing a value will make a sql call otherwise = unnecessary
		$arr_options = array(
			array(
				'name' => 'simple_history_show_as_page',
				'default_value' => 1,
			),
			array(
				'name' => 'simple_history_show_on_dashboard',
				'default_value' => 1,
			),
		);

		foreach ( $arr_options as $one_option ) {
			$option_value = get_option( $one_option['name'] );
			if ( false === ( $option_value ) ) {
				// Value is not set in db, so set it to a default
				update_option( $one_option['name'], $one_option['default_value'] );
			}
		}

		/**
		 * If db_version is 2 then upgrade to 3:
		 * - Add some fields to existing table wp_simple_history_contexts
		 * - Add all new table wp_simple_history_contexts
		 *
		 * @since 2.0
		 */
		if ( 2 == (int) $db_version ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';

			// Update old table
			$sql = "
                CREATE TABLE {$table_name} (
                  id bigint(20) NOT NULL AUTO_INCREMENT,
                  date datetime NOT NULL,
                  logger varchar(30) DEFAULT NULL,
                  level varchar(20) DEFAULT NULL,
                  message varchar(255) DEFAULT NULL,
                  occasionsID varchar(32) DEFAULT NULL,
                  initiator varchar(16) DEFAULT NULL,
                  PRIMARY KEY  (id),
                  KEY date (date),
                  KEY loggerdate (logger,date)
                ) CHARSET=utf8;";

			dbDelta( $sql );

			// Add context table
			$sql = "
                CREATE TABLE IF NOT EXISTS {$table_name_contexts} (
                  context_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                  history_id bigint(20) unsigned NOT NULL,
                  `key` varchar(255) DEFAULT NULL,
                  value longtext,
                  PRIMARY KEY  (context_id),
                  KEY history_id (history_id),
                  KEY `key` (`key`)
                ) CHARSET=utf8;
            ";

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->query( $sql );

			$db_version = 3;
			update_option( 'simple_history_db_version', $db_version );

			// Update possible old items to use SimpleLogger.
			$sql = sprintf(
				'
                    UPDATE %1$s
                    SET
                        logger = "SimpleLogger",
                        level = "info"
                    WHERE logger IS NULL
                ',
				$table_name
			);

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->query( $sql );

			// Say welcome, however loggers are not added this early so we need to
			// use a filter to load it later
			add_action( 'simple_history/loggers_loaded', array( $this, 'add_welcome_log_message' ) );
		} // End if().

		/**
		 * If db version = 3
		 * then we need to update database to allow null values for some old columns
		 * that used to work in pre wp 4.1 beta, but since 4.1 wp uses STRICT_ALL_TABLES
		 * WordPress Commit: https://github.com/WordPress/WordPress/commit/f17d168a0f72211a9bfd9d3fa680713069871bb6
		 *
		 * @since 2.0
		 */
		if ( 3 == (int) $db_version ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';

			// If old columns exist = this is an old install, then modify the columns so we still can keep them
			// we want to keep them because user may have logged items that they want to keep
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$db_cools = $wpdb->get_col( "DESCRIBE $table_name" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

			if ( in_array( 'action', $db_cools ) ) {
				$sql = sprintf(
					'
                        ALTER TABLE %1$s
                        MODIFY `action` varchar(255) NULL,
                        MODIFY `object_type` varchar(255) NULL,
                        MODIFY `object_subtype` varchar(255) NULL,
                        MODIFY `user_id` int(10) NULL,
                        MODIFY `object_id` int(10) NULL,
                        MODIFY `object_name` varchar(255) NULL
                    ',
					$table_name
				);
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$wpdb->query( $sql );
			}

			$db_version = 4;

			update_option( 'simple_history_db_version', $db_version );
		} // End if().

		// Some installs on 2.2.2 got failed installs
		// We detect these by checking for db_version and then running the install stuff again
		if ( 4 == (int) $db_version ) {
			/** @noRector \Rector\CodeQuality\Rector\If_\SimplifyIfElseToTernaryRector */
			if ( ! $this->does_database_have_data() ) {
				// not ok, decrease db number so installs will run again and hopefully fix things
				$db_version = 0;
			} else {
				// all looks ok, upgrade to db version 5, so this part is not done again
				$db_version = 5;
			}

			update_option( 'simple_history_db_version', $db_version );
		}
	}

	/**
	 * Check if the database has data/rows
	 *
	 * @since 2.1.6
	 * @return bool True if database is not empty, false if database is empty = contains no data
	 */
	public function does_database_have_data() {
		global $wpdb;

		$tableprefix = $wpdb->prefix;
		$simple_history_table = self::DBTABLE;

		$sql_data_exists = "SELECT id AS id_exists FROM {$tableprefix}{$simple_history_table} LIMIT 1";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$data_exists = (bool) $wpdb->get_var( $sql_data_exists, 0 );

		return $data_exists;
	}

	// TODO: move functionality out from file/class, to dropin or similar
	/**
	 * Greet users to version 2!
	 * Is only called after database has been upgraded, so only on first install (or upgrade).
	 * Not called after only plugin activation.
	 */
	public function add_welcome_log_message() {
		$db_data_exists = $this->does_database_have_data();

		$plugin_logger = $this->get_instantiated_logger_by_slug( 'SimplePluginLogger' );

		if ( $plugin_logger instanceof Plugin_Logger ) {
			// Add plugin installed message
			$context = array(
				'plugin_name' => 'Simple History',
				'plugin_description' =>
					'Plugin that logs various things that occur in WordPress and then presents those events in a very nice GUI.',
				'plugin_url' => 'https://simple-history.com',
				'plugin_version' => SIMPLE_HISTORY_VERSION,
				'plugin_author' => 'Pär Thernström',
			);

			$plugin_logger->info_message( 'plugin_installed', $context );

			// Add plugin activated message
			$context['plugin_slug'] = 'simple-history';
			$context['plugin_title'] = '<a href="https://simple-history.com/">Simple History</a>';

			$plugin_logger->info_message( 'plugin_activated', $context );
		}

		if ( ! $db_data_exists ) {
			$welcome_message_1 = __(
				'
Welcome to Simple History!

This is the main history feed. It will contain events that this plugin has logged.
',
				'simple-history'
			);

			$welcome_message_2 = __(
				'
Because Simple History was only recently installed, this feed does not display many events yet. As long as the plugin remains activated you will soon see detailed information about page edits, plugin updates, users logging in, and much more.
',
				'simple-history'
			);

			SimpleLogger()->info(
				$welcome_message_2,
				array(
					'_initiator' => Log_Initiators::WORDPRESS,
				)
			);

			SimpleLogger()->info(
				$welcome_message_1,
				array(
					'_initiator' => Log_Initiators::WORDPRESS,
				)
			);
		}
	}

	/**
	 * Register a settings tab.
	 *
	 * @param array $arr_tab_settings {
	 *     An array of default site sign-up variables.
	 *
	 *     @type string   $slug   Unique slug of settings tab.
	 *     @type string   $name Human friendly name of the tab, shown on the settings page.
	 *     @type int      $order Order of the tab, where higher number means earlier output,
	 *     @type callable $function Function that will show the settings tab output.
	 * }
	 */
	public function register_settings_tab( $arr_tab_settings ) {
		$this->arr_settings_tabs[] = $arr_tab_settings;
	}

	/**
	 * Get the registered settings tabs.
	 *
	 * The tabs are ordered by the order key, where higher number means earlier output,
	 * i.e. the tab is outputted more to the left in the settings page.
	 *
	 * Tabs with no order is outputted last.
	 *
	 * @return array
	 */
	public function get_settings_tabs() {
		// Sort by order, where higher number means earlier output.
		usort(
			$this->arr_settings_tabs,
			function( $a, $b ) {
				$a_order = $a['order'] ?? 0;
				$b_order = $b['order'] ?? 0;

				if ( $a_order === $b_order ) {
					return 0;
				}

				return ( $a_order > $b_order ) ? -1 : 1;
			}
		);

		return $this->arr_settings_tabs;
	}

	/**
	 * Set settings tabs.
	 *
	 * @param array $arr_settings_tabs
	 * @return void
	 */
	public function set_settings_tabs( $arr_settings_tabs ) {
		$this->arr_settings_tabs = $arr_settings_tabs;
	}

	/**
	 * Add pages (history page and settings page)
	 */
	public function add_admin_pages() {
		// Add a history page as a sub-page below the Dashboard menu item
		if ( $this->setting_show_as_page() ) {
			/**
			 * Filter to determine if history page should be added to page below dashboard or not
			 *
			 * @since 2.0.23
			 *
			 * @param bool $show_dashboard_page Show the page or not
			 */
			$show_dashboard_page = apply_filters( 'simple_history/show_dashboard_page', true );

			if ( $show_dashboard_page ) {
				add_submenu_page(
					apply_filters( 'simple_history/admin_location', 'index' ) . '.php',
					_x( 'Simple History', 'dashboard title name', 'simple-history' ),
					_x( 'Simple History', 'dashboard menu name', 'simple-history' ),
					$this->get_view_history_capability(),
					'simple_history_page',
					array( $this, 'history_page_output' )
				);
			}
		}
	}

	/**
	 * Detect clear log query arg and clear log if it is set and valid.
	 */
	public function clear_log_from_url_request() {
		// Clear the log if clear button was clicked in settings
		// and redirect user to show message.
		if (
			isset( $_GET['simple_history_clear_log_nonce'] ) &&
			wp_verify_nonce( $_GET['simple_history_clear_log_nonce'], 'simple_history_clear_log' )
		) {
			if ( $this->user_can_clear_log() ) {
				$num_rows_deleted = $this->clear_log();

				/**
				 * Fires after the log has been cleared using
				 * the "Clear log now" button on the settings page.
				 *
				 * @param int $num_rows_deleted Number of rows deleted.
				 */
				do_action( 'simple_history/settings/log_cleared', $num_rows_deleted );
			}

			$msg = __( 'Cleared database', 'simple-history' );

			add_settings_error(
				'simple_history_settings_clear_log',
				'simple_history_settings_clear_log',
				$msg,
				'updated'
			);

			set_transient( 'settings_errors', get_settings_errors(), 30 );

			$goback = esc_url_raw( add_query_arg( 'settings-updated', 'true', wp_get_referer() ) );
			wp_redirect( $goback );
			exit();
		}
	}

	/**
	 * Output for page with the history
	 */
	public function history_page_output() {
		$pager_size = $this->get_pager_size();

		/**
		 * Filter the pager size setting for the history page
		 *
		 * @since 2.0
		 *
		 * @param int $pager_size
		 */
		$pager_size = apply_filters( 'simple_history/page_pager_size', $pager_size );
		?>

		<div class="wrap SimpleHistoryWrap">

			<h1 class="SimpleHistoryPageHeadline">
				<div class="dashicons dashicons-backup SimpleHistoryPageHeadline__icon"></div>
				<?php echo esc_html_x( 'Simple History', 'history page headline', 'simple-history' ); ?>
			</h1>

			<?php
			/**
			 * Fires before the gui div
			 *
			 * @since 2.0
			 *
			 * @param Simple_History $instance This class.
			 */
			do_action( 'simple_history/history_page/before_gui', $this );
			?>

			<div class="SimpleHistoryGuiWrap">

				<div class="SimpleHistoryGui"
					 data-pager-size='<?php echo esc_attr( $pager_size ); ?>'
					 ></div>
					<?php
					/**
					 * Fires after the gui div
					 *
					 * @since 2.0
					 *
					 * @param Simple_History $instance This class.
					 */
					do_action( 'simple_history/history_page/after_gui', $this );
					?>
			</div>
		</div>
		<?php
	}

	/**
	 * Get setting if plugin should be visible on dashboard.
	 * Defaults to true
	 *
	 * @return bool
	 */
	public function setting_show_on_dashboard() {
		$show_on_dashboard = get_option( 'simple_history_show_on_dashboard', 1 );
		$show_on_dashboard = apply_filters( 'simple_history_show_on_dashboard', $show_on_dashboard );
		return (bool) $show_on_dashboard;
	}

	/**
	 * Should simple history be shown as a page
	 * Defaults to true
	 *
	 * @return bool
	 */
	public function setting_show_as_page() {
		$setting = get_option( 'simple_history_show_as_page', 1 );
		$setting = apply_filters( 'simple_history_show_as_page', $setting );

		return (bool) $setting;
	}

	/**
	 * How old log entried are allowed to be.
	 * 0 = don't delete old entries.
	 *
	 * @return int Number of days.
	 */
	public function get_clear_history_interval() {
		$days = 60;

		// Deprecated filter name, use `simple_history/db_purge_days_interval` instead.
		$days = (int) apply_filters( 'simple_history_db_purge_days_interval', $days );

		/**
		 * Filter to modify number of days of history to keep.
		 * Default is 60 days.
		 *
		 * @example Keep only the most recent 7 days in the log.
		 *
		 * ```php
		 * add_filter( "simple_history/db_purge_days_interval", function( $days ) {
		 *      $days = 7;
		 *      return $days;
		 *  } );
		 * ```
		 *
		 * @example Expand the log to keep 90 days in the log.
		 *
		 * ```php
		 * add_filter( "simple_history/db_purge_days_interval", function( $days ) {
		 *      $days = 90;
		 *      return $days;
		 *  } );
		 * ```
		 *
		 * @param int $days Number of days of history to keep
		 */
		$days = apply_filters( 'simple_history/db_purge_days_interval', $days );

		return $days;
	}

	/**
	 * Removes all items from the log.
	 *
	 * @return int Number of rows removed.
	 */
	public function clear_log() {
		global $wpdb;

		$tableprefix = $wpdb->prefix;

		$simple_history_table = self::DBTABLE;
		$simple_history_context_table = self::DBTABLE_CONTEXTS;

		// Get number of rows before delete.
		$sql_num_rows = "SELECT count(id) AS num_rows FROM {$tableprefix}{$simple_history_table}";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$num_rows = $wpdb->get_var( $sql_num_rows, 0 );

		// Use truncate instead of delete because it's much faster (I think, writing this much later).
		$sql = "TRUNCATE {$tableprefix}{$simple_history_table}";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( $sql );

		$sql = "TRUNCATE {$tableprefix}{$simple_history_context_table}";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( $sql );

		Helpers::get_cache_incrementor( true );

		return $num_rows;
	}

	/**
	 * Return plain text output for a log row
	 * Uses the get_log_row_plain_text_output of the logger that logged the row
	 * with fallback to SimpleLogger if logger is not available.
	 *
	 * @param object $row
	 * @return string
	 */
	public function get_log_row_plain_text_output( $row ) {
		$row_logger = $row->logger;
		$row->context = isset( $row->context ) && is_array( $row->context ) ? $row->context : array();

		if ( ! isset( $row->context['_message_key'] ) ) {
			$row->context['_message_key'] = null;
		}

		// Fallback to SimpleLogger if no logger exists for row
		if ( ! isset( $this->instantiated_loggers[ $row_logger ] ) ) {
			$row_logger = 'SimpleLogger';
		}

		$logger = $this->instantiated_loggers[ $row_logger ]['instance'];

		return $logger->get_log_row_plain_text_output( $row );
	}

	/**
	 * Return header output for a log row.
	 *
	 * Uses the get_log_row_header_output of the logger that logged the row
	 * with fallback to SimpleLogger if logger is not available.
	 *
	 * Loggers are discouraged to override this in the loggers,
	 * because the output should be the same for all items in the GUI.
	 *
	 * @param object $row
	 * @return string
	 */
	public function get_log_row_header_output( $row ) {
		$row_logger = $row->logger;
		$row->context = isset( $row->context ) && is_array( $row->context ) ? $row->context : array();

		// Fallback to SimpleLogger if no logger exists for row
		if ( ! isset( $this->instantiated_loggers[ $row_logger ] ) ) {
			$row_logger = 'SimpleLogger';
		}

		$logger = $this->instantiated_loggers[ $row_logger ]['instance'];

		return $logger->get_log_row_header_output( $row );
	}

	/**
	 *
	 *
	 * @param object $row
	 * @return string
	 */
	private function get_log_row_sender_image_output( $row ) {
		$row_logger = $row->logger;
		$row->context = isset( $row->context ) && is_array( $row->context ) ? $row->context : array();

		// Fallback to SimpleLogger if no logger exists for row
		if ( ! isset( $this->instantiated_loggers[ $row_logger ] ) ) {
			$row_logger = 'SimpleLogger';
		}

		$logger = $this->instantiated_loggers[ $row_logger ]['instance'];

		return $logger->get_log_row_sender_image_output( $row );
	}

	public function get_log_row_details_output( $row ) {
		$row_logger = $row->logger;
		$row->context = isset( $row->context ) && is_array( $row->context ) ? $row->context : array();

		// Fallback to SimpleLogger if no logger exists for row
		if ( ! isset( $this->instantiated_loggers[ $row_logger ] ) ) {
			$row_logger = 'SimpleLogger';
		}

		$logger = $this->instantiated_loggers[ $row_logger ]['instance'];

		return $logger->get_log_row_details_output( $row );
	}

	/**
	 * Returns the HTML output for a log row, to be used in the GUI/Activity Feed.
	 * This includes HTML for the header, the sender image, and the details.
	 *
	 * @param object $oneLogRow LogQuery array with data from LogQuery
	 * @return string
	 */
	public function get_log_row_html_output( $oneLogRow, $args ) {
		$defaults = array(
			'type' => 'overview', // or "single" to include more stuff (used in for example modal details window)
		);

		$args = wp_parse_args( $args, $defaults );

		$header_html = $this->get_log_row_header_output( $oneLogRow );
		$plain_text_html = $this->get_log_row_plain_text_output( $oneLogRow );
		$sender_image_html = $this->get_log_row_sender_image_output( $oneLogRow );

		// Details = for example thumbnail of media
		$details_html = trim( $this->get_log_row_details_output( $oneLogRow ) );
		if ( $details_html !== '' ) {
			$details_html = sprintf( '<div class="SimpleHistoryLogitem__details">%1$s</div>', $details_html );
		}

		// subsequentOccasions = including the current one
		$occasions_count = $oneLogRow->subsequentOccasions - 1;
		$occasions_html = '';

		if ( $occasions_count > 0 ) {
			$occasions_html = '<div class="SimpleHistoryLogitem__occasions">';

			$occasions_html .= '<a href="#" class="SimpleHistoryLogitem__occasionsLink">';
			$occasions_html .= sprintf(
				// translators: %1$s is number of similar events.
				_n( '+%1$s similar event', '+%1$s similar events', $occasions_count, 'simple-history' ),
				$occasions_count
			);
			$occasions_html .= '</a>';

			$occasions_html .= '<span class="SimpleHistoryLogitem__occasionsLoading">';
			$occasions_html .= sprintf( __( 'Loading…', 'simple-history' ), $occasions_count );
			$occasions_html .= '</span>';

			$occasions_html .= '<span class="SimpleHistoryLogitem__occasionsLoaded">';
			$occasions_html .= sprintf(
				// translators: %1$s is number of similar events.
				__( 'Showing %1$s more', 'simple-history' ),
				$occasions_count
			);
			$occasions_html .= '</span>';

			$occasions_html .= '</div>';
		}

		// Add data attributes to log row, so plugins can do stuff.
		$data_attrs = '';
		$data_attrs .= sprintf( ' data-row-id="%1$d" ', $oneLogRow->id );
		$data_attrs .= sprintf( ' data-occasions-count="%1$d" ', $occasions_count );
		$data_attrs .= sprintf( ' data-occasions-id="%1$s" ', esc_attr( $oneLogRow->occasionsID ) );

		// Add data attributes for remote address and other ip number headers.
		if ( isset( $oneLogRow->context['_server_remote_addr'] ) ) {
			$data_attrs .= sprintf( ' data-ip-address="%1$s" ', esc_attr( $oneLogRow->context['_server_remote_addr'] ) );
		}

		$arr_found_additional_ip_headers = Helpers::get_event_ip_number_headers( $oneLogRow );

		if ( $arr_found_additional_ip_headers !== [] ) {
			$data_attrs .= ' data-ip-address-multiple="1" ';
		}

		// Add data attributes info for common things like logger, level, data, initiation.
		$data_attrs .= sprintf( ' data-logger="%1$s" ', esc_attr( $oneLogRow->logger ) );
		$data_attrs .= sprintf( ' data-level="%1$s" ', esc_attr( $oneLogRow->level ) );
		$data_attrs .= sprintf( ' data-date="%1$s" ', esc_attr( $oneLogRow->date ) );
		$data_attrs .= sprintf( ' data-initiator="%1$s" ', esc_attr( $oneLogRow->initiator ) );

		if ( isset( $oneLogRow->context['_user_id'] ) ) {
			$data_attrs .= sprintf( ' data-initiator-user-id="%1$d" ', $oneLogRow->context['_user_id'] );
		}

		// If type is single then include more details.
		// This is typically shown in the modal window when clicking the event date and time.
		$more_details_html = '';
		if ( $args['type'] == 'single' ) {
			$more_details_html = apply_filters(
				'simple_history/log_html_output_details_single/html_before_context_table',
				$more_details_html,
				$oneLogRow
			);

			$more_details_html .= sprintf(
				'<h2 class="SimpleHistoryLogitem__moreDetailsHeadline">%1$s</h2>',
				__( 'Context data', 'simple-history' )
			);
			$more_details_html .=
				'<p>' . __( 'This is potentially useful meta data that a logger has saved.', 'simple-history' ) . '</p>';
			$more_details_html .= "<table class='SimpleHistoryLogitem__moreDetailsContext'>";
			$more_details_html .= sprintf(
				'<tr>
                    <th>%1$s</th>
                    <th>%2$s</th>
                </tr>',
				'Key',
				'Value'
			);

			$logRowKeysToShow = array_fill_keys( array_keys( (array) $oneLogRow ), true );

			/**
			 * Filter what keys to show from oneLogRow
			 *
			 * Array is in format
			 *
			 * ```
			 *  Array
			 *   (
			 *       [id] => 1
			 *       [logger] => 1
			 *       [level] => 1
			 *       ...
			 *   )
			 * ```
			 *
			 * @example Hide some columns from the detailed context view popup window
			 *
			 * ```php
			 *  add_filter(
			 *      'simple_history/log_html_output_details_table/row_keys_to_show',
			 *      function ( $logRowKeysToShow, $oneLogRow ) {
			 *
			 *          $logRowKeysToShow['id'] = false;
			 *          $logRowKeysToShow['logger'] = false;
			 *          $logRowKeysToShow['level'] = false;
			 *          $logRowKeysToShow['message'] = false;
			 *
			 *          return $logRowKeysToShow;
			 *      },
			 *      10,
			 *      2
			 *  );
			 * ```
			 *
			 * @since 2.0.29
			 *
			 * @param array $logRowKeysToShow with keys to show. key to show = key. value = boolean to show or not.
			 * @param object $oneLogRow log row to show details from
			 */
			$logRowKeysToShow = apply_filters(
				'simple_history/log_html_output_details_table/row_keys_to_show',
				$logRowKeysToShow,
				$oneLogRow
			);

			// Hide some keys by default
			unset(
				$logRowKeysToShow['occasionsID'],
				$logRowKeysToShow['subsequentOccasions'],
				$logRowKeysToShow['rep'],
				$logRowKeysToShow['repeated'],
				$logRowKeysToShow['occasionsIDType'],
				$logRowKeysToShow['context'],
				$logRowKeysToShow['type']
			);

			foreach ( $oneLogRow as $rowKey => $rowVal ) {
				// Only columns from oneLogRow that exist in logRowKeysToShow will be outputted
				if ( ! array_key_exists( $rowKey, $logRowKeysToShow ) || ! $logRowKeysToShow[ $rowKey ] ) {
					continue;
				}

				// skip arrays and objects and such
				if ( is_array( $rowVal ) || is_object( $rowVal ) ) {
					continue;
				}

				$more_details_html .= sprintf(
					'<tr>
                        <td>%1$s</td>
                        <td>%2$s</td>
                    </tr>',
					esc_html( $rowKey ),
					esc_html( $rowVal )
				);
			}

			$logRowContextKeysToShow = array_fill_keys( array_keys( (array) $oneLogRow->context ), true );

			/**
			 * Filter what keys to show from the row context.
			 *
			 * Array is in format:
			 *
			 * ```
			 *   Array
			 *   (
			 *       [plugin_slug] => 1
			 *       [plugin_name] => 1
			 *       [plugin_title] => 1
			 *       [plugin_description] => 1
			 *       [plugin_author] => 1
			 *       [plugin_version] => 1
			 *       ...
			 *   )
			 * ```
			 *
			 *  @example Hide some more columns from the detailed context view popup window
			 *
			 * ```php
			 *  add_filter(
			 *      'simple_history/log_html_output_details_table/context_keys_to_show',
			 *      function ( $logRowContextKeysToShow, $oneLogRow ) {
			 *
			 *          $logRowContextKeysToShow['plugin_slug'] = false;
			 *          $logRowContextKeysToShow['plugin_name'] = false;
			 *          $logRowContextKeysToShow['plugin_title'] = false;
			 *          $logRowContextKeysToShow['plugin_description'] = false;
			 *
			 *          return $logRowContextKeysToShow;
			 *      },
			 *      10,
			 *      2
			 *  );
			 * ```
			 *
			 *
			 * @since 2.0.29
			 *
			 * @param array $logRowContextKeysToShow with keys to show. key to show = key. value = boolean to show or not.
			 * @param object $oneLogRow log row to show details from
			 */
			$logRowContextKeysToShow = apply_filters(
				'simple_history/log_html_output_details_table/context_keys_to_show',
				$logRowContextKeysToShow,
				$oneLogRow
			);

			foreach ( $oneLogRow->context as $contextKey => $contextVal ) {
				// Only columns from context that exist in logRowContextKeysToShow will be outputted
				if (
					! array_key_exists( $contextKey, $logRowContextKeysToShow ) ||
					! $logRowContextKeysToShow[ $contextKey ]
				) {
					continue;
				}

				$more_details_html .= sprintf(
					'<tr>
                        <td>%1$s</td>
                        <td>%2$s</td>
                    </tr>',
					esc_html( $contextKey ),
					esc_html( $contextVal )
				);
			}

			$more_details_html .= '</table>';

			$more_details_html = apply_filters(
				'simple_history/log_html_output_details_single/html_after_context_table',
				$more_details_html,
				$oneLogRow
			);

			$more_details_html = sprintf(
				'<div class="SimpleHistoryLogitem__moreDetails">%1$s</div>',
				$more_details_html
			);
		} // End if().

		// Classes to add to log item li element
		$classes = array(
			'SimpleHistoryLogitem',
			"SimpleHistoryLogitem--loglevel-{$oneLogRow->level}",
			"SimpleHistoryLogitem--logger-{$oneLogRow->logger}",
		);

		if ( isset( $oneLogRow->initiator ) && ! empty( $oneLogRow->initiator ) ) {
			$classes[] = 'SimpleHistoryLogitem--initiator-' . $oneLogRow->initiator;
		}

		if ( $arr_found_additional_ip_headers !== [] ) {
			$classes[] = 'SimpleHistoryLogitem--IPAddress-multiple';
		}

		// Always append the log level tag
		$log_level_tag_html = sprintf(
			' <span class="SimpleHistoryLogitem--logleveltag SimpleHistoryLogitem--logleveltag-%1$s">%2$s</span>',
			$oneLogRow->level,
			Log_Levels::get_log_level_translated( $oneLogRow->level )
		);

		$plain_text_html .= $log_level_tag_html;

		/**
		 * Filter to modify classes added to item li element
		 *
		 * @since 2.0.7
		 *
		 * @param array $classes Array with classes
		 */
		$classes = apply_filters( 'simple_history/logrowhtmloutput/classes', $classes );

		// Generate the HTML output for a row
		$output = sprintf(
			'
                <li %8$s class="%10$s">
                    <div class="SimpleHistoryLogitem__firstcol">
                        <div class="SimpleHistoryLogitem__senderImage">%3$s</div>
                    </div>
                    <div class="SimpleHistoryLogitem__secondcol">
                        <div class="SimpleHistoryLogitem__header">%1$s</div>
                        <div class="SimpleHistoryLogitem__text">%2$s</div>
                        %6$s <!-- details_html -->
                        %9$s <!-- more details html -->
                        %4$s <!-- occasions -->
                    </div>
                </li>
            ',
			$header_html, // 1
			$plain_text_html, // 2
			$sender_image_html, // 3
			$occasions_html, // 4
			$oneLogRow->level, // 5
			$details_html, // 6
			$oneLogRow->logger, // 7
			$data_attrs, // 8 data attributes
			$more_details_html, // 9
			esc_attr( join( ' ', $classes ) ) // 10
		);

		// Get the main message row.
		// Should be as plain as possible, like plain text
		// but with links to for example users and posts
		// SimpleLoggerFormatter::getRowTextOutput($oneLogRow);
		// Get detailed HTML-based output
		// May include images, lists, any cool stuff needed to view
		// SimpleLoggerFormatter::getRowHTMLOutput($oneLogRow);
		return trim( $output );
	}

	/**
	 * Get instantiated loggers.
	 *
	 * @return array
	 */
	public function get_instantiated_loggers() {
		return $this->instantiated_loggers;
	}

	/**
	 * Set instantiated loggers.
	 *
	 * @param array $instantiated_loggers
	 * @return void
	 */
	public function set_instantiated_loggers( $instantiated_loggers ) {
		$this->instantiated_loggers = $instantiated_loggers;
	}

	/**
	 * Get instantiated dropins.
	 *
	 * @return array
	 */
	public function get_instantiated_dropins() {
		return $this->instantiated_dropins;
	}

	/**
	 * Set instantiated dropins.
	 *
	 * @param array $instantiated_dropins
	 */
	public function set_instantiated_dropins( $instantiated_dropins ) {
		$this->instantiated_dropins = $instantiated_dropins;
	}

	/**
	 * Get instantiated dropin by slug.
	 * Returns the logger instance if found, or bool false if not found.
	 * @param string $slug
	 * @return bool|Dropin
	 */
	public function get_instantiated_dropin_by_slug( $slug = '' ) {
		if ( empty( $slug ) ) {
			return false;
		}

		foreach ( $this->get_instantiated_dropins() as $one_dropin ) {
			if ( $slug === $one_dropin['instance']->get_slug() ) {
				return $one_dropin['instance'];
			}
		}

		return false;
	}

	/**
	 * @param string $slug
	 * @return bool|Logger logger instance if found, bool false if logger not found
	 */
	public function get_instantiated_logger_by_slug( $slug = '' ) {
		if ( empty( $slug ) ) {
			return false;
		}

		foreach ( $this->get_instantiated_loggers() as $one_logger ) {
			if ( $slug === $one_logger['instance']->get_slug() ) {
				return $one_logger['instance'];
			}
		}

		return false;
	}

	/**
	 * Check which loggers a user has the right to read and return an array
	 * with all loggers they are allowed to read.
	 *
	 * @param int    $user_id Id of user to get loggers for. Defaults to current user id.
	 * @param string $format format to return loggers in. Default is array. Can also be "sql"
	 * @return array
	 */
	public function get_loggers_that_user_can_read( $user_id = null, $format = 'array' ) {
		$arr_loggers_user_can_view = array();

		if ( is_null( $user_id ) ) {
			$user_id = get_current_user_id();
		}

		$loggers = $this->get_instantiated_loggers();

		foreach ( $loggers as $one_logger ) {
			$logger_capability = $one_logger['instance']->get_capability();

			$user_can_read_logger = user_can( $user_id, $logger_capability );

			/**
			 * Filters who can read/view the messages from a single logger.
			 *
			 * @example Modify who can read a logger.
			 *
			 * ```php
			 * // Modify who can read a logger.
			 * // Modify the if part to give users access or no access to a logger.
			 * add_filter(
			 *   'simple_history/loggers_user_can_read/can_read_single_logger',
			 *   function ( $user_can_read_logger, $logger_instance, $user_id ) {
			 *     // in this example user with id 3 gets access to the post logger
			 *     // while user with id 8 does not get any access to it
			 *     if ( $logger_instance->get_slug() == 'SimplePostLogger' && $user_id === 3 ) {
			 *       $user_can_read_logger = true;
			 *     } elseif ( $logger_instance->get_slug() == 'SimplePostLogger' && $user_id === 9 ) {
			 *       $user_can_read_logger = false;
			 *     }
			 *
			 *      return $user_can_read_logger;
			 *    },
			 *  10,
			 *  3
			 * );
			 * ```
			 *
			 * @param bool $user_can_read_logger Whether the user is allowed to view the logger.
			 * @param Simple_Logger $logger Logger instance.
			 * @param int $user_id Id of user.
			 */
			$user_can_read_logger = apply_filters(
				'simple_history/loggers_user_can_read/can_read_single_logger',
				$user_can_read_logger,
				$one_logger['instance'],
				$user_id
			);

			if ( $user_can_read_logger ) {
				$arr_loggers_user_can_view[] = $one_logger;
			}
		}

		/**
		 * Fires before Simple History does it's init stuff
		 *
		 * @since 2.0
		 *
		 * @param array $arr_loggers_user_can_view Array with loggers that user $user_id can read
		 * @param int $user_id ID of user to check read capability for
		 */
		$arr_loggers_user_can_view = apply_filters(
			'simple_history/loggers_user_can_read',
			$arr_loggers_user_can_view,
			$user_id
		);

		// just return array with slugs in parenthesis suitable for sql-where
		if ( 'sql' == $format ) {
			$str_return = '(';

			if ( count( $arr_loggers_user_can_view ) ) {
				foreach ( $arr_loggers_user_can_view as $one_logger ) {
					$str_return .= sprintf( '"%1$s", ', esc_sql( $one_logger['instance']->get_slug() ) );
				}

				$str_return = rtrim( $str_return, ' ,' );
			} else {
				// user was not allowed to read any loggers, return in (NULL) to return nothing
				$str_return .= 'NULL';
			}

			$str_return .= ')';

			return $str_return;
		}

		return $arr_loggers_user_can_view;
	}

	/**
	 * Quick stats above the log
	 * Uses filter "simple_history/history_page/before_gui" to output its contents
	 */
	public function output_quick_stats() {
		global $wpdb;

		// Get number of events today
		$logQuery = new Log_Query();
		$logResults = $logQuery->query(
			array(
				'posts_per_page' => 1,
				'date_from' => strtotime( 'today' ),
			)
		);

		$total_row_count = (int) $logResults['total_row_count'];

		// Get sql query for where to read only loggers current user is allowed to read/view
		$sql_loggers_in = $this->get_loggers_that_user_can_read( get_current_user_id(), 'sql' );

		// Get number of users today, i.e. events with wp_user as initiator
		$sql_users_today = sprintf(
			'
            SELECT
                DISTINCT(c.value) AS user_id
                FROM %3$s AS h
            INNER JOIN %4$s AS c
            ON c.history_id = h.id AND c.key = "_user_id"
            WHERE
                initiator = "wp_user"
                AND logger IN %1$s
                AND date > "%2$s"
            ',
			$sql_loggers_in,
			gmdate( 'Y-m-d H:i', strtotime( 'today' ) ),
			$wpdb->prefix . self::DBTABLE,
			$wpdb->prefix . self::DBTABLE_CONTEXTS
		);

		$cache_key = 'quick_stats_users_today_' . md5( serialize( $sql_loggers_in ) );
		$cache_group = 'simple-history-' . Helpers::get_cache_incrementor();
		$results_users_today = wp_cache_get( $cache_key, $cache_group );

		if ( false === $results_users_today ) {
			$results_users_today = $wpdb->get_results( $sql_users_today ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			wp_cache_set( $cache_key, $results_users_today, $cache_group );
		}

		$count_users_today = is_countable( $results_users_today ) ? count( $results_users_today ) : 0;

		// Get number of other sources (not wp_user).
		$sql_other_sources_where = sprintf(
			'
                initiator <> "wp_user"
                AND logger IN %1$s
                AND date > "%2$s"
            ',
			$sql_loggers_in,
			gmdate( 'Y-m-d H:i', strtotime( 'today' ) )
		);

		$sql_other_sources_where = apply_filters( 'simple_history/quick_stats_where', $sql_other_sources_where );

		$sql_other_sources = sprintf(
			'
            SELECT
                DISTINCT(h.initiator) AS initiator
            FROM %3$s AS h
            WHERE
                %5$s
            ',
			$sql_loggers_in,
			gmdate( 'Y-m-d H:i', strtotime( 'today' ) ),
			$wpdb->prefix . self::DBTABLE,
			$wpdb->prefix . self::DBTABLE_CONTEXTS,
			$sql_other_sources_where // 5
		);

		$cache_key = 'quick_stats_results_other_sources_today_' . md5( serialize( $sql_other_sources ) );
		$results_other_sources_today = wp_cache_get( $cache_key, $cache_group );

		if ( false === $results_other_sources_today ) {
			$results_other_sources_today = $wpdb->get_results( $sql_other_sources ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			wp_cache_set( $cache_key, $results_other_sources_today, $cache_group );
		}

		$count_other_sources = is_countable( $results_other_sources_today ) ? count( $results_other_sources_today ) : 0;
		?>
		<div class="SimpleHistoryQuickStats">
			<p>
				<?php
				$msg_tmpl = '';

				// No results today at all
				if ( $total_row_count == 0 ) {
					$msg_tmpl = __( 'No events today so far.', 'simple-history' );
				} else {
					/*
					Type of results
					x1 event today from 1 user.
					x1 event today from 1 source.
					3 events today from 1 user.
					x2 events today from 2 users.
					x2 events today from 1 user and 1 other source.
					x3 events today from 2 users and 1 other source.
					x3 events today from 1 user and 2 other sources.
					x4 events today from 2 users and 2 other sources.
					 */

					// A single event existed and was from a user
					// 1 event today from 1 user.
					if ( $total_row_count == 1 && $count_users_today == 1 ) {
						$msg_tmpl .= __( 'One event today from one user.', 'simple-history' );
					}

					// A single event existed and was from another source
					// 1 event today from 1 source.
					if ( $total_row_count == 1 && ! $count_users_today ) {
						$msg_tmpl .= __( 'One event today from one source.', 'simple-history' );
					}

					// Multiple events from a single user
					// 3 events today from one user.
					if ( $total_row_count > 1 && $count_users_today == 1 && ! $count_other_sources ) {
						// translators: 1 is number of events.
						$msg_tmpl .= __( '%1$d events today from one user.', 'simple-history' );
					}

					// Multiple events from only users
					// 2 events today from 2 users.
					if ( $total_row_count > 1 && $count_users_today == $total_row_count ) {
						// translators: 1 is number of events. 2 is number of users.
						$msg_tmpl .= __( '%1$d events today from %2$d users.', 'simple-history' );
					}

					// Multiple events from 1 single user and 1 single other source
					// 2 events today from 1 user and 1 other source.
					if ( $total_row_count && 1 == $count_users_today && 1 == $count_other_sources ) {
						// translators: 1 is number of events.
						$msg_tmpl .= __( '%1$d events today from one user and one other source.', 'simple-history' );
					}

					// Multiple events from multiple users but from only 1 single other source
					// 3 events today from 2 users and 1 other source.
					if ( $total_row_count > 1 && $count_users_today > 1 && $count_other_sources == 1 ) {
						// translators: 1 is number of events.
						$msg_tmpl .= __( '%1$d events today from one user and one other source.', 'simple-history' );
					}

					// Multiple events from 1 user but from multiple  other source
					// 3 events today from 1 user and 2 other sources.
					if ( $total_row_count > 1 && 1 == $count_users_today && $count_other_sources > 1 ) {
						// translators: 1 is number of events.
						$msg_tmpl .= __( '%1$d events today from one user and %3$d other sources.', 'simple-history' );
					}

					// Multiple events from multiple user and from multiple other sources
					// 4 events today from 2 users and 2 other sources.
					if ( $total_row_count > 1 && $count_users_today > 1 && $count_other_sources > 1 ) {
						// translators: 1 is number of events, 2 is number of users, 3 is number of other sources.
						$msg_tmpl .= __( '%1$s events today from %2$d users and %3$d other sources.', 'simple-history' );
					}
				} // End if().

				// Show stats if we have something to output.
				if ( $msg_tmpl !== '' ) {
					printf(
						esc_html( $msg_tmpl ),
						(int) $logResults['total_row_count'], // 1
						esc_html( $count_users_today ), // 2
						esc_html( $count_other_sources ) // 3
					);
				}
				?>
			</p>
		</div>
		<?php
	}

	// Number of rows the last n days.
	public function get_num_events_last_n_days( $period_days = 28 ) {
		$transient_key = 'sh_' . md5( __METHOD__ . $period_days . '_2' );

		$count = get_transient( $transient_key );

		if ( false === $count ) {
			global $wpdb;

			$sqlStringLoggersUserCanRead = $this->get_loggers_that_user_can_read( null, 'sql' );

			$sql = sprintf(
				'
                    SELECT count(*)
                    FROM %1$s
                    WHERE UNIX_TIMESTAMP(date) >= %2$d
                    AND logger IN %3$s
                ',
				$wpdb->prefix . self::DBTABLE,
				strtotime( "-$period_days days" ),
				$sqlStringLoggersUserCanRead
			);

			$count = $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

			set_transient( $transient_key, $count, HOUR_IN_SECONDS );
		}

		return $count;
	}

	/**
	 * Get number of events per day the last n days.
	 * @param int $period_days Number of days to get events for.
	 * @return array Array with date as key and number of events as value.
	 */
	public function get_num_events_per_day_last_n_days( $period_days = 28 ) {
		$transient_key = 'sh_' . md5( __METHOD__ . $period_days . '_2' );
		$dates = get_transient( $transient_key );

		if ( false === $dates ) {
			global $wpdb;

			$sqlStringLoggersUserCanRead = $this->get_loggers_that_user_can_read( null, 'sql' );

			$sql = sprintf(
				'
                    SELECT
                        date_format(date, "%%Y-%%m-%%d") AS yearDate,
                        count(date) AS count
                    FROM
                        %1$s
                    WHERE
                        UNIX_TIMESTAMP(date) >= %2$d
                        AND logger IN (%3$d)
                    GROUP BY yearDate
                    ORDER BY yearDate ASC
                ',
				$wpdb->prefix . self::DBTABLE,
				strtotime( "-$period_days days" ),
				$sqlStringLoggersUserCanRead
			);

			$dates = $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

			set_transient( $transient_key, $dates, HOUR_IN_SECONDS );
		}

		return $dates;
	}

	/**
	 * Get number of unique events the last n days.
	 *
	 * @param int $days
	 * @return int Number of days.
	 */
	public function get_unique_events_for_days( $days = 7 ) {
		global $wpdb;
		$days = (int) $days;
		$table_name = $wpdb->prefix . self::DBTABLE;
		$cache_key = 'sh_' . md5( __METHOD__ . $days );
		$numEvents = get_transient( $cache_key );

		if ( false == $numEvents ) {
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$sql = $wpdb->prepare(
				"
                SELECT count( DISTINCT occasionsID )
                FROM $table_name
                WHERE date >= DATE_ADD(CURDATE(), INTERVAL -%d DAY)
            	",
				$days
			);
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

			$numEvents = $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

			set_transient( $cache_key, $numEvents, HOUR_IN_SECONDS );
		}

		return $numEvents;
	}

	/**
	 * Get the name of the Simple History database table.
	 *
	 * @return string
	 */
	public function get_events_table_name() {
		return $this::$dbtable;
	}

	/**
	 * Get the name of the Simple History contexts database table.
	 *
	 * @return string
	 */
	public function get_contexts_table_name() {
		return $this::$dbtable_contexts;
	}

	/**
	 * Call new method when calling old/deprecated method names.
	 *
	 * @param string $name
	 * @param array $arguments
	 * @return mixed
	 */
	public function __call( $name, $arguments ) {
		// Convert method to snake_case
		// and check if that method exists,
		// and if it does then call it.
		// For example 'getLogRowHeaderOutput' will be converted to 'get_log_row_header_output'
		// and since that version exists it will be called.
		$camel_cased_method_name = Helpers::camel_case_to_snake_case( $name );
		if ( method_exists( $this, $camel_cased_method_name ) ) {
			return call_user_func_array( array( $this, $camel_cased_method_name ), $arguments );
		}

		$methods_mapping = array(
			'registerSettingsTab' => 'register_settings_tab',
			'get_avatar' => 'get_avatar',
		);

		// Bail if method name is nothing to act on.
		if ( ! isset( $methods_mapping[ $name ] ) ) {
			return false;
		}

		$method_name_to_call = $methods_mapping[ $name ];

		// Special cases, for example get_avatar that is moved to Helpers class.
		if ( $method_name_to_call === 'get_avatar' ) {
			return Helpers::get_avatar( ...$arguments );
		}

		return call_user_func_array( array( $this, $method_name_to_call ), $arguments );

	}
}
