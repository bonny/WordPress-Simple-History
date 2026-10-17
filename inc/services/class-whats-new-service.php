<?php

namespace Simple_History\Services;

use Simple_History\AddOn_Plugin;
use Simple_History\Changelog_Parser;
use Simple_History\Helpers;

/**
 * "What's new" box, billing messages and the lapsed-license card.
 *
 * Experimental. Everything shows on the Simple History log page only:
 *
 * - The top slot, between the page title and the filters, shows one message
 *   at a time: a billing message for Premium (payment failed, subscription
 *   ending) or else the "What's new in Simple History" box after an update.
 * - The sidebar shows a card to sites whose Premium license has expired,
 *   listing the Premium features released since their version.
 *
 * Nothing here makes a remote request or runs on a schedule. Counts come from
 * the plugins' own readme.txt,
 * and license and subscription data from the existing update check.
 */
class Whats_New_Service extends Service {
	/** Slug of the Premium add-on. */
	const PREMIUM_SLUG = 'simple-history-premium';

	/** User meta: per plugin, the version the user has seen and how many times it was shown. */
	const META_SEEN = 'simple_history_whats_new_seen';

	/** User meta: key of the billing message the user dismissed. */
	const META_BILLING_DISMISSED = 'simple_history_billing_message_dismissed';

	/** User meta: lapse stages whose card the user dismissed. */
	const META_LAPSED_DISMISSED = 'simple_history_lapsed_card_dismissed';

	/** Option: parsed changelogs, per plugin and version. Not autoloaded. */
	const CHANGELOG_CACHE_OPTION = 'simple_history_whats_new_changelogs';

	/** AJAX action for dismissals. */
	const AJAX_ACTION = 'simple_history_whats_new';

	/** Nonce action for dismissals. */
	const NONCE_ACTION = 'simple_history_whats_new';

	/** How many times the "What's new" box shows before it counts as seen. */
	const MAX_VIEWS = 3;

	/** Lemon Squeezy's customer billing portal. */
	const BILLING_PORTAL_URL = 'https://simple-history.lemonsqueezy.com/billing';

	/** Minimum number of new Premium features for the lapsed card to show. */
	const MIN_LAPSED_FEATURES = 3;

