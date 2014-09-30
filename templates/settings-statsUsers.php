<?php

// Output users
echo "<h3>Users</h3>";

echo "<p>Number of logged items for the 5 users with most logged rows.</p>";
echo "<p>Deleted users are also included.</p>";

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
#sf_d($user_results);
#printf('<p>Total %1$s users found.</p>', sizeof( $user_results ));

echo "<table class='widefat' cellpadding=2>";
echo "<thead><tr>
		<th></th>
		<th>User ID</th>
		<th>login</th>
		<th>email</th>
		<th>logged items</th>
		<th>deleted</th>
	</tr></thead>";

	$arr_users = array();
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

	$arr_users[] = array(
		"user_id" => $user_id, 
		"user_login" => $one_user_result->user_login, 
		"user_email" => $one_user_result->user_email, // 3
		"str_deleted" => $str_deleted,
		"user_rows_count" => $user_rows_count
	);

}

// order users by count
usort($arr_users, function($a, $b) {
	return $a["user_rows_count"] < $b["user_rows_count"];
});

// only keep the top 10
$arr_users = array_slice($arr_users, 0, 5);

$loopnum = 0;
foreach ($arr_users as $one_user) {

	printf('
		<tr class="%6$s">
			<td>%7$s</td>
			<td>%1$s</td>
			<td>%2$s</td>
			<td>%3$s</td>
			<td>%5$s</td>
			<td>%4$s</td>
		</tr>
		', 
		$one_user["user_id"], 
		$one_user["user_login"], 
		$one_user["user_email"], // 3
		$one_user["str_deleted"],
		$one_user["user_rows_count"],
		$loopnum % 2 ? " alternate " : "", // 6
		$this->sh->get_avatar( $one_user["user_email"], 38 ) // 7
	);

	$loopnum++;

}

echo "</table>";

