<?php

namespace Simple_History\Dropins;

use DateTimeImmutable;
use DateInterval;
use DatePeriod;
use Simple_History\Helpers;
use Simple_History\Menu_Manager;
use Simple_History\Events_Stats;
use Simple_History\Stats_View;
use Simple_History\Date_Helper;
use Simple_History\Simple_History;

/**
 * Dropin Name: Sidebar with eventstats.
 * Dropin URI: https://simple-history.com/
 * Author: Pär Thernström
 */
class Sidebar_Stats_Dropin extends Dropin {
	/**
	 * Cache duration in minutes for sidebar stats.
	 *
	 * @var int
	 */
	const CACHE_DURATION_MINUTES = 5;

	/** @inheritdoc */
	public function loaded() {
		add_action( 'simple_history/dropin/sidebar/sidebar_html', array( $this, 'on_sidebar_html' ), 4 );
		add_action( 'simple_history/enqueue_admin_scripts', array( $this, 'on_admin_enqueue_scripts' ) );
		add_action( 'simple_history/admin_footer', array( $this, 'on_admin_footer' ) );
	}

	/**
	 * Enqueue scripts.
	 */
	public function on_admin_enqueue_scripts() {
		wp_enqueue_script( 'simple_history_chart.js', SIMPLE_HISTORY_DIR_URL . 'js/chart.4.4.8.min.js', array( 'jquery' ), '4.4.0', true );
	}

	/**
	 * Run JS in footer to generate chart.
	 */
	public function on_admin_footer() {
		?>
		<script>
			/**
			 * JavaScript for SimpleHistory_SidebarChart
			 */
			(function($) {

				$(function() {

					var ctx = $(".SimpleHistory_SidebarChart_ChartCanvas");

					if ( ! ctx.length ) {
						return;
					}

					var chartLabels =  JSON.parse( $(".SimpleHistory_SidebarChart_ChartLabels").val() );
					var chartLabelsToDates =  JSON.parse( $(".SimpleHistory_SidebarChart_ChartLabelsToDates").val() );
					var chartDatasetData = JSON.parse( $(".SimpleHistory_SidebarChart_ChartDatasetData").val() );

					const color = getComputedStyle(document.documentElement).getPropertyValue('--sh-color-blue');

					// Create chart.
					var myChart = new Chart(ctx, {
						type: 'line',
						data: {
							labels: chartLabels,
							datasets: [{
								label: '',
								data: chartDatasetData,
								borderColor: color,
								backgroundColor: color,
								borderWidth: 2,
								pointRadius: 0
							}]
						},
						options: {
							interaction: {
								intersect: false,
								mode: 'index',
							},
							scales: {
								y: {
									ticks: {
										beginAtZero:true
									},
								},
								x: {
									display: false
								}
							},
							plugins: {
								legend: {
									display: false
								},
								// https://www.chartjs.org/docs/4.4.0/configuration/tooltip.html
								tooltip: {
									displayColors: false,
									callbacks: {
										label: function(context) {
											let eventsCount = context.parsed.y;
											let label = `${eventsCount} events`;
											return label;
										}
									}
								}
							},
							onClick: clickChart,
						},
					});

					/**
					 * When chart is clicked determine what value/day was clicked
					 * and dispatch a custom event to the React app to handle the date filter.
					 */
					function clickChart(e, legendItem, legend) {
						// Get value of selected bar.
						// Use 'index' mode with intersect: false to match the tooltip behavior,
						// so clicking anywhere in the vertical area of a day will select that day.
						var label;
						const points = myChart.getElementsAtEventForMode(e, 'index', { intersect: false }, true);
						if (points.length) {
							const firstPoint = points[0];
							// Label e.g. "Jun 25".
							label = myChart.data.labels[firstPoint.index];
						}

						// now we have the label which is like "July 23" or "23 juli" depending on language
						// look for that label value in chartLabelsToDates and there we get the date in format Y-m-d
						var labelDate;
						for (idx in chartLabelsToDates) {
							if (label == chartLabelsToDates[idx].label) {
								labelDate = chartLabelsToDates[idx];
							}
						}

						if (!labelDate) {
							return;
						}

						// Dispatch custom event for React app to handle the date filter.
						// The React app will listen for this event and update the date filter state.
						const event = new CustomEvent('SimpleHistory:chartDateClick', {
							detail: {
								// Date in Y-m-d format, e.g., "2024-10-05".
								date: labelDate.date,
							}
						});

						window.dispatchEvent(event);
					}

				});

			})(jQuery);

		</script>

		<?php
	}

