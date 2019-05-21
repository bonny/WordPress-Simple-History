<?php

defined('ABSPATH') or die();

/*
Dropin Name: Sidebar
Drop Description: Outputs HTML and filters for a sidebar
Dropin URI: http://simple-history.com/
Author: Pär Thernström
*/

class SimpleHistorySidebarDropin
{

    private $sh;

    function __construct($sh)
    {

        $this->sh = $sh;

        add_action('simple_history/enqueue_admin_scripts', array( $this, 'enqueue_admin_scripts' ));
        add_action('simple_history/history_page/after_gui', array( $this, 'output_sidebar_html' ));

        // add_action("simple_history/dropin/sidebar/sidebar_html", array($this, "example_output"));
        add_action('simple_history/dropin/sidebar/sidebar_html', array( $this, 'default_sidebar_contents' ));
    }

    public function default_sidebar_contents()
    {

        // Boxes that will appear randomly
        // Box about GitHub
        $headline = _x('Simple History is on GitHub', 'Sidebar box', 'simple-history');

        $body = sprintf(
            _x('You can star, fork, or report issues with this plugin over at the <a href="%1$s">GitHub page</a>.', 'Sidebar box', 'simple-history'),
            'https://github.com/bonny/WordPress-Simple-History'
        );

        $boxGithub = '
			<div class="postbox">
				<h3 class="hndle">' . $headline . '</h3>
				<div class="inside">
					<p>' . $body . '</p>
				</div>
			</div>
		';

        // Box about donation
        $headline = _x('Donate to support development', 'Sidebar box', 'simple-history');

        $body = sprintf(
            _x('If you like and use Simple History you should <a href="%1$s">donate to keep this plugin free</a>.', 'Sidebar box', 'simple-history'),
            'http://eskapism.se/sida/donate/'
        );

        $boxDonate = '
			<div class="postbox">
				<h3 class="hndle">' . $headline . '</h3>
				<div class="inside">
					<p>' . $body . '</p>
				</div>
			</div>
		';

        // Box about review
        $headline = _x('Review this plugin if you like it', 'Sidebar box', 'simple-history');

        $body1 = sprintf(
            _x('If you like Simple History then please <a href="%1$s">give it a nice review over at wordpress.org</a>.', 'Sidebar box', 'simple-history'),
            'https://wordpress.org/support/view/plugin-reviews/simple-history'
        );

        $body2 = _x('A good review will help new users find this plugin. And it will make the plugin author very happy :)', 'Sidebar box', 'simple-history');

        $boxReview = '
			<div class="postbox">
				<h3 class="hndle">' . $headline . '</h3>
				<div class="inside">
					<p>' . $body1 . '</p>
					<p>' . $body2 . '</p>
				</div>
			</div>
		';

        // Box about tweeting and blogging
        /*
        $boxSocial = '
            <div class="postbox">
                <h3 class="hndle">Blog or tweet</h3>
                <div class="inside">
                    <p>Yeah, how about that yo.</p>
                </div>
            </div>
        ';
        */

        // Box about possible events missing
        $boxMissingEvents = sprintf(
            '
			<div class="postbox">
				<h3 class="hndle">%1$s</h3>
				<div class="inside">
					<p>%2$s</p>
					<p><a href="hello@simple-history.com">hello@simple-history.com</a></p>
				</div>
			</div>
			',
            _x('Add more to the log', 'Sidebar box', 'simple-history'), // 1
            _x('Are there things you miss in the history log?', 'Sidebar box', 'simple-history') // 2
        );

        // Box about support
        $boxSupport = sprintf(
            '
			<div class="postbox">
				<h3 class="hndle">%1$s</h3>
				<div class="inside">
					<p>%2$s</p>
				</div>
			</div>
			',
            _x('Support', 'Sidebar box', 'simple-history'), // 1
            sprintf(_x('<a href="%1$s">Visit the support forum</a> if you need help or have questions.', 'Sidebar box', 'simple-history'), 'https://wordpress.org/support/plugin/simple-history') // 2
        );

        $arrBoxes = array(
            'boxReview' => $boxReview,
            'boxSupport' => $boxSupport,
            // "boxMissingEvents" => $boxMissingEvents,
            'boxDonate' => $boxDonate,
            // "boxGithub" => $boxGithub,
        );

        /**
         * Filter the default boxes to output in the sidebar
         *
         * @since 2.0.17
         *
         * @param array $arrBoxes array with boxes to output. Check the key to determine which box is which.
         */
        $arrBoxes = apply_filters('simple_history/SidebarDropin/default_sidebar_boxes', $arrBoxes);

        // echo $arrBoxes[array_rand($arrBoxes)];
        echo implode('', $arrBoxes); // show all

        // Box to encourage people translate plugin
        $current_locale = get_locale();

        /** WordPress Translation Install API. This file exists only since 4.0. */
        $translation_install_file = ABSPATH . 'wp-admin/includes/translation-install.php';

        // Show only the translation box if current language is not an english language
        if (in_array($current_locale, array( 'en_US', 'en_GB', 'en_CA', 'en_NZ', 'en_AU' )) != $current_locale && file_exists($translation_install_file)) {
            require_once $translation_install_file;

            $translations = wp_get_available_translations();

            // This text does not need translation since is's only shown in English
            $boxTranslationTmpl = '
				<div class="postbox">
					<h3 class="hndle">Translate Simple History to %1$s</h3>
					<div class="inside">
						
						<p>
							It looks like Simple History is not yet translated to your language.
						</p>

						<p>
							If you\'re interested in translating it please check out the <a href="https://developer.wordpress.org/plugins/internationalization/localization/">localization</a> part of the Plugin Handbook for info on how to translate plugins.
						</p>

						<p>
							When you\'re done with your translation email it to me at <a href="mailto:par.thernstrom@gmail.com" rel="nofollow">par.thernstrom@gmail.com</a> 
							or <a href="https://github.com/bonny/WordPress-Simple-History/" rel="nofollow">add a pull request</a>.
						</p>
					</div>
				</div>
			';

            if (isset($translations[ $current_locale ])) {
                // Check if an existing text string returns something else, and that current lang is not en
                $teststring_translated = __('Just now', 'simple-history');
                $teststring_untranslated = 'Just now';
                if ($teststring_untranslated == $teststring_translated) {
                    // strings are the same, so plugin probably not translated
                    printf($boxTranslationTmpl, $translations[ $current_locale ]['english_name']);
                }
            }
        } // End if().
    }

