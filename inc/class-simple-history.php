<?php
namespace Simple_History;

use Simple_History\Loggers;
use Simple_History\Loggers\Simple_Logger;
use Simple_History\Dropins;
use Simple_History\Helpers;

/**
 * Main class for Simple History.
 *
 * This is used to init the plugin.
 */
class Simple_History {
    const NAME = 'Simple History';

    /**
     * For singleton.
     *
     * @var Simple_History
     * @see get_instance()
     */
    private static $instance;

    /**
     * Array with external logger classnames to load.
     *
     * @var array
     */
    private $external_loggers;

    /**
     * Array with external dropins to load.
     *
     * @var array
     */
    private $external_dropins;

    /**
     * Array with all instantiated loggers.
     *
     * @var array
     */
    private $instantiated_loggers;

    /**
     * Array with all instantiated dropins.
     *
     * @var array
     */
    private $instantiated_dropins;

    /**
     * Bool if gettext filter function should be active
     * Should only be active during the load of a logger
     *
     * @var bool
     */
    private $do_filter_gettext = false;

    /**
     * Used by gettext filter to temporarily store current logger.
     *
     * @var Logger
     */
    private $do_filter_gettext_current_logger;

    /**
     * Used to store latest translations used by __()
     * Required to automagically determine original text and text domain
     * for calls like this `SimpleLogger()->log( __("My translated message") );`
     *
     * @var array
     */
    public $gettext_latest_translations = array();

    /**
     * All registered settings tabs.
     *
     * @var array
     */
    private $arr_settings_tabs = array();

    const DBTABLE = 'simple_history';
    const DBTABLE_CONTEXTS = 'simple_history_contexts';

    /** @var string $dbtable Full database name, i.e. wp_simple_history */
    public static $dbtable;

    /** @var string $dbtable Full database name for contexts, i.e. wp_simple_history_contexts */
    public static $dbtable_contexts;

    /** @var string $plugin_basename */
    public $plugin_basename;

    /** Slug for the settings menu */
    const SETTINGS_MENU_SLUG = 'simple_history_settings_menu_slug';

    /** Slug for the settings menu */
    const SETTINGS_GENERAL_OPTION_GROUP = 'simple_history_settings_group';

    /** ID for the general settings section */
    const SETTINGS_SECTION_GENERAL_ID = 'simple_history_settings_section_general';

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
         * @param Simple_History $SimpleHistory This class.
         */
        do_action( 'simple_history/before_init', $this );

        $this->setup_variables();

        // Actions and filters, ordered by order specified in codex: http://codex.wordpress.org/Plugin_API/Action_Reference
        add_action( 'after_setup_theme', array( $this, 'load_plugin_textdomain' ) );
        add_action( 'after_setup_theme', array( $this, 'add_default_settings_tabs' ) );

        // Plugins and dropins are loaded using the "after_setup_theme" filter so
        // themes can use filters to modify the loading of them.
        // The drawback with this is that for example logouts done when plugins like
        // iThemes Security is installed is not logged, because those plugins fire wp_logout()
        // using filter "plugins_loaded", i.e. before simple history has loaded its filters.
        add_action( 'after_setup_theme', array( $this, 'load_loggers' ) );
        add_action( 'after_setup_theme', array( $this, 'load_dropins' ) );

        // Run before loading of loggers and before menu items are added.
        add_action( 'after_setup_theme', array( $this, 'check_for_upgrade' ), 5 );

        add_action( 'after_setup_theme', array( $this, 'setup_cron' ) );

        // Filters and actions not called during regular boot.
        add_filter( 'gettext', array( $this, 'filter_gettext' ), 20, 3 );
        add_filter( 'gettext_with_context', array( $this, 'filter_gettext_with_context' ), 20, 4 );
        add_filter( 'gettext', array( $this, 'filter_gettext_store_latest_translations' ), 10, 3 );

        add_action( 'admin_bar_menu', array( $this, 'add_admin_bar_network_menu_item' ), 40 );
        add_action( 'admin_bar_menu', array( $this, 'add_admin_bar_menu_item' ), 40 );

        /**
         * Filter that is used to log things, without the need to check that simple history is available
         * i.e. you can have simple history activated and log things and then you can disable the plugin
         * and no errors will occur
         *
         * Usage:
         * apply_filters("simple_history_log", "This is the log message");
         * apply_filters("simple_history_log", "This is the log message with some extra data/info", ["extraThing1" => $variableWIihThing]);
         * apply_filters("simple_history_log", "This is the log message with severity debug", null, "debug");
         * apply_filters("simple_history_log", "This is the log message with severity debug and with some extra info/data logged", ["userData" => $userData, "shoppingCartDebugData" => $shopDebugData], "debug",);
         *
         * @since 2.13
         */
        add_filter( 'simple_history_log', array( $this, 'on_filter_simple_history_log' ), 10, 3 );

        /**
         * Filter to log with specific log level, for example:
         * apply_filters('simple_history_log_debug', 'My debug message');
         * apply_filters('simple_history_log_warning', 'My warning message');
         *
         * @since 2.17
         */
        add_filter( 'simple_history_log_emergency', array( $this, 'on_filter_simple_history_log_emergency' ), 10, 3 );
        add_filter( 'simple_history_log_alert', array( $this, 'on_filter_simple_history_log_alert' ), 10, 2 );
        add_filter( 'simple_history_log_critical', array( $this, 'on_filter_simple_history_log_critical' ), 10, 2 );
        add_filter( 'simple_history_log_error', array( $this, 'on_filter_simple_history_log_error' ), 10, 2 );
        add_filter( 'simple_history_log_warning', array( $this, 'on_filter_simple_history_log_warning' ), 10, 2 );
        add_filter( 'simple_history_log_notice', array( $this, 'on_filter_simple_history_log_notice' ), 10, 2 );
        add_filter( 'simple_history_log_info', array( $this, 'on_filter_simple_history_log_info' ), 10, 2 );
        add_filter( 'simple_history_log_debug', array( $this, 'on_filter_simple_history_log_debug' ), 10, 2 );

        $this->add_pause_and_resume_actions();

