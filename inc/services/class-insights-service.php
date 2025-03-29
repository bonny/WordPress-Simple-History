<?php

namespace Simple_History\Services;

use Simple_History\Helpers;
use Simple_History\Simple_History;
use Simple_History\Menu_Page;
use Simple_History\Services\Service;

/**
 * Service class that handles insights functionality.
 */
class Insights_Service extends Service {
	/**
	 * Called when service is loaded.
	 */
	public function loaded() {
		add_action( 'admin_menu', [ $this, 'add_menu' ], 20 );
	}

	/**
	 * Add insights menu item.
	 */
	public function add_menu() {
		if ( ! Helpers::setting_show_as_menu_page() ) {
			return;
		}

		$admin_page_location = Helpers::get_menu_page_location();

		// Only add if location is menu_top or menu_bottom.
		if ( ! in_array( $admin_page_location, [ 'top', 'bottom' ], true ) ) {
			return;
		}

		// Add Insights page as a submenu item.
		$insights_page = ( new Menu_Page() )
			->set_page_title( _x( 'Insights - Simple History', 'dashboard title name', 'simple-history' ) )
			->set_menu_title( _x( 'Insights', 'dashboard menu name', 'simple-history' ) )
			->set_menu_slug( 'simple_history_insights_page' )
			->set_capability( Helpers::get_view_history_capability() )
			->set_callback( [ $this, 'output_page' ] )
			->set_parent( Simple_History::MENU_PAGE_SLUG )
			->set_location( 'submenu' );

		$insights_page->add();
	}

