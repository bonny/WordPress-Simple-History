<?php
/*
Dropin Name: Sidebar
Drop Description: Outputs HTML and filters for a sidebar
Dropin URI: http://simple-history.com/
Author: Pär Thernström
*/

class SimpleHistorySidebarDropin {

	private $sh;

	function __construct($sh) {

		$this->sh = $sh;

		add_action("simple_history/enqueue_admin_scripts", array($this, "enqueue_admin_scripts"));
		add_action("simple_history/history_page/after_gui", array( $this, "output_sidebar_html") );

		// add_action("simple_history/dropin/sidebar/sidebar_html", array($this, "example_output"));
		
		add_action("simple_history/dropin/sidebar/sidebar_html", array($this, "default_sidebar_contents"));

	}

	public function default_sidebar_contents() {

		$boxDonate = '
			<div class="postbox">
				<h3 class="hndle">Donate</h3>
				<div class="inside">
					<p>Please donate yo.</p>
				</div>
			</div>
		';

		$boxReview = '
			<div class="postbox">
				<h3 class="hndle">Review</h3>
				<div class="inside">
					<p>Give it a nice review yo.</p>
				</div>
			</div>
		';

		$boxSocial = '
			<div class="postbox">
				<h3 class="hndle">Blog or tweet</h3>
				<div class="inside">
					<p>Yeah, how about that yo.</p>
				</div>
			</div>
		';

		$arrBoxes = array($boxDonate, $boxReview, $boxSocial);

		echo $arrBoxes[array_rand($arrBoxes)];

		$boxTranslation = '
			<div class="postbox">
				<h3 class="hndle">Missing a translation</h3>
				<div class="inside">
					<p>Help out by translating yourself!</p>
				</div>
			</div>
		';

		echo $boxTranslation;

		$boxMissingEvents = '
			<div class="postbox">
				<h3 class="hndle">Missing events?</h3>
				<div class="inside">
					<p>Do you think things are missing in the log? Let me know about it.</p>
				</div>
			</div>
		';

		echo $boxMissingEvents;


	}

	public function example_output() {
		?>
		<div class="postbox">
			<h3 class="hndle">Example title</h3>
			<div class="inside">
				<p>Example content. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Inquit, dasne adolescenti veniam? Non laboro, inquit, de nomine. In quibus doctissimi illi veteres inesse quiddam caeleste et divinum putaverunt. Duo Reges: constructio interrete. Indicant pueri, in quibus ut in speculis natura cernitur. Quod ea non occurrentia fingunt, vincunt Aristonem; Quod quidem iam fit etiam in Academia. Aliter enim nosmet ipsos nosse non possumus.</p>
			</div>
		</div>
		<?php
	}

	public function enqueue_admin_scripts() {

		$file_url = plugin_dir_url(__FILE__);

		// wp_enqueue_script("simple_history_FilterDropin", $file_url . "SimpleHistoryFilterDropin.js", array("jquery"), SimpleHistory::VERSION, true);

		wp_enqueue_style("simple_history_SidebarDropin", $file_url . "SimpleHistorySidebarDropin.css", null, SimpleHistory::VERSION);

	}

	/**
	 * Output the outline for the sidebar
	 * Plugins and dropins simple use the filters to output contents to the sidebar
	 * Example HTML code to generate meta box:
	 *
	 * 	<div class="postbox">
	 * 		<h3 class="hndle">Title</h3>
	 * 		<div class="inside">
	 * 			<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Inquit, dasne adolescenti veniam? Non laboro, inquit, de nomine. In quibus doctissimi illi veteres inesse quiddam caeleste et divinum putaverunt. Duo Reges: constructio interrete. Indicant pueri, in quibus ut in speculis natura cernitur. Quod ea non occurrentia fingunt, vincunt Aristonem; Quod quidem iam fit etiam in Academia. Aliter enim nosmet ipsos nosse non possumus.</p>
	 * 		</div>
	 * 	</div>
	 *
	 */
	public function output_sidebar_html() {

		?>
		<div class="SimpleHistory__pageSidebar">

			<div class="metabox-holder">

				<?php
				/**
				 * Allows to output HTML in sidebar
				 *
				 * @since 2.0.16
				 */
				do_action("simple_history/dropin/sidebar/sidebar_html");
				?>

			</div>

		</div>
		<?php

	}

} // end class