        if ( is_admin() ) {
            $this->add_admin_actions();
        }

        /**
         * Fires after Simple History has done it's init stuff
         *
         * @since 2.0
         *
         * @param Simple_History $SimpleHistory This class.
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
     * Log a message
     *
     * Function called when running filter "simple_history_log"
     *
     * @since 2.13
     * @param string $message The message to log.
     * @param array  $context Optional context to add to the logged data.
     * @param string $level The log level. Must be one of the existing ones. Defaults to "info".
     */
    public function on_filter_simple_history_log( $message = null, $context = null, $level = 'info' ) {
        SimpleLogger()->log( $level, $message, $context );
    }

    /**
     * Log a message, triggered by filter 'on_filter_simple_history_log_emergency'.
     *
     * @param string $message The message to log.
     * @param array  $context The context (optional).
     */
    public function on_filter_simple_history_log_emergency( $message = null, $context = null ) {
        SimpleLogger()->log( 'emergency', $message, $context );
    }

    /**
     * Log a message, triggered by filter 'on_filter_simple_history_log_alert'.
     *
     * @param string $message The message to log.
     * @param array  $context The context (optional).
     */
    public function on_filter_simple_history_log_alert( $message = null, $context = null ) {
        SimpleLogger()->log( 'alert', $message, $context );
    }

    /**
     * Log a message, triggered by filter 'on_filter_simple_history_log_critical'.
     *
     * @param string $message The message to log.
     * @param array  $context The context (optional).
     */
    public function on_filter_simple_history_log_critical( $message = null, $context = null ) {
        SimpleLogger()->log( 'critical', $message, $context );
    }

    /**
     * Log a message, triggered by filter 'on_filter_simple_history_log_error'.
     *
     * @param string $message The message to log.
     * @param array  $context The context (optional).
     */
    public function on_filter_simple_history_log_error( $message = null, $context = null ) {
        SimpleLogger()->log( 'error', $message, $context );
    }

    /**
     * Log a message, triggered by filter 'on_filter_simple_history_log_warning'.
     *
     * @param string $message The message to log.
     * @param array  $context The context (optional).
     */
    public function on_filter_simple_history_log_warning( $message = null, $context = null ) {
        SimpleLogger()->log( 'warning', $message, $context );
    }

    /**
     * Log a message, triggered by filter 'on_filter_simple_history_log_notice'.
     *
     * @param string $message The message to log.
     * @param array  $context The context (optional).
     */
    public function on_filter_simple_history_log_notice( $message = null, $context = null ) {
        SimpleLogger()->log( 'notice', $message, $context );
    }

    /**
     * Log a message, triggered by filter 'on_filter_simple_history_log_info'.
     *
     * @param string $message The message to log.
     * @param array  $context The context (optional).
     */
    public function on_filter_simple_history_log_info( $message = null, $context = null ) {
        SimpleLogger()->log( 'info', $message, $context );
    }

    /**
     * Log a message, triggered by filter 'on_filter_simple_history_log_debug'.
     *
     * @param string $message The message to log.
     * @param array  $context The context (optional).
     */
    public function on_filter_simple_history_log_debug( $message = null, $context = null ) {
        SimpleLogger()->log( 'debug', $message, $context );
    }

    /**
     * @since 2.5.2
     */
    private function add_admin_actions() {
        add_action( 'admin_menu', array( $this, 'add_admin_pages' ) );
        add_action( 'admin_menu', array( $this, 'add_settings' ) );

        add_action( 'admin_footer', array( $this, 'add_js_templates' ) );

        add_action( 'wp_dashboard_setup', array( $this, 'add_dashboard_widget' ) );

        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );

        add_action( 'admin_head', array( $this, 'on_admin_head' ) );
        add_action( 'admin_footer', array( $this, 'on_admin_footer' ) );

        add_action( 'simple_history/history_page/before_gui', array( $this, 'output_quick_stats' ) );
        add_action( 'simple_history/dashboard/before_gui', array( $this, 'output_quick_stats' ) );

        add_action( 'wp_ajax_simple_history_api', array( $this, 'api' ) );

        add_filter( 'plugin_action_links_simple-history/index.php', array( $this, 'plugin_action_links' ), 10, 4 );
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
         * @param bool Add item
         */
        $add_items = apply_filters( 'simple_history/add_admin_bar_network_menu_item', true );

        if ( ! $add_items ) {
            return;
        }

        // Don't show for logged out users or single site mode.
        if ( ! is_user_logged_in() || ! is_multisite() ) {
            return;
        }

