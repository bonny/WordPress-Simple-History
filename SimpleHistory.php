<?php

/**
 * Main class for Simple History
 */ 
class SimpleHistory {
	 
	/**
	 * Capability required to view the history log
	 */
	private $view_history_capability;
	 
	/**
	 * Capability required to view and edit the settings page
	 */
	private $view_settings_capability;

	/**
	 * Array with all instantiated loggers
	 */
	private $instantiatedLoggers;

	/**
	 * Array with all instantiated dropins
	 */
	private $instantiatedDropins;

	public $pluginBasename;

	const NAME = "Simple History";
	const VERSION = "2.0";
	const DBTABLE = "simple_history";

	/** Slug for the settings menu */
	const SETTINGS_MENU_SLUG = "simple_history_settings_menu_slug";

	/** ID for the general settings section */
	const SETTINGS_SECTION_GENERAL_ID = "simple_history_settings_section_general";

	function __construct() {

		/**
	     * Fires before Simple History does it's init stuff
	     *
	     * @since 2.0
	     *
	     * @param SimpleHistory $SimpleHistory This class.
	     */
		do_action( "simple_history/before_init", $this );

		$this->setupVariables();
		$this->loadLoggers();
		$this->loadDropins();

		add_action( 'init', array($this, 'load_plugin_textdomain') );

		add_action( 'admin_init', array($this, 'check_for_upgrade') );

		add_filter( 'plugin_action_links_simple-history/index.php', array($this, 'plugin_action_links'), 10, 4);

		add_action( 'admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));

		add_action( 'admin_menu', array($this, 'add_admin_pages') );
		add_action( 'admin_menu', array($this, 'add_settings') );

		add_action( 'wp_dashboard_setup', array($this, 'add_dashboard_widget') );

		add_action( 'wp_ajax_simple_history_ajax', array($this, 'ajax') );

		$this->add_types_for_translation();
		require_once ( dirname(__FILE__) . "/old-functions.php");
		require_once ( dirname(__FILE__) . "/old-stuff.php");
		require_once ( dirname(__FILE__) . "/simple-history-extender/simple-history-extender.php" );

		/**
	     * Fires after Simple History has done it's init stuff
	     *
	     * @since 2.0
	     *
	     * @param SimpleHistory $SimpleHistory This class.
	     */
		do_action( "simple_history/after_init", $this );

	}

	/**
	 * Load language files.
	 * Uses the method described here:
	 * http://geertdedeckere.be/article/loading-wordpress-language-files-the-right-way
	 * 
	 * @since 2.0
	 */
	public function load_plugin_textdomain() {

		$domain = 'simple-history';
		
		// The "plugin_locale" filter is also used in load_plugin_textdomain()
		$locale = apply_filters('plugin_locale', get_locale(), $domain);

		load_textdomain($domain, WP_LANG_DIR.'/simple-history/'.$domain.'-'.$locale.'.mo');
		load_plugin_textdomain($domain, FALSE, dirname( $this->plugin_basename ).'/languages/');

	}

	/**
	 * Setup variables and things
	 */
	public function setupVariables() {
	
		$this->view_history_capability = "edit_pages";
		$this->view_history_capability = apply_filters("simple_history_view_history_capability", $this->view_history_capability);
		$this->view_history_capability = apply_filters("simple_history/view_history_capability", $this->view_history_capability);

		$this->view_settings_capability = "manage_options";
		$this->view_settings_capability = apply_filters("simple_history_view_settings_capability", $this->view_settings_capability);
		$this->view_settings_capability = apply_filters("simple_history/view_settings_capability", $this->view_settings_capability);

		$this->plugin_basename = plugin_basename(__DIR__ . "/index.php");

	}

	/**
	 * Load built in loggers from all files in /loggers
	 * and instantiates them
	 */
	private function loadLoggers() {
		
		$loggersDir = __DIR__ . "/loggers/";

		/**
		 * Filter the directory to load loggers from
		 *
		 * @since 2.0
		 *
		 * @param string $loggersDir Full directory path
		 */
		$loggersDir = apply_filters("simple_history/loggers_dir", $loggersDir);

		$loggersFiles = glob( $loggersDir . "*.php");

		// SimpleLogger.php must be loaded first
		require_once($loggersDir . "SimpleLogger.php");

		/**
		 * Filter the array with absolute paths to files as returned by glob function.
		 * Each file will be loaded and will be assumed to be a logger with a classname
		 * the same as the filename.
		 *
		 * @since 2.0
		 *
		 * @param array $loggersFiles Array with filenames
		 */		
		$loggersFiles = apply_filters("simple_history/loggers_files", $loggersFiles);
		
		$arrLoggersToInstantiate = array();

		foreach ( $loggersFiles as $oneLoggerFile) {
		
			include_once($oneLoggerFile);

			$arrLoggersToInstantiate[] = basename($oneLoggerFile, ".php");
		
		}

		/**
		 * Filter the array with names of loggers to instantiate.
		 *
		 * @since 2.0
		 *
		 * @param array $arrLoggersToInstantiate Array with class names
		 */		
		$arrLoggersToInstantiate = apply_filters("simple_history/loggers_to_instantiate", $arrLoggersToInstantiate);

		// Instantiate each logger
		foreach ($arrLoggersToInstantiate as $oneLoggerName ) {
			
			$this->instantiatedLoggers[$oneLoggerName] = array(
				"name" => $oneLoggerName,
				"instance" => new $oneLoggerName($this)
			);
		}

	}

	/**
	 * Load built in dropins from all files in /dropins
	 * and instantiates them
	 */
	private function loadDropins() {
		
		$dropinsDir = __DIR__ . "/dropins/";

		/**
		 * Filter the directory to load loggers from
		 *
		 * @since 2.0
		 *
		 * @param string $dropinsDir Full directory path
		 */
		$dropinsDir = apply_filters("simple_history/dropins_dir", $dropinsDir);

		$dropinsFiles = glob( $dropinsDir . "*.php");

		/**
		 * Filter the array with absolute paths to files as returned by glob function.
		 * Each file will be loaded and will be assumed to be a dropin with a classname
		 * the same as the filename.
		 *
		 * @since 2.0
		 *
		 * @param array $dropinsFiles Array with filenames
		 */		
		$dropinsFiles = apply_filters("simple_history/dropins_files", $dropinsFiles);
		
		$arrDropinsToInstantiate = array();

		foreach ( $dropinsFiles as $oneDropinFile) {
		
			include_once($oneDropinFile);

			$arrDropinsToInstantiate[] = basename($oneDropinFile, ".php");
		
		}

		/**
		 * Filter the array with names of dropin to instantiate.
		 *
		 * @since 2.0
		 *
		 * @param array $arrDropinsToInstantiate Array with class names
		 */		
		$arrDropinsToInstantiate = apply_filters("simple_history/dropins_to_instantiate", $arrDropinsToInstantiate);

		// Instantiate each dropin
		foreach ($arrDropinsToInstantiate as $oneDropinName ) {
			
			$this->instantiatedDropins[$oneDropinName] = array(
				"name" => $oneDropinName,
				"instance" => new $oneDropinName($this)
			);
		}

	}

	/**
	 * Gets the pager size, 
	 * i.e. the number of items to show on each page in the history
	 *
	 * @return int
	 */
	function get_pager_size() {

		$pager_size = get_option("simple_history_pager_size", 5);
		return $pager_size;

	}
	
	/**
	 * Some post types etc are added as variables from the log, so to catch these for translation I just add them as dummy stuff here.
	 * There is probably a better way to do this, but this should work anyway
	 */
	function add_types_for_translation() {

		__("added", "simple-history");
		__("approved", "simple-history");
		__("unapproved", "simple-history");
		__("marked as spam", "simple-history");
		__("trashed", "simple-history");
		__("untrashed", "simple-history");
		__("created", "simple-history");
		__("deleted", "simple-history");
		__("updated", "simple-history");
		__("nav_menu_item", "simple-history");
		__("attachment", "simple-history");
		__("user", "simple-history");
		__("settings page", "simple-history");
		__("edited", "simple-history");
		__("comment", "simple-history");
		__("logged in", "simple-history");
		__("logged out", "simple-history");
		__("added", "simple-history");
		__("modified", "simple-history");
		__("upgraded it\'s database", "simple-history");
		__("plugin", "simple-history");

	}

	/**
	 * Show a link to our settings page on the Plugins -> Installed Plugins screen
	 */
	function plugin_action_links($actions, $b, $c, $d) {
		
		// Only add link if user has the right to view the settings page
		if ( ! current_user_can($this->view_settings_capability) ) {
			return $actions;
		}

		$settings_page_url = menu_page_url(SimpleHistory::SETTINGS_MENU_SLUG, 0);
		
		$actions[] = "<a href='$settings_page_url'>" . __("Settings", "simple-history") . "</a>";

		return $actions;
		
	}

	/**
	 * Maybe add a dashboard widget,
	 * requires current user to have view history capability
	 * and a setting to show dashboard to be set
	 */
	function add_dashboard_widget() {
		
		if ( $this->setting_show_on_dashboard() && current_user_can($this->view_history_capability) ) {
		
			wp_add_dashboard_widget("simple_history_dashboard_widget", __("History", 'simple-history'), array($this, "dashboard_widget_output"));
			
		}
	}

	/**
	 * Output html for the dashboard widget
	 */
	function dashboard_widget_output() {

		$this->purge_db();
		
		echo '<div class="wrap simple-history-wrap">';
		simple_history_print_nav();
		echo simple_history_print_history();
		echo simple_history_get_pagination();
		echo '</div>';

	}
	
	/**
	 * Enqueue styles and scripts for Simple History but only to our own pages.
	 *
	 * Only adds scripts to pages where the log is shown or the settings page.
	 */
	function enqueue_admin_scripts($hook) {
	
		if ( ($hook == "settings_page_" . SimpleHistory::SETTINGS_MENU_SLUG) || ($this->setting_show_on_dashboard() && $hook == "index.php") || ($this->setting_show_as_page() && $hook == "dashboard_page_simple_history_page")) {
	
			$plugin_url = plugin_dir_url(__FILE__);
			wp_enqueue_style( "simple_history_styles", $plugin_url . "styles.css", false, SimpleHistory::VERSION );	
			wp_enqueue_script("simple_history_script", $plugin_url . "scripts.js", array("jquery"), SimpleHistory::VERSION);

			wp_localize_script('simple_history_script', 'simple_history_script_vars', array(
				'settingsConfirmClearLog' => __("Remove all log items?", 'simple-history')
			));
		
		}

	}

	function filter_option_page_capability($capability) {
		return $capability;
	}
	
	/**
	 * Check if plugin version have changed, i.e. has been upgraded
	 * If upgrade is detected then maybe modify database and so on for that version
	 */
	function check_for_upgrade() {

		global $wpdb;

		$db_version = get_option("simple_history_db_version");
		$table_name = $wpdb->prefix . SimpleHistory::DBTABLE;

		// If no db_version is set then this 
		// is a version of Simple History < 0.4
		// Fix database not using UTF-8
		if ( false === $db_version ) {
			
			require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
			
			// We change the varchar size to add one num just to force update of encoding. dbdelta didn't see it otherwise.
			$sql = "CREATE TABLE " . $table_name . " (
			  id int(10) NOT NULL AUTO_INCREMENT,
			  date datetime NOT NULL,
			  action VARCHAR(256) NOT NULL COLLATE utf8_general_ci,
			  object_type VARCHAR(256) NOT NULL COLLATE utf8_general_ci,
			  object_subtype VARCHAR(256) NOT NULL COLLATE utf8_general_ci,
			  user_id int(10) NOT NULL,
			  object_id int(10) NOT NULL,
			  object_name VARCHAR(256) NOT NULL COLLATE utf8_general_ci,
			  action_description longtext,
			  PRIMARY KEY  (id)
			) CHARACTER SET=utf8;";

			// Upgrade db / fix utf for varchars
			dbDelta($sql);
			
			// Fix UTF-8 for table
			$sql = sprintf('alter table %1$s charset=utf8;', $table_name);
			$wpdb->query($sql);
			
			// Store this upgrade in ourself :)
			simple_history_add("action=" . 'upgraded it\'s database' . "&object_type=plugin&object_name=" . SimpleHistory::NAME);
			
			$db_version = 1;
			update_option("simple_history_db_version", $db_version);

		} // done pre db ver 1 things


		// If db version is 1 then upgrade to 2
		// Version 2 added the action_description column
		if ( 1 == intval($db_version) ) {

			// Add column for action description in non-translateable free text
			$sql = "ALTER TABLE {$table_name} ADD COLUMN action_description longtext";
			mysql_query($sql);

			simple_history_add("action=" . 'upgraded it\'s database' . "&object_type=plugin&object_name=" . SimpleHistory::NAME . "&description=Database version is now version 2");

			$db_version = 2;
			update_option("simple_history_db_version", $db_version);

		}

		// Check that all options we use are set to their defaults, if they miss value
		// Each option that is missing a value will make a sql call otherwise = unnecessary
		$arr_options = array(
			array(
				"name" => "sh_extender_modules",
				"default_value" => ""
			),
			array(
				"name" => "simple_history_show_as_page",
				"default_value" => 1	
			),
			array(
				"name" => "simple_history_show_on_dashboard",
				"default_value" => 1
			)
		);

		foreach ($arr_options as $one_option) {
			
			if ( false === ($option_value = get_option( $one_option["name"] ) ) ) {

				// Value is not set in db, so set it to a default
				update_option( $one_option["name"], $one_option["default_value"] );

			}
		}
		
	} // end check_for_upgrade
	
