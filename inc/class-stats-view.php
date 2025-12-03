<?php

namespace Simple_History;

use Simple_History\Helpers;

/**
 * Class that handles view/output logic for the stats page.
 */
class Stats_View {
	/**
	 * Generate a random number within a specified range.
	 *
	 * @param int $min Minimum value.
	 * @param int $max Maximum value.
	 * @return int Random number between min and max.
	 */
	private static function get_random_stat( $min, $max ) {
		return wp_rand( $min, $max );
	}

	/**
	 * Output the page title section.
	 */
	public static function output_page_title() {
		?>
		<h1>
			<?php
			echo wp_kses(
				Helpers::get_settings_section_title_output(
					__( 'History Insights', 'simple-history' ),
					// Icons that could be used:
					// query stats, search insights, analytics, monitoring.
					'bar_chart'
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
	 * Output the date range section.
	 *
	 * @param int $date_from Start date as Unix timestamp.
	 * @param int $date_to   End date as Unix timestamp.
	 */
	public static function output_date_range( $date_from, $date_to ) {
		?>
		<div class="sh-StatsDashboard-dateRangeContainer">
			<h2 class="sh-StatsDashboard-dateRangeHeading">
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

			<div class="sh-StatsDashboard-dateRangeControls">
				<select>
					<option disabled>Today</option>
					<option disabled>Last 7 days</option>
					<option selected>Last 30 days</option>
					<option disabled>Last 3 months</option>
					<option disabled>Last 6 months</option>
					<option disabled>Last year</option>
					<option disabled>All time</option>
				</select>

				<div class="sh-StatsDashboard-dateRangeControls-description">
					<span class="sh-Icon sh-Icon-lock"></span>
					<span><a target="_blank" rel="noopener noreferrer" href="<?php echo esc_url( Helpers::get_tracking_url( 'https://simple-history.com/add-ons/premium/', 'premium_stats_daterange' ) ); ?>">Upgrade to Premium</a> to get access to more date ranges.</span>
				</div>
			</div>

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
		$total_events     = $data['overview_total_events'];
		$user_stats       = $data['user_stats'];
		$top_users        = $data['user_rankings'];
		$user_total_count = $data['user_total_count'];

		$sitename = get_bloginfo( 'name' );
		?>
		<div class="sh-StatsDashboard-card sh-StatsDashboard-card--wide sh-StatsDashboard-card--tall">

			<h2 class="sh-StatsDashboard-cardTitle" style="--sh-badge-background-color: var(--sh-color-green-light);">
				<?php
				echo esc_html(
					sprintf(
						/* translators: %s: site name */
						__( 'Summary for %s', 'simple-history' ),
						$sitename
					)
				);
				?>
			</h2>

			<div class="sh-flex sh-justify-between sh-mb-large">
				<div class="sh-StatsDashboard-stat">
					<span class="sh-StatsDashboard-statLabel"><?php esc_html_e( 'Total events', 'simple-history' ); ?></span>
					<span class="sh-StatsDashboard-statValue sh-StatsDashboard-statValue--large"><?php echo esc_html( number_format_i18n( $total_events ) ); ?></span>
				</div>

				<div class="sh-StatsDashboard-stat">
					<span class="sh-StatsDashboard-statLabel"><?php esc_html_e( 'Total users', 'simple-history' ); ?></span>
					<span class="sh-StatsDashboard-statValue sh-StatsDashboard-statValue--large"><?php echo esc_html( number_format_i18n( $user_total_count ) ); ?></span>
				</div>

				<div class="sh-StatsDashboard-stat">
					<span class="sh-StatsDashboard-statLabel"><?php esc_html_e( 'Users with most events', 'simple-history' ); ?></span>
					<span class="sh-StatsDashboard-statValue"><?php self::output_top_users_avatar_list( $top_users ); ?></span>
				</div>
			</div>		
			
			<div class="sh-flex sh-justify-between sh-mb-large">
				<?php
				/*
				 * Output stats for each category.
				 */
				$categories = array(
					array(
						'label' => __( 'User profile actions', 'simple-history' ),
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

				foreach ( $categories as $category ) {
					?>
					<div class="sh-StatsDashboard-stat">
						<span class="sh-StatsDashboard-statLabel"><?php echo esc_html( $category['label'] ); ?></span>
						<span class="sh-StatsDashboard-statValue"><?php echo esc_html( $category['value'] ); ?></span>
					</div>
					<?php
				}
				?>
			</div>
	
			<div class="sh-StatsDashboard-stat">
				<div class="sh-StatsDashboard-statLabel">
					<?php esc_html_e( 'Activity by date', 'simple-history' ); ?>
				</div>

				<span class="sh-StatsDashboard-statValue">
					<div class="sh-StatsDashboard-chartContainer">
						<canvas id="eventsOverviewChart" class="sh-StatsDashboard-chart"></canvas>
					</div>
				</span>
			</div>

			<div class="sh-flex sh-justify-between">
				<span>
					<?php
					echo esc_html( wp_date( get_option( 'date_format' ), $date_from ) );
					?>
				</span>
				<span>
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
		<div class="sh-StatsDashboard-section">
			<h2><?php echo esc_html_x( 'Currently Logged In Users', 'stats section title', 'simple-history' ); ?></h2>

				<div class="sh-StatsDashboard-activeUsers">
					<?php
					if ( $logged_in_users ) {
						?>
						<ul class="sh-StatsDashboard-userList">
							<?php
							foreach ( $logged_in_users as $user_data ) {
								?>
								<li class="sh-StatsDashboard-userItem">
									<?php
									$user = $user_data['user'];
									// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
									echo Helpers::get_avatar( $user->user_email, 32 );
									?>
									<div class="sh-StatsDashboard-userInfo">
										<strong><?php echo esc_html( $user->display_name ); ?></strong>
										<span class="sh-StatsDashboard-userRole">
											<?php echo esc_html( implode( ', ', $user->roles ) ); ?>
										</span>
										<span class="sh-StatsDashboard-userSessions">
											<?php
											printf(
												/* translators: %d: number of active sessions */
												esc_html( _n( '%d active session', '%d active sessions', $user_data['sessions_count'], 'simple-history' ) ),
												esc_html( $user_data['sessions_count'] )
											);
											?>
										</span>

										<?php if ( ! empty( $user_data['sessions'] ) ) { ?>
											<div class="sh-StatsDashboard-userSessions-details">
												<?php foreach ( $user_data['sessions'] as $session ) { ?>
													<div class="sh-StatsDashboard-userSession">
														<span class="sh-StatsDashboard-userLastLogin">
															<?php
															$login_time = date_i18n( 'F d, Y H:i A', $session['login'] );
															printf(
																/* translators: %s: login date and time */
																esc_html__( 'Login: %s', 'simple-history' ),
																esc_html( $login_time )
															);
															?>
														</span>

														<span class="sh-StatsDashboard-userExpiration">
															<?php
															$expiration_time = date_i18n( 'F d, Y H:i A', $session['expiration'] );
															printf(
																/* translators: %s: session expiration date and time */
																esc_html__( 'Expires: %s', 'simple-history' ),
																esc_html( $expiration_time )
															);
															?>
														</span>

														<?php if ( ! empty( $session['ip'] ) ) { ?>
															<span class="sh-StatsDashboard-userIP">
																<?php
																printf(
																	/* translators: %s: IP address */
																	esc_html__( 'IP: %s', 'simple-history' ),
																	esc_html( $session['ip'] )
																);
																?>
															</span>
														<?php } ?>
													</div>
												<?php } ?>
											</div>
										<?php } ?>
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
	 * Output the top posts and pages section.
	 *
	 * @param array $top_posts_and_pages Array of top posts and pages data.
	 */
	public static function output_top_posts_and_pages_section( $top_posts_and_pages ) {
		?>
		<div class="sh-StatsDashboard-card sh-StatsDashboard-card--wide">
			<h2 
				class="sh-StatsDashboard-cardTitle" 
				style="--sh-badge-background-color: var(--sh-color-green-light);"
			>
				<?php echo esc_html_x( 'Most edited posts and pages', 'stats section title', 'simple-history' ); ?>
			</h2>

			<p>Events can be page created, updated, deleted, trashed or restored.</p>

			<div class="sh-StatsDashboard-content">
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
		<div class="sh-StatsDashboard-tableContainer">
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php echo esc_html_x( 'Post', 'stats table header', 'simple-history' ); ?></th>
						<th><?php echo esc_html_x( 'Number of events', 'stats table header', 'simple-history' ); ?></th>
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
	 * Each user array should have the following shape:
	 * [
	 *     'id'           => int    User ID,
	 *     'display_name' => string User display name,
	 *     'user_email'   => string User email,
	 *     'avatar'       => string Avatar URL (absolute, 96x96),
	 *     'count'        => int    Number of events,
	 * ]
	 *
	 * @param array $top_users Array of top users data.
	 * @phpstan-param array<int, array{id: int, display_name: string, user_email: string, avatar: string, count: int}> $top_users
	 */
	public static function output_top_users_avatar_list( $top_users ) {
		$user_count = count( $top_users );

		// Bail if no users.
		if ( $user_count === 0 ) {
			return;
		}

		// Output avatars.
		?>
		<ul class="sh-StatsDashboard-userList">
			<?php
			$loop_count = 0;
			foreach ( $top_users as $user ) {
				// Set z-index to reverse order, so first user is on top.
				$style    = 'z-index: ' . ( $user_count - $loop_count ) . ';';
				$user_url = Helpers::get_filtered_events_url(
					[
						'users' => $user,
						'date'  => 'lastdays:30',
					]
				);
				?>
				<li class="sh-StatsDashboard-userItem" style="<?php echo esc_attr( $style ); ?>">
					<a href="<?php echo esc_url( $user_url ); ?>" class="sh-StatsDashboard-userLink">
						<img
							src="<?php echo esc_url( $user['avatar'] ); ?>"
							alt="<?php echo esc_attr( $user['display_name'] ); ?>"
							class="sh-StatsDashboard-userAvatar"
						>
						<span class="sh-StatsDashboard-userData">
							<span class="sh-StatsDashboard-userName"><?php echo esc_html( $user['display_name'] ); ?></span>
							<span class="sh-StatsDashboard-userActions"><?php echo esc_html( number_format_i18n( $user['count'] ) ); ?> events</span>
						</span>
					</a>
				</li>
				<?php

				++$loop_count;
			}
			?>
		</ul>
		<?php

		// Output user names (if user has no avatar).
		?>
		<p class="sh-StatsDashboard-userNamesList">
			<?php
			// Generate array of user names with links to filtered events log
			// in format that can be used with wp_sprintf.
			$user_names = array_map(
				static function ( $user ) {
					$url = Helpers::get_filtered_events_url(
						[
							'users' => $user,
							'date'  => 'lastdays:30',
						]
					);

					return '<a href="' . esc_url( $url ) . '">' . esc_html( $user['display_name'] ) . '</a><span class="sh-StatsDashboard-userEventCount"> (' . esc_html( number_format_i18n( $user['count'] ) ) . ')</span>';
				},
				$top_users
			);

			// Creates a comma-separated list of user names.
			// Example: "John Doe, Jane Smith, Mary Johnson".
			echo wp_kses_post( implode( ', ', $user_names ) );
			?>
		</p>
		<?php
	}

	/**
	 * Output the table of top users.
	 *
	 * @param array $top_users Array of top users data.
	 */
	public static function output_top_users_table( $top_users ) {
		?>
		<div class="sh-StatsDashboard-tableContainer" style="--sh-avatar-size: 20px;">
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php echo esc_html_x( 'User', 'stats table header', 'simple-history' ); ?></th>
						<th><?php echo esc_html_x( 'Events', 'stats table header', 'simple-history' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $top_users as $user ) { ?>
						<tr>
							<td>
								<img 
										src="<?php echo esc_url( $user['avatar'] ); ?>" 
										alt="<?php echo esc_attr( $user['display_name'] ); ?>" 
										class="sh-StatsDashboard-userAvatar"
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
	 */
	public static function output_chart_section( $title, $chart_id ) {
		?>
		<div class="sh-StatsDashboard-card">
			<h2
			class="sh-StatsDashboard-cardTitle" 
			style="
					--sh-badge-background-color: var(--sh-color-green-light);
					--sh-icon-size: 14px;
				"
			>
				<span class="sh-Icon sh-Icon-lock"></span>
				<?php echo esc_html( $title ); ?>
			</h2>

			<p class="sh-mt-0">
				Premium users get access to charts with detailed stats.
				<a href="<?php echo esc_url( Helpers::get_tracking_url( 'https://simple-history.com/add-ons/premium/#stats-and-summaries', 'premium_stats_charts' ) ); ?>" class="sh-ml-1" target="_blank"><?php esc_html_e( 'View more details', 'simple-history' ); ?></a>.
			</p>

			<div class="sh-StatsDashboard-content">
				<canvas id="<?php echo esc_attr( $chart_id ); ?>" class="sh-StatsDashboard-chart is-blurred"></canvas>
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
		<div class="sh-StatsDashboard-calendar">
			<?php
			// Get the first and last day of the date range.
			$start_date = new \DateTime( gmdate( 'Y-m-d', $date_from ) );
			$end_date   = new \DateTime( gmdate( 'Y-m-d', $date_to ) );

			// Get the first and last day of the month.
			$first_day_of_month = clone $start_date;
			$first_day_of_month->modify( 'first day of this month' );
			$last_day_of_month = clone $start_date;
			$last_day_of_month->modify( 'last day of this month' );

			$current_date  = clone $first_day_of_month;
			$start_weekday = (int) $first_day_of_month->format( 'w' );

			// Create array of dates with their event counts.
			$date_counts = array();
			foreach ( $activity_overview as $day ) {
				$date_counts[ $day->date ] = (int) $day->count;
			}

			// Get month name.
			$month_name = $start_date->format( 'F Y' );
			?>
			<div class="sh-StatsDashboard-calendarMonth">
				<h3 class="sh-StatsDashboard-calendarTitle"><?php echo esc_html( $month_name ); ?></h3>
				<div class="sh-StatsDashboard-calendarGrid">
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
						echo '<div class="sh-StatsDashboard-calendarHeader">' . esc_html( $weekday ) . '</div>';
					}

					// Add empty cells for days before the start of the month.
					for ( $i = 0; $i < $start_weekday; $i++ ) {
						echo '<div class="sh-StatsDashboard-calendarDay sh-StatsDashboard-calendarDay--empty"></div>';
					}

					// Output calendar days.
					while ( $current_date <= $last_day_of_month ) {
						$date_str    = $current_date->format( 'Y-m-d' );
						$count       = isset( $date_counts[ $date_str ] ) ? $date_counts[ $date_str ] : 0;
						$is_in_range = $current_date >= $start_date && $current_date <= $end_date;

						$classes = array( 'sh-StatsDashboard-calendarDay' );

						if ( ! $is_in_range ) {
							$classes[] = 'sh-StatsDashboard-calendarDay--outOfRange';
						} elseif ( $count > 0 ) {
							if ( $count < 10 ) {
								$classes[] = 'sh-StatsDashboard-calendarDay--low';
							} elseif ( $count < 50 ) {
								$classes[] = 'sh-StatsDashboard-calendarDay--medium';
							} else {
								$classes[] = 'sh-StatsDashboard-calendarDay--high';
							}
						}

						$title = $is_in_range ?
							/* translators: %d: number of events */
							sprintf( _n( '%d event', '%d events', $count, 'simple-history' ), $count ) :
							__( 'Outside selected date range', 'simple-history' );
						?>
						<div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" 
							title="<?php echo esc_attr( $title ); ?>">
							<span class="sh-StatsDashboard-calendarDayNumber"><?php echo esc_html( $current_date->format( 'j' ) ); ?></span>
							<?php if ( $is_in_range ) { ?>
								<span class="sh-StatsDashboard-calendarDayCount"><?php echo esc_html( number_format_i18n( $count ) ); ?></span>
							<?php } ?>
						</div>
						<?php
						$current_date->modify( '+1 day' );
					}

					// Add empty cells for days after the end of the month to complete the grid.
					$end_weekday    = (int) $last_day_of_month->format( 'w' );
					$remaining_days = 6 - $end_weekday;
					for ( $i = 0; $i < $remaining_days; $i++ ) {
						echo '<div class="sh-StatsDashboard-calendarDay sh-StatsDashboard-calendarDay--empty"></div>';
					}
					?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Output a generic stats box section.
	 *
	 * @param string $title Card title.
	 * @param array  $stats Array of stats, each containing 'label' and 'value'.
	 * @param string $color_var CSS variable name for the color.
	 * @param string $description_text Description text.
	 */
	public static function output_stats_box_section( $title, $stats, $color_var, $description_text = '' ) {
		?>
		<div class="sh-StatsDashboard-card sh-StatsDashboard-card--wide">
			<h2 
				class="sh-StatsDashboard-cardTitle" 
				style="
					--sh-badge-background-color: var(<?php echo esc_attr( $color_var ); ?>);
					--sh-icon-size: 14px;
				"
			>
				<span class="sh-Icon sh-Icon-lock"></span>
				<?php echo esc_html( $title ); ?>
			</h2>

			<?php
			if ( $description_text ) {
				?>
				<p class="sh-mt-0 sh-mb-large">
					<?php echo esc_html( $description_text ); ?>
					<a href="<?php echo esc_url( Helpers::get_tracking_url( 'https://simple-history.com/add-ons/premium/#stats-and-summaries', 'premium_stats_box' ) ); ?>" class="sh-ml-1" target="_blank"><?php esc_html_e( 'View more details', 'simple-history' ); ?></a>
				</p>
				<?php
			}
			?>
			
			<div class="sh-StatsDashboard-content">
				<div class="sh-StatsDashboard-stats is-blurred">
					<?php foreach ( $stats as $stat ) { ?>
						<div class="sh-StatsDashboard-stat">
							<span class="sh-StatsDashboard-statLabel"><?php echo esc_html( $stat['label'] ); ?></span>
							<span class="sh-StatsDashboard-statValue"><?php echo esc_html( number_format_i18n( $stat['value'] ) ); ?></span>
						</div>
					<?php } ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Output the plugin statistics section.
	 */
	public static function output_plugin_stats() {
		$stats_data = [
			[
				'label' => __( 'Installations', 'simple-history' ),
				'value' => self::get_random_stat( 10, 25 ),
			],
			[
				'label' => __( 'Activations', 'simple-history' ),
				'value' => self::get_random_stat( 20, 40 ),
			],
			[
				'label' => __( 'Updates found', 'simple-history' ),
				'value' => self::get_random_stat( 25, 45 ),
			],
			[
				'label' => __( 'Updates done', 'simple-history' ),
				'value' => self::get_random_stat( 20, 35 ),
			],
			[
				'label' => __( 'Deactivations', 'simple-history' ),
				'value' => self::get_random_stat( 5, 15 ),
			],
			[
				'label' => __( 'Deletions', 'simple-history' ),
				'value' => self::get_random_stat( 1, 8 ),
			],
		];

		self::output_stats_box_section(
			__( 'Plugins', 'simple-history' ),
			$stats_data,
			'--sh-color-green-mint',
			'Premium users get quick overview of vital plugin numbers, like installations, activations, updates, deactivations and deletions.'
		);
	}

	/**
	 * Output the user activity statistics section.
	 */
	public static function output_user_stats_section() {
		$stats_data = [
			[
				'label' => __( 'Successful logins', 'simple-history' ),
				'value' => self::get_random_stat( 80, 150 ),
			],
			[
				'label' => __( 'Failed logins', 'simple-history' ),
				'value' => self::get_random_stat( 10, 30 ),
			],
			[
				'label' => __( 'Profile updates', 'simple-history' ),
				'value' => self::get_random_stat( 8, 20 ),
			],
			[
				'label' => __( 'Added users', 'simple-history' ),
				'value' => self::get_random_stat( 1, 5 ),
			],
			[
				'label' => __( 'Removed users', 'simple-history' ),
				'value' => self::get_random_stat( 0, 3 ),
			],
		];

		self::output_stats_box_section(
			_x( 'User profile activity', 'stats section title', 'simple-history' ),
			$stats_data,
			'--sh-color-pink',
			'Premium users get detailed stats on user profile activity, like successful logins, failed logins, profile updates, added users and removed users.'
		);
	}

	/**
	 * Output the posts and pages statistics section.
	 */
	public static function output_posts_pages_stats_section() {
		$stats_data = [
			[
				'label' => __( 'Created', 'simple-history' ),
				'value' => self::get_random_stat( 30, 60 ),
			],
			[
				'label' => __( 'Updated', 'simple-history' ),
				'value' => self::get_random_stat( 150, 250 ),
			],
			[
				'label' => __( 'Trashed', 'simple-history' ),
				'value' => self::get_random_stat( 8, 20 ),
			],
			[
				'label' => __( 'Deleted', 'simple-history' ),
				'value' => self::get_random_stat( 3, 10 ),
			],
		];

		self::output_stats_box_section(
			_x( 'Posts & pages activity', 'stats section title', 'simple-history' ),
			$stats_data,
			'--sh-color-yellow',
			'Premium users get detailed stats on posts and pages activity, like created, updated, trashed and deleted.'
		);
	}

	/**
	 * Output the media statistics section.
	 */
	public static function output_media_stats_section() {
		$stats_data = [
			[
				'label' => __( 'Uploads', 'simple-history' ),
				'value' => self::get_random_stat( 50, 100 ),
			],
			[
				'label' => __( 'Edits', 'simple-history' ),
				'value' => self::get_random_stat( 15, 35 ),
			],
			[
				'label' => __( 'Deletions', 'simple-history' ),
				'value' => self::get_random_stat( 5, 15 ),
			],
		];

		self::output_stats_box_section(
			_x( 'Media', 'stats section title', 'simple-history' ),
			$stats_data,
			'--sh-color-green-light',
			'Premium users get detailed stats on media activity, like uploads, edits and deletions.'
		);
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
		<div class="sh-StatsDashboard">
			<?php

			// Big box with numbers and one beautiful chart.
			self::output_events_overview(
				$data,
				$date_from,
				$date_to
			);

			// Colorful charts.
			self::output_chart_section(
				_x( 'Peak activity times', 'stats section title', 'simple-history' ),
				'peakTimesChart'
			);

			self::output_chart_section(
				_x( 'Peak activity days', 'stats section title', 'simple-history' ),
				'peakDaysChart'
			);

			// Boxes with stats numbers.
			self::output_plugin_stats();
			self::output_user_stats_section();
			self::output_posts_pages_stats_section();
			self::output_media_stats_section();
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
		<div class="sh-StatsDashboard-section sh-StatsDashboard-section--wide">
			<h2><?php echo esc_html_x( 'Activity calendar', 'stats section title', 'simple-history' ); ?></h2>
			<div class="sh-StatsDashboard-content">
				<?php self::output_activity_calendar( $date_from, $date_to, $activity_overview_by_date ); ?>
			</div>
		</div>
		<?php
	}
}