	/** @inheritdoc */
	public function loaded() {
		if ( ! Helpers::experimental_features_is_enabled() ) {
			return;
		}

		add_action( 'simple_history/history_page/before_gui', [ $this, 'output_top_slot' ] );
		add_action( 'simple_history/dropin/sidebar/sidebar_html', [ $this, 'output_lapsed_card' ], 5 );
		add_action( 'wp_ajax_' . self::AJAX_ACTION, [ $this, 'handle_ajax' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );

		if ( ! Helpers::dev_mode_is_enabled() ) {
			return;
		}

		( new Whats_New_Demo() )->init();
	}

	/**
	 * Output the top slot: a billing message, or else the "What's new" box.
	 *
	 * A render error must never break the log page.
	 */
	public function output_top_slot() {
		try {
			$billing_message = $this->get_billing_message();

			if ( $billing_message !== null ) {
				$this->render_billing_message( $billing_message );

				return;
			}

			$updates = $this->get_pending_updates( get_current_user_id() );

			if ( $updates !== [] ) {
				$this->render_whats_new_box( $updates );
				$this->record_view( get_current_user_id(), $updates );
			}
		} catch ( \Throwable $error ) {
			$this->log_error( 'Simple History: what\'s new render failed: ' . $error->getMessage() );
		}
	}

	/**
	 * Log a render error at most once an hour, so a lasting problem does not
	 * fill the error log with one line per page view.
	 *
	 * @param string $message Message.
	 */
	private function log_error( $message ) {
		if ( get_transient( 'simple_history_whats_new_error_logged' ) ) {
			return;
		}

		set_transient( 'simple_history_whats_new_error_logged', 1, HOUR_IN_SECONDS );

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( $message );
	}

	/**
	 * Output the sidebar card for an expired Premium license.
	 */
	public function output_lapsed_card() {
		try {
			$card = $this->get_lapsed_card();

			if ( $card !== null ) {
				$this->render_lapsed_card( $card );
			}
		} catch ( \Throwable $error ) {
			$this->log_error( 'Simple History: lapsed card render failed: ' . $error->getMessage() );
		}
	}

	/**
	 * The plugins the box reports on: core, and Premium when it's installed.
	 *
	 * @return array<string,array{slug:string,name:string,version:string,readme:string}> Keyed by slug.
	 */
	public function get_plugins() {
		$plugins = [
			'simple-history' => [
				'slug'    => 'simple-history',
				'name'    => 'Simple History',
				'version' => SIMPLE_HISTORY_VERSION,
				'readme'  => SIMPLE_HISTORY_PATH . 'readme.txt',
			],
		];

		$premium = $this->get_premium_add_on();

		if ( $premium !== null ) {
			$plugins[ self::PREMIUM_SLUG ] = [
				'slug'    => self::PREMIUM_SLUG,
				'name'    => 'Premium',
				'version' => $premium->version,
				'readme'  => WP_PLUGIN_DIR . '/' . self::PREMIUM_SLUG . '/readme.txt',
			];
		}

		/**
		 * Filter the plugins the "What's new" box reports on.
		 *
		 * @param array<string,array{slug:string,name:string,version:string,readme:string}> $plugins Plugins keyed by slug.
		 */
		return apply_filters( 'simple_history/whats_new/plugins', $plugins );
	}

	/**
	 * Updates the user hasn't seen yet, one entry per plugin.
	 *
	 * The first time a plugin is seen for a user (new user, first release
	 * with this feature, Premium installed later), its installed version is
	 * stored as seen and nothing shows: the box starts at the next update. A
	 * downgrade shows nothing either.
	 *
	 * @param int $user_id User ID.
	 * @return array<string,array<string,mixed>> Keyed by slug.
	 */
	public function get_pending_updates( $user_id ) {
		$seen    = $this->get_seen( $user_id );
		$updates = [];
		$changed = false;

		foreach ( $this->get_plugins() as $slug => $plugin ) {
			if ( ! isset( $seen[ $slug ]['version'] ) ) {
				$seen[ $slug ] = [
					'version' => $plugin['version'],
					'views'   => 0,
				];
				$changed       = true;

				continue;
			}

			if ( version_compare( $plugin['version'], $seen[ $slug ]['version'], '<=' ) ) {
				continue;
			}

			$summary = Changelog_Parser::summarize( $this->get_changelog( $plugin ), $seen[ $slug ]['version'], $plugin['version'] );

			// Nothing to say when the changelog lacks these versions or none of
			// their entries could be read (a readme in an unexpected format).
			if ( $summary['versions'] === [] || $summary['features'] + $summary['fixes'] + $summary['other'] === 0 ) {
				continue;
			}

			$updates[ $slug ] = [
				'slug'    => $slug,
				'name'    => $plugin['name'],
				'version' => $plugin['version'],
				'summary' => $summary,
			];
		}

		if ( $changed ) {
			update_user_meta( $user_id, self::META_SEEN, $seen );
		}

		return $updates;
	}

	/**
	 * Count one showing of the box. After MAX_VIEWS showings it counts as seen.
	 *
	 * @param int                               $user_id User ID.
	 * @param array<string,array<string,mixed>> $updates Updates that were shown.
	 */
	private function record_view( $user_id, $updates ) {
		$seen = $this->get_seen( $user_id );

		foreach ( $updates as $slug => $update ) {
			// Views count for one installed version. A newer update starts over,
			// so it gets its own MAX_VIEWS showings.
			$counted_for = $seen[ $slug ]['views_for'] ?? '';
			$views       = ( $counted_for === $update['version'] ? (int) ( $seen[ $slug ]['views'] ?? 0 ) : 0 ) + 1;

			$seen[ $slug ] = $views >= self::MAX_VIEWS
				? [
					'version' => $update['version'],
					'views'   => 0,
				]
				: [
					'version'   => $seen[ $slug ]['version'],
					'views'     => $views,
					'views_for' => $update['version'],
				];
		}

		update_user_meta( $user_id, self::META_SEEN, $seen );
	}

	/**
	 * Mark every plugin's installed version as seen.
	 *
	 * @param int $user_id User ID.
	 */
	public function mark_all_seen( $user_id ) {
		$seen = $this->get_seen( $user_id );

		foreach ( $this->get_plugins() as $slug => $plugin ) {
			$seen[ $slug ] = [
				'version' => $plugin['version'],
				'views'   => 0,
			];
		}

		update_user_meta( $user_id, self::META_SEEN, $seen );
	}

	/**
	 * Get the stored seen state of a user.
	 *
	 * @param int $user_id User ID.
	 * @return array<string,array{version:string,views:int,views_for?:string}>
	 */
	private function get_seen( $user_id ) {
		$seen = get_user_meta( $user_id, self::META_SEEN, true );

		return is_array( $seen ) ? $seen : [];
	}

	/**
	 * Parsed changelog of a plugin, parsed once per installed version.
	 *
	 * The cache knows which version it was parsed for, so an update re-parses
	 * on the next view without an upgrade hook.
	 *
	 * @param array{slug:string,version:string,readme:string} $plugin Plugin.
	 * @return array<string,array<string,mixed>> Parsed versions.
	 */
	private function get_changelog( $plugin ) {
		$cache = get_option( self::CHANGELOG_CACHE_OPTION, [] );
		$cache = is_array( $cache ) ? $cache : [];

		$cached = $cache[ $plugin['slug'] ] ?? null;

		if ( is_array( $cached ) && ( $cached['version'] ?? '' ) === $plugin['version'] && is_array( $cached['versions'] ?? null ) ) {
			return $cached['versions'];
		}

		$text = is_readable( $plugin['readme'] ) ? file_get_contents( $plugin['readme'] ) : '';

		// Keep only the versions the box can ever need, newest first, and of
		// the Added entries only their names: the box counts them.
		$versions = array_slice( Changelog_Parser::parse( $text ), 0, 20, true );

		foreach ( $versions as $version => $data ) {
			$versions[ $version ]['added'] = array_column( $data['added'], 'name' );
		}

		$cache[ $plugin['slug'] ] = [
			'version'  => $plugin['version'],
			'versions' => $versions,
		];

		update_option( self::CHANGELOG_CACHE_OPTION, $cache, false );

		return $versions;
	}

	/**
	 * The one-line summary of a plugin's update, like
	 * "Simple History 5.35.0: 4 new features and 9 fixes.".
	 *
	 * @param array<string,mixed> $update Update from get_pending_updates().
	 * @return string
	 */
	public function get_update_line( $update ) {
		$label = $update['name'] . ' ' . $update['version'];

		/* translators: 1: plugin name and version, e.g. "Simple History 5.35.0", 2: e.g. "4 new features and 9 fixes.". */
		return sprintf( __( '%1$s: %2$s', 'simple-history' ), $label, $this->get_counts_text( $update['summary'] ) );
	}

	/**
	 * The billing message to show, if any.
	 *
	 * Only for users who can manage options, never on multisite in this
	 * version, and only while the user hasn't dismissed this exact state.
	 *
	 * @return array{type:string,key:string,ends_timestamp:?int}|null
	 */
	public function get_billing_message() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return null;
		}

