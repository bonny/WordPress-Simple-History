<?php

namespace Simple_History;

/**
 * @var array{
 *      instantiated_loggers:array,
 *      instantiated_dropins:array,
 *      instantiated_services:array,
 *      events_table_name:string,
 *      simple_history_instance:Simple_History,
 *      wpdb:\wpdb
 * } $args
 **/

defined( 'ABSPATH' ) || die();

/**
 * Check that required tables exists.
 * Some users have had issues with tables not being created after
 * moving site from one server to another. Probably because the history tables
 * are not moved with the rest of the database, but the options table are and that
 * confuses Simple History.
 */
$tables_info = Helpers::required_tables_exist();

foreach ( $tables_info as $table_info ) {
	if ( ! $table_info['table_exists'] ) {
		echo '<div class="notice notice-error">';
		echo '<p>';
		printf(
			/* translators: %s table name. */
			esc_html_x( 'Required table "%s" does not exist.', 'debug dropin', 'simple-history' ),
			esc_html( $table_info['table_name'] )
		);
		echo '</p>';
		echo '</div>';
	}
}

/**
 * Size of database in both number or rows and table size
 */

echo '<h3>' . esc_html_x( 'Database size', 'debug dropin', 'simple-history' ) . '</h3>';

$table_size_result = Helpers::get_db_table_stats();

echo "<table class='widefat striped'>";
printf(
	'<thead>
		<tr>
			<th>%1$s</th>
			<th>%2$s</th>
			<th>%3$s</th>
		</tr>
	</thead>
	',
	esc_html_x( 'Table name', 'debug dropin', 'simple-history' ),
	esc_html_x( 'Size', 'debug dropin', 'simple-history' ),
	esc_html_x( 'Rows', 'debug dropin', 'simple-history' )
);

if ( sizeof( $table_size_result ) === 0 ) {
	echo '<tr><td colspan="3">';
	echo esc_html_x( 'No tables found.', 'debug dropin', 'simple-history' );
	echo '</td></tr>';
} else {
	foreach ( $table_size_result as $one_table ) {
		/* translators: %s size in mb. */
		$size = sprintf( _x( '%s MB', 'debug dropin', 'simple-history' ), $one_table->size_in_mb );

		/* translators: %s number of rows. */
		$rows = sprintf( _x( '%s rows', 'debug dropin', 'simple-history' ), number_format_i18n( $one_table->num_rows, 0 ) );

		printf(
			'<tr>
				<td>%1$s</td>
				<td>%2$s</td>
				<td>%3$s</td>
			</tr>',
			esc_html( $one_table->table_name ),
			esc_html( $size ),
			esc_html( $rows ),
		);
	}
}

echo '</table>';

$logQuery = new Log_Query();
$rows = $logQuery->query(
	array(
		'posts_per_page' => 1,
	)
);

// This is the number of rows with occasions taken into consideration
$total_accassions_rows_count = $rows['total_row_count'];

echo '<p>';
printf(
	/* translators: %d number of rows. */
	esc_html_x( 'Total %s rows, when grouped by occasion id.', 'debug dropin', 'simple-history' ),
	esc_html( $total_accassions_rows_count )
);
echo '</p>';

// List services.
echo '<h3>' . esc_html_x( 'Services', 'debug dropin', 'simple-history' ) . '</h3>';

echo '<p>';
printf(
	/* translators: %d number of dropins loaded. */
	esc_html_x( '%1$d services loaded.', 'debug dropin', 'simple-history' ),
	esc_html( count( $args['instantiated_services'] ) ) // 1
);
echo '</p>';

echo "<table class='widefat striped' cellpadding=2>";
printf(
	'
	<thead>
		<tr>
			<th>%1$s</th>
			<th>%2$s</th>
		</tr>
	</thead>
	',
	esc_html_x( 'Short name', 'debug dropin', 'simple-history' ),
	esc_html_x( 'Namespaced name', 'debug dropin', 'simple-history' ),
);

