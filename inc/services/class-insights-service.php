<?php

namespace Simple_History\Services;

use WP_Session_Tokens;
use Simple_History\Helpers;
use Simple_History\Menu_Page;
use Simple_History\Simple_History;
use Simple_History\Services\Service;
use Simple_History\Activity_Analytics;
use Simple_History\Services\Admin_Pages;
use Simple_History\Insights_View;

/**
 * Service class that handles insights functionality.
 */
class Insights_Service extends Service {
	/**
	 * Stats instance.
	 *
	 * @var Activity_Analytics
	 */
	private $stats;

	/**
	 * Called when service is loaded.
	 */
	public function loaded() {
		// Only enable for users with experimental features enabled.
		if ( ! Helpers::experimental_features_is_enabled() ) {
			return;
		}

		$this->stats = new Activity_Analytics();

		add_action( 'admin_menu', [ $this, 'add_menu' ], 10 );
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
		$new_text = '<span class="sh-PremiumFeatureBadge" style="--sh-badge-background-color: var(--sh-color-yellow);">' . __( 'New', 'simple-history' ) . '</span>';
		$insights_page = ( new Menu_Page() )
			->set_page_title( _x( 'Insights - Simple History', 'dashboard title name', 'simple-history' ) )
			->set_menu_title( _x( 'Insights', 'dashboard menu name', 'simple-history' ) . ' ' . $new_text )
			->set_menu_slug( 'simple_history_insights_page' )
			->set_capability( 'manage_options' )
			->set_callback( [ $this, 'output_page' ] )
			->set_parent( Simple_History::MENU_PAGE_SLUG )
			->set_location( 'submenu' );

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
	private function enqueue_scripts_and_styles() {
		wp_enqueue_script(
			'simple-history-insights',
			SIMPLE_HISTORY_DIR_URL . 'js/simple-history-insights.js',
			[ 'wp-element', 'wp-components', 'wp-i18n', 'wp-api-fetch' ],
			SIMPLE_HISTORY_VERSION,
			true
		);

		wp_enqueue_style(
			'simple-history-insights',
			SIMPLE_HISTORY_DIR_URL . 'css/simple-history-insights.css',
			[],
			SIMPLE_HISTORY_VERSION
		);

		// wp_enqueue_script(
		// 'chartjs',
		// 'https://cdn.jsdelivr.net/npm/chart.js@4.4.8',
		// [],
		// '4.4.8',
		// true
		// );
	}

	/**
	 * Output the insights page.
	 */
	public function output_page() {
		[ 'date_from' => $date_from, 'date_to' => $date_to ] = $this->get_selected_date_range();

		$data = $this->prepare_insights_data( $date_from, $date_to );

		$this->enqueue_scripts_and_styles();
		$this->localize_script_data( $data, $date_from, $date_to );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo Admin_Pages::header_output();

		?>
		<div class="wrap sh-Page-content">
			<?php
			Insights_View::output_page_title();
			Insights_View::output_date_filters();
			Insights_View::output_date_range( $date_from, $date_to );
			Insights_View::output_dashboard_content( $data, $date_from, $date_to );
			?>
		</div>
		<?php
	}

	/**
	 * Prepare data for the insights page.
	 *
	 * @param int $date_from Start date as Unix timestamp.
	 * @param int $date_to   End date as Unix timestamp.
	 * @return array Array of prepared data for the insights page.
	 */
	private function prepare_insights_data( $date_from, $date_to ) {
		$data = [
			'total_events' => $this->stats->get_total_events( $date_from, $date_to ),
			'total_users' => $this->stats->get_total_users( $date_from, $date_to ),
			'last_edit' => $this->stats->get_last_edit_action( $date_from, $date_to ),
			'activity_overview_by_date' => $this->stats->get_activity_overview_by_date( $date_from, $date_to ),
			'peak_times' => $this->stats->get_peak_activity_times( $date_from, $date_to ),
			'peak_days' => $this->stats->get_peak_days( $date_from, $date_to ),
			'logged_in_users' => $this->stats->get_logged_in_users(),
			// Add new user statistics.
			'user_stats' => [
				'failed_logins' => $this->stats->get_failed_logins( $date_from, $date_to ),
				'users_added' => $this->stats->get_users_added( $date_from, $date_to ),
				'users_removed' => $this->stats->get_users_removed( $date_from, $date_to ),
				'users_updated' => $this->stats->get_users_updated( $date_from, $date_to ),
				'successful_logins' => $this->stats->get_successful_logins( $date_from, $date_to ),
				'top_users' => $this->stats->get_top_users( $date_from, $date_to, 10 ),
			],
			// Add WordPress core and plugin statistics.
			'wordpress_stats' => [
				'core_updates' => $this->stats->get_wordpress_core_updates( $date_from, $date_to ),
				'plugin_updates' => $this->stats->get_plugin_updates( $date_from, $date_to ),
				'plugin_installs' => $this->stats->get_plugin_installs( $date_from, $date_to ),
				'plugin_deletions' => $this->stats->get_plugin_deletions( $date_from, $date_to ),
				'plugin_activations' => $this->stats->get_plugin_activations( $date_from, $date_to ),
				'plugin_deactivations' => $this->stats->get_plugin_deactivations( $date_from, $date_to ),
			],
			// Add posts and pages statistics.
			'posts_pages_stats' => [
				'created' => $this->stats->get_posts_pages_created( $date_from, $date_to ),
				'updated' => $this->stats->get_posts_pages_updated( $date_from, $date_to ),
				'deleted' => $this->stats->get_posts_pages_deleted( $date_from, $date_to ),
				'trashed' => $this->stats->get_posts_pages_trashed( $date_from, $date_to ),
				'most_edited' => $this->stats->get_most_edited_posts( $date_from, $date_to, 5 ),
			],
			// Add media statistics.
			'media_stats' => [
				'uploads' => $this->stats->get_media_uploads( $date_from, $date_to ),
				'edits' => $this->stats->get_media_edits( $date_from, $date_to ),
				'deletions' => $this->stats->get_media_deletions( $date_from, $date_to ),
			],
			// Add stats object for user activity lookups.
			'stats' => $this->stats,
		];

		// Format top users data for the chart.
		$data['formatted_top_users'] = array_map(
			function ( $user ) {
				return [
					'id' => $user->user_id,
					/* translators: %s: numeric user ID */
					'display_name' => $user->display_name,
					'avatar' => get_avatar_url( $user->user_id ),
					'count' => (int) $user->count,
				];
			},
			$data['user_stats']['top_users'] ? $data['user_stats']['top_users'] : []
		);

		return $data;
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
			'simple-history-insights',
			'simpleHistoryInsights',
			[
				'data' => [
					'activityOverview' => $data['activity_overview_by_date'] ? $data['activity_overview_by_date'] : [],
					'topUsers' => $data['formatted_top_users'] ? $data['formatted_top_users'] : [],
					'peakTimes' => $data['peak_times'] ? $data['peak_times'] : [],
					'peakDays' => $data['peak_days'] ? $data['peak_days'] : [],
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
