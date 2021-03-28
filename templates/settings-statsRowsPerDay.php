<?php

defined( 'ABSPATH' ) || die();

echo "<h4 class=''>";
esc_html_e( 'Rows per day', 'simple-history' );
echo '</h4>';

$dates = $wpdb->get_results(
	$wpdb->prepare(
		'
			SELECT 
				date_format(date, "%%Y-%%m-%%d") AS yearDate,
				count(date) AS count
			FROM  
				%1$s
			WHERE UNIX_TIMESTAMP(date) >= %2$d
			GROUP BY yearDate
			ORDER BY yearDate ASC
		',
		$wpdb->prefix . SimpleHistory::DBTABLE,
		strtotime( "-$period_days days" )
	)
);

// echo '<div class="SimpleHistoryChart__rowsPerDay"></div>';
echo '<div class="SimpleHistoryChart__rowsPerDayGoogleChart"></div>';

// Loop from $period_start_date to $period_end_date
$interval = DateInterval::createFromDateString( '1 day' );
$period = new DatePeriod( $period_start_date, $interval, $period_end_date->add( date_interval_create_from_date_string( '1 days' ) ) );
$str_js_chart_labels = '';
$str_js_chart_data = '';
$str_js_google_chart_data = '';

foreach ( $period as $dt ) {
	$datef = _x( 'M j', 'stats: date in rows per day chart', 'simple-history' );
	$str_date = date_i18n( $datef, $dt->getTimestamp() );

	$str_js_chart_labels .= sprintf(
		'"%1$s",',
		$str_date
	);

	// Get data for this day, if exist
	// Day in object is in format '2014-09-07'
	$day_data = wp_filter_object_list(
		$dates,
		array(
			'yearDate' => $dt->format( 'Y-m-d' ),
		)
	);
	$day_data_value = 0;
	if ( $day_data ) {
		$day_data_value = (int) current( $day_data )->count;
	}

	$str_js_chart_data .= sprintf(
		'%1$s,',
		$day_data_value
	);

	$str_js_google_chart_data .= sprintf(
		'["%2$s", %1$d], ',
		$day_data_value, // 1
		$str_date // 2
	);
}

$str_js_chart_labels = rtrim( $str_js_chart_labels, ',' );
$str_js_chart_data = rtrim( $str_js_chart_data, ',' );
$str_js_google_chart_data = rtrim( $str_js_google_chart_data, ', ' );

?>

<script>
	
	/**
	 * Bar chart with rows per day
	 */
	function initStatsRowsPerDay($) {
		// Google Bar Chart
		var data = google.visualization.arrayToDataTable([
			['Date', 'Number of rows'],
			<?php echo $str_js_google_chart_data; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		]);

		var options = {
			xtitle: 'Company Performance',
			xhAxis: {
				title: 'Year', 
				titleTextStyle: {
					color: 'red'
				}
			},
			xlegend: { position: "none" },
			backgroundColor: "transparent",
			xchartArea: { left: 0, width: "80%" },
			xchartArea2: {'width': '100%', 'xheight': '80%'},
			xxlegend: {'position': 'bottom'},
			legend: { 
				xposition: 'top',
				alignment: 'center'
			}


		};

		var chart = new google.visualization.LineChart( $(".SimpleHistoryChart__rowsPerDayGoogleChart").get(0) );

		chart.draw(data, options);
	};
	google.setOnLoadCallback(function () {
		initStatsRowsPerDay(jQuery);
	});
</script>
