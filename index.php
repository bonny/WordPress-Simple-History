<?php
/*
Plugin Name: Simple History
Plugin URI: http://eskapism.se/code-playground/simple-history/
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

require_once ( dirname(__FILE__) . "/old-functions.php");
require_once ( dirname(__FILE__) . "/old-stuff.php");

define( "SIMPLE_HISTORY_VERSION", "2");

define( "SIMPLE_HISTORY_NAME", "Simple History");

// For example http://playground-root.ep/assets/plugins/simple-history/
define( "SIMPLE_HISTORY_URL", plugin_dir_url(__FILE__) );


/**
 * Simple History
 */ 
 class simple_history {
	 
	 /**
	  * Plugin folder name and filename, for example 'Simple-History/index.php'
	  */
	 private $plugin_foldername_and_filename;

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

	 function __construct() {
	 
	 	$this->setupVariables();
	 	$this->loadLoggers();
	
		add_action('init', array($this, 'loadPluginTextdomain'));

		add_action( 'init', array($this, 'init') );
		add_action( 'admin_init', array($this, 'admin_init') );
		add_action( 'admin_menu', array($this, 'admin_menu') );
		add_action( 'wp_dashboard_setup', array($this, 'wp_dashboard_setup') );
		add_action( 'wp_ajax_simple_history_ajax', array($this, 'ajax') );
		add_filter( 'plugin_action_links_simple-history/index.php', array($this, "plugin_action_links"), 10, 4);

		$this->add_types_for_translation();

		require_once ( dirname(__FILE__) . "/simple-history-extender/simple-history-extender.php" );

	}

	/**
	 * Load language files.
	 * Uses the method described here:
	 * http://geertdedeckere.be/article/loading-wordpress-language-files-the-right-way
	 * 
	 * @since 2.0
	 */
	public function loadPluginTextdomain() {

		$domain = 'simple-history';
		
		// The "plugin_locale" filter is also used in load_plugin_textdomain()
		$locale = apply_filters('plugin_locale', get_locale(), $domain);

		load_textdomain($domain, WP_LANG_DIR.'/simple-history/'.$domain.'-'.$locale.'.mo');
		load_plugin_textdomain($domain, FALSE, dirname(plugin_basename(__FILE__)).'/languages/');

	}

	/**
	 * Setup variables and things
	 */
	function setupVariables() {

		$this->plugin_foldername_and_filename = basename(dirname(__FILE__)) . "/" . basename(__FILE__);
		
		$this->view_history_capability = "edit_pages";
		$this->view_history_capability = apply_filters("simple_history_view_history_capability", $this->view_history_capability);

		$this->view_settings_capability = "manage_options";
		$this->view_settings_capability = apply_filters("simple_history_view_settings_capability", $this->view_settings_capability);		

	}

	/**
	 * Load built in loggers
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

	function plugin_action_links($actions, $b, $c, $d) {
		$settings_page_url = menu_page_url("simple_history_settings_menu_slug", 0);
		$actions[] = "<a href='$settings_page_url'>Settings</a>";
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
	
	// stuff that happens in the admin
	// "admin_init is triggered before any other hook when a user access the admin area"
	function admin_init() {

		// posts						 
		add_action("save_post", "simple_history_save_post");
		add_action("transition_post_status", "simple_history_transition_post_status", 10, 3);
		add_action("delete_post", "simple_history_delete_post");
										 
		// attachments/media			 
		add_action("add_attachment", "simple_history_add_attachment");
		add_action("edit_attachment", "simple_history_edit_attachment");
		add_action("delete_attachment", "simple_history_delete_attachment");
		
		// comments
		add_action("edit_comment", "simple_history_edit_comment");
		add_action("delete_comment", "simple_history_delete_comment");
		add_action("wp_set_comment_status", "simple_history_set_comment_status", 10, 2);

		// settings (all built in except permalinks)
		$arr_option_pages = array("general", "writing", "reading", "discussion", "media", "privacy");
		foreach ($arr_option_pages as $one_option_page_name) {
			$new_func = create_function('$capability', '
					return simple_history_add_update_option_page($capability, "'.$one_option_page_name.'");
				');
			add_filter("option_page_capability_{$one_option_page_name}", $new_func);
		}

		// settings page for permalinks
		add_action('check_admin_referer', "simple_history_add_update_option_page_permalinks", 10, 2);

		// core update = wordpress updates
		add_action( '_core_updated_successfully', array($this, "action_core_updated") );

		// add donate link to plugin list page
		add_action("plugin_row_meta", array($this, "action_plugin_row_meta"), 10, 2);

		// check if database needs upgrade
		$this->check_upgrade_stuff();

		// add scripts and styles
		add_action("admin_enqueue_scripts", array($this, "admin_enqueue"));
										 
	}

	// enqueue styles and scripts, but only to our own pages
	function admin_enqueue($hook) {
		if ( ($hook == "settings_page_simple_history_settings_menu_slug") || (simple_history_setting_show_on_dashboard() && $hook == "index.php") || (simple_history_setting_show_as_page() && $hook == "dashboard_page_simple_history_page")) {
			wp_enqueue_style( "simple_history_styles", SIMPLE_HISTORY_URL . "styles.css", false, SIMPLE_HISTORY_VERSION );	
			wp_enqueue_script("simple_history", SIMPLE_HISTORY_URL . "scripts.js", array("jquery"), SIMPLE_HISTORY_VERSION);
		}
	}

	// WordPress Core updated
	function action_core_updated($wp_version) {
		simple_history_add("action=updated&object_type=wordpress_core&object_id=wordpress_core&object_name=".sprintf(__('WordPress %1$s', 'simple-history'), $wp_version));
	}

	function filter_option_page_capability($capability) {
		return $capability;
	}

	// Add link to donate page. Note to self: does not work on dev install because of dir being trunk and not "simple-history"
	function action_plugin_row_meta($links, $file) {

		if ($file == $this->plugin_foldername_and_filename) {
			return array_merge(
				$links,
				array( sprintf( '<a href="http://eskapism.se/sida/donate/?utm_source=wordpress&utm_medium=pluginpage&utm_campaign=simplehistory">%1$s</a>', __('Donate', "simple-history") ) )
			);
		}
		return $links;

	}

	
	// check some things regarding update
	function check_upgrade_stuff() {

		global $wpdb;

		$db_version = get_option("simple_history_db_version");
		$table_name = $wpdb->prefix . "simple_history";
		// $db_version = FALSE;
		
		if ( false === $db_version ) {

			// db fix has never been run
			// user is on version 0.4 or earlier
			// = database is not using utf-8
			// so fix that
			
			require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
			#echo "begin upgrading database";
			
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
			simple_history_add("action=" . 'upgraded it\'s database' . "&object_type=plugin&object_name=" . SIMPLE_HISTORY_NAME);

			#echo "done upgrading database";
			
			update_option("simple_history_db_version", 1);

		} // done pre db ver 1 things

		// DB version is 1, upgrade to 2
		if ( 1 == intval($db_version) ) {

			// Add column for action description in non-translateable free text
			$sql = "ALTER TABLE {$table_name} ADD COLUMN action_description longtext";
			mysql_query($sql);

			simple_history_add("action=" . 'upgraded it\'s database' . "&object_type=plugin&object_name=" . SIMPLE_HISTORY_NAME . "&description=Database version is now version 2");
			update_option("simple_history_db_version", 2);

		}

		// Check that all options we use are set to their defaults, if they miss value
		// Each option that is missing a value will make a sql cal otherwise = unnecessary
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
				"default_value" => 0
			)
		);

		foreach ($arr_options as $one_option) {
			
			if ( false === ($option_value = get_option( $one_option["name"] ) ) ) {

				// Value is not set in db, so set it to a default
				update_option( $one_option["name"], $one_option["default_value"] );

			}
		}
		
	}
							 
	function settings_page() {
		
		?>
		<div class="wrap">
			<form method="post" action="options.php">
				<h2><?php _e("Simple History Settings", "simple-history") ?></h2>
				<?php do_settings_sections("simple_history_settings_menu_slug"); ?>
				<?php settings_fields("simple_history_settings_group"); ?>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
		
	}

	function admin_menu() {
	
		// show as page?
		if (simple_history_setting_show_as_page()) {
			add_dashboard_page(SIMPLE_HISTORY_NAME, __("History", 'simple-history'), $this->view_history_capability, "simple_history_page", "simple_history_management_page");
		}

		// add page for settings
		$show_settings_page = TRUE;
		$show_settings_page = apply_filters("simple_history_show_settings_page", $show_settings_page);
		if ($show_settings_page) {
			add_options_page(__('Simple History Settings', "simple-history"), SIMPLE_HISTORY_NAME, $this->view_settings_capability, "simple_history_settings_menu_slug", array($this, 'settings_page'));
		}

		add_settings_section("simple_history_settings_section", __("", "simple-history"), "simple_history_settings_page", "simple_history_settings_menu_slug");

		add_settings_field("simple_history_settings_field_1", __("Show Simple History", "simple-history"), 	"simple_history_settings_field", 							"simple_history_settings_menu_slug", "simple_history_settings_section");
		add_settings_field("simple_history_settings_field_5", __("Number of items per page", "simple-history"), 		"simple_history_settings_field_number_of_items", 			"simple_history_settings_menu_slug", "simple_history_settings_section");
		add_settings_field("simple_history_settings_field_2", __("RSS feed", "simple-history"), 			"simple_history_settings_field_rss", 						"simple_history_settings_menu_slug", "simple_history_settings_section");
		add_settings_field("simple_history_settings_field_4", __("Clear log", "simple-history"), 			"simple_history_settings_field_clear_log",					"simple_history_settings_menu_slug", "simple_history_settings_section");
		add_settings_field("simple_history_settings_field_3", __("Donate", "simple-history"), 				"simple_history_settings_field_donate",						"simple_history_settings_menu_slug", "simple_history_settings_section");

		register_setting("simple_history_settings_group", "simple_history_show_on_dashboard");
		register_setting("simple_history_settings_group", "simple_history_show_as_page");
		register_setting("simple_history_settings_group", "simple_history_pager_size");
	
	}



	/**
	 * Init for both public and admin
	 */
	function init() {
	
		// user login and logout
		add_action("wp_login", "simple_history_wp_login");
		add_action("wp_logout", "simple_history_wp_logout");

		// user failed login attempt to username that exists
		#$user = apply_filters('wp_authenticate_user', $user, $password);
		add_action("wp_authenticate_user", "sh_log_wp_authenticate_user", 10, 2);

		// user profile page modifications
		add_action("delete_user", "simple_history_delete_user");
		add_action("user_register", "simple_history_user_register");
		add_action("profile_update", "simple_history_profile_update");
	
		// options
		#add_action("updated_option", "simple_history_updated_option", 10, 3);
		#add_action("updated_option", "simple_history_updated_option2", 10, 2);
		#add_action("updated_option", "simple_history_updated_option3", 10, 1);
		#add_action("update_option", "simple_history_update_option", 10, 3);
	
		// plugin
		add_action("activated_plugin", "simple_history_activated_plugin");
		add_action("deactivated_plugin", "simple_history_deactivated_plugin");
	
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

} // class

// Boot up
$simple_history = new simple_history;







// when activating plugin: create tables
// __FILE__ doesnt work for me because of soft linkes directories
register_activation_hook( WP_PLUGIN_DIR . "/simple-history/index.php" , 'simple_history_install' );