	/**
	 * Get data for chart.
	 *
	 * @param int   $num_days Number of days to get data for.
	 * @param array $num_events_per_day_for_period Cached chart data from get_quick_stats_data.
	 * @return string HTML.
	 */
	protected function get_chart_data( $num_days, $num_events_per_day_for_period ) {
		ob_start();

		// Period = all dates, so empty ones don't get lost.
		// For "last N days" including today, we go back N-1 days.
		// E.g., "last 30 days" on Oct 7 = Sep 8 to Oct 7 (30 days).
		// Create DateTimeImmutable directly in WordPress timezone to avoid timezone conversion issues.
		$days_ago = $num_days - 1;
		$period_start_date = new DateTimeImmutable( "-{$days_ago} days", wp_timezone() );
		$period_start_date = new DateTimeImmutable( $period_start_date->format( 'Y-m-d' ) . ' 00:00:00', wp_timezone() );
		$today = new DateTimeImmutable( 'today', wp_timezone() );
		$interval = DateInterval::createFromDateString( '1 day' );

		// DatePeriod excludes end date by default.
		// To include today, we need to set end to tomorrow 00:00:00.
		$tomorrow = $today->add( date_interval_create_from_date_string( '1 days' ) );
		$period = new DatePeriod( $period_start_date, $interval, $tomorrow );

		?>

		<div class="sh-StatsDashboard-stat sh-StatsDashboard-stat--small">
			<div class="sh-StatsDashboard-statLabel">
				<?php
				printf(
					// translators: 1 is number of days.
					esc_html__( 'Daily activity over last %d days', 'simple-history' ),
					esc_html( Date_Helper::DAYS_PER_MONTH )
				);
				?>
			</div>

			<div class="sh-StatsDashboard-statValue">
				<!-- wrapper div so sidebar does not "jump" when loading. so annoying. -->
				<div style="position: relative; height: 0; overflow: hidden; padding-bottom: 40%;">
					<canvas style="position: absolute; left: 0; right: 0;" class="SimpleHistory_SidebarChart_ChartCanvas" width="100" height="40"></canvas>
				</div>
			</div>
			
			<div class="sh-StatsDashboard-statSubValue">
				<p class="sh-flex sh-justify-between sh-m-0"
					style="margin-top: calc(-1 * var(--sh-spacing-small));"
				>
					<span>
						<?php
						// From date, localized.
						// Example: "September 4, 2025".
						echo esc_html(
							wp_date(
								'M j',
								$period_start_date->getTimestamp()
							)
						);
						?>
					</span>
					<span>
						<?php
						// To date, localized.
						// Example: "October 4, 2025".
						echo esc_html(
							wp_date(
								'M j',
								$today->getTimestamp()
							)
						);
						?>
					</span>
				</p>

			</div>
		</div>
		<?php
		$arr_labels = array();
		$arr_labels_to_datetime = array();
		$arr_dataset_data = array();

		foreach ( $period as $dt ) {
			$datef = _x( 'M j', 'stats: date in rows per day chart', 'simple-history' );
			$str_date = wp_date( $datef, $dt->getTimestamp() );
			$str_date_ymd = $dt->format( 'Y-m-d' );

			// Get data for this day, if exist
			// Day in object is in format '2014-09-07'.
			$yearDate = $dt->format( 'Y-m-d' );
			$day_data = wp_filter_object_list(
				$num_events_per_day_for_period,
				array(
					'yearDate' => $yearDate,
				)
			);

			$arr_labels[] = $str_date;

			$arr_labels_to_datetime[] = array(
				'label' => $str_date,
				'date' => $str_date_ymd,
			);

			if ( $day_data ) {
				$day_data = reset( $day_data );
				$arr_dataset_data[] = $day_data->count;
			} else {
				$arr_dataset_data[] = 0;
			}
		}

		?>
		<input
			type="hidden"
			class="SimpleHistory_SidebarChart_ChartLabels"
			value="<?php echo esc_attr( json_encode( $arr_labels ) ); ?>"
			/>

		<input
			type="hidden"
			class="SimpleHistory_SidebarChart_ChartLabelsToDates"
			value="<?php echo esc_attr( json_encode( $arr_labels_to_datetime ) ); ?>"
			/>

		<input
			type="hidden"
			class="SimpleHistory_SidebarChart_ChartDatasetData"
			value="<?php echo esc_attr( json_encode( $arr_dataset_data ) ); ?>"
			/>

		<?php

		return ob_get_clean();
	}

