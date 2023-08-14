<?php

namespace Simple_History;

use Simple_History\Loggers;
use Simple_History\Loggers\Logger;
use Simple_History\Loggers\Simple_Logger;
use Simple_History\Dropins;
use Simple_History\Dropins\Dropin;
use Simple_History\Helpers;
use Simple_History\Services;
use Simple_History\Services\Service;

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

	/** Array with instantiated setup class. */
	private array $instantiated_services = [];

	/** @var array<int,mixed>  Registered settings tabs. */
	private array $arr_settings_tabs = [];

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

		$this->setup_db_variables();

		// Load services that are required for Simple History to work.
		$this->load_services();

		if ( is_admin() ) {
			$this->add_admin_actions();
		}

		/**
		 * Fires after Simple History has done it's init stuff
		 *
		 * @since 2.0
		 *
		 * @param Simple_History $instance Simple_History instance.
		 */
		do_action( 'simple_history/after_init', $this );
	}

	/**
	 * Return array with classnames core services classnames.
	 *
	 * @return array<string> Array with classnames.
	 */
	private function get_services() {
		return [
			Services\Language_Loader::class,
			Services\Setup_Database::class,
			Services\Scripts_And_Templates::class,
			Services\Admin_Pages::class,
			Services\Setup_Settings_Page::class,
			Services\Loggers_Loader::class,
			Services\Dropins_Loader::class,
			Services\Setup_Log_Filters::class,
			Services\Setup_Pause_Resume_Actions::class,
			Services\Setup_Purge_DB_Cron::class,
			Services\API::class,
			Services\Dashboard_Widget::class,
			Services\Network_Menu_Items::class,
			Services\Plugin_List_Link::class,
		];
	}

	/**
	 * Load services that are required for Simple History to work.
	 */
	private function load_services() {
		foreach ( $this->get_services() as $service_classname ) {
			$this->load_service( $service_classname );
		}
	}

	/**
	 * Load a service class.
	 */
	private function load_service( $service_classname ) {
		$service = new $service_classname( $this );
		$service->loaded();
		$this->instantiated_services[] = $service;
	}

	/**
	 * Get instantiated services.
	 *
	 * @return array<int,Service> Array with instantiated services.
	 */
	public function get_instantiated_services() {
		return $this->instantiated_services;
	}

	/**
	 * @since 2.5.2
	 */
	private function add_admin_actions() {
		add_action( 'admin_head', array( $this, 'on_admin_head' ) );
		add_action( 'admin_footer', array( $this, 'on_admin_footer' ) );
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
	 * Setup variables and things.
	 */
	private function setup_db_variables() {
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
			Dropins\Quick_Stats::class,
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

	// TODO: Is this used anywhere?
	public function filter_option_page_capability( $capability ) {
		return $capability;
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
				return $b_order <=> $a_order;
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
	 * @return array|string Array or SQL string with loggers that user can read.
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