        // Show only when the user has at least one site, or they're a super admin.
        if ( count( $wp_admin_bar->user->blogs ) < 1 && ! is_super_admin() ) {
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

        /**
         * `menu_page_url()` is defined in the WordPress Plugin Administration API,
         * which is not loaded here by default
         *
         * ditto for `is_plugin_active()`
         */
        require_once ABSPATH . 'wp-admin/includes/plugin.php';

        foreach ( (array) $wp_admin_bar->user->blogs as $blog ) {
            switch_to_blog( $blog->userblog_id );

            if ( is_plugin_active( SIMPLE_HISTORY_BASENAME ) ) {
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
         * @param bool Add item
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

        /* menu_page_url() and is_plugin_active()is defined in the WordPress Plugin Administration API, which is not loaded here by default */
        require_once ABSPATH . 'wp-admin/includes/plugin.php';

        $menu_id = 'simple-history-view-history';
        $parent_menu_id = 'site-name';
        $url = admin_url( apply_filters( 'simple_history/admin_location', 'index' ) . '.php?page=simple_history_page' );

        $args = array(
            'id' => $menu_id,
            'parent' => $parent_menu_id,
            'title' => _x( 'Simple History', 'Admin bar name', 'simple-history' ),
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

    public function filter_gettext_store_latest_translations( $translation, $text, $domain ) {
        // Check that translation is a string or integer, i.ex. the valid values for an array key
        if ( ! is_string( $translation ) || ! is_integer( $translation ) ) {
            return $translation;
        }

        $array_max_size = 5;

        // Keep a listing of the n latest translation
        // when SimpleLogger->log() is called from anywhere we can then search for the
        // translated string among our n latest things and find it there, if it's translated
        // global $sh_latest_translations;
        $sh_latest_translations = $this->gettext_latest_translations;

        $sh_latest_translations[ $translation ] = array(
            'translation' => $translation,
            'text' => $text,
            'domain' => $domain,
        );

        $arr_length = count( $sh_latest_translations );
        if ( $arr_length > $array_max_size ) {
            $sh_latest_translations = array_slice( $sh_latest_translations, $arr_length - $array_max_size );
        }

        $this->gettext_latest_translations = $sh_latest_translations;

        return $translation;
    }

    public function setup_cron() {
        add_filter( 'simple_history/maybe_purge_db', array( $this, 'maybe_purge_db' ) );

        if ( ! wp_next_scheduled( 'simple_history/maybe_purge_db' ) ) {
            wp_schedule_event( time(), 'daily', 'simple_history/maybe_purge_db' );
        }
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
             * @param Simple_History $SimpleHistory This class.
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
             * @param Simple_History $SimpleHistory This class.
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
                <li class="SimpleHistoryLogitem SimpleHistoryLogitem--occasion SimpleHistoryLogitem--occasion-tooMany">
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

        $data = array();

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
                    $data['log_rows_raw'] = array();

                    foreach ( $data['log_rows'] as $key => $oneLogRow ) {
                        $args = array();
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
     * During the load of info for a logger we want to get a reference
     * to the untranslated text too, because that's the version we want to store
     * in the database.
     */
    public function filter_gettext( $translated_text, $untranslated_text, $domain ) {
        if ( isset( $this->do_filter_gettext ) && $this->do_filter_gettext ) {
            $this->do_filter_gettext_current_logger->messages[] = array(
                'untranslated_text' => $untranslated_text,
                'translated_text' => $translated_text,
                'domain' => $domain,
                'context' => null,
            );
        }

        return $translated_text;
    }

    /**
     * Store messages with context
     */
    public function filter_gettext_with_context( $translated_text, $untranslated_text, $context, $domain ) {
        if ( isset( $this->do_filter_gettext ) && $this->do_filter_gettext ) {
            $this->do_filter_gettext_current_logger->messages[] = array(
                'untranslated_text' => $untranslated_text,
                'translated_text' => $translated_text,
                'domain' => $domain,
                'context' => $context,
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
        $locale = apply_filters( 'plugin_locale', get_locale(), $domain ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
        load_textdomain( $domain, WP_LANG_DIR . '/simple-history/' . $domain . '-' . $locale . '.mo' );
        load_plugin_textdomain( $domain, false, dirname( $this->plugin_basename ) . '/languages/' );
    }

    /**
     * Setup variables and things
     */
    public function setup_variables() {
        $this->external_loggers = array();
        $this->external_dropins = array();
        $this->instantiated_loggers = array();
        $this->instantiated_dropins = array();
        $this->plugin_basename = SIMPLE_HISTORY_BASENAME;

        global $wpdb;
        $this::$dbtable = $wpdb->prefix . self::DBTABLE;
        $this::$dbtable_contexts = $wpdb->prefix . self::DBTABLE_CONTEXTS;
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
     * Adds default tabs to settings
     */
    public function add_default_settings_tabs() {
        // Add default settings tabs
        $this->arr_settings_tabs = array(
            array(
                'slug' => 'settings',
                'name' => __( 'Settings', 'simple-history' ),
                'function' => array( $this, 'settings_output_general' ),
            ),
        );

        if ( defined( 'SIMPLE_HISTORY_DEV' ) && constant( 'SIMPLE_HISTORY_DEV' ) ) {
            $arr_dev_tabs = array(
                array(
                    'slug' => 'log',
                    'name' => __( 'Log (debug)', 'simple-history' ),
                    'function' => array( $this, 'settings_output_log' ),
                ),
                array(
                    'slug' => 'styles-example',
                    'name' => __( 'Styles example (debug)', 'simple-history' ),
                    'function' => array( $this, 'settings_output_styles_example' ),
                ),
            );

            $this->arr_settings_tabs = array_merge( $this->arr_settings_tabs, $arr_dev_tabs );
        }
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
     * Instantiates built in loggers.
     */
    public function load_loggers() {
        // Bail if we are not in filter after_setup_theme,
        // i.e. we are probably calling SimpleLogger() early.
        // TODO: Test if this is still needed, after adding autoloading of classes.
        if ( ! doing_action( 'after_setup_theme' ) ) {
            return;
        }

        $arr_loggers_to_instantiate = $this->get_core_loggers();

        /**
         * Fires after the list of loggers to load are populated.
         *
         * Can for example be used by plugin to load their own custom loggers.
         *
         * See register_logger() for more info.
         *
         * @since 2.1
         *
         * @param Simple_History $this Simple History instance.
         */
        do_action( 'simple_history/add_custom_logger', $this );

        $arr_loggers_to_instantiate = array_merge( $arr_loggers_to_instantiate, $this->external_loggers );

        /**
         * Filter the array with class names of loggers to instantiate.
         *
         * Array
         * (
         *  [0] => SimpleHistory\Loggers\SimpleUserLogger
         *  [1] => SimpleHistory\Loggers\SimplePostLogger
         *   ...
         * )
         *
         * @since 2.0
         *
         * @param array $arr_loggers_to_instantiate Array with class names
         */
        $arr_loggers_to_instantiate = apply_filters(
            'simple_history/loggers_to_instantiate',
            $arr_loggers_to_instantiate
        );

        // Instantiate each logger.
        foreach ( $arr_loggers_to_instantiate as $one_logger_class ) {
            $is_valid_logger_subclass = is_subclass_of( $one_logger_class, 'Simple_History\Loggers\Logger' );
            $is_valid_old_simplelogger_subclass = is_subclass_of( $one_logger_class, 'SimpleLogger' );

            if ( ! $is_valid_logger_subclass && ! $is_valid_old_simplelogger_subclass ) {
                continue;
            }

            /** @var Simple_Logger $logger_instance */
            $logger_instance = new $one_logger_class( $this );
            $logger_instance->loaded();

            // Tell gettext-filter to add untranslated messages.
            // TODO: Filter for texttext is called on every gettext, we should improve
            // this by adding filter before this loop and then removing the filter,
            // so filter is only called for a short period of time.
            $this->do_filter_gettext = true;
            $this->do_filter_gettext_current_logger = $logger_instance;

            $logger_info = $logger_instance->get_info();

            // Check so no logger has a logger slug with more than 30 chars,
            // because db column is only 30 chars.
            if ( strlen( $logger_instance->get_slug() ) > 30 ) {
                _doing_it_wrong(
                    __METHOD__,
                    sprintf(
                        esc_html( __( 'A logger slug can be max 30 chars long. Slug %1$s of logger %2$s is to long.', 'simple-history' ) ),
                        esc_html( $logger_instance->get_slug() ),
                        esc_html( $logger_instance->get_info_value_by_key( 'name' ) )
                    ),
                    '3.0'
                );
            }

            // Check that logger has a slug set.
            if ( empty( $logger_instance->get_slug() ) ) {
                _doing_it_wrong(
                    __METHOD__,
                    sprintf(
                        esc_html( __( 'A logger is missing a slug.', 'simple-history' ) ),
                    ),
                    '4.0'
                );
            }

            // Check that logger has a name set.
            if ( ! isset( $logger_info['name'] ) ) {
                _doing_it_wrong(
                    __METHOD__,
                    sprintf(
                        esc_html( __( 'Logger %1$s is missing a name.', 'simple-history' ) ),
                        esc_html( $logger_instance->get_slug() ),
                    ),
                    '4.0'
                );
            }

            // Un-tell gettext filter.
            $this->do_filter_gettext = false;
            $this->do_filter_gettext_current_logger = null;

            // LoggerInfo contains all messages, both translated an not, by key.
            // Add messages to the loggerInstance.
            $arr_messages_by_message_key = array();

            // Check that required content in messages array exist.
            if ( isset( $logger_info['messages'] ) && is_array( $logger_info['messages'] ) ) {
                foreach ( $logger_info['messages'] as $message_key => $message_translated ) {
                    // Find message in array with both translated and non translated strings.
                    foreach ( $logger_instance->messages as $one_message_with_translation_info ) {
                        if ( $message_translated == $one_message_with_translation_info['translated_text'] ) {
                            $arr_messages_by_message_key[ $message_key ] = $one_message_with_translation_info;
                            continue;
                        }
                    }
                }
            }

            $logger_instance->messages = $arr_messages_by_message_key;

            $this->instantiated_loggers[ $logger_instance->get_slug() ] = array(
                'name' => $logger_instance->get_info_value_by_key( 'name' ),
                'instance' => $logger_instance,
            );
        } // End foreach().

        /**
         * Fired when all loggers are instantiated.
         *
         * @deprecated 3.0 Use action `simple_history/loggers/instantiated` instead.
         *
         * @since 3.0
         */
        do_action( 'simple_history/loggers_loaded' );

        /**
         * Fired when all loggers are instantiated.
         *
         * @since 4.0
         */
        do_action( 'simple_history/loggers/instantiated', $this );
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
     * Instantiates built in dropins.
     */
    public function load_dropins() {
        $dropins_to_instantiate = $this->get_core_dropins();

        /**
         * Fires after the list of dropins to load are populated.
         * Can for example be used by dropins can to add their own custom loggers.
         *
         * See register_dropin() for more info.
         *
         * @since 2.3.2
         *
         * @param Simple_History $this Simple History instance.
         */
        do_action( 'simple_history/add_custom_dropin', $this );

        $dropins_to_instantiate = array_merge( $dropins_to_instantiate, $this->external_dropins );

        /**
         * Filter the array with dropin classnames to instantiate.
         *
         * @since 3.0
         *
         * @param array $dropins_to_instantiate Array with dropin class names.
         */
        $dropins_to_instantiate = apply_filters( 'simple_history/dropins_to_instantiate', $dropins_to_instantiate );

        // $one_dropin_class is full namespaced class, i.e. 'SimpleHistory\Dropins\SimpleHistoryRSSDropin'.
        foreach ( $dropins_to_instantiate as $one_dropin_class ) {
            $instantiate_dropin = true;

            // Check that dropin exists.
            if ( ! class_exists( $one_dropin_class ) ) {
                _doing_it_wrong(
                    __METHOD__,
                    sprintf(
                        esc_html( __( 'A dropin was not found. Classname was "%1$s".', 'simple-history' ) ),
                        esc_html( $one_dropin_class ),
                    ),
                    '4.0'
                );

                continue;
            }

            $dropin_short_name = ( new \ReflectionClass( $one_dropin_class ) )->getShortName();

            /**
             * Filter to completely skip instantiate a dropin.
             *
             * Complete filter name will be something like
             * `simple_history/dropin/instantiate_SimpleHistoryRSSDropin`
             *
             * @example Do not instantiate dropin SimpleHistoryRSSDropin.
             *
             * ```php
             * add_filter( 'simple_history/dropin/instantiate_SimpleHistoryRSSDropin', '__return_false' );
             * ```
             *
             * @since 4.0
             *
             * @param bool if to load the dropin. return false to not load it.
             */
            $instantiate_dropin = apply_filters( "simple_history/dropin/instantiate_{$dropin_short_name}", $instantiate_dropin );

            /**
             * Filter to completely skip loading of a dropin.
             *
             * @since 4.0
             *
             * @param bool $instantiate_dropin if to load the dropin. return false to not load it.
             * @param string $dropin_short_name slug of dropin, i.e. "SimpleHistoryRSSDropin"
             * @param string $one_dropin_class fully qualified name of class, i.e. "SimpleHistory\Dropins\SimpleHistoryRSSDropin"
             */
            $instantiate_dropin = apply_filters( 'simple_history/dropin/instantiate', $instantiate_dropin, $dropin_short_name, $one_dropin_class );

            // Bail if dropin should not be instantiated.
            if ( ! $instantiate_dropin ) {
                continue;
            }

            // New dropins must extend Simple_History\Dropins\Dropin,
            // but old dropins are not extending anything,
            // so that's why we do not check type of class, like we do
            // with plugins.
            $dropin_instance = new $one_dropin_class( $this );

            if ( method_exists( $dropin_instance, 'loaded' ) ) {
                $dropin_instance->loaded();
            }

            $this->instantiated_dropins[ $dropin_short_name ] = array(
                'name' => $dropin_short_name,
                'instance' => $dropin_instance,
            );
        } // End foreach().

        /**
         * Fires after all dropins are instantiated.
         * @since 3.0
         *
         * @param Simple_History $this Simple History instance.
         */
        do_action( 'simple_history/dropins/instantiated', $this );

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
     * Gets the pager size,
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
            $actions = array();
        } elseif ( is_string( $actions ) ) {
            // Convert the string (which it might've been retrieved as) to an array for future use as an array
            $actions = array( $actions );
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
             * @param bool Show the page or not
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

    public function is_on_our_own_pages( $hook = '' ) {
        $current_screen = get_current_screen();

        $basePrefix = apply_filters( 'simple_history/admin_location', 'index' );
        $basePrefix = $basePrefix === 'index' ? 'dashboard' : $basePrefix;

        if ( $current_screen && $current_screen->base == 'settings_page_' . self::SETTINGS_MENU_SLUG ) {
            return true;
        } elseif ( $current_screen && $current_screen->base == $basePrefix . '_page_simple_history_page' ) {
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
             * @param Simple_History $SimpleHistory This class.
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
        if ( false === $db_version || intval( $db_version ) == 0 ) {
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
        if ( 1 == intval( $db_version ) ) {
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
        if ( 2 == intval( $db_version ) ) {
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
        if ( 3 == intval( $db_version ) ) {
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
        if ( 4 == intval( $db_version ) ) {
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
        // $db_data_exists = false;
        $pluginLogger = $this->get_instantiated_logger_by_slug( 'SimplePluginLogger' );
        if ( $pluginLogger ) {
            // Add plugin installed message
            $context = array(
                'plugin_name' => 'Simple History',
                'plugin_description' =>
                    'Plugin that logs various things that occur in WordPress and then presents those events in a very nice GUI.',
                'plugin_url' => 'https://simple-history.com',
                'plugin_version' => SIMPLE_HISTORY_VERSION,
                'plugin_author' => 'Pär Thernström',
            );

            $pluginLogger->info_message( 'plugin_installed', $context );

            // Add plugin activated message
            $context['plugin_slug'] = 'simple-history';
            $context['plugin_title'] = '<a href="https://simple-history.com/">Simple History</a>';

            $pluginLogger->info_message( 'plugin_activated', $context );
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
     *     @type callable $function Function that will show the settings tab output.
     * }
     */
    public function register_settings_tab( $arr_tab_settings ) {
        $this->arr_settings_tabs[] = $arr_tab_settings;
    }

    /**
     * Get the registered settings tabs.
     *
     * @return array
     */
    public function get_settings_tabs() {
        return $this->arr_settings_tabs;
    }

    /**
     * Output HTML for the settings page
     * Called from add_options_page
     */
    public function settings_page_output() {
        $arr_settings_tabs = $this->get_settings_tabs();
        ?>
        <div class="wrap">

            <h1 class="SimpleHistoryPageHeadline">
                <div class="dashicons dashicons-backup SimpleHistoryPageHeadline__icon"></div>
                <?php esc_html_e( 'Simple History Settings', 'simple-history' ); ?>
            </h1>

            <?php
            $active_tab = $_GET['selected-tab'] ?? 'settings';
            $settings_base_url = menu_page_url( self::SETTINGS_MENU_SLUG, 0 );
            ?>

            <h2 class="nav-tab-wrapper">
                <?php
                foreach ( $arr_settings_tabs as $one_tab ) {
                    $tab_slug = $one_tab['slug'];

                    printf(
                        '<a href="%3$s" class="nav-tab %4$s">%1$s</a>',
                        $one_tab['name'], // 1
                        $tab_slug, // 2
                        esc_url( add_query_arg( 'selected-tab', $tab_slug, $settings_base_url ) ), // 3
                        $active_tab == $tab_slug ? 'nav-tab-active' : '' // 4
                    );
                }
                ?>
            </h2>

            <?php
            // Output contents for selected tab
            $arr_active_tab = wp_filter_object_list(
                $arr_settings_tabs,
                array(
                    'slug' => $active_tab,
                )
            );
            $arr_active_tab = current( $arr_active_tab );

            // We must have found an active tab and it must have a callable function
            if ( ! $arr_active_tab || ! is_callable( $arr_active_tab['function'] ) ) {
                wp_die( esc_html__( 'No valid callback found', 'simple-history' ) );
            }

            $args = array(
                'arr_active_tab' => $arr_active_tab,
            );

            call_user_func_array( $arr_active_tab['function'], array_values( $args ) );
            ?>

        </div>
        <?php
    }

    public function settings_output_log() {
        include SIMPLE_HISTORY_PATH . 'templates/settings-log.php';
    }

    public function settings_output_general() {
        include SIMPLE_HISTORY_PATH . 'templates/settings-general.php';
    }

    public function settings_output_styles_example() {
        include SIMPLE_HISTORY_PATH . 'templates/settings-style-example.php';
    }

    /**
     * Content for section intro. Leave it be, even if empty.
     * Called from add_sections_setting.
     */
    public function settings_section_output() {
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
             * @param bool Show the page or not
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

        // Add a settings page
        $show_settings_page = true;
        $show_settings_page = apply_filters( 'simple_history_show_settings_page', $show_settings_page );
        $show_settings_page = apply_filters( 'simple_history/show_settings_page', $show_settings_page );

        if ( $show_settings_page ) {
            add_options_page(
                __( 'Simple History Settings', 'simple-history' ),
                _x( 'Simple History', 'Options page menu title', 'simple-history' ),
                $this->get_view_settings_capability(),
                self::SETTINGS_MENU_SLUG,
                array( $this, 'settings_page_output' )
            );
        }
    }

    /**
     * Add setting sections and settings for the settings page
     * Also maybe save some settings before outputting them
     */
    public function add_settings() {
        // Clear the log if clear button was clicked in settings
        // and redirect user to show message.
        if (
            isset( $_GET['simple_history_clear_log_nonce'] ) &&
            wp_verify_nonce( $_GET['simple_history_clear_log_nonce'], 'simple_history_clear_log' )
        ) {
            if ( $this->user_can_clear_log() ) {
                $this->clear_log();
            }

            $msg = __( 'Cleared database', 'simple-history' );

            add_settings_error(
                'simple_history_rss_feed_regenerate_secret',
                'simple_history_rss_feed_regenerate_secret',
                $msg,
                'updated'
            );

            set_transient( 'settings_errors', get_settings_errors(), 30 );

            $goback = esc_url_raw( add_query_arg( 'settings-updated', 'true', wp_get_referer() ) );
            wp_redirect( $goback );
            exit();
        }

        // Section for general options.
        // Will contain settings like where to show simple history and number of items.
        $settings_section_general_id = self::SETTINGS_SECTION_GENERAL_ID;
        add_settings_section(
            $settings_section_general_id,
            '',
            array( $this, 'settings_section_output' ),
            self::SETTINGS_MENU_SLUG // Same slug as for options menu page.
        );

        // Settings for the general settings section
        // Each setting = one row in the settings section
        // add_settings_field( $id, $title, $callback, $page, $section, $args );
        // Checkboxes for where to show simple history
        add_settings_field(
            'simple_history_show_where',
            __( 'Show history', 'simple-history' ),
            array( $this, 'settings_field_where_to_show' ),
            self::SETTINGS_MENU_SLUG,
            $settings_section_general_id
        );

        // Nonces for show where inputs.
        register_setting( self::SETTINGS_GENERAL_OPTION_GROUP, 'simple_history_show_on_dashboard' );
        register_setting( self::SETTINGS_GENERAL_OPTION_GROUP, 'simple_history_show_as_page' );

        // Number if items to show on the history page.
        add_settings_field(
            'simple_history_number_of_items',
            __( 'Number of items per page on the log page', 'simple-history' ),
            array( $this, 'settings_field_number_of_items' ),
            self::SETTINGS_MENU_SLUG,
            $settings_section_general_id
        );

        // Nonces for number of items inputs.
        register_setting( self::SETTINGS_GENERAL_OPTION_GROUP, 'simple_history_pager_size' );

        // Number if items to show on dashboard.
        add_settings_field(
            'simple_history_number_of_items_dashboard',
            __( 'Number of items per page on the dashboard', 'simple-history' ),
            array( $this, 'settings_field_number_of_items_dashboard' ),
            self::SETTINGS_MENU_SLUG,
            $settings_section_general_id
        );

        // Nonces for number of items inputs.
        register_setting( self::SETTINGS_GENERAL_OPTION_GROUP, 'simple_history_pager_size_dashboard' );

        // Link/button to clear log.
        if ( $this->user_can_clear_log() ) {
            add_settings_field(
                'simple_history_clear_log',
                __( 'Clear log', 'simple-history' ),
                array( $this, 'settings_field_clear_log' ),
                self::SETTINGS_MENU_SLUG,
                $settings_section_general_id
            );
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
             * @param Simple_History $SimpleHistory This class.
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
                     * @param Simple_History $SimpleHistory This class.
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
     * Settings field for how many rows/items to show in log on the log page
     */
    public function settings_field_number_of_items() {
        $current_pager_size = $this->get_pager_size();
        $pager_size_default_values = array( 5, 10, 15, 20, 25, 30, 40, 50, 75, 100 );

        // If number of items is controlled via filter then return early.
        if ( has_filter( 'simple_history/pager_size' ) ) {
            printf(
                '<input type="text" readonly value="%1$s" />',
                esc_html( $current_pager_size ),
            );

            return;
        }

        ?>
        <select name="simple_history_pager_size">
            <?php
            foreach ( $pager_size_default_values as $one_value ) {
                $selected = selected( $current_pager_size, $one_value, false );

                printf(
                    '<option %1$s value="%2$s">%2$s</option>',
                    esc_html( $selected ),
                    esc_html( $one_value )
                );
            }

            // If current pager size is not among array values then manually output selected value here.
            // This can happen if user has set a value that is not in the array.
            if ( ! in_array( $current_pager_size, $pager_size_default_values, true ) ) {
                printf(
                    '<option selected="selected" value="%1$s">%1$s</option>',
                    esc_html( $current_pager_size )
                );
            }
            ?>
        </select>

        <?php
    }

    /**
     * Settings field for how many rows/items to show in log on the dashboard
     */
    public function settings_field_number_of_items_dashboard() {
        $current_pager_size = $this->get_pager_size_dashboard();
        $pager_size_default_values = array( 5, 10, 15, 20, 25, 30, 40, 50, 75, 100 );

        // If number of items is controlled via filter then return early.
        if ( has_filter( 'simple_history_pager_size_dashboard' ) || has_filter( 'simple_history/dashboard_pager_size' ) ) {
            printf(
                '<input type="text" readonly value="%1$s" />',
                esc_html( $current_pager_size ),
            );

            return;
        }

        ?>
        <select name="simple_history_pager_size_dashboard">
            <?php
            foreach ( $pager_size_default_values as $one_value ) {
                $selected = selected( $current_pager_size, $one_value, false );

                printf(
                    '<option %1$s value="%2$s">%2$s</option>',
                    esc_html( $selected ),
                    esc_html( $one_value )
                );
            }

            // If current pager size is not among array values then manually output selected value here.
            // This can happen if user has set a value that is not in the array.
            if ( ! in_array( $current_pager_size, $pager_size_default_values, true ) ) {
                printf(
                    '<option selected="selected" value="%1$s">%1$s</option>',
                    esc_html( $current_pager_size )
                );
            }
            ?>
        </select>
        <?php
    }

    /**
     * Settings field for where to show the log, page or dashboard
     */
    public function settings_field_where_to_show() {
        $show_on_dashboard = $this->setting_show_on_dashboard();
        $show_as_page = $this->setting_show_as_page();
        ?>

        <input
            <?php echo $show_on_dashboard ? "checked='checked'" : ''; ?>
            type="checkbox" value="1" name="simple_history_show_on_dashboard" id="simple_history_show_on_dashboard" class="simple_history_show_on_dashboard" />
        <label for="simple_history_show_on_dashboard"><?php esc_html_e( 'on the dashboard', 'simple-history' ); ?></label>

        <br />

        <input
            <?php echo $show_as_page ? "checked='checked'" : ''; ?>
            type="checkbox" value="1" name="simple_history_show_as_page" id="simple_history_show_as_page" class="simple_history_show_as_page" />
        <label for="simple_history_show_as_page">
            <?php esc_html_e( 'as a page under the dashboard menu', 'simple-history' ); ?>
        </label>

        <?php
    }

    /**
     * Settings section to clear database
     */
    public function settings_field_clear_log() {
        // Get base URL to current page.
        // Will be like "/wordpress/wp-admin/options-general.php?page=simple_history_settings_menu_slug&"
        $clear_link = add_query_arg( '', '' );

        // Append nonce to URL.
        $clear_link = wp_nonce_url( $clear_link, 'simple_history_clear_log', 'simple_history_clear_log_nonce' );

        $clear_days = $this->get_clear_history_interval();

        echo '<p>';

        if ( $clear_days > 0 ) {
            echo sprintf(
                esc_html__( 'Items in the database are automatically removed after %1$s days.', 'simple-history' ),
                esc_html( $clear_days )
            );
        } else {
            esc_html_e( 'Items in the database are kept forever.', 'simple-history' );
        }

        echo '</p>';

        printf(
            '<p><a class="button js-SimpleHistory-Settings-ClearLog" href="%2$s">%1$s</a></p>',
            esc_html__( 'Clear log now', 'simple-history' ),
            esc_url( $clear_link )
        );
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
        $days = (int) apply_filters( 'simple_history/db_purge_days_interval', $days );

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

        // Zero state sucks.
        SimpleLogger()->info(
            __( 'The log for Simple History was cleared ({num_rows} rows were removed).', 'simple-history' ),
            array(
                'num_rows' => $num_rows,
            )
        );

        Helpers::get_cache_incrementor( true );

        return $num_rows;
    }

    /**
     * Runs the purge_db() method sometimes.
     *
     * Fired from filter `simple_history/maybe_purge_db``
     * that is scheduled to run once a day.
     *
     * The db is purged only on Sundays by default,
     * this is to keep the history clean. If it was done
     * every day it could pollute the log with a lot of
     * "Simple History removed X events that were older than Y days".
     *
     * @since 2.0.17
     */
    public function maybe_purge_db() {
        /**
         * Day of week today.
         * @int $current_day_of_week
         */
        $current_day_of_week = (int) gmdate( 'N' );

        /**
         * Day number to purge db on.
         *
         * @int $day_of_week_to_purge_db
         */
        $day_of_week_to_purge_db = 7;

        /**
         * Filter to change day of week to purge db on.
         * Default is 7 (sunday).
         *
         * @param int $day_of_week_to_purge_db
         * @since 4.1.0
         */
        $day_of_week_to_purge_db = apply_filters( 'simple_history/day_of_week_to_purge_db', $day_of_week_to_purge_db );

        if ( $current_day_of_week === $day_of_week_to_purge_db ) {
            $this->purge_db();
        }
    }

    /**
     * Removes old entries from the db.
     */
    public function purge_db() {
        $do_purge_history = true;

        $do_purge_history = apply_filters( 'simple_history_allow_db_purge', $do_purge_history );
        $do_purge_history = apply_filters( 'simple_history/allow_db_purge', $do_purge_history );

        if ( ! $do_purge_history ) {
            return;
        }

        $days = $this->get_clear_history_interval();

        // Never clear log if days = 0.
        if ( 0 == $days ) {
            return;
        }

        global $wpdb;

        $table_name = $wpdb->prefix . self::DBTABLE;
        $table_name_contexts = $wpdb->prefix . self::DBTABLE_CONTEXTS;

        while ( 1 > 0 ) {
            // Get id of rows to delete.
            $sql = $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
                "SELECT id FROM $table_name WHERE DATE_ADD(date, INTERVAL %d DAY) < now() LIMIT 100000",
                $days
            );

            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $ids_to_delete = $wpdb->get_col( $sql );

            if ( empty( $ids_to_delete ) ) {
                // Nothing to delete.
                return;
            }

            $sql_ids_in = implode( ',', $ids_to_delete );

            // Add number of deleted rows to total_rows option.
            $prev_total_rows = (int) get_option( 'simple_history_total_rows', 0 );
            $total_rows = $prev_total_rows + count( $ids_to_delete );
            update_option( 'simple_history_total_rows', $total_rows );

            // Remove rows + contexts.
            $sql_delete_history = "DELETE FROM {$table_name} WHERE id IN ($sql_ids_in)";
            $sql_delete_history_context = "DELETE FROM {$table_name_contexts} WHERE history_id IN ($sql_ids_in)";

            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $wpdb->query( $sql_delete_history );
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $wpdb->query( $sql_delete_history_context );

            $message = _nx(
                'Simple History removed one event that were older than {days} days',
                'Simple History removed {num_rows} events that were older than {days} days',
                count( $ids_to_delete ),
                'Database is being cleared automagically',
                'simple-history'
            );

            SimpleLogger()->info(
                $message,
                array(
                    'days' => $days,
                    'num_rows' => count( $ids_to_delete ),
                )
            );

            Helpers::get_cache_incrementor( true );
        }
    }

    /**
     * Return plain text output for a log row
     * Uses the get_log_row_plain_text_output of the logger that logged the row
     * with fallback to SimpleLogger if logger is not available.
     *
     * @param context $row
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
        if ( $details_html ) {
            $details_html = sprintf( '<div class="SimpleHistoryLogitem__details">%1$s</div>', $details_html );
        }

        // subsequentOccasions = including the current one
        $occasions_count = $oneLogRow->subsequentOccasions - 1;
        $occasions_html = '';

        if ( $occasions_count > 0 ) {
            $occasions_html = '<div class="SimpleHistoryLogitem__occasions">';

            $occasions_html .= '<a href="#" class="SimpleHistoryLogitem__occasionsLink">';
            $occasions_html .= sprintf(
                _n( '+%1$s similar event', '+%1$s similar events', $occasions_count, 'simple-history' ),
                $occasions_count
            );
            $occasions_html .= '</a>';

            $occasions_html .= '<span class="SimpleHistoryLogitem__occasionsLoading">';
            $occasions_html .= sprintf( __( 'Loading…', 'simple-history' ), $occasions_count );
            $occasions_html .= '</span>';

            $occasions_html .= '<span class="SimpleHistoryLogitem__occasionsLoaded">';
            $occasions_html .= sprintf( __( 'Showing %1$s more', 'simple-history' ), $occasions_count );
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

        $arr_found_additional_ip_headers = $this->instantiated_loggers['SimpleLogger']['instance']->get_event_ip_number_headers( $oneLogRow );

        if ( $arr_found_additional_ip_headers ) {
            $data_attrs .= sprintf( ' data-ip-address-multiple="1" ' );
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
             * @param array with keys to show. key to show = key. value = boolean to show or not.
             * @param object log row to show details from
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
             * @param array with keys to show. key to show = key. value = boolean to show or not.
             * @param object log row to show details from
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

        if ( $arr_found_additional_ip_headers ) {
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
         * @param $classes Array with classes
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
     * Get instantiated dropins.
     *
     * @return array
     */
    public function get_instantiated_dropins() {
        return $this->instantiated_dropins;
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
    public function get_loggers_that_user_can_read( $user_id = '', $format = 'array' ) {
        $arr_loggers_user_can_view = array();

        if ( ! is_numeric( $user_id ) ) {
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
             * @param bool Whether the user is allowed to view the logger.
             * @param Simple_Logger Logger instance.
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
         * @param int user_id ID of user to check read capability for
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
     * Retrieve the avatar for a user who provided a user ID or email address.
     * A modified version of the function that comes with WordPress, but we
     * want to allow/show gravatars even if they are disabled in discussion settings
     *
     * @since 2.0
     * @since 3.3 Respects gravatar setting in discussion settings.
     *
     * @param string $email email address
     * @param int    $size Size of the avatar image
     * @param string $default URL to a default image to use if no avatar is available
     * @param string $alt Alternative text to use in image tag. Defaults to blank
     * @return string <img> tag for the user's avatar
     */
    // TODO: move to helpers
    public function get_avatar( $email, $size = '96', $default = '', $alt = false, $args = array() ) {
        $args = array(
            'force_display' => false,
        );

        /**
         * Filter to control if avatars should be displayed, even if the show_avatars option
         * is set to false in WordPress discussion settings.
         *
         * @since 3.3.0
         *
         * @example Force display of Gravatars
         *
         * ```php
         *  add_filter(
         *      'simple_history/show_avatars',
         *      function ( $force ) {
         *          $force = true;
         *          return $force;
         *      }
         *  );
         * ```
         *
         * @param bool Force display. Default false.
         */
        $args['force_display'] = apply_filters( 'simple_history/show_avatars', $args['force_display'] );

        return get_avatar( $email, $size, $default, $alt, $args );
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

        $count_users_today = count( $results_users_today );

        // Get number of other sources (not wp_user).
        $sql_other_sources_where = sprintf(
            '
                initiator <> "wp_user"
                AND logger IN %1$s
                AND date > "%2$s"
            ',
            $sql_loggers_in,
            gmdate( 'Y-m-d H:i', strtotime( 'today' ) ),
            $wpdb->prefix . self::DBTABLE,
            $wpdb->prefix . self::DBTABLE_CONTEXTS
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

        $count_other_sources = count( $results_other_sources_today );
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
                        $msg_tmpl .= __( '%1$d events today from one user.', 'simple-history' );
                    }

                    // Multiple events from only users
                    // 2 events today from 2 users.
                    if ( $total_row_count > 1 && $count_users_today == $total_row_count ) {
                        $msg_tmpl .= __( '%1$d events today from %2$d users.', 'simple-history' );
                    }

                    // Multiple events from 1 single user and 1 single other source
                    // 2 events today from 1 user and 1 other source.
                    if ( $total_row_count && 1 == $count_users_today && 1 == $count_other_sources ) {
                        $msg_tmpl .= __( '%1$d events today from one user and one other source.', 'simple-history' );
                    }

                    // Multiple events from multiple users but from only 1 single other source
                    // 3 events today from 2 users and 1 other source.
                    if ( $total_row_count > 1 && $count_users_today > 1 && $count_other_sources == 1 ) {
                        $msg_tmpl .= __( '%1$d events today from one user and one other source.', 'simple-history' );
                    }

                    // Multiple events from 1 user but from multiple  other source
                    // 3 events today from 1 user and 2 other sources.
                    if ( $total_row_count > 1 && 1 == $count_users_today && $count_other_sources > 1 ) {
                        $msg_tmpl .= __( '%1$d events today from one user and %3$d other sources.', 'simple-history' );
                    }

                    // Multiple events from multiple user and from multiple other sources
                    // 4 events today from 2 users and 2 other sources.
                    if ( $total_row_count > 1 && $count_users_today > 1 && $count_other_sources > 1 ) {
                        $msg_tmpl .= __( '%1$s events today from %2$d users and %3$d other sources.', 'simple-history' );
                    }
                } // End if().

                // Show stats if we have something to output.
                if ( $msg_tmpl ) {
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
        $methods_mapping = array(
            'registerSettingsTab' => 'register_settings_tab',
        );

        // Bail if method name is nothing to act on.
        if ( ! isset( $methods_mapping[ $name ] ) ) {
            return;
        }

        $method_name_to_call = $methods_mapping[ $name ];

        return call_user_func_array( array( $this, $method_name_to_call ), $arguments );

    }
}
