<?php

/*
Dropin Name: Settings stats
Dropin Description: Adds a tab with stats
Dropin URI: http://simple-history.com/
Author: Pär Thernström
*/

class SimpleHistorySettingsStatsDropin {

	// Simple History instance
	private $sh;

	public function __construct($sh) {
		
		$this->sh = $sh;

		// How do we register this to the settings array?
		$sh->registerSettingsTab(array(
			"slug" => "stats",
			"name" => __("Stats", "simple-history"),
			"function" => array($this, "output")
		));

		add_action( 'admin_enqueue_scripts', array( $this, 'on_admin_enqueue_scripts') );

	}

	public function on_admin_enqueue_scripts() {
		
		wp_enqueue_script( "google-ajax-api", "https://www.google.com/jsapi");
		
	}

	public function output() {

		global $wpdb;
		$table_name = $wpdb->prefix . SimpleHistory::DBTABLE;
		$table_name_contexts = $wpdb->prefix . SimpleHistory::DBTABLE_CONTEXTS;

		$period_days = (int) 28;
		$period_start_date = DateTime::createFromFormat('U', strtotime("-$period_days days"));
		$period_end_date = DateTime::createFromFormat('U', time());

		// Colors taken from the gogole chart example that was found in this Stack Overflow thread:
		// http://stackoverflow.com/questions/236936/how-pick-colors-for-a-pie-chart
		$arr_colors = explode(",", "8a56e2,cf56e2,e256ae,e25668,e28956,e2cf56,aee256,68e256,56e289,56e2cf,56aee2,5668e2");

		// Generate CSS classes for chartist, based on $arr_colors
		$str_chartist_css_colors = "";
		$arr_chars = str_split("abcdefghijkl");
		$i = 0;
		foreach ($arr_chars as $one_char) {
			
			$str_chartist_css_colors .= sprintf('
				.ct-chart .ct-series.ct-series-%1$s .ct-slice:not(.ct-donut) {
					fill: #%2$s;
				}
				', 
				$one_char, // 1
				$arr_colors[$i] // 2
			);
			$i++;

		}

		// Echo styles like this because syntax highlighter in sublime goes bananas 
		// if I try to do it in any other way...
		echo "
			<style>
				.SimpleHistoryStats__intro {
					font-size: 1.4em;
				}
				.SimpleHistoryStats__graphs {
					overflow: auto;
				}
				.SimpleHistoryStats__graph {
					float: left;
					width: 50%;
				}

				/* chartist bar */
				.ct-chart .ct-bar {
					stroke-width: 15px;
					box-shadow: 1px 1px 1px black;
				}
				.ct-chart .ct-series.ct-series-a .ct-bar {
					stroke: rgb(226, 86, 174);
				}
				
				/* chartist chart */
				{$str_chartist_css_colors}
				
			</style>
		";


		?>
		<!-- Overview, larger text -->
		<div class='SimpleHistoryStats__intro'>
			<?php
			include(dirname(__FILE__) . "/../templates/settings-statsIntro.php");
			?>
		</div>

		<!-- Start charts wrap -->
		<div class='SimpleHistoryStats__graphs'>

			<!-- bar chart with rows per day -->
			<div class='SimpleHistoryStats__graph SimpleHistoryStats__graph--rowsPerDay'>
				<?php include(dirname(__FILE__) . "/../templates/settings-statsRowsPerDay.php") ?>
			</div><!-- // end bar chart rows per day -->

			<div class='SimpleHistoryStats__graph SimpleHistoryStats__graph--loggersPie'>
				<?php include(dirname(__FILE__) . "/../templates/settings-statsLoggers.php") ?>
			</div>

			<div class='SimpleHistoryStats__graph SimpleHistoryStats__graph--logLevels'>
				<?php include(dirname(__FILE__) . "/../templates/settings-statsLogLevels.php") ?>
			</div>

			<div class='SimpleHistoryStats__graph SimpleHistoryStats__graph--initiators'>
				<?php include(dirname(__FILE__) . "/../templates/settings-statsInitiators.php") ?>
			</div>

			<div class='SimpleHistoryStats__graph SimpleHistoryStats__graph--users'>
				<?php include(dirname(__FILE__) . "/../templates/settings-statsUsers.php") ?>
			</div>


		</div><!-- // end charts wrapper -->

		<?php

		include(dirname(__FILE__) . "/../templates/settings-statsForGeeks.php");		

	}



}


