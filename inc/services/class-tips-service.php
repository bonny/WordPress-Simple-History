<?php

namespace Simple_History\Services;

use Simple_History\Helpers;

/**
 * Service that provides rotating tips surfaced in the sidebar and dashboard widget.
 *
 * Tips carry a `contexts` array so each surface picks the ones that make sense there
 * (e.g. the "/" search shortcut is event-log only, while sticky/alerts work anywhere).
 */
class Tips_Service extends Service {
	/**
	 * Called when service is loaded.
	 */
	public function loaded() {
		add_action( 'simple_history/dropin/sidebar/sidebar_html', [ $this, 'output_tip' ], 40 );

		// Priority 20 to run after React_Dropin (priority 10) registers the script handle.
		add_action( 'simple_history/enqueue_admin_scripts', [ $this, 'localize_tips_for_react' ], 20 );
	}

	/**
	 * Get the full list of tips with context metadata.
	 *
	 * Each tip is an array of:
	 * - text: string The tip text.
	 * - contexts: string[] Surfaces where the tip should appear (e.g. 'sidebar', 'dashboard').
	 *
	 * @return array<int, array{text: string, contexts: string[]}> Structured tip list.
	 */
	private function get_all_tips() {
		$is_premium_active = Helpers::is_premium_add_on_active();

		$tips = [
			[
				'text'     => __( 'Subscribe to your activity log via RSS. Enable it in Settings > Simple History.', 'simple-history' ),
				'contexts' => [ 'sidebar' ],
			],
			[
				'text'     => __( 'Get a weekly email summary of your site\'s activity. Enable it in Settings > Simple History.', 'simple-history' ),
				'contexts' => [ 'sidebar', 'dashboard' ],
			],
			[
				'text'     => __( 'Use "wp simple-history list" to view your activity log from the terminal.', 'simple-history' ),
				'contexts' => [ 'sidebar' ],
			],
			[
				'text'     => __( 'Export your event log as CSV, JSON, or HTML from Export & Tools.', 'simple-history' ),
				'contexts' => [ 'sidebar', 'dashboard' ],
			],
			[
				'text'     => __( 'Use "Show surrounding events" to see what happened right before and after any event.', 'simple-history' ),
				'contexts' => [ 'sidebar', 'dashboard' ],
			],
			[
				'text'     => __( 'Press Cmd+K (or Ctrl+K) in the block editor and type "history" to jump to a post\'s activity log.', 'simple-history' ),
				'contexts' => [ 'sidebar' ],
			],
			[
				'text'     => __( 'Use the Quick View dropdown in the admin bar to see recent events without leaving your page.', 'simple-history' ),
				'contexts' => [ 'sidebar', 'dashboard' ],
			],
			$is_premium_active
				? [
					'text'     => __( 'Click a user\'s name or avatar on any event to see their details and view all their activity.', 'simple-history' ),
					'contexts' => [ 'sidebar' ],
				]
				: [
					'text'     => __( 'Click a user\'s name or avatar on any event to see their details and open their profile.', 'simple-history' ),
					'contexts' => [ 'sidebar' ],
				],
			[
				'text'     => __( 'Press "/" on the event log page to quickly jump to the search field. Press Escape to return to where you were.', 'simple-history' ),
				'contexts' => [ 'sidebar' ],
			],
			[
				'text'     => __( 'Use "Hide my own events" in search filters to focus on what others did.', 'simple-history' ),
				'contexts' => [ 'sidebar' ],
			],
			[
				'text'     => __( 'Developers: Log your own events from themes and plugins using the simple_history_log action.', 'simple-history' ),
				'contexts' => [ 'sidebar' ],
			],
			$is_premium_active
				? [
					'text'     => __( 'Pin important events with "Sticky" so they don\'t scroll away.', 'simple-history' ),
					'contexts' => [ 'sidebar', 'dashboard' ],
				]
				: [
					'text'     => __( 'Pin important events so they don\'t scroll away. Available with Simple History Premium.', 'simple-history' ),
					'contexts' => [ 'sidebar', 'dashboard' ],
				],
			$is_premium_active
				? [
					'text'     => __( 'Set up alerts in Settings to get notified when important events happen.', 'simple-history' ),
					'contexts' => [ 'sidebar', 'dashboard' ],
				]
				: [
					'text'     => __( 'Get instant alerts when important events happen. Available with Simple History Premium.', 'simple-history' ),
					'contexts' => [ 'sidebar', 'dashboard' ],
				],
			$is_premium_active
				? [
					'text'     => __( 'Use Message Control in Settings to choose exactly which events get logged.', 'simple-history' ),
					'contexts' => [ 'sidebar', 'dashboard' ],
				]
				: [
					'text'     => __( 'Control exactly which events get logged. Available with Simple History Premium.', 'simple-history' ),
					'contexts' => [ 'sidebar', 'dashboard' ],
				],
		];

		if ( ! $is_premium_active ) {
			$tips[] = [
				'text'     => __( 'Need a longer history? Simple History Premium stores up to a full year of events.', 'simple-history' ),
				'contexts' => [ 'sidebar', 'dashboard' ],
			];
		}

		/**
		 * Filter the structured list of tips.
		 *
		 * @since 5.27.0
		 *
		 * @param array<int, array{text: string, contexts: string[]}> $tips Structured tip list.
		 */
		return apply_filters( 'simple_history/tips', $tips );
	}

	/**
	 * Get tip strings filtered for a given context.
	 *
	 * @param string $context Context name, e.g. 'sidebar' or 'dashboard'.
	 * @return string[] Tip texts applicable to the context.
	 */
	public function get_tips_for_context( $context ) {
		$all_tips = $this->get_all_tips();

		$tips = [];
		foreach ( $all_tips as $tip ) {
			if ( ! in_array( $context, $tip['contexts'], true ) ) {
				continue;
			}

			$tips[] = $tip['text'];
		}

		if ( $context === 'sidebar' ) {
			/**
			 * Filter the list of sidebar tips.
			 *
			 * For tips that should appear on multiple surfaces (e.g. sidebar and dashboard),
			 * use the structured `simple_history/tips` filter instead.
			 *
			 * @since 5.24.0
			 *
			 * @param string[] $tips Array of tip strings.
			 */
			$tips = apply_filters( 'simple_history/sidebar_tips', $tips );
		}

		return $tips;
	}

	/**
	 * Expose tips to the React bundle so the dashboard widget can render them.
	 */
	public function localize_tips_for_react() {
		if ( ! wp_script_is( 'simple_history_wp_scripts', 'registered' ) ) {
			return;
		}

		wp_localize_script(
			'simple_history_wp_scripts',
			'simpleHistoryTips',
			[
				'dashboard' => $this->get_tips_for_context( 'dashboard' ),
			]
		);
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

		$tips = $this->get_tips_for_context( 'sidebar' );

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
