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

