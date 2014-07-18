<?php

global $wpdb;
$table_name = $wpdb->prefix . SimpleHistory::DBTABLE;

// Get nn latest log entries
$logQuery = new SimpleHistoryLogQuery();
$logRows = $logQuery->query(array(
	"posts_per_page" => 50
));

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

// Output items
echo "<ul class='simple-history-logitems'>";
foreach ($logRows as $oneLogRow) {
	
	$header_html = $this->getLogRowHeaderOutput($oneLogRow);	
	$plain_text_html = $this->getLogRowPlainTextOutput($oneLogRow);
	$sender_image_html = $this->getLogRowSenderImageOutput($oneLogRow);
	
	$details_html = trim( $this->getLogRowDetailsOutput($oneLogRow) );

	if ($details_html) {

		$details_html = sprintf(
			'<div class="simple-history-logitem__details">%1$s</div>',
			$details_html
		);

	}

	// subsequentOccasions = including the current one
	$occasions_count = $oneLogRow->subsequentOccasions - 1;
	$occasions_html = "";
	if ($occasions_count > 0) {
		$occasions_html = sprintf(
			'
			<div class="simple-history-logitem__occasions">
				%1$s more occasions
			</div>
			',
			$occasions_count
		);
	}

	printf(
		'
			<li class="simple-history-logitem simple-history-logitem--loglevel-%5$s simple-history-logitem--logger-%7$s">
				<div class="simple-history-logitem__firstcol">
					<div class="simple-history-logitem__senderImage">%3$s</div>
				</div>
				<div class="simple-history-logitem__secondcol">
					<div class="simple-history-logitem__header">%1$s</div>
					<div class="simple-history-logitem__text">%2$s</div>
					%4$s
					%6$s
				</div>
			</li>
		',
		$header_html, // 1
		$plain_text_html, // 2
		$sender_image_html, // 3
		$occasions_html, // 4
		$oneLogRow->level, // 5
		$details_html, // 6
		$oneLogRow->logger // 7
	);

	// Get the main message row.
	// Should be as plain as possible, like plain text 
	// but with links to for example users and posts
	#SimpleLoggerFormatter::getRowTextOutput($oneLogRow);

	// Get detailed HTML-based output
	// May include images, lists, any cool stuff needed to view
	#SimpleLoggerFormatter::getRowHTMLOutput($oneLogRow);

}
echo "</ul>";