    public function example_output()
    {
        ?>
        <div class="postbox">
            <h3 class="hndle">Example title</h3>
            <div class="inside">
                <p>Example content. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Inquit, dasne adolescenti veniam? Non laboro, inquit, de nomine. In quibus doctissimi illi veteres inesse quiddam caeleste et divinum putaverunt. Duo Reges: constructio interrete. Indicant pueri, in quibus ut in speculis natura cernitur. Quod ea non occurrentia fingunt, vincunt Aristonem; Quod quidem iam fit etiam in Academia. Aliter enim nosmet ipsos nosse non possumus.</p>
            </div>
        </div>
        <?php
    }

    public function enqueue_admin_scripts()
    {

        $file_url = plugin_dir_url(__FILE__);

        wp_enqueue_style('simple_history_SidebarDropin', $file_url . 'SimpleHistorySidebarDropin.css', null, SIMPLE_HISTORY_VERSION);
    }

    /**
     * Output the outline for the sidebar
     * Plugins and dropins simple use the filters to output contents to the sidebar
     * Example HTML code to generate meta box:
     *
     *  <div class="postbox">
     *      <h3 class="hndle">Title</h3>
     *      <div class="inside">
     *          <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Inquit, dasne adolescenti veniam? Non laboro, inquit, de nomine. In quibus doctissimi illi veteres inesse quiddam caeleste et divinum putaverunt. Duo Reges: constructio interrete. Indicant pueri, in quibus ut in speculis natura cernitur. Quod ea non occurrentia fingunt, vincunt Aristonem; Quod quidem iam fit etiam in Academia. Aliter enim nosmet ipsos nosse non possumus.</p>
     *      </div>
     *  </div>
     */
    public function output_sidebar_html()
    {

        ?>
        <div class="SimpleHistory__pageSidebar">

            <div class="metabox-holder">

                <?php
                /**
                 * Allows to output HTML in sidebar
                 *
                 * @since 2.0.16
                 */
                do_action('simple_history/dropin/sidebar/sidebar_html');
                ?>
            </div>

        </div>
        <?php
    }
}//end class
