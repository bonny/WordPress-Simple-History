<?php

global $wpdb;
$table_name = $wpdb->prefix . SimpleHistory::DBTABLE;
$table_name_contexts = $wpdb->prefix . SimpleHistory::DBTABLE_CONTEXTS;

$period_days = (int) 28;
$period_start_date = DateTime::createFromFormat('U', strtotime("-$period_days days"));
$period_end_date = DateTime::createFromFormat('U', time());

?>
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

	.SimpleHistoryChart__loggersPie2 {
		width: 100%;
		position: relative;
		/*height: 300px;*/
	}

	.SimpleHistoryChart__loggersPie2__canvasHolder {
		position: relative;
		xbackground: #aaa;
		margin-right: 100px;
	}
	
	.SimpleHistoryChart__loggersPie2__canvas {
		xposition: absolute;
		xtop: 0;
		xright: 0;
	}

	.SimpleHistoryChart__loggersPie2 ul {
		position: absolute;
		top: 0;
		right: 0;
	}

	.SimpleHistoryChart__loggersPie2 span {
		width: 10px;
		height: 10px;
		display: inline-block;
		margin-right: 5px;
	}

	.ct-chart .ct-bar {
		stroke-width: 15px;
		box-shadow: 1px 1px 1px black;
	}

	.ct-chart .ct-series.ct-series-d .ct-slice:not(.ct-donut) {
		fill: #666;
	}

	.ct-chart .ct-series.ct-series-e .ct-slice:not(.ct-donut) {
		fill: #999;
	}

	.ct-chart .ct-series.ct-series-f .ct-slice:not(.ct-donut) {
		fill: blue;
	}

	.ct-chart .ct-series.ct-series-g .ct-slice:not(.ct-donut) {
		fill: yellow;
	}

	.ct-chart .ct-series.ct-series-h .ct-slice:not(.ct-donut) {
		fill: red;
	}

	.ct-chart .ct-series.ct-series-i .ct-slice:not(.ct-donut) {
		fill: pink;
	}

	.ct-chart .ct-series.ct-series-j .ct-slice:not(.ct-donut) {
		fill: green;
	}

	.ct-chart .ct-series.ct-series-k .ct-slice:not(.ct-donut) {
		fill: purple;
	}


</style>
<?php

// Output filters
echo "<div class='simple-history-filters'>";

// echo "<h2>Statistics</h2>";


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

// Bar chart with rows per day
echo "<div class='SimpleHistoryStats__graphs'>";

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

#echo '<div class="ct-chart ct-minor-seventh SimpleHistoryChart__loggersPie"></div>';
?>
<div class="SimpleHistoryChart__loggersPie2">
	<div class="SimpleHistoryChart__loggersPie2__canvasHolder">
		<canvas class="SimpleHistoryChart__loggersPie2__canvas"></canvas>
	</div>
</div>
<?php

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
$i = 0;
$max_loggers_in_chart = 10;
$arr_pie_colors = array(
	0 => array(
		"color" => "#F7464A",
		"highlight" => "#FF5A5E"
	),
	1 => array(
        "color" => "#46BFBD",
        "highlight" => "#5AD3D1"
    ),
    2 => array(
    	"color" => "#FDB45C",
		"highlight" => "#FFC870"
	)
);

foreach ( $logger_rows_count as $one_logger_count ) {

	$logger = $this->getInstantiatedLoggerBySlug( $one_logger_count->logger );
	if ( ! $logger) {
		continue;
	}

	if ($i > $max_loggers_in_chart) {
		break;
	}

	$logger_info = $logger->getInfo();

	$color = $arr_pie_colors[$i];
	
	$str_js_chart_data .= sprintf(
		'
			{
				value: %1$d,
				color:"%3$s",
				xhighlight: "%4$s",
				label: "%2$s"
			},',
		$one_logger_count->count,
		$logger_info["name"],
		$color["color"],
		$color["highlight"]
	);

	$i++;

}
$str_js_chart_data = rtrim($str_js_chart_data, ",");
$str_js_chart_labels = rtrim($str_js_chart_labels, ",");

echo "</div>"; // graph loggers pie