		$message = $this->get_billing_state();

		if ( $message === null ) {
			return null;
		}

		if ( get_user_meta( get_current_user_id(), self::META_BILLING_DISMISSED, true ) === $message['key'] ) {
			return null;
		}

		return $message;
	}

	/**
	 * The billing state behind the messages, ignoring who looks and what they dismissed.
	 *
	 * @return array{type:string,key:string,ends_timestamp:?int}|null
	 */
	private function get_billing_state() {
		$state = null;

		if ( ! is_multisite() ) {
			$premium      = $this->get_premium_add_on();
			$subscription = $premium !== null ? $premium->get_license_state()['subscription'] : null;

			if ( is_array( $subscription ) ) {
				$ends_timestamp = $subscription['ends_at'] !== null ? strtotime( $subscription['ends_at'] ) : false;

				if ( in_array( $subscription['status'], [ 'past_due', 'unpaid' ], true ) ) {
					$state = [
						'type'           => 'payment_failed',
						'key'            => 'payment_failed:' . $subscription['status'],
						'ends_timestamp' => null,
					];
				} elseif ( $subscription['cancelled'] && $ends_timestamp !== false && $ends_timestamp > time() ) {
					$state = [
						'type'           => 'ends',
						'key'            => 'ends:' . $subscription['ends_at'],
						'ends_timestamp' => $ends_timestamp,
					];
				}
			}
		}

		/**
		 * Filter the billing state that decides the billing message.
		 *
		 * @param array{type:string,key:string,ends_timestamp:?int}|null $state Billing state, or null for none.
		 */
		return apply_filters( 'simple_history/whats_new/billing_state', $state );
	}

	/**
	 * The lapsed-license card to show, if any.
	 *
	 * @return array{stage:string,features:array<int,array<string,mixed>>,expires_timestamp:int}|null
	 */
	public function get_lapsed_card() {
		// phpcs:ignore WordPress.WP.Capabilities.Undetermined -- Dynamic capability from Helpers::get_view_settings_capability(), filterable.
		if ( ! current_user_can( Helpers::get_view_settings_capability() ) ) {
			return null;
		}

		// A billing problem has its own message; don't also suggest buying.
		if ( $this->get_billing_state() !== null ) {
			return null;
		}

		$lapse = $this->get_lapse();

		if ( $lapse === null || count( $lapse['features'] ) < self::MIN_LAPSED_FEATURES ) {
			return null;
		}

		$days  = (int) floor( ( time() - $lapse['expires_timestamp'] ) / DAY_IN_SECONDS );
		$stage = $days < 90 ? 'recent' : ( $days <= 365 ? 'year' : 'old' );

		$dismissed = get_user_meta( get_current_user_id(), self::META_LAPSED_DISMISSED, true );

		if ( is_array( $dismissed ) && ! empty( $dismissed[ $stage ] ) ) {
			return null;
		}

		return [
			'stage'             => $stage,
			'features'          => $lapse['features'],
			'expires_timestamp' => $lapse['expires_timestamp'],
		];
	}

	/**
	 * When the Premium license expired, and the features released since the installed version.
	 *
	 * @return array{expires_timestamp:int,features:array<int,array<string,mixed>>}|null
	 */
	private function get_lapse() {
		$lapse   = null;
		$premium = is_multisite() ? null : $this->get_premium_add_on();

		if ( $premium !== null ) {
			$state = $premium->get_license_state();

			// Only a real update check counts; an activation-time date is stale.
			if ( $state['state'] === 'expired' && $state['source'] === 'update_check' && $state['expires_timestamp'] !== null ) {
				$lapse = [
					'expires_timestamp' => $state['expires_timestamp'],
					'features'          => $premium->get_new_features( $premium->version ),
				];
			}
		}

		/**
		 * Filter the lapsed-license data behind the sidebar card.
		 *
		 * @param array{expires_timestamp:int,features:array<int,array<string,mixed>>}|null $lapse Lapse data, or null for none.
		 */
		return apply_filters( 'simple_history/whats_new/lapse', $lapse );
	}

	/**
	 * Get the registered Premium add-on.
	 *
	 * @return AddOn_Plugin|null
	 */
	protected function get_premium_add_on() {
		$licences = $this->simple_history->get_service( AddOns_Licences::class );

		if ( ! $licences instanceof AddOns_Licences ) {
			return null;
		}

		$premium = $licences->get_plugin( self::PREMIUM_SLUG );

		return $premium instanceof AddOn_Plugin ? $premium : null;
	}

	/**
	 * Output the "What's new" box, titled "Updated to Simple History 5.36.0".
	 *
	 * @param array<string,array<string,mixed>> $updates Updates from get_pending_updates().
	 */
	private function render_whats_new_box( $updates ) {
		?>
		<section class="sh-WhatsNew" data-sh-whats-new-kind="whats_new" aria-labelledby="sh-WhatsNew-title">
			<?php
			// The first line names the update in bold, the rest follow below it.
			$title = $this->get_box_title( $updates );

			foreach ( $updates as $update ) {
				$this->render_update_line( $update, $title );
				$title = null;
			}

			$this->render_dismiss_button( __( 'Dismiss what\'s new', 'simple-history' ) );
			?>
		</section>
		<?php
	}

	/**
	 * Output a line's text, led by the box's bold title on the first line.
	 *
	 * @param string      $line Line text.
	 * @param string|null $title Title, or null on the lines after the first.
	 */
	private function render_line_text( $line, $title ) {
		if ( $title !== null ) {
			?>
			<strong class="sh-WhatsNew-title" id="sh-WhatsNew-title"><?php echo esc_html( $title ); ?></strong>
			<?php
		}

		echo esc_html( $line );
	}

	/**
	 * What an update brought, like "9 new features and 15 fixes.", for the
	 * first line after the box's title.
	 *
	 * @param array<string,mixed> $summary Summary from Changelog_Parser::summarize().
	 * @return string
	 */
	public function get_counts_text( $summary ) {
		$features = (int) $summary['features'];
		$fixes    = (int) $summary['fixes'];
		$other    = (int) $summary['other'];
		$parts    = [];

		if ( $features > 0 ) {
			/* translators: %d: number of new features. */
			$parts[] = sprintf( _n( '%d new feature', '%d new features', $features, 'simple-history' ), $features );
		}

		if ( $fixes > 0 ) {
			/* translators: %d: number of fixes, security fixes included. */
			$parts[] = sprintf( _n( '%d fix', '%d fixes', $fixes, 'simple-history' ), $fixes );
		}

		if ( $other > 0 ) {
			/* translators: %d: number of other changes. */
			$parts[] = sprintf( _n( '%d other change', '%d other changes', $other, 'simple-history' ), $other );
		}

		if ( count( $parts ) === 3 ) {
			/* translators: 1: e.g. "9 new features", 2: e.g. "15 fixes", 3: e.g. "4 other changes". */
			return sprintf( __( '%1$s, %2$s and %3$s.', 'simple-history' ), ...$parts );
		}

		if ( count( $parts ) === 2 ) {
			/* translators: 1: e.g. "9 new features", 2: e.g. "15 fixes". */
			return sprintf( __( '%1$s and %2$s.', 'simple-history' ), ...$parts );
		}

		return ( $parts[0] ?? '' ) . '.';
	}

	/**
	 * The box's title, like "Updated to Simple History 5.36.0".
	 *
	 * Names core's new version when core was updated, else Premium's.
	 *
	 * @param array<string,array<string,mixed>> $updates Updates from get_pending_updates().
	 * @return string
	 */
	public function get_box_title( $updates ) {
		$update = $updates['simple-history'] ?? reset( $updates );
		$name   = $update['slug'] === 'simple-history' ? 'Simple History' : 'Simple History ' . $update['name'];

		/* translators: 1: plugin name, e.g. "Simple History" or "Simple History Premium", 2: version, e.g. "5.36.0". */
		return sprintf( __( 'Updated to %1$s %2$s', 'simple-history' ), $name, $update['version'] );
	}

	/**
	 * Output one plugin's line in the box, with a link to the newest release post.
	 *
	 * Like Claude Code's update notice: what the update brought in one line,
	 * and the release post for the details.
	 *
	 * @param array<string,mixed> $update Update from get_pending_updates().
	 * @param string|null         $title The box's title, to lead the first line; null on the others.
	 */
	private function render_update_line( $update, $title = null ) {
		$line         = $title === null ? $this->get_update_line( $update ) : $this->get_counts_text( $update['summary'] );
		$release_link = $update['summary']['release_url'];
		?>
		<p class="sh-WhatsNew-line">
			<?php $this->render_line_text( $line, $title ); ?>
			<?php if ( $release_link !== '' ) { ?>
				<a class="sh-WhatsNew-link js-sh-whats-new-seen" href="<?php echo esc_url( Helpers::get_tracking_url( $release_link, 'whats_new_box', 'wpadmin', 'plugin', $update['slug'] ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Release post →', 'simple-history' ); ?></a>
			<?php } ?>
		</p>
		<?php
	}

	/**
	 * Output a billing message.
	 *
	 * Written for someone who may not be the one who pays (an agency's
	 * client, one of several admins): no amounts, no card details, no alarm.
	 *
	 * @param array{type:string,key:string,ends_timestamp:?int} $message Billing message.
	 */
	private function render_billing_message( $message ) {
		$billing_url = self::BILLING_PORTAL_URL;
		?>
		<section class="sh-WhatsNew sh-WhatsNew--billing" data-sh-whats-new-kind="billing" data-sh-whats-new-value="<?php echo esc_attr( $message['key'] ); ?>" aria-label="<?php esc_attr_e( 'Simple History Premium subscription', 'simple-history' ); ?>">
			<span class="sh-WhatsNew-icon dashicons dashicons-info-outline" aria-hidden="true"></span>

			<div class="sh-WhatsNew-body">
				<?php if ( $message['type'] === 'payment_failed' ) { ?>
					<p><?php esc_html_e( 'The latest payment for Simple History Premium didn\'t go through. If the card has changed, you can update it in the billing portal. If someone else handles billing for this site, you may want to let them know.', 'simple-history' ); ?></p>
					<p class="sh-WhatsNew-links">
						<a href="<?php echo esc_url( $billing_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Update payment details →', 'simple-history' ); ?></a>
					</p>
				<?php } else { ?>
					<p>
						<?php
						echo esc_html(
							sprintf(
								/* translators: %s: date the subscription ends, e.g. "3 November 2026". */
								__( 'Your Simple History Premium subscription ends on %s. Premium keeps working after that, but it won\'t get new features or fixes.', 'simple-history' ),
								wp_date( get_option( 'date_format' ), (int) $message['ends_timestamp'] )
							)
						);
						?>
					</p>
					<p class="sh-WhatsNew-links">
						<a href="<?php echo esc_url( $billing_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Keep my subscription →', 'simple-history' ); ?></a>
						<a href="<?php echo esc_url( Helpers::get_tracking_url( 'https://simple-history.com/add-ons/premium/', 'premium_whats_new_ends' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'See what Premium added this year →', 'simple-history' ); ?></a>
					</p>
				<?php } ?>
			</div>

			<?php $this->render_dismiss_button( __( 'Dismiss subscription message', 'simple-history' ) ); ?>
		</section>
		<?php
	}

	/**
	 * Output the sidebar card for an expired Premium license.
	 *
	 * @param array{stage:string,features:array<int,array<string,mixed>>,expires_timestamp:int} $card Card from get_lapsed_card().
	 */
	private function render_lapsed_card( $card ) {
		$count    = count( $card['features'] );
		$bullets  = array_slice( $card['features'], 0, 2 );
		$more     = $count - count( $bullets );
		$cta_text = __( 'See what\'s new in Premium →', 'simple-history' );

		if ( $card['stage'] === 'old' ) {
			$title    = __( 'Premium has grown since your version', 'simple-history' );
			$subhead  = '';
			$cta_text = __( 'See what Premium does now →', 'simple-history' );
		} else {
			$title = sprintf(
				/* translators: %d: number of Premium features released after the installed version. */
				_n( '%d new Premium feature you don\'t have yet', '%d new Premium features you don\'t have yet', $count, 'simple-history' ),
				$count
			);

			$subhead = $card['stage'] === 'recent'
				? __( 'Your license expired, so Premium stopped getting updates. It still works as before.', 'simple-history' )
				: sprintf(
					/* translators: %s: month and year the license expired, e.g. "March 2026". */
					__( 'Updates stopped when your license expired in %s.', 'simple-history' ),
					wp_date( 'F Y', $card['expires_timestamp'] )
				);
		}

		$cta_url = Helpers::get_tracking_url( 'https://simple-history.com/add-ons/premium/', 'premium_whats_new_lapsed', 'wpadmin', 'plugin', $card['stage'] );
		?>
		<section class="postbox sh-LapsedCard" data-sh-whats-new-kind="lapsed" data-sh-whats-new-value="<?php echo esc_attr( $card['stage'] ); ?>" aria-labelledby="sh-LapsedCard-title">
			<h3 class="sh-LapsedCard-title" id="sh-LapsedCard-title"><?php echo esc_html( $title ); ?></h3>

			<?php if ( $subhead !== '' ) { ?>
				<p class="sh-LapsedCard-subhead"><?php echo esc_html( $subhead ); ?></p>
			<?php } ?>

			<ul class="sh-LapsedCard-features">
				<?php foreach ( $bullets as $feature ) { ?>
					<li><?php echo esc_html( $feature['name'] ); ?></li>
				<?php } ?>

				<?php if ( $card['stage'] !== 'old' && $more > 0 ) { ?>
					<li class="sh-LapsedCard-more">
						<?php
						echo esc_html(
							sprintf(
								/* translators: %d: number of further Premium features. */
								_n( '+%d more', '+%d more', $more, 'simple-history' ),
								$more
							)
						);
						?>
					</li>
				<?php } ?>
			</ul>

			<p class="sh-LapsedCard-cta">
				<a href="<?php echo esc_url( $cta_url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $cta_text ); ?></a>
			</p>

			<?php $this->render_dismiss_button( __( 'Dismiss Premium card', 'simple-history' ) ); ?>
		</section>
		<?php
	}

	/**
	 * Output a dismiss button. Last in source order, so it's reached after the content.
	 *
	 * @param string $label Accessible label.
	 */
	private function render_dismiss_button( $label ) {
		?>
		<button type="button" class="sh-WhatsNew-dismiss js-sh-whats-new-dismiss" aria-label="<?php echo esc_attr( $label ); ?>">
			<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
		</button>
		<?php
	}

	/**
	 * Record a dismissal, or the "What's new" box being opened.
	 */
	public function handle_ajax() {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		$kind    = isset( $_POST['kind'] ) ? sanitize_key( wp_unslash( $_POST['kind'] ) ) : '';
		$value   = isset( $_POST['value'] ) ? sanitize_text_field( wp_unslash( $_POST['value'] ) ) : '';
		$user_id = get_current_user_id();

		if ( $kind === 'whats_new' && Helpers::current_user_can_view_history() ) {
			$this->mark_all_seen( $user_id );
			wp_send_json_success();
		}

		$billing_state = $kind === 'billing' ? $this->get_billing_state() : null;

		// Only the message being shown can be dismissed.
		if ( $billing_state !== null && $value === $billing_state['key'] && current_user_can( 'manage_options' ) ) {
			update_user_meta( $user_id, self::META_BILLING_DISMISSED, $value );
			wp_send_json_success();
		}

		// phpcs:ignore WordPress.WP.Capabilities.Undetermined -- Dynamic capability from Helpers::get_view_settings_capability(), filterable.
		if ( $kind === 'lapsed' && in_array( $value, [ 'recent', 'year', 'old' ], true ) && current_user_can( Helpers::get_view_settings_capability() ) ) {
			$dismissed           = get_user_meta( $user_id, self::META_LAPSED_DISMISSED, true );
			$dismissed           = is_array( $dismissed ) ? $dismissed : [];
			$dismissed[ $value ] = true;

			update_user_meta( $user_id, self::META_LAPSED_DISMISSED, $dismissed );
			wp_send_json_success();
		}

		wp_send_json_error( null, 400 );
	}

	/**
	 * Load the small dismissal script and the styles on Simple History pages.
	 */
	public function enqueue_scripts() {
		if ( ! Helpers::is_on_our_own_pages() ) {
			return;
		}

		wp_enqueue_script(
			'simple-history-whats-new',
			plugins_url( 'js/whats-new.js', dirname( __DIR__ ) ),
			[],
			SIMPLE_HISTORY_VERSION,
			true
		);

		wp_localize_script(
			'simple-history-whats-new',
			'simpleHistoryWhatsNew',
			[
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'action'  => self::AJAX_ACTION,
				'nonce'   => wp_create_nonce( self::NONCE_ACTION ),
			]
		);
	}
}