	/**
	 * Output HTML for the settings page
	 * Called from add_options_page
	 */		 
	function settings_page_output() {
		
		?>
		<div class="wrap">

			<form method="post" action="options.php">
			
				<h2><?php _e("Simple History Settings", "simple-history") ?></h2>
			
				<?php 
				// Prints out all settings sections added to a particular settings page
				do_settings_sections(SimpleHistory::SETTINGS_MENU_SLUG);
				?>

				<?php 
				// Output nonce, action, and option_page fields
				settings_fields("simple_history_settings_group");
				?>

				<?php submit_button(); ?>

			</form>

		</div>
		<?php
		
	}


	/**
	 * Content for section intro. Leave it be, even if empty.
	 * Called from add_sections_setting.
	 */
	function settings_section_output() {
		
	}


	/**
	 * Add pages (history page and settings page)
	 */
	function add_admin_pages() {
	
		// Add a history page as a sub-page below the Dashboard menu item
		if ($this->setting_show_as_page()) {
			
			add_dashboard_page(
					SimpleHistory::NAME, 
					_x("History", 'dashboard menu name', 'simple-history'),
					$this->view_history_capability, 
					"simple_history_page", 
					array($this, "history_page_output")
				);

		}

		// Add a settings page
		$show_settings_page = true;
		$show_settings_page = apply_filters("simple_history_show_settings_page", $show_settings_page);
		$show_settings_page = apply_filters("simple_history/show_settings_page", $show_settings_page);
		if ($show_settings_page) {

			add_options_page(
					__('Simple History Settings', "simple-history"), 
					SimpleHistory::NAME, 
					$this->view_settings_capability, 
					SimpleHistory::SETTINGS_MENU_SLUG, 
					array($this, 'settings_page_output')
				);

		}

	}

