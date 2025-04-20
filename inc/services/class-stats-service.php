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
			->set_page_title( _x( 'Stats & Summaries - Simple History', 'dashboard title name', 'simple-history' ) )
			->set_menu_title( _x( 'Stats & Summaries', 'dashboard menu name', 'simple-history' ) . ' ' . $new_badge_text )
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
		$period = isset( $_GET['period'] ) ? sanitize_text_field( wp_unslash( $_GET['period'] ) ) : '1m';
		$date_to = time();

		switch ( $period ) {
			case '1h':
				$date_from = strtotime( '-1 hour' );
				break;
			case '24h':
				$date_from = strtotime( '-24 hours' );
				break;
			case '14d':
				$date_from = strtotime( '-14 days' );
				break;
			case '1m':
				$date_from = strtotime( '-1 month' );
				break;
			case '7d':
				$date_from = strtotime( '-7 days' );
				break;
			case '3m':
				$date_from = strtotime( '-3 months' );
				break;
			case '6m':
				$date_from = strtotime( '-6 months' );
				break;
			case '12m':
				$date_from = strtotime( '-12 months' );
				break;
			default:
				$date_from = strtotime( '-1 month' );
				break;
		}

		return [
			'date_from' => $date_from,
			'date_to' => $date_to,
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

		// Get peak activity times.
		$peak_activity_times = $this->stats->get_peak_activity_times( $date_from, $date_to );

		// Get peak days.
		$peak_days = $this->stats->get_peak_days( $date_from, $date_to );

		// Get logged in users.
		$logged_in_users = $this->stats->get_logged_in_users();

		// Get user statistics.
		$user_stats = [
			'user_logins_successful' => $this->stats->get_successful_logins_count( $date_from, $date_to ),
			'user_logins_failed' => $this->stats->get_failed_logins_count( $date_from, $date_to ),
			'user_profiles_updated' => $this->stats->get_user_updated_count( $date_from, $date_to ),
			'user_accounts_added' => $this->stats->get_user_added_count( $date_from, $date_to ),
			'user_accounts_removed' => $this->stats->get_user_removed_count( $date_from, $date_to ),
			'total_count' => 0, // Will be calculated below.
		];
		$user_stats['total_count'] = array_sum( array_filter( array_values( $user_stats ), 'is_numeric' ) );

		// Get WordPress and plugin statistics.
		$plugin_stats = [
			'plugin_updates_completed' => $this->stats->get_plugin_updates_count( $date_from, $date_to ),
			'plugin_installs_completed' => $this->stats->get_plugin_installs_count( $date_from, $date_to ),
			'plugin_deletions_completed' => $this->stats->get_plugin_deletions_count( $date_from, $date_to ),
			'plugin_activations_completed' => $this->stats->get_plugin_activations_count( $date_from, $date_to ),
			'plugin_deactivations_completed' => $this->stats->get_plugin_deactivations_count( $date_from, $date_to ),
			'plugin_updates_found' => $this->stats->get_plugin_updates_found_count( $date_from, $date_to ),
			'total_count' => 0, // Initialize total count that will be summed up later.
		];
		$plugin_stats['total_count'] = array_sum( array_filter( array_values( $plugin_stats ), 'is_numeric' ) );

		// Get WordPress core statistics.
		$wordpress_stats = [
			'wordpress_core_updates_found' => $this->stats->get_wordpress_core_updates_found_count( $date_from, $date_to ),
			'wordpress_core_updates_completed' => $this->stats->get_wordpress_core_updates_count( $date_from, $date_to ),
			'total_count' => 0, // Will be calculated below.
		];
		$wordpress_stats['total_count'] = array_sum( array_filter( array_values( $wordpress_stats ), 'is_numeric' ) );

		// Get posts and pages statistics.
		$posts_pages_stats = [
			'content_items_created' => $this->stats->get_posts_pages_created( $date_from, $date_to ),
			'content_items_updated' => $this->stats->get_posts_pages_updated( $date_from, $date_to ),
			'content_items_deleted' => $this->stats->get_posts_pages_deleted( $date_from, $date_to ),
			'content_items_trashed' => $this->stats->get_posts_pages_trashed( $date_from, $date_to ),
			'content_items_most_edited' => $this->stats->get_most_edited_posts( $date_from, $date_to ),
			'total_count' => 0, // Will be calculated below.
		];
		// Don't include most_edited in total since it's an array of items.
		$posts_pages_stats['total_count'] = array_sum(
			array_filter(
				[
					$posts_pages_stats['content_items_created'],
					$posts_pages_stats['content_items_updated'],
					$posts_pages_stats['content_items_deleted'],
					$posts_pages_stats['content_items_trashed'],
				],
				'is_numeric'
			)
		);

		// Get detailed content stats.
		$detailed_content_stats = $this->stats->get_detailed_content_stats( $date_from, $date_to );
		if ( $detailed_content_stats ) {
			$posts_pages_stats = array_merge( $posts_pages_stats, $detailed_content_stats );
		}

		// Get media statistics for the period.
		$media_stats = [
			'media_files_uploaded' => $this->stats->get_media_uploads_count( $date_from, $date_to ),
			'media_files_edited' => $this->stats->get_media_edits_count( $date_from, $date_to ),
			'media_files_deleted' => $this->stats->get_media_deletions_count( $date_from, $date_to ),
			'total_count' => 0, // Will be calculated below.
		];
		$media_stats['total_count'] = array_sum( array_filter( array_values( $media_stats ), 'is_numeric' ) );

		$media_stats_details = [
			'media_files_uploaded_details' => $this->stats->get_media_uploaded_details( $date_from, $date_to ),
			'media_files_edited_details' => $this->stats->get_media_edited_details( $date_from, $date_to ),
			'media_files_deleted_details' => $this->stats->get_media_deleted_details( $date_from, $date_to ),
		];

		// Get top users.
		$top_users = $this->stats->get_top_users( $date_from, $date_to );

		return [
			'stats' => $this->stats,
			'overview_total_events' => $total_events,
			'overview_activity_by_date' => $activity_overview_by_date,
			'overview_peak_times' => $peak_activity_times,
			'overview_peak_days' => $peak_days,
			'overview_logged_in_users' => $logged_in_users,
			'user_stats' => $user_stats,
			'plugin_stats' => $plugin_stats,
			'wordpress_stats' => $wordpress_stats,
			'content_stats' => $posts_pages_stats,
			'media_stats' => $media_stats,
			'media_stats_details' => $media_stats_details,
			'user_rankings' => $top_users,
			'user_total_count' => $this->stats->get_total_users( $date_from, $date_to ),
			'user_stats_details' => $this->stats->get_detailed_user_stats( $date_from, $date_to ),
		];
	}

	/**
	 * Format top users data for the chart.
	 *
	 * @param array $top_users Array of top users.
	 * @return array Formatted top users data.
	 */
	private function format_top_users_data( $top_users ) {
		return $top_users ? $top_users : [];
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
					'peakTimes' => $data['overview_peak_times'] ? $data['overview_peak_times'] : [],
					'peakDays' => $data['overview_peak_days'] ? $data['overview_peak_days'] : [],
				],
				'dateRange' => [
					'from' => $date_from,
					'to' => $date_to,
				],
				'strings' => [
					'events' => __( 'Events', 'simple-history' ),
					'actions' => __( 'Actions', 'simple-history' ),
					'users' => __( 'Users', 'simple-history' ),
					'weekdays' => [
						__( 'Sunday', 'simple-history' ),
						__( 'Monday', 'simple-history' ),
						__( 'Tuesday', 'simple-history' ),
						__( 'Wednesday', 'simple-history' ),
						__( 'Thursday', 'simple-history' ),
						__( 'Friday', 'simple-history' ),
						__( 'Saturday', 'simple-history' ),
					],
				],
			]
		);
	}
}
