<?php
/*
Plugin Name: Simple History
Plugin URI: http://eskapism.se/code-playground/simple-history/
Description: Get a log/history/audit log/version history of the changes made by users in WordPress.
Version: 1.3.4
Author: Pär Thernström
Author URI: http://eskapism.se/
License: GPL2
*/

/*  Copyright 2010  Pär Thernström (email: par.thernstrom@gmail.com)

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

load_plugin_textdomain('simple-history', false, "/simple-history/languages");

define( "SIMPLE_HISTORY_VERSION", "1.3.4");
define( "SIMPLE_HISTORY_NAME", "Simple History");

// Find the plugin directory URL
$aa = __FILE__;
if ( isset( $mu_plugin ) ) {
	$aa = $mu_plugin;
}
if ( isset( $network_plugin ) ) {
	$aa = $network_plugin;
}
if ( isset( $plugin ) ) {
	$aa = $plugin;
}

$plugin_dir_url = plugin_dir_url(basename($aa)) . basename(dirname(__FILE__)) . '/';
define("SIMPLE_HISTORY_URL", $plugin_dir_url);

/**
 * Let's begin on a class, since they rule so much more than functions.
 */ 
 class simple_history {
	 
	 var
	 	$plugin_foldername_and_filename,
	 	$view_history_capability,
	 	$view_settings_capability
	 	;

	 function __construct() {
	 
		add_action( 'admin_init', 					array($this, 'admin_init') );
		add_action( 'init', 						array($this, 'init') );
		add_action( 'admin_menu', 					array($this, 'admin_menu') );
		add_action( 'wp_dashboard_setup', 			array($this, 'wp_dashboard_setup') );
		add_action( 'wp_ajax_simple_history_ajax',  array($this, 'ajax') );
		add_filter( 'plugin_action_links_simple-history/index.php', array($this, "plugin_action_links"), 10, 4);

		$this->plugin_foldername_and_filename = basename(dirname(__FILE__)) . "/" . basename(__FILE__);
		
		$this->view_history_capability = "edit_pages";
		$this->view_history_capability = apply_filters("simple_history_view_history_capability", $this->view_history_capability);

		$this->view_settings_capability = "manage_options";
		$this->view_settings_capability = apply_filters("simple_history_view_settings_capability", $this->view_settings_capability);
		
		// Load Modules
		require_once ( dirname(__FILE__) . "/modules/modules.php" );

	}
	
	function get_pager_size() {
		$pager_size = get_option("simple_history_pager_size", 5);
		return $pager_size;
	}
	
	function plugin_action_links($actions, $b, $c, $d) {
		$settings_page_url = menu_page_url("simple_history_settings_menu_slug", 0);
		$actions[] = "<a href='$settings_page_url'>Settings</a>";
		return $actions;
		
	}

	function wp_dashboard_setup() {
		if (simple_history_setting_show_on_dashboard()) {
			if (current_user_can($this->view_history_capability)) {
				wp_add_dashboard_widget("simple_history_dashboard_widget", __("History", 'simple-history'), "simple_history_dashboard");
			}
		}
	}
	
	// stuff that happens in the admin
	// "admin_init is triggered before any other hook when a user access the admin area"
	function admin_init() {

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

			echo '<?xml version="1.0"?>';
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

							$item_title = simple_history_get_event_title( esc_html($object_type), "\"" . esc_html($object_name) . "\"", $one_item->action );
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


function simple_history_settings_page() {
	// never remove this function, it must exist.	
	// echo "Please choose options for simple history ...";
}

// get settings if plugin should be visible on dasboard. default in no since 0.7
function simple_history_setting_show_on_dashboard() {
	$show_on_dashboard = get_option("simple_history_show_on_dashboard", 0);
	$show_on_dashboard = apply_filters("simple_history_show_on_dashboard", $show_on_dashboard);
	return (bool) $show_on_dashboard;
}
function simple_history_setting_show_as_page() {
	$setting = get_option("simple_history_show_as_page", 1);
	$setting = apply_filters("simple_history_show_as_page", $setting);
	return (bool) $setting;

}

function simple_history_settings_field_number_of_items() {
	
	global $simple_history;
	$current_pager_size = $simple_history->get_pager_size();

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

function simple_history_settings_field() {
	$show_on_dashboard = simple_history_setting_show_on_dashboard();
	$show_as_page = simple_history_setting_show_as_page();
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
function simple_history_settings_field_clear_log() {

	$clear_log = false;

	if (isset($_GET["simple_history_clear_log"]) && $_GET["simple_history_clear_log"]) {
		$clear_log = true;
		echo "<div class='simple-history-settings-page-updated'><p>";
		_e("Cleared database", 'simple-history');
		echo "</p></div>";
	}
	
	if ($clear_log) {
		simple_history_clear_log();
	}
	
	_e("Items in the database are automatically removed after 60 days.", 'simple-history');
	$update_link = add_query_arg("simple_history_clear_log", "1");
	printf(' <a href="%2$s">%1$s</a>', __('Clear it now.', 'simple-history'), $update_link);
}

function simple_history_clear_log() {
	global $wpdb;
	$tableprefix = $wpdb->prefix;
	$sql = "DELETE FROM {$tableprefix}simple_history";
	$wpdb->query($sql);
}

function simple_history_settings_field_donate() {
	?>
	<p>
		<?php
		_e('
			Please
			<a href="http://eskapism.se/sida/donate/?utm_source=wordpress&utm_medium=settingpage&utm_campaign=simplehistory">
			donate
			</a> to support the development of this plugin and to keep it free.
			Thanks!
			', "simple-history")
		?>
	</p>
	<?php
}


function simple_history_get_rss_address() {
	$rss_secret = get_option("simple_history_rss_secret");
	$rss_address = add_query_arg(array("simple_history_get_rss" => "1", "rss_secret" => $rss_secret), get_bloginfo("url") . "/");
	$rss_address = htmlspecialchars($rss_address, ENT_COMPAT, "UTF-8");
	return $rss_address;
}

function simple_history_update_rss_secret() {
	$rss_secret = "";
	for ($i=0; $i<20; $i++) {
		$rss_secret .= chr(rand(97,122));
	}
	update_option("simple_history_rss_secret", $rss_secret);
	return $rss_secret;
}

function simple_history_settings_field_rss() {
	$create_new_secret = false;
	if (isset($_GET["simple_history_rss_update_secret"]) && $_GET["simple_history_rss_update_secret"]) {
		$create_new_secret = true;
		echo "<div class='simple-history-settings-page-updated'><p>";
		_e("Created new secret RSS address", 'simple-history');
		echo "</p></div>";
	}
	
	if ($create_new_secret) {
		simple_history_update_rss_secret();
	}
	
	$rss_address = simple_history_get_rss_address();
	echo "<code><a href='$rss_address'>$rss_address</a></code>";
	echo "<br />";
	_e("This is a secret RSS feed for Simple History. Only share the link with people you trust", 'simple-history');
	echo "<br />";
	$update_link = add_query_arg("simple_history_rss_update_secret", "1");
	printf(__("You can <a href='%s'>generate a new address</a> for the RSS feed. This is useful if you think that the address has fallen into the wrong hands.", 'simple-history'), $update_link);
}

/**
 * add event to history table
 */
function simple_history_add($args) {

	$defaults = array(
		"action" => null,
		"object_type" => null,
		"object_subtype" => null,
		"object_id" => null,
		"object_name" => null,
		"user_id" => null,
		"description" => null
	);

	$args = wp_parse_args( $args, $defaults );

	$action = mysql_real_escape_string($args["action"]);
	$object_type = mysql_real_escape_string($args["object_type"]);
	$object_subtype = mysql_real_escape_string($args["object_subtype"]);
	$object_id = mysql_real_escape_string($args["object_id"]);
	$object_name = mysql_real_escape_string($args["object_name"]);
	$user_id = $args["user_id"];
	$description = mysql_real_escape_string($args["description"]);

	global $wpdb;
	$tableprefix = $wpdb->prefix;
	if ($user_id) {
		$current_user_id = $user_id;
	} else {
		$current_user = wp_get_current_user();
		$current_user_id = (int) $current_user->ID;
	}

	// date, store at utc or local time
	// anything is better than now() anyway!
	// WP seems to use the local time, so I will go with that too I think
	// GMT/UTC-time is: date_i18n($timezone_format, false, 'gmt')); 
	// local time is: date_i18n($timezone_format));
	$localtime = current_time("mysql");
	$sql = "
		INSERT INTO {$tableprefix}simple_history 
		SET 
			date = '$localtime', 
			action = '$action', 
			object_type = '$object_type', 
			object_subtype = '$object_subtype', 
			user_id = '$current_user_id', 
			object_id = '$object_id', 
			object_name = '$object_name',
			action_description = '$description'
		";
	$wpdb->query($sql);
}

/**
 * Removes old entries from the db
 * @todo: let user set value, if any
 */
function simple_history_purge_db() {

	$do_purge_history = TRUE;
	$do_purge_history = apply_filters("simple_history_allow_db_purge", $do_purge_history);

	global $wpdb;
	$tableprefix = $wpdb->prefix;

	$days = 60;
	$days = (int) apply_filters("simple_history_db_purge_days_interval", $days);

	$sql = "DELETE FROM {$tableprefix}simple_history WHERE DATE_ADD(date, INTERVAL $days DAY) < now()";

	if ($do_purge_history) {
		$wpdb->query($sql);
	}

}

// widget on dashboard
function simple_history_dashboard() {
	simple_history_purge_db();
	echo '<div class="wrap simple-history-wrap">';
	simple_history_print_nav();
	echo simple_history_print_history();
	echo simple_history_get_pagination();
	echo '</div>';
}

// own page under dashboard
function simple_history_management_page() {

	global $simple_history;

	simple_history_purge_db();

	?>

	<div class="wrap simple-history-wrap">
		<h2><?php echo __("History", 'simple-history') ?></h2>
		<?php	
		simple_history_print_nav(array("from_page=1"));
		echo simple_history_print_history(array("items" => $simple_history->get_pager_size(), "from_page" => "1"));
		echo simple_history_get_pagination();
		?>
	</div>

	<?php

}

if (!function_exists("bonny_d")) {
	function bonny_d($var) {
		echo "<pre>";
		print_r($var);
		echo "</pre>";
	}
}

// when activating plugin: create tables
// __FILE__ doesnt work for me because of soft linkes directories
register_activation_hook( WP_PLUGIN_DIR . "/simple-history/index.php" , 'simple_history_install' );

/*
The theory behind the right way to do this. The proper way to handle an upgrade path is to only
run an upgrade procedure when you need to. Ideally, you would store a “version” in your
plugin’s database option, and then a version in the code. If they do not match, you
would fire your upgrade procedure, and then set the database option to equal the version in 
the code. This is how many plugins handle upgrades, and this is how core works as well.	
*/

// when installing plugin: create table
function simple_history_install() {

	global $wpdb;

	$table_name = $wpdb->prefix . "simple_history";
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
		$plugin_name = urlencode(SIMPLE_HISTORY_NAME);
	
	#}

	simple_history_add("action=activated&object_type=plugin&object_name=$plugin_name");

	// also generate a rss secret, if it does not exist
	if (!get_option("simple_history_rss_secret")) {
		simple_history_update_rss_secret();
	}
	
	update_option("simple_history_version", SIMPLE_HISTORY_VERSION);

}

/**
 * Output navigation at top with filters for type, users, and free text search input
 */
function simple_history_print_nav() {

	global $wpdb;
	$tableprefix = $wpdb->prefix;
	
	// fetch all types that are in the log
	if (isset($_GET["simple_history_type_to_show"])) {
		$simple_history_type_to_show = $_GET["simple_history_type_to_show"];
	} else {
		$simple_history_type_to_show = "";
	}

	// Get all object types and object subtypes
	// order by the number of times they occur
	$sql = "SELECT 
				count(object_type) AS object_type_count,
				object_type, object_subtype 
			FROM {$tableprefix}simple_history 
			GROUP BY object_type, object_subtype
			ORDER BY object_type_count DESC, object_type, object_subtype
		";
	$arr_types = $wpdb->get_results($sql);

	$css = "";
	if (empty($simple_history_type_to_show)) {
		$css = "class='selected'";
	}

	// Reload-button
	$str_reload_button = sprintf('<a class="simple-fields-reload button" title="%1$s" href="#"><span>Reload</span></a>', esc_attr__("Reload history", "simple-history"));
	echo $str_reload_button;

	// Begin select
	$str_types_select = "";
	$str_types_select .= "<select name='' class='simple-history-filter simple-history-filter-type'>";

	$total_object_num_count = 0;
	foreach ( $arr_types as $one_type ) {
		$total_object_num_count += $one_type->object_type_count;
	}

	// First filter is "all types"
	$link = esc_html(add_query_arg("simple_history_type_to_show", ""));
	$str_types_desc = __("All types", 'simple-history');

	$str_types_select .= sprintf('<option data-simple-history-filter-type="" data-simple-history-filter-subtype="" value="%1$s">%2$s (%3$d)</option>', $link, esc_html($str_types_desc), $total_object_num_count );

	// Loop through all types
	// $one_type->object_type = user | post | attachment | comment | plugin | attachment | post | Reply | Topic | Widget | Wordpress_core
	// $one_type->object_subtype = page | nav_menu_item | ...
	#sf_d($arr_types);
	foreach ($arr_types as $one_type) {

		$css = "";
		$option_selected = "";
		if ($one_type->object_subtype && $simple_history_type_to_show == ($one_type->object_type."/".$one_type->object_subtype)) {
			$css = "class='selected'";
			$option_selected = " selected ";
		} elseif (!$one_type->object_subtype && $simple_history_type_to_show == $one_type->object_type) {
			$css = "class='selected'";
			$option_selected = " selected ";
		}

		// Create link to filter this type + subtype
		$arg = "";
		if ($one_type->object_subtype) {
			$arg = $one_type->object_type."/".$one_type->object_subtype;
		} else {
			$arg = $one_type->object_type;
		}
		$link = esc_html(add_query_arg("simple_history_type_to_show", $arg));

		// Begin option
		$str_types_select .= sprintf(
			'<option %1$s data-simple-history-filter-type="%2$s" data-simple-history-filter-subtype="%3$s" value="%4$s">',
			$option_selected, // 1
			$one_type->object_type, // 2
			$one_type->object_subtype, // 3
			$link // 4
		);
		
		// Some built in types we translate with built in translation, the others we use simple history for
		// TODO: use WP-function to get all built in types?
		$object_type_translated = "";
		$object_subtype_translated = "";

		// Get built in post types
		$arr_built_in_post_types = get_post_types( array("_builtin" => true) );

		$object_type_translated = "";
		$object_subtype_translated = "";

		// For built in types
		if ( in_array( $one_type->object_type, $arr_built_in_post_types ) ) {
			
			// Get name of main type
			$object_post_type_object = get_post_type_object( $one_type->object_type );
			$object_type_translated = $object_post_type_object->labels->name;
			
			// Get name of subtype
			$object_subtype_post_type_object = get_post_type_object( $one_type->object_subtype );
			if ( ! is_null( $object_subtype_post_type_object ) ) {
				$object_subtype_translated = $object_subtype_post_type_object->labels->name;;
			}

		}
		
		if ( empty( $object_type_translated ) ) {
			$object_type_translated = ucfirst( esc_html__( $one_type->object_type, "simple-history") );
		}

		if ( empty( $object_subtype_translated ) ) {
			$object_subtype_translated = ucfirst( esc_html__( $one_type->object_subtype, "simple-history") );
		}
		
		// Add name of type (post / attachment / user / etc.)
		
		// built in types use only subtype
		if ( in_array( $one_type->object_type, $arr_built_in_post_types ) && ! empty($object_subtype_translated) ) {

			$str_types_select .= $object_subtype_translated;

		} else {
			
			$str_types_select .= $object_type_translated;

			// And subtype, if different from main type
			if ($object_subtype_translated && $object_subtype_translated != $object_type_translated) {
				$str_types_select .= "/" . $object_subtype_translated;
			}

		}
		// Add object count
		$str_types_select .= sprintf(' (%d)', $one_type->object_type_count);
		
		// Close option
		$str_types_select .= "\n</option>";
		
		// debug
		#$str_types .= " type: " . $one_type->object_type;
		#$str_types .= " type: " . ucfirst($one_type->object_type);
		#$str_types .= " subtype: " . $one_type->object_subtype. " ";
		
	} // foreach arr types

	$str_types_select .= "\n</select>";

	// Output filters
	if ( ! empty( $arr_types ) ) {
		// echo $str_types;
		echo $str_types_select;
	}

	// fetch all users that are in the log
	$sql = "SELECT DISTINCT user_id FROM {$tableprefix}simple_history WHERE user_id <> 0";
	$arr_users_regular = $wpdb->get_results($sql);
	foreach ($arr_users_regular as $one_user) {
		$arr_users[$one_user->user_id] = array("user_id" => $one_user->user_id);
	}
	
	if ( ! empty( $arr_users ) ) {
	
		foreach ($arr_users as $user_id => $one_user) {
			$user = get_user_by("id", $user_id);
			if ($user) {
				$arr_users[$user_id]["user_login"] = $user->user_login;
				$arr_users[$user_id]["user_nicename"] = $user->user_nicename;
				if (isset($user->first_name)) {
					$arr_users[$user_id]["first_name"] = $user->first_name;
				}
				if (isset($user->last_name)) {
					$arr_users[$user_id]["last_name"] = $user->last_name;
				}
			}
		}

	}

	if (isset($arr_users) && $arr_users) {

		if (isset($_GET["simple_history_user_to_show"])) {
			$simple_history_user_to_show = $_GET["simple_history_user_to_show"];
		} else {
			$simple_history_user_to_show = "";
		}

		$str_users_select = "";
		$str_users_select .= "<select name='' class='simple-history-filter simple-history-filter-user'>";

		$css = "";
		$option_selected = "";
		if (empty($simple_history_user_to_show)) {
			$css = " class='selected' ";
			$option_selected = " selected ";
		}

		// All users
		$link = esc_html(add_query_arg("simple_history_user_to_show", ""));

		$str_users_select .= sprintf(
				'<option data-simple-history-filter-user-id="%4$s" value="%3$s" %2$s>%1s</option>', 
				__("By all users", 'simple-history'), // 1
				$option_selected, // 2
				$link, // 3
				"" // 4
			);

		foreach ($arr_users as $user_id => $user_info) {

			$user = new WP_User($user_id);
			if ( ! $user->exists() ) continue;

			$link = esc_html(add_query_arg("simple_history_user_to_show", $user_id));
			
			$css = "";
			$option_selected = "";

			if ($user_id == $simple_history_user_to_show) {
				$css = " class='selected' ";
				$option_selected = " selected ";
			}

			// all users must have username and email
			$str_user_name = sprintf('%1$s (%2$s)', esc_attr($user->user_login), esc_attr($user->user_email));
			// if ( ! empty( $user_info["first_name"] )  $user_info["last_name"] );
			
			$str_users_select .= sprintf(
				'<option data-simple-history-filter-user-id="%4$s" %2$s value="%1$s">%1$s</option>',
				$str_user_name, // 1
				$option_selected, // 2
				$link, // 3
				$user_id
			);

		}

		$str_users_select .= "</select>";

		if ( ! empty($str_users_select) ) {
			echo $str_users_select;
		}

	}
	
	// search
	$str_search = __("Search", 'simple-history');
	$search = "<p class='simple-history-filter simple-history-filter-search'>
		<input type='text' />
		<input type='button' value='$str_search' class='button' />
	</p>";
	echo $search;
	
}

function simple_history_get_pagination() {

	// pagination
	global $simple_history;
	$all_items = simple_history_get_items_array("items=all");
	$items_count = sizeof($all_items);
	$pages_count = ceil($items_count/$simple_history->get_pager_size());
	$page_current = 1;

	$out = sprintf('
		<div class="tablenav simple-history-tablenav">
			<div class="tablenav-pages">
				<span class="displaying-num">%1$s</span>
				<span class="pagination-links">
					<a class="first-page disabled" title="%5$s" href="#"><span>«</span></a>
					<a class="prev-page disabled" title="%6$s" href="#"><span>‹</span></a>
					<span class="paging-input"><input class="current-page" title="%7$s" type="text" name="paged" value="%2$d" size="2"> %8$s <span class="total-pages">%3$d</span></span>
					<a class="next-page %4$s" title="%9$s" href="#"><span>›</span></a>
					<a class="last-page %4$s" title="%10$s" href="#"><span>»</span></a>
				</span>
			</div>
		</div>
		',
		sprintf(_n('One item', '%1$d items', sizeof($all_items), "simple-history"), sizeof($all_items)),
		$page_current,
		$pages_count,
		($pages_count == 1) ? "disabled" : "",
		__("Go to the first page"), // 5
		__("Go to the previous page"), // 6
		__("Current page"), // 7
		__("of"), // 8
		__("Go to the next page"), // 9
		__("Go to the last page") // 10
	);

	return $out;
	
}


// return an array with all events and occasions
function simple_history_get_items_array($args = "") {

	global $wpdb, $simple_history;
	
	$defaults = array(
		"page"        => 0,
		"items"       => $simple_history->get_pager_size(),
		"filter_type" => "",
		"filter_user" => "",
		"is_ajax"     => false,
		"search"      => "",
		"num_added"   => 0
	);
	$args = wp_parse_args( $args, $defaults );

	$simple_history_type_to_show = $args["filter_type"];
	$simple_history_user_to_show = $args["filter_user"];

	$where = " WHERE 1=1 ";
	if ($simple_history_type_to_show) {
		$filter_type = "";
		$filter_subtype = "";
		if (strpos($simple_history_type_to_show, "/") !== false) {
			// split it up
			$arr_args = explode("/", $simple_history_type_to_show);
			$filter_type = $arr_args[0];
			$filter_subtype = $arr_args[1];
		} else {
			$filter_type = $simple_history_type_to_show;
		}
		if ($filter_type) {
			$where .= " AND lower(object_type) = '" . $wpdb->escape(strtolower($filter_type)) . "' ";		
		}
		if ($filter_subtype) {
			$where .= " AND lower(object_subtype) = '" . $wpdb->escape(strtolower($filter_subtype)) . "' ";
		}
	}
	if ($simple_history_user_to_show) {
		
		$userinfo = get_user_by("slug", $simple_history_user_to_show);

		if (isset($userinfo->ID)) {
			$where .= " AND user_id = '" . $userinfo->ID . "'";
		}

	}

	$tableprefix = $wpdb->prefix;

	$sql = "SELECT * FROM {$tableprefix}simple_history $where ORDER BY date DESC, id DESC ";
	#sf_d($args);
	#echo "\n$sql\n";
	$rows = $wpdb->get_results($sql);
	
	$loopNum = 0;
	$real_loop_num = -1;
	
	$search = strtolower($args["search"]);
	
	$arr_events = array();
	if ($rows) {
		$prev_row = null;
		foreach ($rows as $one_row) {
			
			// check if this event is same as prev event
			// todo: how to do with object_name vs object id?
			// if object_id is same as prev, but object_name differ, then it's the same object but with a new name
			// we store it as same and use occations to output the name etc of
			if (
				$prev_row
				&& $one_row->action == $prev_row->action
				&& $one_row->object_type == $prev_row->object_type
				&& $one_row->object_type == $prev_row->object_type
				&& $one_row->object_subtype == $prev_row->object_subtype
				&& $one_row->user_id == $prev_row->user_id
				&& (
						(!empty($one_row->object_id) && !empty($prev_row->object_id))
						&& ($one_row->object_id == $prev_row->object_id)
						|| ($one_row->object_name == $prev_row->object_name)
				)
			) {
				
				// this event is like the previous event, but only with a different date
				// so add it to the last element in arr_events
				$arr_events[$prev_row->id]->occasions[] = $one_row;
				
			} else {

				#echo "<br>real_loop_num: $real_loop_num";
				#echo "<br>loop_num: $loopNum";
				
				//  check if we have a search. of so, only add if there is a match
				$do_add = FALSE;
				if ($search) {
					/* echo "<br>search: $search";
					echo "<br>object_name_lower: $object_name_lower";
					echo "<br>objecttype: " . $one_row->object_type;
					echo "<br>object_subtype: " . $one_row->object_subtype;
					// */
					if (strpos(strtolower($one_row->object_name), $search) !== FALSE) {
						$do_add = TRUE;
					} else if (strpos(strtolower($one_row->object_type), $search) !== FALSE) {
						$do_add = TRUE;
					} else if (strpos(strtolower($one_row->object_subtype), $search) !== FALSE) {
						$do_add = TRUE;
					} else if (strpos(strtolower($one_row->action), $search) !== FALSE) {
						$do_add = TRUE;
					} else if (strpos(strtolower($one_row->action_description), $search) !== FALSE) {
						$do_add = TRUE;
					}
		        } else {
			        $do_add = TRUE;
		        }
		        
		        if ($do_add) {
			        $real_loop_num++;
		        }
		        			
				// new event, not as previous one								
				if ($do_add) {
					$arr_events[$one_row->id] = $one_row;
					$arr_events[$one_row->id]->occasions = array();
					$loopNum++;
					$prev_row = $one_row;
				}

			}
		}

	}

	// arr_events is now all events
	// but we only want some of them
	// limit by using 
	// num_added = number of prev added items
	// items = number of items to get
	/*sf_d($args["num_added"]);
	sf_d($args["items"]);
	sf_d($arr_events);
	// */
	// 
	//$offset = $args["num_added"]; // old way when we appended
/*
<pre class='sf_box_debug'>Array
(
    [page] =&gt; 1
    [items] =&gt; 5
    [filter_type] =&gt; /
    [filter_user] =&gt; 
    [is_ajax] =&gt; 1
    [search] =&gt; 
    [num_added] =&gt; 5
)
*/

	if (is_numeric($args["items"]) && $args["items"] > 0) {
		#sf_d($args);
		$offset = ($args["page"] * $args["items"]);
		#echo "offset: $offset";
		$arr_events = array_splice($arr_events, $offset, $args["items"]);
	}

	return $arr_events;
	
}

// return the log
// taking filtrering into consideration
function simple_history_print_history($args = null) {
	
	global $simple_history;
	
	$arr_events = simple_history_get_items_array($args);
	#sf_d($arr_events);
	#sf_d($args);sf_d($arr_events);
	$defaults = array(
		"page" => 0,
		"items" => $simple_history->get_pager_size(),
		"filter_type" => "",
		"filter_user" => "",
		"is_ajax" => false
	);

	$args = wp_parse_args( $args, $defaults );
	$output = "";
	if ($arr_events) {
		if (!$args["is_ajax"]) {
			// if not ajax, print the div
			$output .= "<div class='simple-history-ol-wrapper'><ol class='simple-history'>";
		}
	
		$loopNum = 0;
		$real_loop_num = -1;
		foreach ($arr_events as $one_row) {
			
			$real_loop_num++;

			$object_type = $one_row->object_type;
			$object_type_lcase = strtolower($object_type);
			$object_subtype = $one_row->object_subtype;
			$object_id = $one_row->object_id;
			$object_name = $one_row->object_name;
			$user_id = $one_row->user_id;
			$action = $one_row->action;
			$action_description = $one_row->action_description;
			$occasions = $one_row->occasions;
			$num_occasions = sizeof($occasions);
			$object_image_out = "";

			$css = "";
			if ("attachment" == $object_type_lcase) {
				if (wp_get_attachment_image_src($object_id, array(50,50), true)) {
					// yep, it's an attachment and it has an icon/thumbnail
					$css .= ' simple-history-has-attachment-thumnbail ';
				}
			}
			if ("user" == $object_type_lcase) {
				$css .= ' simple-history-has-attachment-thumnbail ';
			}

			if ($num_occasions > 0) {
				$css .= ' simple-history-has-occasions ';
			}
			
			$output .= "<li class='$css'>";

			$output .= "<div class='first'>";
			
			// who performed the action
			$who = "";
			$user = get_user_by("id", $user_id); // false if user does not exist

			if ($user) {
				$user_avatar = get_avatar($user->user_email, "32"); 
				$user_link = "user-edit.php?user_id={$user->ID}";
				$who_avatar = sprintf('<a class="simple-history-who-avatar" href="%2$s">%1$s</a>', $user_avatar, $user_link);
			} else {
				$user_avatar = get_avatar("", "32"); 
				$who_avatar = sprintf('<span class="simple-history-who-avatar">%1$s</span>', $user_avatar);
			}
			$output .= $who_avatar;
			
			// section with info about the user who did something
			$who .= "<span class='who'>";
			if ($user) {
				$who .= sprintf('<a href="%2$s">%1$s</a>', $user->user_nicename, $user_link);
				if (isset($user->first_name) || isset($user->last_name)) {
					if ($user->first_name || $user->last_name) {
						$who .= " (";
						if ($user->first_name && $user->last_name) {
							$who .= esc_html($user->first_name) . " " . esc_html($user->last_name);
						} else {
							$who .= esc_html($user->first_name) . esc_html($user->last_name); // just one of them, no space necessary
						}
						$who .= ")";
					}
				}
			} else {
				$who .= "&lt;" . __("Unknown or deleted user", 'simple-history') ."&gt;";
			}
			$who .= "</span>";

			// what and object
			if ("post" == $object_type_lcase) {
				
				// Get real name for post type (not just the slug for custom post types)
				$type = ! empty( $object_subtype ) ? $object_subtype : $object_type;
				$post_type_object = get_post_type_object( $type );
				if ( is_null($post_type_object) ) {
					$post_label = esc_html__( ucfirst( $type ) );
				} else {
					$post_label = esc_html__( ucfirst( $post_type_object->labels->singular_name ) );
				}

				$post = get_post($object_id);

				if (null == $post) {
					// post does not exist, probably deleted
					// check if object_name exists
					if ($object_name) {
						$post_title = "<span class='simple-history-title'>\"" . esc_html($object_name) . "\"</span>";
					} else {
						$post_title = "<span class='simple-history-title'>&lt;unknown name&gt;</span>";
					}
				} else {
					#$title = esc_html($post->post_title);
					$title = get_the_title($post->ID);
					$title = esc_html($title);
					$edit_link = get_edit_post_link($object_id, 'display');
					$post_title  = "<a href='$edit_link'>";
					$post_title .= "<span class='simple-history-title'>\"{$title}\"</span>";
					$post_title .= "</a>";
				}

				$post_action = esc_html__($action, "simple-history");
				
				$output .= simple_history_get_event_title( $post_label, $post_title, $post_action );

				
			} elseif ("attachment" == $object_type_lcase) {
			
				$attachment_label = __("attachment", 'simple-history');

				$post = get_post($object_id);
				
				if ($post) {

					// Post for attachment was found

					$title = esc_html(get_the_title($post->ID));
					$edit_link = get_edit_post_link($object_id, 'display');
					$attachment_metadata = wp_get_attachment_metadata( $object_id );
					$attachment_file = get_attached_file( $object_id );
					$attachment_mime = get_post_mime_type( $object_id );
					$attachment_url = wp_get_attachment_url( $object_id );

					// Get attachment thumbnail. 60 x 60 is the same size as the media overview uses
					// Is thumbnail of object if image, is wp icon if not
					$attachment_image_src = wp_get_attachment_image_src($object_id, array(60, 60), true);					
					if ($attachment_image_src) {
						$object_image_out .= "<a class='simple-history-attachment-thumbnail' href='$edit_link'><img src='{$attachment_image_src[0]}' alt='Attachment icon' width='{$attachment_image_src[1]}' height='{$attachment_image_src[2]}' /></a>";
					}
					
					// Begin adding nice to have meta info about to attachment (name, size, mime, etc.)					
					$object_image_out .= "<div class='simple-history-attachment-meta'>";

					// File name

					// Get size in human readable format. Code snippet from media.php
					$sizes = array( 'KB', 'MB', 'GB' );
					$attachment_filesize = filesize( $attachment_file );
					for ( $u = -1; $attachment_filesize > 1024 && $u < count( $sizes ) - 1; $u++ ) {
						$attachment_filesize /= 1024;
					}

					// File type
					$file_type_out = "";
					if ( preg_match( '/^.*?\.(\w+)$/', $attachment_file, $matches ) )
						$file_type_out .= esc_html( strtoupper( $matches[1] ) );
					else
						$file_type_out .= strtoupper( str_replace( 'image/', '', $post->post_mime_type ) );
			
					// Media size, width x height
					$media_dims = "";
					if ( ! empty( $attachment_metadata['width'] ) && ! empty( $attachment_metadata['height'] ) ) {
						$media_dims .= "<span>{$attachment_metadata['width']}&nbsp;&times;&nbsp;{$attachment_metadata['height']}</span>";
					}

					// Generate string with metainfo
					$size_unit = ($u == -1) ? __("bytes", "simple-history") : $sizes[$u];
					$object_image_out .= sprintf('<p>%1$s %2$s</p>', __("File name:"), esc_html( basename( $attachment_file ) ) );;
					$object_image_out .= sprintf('<p>%1$s %2$s %3$s</p>', __("File size:", "simple-history"), round( $attachment_filesize, 0 ), $size_unit );
					// $object_image_out .= sprintf('<p>%1$s %2$s</p>', __("File type:"), $file_type_out );
					if ( ! empty( $media_dims ) ) $object_image_out .= sprintf('<p>%1$s %2$s</p>', __("Dimensions:"), $media_dims );					
					if ( ! empty( $attachment_metadata["length_formatted"] ) ) $object_image_out .= sprintf('<p>%1$s %2$s</p>', __("Length:"), $attachment_metadata["length_formatted"] );					
										
					// end attachment meta info box output
					$object_image_out .= "</div>"; // close simple-history-attachment-meta

					$attachment_title  = "<a href='$edit_link'>";
					$attachment_title .= "<span class='simple-history-title'>{$title}</span>";
					$attachment_title .= "</a>";
					
				} else {

					// Post for attachment was not found
					if ($object_name) {
						$attachment_title = "<span class='simple-history-title'>\"" . esc_html($object_name) . "\"</span>";
					} else {
						$attachment_title = "<span class='simple-history-title'>&lt;deleted&gt;</span>";
					}

				}

				$attachment_action = esc_html__($action, "simple-history");
				
				$output .= simple_history_get_event_title( $attachment_label, $attachment_title, $attachment_action );

			} elseif ("user" == $object_type_lcase) {

				$user_label = __("user", 'simple-history');
				$user = get_user_by("id", $object_id);
				if ($user) {
					$user_link = "user-edit.php?user_id={$user->ID}";
					$user_title  = "<span class='simple-history-title'>";
					$user_title .= " <a href='$user_link'>";
					$user_title .= $user->user_nicename;
					$user_title .= "</a>";
					if (isset($user->first_name) && isset($user->last_name)) {
						if ($user->first_name || $user->last_name) {
							$user_title .= " (";
							if ($user->first_name && $user->last_name) {
								$user_title .= esc_html($user->first_name) . " " . esc_html($user->last_name);
							} else {
								$user_title .= esc_html($user->first_name) . esc_html($user->last_name); // just one of them, no space necessary
							}
							$user_title .= ")";
						}
					}
					$user_title .= "</span>";
				} else {
					// most likely deleted user
					$user_link = "";
					$user_title = "\"" . esc_html($object_name) . "\"";
				}

				$user_action = esc_html__($action, "simple-history");
				
				$output .= simple_history_get_event_title( $user_label, $user_title, $user_action );

			} elseif ("comment" == $object_type_lcase) {
				
				$comment_link = get_comment($object_id) ? get_edit_comment_link($object_id) : '';
				$comment_label = ucwords(esc_html__(ucfirst($object_type))) . " " . esc_html($object_subtype);
				$comment_title = "<a href='$comment_link'><span class='simple-history-title'>\"" . esc_html($object_name) . "\"</span></a>";
				$comment_action = esc_html__($action, "simple-history");

				$output .= simple_history_get_event_title( $comment_label, $comment_title, $comment_action );

			} else {

				// unknown/general type
				// translate the common types
				$unknown_action = $action;
				switch ($unknown_action) {
					case "activated":
						$unknown_action = __("activated", 'simple-history');
						break;
					case "deactivated":
						$unknown_action = __("deactivated", 'simple-history');
						break;
						case "enabled":
						$unknown_action = __("enabled", 'simple-history');
						break;
					case "disabled":
						$unknown_action = __("disabled", 'simple-history');
						break;
					default:
						$unknown_action = $unknown_action; // dah!
				}
				$unknown_label = ucwords(esc_html__($object_type, "simple-history")) . " " . ucwords(esc_html__($object_subtype, "simple-history"));
				$unknown_title = "<span class='simple-history-title'>\"" . esc_html($object_name) . "\"</span>";
				$unknown_action = esc_html($unknown_action);

				$output .= simple_history_get_event_title( $unknown_label, $unknown_title, $unknown_action );

			}
			$output .= "</div>";
			
			// second div = when and who
			$output .= "<div class='second'>";
			
			$date_i18n_date = date_i18n(get_option('date_format'), strtotime($one_row->date), $gmt=false);
			$date_i18n_time = date_i18n(get_option('time_format'), strtotime($one_row->date), $gmt=false);		
			$now = strtotime(current_time("mysql"));
			$diff_str = sprintf( __('<span class="when">%1$s ago</span> by %2$s', "simple-history"), human_time_diff(strtotime($one_row->date), $now), $who );
			$output .= $diff_str;
			$output .= "<span class='when_detail'>".sprintf(__('%s at %s', 'simple-history'), $date_i18n_date, $date_i18n_time)."</span>";

			// action description
			if ( trim( $action_description ) )  {
				$output .= sprintf(
					'
					<a href="#" class="simple-history-item-description-toggler">%2$s</a>
					<div class="simple-history-item-description-wrap">
						<div class="simple-history-action-description">%1$s</div>
					</div>
					',
					nl2br( esc_attr( $action_description ) ), // 2
					__("Details", "simple-history") // 2
				);
			}
			
			$output .= "</div>";

			// Object image
			if ( $object_image_out ) {

				$output .= sprintf(
					'
					<div class="simple-history-object-image">
						%1$s
					</div>
					',
					$object_image_out
				);

			}

			// occasions
			if ($num_occasions > 0) {
				$output .= "<div class='third'>";
				if ($num_occasions == 1) {
					$one_occasion = __("+ 1 occasion", 'simple-history');
					$output .= "<a class='simple-history-occasion-show' href='#'>$one_occasion</a>";
				} else {
					$many_occasion = sprintf(__("+ %d occasions", 'simple-history'), $num_occasions);
					$output .= "<a class='simple-history-occasion-show' href='#'>$many_occasion</a>";
				}
				
				$output .= "<ul class='simple-history-occasions hidden'>";
				foreach ($occasions as $one_occasion) {
				
					$output .= "<li>";
				
					$date_i18n_date = date_i18n(get_option('date_format'), strtotime($one_occasion->date), $gmt=false);
					$date_i18n_time = date_i18n(get_option('time_format'), strtotime($one_occasion->date), $gmt=false);		
				
					$output .= "<div class='simple-history-occasions-one-when'>";
					$output .= sprintf(
							__('%s ago (%s at %s)', "simple-history"), 
							human_time_diff(strtotime($one_occasion->date), $now), 
							$date_i18n_date, 
							$date_i18n_time
						);
					
					if ( trim( $one_occasion->action_description ) )  {
						$output .= "<a href='#' class='simple-history-occasions-details-toggle'>" . __("Details", "simple-history") . "</a>";
					}
					
					$output .= "</div>";

					if ( trim( $one_occasion->action_description ) )  {
						$output .= sprintf(
							'<div class="simple-history-occasions-one-action-description">%1$s</div>',
							nl2br( esc_attr( $one_occasion->action_description ) )
						);
					}


					$output .= "</li>";
				}

				$output .= "</ul>";

				$output .= "</div>";
			}

			$output .= "</li>";

			$loopNum++;

		}
		
		// if $loopNum == 0 no items where found for this page
		if ($loopNum == 0) {
			$output .= "noMoreItems";
		}
		
		if ( ! $args["is_ajax"] ) {

			// if not ajax, print the divs and stuff we need
			$show_more = "<select>";
			$show_more .= sprintf('<option value=5 %2$s>%1$s</option>', __("Show 5 more", 'simple-history'), ($args["items"] == 5 ? " selected " : "") );
			$show_more .= sprintf('<option value=15 %2$s>%1$s</option>', __("Show 15 more", 'simple-history'), ($args["items"] == 15 ? " selected " : "") );
			$show_more .= sprintf('<option value=50 %2$s>%1$s</option>', __("Show 50 more", 'simple-history'), ($args["items"] == 50 ? " selected " : "") );
			$show_more .= sprintf('<option value=100 %2$s>%1$s</option>', __("Show 100 more", 'simple-history'), ($args["items"] == 100 ? " selected " : "") );
			$show_more .= "</select>";

			$no_found = __("No matching items found.", 'simple-history');
			$view_rss = __("RSS feed", 'simple-history');
			$view_rss_link = simple_history_get_rss_address();
			$str_show = __("Show", 'simple-history');
			$output .= "</ol>";

			$output .= sprintf( '
					<div class="simple-history-loading">%2$s %1$s</div>
					', 
					__("Loading...", 'simple-history'), // 1
					"<img src='".site_url("wp-admin/images/loading.gif")."' width=16 height=16>"
				);

			$output .= "</div>";

			$output .= "
				<p class='simple-history-no-more-items'>$no_found</p>			
				<p class='simple-history-rss-feed-dashboard'><a title='$view_rss' href='$view_rss_link'>$view_rss</a></p>
				<p class='simple-history-rss-feed-page'><a title='$view_rss' href='$view_rss_link'><span></span>$view_rss</a></p>
			";

		}

	} else {

		if ($args["is_ajax"]) {
			$output .= "noMoreItems";
		} else {
			$no_found = __("No history items found.", 'simple-history');
			$please_note = __("Please note that Simple History only records things that happen after this plugin have been installed.", 'simple-history');
			$output .= "<p>$no_found</p>";
			$output .= "<p>$please_note</p>";
		}

	}
	return $output;
}

/**
 * Return event title string
 *
 * @since 1.3.5
 *
 * @param string $label Object label
 * @param string $name Object name
 * @param string $action Object action
 * @return string Event title
 */
function simple_history_get_event_title( $label, $name, $action ) {

	// Setup title string
	// Find %1$s and/or %2$s
	if ( false !== strpos( $action, '%1$s' ) || false !== strpos( $action, '%2$s' ) )
		$title = sprintf( $action, ucfirst( $label ), $name, 'X' );
	
	// Pre-1.3.5
	else
		$title = ucfirst( $label ) .' '. $name .' '. $action;

	// Filtering comes later
	return $title;
}

/**
 * Return whether not to log the given event
 *
 * Providing a generic filter for cancelling event logging in case an
 * event gets logged more than once by different functions.
 * 
 * @since 1.3.5
 * 
 * @param string $event Event name
 * @param array $args Event arguments
 * @uses apply_filters() Calls 'simple_history_dont_log' with the
 *                        result, event name and event arguments
 * @return boolean Whether not to log
 */
function simple_history_dont_log( $event, $args = array() ) {
	return apply_filters( 'simple_history_dont_log', false, $event, (array) $args );
}

