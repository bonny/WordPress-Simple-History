<?php
defined( 'ABSPATH' ) or die();

global $wpdb;

$table_name = $wpdb->prefix . SimpleHistory::DBTABLE;
$table_name_contexts = $wpdb->prefix . SimpleHistory::DBTABLE_CONTEXTS;

$period_days = (int) 14;
$period_start_date = DateTime::createFromFormat( 'U', strtotime( "-$period_days days" ) );
$period_end_date = DateTime::createFromFormat( 'U', time() );

/**
 * Size of database in both number or rows and table size
 */

echo "<h3>Database size</h3>";

// Get table sizes in mb
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

// Get num of rows for each table
$total_num_rows_table = (int) $wpdb->get_var("select count(*) FROM {$table_name}");
$total_num_rows_table_contexts = (int) $wpdb->get_var("select count(*) FROM {$table_name_contexts}");

$table_size_result[0]->num_rows = $total_num_rows_table;
$table_size_result[1]->num_rows = $total_num_rows_table_contexts;

echo "<table class='widefat'>";
echo "
	<thead>
		<tr>
			<th>Name</th>
			<th>Size</th>
			<th>Rows</th>
		</tr>
	</thead>
";

$loopnum = 0;
foreach ($table_size_result as $one_table) {

	printf('<tr class="%4$s">
			<td>%1$s</td>
			<td>%2$s MB</td>
			<td>%3$s rows</td>
		</tr>',
		$one_table->table_name,
		$one_table->size_in_mb,
		number_format_i18n( $one_table->num_rows, 0 ),
		$loopnum % 2 ? " alt " : ""
	);

	$loopnum++;
}

echo "</table>";

$logQuery = new SimpleHistoryLogQuery();
$rows = $logQuery->query(array(
	"posts_per_page" => 1,
));

// This is the number of rows with occasions taken into consideration
$total_accassions_rows_count = $rows["total_row_count"];

echo "<p>Total $total_accassions_rows_count rows, when grouped by occasion id.</p>";


# echo "<h4>Clear history interval</h4>";
# echo "<p>" . $this->sh->get_clear_history_interval() . "</p>";


/**
 * Output a list of all active loggers, including name, slug, comment, message, capability and number of rows
 */

$arr_logger_slugs = array();

foreach ( $this->sh->getInstantiatedLoggers() as $oneLogger ) {
    $arr_logger_slugs[] = $oneLogger["instance"]->slug;
}

$sql_logger_counts = sprintf('
    SELECT logger, count(id) as count
    FROM %1$s
    WHERE logger IN ("%2$s")
    GROUP BY logger
    ORDER BY count DESC
', $table_name, join( $arr_logger_slugs, '","') );
$logger_rows_count = $wpdb->get_results( $sql_logger_counts, OBJECT_K );

dd($logger_rows_count);

echo "<h3>Loggers</h3>";

echo "<p>There are " . sizeof( $arr_logger_slugs ) . " instantiated loggers.</p>";

echo "<table class='widefat fixed' cellpadding=2>";
echo "
	<thead>
		<tr>
			<th>Name + Slug</th>
			<th>Description</th>
			<th>Messages</th>
			<th>Capability</th>
			<th>Rows count</th>
		</tr>
	</thead>
";

$loopnum = 0;

foreach ( $arr_logger_slugs as $one_logger_slug ) {

	$logger = $this->sh->getInstantiatedLoggerBySlug( $one_logger_slug );

	if ( ! $logger ) {
		continue;
	}

	if ( isset( $logger_rows_count[ $one_logger_slug ] ) ) {
		$one_logger_count = $logger_rows_count[ $one_logger_slug ];
	} else {
		// logger was not is sql result, so fake result
		$one_logger_count = new stdclass;
		$one_logger_count->count = 0;
	}

	$logger_info = $logger->getInfo();
	$logger_messages = isset( $logger_info["messages"] ) ? (array) $logger_info["messages"] : array();

	$html_logger_messages = "";

	foreach ( $logger_messages as $message_key => $message ) {
		$html_logger_messages .= sprintf('<li>%1$s</li>', esc_html($message));
	}

    if ( $html_logger_messages ) {

		$html_logger_messages = sprintf('
                <p>%2$s message strings</p>
                <ul class="hide-if-js">
                    %1$s
                </ul>
            ',
            $html_logger_messages, // 1
            sizeof( $logger_messages ) // 2
        );

	} else {
        $html_logger_messages = "<p>No message strings</p>";
    }

	printf(
		'
		<tr class="%6$s">
			<td>
				<p><strong>%3$s</strong>
				<br><code>%2$s</code></p>
			</td>
			<td><p>%4$s</p></td>
			<td>%7$s</td>
			<td><p>%5$s</p></td>
			<td><p>%1$s</p></td>
		</tr>
		',
		$one_logger_count->count,
		$one_logger_slug,
		esc_html( $logger_info["name"]),
		esc_html( $logger_info["description"]), // 4
		esc_html( $logger->getCapability() ),
		$loopnum % 2 ? " alt " : "", // 6
		$html_logger_messages // 7
	);

	$loopnum++;

}

echo "</table>";
