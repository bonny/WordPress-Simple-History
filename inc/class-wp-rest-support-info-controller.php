<?php

namespace Simple_History;

use WP_Error;
use WP_REST_Controller;
use WP_REST_Server;

/**
 * REST API controller for support info.
 * Provides endpoints for gathering system information for support requests
 * and health check for API connectivity.
 */
class WP_REST_Support_Info_Controller extends WP_REST_Controller {
	/**
	 * Simple History instance.
	 *
	 * @var Simple_History
	 */
	protected Simple_History $simple_history;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->namespace      = 'simple-history/v1';
		$this->rest_base      = 'support-info';
		$this->simple_history = Simple_History::get_instance();
	}

	/**
	 * Register the routes for the objects of the controller.
	 */
	public function register_routes() {
		// GET /wp-json/simple-history/v1/support-info.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_support_info' ],
					'permission_callback' => [ $this, 'get_items_permissions_check' ],
				],
				'schema' => [ $this, 'get_public_item_schema' ],
			]
		);

		// GET /wp-json/simple-history/v1/support-info/health-check.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/health-check',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_health_check' ],
					'permission_callback' => [ $this, 'get_items_permissions_check' ],
				],
			]
		);
	}

	/**
	 * Checks if a given request has access to read support info.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return true|\WP_Error True if the request has read access, WP_Error object otherwise.
	 */
	public function get_items_permissions_check( $request ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'rest_forbidden_context',
				__( 'Sorry, you are not allowed to view support info.', 'simple-history' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	/**
	 * Quick health check endpoint to verify REST API connectivity.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return \WP_REST_Response Response object.
	 */
	public function get_health_check( $request ) {
		return rest_ensure_response(
			[
				'status'    => 'ok',
				'timestamp' => current_time( 'mysql' ),
			]
		);
	}

	/**
	 * Get comprehensive support information.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return \WP_REST_Response Response object.
	 */
	public function get_support_info( $request ) {
		$info = $this->gather_support_info();

		return rest_ensure_response(
			[
				'info'       => $info,
				'plain_text' => $this->format_as_plain_text( $info ),
			]
		);
	}

	/**
	 * Gather all support information.
	 *
	 * @return array Support information data.
	 */
	private function gather_support_info() {
		global $wpdb;

		$info = [];

		// WordPress info.
		$info['wordpress'] = [
			'version'             => get_bloginfo( 'version' ),
			'multisite'           => is_multisite() ? __( 'Yes', 'simple-history' ) : __( 'No', 'simple-history' ),
			'locale'              => get_locale(),
			'timezone'            => wp_timezone_string(),
			'permalink_structure' => get_option( 'permalink_structure' ) !== '' ? get_option( 'permalink_structure' ) : __( 'Plain', 'simple-history' ),
			'https'               => is_ssl() ? __( 'Yes', 'simple-history' ) : __( 'No', 'simple-history' ),
			'wp_debug'            => defined( 'WP_DEBUG' ) && WP_DEBUG ? __( 'Enabled', 'simple-history' ) : __( 'Disabled', 'simple-history' ),
			'wp_debug_log'        => defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ? __( 'Enabled', 'simple-history' ) : __( 'Disabled', 'simple-history' ),
			'wp_cron_disabled'    => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ? __( 'Yes', 'simple-history' ) : __( 'No', 'simple-history' ),
			'wp_memory_limit'     => defined( 'WP_MEMORY_LIMIT' ) ? WP_MEMORY_LIMIT : __( 'Not set', 'simple-history' ),
			'table_prefix'        => $wpdb->prefix,
			'object_cache'        => wp_using_ext_object_cache() ? __( 'Yes', 'simple-history' ) : __( 'No', 'simple-history' ),
		];

		// Server info.
		$info['server'] = [
			'php_version'        => phpversion(),
			'database'           => $this->get_database_info(),
			'memory_limit'       => ini_get( 'memory_limit' ),
			'max_input_vars'     => ini_get( 'max_input_vars' ),
			'max_execution_time' => ini_get( 'max_execution_time' ),
			'post_max_size'      => ini_get( 'post_max_size' ),
			'upload_max_size'    => ini_get( 'upload_max_filesize' ),
			'php_extensions'     => $this->get_php_extensions(),
			'server_software'    => isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : __( 'Unknown', 'simple-history' ),
			'hosting_provider'   => $this->get_hosting_provider(),
		];

		// Simple History info.
		$total_events           = Helpers::get_total_logged_events_count();
		$table_stats            = Helpers::get_db_table_stats();
		$oldest_event           = ( new Events_Stats() )->get_oldest_event();
		$db_engine              = Log_Query::get_db_engine();
		$info['simple_history'] = [
			'version'        => SIMPLE_HISTORY_VERSION,
			'premium'        => $this->get_premium_status(),
			'total_events'   => $total_events,
			'table_stats'    => $table_stats,
			'oldest_event'   => $oldest_event,
			'db_engine'      => $db_engine,
			'retention_days' => $this->get_retention_days(),
			'items_per_page' => Helpers::get_pager_size(),
		];

		// Warnings section.
		$info['warnings'] = $this->get_warnings( $info );

		// Active theme.
		$theme         = wp_get_theme();
		$info['theme'] = [
			'name'        => $theme->get( 'Name' ),
			'version'     => $theme->get( 'Version' ),
			'author'      => $theme->get( 'Author' ),
			'child_theme' => is_child_theme() ? __( 'Yes', 'simple-history' ) : __( 'No', 'simple-history' ),
		];

		// Active plugins.
		$info['plugins'] = $this->get_active_plugins();

		// Must-use plugins.
		$info['mu_plugins'] = $this->get_mu_plugins();

		// WordPress drop-ins.
		$info['dropins'] = $this->get_wp_dropins();

		// Loggers by row count.
		$info['loggers'] = $this->get_loggers_by_row_count();

		// Browser/client info - user agent is useful for debugging display issues.
		$info['browser'] = [
			// phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___SERVER__HTTP_USER_AGENT__ -- Needed for support debugging.
			'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : __( 'Unknown', 'simple-history' ),
		];

		return $info;
	}

	/**
	 * Get database engine and version.
	 *
	 * @return string Database info string.
	 */
	private function get_database_info() {
		global $wpdb;

		$db_engine = Log_Query::get_db_engine();

		// Get version - direct database calls needed to get version info.
		if ( $db_engine === 'sqlite' ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$version = $wpdb->get_var( 'SELECT sqlite_version()' );
			return sprintf( 'SQLite %s', $version );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$version = $wpdb->get_var( 'SELECT VERSION()' );

		// Detect MariaDB vs MySQL.
		if ( stripos( $version, 'mariadb' ) !== false ) {
			return sprintf( 'MariaDB %s', $version );
		}

		return sprintf( 'MySQL %s', $version );
	}

	/**
	 * Get premium add-on status.
	 *
	 * @return string Premium status string.
	 */
	private function get_premium_status() {
		if ( ! Helpers::is_premium_add_on_active() ) {
			return __( 'Not active', 'simple-history' );
		}

		// Try to get premium version.
		if ( defined( 'SIMPLE_HISTORY_PREMIUM_VERSION' ) ) {
			/* translators: %s: premium version number */
			return sprintf( __( 'Active (v%s)', 'simple-history' ), SIMPLE_HISTORY_PREMIUM_VERSION );
		}

		return __( 'Active', 'simple-history' );
	}

	/**
	 * Get relevant PHP extensions status.
	 *
	 * @return string Comma-separated list of extensions.
	 */
	private function get_php_extensions() {
		$extensions = [
			'mbstring' => extension_loaded( 'mbstring' ),
			'json'     => extension_loaded( 'json' ),
			'curl'     => extension_loaded( 'curl' ),
			'zip'      => extension_loaded( 'zip' ),
		];

		$result = [];
		foreach ( $extensions as $name => $loaded ) {
			$result[] = $loaded ? $name : $name . ' (missing)';
		}

		return implode( ', ', $result );
	}

	/**
	 * Get log retention days setting.
	 *
	 * @return string Retention days or 'Forever'.
	 */
	private function get_retention_days() {
		$days = Helpers::get_clear_history_interval();

		if ( $days === 0 ) {
			return __( 'Forever (no auto-cleanup)', 'simple-history' );
		}

		/* translators: %d: number of days */
		return sprintf( __( '%d days', 'simple-history' ), $days );
	}

	/**
	 * Get warnings about potential issues.
	 *
	 * @param array $info Current info array.
	 * @return array List of warnings.
	 */
	private function get_warnings( $info ) {
		$warnings = [];

		// Check PHP memory limit.
		$memory_limit = ini_get( 'memory_limit' );
		$memory_bytes = wp_convert_hr_to_bytes( $memory_limit );
		if ( $memory_bytes > 0 && $memory_bytes < 256 * MB_IN_BYTES ) {
			$warnings[] = sprintf(
				/* translators: %s: current memory limit */
				__( 'PHP memory limit (%s) is below recommended 256M', 'simple-history' ),
				$memory_limit
			);
		}

		// Check if WP_CRON is disabled.
		if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
			$warnings[] = __( 'WP_CRON is disabled - ensure external cron is configured for log cleanup', 'simple-history' );
		}

		return $warnings;
	}

	/**
	 * Get list of active plugins with versions.
	 *
	 * @return array List of active plugins.
	 */
	private function get_active_plugins() {
		$plugins        = get_plugins();
		$active_plugins = [];

		foreach ( $plugins as $plugin_file => $plugin_data ) {
			if ( ! Helpers::is_plugin_active( $plugin_file ) ) {
				continue;
			}

			$active_plugins[] = [
				'name'    => $plugin_data['Name'],
				'version' => $plugin_data['Version'],
			];
		}

		return $active_plugins;
	}

	/**
	 * Get must-use plugins.
	 *
	 * @return array List of MU plugins.
	 */
	private function get_mu_plugins() {
		$mu_plugins = get_mu_plugins();
		$result     = [];

		foreach ( $mu_plugins as $plugin_data ) {
			$version  = ! empty( $plugin_data['Version'] ) ? $plugin_data['Version'] : __( 'Unknown', 'simple-history' );
			$result[] = [
				'name'    => $plugin_data['Name'],
				'version' => $version,
			];
		}

		return $result;
	}

	/**
	 * Detect the hosting provider based on environment constants, classes, and paths.
	 *
	 * Detection methods verified from official documentation and source code.
	 *
	 * @see https://wpengine.com/support/determining-wp-engine-environment/
	 * @see https://docs.pantheon.io/guides/environment-configuration/read-environment-config
	 * @see https://kinsta.com/docs/wordpress-hosting/php/wordpress-php-constants/
	 * @see https://docs.wpvip.com/infrastructure/environments/environment-specific-code/
	 * @see https://github.com/wp-media/wp-rocket/issues/7425
	 *
	 * @return string Hosting provider name or 'Unknown'.
	 */
	private function get_hosting_provider() {
		// WP Engine - is_wpe() function or WPE_APIKEY constant.
		// Note: is_wpe() returns string "1", not boolean true.
		if ( function_exists( 'is_wpe' ) || defined( 'WPE_APIKEY' ) ) {
			return 'WP Engine (probable)';
		}

		// Flywheel (owned by WP Engine) - check for Flywheel-specific constant.
		if ( defined( 'FLYWHEEL_CONFIG_DIR' ) ) {
			return 'Flywheel (probable)';
		}

		// Pantheon - PANTHEON_ENVIRONMENT constant.
		if ( defined( 'PANTHEON_ENVIRONMENT' ) ) {
			return 'Pantheon (probable)';
		}

		// Kinsta - KINSTAMU_VERSION from their mu-plugin, or KINSTA_DEV_ENV for staging.
		if ( defined( 'KINSTAMU_VERSION' ) || defined( 'KINSTA_DEV_ENV' ) ) {
			return 'Kinsta (probable)';
		}

		// WordPress VIP - multiple constants available.
		if ( defined( 'WPCOM_IS_VIP_ENV' ) || defined( 'VIP_GO_ENV' ) || defined( 'VIP_GO_APP_ENVIRONMENT' ) ) {
			return 'WordPress VIP (probable)';
		}

		// WordPress.com (Atomic/Simple) - ATOMIC_SITE_ID or IS_WPCOM.
		if ( defined( 'ATOMIC_SITE_ID' ) || defined( 'IS_WPCOM' ) ) {
			return 'WordPress.com (probable)';
		}

		// GoDaddy Managed WordPress - WPaaS\Plugin class from their mu-plugin.
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		if ( class_exists( 'WPaaS\Plugin' ) || defined( 'GD_SYSTEM_PLUGIN_DIR' ) ) {
			return 'GoDaddy (probable)';
		}

		// Pagely - PagelyCachePurge class from their management plugin.
		if ( class_exists( 'PagelyCachePurge' ) ) {
			return 'Pagely (probable)';
		}

		// Cloudways - check server variable or document root path.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		if ( isset( $_SERVER['cw_allowed_ip'] ) ) {
			return 'Cloudways (probable)';
		}
		if ( isset( $_SERVER['DOCUMENT_ROOT'] ) ) {
			$doc_root = sanitize_text_field( wp_unslash( $_SERVER['DOCUMENT_ROOT'] ) );
			if ( strpos( $doc_root, '.cloudwaysapps.com' ) !== false || strpos( $doc_root, '/home/master/applications/' ) !== false ) {
				return 'Cloudways (probable)';
			}
		}

		// SiteGround - detected via SG Optimizer plugin constant.
		// Note: Plugin can be installed on non-SiteGround hosts, so this is imperfect.
		if ( defined( 'SG_OPTIMIZER_VERSION' ) ) {
			return 'SiteGround (probable)';
		}

		// Servebolt - detected via Servebolt Optimizer plugin.
		if ( defined( 'DEVELOPER_SERVEBOLT' ) || class_exists( 'Developer\\Servebolt\\Plugin' ) ) {
			return 'Servebolt (probable)';
		}

		// Starter mu-plugin detection - commonly used by EIG brands (Bluehost, HostGator, etc.).
		if ( defined( 'MM_BASE_DIR' ) ) {
			return 'Bluehost/EIG (probable)';
		}

		// SpinupWP - check for their plugin class.
		if ( class_exists( 'SpinupWp\Plugin' ) || defined( 'DEVELOPER_SPINUPWP' ) ) {
			return 'SpinupWP (probable)';
		}

		// RunCloud - check for RunCloud Hub plugin.
		if ( class_exists( 'Developer\\RunCloud\\Hub' ) || defined( 'DEVELOPER_RUNCLOUD_HUB_VERSION' ) ) {
			return 'RunCloud (probable)';
		}

		// GridPane - check for their plugin.
		if ( class_exists( 'Developer\\GridPane\\Plugin' ) || defined( 'DEVELOPER_DEVELOPER_GRIDPANE' ) ) {
			return 'GridPane (probable)';
		}

		return __( 'Unknown', 'simple-history' );
	}

	/**
	 * Get WordPress drop-ins.
	 *
	 * @return array List of WordPress drop-ins.
	 */
	private function get_wp_dropins() {
		$dropins = get_dropins();
		$result  = [];

		foreach ( $dropins as $file => $plugin_data ) {
			$result[] = [
				'file' => $file,
				'name' => ! empty( $plugin_data['Name'] ) ? $plugin_data['Name'] : $file,
			];
		}

		return $result;
	}

	/**
	 * Get loggers ordered by row count in database.
	 *
	 * @return array List of loggers with row counts.
	 */
	private function get_loggers_by_row_count() {
		global $wpdb;

		$instantiated_loggers = $this->simple_history->get_instantiated_loggers();
		$events_table_name    = $this->simple_history->get_events_table_name();

		// Get all logger slugs.
		$arr_logger_slugs = [];
		foreach ( $instantiated_loggers as $one_logger ) {
			$arr_logger_slugs[] = $one_logger['instance']->get_slug();
		}

		if ( empty( $arr_logger_slugs ) ) {
			return [];
		}

		// Build placeholders for prepared statement.
		$placeholders = implode( ', ', array_fill( 0, count( $arr_logger_slugs ), '%s' ) );

		// Query for logger row counts.
		// Table name and placeholders are from trusted internal sources.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$sql = $wpdb->prepare(
			"SELECT logger, count(id) as count
			FROM {$events_table_name}
			WHERE logger IN ({$placeholders})
			GROUP BY logger
			ORDER BY count DESC",
			$arr_logger_slugs
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- $sql is prepared above.
		$logger_rows = $wpdb->get_results( $sql, OBJECT_K );

		// Build result with all loggers, including those with 0 rows.
		$result = [];
		foreach ( $logger_rows as $logger_slug => $row ) {
			$result[] = [
				'slug'  => $logger_slug,
				'count' => (int) $row->count,
			];
		}

		// Add loggers with 0 rows.
		$missing_slugs = array_diff( $arr_logger_slugs, array_keys( $logger_rows ) );
		foreach ( $missing_slugs as $slug ) {
			$result[] = [
				'slug'  => $slug,
				'count' => 0,
			];
		}

		return $result;
	}

	/**
	 * Format support info as plain text for forum posting.
	 *
	 * @param array $info Support information data.
	 * @return string Plain text formatted support info.
	 */
	private function format_as_plain_text( $info ) {
		$lines = [];

		$lines[] = '=== Simple History Support Info ===';
		$lines[] = sprintf( 'Generated: %s', current_time( 'Y-m-d H:i:s' ) );
		$lines[] = '';

		// Warnings at top so users and support staff see issues immediately.
		if ( ! empty( $info['warnings'] ) ) {
			$lines[] = '=== ⚠ Warnings ===';
			foreach ( $info['warnings'] as $warning ) {
				$lines[] = sprintf( '⚠ %s', $warning );
			}
			$lines[] = '';
		}

		// WordPress.
		$lines[] = '=== WordPress ===';
		$lines[] = sprintf( 'Version: %s', $info['wordpress']['version'] );
		$lines[] = sprintf( 'Multisite: %s', $info['wordpress']['multisite'] );
		$lines[] = sprintf( 'Locale: %s', $info['wordpress']['locale'] );
		$lines[] = sprintf( 'Timezone: %s', $info['wordpress']['timezone'] );
		$lines[] = sprintf( 'Permalinks: %s', $info['wordpress']['permalink_structure'] );

		// HTTPS with inline warning if not enabled.
		$https_warning = $info['wordpress']['https'] === __( 'No', 'simple-history' ) ? ' ⚠' : '';
		$lines[]       = sprintf( 'HTTPS: %s%s', $info['wordpress']['https'], $https_warning );

		$lines[] = sprintf( 'WP_DEBUG: %s', $info['wordpress']['wp_debug'] );
		$lines[] = sprintf( 'WP_DEBUG_LOG: %s', $info['wordpress']['wp_debug_log'] );

		// WP_CRON with clearer wording and inline warning.
		$cron_disabled = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;
		$lines[]       = sprintf( 'WP_CRON: %s', $cron_disabled ? __( 'Disabled', 'simple-history' ) . ' ⚠' : __( 'Enabled', 'simple-history' ) );

		$lines[] = sprintf( 'WP Memory Limit: %s', $info['wordpress']['wp_memory_limit'] );
		$lines[] = sprintf( 'Table Prefix: %s', $info['wordpress']['table_prefix'] );
		$lines[] = sprintf( 'Object Cache: %s', $info['wordpress']['object_cache'] );
		$lines[] = '';

		// Server.
		$lines[] = '=== Server ===';
		$lines[] = sprintf( 'PHP Version: %s', $info['server']['php_version'] );
		$lines[] = sprintf( 'Database: %s', $info['server']['database'] );
		$lines[] = sprintf( 'Server Software: %s', $info['server']['server_software'] );

		// Only show hosting provider if detected.
		if ( $info['server']['hosting_provider'] !== __( 'Unknown', 'simple-history' ) ) {
			$lines[] = sprintf( 'Hosting Provider: %s', $info['server']['hosting_provider'] );
		}

		// Memory limit with inline warning if below 256M.
		$memory_limit   = $info['server']['memory_limit'];
		$memory_bytes   = wp_convert_hr_to_bytes( $memory_limit );
		$memory_warning = $memory_bytes > 0 && $memory_bytes < 256 * MB_IN_BYTES ? ' ⚠' : '';
		$lines[]        = sprintf( 'PHP Memory Limit: %s%s', $memory_limit, $memory_warning );

		$lines[] = sprintf( 'PHP Max Input Vars: %s', $info['server']['max_input_vars'] );
		$lines[] = sprintf( 'Max Execution Time: %s', $info['server']['max_execution_time'] );
		$lines[] = sprintf( 'Post Max Size: %s', $info['server']['post_max_size'] );
		$lines[] = sprintf( 'Upload Max Size: %s', $info['server']['upload_max_size'] );
		$lines[] = sprintf( 'PHP Extensions: %s', $info['server']['php_extensions'] );
		$lines[] = '';

		// Theme.
		$lines[]      = '=== Theme ===';
		$child_status = $info['theme']['child_theme'] === __( 'Yes', 'simple-history' ) ? ' (Child Theme)' : '';
		$lines[]      = sprintf( 'Name: %s%s', $info['theme']['name'], $child_status );
		$lines[]      = sprintf( 'Version: %s', $info['theme']['version'] );
		$lines[]      = sprintf( 'Author: %s', wp_strip_all_tags( $info['theme']['author'] ) );
		$lines[]      = '';

		// Simple History.
		$lines[] = '=== Simple History ===';
		$lines[] = sprintf( 'Version: %s', $info['simple_history']['version'] );
		$lines[] = sprintf( 'Premium Add-on: %s', $info['simple_history']['premium'] );
		$lines[] = sprintf( 'Log Retention: %s', $info['simple_history']['retention_days'] );
		$lines[] = sprintf( 'Items Per Page: %s', $info['simple_history']['items_per_page'] );
		$lines[] = sprintf( 'Total Events Logged: %s (cumulative, not current DB count)', number_format_i18n( $info['simple_history']['total_events'] ) );

		// Table stats.
		if ( ! empty( $info['simple_history']['table_stats'] ) ) {
			$lines[] = '';
			$lines[] = 'Database Tables:';
			foreach ( $info['simple_history']['table_stats'] as $table ) {
				// Format size - handle N/A for SQLite without dbstat.
				$size = $table['size_in_mb'] === 'N/A'
					? 'N/A'
					: $table['size_in_mb'] . ' MB';

				$lines[] = sprintf(
					'%s: %s, %s rows',
					$table['table_name'],
					$size,
					number_format_i18n( $table['num_rows'] )
				);
			}
		}

		// Oldest event.
		if ( ! empty( $info['simple_history']['oldest_event'] ) ) {
			$lines[] = sprintf(
				'Oldest Event: %s (ID: %s)',
				$info['simple_history']['oldest_event']['date'],
				$info['simple_history']['oldest_event']['id']
			);
		}
		$lines[] = '';

		// Active plugins.
		$plugin_count = count( $info['plugins'] );
		/* translators: %d: number of active plugins */
		$lines[] = sprintf( '=== Active Plugins (%d) ===', $plugin_count );
		foreach ( $info['plugins'] as $plugin ) {
			$lines[] = sprintf( '- %s (%s)', $plugin['name'], $plugin['version'] );
		}
		$lines[] = '';

		// MU plugins.
		if ( ! empty( $info['mu_plugins'] ) ) {
			/* translators: %d: number of must-use plugins */
			$lines[] = sprintf( '=== Must-Use Plugins (%d) ===', count( $info['mu_plugins'] ) );
			foreach ( $info['mu_plugins'] as $plugin ) {
				$lines[] = sprintf( '- %s (%s)', $plugin['name'], $plugin['version'] );
			}
			$lines[] = '';
		}

		// WordPress drop-ins.
		if ( ! empty( $info['dropins'] ) ) {
			/* translators: %d: number of WordPress drop-ins */
			$lines[] = sprintf( '=== WordPress Drop-ins (%d) ===', count( $info['dropins'] ) );
			foreach ( $info['dropins'] as $dropin ) {
				$lines[] = sprintf( '- %s', $dropin['name'] );
			}
			$lines[] = '';
		}

		// Loggers by row count (only those with rows, show top 5).
		$loggers_with_rows = array_filter(
			$info['loggers'],
			function ( $logger ) {
				return $logger['count'] > 0;
			}
		);

		if ( ! empty( $loggers_with_rows ) ) {
			$lines[]     = '=== Top Loggers by Row Count ===';
			$total_count = count( $loggers_with_rows );
			$top_loggers = array_slice( $loggers_with_rows, 0, 5 );

			foreach ( $top_loggers as $logger ) {
				$row_label = $logger['count'] === 1 ? __( 'row', 'simple-history' ) : __( 'rows', 'simple-history' );
				$lines[]   = sprintf( '- %s (%s %s)', $logger['slug'], number_format_i18n( $logger['count'] ), $row_label );
			}

			// Show count of remaining loggers.
			$remaining = $total_count - 5;
			if ( $remaining > 0 ) {
				$lines[] = sprintf(
					/* translators: %d: number of additional loggers */
					__( '(%d more loggers with rows)', 'simple-history' ),
					$remaining
				);
			}
			$lines[] = '';
		}

		// Browser info.
		$lines[] = '=== Browser ===';
		$lines[] = sprintf( 'User Agent: %s', $info['browser']['user_agent'] );

		return implode( "\n", $lines );
	}

	/**
	 * Retrieves the item schema.
	 *
	 * @return array Item schema data.
	 */
	public function get_item_schema() {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}

		$schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'simple-history-support-info',
			'type'       => 'object',
			'properties' => array(
				'info'       => array(
					'description' => __( 'Support information data.', 'simple-history' ),
					'type'        => 'object',
				),
				'plain_text' => array(
					'description' => __( 'Plain text formatted support information.', 'simple-history' ),
					'type'        => 'string',
				),
			),
		);

		$this->schema = $schema;

		return $this->add_additional_fields_schema( $this->schema );
	}
}
