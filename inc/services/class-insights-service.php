<?php

namespace Simple_History\Services;

use Simple_History\Helpers;
use Simple_History\Simple_History;
use Simple_History\Menu_Page;
use Simple_History\Services\Service;
use WP_Session_Tokens;
use Simple_History\Services\Admin_Pages;

/**
 * Service class that handles insights functionality.
 */
class Insights_Service extends Service {
	/**
	 * Called when service is loaded.
	 */
	public function loaded() {
		// Only enable for users with experimental features enabled.
		if ( ! Helpers::experimental_features_is_enabled() ) {
			return;
		}

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
	public function get_logged_in_users( $limit = 10 ) {
		global $wpdb;
		$logged_in_users = [];

		// Query session tokens directly from user meta table.
		$users_with_session_tokens = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value != ''",
				'session_tokens'
			)
		);

		foreach ( $users_with_session_tokens as $one_user_id ) {
			$sessions = WP_Session_Tokens::get_instance( $one_user_id );

			$all_user_sessions = $sessions->get_all();
			if ( $all_user_sessions ) {
				$logged_in_users[] = [
					'user' => get_userdata( $one_user_id ),
					'sessions_count' => count( $all_user_sessions ),
					'sessions' => $all_user_sessions,
				];
			}
		}

		return array_slice( $logged_in_users, 0, $limit );
	}

	/**
	 * Get total number of events for a given period.
	 *
	 * @param int $date_from Required. Start date as Unix timestamp.
	 * @param int $date_to   Required. End date as Unix timestamp.
	 * @return int|false Total number of events, or false if invalid dates.
	 */
	public function get_total_events( $date_from, $date_to ) {
		global $wpdb;

		if ( ! $date_from || ! $date_to ) {
			return false;
		}

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT 
					COUNT(*)
				FROM 
					{$wpdb->prefix}simple_history
				WHERE 
					date >= FROM_UNIXTIME(%d)
					AND date <= FROM_UNIXTIME(%d)",
				$date_from,
				$date_to
			)
		);
	}

	/**
	 * Get total number of unique users involved in events for a given period.
	 *
	 * @param int $date_from Required. Start date as Unix timestamp.
	 * @param int $date_to   Required. End date as Unix timestamp.
	 * @return int|false Total number of unique users, or false if invalid dates.
	 */
	public function get_total_users( $date_from, $date_to ) {
		global $wpdb;

		if ( ! $date_from || ! $date_to ) {
			return false;
		}

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT 
					COUNT(DISTINCT c.value)
				FROM 
					{$wpdb->prefix}simple_history_contexts c
				JOIN 
					{$wpdb->prefix}simple_history h ON h.id = c.history_id
				WHERE 
					c.key = '_user_id'
					AND h.date >= FROM_UNIXTIME(%d)
					AND h.date <= FROM_UNIXTIME(%d)",
				$date_from,
				$date_to
			)
		);
	}

	/**
	 * Get the last user edit action.
	 *
	 * @param int $date_from Required. Start date as Unix timestamp.
	 * @param int $date_to   Required. End date as Unix timestamp.
	 * @return object|false Last edit action details, or false if invalid dates or no actions found.
	 */
	public function get_last_edit_action( $date_from, $date_to ) {
		global $wpdb;

		if ( ! $date_from || ! $date_to ) {
			return false;
		}

		$last_action = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT 
					h.*,
					c.value as user_id,
					u.display_name
				FROM 
					{$wpdb->prefix}simple_history h
				JOIN 
					{$wpdb->prefix}simple_history_contexts c ON h.id = c.history_id
				LEFT JOIN 
					{$wpdb->users} u ON u.ID = CAST(c.value AS UNSIGNED)
				WHERE 
					c.key = '_user_id'
					AND h.date >= FROM_UNIXTIME(%d)
					AND h.date <= FROM_UNIXTIME(%d)
				ORDER BY 
					h.date DESC
				LIMIT 1",
				$date_from,
				$date_to
			)
		);

		return $last_action;
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
		?>
		<div class="sh-InsightsDashboard-filters">
			<div class="sh-InsightsDashboard-dateFilters">
				<span class="sh-InsightsDashboard-dateFilters-label"><?php echo esc_html_x( 'Time period:', 'insights date filter label', 'simple-history' ); ?></span>
				<a href="<?php echo esc_url( add_query_arg( 'period', '1h' ) ); ?>" class="sh-InsightsDashboard-dateFilter <?php echo isset( $_GET['period'] ) && $_GET['period'] === '1h' ? 'is-active' : ''; ?>">
					<?php echo esc_html_x( '1H', 'insights date filter 1 hour', 'simple-history' ); ?>
				</a>
				<a href="<?php echo esc_url( add_query_arg( 'period', '24h' ) ); ?>" class="sh-InsightsDashboard-dateFilter <?php echo isset( $_GET['period'] ) && $_GET['period'] === '24h' ? 'is-active' : ''; ?>">
					<?php echo esc_html_x( '24H', 'insights date filter 24 hours', 'simple-history' ); ?>
				</a>
				<a href="<?php echo esc_url( add_query_arg( 'period', '7d' ) ); ?>" class="sh-InsightsDashboard-dateFilter <?php echo isset( $_GET['period'] ) && $_GET['period'] === '7d' ? 'is-active' : ''; ?>">
					<?php echo esc_html_x( '7D', 'insights date filter 7 days', 'simple-history' ); ?>
				</a>
				<a href="<?php echo esc_url( add_query_arg( 'period', '14d' ) ); ?>" class="sh-InsightsDashboard-dateFilter <?php echo isset( $_GET['period'] ) && $_GET['period'] === '14d' ? 'is-active' : ''; ?>">
					<?php echo esc_html_x( '14D', 'insights date filter 14 days', 'simple-history' ); ?>
				</a>
				<a href="<?php echo esc_url( add_query_arg( 'period', '1m' ) ); ?>" class="sh-InsightsDashboard-dateFilter <?php echo isset( $_GET['period'] ) && $_GET['period'] === '1m' ? 'is-active' : ''; ?>">
					<?php echo esc_html_x( '1M', 'insights date filter 1 month', 'simple-history' ); ?>
				</a>
			</div>
		</div>
		<?php
	}

	/**
	 * Enqueue required scripts and styles for the insights page.
	 */
	private function enqueue_scripts_and_styles() {
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
			'total_events' => $this->get_total_events( $date_from, $date_to ),
			'total_users' => $this->get_total_users( $date_from, $date_to ),
			'last_edit' => $this->get_last_edit_action( $date_from, $date_to ),
			'top_users' => $this->get_top_users( $date_from, $date_to, 10 ),
			'activity_overview' => $this->get_activity_overview( $date_from, $date_to ),
			'common_actions' => $this->get_most_common_actions( $date_from, $date_to, 10 ),
			'peak_times' => $this->get_peak_activity_times( $date_from, $date_to ),
			'peak_days' => $this->get_peak_days( $date_from, $date_to ),
			'logged_in_users' => $this->get_logged_in_users(),
		];

		// Format logger names for common actions.
		$data['formatted_common_actions'] = array_map(
			function ( $action ) {
				$action->logger = $this->format_logger_name( $action->logger );
				return $action;
			},
			$data['common_actions'] ? $data['common_actions'] : []
		);

		return $data;
	}

	/**
	 * Localize script data for the insights page.
	 *
	 * @param array $data     Insights data array.
	 * @param int   $date_from Start date as Unix timestamp.
	 * @param int   $date_to   End date as Unix timestamp.
	 */
	private function localize_script_data( $data, $date_from, $date_to ) {
		wp_localize_script(
			'simple-history-insights',
			'simpleHistoryInsights',
			[
				'topUsers' => $data['top_users'] ? $data['top_users'] : [],
				'activityOverview' => $data['activity_overview'] ? $data['activity_overview'] : [],
				'commonActions' => $data['formatted_common_actions'],
				'peakTimes' => $data['peak_times'] ? $data['peak_times'] : [],
				'peakDays' => $data['peak_days'] ? $data['peak_days'] : [],
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
	}

	/**
	 * Output the currently logged in users section.
	 *
	 * @param array $logged_in_users Array of currently logged in users.
	 */
	private function output_logged_in_users_section( $logged_in_users ) {
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
	private function output_top_users_section( $top_users ) {
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
	private function output_chart_section( $title, $chart_id, $css_class = '' ) {
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
	 * Output the main insights dashboard content.
	 *
	 * @param array $data      Insights data array.
	 * @param int   $date_from Start date as Unix timestamp.
	 * @param int   $date_to   End date as Unix timestamp.
	 */
	private function output_dashboard_content( $data, $date_from, $date_to ) {
		?>
		<div class="sh-InsightsDashboard">
			<?php
			$this->output_logged_in_users_section( $data['logged_in_users'] );
			$this->output_top_users_section( $data['top_users'] );

			// Output chart sections.
			$this->output_chart_section(
				_x( 'Activity Overview', 'insights section title', 'simple-history' ),
				'activityChart'
			);

			$this->output_chart_section(
				_x( 'Most Common Actions', 'insights section title', 'simple-history' ),
				'actionsChart'
			);

			$this->output_chart_section(
				_x( 'Peak Activity Times', 'insights section title', 'simple-history' ),
				'peakTimesChart'
			);

			$this->output_chart_section(
				_x( 'Peak Activity Days', 'insights section title', 'simple-history' ),
				'peakDaysChart'
			);
			?>

			<div class="sh-InsightsDashboard-section sh-InsightsDashboard-section--extraWide">
				<h2><?php echo esc_html_x( 'Activity Calendar', 'insights section title', 'simple-history' ); ?></h2>
				<div class="sh-InsightsDashboard-content">
					<?php $this->output_activity_calendar( $date_from, $date_to, $data['activity_overview'] ); ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Output the insights page content.
	 */
	public function output_page() {
		// Enqueue required scripts and styles.
		$this->enqueue_scripts_and_styles();

		// Get date range for the last 7 days.
		$defaults = $this->get_default_date_range();
		$date_from = $defaults['date_from'];
		$date_to = $defaults['date_to'];

		// Prepare insights data.
		$data = $this->prepare_insights_data( $date_from, $date_to );

		// Localize script data.
		$this->localize_script_data( $data, $date_from, $date_to );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo Admin_Pages::header_output();
		?>

			<div class="wrap sh-Page-content">
				<h1>
					<?php
					echo wp_kses(
						Helpers::get_settings_section_title_output(
							__( 'Insights', 'simple-history' ),
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

				<?php
				$this->output_dashboard_stats( $data['total_events'], $data['total_users'], $data['last_edit'] );
				$this->output_date_range( $date_from, $date_to );
				$this->output_date_filters();
				$this->output_dashboard_content( $data, $date_from, $date_to );
				?>
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
}
