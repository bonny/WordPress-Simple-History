<?php
/**
 * Undocumented class
 *
 * @package SimpleHistory
 **/

defined('ABSPATH') || die();

global $wpdb;

$table_name = $wpdb->prefix . SimpleHistory::DBTABLE;
$table_name_contexts = $wpdb->prefix . SimpleHistory::DBTABLE_CONTEXTS;

$period_days = (int) 14;
$period_start_date = DateTime::createFromFormat('U', strtotime("-$period_days days"));
$period_end_date = DateTime::createFromFormat('U', time());

/**
 * Size of database in both number or rows and table size
 */

echo '<h3>' . _x('Database size', 'debug dropin', 'simple-history') . '</h3>';

// Get table sizes in mb.
$sql_table_size = sprintf(
    '
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
printf(
    '<thead>
		<tr>
			<th>%1$s</th>
			<th>%2$s</th>
			<th>%3$s</th>
		</tr>
	</thead>
	',
    _x('Table name', 'debug dropin', 'simple-history'),
    _x('Size', 'debug dropin', 'simple-history'),
    _x('Rows', 'debug dropin', 'simple-history')
);

$loopnum = 0;
foreach ($table_size_result as $one_table) {
    $size = sprintf(_x('%s MB', 'debug dropin', 'simple-history'), $one_table->size_in_mb);

    $rows = sprintf(_x('%s rows', 'debug dropin', 'simple-history'), number_format_i18n($one_table->num_rows, 0));

    printf(
        '<tr class="%4$s">
			<td>%1$s</td>
			<td>%2$s</td>
			<td>%3$s</td>
		</tr>',
        $one_table->table_name,
        $size,
        $rows,
        $loopnum % 2 ? ' alt ' : ''
    );

    $loopnum++;
}

echo '</table>';

$logQuery = new SimpleHistoryLogQuery();
$rows = $logQuery->query(array(
    'posts_per_page' => 1
));

// This is the number of rows with occasions taken into consideration
$total_accassions_rows_count = $rows['total_row_count'];

echo '<p>';
printf(
    _x('Total %s rows, when grouped by occasion id.', 'debug dropin', 'simple-history'),
    $total_accassions_rows_count
);
echo '</p>';

// echo "<h4>Clear history interval</h4>";
// echo "<p>" . $this->sh->get_clear_history_interval() . "</p>";
/**
 * Output a list of all active loggers, including name, slug, comment, message, capability and number of rows
 * Retrieve them in order by the number of rows they have in the db
 * Loggers with 0 rows in the db will not be included in the array, so we need to find those
 * and add them manually last
 */

$arr_logger_slugs = array();

foreach ($this->sh->getInstantiatedLoggers() as $oneLogger) {
    $arr_logger_slugs[] = $oneLogger['instance']->slug;
}

$sql_logger_counts = sprintf(
    '
    SELECT logger, count(id) as count
    FROM %1$s
    WHERE logger IN ("%2$s")
    GROUP BY logger
    ORDER BY count DESC
',
    $table_name,
    join('","', $arr_logger_slugs)
);

$logger_rows_count = $wpdb->get_results($sql_logger_counts, OBJECT_K);

// Find loggers with no rows in db and append to array
$missing_logger_slugs = array_diff($arr_logger_slugs, array_keys($logger_rows_count));

foreach ($missing_logger_slugs as $one_missing_logger_slug) {
    $logger_rows_count[$one_missing_logger_slug] = (object) array(
        'logger' => $one_missing_logger_slug,
        'count' => 0
    );
}

echo '<h3>';
_ex('Loggers', 'debug dropin', 'simple-history');
echo '</h3>';

echo '<p>';
printf(
    _x('Listing %1$d loggers, ordered by rows count in database.', 'debug dropin', 'simple-history'),
    sizeof($arr_logger_slugs) // 1
);
echo '</p>';

echo "<table class='widefat fixed' cellpadding=2>";
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
    _x('Logger name', 'debug dropin', 'simple-history'),
    _x('Slug', 'debug dropin', 'simple-history'),
    _x('Description', 'debug dropin', 'simple-history'),
    _x('Messages', 'debug dropin', 'simple-history'),
    _x('Capability', 'debug dropin', 'simple-history'),
    _x('Rows count', 'debug dropin', 'simple-history')
);

$loopnum = 0;

foreach ($logger_rows_count as $one_logger_slug => $one_logger_val) {
    $logger = $this->sh->getInstantiatedLoggerBySlug($one_logger_slug);

    if (!$logger) {
        continue;
    }

    if (isset($logger_rows_count[$one_logger_slug])) {
        $one_logger_count = $logger_rows_count[$one_logger_slug];
    } else {
        // logger was not is sql result, so fake result
        $one_logger_count = new stdclass();
        $one_logger_count->count = 0;
    }

    $logger_info = $logger->getInfo();
    $logger_messages = isset($logger_info['messages']) ? (array) $logger_info['messages'] : array();

    $html_logger_messages = '';

    foreach ($logger_messages as $message_key => $message) {
        $html_logger_messages .= sprintf('<li>%1$s</li>', esc_html($message));
    }

    if ($html_logger_messages) {
        $str_num_message_strings = sprintf(
            _x('%1$s message strings', 'debug dropin', 'simple-history'),
            sizeof($logger_messages)
        );

        $html_logger_messages = sprintf(
            '
                <p>%1$s</p>
                <ul class="hide-if-js">
                    %2$s
                </ul>
            ',
            $str_num_message_strings, // 1
            $html_logger_messages // 2
        );
    } else {
        $html_logger_messages = '<p>' . _x('No message strings', 'debug dropin', 'simple-history') . '</p>';
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
        number_format_i18n($one_logger_count->count),
        esc_html($one_logger_slug), // 2
        esc_html($logger_info['name']),
        esc_html($logger_info['description']), // 4
        esc_html($logger->getCapability()), // 5
        $loopnum % 2 ? ' alt ' : '', // 6
        $html_logger_messages // 7
    );

    $loopnum++;
} // End foreach().

echo '</table>';

// List installed plugins
echo '<h2>' . _x('Plugins', 'debug dropin', 'simple-history') . '</h2>';

echo '<p>' . _x('As returned from <code>get_plugins()</code>', 'debug dropin', 'simple-history') . '</p>';

$plugins = get_plugins();

echo "<table class='widefat'>";
printf(
    '<thead>
        <tr>
            <th>%1$s</th>
            <th>%2$s</th>
            <th>%3$s</th>
        </tr>
    </thead>
    ',
    _x('Plugin name', 'debug dropin', 'simple-history'),
    _x('Plugin file path', 'debug dropin', 'simple-history'),
    _x('Active', 'debug dropin', 'simple-history')
);

foreach ($plugins as $pluginFilePath => $onePlugin) {
    $isPluginActive = is_plugin_active($pluginFilePath);
    printf(
        '
        <tr>
            <td><strong>%1$s</strong></td>
            <td>%2$s</td>
            <td>%3$s</td>
        </tr>
        ',
        esc_html($onePlugin['Name']),
        esc_html($pluginFilePath),
        $isPluginActive ? _x('Yes', 'debug dropin', 'simple-history') : _x('No', 'debug dropin', 'simple-history')
        // 3
    );
}

echo '</table>';
