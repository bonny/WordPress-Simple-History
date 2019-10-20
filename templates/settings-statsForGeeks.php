<?php
defined('ABSPATH') or die(); ?>

<script>

    jQuery(function($) {

        var $button = $(".js-SimpleHistoryShowsStatsForGeeks");
        var $wrapper = $(".SimpleHistory__statsForGeeksInner");

        $button.on("click", function() {
            $wrapper.toggle();
        });


    });

</script>
<?php
defined('ABSPATH') or exit();

echo '<hr>';
echo "<p class='hide-if-no-js'><button class='button js-SimpleHistoryShowsStatsForGeeks'>Show stats for geeks</button></p>";
?>

<div class="SimpleHistory__statsForGeeksInner hide-if-js">
    <?php
    echo '<h4>Rows count</h4>';
    $logQuery = new SimpleHistoryLogQuery();
    $rows = $logQuery->query(array(
        'posts_per_page' => 1
        // "date_from" => strtotime("-$period_days days")
    ));

    // This is the number of rows with occasions taken into consideration
    $total_accassions_rows_count = $rows['total_row_count'];

    // Total number of log rows
    // Not caring about occasions, this number = all occasions
    $total_num_rows = $wpdb->get_var("select count(*) FROM {$table_name}");
    echo '<ul>';
    echo "<li>Total $total_num_rows log rows in db.</li>";
    echo "<li>Total $total_accassions_rows_count rows, when grouped by occasion id.</li>";
    echo '</ul>';

    echo '<h4>Clear history interval</h4>';
    echo '<p>' . $this->sh->get_clear_history_interval() . '</p>';

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

    echo '<h4>Database size</h4>';

    echo "<table class='widefat'>";
    echo '
        <thead>
            <tr>
                <th>Table name</th>
                <th>Table size (MB)</th>
                </tr>
        </thead>
    ';

    $loopnum = 0;
    foreach ($table_size_result as $one_table) {
        printf(
            '<tr class="%3$s">
				<td>%1$s</td>
				<td>%2$s</td>
			</tr>',
            $one_table->table_name,
            $one_table->size_in_mb,
            $loopnum % 2 ? ' alt ' : ''
        );

        $loopnum++;
    }

    echo '</table>';

    // @TODO: this does actually only show all loggers that have logged rows,
    // not all loggers!
    echo '<h4>Loggers</h4>';

    echo '<p>All instantiated loggers.</p>';

    echo "<table class='widefat' cellpadding=2>";
    echo '
        <thead>
            <tr>
                <th>Name + Slug</th>
                <th>Description</th>
                <th>Messages</th>
                <th>Capability</th>
                <th>Rows count</th>
            </tr>
        </thead>
    ';

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

    $loopnum = 0;
    // foreach ( $logger_rows_count as $one_logger_count ) {
    foreach ($arr_logger_slugs as $one_logger_slug) {
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
        $logger_messages = $logger_info['messages'];

        $html_logger_messages = '';
        foreach ($logger_messages as $message_key => $message) {
            $html_logger_messages .= sprintf('<li>%1$s</li>', esc_html($message));
        }
        if ($html_logger_messages) {
            $html_logger_messages = "<ul>{$html_logger_messages}</ul>";
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
            esc_html($logger_info['name']),
            esc_html($logger_info['description']), // 4
            esc_html($logger->getCapability()),
            $loopnum % 2 ? ' alt ' : '', // 6
            $html_logger_messages // 7
        );

        $loopnum++;
    } // End foreach().
    echo '</table>';
    ?>
</div><!-- // stats for geeks inner -->