	/**
	 * Output the insights page content.
	 */
	public function output_page() {
		// Enqueue Chart.js and our custom scripts.
		wp_enqueue_style(
			'simple-history-insights',
			SIMPLE_HISTORY_DIR_URL . 'css/simple-history-insights.css',
			[],
			SIMPLE_HISTORY_VERSION
		);

		wp_enqueue_script(
			'chartjs',
			'https://cdn.jsdelivr.net/npm/chart.js',
			[],
			'4.4.1',
			true
		);

		wp_enqueue_script(
			'simple-history-insights',
			SIMPLE_HISTORY_DIR_URL . 'js/simple-history-insights.js',
			[ 'jquery', 'chartjs' ],
			SIMPLE_HISTORY_VERSION,
			true
		);

		// Get insights data.
		$top_users = $this->get_top_users( 10 );
		$activity_overview = $this->get_activity_overview( 30 );
		$common_actions = $this->get_most_common_actions( 10 );
		$peak_times = $this->get_peak_activity_times();

		// Pass data to JavaScript.
		wp_localize_script(
			'simple-history-insights',
			'simpleHistoryInsights',
			[
				'topUsers' => $top_users,
				'activityOverview' => $activity_overview,
				'commonActions' => $common_actions,
				'peakTimes' => $peak_times,
				'strings' => [
					'topUsers' => __( 'Top Users', 'simple-history' ),
					'activityOverview' => __( 'Activity Overview', 'simple-history' ),
					'commonActions' => __( 'Most Common Actions', 'simple-history' ),
					'peakTimes' => __( 'Peak Activity Times', 'simple-history' ),
					'actions' => __( 'Actions', 'simple-history' ),
					'users' => __( 'Users', 'simple-history' ),
					'events' => __( 'Events', 'simple-history' ),
					'time' => __( 'Time', 'simple-history' ),
				],
			]
		);
		?>
		<div class="wrap">
			<h1><?php echo esc_html_x( 'Simple History Insights', 'insights page headline', 'simple-history' ); ?></h1>

			<div class="sh-InsightsDashboard">
				<div class="sh-InsightsDashboard-section">
					<h2><?php echo esc_html_x( 'Top Users', 'insights section title', 'simple-history' ); ?></h2>
					<div class="sh-InsightsDashboard-content">
						<canvas id="topUsersChart" class="sh-InsightsDashboard-chart"></canvas>
					</div>
				</div>

				<div class="sh-InsightsDashboard-section">
					<h2><?php echo esc_html_x( 'Activity Overview', 'insights section title', 'simple-history' ); ?></h2>
					<div class="sh-InsightsDashboard-content">
						<canvas id="activityChart" class="sh-InsightsDashboard-chart"></canvas>
					</div>
				</div>

				<div class="sh-InsightsDashboard-section">
					<h2><?php echo esc_html_x( 'Most Common Actions', 'insights section title', 'simple-history' ); ?></h2>
					<div class="sh-InsightsDashboard-content">
						<canvas id="actionsChart" class="sh-InsightsDashboard-chart"></canvas>
					</div>
				</div>

				<div class="sh-InsightsDashboard-section">
					<h2><?php echo esc_html_x( 'Peak Activity Times', 'insights section title', 'simple-history' ); ?></h2>
					<div class="sh-InsightsDashboard-content">
						<canvas id="peakTimesChart" class="sh-InsightsDashboard-chart"></canvas>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Get top users by activity count.
	 *
	 * @param int $limit Number of users to return.
	 * @return array Array of users with their activity counts.
	 */
	public function get_top_users( $limit = 10 ) {
		global $wpdb;

		$sql = $wpdb->prepare(
			"SELECT 
                c.value as user_id,
                COUNT(*) as count,
                u.display_name
            FROM 
                {$wpdb->prefix}simple_history_contexts c
            JOIN 
                {$wpdb->prefix}simple_history h ON h.id = c.history_id
            LEFT JOIN 
                {$wpdb->users} u ON u.ID = CAST(c.value AS UNSIGNED)
            WHERE 
                c.key = '_user_id'
            GROUP BY 
                c.value
            ORDER BY 
                count DESC
            LIMIT %d",
			$limit
		);

		return $wpdb->get_results( $sql );
	}

	/**
	 * Get activity overview by date.
	 *
	 * @param int $days Number of days to look back.
	 * @return array Array of dates with their activity counts.
	 */
	public function get_activity_overview( $days = 30 ) {
		global $wpdb;

		$sql = $wpdb->prepare(
			"SELECT 
                DATE(date) as date,
                COUNT(*) as count
            FROM 
                {$wpdb->prefix}simple_history
            WHERE 
                date >= DATE_SUB(NOW(), INTERVAL %d DAY)
            GROUP BY 
                DATE(date)
            ORDER BY 
                date ASC",
			$days
		);

		return $wpdb->get_results( $sql );
	}

	/**
	 * Get most common actions.
	 *
	 * @param int $limit Number of actions to return.
	 * @return array Array of actions with their counts.
	 */
	public function get_most_common_actions( $limit = 10 ) {
		global $wpdb;

		$sql = $wpdb->prepare(
			"SELECT 
                logger,
                level,
                COUNT(*) as count
            FROM 
                {$wpdb->prefix}simple_history
            GROUP BY 
                logger, level
            ORDER BY 
                count DESC
            LIMIT %d",
			$limit
		);

		return $wpdb->get_results( $sql );
	}

	/**
	 * Get peak activity times.
	 *
	 * @return array Array of hours with their activity counts.
	 */
	public function get_peak_activity_times() {
		global $wpdb;

		$table_name = $this->simple_history->get_events_table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$sql = "SELECT 
            HOUR(date) as hour,
            COUNT(*) as count
        FROM 
            {$table_name}
        GROUP BY 
            HOUR(date)
        ORDER BY 
            hour ASC";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->get_results( $sql );
	}

	/**
	 * Format logger name for display.
	 *
	 * @param string $logger_name Raw logger name.
	 * @return string Formatted logger name.
	 */
	public function format_logger_name( $logger_name ) {
		// Remove namespace if present.
		$logger_name = str_replace( 'SimpleHistory\\Loggers\\', '', $logger_name );

		// Convert CamelCase to spaces.
		$logger_name = preg_replace( '/(?<!^)[A-Z]/', ' $0', $logger_name );

		// Remove "Logger" suffix if present.
		$logger_name = str_replace( ' Logger', '', $logger_name );

		return trim( $logger_name );
	}
}
