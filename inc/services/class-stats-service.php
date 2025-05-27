<?php

namespace Simple_History\Services;

use Simple_History\Helpers;
use Simple_History\Menu_Page;
use Simple_History\Simple_History;
use Simple_History\Services\Service;
use Simple_History\Events_Stats;
use Simple_History\Services\Admin_Pages;
use Simple_History\Stats_View;

/**
 * Service class that handles stats functionality.
 */
class Stats_Service extends Service {
	/**
	 * Stats instance.
	 *
	 * @var Events_Stats
	 */
	private $stats;

	/**
	 * Called when service is loaded.
	 */
	public function loaded() {
		$this->stats = new Events_Stats();

		add_action( 'admin_menu', [ $this, 'add_menu' ], 10 );

		add_action( 'simple_history/enqueue_admin_scripts', [ $this, 'enqueue_scripts_and_styles' ] );
		add_action( 'simple_history/stats/output_page_contents', [ $this, 'output_page_contents' ] );
	}

	/**
	 * Add stats menu item.
	 */
	public function add_menu() {
		if ( ! Helpers::setting_show_as_menu_page() ) {
			return;
		}

		$admin_page_location = Helpers::get_menu_page_location();

		$new_badge_text = '<span class="sh-PremiumFeatureBadge" style="--sh-badge-background-color: var(--sh-color-yellow);">' . __( 'New', 'simple-history' ) . '</span>';

		// Create insights page.
		$insights_page = ( new Menu_Page() )
			->set_page_title( _x( 'History Insights - Simple History', 'dashboard title name', 'simple-history' ) )
			->set_menu_title( _x( 'History Insights', 'dashboard menu name', 'simple-history' ) . ' ' . $new_badge_text )
			->set_menu_slug( 'simple_history_stats_page' )
			->set_capability( 'manage_options' )
			->set_callback( [ $this, 'output_page' ] );

		// Set different options depending on location.
		if ( in_array( $admin_page_location, [ 'top', 'bottom' ], true ) ) {
			// Add as a submenu to the main page if location is top or bottom in the main menu.
			$insights_page
				->set_parent( Simple_History::MENU_PAGE_SLUG )
				->set_location( 'submenu' );
		} else if ( in_array( $admin_page_location, [ 'inside_dashboard', 'inside_tools' ], true ) ) {
			// If main page is shown as child to tools or dashboard then settings page is shown as child to settings main menu.
			$insights_page
				->set_parent( Simple_History::SETTINGS_MENU_PAGE_SLUG );
		}

		$insights_page->add();
	}

	/**
	 * Get date range based on period query string parameter.
	 *
	 * @return array {
	 *     Array of timestamps for the selected date range.
	 *
	 *     @type int $date_from Unix timestamp for start date.
	 *     @type int $date_to   Unix timestamp for end date.
	 * }
	 */
	private function get_selected_date_range() {
		// Example periods:
		// 1h: 1 hour ago
		// 24h: 1 day ago
		// 7d: 7 days ago
		// 14d: 14 days ago
		// 1m: 1 month ago
		// 3m: 3 months ago
		// 6m: 6 months ago.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$period = isset( $_GET['period'] ) ? sanitize_text_field( wp_unslash( $_GET['period'] ) ) : '1m';
		if ( empty( $period ) ) {
			$period = '1m';
		}

		// Get current date in UTC.
		$now = new \DateTimeImmutable( 'now', new \DateTimeZone( 'UTC' ) );
		$date_to = $now->getTimestamp();

		// Get the number of the period, i.e. 1, 24, 7, 14, 1, 3, 6, 12.
		$period_number = substr( $period, 0, -1 );

		// Get the last character of the period, i.e. h, d, m, y.
		$period_string_suffix = substr( $period, -1 );

		switch ( $period_string_suffix ) {
			case 'h':
				$period_string_full_name = 'hour';
				break;
			case 'd':
				$period_string_full_name = 'day';
				break;
			case 'm':
				$period_string_full_name = 'month';
				break;
			case 'y':
				$period_string_full_name = 'year';
				break;
			default:
				$period_string_full_name = 'month';
				break;
		}

		// Generate string like "-1 hour", "-1 day", "-1 month", "-1 year".
		$date_time_modifier = "-{$period_number} {$period_string_full_name}";

		$date_from = $now->modify( $date_time_modifier )->getTimestamp();

		return [
			'date_from' => $date_from,
			'date_to'   => $date_to,
		];
	}

