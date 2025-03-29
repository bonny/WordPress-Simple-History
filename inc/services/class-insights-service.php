<?php

namespace Simple_History\Services;

use Simple_History\Helpers;
use Simple_History\Simple_History;
use Simple_History\Menu_Page;
use Simple_History\Services\Service;
use WP_Session_Tokens;

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
	 * Get currently logged in users.
	 *
	 * @return array Array of currently logged in users with their last activity.
	 */
	public function get_logged_in_users() {
		$logged_in_users = [];
		$all_users = get_users();

		foreach ( $all_users as $user ) {
			$sessions = WP_Session_Tokens::get_instance( $user->ID );

			if ( $sessions->get_all() ) {
				$logged_in_users[] = [
					'user' => $user,
					'sessions' => count( $sessions->get_all() ),
				];
			}
		}

		return $logged_in_users;
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

		// Get date range for the last 7 days.
		$defaults = $this->get_default_date_range();
		$date_from = $defaults['date_from'];
		$date_to = $defaults['date_to'];

		// Get insights data.
		$top_users = $this->get_top_users( $date_from, $date_to, 10 );
		$activity_overview = $this->get_activity_overview( $date_from, $date_to );
		$common_actions = $this->get_most_common_actions( $date_from, $date_to, 10 );
		$peak_times = $this->get_peak_activity_times( $date_from, $date_to );
		$peak_days = $this->get_peak_days( $date_from, $date_to );
		$logged_in_users = $this->get_logged_in_users();

		// Format logger names for common actions.
		$formatted_common_actions = array_map(
			function ( $action ) {
				$action->logger = $this->format_logger_name( $action->logger );
				return $action;
			},
			$common_actions ? $common_actions : []
		);

		// Pass data to JavaScript.
		wp_localize_script(
			'simple-history-insights',
			'simpleHistoryInsights',
			[
				'topUsers' => $top_users ? $top_users : [],
				'activityOverview' => $activity_overview ? $activity_overview : [],
				'commonActions' => $formatted_common_actions,
				'peakTimes' => $peak_times ? $peak_times : [],
				'peakDays' => $peak_days ? $peak_days : [],
				'dateRange' => [
					'from' => gmdate( 'Y-m-d', $date_from ),
					'to' => gmdate( 'Y-m-d', $date_to ),
				],
				'strings' => [
					'topUsers' => __( 'Top Users', 'simple-history' ),
					'activityOverview' => __( 'Activity Overview', 'simple-history' ),
					'commonActions' => __( 'Most Common Actions', 'simple-history' ),
					'peakTimes' => __( 'Peak Activity Times', 'simple-history' ),
					'peakDays' => __( 'Peak Activity Days', 'simple-history' ),
					'actions' => __( 'Actions', 'simple-history' ),
					'users' => __( 'Users', 'simple-history' ),
					'events' => __( 'Events', 'simple-history' ),
					'time' => __( 'Time', 'simple-history' ),
					'day' => __( 'Day', 'simple-history' ),
					'dateRange' => sprintf(
						/* translators: 1: Start date, 2: End date */
						__( 'Data shown for period: %1$s to %2$s', 'simple-history' ),
						gmdate( get_option( 'date_format' ), $date_from ),
						gmdate( get_option( 'date_format' ), $date_to )
					),
				],
			]
		);
		?>
		<div class="wrap">
			<h1><?php echo esc_html_x( 'Simple History Insights', 'insights page headline', 'simple-history' ); ?></h1>
			
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

			<div class="sh-InsightsDashboard">
				<div class="sh-InsightsDashboard-section">
					<h2><?php echo esc_html_x( 'Currently Logged In Users', 'insights section title', 'simple-history' ); ?></h2>
					<div class="sh-InsightsDashboard-content">
						<div class="sh-InsightsDashboard-activeUsers">
							<?php if ( $logged_in_users ) : ?>
								<ul class="sh-InsightsDashboard-userList">
									<?php foreach ( $logged_in_users as $user_data ) : ?>
										<li class="sh-InsightsDashboard-userItem">
											<?php
											$user = $user_data['user'];
											echo get_avatar( $user->ID, 32 );
											?>
											<div class="sh-InsightsDashboard-userInfo">
												<strong><?php echo esc_html( $user->display_name ); ?></strong>
												<span class="sh-InsightsDashboard-userRole">
													<?php echo esc_html( implode( ', ', $user->roles ) ); ?>
												</span>
												<span class="sh-InsightsDashboard-userSessions">
													<?php
													printf(
														/* translators: %d: number of active sessions */
														esc_html( _n( '%d active session', '%d active sessions', $user_data['sessions'], 'simple-history' ) ),
														esc_html( $user_data['sessions'] )
													);
													?>
												</span>
											</div>
										</li>
									<?php endforeach; ?>
								</ul>
							<?php else : ?>
								<p><?php esc_html_e( 'No users are currently logged in.', 'simple-history' ); ?></p>
							<?php endif; ?>
						</div>
					</div>
				</div>

				<div class="sh-InsightsDashboard-section sh-InsightsDashboard-section--wide">
					<h2><?php echo esc_html_x( 'Top Users', 'insights section title', 'simple-history' ); ?></h2>
					<div class="sh-InsightsDashboard-content sh-InsightsDashboard-content--sideBySide">
						<div class="sh-InsightsDashboard-chartContainer">
							<canvas id="topUsersChart" class="sh-InsightsDashboard-chart"></canvas>
						</div>
						<?php if ( $top_users && count( $top_users ) > 0 ) : ?>
							<div class="sh-InsightsDashboard-tableContainer">
								<table class="widefat striped">
									<thead>
										<tr>
											<th><?php echo esc_html_x( 'User', 'insights table header', 'simple-history' ); ?></th>
											<th><?php echo esc_html_x( 'Actions', 'insights table header', 'simple-history' ); ?></th>
										</tr>
									</thead>
									<tbody>
										<?php foreach ( $top_users as $user ) : ?>
											<tr>
												<td>
												<?php
													/* translators: %s: user ID number */
													echo esc_html( $user->display_name ? $user->display_name : sprintf( __( 'User ID %s', 'simple-history' ), $user->user_id ) );
												?>
												</td>
												<td><?php echo esc_html( number_format_i18n( $user->count ) ); ?></td>
											</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
							</div>
						<?php endif; ?>
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

				<div class="sh-InsightsDashboard-section">
					<h2><?php echo esc_html_x( 'Peak Activity Days', 'insights section title', 'simple-history' ); ?></h2>
					<div class="sh-InsightsDashboard-content">
						<canvas id="peakDaysChart" class="sh-InsightsDashboard-chart"></canvas>
					</div>
				</div>

				<div class="sh-InsightsDashboard-section sh-InsightsDashboard-section--extraWide">
					<h2><?php echo esc_html_x( 'Activity Calendar', 'insights section title', 'simple-history' ); ?></h2>
					<div class="sh-InsightsDashboard-content">
						<?php $this->output_activity_calendar( $date_from, $date_to, $activity_overview ); ?>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Get default date range.
	 *
	 * @return array {
	 *     Array of timestamps for the default date range (last 7 days).
	 *
	 *     @type int $date_from Unix timestamp for start date (7 days ago).
	 *     @type int $date_to   Unix timestamp for end date (current time).
	 * }
	 */
	private function get_default_date_range() {
		return [
			'date_from' => strtotime( '-7 days' ),
			'date_to' => time(),
		];
	}

	/**
	 * Get top users by activity count.
	 *
	 * @param int $date_from  Required. Start date as Unix timestamp.
	 * @param int $date_to    Required. End date as Unix timestamp.
	 * @param int $limit      Optional. Number of users to return. Default 10.
	 * @return array|false Array of users with their activity counts, or false if invalid dates.
	 */
	public function get_top_users( $date_from, $date_to, $limit = 10 ) {
		global $wpdb;

		if ( ! $date_from || ! $date_to ) {
			return false;
		}

		return $wpdb->get_results(
			$wpdb->prepare(
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
					AND h.date >= FROM_UNIXTIME(%d)
					AND h.date <= FROM_UNIXTIME(%d)
				GROUP BY 
					c.value
				ORDER BY 
					count DESC
				LIMIT %d",
				$date_from,
				$date_to,
				$limit
			)
		);
	}

	/**
	 * Get activity overview by date.
	 *
	 * @param int $date_from  Required. Start date as Unix timestamp.
	 * @param int $date_to    Required. End date as Unix timestamp.
	 * @return array|false Array of dates with their activity counts, or false if invalid dates. Dates are in MySQL format (YYYY-MM-DD).
	 */
	public function get_activity_overview( $date_from, $date_to ) {
		global $wpdb;

		if ( ! $date_from || ! $date_to ) {
			return false;
		}

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
					DATE(date) as date,
					COUNT(*) as count
				FROM 
					{$wpdb->prefix}simple_history
				WHERE 
					date >= FROM_UNIXTIME(%d)
					AND date <= FROM_UNIXTIME(%d)
				GROUP BY 
					DATE(date)
				ORDER BY 
					date ASC",
				$date_from,
				$date_to
			)
		);
	}

	/**
	 * Get most common actions.
	 *
	 * @param int $date_from  Required. Start date as Unix timestamp.
	 * @param int $date_to    Required. End date as Unix timestamp.
	 * @param int $limit      Optional. Number of actions to return. Default 10.
	 * @return array|false Array of actions with their counts, or false if invalid dates.
	 */
	public function get_most_common_actions( $date_from, $date_to, $limit = 10 ) {
		global $wpdb;

		if ( ! $date_from || ! $date_to ) {
			return false;
		}

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
					logger,
					level,
					COUNT(*) as count
				FROM 
					{$wpdb->prefix}simple_history
				WHERE 
					date >= FROM_UNIXTIME(%d)
					AND date <= FROM_UNIXTIME(%d)
				GROUP BY 
					logger, level
				ORDER BY 
					count DESC
				LIMIT %d",
				$date_from,
				$date_to,
				$limit
			)
		);
	}

	/**
	 * Get peak activity times.
	 *
	 * @param int $date_from  Required. Start date as Unix timestamp.
	 * @param int $date_to    Required. End date as Unix timestamp.
	 * @return array|false Array of hours (0-23) with their activity counts, or false if invalid dates.
	 */
	public function get_peak_activity_times( $date_from, $date_to ) {
		global $wpdb;

		if ( ! $date_from || ! $date_to ) {
			return false;
		}

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
					HOUR(date) as hour,
					COUNT(*) as count
				FROM 
					{$wpdb->prefix}simple_history
				WHERE 
					date >= FROM_UNIXTIME(%d)
					AND date <= FROM_UNIXTIME(%d)
				GROUP BY 
					HOUR(date)
				ORDER BY 
					hour ASC",
				$date_from,
				$date_to
			)
		);
	}

	/**
	 * Get peak days of the week.
	 *
	 * @param int $date_from  Required. Start date as Unix timestamp.
	 * @param int $date_to    Required. End date as Unix timestamp.
	 * @return array|false Array of weekdays (0-6, Sunday-Saturday) with their activity counts, or false if invalid dates.
	 */
	public function get_peak_days( $date_from, $date_to ) {
		global $wpdb;

		if ( ! $date_from || ! $date_to ) {
			return false;
		}

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT 
					DAYOFWEEK(date) - 1 as day,
					COUNT(*) as count
				FROM 
					{$wpdb->prefix}simple_history
				WHERE 
					date >= FROM_UNIXTIME(%d)
					AND date <= FROM_UNIXTIME(%d)
				GROUP BY 
					DAYOFWEEK(date)
				ORDER BY 
					day ASC",
				$date_from,
				$date_to
			)
		);
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

	/**
	 * Output the activity calendar view.
	 *
	 * @param int   $date_from         Start date as Unix timestamp.
	 * @param int   $date_to           End date as Unix timestamp.
	 * @param array $activity_overview Array of activity data by date.
	 */
	private function output_activity_calendar( $date_from, $date_to, $activity_overview ) {
		?>
		<div class="sh-InsightsDashboard-calendar">
			<?php
			// Get the first and last day of the date range.
			$start_date = new \DateTime( gmdate( 'Y-m-d', $date_from ) );
			$end_date = new \DateTime( gmdate( 'Y-m-d', $date_to ) );

			// Get the first and last day of the month.
			$first_day_of_month = clone $start_date;
			$first_day_of_month->modify( 'first day of this month' );
			$last_day_of_month = clone $start_date;
			$last_day_of_month->modify( 'last day of this month' );

			$current_date = clone $first_day_of_month;
			$start_weekday = (int) $first_day_of_month->format( 'w' );

			// Create array of dates with their event counts.
			$date_counts = array();
			foreach ( $activity_overview as $day ) {
				$date_counts[ $day->date ] = (int) $day->count;
			}

			// Get month name.
			$month_name = $start_date->format( 'F Y' );
			?>
			<div class="sh-InsightsDashboard-calendarMonth">
				<h3 class="sh-InsightsDashboard-calendarTitle"><?php echo esc_html( $month_name ); ?></h3>
				<div class="sh-InsightsDashboard-calendarGrid">
					<?php
					// Output weekday headers.
					$weekdays = array(
						__( 'Sun', 'simple-history' ),
						__( 'Mon', 'simple-history' ),
						__( 'Tue', 'simple-history' ),
						__( 'Wed', 'simple-history' ),
						__( 'Thu', 'simple-history' ),
						__( 'Fri', 'simple-history' ),
						__( 'Sat', 'simple-history' ),
					);
					foreach ( $weekdays as $weekday ) {
						echo '<div class="sh-InsightsDashboard-calendarHeader">' . esc_html( $weekday ) . '</div>';
					}

					// Add empty cells for days before the start of the month.
					for ( $i = 0; $i < $start_weekday; $i++ ) {
						echo '<div class="sh-InsightsDashboard-calendarDay sh-InsightsDashboard-calendarDay--empty"></div>';
					}

					// Output calendar days.
					while ( $current_date <= $last_day_of_month ) {
						$date_str = $current_date->format( 'Y-m-d' );
						$count = isset( $date_counts[ $date_str ] ) ? $date_counts[ $date_str ] : 0;
						$is_in_range = $current_date >= $start_date && $current_date <= $end_date;

						$classes = array( 'sh-InsightsDashboard-calendarDay' );

						if ( ! $is_in_range ) {
							$classes[] = 'sh-InsightsDashboard-calendarDay--outOfRange';
						} elseif ( $count > 0 ) {
							if ( $count < 10 ) {
								$classes[] = 'sh-InsightsDashboard-calendarDay--low';
							} elseif ( $count < 50 ) {
								$classes[] = 'sh-InsightsDashboard-calendarDay--medium';
							} else {
								$classes[] = 'sh-InsightsDashboard-calendarDay--high';
							}
						}

						$title = $is_in_range ?
							/* translators: %d: number of events */
							sprintf( _n( '%d event', '%d events', $count, 'simple-history' ), $count ) :
							__( 'Outside selected date range', 'simple-history' );
						?>
						<div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" 
							title="<?php echo esc_attr( $title ); ?>">
							<span class="sh-InsightsDashboard-calendarDayNumber"><?php echo esc_html( $current_date->format( 'j' ) ); ?></span>
							<?php if ( $is_in_range ) : ?>
								<span class="sh-InsightsDashboard-calendarDayCount"><?php echo esc_html( number_format_i18n( $count ) ); ?></span>
							<?php endif; ?>
						</div>
						<?php
						$current_date->modify( '+1 day' );
					}

					// Add empty cells for days after the end of the month to complete the grid.
					$end_weekday = (int) $last_day_of_month->format( 'w' );
					$remaining_days = 6 - $end_weekday;
					for ( $i = 0; $i < $remaining_days; $i++ ) {
						echo '<div class="sh-InsightsDashboard-calendarDay sh-InsightsDashboard-calendarDay--empty"></div>';
					}
					?>
				</div>
			</div>
		</div>
		<?php
	}
}
