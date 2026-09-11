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
	 * - contexts: string[] Surfaces where the tip should appear (e.g. 'sidebar', 'dashboard', 'email').
	 * - triggers: string[] Optional. Report counter names that make this tip relevant in the weekly summary email.
	 *
	 * @return array<int, array{text: string, contexts: string[], triggers?: string[]}> Structured tip list.
	 */
	private function get_all_tips() {
		$is_premium_active = Helpers::is_premium_add_on_active();

		$tips = [
			[
				'text'     => __( 'Subscribe to your activity log via RSS — enable it under Simple History > Settings.', 'simple-history' ),
				'contexts' => [ 'sidebar', 'email' ],
			],
			[
				'text'     => __( 'Get a weekly email digest of your site\'s activity — it\'s an easy way to catch changes that happened while you were away. Enable it under Simple History > Settings.', 'simple-history' ),
				'contexts' => [ 'sidebar', 'dashboard' ],
			],
			[
				'text'     => __( 'Use "wp simple-history list" to view your activity log from the terminal.', 'simple-history' ),
				'contexts' => [ 'sidebar', 'email' ],
			],
			[
				'text'     => __( 'Export your event log as CSV, JSON, or HTML — find it under Simple History > Export & Tools.', 'simple-history' ),
				'contexts' => [ 'sidebar', 'dashboard', 'email' ],
			],
			[
				'text'     => __( 'Use "Show surrounding events" to see what happened right before and after any event.', 'simple-history' ),
				'contexts' => [ 'sidebar', 'dashboard', 'email' ],
			],
			[
				'text'     => __( 'In the block editor, press Cmd+K (Ctrl+K on Windows) and type "history" to see that post\'s activity.', 'simple-history' ),
				'contexts' => [ 'sidebar', 'email' ],
			],
			[
				'text'     => __( 'Use the Quick View dropdown in the admin bar to see recent events without leaving your page.', 'simple-history' ),
				'contexts' => [ 'sidebar', 'dashboard', 'email' ],
			],
			$is_premium_active
				? [
					'text'     => __( 'Click a user\'s name or avatar on any event to see their details and view all their activity.', 'simple-history' ),
					'contexts' => [ 'sidebar', 'email' ],
				]
				: [
					'text'     => __( 'Click a user\'s name or avatar on any event to see their details and open their profile.', 'simple-history' ),
					'contexts' => [ 'sidebar', 'email' ],
				],
			[
				'text'     => __( 'Press "/" anywhere on the event log page to jump straight to the search field.', 'simple-history' ),
				'contexts' => [ 'sidebar', 'email' ],
			],
			[
				'text'     => __( 'Use "Hide my own events" in search filters to focus on what others did.', 'simple-history' ),
				'contexts' => [ 'sidebar', 'email' ],
			],
			[
				'text'     => __( 'Developers can log custom events from themes and plugins using the simple_history_log action.', 'simple-history' ),
				'contexts' => [ 'sidebar', 'email' ],
			],
			[
				'text'     => __( 'Developers can fetch events over the REST API — try the /wp-json/simple-history/v1/events endpoint.', 'simple-history' ),
				'contexts' => [ 'sidebar', 'email' ],
			],
			[
				'text'     => __( 'Site acting strangely? Check the log — what changed right before often points straight to the cause.', 'simple-history' ),
				'contexts' => [ 'sidebar', 'dashboard', 'email' ],
				'triggers' => [ 'plugin_activations', 'plugin_deactivations', 'theme_switches', 'theme_updates', 'wordpress_updates' ],
			],
			[
				'text'     => __( 'After updating plugins, the log remembers exactly what was updated and when — handy if something breaks days later.', 'simple-history' ),
				'contexts' => [ 'sidebar', 'dashboard', 'email' ],
				'triggers' => [ 'plugin_activations', 'plugin_deactivations' ],
			],
			[
				'text'     => __( 'Repeated failed login attempts show up in the log — useful if you suspect someone is trying to get in.', 'simple-history' ),
				'contexts' => [ 'sidebar', 'dashboard', 'email' ],
				'triggers' => [ 'failed_logins' ],
			],
			[
				'text'     => __( 'Spot an admin user you don\'t recognize? User creations and role changes are always logged.', 'simple-history' ),
				'contexts' => [ 'sidebar', 'dashboard', 'email' ],
				'triggers' => [ 'users_created' ],
			],
			[
				'text'     => __( 'Working with a team or clients? The log shows who changed what, so there\'s no guessing when something looks different.', 'simple-history' ),
				'contexts' => [ 'sidebar', 'dashboard', 'email' ],
			],
			[
				'text'     => __( 'Handing over a site? The log gives the next person a head start on understanding what\'s been happening.', 'simple-history' ),
				'contexts' => [ 'sidebar', 'dashboard', 'email' ],
			],
			[
				'text'     => __( 'IP addresses in the log are anonymized by default, balancing accountability with user privacy.', 'simple-history' ),
				'contexts' => [ 'sidebar', 'dashboard', 'email' ],
			],
			$is_premium_active
				? [
					'text'     => __( 'Pin important events with "Sticky" so they don\'t scroll away.', 'simple-history' ),
					'contexts' => [ 'sidebar', 'dashboard', 'email' ],
				]
				: [
					'text'     => __( 'Want to pin important events so they don\'t scroll away? That\'s part of Simple History Premium.', 'simple-history' ),
					'contexts' => [ 'sidebar', 'dashboard' ],
				],
			$is_premium_active
				? [
					'text'     => __( 'Set up alerts in Simple History > Settings to get notified when specific events happen.', 'simple-history' ),
					'contexts' => [ 'sidebar', 'dashboard', 'email' ],
					'triggers' => [ 'failed_logins' ],
				]
				: [
					'text'     => __( 'Want instant alerts when specific events happen? That\'s part of Simple History Premium.', 'simple-history' ),
					'contexts' => [ 'sidebar', 'dashboard' ],
				],
			$is_premium_active
				? [
					'text'     => __( 'Use Message Control in Settings to choose exactly which events get logged.', 'simple-history' ),
					'contexts' => [ 'sidebar', 'dashboard', 'email' ],
				]
				: [
					'text'     => __( 'Want to control exactly which events get logged? That\'s part of Simple History Premium.', 'simple-history' ),
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
		 * @param array<int, array{text: string, contexts: string[], triggers?: string[]}> $tips Structured tip list.
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
	 * Pick the tip to show in the weekly summary email.
	 *
	 * Tips whose triggers match activity in the report are preferred, so the
	 * tip relates to what actually happened. The pick rotates by week number
	 * so a site with the same activity every week still sees different tips.
	 * Free users get only untriggered tips, because the email's premium teaser
	 * already covers the data-driven angle.
	 *
	 * @param array $args Email template args, including the report counters.
	 * @return string Tip text, or empty string when no tip applies.
	 */
	public function get_tip_for_email( $args ) {
		$all_tips = $this->get_all_tips();

		$email_tips = [];

		foreach ( $all_tips as $tip ) {
			if ( ! in_array( 'email', $tip['contexts'], true ) ) {
				continue;
			}

			$email_tips[] = $tip;
		}

		$triggered   = [];
		$untriggered = [];

		foreach ( $email_tips as $tip ) {
			if ( empty( $tip['triggers'] ) ) {
				$untriggered[] = $tip;
				continue;
			}

			foreach ( $tip['triggers'] as $trigger_key ) {
				if ( (int) ( $args[ $trigger_key ] ?? 0 ) > 0 ) {
					$triggered[] = $tip;
					break;
				}
			}
		}

		if ( Helpers::is_premium_add_on_active() ) {
			$pool = ! empty( $triggered ) ? $triggered : $email_tips;
		} else {
			$pool = $untriggered;
		}

		if ( empty( $pool ) ) {
			return '';
		}

		$pool = array_values( $pool );
		$week = $this->get_week_index( $args );

		$tip_text = $pool[ $week % count( $pool ) ]['text'];

		/**
		 * Filter the tip text shown in the weekly summary email.
		 *
		 * @since 5.33.0
		 *
		 * @param string $tip_text Tip text.
		 * @param array  $args Email template args.
		 */
		return apply_filters( 'simple_history/email_summary_report/tip_text', $tip_text, $args );
	}

	/**
	 * Week index used to rotate weekly picks, based on the report's end date
	 * so the preview and the real email agree.
	 *
	 * @param array $args Email template args.
	 * @return int Monotonic week number.
	 */
	public function get_week_index( $args ) {
		$timestamp = (int) ( $args['date_to_timestamp'] ?? 0 );

		if ( $timestamp === 0 ) {
			$timestamp = time();
		}

		return (int) gmdate( 'o', $timestamp ) * 53 + (int) gmdate( 'W', $timestamp );
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