	/**
	 * Enqueue required scripts and styles for the insights page.
	 */
	public function enqueue_scripts_and_styles() {
		wp_enqueue_script(
			'simple-history-stats',
			SIMPLE_HISTORY_DIR_URL . 'js/simple-history-stats.js',
			[ 'wp-element', 'wp-components', 'wp-i18n', 'wp-api-fetch' ],
			SIMPLE_HISTORY_VERSION,
			true
		);

		wp_enqueue_style(
			'simple-history-stats',
			SIMPLE_HISTORY_DIR_URL . 'css/simple-history-stats.css',
			[],
			SIMPLE_HISTORY_VERSION
		);
	}

	/**
	 * Output the insights page.
	 */
	public function output_page() {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo Admin_Pages::header_output();

		do_action( 'simple_history/stats/output_page_contents' );
	}

	/**
	 * Output stats page contents with basic information + information about premium features.
	 */
	public function output_page_contents() {
		[ 'date_from' => $date_from, 'date_to' => $date_to ] = $this->get_selected_date_range();

		$data = $this->init_stats( $date_from, $date_to );

		$this->localize_script_data( $data, $date_from, $date_to );

		?>
		<div class="wrap sh-Page-content">
			<?php
			Stats_View::output_page_title();
			Stats_View::output_date_range( $date_from, $date_to );
			Stats_View::output_dashboard_content( $data, $date_from, $date_to );
			?>
		</div>
		<?php
	}

	/**
	 * Initialize statistics data.
	 *
	 * @param int $date_from Start date timestamp.
	 * @param int $date_to End date timestamp.
	 * @return array Array of statistics data.
	 */
	public function init_stats( $date_from, $date_to ) {
		$this->stats = new Events_Stats();

		// Get total number of events.
		$total_events = $this->stats->get_total_events( $date_from, $date_to );

		// Get activity overview by date.
		$activity_overview_by_date = $this->stats->get_activity_overview_by_date( $date_from, $date_to );

		// Get user statistics with optimized total count.
		$user_stats = [
			'total_count' => $this->stats->get_user_total_count( $date_from, $date_to ),
		];

		// Get WordPress and plugin statistics with optimized total count.
		$plugin_stats = [
			'total_count' => $this->stats->get_plugin_total_count( $date_from, $date_to ),
		];

		// Get WordPress core statistics with optimized total count.
		$wordpress_stats = [
			'total_count' => $this->stats->get_core_total_count( $date_from, $date_to ),
		];

		// Get posts and pages statistics with optimized total count.
		$posts_pages_stats = [
			'total_count' => $this->stats->get_content_total_count( $date_from, $date_to ),
		];

		// Get media statistics with optimized total count.
		$media_stats = [
			'total_count' => $this->stats->get_media_total_count( $date_from, $date_to ),
		];

		// Get top users.
		$top_users = $this->stats->get_top_users( $date_from, $date_to );

		return [
			'stats' => $this->stats,
			'overview_total_events' => $total_events,
			'overview_activity_by_date' => $activity_overview_by_date,
			'user_stats' => $user_stats,
			'plugin_stats' => $plugin_stats,
			'wordpress_stats' => $wordpress_stats,
			'content_stats' => $posts_pages_stats,
			'media_stats' => $media_stats,
			'user_rankings' => $top_users,
			'user_total_count' => $this->stats->get_total_users( $date_from, $date_to ),
		];
	}

	/**
	 * Localize script data.
	 *
	 * @param array $data      Insights data array.
	 * @param int   $date_from Start date as Unix timestamp.
	 * @param int   $date_to   End date as Unix timestamp.
	 */
	private function localize_script_data( $data, $date_from, $date_to ) {
		wp_localize_script(
			'simple-history-stats',
			'simpleHistoryStats',
			[
				'data' => [
					'activityOverview' => $data['overview_activity_by_date'] ? $data['overview_activity_by_date'] : [],
					'topUsers' => $data['user_rankings'] ? $data['user_rankings'] : [],
				],
				'strings' => [
					'weekdays' => [
						__( 'Sunday', 'simple-history' ),
						__( 'Monday', 'simple-history' ),
						__( 'Tuesday', 'simple-history' ),
						__( 'Wednesday', 'simple-history' ),
						__( 'Thursday', 'simple-history' ),
						__( 'Friday', 'simple-history' ),
						__( 'Saturday', 'simple-history' ),
					],
					'events' => __( 'Events', 'simple-history' )
				],
			]
		);
	}
}
