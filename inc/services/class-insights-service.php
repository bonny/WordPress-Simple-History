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
	 * Get date range based on period parameter.
	 *
	 * @return array {
	 *     Array of timestamps for the selected date range.
	 *
	 *     @type int $date_from Unix timestamp for start date.
	 *     @type int $date_to   Unix timestamp for end date.
	 * }
	 */
	private function get_selected_date_range() {
		$period = isset( $_GET['period'] ) ? sanitize_text_field( wp_unslash( $_GET['period'] ) ) : '7d';
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
			default:
				$date_from = strtotime( '-7 days' );
				break;
		}

		return [
			'date_from' => $date_from,
			'date_to' => $date_to,
		];
	}

	/**
	 * Output the dashboard stats section.
	 *
	 * @param int    $total_events Total number of events.
	 * @param int    $total_users  Total number of users.
	 * @param object $last_edit    Last edit action details.
	 */
	private function output_dashboard_stats( $total_events, $total_users, $last_edit ) {
		?>
		<div class="sh-InsightsDashboard-stats">
			<div class="sh-InsightsDashboard-stat">
				<span class="sh-InsightsDashboard-statLabel"><?php esc_html_e( 'Total Events', 'simple-history' ); ?></span>
				<span class="sh-InsightsDashboard-statValue"><?php echo esc_html( number_format_i18n( $total_events ) ); ?></span>
			</div>
			<div class="sh-InsightsDashboard-stat">
				<span class="sh-InsightsDashboard-statLabel"><?php esc_html_e( 'Active Users', 'simple-history' ); ?></span>
				<span class="sh-InsightsDashboard-statValue"><?php echo esc_html( number_format_i18n( $total_users ) ); ?></span>
			</div>
			<?php if ( $last_edit ) { ?>
				<div class="sh-InsightsDashboard-stat">
					<span class="sh-InsightsDashboard-statLabel"><?php esc_html_e( 'Last Action', 'simple-history' ); ?></span>
					<span class="sh-InsightsDashboard-statValue">
						<?php
						printf(
							/* translators: 1: user's display name, 2: time ago */
							esc_html__( '%1$s, %2$s ago', 'simple-history' ),
							esc_html( $last_edit->display_name ),
							esc_html( human_time_diff( strtotime( $last_edit->date ) ) )
						);
						?>
					</span>
				</div>
			<?php } ?>
		</div>
		<?php
	}

	/**
	 * Output the date range section.
	 *
	 * @param int $date_from Start date as Unix timestamp.
	 * @param int $date_to   End date as Unix timestamp.
	 */
	private function output_date_range( $date_from, $date_to ) {
		?>
		<p class="sh-InsightsDashboard-dateRange">
			<?php
			echo esc_html(
				sprintf(
					/* translators: 1: Start date, 2: End date */
					__( 'Data shown for period: %1$s to %2$s', 'simple-history' ),
					gmdate( get_option( 'date_format' ), $date_from ),
					gmdate( get_option( 'date_format' ), $date_to )
				)
			);
			?>
		</p>
		<?php
	}

	/**
	 * Output the date filters section.
	 */
	private function output_date_filters() {
		$current_period = isset( $_GET['period'] ) ? sanitize_text_field( wp_unslash( $_GET['period'] ) ) : '7d';
		$current_page = menu_page_url( 'simple_history_insights_page', false );

		// Define the time periods.
		$time_periods = array(
			'1h'  => array(
				'label' => _x( '1 Hour', 'insights date filter 1 hour', 'simple-history' ),
				'short_label' => _x( '1H', 'insights date filter 1 hour short', 'simple-history' ),
			),
			'24h' => array(
				'label' => _x( '24 Hours', 'insights date filter 24 hours', 'simple-history' ),
				'short_label' => _x( '24H', 'insights date filter 24 hours short', 'simple-history' ),
			),
			'7d'  => array(
				'label' => _x( '7 Days', 'insights date filter 7 days', 'simple-history' ),
				'short_label' => _x( '7D', 'insights date filter 7 days short', 'simple-history' ),
			),
			'14d' => array(
				'label' => _x( '14 Days', 'insights date filter 14 days', 'simple-history' ),
				'short_label' => _x( '14D', 'insights date filter 14 days short', 'simple-history' ),
			),
			'1m'  => array(
				'label' => _x( '1 Month', 'insights date filter 1 month', 'simple-history' ),
				'short_label' => _x( '1M', 'insights date filter 1 month short', 'simple-history' ),
			),
		);
		?>
		<div class="sh-InsightsDashboard-filters" role="navigation" aria-label="<?php esc_attr_e( 'Time period navigation', 'simple-history' ); ?>">
			<div class="sh-InsightsDashboard-dateFilters">
				<span class="sh-InsightsDashboard-dateFilters-label" id="timeperiod-label">
					<?php echo esc_html_x( 'Time period:', 'insights date filter label', 'simple-history' ); ?>
				</span>
				<div class="sh-InsightsDashboard-dateFilters-buttons" role="group" aria-labelledby="timeperiod-label">
					<?php foreach ( $time_periods as $period => $labels ) : ?>
						<a 
							href="<?php echo esc_url( add_query_arg( 'period', $period, $current_page ) ); ?>" 
							class="sh-InsightsDashboard-dateFilter <?php echo $current_period === $period ? 'is-active' : ''; ?>"
							<?php echo $current_period === $period ? 'aria-current="page"' : ''; ?>
							title="<?php echo esc_attr( $labels['label'] ); ?>"
						>
							<span class="screen-reader-text"><?php echo esc_html( $labels['label'] ); ?></span>
							<span aria-hidden="true"><?php echo esc_html( $labels['short_label'] ); ?></span>
						</a>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Enqueue required scripts and styles for the insights page.
	 */
	private function enqueue_scripts_and_styles() {
		wp_enqueue_script(
			'simple-history-insights',
			SIMPLE_HISTORY_DIR_URL . 'js/simple-history-insights.js',
			[ 'wp-element', 'wp-components', 'wp-i18n', 'wp-api-fetch', 'chartjs' ],
			SIMPLE_HISTORY_VERSION,
			true
		);

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
	}

	/**
	 * Output the insights page.
	 */
	public function output_page() {
		$date_range = $this->get_selected_date_range();
		$date_from = $date_range['date_from'];
		$date_to = $date_range['date_to'];

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
			Insights_View::output_dashboard_stats( $data['total_events'], $data['total_users'], $data['last_edit'] );
			$this->output_wordpress_stats( $data['wordpress_stats'] );
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
			'activity_overview' => $this->stats->get_activity_overview( $date_from, $date_to ),
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
			],
			// Add posts and pages statistics.
			'posts_pages_stats' => [
				'created' => $this->stats->get_posts_pages_created( $date_from, $date_to ),
				'updated' => $this->stats->get_posts_pages_updated( $date_from, $date_to ),
				'deleted' => $this->stats->get_posts_pages_deleted( $date_from, $date_to ),
				'most_edited' => $this->stats->get_most_edited_posts( $date_from, $date_to, 5 ),
			],
			// Add stats object for user activity lookups.
			'stats' => $this->stats,
		];

		// Format top users data for the chart.
		$data['formatted_top_users'] = array_map(
			function ( $user ) {
				return [
					/* translators: %s: numeric user ID */
					'name' => $user->display_name ? $user->display_name : sprintf( __( 'User ID %s', 'simple-history' ), $user->user_id ),
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
					'activityOverview' => $data['activity_overview'] ? $data['activity_overview'] : [],
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

	/**
	 * Output the WordPress core and plugins statistics section.
	 *
	 * @param array $wordpress_stats Array of WordPress statistics.
	 */
	private function output_wordpress_stats( $wordpress_stats ) {
		?>
		<div class="sh-InsightsDashboard-section">
			<h2><?php echo esc_html_x( 'WordPress Core and Plugins', 'insights section title', 'simple-history' ); ?></h2>
			<div class="sh-InsightsDashboard-content">
				<div class="sh-InsightsDashboard-stats">
					<div class="sh-InsightsDashboard-stat">
						<span class="sh-InsightsDashboard-statLabel"><?php esc_html_e( 'Core Updates', 'simple-history' ); ?></span>
						<span class="sh-InsightsDashboard-statValue"><?php echo esc_html( number_format_i18n( $wordpress_stats['core_updates'] ) ); ?></span>
					</div>
					<div class="sh-InsightsDashboard-stat">
						<span class="sh-InsightsDashboard-statLabel"><?php esc_html_e( 'Plugin Updates', 'simple-history' ); ?></span>
						<span class="sh-InsightsDashboard-statValue"><?php echo esc_html( number_format_i18n( $wordpress_stats['plugin_updates'] ) ); ?></span>
					</div>
					<div class="sh-InsightsDashboard-stat">
						<span class="sh-InsightsDashboard-statLabel"><?php esc_html_e( 'Plugins Installed', 'simple-history' ); ?></span>
						<span class="sh-InsightsDashboard-statValue"><?php echo esc_html( number_format_i18n( $wordpress_stats['plugin_installs'] ) ); ?></span>
					</div>
					<div class="sh-InsightsDashboard-stat">
						<span class="sh-InsightsDashboard-statLabel"><?php esc_html_e( 'Plugins Deleted', 'simple-history' ); ?></span>
						<span class="sh-InsightsDashboard-statValue"><?php echo esc_html( number_format_i18n( $wordpress_stats['plugin_deletions'] ) ); ?></span>
					</div>
					<div class="sh-InsightsDashboard-stat">
						<span class="sh-InsightsDashboard-statLabel"><?php esc_html_e( 'Available Plugin Updates', 'simple-history' ); ?></span>
						<span class="sh-InsightsDashboard-statValue"><?php echo esc_html( number_format_i18n( $this->stats->get_available_plugin_updates() ) ); ?></span>
					</div>
				</div>
			</div>
		</div>
		<?php
	}
}
