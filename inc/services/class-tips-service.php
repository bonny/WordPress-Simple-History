<?php

namespace Simple_History\Services;

use Simple_History\Helpers;
use Simple_History\Dropins\Sidebar_Add_Ons_Dropin;

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
	 * - link_text: string Optional. Label for a link shown after the text. Must make sense on its own,
	 *   since screen reader users navigate links out of context.
	 * - link_url: string Optional. Untracked destination. UTM parameters are added per context in
	 *   get_tips_for_context(), so the same tip can be measured separately in the sidebar and the dashboard.
	 * - link_campaign: string Optional. UTM campaign for documentation links. Premium tips leave this
	 *   unset and get a campaign derived from the context instead.
	 * - is_premium: bool Optional. True for the teasers that pitch Premium to free users. Used to keep
	 *   them from stacking with the premium card in the sidebar.
	 *
	 * Links never render in the weekly email — see get_tip_for_email().
	 *
	 * @return array<int, array{text: string, contexts: string[], triggers?: string[], link_text?: string, link_url?: string, link_campaign?: string, is_premium?: bool}> Structured tip list.
	 */
	private function get_all_tips() {
		$is_premium_active = Helpers::is_premium_add_on_active();

		$show_premium_tips = Helpers::show_promo_boxes();

		$tips = [
			[
				'text'          => __( 'Subscribe to your activity log via RSS — enable it under Simple History > Settings.', 'simple-history' ),
				'contexts'      => [ 'sidebar', 'email' ],
				'link_text'     => __( 'How RSS feeds work', 'simple-history' ),
				'link_url'      => 'https://simple-history.com/docs/feeds/',
				'link_campaign' => 'docs_rss_help',
			],
			[
				'text'          => __( 'Get a weekly email digest of your site\'s activity — it\'s an easy way to catch changes that happened while you were away. Enable it under Simple History > Settings.', 'simple-history' ),
				'contexts'      => [ 'sidebar', 'dashboard' ],
				'link_text'     => __( 'About the weekly email report', 'simple-history' ),
				'link_url'      => 'https://simple-history.com/support/weekly-email-report/',
				'link_campaign' => 'docs_tip_weekly_email',
			],
			[
				'text'          => __( 'Use "wp simple-history list" to view your activity log from the terminal.', 'simple-history' ),
				'contexts'      => [ 'sidebar', 'email' ],
				'link_text'     => __( 'All WP-CLI commands', 'simple-history' ),
				'link_url'      => 'https://simple-history.com/features/wp-cli-commands/',
				'link_campaign' => 'docs_tip_wpcli',
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
				'text'          => __( 'Use the Quick View dropdown in the admin bar to see recent events without leaving your page.', 'simple-history' ),
				'contexts'      => [ 'sidebar', 'dashboard', 'email' ],
				'link_text'     => __( 'About Quick View', 'simple-history' ),
				'link_url'      => 'https://simple-history.com/features/admin-bar-quick-view/',
				'link_campaign' => 'docs_tip_quick_view',
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
				'text'          => __( 'Developers can log custom events from themes and plugins using the simple_history_log action.', 'simple-history' ),
				'contexts'      => [ 'sidebar', 'email' ],
				'link_text'     => __( 'The logging API', 'simple-history' ),
				'link_url'      => 'https://simple-history.com/docs/logging-api/',
				'link_campaign' => 'docs_tip_logging_api',
			],
			[
				'text'          => __( 'Developers can fetch events over the REST API — try the /wp-json/simple-history/v1/events endpoint.', 'simple-history' ),
				'contexts'      => [ 'sidebar', 'email' ],
				'link_text'     => __( 'REST API endpoints', 'simple-history' ),
				'link_url'      => 'https://simple-history.com/docs/rest-api-endpoints/',
				'link_campaign' => 'docs_tip_rest_api',
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
			// Links to the privacy documentation rather than to Premium, on purpose.
			// The anonymization itself is core, switched with the free
			// simple_history/privacy/anonymize_ip_address filter, so a Premium pitch
			// here would read as gating something that is not gated.
			[
				'text'          => __( 'IP addresses in the log are anonymized by default, balancing accountability with user privacy.', 'simple-history' ),
				'contexts'      => [ 'sidebar', 'dashboard', 'email' ],
				'link_text'     => __( 'Privacy and GDPR', 'simple-history' ),
				'link_url'      => 'https://simple-history.com/support/gdpr-and-privacy/',
				'link_campaign' => 'docs_tip_privacy',
			],
		];

		if ( $is_premium_active ) {
			// Premium features, as instructions for someone who already has them.
			$tips = array_merge(
				$tips,
				[
					[
						'text'     => __( 'Pin important events with "Sticky" so they don\'t scroll away.', 'simple-history' ),
						'contexts' => [ 'sidebar', 'dashboard', 'email' ],
					],
					[
						'text'     => __( 'Set up alerts in Simple History > Settings to get notified when specific events happen.', 'simple-history' ),
						'contexts' => [ 'sidebar', 'dashboard', 'email' ],
						'triggers' => [ 'failed_logins' ],
					],
					[
						'text'     => __( 'Use Message Control in Settings to choose exactly which events get logged.', 'simple-history' ),
						'contexts' => [ 'sidebar', 'dashboard', 'email' ],
					],
				]
			);
		} elseif ( $show_premium_tips ) {
			// The same features as teasers. These are promotional, so they follow the
			// same rule as every other promo surface and are left out when Extended
			// Settings is active or a site has turned promo boxes off.
			$tips = array_merge(
				$tips,
				[
					[
						'text'       => __( 'Want to pin important events so they don\'t scroll away? That\'s part of Simple History Premium.', 'simple-history' ),
						'contexts'   => [ 'sidebar', 'dashboard' ],
						'link_text'  => __( 'About sticky events', 'simple-history' ),
						'link_url'   => 'https://simple-history.com/add-ons/premium/#sticky-events',
						'is_premium' => true,
					],
					[
						'text'       => __( 'Want instant alerts when specific events happen? That\'s part of Simple History Premium.', 'simple-history' ),
						'contexts'   => [ 'sidebar', 'dashboard' ],
						'link_text'  => __( 'About alerts', 'simple-history' ),
						'link_url'   => 'https://simple-history.com/add-ons/premium/#alerts',
						'is_premium' => true,
					],
					[
						'text'       => __( 'Want to control exactly which events get logged? That\'s part of Simple History Premium.', 'simple-history' ),
						'contexts'   => [ 'sidebar', 'dashboard' ],
						'link_text'  => __( 'About message control', 'simple-history' ),
						'link_url'   => 'https://simple-history.com/add-ons/premium/#message-control',
						'is_premium' => true,
					],
					[
						'text'       => __( 'Need a longer history? Simple History Premium stores up to a full year of events.', 'simple-history' ),
						'contexts'   => [ 'sidebar', 'dashboard' ],
						'link_text'  => __( 'About log retention', 'simple-history' ),
						'link_url'   => 'https://simple-history.com/add-ons/premium/#log-retention',
						'is_premium' => true,
					],
				]
			);
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
	 * Get tips applicable to a given context, with link URLs resolved.
	 *
	 * Premium tips get a per-context campaign so the sidebar and the dashboard
	 * can be told apart in the funnel reports; documentation links carry their
	 * own campaign and are the same on every surface.
	 *
	 * @param string $context Context name, e.g. 'sidebar' or 'dashboard'.
	 * @return array<int, array{text: string, link_text?: string, link_url?: string, is_premium?: bool}> Tips applicable to the context.
	 */
	public function get_tips_for_context( $context ) {
		$all_tips = $this->get_all_tips();

		$tips = [];
		foreach ( $all_tips as $tip ) {
			if ( ! in_array( $context, $tip['contexts'], true ) ) {
				continue;
			}

			$tips[] = $this->prepare_tip_for_output( $tip, $context );
		}

		if ( $context === 'sidebar' ) {
			// The premium card renders into this same sidebar at priority 25. When it is
			// there, drop the premium tips so a free user does not get the card and a
			// premium tip on one screen. The instructional tips stay, so a tip still shows.
			//
			// This runs before the filter below on purpose: that filter only sees texts,
			// so anything it returns has lost is_premium and could no longer be suppressed.
			if ( $this->premium_card_shows_in_sidebar() ) {
				$tips = array_values(
					array_filter(
						$tips,
						function ( $tip ) {
							return empty( $tip['is_premium'] );
						}
					)
				);
			}

			$texts = wp_list_pluck( $tips, 'text' );

			/**
			 * Filter the list of sidebar tips.
			 *
			 * For tips that should appear on multiple surfaces (e.g. sidebar and dashboard),
			 * or that need a link, use the structured `simple_history/tips` filter instead.
			 *
			 * @since 5.24.0
			 *
			 * @param string[] $tips Array of tip strings.
			 */
			$filtered_texts = apply_filters( 'simple_history/sidebar_tips', $texts );

			// A callback that changed the list only ever saw the texts, so it cannot
			// have expressed an opinion about the links. Fall back to plain tips
			// rather than pairing its strings with links that belonged to other tips.
			if ( $filtered_texts !== $texts ) {
				$tips = [];

				foreach ( (array) $filtered_texts as $text ) {
					$tips[] = [ 'text' => $text ];
				}
			}
		}

		return $tips;
	}

	/**
	 * Whether the compact premium card is on screen in the sidebar.
	 *
	 * Asks the dropin that renders it, and first that the dropin is loaded at all —
	 * it can be switched off with simple_history/dropin/instantiate_Sidebar_Add_Ons_Dropin,
	 * and on such a site there is no card to stack with.
	 *
	 * @return bool True when the card shows.
	 */
	private function premium_card_shows_in_sidebar() {
		$dropin = $this->simple_history->get_instantiated_dropin_by_slug( 'Sidebar_Add_Ons_Dropin' );

		if ( ! $dropin ) {
			return false;
		}

		return Sidebar_Add_Ons_Dropin::should_show_premium_promo_compact();
	}

	/**
	 * Reduce a tip to what a surface needs to render it, resolving the link URL.
	 *
	 * @param array  $tip     Tip as defined in get_all_tips().
	 * @param string $context Context name, e.g. 'sidebar' or 'dashboard'.
	 * @return array{text: string, link_text?: string, link_url?: string, is_premium?: bool} Tip ready for output.
	 */
	private function prepare_tip_for_output( $tip, $context ) {
		$prepared = [ 'text' => $tip['text'] ];

		if ( empty( $tip['link_text'] ) || empty( $tip['link_url'] ) ) {
			return $prepared;
		}

		$is_premium = ! empty( $tip['is_premium'] );

		// Premium tips are measured per surface, since the sidebar and the dashboard
		// carry different competing teasers. Documentation links bring their own
		// campaign so they stay out of the premium funnel reports.
		$campaign = $is_premium
			? 'premium_' . $context . '_tip'
			: ( $tip['link_campaign'] ?? '' );

		if ( $campaign === '' ) {
			$prepared['link_url'] = $tip['link_url'];
		} else {
			$prepared['link_url'] = Helpers::get_tracking_url( $tip['link_url'], $campaign );
		}

		$prepared['link_text'] = $tip['link_text'];

		if ( $is_premium ) {
			$prepared['is_premium'] = true;
		}

		return $prepared;
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
				<?php echo esc_html( $tip['text'] ); ?>
				<?php if ( ! empty( $tip['link_text'] ) && ! empty( $tip['link_url'] ) ) { ?>
					<a class="sh-SidebarTip-link" href="<?php echo esc_url( $tip['link_url'] ); ?>"><?php echo esc_html( $tip['link_text'] ); ?></a>
				<?php } ?>
			</p>
		</div>
		<?php
	}
}
