<?php
defined('ABSPATH') or die();

echo "<h4 class=''>";
echo __('Loggers', 'simple-history');
echo '</h4>';

// echo '<div class="SimpleHistoryChart__loggersPie"></div>';
echo '<div class="SimpleHistoryChart__loggersPieGoogleChart"></div>';
// echo '<div class="SimpleHistoryChart__loggersGoogleBarChart"></div>';
$arr_logger_slugs = array();
foreach ($this->sh->getInstantiatedLoggers() as $oneLogger) {
    $arr_logger_slugs[] = $oneLogger['instance']->slug;
}

$sql_logger_counts = sprintf(
    '
	SELECT logger, count(id) as count
	FROM %1$s
	WHERE
		logger IN ("%2$s")
		AND UNIX_TIMESTAMP(date) >= %3$d
	GROUP BY logger
	ORDER BY count DESC
	',
    $table_name, // 1
    join('","', $arr_logger_slugs), // 2
    strtotime("-$period_days days")
);

$logger_rows_count = $wpdb->get_results($sql_logger_counts);
// sf_d($logger_rows_count);
$str_js_chart_labels = '';
$str_js_chart_data = '';
$str_js_chart_data_chartist = '';
$str_js_google_chart_data = "['Logger name', 'Logged rows'],";
$i = 0;

// shuffle($arr_colors);
$max_loggers_in_chart = sizeof($arr_colors);

foreach ($logger_rows_count as $one_logger_count) {
    $logger = $this->sh->getInstantiatedLoggerBySlug($one_logger_count->logger);

    if (!$logger) {
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
        $logger_info['name'], // 2
        $arr_colors[$i] // 3
    );

    $str_js_chart_data_chartist .= sprintf(
        '%1$d,',
        $one_logger_count->count // 1
    );

    $str_js_chart_labels .= sprintf('"%1$s",', $logger_info['name']);

    $str_js_google_chart_data .= sprintf(
        '["%1$s", %2$d], ',
        $logger_info['name'], // 1
        $one_logger_count->count // 2
    );

    $i++;
} // End foreach().
$str_js_chart_data = rtrim($str_js_chart_data, ',');
$str_js_chart_data_chartist = rtrim($str_js_chart_data_chartist, ',');
$str_js_chart_labels = rtrim($str_js_chart_labels, ',');
$str_js_google_chart_data = rtrim($str_js_google_chart_data, ',');
?>
<script>

    /**
     * Pie chart with loggers distribution
     */
    jQuery(function($) {

        /*
        var data = {
            series: [<?php echo $str_js_chart_data_chartist; ?>],
            labels: [<?php echo $str_js_chart_labels; ?>]
        };

        var options = {};

        Chartist.Pie(".SimpleHistoryChart__loggersPie", data, options);
        */

        var data = google.visualization.arrayToDataTable([
            <?php echo $str_js_google_chart_data; ?>
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

        var chart = new google.visualization.PieChart( $(".SimpleHistoryChart__loggersPieGoogleChart").get(0) );
        chart.draw(data, options);

        //var chart2 = new google.visualization.BarChart( $(".SimpleHistoryChart__loggersGoogleBarChart").get(0) );
        //chart2.draw(data, options);

    });

</script>
