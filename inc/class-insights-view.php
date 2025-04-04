<?php

namespace Simple_History;

use Simple_History\Helpers;

/**
 * Class that handles view/output logic for the insights page.
 */
class Insights_View {
	/**
	 * Output the page title section.
	 */
	public static function output_page_title() {
		?>
		<h1>
			<?php
			echo wp_kses(
				Helpers::get_settings_section_title_output(
					__( 'Insights (summaries and analytics)', 'simple-history' ),
					'troubleshoot'
				),
				[
					'span' => [
						'class' => [],
					],
				]
			);
			?>
		</h1>

		<p>(Note: This is still an experimental feature and things may change.)</p>
		<?php
	}

	/**
	 * Output the date filters section.
	 */
	public static function output_date_filters() {
		$current_period = isset( $_GET['period'] ) ? sanitize_text_field( wp_unslash( $_GET['period'] ) ) : '1m';
		$current_page = menu_page_url( 'simple_history_insights_page', false );

		// Define the time periods.
		$time_periods = array(

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
	 * Output the date range section.
	 *
	 * @param int $date_from Start date as Unix timestamp.
	 * @param int $date_to   End date as Unix timestamp.
	 */
	public static function output_date_range( $date_from, $date_to ) {
		?>
		<div class="sh-InsightsDashboard-dateRangeContainer">
			<h2 class="sh-InsightsDashboard-dateRangeHeading">
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: Start date, 2: End date */
						__( 'Showing summary data for period %1$s to %2$s', 'simple-history' ),
						wp_date( get_option( 'date_format' ), $date_from ),
						wp_date( get_option( 'date_format' ), $date_to )
					)
				);
				?>
			</h2>
		</div>
		<?php
	}

	/**
	 * Output the events overview section that show the total number of events
	 * for a period with a bar chart of number of events per day.
	 *
	 * @param int   $total_events Total number of events.
	 * @param array $activity_overview Array of activity data by date.
	 * @param int   $date_from Start date as Unix timestamp.
	 * @param int   $date_to   End date as Unix timestamp.
	 */
	public static function output_events_overview( $total_events, $activity_overview, $date_from, $date_to ) {
		?>
		<div class="sh-InsightsDashboard-card sh-InsightsDashboard-card--wide">
			<div class="sh-InsightsDashboard-stat">
				<span class="sh-InsightsDashboard-statLabel"><?php esc_html_e( 'Events', 'simple-history' ); ?></span>
				<span class="sh-InsightsDashboard-statValue"><?php echo esc_html( number_format_i18n( $total_events ) ); ?></span>
			</div>

			<div class="sh-InsightsDashboard-smallChartContainer">
				<canvas id="eventsOverviewChart" class="sh-InsightsDashboard-chart"></canvas>
			</div>

			<div class="sh-InsightsDashboard-dateRange">
				<span class="sh-InsightsDashboard-dateRangeValue">
					<?php
					echo esc_html( wp_date( get_option( 'date_format' ), $date_from ) );
					?>
				</span>
				<span class="sh-InsightsDashboard-dateRangeValue">
					<?php
					echo esc_html( wp_date( get_option( 'date_format' ), $date_to ) );
					?>
				</span>
			</div>

		</div>
		<?php
	}


	/**
	 * Output the dashboard overview stats section.
	 *
	 * @param int    $total_events Total number of events.
	 * @param int    $total_users  Total number of users.
	 * @param object $last_edit    Last edit action details.
	 */
	public static function output_dashboard_overview_stats( $total_events, $total_users, $last_edit ) {
		?>
		<div class="sh-InsightsDashboard-stats">
			<div class="sh-InsightsDashboard-stat">
				<span class="sh-InsightsDashboard-statLabel"><?php esc_html_e( 'Events', 'simple-history' ); ?></span>
				<span class="sh-InsightsDashboard-statValue"><?php echo esc_html( number_format_i18n( $total_events ) ); ?></span>
			</div>
	
			<div class="sh-InsightsDashboard-stat">
				<span class="sh-InsightsDashboard-statLabel"><?php esc_html_e( 'Users', 'simple-history' ); ?></span>
				<span class="sh-InsightsDashboard-statValue"><?php echo esc_html( number_format_i18n( $total_users ) ); ?></span>
			</div>
	
			<?php
			if ( $last_edit ) {
				?>
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
				<?php
			}
			?>
		</div>
		<?php
	}