?>
<script>
	
	/**
	 * Pie chart with loggers distribution
	 */
	jQuery(function($) {
		
		/*
		var data = {
			series: [<?php echo $str_js_chart_data ?>],
			labels: [<?php echo $str_js_chart_labels ?>]
		};		
		
		var options = {
			//chartPadding: 0,
			//labelOffset: 100,
			//labelDirection: 'explode'
		};

		Chartist.Pie(".SimpleHistoryChart__loggersPie", data, options);
		*/

		var ctx = $(".SimpleHistoryChart__loggersPie2 canvas").get(0).getContext("2d");

		var data = [
			<?php echo $str_js_chart_data; ?>
		];

		var options = {
		    //Boolean - Whether we should show a stroke on each segment
		    segmentShowStroke : true,

		    //String - The colour of each segment stroke
		    segmentStrokeColor : "#fff",

		    //Number - The width of each segment stroke
		    segmentStrokeWidth : 1,

		    responsive: true,

		    //Number - The percentage of the chart that we cut out of the middle
		    //percentageInnerCutout : 50,

		    //Number - Amount of animation steps
		    animationSteps : 25,

		    //String - Animation easing effect
		    animationEasing : "easeOutExpo",

		    //Boolean - Whether we animate the rotation of the Doughnut
		    animateRotate : true,

		    //Boolean - Whether we animate scaling the Doughnut from the centre
		    animateScale : true,

	        scaleShowLabels: true,

		    //String - A legend template
		    legendTemplate : "<ul class=\"<%=name.toLowerCase()%>-legend\"><% for (var i=0; i<segments.length; i++){%><li><span style=\"background-color:<%=segments[i].fillColor%>\"></span><%if(segments[i].label){%><%=segments[i].label%><%}%></li><%}%></ul>"

		}

		var myPieChart = new Chart(ctx).Pie(data, options);
		var legendHTML = myPieChart.generateLegend();

		$(".SimpleHistoryChart__loggersPie2").append(legendHTML);

	});

</script>
<?php

echo "</div>"; // wrap charts


echo "<hr>";
echo "<h3>Database size + rows count</h3>";
$logQuery = new SimpleHistoryLogQuery();
$rows = $logQuery->query(array(
	"posts_per_page" => 1,
	"date_from" => strtotime("-$period_days days")
));

// This is the number of rows with occasions taken into consideration
$total_accassions_rows_count = $rows["total_row_count"];

// Total number of log rows
// Not caring about occasions, this number = all occasions
$total_num_rows = $wpdb->get_var("select count(*) FROM {$table_name}");
echo "<p>Total $total_num_rows log rows in db.</p>";
echo "<p>Total $total_accassions_rows_count rows, when grouped by occasion id.</p>";

$sql_table_size = sprintf('
	SELECT table_name AS "table_name", 
	round(((data_length + index_length) / 1024 / 1024), 2) "size_in_mb" 
	FROM information_schema.TABLES 
	WHERE table_schema = "%1$s"
	AND table_name IN ("%2$s", "%3$s");
	', 
	DB_NAME, // 1
	$table_name, // 2
	$table_name_contexts
);

$table_size_result = $wpdb->get_results($sql_table_size);

echo "<table>";
echo "<tr>
	<th>Table name</th>
	<th>Table size (MB)</th>
</tr>";

foreach ($table_size_result as $one_table) {

	printf('<tr>
			<td>%1$s</td>
			<td>%2$s</td>
		</tr>',
		$one_table->table_name,
		$one_table->size_in_mb
	);
}

echo "</table>";


// Output all available (instantiated) loggers
// @TODO: order by number of rows
echo "<h3>Loggers</h3>";
echo "<p>All instantiated loggers.</p>";

echo "<table class='' cellpadding=2>";
echo "<tr>
		<th>Count</th>
		<th>Slug</th>
		<th>Name</th>
		<th>Description</th>
		<th>Capability</th>
	</tr>";


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

foreach ( $logger_rows_count as $one_logger_count ) {

	$logger = $this->getInstantiatedLoggerBySlug( $one_logger_count->logger );
	if (!$logger) {
		continue;
	}
	#sf_d($logger);
	$logger_info = $logger->getInfo();

	printf(
		'
		<tr>
			<td>%1$s</td>
			<td>%2$s</td>
			<td>%3$s</td>
			<td>%4$s</td>
			<td>%5$s</td>
		</tr>
		',
		$one_logger_count->count,
		$one_logger_count->logger,
		esc_html( $logger_info["name"]),
		esc_html( $logger_info["description"]),
		esc_html( $logger_info["capability"])
	);

}
echo "</table>";

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

foreach ( $level_counts as $row ) {
		
		printf('
		<tr>
			<td>%1$s</td>
			<td>%2$s</td>
		</tr>
		', 
		$row->level, 
		$row->count 
	);

}

echo "</table>";

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

echo "</div>"; // div.simple-history-filters