	/*
	 * Add setting sections and settings for the settings page
	 * Also maybe save some settings before outputing them
	 */
	function add_settings() {

		// Clear the log if clear button was clicked in settings
	    if ( isset( $_GET["simple_history_clear_log_nonce"] ) && wp_verify_nonce( $_GET["simple_history_clear_log_nonce"], 'simple_history_clear_log')) {
		
			$this->clear_log();
			$msg = __("Cleared database", 'simple-history');
			add_settings_error( "simple_history_rss_feed_regenerate_secret", "simple_history_rss_feed_regenerate_secret", $msg, "updated" );
			set_transient('settings_errors', get_settings_errors(), 30);

			$goback = add_query_arg( 'settings-updated', 'true',  wp_get_referer() );
			wp_redirect( $goback );
			exit;

		}

		// Section for general options
		// Will contain settings like where to show simple history and number of items
		$settings_section_general_id = self::SETTINGS_SECTION_GENERAL_ID;
		add_settings_section(
			$settings_section_general_id, 
			"", // No title __("General", "simple-history"), 
			array($this, "settings_section_output"), 
			SimpleHistory::SETTINGS_MENU_SLUG // same slug as for options menu page
		);

		// Settings for the general settings section
		// Each setting = one row in the settings section
		// add_settings_field( $id, $title, $callback, $page, $section, $args );

		// Checkboxes for where to show simple history
		add_settings_field(
			"simple_history_show_where", 
			__("Show history", "simple-history"),
			array($this, "settings_field_where_to_show"),
			SimpleHistory::SETTINGS_MENU_SLUG,
			$settings_section_general_id
		);

		// Nonces for show where inputs
		register_setting("simple_history_settings_group", "simple_history_show_on_dashboard");
		register_setting("simple_history_settings_group", "simple_history_show_as_page");

		// Dropdown number if items to show
		add_settings_field(
			"simple_history_number_of_items", 
			__("Number of items per page", "simple-history"),
			array($this, "settings_field_number_of_items"),
			SimpleHistory::SETTINGS_MENU_SLUG,
			$settings_section_general_id
		);

		// Nonces for number of items inputs
		register_setting("simple_history_settings_group", "simple_history_pager_size");

		// Link to clear log
		add_settings_field(
			"simple_history_clear_log",
			__("Clear log", "simple-history"),
			array($this, "settings_field_clear_log"),
			SimpleHistory::SETTINGS_MENU_SLUG,
			$settings_section_general_id
		);

	}


