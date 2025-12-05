<?php

namespace Simple_History;

use Simple_History\Loggers;
use Simple_History\Loggers\Logger;
use Simple_History\Dropins\Dropin;
use Simple_History\Event_Details\Event_Details_Container;
use Simple_History\Helpers;
use Simple_History\Services;
use Simple_History\Services\Service;
use Simple_History\Event_Details\Event_Details_Simple_Container;
use Simple_History\Event_Details\Event_Details_Container_Interface;
use Simple_History\Event_Details\Event_Details_Group;
use Simple_History\Services\Setup_Settings_Page;
use Simple_History\Date_Helper;

/**
 * Main class for Simple History.
 *
 * This is used to init the plugin.
 *
 * @method bool registerSettingsTab(array $arr_tab_settings) Register a new tab in the settings.
 * @method string get_avatar(...$args) Get avatar image. Moved to Helpers class.
 */
class Simple_History {
	public const NAME = 'Simple History';

	/**
	 * For singleton.
	 *
	 * @var Simple_History
	 * @see get_instance()
	 */
	private static ?\Simple_History\Simple_History $instance = null;

	/** @var Array with external logger classnames to load. */
	private array $external_loggers = [];

	/** @var Array with external dropins to load. */
	private array $external_dropins = [];

	/** @var Array with external services to load. */
	private array $external_services = [];

	/** @var array Array with all instantiated loggers. */
	private array $instantiated_loggers = [];

	/** @var Array with all instantiated dropins. */
	private array $instantiated_dropins = [];

	/** @var Array with instantiated setup class. */
	private array $instantiated_services = [];

	/** @var array<int,mixed>  Registered settings tabs. */
	private array $arr_settings_tabs = [];

	/** @var \Simple_History\Channels\Channels_Manager|null The integrations manager instance. */
	public $integrations_manager = null;

	public const DBTABLE          = 'simple_history';
	public const DBTABLE_CONTEXTS = 'simple_history_contexts';

	/** @var string $dbtable Full database name with prefix, i.e. wp_simple_history */
	public static $dbtable;

	/** @var string $dbtable Full database name with prefix for contexts, i.e. wp_simple_history_contexts */
	public static $dbtable_contexts;

	/** @var string $plugin_basename */
	public $plugin_basename = SIMPLE_HISTORY_BASENAME;

	/** Slug for the admin menu main page. */
	public const MENU_PAGE_SLUG = 'simple_history_admin_menu_page';

	/** Slug for the view events subpage_default page */
	public const VIEW_EVENTS_PAGE_SLUG = 'simple_history_view_events_page';

	/**
	 * Settings page menu slug used in WordPress admin.
	 *
	 * This constant defines the unique identifier (slug) used for the Simple History
	 * settings page in the WordPress admin menu.
	 *
	 * @var string
	 */
	public const SETTINGS_MENU_PAGE_SLUG = 'simple_history_settings_page';

	/** Slug for the settings menu. Is this the slug for options groups? */
	public const SETTINGS_MENU_SLUG = 'simple_history_settings_menu_slug';

	/** Slug for the settings menu */
	public const SETTINGS_GENERAL_OPTION_GROUP = 'simple_history_settings_group';

	/** ID for the general settings section */
	public const SETTINGS_SECTION_GENERAL_ID = 'simple_history_settings_section_general';

	/**
	 * Init Simple History.
	 */
	public function __construct() {
		$this->init();
	}

	/**
	 * Called on class construct.
	 *
	 * @since 2.5.2
	 */
	public function init() {
		/**
		 * Fires before Simple History does it's init stuff.
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
		 * Fires after Simple History has done it's init stuff.
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
		$services      = [];
		$services_dir  = SIMPLE_HISTORY_PATH . 'inc/services';
		$service_files = glob( $services_dir . '/*.php' );

		foreach ( $service_files as $file ) {
			// Skip service main class that other classes depend on.
			if ( basename( $file ) === 'class-service.php' ) {
				continue;
			}

			// Skip non-class files.
			if ( strpos( basename( $file ), 'class-' ) !== 0 ) {
				continue;
			}

			// Convert filename to class name.
			// e.g. class-admin-pages.php -> Admin_Pages.
			$class_name = str_replace( 'class-', '', basename( $file, '.php' ) );
			$class_name = str_replace( '-', '_', $class_name );
			$class_name = ucwords( $class_name, '_' );

			// Add full namespace.
			$class_name = "Simple_History\\Services\\{$class_name}";

			$services[] = $class_name;
		}

		/**
		 * Filter the array with class names of core services.
		 *
		 * @since 4.0
		 *
		 * @param array $services Array with class names.
		 */
		$services = apply_filters( 'simple_history/core_services', $services );

		return $services;
	}

