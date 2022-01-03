<?php

defined( 'ABSPATH' ) || die();

/*
Dropin Name: Sidebar
Drop Description: Outputs HTML and filters for a sidebar
Dropin URI: http://simple-history.com/
Author: Pär Thernström
*/

class SimpleHistorySidebarDropin {
	public function __construct( $sh ) {
		add_action( 'simple_history/enqueue_admin_scripts', array( $this, 'enqueue_admin_scripts' ) );
		add_action( 'simple_history/history_page/after_gui', array( $this, 'output_sidebar_html' ) );
		add_action( 'simple_history/dropin/sidebar/sidebar_html', array( $this, 'default_sidebar_contents' ) );
	}

	public function default_sidebar_contents() {
		// Box about donation
		$headline = _x( 'Donate to support development', 'Sidebar box', 'simple-history' );

		$bodyDonate = sprintf(
			_x( 'If you like and use Simple History you should <a href="%1$s">donate to keep this plugin free</a>.', 'Sidebar box', 'simple-history' ),
			'https://eskapism.se/sida/donate/'
		);

		$bodyGithubSponsors = sprintf(
			_x( 'You can also <a href="%1$s">sponsor me at Github</a>.', 'Sidebar box', 'simple-history' ),
			'https://github.com/sponsors/bonny/'
		);

		$boxDonate = '
			<div class="postbox">
				<h3 class="hndle">' . $headline . '</h3>
				<div class="inside">
					<p>' . $bodyDonate . '</p>
					<p>' . $bodyGithubSponsors . '</p>
				</div>
			</div>
		';

		// Box about review
		$headline = _x( 'Review this plugin if you like it', 'Sidebar box', 'simple-history' );

		$body1 = sprintf(
			_x( 'If you like Simple History then please <a href="%1$s">give it a nice review over at wordpress.org</a>.', 'Sidebar box', 'simple-history' ),
			'https://wordpress.org/support/view/plugin-reviews/simple-history'
		);

		$body2 = _x( 'A good review will help new users find this plugin. And it will make the plugin author very happy :)', 'Sidebar box', 'simple-history' );

		$boxReview = '
			<div class="postbox">
				<h3 class="hndle">' . $headline . '</h3>
				<div class="inside">
					<p>' . $body1 . '</p>
					<p>' . $body2 . '</p>
				</div>
			</div>
		';

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
			_x( 'Support', 'Sidebar box', 'simple-history' ), // 1
			sprintf( _x( '<a href="%1$s">Visit the support forum</a> if you need help or have questions.', 'Sidebar box', 'simple-history' ), 'https://wordpress.org/support/plugin/simple-history' ) // 2
		);

		$arrBoxes = array(
			'boxReview' => $boxReview,
			'boxSupport' => $boxSupport,
			'boxDonate' => $boxDonate,
		);

		/**
		 * Filter the default boxes to output in the sidebar
		 *
		 * @since 2.0.17
		 *
		 * @param array $arrBoxes array with boxes to output. Check the key to determine which box is which.
		 */
		$arrBoxes = apply_filters( 'simple_history/SidebarDropin/default_sidebar_boxes', $arrBoxes );

		echo implode( '', $arrBoxes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	public function enqueue_admin_scripts() {
		$file_url = plugin_dir_url( __FILE__ );

		wp_enqueue_style( 'simple_history_SidebarDropin', $file_url . 'SimpleHistorySidebarDropin.css', null, SIMPLE_HISTORY_VERSION );
	}

	/**
	 * Output the outline for the sidebar
	 * Plugins and dropins simple use the filters to output contents to the sidebar
	 * Example HTML code to generate meta box:
	 *
	 *  <div class="postbox">
	 *      <h3 class="hndle">Title</h3>
	 *      <div class="inside">
	 *          <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit.</p>
	 *      </div>
	 *  </div>
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
				do_action( 'simple_history/dropin/sidebar/sidebar_html' );
				?>
			</div>

		</div>
		<?php
	}
}