	/**
	 * Output for page with the history
	 */
	function history_page_output() {

		global $simple_history;

		$this->purge_db();

		?>

		<div class="wrap simple-history-wrap">
			
			<h2><?php echo _x("History", 'history page headline', 'simple-history') ?></h2>
			
			<?php	
			
			simple_history_print_nav(array("from_page=1"));
			echo simple_history_print_history(array("items" => $simple_history->get_pager_size(), "from_page" => "1"));
			echo simple_history_get_pagination();
			
			?>

		</div>

		<?php

	}

	/**
	 * Get history from ajax
	 */
	function ajax() {
	
		global $simple_history;
	
		$type = isset($_POST["type"]) ? $_POST["type"] : "";
		$subtype = isset($_POST["subtype"]) ? $_POST["subtype"] : "";
	
		// We get users by username, so get username from id
		$user_id = (int) $_POST["user_id"];
		if (empty($user_id)) {
			$user = "";
		} else {
			$user_obj = new WP_User($user_id);
			if ( ! $user_obj->exists() ) exit;
			$user = $user_obj->user_login;
		};

		// page to show. 1 = first page.
		$page = 0;
		if (isset($_POST["page"])) {
			$page = (int) $_POST["page"];
		}
	
		// number of items to get
		$items = (int) (isset($_POST["items"])) ? $_POST["items"] : $simple_history->get_pager_size();

		// number of prev added items = number of items to skip before starting to add $items num of new items
		$num_added = (int) (isset($_POST["num_added"])) ? $_POST["num_added"] : $simple_history->get_pager_size();
	
		$search = (isset($_POST["search"])) ? $_POST["search"] : "";
	
		$filter_type = $type . "/" . $subtype;

		$args = array(
			"is_ajax" => true,
			"filter_type" => $filter_type,
			"filter_user" => $user,
			"page" => $page,
			"items" => $items,
			"num_added" => $num_added,
			"search" => $search 
		);
		
		$arr_json = array(
			"status" => "ok",
			"error"	=> "",
			"items_li" => "",
			"filtered_items_total_count" => 0,
			"filtered_items_total_count_string" => "",
			"filtered_items_total_pages" => 0
		);
		
		// ob_start();
		$return = simple_history_print_history($args);
		// $return = ob_get_clean();
		if ("noMoreItems" == $return) {
			$arr_json["status"] = "error";
			$arr_json["error"] = "noMoreItems";
		} else {
			$arr_json["items_li"] = $return;
			// total number of event. really bad way since we get them all again. need to fix this :/
			$args["items"] = "all";
			$all_items = simple_history_get_items_array($args);
			$arr_json["filtered_items_total_count"] = sizeof($all_items);
			$arr_json["filtered_items_total_count_string"] = sprintf(_n('One item', '%1$d items', sizeof($all_items), "simple-history"), sizeof($all_items));
			$arr_json["filtered_items_total_pages"] = ceil($arr_json["filtered_items_total_count"] / $simple_history->get_pager_size());
		}
		
		header("Content-type: application/json");
		echo json_encode($arr_json);
		
		exit;
	
	}


