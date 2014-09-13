<?php

global $wpdb;
$table_name = $wpdb->prefix . SimpleHistory::DBTABLE;

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

// Output all available (instantiated) loggers
echo "<h3>Loggers</h3>";
echo "<p>All instantiated loggers.</p>";

echo "<table class='' cellpadding=2>";
echo "<tr>
		<th>Name</th>
		<th>Description</th>
		<th>Capability</th>
	</tr>";
foreach ( $this->instantiatedLoggers as $oneLogger ) {

	// sf_d($oneLogger["name"]);
	$logger_info = $oneLogger["instance"]->getInfo();
	printf(
		'
		<tr>
			<td>%1$s</td>
			<td>%2$s</td>
			<td>%3$s</td>
		</tr>
		',
		esc_html( $logger_info["name"]),
		esc_html( $logger_info["description"]),
		esc_html( $logger_info["capability"])

	);

}
echo "</table>";


// Output users
echo "<h3>Users that have logged things</h3>";

echo "<ul>";
$sql_users = '
	SELECT 
		DISTINCT value, 
		wp_users.* 
	FROM wp_simple_history_contexts
	LEFT JOIN wp_users ON wp_users.id = wp_simple_history_contexts.value
	WHERE `KEY` = "_user_id"
	GROUP BY value
';
$user_results = $wpdb->get_results($sql_users);
foreach ($user_results as $one_user_result) {
	printf('<li>%3$s</li>', $one_user_result->ID, $one_user_result->user_login, $one_user_result->user_email);
}
echo "</ul>";

echo "</div>";
