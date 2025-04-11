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
					__( 'Stats & Summaries', 'simple-history' ),
				),
				[
					'span' => [
						'class' => [],
					],
				]
			);
			?>
		</h1>
		<?php
	}

	/**
	 * Output the date filters section and the date range.
	 *
	 * @param int $date_from Start date as Unix timestamp.
	 * @param int $date_to   End date as Unix timestamp.
	 */
	public static function output_filters( $date_from, $date_to ) {
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
			'3m'  => array(
				'label' => _x( '3 Months', 'insights date filter 3 months', 'simple-history' ),
				'short_label' => _x( '3M', 'insights date filter 3 months short', 'simple-history' ),
			),
			'6m'  => array(
				'label' => _x( '6 Months', 'insights date filter 6 months', 'simple-history' ),
				'short_label' => _x( '6M', 'insights date filter 6 months short', 'simple-history' ),
			),
			'12m' => array(
				'label' => _x( '12 Months', 'insights date filter 12 months', 'simple-history' ),
				'short_label' => _x( '12M', 'insights date filter 12 months short', 'simple-history' ),
			),
		);

		?>
		<div class="sh-InsightsDashboard-filters" role="navigation" aria-label="<?php esc_attr_e( 'Time period navigation', 'simple-history' ); ?>">

			<?php
			self::output_date_range( $date_from, $date_to );
			?>
	
			<div class="sh-InsightsDashboard-dateFilters">
				<form method="get" action="<?php echo esc_url( $current_page ); ?>">
					<?php
					// Add any existing query parameters except 'period'.
					foreach ( $_GET as $key => $value ) {
						if ( in_array( $key, array( 'period', 'page' ) ) ) {
							continue;
						}

						echo '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '">';
					}

					// Add page parameter (so we return to the same page).
					echo '<input type="hidden" name="page" value="simple_history_insights_page">';

					?>
					<select 
						name="period" 
						id="period-select" 
						class="sh-InsightsDashboard-dateSelect" 
						onchange="this.form.submit()"
						aria-label="<?php esc_attr_e( 'Select time period', 'simple-history' ); ?>"
					>
						<?php foreach ( $time_periods as $period => $labels ) : ?>
							<option 
								value="<?php echo esc_attr( $period ); ?>" 
								<?php selected( $current_period, $period ); ?>
							>
								<?php echo esc_html( $labels['label'] ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</form>
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
						__( '%1$s - %2$s', 'simple-history' ),
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
	 * This is the largest card on the dashboard. Shows the summary of the
	 * number of events for the selected date range, without giving away
	 * too much information.
	 *
	 * @param int   $total_events Total number of events.
	 * @param array $user_stats Array of user statistics.
	 * @param array $top_users Array of top users data.
	 * @param array $activity_overview Array of activity data by date.
	 * @param int   $date_from Start date as Unix timestamp.
	 * @param int   $date_to   End date as Unix timestamp.
	 */
	public static function output_events_overview( $total_events, $user_stats, $top_users, $activity_overview, $date_from, $date_to ) {
		?>
		<div class="sh-InsightsDashboard-card sh-InsightsDashboard-card--wide sh-InsightsDashboard-card--tall">
			<div class="sh-InsightsDashboard-dateRange">
				<div class="sh-InsightsDashboard-stat">
					<span class="sh-InsightsDashboard-statLabel"><?php esc_html_e( 'Total events', 'simple-history' ); ?></span>
					<span class="sh-InsightsDashboard-statValue sh-InsightsDashboard-statValue--large"><?php echo esc_html( number_format_i18n( $total_events ) ); ?></span>
				</div>

				<div class="sh-InsightsDashboard-stat">
					<span class="sh-InsightsDashboard-statLabel"><?php esc_html_e( 'Users with most events', 'simple-history' ); ?></span>
					<span class="sh-InsightsDashboard-statValue"><?php self::output_top_users_avatar_list( $top_users ); ?></span>
				</div>
			</div>		

			<div class="sh-InsightsDashboard-chartContainer">
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
	 * Output the logged in users section.
	 *
	 * @param array $logged_in_users Array of currently logged in users.
	 */
	public static function output_logged_in_users_section( $logged_in_users ) {
		?>
		<div class="sh-InsightsDashboard-section">
			<h2><?php echo esc_html_x( 'Currently Logged In Users', 'insights section title', 'simple-history' ); ?></h2>

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
		<?php
	}

	/**
	 * Output the top users section.
	 *
	 * @param array $top_users Array of top users data.
	 */
	public static function output_top_users_section( $top_users ) {
		?>
		<div class="sh-InsightsDashboard-card sh-InsightsDashboard-card--wide">
			<h2 
				class="sh-InsightsDashboard-cardTitle" 
				style="--sh-badge-background-color: var(--sh-color-green-light);"
			>
				<?php echo esc_html_x( 'User activity', 'insights section title', 'simple-history' ); ?>
			</h2>

			<div class="sh-InsightsDashboard-stat">
				<div class="sh-InsightsDashboard-statLabel">Most active users</div>
				<div class="sh-InsightsDashboard-statValue">
					<?php
					// Output a nice list of users with avatars.
					if ( $top_users && count( $top_users ) > 0 ) {
						self::output_top_users_avatar_list( $top_users );
					}
					?>
				</div>
			</div>

			<div class="sh-InsightsDashboard-content">
				<?php
				if ( $top_users && count( $top_users ) > 0 ) {
					self::output_top_users_table( $top_users );
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Output the top posts and pages section.
	 *
	 * @param array $top_posts_and_pages Array of top posts and pages data.
	 */
	public static function output_top_posts_and_pages_section( $top_posts_and_pages ) {
		?>
		<div class="sh-InsightsDashboard-card sh-InsightsDashboard-card--wide">
			<h2 
				class="sh-InsightsDashboard-cardTitle" 
				style="--sh-badge-background-color: var(--sh-color-green-light);"
			>
				<?php echo esc_html_x( 'Most edited posts and pages', 'insights section title', 'simple-history' ); ?>
			</h2>

			<p>Events can be page created, updated, deleted, trashed or restored.</p>

			<div class="sh-InsightsDashboard-content">
				<?php
				if ( $top_posts_and_pages && count( $top_posts_and_pages ) > 0 ) {
					self::output_top_posts_and_pages_table( $top_posts_and_pages );
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Output the table of top posts and pages.
	 *
	 * @param object $top_posts_and_pages Array of top posts and pages data.
	 */
	public static function output_top_posts_and_pages_table( $top_posts_and_pages ) {
		?>
		<div class="sh-InsightsDashboard-tableContainer">
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php echo esc_html_x( 'Post', 'insights table header', 'simple-history' ); ?></th>
						<th><?php echo esc_html_x( 'Number of events', 'insights table header', 'simple-history' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $top_posts_and_pages as $post ) { ?>
						<tr>
							<td>
								<span class="dashicons dashicons-admin-page"></span>
								<?php echo esc_html( $post->post_title ); ?>
							</td>
							<td>
								<?php echo esc_html( $post->edit_count ); ?>
							</td>
						</tr>
					<?php } ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Output the avatar list of top users.
	 *
	 * @param array $top_users Array of top users data.
	 */
	public static function output_top_users_avatar_list( $top_users ) {
		?>
		<ul class="sh-InsightsDashboard-userList">
			<?php
			$loop_count = 0;
			$user_count = count( $top_users );
			foreach ( $top_users as $user ) {
				// Set z-index to reverse order, so first user is on top.
				$style = 'z-index: ' . ( $user_count - $loop_count ) . ';';
				?>
				<li class="sh-InsightsDashboard-userItem" style="<?php echo esc_attr( $style ); ?>">
					<img 
						src="<?php echo esc_url( $user['avatar'] ); ?>" 
						alt="<?php echo esc_attr( $user['display_name'] ); ?>" 
						class="sh-InsightsDashboard-userAvatar"
					>
					<span class="sh-InsightsDashboard-userData">
						<span class="sh-InsightsDashboard-userName"><?php echo esc_html( $user['display_name'] ); ?></span>
						<span class="sh-InsightsDashboard-userActions"><?php echo esc_html( number_format_i18n( $user['count'] ) ); ?> events</span>
					</span>
				</li>
				<?php

				$loop_count++;
			}
			?>
		</ul>
		<?php
	}

	/**
	 * Output the table of top users.
	 *
	 * @param array $top_users Array of top users data.
	 */
	public static function output_top_users_table( $top_users ) {
		?>
		<div class="sh-InsightsDashboard-tableContainer" style="--sh-avatar-size: 20px;">
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php echo esc_html_x( 'User', 'insights table header', 'simple-history' ); ?></th>
						<th><?php echo esc_html_x( 'Events', 'insights table header', 'simple-history' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $top_users as $user ) { ?>
						<tr>
							<td>
								<img 
										src="<?php echo esc_url( $user['avatar'] ); ?>" 
										alt="<?php echo esc_attr( $user['display_name'] ); ?>" 
										class="sh-InsightsDashboard-userAvatar"
									>
								<?php
								/* translators: %s: user ID number */
								echo esc_html( $user['display_name'] );
								?>
							</td>
							<td>
								<?php echo esc_html( number_format_i18n( $user['count'] ) ); ?>
							</td>
						</tr>
					<?php } ?>
				</tbody>
			</table>
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
		$section_class = 'sh-InsightsDashboard-card';
		if ( $css_class ) {
			$section_class .= ' ' . $css_class;
		}
		?>
		<div class="<?php echo esc_attr( $section_class ); ?>">
			<h2
				class="sh-InsightsDashboard-cardTitle" 
				style="--sh-badge-background-color: var(--sh-color-green-light);"
			>
				<?php echo esc_html( $title ); ?>
			</h2>

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
		<div class="sh-InsightsDashboard-card sh-InsightsDashboard-card--wide">
			<h2 
				class="sh-InsightsDashboard-cardTitle"	 
				style="--sh-badge-background-color: var(--sh-color-pink);"
			>
				<?php echo esc_html_x( 'User profile activity', 'insights section title', 'simple-history' ); ?>
			</h2>

			<div class="sh-InsightsDashboard-content">
				<div class="sh-InsightsDashboard-stats">
					<div class="sh-InsightsDashboard-stat">
						<span class="sh-InsightsDashboard-statLabel"><?php esc_html_e( 'Logins', 'simple-history' ); ?></span>
						<span class="sh-InsightsDashboard-statValue"><?php echo esc_html( number_format_i18n( $user_stats['user_logins_successful'] ) ); ?></span>
					</div>

					<div class="sh-InsightsDashboard-stat">
						<span class="sh-InsightsDashboard-statLabel"><?php esc_html_e( 'Failed logins', 'simple-history' ); ?></span>
						<span class="sh-InsightsDashboard-statValue"><?php echo esc_html( number_format_i18n( $user_stats['user_logins_failed'] ) ); ?></span>
					</div>

					<div class="sh-InsightsDashboard-stat">
						<span class="sh-InsightsDashboard-statLabel"><?php esc_html_e( 'Profile updates', 'simple-history' ); ?></span>
						<span class="sh-InsightsDashboard-statValue"><?php echo esc_html( number_format_i18n( $user_stats['user_profiles_updated'] ) ); ?></span>
					</div>
				</div>

				<div class="sh-InsightsDashboard-stats">
					<div class="sh-InsightsDashboard-stat">
						<span class="sh-InsightsDashboard-statLabel"><?php esc_html_e( 'Added users', 'simple-history' ); ?></span>
						<span class="sh-InsightsDashboard-statValue"><?php echo esc_html( number_format_i18n( $user_stats['user_accounts_added'] ) ); ?></span>
					</div>

					<div class="sh-InsightsDashboard-stat">
						<span class="sh-InsightsDashboard-statLabel"><?php esc_html_e( 'Removed users', 'simple-history' ); ?></span>
						<span class="sh-InsightsDashboard-statValue"><?php echo esc_html( number_format_i18n( $user_stats['user_accounts_removed'] ) ); ?></span>
					</div>
				</div>

				<?php
				// Display top users table if available.
				// self::output_top_users_table( $user_stats['top_users'], $stats );
				?>
			</div>
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
		<div class="sh-InsightsDashboard-card sh-InsightsDashboard-card--wide">
			<h2 
				class="sh-InsightsDashboard-cardTitle" 
				style="--sh-badge-background-color: var(--sh-color-yellow);"
			>
				<?php echo esc_html_x( 'Posts & pages activity', 'insights section title', 'simple-history' ); ?>
			</h2>

			<div class="sh-InsightsDashboard-content">
				<div class="sh-InsightsDashboard-stats">
					<div class="sh-InsightsDashboard-stat">
						<span class="sh-InsightsDashboard-statLabel"><?php esc_html_e( 'Created', 'simple-history' ); ?></span>
						<span class="sh-InsightsDashboard-statValue"><?php echo esc_html( number_format_i18n( $posts_pages_stats['content_items_created'] ) ); ?></span>
					</div>

					<div class="sh-InsightsDashboard-stat">
						<span class="sh-InsightsDashboard-statLabel"><?php esc_html_e( 'Updated', 'simple-history' ); ?></span>
						<span class="sh-InsightsDashboard-statValue"><?php echo esc_html( number_format_i18n( $posts_pages_stats['content_items_updated'] ) ); ?></span>
					</div>

					<div class="sh-InsightsDashboard-stat">
						<span class="sh-InsightsDashboard-statLabel"><?php esc_html_e( 'Trashed', 'simple-history' ); ?></span>
						<span class="sh-InsightsDashboard-statValue"><?php echo esc_html( number_format_i18n( $posts_pages_stats['content_items_trashed'] ) ); ?></span>
					</div>

					<div class="sh-InsightsDashboard-stat">
						<span class="sh-InsightsDashboard-statLabel"><?php esc_html_e( 'Deleted', 'simple-history' ); ?></span>
						<span class="sh-InsightsDashboard-statValue"><?php echo esc_html( number_format_i18n( $posts_pages_stats['content_items_deleted'] ) ); ?></span>
					</div>
				</div>
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
		<div class="sh-InsightsDashboard-card sh-InsightsDashboard-card--wide">
			<h2 
				class="sh-InsightsDashboard-cardTitle" 
				style="--sh-badge-background-color: var(--sh-color-green-light);"
			>
				<?php echo esc_html_x( 'Media', 'insights section title', 'simple-history' ); ?>
			</h2>
			
			<div class="sh-InsightsDashboard-content">
				<div class="sh-InsightsDashboard-stats">
					<div class="sh-InsightsDashboard-stat">
						<span class="sh-InsightsDashboard-statLabel"><?php esc_html_e( 'Uploads', 'simple-history' ); ?></span>
						<span class="sh-InsightsDashboard-statValue"><?php echo esc_html( number_format_i18n( $media_stats['media_files_uploaded'] ) ); ?></span>
					</div>
					<div class="sh-InsightsDashboard-stat">
						<span class="sh-InsightsDashboard-statLabel"><?php esc_html_e( 'Edits', 'simple-history' ); ?></span>
						<span class="sh-InsightsDashboard-statValue"><?php echo esc_html( number_format_i18n( $media_stats['media_files_edited'] ) ); ?></span>
					</div>
					<div class="sh-InsightsDashboard-stat">
						<span class="sh-InsightsDashboard-statLabel"><?php esc_html_e( 'Deletions', 'simple-history' ); ?></span>
						<span class="sh-InsightsDashboard-statValue"><?php echo esc_html( number_format_i18n( $media_stats['media_files_deleted'] ) ); ?></span>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Output a plugin table section.
	 *
	 * @param string       $title Title of the table section.
	 * @param string       $action_type Type of plugin action to show.
	 * @param int          $date_from Start date as Unix timestamp.
	 * @param int          $date_to End date as Unix timestamp.
	 * @param Events_Stats $stats Stats instance.
	 */
	public static function output_plugin_table( $title, $action_type, $date_from, $date_to, $stats ) {
		$plugins = $stats->get_plugin_details( $action_type, $date_from, $date_to );

		if ( empty( $plugins ) ) {
			echo '<p>Error: No plugins found for action type: ' . esc_html( $action_type ) . '</p>';

			return;
		}
		?>
		<div class="sh-InsightsDashboard-pluginTable">
			<h3><?php echo esc_html( $title ); ?></h3>

			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Plugin Name', 'simple-history' ); ?></th>
						<th><?php esc_html_e( 'Event date', 'simple-history' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					foreach ( $plugins as $plugin ) {
						?>
						<tr>
							<td><?php echo esc_html( $plugin['name'] ); ?></td>
							<td><?php echo esc_html( $plugin['when'] ); ?></td>
						</tr>
						<?php
					}
					?>
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
	 * @param array        $plugin_stats Array of WordPress statistics.
	 * @param Events_Stats $stats          Stats instance.
	 * @param int          $date_from      Start date as Unix timestamp.
	 * @param int          $date_to        End date as Unix timestamp.
	 */
	public static function output_plugin_stats( $plugin_stats, $stats, $date_from, $date_to ) {
		?>
		<div class="sh-InsightsDashboard-card sh-InsightsDashboard-card--wide">
			<h2 class="sh-InsightsDashboard-cardTitle"><?php esc_html_e( 'Plugins', 'simple-history' ); ?></h2>

			<div class="sh-InsightsDashboard-stats">
				<div class="sh-InsightsDashboard-stat">
					<div class="sh-InsightsDashboard-statLabel"><?php esc_html_e( 'Installations', 'simple-history' ); ?></div>
					<div class="sh-InsightsDashboard-statValue"><?php echo esc_html( $plugin_stats['plugin_installs_completed'] ); ?></div>
				</div>

				<div class="sh-InsightsDashboard-stat">
					<div class="sh-InsightsDashboard-statLabel"><?php esc_html_e( 'Activations', 'simple-history' ); ?></div>
					<div class="sh-InsightsDashboard-statValue"><?php echo esc_html( $plugin_stats['plugin_activations_completed'] ); ?></div>
				</div>

				<div class="sh-InsightsDashboard-stat">
					<div class="sh-InsightsDashboard-statLabel"><?php esc_html_e( 'Updates found', 'simple-history' ); ?></div>
					<div class="sh-InsightsDashboard-statValue"><?php echo esc_html( $plugin_stats['plugin_updates_found'] ); ?></div>
				</div>

				<div class="sh-InsightsDashboard-stat">
					<div class="sh-InsightsDashboard-statLabel"><?php esc_html_e( 'Updates done', 'simple-history' ); ?></div>
					<div class="sh-InsightsDashboard-statValue"><?php echo esc_html( $plugin_stats['plugin_updates_completed'] ); ?></div>
				</div>
			</div>

			<div class="sh-InsightsDashboard-stats">
				<div class="sh-InsightsDashboard-stat">
					<div class="sh-InsightsDashboard-statLabel"><?php esc_html_e( 'Deactivations', 'simple-history' ); ?></div>
					<div class="sh-InsightsDashboard-statValue"><?php echo esc_html( $plugin_stats['plugin_deactivations_completed'] ); ?></div>
				</div>

				<div class="sh-InsightsDashboard-stat">
					<div class="sh-InsightsDashboard-statLabel"><?php esc_html_e( 'Deletions', 'simple-history' ); ?></div>
					<div class="sh-InsightsDashboard-statValue"><?php echo esc_html( $plugin_stats['plugin_deletions_completed'] ); ?></div>
				</div>
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
			// Display overview section.
			self::output_events_overview(
				$data['overview_total_events'],
				$data['user_stats'],
				$data['user_rankings_formatted'],
				$data['overview_activity_by_date'],
				$date_from,
				$date_to
			);

			self::output_chart_section(
				_x( 'Peak activity times', 'insights section title', 'simple-history' ),
				'peakTimesChart'
			);

			self::output_chart_section(
				_x( 'Peak activity days', 'insights section title', 'simple-history' ),
				'peakDaysChart'
			);

			self::output_plugin_stats( $data['plugin_stats'], $data['stats'], $date_from, $date_to );

				self::output_user_stats_section( $data['user_stats'], $data['stats'] );

				self::output_posts_pages_stats_section( $data['content_stats'] );

				self::output_media_stats_section( $data['media_stats'] );

				self::output_top_users_section( $data['user_rankings_formatted'] );

				self::output_top_posts_and_pages_section( $data['content_stats']['content_items_most_edited'] );

			?>
			<div class="sh-InsightsDashboard-card sh-InsightsDashboard-card--full">
				<h2 class="sh-InsightsDashboard-cardTitle"><?php esc_html_e( 'Plugins details', 'simple-history' ); ?></h2>

				<?php
				self::output_plugin_table( __( 'Installed plugins', 'simple-history' ), 'installed', $date_from, $date_to, $data['stats'] );
				self::output_plugin_table( __( 'Activated plugins', 'simple-history' ), 'activated', $date_from, $date_to, $data['stats'] );
				self::output_plugin_table( __( 'Deactivated plugins', 'simple-history' ), 'deactivated', $date_from, $date_to, $data['stats'] );
				self::output_plugin_table( __( 'Deleted plugins', 'simple-history' ), 'deleted', $date_from, $date_to, $data['stats'] );
				?>
			</div>

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
			<h2><?php echo esc_html_x( 'Activity calendar', 'insights section title', 'simple-history' ); ?></h2>
			<div class="sh-InsightsDashboard-content">
				<?php self::output_activity_calendar( $date_from, $date_to, $activity_overview_by_date ); ?>
			</div>
		</div>
		<?php
	}
}