	/**
	 * Load services that are required for Simple History to work.
	 */
	private function load_services() {
		$services_to_load = $this->get_services();

		/**
		 * Fires after the list of services to load are populated.
		 * Can be used to register custom services.
		 *
		 * @since 4.0
		 *
		 * @param Simple_History $instance Simple History instance.
		 */
		do_action( 'simple_history/add_custom_service', $this );

		$services_to_load = array_merge( $services_to_load, $this->get_external_services() );

		/**
		 * Filter the array with service classnames to instantiate.
		 *
		 * @since 4.0
		 *
		 * @param array $services_to_load Array with service class names.
		 */
		$services_to_load = apply_filters( 'simple_history/services_to_load', $services_to_load );

		foreach ( $services_to_load as $service_classname ) {
			$this->load_service( $service_classname );
		}

		/**
		 * Fires after all services are loaded.
		 *
		 * @since 4.0
		 *
		 * @param Simple_History $instance Simple History instance.
		 */
		do_action( 'simple_history/services/loaded', $this );
	}

	/**
	 * Load a service class.
	 *
	 * @param string $service_classname Class name of service to load.
	 */
	private function load_service( $service_classname ) {
		if ( ! class_exists( $service_classname ) ) {
			_doing_it_wrong(
				__METHOD__,
				sprintf(
					esc_html(
						// translators: 1: service class name.
						__( 'A service was not found. Classname was "%1$s".', 'simple-history' )
					),
					esc_html( $service_classname ),
				),
				'4.0'
			);
			return;
		}

		// Verify that service extends base Service class.
		if ( ! is_subclass_of( $service_classname, Service::class ) ) {
			_doing_it_wrong(
				__METHOD__,
				sprintf(
					esc_html(
						// translators: 1: service class name.
						__( 'A service must extend the Service base class. Classname was "%1$s".', 'simple-history' )
					),
					esc_html( $service_classname ),
				),
				'4.0'
			);
			return;
		}

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
		if ( Helpers::is_on_our_own_pages() ) {
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
		if ( Helpers::is_on_our_own_pages() ) {
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

		$this::$dbtable          = $wpdb->prefix . self::DBTABLE;
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
	 * @deprecated 4.8 Use Helpers::get_view_history_capability().
	 * @return string capability
	 */
	public function get_view_history_capability() {
		return Helpers::get_view_history_capability();
	}

	/**
	 * Return capability required to view settings.
	 * Default capability is "manage_options",
	 * but can be modified using filter.
	 *
	 * @since 2.1.5
	 * @deprecated 4.8 Use Helpers::get_view_settings_capability().
	 * @return string capability
	 */
	public function get_view_settings_capability() {
		_deprecated_function( __METHOD__, '4.8', 'Helpers::get_view_settings_capability()' );
		return Helpers::get_view_settings_capability();
	}

	/**
	 * Check if the current user can clear the log.
	 *
	 * @since 2.19
	 * @deprecated 4.8 Use Helpers::user_can_clear_log().
	 * @return bool
	 */
	public function user_can_clear_log() {
		_deprecated_function( __METHOD__, '4.8', 'Helpers::user_can_clear_log()' );
		return Helpers::user_can_clear_log();
	}

	/**
	 * Register an external logger so Simple History knows about it.
	 * Does not load the logger, so file with logger class must be loaded already.
	 *
	 * See example-logger.php for an example on how to use this.
	 *
	 * @since 2.1
	 * @param string $loggerClassName Class name of logger to register.
	 */
	public function register_logger( $loggerClassName ) {
		$this->external_loggers[] = $loggerClassName;
	}

	/**
	 * Register a PLUS plugin that has support for licences.
	 *
	 * @param string $plugin_id Id of plugin, eg basenamed path + index file: "simple-history-plus-woocommerce/index.php".
	 * @param string $plugin_slug Slug of plugin, eg "simple-history-plus-woocommerce".
	 * @param string $version Current version of plugin, eg "1.0.0".
	 * @param string $plugin_name Name of plugin, eg "Simple History Plus WooCommerce".
	 * @param int    $product_id ID of product that this plugin is for.
	 * @return bool True if plugin was registered, false if not.
	 */
	public function register_plugin_with_license( $plugin_id, $plugin_slug, $version, $plugin_name, $product_id ) {
		/** @var Services\AddOns_Licences|null $licences_service */
		$licences_service = $this->get_service( Services\AddOns_Licences::class );

		if ( is_null( $licences_service ) ) {
			return false;
		}

		$licences_service->register_plugin_for_license( $plugin_id, $plugin_slug, $version, $plugin_name, $product_id );

		return true;
	}

	/**
	 * Get an instantiated service by its class name.
	 *
	 * @param string $service_classname Full class name of service to get. Example: AddOns_Licences::class.
	 * @return Service|null Found service or null if no service found.
	 */
	public function get_service( $service_classname ) {
		foreach ( $this->instantiated_services as $service ) {
			if ( get_class( $service ) === $service_classname ) {
				return $service;
			}
		}

		return null;
	}

	/**
	 * Register an external dropin so Simple History knows about it.
	 * Does not load the dropin, so file with dropin class must be loaded already.
	 *
	 * See example-dropin.php for an example on how to use this.
	 *
	 * @since 2.1
	 * @param string $dropinClassName Class name of dropin to register.
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
			Loggers\Notes_Logger::class,
			Loggers\Core_Updates_Logger::class,
			Loggers\Core_Files_Logger::class,
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
			Loggers\Custom_Entry_Logger::class,
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
		$dropins      = [];
		$dropins_dir  = SIMPLE_HISTORY_PATH . 'dropins';
		$dropin_files = glob( $dropins_dir . '/*.php' );

		foreach ( $dropin_files as $file ) {
			// Skip dropin main class that other classes depend on.
			if ( basename( $file ) === 'class-dropin.php' ) {
				continue;
			}

			// Skip non-class files.
			if ( strpos( basename( $file ), 'class-' ) !== 0 ) {
				continue;
			}

			// Convert filename to class name.
			// e.g. class-quick-stats.php -> Quick_Stats.
			$class_name = str_replace( 'class-', '', basename( $file, '.php' ) );
			$class_name = str_replace( '-', '_', $class_name );
			$class_name = str_replace( 'dropin', 'Dropin', $class_name );
			$class_name = ucwords( $class_name, '_' );

			// Add full namespace.
			$class_name = "Simple_History\\Dropins\\{$class_name}";

			$dropins[] = $class_name;
		}

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
	 * Gets the pager size for the history page,
	 * i.e. the number of items to show on each page in the history
	 *
	 * @deprecated 4.8 Use Helpers::get_pager_size().
	 * @return int
	 */
	public function get_pager_size() {
		_deprecated_function( __METHOD__, '4.8', 'Helpers::get_pager_size()' );
		return Helpers::get_pager_size();
	}

	/**
	 * Gets the pager size for the dashboard widget,
	 * i.e. the number of items to show on each page in the history
	 *
	 * @since 2.12
	 * @deprecated 4.8 Use Helpers::get_pager_size_dashboard().
	 * @return int
	 */
	public function get_pager_size_dashboard() {
		_deprecated_function( __METHOD__, '4.8', 'Helpers::get_pager_size_dashboard()' );
		return Helpers::get_pager_size_dashboard();
	}

	/**
	 * Check if the current page is any of the pages that belong
	 * to Simple History.
	 *
	 * @deprecated 4.8 Use Helpers::is_on_our_own_pages().
	 * @return bool
	 */
	public function is_on_our_own_pages() {
		_deprecated_function( __METHOD__, '4.8', 'Helpers::is_on_our_own_pages()' );
		return Helpers::is_on_our_own_pages();
	}

	/**
	 * Check if the database has any data, i.e. at least 1 row.
	 *
	 * @since 2.1.6
	 * @deprecated 4.8 Use Helpers::does_database_have_data().
	 * @return bool True if database is not empty, false if database is empty = contains no data
	 */
	public function does_database_have_data() {
		_deprecated_function( __METHOD__, '4.8', 'Helpers::does_database_have_data()' );
		return Helpers::db_has_data();
	}

	/**
	 * Register a settings tab.
	 *
	 * @deprecated 5.7.0 Use Menu_Page class instead. See Message_Control_Module or Failed_Login_Attempts_Settings_Module for examples.
	 * @param array $arr_tab_settings {
	 *     An array of default site sign-up variables.
	 *
	 *     @type string   $slug   Unique slug of settings tab.
	 *     @type string   $name Human friendly name of the tab, shown on the settings page.
	 *     @type int      $order Order of the tab, where higher number means earlier output,
	 *     @type callable $function Function that will show the settings tab output.
	 *     @type string   $parent_slug Slug of parent tab, if this is a sub tab.
	 *     @type string   $icon Icon to use for tab.
	 * }
	 */
	public function register_settings_tab( $arr_tab_settings ) {
		_deprecated_function(
			__METHOD__,
			'5.7.0',
			'Menu_Page class. See Message_Control_Module or Failed_Login_Attempts_Settings_Module for examples.'
		);

		// This is called early from add-ons, while the new menu manager expects
		// registration to be called on menu_manager hook.
		// Also we want to call it after Simple History core has done its init stuff.
		add_action(
			'admin_menu',
			function () use ( $arr_tab_settings ) {
				// Create new Menu_Page instance using method chaining pattern.
				$menu_page = ( new Menu_Page() )
				->set_page_title( $arr_tab_settings['name'] )
				->set_menu_title( $arr_tab_settings['name'] )
				->set_menu_slug( $arr_tab_settings['slug'] )
				->set_callback( $arr_tab_settings['function'] )
				->set_order( $arr_tab_settings['order'] ?? 10 );

				// Set icon if provided.
				if ( ! empty( $arr_tab_settings['icon'] ) ) {
					$menu_page->set_icon( $arr_tab_settings['icon'] );
				}

				// Set parent.
				$parent_slug = $arr_tab_settings['parent_slug'] ?? null;

				if ( $parent_slug === null || $parent_slug === 'settings' ) {
					// In premium and extended settings parent was always "settings".
					$parent_slug = Setup_Settings_Page::SETTINGS_GENERAL_SUBTAB_SLUG;
				}

				$menu_page->set_parent( $parent_slug );

				$menu_page->add();
			},
			20
		);
	}

	/**
	 * Get the registered settings tabs.
	 *
	 * @deprecated 5.7.0 Use Menu_Page class instead. See Message_Control_Module or Failed_Login_Attempts_Settings_Module for examples.
	 *
	 * The tabs are ordered by the order key, where higher number means earlier output,
	 * i.e. the tab is outputted more to the left in the settings page.
	 *
	 * Tabs with no order is outputted last.
	 *
	 * @param string $type Type of tabs to get. Can be "top" or "sub".
	 * @return array
	 */
	public function get_settings_tabs( $type = 'top' ) {
		// Sort by order, where higher number means earlier output.
		usort(
			$this->arr_settings_tabs,
			function ( $a, $b ) {
				$a_order = $a['order'] ?? 0;
				$b_order = $b['order'] ?? 0;
				return $b_order <=> $a_order;
			}
		);

		// Filter out tabs that are not of the type we want.
		$settings_tabs_of_selected_type = array_filter(
			$this->arr_settings_tabs,
			function ( $tab ) use ( $type ) {
				if ( $type === 'top' ) {
					return empty( $tab['parent_slug'] );
				} elseif ( $type === 'sub' ) {
					return ! empty( $tab['parent_slug'] );
				}
				return false;
			}
		);

		// Re-index.
		$settings_tabs_of_selected_type = array_values( $settings_tabs_of_selected_type );

		return $settings_tabs_of_selected_type;
	}

	/**
	 * Set settings tabs.
	 *
	 * @param array $arr_settings_tabs Array with settings tabs.
	 * @return void
	 */
	public function set_settings_tabs( $arr_settings_tabs ) {
		$this->arr_settings_tabs = $arr_settings_tabs;
	}

	/**
	 * Get setting if plugin should be visible on dashboard.
	 * Defaults to true
	 *
	 * @deprecated 4.8 Use Helpers::setting_show_on_dashboard().
	 * @return bool
	 */
	public function setting_show_on_dashboard() {
		_deprecated_function( __METHOD__, '4.8', 'Helpers::setting_show_on_dashboard()' );
		return Helpers::setting_show_on_dashboard();
	}

	/**
	 * Should simple history be shown as a page
	 * Defaults to true
	 *
	 * @deprecated 4.8 Use Helpers::setting_show_as_page().
	 * @return bool
	 */
	public function setting_show_as_page() {
		_deprecated_function( __METHOD__, '4.8', 'Helpers::setting_show_as_page()' );
		return Helpers::setting_show_as_page();
	}

	/**
	 * How old log entries are allowed to be.
	 * 0 = don't delete old entries.
	 *
	 * @deprecated 4.8 Use Helpers::get_clear_history_interval().
	 * @return int Number of days.
	 */
	public function get_clear_history_interval() {
		_deprecated_function( __METHOD__, '4.8', 'Helpers::get_clear_history_interval()' );
		return Helpers::get_clear_history_interval();
	}

	/**
	 * Removes all items from the log.
	 *
	 * @deprecated 4.8 Use Helpers::clear_log().
	 * @return int Number of rows removed.
	 */
	public function clear_log() {
		_deprecated_function( __METHOD__, '4.8', 'Helpers::clear_log()' );
		return Helpers::clear_log();
	}

	/**
	 * Return plain text output for a log row
	 * Uses the get_log_row_plain_text_output of the logger that logged the row
	 * with fallback to SimpleLogger if logger is not available.
	 *
	 * @param object $row Log row object.
	 * @return string
	 */
	public function get_log_row_plain_text_output( $row ) {
		$row_logger_slug = $row->logger;
		$row->context    = isset( $row->context ) && is_array( $row->context ) ? $row->context : array();

		if ( ! isset( $row->context['_message_key'] ) ) {
			$row->context['_message_key'] = null;
		}

		$logger = $this->get_instantiated_logger_by_slug( $row_logger_slug );

		// Fallback to SimpleLogger if no logger exists for row.
		if ( $logger === false ) {
			$logger = $this->get_instantiated_logger_by_slug( 'Simple_Logger' );
		}

		// Bail if no logger found.
		if ( $logger === false ) {
			return '';
		}

		/**
		 * Filter the plain text output for a log row.
		 *
		 * @since 4.6.0
		 *
		 * @param string $output Plain text output for a log row.
		 * @param object $row Log row object.
		 * @param Logger $logger Logger instance.
		 */
		$output = apply_filters(
			'simple_history/get_log_row_plain_text_output/output',
			$logger->get_log_row_plain_text_output( $row ),
			$row,
			$logger
		);

		return $output;
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
	 * @param object $row Log row object.
	 * @return string
	 */
	public function get_log_row_header_output( $row ) {
		$row_logger   = $row->logger;
		$row->context = isset( $row->context ) && is_array( $row->context ) ? $row->context : array();

		// Fallback to SimpleLogger if no logger exists for row.
		if ( ! isset( $this->instantiated_loggers[ $row_logger ] ) ) {
			$row_logger = 'SimpleLogger';
		}

		$logger = $this->instantiated_loggers[ $row_logger ]['instance'];

		return $logger->get_log_row_header_output( $row );
	}

	/**
	 *
	 *
	 * @param object $row Log row object.
	 * @return string
	 */
	public function get_log_row_sender_image_output( $row ) {
		/** @var Loggers\Logger $row_logger */
		$row_logger   = $row->logger;
		$row->context = isset( $row->context ) && is_array( $row->context ) ? $row->context : array();

		// Fallback to SimpleLogger if no logger exists for row.
		if ( ! isset( $this->instantiated_loggers[ $row_logger ] ) ) {
			$row_logger = 'SimpleLogger';
		}

		$logger = $this->instantiated_loggers[ $row_logger ]['instance'];

		return $logger->get_log_row_sender_image_output( $row );
	}

	/**
	 * Return details output for a log row.
	 *
	 * @param object $row Log row object.
	 * @return string|Event_Details_Container_Interface|Event_Details_Group
	 */
	public function get_log_row_details_output( $row ) {
		$row_logger   = $row->logger;
		$row->context = isset( $row->context ) && is_array( $row->context ) ? $row->context : array();

		// Get logger for row.
		// Fallback to SimpleLogger if no logger exists for row.
		$logger = $this->get_instantiated_logger_by_slug( $row_logger );

		if ( $logger === false ) {
			$logger = $this->get_instantiated_logger_by_slug( 'Simple_Logger' );
		}

		// Bail if no logger found. Can happen when user has disabled all loggers via filter.
		if ( $logger === false ) {
			return new Event_Details_Simple_Container();
		}

		$logger_details_output = $logger->get_log_row_details_output( $row );

		if ( $logger_details_output instanceof Event_Details_Container_Interface ) {
			return $logger_details_output;
		} elseif ( $logger_details_output instanceof Event_Details_Group ) {
			/**
			 * Filter the event details group output for a logger
			 * that has returned an Event_Details_Group.
			 *
			 * @param Event_Details_Group $event_details_group
			 * @param object $row
			 * @return Event_Details_Group
			 */
			$logger_details_output = apply_filters( 'simple_history/log_row_details_output-' . $logger->get_slug(), $logger_details_output, $row );
			return new Event_Details_Container( $logger_details_output, $row->context );
		} else {
			return new Event_Details_Simple_Container( $logger_details_output );
		}
	}

	/**
	 * Returns the HTML output for a log row, to be used in the GUI/Activity Feed.
	 * This includes HTML for the header, the sender image, and the details.
	 *
	 * @param object $one_log_row LogQuery array with data from LogQuery.
	 * @param array  $args Array with arguments.
	 * @return string
	 */
	public function get_log_row_html_output( $one_log_row, $args ) {
		$defaults = array(
			'type' => 'overview', // or "single" to include more stuff (used in for example modal details window).
		);

		$args = wp_parse_args( $args, $defaults );

		$context     = $one_log_row->context ?? [];
		$message_key = $context['_message_key'] ?? null;

		$header_html       = $this->get_log_row_header_output( $one_log_row );
		$plain_text_html   = $this->get_log_row_plain_text_output( $one_log_row );
		$sender_image_html = $this->get_log_row_sender_image_output( $one_log_row );

		// Details = for example thumbnail of media.
		$details_html = trim( $this->get_log_row_details_output( $one_log_row ) );
		if ( $details_html !== '' ) {
			$details_html = sprintf( '<div class="SimpleHistoryLogitem__details">%1$s</div>', $details_html );
		}

		// subsequentOccasions = including the current one.
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$occasions_count = $one_log_row->subsequentOccasions - 1;
		$occasions_html  = '';

		// Add markup for occasions.
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

			// Div with information about add-ons that can limit the number of login attempts stored.
			// Only show for SimpleUserLogger and login failed events and if the add-on is not active.
			$logger = $one_log_row->logger;

			$is_simple_history_extended_settings_active = Helpers::is_extended_settings_add_on_active();
			$is_simple_history_premium_active           = Helpers::is_premium_add_on_active();

			if ( $logger === 'SimpleUserLogger' && in_array( $message_key, [ 'user_login_failed', 'user_unknown_login_failed' ], true ) ) {

				$ƒailed_login_attempts_settings_page_url = Helpers::get_settings_page_tab_url( 'failed-login-attempts' );

				if ( $is_simple_history_extended_settings_active ) {
					// Show link to extended settings settings page if extended settings plugin is active.
					$occasions_html .= '<div class="SimpleHistoryLogitem__occasionsAddOns">';
					$occasions_html .= '<p class="SimpleHistoryLogitem__occasionsAddOnsText">';
					$occasions_html .= '<a href="' . esc_url( $ƒailed_login_attempts_settings_page_url ) . '">';
					$occasions_html .= __( 'Configure failed login attempts', 'simple-history' );
					$occasions_html .= '</a>';
					$occasions_html .= '</p>';
					$occasions_html .= '</div>';
				} elseif ( $is_simple_history_premium_active ) {
					// Show link to premium settings page if extended settings plugin is active.
					$occasions_html .= '<div class="SimpleHistoryLogitem__occasionsAddOns">';
					$occasions_html .= '<p class="SimpleHistoryLogitem__occasionsAddOnsText">';
					$occasions_html .= '<a href="' . esc_url( $ƒailed_login_attempts_settings_page_url ) . '">';
					$occasions_html .= __( 'Configure failed login attempts', 'simple-history' );
					$occasions_html .= '</a>';
					$occasions_html .= '</p>';
					$occasions_html .= '</div>';
				} else {
					// Show link to add-on if extended settings plugin is not active.
					$occasions_html .= '<div class="SimpleHistoryLogitem__occasionsAddOns">';
					$occasions_html .= '<p class="SimpleHistoryLogitem__occasionsAddOnsText">';
					$occasions_html .= '<a href="' . esc_url( Helpers::get_tracking_url( 'https://simple-history.com/add-ons/extended-settings/', 'premium_occasions_loginlimit' ) ) . '" class="sh-ExternalLink" target="_blank">';
					$occasions_html .= __( 'Limit logged login attempts', 'simple-history' );
					$occasions_html .= '</a>';
					$occasions_html .= '</p>';
					$occasions_html .= '</div>';
				}
			}

			$occasions_html .= '</div>';
		}

		// Add data attributes to log row, so plugins can do stuff.
		$data_attrs  = '';
		$data_attrs .= sprintf( ' data-row-id="%1$d" ', $one_log_row->id );
		$data_attrs .= sprintf( ' data-occasions-count="%1$d" ', $occasions_count );

		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$data_attrs .= sprintf( ' data-occasions-id="%1$s" ', esc_attr( $one_log_row->occasionsID ) );

		// Add data attributes for remote address and other ip number headers.
		if ( isset( $one_log_row->context['_server_remote_addr'] ) ) {
			$data_attrs .= sprintf( ' data-ip-address="%1$s" ', esc_attr( $one_log_row->context['_server_remote_addr'] ) );
		}

		$arr_found_additional_ip_headers = Helpers::get_event_ip_number_headers( $one_log_row );

		if ( $arr_found_additional_ip_headers !== [] ) {
			$data_attrs .= ' data-ip-address-multiple="1" ';
		}

		// Add data attributes info for common things like logger, level, data, initiation.
		$data_attrs .= sprintf( ' data-logger="%1$s" ', esc_attr( $one_log_row->logger ) );
		$data_attrs .= sprintf( ' data-level="%1$s" ', esc_attr( $one_log_row->level ) );
		$data_attrs .= sprintf( ' data-date="%1$s" ', esc_attr( $one_log_row->date ) );
		$data_attrs .= sprintf( ' data-initiator="%1$s" ', esc_attr( $one_log_row->initiator ) );

		if ( isset( $one_log_row->context['_user_id'] ) ) {
			$data_attrs .= sprintf( ' data-initiator-user-id="%1$d" ', $one_log_row->context['_user_id'] );
		}

		// If type is single then include more details.
		// This is typically shown in the modal window when clicking the event date and time.
		$more_details_html = '';
		if ( $args['type'] === 'single' ) {
			$more_details_html = apply_filters(
				'simple_history/log_html_output_details_single/html_before_context_table',
				$more_details_html,
				$one_log_row
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

			$logRowKeysToShow = array_fill_keys( array_keys( (array) $one_log_row ), true );

			/**
			 * Filter what keys to show from one_log_row
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
			 *      function ( $logRowKeysToShow, $one_log_row ) {
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
			 * @param object $one_log_row log row to show details from
			 */
			$logRowKeysToShow = apply_filters(
				'simple_history/log_html_output_details_table/row_keys_to_show',
				$logRowKeysToShow,
				$one_log_row
			);

			// Hide some keys by default.
			unset(
				$logRowKeysToShow['occasionsID'],
				$logRowKeysToShow['subsequentOccasions'],
				$logRowKeysToShow['context'],
				$logRowKeysToShow['type']
			);

			foreach ( $one_log_row as $rowKey => $rowVal ) {
				// Only columns from one_log_row that exist in logRowKeysToShow will be outputted.
				if ( ! array_key_exists( $rowKey, $logRowKeysToShow ) || ! $logRowKeysToShow[ $rowKey ] ) {
					continue;
				}

				// skip arrays and objects and such.
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

			$logRowContextKeysToShow = array_fill_keys( array_keys( (array) $one_log_row->context ), true );

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
			 *      function ( $logRowContextKeysToShow, $one_log_row ) {
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
			 * @param object $one_log_row log row to show details from
			 */
			$logRowContextKeysToShow = apply_filters(
				'simple_history/log_html_output_details_table/context_keys_to_show',
				$logRowContextKeysToShow,
				$one_log_row
			);

			foreach ( $context as $contextKey => $contextVal ) {
				// Only columns from context that exist in logRowContextKeysToShow will be outputted.
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
				$one_log_row
			);

			$more_details_html = sprintf(
				'<div class="SimpleHistoryLogitem__moreDetails">%1$s</div>',
				$more_details_html
			);
		}

		// Classes to add to log item li element.
		$classes = array(
			'SimpleHistoryLogitem',
			"SimpleHistoryLogitem--loglevel-{$one_log_row->level}",
			"SimpleHistoryLogitem--logger-{$one_log_row->logger}",
		);

		if ( isset( $one_log_row->initiator ) && ! empty( $one_log_row->initiator ) ) {
			$classes[] = 'SimpleHistoryLogitem--initiator-' . $one_log_row->initiator;
		}

		if ( $arr_found_additional_ip_headers !== [] ) {
			$classes[] = 'SimpleHistoryLogitem--IPAddress-multiple';
		}

		// Always append the log level tag.
		$log_level_tag_html = sprintf(
			' <span class="SimpleHistoryLogitem--logleveltag SimpleHistoryLogitem--logleveltag-%1$s">%2$s</span>',
			$one_log_row->level,
			Log_Levels::get_log_level_translated( $one_log_row->level )
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

		// Generate the HTML output for a row.
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
			$one_log_row->level, // 5
			$details_html, // 6
			$one_log_row->logger, // 7
			$data_attrs, // 8 data attributes
			$more_details_html, // 9
			esc_attr( join( ' ', $classes ) ) // 10
		);

		// Get the main message row.
		// Should be as plain as possible, like plain text
		// but with links to for example users and posts
		// SimpleLoggerFormatter::getRowTextOutput($one_log_row);
		// Get detailed HTML-based output
		// May include images, lists, any cool stuff needed to view
		// SimpleLoggerFormatter::getRowHTMLOutput($one_log_row);.
		return trim( $output );
	}

	/**
	 * Get instantiated loggers.
	 *
	 * @return array<array<string,Logger>>
	 */
	public function get_instantiated_loggers() {
		return $this->instantiated_loggers;
	}

	/**
	 * Set instantiated loggers.
	 *
	 * @param array $instantiated_loggers Array with instantiated loggers.
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
	 * @param array $instantiated_dropins Array with instantiated dropins.
	 */
	public function set_instantiated_dropins( $instantiated_dropins ) {
		$this->instantiated_dropins = $instantiated_dropins;
	}

	/**
	 * Get instantiated dropin by slug.
	 * Returns the logger instance if found, or bool false if not found.
	 * @param string $slug Slug of dropin to get.
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
	 * @param string $slug Slug of logger to get.
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
	 * @param int|null $user_id Id of user to get loggers for. Defaults to current user id.
	 * @param string   $format format to return loggers in. array|sql|slugs. Default is "array".
	 * @return array<\Simple_History\Loggers\Simple_Logger>|string Array or SQL string with loggers that user can read.
	 */
	public function get_loggers_that_user_can_read( $user_id = null, $format = 'array' ) {
		$arr_loggers_user_can_view = [];

		if ( is_null( $user_id ) ) {
			$user_id = get_current_user_id();
		}

		$loggers = $this->get_instantiated_loggers();

		foreach ( $loggers as $one_logger ) {
			$logger_capability = $one_logger['instance']->get_capability();

			// phpcs:ignore WordPress.WP.Capabilities.Undetermined -- Capability from logger, filterable, defaults to 'read'.
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
			 * @param Logger $logger Logger instance.
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
		 * Filter loggers that user can read.
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

		// Sort loggers by slug to ensure consistent ordering for caching.
		usort(
			$arr_loggers_user_can_view,
			function ( $a, $b ) {
				return strcmp( $a['instance']->get_slug(), $b['instance']->get_slug() );
			}
		);

		// just return array with slugs in parenthesis suitable for sql-where.
		if ( 'sql' === $format ) {
			$str_return = '(';

			if ( count( $arr_loggers_user_can_view ) ) {
				foreach ( $arr_loggers_user_can_view as $one_logger ) {
					$str_return .= sprintf( '\'%1$s\', ', esc_sql( $one_logger['instance']->get_slug() ) );
				}

				$str_return = rtrim( $str_return, ' ,' );
			} else {
				// user was not allowed to read any loggers, return in (NULL) to return nothing.
				$str_return .= 'NULL';
			}

			$str_return .= ')';

			return $str_return;
		} elseif ( 'slugs' === $format ) {
			$logger_slugs = array_map(
				function ( $logger ) {
					return $logger['instance']->get_slug();
				},
				$arr_loggers_user_can_view
			);

			return $logger_slugs;
		}

		// Return array with loggers that user can read.
		return $arr_loggers_user_can_view;
	}

	/**
	 * Get number of events the last n days.
	 *
	 * @deprecated 4.8 Use Helpers::get_num_events_last_n_days().
	 * @param int $period_days Number of days to get events for.
	 * @return int
	 */
	public function get_num_events_last_n_days( $period_days = Date_Helper::DAYS_PER_MONTH ) {
		_deprecated_function( __METHOD__, '4.8', 'Helpers::get_num_events_last_n_days()' );
		return Helpers::get_num_events_last_n_days( $period_days );
	}

	/**
	 * Get number of events per day the last n days.
	 *
	 * @deprecated 4.8 Use Helpers::get_num_events_per_day_last_n_days().
	 * @param int $period_days Number of days to get events for.
	 * @return array Array with date as key and number of events as value.
	 */
	public function get_num_events_per_day_last_n_days( $period_days = Date_Helper::DAYS_PER_MONTH ) {
		_deprecated_function( __METHOD__, '4.8', 'Helpers::get_num_events_per_day_last_n_days()' );
		return Helpers::get_num_events_per_day_last_n_days( $period_days );
	}

	/**
	 * Get number of unique events the last n days.
	 *
	 * @deprecated 4.8 Use Helpers::get_num_unique_events_last_n_days().
	 * @param int $days Number of days to get events for.
	 * @return int Number of days.
	 */
	public function get_unique_events_for_days( $days = 7 ) {
		_deprecated_function( __METHOD__, '4.8', 'Helpers::get_unique_events_for_days()' );
		return Helpers::get_unique_events_for_days( $days );
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
	 * @param string $name Method name.
	 * @param array  $arguments Arguments to method.
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
			'get_avatar'          => 'get_avatar',
		);

		// Bail if method name is nothing to act on.
		if ( ! isset( $methods_mapping[ $name ] ) ) {
			_doing_it_wrong(
				__CLASS__ . '::' . esc_html( $name ),
				sprintf(
					'Call to undefined or deprecated method %s::%s(). This indicates a bug in the calling code.',
					__CLASS__,
					esc_html( $name )
				),
				'5.19.0'
			);

			return false;
		}

		$method_name_to_call = $methods_mapping[ $name ];

		// Special cases, for example get_avatar that is moved to Helpers class.
		if ( $method_name_to_call === 'get_avatar' ) {
			return Helpers::get_avatar( ...$arguments );
		}

		return call_user_func_array( array( $this, $method_name_to_call ), $arguments );
	}

	/**
	 * Get the menu manager class from the menu_service class instance.
	 *
	 * @return Menu_Manager Menu manager instance or null if menu service is not available.
	 */
	public function get_menu_manager() {
		/** @var Services\Menu_Service $menu_service */
		$menu_service = $this->get_service( Services\Menu_Service::class );

		return $menu_service->get_menu_manager();
	}

	/**
	 * Register an external service so Simple History knows about it.
	 * Does not load the service, so file with service class must be loaded already.
	 *
	 * @since 4.0
	 * @param string $serviceClassName Class name of service to register.
	 */
	public function register_service( $serviceClassName ) {
		$this->external_services[] = $serviceClassName;
	}

	/**
	 * Get array with classnames of all external services.
	 *
	 * @return array
	 */
	public function get_external_services() {
		return $this->external_services;
	}
}
