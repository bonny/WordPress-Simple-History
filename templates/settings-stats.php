<?php

global $wpdb;
$table_name = $wpdb->prefix . SimpleHistory::DBTABLE;
$table_name_contexts = $wpdb->prefix . SimpleHistory::DBTABLE_CONTEXTS;

$period_days = (int) 28;
$period_start_date = DateTime::createFromFormat('U', strtotime("-$period_days days"));
$period_end_date = DateTime::createFromFormat('U', time());

// Colors taken from the gogole chart example that was found in this Stack Overflow thread:
// http://stackoverflow.com/questions/236936/how-pick-colors-for-a-pie-chart
$arr_colors = explode(",", "8a56e2,cf56e2,e256ae,e25668,e28956,e2cf56,aee256,68e256,56e289,56e2cf,56aee2,5668e2");

// Generate CSS classes for chartist, based on $arr_colors
$str_chartist_css_colors = "";
$arr_chars = str_split("abcdefghijkl");
$i = 0;
foreach ($arr_chars as $one_char) {
	
	$str_chartist_css_colors .= sprintf('
		.ct-chart .ct-series.ct-series-%1$s .ct-slice:not(.ct-donut) {
			fill: #%2$s;
		}
		', 
		$one_char, // 1
		$arr_colors[$i] // 2
	);
	$i++;

}

// Echo styles like this because syntax highlighter in sublime goes bananas 
// if I try to do it in any other way...
echo "
	<style>
		.SimpleHistoryStats__intro {
			font-size: 1.4em;
		}
		.SimpleHistoryStats__graphs {
			overflow: auto;
		}
		.SimpleHistoryStats__graph {
			float: left;
			width: 50%;
		}

		/* chartist bar */
		.ct-chart .ct-bar {
			stroke-width: 15px;
			box-shadow: 1px 1px 1px black;
		}
		.ct-chart .ct-series.ct-series-a .ct-bar {
			stroke: rgb(226, 86, 174);
		}
		
		/* chartist chart */
		{$str_chartist_css_colors}
		
	</style>
";

// Number of rows the last n days
function get_num_rows_last_n_days($period_days) {

	global $wpdb;

	$sql = sprintf(
		'select count(*) FROM %1$s WHERE UNIX_TIMESTAMP(date) >= %2$d',
		$wpdb->prefix . SimpleHistory::DBTABLE,
		strtotime("-$period_days days")
	);
	
	return $wpdb->get_var($sql);

}



// Overview, larger text
echo "<p class='SimpleHistoryStats__intro'>";
printf(
	__('<b>%1$s rows</b> have been logged the last <b>%2$s days</b>', "simple-history"),
	get_num_rows_last_n_days($period_days),
	$period_days
);
echo "</p>";

?>
<!-- Start charts wrap -->
<div class='SimpleHistoryStats__graphs'>

<?php

echo "<div class='SimpleHistoryStats__graph SimpleHistoryStats__graph--rowsPerDay'>";
echo "<h4 class=''>";
echo __("Rows per day", "simple-history");
echo "</h4>";

$sql = sprintf(
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
	strtotime("-$period_days days")
);

$dates = $wpdb->get_results( $sql );

echo '<div class="ct-chart ct-minor-seventh SimpleHistoryChart__rowsPerDay"></div>';

// Loop from $period_start_date to $period_end_date
$interval = DateInterval::createFromDateString('1 day');
$period = new DatePeriod($period_start_date, $interval, $period_end_date->add( date_interval_create_from_date_string('1 days') ) );
$str_js_chart_labels = "";
$str_js_chart_data = "";

foreach ( $period as $dt ) {
	
	$datef = _x( 'M j', "stats: date in rows per day chart", "simple-history" );
	$str_date = date_i18n( $datef, $dt->getTimestamp() );
	$str_js_chart_labels .= sprintf(
		'"%1$s",', 
		$str_date
	);

	// Get data for this day, if exist
	// Day in object is in format '2014-09-07'
	$day_data = wp_filter_object_list( $dates, array("yearDate" => $dt->format( "Y-m-d" )) );
	$day_data_value = 0;
	if ($day_data) {
		$day_data_value = (int) current($day_data)->count;
	}

	$str_js_chart_data .= sprintf(
		'%1$s,',
		$day_data_value
	);

}
$str_js_chart_labels = rtrim($str_js_chart_labels, ",");
$str_js_chart_data = rtrim($str_js_chart_data, ",");

?>

<script>
	
	/**
	 * Bar chart with rows per day
	 */
	jQuery(function($) {
		
		var data = {
			// A labels array that can contain any sort of values
			labels: [<?php echo $str_js_chart_labels ?>],
			// Our series array that contains series objects or in this case series data arrays
			series: [
				[<?php echo $str_js_chart_data ?>]
			]
		};
		
		var options = {
			// the name of the dates at bottom
			axisX: {
				// If the axis grid should be drawn or not
				showGrid: false,
				// Interpolation function that allows you to intercept the value from the axis label
				labelInterpolationFnc: function(value, i) {

					// If it's the last value then always show
					if (i === data.series[0].length-1) {
						return value;
					}

					// only return every n value
					if ( i % 7 ) {
						return "";
					}
					
					return value;
				}
			}
		};

		Chartist.Bar(".SimpleHistoryChart__rowsPerDay", data, options);

	});

</script>
<?php

echo "</div>";
// end bar chart rows per day

