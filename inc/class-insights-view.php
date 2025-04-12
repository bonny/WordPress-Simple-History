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
						<?php
						foreach ( $time_periods as $period => $labels ) {
							?>
							<option 
								value="<?php echo esc_attr( $period ); ?>" 
								<?php selected( $current_period, $period ); ?>
							>
								<?php echo esc_html( $labels['label'] ); ?>
							</option>
							<?php
						}
						?>
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
	 * @param array $data Array with stats data.
	 * @param int   $date_from Start date as Unix timestamp.
	 * @param int   $date_to   End date as Unix timestamp.
	 */
	public static function output_events_overview( $data, $date_from, $date_to ) {
		$total_events = $data['overview_total_events'];
		$user_stats = $data['user_stats'];
		$top_users = $data['user_rankings_formatted'];
		$activity_overview = $data['overview_activity_by_date'];
		$user_total_count = $data['user_total_count'];
		?>
		<div class="sh-InsightsDashboard-card sh-InsightsDashboard-card--wide sh-InsightsDashboard-card--tall">

			<h2 class="sh-InsightsDashboard-cardTitle" style="--sh-badge-background-color: var(--sh-color-green-light);">
				<?php esc_html_e( 'Summary', 'simple-history' ); ?>
			</h2>

			<div class="sh-InsightsDashboard-dateRange">
				<div class="sh-InsightsDashboard-stat">
					<span class="sh-InsightsDashboard-statLabel"><?php esc_html_e( 'Total events', 'simple-history' ); ?></span>
					<span class="sh-InsightsDashboard-statValue sh-InsightsDashboard-statValue--large"><?php echo esc_html( number_format_i18n( $total_events ) ); ?></span>
				</div>

				<div class="sh-InsightsDashboard-stat">
					<span class="sh-InsightsDashboard-statLabel"><?php esc_html_e( 'Total users', 'simple-history' ); ?></span>
					<span class="sh-InsightsDashboard-statValue sh-InsightsDashboard-statValue--large"><?php echo esc_html( number_format_i18n( $user_total_count ) ); ?></span>
				</div>

				<div class="sh-InsightsDashboard-stat">
					<span class="sh-InsightsDashboard-statLabel"><?php esc_html_e( 'Users with most events', 'simple-history' ); ?></span>
					<span class="sh-InsightsDashboard-statValue"><?php self::output_top_users_avatar_list( $top_users ); ?></span>
				</div>
			</div>		
			
			<div class="sh-InsightsDashboard-dateRange">
				<?php
				// Output totals for:
				// - User profile activity
				// - Plugins
				// - Posts & pages activity
				// - Media
				// Output summary stats for key categories.
				$categories = array(
					array(
						'label' => __( 'User activity actions', 'simple-history' ),
						'value' => number_format_i18n( $user_stats['total_count'] ),
					),
					array(
						'label' => __( 'Content actions', 'simple-history' ),
						'value' => number_format_i18n( $data['content_stats']['total_count'] ),
					),
					array(
						'label' => __( 'Plugin actions', 'simple-history' ),
						'value' => number_format_i18n( $data['plugin_stats']['total_count'] ),
					),
					array(
						'label' => __( 'Media actions', 'simple-history' ),
						'value' => number_format_i18n( $data['media_stats']['total_count'] ),
					),
				);

				/*
				foreach ( $categories as $category ) {
					?>
					<div class="sh-InsightsDashboard-stat">
						<span class="sh-InsightsDashboard-statLabel"><?php echo esc_html( $category['label'] ); ?></span>
						<span class="sh-InsightsDashboard-statValue"><?php echo esc_html( $category['value'] ); ?></span>
					</div>
					<?php
				}

				*/
				?>
			</div>
	

			<div class="sh-InsightsDashboard-stat">
				<div class="sh-InsightsDashboard-statLabel">
					<?php esc_html_e( 'Activity by date', 'simple-history' ); ?>
				</div>

				<span class="sh-InsightsDashboard-statValue">
					<div class="sh-InsightsDashboard-chartContainer">
						<canvas id="eventsOverviewChart" class="sh-InsightsDashboard-chart"></canvas>
					</div>
				</span>
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
	 * @param array $data Array of insights data.
	 */
	public static function output_user_stats_section( $data ) {
		$user_stats = $data['user_stats'];
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
						<span class="sh-InsightsDashboard-statLabel"><?php esc_html_e( 'Successful logins', 'simple-history' ); ?></span>
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

				<details>
					<summary>
						<?php esc_html_e( 'Show details', 'simple-history' ); ?>
					</summary>

					<p>
						<?php esc_html_e( 'Showing max 50 events, ordered by count.', 'simple-history' ); ?>
					</p>

					<div class="" style="display: flex; gap: 2rem; flex-wrap: wrap;">
						<?php
						self::output_user_successful_logins_table( $data['user_stats_details']['successful_logins'] );
						self::output_user_failed_logins_table( $data['user_stats_details']['failed_logins'] );
						self::output_user_profile_updates_table( $data['user_stats_details']['profile_updates'] );
						self::output_user_added_table( $data['user_stats_details']['added_users'] );
						self::output_user_removed_table( $data['user_stats_details']['removed_users'] );
						?>
					</div>
				</details>

			</div>
		</div>
		<?php
	}

	/**
	 * Helper function to output a table with consistent structure.
	 *
	 * @param string   $title Table title.
	 * @param array    $headers Array of column headers.
	 * @param array    $data Array of data to display.
	 * @param callable $row_callback Callback function to format each row's data.
	 */
	private static function output_details_table( $title, $headers, $data, $row_callback ) {
		?>
		<div class="sh-InsightsDashboard-tableContainer" style="--sh-avatar-size: 20px;">
			<h3><?php echo esc_html( $title ); ?></h3>

			<?php
			if ( empty( $data ) ) {
				?>
				<p><?php esc_html_e( 'No plugins found.', 'simple-history' ); ?></p>
				<?php
			} else {
				?>
				<table class="widefat striped">
					<thead>
						<tr>
							<?php
							foreach ( $headers as $header ) {
								?>
								<th><?php echo esc_html( $header ); ?></th>
								<?php
							}
							?>
						</tr>
					</thead>
					<tbody>
						<?php
						foreach ( $data as $item ) {
							?>
							<tr>
								<?php
								foreach ( $row_callback( $item ) as $cell ) {
									?>
									<td><?php echo wp_kses_post( $cell ); ?></td>
									<?php
								}
								?>
							</tr>
							<?php
						}
						?>
					</tbody>
				</table>
				<?php
			}
			?>
		</div>
		<?php
	}

	/**
	 * Output a table of users with successful logins.
	 *
	 * @param array $successful_logins Array of successful login details.
	 */
	public static function output_user_successful_logins_table( $successful_logins ) {
		self::output_details_table(
			__( 'Users with successful logins', 'simple-history' ),
			[
				__( 'User', 'simple-history' ),
				__( 'Number of logins', 'simple-history' ),
			],
			$successful_logins,
			function ( $login ) {
				$user_avatar = get_avatar_url( $login->user_id );
				return [
					sprintf(
						'<img src="%s" alt="%s" class="sh-InsightsDashboard-userAvatar">%s',
						esc_url( $user_avatar ),
						esc_attr( $login->user_login ),
						esc_html( $login->user_login )
					),
					esc_html( $login->login_count ),
				];
			}
		);
	}

	/**
	 * Output a table of users with failed logins.
	 *
	 * @param array $failed_logins Array of failed login details.
	 */
	public static function output_user_failed_logins_table( $failed_logins ) {
		self::output_details_table(
			__( 'Accounts with failed logins', 'simple-history' ),
			[
				__( 'Account', 'simple-history' ),
				__( 'Number of failed logins', 'simple-history' ),
			],
			$failed_logins,
			function ( $login ) {
				return [
					esc_html( $login->attempted_username ),
					esc_html( $login->failed_count ),
				];
			}
		);
	}

	/**
	 * Output a table of user profile updates.
	 *
	 * @param array $profile_updates Array of profile update details.
	 */
	public static function output_user_profile_updates_table( $profile_updates ) {
		self::output_details_table(
			__( 'User profile updates', 'simple-history' ),
			[
				__( 'User', 'simple-history' ),
				__( 'Updates', 'simple-history' ),
			],
			$profile_updates,
			function ( $update ) {
				$user_avatar = get_avatar_url( $update->user_id );
				return [
					sprintf(
						'<img src="%s" alt="%s" class="sh-InsightsDashboard-userAvatar">%s',
						esc_url( $user_avatar ),
						esc_attr( $update->user_login ),
						esc_html( $update->user_login )
					),
					esc_html( $update->update_count ),
				];
			}
		);
	}

	/**
	 * Output a table of added users.
	 *
	 * @param array $added_users Array of added user details.
	 */
	public static function output_user_added_table( $added_users ) {
		self::output_details_table(
			__( 'Added users', 'simple-history' ),
			[
				__( 'Added user', 'simple-history' ),
				__( 'Role', 'simple-history' ),
				__( 'Added by', 'simple-history' ),
			],
			$added_users,
			function ( $user ) {
				$user_avatar = get_avatar_url( $user->user_id );
				$added_by = get_userdata( $user->added_by_id );
				$added_by_avatar = get_avatar_url( $user->added_by_id );
				return [
					sprintf(
						'<img src="%s" alt="%s" class="sh-InsightsDashboard-userAvatar">%s',
						esc_url( $user_avatar ),
						esc_attr( $user->user_login ),
						esc_html( $user->user_login )
					),
					esc_html( $user->user_role ),
					sprintf(
						'<img src="%s" alt="%s" class="sh-InsightsDashboard-userAvatar">%s',
						esc_url( $added_by_avatar ),
						esc_attr( $added_by->user_login ),
						esc_html( $added_by->display_name )
					),
				];
			}
		);
	}

	/**
	 * Output a table of removed users.
	 *
	 * @param array $removed_users Array of removed user details.
	 */
	public static function output_user_removed_table( $removed_users ) {
		self::output_details_table(
			__( 'Removed users', 'simple-history' ),
			[
				__( 'Removed user', 'simple-history' ),
				__( 'Email', 'simple-history' ),
				__( 'Removed by', 'simple-history' ),
			],
			$removed_users,
			function ( $user ) {
				$removed_by = get_userdata( $user->removed_by_id );
				$removed_by_avatar = get_avatar_url( $user->removed_by_id );
				return [
					esc_html( $user->user_login ),
					esc_html( $user->user_email ),
					sprintf(
						'<img src="%s" alt="%s" class="sh-InsightsDashboard-userAvatar">%s',
						esc_url( $removed_by_avatar ),
						esc_attr( $removed_by->user_login ),
						esc_html( $removed_by->display_name )
					),
				];
			}
		);
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

				<details>
					<summary>
						<?php esc_html_e( 'Show details', 'simple-history' ); ?>
					</summary>

					<p>
						<?php esc_html_e( 'Showing max 50 events, ordered by date.', 'simple-history' ); ?>
					</p>

					<div class="" style="display: flex; gap: 2rem; flex-wrap: wrap;">
						<?php
						self::output_content_created_table( $posts_pages_stats['content_items_created_details'] ?? [] );
						self::output_content_updated_table( $posts_pages_stats['content_items_updated_details'] ?? [] );
						self::output_content_trashed_table( $posts_pages_stats['content_items_trashed_details'] ?? [] );
						self::output_content_deleted_table( $posts_pages_stats['content_items_deleted_details'] ?? [] );
						?>
					</div>
				</details>
			</div>
		</div>
		<?php
	}

	/**
	 * Output a table of created content items.
	 *
	 * @param array $created_items Array of created content items.
	 */
	public static function output_content_created_table( $created_items ) {
		self::output_details_table(
			__( 'Created content', 'simple-history' ),
			[
				__( 'Title', 'simple-history' ),
				__( 'Type', 'simple-history' ),
				__( 'Created by', 'simple-history' ),
				__( 'Date', 'simple-history' ),
			],
			$created_items,
			function ( $item ) {
				$created_by = get_userdata( $item->created_by_id );
				$created_by_avatar = get_avatar_url( $item->created_by_id );
				return [
					esc_html( $item->post_title ),
					esc_html( $item->post_type ),
					sprintf(
						'<img src="%s" alt="%s" class="sh-InsightsDashboard-userAvatar">%s',
						esc_url( $created_by_avatar ),
						esc_attr( $created_by->user_login ),
						esc_html( $created_by->display_name )
					),
					esc_html( $item->created_date ),
				];
			}
		);
	}

	/**
	 * Output a table of updated content items.
	 *
	 * @param array $updated_items Array of updated content items.
	 */
	public static function output_content_updated_table( $updated_items ) {
		self::output_details_table(
			__( 'Updated content', 'simple-history' ),
			[
				__( 'Title', 'simple-history' ),
				__( 'Type', 'simple-history' ),
				__( 'Updated by', 'simple-history' ),
				__( 'Date', 'simple-history' ),
			],
			$updated_items,
			function ( $item ) {
				$updated_by = get_userdata( $item->updated_by_id );
				$updated_by_avatar = get_avatar_url( $item->updated_by_id );
				return [
					esc_html( $item->post_title ),
					esc_html( $item->post_type ),
					sprintf(
						'<img src="%s" alt="%s" class="sh-InsightsDashboard-userAvatar">%s',
						esc_url( $updated_by_avatar ),
						esc_attr( $updated_by->user_login ),
						esc_html( $updated_by->display_name )
					),
					esc_html( $item->updated_date ),
				];
			}
		);
	}

	/**
	 * Output a table of trashed content items.
	 *
	 * @param array $trashed_items Array of trashed content items.
	 */
	public static function output_content_trashed_table( $trashed_items ) {
		self::output_details_table(
			__( 'Trashed content', 'simple-history' ),
			[
				__( 'Title', 'simple-history' ),
				__( 'Type', 'simple-history' ),
				__( 'Trashed by', 'simple-history' ),
				__( 'Date', 'simple-history' ),
			],
			$trashed_items,
			function ( $item ) {
				$trashed_by = get_userdata( $item->trashed_by_id );
				$trashed_by_avatar = get_avatar_url( $item->trashed_by_id );
				return [
					esc_html( $item->post_title ),
					esc_html( $item->post_type ),
					sprintf(
						'<img src="%s" alt="%s" class="sh-InsightsDashboard-userAvatar">%s',
						esc_url( $trashed_by_avatar ),
						esc_attr( $trashed_by->user_login ),
						esc_html( $trashed_by->display_name )
					),
					esc_html( $item->trashed_date ),
				];
			}
		);
	}

	/**
	 * Output a table of deleted content items.
	 *
	 * @param array $deleted_items Array of deleted content items.
	 */
	public static function output_content_deleted_table( $deleted_items ) {
		self::output_details_table(
			__( 'Deleted content', 'simple-history' ),
			[
				__( 'Title', 'simple-history' ),
				__( 'Type', 'simple-history' ),
				__( 'Deleted by', 'simple-history' ),
				__( 'Date', 'simple-history' ),
			],
			$deleted_items,
			function ( $item ) {
				$deleted_by = get_userdata( $item->deleted_by_id );
				$deleted_by_avatar = get_avatar_url( $item->deleted_by_id );
				return [
					esc_html( $item->post_title ),
					esc_html( $item->post_type ),
					sprintf(
						'<img src="%s" alt="%s" class="sh-InsightsDashboard-userAvatar">%s',
						esc_url( $deleted_by_avatar ),
						esc_attr( $deleted_by->user_login ),
						esc_html( $deleted_by->display_name )
					),
					esc_html( $item->deleted_date ),
				];
			}
		);
	}

	/**
	 * Output the media statistics section.
	 *
	 * @param array $media_stats Array of media statistics.
	 */
	public static function output_media_stats_section( $media_stats, $media_stats_details ) {
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

				<details>
					<summary>
						<?php esc_html_e( 'Show media details', 'simple-history' ); ?>
					</summary>
					
					<div class="" style="display: flex; gap: 2rem; flex-wrap: wrap;">
						<?php
						self::output_media_uploads_table( $media_stats_details['media_files_uploaded_details'] ?? [] );
						self::output_media_edits_table( $media_stats_details['media_files_edited_details'] ?? [] );
						self::output_media_deletions_table( $media_stats_details['media_files_deleted_details'] ?? [] );
						?>
					</div>
				</details>

			</div>
		</div>
		<?php
	}

	/**
	 * Output the media uploads table.
	 *
	 * @param array $uploads Array of media upload events.
	 */
	public static function output_media_uploads_table( $uploads ) {
		self::output_details_table(
			__( 'Media uploads', 'simple-history' ),
			[
				__( 'File', 'simple-history' ),
				__( 'Uploaded by', 'simple-history' ),
				__( 'Date', 'simple-history' ),
			],
			$uploads,
			function ( $upload ) {
				// Get the user who performed the action.
				$user_id = isset( $upload->context['_user_id'] ) ? $upload->context['_user_id'] : 0;
				$user = get_user_by( 'id', $user_id );
				$user_name = $user ? $user->display_name : __( 'Unknown user', 'simple-history' );
				$date = isset( $upload->date ) ? strtotime( $upload->date ) : '';
				$attachment_filename = isset( $upload->context['attachment_filename'] ) ? $upload->context['attachment_filename'] : __( 'Unknown file', 'simple-history' );

				return [
					esc_html( $attachment_filename ),
					esc_html( $user_name ),
					sprintf(
						/* translators: %s last modified date and time in human time diff-format */
						__( '%1$s ago', 'simple-history' ),
						human_time_diff( $date, time() )
					),
				];
			}
		);
	}

	/**
	 * Output the media edits table.
	 *
	 * @param array $edits Array of media edit events.
	 */
	public static function output_media_edits_table( $edits ) {
		self::output_details_table(
			__( 'Media edits', 'simple-history' ),
			[
				__( 'Title', 'simple-history' ),
				__( 'Edited by', 'simple-history' ),
				__( 'Date', 'simple-history' ),
			],
			$edits,
			function ( $edit ) {
				// Get the user who performed the action.
				$user_id = isset( $edit->context['_user_id'] ) ? $edit->context['_user_id'] : 0;
				$user = get_user_by( 'id', $user_id );
				$user_name = $user ? $user->display_name : __( 'Unknown user', 'simple-history' );
				$date = isset( $edit->date ) ? strtotime( $edit->date ) : '';
				$attachment_title = isset( $edit->context['attachment_title'] ) ? $edit->context['attachment_title'] : __( 'Unknown title', 'simple-history' );

				return [
					esc_html( $attachment_title ),
					esc_html( $user_name ),
					sprintf(
						/* translators: %s last modified date and time in human time diff-format */
						__( '%1$s ago', 'simple-history' ),
						human_time_diff( $date, time() )
					),
				];
			}
		);
	}

	/**
	 * Output the media deletions table.
	 *
	 * @param array $deletions Array of media deletion events.
	 */
	public static function output_media_deletions_table( $deletions ) {
		self::output_details_table(
			__( 'Media deletions', 'simple-history' ),
			[
				__( 'File', 'simple-history' ),
				__( 'Deleted by', 'simple-history' ),
				__( 'Date', 'simple-history' ),
			],
			$deletions,
			function ( $deletion ) {
				// Get the user who performed the action.
				$user_id = isset( $deletion->context['_user_id'] ) ? $deletion->context['_user_id'] : 0;
				$user = get_user_by( 'id', $user_id );
				$user_name = $user ? $user->display_name : __( 'Unknown user', 'simple-history' );
				$date = isset( $deletion->date ) ? strtotime( $deletion->date ) : '';
				$attachment_filename = isset( $deletion->context['attachment_filename'] ) ? $deletion->context['attachment_filename'] : __( 'Unknown file', 'simple-history' );

				return [
					esc_html( $attachment_filename ),
					esc_html( $user_name ),
					sprintf(
						/* translators: %s last modified date and time in human time diff-format */
						__( '%1$s ago', 'simple-history' ),
						human_time_diff( $date, time() )
					),
				];
			}
		);
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
		// Show up to 50 entries for better overview.
		$plugins = $stats->get_plugin_details( $action_type, $date_from, $date_to, 50 );

		self::output_details_table(
			$title,
			[
				__( 'Name', 'simple-history' ),
				__( 'Event date', 'simple-history' ),
			],
			$plugins,
			function ( $plugin ) {
				return [
					esc_html( $plugin['name'] ),
					esc_html( $plugin['when'] ),
				];
			}
		);
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

			<details>
				<summary>
					<?php esc_html_e( 'Show details', 'simple-history' ); ?>
				</summary>

				<p>
					<?php esc_html_e( 'Showing max 50 events, ordered by date.', 'simple-history' ); ?>
				</p>

				<div class="" style="display: flex; gap: 2rem; flex-wrap: wrap;">
					<?php
					// Output tables for each plugin action type.
					self::output_plugin_table( __( 'Installed plugins', 'simple-history' ), 'installed', $date_from, $date_to, $stats );
					self::output_plugin_table( __( 'Activated plugins', 'simple-history' ), 'activated', $date_from, $date_to, $stats );
					self::output_plugin_table( __( 'Deactivated plugins', 'simple-history' ), 'deactivated', $date_from, $date_to, $stats );
					self::output_plugin_table( __( 'Deleted plugins', 'simple-history' ), 'deleted', $date_from, $date_to, $stats );
					self::output_plugin_table( __( 'Updates found for plugins', 'simple-history' ), 'plugin_update_available', $date_from, $date_to, $stats );
					?>
				</div>
			</details>
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
				$data,
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

			self::output_user_stats_section( $data );

			self::output_posts_pages_stats_section( $data['content_stats'] );

			self::output_media_stats_section( $data['media_stats'], $data['media_stats_details'] );

			self::output_top_users_section( $data['user_rankings_formatted'] );

			self::output_top_posts_and_pages_section( $data['content_stats']['content_items_most_edited'] );

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
			<h2><?php echo esc_html_x( 'Activity calendar', 'insights section title', 'simple-history' ); ?></h2>
			<div class="sh-InsightsDashboard-content">
				<?php self::output_activity_calendar( $date_from, $date_to, $activity_overview_by_date ); ?>
			</div>
		</div>
		<?php
	}
}
