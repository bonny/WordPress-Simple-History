<?php

namespace Simple_History;

use Simple_History\Services\Admin_Pages;
use Simple_History\Services\Stealth_Mode;

defined( 'ABSPATH' ) || die();

/**
 * @var array{
 *      instantiated_loggers:array,
 *      instantiated_dropins:array,
 *      instantiated_services:array,
 *      events_table_name:string,
 *      simple_history_instance: Simple_History,
 *      wpdb:\wpdb,
 *      plugins:array,
 *      dropins:array,
 *      tables_info:array,
 *      table_size_result:array,
 *      db_engine:string
 * } $args
 */
$args = $args ?? [];

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
echo Admin_Pages::header_output();

?>
<div class="wrap">
	<?php

	/**
	 * Check that required tables exists.
	 * Some users have had issues with tables not being created after
	 * moving site from one server to another. Probably because the history tables
	 * are not moved with the rest of the database, but the options table are and that
	 * confuses Simple History.
	 */
	foreach ( $args['tables_info'] as $table_info ) {
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

	echo wp_kses(
		Helpers::get_settings_section_title_output( __( 'Debug', 'simple-history' ), 'build' ),
		[
			'span' => [
				'class' => [],
			],
		]
	);

	/**
	 * Database info.
	 */
	echo '<h3>' . esc_html_x( 'Database', 'debug dropin', 'simple-history' ) . '</h3>';

	echo '<h4>' . esc_html_x( 'Database engine', 'debug dropin', 'simple-history' ) . '</h4>';

	echo wp_kses(
		sprintf(
			/* translators: %1$s database engine name, %2$s database engine version. */
			__( 'Database engine used to perform queries: <code>%1$s</code>.', 'simple-history' ),
			$args['db_engine']
		),
		[
			'code' => [],
		]
	);

	/**
	 * Size of database in both number or rows and table size
	 */

	echo '<h4>' . esc_html_x( 'Database size', 'debug dropin', 'simple-history' ) . '</h4>';

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

	if ( sizeof( $args['table_size_result'] ) === 0 ) {
		echo '<tr><td colspan="3">';
		echo esc_html_x( 'No tables found.', 'debug dropin', 'simple-history' );
		echo '</td></tr>';
	} else {
		foreach ( $args['table_size_result'] as $one_table ) {
			// Handle N/A for SQLite environments without dbstat extension.
			if ( $one_table['size_in_mb'] === 'N/A' ) {
				$size = _x( 'N/A', 'debug dropin', 'simple-history' );
			} else {
				/* translators: %s size in mb. */
				$size = sprintf( _x( '%s MB', 'debug dropin', 'simple-history' ), $one_table['size_in_mb'] );
			}

			/* translators: %s number of rows. */
			$rows = sprintf( _x( '%s rows', 'debug dropin', 'simple-history' ), number_format_i18n( $one_table['num_rows'], 0 ) );

			printf(
				'<tr>
					<td>%1$s</td>
					<td>%2$s</td>
					<td>%3$s</td>
				</tr>',
				esc_html( $one_table['table_name'] ),
				esc_html( $size ),
				esc_html( $rows ),
			);
		}
	}

	echo '</table>';

	/**
	 * Number of rows in database
	 */
	$rows = ( new Log_Query() )->query( [ 'posts_per_page' => 1 ] );

	// Handle database errors gracefully.
	if ( is_wp_error( $rows ) ) {
		echo '<p>';
		echo '<strong>' . esc_html_x( 'Error:', 'debug dropin', 'simple-history' ) . '</strong> ';
		echo esc_html( $rows->get_error_message() );
		echo '</p>';
	} else {
		// This is the number of rows with occasions taken into consideration.
		$total_accassions_rows_count = $rows['total_row_count'];

		echo '<p>';
		printf(
			/* translators: %d number of rows. */
			esc_html_x( 'Total %s rows, when grouped by occasion id.', 'debug dropin', 'simple-history' ),
			esc_html( $total_accassions_rows_count )
		);
		echo '</p>';
	}

	// Total number of logged events,
	// since installing the plugin or since the feature was added.
	echo '<h4>' . esc_html_x( 'Total number of logged events', 'debug dropin', 'simple-history' ) . '</h4>';
	$plugin_install_date = Helpers::get_plugin_install_date();
	if ( $plugin_install_date === false ) {
		echo '<p>';
		echo esc_html_x( 'Could not find the date when the plugin was installed.', 'debug dropin', 'simple-history' );
		echo '</p>';
	} else {
		$total_logged_events_count = Helpers::get_total_logged_events_count();
		$plugin_install_date_local = wp_date( 'Y-m-d H:i:s', strtotime( $plugin_install_date ) );
		echo '<p>';
		printf(
			/* translators: %d number of logged events. */
			esc_html_x( '%1$s logged events since %2$s.', 'debug dropin', 'simple-history' ),
			esc_html( number_format_i18n( $total_logged_events_count ) ),
			esc_html( $plugin_install_date_local )
		);
		echo '</p>';
	}

	// Date of oldest event.
	$oldest_event = ( new Events_Stats() )->get_oldest_event();

	if ( $oldest_event ) {
		echo '<p>';
		printf(
			/* translators: %s date of oldest event. */
			esc_html_x( 'Oldest event is from %1$s and has id %2$s.', 'debug dropin', 'simple-history' ),
			esc_html( $oldest_event['date'] ),
			esc_html( $oldest_event['id'] )
		);
		echo '</p>';
	}

	// Output Stealh Mode status if Full or Partial Stealth Mode is enabled.
	/** @var Stealth_Mode|null $stealh_mode_service */
	$stealh_mode_service = $args['simple_history_instance']->get_service( Stealth_Mode::class );
	if ( $stealh_mode_service !== null ) {
		$is_stealth_mode_enabled = $stealh_mode_service::is_stealth_mode_enabled();

		if ( $is_stealth_mode_enabled ) {
			$stealth_mode_allowed_emails = $stealh_mode_service::get_allowed_email_addresses();

			// If number of emails are more than this, only show the first 5.
			$large_amount_of_emails_threshold           = 10;
			$large_amount_of_emails_more_than_threshold = 0;

			// If large amount of emails, only show the first 5.
			if ( count( $stealth_mode_allowed_emails ) > $large_amount_of_emails_threshold ) {
				$large_amount_of_emails_more_than_threshold = count( $stealth_mode_allowed_emails ) - $large_amount_of_emails_threshold;
				$stealth_mode_allowed_emails                = array_slice( $stealth_mode_allowed_emails, 0, $large_amount_of_emails_threshold );
				$stealth_mode_allowed_emails[]              = sprintf(
					/* translators: %d number of emails. */
					esc_html_x( 'And %d more.', 'debug dropin', 'simple-history' ),
					$large_amount_of_emails_more_than_threshold
				);
			}

			echo '<h3>' . esc_html_x( 'Stealth Mode', 'debug dropin', 'simple-history' ) . '</h3>';

			echo '<p>';
			echo esc_html_x( 'Partial Stealth Mode is enabled.', 'debug dropin', 'simple-history' );
			echo '</p>';

			echo '<p>' . esc_html_x( 'Allowed email addresses:', 'debug dropin', 'simple-history' ) . '</p>';
			echo '<ul>';
			foreach ( $stealth_mode_allowed_emails as $email ) {
				echo '<li>' . esc_html( $email ) . '</li>';
			}
			echo '</ul>';
		}
	}

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
			'count'  => 0,
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
				<th>%7$s</th>
			</tr>
		</thead>
		',
		esc_html_x( 'Logger name', 'debug dropin', 'simple-history' ),
		esc_html_x( 'Slug', 'debug dropin', 'simple-history' ),
		esc_html_x( 'Description', 'debug dropin', 'simple-history' ),
		esc_html_x( 'Messages', 'debug dropin', 'simple-history' ),
		esc_html_x( 'Capability', 'debug dropin', 'simple-history' ),
		esc_html_x( 'Rows count', 'debug dropin', 'simple-history' ),
		esc_html_x( 'Status', 'debug dropin', 'simple-history' )
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
			// logger was not is sql result, so fake result.
			$one_logger_count        = new \stdClass();
			$one_logger_count->count = 0;
		}

		$logger_info     = $logger->get_info();
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

		$logger_enabled_text = $logger->is_enabled() ? _x( 'Enabled', 'debug dropin', 'simple-history' ) : _x( 'Disabled', 'debug dropin', 'simple-history' );

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
				<td>
					<p>%8$s</p>
				</td>
			</tr>
			',
			esc_html( number_format_i18n( $one_logger_count->count ) ),
			esc_html( $one_logger_slug ), // 2
			esc_html( $logger_info['name'] ),
			esc_html( $logger_info['description'] ), // 4
			esc_html( $logger->get_capability() ), // 5
			$loopnum % 2 ? ' alt ' : '', // 6
			$html_logger_messages, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			esc_html( $logger_enabled_text )
		);

		++$loopnum;
	}

	echo '</table>';

	// List installed plugins.
	echo '<h2>' . esc_html_x( 'Plugins', 'debug dropin', 'simple-history' ) . '</h2>';

	echo '<p>' . esc_html_x( 'As returned from get_plugins().', 'debug dropin', 'simple-history' ) . '</p>';

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

	foreach ( $args['plugins'] as $pluginFilePath => $onePlugin ) {
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

	// WordPress drop-ins.
	echo '<h2>' . esc_html_x( 'WordPress drop-ins', 'debug dropin', 'simple-history' ) . '</h2>';

	echo '<p>' . esc_html_x( 'As returned from get_dropins().', 'debug dropin', 'simple-history' ) . '</p>';

	if ( count( $args['dropins'] ) === 0 ) {
		echo '<p>' . esc_html_x( 'No drop-ins found.', 'debug dropin', 'simple-history' ) . '</p>';
	} else {
		echo "<table class='widefat striped'>";
		printf(
			'<thead>
				<tr>
					<th>%1$s</th>
					<th>%2$s</th>
				</tr>
			</thead>
			',
			esc_html_x( 'Drop-in name', 'debug dropin', 'simple-history' ),
			esc_html_x( 'Drop-in filename', 'debug dropin', 'simple-history' )
		);

		foreach ( $args['dropins'] as $dropinFilename => $dropinInfo ) {
			printf(
				'
				<tr>
					<td><strong>%1$s</strong></td>
					<td>%2$s</td>
				</tr>
				',
				esc_html( $dropinInfo['Name'] ),
				esc_html( $dropinFilename )
			);
		}

		echo '</table>';
	}
	?>
</div>
