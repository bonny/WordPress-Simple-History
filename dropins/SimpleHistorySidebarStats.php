<?php

defined( 'ABSPATH' ) or die();

/*
Dropin Name: Sidebar with short stats
Dropin URI: http://simple-history.com/
Author: Pär Thernström
*/

class SimpleHistorySidebarStats {

	private $sh;

	function __construct( $sh ) {
	
		$this->init( $sh );

	}

	function init( $sh ) {

		$this->sh = $sh;

		add_action("simple_history/dropin/sidebar/sidebar_html", array( $this, "on_sidebar_html" ), 5 );

		add_action( 'simple_history/enqueue_admin_scripts', array( $this, 'on_admin_enqueue_scripts') );

		add_action( "simple_history/admin_footer", array($this, "on_admin_footer") );

	}

	public function on_admin_enqueue_scripts() {

		wp_enqueue_script( "chart.js", SIMPLE_HISTORY_DIR_URL . "js/Chart.js", array( "jquery" ), SIMPLE_HISTORY_VERSION, true );

	}

	function on_admin_footer() {

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
					var chartDatasetData = JSON.parse( $(".SimpleHistory_SidebarChart_ChartDatasetData").val() );

					var myChart = new Chart(ctx, {
					    type: 'bar',
					    data: {
					        labels: chartLabels,
					        datasets: [{
					            data: chartDatasetData,
					            backgroundColor: "rgb(210,210,210)",
					            hoverBackgroundColor: "rgb(175,175,175)",
					        }]
					    },
					    options: {
					    	legend: {
					    		display: false
					    	},
					        scales: {
					            yAxes: [{
					                ticks: {
					                    beginAtZero:true
					                },
					            }],
					            xAxes: [{
					            	display: false
					            }]
					        }
					    }
					});			

				});

			})(jQuery);


		</script>
		
		<?php

	}

	function on_sidebar_html() {

		$num_days = 28;

		$num_events_per_day_for_period = $this->sh->get_num_events_per_day_last_n_days( $num_days );

		// Period = all dates, so empty ones don't get lost
		$period_start_date = DateTime::createFromFormat('U', strtotime("-$num_days days"));
		$period_end_date = DateTime::createFromFormat('U', time());
		$interval = DateInterval::createFromDateString('1 day');
		$period = new DatePeriod($period_start_date, $interval, $period_end_date->add( date_interval_create_from_date_string('1 days') ) );

		?>
				
		<div class="postbox">

			<h3 class="hndle"><?php _e("Stats", "simple-history") ?></h3>

			<div class="inside">
				
				<p>
					<?php				

					printf(
						__('<b>%1$s events</b> have been logged the last <b>%2$s days</b>.', "simple-history"),
						$this->sh->get_num_events_last_n_days( $num_days ),
						number_format_i18n( $num_days )
					);

					?>
				</p>
				
				<!-- wrapper div so sidebar does not "jump" when loading. so annoying. -->
				<div style="position: relative; height: 0; overflow: hidden; padding-bottom: 40%;">
					<canvas style="position: absolute; left: 0; right: 0;" class="SimpleHistory_SidebarChart_ChartCanvas" width="100" height="40"></canvas>
				</div>

				<p class="SimpleHistory_SidebarChart_ChartDescription" style="font-style: italic; color: #777; text-align: center;">
					<?php _e("Number of events per day.", "simple-history") ?>
				</p>

				<?php

				$arr_labels = array();
				$arr_dataset_data = array();

				foreach ( $period as $dt ) {
					
					$datef = _x( 'M j', "stats: date in rows per day chart", "simple-history" );
					$str_date = date_i18n( $datef, $dt->getTimestamp() );

					// Get data for this day, if exist
					// Day in object is in format '2014-09-07'
					$yearDate = $dt->format( "Y-m-d" );
					$day_data = wp_filter_object_list( $num_events_per_day_for_period, array("yearDate" => $yearDate) );

					$arr_labels[] = $str_date;

					if ( $day_data ) {

						$day_data = reset( $day_data );
						$arr_dataset_data[] = $day_data->count;

					} else {
						$arr_dataset_data[] = 0;
					}

				}

				?>

				<input type="hidden" class="SimpleHistory_SidebarChart_ChartLabels" value="<?php esc_attr_e( json_encode( $arr_labels )  ) ?>">
				<input type="hidden" class="SimpleHistory_SidebarChart_ChartDatasetData" value="<?php esc_attr_e( json_encode( $arr_dataset_data )  ) ?>">

			</div>
		</div>
	
		<?php

	}

}
