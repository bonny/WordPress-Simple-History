<?php

namespace Simple_History\Services;

use Simple_History\Helpers;

/**
 * "What's new" re-engagement surfaces for lapsed and active premium users.
 *
 * Shows a sidebar card to users whose premium license has expired,
 * and a one-line strip to active premium users with a pending update.
 * Free users get nothing — not even markup.
 *
 * Feed data comes from a static JSON file on simple-history.com,
 * fetched daily via wp-cron and cached in a site transient.
 * The render path never performs HTTP requests and soft-fails
 * silently at every layer.
 */
class Whats_New_Service extends Service {
	/** Cron hook name for the daily feed fetch. */
	const CRON_HOOK = 'sh_whats_new_fetch';

	/** Site transient holding the decoded, validated highlights array. */
	const FEED_TRANSIENT = 'sh_whats_new_feed';

	/** Short-lived transient acting as a lock around the HTTP call. */
	const LOCK_TRANSIENT = 'sh_whats_new_fetching';

	/** Option holding ETag/Last-Modified validators for conditional GET. */
	const HTTP_META_OPTION = 'sh_whats_new_http_meta';

	/** Option holding the last successfully fetched highlights (72h fallback). */
	const LAST_KNOWN_GOOD_OPTION = 'sh_whats_new_last_known_good';

	/** User meta: timestamp when the full card was dismissed. */
	const META_DISMISSED_FULL = 'sh_whats_new_dismissed_full';

	/** User meta: timestamp when the small variant was dismissed. */
	const META_DISMISSED_SMALL = 'sh_whats_new_dismissed_small';

	/** User meta: number of times the small variant has been dismissed. */
	const META_SMALL_DISMISS_COUNT = 'sh_whats_new_small_dismiss_count';

	/** AJAX action name for dismissals. */
	const DISMISS_ACTION = 'simple_history_whats_new_dismiss';

	/** Nonce name for dismissals. */
	const DISMISS_NONCE = 'simple_history_whats_new_dismiss_nonce';

	/** Default feed URL. Filterable via simple_history/whats_new/feed_url. */
	const FEED_URL = 'https://simple-history.com/whats-new.json';

	/** Plugin basename of the premium add-on. */
	const PREMIUM_PLUGIN_FILE = 'simple-history-premium/simple-history-premium.php';

	/** Option where the premium license activation response is stored. */
	const PREMIUM_LICENSE_MESSAGE_OPTION = 'simple_history_plusplugin_message_simple-history-premium';

	/** @inheritdoc */
	public function loaded() {
		add_action( self::CRON_HOOK, [ $this, 'fetch_feed' ] );
		add_action( 'after_setup_theme', [ $this, 'setup_cron' ] );
		add_action( 'simple_history/dropin/sidebar/sidebar_html', [ $this, 'output_expired_card' ], 5 );
		add_action( 'simple_history/history_page/before_gui', [ $this, 'output_active_update_strip' ] );
		add_action( 'wp_ajax_' . self::DISMISS_ACTION, [ $this, 'handle_ajax_dismiss' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );

		if ( Helpers::dev_mode_is_enabled() ) {
			( new Whats_New_Demo() )->init();
		}
	}

	/**
	 * Schedule the daily feed fetch, but only when the feed can be useful:
	 * premium installed (update strip) or a lapsed license (sidebar card).
	 * Unschedule when neither applies, so free installs never poll the feed.
	 */
	public function setup_cron() {
		$needs_feed = Helpers::is_premium_add_on_active() || $this->get_license_state()['state'] === 'expired';

		if ( $needs_feed ) {
			if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
				wp_schedule_event( time(), 'daily', self::CRON_HOOK );
			}

			return;
		}

		$next_scheduled = wp_next_scheduled( self::CRON_HOOK );

		if ( $next_scheduled !== false ) {
			wp_unschedule_event( $next_scheduled, self::CRON_HOOK );
		}
	}