	/**
	 * Get HTML for stats and summaries link.
	 *
	 * @param bool $current_user_can_manage_options If current user has manage options capability.
	 * @return string HTML.
	 */
	protected function get_cta_link_html( $current_user_can_manage_options ) {
		if ( ! $current_user_can_manage_options ) {
			return '';
		}

		$stats_page_url = Menu_Manager::get_admin_url_by_slug( 'simple_history_stats_page' );

		// Bail if no stats page url (user has no access to stats page).
		if ( empty( $stats_page_url ) ) {
			return '';
		}

		ob_start();
		?>
		<p>
			<a class="sh-PremiumFeaturesPostbox-button" href="<?php echo esc_url( $stats_page_url ); ?>">
				<?php esc_html_e( 'See all History Insights', 'simple-history' ); ?>
			</a>
		</p>
		<?php
		return ob_get_clean();
	}

	/**
	 * Get data for the quick stats.
	 * Data is cached in transient for CACHE_DURATION_MINUTES minutes.
	 *
	 * @param int $num_days_month Number of days to consider month, to get data for.
	 * @param int $num_days_week Number of days to consider week, to get data for.
	 * @return array<string,mixed> Array with stats data.
	 */
	protected function get_quick_stats_data( $num_days_month, $num_days_week ) {
		$simple_history = Simple_History::get_instance();
		$loggers_slugs = $simple_history->get_loggers_that_user_can_read( null, 'slugs' );
		$current_user_can_manage_options = current_user_can( 'manage_options' );
		$current_user_can_list_users = current_user_can( 'list_users' );
		$args_serialized = serialize( [ $num_days_month, $num_days_week, $loggers_slugs, $current_user_can_manage_options, $current_user_can_list_users ] );
		$cache_key = 'sh_quick_stats_data_' . md5( $args_serialized );
		$cache_expiration_seconds = self::CACHE_DURATION_MINUTES * MINUTE_IN_SECONDS;

		$results = get_transient( $cache_key );

		// Uncomment below to test without cache = always run the queries = always fresh data.
		// phpcs:ignore Squiz.Commenting.InlineComment.InvalidEndChar
		// Claude: please keep this, I need it to always get fresh data when testing/developing.
		$results = false;

		if ( false !== $results ) {
			return $results;
		}

		$results = [
			'num_events_today' => Helpers::get_num_events_today(),
			'num_events_week' => Helpers::get_num_events_last_n_days( $num_days_week ),
			'num_events_month' => Helpers::get_num_events_last_n_days( $num_days_month ),
			'chart_data_month' => Helpers::get_num_events_per_day_last_n_days( $num_days_month ),
		];

		// Only fetch total_events for admins.
		if ( $current_user_can_manage_options ) {
			$results['total_events'] = Helpers::get_total_logged_events_count();
		}

		// Only fetch top_users for users who can list users.
		if ( $current_user_can_list_users ) {
			$month_date_from = DateTimeImmutable::createFromFormat( 'U', Date_Helper::get_last_n_days_start_timestamp( $num_days_month ) );
			$month_date_to = DateTimeImmutable::createFromFormat( 'U', Date_Helper::get_current_timestamp() );
			$events_stats = new Events_Stats();
			$results['top_users'] = $events_stats->get_top_users( $month_date_from->getTimestamp(), $month_date_to->getTimestamp(), 5 );
		}

		set_transient( $cache_key, $results, $cache_expiration_seconds );

		return $results;
	}

