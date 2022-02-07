<?php

defined( 'ABSPATH' ) || die();

// Output users
echo '<h3>' . esc_html__( 'Users', 'simple-history' ) . '</h3>';

echo '<p>' . esc_html__( 'Number of logged items for the 5 users with most logged rows.', 'simple-history' ) . '</p>';
echo '<p>' . esc_html__( 'Deleted users are also included.', 'simple-history' ) . '</p>';

$user_results = $wpdb->get_results(
	$wpdb->prepare(
		'
			SELECT 
				DISTINCT value as user_id, 
				%4$s.* 
			FROM %1$s AS c
			LEFT JOIN %2$s ON %3$s.id = c.value
			WHERE c.key = "_user_id"
			GROUP BY c.value
			',
		$wpdb->prefix . SimpleHistory::DBTABLE_CONTEXTS, // 1
		$wpdb->users, // 2
		$wpdb->users, // 3
		$wpdb->users // 4
	)
);

// printf('<p>Total %1$s users found.</p>', sizeof( $user_results ));
echo "<table class='widefat' cellpadding=2>";
echo '<thead><tr>
        <th></th>
        <th>' . __( 'User ID', 'simple-history' ) . '</th>
        <th>' . __( 'Login', 'simple-history' ) . '</th>
        <th>' . __( 'E-mail', 'simple-history' ) . '</th>
        <th>' . __( '# logged items', 'simple-history' ) . '</th>
        <th>' . __( 'Deleted', 'simple-history' ) . '</th>
    </tr></thead>';

	$arr_users = array();
foreach ( $user_results as $one_user_result ) {
	$user_id = $one_user_result->user_id;
	if ( empty( $user_id ) ) {
		continue;
	}

	$str_deleted = empty( $one_user_result->user_login ) ? 'yes' : '';

	// get number of rows this user is responsible for
	if ( $user_id ) {
		$user_rows_count = $wpdb->get_var(
			$wpdb->prepare(
				'
			SELECT count(VALUE) AS count
			FROM %1$s AS c
			INNER JOIN %2$s AS h ON h.id = c.history_id
			WHERE c.key = "_user_id"
			AND c.value = %3$s
			AND UNIX_TIMESTAMP(h.date) >= %4$s
					',
				$wpdb->prefix . SimpleHistory::DBTABLE_CONTEXTS, // 1
				$wpdb->prefix . SimpleHistory::DBTABLE, // 2
				$user_id, // 3
				strtotime( "-$period_days days" ) // 4
			)
		);
	}

	$arr_users[] = array(
		'user_id'         => $user_id,
		'user_login'      => $one_user_result->user_login,
		'user_email'      => $one_user_result->user_email, // 3
		'str_deleted'     => $str_deleted,
		'user_rows_count' => $user_rows_count,
	);
}// End foreach().

// order users by count
usort(
	$arr_users,
	function ( $a, $b ) {
		return (int) ( $a['user_rows_count'] < $b['user_rows_count'] );
	}
);

// only keep the top 10
$arr_users = array_slice( $arr_users, 0, 5 );

$loopnum = 0;
foreach ( $arr_users as $one_user ) {
	printf(
		'
		<tr class="%6$s">
			<td>%7$s</td>
			<td>%1$s</td>
			<td>%2$s</td>
			<td>%3$s</td>
			<td>%5$s</td>
			<td>%4$s</td>
		</tr>
		',
		esc_html( $one_user['user_id'] ),
		esc_html( $one_user['user_login'] ),
		esc_html( $one_user['user_email'] ), // 3
		esc_html( $one_user['str_deleted'] ),
		esc_html( $one_user['user_rows_count'] ),
		$loopnum % 2 ? ' alternate ' : '', // 6
		$this->sh->get_avatar( $one_user['user_email'], 38 ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	);

	$loopnum++;
}

echo '</table>';
