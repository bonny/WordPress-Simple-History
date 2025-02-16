<?php

namespace Simple_History\Dropins;

use DateTime;
use DateInterval;
use DatePeriod;
use Simple_History\Helpers;

/**
 * Dropin Name: Sidebar with short stats
 * Dropin URI: https://simple-history.com/
 * Author: Pär Thernström
 */
class Sidebar_Stats_Dropin extends Dropin {
	/** @inheritdoc */
	public function loaded() {
		add_action( 'simple_history/dropin/sidebar/sidebar_html', array( $this, 'on_sidebar_html' ), 5 );
		add_action( 'simple_history/enqueue_admin_scripts', array( $this, 'on_admin_enqueue_scripts' ) );
		add_action( 'simple_history/admin_footer', array( $this, 'on_admin_footer' ) );
	}

	/**
	 * Enqueue scripts.
	 */
	public function on_admin_enqueue_scripts() {
		wp_enqueue_script( 'simple_history_chart.js', SIMPLE_HISTORY_DIR_URL . 'js/chart.4.3.0.min.js', array( 'jquery' ), '4.3.0', true );
	}

	/**
	 * Run JS in footer.
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

					var myChart = new Chart(ctx, {
						type: 'bar',
						data: {
							labels: chartLabels,
							datasets: [{
								label: '',
								data: chartDatasetData,
								backgroundColor: "rgb(210,210,210)",
								hoverBackgroundColor: "rgb(175,175,175)",
							}]
						},
						options: {
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
							},
							onClick: clickChart
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

		<style>
			.SimpleHistory_SidebarChart_ChartDescription {
				margin-bottom: 0;
			}
		</style>

		<?php
	}

	/**
	 * Get text for stats, i.e. "X events have been logged the last Y days."
	 *
	 * @param int $num_days Number of days to get stats for.
	 * @return string HTML, contains tags for bold and paragraph.
	 */
	protected function get_events_in_last_days_stats_text( $num_days ) {
		$msg = sprintf(
			// translators: 1 is number of events, 2 is number of days.
			__( '<b>%1$s events</b> have been logged the last <b>%2$s days</b>.', 'simple-history' ),
			number_format_i18n( Helpers::get_num_events_last_n_days( $num_days ) ),
			number_format_i18n( $num_days )
		);

		return '<p class="SimpleHistoryQuickStats">' . $msg . '</p>';
	}

	/**
	 * Get text for stats, i.e. "X events have been logged since plugin install."
	 *
	 * @return string HTML, contains tags for bold, paragraph, and span.
	 */
	protected function get_events_since_plugin_install_stats_text() {
		$total_events = Helpers::get_total_logged_events_count();

		$msg = sprintf(
			// translators: 1 is number of events, 2 is description of when the plugin was installed.
			__( '<b>%1$s events</b> have been logged since Simple History <span title="%2$s">was installed</span>.', 'simple-history' ),
			number_format_i18n( $total_events ),
			__( 'Since install or since the install of version 5.20 if you were already using the plugin before then.', 'simple-history' )
		);

		return '<p class="SimpleHistoryQuickStats SimpleHistoryQuickStats--totalLoggedEvents">' . $msg . '</p>';
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
		$period_start_date = DateTime::createFromFormat( 'U', strtotime( "-$num_days days" ) );
		$period_end_date = DateTime::createFromFormat( 'U', time() );
		$interval = DateInterval::createFromDateString( '1 day' );

		$period = new DatePeriod( $period_start_date, $interval, $period_end_date->add( date_interval_create_from_date_string( '1 days' ) ) );

		?>
		<!-- wrapper div so sidebar does not "jump" when loading. so annoying. -->
		<div style="position: relative; height: 0; overflow: hidden; padding-bottom: 40%;">
			<canvas style="position: absolute; left: 0; right: 0;" class="SimpleHistory_SidebarChart_ChartCanvas" width="100" height="40"></canvas>
		</div>

		<p class="SimpleHistory_SidebarChart_ChartDescription" style="font-style: italic; color: #777; text-align: center;">
			<?php esc_html_e( 'Number of events per day.', 'simple-history' ); ?>
		</p>

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
	 * Output HTML for sidebar.
	 */
	public function on_sidebar_html() {
		$num_days = 28;

		?>
		<div class="postbox sh-PremiumFeaturesPostbox">			
			<div class="inside">
				<h3 class="sh-PremiumFeaturesPostbox-title">
					<?php esc_html_e( 'Stats & Insights', 'simple-history' ); ?>
				</h3>

				<?php
				/**
				 * Fires inside the stats sidebar box, after the headline but before any content.
				 */
				do_action( 'simple_history/dropin/stats/before_content' );

				echo wp_kses(
					$this->get_events_in_last_days_stats_text( $num_days ),
					array(
						'p' => array(
							'class' => array(),
						),
						'b' => array(),
					)
				);

				// Output total number of events logged since plugin install.
				echo wp_kses(
					$this->get_events_since_plugin_install_stats_text(),
					array(
						'p' => array(
							'class' => array(),
						),
						'b' => array(),
						'span' => array(
							'title' => array(),
						),
					)
				);

				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo $this->get_chart_data( $num_days );
				?>
			</div>
		</div>
		<?php
	}
}