foreach ( $args['instantiated_services'] as $one_service ) {
	printf(
		'
		<tr>
            <td>
                <code>%1$s</code>
            </td>
            <td>
                <code>%2$s</code>
            </td>
		</tr>
		',
		esc_html( $one_service->get_slug() ), // 1 slug
		esc_html( get_class( $one_service ) ), // 2 full namespace and class name
	);
}

echo '</table>';

// List dropins.
echo '<h3>' . esc_html_x( 'Dropins', 'debug dropin', 'simple-history' ) . '</h3>';

echo '<p>';
printf(
	/* translators: %d number of dropins loaded. */
	esc_html_x( '%1$d dropins loaded.', 'debug dropin', 'simple-history' ),
	esc_html( count( $args['instantiated_dropins'] ) ) // 1
);
echo '</p>';

echo "<table class='widefat striped' cellpadding=2>";
printf(
	'
	<thead>
		<tr>
			<th>%1$s</th>
			<th>%2$s</th>
		</tr>
	</thead>
	',
	esc_html_x( 'Short name', 'debug dropin', 'simple-history' ),
	esc_html_x( 'Namespaced name', 'debug dropin', 'simple-history' ),
);

foreach ( $args['instantiated_dropins'] as $oneDropin ) {
	// Older Dropins does not have a get_slug method.
	$dropin_slug = method_exists( $oneDropin['instance'], 'get_slug' )
		? $oneDropin['instance']->get_slug()
		: Helpers::get_class_short_name( $oneDropin['instance'] );

	printf(
		'
		<tr>
            <td>
                <code>%1$s</code>
            </td>
            <td>
                <code>%2$s</code>
            </td>
		</tr>
		',
		esc_html( $dropin_slug ),
		esc_html( get_class( $oneDropin['instance'] ) ),
	);
}

echo '</table>';

// echo "<h4>Clear history interval</h4>";
// echo "<p>" . $this->simple_history->get_clear_history_interval() . "</p>";
/**
 * Output a list of all active loggers, including name, slug, comment, message, capability and number of rows
 * Retrieve them in order by the number of rows they have in the db
 * Loggers with 0 rows in the db will not be included in the array, so we need to find those
 * and add them manually last
 */
$arr_logger_slugs = array();
foreach ( $args['instantiated_loggers'] as $oneLogger ) {
	$arr_logger_slugs[] = esc_sql( $oneLogger['instance']->get_slug() );
}

$sql_logger_counts = sprintf(
	'
	SELECT logger, count(id) as count
	FROM %1$s
	WHERE logger IN ("%2$s")
	GROUP BY logger
	ORDER BY count DESC
',
	$args['events_table_name'],
	join( '","', $arr_logger_slugs )
);

// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
$logger_rows_count = $args['wpdb']->get_results( $sql_logger_counts, OBJECT_K );

// Find loggers with no rows in db and append to array.
$missing_logger_slugs = array_diff( $arr_logger_slugs, array_keys( $logger_rows_count ) );

foreach ( $missing_logger_slugs as $one_missing_logger_slug ) {
	$logger_rows_count[ $one_missing_logger_slug ] = (object) array(
		'logger' => $one_missing_logger_slug,
		'count' => 0,
	);
}

echo '<h3>';
echo esc_html_x( 'Loggers', 'debug dropin', 'simple-history' );
echo '</h3>';

echo '<p>';
printf(
	/* translators: %d number of loggers. */
	esc_html_x( 'Listing %1$d loggers, ordered by rows count in database.', 'debug dropin', 'simple-history' ),
	esc_html( count( $arr_logger_slugs ) ) // 1
);
echo '</p>';

echo "<table class='widefat fixed striped' cellpadding=2>";
printf(
	'
	<thead>
		<tr>
			<th>%1$s</th>
			<th>%2$s</th>
			<th>%3$s</th>
			<th>%4$s</th>
			<th>%5$s</th>
			<th>%6$s</th>
		</tr>
	</thead>
	',
	esc_html_x( 'Logger name', 'debug dropin', 'simple-history' ),
	esc_html_x( 'Slug', 'debug dropin', 'simple-history' ),
	esc_html_x( 'Description', 'debug dropin', 'simple-history' ),
	esc_html_x( 'Messages', 'debug dropin', 'simple-history' ),
	esc_html_x( 'Capability', 'debug dropin', 'simple-history' ),
	esc_html_x( 'Rows count', 'debug dropin', 'simple-history' )
);