	/**
	 * Function that is called when plugin is activated
	 * Create database tables if they don't exist
	 * and also create some defaults
	 *
	 * Some good info:
	 * http://wordpress.stackexchange.com/questions/25910/uninstall-activate-deactivate-a-plugin-typical-features-how-to
	 */
	public static function on_plugin_activate() {

		global $wpdb;

		$table_name = $wpdb->prefix . SimpleHistory::DBTABLE;
		#if($wpdb->get_var("show tables like '$table_name'") != $table_name) {

			$sql = "CREATE TABLE " . $table_name . " (
			  id int(10) NOT NULL AUTO_INCREMENT,
			  date datetime NOT NULL,
			  action varchar(255) NOT NULL COLLATE utf8_general_ci,
			  object_type varchar(255) NOT NULL COLLATE utf8_general_ci,
			  object_subtype VARCHAR(255) NOT NULL COLLATE utf8_general_ci,
			  user_id int(10) NOT NULL,
			  object_id int(10) NOT NULL,
			  object_name varchar(255) NOT NULL COLLATE utf8_general_ci,
			  action_description longtext,
			  PRIMARY KEY  (id)
			) CHARACTER SET=utf8;";

			require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
			dbDelta($sql);

			// add ourself as a history item.
			$plugin_name = urlencode(SimpleHistory::NAME);
		
