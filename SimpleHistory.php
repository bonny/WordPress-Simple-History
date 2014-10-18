<?php

/**
 * Main class for Simple History
 */ 
class SimpleHistory {
	 
 	const NAME = "Simple History";
	const VERSION = "2.0";

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

	/**
	 * Bool if gettext filter function should be active
	 * Should only be active during the load of a logger
	 */
	private $doFilterGettext = false;

	/**
	 * Used by gettext filter to temporarily store current logger
	 */
	private $doFilterGettext_currentLogger = null;

	/**
	 * All registered settings tabs
	 */
	private $arr_settings_tabs = array();

	const DBTABLE = "simple_history";
	const DBTABLE_CONTEXTS = "simple_history_contexts";

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

		add_filter("gettext", array($this, 'filter_gettext'), 20, 3);
		add_filter("gettext_with_context", array($this, 'filter_gettext_with_context'), 20, 4);

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

		require_once ( dirname(__FILE__) . "/old-functions.php");

		add_action( 'wp_ajax_simple_history_api', array($this, 'api') );

		add_action( 'admin_footer', array( $this, "add_js_templates" ) );

		add_action( 'simple_history/history_page/before_gui', array( $this, "output_quick_stats" ) );
		add_action( 'simple_history/dashboard/before_gui', array( $this, "output_quick_stats" ) );

		add_action(  'admin_head', array( $this, "onAdminHead" ) );

