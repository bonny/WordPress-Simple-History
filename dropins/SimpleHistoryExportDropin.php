<?php

/*
Dropin Name: Export
Dropin Description: Adds a tab with export options
Dropin URI: http://simple-history.com/
Author: Pär Thernström
*/

class SimpleHistoryExportDropin {

	// Simple History instance
	private $sh;

	public function __construct($sh) {

		// Since it's not quite done yet, it's for da devs only for now
		if ( ! defined("SIMPLE_HISTORY_DEV") || ! SIMPLE_HISTORY_DEV ) {
			return;
		}

		// Set simple history variable
		$this->sh = $sh;

		// Add tab to settings page
		$sh->registerSettingsTab(array(
			"slug" => "export",
			"name" => _x("Export", "Tab name on settings page", "simple-history"),
			"function" => array($this, "output")
		));

		add_action("init", array($this, "downloadExport"));

	}

	public function downloadExport() {

		global $wpdb;

		$table_name = $wpdb->prefix . SimpleHistory::DBTABLE;
		$table_name_contexts = $wpdb->prefix . SimpleHistory::DBTABLE_CONTEXTS;

		if ( isset($_POST["simple-history-action"]) && $_POST["simple-history-action"] === "export-history" ) {

			// Will die if nonce not valid
			check_admin_referer( __CLASS__ . "-action-export" );

			$export_format = isset( $_POST["format"] ) ? $_POST["format"] : "json";

			/*
			// Get all history
			// @todo: need examples of that. I wrote the api. I dunno how to use it. *facepalm*
			// get mem problem right away. crap.
			use unbuffered queries, using MYSQLI_USE_RESULT
			https://make.wordpress.org/core/2014/04/07/mysql-in-wordpress-3-9/
			http://stackoverflow.com/questions/12663544/export-large-database-to-file-without-mysqldump-low-memory-footprint
			http://php.net/manual/en/mysqli.use-result.php
			http://php.net/manual/en/function.mysql-unbuffered-query.php
			if ( $wpdb->use_mysqli ) {
				$mysql_ver = @mysqli_get_server_info( $wpdb->dbh );
			} else {
				$mysql_ver = @mysql_get_server_info();
			}

			*/

			#d($events_sql);exit;
			#$sql = "SELECT * FROM {$table_name_contexts}";

			/*global $wbdp;
			if( $wpdb->use_mysqli ) {
				$rows = mysqli_query($wpdb->dbh, $sql, MYSQLI_USE_RESULT);
			} else {
				$rows = mysql_unbuffered_query($sql, $wpdb->dbh);
			}*/

			add_filter("simple_history/header_time_ago_max_time", "__return_zero");
			add_filter("simple_history/header_just_now_max_time", "__return_zero");
			add_filter("simple_history/header_initiator_use_you", "__return_false");

			// Paginate through all rows
			$query = new SimpleHistoryLogQuery();
			$query_args = array(
				"paged" => 1,
				"posts_per_page" => 5000
			);
			$events = $query->query($query_args);

			// $events->total_row_count;
			$pages_count = $events["pages_count"];
			$page_current = $events["page_current"];

			$fp = fopen('php://output', 'w');

			#header("Content-Type: application/octet-stream");
			header("Content-Type: text/plain");
			#header("Content-Disposition: attachment; filename='export.txt'");

			while ( $page_current <= $pages_count + 1 ) {

				// echo "<br>exporting page $page_current of $pages_count";

				foreach ($events["log_rows"] as $one_row)  {

					#$message = sprintf('%1$s: %2$s', $one_row->date, $one_row->message);
					$header_output = strip_tags( html_entity_decode( $this->sh->getLogRowHeaderOutput( $one_row ), ENT_QUOTES, 'UTF-8') );
					#$header_output = str_replace(array("\r", "\n"), '', $header_output);
					$header_output = trim(preg_replace('/\s\s+/', ' ', $header_output));

					$message_output = strip_tags( html_entity_decode( $this->sh->getLogRowPlainTextOutput( $one_row ), ENT_QUOTES, 'UTF-8') );
					#$details_output = $this->sh->getLogRowDetailsOutput( $row );

					#$text_output = "{$header_output} – {$message_output}\n";

					print_r($one_row);exit;
					fputcsv($fp, array(
						$one_row->date,
						$one_row->logger,
						$one_row->level,
						$one_row->initiator,
						$one_row->context_message_key,
						$header_output,
						$message_output,
						$one_row->subsequentOccasions
					));

					#fwrite($fp, $text_output);
					/*
					fwrite($fp,"Hello, world !<BR>\n");
					fclose($fp);
					*/
					flush();
					exit;

				}

				#d(memory_get_usage());
				#d(memory_get_peak_usage());
				flush();

				#exit;
				#d($query_args);exit;

				// Fetch next page
				$page_current++;
				$query_args["paged"] = $page_current;
				$events = $query->query($query_args);


			}

			fclose($fp);

			#d($events);


			echo "<br>done";

		}

	}


	public function output() {


		?>
		<!-- <h2>Export</h2> -->

		<p>The export function will export the full history.</p>

		<form method="post">

			<h3>Format</h3>

			<p>
				<label>
					<input type="radio" name="format" value="csv" checked>
					CSV
				</label>
				<br>

				<label>
					<input type="radio" name="format" value="json">
					JSON
				</label>
				<br>

				<label>
					<input type="radio" name="format" value="html">
					HTML
				</label>
				<br>

				<label>
					<input type="radio" name="format" value="xml">
					XML
				</label>
			</p>

			<p>
				<button type="submit" class="button button-primary">Export</button>
				<input type="hidden" name="simple-history-action" value="export-history">
			</p>

			<?php
			wp_nonce_field( __CLASS__ . "-action-export" );
			?>

		</form>

		<?php

	}

}