		#}

		simple_history_add("action=activated&object_type=plugin&object_name=$plugin_name");

		// also generate a rss secret, if it does not exist
		if ( ! get_option("simple_history_rss_secret") ) {
			$this->instantiatedDropins["SimpleHistoryRSSDropin"]["instance"]->update_rss_secret();
		}
		
		update_option("simple_history_version", SimpleHistory::VERSION);

	}

	/**
	 * Get setting if plugin should be visible on dasboard. 
	 * Defaults to false
	 *
	 * @return bool
	 */
	function setting_show_on_dashboard() {
		$show_on_dashboard = get_option("simple_history_show_on_dashboard", 0);
		$show_on_dashboard = apply_filters("simple_history_show_on_dashboard", $show_on_dashboard);
		return (bool) $show_on_dashboard;
	}

	/**
	 * Should simple history be shown as a page
	 * Defaults to true
	 *
	 * @return bool
	 */
	function setting_show_as_page() {

		$setting = get_option("simple_history_show_as_page", 1);
		$setting = apply_filters("simple_history_show_as_page", $setting);
		return (bool) $setting;

	}

	/**
	 * Settings field for how many rows/items to show in log
	 */
	function settings_field_number_of_items() {
		
		$current_pager_size = $this->get_pager_size();

		?>
		<select name="simple_history_pager_size">
			<option <?php echo $current_pager_size == 5 ? "selected" : "" ?> value="5">5</option>
			<option <?php echo $current_pager_size == 10 ? "selected" : "" ?> value="10">10</option>
			<option <?php echo $current_pager_size == 15 ? "selected" : "" ?> value="15">15</option>
			<option <?php echo $current_pager_size == 20 ? "selected" : "" ?> value="20">20</option>
			<option <?php echo $current_pager_size == 25 ? "selected" : "" ?> value="25">25</option>
			<option <?php echo $current_pager_size == 30 ? "selected" : "" ?> value="30">30</option>
			<option <?php echo $current_pager_size == 40 ? "selected" : "" ?> value="40">40</option>
			<option <?php echo $current_pager_size == 50 ? "selected" : "" ?> value="50">50</option>
			<option <?php echo $current_pager_size == 75 ? "selected" : "" ?> value="75">75</option>
			<option <?php echo $current_pager_size == 100 ? "selected" : "" ?> value="100">100</option>
		</select>
		<?php

	}

	/**
	 * Settings field for where to show the log, page or dashboard
	 */
	function settings_field_where_to_show() {

		$show_on_dashboard = $this->setting_show_on_dashboard();
		$show_as_page = $this->setting_show_as_page();
		?>
		
		<input <?php echo $show_on_dashboard ? "checked='checked'" : "" ?> type="checkbox" value="1" name="simple_history_show_on_dashboard" id="simple_history_show_on_dashboard" class="simple_history_show_on_dashboard" />
		<label for="simple_history_show_on_dashboard"><?php _e("on the dashboard", 'simple-history') ?></label>

		<br />
		
		<input <?php echo $show_as_page ? "checked='checked'" : "" ?> type="checkbox" value="1" name="simple_history_show_as_page" id="simple_history_show_as_page" class="simple_history_show_as_page" />
		<label for="simple_history_show_as_page"><?php _e("as a page under the dashboard menu", 'simple-history') ?></label>
		
		<?php
	}

	/**
	 * Settings section to clear database
	 */
	function settings_field_clear_log() {
		
		$clear_link = add_query_arg("", "");
		$clear_link = wp_nonce_url( $clear_link, "simple_history_clear_log", "simple_history_clear_log_nonce" );
		$clear_days = $this->get_clear_history_interval();

		echo "<p>";
		if ( $clear_days > 0 ) {
			_e(  sprintf('Items in the database are automatically removed after %1$s days.', $clear_days), 'simple-history');
		} else {
			_e( 'Items in the database are kept forever.', 'simple-history');
		}
		echo "</p>";

		printf('<p><a class="button js-SimpleHistory-Settings-ClearLog" href="%2$s">%1$s</a></p>', __('Clear log now', 'simple-history'), $clear_link);
	}

	/**
	 * How old log entried are allowed to be. 
	 * 0 = don't delete old entries.
	 * @return int Number of days.
	 */
	function get_clear_history_interval() {

		$days = 60;

		$days = (int) apply_filters("simple_history_db_purge_days_interval", $days);
		$days = (int) apply_filters("simple_history/db_purge_days_interval", $days);

		return $days;

	}

	/**
	 * Removes all items from the log
	 */
	function clear_log() {

		global $wpdb;
		
		$tableprefix = $wpdb->prefix;
		$simple_history_table = SimpleHistory::DBTABLE;
		
		$sql = "DELETE FROM {$tableprefix}{$simple_history_table}";
		$wpdb->query($sql);

	}

	/**
	 * Removes old entries from the db
	 */
	function purge_db() {

		$do_purge_history = true;
		$do_purge_history = apply_filters("simple_history_allow_db_purge", $do_purge_history);
		$do_purge_history = apply_filters("simple_history/allow_db_purge", $do_purge_history);
		if ( ! $do_purge_history ) {
			return;
		}

		$days = $this->get_clear_history_interval();

		// Never clear log if days = 0
		if (0 == $days) {
			return;
		}

		global $wpdb;
		$tableprefix = $wpdb->prefix;
		$simple_history_table = SimpleHistory::DBTABLE;


		$sql = "DELETE FROM {$tableprefix}{$simple_history_table} WHERE DATE_ADD(date, INTERVAL $days DAY) < now()";

		$wpdb->query($sql);

	}

} // class
