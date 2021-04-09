<?php

defined( 'ABSPATH' ) || die();

// Stats på level (notice, warning, debug, etc.)
echo '<h3>' . esc_html__( 'Log levels', 'simple-history' ) . '</h3>';

echo '<p>' . esc_html__( 'Number of rows logged for each log level.', 'simple-history' ) . '</p>';

/*
echo "<table>";
echo "<tr>
		<th>Log level</th>
		<th>Count</th>
	</tr>";
*/
global $wpdb;

$level_counts = $wpdb->get_results(
	$wpdb->prepare(
		'
		SELECT 
			level,
			count(level) as count
		FROM %1$s
		WHERE UNIX_TIMESTAMP(date) >= %2$d
		GROUP BY level
		ORDER BY count DESC
		',
		$table_name, // 1
		strtotime( "-$period_days days" ) // 2
	)
);

$arr_chart_data = array();
$arr_chart_labels = array();
$str_js_google_chart_data = '["Log level", "Count"], ';

foreach ( $level_counts as $row ) {
	if ( empty( $row->level ) ) {
		continue;
	}

	/*
	printf('
		<tr>
			<td>%1$s</td>
			<td>%2$s</td>
		</tr>
		',
		$row->level,
		$row->count
	);
	*/

	$arr_chart_data[] = $row->count;
	$arr_chart_labels[] = $row->level;

	$str_js_google_chart_data .= sprintf(
		'["%1$s", %2$d], ',
		esc_js( $row->level ),
		esc_js( $row->count )
	);
}

$str_js_google_chart_data = rtrim( $str_js_google_chart_data, ', ' );

echo '</table>';

echo "<div class='SimpleHistoryChart__logLevelsPie'></div>";

?>
<script>
	
	/**
	 * Bar chart with log levels
	 */
	function initStatsLoggersLogLevels($) {
		var data = google.visualization.arrayToDataTable([
			<?php echo $str_js_google_chart_data;  // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		]);

		var options = {
			xtitle: 'My Daily Activities',
			backgroundColor: "transparent",
			is3D: true,
			legend: { 
				xposition: 'top',
				alignment: 'center'
			}
		};

		var chart = new google.visualization.PieChart( $(".SimpleHistoryChart__logLevelsPie").get(0) );

		chart.draw(data, options);
	}; 

	google.setOnLoadCallback(function () {
		initStatsLoggersLogLevels(jQuery);
	});

</script>