$loopnum = 0;

foreach ( $logger_rows_count as $one_logger_slug => $one_logger_val ) {
	/** @var Loggers\Logger $logger */
	$logger = $args['simple_history_instance']->get_instantiated_logger_by_slug( $one_logger_slug );

	if ( ! $logger ) {
		continue;
	}

	if ( isset( $logger_rows_count[ $one_logger_slug ] ) ) {
		$one_logger_count = $logger_rows_count[ $one_logger_slug ];
	} else {
		// logger was not is sql result, so fake result
		$one_logger_count = new \stdClass();
		$one_logger_count->count = 0;
	}

	$logger_info = $logger->get_info();
	$logger_messages = isset( $logger_info['messages'] ) ? (array) $logger_info['messages'] : array();

	$html_logger_messages = '';

	foreach ( $logger_messages as $message ) {
		$html_logger_messages .= sprintf( '<li>%1$s</li>', esc_html( $message ) );
	}
	if ( $html_logger_messages ) {
		$str_num_message_strings = sprintf(
			/* translators: %d number of message strings. */
			esc_html_x( '%1$s message strings', 'debug dropin', 'simple-history' ),
			esc_html( count( $logger_messages ) )
		);

		$html_logger_messages = sprintf(
			'
			<details>
				<summary>%1$s</summary>
				<ul>
					%2$s
				</ul>
			</details>
            ',
			esc_html( $str_num_message_strings ), // 1
			$html_logger_messages // 2
		);
	} else {
		$html_logger_messages = '<p>' . esc_html_x( 'No message strings', 'debug dropin', 'simple-history' ) . '</p>';
	}

	printf(
		'
		<tr class="%6$s">
			<td>
				<p><strong>%3$s</strong>
			</td>
            <td>
                <p><code>%2$s</code></p>
            </td>
			<td>
                <p>%4$s</p>
            </td>
			<td>
                %7$s
            </td>
			<td>
                <p>%5$s</p>
            </td>
			<td>
                <p>%1$s</p>
            </td>
		</tr>
		',
		number_format_i18n( $one_logger_count->count ),
		esc_html( $one_logger_slug ), // 2
		esc_html( $logger_info['name'] ),
		esc_html( $logger_info['description'] ), // 4
		esc_html( $logger->get_capability() ), // 5
		$loopnum % 2 ? ' alt ' : '', // 6
		$html_logger_messages // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	);

	$loopnum++;
} // End foreach().

echo '</table>';

// List installed plugins
echo '<h2>' . esc_html_x( 'Plugins', 'debug dropin', 'simple-history' ) . '</h2>';

echo '<p>' . esc_html_x( 'As returned from get_plugins().', 'debug dropin', 'simple-history' ) . '</p>';

$all_plugins = get_plugins();

echo "<table class='widefat striped'>";
printf(
	'<thead>
        <tr>
            <th>%1$s</th>
            <th>%2$s</th>
            <th>%3$s</th>
        </tr>
    </thead>
    ',
	esc_html_x( 'Plugin name', 'debug dropin', 'simple-history' ),
	esc_html_x( 'Plugin file path', 'debug dropin', 'simple-history' ),
	esc_html_x( 'Active', 'debug dropin', 'simple-history' )
);

foreach ( $all_plugins as $pluginFilePath => $onePlugin ) {
	$isPluginActive = Helpers::is_plugin_active( $pluginFilePath );

	printf(
		'
        <tr>
            <td><strong>%1$s</strong></td>
            <td>%2$s</td>
            <td>%3$s</td>
        </tr>
        ',
		esc_html( $onePlugin['Name'] ),
		esc_html( $pluginFilePath ),
		$isPluginActive ? esc_html_x( 'Yes', 'debug dropin', 'simple-history' ) : esc_html_x( 'No', 'debug dropin', 'simple-history' )
		// 3
	);
}

echo '</table>';
