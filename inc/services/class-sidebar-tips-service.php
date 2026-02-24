<?php

namespace Simple_History\Services;

/**
 * Service that displays a rotating tip in the sidebar.
 *
 * Shows a random helpful tip on each page load to surface
 * features users may not know about.
 */
class Sidebar_Tips_Service extends Service {
	/**
	 * Called when service is loaded.
	 */
	public function loaded() {
		add_action( 'simple_history/dropin/sidebar/sidebar_html', [ $this, 'output_tip' ], 40 );
	}

	/**
	 * Get the list of tips to display.
	 *
	 * @return string[] Array of tip strings.
	 */
	private function get_tips() {
		$tips = [
			__( 'Subscribe to your activity log via RSS. Enable it in Settings > Simple History.', 'simple-history' ),
			__( 'Get a weekly email summary of your site\'s activity. Enable it in Settings > Simple History.', 'simple-history' ),
			__( 'Use "wp simple-history list" to view your activity log from the terminal.', 'simple-history' ),
			__( 'Export your event log as CSV, JSON, or HTML from Export & Tools.', 'simple-history' ),
			__( 'Pin important events with "Sticky" so they don\'t scroll away.', 'simple-history' ),
			__( 'Use "Show surrounding events" to see what happened right before and after any event.', 'simple-history' ),
			__( 'Press Cmd+K in the block editor and type "history" to jump to a post\'s activity log.', 'simple-history' ),
			__( 'Use the Quick View dropdown in the admin bar to see recent events without leaving your page.', 'simple-history' ),
			__( 'Click a user\'s avatar in the sidebar to filter the log to just their activity.', 'simple-history' ),
			__( 'Use "Hide my own events" in search filters to focus on what others did.', 'simple-history' ),
			__( 'Get instant alerts when important events happen. Available with Simple History Premium.', 'simple-history' ),
			__( 'Control exactly which events get logged with Premium\'s Message Control.', 'simple-history' ),
		];

		/**
		 * Filter the list of sidebar tips.
		 *
		 * @since 5.24.0
		 *
		 * @param string[] $tips Array of tip strings.
		 */
		return apply_filters( 'simple_history/sidebar_tips', $tips );
	}

	/**
	 * Output a random tip in the sidebar.
	 */
	public function output_tip() {
		/**
		 * Filter whether to show the sidebar tip.
		 *
		 * @since 5.24.0
		 *
		 * @param bool $show Whether to show the tip. Default true.
		 */
		if ( ! apply_filters( 'simple_history/sidebar_tips/show', true ) ) {
			return;
		}

		$tips = $this->get_tips();

		if ( empty( $tips ) ) {
			return;
		}

		$tip = $tips[ array_rand( $tips ) ];

		?>
		<div class="postbox sh-PremiumFeaturesPostbox sh-SidebarTip">
			<p class="sh-SidebarTip-text">
				<span class="sh-SidebarTip-icon" aria-hidden="true">💡</span>
				<span class="sh-SidebarTip-label"><?php esc_html_e( 'Tip:', 'simple-history' ); ?></span>
				<?php echo esc_html( $tip ); ?>
			</p>
		</div>
		<?php
	}
}