		/**
	     * Fires after Simple History has done it's init stuff
	     *
	     * @since 2.0
	     *
	     * @param SimpleHistory $SimpleHistory This class.
	     */
		do_action( "simple_history/after_init", $this );

	}

	public function onAdminHead() {

		if ( $this->is_on_our_own_pages() ) {
			do_action( "simple_history/admin_head", $this );
		}

	}

	/**
	 * Output JS templated into footer
	 */
	public function add_js_templates($hook) {
		
		if ( $this->is_on_our_own_pages() ) {

			?>
			
			<script type="text/html" id="tmpl-simple-history-base">

				<div class="SimpleHistory__waitingForFirstLoad">
					<img src="<?php echo admin_url("/images/spinner.gif");?>" alt="" width="20" height="20">
					<?php echo _x("Loading history...", "Message visible while waiting for log to load from server the first time", "simple-history") ?>
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
					<%= page_rows_from %>–<%= page_rows_to %> 
					<span class="SimpleHistoryPaginationDisplayNum"> of <%= total_row_count %></span>
					-->
					<span class="SimpleHistoryPaginationLinks">
						<a 	
							data-direction="first" 
							class="button SimpleHistoryPaginationLink SimpleHistoryPaginationLink--firstPage <% if ( api_args.paged <= 1 ) { %> disabled <% } %>" 
							title="<%= strings.goToTheFirstPage %>" 
							href="#">«</a>
						<a 
							data-direction="prev" 
							class="button SimpleHistoryPaginationLink SimpleHistoryPaginationLink--prevPage <% if ( api_args.paged <= 1 ) { %> disabled <% } %>" 
							title="<%= strings.goToThePrevPage %>"
							href="#">‹</a>
						<span class="SimpleHistoryPaginationInput">
							<input class="SimpleHistoryPaginationCurrentPage" title="<%= strings.currentPage %>" type="text" name="paged" value="<%= api_args.paged %>" size="4">
							of 
							<span class="total-pages"><%= pages_count %></span>
						</span>
						<a 
							data-direction="next" 
							class="button SimpleHistoryPaginationLink SimpleHistoryPaginationLink--nextPage <% if ( api_args.paged >= pages_count ) { %> disabled <% } %>" 
							title="<%= strings.goToTheNextPage %>"
							href="#">›</a>
						<a 
							data-direction="last" 
							class="button SimpleHistoryPaginationLink SimpleHistoryPaginationLink--lastPage <% if ( api_args.paged >= pages_count ) { %> disabled <% } %>" 
							title="<%= strings.goToTheLastPage %>"
							href="#">»</a>
					</span>
				</div>

			</script>

			<script type="text/html" id="tmpl-simple-history-logitems-modal">

				<div class="SimpleHistory-modal">
					<div class="SimpleHistory-modal__background"></div>
					<div class="SimpleHistory-modal__content">
						<div class="SimpleHistory-modal__contentInner">
							<img class="SimpleHistory-modal__contentSpinner" src="<?php echo admin_url("/images/spinner.gif");?>" alt="">
						</div>
						<div class="SimpleHistory-modal__contentClose">
							<button class="button">✕</button>
						</div>
					</div>
				</div>

			</script>

			<?php

			// Call plugins so they can add their js
			foreach ( $this->instantiatedLoggers as $one_logger ) {
				if( method_exists($one_logger["instance"], "adminJS" ) ) {
					$one_logger["instance"]->adminJS();
				}
			}

		}

	}

	/**
	 * Base url is:
	 * /wp-admin/admin-ajax.php?action=simple_history_api
	 *
	 * Examples:
	 * http://playground-root.ep/wp-admin/admin-ajax.php?action=simple_history_api&posts_per_page=5&paged=1&format=html
	 *
	 */
	public function api() {
		
		global $wpdb;

		// Fake slow answers
		//sleep(2);
		//sleep(rand(0,3));
		$args = $_GET;
		unset($args["action"]);

		// Type = overview | ...
		$type = isset( $_GET["type"] ) ? $_GET["type"] : null;

		if ( empty( $args ) || ! $type ) {

			wp_send_json_error( array(
				_x("Not enough args specified", "API: not enought arguments passed", "simple-history")
			) );

		}

		if (isset($args["id"])) {
			$args["post__in"] = array(
				$args["id"]
			);
		}


		$data = array();

		switch ($type) {

			case "overview":
			case "occasions":
			case "single":

				// API use SimpleHistoryLogQuery, so simply pass args on to that
				$logQuery = new SimpleHistoryLogQuery();
				$data = $logQuery->query($args);
				
				$data["api_args"] = $args;

				// Output can be array or HMTL
				if ( isset( $args["format"] ) && "html" === $args["format"] ) {
					
					$data["log_rows_raw"] = array();

					foreach ($data["log_rows"] as $key => $oneLogRow) {
						
						$args = array();
						if ($type == "single") {
							$args["type"] = "single";
						}

						$data["log_rows"][$key] = $this->getLogRowHTMLOutput( $oneLogRow, $args);

					}

				} else {
					
					$data["logRows"] = $logRows;
				}

				break;


			default:
				$data[] = "Nah.";

		}

		wp_send_json_success( $data );

	}

	/**
	 * During the load of info for a logger we want to get a reference
	 * to the untranslated text too, because that's the version we want to store
	 * in the database.
	 */
	public function filter_gettext( $translated_text, $untranslated_text, $domain ) {

		if ( isset( $this->doFilterGettext ) && $this->doFilterGettext ) {

			$this->doFilterGettext_currentLogger->messages[] = array(
				"untranslated_text" => $untranslated_text,
				"translated_text" => $translated_text,
				"domain" => $domain,
				"context" => null,
			);

		}

		return $translated_text;
		
	}

	/**
	 * Store messages with context
	 */
	public function filter_gettext_with_context( $translated_text, $untranslated_text, $context, $domain ) {

		if ( isset( $this->doFilterGettext ) && $this->doFilterGettext ) {

			$this->doFilterGettext_currentLogger->messages[] = array(
				"untranslated_text" => $untranslated_text,
				"translated_text" => $translated_text,
				"domain" => $domain,
				"context" => $context,
			);

		}

		return $translated_text;
		
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
	
		// Capability required to view history = for who will the History page be added
		$this->view_history_capability = "edit_pages";
		$this->view_history_capability = apply_filters("simple_history_view_history_capability", $this->view_history_capability);
		$this->view_history_capability = apply_filters("simple_history/view_history_capability", $this->view_history_capability);

		// Capability required to view settings
		$this->view_settings_capability = "manage_options";
		$this->view_settings_capability = apply_filters("simple_history_view_settings_capability", $this->view_settings_capability);
		$this->view_settings_capability = apply_filters("simple_history/view_settings_capability", $this->view_settings_capability);

		$this->plugin_basename = plugin_basename(dirname(__FILE__) . "/index.php");

		// Add default settings tabs
		$this->arr_settings_tabs = array(
				
			array(
				"slug" => "settings",
				"name" => __("Settings", "simple-history"),
				"function" => array($this, "settings_output_general")
			),
			array(
				"slug" => "log",
				"name" => __("Log", "simple-history"),
				"function" => array($this, "settings_output_log")
			),
			array(
				"slug" => "styles-example",
				"name" => __("Styles example", "simple-history"),
				"function" => array($this, "settings_output_styles_example")
			)

		);

	}

	/**
	 * Load built in loggers from all files in /loggers
	 * and instantiates them
	 */
	private function loadLoggers() {
		
		$loggersDir = dirname(__FILE__) . "/loggers/";

		/**
		 * Filter the directory to load loggers from
		 *
		 * @since 2.0
		 *
		 * @param string $loggersDir Full directory path
		 */
		$loggersDir = apply_filters("simple_history/loggers_dir", $loggersDir);

		$loggersFiles = glob( $loggersDir . "*.php");

		// SimpleLogger.php must be loaded first since the other loggers extend it
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
			
			if ( basename($oneLoggerFile) == "SimpleLogger.php" ) {
				
				// SimpleLogger is already loaded

			} else {

				include_once($oneLoggerFile);

			}

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
			
			if ( ! class_exists($oneLoggerName) ) {
				continue;
			}

			$loggerInstance = new $oneLoggerName($this);

			if ( ! is_subclass_of($loggerInstance, "SimpleLogger") && ! is_a($loggerInstance, "SimpleLogger")  ) {
				continue;
			}

			$loggerInstance->loaded();
			
			// Tell gettext-filter to add untraslated messages
			$this->doFilterGettext = true;
			$this->doFilterGettext_currentLogger = $loggerInstance;

			$loggerInfo = $loggerInstance->getInfo();

			// Un-tell gettext filter
			$this->doFilterGettext = false;
			$this->doFilterGettext_currentLogger = null;

			// Add message slugs and translated text to the message array
			$loopNum = 0;
			foreach ( $loggerInfo["messages"] as $key => $message ) {

				$loggerInstance->messages[ $key ] = $loggerInstance->messages[ $loopNum ];

				// message was not added using __ or _x
				//$loggerInstance->messages[ $key ] = "apa";

				unset( $loggerInstance->messages[ $loopNum ] );
				$loopNum++;

			}

			// Add logger to array of loggers
			$this->instantiatedLoggers[ $loggerInstance->slug ] = array(
				"name" => $loggerInfo["name"],
				"instance" => $loggerInstance
			);

		}

		#sf_d($this->instantiatedLoggers);exit;

	}

	/**
	 * Load built in dropins from all files in /dropins
	 * and instantiates them
	 */
	private function loadDropins() {
		
		$dropinsDir = dirname(__FILE__) . "/dropins/";

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
			
			if ( ! class_exists( $oneDropinName ) ) {
				continue;
			}

			$this->instantiatedDropins[$oneDropinName] = array(
				"name" => $oneDropinName,
				"instance" => new $oneDropinName( $this )
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

		/**
		 * Filter the pager size setting
		 *
		 * @since 2.0
		 *
		 * @param int $pager_size
		 */
		$pager_size = apply_filters("simple_history/pager_size", $pager_size);

		return $pager_size;

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
	
		$pager_size = $this->get_pager_size();

		/**
		 * Filter the pager size setting for the dashboard
		 *
		 * @since 2.0
		 *
		 * @param int $pager_size
		 */
		$pager_size = apply_filters("simple_history/dashboard_pager_size", $pager_size);

		do_action( "simple_history/dashboard/before_gui", $this );

		?>
		<div class="SimpleHistoryGui"
			 data-pager-size='<?php echo $pager_size ?>'
			 ></div>
		<?php

	}
	
	function is_on_our_own_pages($hook = "") {

		$current_screen = get_current_screen();

		if ( $current_screen && $current_screen->base == "settings_page_" . SimpleHistory::SETTINGS_MENU_SLUG ) {
			
			return true;

		} else if ( $current_screen && $current_screen->base == "dashboard_page_simple_history_page" ) {

			return true;

		} else if ( ($hook == "settings_page_" . SimpleHistory::SETTINGS_MENU_SLUG) || ($this->setting_show_on_dashboard() && $hook == "index.php") || ($this->setting_show_as_page() && $hook == "dashboard_page_simple_history_page")) {

			return true;

		} else if ( $current_screen && $current_screen->base == "dashboard" && $this->setting_show_on_dashboard() ) {

			return true;

		}

		return false;
	}

	/**
	 * Enqueue styles and scripts for Simple History but only to our own pages.
	 *
	 * Only adds scripts to pages where the log is shown or the settings page.
	 */
	function enqueue_admin_scripts($hook) {
		
		if ( $this->is_on_our_own_pages() ) {
	
			$plugin_url = plugin_dir_url(__FILE__);
			wp_enqueue_style( "simple_history_styles", $plugin_url . "styles.css", false, SimpleHistory::VERSION );	
			wp_enqueue_script("simple_history_script", $plugin_url . "scripts.js", array("jquery", "backbone"), SimpleHistory::VERSION, true);

			// Load chartist, for charts
			//wp_enqueue_script("chartist", $plugin_url . "/chartist-js/chartist.min.js", array("jquery"), SimpleHistory::VERSION, true);
			//wp_enqueue_style("chartist", $plugin_url . "/chartist-js/chartist.min.css", false, SimpleHistory::VERSION);

			// Load chart.js
			//wp_enqueue_script("chartjs", $plugin_url . "/chartjs/Chart.min.js", array("jquery"), SimpleHistory::VERSION, true);
			
			wp_enqueue_script("select2", $plugin_url . "/js/select2/select2.min.js", array("jquery"));
			wp_enqueue_style("select2", $plugin_url . "/js/select2/select2.css");

			// Translations that we use in JavaScript
			wp_localize_script('simple_history_script', 'simple_history_script_vars', array(				
				'settingsConfirmClearLog' => __("Remove all log items?", 'simple-history'),
				'pagination' => array(
					'goToTheFirstPage' => __("Go to the first page", 'simple-history'),
					'goToThePrevPage' => __("Go to the previous page", 'simple-history'),
					'goToTheNextPage' => __("Go to the next page", 'simple-history'),
					'goToTheLastPage' => __("Go to the last page", 'simple-history'),
					'currentPage' => __("Current page", 'simple-history'),
				),
				"loadLogAPIError" => __("Oups, the log could not be loaded right now.", 'simple-history'),
			));

			// Call plugins adminCSS-method, so they can add their CSS
			foreach ( $this->instantiatedLoggers as $one_logger ) {
				if ( method_exists($one_logger["instance"], "adminCSS" ) ) {
					$one_logger["instance"]->adminCSS();
				}
			}

			/**
		     * Fires when the admin scripts have been enqueued.
		     * Only fires on any of the pages where Simple History is used
		     *
		     * @since 2.0
		     *
		     * @param SimpleHistory $SimpleHistory This class.
		     */
			do_action("simple_history/enqueue_admin_scripts", $this);
		
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
		$table_name_contexts = $wpdb->prefix . SimpleHistory::DBTABLE_CONTEXTS;

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
			
			$db_version_prev = $db_version;
			$db_version = 1;

			SimpleLogger()->debug(
				"Simple History updated its database from version {from_version} to {to_version}",
				array(
					"from_version" => $db_version_prev,
					"to_version" => $db_version
				)
			);
			
			update_option("simple_history_db_version", $db_version);

		} // done pre db ver 1 things


		// If db version is 1 then upgrade to 2
		// Version 2 added the action_description column
		if ( 1 == intval($db_version) ) {

			// Add column for action description in non-translateable free text
			$sql = "ALTER TABLE {$table_name} ADD COLUMN action_description longtext";
			$wpdb->query($sql);

			$db_version_prev = $db_version;
			$db_version = 2;

			SimpleLogger()->debug(
				"Simple History updated its database from version {from_version} to {to_version}",
				array(
					"from_version" => $db_version_prev,
					"to_version" => $db_version
				)
			);

			update_option("simple_history_db_version", $db_version);

		}

		// Check that all options we use are set to their defaults, if they miss value
		// Each option that is missing a value will make a sql call otherwise = unnecessary
		$arr_options = array(
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

		/**
		 * If db_version is 2 then upgrade to 3:
		 * - Add some fields to existing table wp_simple_history_contexts
		 * - Add all new table wp_simple_history_contexts
		 */
		if ( 2 == intval($db_version) ) {

			require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

			// Update old table
			$sql = "
				CREATE TABLE `{$table_name}` (
				  `id` bigint(20) NOT NULL AUTO_INCREMENT,
				  `date` datetime NOT NULL,
				  `logger` varchar(30) DEFAULT NULL,
				  `level` varchar(20) DEFAULT NULL,
				  `message` varchar(255) DEFAULT NULL,
				  `occasionsID` varchar(32) DEFAULT NULL,
				  `type` varchar(16) DEFAULT NULL,
				  `initiator` varchar(16) DEFAULT NULL,
				  `action` varchar(255) NOT NULL,
				  `object_type` varchar(255) NOT NULL,
				  `object_subtype` varchar(255) NOT NULL,
				  `user_id` int(10) NOT NULL,
				  `object_id` int(10) NOT NULL,
				  `object_name` varchar(255) NOT NULL,
				  `action_description` longtext,
				  PRIMARY KEY (`id`)
				) CHARSET=utf8;";
			
			dbDelta($sql);

			// Add context table
			$sql = "
				CREATE TABLE `{$table_name_contexts}` (
				  `context_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				  `history_id` bigint(20) unsigned NOT NULL,
				  `key` varchar(255) DEFAULT NULL,
				  `value` longtext,
				  PRIMARY KEY (`context_id`),
				  KEY `history_id` (`history_id`),
				  KEY `key` (`key`)
				) CHARSET=utf8;
			";

			$wpdb->query($sql);

			$db_version_prev = $db_version;
			$db_version = 3;
			update_option("simple_history_db_version", $db_version);

			// How to translate this?
			SimpleLogger()->debug(
				"Simple History updated its database from version {from_version} to {to_version}",
				array(
					"from_version" => $db_version_prev,
					"to_version" => $db_version
				)
			);

		}
		
	} // end check_for_upgrade
	

	public function registerSettingsTab($arr_tab_settings) {

		$this->arr_settings_tabs[] = $arr_tab_settings;

	}

	public function getSettingsTabs() {

		return $this->arr_settings_tabs;

	}

	/**
	 * Output HTML for the settings page
	 * Called from add_options_page
	 */		 
	function settings_page_output() {
		
		$arr_settings_tabs = $this->getSettingsTabs();

		?>
		<div class="wrap">

			<h2><?php _e("Simple History Settings", "simple-history") ?></h2>
			
			<?php
			$active_tab = isset( $_GET["selected-tab"] ) ? $_GET["selected-tab"] : "settings";
			$settings_base_url = menu_page_url(SimpleHistory::SETTINGS_MENU_SLUG, 0);
			?>

			<h3 class="nav-tab-wrapper">
				<?php
				foreach ( $arr_settings_tabs as $one_tab ) {

					$tab_slug = $one_tab["slug"];
					
					printf(
						'<a href="%3$s" class="nav-tab %4$s">%1$s</a>', 
						$one_tab["name"], // 1
						$tab_slug, // 2
						add_query_arg("selected-tab", $tab_slug, $settings_base_url), // 3
						$active_tab == $tab_slug ? "nav-tab-active" : "" // 4
					);

				}
				?>
			</h3>

			<?php
			
			// Output contents for selected tab
			$arr_active_tab = wp_filter_object_list( $arr_settings_tabs, array("slug" => $active_tab));
			$arr_active_tab = current($arr_active_tab);
			
			// We must have found an active tab and it must have a callable function
			if ( ! $arr_active_tab || ! is_callable( $arr_active_tab["function"] ) ) {
				wp_die( __("No valid callback found", "simple-history") );
			}

			$args = array(
				"arr_active_tab" => $arr_active_tab
			);

			call_user_func_array( $arr_active_tab["function"], $args );

			?>

		</div>
		<?php
		
	}

	public function settings_output_log() {
		
		include( dirname(__FILE__) . "/templates/settings-log.php" );

	}

	public function settings_output_general() {
		
		include( dirname(__FILE__) . "/templates/settings-general.php" );

	}

	public function settings_output_styles_example() {
		
		include( dirname(__FILE__) . "/templates/settings-style-example.php" );

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
		if ( $this->setting_show_as_page() ) {
			
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

		//global $simple_history;

		//$this->purge_db();

		global $wpdb;

		$pager_size = $this->get_pager_size();

		/**
		 * Filter the pager size setting for the history page
		 *
		 * @since 2.0
		 *
		 * @param int $pager_size
		 */
		$pager_size = apply_filters("simple_history/page_pager_size", $pager_size);

		?>

		<div class="wrap SimpleHistoryWrap">
			
			<h2 class="SimpleHistoryPageHeadline">
				<div class="dashicons dashicons-backup SimpleHistoryPageHeadline__icon"></div>
				<!-- <div class="dashicons dashicons-exerpt-view"></div>
				<div class="dashicons dashicons-editor-alignleft"></div> -->
				<?php echo _x("History", 'history page headline', 'simple-history') ?>
			</h2>

			<?php
			/**
		     * Fires before the gui div
		     *
		     * @since 2.0
		     *
		     * @param SimpleHistory $SimpleHistory This class.
		     */
			do_action( "simple_history/history_page/before_gui", $this );
			?>

			<div class="SimpleHistoryGuiWrap">

				<div class="SimpleHistoryGui"
					 data-pager-size='<?php echo $pager_size ?>'
					 ></div>

				<?php

				/**
			     * Fires after the gui div
			     *
			     * @since 2.0
			     *
			     * @param SimpleHistory $SimpleHistory This class.
			     */
				do_action( "simple_history/history_page/after_gui", $this );

				?>
			
			</div>

		</div>

		<?php

	}

	/**
	 * Get history from ajax
	 */
	/*
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
	*/


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
	 *
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

	/**
	 * Return plain text output for a log row
	 * Uses the getLogRowPlainTextOutput of the logger that logged the row
	 * with fallback to SimpleLogger if logger is not available
	 *
	 * @param array $row 
	 * @return string
	 */
	public function getLogRowPlainTextOutput($row) {

		$row_logger = $row->logger;
		$logger = null;
		$row->context = isset( $row->context ) && is_array( $row->context ) ? $row->context : array();

		if ( ! isset( $row->context["_message_key"] ) ) {
			$row->context["_message_key"] = null;
		}
	
		// Fallback to SimpleLogger if no logger exists for row
		if ( ! isset( $this->instantiatedLoggers[$row_logger] ) ) {
			$row_logger = "SimpleLogger";
		}

		$logger = $this->instantiatedLoggers[$row_logger]["instance"];		

		return $logger->getLogRowPlainTextOutput( $row );
		
	}

	/**
	 * Return header output for a log row
	 * Uses the getLogRowHeaderOutput of the logger that logged the row
	 * with fallback to SimpleLogger if logger is not available
	 *
	 * Loggers are discouraged to override this in the loggers, 
	 * because the output should be the same for all items in the gui
	 * 
	 * @param array $row
	 * @return string
	 */
	public function getLogRowHeaderOutput($row) {

		$row_logger = $row->logger;
		$logger = null;
		$row->context = isset( $row->context ) && is_array( $row->context ) ? $row->context : array();
	
		// Fallback to SimpleLogger if no logger exists for row
		if ( ! isset( $this->instantiatedLoggers[$row_logger] ) ) {
			$row_logger = "SimpleLogger";
		}

		$logger = $this->instantiatedLoggers[$row_logger]["instance"];		

		return $logger->getLogRowHeaderOutput( $row );

	}

	/**
	 * 
	 * 
	 * @param array $row
	 * @return string
	 */
	private function getLogRowSenderImageOutput($row) {

		$row_logger = $row->logger;
		$logger = null;
		$row->context = isset( $row->context ) && is_array( $row->context ) ? $row->context : array();
	
		// Fallback to SimpleLogger if no logger exists for row
		if ( ! isset( $this->instantiatedLoggers[$row_logger] ) ) {
			$row_logger = "SimpleLogger";
		}

		$logger = $this->instantiatedLoggers[$row_logger]["instance"];		

		return $logger->getLogRowSenderImageOutput( $row );

	}

	public function getLogRowDetailsOutput($row) {

		$row_logger = $row->logger;
		$logger = null;
		$row->context = isset( $row->context ) && is_array( $row->context ) ? $row->context : array();
	
		// Fallback to SimpleLogger if no logger exists for row
		if ( ! isset( $this->instantiatedLoggers[$row_logger] ) ) {
			$row_logger = "SimpleLogger";
		}

		$logger = $this->instantiatedLoggers[$row_logger]["instance"];		

		return $logger->getLogRowDetailsOutput( $row );

	}

	/**
	 * Works like json_encode, but adds JSON_PRETTY_PRINT if the current php version supports it
	 * i.e. PHP is 5.4.0 or greated
	 * 
	 * @param $value array|object|string|whatever that is json_encode'able
	 */
	public static function json_encode($value) {
		
		return version_compare(PHP_VERSION, '5.4.0') >=0 ? json_encode($value, JSON_PRETTY_PRINT) : json_encode($value);

	}

	/**
	 * Returns the HTML output for a log row, to be used in the GUI/Activity Feed
	 *
	 * @param array $oneLogRow SimpleHistoryLogQuery array with data from SimpleHistoryLogQuery
	 * @return string
	 */
	public function getLogRowHTMLOutput($oneLogRow, $args) {

		$defaults = array(
			"type" => "overview" // or "single" to include more stuff
		);

		$args = wp_parse_args( $args, $defaults );

		$header_html = $this->getLogRowHeaderOutput($oneLogRow);	
		$plain_text_html = $this->getLogRowPlainTextOutput($oneLogRow);
		$sender_image_html = $this->getLogRowSenderImageOutput($oneLogRow);
		
		// Details = for example thumbnail of media
		$details_html = trim( $this->getLogRowDetailsOutput($oneLogRow) );
		if ($details_html) {

			$details_html = sprintf(
				'<div class="SimpleHistoryLogitem__details">%1$s</div>',
				$details_html
			);

		}

		// subsequentOccasions = including the current one
		$occasions_count = $oneLogRow->subsequentOccasions - 1;
		$occasions_html = "";
		if ($occasions_count > 0) {

			$occasions_html = '<div class="SimpleHistoryLogitem__occasions">';
			
			$occasions_html .= '<a href="#" class="SimpleHistoryLogitem__occasionsLink">';
			$occasions_html .= sprintf(
				__('+%1$s more', "simple-history"),
				$occasions_count
			);
			$occasions_html .= '</a>';

			$occasions_html .= '<span class="SimpleHistoryLogitem__occasionsLoading">';
			$occasions_html .= sprintf(
				__('Loading…', "simple-history"),
				$occasions_count
			);
			$occasions_html .= '</span>';

			$occasions_html .= '<span class="SimpleHistoryLogitem__occasionsLoaded">';
			$occasions_html .= sprintf(
				__('Showing %1$s more', "simple-history"),
				$occasions_count
			);
			$occasions_html .= '</span>';
			
			$occasions_html .= '</div>';

		}

		$data_attrs = "";
		$data_attrs .= sprintf(' data-row-id="%1$d" ', $oneLogRow->id );
		$data_attrs .= sprintf(' data-occasions-count="%1$d" ', $occasions_count );
		$data_attrs .= sprintf(' data-occasions-id="%1$s" ', $oneLogRow->occasionsID );
		
		// If type is single then include more details
		$more_details_html = "";
		if ( $args["type"] == "single" ) {

			$more_details_html .= sprintf('<h2 class="SimpleHistoryLogitem__moreDetailsHeadline">%1$s</h2>', __("Context data", "simple-history"));
			$more_details_html .= "<p>" . __("This is potentially useful meta data that a logger have saved.", "simple-history") . "</p>";
			$more_details_html .= "<table class='SimpleHistoryLogitem__moreDetailsContext'>";
			$more_details_html .= sprintf(
				'<tr>
					<th>%1$s</th>
					<th>%2$s</th>
				</tr>',
				"Key",
				"Value"
			);

			foreach ($oneLogRow->context as $contextKey => $contextVal) {

				$more_details_html .= sprintf(
					'<tr>
						<td>%1$s</td>
						<td>%2$s</td>
					</tr>',
					esc_html( $contextKey ),
					esc_html( $contextVal )
				);

			}
			$more_details_html .= "</table>";

			$more_details_html = sprintf(
				'<div class="SimpleHistoryLogitem__moreDetails">%1$s</div>',
				$more_details_html
			);

		}

		$class_sender = "";
		if (isset($oneLogRow->initiator) && !empty($oneLogRow->initiator)) {
			$class_sender .= "SimpleHistoryLogitem--initiator-" . esc_attr($oneLogRow->initiator);
		}

		/*$level_html = sprintf(
			'<span class="SimpleHistoryLogitem--logleveltag SimpleHistoryLogitem--logleveltag-%1$s">%1$s</span>',
			$row->level
		);*/

		// Always append the log level tag
		$log_level_tag_html = sprintf(
			' <span class="SimpleHistoryLogitem--logleveltag SimpleHistoryLogitem--logleveltag-%1$s">%1$s</span>',
			$oneLogRow->level
		);

		$plain_text_html .= $log_level_tag_html;

		// Generate the HTML output for a row
		$output = sprintf(
			'
				<li %8$s class="SimpleHistoryLogitem SimpleHistoryLogitem--loglevel-%5$s SimpleHistoryLogitem--logger-%7$s %10$s">
					<div class="SimpleHistoryLogitem__firstcol">
						<div class="SimpleHistoryLogitem__senderImage">%3$s</div>
					</div>
					<div class="SimpleHistoryLogitem__secondcol">
						<div class="SimpleHistoryLogitem__header">%1$s</div>
						<div class="SimpleHistoryLogitem__text">%2$s</div>
						%4$s
						%6$s
						%9$s
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
			$class_sender // 10
		);

		// Get the main message row.
		// Should be as plain as possible, like plain text 
		// but with links to for example users and posts
		#SimpleLoggerFormatter::getRowTextOutput($oneLogRow);

		// Get detailed HTML-based output
		// May include images, lists, any cool stuff needed to view
		#SimpleLoggerFormatter::getRowHTMLOutput($oneLogRow);

		return trim($output);

	}

	public function getInstantiatedLoggers() {

		return $this->instantiatedLoggers;

	}

	public function getInstantiatedLoggerBySlug($slug = "") {
		
		if (empty( $slug )) {
			return false;
		}
		
		foreach ($this->getInstantiatedLoggers() as $one_logger) {
			
			if ( $slug == $one_logger["instance"]->slug ) {
				return $one_logger["instance"];
			}

		}

		return false;

	}

	/**
	 * Check which loggers a user has the right to read and return an array
	 * with all loggers they are allowed to read
	 *
	 * @param int $user_id Id of user to get loggers for. Defaults to current user id.
	 * @param string $format format to return loggers in. Default is array.
	 * @return array
	 */
	public function getLoggersThatUserCanRead($user_id = "", $format = "array") {

		$arr_loggers_user_can_view = array();

		if ( ! is_numeric($user_id) ) {
			$user_id = get_current_user_id();
		}

		$loggers = $this->getInstantiatedLoggers();
		foreach ($loggers as $one_logger) {

			$logger_capability = $one_logger["instance"]->getCapability();

			//$arr_loggers_user_can_view = apply_filters("simple_history/loggers_user_can_read", $user_id, $arr_loggers_user_can_view);
			$user_can_read_logger = user_can( $user_id, $logger_capability );			
			$user_can_read_logger = apply_filters("simple_history/loggers_user_can_read/can_read_single_logger", $user_can_read_logger, $one_logger["instance"], $user_id);

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
	     * @param int user_id ID of user to check read capability for
	     */
		$arr_loggers_user_can_view = apply_filters("simple_history/loggers_user_can_read", $arr_loggers_user_can_view, $user_id);

		// just return array with slugs in parenthesis suitable for sql-where
		if ( "sql" == $format ) {

			$str_return = "(";
			
			foreach ($arr_loggers_user_can_view as $one_logger) {
				
				$str_return .= sprintf(
					'"%1$s", ',
					$one_logger["instance"]->slug
				);

			}
			
			$str_return = rtrim($str_return, " ,");
			$str_return .= ")";

			return $str_return;

		}


		return $arr_loggers_user_can_view;

	}

	/**
	 * Retrieve the avatar for a user who provided a user ID or email address.
	 * A modified version of the function that comes with WordPress, but we 
	 * want to allow/show gravatars even if they are disabled in discussion settings
	 *
	 * @since 2.0
	 *
	 * @param string $email email address
	 * @param int $size Size of the avatar image
	 * @param string $default URL to a default image to use if no avatar is available
	 * @param string $alt Alternative text to use in image tag. Defaults to blank
	 * @return string <img> tag for the user's avatar
	*/
	function get_avatar( $email, $size = '96', $default = '', $alt = false ) {

		if ( false === $alt)
			$safe_alt = '';
		else
			$safe_alt = esc_attr( $alt );

		if ( !is_numeric($size) )
			$size = '96';

		if ( empty($default) ) {
			$avatar_default = get_option('avatar_default');
			if ( empty($avatar_default) )
				$default = 'mystery';
			else
				$default = $avatar_default;
		}

		if ( !empty($email) )
			$email_hash = md5( strtolower( trim( $email ) ) );

		if ( is_ssl() ) {
			$host = 'https://secure.gravatar.com';
		} else {
			if ( !empty($email) )
				$host = sprintf( "http://%d.gravatar.com", ( hexdec( $email_hash[0] ) % 2 ) );
			else
				$host = 'http://0.gravatar.com';
		}

		if ( 'mystery' == $default )
			$default = "$host/avatar/ad516503a11cd5ca435acc9bb6523536?s={$size}"; // ad516503a11cd5ca435acc9bb6523536 == md5('unknown@gravatar.com')
		elseif ( 'blank' == $default )
			$default = $email ? 'blank' : includes_url( 'images/blank.gif' );
		elseif ( !empty($email) && 'gravatar_default' == $default )
			$default = '';
		elseif ( 'gravatar_default' == $default )
			$default = "$host/avatar/?s={$size}";
		elseif ( empty($email) )
			$default = "$host/avatar/?d=$default&amp;s={$size}";
		elseif ( strpos($default, 'http://') === 0 )
			$default = add_query_arg( 's', $size, $default );

		if ( !empty($email) ) {
			$out = "$host/avatar/";
			$out .= $email_hash;
			$out .= '?s='.$size;
			$out .= '&amp;d=' . urlencode( $default );

			$rating = get_option('avatar_rating');
			if ( !empty( $rating ) )
				$out .= "&amp;r={$rating}";

			$out = str_replace( '&#038;', '&amp;', esc_url( $out ) );
			$avatar = "<img alt='{$safe_alt}' src='{$out}' class='avatar avatar-{$size} photo' height='{$size}' width='{$size}' />";
		} else {
			$out = esc_url( $default );
			$avatar = "<img alt='{$safe_alt}' src='{$out}' class='avatar avatar-{$size} photo avatar-default' height='{$size}' width='{$size}' />";
		}

		return $avatar;
	}

	/**
	 * Quick stats above the log
	 * Uses filter "simple_history/history_page/before_gui" to output its contents
	 */
	public function output_quick_stats() {
		
		global $wpdb;

		$logQuery = new SimpleHistoryLogQuery();
		$logResults = $logQuery->query(array(
			"posts_per_page" => 1,
			"date_from" => strtotime("today")
		));

		$sql_loggers_in = $this->getLoggersThatUserCanRead(get_current_user_id(), "sql");
		$sql_users_today = sprintf('
			SELECT 
				DISTINCT(c.value) AS user_id
				#h.id, h.logger, h.level, h.initiator, h.date
				FROM wp_simple_history AS h
			INNER JOIN wp_simple_history_contexts AS c 
			ON c.history_id = h.id AND c.key = "_user_id"
			WHERE 
				initiator = "wp_user"
				AND logger IN %1$s
				AND date > "%2$s"
			', 
			$sql_loggers_in,
			date("Y-m-d H:i", strtotime("today"))
		);

		$results_users_today = $wpdb->get_results($sql_users_today);
		
		?>
		<div class="SimpleHistoryQuickStats">
			<p>
				<?php
				
				$msg_tmpl = "";

				if ( $logResults["total_row_count"] == 0 ) {
					
					$msg_tmpl = __("No events today so far.", "simple-history");

				} elseif ( $logResults["total_row_count"] == 1 ) {

					$msg_tmpl = __('%1$d event today from one user.', "simple-history");

				} elseif ( $logResults["total_row_count"] > 0 && sizeof( $results_users_today ) > 1 ) {

					$msg_tmpl = __('%1$d events today from %2$d users.', "simple-history");

				} elseif ( $logResults["total_row_count"] > 0 && sizeof( $results_users_today ) == 1 ) {
					
					$msg_tmpl = __('%1$d events today from one user.', "simple-history");						

				}

				// only show stats if we have something to output
				if ( $msg_tmpl ) {

					printf(
						$msg_tmpl,
						$logResults["total_row_count"],
						sizeof( $results_users_today )
					);

					// Space between texts
					/*
					echo " ";

					// http://playground-root.ep/wp-admin/options-general.php?page=simple_history_settings_menu_slug&selected-tab=stats
					printf(
						'<a href="%1$s">View more stats</a>.',
						add_query_arg("selected-tab", "stats", menu_page_url(SimpleHistory::SETTINGS_MENU_SLUG, 0))
					);
					*/

				}
	
				?>
			</p>
		</div>	
		<?php

	}

} // class