	/**
	 * Output HTML for sidebar.
	 */
	public function on_sidebar_html() {
		$num_days_month = Date_Helper::DAYS_PER_MONTH;
		$num_days_week = Date_Helper::DAYS_PER_WEEK;

		$stats_data = $this->get_quick_stats_data( $num_days_month, $num_days_week );

		$current_user_can_list_users = current_user_can( 'list_users' );
		$current_user_can_manage_options = current_user_can( 'manage_options' );

		?>
		<div class="postbox sh-PremiumFeaturesPostbox">
			<div class="inside">

				<h3 class="sh-PremiumFeaturesPostbox-title sh-mb-small">
					<?php esc_html_e( 'History Insights', 'simple-history' ); ?>
				</h3>

				<p class="sh-mt-0">
					<?php
					if ( $current_user_can_manage_options ) {
						printf(
							/* translators: %d is the number of minutes between cache refreshes */
							esc_html__( 'Calculated from all events. Updates every %d minutes.', 'simple-history' ),
							absint( self::CACHE_DURATION_MINUTES )
						);
					} else {
						printf(
							/* translators: %d is the number of minutes between cache refreshes */
							esc_html__( 'Based on events you can view. Updates every %d minutes.', 'simple-history' ),
							absint( self::CACHE_DURATION_MINUTES )
						);
					}
					?>
				</p>

				<?php
				/**
				 * Fires inside the stats sidebar box, after the headline but before any content.
				 */
				do_action( 'simple_history/dropin/stats/today' );

				/**
				 * Fires inside the stats sidebar box, after the headline but before any content.
				 */
				do_action( 'simple_history/dropin/stats/before_content' );

				// Today, 7 days, 30 days.
				echo wp_kses_post( $this->get_events_per_days_stats_html( $stats_data ) );

				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo $this->get_chart_data( $num_days_month, $stats_data['chart_data_month'] );

				// Most active users in last 30 days. Only visible to admins.
				echo wp_kses_post( $this->get_most_active_users_html( $current_user_can_list_users, $stats_data ) );

				// Show total events logged. Only visible to admins.
				echo wp_kses_post( $this->get_total_events_logged_html( $current_user_can_manage_options, $stats_data ) );

				// Show insights page CTA. Only visible to admins.
				echo wp_kses_post( $this->get_cta_link_html( $current_user_can_manage_options ) );

				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Get HTML for total events logged.
	 *
	 * @param bool  $current_user_can_manage_options If current user has manage options capability.
	 * @param array $stats_data Stats data.
	 * @return string HTML.
	 */
	protected function get_total_events_logged_html( $current_user_can_manage_options, $stats_data ) {
		if ( ! $current_user_can_manage_options ) {
			return '';
		}

		if ( ! isset( $stats_data['total_events'] ) ) {
			return '';
		}

		$msg_text = sprintf(
			// translators: 1 is number of events, 2 is description of when the plugin was installed.
			__( 'A total of <b>%1$s events</b> have been logged since Simple History was installed.', 'simple-history' ),
			number_format_i18n( $stats_data['total_events'] ),
		);

		// Append tooltip.
		$msg_text .= Helpers::get_tooltip_html( __( 'Since install or since the install of version 5.20 if you were already using the plugin before then. Only administrators can see this number.', 'simple-history' ) );

		// Return concatenated result.
		return wp_kses_post( "<p class='sh-mt-large sh-mb-medium'>" . $msg_text . '</p>' );
	}

	/**
	 * Output HTML for most active users data, i.e. avatars and usernames.
	 *
	 * @param bool  $current_user_can_list_users If current user has list users capability.
	 * @param array $stats_data Stats data.
	 * @return string Avatars and usernames if user can view, empty string otherwise.
	 */
	protected function get_most_active_users_html( $current_user_can_list_users, $stats_data ) {
		if ( ! $current_user_can_list_users ) {
			return '';
		}

		if ( ! isset( $stats_data['top_users'] ) ) {
			return '';
		}

		ob_start();

		?>
		<div class="sh-StatsDashboard-stat sh-StatsDashboard-stat--small sh-my-large">
			<span class="sh-StatsDashboard-statLabel">
				<?php
				printf(
					// translators: 1 is number of days.
					esc_html__( 'Most active users in last %d days', 'simple-history' ),
					esc_html( Date_Helper::DAYS_PER_MONTH )
				);
				?>
				<?php echo wp_kses_post( Helpers::get_tooltip_html( __( 'Only administrators can see user names and avatars.', 'simple-history' ) ) ); ?>
			</span>
			<span class="sh-StatsDashboard-statSubValue">
				<?php Stats_View::output_top_users_avatar_list( $stats_data['top_users'] ); ?>
			</span>
		</div>
		<?php

		return ob_get_clean();
	}

	/**
	 * Get HTML for number of events per today, 7 days, 30 days.
	 *
	 * @param array $stats_data Array with stats data.
	 * @return string
	 */
	protected function get_events_per_days_stats_html( $stats_data ) {
		// Generate URLs for filtering events by time period.
		$url_today = Helpers::get_filtered_events_url( [ 'date' => 'lastdays:1' ] );
		$url_week = Helpers::get_filtered_events_url( [ 'date' => 'lastdays:7' ] );
		$url_month = Helpers::get_filtered_events_url( [ 'date' => 'lastdays:30' ] );

		ob_start();

		?>
		<div class="sh-SidebarStats-eventsPerDays">
			<?php
			// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $this->get_stat_dashboard_item(
				__( 'Today', 'simple-history' ),
				number_format_i18n( $stats_data['num_events_today'] ),
				_n( 'event', 'events', $stats_data['num_events_today'], 'simple-history' ),
				$url_today
			);

			echo $this->get_stat_dashboard_item(
				// translators: %d is the number of days.
				sprintf( __( '%d days', 'simple-history' ), Date_Helper::DAYS_PER_WEEK ),
				number_format_i18n( $stats_data['num_events_week'] ),
				_n( 'event', 'events', $stats_data['num_events_week'], 'simple-history' ),
				$url_week
			);

			echo $this->get_stat_dashboard_item(
				// translators: %d is the number of days.
				sprintf( __( '%d days', 'simple-history' ), Date_Helper::DAYS_PER_MONTH ),
				number_format_i18n( $stats_data['num_events_month'] ),
				_n( 'event', 'events', $stats_data['num_events_month'], 'simple-history' ),
				$url_month
			);
			// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
			?>
			</div>
		<?php

		return ob_get_clean();
	}

