
<!-- This this will get it's contents populated by JavaScript -->
<div class="SimpleHistoryGui"></div>

<?php

global $wpdb;
$table_name = $wpdb->prefix . SimpleHistory::DBTABLE;

// Output filters
echo "<div class='simple-history-filters'>";
echo "<h2>Filter log</h2>";

// Total number of log rows
$total_num_rows = $wpdb->get_var("select count(*) FROM {$table_name}");
echo "<p>Total $total_num_rows log rows in db</p>";

// Output all loggers
echo "<b>Loggers</b><ul>";
foreach ( $this->instantiatedLoggers as $oneLogger ) {

	// sf_d($oneLogger["name"]);
	$logger_info = $oneLogger["instance"]->getInfo();
	echo "<li>" . $logger_info["name"];
	echo "<br>" . $logger_info["description"];
	echo "</li>";

}
echo "</ul>";

// Output users
echo "<b>Users</b><ul>";
$sql_users = '
	SELECT DISTINCT VALUE, wp_users.* FROM wp_simple_history_contexts
	LEFT JOIN wp_users ON wp_users.id = wp_simple_history_contexts.value
	WHERE `KEY` = "_user_id"
	GROUP BY VALUE
';
$user_results = $wpdb->get_results($sql_users);
foreach ($user_results as $one_user_result) {
	printf('<li>%3$s</li>', $one_user_result->ID, $one_user_result->user_login, $one_user_result->user_email);
}
echo "</ul>";

echo "</div>";
