<?php
/*
Plugin Name: Simple History
Plugin URI: http://simple-history.com
Description: Get a log/history/audit log/version history of the changes made by users in WordPress.
Version: 2
Author: Pär Thernström
Author URI: http://simple-history.com/
License: GPL2
*/

/*  Copyright 2014  Pär Thernström (email: par.thernstrom@gmail.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

/**
 * Register function that is called when plugin is installed
 * @TODO: check that this works with wp 3.9 that have symlink support
 * @TODO: make activation multi site aware, as in https://github.com/scribu/wp-proper-network-activation
 */
register_activation_hook( trailingslashit(WP_PLUGIN_DIR) . trailingslashit( plugin_basename(__DIR__) ) . "index.php" , array("SimpleHistory", "on_plugin_activate" ) );


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

	const NAME = "Simple History";
	const VERSION = "2.0";
	const DBTABLE = "simple_history";

	/** Slug for the settings menu */
	const SETTINGS_MENU_SLUG = "simple_history_settings_menu_slug";

	function __construct() {

		$this->setupVariables();
		$this->loadLoggers();

		add_action( 'init', array($this, 'load_plugin_textdomain') );
		add_action( 'init', array($this, 'check_for_rss_feed_request') );

		add_action( 'admin_init', array($this, 'check_for_upgrade') );

		add_filter( 'plugin_action_links_simple-history/index.php', array($this, 'plugin_action_links'), 10, 4);

		add_action( 'plugin_row_meta', array($this, 'action_plugin_row_meta'), 10, 2);

		add_action( 'admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));


		add_action( 'admin_menu', array($this, 'add_admin_menus') );

		add_action( 'wp_dashboard_setup', array($this, 'wp_dashboard_setup') );
		add_action( 'wp_ajax_simple_history_ajax', array($this, 'ajax') );

		$this->add_types_for_translation();

		require_once ( dirname(__FILE__) . "/old-functions.php");
		require_once ( dirname(__FILE__) . "/old-stuff.php");
		require_once ( dirname(__FILE__) . "/simple-history-extender/simple-history-extender.php" );

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
		load_plugin_textdomain($domain, FALSE, dirname(plugin_basename(__FILE__)).'/languages/');

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

	}

	/**
	 * Load built in loggers from all files in /loggers
	 * and instantiates them
	 */
	private function loadLoggers() {
		
		$loggersDir = trailingslashit(__DIR__) . "loggers/";

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
			
			$this->instantiatedLoggers[] = array(
				"name" => $oneLoggerName,
				"instance" => new $oneLoggerName()
			);
		}

	}
	
	function get_pager_size() {

		$pager_size = get_option("simple_history_pager_size", 5);
		return $pager_size;

	}
	
	/**
	 * Some post types etc are added as variables from the log, so to catch these for translation I just add them as dummy stuff here.
	 * There is probably a better way to do this, but this should work anyway
	 */
	function add_types_for_translation() {
		$dummy = __("added", "simple-history");
		$dummy = __("approved", "simple-history");
		$dummy = __("unapproved", "simple-history");
		$dummy = __("marked as spam", "simple-history");
		$dummy = __("trashed", "simple-history");
		$dummy = __("untrashed", "simple-history");
		$dummy = __("created", "simple-history");
		$dummy = __("deleted", "simple-history");
		$dummy = __("updated", "simple-history");
		$dummy = __("nav_menu_item", "simple-history");
		$dummy = __("attachment", "simple-history");
		$dummy = __("user", "simple-history");
		$dummy = __("settings page", "simple-history");
		$dummy = __("edited", "simple-history");
		$dummy = __("comment", "simple-history");
		$dummy = __("logged in", "simple-history");
		$dummy = __("logged out", "simple-history");
		$dummy = __("added", "simple-history");
		$dummy = __("modified", "simple-history");
		$dummy = __("upgraded it\'s database", "simple-history");
		$dummy = __("plugin", "simple-history");
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
	function wp_dashboard_setup() {
		
		if ( simple_history_setting_show_on_dashboard() && current_user_can($this->view_history_capability) ) {
		
			wp_add_dashboard_widget("simple_history_dashboard_widget", __("History", 'simple-history'), "simple_history_dashboard");
			
		}
	}
	
	/**
	 * Enqueue styles and scripts for Simple History but only to our own pages.
	 *
	 * Only adds scripts to pages where the log is shown or the settings page.
	 */
	function enqueue_admin_scripts($hook) {
		
		if ( ($hook == "settings_page_" . SimpleHistory::SETTINGS_MENU_SLUG) || (simple_history_setting_show_on_dashboard() && $hook == "index.php") || (simple_history_setting_show_as_page() && $hook == "dashboard_page_simple_history_page")) {
			
			$plugin_url = plugin_dir_url(__FILE__);
			wp_enqueue_style( "simple_history_styles", $plugin_url . "styles.css", false, SimpleHistory::VERSION );	
			wp_enqueue_script("simple_history", $plugin_url . "scripts.js", array("jquery"), SimpleHistory::VERSION);
		
		}

	}

	function filter_option_page_capability($capability) {
		return $capability;
	}

	/**
	 * Add link to the donate page in the Plugins » Installed plugins screen
	 * Called from filter 'plugin_row_meta'
	 */
	function action_plugin_row_meta($links, $file) {

		if ($file == plugin_basename(__FILE__)) {

			$links = array_merge(
				$links,
				array( sprintf( '<a href="http://eskapism.se/sida/donate/?utm_source=wordpress&utm_medium=pluginpage&utm_campaign=simplehistory">%1$s</a>', __('Donate', "simple-history") ) )
			);

		}
		
		return $links;

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
	function add_admin_menus() {
	
		// Add a history page as a sub-page below the Dashboard menu item
		if (simple_history_setting_show_as_page()) {
			
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

		/*
		 * Add setting sections and settings for the settings page
		 *
		 * From codex:
		 * Settings Sections are the groups of settings you see on WordPress settings pages 
		 * with a shared heading. In your plugin you can add new sections to existing 
		 * settings pages rather than creating a whole new page. This makes your plugin 
		 * simpler to maintain and creates less new pages for users to learn. You just 
		 * tell them to change your setting on the relevant existing page.
		 */

		// Section for general options
		// Will contain settings like where to show simple history and number of items
		$settings_section_general_id = "simple_history_settings_section_general";
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
			"simple_history_settings_field",
			SimpleHistory::SETTINGS_MENU_SLUG,
			$settings_section_general_id
		);

		// Dropdown number if items to show
		add_settings_field(
			"simple_history_number_of_items", 
			__("Number of items per page", "simple-history"),
			"simple_history_settings_field_number_of_items",
			SimpleHistory::SETTINGS_MENU_SLUG,
			$settings_section_general_id
		);

		// Settings regarding the RSS feed
		add_settings_field(
			"simple_history_rss_feed", 
			__("RSS feed", "simple-history"),
			"simple_history_settings_field_rss",
			SimpleHistory::SETTINGS_MENU_SLUG,
			$settings_section_general_id
		);

		// Link to clear log
		add_settings_field(
			"simple_history_clear_log",
			__("Clear log", "simple-history"),
			"simple_history_settings_field_clear_log",
			SimpleHistory::SETTINGS_MENU_SLUG,
			$settings_section_general_id
		);

		// The donate-link, that no one ever uses
		add_settings_field(
			"simple_history_settings_donate",
			__("Donate", "simple-history"),
			"simple_history_settings_field_donate",
			SimpleHistory::SETTINGS_MENU_SLUG,
			$settings_section_general_id
		);

		// Register settings and their sanitization callbacks
		register_setting("simple_history_settings_group", "simple_history_show_on_dashboard");
		register_setting("simple_history_settings_group", "simple_history_show_as_page");
		register_setting("simple_history_settings_group", "simple_history_pager_size");
	
	}


	/**
	 * Output for page with the history
	 */
	function history_page_output() {

		global $simple_history;

		simple_history_purge_db();

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
	 * Init for both public and admin
	 */
	function check_for_rss_feed_request() {
		
		// check for RSS
		// don't know if this is the right way to do this, but it seems to work!
		if ( isset($_GET["simple_history_get_rss"]) ) {

			$this->output_rss();

		}
		
	}

	/**
	 * Output RSS
	 */
	function output_rss() {

			$rss_secret_option = get_option("simple_history_rss_secret");
			$rss_secret_get = isset( $_GET["rss_secret"] ) ? $_GET["rss_secret"] : "";

			if ( empty($rss_secret_option) || empty($rss_secret_get) ) {
				die();
			}

			header ("Content-Type:text/xml");
			echo '<?xml version="1.0" encoding="UTF-8"?>';
			$self_link = simple_history_get_rss_address();
	
			if ($rss_secret_option === $rss_secret_get) {
				?>
				<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
					<channel>
						<title><?php printf(__("History for %s", 'simple-history'), get_bloginfo("name")) ?></title>
						<description><?php printf(__("WordPress History for %s", 'simple-history'), get_bloginfo("name")) ?></description>
						<link><?php echo get_bloginfo("url") ?></link>
						<atom:link href="<?php echo $self_link; ?>" rel="self" type="application/atom+xml" />
						<?php

						// Add filters here
						/*
								"page"        => 0,
								"items"       => $simple_history->get_pager_size(),
								"filter_type" => "",
								"filter_user" => "",
								"search"      => "",
								"num_added"   => 0
						*/
						$arr_items = simple_history_get_items_array("items=10");
						foreach ($arr_items as $one_item) {
							$object_type = ucwords($one_item->object_type);
							$object_name = esc_html($one_item->object_name);
							$user = get_user_by("id", $one_item->user_id);
							$user_nicename = esc_html(@$user->user_nicename);
							$user_email = esc_html(@$user->user_email);
							$description = "";
							if ($user_nicename) {
								$description .= sprintf(__("By %s", 'simple-history'), $user_nicename);
								$description .= "<br />";
							}
							if ($one_item->occasions) {
								$description .= sprintf(__("%d occasions", 'simple-history'), sizeof($one_item->occasions));
								$description .= "<br />";
							}
							$description = apply_filters("simple_history_rss_item_description", $description, $one_item);
	
							$item_title = esc_html($object_type) . " \"" . esc_html($object_name) . "\" {$one_item->action}";
							$item_title = html_entity_decode($item_title, ENT_COMPAT, "UTF-8");
							$item_title = apply_filters("simple_history_rss_item_title", $item_title, $one_item);

							$item_guid = home_url() . "?simple-history-guid=" . $one_item->id;

							?>
							  <item>
								 <title><![CDATA[<?php echo $item_title; ?>]]></title>
								 <description><![CDATA[<?php echo $description ?>]]></description>
								 <author><?php echo $user_email . ' (' . $user_nicename . ')' ?></author>
								 <pubDate><?php echo date("D, d M Y H:i:s", strtotime($one_item->date)) ?> GMT</pubDate>
								 <guid isPermaLink="false"><?php echo $item_guid ?></guid>
								 <link><?php echo $item_guid ?></link>
							  </item>
							<?php
						}
						?>
					</channel>
				</rss>
				<?php
			} else {
				// not ok rss secret
				?>
				<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
					<channel>
						<title><?php printf(__("History for %s", 'simple-history'), get_bloginfo("name")) ?></title>
						<description><?php printf(__("WordPress History for %s", 'simple-history'), get_bloginfo("name")) ?></description>
						<link><?php echo home_url() ?></link>
						<item>
							<title><?php _e("Wrong RSS secret", 'simple-history')?></title>
							<description><?php _e("Your RSS secret for Simple History RSS feed is wrong. Please see WordPress settings for current link to the RSS feed.", 'simple-history')?></description>
							<pubDate><?php echo date("D, d M Y H:i:s", time()) ?> GMT</pubDate>
							<guid><?php echo home_url() . "?simple-history-guid=wrong-secret" ?></guid>
						</item>
					</channel>
				</rss>
				<?php
	
			}
			exit;
	} // rss

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
			simple_history_update_rss_secret();
		}
		
		update_option("simple_history_version", SimpleHistory::VERSION);


	}

} // class

// Boot up
$simple_history = new SimpleHistory;

