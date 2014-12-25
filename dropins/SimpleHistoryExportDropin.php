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

	}


	public function output() {

		global $wpdb;
		$table_name = $wpdb->prefix . SimpleHistory::DBTABLE;
		$table_name_contexts = $wpdb->prefix . SimpleHistory::DBTABLE_CONTEXTS;

		if ( isset($_POST["simple-history-action"]) && $_POST["simple-history-action"] === "export-history" ) {
			sf_d($_POST);
		}

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
			</p>

			<p>
				<button type="submit" class="button button-primary">Export</button>
				<input type="hidden" name="simple-history-action" value="export-history">
			</p>

		</form>

		<?php

	}

}