echo "<div class='SimpleHistoryStats__graph SimpleHistoryStats__graph--loggersPie'>";

echo "<h4 class=''>";
echo __("Loggers", "simple-history");
echo "</h4>";

echo '<div class="ct-chart ct-minor-seventh SimpleHistoryChart__loggersPie"></div>';

$arr_logger_slugs = array();
foreach ( $this->getInstantiatedLoggers() as $oneLogger ) {
	$arr_logger_slugs[] = $oneLogger["instance"]->slug;
}

$sql_logger_counts = sprintf('
	SELECT logger, count(id) as count
	FROM %1$s
	WHERE logger IN ("%2$s")
	GROUP BY logger
	ORDER BY count DESC
', $table_name, join($arr_logger_slugs, '","'));

$logger_rows_count = $wpdb->get_results( $sql_logger_counts );

$str_js_chart_labels = "";
$str_js_chart_data = "";
$str_js_chart_data_chartist = "";
$i = 0;

//shuffle($arr_colors);
$max_loggers_in_chart = sizeof( $arr_colors );

foreach ( $logger_rows_count as $one_logger_count ) {

	$logger = $this->getInstantiatedLoggerBySlug( $one_logger_count->logger );

	if ( ! $logger) {
		continue;
	}

	if ($i > $max_loggers_in_chart) {
		break;
	}

	$logger_info = $logger->getInfo();

	$str_js_chart_data .= sprintf(
		'
			{
				value: %1$d,
				color:"#%3$s",
				label: "%2$s"
			},',
		$one_logger_count->count, // 1
		$logger_info["name"], // 2
		$arr_colors[$i] // 3
	);

	$str_js_chart_data_chartist .= sprintf(
		'%1$d,',
		$one_logger_count->count // 1
	);

	$str_js_chart_labels .= sprintf(
		'"%1$s",',
		$logger_info["name"]
	);

	$i++;

}
$str_js_chart_data = rtrim($str_js_chart_data, ",");
$str_js_chart_data_chartist = rtrim($str_js_chart_data_chartist, ",");
$str_js_chart_labels = rtrim($str_js_chart_labels, ",");

echo "</div>"; // graph loggers pie

?>
<script>
	
	/**
	 * Pie chart with loggers distribution
	 */
	jQuery(function($) {
		
		var data = {
			series: [<?php echo $str_js_chart_data_chartist ?>],
			labels: [<?php echo $str_js_chart_labels ?>]
		};		
		
		var options = {};

		Chartist.Pie(".SimpleHistoryChart__loggersPie", data, options);

	});

</script>
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


?>
<div class='SimpleHistoryStats__graph SimpleHistoryStats__graph--logLevels'>
<?php

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

</div>

<div class='SimpleHistoryStats__graph SimpleHistoryStats__graph--initiators'>

<?php


// Stats based by initiator

// Stats på level (notice, warning, debug, etc.)
$sql = sprintf('
	SELECT 
		initiator,
		count(initiator) as count
	FROM %1$s
	GROUP BY initiator
	ORDER BY count DESC
	', $table_name
);

$level_counts = $wpdb->get_results($sql);

echo "<h3>Initiators</h3>";
echo "<table>";
echo "<tr>
		<th>Initiator</th>
		<th>Count</th>
	</tr>";

foreach ( $level_counts as $row ) {

	if ( empty($row->initiator) ) {
		continue;
	}
		
	printf('
		<tr>
			<td>%1$s</td>
			<td>%2$s</td>
		</tr>
		', 
		$row->initiator, 
		$row->count 
	);

}

echo "</table>";

?>

</div><!-- // end initiators -->


</div><!-- // end charts wrapper -->

<?php

// Output users
echo "<h3>Users that have logged things</h3>";

echo "<p>Deleted users are also included.";

$sql_users = '
	SELECT 
		DISTINCT value as user_id, 
		wp_users.* 
	FROM wp_simple_history_contexts
	LEFT JOIN wp_users ON wp_users.id = wp_simple_history_contexts.value
	WHERE `KEY` = "_user_id"
	GROUP BY value
';

$user_results = $wpdb->get_results($sql_users);

printf('<p>Total %1$s users found.</p>', sizeof( $user_results ));

echo "<table class='' cellpadding=2>";
echo "<tr>
		<th>ID</th>
		<th>login</th>
		<th>email</th>
		<th>logged items</th>
		<th>deleted</th>
	</tr>";

foreach ($user_results as $one_user_result) {
	
	$user_id = $one_user_result->user_id;
	if ( empty( $user_id ) ) {
		continue;
	}

	$str_deleted = empty($one_user_result->user_login) ? "yes" : "";

	// get number of rows this user is responsible for
	if ($user_id) {

		$sql_user_count = sprintf('
			SELECT count(value) as count
			FROM wp_simple_history_contexts
			WHERE `KEY` = "_user_id"
			AND value = %1$s
		', $user_id);

		$user_rows_count = $wpdb->get_var( $sql_user_count );

	}

	printf('
		<tr>
			<td>%1$s</td>
			<td>%2$s</td>
			<td>%3$s</td>
			<td>%5$s</td>
			<td>%4$s</td>
		</tr>
		', 
		$user_id, 
		$one_user_result->user_login, 
		$one_user_result->user_email,
		$str_deleted,
		$user_rows_count
	);

}

echo "</table>";


include(dirname(__FILE__) . "/settings-statsForGeeks.php");

