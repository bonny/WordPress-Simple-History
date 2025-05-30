<?php

namespace Simple_History\Dropins;

use DateTimeImmutable;
use DateInterval;
use DatePeriod;
use Simple_History\Helpers;
use Simple_History\Menu_Manager;
use Simple_History\Log_Query;
use Simple_History\Events_Stats;
use Simple_History\Stats_View;

/**
 * Dropin Name: Sidebar with eventstats.
 * Dropin URI: https://simple-history.com/
 * Author: Pär Thernström
 */
class Sidebar_Stats_Dropin extends Dropin {
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

					// when chart is clicked determine what value/day was clicked
					function clickChart(e, legendItem, legend) {
						console.log("clickChart", e, legendItem, legend);

						// Get value of selected bar.
						var label;
						const points = myChart.getElementsAtEventForMode(e, 'nearest', { intersect: true }, true);
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
								//console.log(chartLabelsToDates[idx]);
								labelDate = chartLabelsToDates[idx];
							}
						}

						if (!labelDate) {
							return;
						}

						// got a date, now reload the history/post search filter form again
						var labelDateParts = labelDate.date.split("-"); ["2016", "07", "18"]

						// show custom date range
						$(".SimpleHistory__filters__filter--date").val("customRange").trigger("change");

						// set values, same for both from and to because we only want to show one day
						SimpleHistoryFilterDropin.$elms.filter_form.find("[name='from_aa'], [name='to_aa']").val(labelDateParts[0]);
						SimpleHistoryFilterDropin.$elms.filter_form.find("[name='from_jj'], [name='to_jj']").val(labelDateParts[2]);
						SimpleHistoryFilterDropin.$elms.filter_form.find("[name='from_mm'], [name='to_mm']").val(labelDateParts[1]);

						SimpleHistoryFilterDropin.$elms.filter_form.trigger("submit");

					}

				});

			})(jQuery);

		</script>

		<?php
	}

	/**
	 * Get data for chart.
	 *
	 * @param int $num_days Number of days to get data for.
	 * @return string HTML.
	 */
	protected function get_chart_data( $num_days ) {
		ob_start();

		$num_events_per_day_for_period = Helpers::get_num_events_per_day_last_n_days( $num_days );

		// Period = all dates, so empty ones don't get lost.
		$period_start_date = DateTimeImmutable::createFromFormat( 'U', strtotime( "-$num_days days" ) );
		$period_end_date = DateTimeImmutable::createFromFormat( 'U', time() );
		$interval = DateInterval::createFromDateString( '1 day' );

		$period = new DatePeriod( $period_start_date, $interval, $period_end_date->add( date_interval_create_from_date_string( '1 days' ) ) );

		?>

		<div class="sh-StatsDashboard-stat sh-StatsDashboard-stat--small">
			<div class="sh-StatsDashboard-statLabel"><?php esc_html_e( 'Daily activity over last 28 days', 'simple-history' ); ?></div>

			<div class="sh-StatsDashboard-statValue">
				<!-- wrapper div so sidebar does not "jump" when loading. so annoying. -->
				<div style="position: relative; height: 0; overflow: hidden; padding-bottom: 40%;">
					<canvas style="position: absolute; left: 0; right: 0;" class="SimpleHistory_SidebarChart_ChartCanvas" width="100" height="40"></canvas>
				</div>
			</div>
			
			<div class="sh-StatsDashboard-statSubValue">
				<p class="sh-flex sh-justify-between sh-m-0">
					<span>
						<?php
						// From date, localized.
						echo esc_html(
							wp_date(
								get_option( 'date_format' ),
								$period_start_date->getTimestamp()
							)
						);
						?>
					</span>
					<span>
						<?php
						// To date, localized.
						echo esc_html(
							wp_date(
								get_option( 'date_format' ),
								$period_end_date->getTimestamp()
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
			$str_date = date_i18n( $datef, $dt->getTimestamp() );
			$str_date_ymd = gmdate( 'Y-m-d', $dt->getTimestamp() );

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
	 * @return string HTML.
	 */
	protected function get_cta_link_html() {
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
	 * Data is cached in transient for 10 minutes.
	 *
	 * @param int $num_days_month Number of days to consider month, to get data for.
	 * @param int $num_days_week Number of days to consider week, to get data for.
	 * @return array<string,mixed>
	 */
	protected function get_quick_stats_data( $num_days_month, $num_days_week ) {
		$args_serialized = serialize( [ $num_days_month, $num_days_week ] );
		$cache_key = 'sh_quick_stats_data_' . md5( $args_serialized );
		$cache_expiration_seconds = 5 * MINUTE_IN_SECONDS;

		$results = get_transient( $cache_key );

		if ( false !== $results ) {
			return $results;
		}

		$month_date_from = DateTimeImmutable::createFromFormat( 'U', strtotime( "-$num_days_month days" ) );
		$month_date_to = DateTimeImmutable::createFromFormat( 'U', time() );

		$events_stats = new Events_Stats();

		$results = [
			'num_events_today' => Events_Stats::get_num_events_today(),
			'num_events_week' => Helpers::get_num_events_last_n_days( $num_days_week ),
			'num_events_month' => Helpers::get_num_events_last_n_days( $num_days_month ),
			'total_events' => Helpers::get_total_logged_events_count(),
			'top_users' => $events_stats->get_top_users( $month_date_from->getTimestamp(), $month_date_to->getTimestamp(), 5 ),
		];

		set_transient( $cache_key, $results, $cache_expiration_seconds );

		return $results;
	}

	/**
	 * Output HTML for sidebar.
	 */
	public function on_sidebar_html() {
		$num_days_month = 28;
		$num_days_week = 7;

		$stats_data = $this->get_quick_stats_data( $num_days_month, $num_days_week );

		$current_user_can_list_users = current_user_can( 'list_users' );

		?>
		<div class="postbox sh-PremiumFeaturesPostbox">
			<div class="inside">

				<h3 class="sh-PremiumFeaturesPostbox-title">
					<?php esc_html_e( 'History Insights', 'simple-history' ); ?>
				</h3>

				<?php
				/**
				 * Fires inside the stats sidebar box, after the headline but before any content.
				 */
				do_action( 'simple_history/dropin/stats/today' );

				/**
				 * Fires inside the stats sidebar box, after the headline but before any content.
				 */
				do_action( 'simple_history/dropin/stats/before_content' );
				?>

				<div class="sh-flex sh-justify-between sh-mb-large sh-mt-large">
					<?php
					// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
					echo $this->get_stat_dashboard_item(
						__( 'Today', 'simple-history' ),
						number_format_i18n( $stats_data['num_events_today'] ),
						_n( 'event', 'events', $stats_data['num_events_today'], 'simple-history' ),
					);

					echo $this->get_stat_dashboard_item(
						__( 'Last 7 days', 'simple-history' ),
						number_format_i18n( $stats_data['num_events_week'] ),
						_n( 'event', 'events', $stats_data['num_events_week'], 'simple-history' ),
					);

					echo $this->get_stat_dashboard_item(
						__( 'Last 28 days', 'simple-history' ),
						number_format_i18n( $stats_data['num_events_month'] ),
						_n( 'event', 'events', $stats_data['num_events_month'], 'simple-history' )
					);
					// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
					?>
				</div>

				<?php
				if ( $current_user_can_list_users ) {
					?>
					<div class="sh-StatsDashboard-stat sh-StatsDashboard-stat--small sh-my-large">
						<span class="sh-StatsDashboard-statLabel">
							<?php esc_html_e( 'Most active users in last 28 days', 'simple-history' ); ?>
							<?php echo wp_kses_post( Helpers::get_tooltip_html( __( 'Only administrators can see user names and avatars.', 'simple-history' ) ) ); ?>
						</span>
						<span class="sh-StatsDashboard-statValue"><?php Stats_View::output_top_users_avatar_list( $stats_data['top_users'] ); ?></span>
					</div>
					<?php
				}

				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo $this->get_chart_data( $num_days_month );

				$msg_text = sprintf(
					// translators: 1 is number of events, 2 is description of when the plugin was installed.
					__( 'A total of <b>%1$s events</b> have been logged since Simple History was installed.', 'simple-history' ),
					number_format_i18n( $stats_data['total_events'] ),
				);

				// Append tooltip.
				$msg_text .= Helpers::get_tooltip_html( __( 'Since install or since the install of version 5.20 if you were already using the plugin before then.', 'simple-history' ) );

				echo wp_kses_post( "<p class='sh-mt-large sh-mb-medium'>" . $msg_text . '</p>' );

				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo $this->get_cta_link_html();
		?>
			</div>
		</div>
		<?php
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
			gmdate( 'Y-m-d H:i', strtotime( 'today' ) ),
			$this->simple_history->get_events_table_name(),
			$this->simple_history->get_contexts_table_name()
		);

		$cache_key = 'quick_stats_users_today_' . md5( serialize( $sql_loggers_in ) );
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
			gmdate( 'Y-m-d H:i', strtotime( 'today' ) )
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
			gmdate( 'Y-m-d H:i', strtotime( 'today' ) ),
			$this->simple_history->get_events_table_name(),
			$this->simple_history->get_contexts_table_name(),
			$sql_other_sources_where // 5
		);

		$cache_key = 'quick_stats_results_other_sources_today_' . md5( serialize( $sql_other_sources ) );
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
	 */
	protected function get_stat_dashboard_item( $stat_label, $stat_value, $stat_subvalue = '' ) {
		ob_start();

		?>
		<div class="sh-StatsDashboard-stat sh-StatsDashboard-stat--small">
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
		</div>
		<?php

		return ob_get_clean();
	}
}
