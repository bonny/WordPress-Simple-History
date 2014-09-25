<?php

// Stats på level (notice, warning, debug, etc.)
$sql = sprintf('
	SELECT 
		level,
		count(level) as count
	FROM %1$s
	GROUP BY level
	ORDER BY count DESC
	', $table_name
);

$level_counts = $wpdb->get_results($sql);



echo "<h3>Log levels</h3>";
echo "<table>";
echo "<tr>
		<th>Log level</th>
		<th>Count</th>
	</tr>";

$arr_chart_data = array();
$arr_chart_labels = array();

foreach ( $level_counts as $row ) {

	if ( empty($row->level) ) {
		continue;
	}
		
	printf('
		<tr>
			<td>%1$s</td>
			<td>%2$s</td>
		</tr>
		', 
		$row->level, 
		$row->count 
	);

	$arr_chart_data[] = $row->count;
	$arr_chart_labels[] = $row->level;

}

echo "</table>";

echo "<div class='ct-chart ct-minor-seventh SimpleHistoryChart__logLevels'></div>";

?>
<script>
	
	/**
	 * Bar chart with log levels
	 */
	jQuery(function($) {
		
		var data = {
			labels: ["<?php echo implode('", "', $arr_chart_labels) ?>"],
			series: [
				[<?php echo implode(",", $arr_chart_data) ?>]
			]
		};		
		
		var options = {
		};

		Chartist.Bar(".SimpleHistoryChart__logLevels", data, options);

	});

</script>