	/**
	 * Fetch the feed JSON. Runs from wp-cron only, never in the render path.
	 *
	 * Soft-fails silently: HTTP errors are retried on the next cron run,
	 * malformed payloads are logged and cached as empty for one hour so a
	 * broken feed cannot cause request storms.
	 */
	public function fetch_feed() {
		// Bail if another process is already fetching.
		if ( get_transient( self::LOCK_TRANSIENT ) ) {
			return;
		}

		set_transient( self::LOCK_TRANSIENT, 1, MINUTE_IN_SECONDS );

		/**
		 * Filter the URL of the what's new JSON feed.
		 *
		 * @param string $feed_url Feed URL.
		 */
		$feed_url = apply_filters( 'simple_history/whats_new/feed_url', self::FEED_URL );

		$headers   = [];
		$http_meta = get_option( self::HTTP_META_OPTION );

		if ( is_array( $http_meta ) ) {
			if ( ! empty( $http_meta['etag'] ) && is_string( $http_meta['etag'] ) ) {
				$headers['If-None-Match'] = $http_meta['etag'];
			}

			if ( ! empty( $http_meta['last_modified'] ) && is_string( $http_meta['last_modified'] ) ) {
				$headers['If-Modified-Since'] = $http_meta['last_modified'];
			}
		}

		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.wp_remote_get_wp_remote_get
		$response = wp_remote_get(
			$feed_url,
			[
				'timeout' => 10,
				'headers' => $headers,
			]
		);

		delete_transient( self::LOCK_TRANSIENT );

		// Network error: keep whatever we have and retry on the next cron run.
		if ( is_wp_error( $response ) ) {
			return;
		}

		$response_code = wp_remote_retrieve_response_code( $response );

		// Not modified: re-extend the cached copy from the last known good data.
		if ( $response_code === 304 ) {
			$last_known_good = $this->get_last_known_good_highlights( true );

			if ( $last_known_good !== null ) {
				set_site_transient( self::FEED_TRANSIENT, $last_known_good, $this->get_feed_ttl() );
			}

			return;
		}

		// Any non-200 (404, 500, ...): no card, retry next cron run.
		if ( $response_code !== 200 ) {
			return;
		}

		$decoded = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'Simple History: whats-new feed contained malformed JSON, caching empty for one hour.' );
			set_site_transient( self::FEED_TRANSIENT, [], HOUR_IN_SECONDS );

