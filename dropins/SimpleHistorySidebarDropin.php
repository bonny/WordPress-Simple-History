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

		// Boxes that will appear randomly

		$boxGithub = '
			<div class="postbox">
				<h3 class="hndle">Simple History is on GitHub</h3>
				<div class="inside">
					<p>Star, fork, or report issues with this plugin over
					at the <a href="https://github.com/bonny/WordPress-Simple-History">Simple History Github page</a></p>
				</div>
			</div>
		';


		$boxDonate = '
			<div class="postbox">
				<h3 class="hndle">Donate to support development</h3>
				<div class="inside">
					<p>If you like and use Simple History consider <a href="eskapism.se/sida/donate/">donating</a>.</p>
				</div>
			</div>
		';

		$boxReview = '
			<div class="postbox">
				<h3 class="hndle">Help others find Simple History by giving it a review</h3>
				<div class="inside">
					<p><a href="https://wordpress.org/support/view/plugin-reviews/simple-history">Give it a nice review over at wordpress.org</a>.</p>
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

		$arrBoxes = array($boxGithub, $boxDonate, $boxReview, $boxSocial);

		//echo $arrBoxes[array_rand($arrBoxes)];
		echo implode("", $arrBoxes); // show all

		// Box to encourage people translate plugin
		$current_locale = get_locale();
		if ( "en_US" != $current_locale ) {

			/** WordPress Translation Install API */
			require_once( ABSPATH . 'wp-admin/includes/translation-install.php' );
			$translations = wp_get_available_translations();

			// This text is only shown in English, so no need for translation of it
			$boxTranslationTmpl = '
				<div class="postbox">
					<h3 class="hndle">Translate Simple History to %1$s</h3>
					<div class="inside">
						<p>It looks like Simple History is not yet translated to your language.</p>

						<p>If you\'re interested in translating it please check out the <a href="https://developer.wordpress.org/plugins/internationalization/localization/">localization</a> part of the Plugin Handbook for info on how to translate plugins.
						</p>

						<p>When you\'re done with your translation email it to me at <a href="mailto:par.thernstrom@gmail.com" rel="nofollow">par.thernstrom@gmail.com</a>, or <a href="https://github.com/bonny/WordPress-Simple-History/" rel="nofollow">add a pull request</a>.</p>
					</div>
				</div>
			';

			if ( isset( $translations[$current_locale] ) ) {
				
				// Check if an existing text string returns something else, and that current lang is not en
				$teststring_translated = __("Just now", "simple-history");
				$teststring_untranslated = "Just now";
				if ( $teststring_untranslated == $teststring_translated ) {
					// strings are the same, so plugin probably not translated
					printf($boxTranslationTmpl, $translations[$current_locale]["english_name"]);
				}
				
			}
		}

		$boxMissingEvents = '
			<div class="postbox">
				<h3 class="hndle">Missing events?</h3>
				<div class="inside">
					<p>Do you think things are missing in the log? Let me know about it.</p>
				</div>
			</div>
		';
		//echo $boxMissingEvents;

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
