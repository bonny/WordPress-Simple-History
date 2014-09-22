<?php

defined('ABSPATH') OR exit;

echo "<hr>";
echo "<h3>Stats for geeks</h3>";
echo "<h4>Database size + rows count</h4>";
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

echo "<h4>Loggers</h4>";
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
