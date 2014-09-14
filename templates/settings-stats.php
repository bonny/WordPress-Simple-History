<?php

global $wpdb;
$table_name = $wpdb->prefix . SimpleHistory::DBTABLE;
$table_name_contexts = $wpdb->prefix . SimpleHistory::DBTABLE_CONTEXTS;

// Output filters
echo "<div class='simple-history-filters'>";

echo "<h2>Statistics</h2>";

echo "<h3>Database size + rows count</h3>";
$logQuery = new SimpleHistoryLogQuery();
$rows = $logQuery->query(array(
	"posts_per_page" => 1
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
echo "<h3>Loggers</h3>";
echo "<p>All instantiated loggers.</p>";

echo "<table class='' cellpadding=2>";
echo "<tr>
		<th>Name</th>
		<th>Description</th>
		<th>Capability</th>
		<th>Rows</th>
	</tr>";
foreach ( $this->instantiatedLoggers as $oneLogger ) {

	$logger_info = $oneLogger["instance"]->getInfo();

	// get number of rows this logger is responsible for
	$sql_logger_count = sprintf('
		SELECT count(id) as count
		FROM %1$s
		WHERE logger = "%2$s"
	', $table_name, $oneLogger["instance"]->slug);

	$logger_rows_count = $wpdb->get_var( $sql_logger_count );

	printf(
		'
		<tr>
			<td>%1$s</td>
			<td>%2$s</td>
			<td>%3$s</td>
			<td>%4$s</td>
		</tr>
		',
		esc_html( $logger_info["name"]),
		esc_html( $logger_info["description"]),
		esc_html( $logger_info["capability"]),
		$logger_rows_count

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