	/**
	 * Get the SQL for the loggers that the current user is allowed to read.
	 *
	 * @return string
	 */
	protected function get_sql_loggers_in() {
		return $this->simple_history->get_loggers_that_user_can_read( get_current_user_id(), 'sql' );
	}

	/**
	 * Get the number of users that have done something today.
	 *
	 * @return int
	 */
	protected function get_num_users_today() {
		global $wpdb;

		$sql_loggers_in = $this->get_sql_loggers_in();

		// Get number of users today, i.e. events with wp_user as initiator.
		$sql_users_today = sprintf(
			'
            SELECT
                DISTINCT(c.value) AS user_id
                FROM %3$s AS h
            INNER JOIN %4$s AS c
            ON c.history_id = h.id AND c.key = \'_user_id\'
            WHERE
                initiator = \'wp_user\'
                AND logger IN %1$s
                AND date > \'%2$s\'
            ',
			$sql_loggers_in,
			gmdate( 'Y-m-d H:i:s', Date_Helper::get_today_start_timestamp() ),
			$this->simple_history->get_events_table_name(),
			$this->simple_history->get_contexts_table_name()
		);

		$cache_key = 'sh_quick_stats_users_today_' . md5( serialize( $sql_loggers_in ) );
		$cache_group = Helpers::get_cache_group();
		$results_users_today = wp_cache_get( $cache_key, $cache_group );

		if ( false === $results_users_today ) {
			$results_users_today = $wpdb->get_results( $sql_users_today ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			wp_cache_set( $cache_key, $results_users_today, $cache_group );
		}

		$count_users_today = is_countable( $results_users_today ) ? count( $results_users_today ) : 0;

		return $count_users_today;
	}

	/**
	 * Get number of other sources (not wp_user).
	 *
	 * @return int Number of other sources.
	 */
	protected function get_other_sources_count() {
		global $wpdb;

		$cache_group = Helpers::get_cache_group();
		$sql_loggers_in = $this->get_sql_loggers_in();

		$sql_other_sources_where = sprintf(
			'
                initiator <> \'wp_user\'
                AND logger IN %1$s
                AND date > \'%2$s\'
            ',
			$sql_loggers_in,
			gmdate( 'Y-m-d H:i:s', Date_Helper::get_today_start_timestamp() )
		);

		$sql_other_sources_where = apply_filters( 'simple_history/quick_stats_where', $sql_other_sources_where );

		$sql_other_sources = sprintf(
			'
            SELECT
                DISTINCT(h.initiator) AS initiator
            FROM %3$s AS h
            WHERE
                %5$s
            ',
			$sql_loggers_in,
			gmdate( 'Y-m-d H:i:s', Date_Helper::get_today_start_timestamp() ),
			$this->simple_history->get_events_table_name(),
			$this->simple_history->get_contexts_table_name(),
			$sql_other_sources_where // 5
		);

		$cache_key = 'sh_quick_stats_other_sources_today_' . md5( serialize( $sql_other_sources ) );
		$results_other_sources_today = wp_cache_get( $cache_key, $cache_group );

		if ( false === $results_other_sources_today ) {
			$results_other_sources_today = $wpdb->get_results( $sql_other_sources ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			wp_cache_set( $cache_key, $results_other_sources_today, $cache_group );
		}

		$count_other_sources = is_countable( $results_other_sources_today ) ? count( $results_other_sources_today ) : 0;

		return $count_other_sources;
	}

	/**
	 * Get a stat dashboard stats item.
	 *
	 * @param string $stat_label The label text for the stat.
	 * @param string $stat_value The main value text to display.
	 * @param string $stat_subvalue Optional subvalue to display below main value.
	 * @param string $stat_url Optional URL to make the stat label clickable.
	 */
	protected function get_stat_dashboard_item( $stat_label, $stat_value, $stat_subvalue = '', $stat_url = '' ) {
		ob_start();

		$tag = empty( $stat_url ) ? 'div' : 'a';
		$attrs = empty( $stat_url ) ? '' : sprintf( ' href="%s"', esc_url( $stat_url ) );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $tag is hardcoded 'a' or 'div', $attrs is escaped.
		printf( '<%s class="sh-StatsDashboard-stat sh-StatsDashboard-stat--small"%s>', $tag, $attrs );
		?>
			<span class="sh-StatsDashboard-statLabel"><?php echo esc_html( $stat_label ); ?></span>
			<span class="sh-StatsDashboard-statValue"><?php echo esc_html( $stat_value ); ?></span>
			<?php
			if ( ! empty( $stat_subvalue ) ) {
				?>
				<span class="sh-StatsDashboard-statSubValue">
					<?php echo wp_kses_post( $stat_subvalue ); ?>
				</span>
				<?php
			}
			?>
		<?php
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $tag is hardcoded 'a' or 'div'.
		printf( '</%s>', $tag );

		return ob_get_clean();
	}
}