	/**
	 * Output the logged in users section.
	 *
	 * @param array $logged_in_users Array of currently logged in users.
	 */
	public static function output_logged_in_users_section( $logged_in_users ) {
		?>
		<div class="sh-InsightsDashboard-section">
			<h2><?php echo esc_html_x( 'Currently Logged In Users', 'insights section title', 'simple-history' ); ?></h2>
			<div class="sh-InsightsDashboard-content">
				<div class="sh-InsightsDashboard-activeUsers">
					<?php
					if ( $logged_in_users ) {
						?>
						<ul class="sh-InsightsDashboard-userList">
							<?php
							foreach ( $logged_in_users as $user_data ) {
								?>
								<li class="sh-InsightsDashboard-userItem">
									<?php
									$user = $user_data['user'];
									// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
									echo Helpers::get_avatar( $user->user_email, 32 );
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
												esc_html( _n( '%d active session', '%d active sessions', $user_data['sessions_count'], 'simple-history' ) ),
												esc_html( $user_data['sessions_count'] )
											);
											?>
										</span>

										<?php if ( ! empty( $user_data['sessions'] ) ) : ?>
											<div class="sh-InsightsDashboard-userSessions-details">
												<?php foreach ( $user_data['sessions'] as $session ) : ?>
													<div class="sh-InsightsDashboard-userSession">
														<span class="sh-InsightsDashboard-userLastLogin">
															<?php
															$login_time = date_i18n( 'F d, Y H:i A', $session['login'] );
															printf(
																/* translators: %s: login date and time */
																esc_html__( 'Login: %s', 'simple-history' ),
																esc_html( $login_time )
															);
															?>
														</span>
														
														<span class="sh-InsightsDashboard-userExpiration">
															<?php
															$expiration_time = date_i18n( 'F d, Y H:i A', $session['expiration'] );
															printf(
																/* translators: %s: session expiration date and time */
																esc_html__( 'Expires: %s', 'simple-history' ),
																esc_html( $expiration_time )
															);
															?>
														</span>
														
														<?php if ( ! empty( $session['ip'] ) ) : ?>
															<span class="sh-InsightsDashboard-userIP">
																<?php
																printf(
																	/* translators: %s: IP address */
																	esc_html__( 'IP: %s', 'simple-history' ),
																	esc_html( $session['ip'] )
																);
																?>
															</span>
														<?php endif; ?>
													</div>
												<?php endforeach; ?>
											</div>
										<?php endif; ?>
									</div>
								</li>
								<?php
							}
							?>
						</ul>
						<?php
					} else {
						?>
						<p><?php esc_html_e( 'No users are currently logged in.', 'simple-history' ); ?></p>
						<?php
					}
					?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Output the top users section.
	 *
	 * @param array $top_users Array of top users data.
	 */
	public static function output_top_users_section( $top_users ) {
		?>
		<div class="sh-InsightsDashboard-section sh-InsightsDashboard-section--wide">
			<h2><?php echo esc_html_x( 'Top Users', 'insights section title', 'simple-history' ); ?></h2>
			<div class="sh-InsightsDashboard-content sh-InsightsDashboard-content--sideBySide">
				<div class="sh-InsightsDashboard-chartContainer">
					<canvas id="topUsersChart" class="sh-InsightsDashboard-chart"></canvas>
				</div>
				<?php if ( $top_users && count( $top_users ) > 0 ) { ?>
					<div class="sh-InsightsDashboard-tableContainer">
						<table class="widefat striped">
							<thead>
								<tr>
									<th><?php echo esc_html_x( 'User', 'insights table header', 'simple-history' ); ?></th>
									<th><?php echo esc_html_x( 'Actions', 'insights table header', 'simple-history' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $top_users as $user ) { ?>
									<tr>
										<td>
										<?php
											/* translators: %s: user ID number */
											echo esc_html( $user->display_name ? $user->display_name : sprintf( __( 'User ID %s', 'simple-history' ), $user->user_id ) );
										?>
										</td>
										<td><?php echo esc_html( number_format_i18n( $user->count ) ); ?></td>
									</tr>
								<?php } ?>
							</tbody>
						</table>
					</div>
				<?php } ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Output a chart section.
	 *
	 * @param string $title      Section title.
	 * @param string $chart_id   HTML ID for the chart canvas.
	 * @param string $css_class  Optional. Additional CSS class for the section.
	 */
	public static function output_chart_section( $title, $chart_id, $css_class = '' ) {
		$section_class = 'sh-InsightsDashboard-section';
		if ( $css_class ) {
			$section_class .= ' ' . $css_class;
		}
		?>
		<div class="<?php echo esc_attr( $section_class ); ?>">
			<h2><?php echo esc_html( $title ); ?></h2>
			<div class="sh-InsightsDashboard-content">
				<canvas id="<?php echo esc_attr( $chart_id ); ?>" class="sh-InsightsDashboard-chart"></canvas>
			</div>
		</div>
		<?php
	}

	/**
	 * Output the activity calendar view.
	 *
	 * @param int   $date_from         Start date as Unix timestamp.
	 * @param int   $date_to           End date as Unix timestamp.
	 * @param array $activity_overview Array of activity data by date.
	 */
	public static function output_activity_calendar( $date_from, $date_to, $activity_overview ) {
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
							<?php if ( $is_in_range ) { ?>
								<span class="sh-InsightsDashboard-calendarDayCount"><?php echo esc_html( number_format_i18n( $count ) ); ?></span>
							<?php } ?>
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

	/**
	 * Output the user activity statistics section.
	 *
	 * @param array  $user_stats Array of user statistics.
	 * @param object $stats Stats object for getting user activity.
	 */
	public static function output_user_stats_section( $user_stats, $stats ) {
		?>
		<div class="sh-InsightsDashboard-section sh-InsightsDashboard-section--wide">
			<h2><?php echo esc_html_x( 'User Activity Statistics', 'insights section title', 'simple-history' ); ?></h2>
			<div class="sh-InsightsDashboard-content">
				<div class="sh-InsightsDashboard-stats">
					<!-- Login Activity -->
					<div class="sh-InsightsDashboard-stat">
						<span class="sh-InsightsDashboard-statLabel"><?php esc_html_e( 'Successful Logins', 'simple-history' ); ?></span>
						<span class="sh-InsightsDashboard-statValue"><?php echo esc_html( number_format_i18n( $user_stats['successful_logins'] ) ); ?></span>
					</div>
					<div class="sh-InsightsDashboard-stat">
						<span class="sh-InsightsDashboard-statLabel"><?php esc_html_e( 'Failed Logins', 'simple-history' ); ?></span>
						<span class="sh-InsightsDashboard-statValue"><?php echo esc_html( number_format_i18n( $user_stats['failed_logins'] ) ); ?></span>
					</div>

					<!-- User Management -->
					<div class="sh-InsightsDashboard-stat">
						<span class="sh-InsightsDashboard-statLabel"><?php esc_html_e( 'Users Added', 'simple-history' ); ?></span>
						<span class="sh-InsightsDashboard-statValue"><?php echo esc_html( number_format_i18n( $user_stats['users_added'] ) ); ?></span>
					</div>
					<div class="sh-InsightsDashboard-stat">
						<span class="sh-InsightsDashboard-statLabel"><?php esc_html_e( 'Users Removed', 'simple-history' ); ?></span>
						<span class="sh-InsightsDashboard-statValue"><?php echo esc_html( number_format_i18n( $user_stats['users_removed'] ) ); ?></span>
					</div>
					<div class="sh-InsightsDashboard-stat">
						<span class="sh-InsightsDashboard-statLabel"><?php esc_html_e( 'Profile Updates', 'simple-history' ); ?></span>
						<span class="sh-InsightsDashboard-statValue"><?php echo esc_html( number_format_i18n( $user_stats['users_updated'] ) ); ?></span>
					</div>
				</div>

				<?php
				// Calculate login success rate if there were any login attempts.
				$total_login_attempts = $user_stats['successful_logins'] + $user_stats['failed_logins'];
				if ( $total_login_attempts > 0 ) {
					$success_rate = round( ( $user_stats['successful_logins'] / $total_login_attempts ) * 100 );
					?>
					<div class="sh-InsightsDashboard-extraStats">
						<p>
							<?php
							printf(
								/* translators: %d: login success rate percentage */
								esc_html__( 'Login Success Rate: %d%%', 'simple-history' ),
								esc_html( $success_rate )
							);
							?>
						</p>
					</div>
					<?php
				}

				// Display top users table if available.
				// self::output_top_users_table( $user_stats['top_users'], $stats );
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Output the top users table.
	 *
	 * @param array  $top_users Array of top users data.
	 * @param object $stats     Stats instance for getting user activity.
	 */
	public static function output_top_users_table( $top_users, $stats ) {
		if ( empty( $top_users ) ) {
			return;
		}
		?>
		<div class="sh-InsightsDashboard-topUsers">
			<h3><?php esc_html_e( 'Most Active Users', 'simple-history' ); ?></h3>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'User', 'simple-history' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'simple-history' ); ?></th>
						<th><?php esc_html_e( 'Last Active', 'simple-history' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $top_users as $user ) : ?>
						<tr>
							<td class="sh-InsightsDashboard-userCell">
								<?php
								// Try to get the full user data if we only have the ID.
								$wp_user = get_user_by( 'id', $user->user_id );
								if ( $wp_user ) {
									// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
									echo Helpers::get_avatar( $wp_user->user_email, 24 );
								}
								?>
								<span class="sh-InsightsDashboard-userName">
									<?php
									if ( $wp_user && $wp_user->display_name ) {
										echo esc_html( $wp_user->display_name );
									} else {
										/* translators: %s: numeric user ID */
										printf( esc_html__( 'User ID %s', 'simple-history' ), esc_html( $user->user_id ) );
									}
									?>
								</span>
							</td>
							<td><?php echo esc_html( number_format_i18n( $user->count ) ); ?></td>
							<td>
								<?php
								// Get the user's most recent activity time from the history table.
								$last_activity = $stats->get_user_last_activity( $user->user_id );
								if ( $last_activity ) {
									/* translators: %s: human readable time difference */
									printf( esc_html__( '%s ago', 'simple-history' ), esc_html( human_time_diff( strtotime( $last_activity ) ) ) );
								} else {
									echo '—';
								}
								?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Output the posts and pages statistics section.
	 *
	 * @param array $posts_pages_stats Array of posts and pages statistics.
	 */
	public static function output_posts_pages_stats_section( $posts_pages_stats ) {
		?>
		<div class="sh-InsightsDashboard-section sh-InsightsDashboard-section--wide">
			<h2><?php echo esc_html_x( 'Posts and Pages Activity', 'insights section title', 'simple-history' ); ?></h2>
			<div class="sh-InsightsDashboard-content">
				<div class="sh-InsightsDashboard-stats">
					<div class="sh-InsightsDashboard-stat">
						<span class="sh-InsightsDashboard-statLabel"><?php esc_html_e( 'Created', 'simple-history' ); ?></span>
						<span class="sh-InsightsDashboard-statValue"><?php echo esc_html( number_format_i18n( $posts_pages_stats['created'] ) ); ?></span>
					</div>
					<div class="sh-InsightsDashboard-stat">
						<span class="sh-InsightsDashboard-statLabel"><?php esc_html_e( 'Updated', 'simple-history' ); ?></span>
						<span class="sh-InsightsDashboard-statValue"><?php echo esc_html( number_format_i18n( $posts_pages_stats['updated'] ) ); ?></span>
					</div>
					<div class="sh-InsightsDashboard-stat">
						<span class="sh-InsightsDashboard-statLabel"><?php esc_html_e( 'Deleted', 'simple-history' ); ?></span>
						<span class="sh-InsightsDashboard-statValue"><?php echo esc_html( number_format_i18n( $posts_pages_stats['deleted'] ) ); ?></span>
					</div>
				</div>

				<?php if ( ! empty( $posts_pages_stats['most_edited'] ) ) : ?>
					<div class="sh-InsightsDashboard-mostEdited">
						<h3><?php esc_html_e( 'Most Edited Posts and Pages', 'simple-history' ); ?></h3>
						<table class="widefat striped">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Title', 'simple-history' ); ?></th>
									<th><?php esc_html_e( 'Edit Count', 'simple-history' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $posts_pages_stats['most_edited'] as $post ) : ?>
									<tr>
										<td><?php echo esc_html( $post->post_title ); ?></td>
										<td><?php echo esc_html( number_format_i18n( $post->edit_count ) ); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Output the media statistics section.
	 *
	 * @param array $media_stats Array of media statistics.
	 */
	public static function output_media_stats_section( $media_stats ) {
		?>
		<div class="sh-InsightsDashboard-section">
			<h2><?php echo esc_html_x( 'Media', 'insights section title', 'simple-history' ); ?></h2>
			<div class="sh-InsightsDashboard-content">
				<div class="sh-InsightsDashboard-stats">
					<div class="sh-InsightsDashboard-stat">
						<span class="sh-InsightsDashboard-statLabel"><?php esc_html_e( 'Media Uploads', 'simple-history' ); ?></span>
						<span class="sh-InsightsDashboard-statValue"><?php echo esc_html( number_format_i18n( $media_stats['uploads'] ) ); ?></span>
					</div>
					<div class="sh-InsightsDashboard-stat">
						<span class="sh-InsightsDashboard-statLabel"><?php esc_html_e( 'Media Edits', 'simple-history' ); ?></span>
						<span class="sh-InsightsDashboard-statValue"><?php echo esc_html( number_format_i18n( $media_stats['edits'] ) ); ?></span>
					</div>
					<div class="sh-InsightsDashboard-stat">
						<span class="sh-InsightsDashboard-statLabel"><?php esc_html_e( 'Media Deletions', 'simple-history' ); ?></span>
						<span class="sh-InsightsDashboard-statValue"><?php echo esc_html( number_format_i18n( $media_stats['deletions'] ) ); ?></span>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Output a plugin table section.
	 *
	 * @param string $title Title of the table section.
	 * @param string $action_type Type of plugin action to show.
	 * @param int    $date_from Start date as Unix timestamp.
	 * @param int    $date_to End date as Unix timestamp.
	 * @param object $stats Stats instance.
	 */
	public static function output_plugin_table( $title, $action_type, $date_from, $date_to, $stats ) {
		$plugins = $stats->get_plugin_details( $action_type, $date_from, $date_to );
		if ( empty( $plugins ) ) {
			return;
		}
		?>
		<div class="sh-InsightsDashboard-pluginTable">
			<h3><?php echo esc_html( $title ); ?></h3>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Plugin Name', 'simple-history' ); ?></th>
						<th><?php esc_html_e( 'Version', 'simple-history' ); ?></th>
						<th><?php esc_html_e( 'Date', 'simple-history' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $plugins as $plugin ) : ?>
						<tr>
							<td><?php echo esc_html( $plugin['name'] ); ?></td>
							<td><?php echo esc_html( $plugin['version'] ); ?></td>
							<td><?php echo esc_html( $plugin['date'] ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Output a table of plugins that have updates available.
	 *
	 * @param object $stats Stats instance.
	 */
	public static function output_plugins_with_updates_table( $stats ) {
		$plugins_with_updates = $stats->get_plugins_with_updates();
		if ( empty( $plugins_with_updates ) ) {
			return;
		}
		?>
		<div class="sh-InsightsDashboard-pluginTable">
			<h3><?php esc_html_e( 'Plugins with Updates Available', 'simple-history' ); ?></h3>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Plugin Name', 'simple-history' ); ?></th>
						<th><?php esc_html_e( 'Current Version', 'simple-history' ); ?></th>
						<th><?php esc_html_e( 'New Version', 'simple-history' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $plugins_with_updates as $plugin ) : ?>
						<tr>
							<td><?php echo esc_html( $plugin['name'] ); ?></td>
							<td><?php echo esc_html( $plugin['current_version'] ); ?></td>
							<td><?php echo esc_html( $plugin['new_version'] ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Output the WordPress core and plugins statistics section.
	 *
	 * @param array              $wordpress_stats Array of WordPress statistics.
	 * @param Activity_Analytics $stats          Stats instance.
	 * @param int                $date_from      Start date as Unix timestamp.
	 * @param int                $date_to        End date as Unix timestamp.
	 */
	public static function output_plugin_stats( $wordpress_stats, $stats, $date_from, $date_to ) {
		?>
		<div class="sh-InsightsDashboard-card sh-InsightsDashboard-card--wide">
			<h2 class="sh-InsightsDashboard-cardTitle sh-PremiumFeatureBadge"><?php esc_html_e( 'Plugins', 'simple-history' ); ?></h2>

			<div class="sh-InsightsDashboard-stats">
				<!-- <div class="sh-InsightsDashboard-stat">
					<div class="sh-InsightsDashboard-statLabel"><?php esc_html_e( 'WordPress Updates', 'simple-history' ); ?></div>
					<div class="sh-InsightsDashboard-statValue"><?php echo esc_html( $wordpress_stats['core_updates'] ); ?></div>
				</div> -->

				<div class="sh-InsightsDashboard-stat">
					<div class="sh-InsightsDashboard-statLabel"><?php esc_html_e( 'Updated', 'simple-history' ); ?></div>
					<div class="sh-InsightsDashboard-statValue"><?php echo esc_html( $wordpress_stats['plugin_updates'] ); ?></div>
				</div>
				
				<div class="sh-InsightsDashboard-stat">
					<div class="sh-InsightsDashboard-statLabel"><?php esc_html_e( 'Installed', 'simple-history' ); ?></div>
					<div class="sh-InsightsDashboard-statValue"><?php echo esc_html( $wordpress_stats['plugin_installs'] ); ?></div>
				</div>

				<div class="sh-InsightsDashboard-stat">
					<div class="sh-InsightsDashboard-statLabel"><?php esc_html_e( 'Activated', 'simple-history' ); ?></div>
					<div class="sh-InsightsDashboard-statValue"><?php echo esc_html( $wordpress_stats['plugin_activations'] ); ?></div>
				</div>

				<div class="sh-InsightsDashboard-stat">
					<div class="sh-InsightsDashboard-statLabel"><?php esc_html_e( 'Deactivated', 'simple-history' ); ?></div>
					<div class="sh-InsightsDashboard-statValue"><?php echo esc_html( $wordpress_stats['plugin_deactivations'] ); ?></div>
				</div>

				<div class="sh-InsightsDashboard-stat">
					<div class="sh-InsightsDashboard-statLabel"><?php esc_html_e( 'Deleted', 'simple-history' ); ?></div>
					<div class="sh-InsightsDashboard-statValue"><?php echo esc_html( $wordpress_stats['plugin_deletions'] ); ?></div>
				</div>

				<!-- <div class="sh-InsightsDashboard-stat">
					<div class="sh-InsightsDashboard-statLabel"><?php esc_html_e( 'Plugin Updates Found', 'simple-history' ); ?></div>
					<div class="sh-InsightsDashboard-statValue"><?php echo esc_html( $stats->get_available_plugin_updates() ); ?></div>
				</div> -->			

				<?php
				// Output various plugin tables.
				// self::output_plugin_table( __( 'Recently Updated Plugins', 'simple-history' ), 'updated', $date_from, $date_to, $stats );
				// self::output_plugin_table( __( 'Recently Deleted Plugins', 'simple-history' ), 'deleted', $date_from, $date_to, $stats );
				// self::output_plugin_table( __( 'Recently Activated Plugins', 'simple-history' ), 'activated', $date_from, $date_to, $stats );
				// self::output_plugin_table( __( 'Recently Deactivated Plugins', 'simple-history' ), 'deactivated', $date_from, $date_to, $stats );
				// self::output_plugins_with_updates_table( $stats );
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Output the main insights dashboard content.
	 *
	 * @param array $data      Insights data array.
	 * @param int   $date_from Start date as Unix timestamp.
	 * @param int   $date_to   End date as Unix timestamp.
	 */
	public static function output_dashboard_content( $data, $date_from, $date_to ) {
		?>
		<div class="sh-InsightsDashboard">
			<?php

			self::output_events_overview( $data['total_events'], $data['activity_overview_by_date'], $date_from, $date_to );

			// self::output_activity_calendar_section( $date_from, $date_to, $data['activity_overview_by_date'] );
			// self::output_dashboard_overview_stats( $data['total_events'], $data['total_users'], $data['last_edit'] );

			self::output_plugin_stats( $data['wordpress_stats'], $data['stats'], $date_from, $date_to );

			// self::output_logged_in_users_section( $data['logged_in_users'] );
			self::output_user_stats_section( $data['user_stats'], $data['stats'] );
			self::output_posts_pages_stats_section( $data['posts_pages_stats'] );
			self::output_media_stats_section( $data['media_stats'] );
			self::output_top_users_section( $data['user_stats']['top_users'] );

			// Output chart sections.
			// self::output_chart_section(
			// _x( 'Activity Overview', 'insights section title', 'simple-history' ),
			// 'activityChart'
			// );

			self::output_chart_section(
				_x( 'Peak Activity Times', 'insights section title', 'simple-history' ),
				'peakTimesChart'
			);

			self::output_chart_section(
				_x( 'Peak Activity Days', 'insights section title', 'simple-history' ),
				'peakDaysChart'
			);
			?>
		</div>
		<?php
	}

	/**
	 * Output the activity calendar section.
	 *
	 * @param int   $date_from Start date as Unix timestamp.
	 * @param int   $date_to   End date as Unix timestamp.
	 * @param array $activity_overview_by_date Activity overview by date.
	 */
	public static function output_activity_calendar_section( $date_from, $date_to, $activity_overview_by_date ) {
		?>
		<div class="sh-InsightsDashboard-section sh-InsightsDashboard-section--wide">
			<h2><?php echo esc_html_x( 'Activity Calendar', 'insights section title', 'simple-history' ); ?></h2>
			<div class="sh-InsightsDashboard-content">
				<?php self::output_activity_calendar( $date_from, $date_to, $activity_overview_by_date ); ?>
			</div>
		</div>
		<?php
	}
}