			return;
		}

		// Wrong top-level shape is treated the same as malformed JSON.
		if ( ! is_array( $decoded ) || ! isset( $decoded['highlights'] ) || ! is_array( $decoded['highlights'] ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'Simple History: whats-new feed had unexpected top-level shape, caching empty for one hour.' );
			set_site_transient( self::FEED_TRANSIENT, [], HOUR_IN_SECONDS );

			return;
		}

		$valid_entries = [];

		foreach ( $decoded['highlights'] as $entry ) {
			// Skip entries that do not match the schema, keep the valid ones.
			if ( ! $this->is_valid_entry( $entry ) ) {
				continue;
			}

			$valid_entries[] = $this->normalize_entry( $entry );
		}

		set_site_transient( self::FEED_TRANSIENT, $valid_entries, $this->get_feed_ttl() );

		// One option write per successful fetch: the 72h last-known-good fallback.
		update_option(
			self::LAST_KNOWN_GOOD_OPTION,
			[
				'highlights' => $valid_entries,
				'fetched_at' => time(),
			],
			false
		);

		update_option(
			self::HTTP_META_OPTION,
			[
				'etag'          => wp_remote_retrieve_header( $response, 'etag' ),
				'last_modified' => wp_remote_retrieve_header( $response, 'last-modified' ),
			],
			false
		);
	}

	/**
	 * Jittered cache lifetime so sites do not stampede the feed at the same time.
	 *
	 * @return int TTL in seconds, between 12 and 24 hours.
	 */
	private function get_feed_ttl() {
		return wp_rand( 12, 24 ) * HOUR_IN_SECONDS;
	}

	/**
	 * Get cached highlights. Never performs HTTP.
	 *
	 * Falls back to the last successful fetch if the primary transient is
	 * empty and the fallback is at most 72 hours old.
	 *
	 * @return array<int,array<string,mixed>> Highlights, possibly empty.
	 */
	public function get_highlights() {
		$highlights = get_site_transient( self::FEED_TRANSIENT );

		if ( ! is_array( $highlights ) ) {
			$highlights = $this->get_last_known_good_highlights();
		}

		if ( ! is_array( $highlights ) ) {
			$highlights = [];
		}

		/**
		 * Filter the cached what's new highlights.
		 *
		 * @param array<int,array<string,mixed>> $highlights Highlights from the feed cache.
		 */
		$highlights = apply_filters( 'simple_history/whats_new/highlights', $highlights );

		return is_array( $highlights ) ? $highlights : [];
	}

	/**
	 * Read the last-known-good highlights stored at the latest successful fetch.
	 *
	 * @param bool $ignore_age True to skip the 72 hour freshness check.
	 * @return array<int,array<string,mixed>>|null Highlights or null if missing/stale.
	 */
	private function get_last_known_good_highlights( $ignore_age = false ) {
		$stored = get_option( self::LAST_KNOWN_GOOD_OPTION );

		if ( ! is_array( $stored ) || ! isset( $stored['highlights'] ) || ! is_array( $stored['highlights'] ) ) {
			return null;
		}

		$fetched_at = isset( $stored['fetched_at'] ) ? (int) $stored['fetched_at'] : 0;

		if ( ! $ignore_age && time() - $fetched_at > 72 * HOUR_IN_SECONDS ) {
			return null;
		}

		return $stored['highlights'];
	}

	/**
	 * Validate a single feed entry against the expected schema.
	 *
	 * @param mixed $entry Decoded entry.
	 * @return bool True when the entry is usable.
	 */
	private function is_valid_entry( $entry ) {
		if ( ! is_array( $entry ) ) {
			return false;
		}

		foreach ( [ 'version', 'title', 'summary', 'url' ] as $required_string_field ) {
			if ( ! isset( $entry[ $required_string_field ] ) || ! is_string( $entry[ $required_string_field ] ) || $entry[ $required_string_field ] === '' ) {
				return false;
			}
		}

		if ( ! isset( $entry['plugin'] ) || ! in_array( $entry['plugin'], [ 'core', 'premium', 'woocommerce' ], true ) ) {
			return false;
		}

		if ( ! isset( $entry['audience'] ) || ! is_array( $entry['audience'] ) || $entry['audience'] === [] ) {
			return false;
		}

		foreach ( $entry['audience'] as $audience ) {
			if ( ! is_string( $audience ) || ! in_array( $audience, [ 'all', 'premium-active', 'premium-expired' ], true ) ) {
				return false;
			}
		}

		if ( isset( $entry['min_version_to_show'] ) && ! is_string( $entry['min_version_to_show'] ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Reduce an already validated entry to the known schema fields.
	 *
	 * @param array<string,mixed> $entry Validated entry.
	 * @return array<string,mixed> Normalized entry.
	 */
	private function normalize_entry( $entry ) {
		return [
			'version'             => $entry['version'],
			'plugin'              => $entry['plugin'],
			'title'               => $entry['title'],
			'summary'             => $entry['summary'],
			'url'                 => $entry['url'],
			'min_version_to_show' => isset( $entry['min_version_to_show'] ) ? $entry['min_version_to_show'] : '',
			'audience'            => array_values( $entry['audience'] ),
		];
	}

	/**
	 * Get highlights matching an audience, respecting min_version_to_show.
	 *
	 * @param string $audience One of 'premium-active' or 'premium-expired'.
	 * @return array<int,array<string,mixed>> Matching highlights.
	 */
	public function get_highlights_for_audience( $audience ) {
		$current_version = defined( 'SIMPLE_HISTORY_VERSION' ) ? SIMPLE_HISTORY_VERSION : '0';
		$matched         = [];

		foreach ( $this->get_highlights() as $entry ) {
			// Defensive: filters may inject malformed entries.
			if ( ! is_array( $entry ) ) {
				continue;
			}

			$entry_audience = isset( $entry['audience'] ) ? $entry['audience'] : null;

			if ( ! is_array( $entry_audience ) ) {
				continue;
			}

			if ( ! in_array( 'all', $entry_audience, true ) && ! in_array( $audience, $entry_audience, true ) ) {
				continue;
			}

			$min_version = isset( $entry['min_version_to_show'] ) && is_string( $entry['min_version_to_show'] )
				? $entry['min_version_to_show']
				: '';

			// Entries can require a minimum plugin version before they are shown,
			// so future schema or UX changes can be gated server-side.
			if ( $min_version !== '' && version_compare( $current_version, $min_version, '<' ) ) {
				continue;
			}

			$matched[] = $entry;
		}

		return $matched;
	}

	/**
	 * Determine the premium license state for the current site.
	 *
	 * There is no clean "had premium, now lapsed" signal in core: the license
	 * activation response (including key_expires_at) is stored once at
	 * activation time and never refreshed. This helper reads that stored
	 * value as a best-effort signal and lets premium supply the authoritative
	 * state via the simple_history/whats_new/license_state filter.
	 *
	 * @return array{state:string,expires_at:?string} State is 'none', 'active' or 'expired'.
	 */
	public function get_license_state() {
		$license_state = [
			'state'      => 'none',
			'expires_at' => null,
		];

		$message = get_option( self::PREMIUM_LICENSE_MESSAGE_OPTION );

		if ( is_array( $message ) && ! empty( $message['key_activated'] ) ) {
			$expires_at        = isset( $message['key_expires_at'] ) && is_string( $message['key_expires_at'] ) ? $message['key_expires_at'] : null;
			$expires_timestamp = $expires_at !== null ? strtotime( $expires_at ) : false;

			if ( $expires_timestamp !== false && $expires_timestamp < time() ) {
				$license_state = [
					'state'      => 'expired',
					'expires_at' => $expires_at,
				];
			} else {
				// No expiry date (lifetime) or a future expiry both count as active.
				$license_state = [
					'state'      => 'active',
					'expires_at' => $expires_at,
				];
			}
		}

		/**
		 * Filter the premium license state used by the what's new surfaces.
		 *
		 * The premium add-on can hook this to supply the real, up-to-date
		 * license state instead of the stored activation-time snapshot.
		 *
		 * @param array{state:string,expires_at:?string} $license_state License state.
		 */
		$license_state = apply_filters( 'simple_history/whats_new/license_state', $license_state );

		if ( ! is_array( $license_state ) || ! isset( $license_state['state'] ) ) {
			$license_state = [
				'state'      => 'none',
				'expires_at' => null,
			];
		}

		return $license_state;
	}

	/**
	 * Detect a pending premium plugin update.
	 *
	 * Reuses the update_plugins site transient, never the feed.
	 *
	 * @return array{new_version:string}|null Update info or null.
	 */
	public function get_premium_update() {
		$update = null;

		if ( Helpers::is_premium_add_on_active() ) {
			$update_plugins = get_site_transient( 'update_plugins' );

			$plugin_updates = is_object( $update_plugins ) && isset( $update_plugins->response ) && is_array( $update_plugins->response )
				? $update_plugins->response
				: [];

			$premium_update = isset( $plugin_updates[ self::PREMIUM_PLUGIN_FILE ] ) ? $plugin_updates[ self::PREMIUM_PLUGIN_FILE ] : null;

			$new_version = null;

			if ( is_object( $premium_update ) && isset( $premium_update->new_version ) && is_scalar( $premium_update->new_version ) ) {
				$new_version = (string) $premium_update->new_version;
			} elseif ( is_array( $premium_update ) && isset( $premium_update['new_version'] ) && is_scalar( $premium_update['new_version'] ) ) {
				$new_version = (string) $premium_update['new_version'];
			}

			if ( $new_version !== null && $new_version !== '' ) {
				$update = [ 'new_version' => $new_version ];
			}
		}

		/**
		 * Filter the detected premium update info.
		 *
		 * @param array{new_version:string}|null $update Update info or null when no update.
		 */
		$update = apply_filters( 'simple_history/whats_new/premium_update', $update );

		if ( ! is_array( $update ) || empty( $update['new_version'] ) || ! is_string( $update['new_version'] ) ) {
			$update = null;
		}

		return $update;
	}

	/**
	 * Get the current user's dismissal state for the expired card.
	 *
	 * @return array{full_dismissed_at:int,small_dismissed_at:int,small_dismiss_count:int}
	 */
	private function get_dismissal_state() {
		$user_id = get_current_user_id();

		$dismissal_state = [
			'full_dismissed_at'   => (int) get_user_meta( $user_id, self::META_DISMISSED_FULL, true ),
			'small_dismissed_at'  => (int) get_user_meta( $user_id, self::META_DISMISSED_SMALL, true ),
			'small_dismiss_count' => (int) get_user_meta( $user_id, self::META_SMALL_DISMISS_COUNT, true ),
		];

		/**
		 * Filter the dismissal state. Used by demo mode to preview states.
		 *
		 * @param array{full_dismissed_at:int,small_dismissed_at:int,small_dismiss_count:int} $dismissal_state Dismissal state.
		 */
		$dismissal_state = apply_filters( 'simple_history/whats_new/dismissal_state', $dismissal_state );

		return $dismissal_state;
	}

	/**
	 * Output the expired-license sidebar card.
	 *
	 * Wrapped in try/catch so a render error can never break the log page.
	 */
	public function output_expired_card() {
		try {
			$this->render_expired_card();
		} catch ( \Throwable $error ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'Simple History: whats-new card render failed: ' . $error->getMessage() );
		}
	}

	/**
	 * Render the expired-license sidebar card, if all conditions are met.
	 */
	private function render_expired_card() {
		// phpcs:ignore WordPress.WP.Capabilities.Undetermined -- Dynamic capability from Helpers::get_view_settings_capability(), filterable.
		if ( ! current_user_can( Helpers::get_view_settings_capability() ) ) {
			return;
		}

		$license_state = $this->get_license_state();

		if ( $license_state['state'] !== 'expired' ) {
			return;
		}

		$dismissal = $this->get_dismissal_state();

		// Permanent suppression after the second small dismissal.
		if ( $dismissal['small_dismiss_count'] >= 2 ) {
			return;
		}

		// Silent period of 60 days after the first small dismissal.
		if ( $dismissal['small_dismissed_at'] > 0 && time() - $dismissal['small_dismissed_at'] < 60 * DAY_IN_SECONDS ) {
			return;
		}

		$highlights = $this->get_highlights_for_audience( 'premium-expired' );

		// A card with too little content looks broken — suppress entirely.
		if ( count( $highlights ) < 3 ) {
			return;
		}

		$expires_at        = isset( $license_state['expires_at'] ) && is_string( $license_state['expires_at'] ) ? $license_state['expires_at'] : null;
		$expires_timestamp = $expires_at !== null ? strtotime( $expires_at ) : false;

		// Default to the middle bucket if the expiry date is unusable.
		$days_lapsed = $expires_timestamp !== false ? (int) floor( ( time() - $expires_timestamp ) / DAY_IN_SECONDS ) : 120;

		$copy = $this->get_expired_card_copy( $days_lapsed, count( $highlights ), $expires_timestamp );

		$cta_url     = Helpers::get_tracking_url( 'https://simple-history.com/add-ons/premium/', 'premium_whats_new' );
		$details_url = Helpers::get_tracking_url( 'https://simple-history.com/add-ons/premium/', 'premium_whats_new', 'wpadmin', 'plugin', 'small' );

		$show_full = $dismissal['full_dismissed_at'] === 0;

		if ( $show_full ) {
			$this->render_full_card( $copy, $highlights, $cta_url );
		}

		$this->render_small_variant( count( $highlights ), $details_url, $show_full );
	}

	/**
	 * Get title, subhead, CTA label and "+N more" visibility for a lapse bucket.
	 *
	 * @param int       $days_lapsed Days since the license expired.
	 * @param int       $total_highlights Number of matching highlights.
	 * @param int|false $expires_timestamp Expiry timestamp, or false if unknown.
	 * @return array{title:string,subhead:string,cta:string,show_more_line:bool,more_since:string}
	 */
	private function get_expired_card_copy( $days_lapsed, $total_highlights, $expires_timestamp ) {
		/**
		 * Filter the price text used in the card CTA buttons.
		 *
		 * @param string $price_text Price text, e.g. "$79".
		 */
		$price_text = apply_filters( 'simple_history/whats_new/price_text', '$79' );

		$more_since = $expires_timestamp !== false ? date_i18n( 'F Y', $expires_timestamp ) : '';

		/* translators: %s: price, e.g. "$79". */
		$price_caption = sprintf( __( 'from %s/yr', 'simple-history' ), $price_text );

		// Every bucket leads with what shipped, never with license status —
		// the momentum framing is the whole mechanic.
		if ( $days_lapsed < 90 ) {
			return [
				'title'          => sprintf(
					/* translators: %d: number of premium features shipped. */
					_n( '%d premium feature shipped since you left', '%d premium features shipped since you left', $total_highlights, 'simple-history' ),
					$total_highlights
				),
				'subhead'        => __( 'Your license lapsed recently. Quick to catch up.', 'simple-history' ),
				'cta'            => __( 'See what you missed', 'simple-history' ),
				'price'          => $price_caption,
				'show_more_line' => true,
				'more_since'     => $more_since,
			];
		}

		if ( $days_lapsed <= 365 ) {
			return [
				'title'          => sprintf(
					/* translators: %d: number of premium features shipped. */
					_n( '%d premium feature shipped', '%d premium features shipped', $total_highlights, 'simple-history' ),
					$total_highlights
				),
				'subhead'        => __( 'While your license was inactive.', 'simple-history' ),
				'cta'            => __( 'See what you missed', 'simple-history' ),
				'price'          => $price_caption,
				'show_more_line' => true,
				'more_since'     => $more_since,
			];
		}

		return [
			'title'          => __( 'Simple History Premium now does this', 'simple-history' ),
			'subhead'        => __( 'A quick look at recent additions.', 'simple-history' ),
			'cta'            => __( 'Explore Premium', 'simple-history' ),
			'price'          => $price_caption,
			'show_more_line' => false,
			'more_since'     => '',
		];
	}

	/**
	 * Output the full card markup.
	 *
	 * @param array<string,mixed>             $copy Bucket copy from get_expired_card_copy().
	 * @param array<int,array<string,mixed>>  $highlights Matching highlights.
	 * @param string                          $cta_url Renewal URL.
	 */
	private function render_full_card( $copy, $highlights, $cta_url ) {
		$bullets    = array_slice( $highlights, 0, 2 );
		$more_count = count( $highlights ) - count( $bullets );
		?>
		<div class="postbox sh-WhatsNewCard" role="region" aria-label="<?php esc_attr_e( 'What\'s new in Premium', 'simple-history' ); ?>">
			<h3 class="sh-WhatsNewCard-title"><?php echo esc_html( $copy['title'] ); ?></h3>
			<p class="sh-WhatsNewCard-subhead"><?php echo esc_html( $copy['subhead'] ); ?></p>
			<ul class="sh-WhatsNewCard-list">
				<?php foreach ( $bullets as $bullet ) { ?>
					<li><?php echo esc_html( isset( $bullet['title'] ) && is_string( $bullet['title'] ) ? $bullet['title'] : '' ); ?></li>
				<?php } ?>
				<?php if ( $copy['show_more_line'] && $more_count > 0 ) { ?>
					<li class="sh-WhatsNewCard-more">
						<?php
						echo esc_html(
							sprintf(
								/* translators: 1: number of additional highlights, 2: month and year, e.g. "March 2026". */
								__( '+%1$d more since %2$s', 'simple-history' ),
								$more_count,
								$copy['more_since']
							)
						);
						?>
					</li>
				<?php } ?>
			</ul>
			<a href="<?php echo esc_url( $cta_url ); ?>" target="_blank" rel="noopener noreferrer" class="button button-secondary sh-WhatsNewCard-cta">
				<?php echo esc_html( $copy['cta'] ); ?>
			</a>
			<?php if ( isset( $copy['price'] ) && is_string( $copy['price'] ) && $copy['price'] !== '' ) { ?>
				<p class="sh-WhatsNewCard-price"><?php echo esc_html( $copy['price'] ); ?></p>
			<?php } ?>
			<button type="button" class="sh-WhatsNewCard-dismiss js-sh-whats-new-dismiss-full" aria-label="<?php esc_attr_e( 'Dismiss what\'s new', 'simple-history' ); ?>">&times;</button>
		</div>
		<?php
	}

	/**
	 * Output the small single-line variant.
	 *
	 * Rendered hidden alongside the full card so the dismiss animation can
	 * reveal it without a page reload.
	 *
	 * @param int    $total_highlights Number of matching highlights.
	 * @param string $details_url Details URL.
	 * @param bool   $hidden True to render with the hidden attribute.
	 */
	private function render_small_variant( $total_highlights, $details_url, $hidden ) {
		?>
		<div class="sh-WhatsNewSmall" <?php echo $hidden ? 'hidden' : ''; ?>>
			<span class="sh-WhatsNewSmall-text">
				<?php
				echo esc_html(
					sprintf(
						/* translators: %d: number of premium updates. */
						__( 'Simple History Premium — %d updates available.', 'simple-history' ),
						$total_highlights
					)
				);
				?>
			</span>
			<a href="<?php echo esc_url( $details_url ); ?>" target="_blank" rel="noopener noreferrer" class="sh-WhatsNewSmall-link">
				<?php esc_html_e( 'Details', 'simple-history' ); ?>
			</a>
			<button type="button" class="sh-WhatsNewSmall-dismiss js-sh-whats-new-dismiss-small" aria-label="<?php esc_attr_e( 'Dismiss what\'s new', 'simple-history' ); ?>">&times;</button>
		</div>
		<?php
	}

	/**
	 * Output the one-line update strip for active premium users.
	 *
	 * Wrapped in try/catch so a render error can never break the log page.
	 */
	public function output_active_update_strip() {
		try {
			$this->render_active_update_strip();
		} catch ( \Throwable $error ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'Simple History: whats-new strip render failed: ' . $error->getMessage() );
		}
	}

	/**
	 * Render the update strip if premium is active and an update is pending.
	 */
	private function render_active_update_strip() {
		if ( ! current_user_can( 'update_plugins' ) ) {
			return;
		}

		$update = $this->get_premium_update();

		if ( $update === null ) {
			return;
		}

		$license_state = $this->get_license_state();

		// The strip targets active premium users; lapsed users get the card.
		if ( $license_state['state'] === 'expired' ) {
			return;
		}

		$highlights = $this->get_highlights_for_audience( 'premium-active' );

		$first_title = '';

		if ( isset( $highlights[0]['title'] ) && is_string( $highlights[0]['title'] ) ) {
			$first_title = $highlights[0]['title'];
		}

		$more_count = max( 0, count( $highlights ) - 1 );

		if ( $first_title !== '' && $more_count > 0 ) {
			$text = sprintf(
				/* translators: 1: version number, 2: feature title, 3: number of additional features. */
				__( 'v%1$s — %2$s + %3$d more this year.', 'simple-history' ),
				$update['new_version'],
				$first_title,
				$more_count
			);
		} elseif ( $first_title !== '' ) {
			$text = sprintf(
				/* translators: 1: version number, 2: feature title. */
				__( 'v%1$s — %2$s.', 'simple-history' ),
				$update['new_version'],
				$first_title
			);
		} else {
			$text = sprintf(
				/* translators: %s: version number. */
				__( 'Simple History Premium v%s is available.', 'simple-history' ),
				$update['new_version']
			);
		}
		?>
		<div class="sh-WhatsNewStrip">
			<span class="sh-WhatsNewStrip-text"><?php echo esc_html( $text ); ?></span>
			<a href="<?php echo esc_url( self_admin_url( 'update-core.php' ) ); ?>" class="sh-WhatsNewStrip-link">
				<?php esc_html_e( 'Update now', 'simple-history' ); ?>
			</a>
		</div>
		<?php
	}

	/**
	 * Handle the AJAX request that records a dismissal.
	 */
	public function handle_ajax_dismiss() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['nonce'] ), self::DISMISS_NONCE ) ) {
			wp_send_json_error( 'Invalid nonce' );
		}

		// phpcs:ignore WordPress.WP.Capabilities.Undetermined -- Dynamic capability from Helpers::get_view_settings_capability(), filterable.
		if ( ! current_user_can( Helpers::get_view_settings_capability() ) ) {
			wp_send_json_error( 'Permission denied' );
		}

		$level   = isset( $_POST['level'] ) ? sanitize_key( wp_unslash( $_POST['level'] ) ) : '';
		$user_id = get_current_user_id();

		if ( $level === 'full' ) {
			update_user_meta( $user_id, self::META_DISMISSED_FULL, time() );
		} elseif ( $level === 'small' ) {
			update_user_meta( $user_id, self::META_DISMISSED_SMALL, time() );

			$small_dismiss_count = (int) get_user_meta( $user_id, self::META_SMALL_DISMISS_COUNT, true );
			update_user_meta( $user_id, self::META_SMALL_DISMISS_COUNT, $small_dismiss_count + 1 );
		} else {
			wp_send_json_error( 'Invalid level' );
		}

		wp_send_json_success();
	}

	/**
	 * Enqueue the dismissal script on Simple History pages, but only when
	 * the card can actually appear (lapsed license, or dev mode for demos).
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_scripts( $hook ) {
		if ( ! Helpers::is_on_our_own_pages() ) {
			return;
		}

		// phpcs:ignore WordPress.WP.Capabilities.Undetermined -- Dynamic capability from Helpers::get_view_settings_capability(), filterable.
		if ( ! current_user_can( Helpers::get_view_settings_capability() ) ) {
			return;
		}

		if ( $this->get_license_state()['state'] !== 'expired' && ! Helpers::dev_mode_is_enabled() ) {
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
				'action'  => self::DISMISS_ACTION,
				'nonce'   => wp_create_nonce( self::DISMISS_NONCE ),
			]
		);
	}
}
