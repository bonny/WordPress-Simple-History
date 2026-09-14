<?php

namespace Simple_History\Services;

use Simple_History\Helpers;
use Simple_History\Events_Stats;
use Simple_History\Date_Helper;
use Simple_History\Loggers\User_Logger;
use Simple_History\Menu_Page;
use Simple_History\Simple_History;

/**
 * Service that handles email reports.
 */
class Email_Report_Service extends Service {
	private const SETTINGS_PAGE_SLUG    = 'simple_history_settings_menu_slug_email_reports';
	private const SETTINGS_OPTION_GROUP = 'simple_history_settings_group_email_reports';

	/**
	 * UTM campaign for the Premium links in the email. Uses the premium_ prefix
	 * like every other upsell, so email clicks count in the conversion funnel.
	 */
	public const PREMIUM_UTM_CAMPAIGN = 'premium_email_weekly';

	/** The last few periods a report was sent for, newest first, so a report can compare itself against them. */
	private const SENT_PERIODS_OPTION = 'simple_history_email_report_sent_periods';

	/**
	 * How many sent periods to remember.
	 *
	 * One is enough to say "compared with last week". Keeping a few costs
	 * nothing — it is a handful of integers in one option — and is what a
	 * typical-week comparison would need, which is the more useful statement
	 * and the one worth being able to add without a data migration.
	 */
	private const MAX_STORED_PERIODS = 5;

	/** The admin-post action for the one-click opt-in offered after install. */
	public const OPT_IN_ACTION = 'simple_history_email_report_opt_in';

	/** Query arg added to the redirect after opting in, so the confirmation notice can show. */
	private const OPT_IN_QUERY_ARG = 'simple-history-email-report-opt-in';

	/** Settings sub-tab slug for the email reports settings. */
	private const SETTINGS_SUB_TAB_SLUG = 'general_settings_subtab_email_reports';

	/**
	 * @inheritdoc
	 */
	public function loaded() {
		// Register settings and menu tab.
		add_action( 'admin_menu', [ $this, 'add_settings_menu_tab' ], 15 );
		add_action( 'admin_menu', [ $this, 'register_settings' ], 13 );

		// Register REST API endpoints.
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );

		// Schedule email report.
		add_action( 'init', [ $this, 'schedule_email_report' ] );
		add_action( 'simple_history/email_report', [ $this, 'send_email_report' ] );

		// Handle enable/disable of email reports.
		add_action( 'update_option_simple_history_email_report_enabled', [ $this, 'on_email_report_enabled_updated' ], 10, 2 );

		// One-click opt-in from the welcome notice and the welcome log event.
		add_action( 'admin_post_' . self::OPT_IN_ACTION, [ $this, 'handle_opt_in' ] );
		add_action( 'admin_notices', [ $this, 'show_opt_in_confirmation_notice' ] );
		add_filter( 'removable_query_args', [ $this, 'add_removable_query_args' ] );
	}

	/**
	 * Get the one-click weekly email opt-in: button and the text that goes with it.
	 *
	 * Returns an empty string when the current user can not change the setting,
	 * has no valid email address, or already gets the report, so callers can
	 * output the result without checks of their own.
	 *
	 * A nonce link, not a form: wp_admin_notice() runs its message through
	 * wp_kses_post(), which strips form and input tags. WordPress uses the same
	 * pattern for its own Activate and Trash links.
	 *
	 * A secondary button: the offer is optional, and the notice shows on screens
	 * that already have a primary action of their own.
	 *
	 * @return string Button HTML, escaped.
	 */
	public static function get_opt_in_html() {
		if ( ! self::should_offer_opt_in() ) {
			return '';
		}

		// Inline layout styles, matching .sh-EmailReportOptIn in styles.css: the welcome
		// notice shows on screens where that stylesheet is not loaded.
		return sprintf(
			'<p class="sh-EmailReportOptIn" style="display:flex;flex-wrap:wrap;align-items:center;gap:0.5em 1em;">
				%1$s
				<span class="description sh-EmailReportOptIn-description" style="color:#50575e;font-size:13px;">%2$s</span>
			</p>',
			self::get_opt_in_button_html(),
			esc_html( self::get_opt_in_description() )
		);
	}

	/**
	 * Get the one-click opt-in button, with an envelope icon that sets it apart from
	 * buttons that only navigate.
	 *
	 * @return string Button HTML, escaped. Empty string if the opt-in should not be offered.
	 */
	public static function get_opt_in_button_html() {
		if ( ! self::should_offer_opt_in() ) {
			return '';
		}

		// Say what the click does: turn the email on, or add the user when it is
		// already on for other recipients.
		if ( get_option( 'simple_history_email_report_enabled', false ) ) {
			$label = __( 'Add me to the weekly email', 'simple-history' );
		} else {
			$label = __( 'Turn on weekly email', 'simple-history' );
		}

		return sprintf(
			'<a href="%1$s" class="button" style="display:inline-flex;align-items:center;gap:4px;"><span class="dashicons dashicons-email-alt" style="line-height:1;" aria-hidden="true"></span>%2$s</a>',
			esc_url( self::get_opt_in_url() ),
			esc_html( $label )
		);
	}

	/**
	 * Get the text that goes with the opt-in button.
	 *
	 * The button label already says the click turns the email on, so this only says
	 * what it contains, where it goes, when, and how to stop it.
	 *
	 * @return string Plain text, unescaped.
	 */
	public static function get_opt_in_description() {
		return sprintf(
			/* translators: %s: email address of the current user. */
			__( 'A summary of the past week, sent to %s every Monday. You can turn it off in the Simple History settings.', 'simple-history' ),
			wp_get_current_user()->user_email
		);
	}

	/**
	 * Check if the one-click opt-in should be offered to the current user: they can
	 * change the setting, have a valid email address, and do not already get the email.
	 *
	 * @return bool
	 */
	public static function should_offer_opt_in() {
		if ( ! self::current_user_can_opt_in() ) {
			return false;
		}

		// Offer it unless the user already gets the email.
		return ! self::is_email_in_active_recipients( wp_get_current_user()->user_email );
	}

	/**
	 * Get the URL that turns on the weekly email for the current user in one click.
	 *
	 * @return string URL, unescaped. Empty string if the current user can not opt in.
	 */
	public static function get_opt_in_url() {
		if ( ! self::current_user_can_opt_in() ) {
			return '';
		}

		return wp_nonce_url(
			add_query_arg( 'action', self::OPT_IN_ACTION, admin_url( 'admin-post.php' ) ),
			self::OPT_IN_ACTION
		);
	}

	/**
	 * Check if the current user can use the one-click opt-in.
	 *
	 * Uses the same capability as the email report settings page.
	 *
	 * @return bool
	 */
	private static function current_user_can_opt_in() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		return (bool) is_email( wp_get_current_user()->user_email );
	}

	/**
	 * Check if reports are enabled and the email address is one of the recipients.
	 *
	 * @param string $email Email address.
	 * @return bool
	 */
	public static function is_email_in_active_recipients( $email ) {
		if ( ! get_option( 'simple_history_email_report_enabled', false ) ) {
			return false;
		}

		$recipients = explode( "\n", (string) get_option( 'simple_history_email_report_recipients', '' ) );
		$recipients = array_map( 'strtolower', array_map( 'trim', $recipients ) );

		return in_array( strtolower( $email ), $recipients, true );
	}

	/**
	 * Handle the one-click opt-in button.
	 *
	 * Enables the weekly report and adds the current user's email address to the
	 * recipients. Recipients that are already set are kept.
	 */
	public function handle_opt_in() {
		if ( ! self::current_user_can_opt_in() ) {
			wp_die( esc_html__( 'You are not allowed to change the email report settings.', 'simple-history' ), 403 );
		}

		check_admin_referer( self::OPT_IN_ACTION );

		// Add the current user and keep any recipients that are already set.
		$recipients = $this->sanitize_email_recipients(
			$this->get_email_report_recipients() . "\n" . wp_get_current_user()->user_email
		);

		update_option( 'simple_history_email_report_recipients', $recipients );
		update_option( 'simple_history_email_report_enabled', true );

		// update_option() only fires the "updated" hook that schedules the report when
		// the option already existed, and on a new install it does not.
		$this->schedule_email_report();

		$redirect_url = wp_get_referer();

		if ( ! $redirect_url ) {
			$redirect_url = Helpers::get_history_admin_url();
		}

		wp_safe_redirect( add_query_arg( self::OPT_IN_QUERY_ARG, 'enabled', $redirect_url ) );
		exit;
	}

	/**
	 * Show a confirmation notice after opting in.
	 */
	public function show_opt_in_confirmation_notice() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only decides whether to show a notice.
		$opt_in_state = isset( $_GET[ self::OPT_IN_QUERY_ARG ] ) ? sanitize_key( wp_unslash( $_GET[ self::OPT_IN_QUERY_ARG ] ) ) : '';

		if ( $opt_in_state !== 'enabled' || ! function_exists( 'wp_admin_notice' ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$user_email = wp_get_current_user()->user_email;

		// Confirm only what is actually true, in case the arg was added to a URL by hand.
		if ( ! self::is_email_in_active_recipients( $user_email ) ) {
			return;
		}

		$message = sprintf(
			/* translators: 1: email address, 2: opening link tag, 3: closing link tag. */
			esc_html__( 'Done. Every Monday, %1$s will get a summary of the past week. %2$sChange recipients or turn it off%3$s', 'simple-history' ),
			'<strong>' . esc_html( $user_email ) . '</strong>',
			'<a href="' . esc_url( Helpers::get_settings_page_sub_tab_url( self::SETTINGS_SUB_TAB_SLUG ) ) . '">',
			'</a>'
		);

		// Opting in from the welcome notice sends people back to the screen they were on,
		// often Plugins, so point them to the log too, unless they are already there.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only check of which admin page is being viewed.
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';

		if ( $page !== Simple_History::MENU_PAGE_SLUG ) {
			$message .= sprintf(
				' | <a href="%1$s">%2$s</a>',
				esc_url( Helpers::get_history_admin_url() ),
				esc_html__( 'Open the activity log', 'simple-history' )
			);
		}

		wp_admin_notice(
			$message,
			[
				'type'        => 'success',
				'dismissible' => true,
			]
		);
	}

	/**
	 * Remove the opt-in query arg from the address bar after the page has loaded.
	 *
	 * @param array<string> $args Query args WordPress removes.
	 * @return array<string>
	 */
	public function add_removable_query_args( $args ) {
		$args[] = self::OPT_IN_QUERY_ARG;

		return $args;
	}

	/**
	 * Register REST API routes.
	 */
	public function register_rest_routes() {
		register_rest_route(
			'simple-history/v1',
			'/email-report/preview/email',
			[
				'methods'             => [ \WP_REST_Server::CREATABLE, \WP_REST_Server::READABLE ],
				'callback'            => [ $this, 'rest_preview_email' ],
				'permission_callback' => [ $this, 'rest_permission_callback' ],
			]
		);

		register_rest_route(
			'simple-history/v1',
			'/email-report/preview/html',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'rest_preview_html' ],
				'permission_callback' => [ $this, 'rest_permission_callback' ],
			]
		);
	}

	/**
	 * Permission callback for REST API endpoints.
	 */
	public function rest_permission_callback() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Add email reports settings tab as a subtab to main settings tab.
	 */
	public function add_settings_menu_tab() {
		$menu_manager = $this->simple_history->get_menu_manager();

		// Bail if parent settings page does not exist (due to Stealth Mode or similar).
		if ( ! $menu_manager->page_exists( Setup_Settings_Page::SETTINGS_GENERAL_SUBTAB_SLUG ) ) {
			return;
		}

		( new Menu_Page() )
			->set_page_title( __( 'Email Reports', 'simple-history' ) )
			->set_menu_title( __( 'Email Reports', 'simple-history' ) )
			->set_menu_slug( self::SETTINGS_SUB_TAB_SLUG )
			->set_callback( [ $this, 'settings_output_email_reports' ] )
			->set_order( 15 )
			->set_parent( Setup_Settings_Page::SETTINGS_GENERAL_SUBTAB_SLUG )
			->add();
	}

	/**
	 * Output for the email reports settings tab.
	 */
	public function settings_output_email_reports() {
		?>
		<div class="wrap sh-Page-content">
			<form method="post" action="options.php">
				<?php
				do_settings_sections( self::SETTINGS_PAGE_SLUG );
				settings_fields( self::SETTINGS_OPTION_GROUP );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Prepare top items array with safe fallbacks.
	 *
	 * @param array  $items Raw items array.
	 * @param int    $limit Maximum number of items to return.
	 * @param string $name_key Key for the name field.
	 * @param string $count_key Key for the count field.
	 * @return array
	 */
	private function prepare_top_items( $items, $limit = 3, $name_key = 'name', $count_key = 'count' ) {
		$result = [];

		if ( ! is_array( $items ) || empty( $items ) ) {
			return array_fill(
				0,
				$limit,
				[
					'name'  => '',
					'count' => 0,
				]
			);
		}

		for ( $i = 0; $i < $limit; $i++ ) {
			if ( isset( $items[ $i ] ) ) {
				$item     = $items[ $i ];
				$result[] = [
					'name'  => is_object( $item ) ? $item->$name_key : $item[ $name_key ],
					'count' => is_object( $item ) ? $item->$count_key : $item[ $count_key ],
				];
			} else {
				$result[] = [
					'name'  => '',
					'count' => 0,
				];
			}
		}

		return $result;
	}

	/**
	 * Get summary report data for a given date range.
	 *
	 * Note: Event counts include ALL logged events, including those from experimental/verbose
	 * loggers like WPCronLogger, WPRESTAPIRequestsLogger, and WPHTTPRequestsLogger. These loggers
	 * can generate hundreds of events per day from background system activity.
	 *
	 * This can cause a mismatch between email stats and what users see in the log UI, because:
	 * 1. Experimental loggers may be disabled after events were logged
	 * 2. The log UI filters events based on user permissions and enabled loggers
	 * 3. Users clicking through from email may see fewer events than reported
	 *
	 * Potential future solutions:
	 * - Filter stats to only include "standard" loggers (exclude experimental/verbose ones)
	 * - Show breakdown in email: "X user events + Y system events"
	 * - Add disclaimer text explaining the mismatch
	 * - Make day links include all events regardless of current logger settings
	 *
	 * @param int  $date_from Start timestamp.
	 * @param int  $date_to End timestamp.
	 * @param bool $is_preview Whether this is a preview email.
	 * @return array
	 */
	public function get_summary_report_data( $date_from, $date_to, $is_preview = false ) {
		// Get stats for the specified period.
		$events_stats = new Events_Stats();

		// Get basic site info.
		$stats = [
			'site_name'       => get_bloginfo( 'name' ),
			'site_url'        => get_bloginfo( 'url' ),
			'site_url_domain' => wp_parse_url( get_bloginfo( 'url' ), PHP_URL_HOST ),
			// Date range as string, as it's displayed in the email.
			'date_range'      => sprintf(
				/* translators: 1: start date with day name, 2: end date with day name, 3: year */
				__( '%1$s – %2$s, %3$s', 'simple-history' ),
				wp_date(
					sprintf(
						/* translators: %s is the site's date format setting without year */
						__( 'D %s', 'simple-history' ),
						// Remove year from date format.
						trim( preg_replace( '/[,\s]*[YyoL][,\s]*/', '', get_option( 'date_format' ) ), ', ' )
					),
					$date_from
				),
				wp_date(
					sprintf(
						/* translators: %s is the site's date format setting without year */
						__( 'D %s', 'simple-history' ),
						// Remove year from date format.
						trim( preg_replace( '/[,\s]*[YyoL][,\s]*/', '', get_option( 'date_format' ) ), ', ' )
					),
					$date_to
				),
				wp_date( 'Y', $date_to )
			),
			'email_subject'   => $this->get_email_subject( $is_preview ),
		];

		// Get total events for this week.
		$stats['total_events_this_week'] = $events_stats->get_total_events( $date_from, $date_to );

		// Get all days with event counts for the template.
		// Don't limit or sort - template will use all days in chronological order.
		$peak_days = $events_stats->get_peak_days( $date_from, $date_to );

		// Convert to array format for template (handle both empty and populated results).
		// Use day numbers (0-6) instead of translated names to avoid language issues.
		$all_days = [];
		if ( $peak_days && is_array( $peak_days ) ) {
			foreach ( $peak_days as $day ) {
				$all_days[] = [
					// phpcs:ignore Squiz.PHP.CommentedOutCode.Found -- This explains value range.
					'day_number' => $day->day,  // 0=Sunday and 6=Saturday.
					'count'      => $day->count,
				];
			}
		}

		$stats['most_active_days'] = $all_days;

		// Find the busiest day (day with the highest count).
		$busiest_day_name = __( 'No activity', 'simple-history' );
		if ( ! empty( $all_days ) ) {
			$max_count          = 0;
			$busiest_day_number = 0;
			foreach ( $all_days as $day ) {
				if ( $day['count'] <= $max_count ) {
					continue;
				}

				$max_count          = $day['count'];
				$busiest_day_number = $day['day_number'];
			}
			// Only set the busiest day if there was actual activity.
			if ( $max_count > 0 ) {
				// Convert day number to localized day name.
				// day_number: 0=Sunday, 1=Monday, etc.
				$day_names        = [
					0 => __( 'Sunday', 'simple-history' ),
					1 => __( 'Monday', 'simple-history' ),
					2 => __( 'Tuesday', 'simple-history' ),
					3 => __( 'Wednesday', 'simple-history' ),
					4 => __( 'Thursday', 'simple-history' ),
					5 => __( 'Friday', 'simple-history' ),
					6 => __( 'Saturday', 'simple-history' ),
				];
				$busiest_day_name = $day_names[ $busiest_day_number ] ?? __( 'No activity', 'simple-history' );
			}
		}
		$stats['busiest_day_name'] = $busiest_day_name;

		// Pass date range timestamps for chronological day ordering in template.
		$stats['date_from_timestamp'] = $date_from;
		$stats['date_to_timestamp']   = $date_to;

		// Get most active users and format them for the template.
		$top_users                  = $events_stats->get_top_users( $date_from, $date_to, 3 );
		$stats['most_active_users'] = $this->prepare_top_items( $top_users, 3, 'display_name', 'count' );

		// Get user login statistics.
		$stats['successful_logins'] = $events_stats->get_successful_logins_count( $date_from, $date_to );
		$stats['failed_logins']     = $events_stats->get_failed_logins_count( $date_from, $date_to );

		// Get posts statistics.
		$stats['posts_created'] = $events_stats->get_posts_pages_created( $date_from, $date_to );
		$stats['posts_updated'] = $events_stats->get_posts_pages_updated( $date_from, $date_to );

		// Get plugins statistics.
		$stats['plugin_activations']   = $events_stats->get_plugin_activations_count( $date_from, $date_to );
		$stats['plugin_deactivations'] = $events_stats->get_plugin_deactivations_count( $date_from, $date_to );

		// Get user creation and modification statistics.
		$stats['users_created'] = $events_stats->get_user_added_count( $date_from, $date_to );
		$stats['users_updated'] = $events_stats->get_user_updated_count( $date_from, $date_to );

		// Get media statistics.
		$stats['media_uploads'] = $events_stats->get_media_uploads_count( $date_from, $date_to );
		$stats['media_edits']   = $events_stats->get_media_edits_count( $date_from, $date_to );

		// Get comments statistics (only query when comments are enabled).
		$stats['comments_enabled'] = get_option( 'default_comment_status' ) === 'open';

		if ( $stats['comments_enabled'] ) {
			$stats['comments_added']    = $events_stats->get_comments_added_count( $date_from, $date_to );
			$stats['comments_approved'] = $events_stats->get_comments_approved_count( $date_from, $date_to );
			$stats['comments_spam']     = $events_stats->get_comments_spam_count( $date_from, $date_to );
		}

		// Get theme statistics.
		$stats['theme_switches'] = $events_stats->get_theme_switches_count( $date_from, $date_to );
		$stats['theme_updates']  = $events_stats->get_theme_updates_count( $date_from, $date_to );

		// Get WordPress core statistics.
		$stats['wordpress_updates'] = $events_stats->get_wordpress_core_updates_count( $date_from, $date_to );

		// Get Notes statistics (WordPress 6.9+).
		global $wp_version;
		$stats['notes_enabled']  = version_compare( $wp_version, '6.9', '>=' );
		$stats['notes_added']    = $events_stats->get_notes_added_count( $date_from, $date_to );
		$stats['notes_resolved'] = $events_stats->get_notes_resolved_count( $date_from, $date_to );

		// Add history admin URL.
		$stats['history_admin_url'] = \Simple_History\Helpers::get_history_admin_url();

		// Add a log page URL behind every number in the report.
		$stats['stat_urls'] = $this->get_stat_urls( $date_from, $date_to );

		// The opening sentences, which need the numbers gathered above.
		$stats['summary_text'] = $this->get_summary_text( $stats, $date_from, $date_to );

		// Add settings URL for unsubscribe link.
		$stats['settings_url'] = admin_url( 'admin.php?page=simple_history_settings_page&selected-tab=general_settings_subtab_general&selected-sub-tab=general_settings_subtab_email_reports' );

		return $stats;
	}

	/**
	 * Opening sentences of the report.
	 *
	 * Says the things the numbers below cannot: how this week compares with
	 * the last one, and who was behind it. Everything already printed as a
	 * large number in its own block is left out, because a number in a box is
	 * read faster than the same number inside a sentence.
	 *
	 * The wording never varies for the same situation. A reader who gets this
	 * every week learns the sentence as a shape and reads it at a glance, so
	 * rewording it for variety would cost them that and give nothing back.
	 *
	 * @param array $stats     Report stats gathered so far.
	 * @param int   $date_from Start date as Unix timestamp.
	 * @param int   $date_to   End date as Unix timestamp.
	 * @return string One to three sentences, or an empty string.
	 */
	private function get_summary_text( $stats, $date_from, $date_to ) {
		$total     = (int) ( $stats['total_events_this_week'] ?? 0 );
		$previous  = $this->get_previous_period_total( $date_from, $date_to );
		$sentences = [];

		// How much happened, and whether that is more or less than last time.
		// On an empty week this is the sentence that tells someone their
		// logging stopped working, so it is never dropped.
		if ( $total === 0 && $previous === null ) {
			$sentences[] = __( 'No events were logged.', 'simple-history' );
		} elseif ( $total === 0 ) {
			$sentences[] = sprintf(
				/* translators: %s: number of events in the previous period */
				__( 'No events were logged, compared with %s the week before.', 'simple-history' ),
				number_format_i18n( $previous )
			);
		} elseif ( $previous === null ) {
			$sentences[] = sprintf(
				/* translators: %s: number of events */
				_n( 'Your site logged %s event.', 'Your site logged %s events.', $total, 'simple-history' ),
				number_format_i18n( $total )
			);
		} else {
			$sentences[] = sprintf(
				/* translators: 1: number of events this period, 2: number of events in the previous period */
				_n(
					'Your site logged %1$s event, compared with %2$s the week before.',
					'Your site logged %1$s events, compared with %2$s the week before.',
					$total,
					'simple-history'
				),
				number_format_i18n( $total ),
				number_format_i18n( $previous )
			);
		}

		// Failed logins, stated plainly at any number. A threshold above which
		// it becomes worth mentioning would be a judgement the log cannot make:
		// twenty attempts is background noise on a public site and a real
		// event on a private one.
		$failed_logins = (int) ( $stats['failed_logins'] ?? 0 );

		if ( $failed_logins > 0 ) {
			$sentences[] = sprintf(
				/* translators: %s: number of failed login attempts */
				_n( 'There was %s failed login.', 'There were %s failed logins.', $failed_logins, 'simple-history' ),
				number_format_i18n( $failed_logins )
			);
		}

		$most_active_user = $this->get_most_active_user_name( $stats, $total );

		if ( $most_active_user !== '' ) {
			$sentences[] = sprintf(
				/* translators: %s: display name of the user with the most events */
				__( '%s was the most active user.', 'simple-history' ),
				$most_active_user
			);
		}

		return implode( ' ', $sentences );
	}

	/**
	 * Name of the one user who was clearly the most active, if there is one.
	 *
	 * Silent unless the answer is unambiguous and worth saying: a busy enough
	 * week, more than one person in it, and a clear leader. A tie has no single
	 * most active user, so naming either of them would be false.
	 *
	 * @param array $stats Report stats.
	 * @param int   $total Total events in the period.
	 * @return string Display name, or an empty string when there is no clear answer.
	 */
	private function get_most_active_user_name( $stats, $total ) {
		// Below this the ranking says more about chance than about the week.
		if ( $total < 20 ) {
			return '';
		}

		$users = $stats['most_active_users'] ?? [];

		// Every entry is padded out to a fixed length, so drop the empty ones.
		$users = array_values(
			array_filter(
				$users,
				function ( $user ) {
					return ! empty( $user['name'] ) && ! empty( $user['count'] );
				}
			)
		);

		if ( count( $users ) < 2 ) {
			return '';
		}

		if ( (int) $users[0]['count'] === (int) $users[1]['count'] ) {
			return '';
		}

		return $users[0]['name'];
	}

	/**
	 * Total events in the period before this one, when it can be compared.
	 *
	 * Returns null when there is nothing stored yet, or when the stored period
	 * covered a different number of days — "the week before" has to be true.
	 *
	 * @param int $date_from Start date as Unix timestamp.
	 * @param int $date_to   End date as Unix timestamp.
	 * @return int|null Previous total, or null when there is nothing to compare with.
	 */
	private function get_previous_period_total( $date_from, $date_to ) {
		$days = $this->get_period_days( $date_from, $date_to );

		foreach ( $this->get_sent_periods() as $period ) {
			// A period of a different length is not "the week before", whether
			// the schedule changed or the first report covered a part week.
			if ( (int) $period['days'] !== $days ) {
				continue;
			}

			$period_to = $this->parse_stored_date( $period['to'] );

			// It also has to be the period that ran up to this one. Reports
			// switched off for a month and back on again would otherwise
			// compare against a week from before the gap and call it last week.
			if ( $period_to === null || abs( $date_from - $period_to ) > 2 * DAY_IN_SECONDS ) {
				continue;
			}

			return (int) $period['total'];
		}

		return null;
	}

	/**
	 * The periods reports have been sent for, newest first.
	 *
	 * @return array List of [ 'from' => string, 'to' => string, 'days' => int, 'total' => int ].
	 */
	private function get_sent_periods() {
		$periods = get_option( self::SENT_PERIODS_OPTION );

		if ( ! is_array( $periods ) ) {
			return [];
		}

		return array_values(
			array_filter(
				$periods,
				function ( $period ) {
					return is_array( $period ) && isset( $period['from'], $period['to'], $period['days'], $period['total'] );
				}
			)
		);
	}

	/**
	 * Remember this period so the next report can compare against it.
	 *
	 * Only a report that was actually sent counts. Previews and test emails
	 * would otherwise overwrite the number a real report is going to be
	 * measured against.
	 *
	 * @param int $total     Total events in the period.
	 * @param int $date_from Start date as Unix timestamp.
	 * @param int $date_to   End date as Unix timestamp.
	 * @return void
	 */
	private function store_period_total( $total, $date_from, $date_to ) {
		$periods = $this->get_sent_periods();

		array_unshift(
			$periods,
			[
				// ISO 8601 in UTC, the same basis the events table stores its
				// dates on. Readable when the option is opened, sorts the way
				// it reads, and cannot drift if the site's timezone changes.
				// Render with wp_date() to show it in the site's timezone.
				'from'  => gmdate( 'Y-m-d\TH:i:s\Z', $date_from ),
				'to'    => gmdate( 'Y-m-d\TH:i:s\Z', $date_to ),
				'days'  => $this->get_period_days( $date_from, $date_to ),
				'total' => (int) $total,
			]
		);

		update_option(
			self::SENT_PERIODS_OPTION,
			array_slice( $periods, 0, self::MAX_STORED_PERIODS ),
			false
		);
	}

	/**
	 * Timestamp for a date stored in the option.
	 *
	 * @param mixed $value ISO 8601 date string in UTC.
	 * @return int|null Unix timestamp, or null when the value cannot be read.
	 */
	private function parse_stored_date( $value ) {
		if ( ! is_string( $value ) || $value === '' ) {
			return null;
		}

		try {
			$date = new \DateTimeImmutable( $value );
		} catch ( \Exception $e ) {
			return null;
		}

		return $date->getTimestamp();
	}

	/**
	 * Number of days a report period covers.
	 *
	 * @param int $date_from Start date as Unix timestamp.
	 * @param int $date_to   End date as Unix timestamp.
	 * @return int Days, at least 1.
	 */
	private function get_period_days( $date_from, $date_to ) {
		$days = (int) round( ( $date_to - $date_from ) / DAY_IN_SECONDS );

		return max( 1, $days );
	}

	/**
	 * Log page URLs for the numbers in the report, keyed by stat.
	 *
	 * Every number in the email answers "how many", and the reader's next
	 * question is "which ones". Each URL filters the log to the same logger and
	 * message keys the stat was counted from, over the same days, so the page
	 * they land on holds the events behind the number they clicked.
	 *
	 * @param int $date_from Start date as Unix timestamp.
	 * @param int $date_to   End date as Unix timestamp.
	 * @return array<string,string> Stat key to log page URL.
	 */
	private function get_stat_urls( $date_from, $date_to ) {
		// Label, logger slug and message keys per stat. The keys have to stay
		// in step with the Events_Stats methods the counts come from, or a
		// number will lead to a page that disagrees with it.
		$stat_events = [
			'successful_logins'    => [ __( 'Successful logins', 'simple-history' ), 'SimpleUserLogger', [ 'user_logged_in', 'user_unknown_logged_in' ] ],
			'failed_logins'        => [ __( 'Failed logins', 'simple-history' ), 'SimpleUserLogger', User_Logger::get_failed_login_message_keys() ],
			'users_created'        => [ __( 'Users created', 'simple-history' ), 'SimpleUserLogger', [ 'user_created' ] ],
			'users_updated'        => [ __( 'Profile updates', 'simple-history' ), 'SimpleUserLogger', [ 'user_updated_profile' ] ],
			'posts_created'        => [ __( 'Posts and pages created', 'simple-history' ), 'SimplePostLogger', [ 'post_created' ] ],
			'posts_updated'        => [ __( 'Posts and pages edited', 'simple-history' ), 'SimplePostLogger', [ 'post_updated' ] ],
			'media_uploads'        => [ __( 'Media uploads', 'simple-history' ), 'SimpleMediaLogger', [ 'attachment_created' ] ],
			'media_edits'          => [ __( 'Media edits', 'simple-history' ), 'SimpleMediaLogger', [ 'attachment_updated' ] ],
			'comments_added'       => [ __( 'Comments added', 'simple-history' ), 'SimpleCommentsLogger', [ 'anon_comment_added', 'user_comment_added' ] ],
			'comments_approved'    => [ __( 'Comments approved', 'simple-history' ), 'SimpleCommentsLogger', [ 'comment_status_approve' ] ],
			'comments_spam'        => [ __( 'Comments marked as spam', 'simple-history' ), 'SimpleCommentsLogger', [ 'comment_status_spam' ] ],
			'notes_added'          => [ __( 'Notes added', 'simple-history' ), 'NotesLogger', [ 'note_added', 'note_reply_added' ] ],
			'notes_resolved'       => [ __( 'Notes resolved', 'simple-history' ), 'NotesLogger', [ 'note_resolved' ] ],
			'plugin_activations'   => [ __( 'Plugin activations', 'simple-history' ), 'SimplePluginLogger', [ 'plugin_activated' ] ],
			'plugin_deactivations' => [ __( 'Plugin deactivations', 'simple-history' ), 'SimplePluginLogger', [ 'plugin_deactivated' ] ],
			'theme_switches'       => [ __( 'Theme switches', 'simple-history' ), 'SimpleThemeLogger', [ 'theme_switched' ] ],
			'theme_updates'        => [ __( 'Theme updates', 'simple-history' ), 'SimpleThemeLogger', [ 'theme_updated' ] ],
			'wordpress_updates'    => [ __( 'WordPress core updates', 'simple-history' ), 'SimpleCoreUpdatesLogger', [ 'core_updated', 'core_auto_updated' ] ],
		];

		// The report covers whole days in the site's timezone, so the log has to
		// be asked for those same days rather than the last seven from now.
		$from = ( new \DateTimeImmutable( '@' . $date_from ) )->setTimezone( wp_timezone() )->format( 'Y-m-d' );
		$to   = ( new \DateTimeImmutable( '@' . $date_to ) )->setTimezone( wp_timezone() )->format( 'Y-m-d' );

		$date_args = [
			'date' => 'customRange',
			'from' => $from,
			'to'   => $to,
		];

		// The whole period, for the total events number.
		$urls = [ 'total_events_this_week' => Helpers::get_filtered_history_url( $date_args ) ];

		foreach ( $stat_events as $stat_key => $stat_event ) {
			list( $label, $logger_slug, $message_keys ) = $stat_event;

			$search_options = [];

			foreach ( $message_keys as $message_key ) {
				$search_options[] = $logger_slug . ':' . $message_key;
			}

			$urls[ $stat_key ] = Helpers::get_filtered_history_url(
				array_merge(
					$date_args,
					[
						'messages' => [
							[
								'value'          => $label,
								'search_options' => $search_options,
							],
						],
					]
				)
			);
		}

		return $urls;
	}

	/**
	 * The Premium teaser shown under the intro, for free users.
	 *
	 * Every teaser that matches this week's activity goes into a pool with the
	 * generic ones, and the pick rotates by week number so the same activity
	 * does not produce the same line two weeks running.
	 *
	 * Both versions of the email ask for this, so that the text part of a
	 * message says what its HTML part says.
	 *
	 * @param array $args Email template args.
	 * @return string Teaser text, empty when there is no upsell to show.
	 */
	public function get_top_teaser_text( $args ) {
		/** This filter is documented in templates/email-summary-report.php */
		$show_upsell = apply_filters( 'simple_history/email_summary_report/show_upsell', Helpers::show_promo_boxes() );

		if ( ! $show_upsell ) {
			return '';
		}

		$teaser_pool = [];

		if ( ( $args['failed_logins'] ?? 0 ) > 0 ) {
			$teaser_pool[] = __( 'With Premium, this email shows the IP addresses and usernames behind every failed login attempt.', 'simple-history' );
		}

		if ( ( $args['plugin_activations'] ?? 0 ) + ( $args['plugin_deactivations'] ?? 0 ) > 0 ) {
			$teaser_pool[] = __( 'With Premium, this email names each plugin and the person who changed it.', 'simple-history' );
		}

		// Only tease the posts list when activity is notable — low counts don't create curiosity.
		if ( ( $args['posts_created'] ?? 0 ) + ( $args['posts_updated'] ?? 0 ) > 3 ) {
			$teaser_pool[] = __( 'With Premium, this email shows who edited which posts and when.', 'simple-history' );
		}

		if ( ( $args['users_created'] ?? 0 ) > 0 ) {
			$teaser_pool[] = __( 'With Premium, this email includes the username and role of every new account.', 'simple-history' );
		}

		// Generic teasers, always in the pool.
		$teaser_pool[] = __( 'Premium fills this email in with the details — which post, which plugin, who logged in — and sends real-time alerts for critical events, so you don\'t have to wait for Monday to hear about them.', 'simple-history' );
		$teaser_pool[] = __( 'With Premium, this email lists who did what — the names behind the numbers below.', 'simple-history' );

		// Retention teaser only when the log is actually purged. Retention is
		// 30 days on new installs and 60 on older ones, so never hardcode it.
		$retention_days = Helpers::get_clear_history_interval();

		if ( $retention_days > 0 ) {
			$teaser_pool[] = sprintf(
				/* translators: %d: number of days events are kept. */
				_n(
					'Free logs are removed after %d day. Premium keeps them as long as you need, so you can still see what changed months later.',
					'Free logs are removed after %d days. Premium keeps them as long as you need, so you can still see what changed months later.',
					$retention_days,
					'simple-history'
				),
				$retention_days
			);
		}

		$tips_service = Simple_History::get_instance()->get_service( Tips_Service::class );
		$week_index   = $tips_service instanceof Tips_Service ? $tips_service->get_week_index( $args ) : (int) gmdate( 'W' );

		$top_teaser_text = $teaser_pool[ $week_index % count( $teaser_pool ) ];

		/**
		 * Filter the teaser text shown under the intro.
		 * Return an empty string to hide the teaser.
		 *
		 * @param string $top_teaser_text The teaser text.
		 * @param array  $args The email template args.
		 */
		return apply_filters( 'simple_history/email_summary_report/top_teaser_text', $top_teaser_text, $args );
	}

	/**
	 * The body text of the upsell block at the bottom of the email, for free users.
	 *
	 * Both versions of the email ask for this, so that the text part of a
	 * message says what its HTML part says.
	 *
	 * @return string Upsell text.
	 */
	public function get_upsell_block_text() {
		$retention_days = Helpers::get_clear_history_interval();

		// Retention can be set to forever with a filter, then only the other features apply.
		if ( $retention_days <= 0 ) {
			return __( 'Premium adds real-time alerts, Slack notifications, CSV export, and log forwarding to syslog or external databases.', 'simple-history' );
		}

		return sprintf(
			/* translators: %d: number of days events are kept. */
			_n(
				'Free logs are removed after %d day. Premium lets you keep them longer — and adds real-time alerts, Slack notifications, CSV export, and log forwarding to syslog or external databases.',
				'Free logs are removed after %d days. Premium lets you keep them longer — and adds real-time alerts, Slack notifications, CSV export, and log forwarding to syslog or external databases.',
				$retention_days,
				'simple-history'
			),
			$retention_days
		);
	}

	/**
	 * Render one of the report templates into a string.
	 *
	 * @param string $template    Template file name, inside templates/.
	 * @param array  $report_data Data the template renders.
	 * @return string
	 */
	private function render_report_template( $template, $report_data ) {
		ob_start();

		load_template( SIMPLE_HISTORY_PATH . 'templates/' . $template, false, $report_data );

		return ob_get_clean();
	}

	/**
	 * Send one report email, with a plain text part alongside the HTML.
	 *
	 * wp_mail() cannot carry a text alternative on its own: the message it is
	 * given goes straight to PHPMailer's Body, and AltBody is reset to an
	 * empty string on every call. The phpmailer_init hook fires after that
	 * reset and before the message is assembled, so setting AltBody there is
	 * what makes PHPMailer build a multipart/alternative email.
	 *
	 * The hook is global, so the callback is added and removed around this one
	 * send. Left attached it would put this report inside every email the site
	 * sends afterwards. It runs last so that a mailer plugin generating its own
	 * AltBody out of the HTML does not overwrite the text written for this.
	 *
	 * A plugin that filters pre_wp_mail short-circuits before phpmailer_init
	 * runs, and an API mailer may only map the HTML body. Both end up sending
	 * what was sent before this existed: HTML only, never a broken email.
	 *
	 * @param string   $recipient Recipient email address.
	 * @param string   $subject   Email subject.
	 * @param string   $html      HTML version of the report.
	 * @param string   $text      Plain text version of the report.
	 * @param string[] $headers   Email headers.
	 * @return bool Whether the email was handed off for sending.
	 */
	private function send_report_email( $recipient, $subject, $html, $text, $headers ) {
		/**
		 * Add the text part to the message PHPMailer is about to build.
		 *
		 * @param \PHPMailer\PHPMailer\PHPMailer $phpmailer The mailer instance.
		 */
		$set_alt_body = function ( $phpmailer ) use ( $text ) {
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPMailer's property name.
			$phpmailer->AltBody = $text;
		};

		add_action( 'phpmailer_init', $set_alt_body, PHP_INT_MAX );

		try {
			// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.wp_mail_wp_mail -- Not bulk, this goes to a single manually added recipient.
			return wp_mail( $recipient, $subject, $html, $headers );
		} finally {
			remove_action( 'phpmailer_init', $set_alt_body, PHP_INT_MAX );
		}
	}

	/**
	 * Generate email subject for reports.
	 *
	 * @param bool $is_preview Whether this is a preview email.
	 * @return string
	 */
	private function get_email_subject( $is_preview = false ) {
		$subject = sprintf(
			// translators: %s: Site name.
			__( 'Weekly Activity Summary for %s', 'simple-history' ),
			get_bloginfo( 'name' )
		);

		if ( $is_preview ) {
			$subject .= ' (preview)';
		}

		return $subject;
	}

	/**
	 * REST API endpoint for sending preview email.
	 */
	public function rest_preview_email() {
		$current_user = wp_get_current_user();

		// Preview shows last 7 days including today, matching sidebar "7 days" stat.
		$date_range = Date_Helper::get_last_n_days_range( Date_Helper::DAYS_PER_WEEK );
		$date_from  = $date_range['from'];
		$date_to    = $date_range['to'];

		$report_data = $this->get_summary_report_data( $date_from, $date_to, true );

		$email_content = $this->render_report_template( 'email-summary-report.php', $report_data );
		$text_content  = $this->render_report_template( 'email-summary-report-text.php', $report_data );

		$subject = $this->get_email_subject( true );

		$headers = [ 'Content-Type: text/html; charset=UTF-8' ];

		$sent = $this->send_report_email(
			$current_user->user_email,
			$subject,
			$email_content,
			$text_content,
			$headers
		);

		if ( $sent ) {
			return rest_ensure_response(
				[
					'success' => true,
					'message' => sprintf(
						/* translators: %s: Email address */
						__( 'Test email sent successfully to %s.', 'simple-history' ),
						$current_user->user_email
					),
				]
			);
		}

		return new \WP_Error(
			'email_send_failed',
			__( 'Failed to send test email.', 'simple-history' ),
			[ 'status' => 500 ]
		);
	}

	/**
	 * REST API endpoint for getting HTML preview.
	 */
	public function rest_preview_html() {
		// Preview shows last 7 days including today, matching sidebar "7 days" stat.
		$date_range = Date_Helper::get_last_n_days_range( Date_Helper::DAYS_PER_WEEK );
		$date_from  = $date_range['from'];
		$date_to    = $date_range['to'];

		// Set content type to HTML.
		header( 'Content-Type: text/html; charset=UTF-8' );

		load_template(
			SIMPLE_HISTORY_PATH . 'templates/email-summary-report.php',
			false,
			$this->get_summary_report_data( $date_from, $date_to, true )
		);

		exit;
	}

	/**
	 * Register settings for email report.
	 */
	public function register_settings() {
		// Add settings section for email reports.
		Helpers::add_settings_section(
			'simple_history_email_report_section',
			[ __( 'Email Reports (Weekly Activity Digest)', 'simple-history' ), 'schedule_send', 'simple_history_email_report_section' ],
			[ $this, 'settings_section_output' ],
			self::SETTINGS_PAGE_SLUG,
			[
				'callback_last' => [ $this, 'settings_section_output_last' ],
			],
		);

		register_setting(
			self::SETTINGS_OPTION_GROUP,
			'simple_history_email_report_enabled',
			[
				'type'              => 'boolean',
				'default'           => false,
				'sanitize_callback' => 'rest_sanitize_boolean',
			]
		);

		register_setting(
			self::SETTINGS_OPTION_GROUP,
			'simple_history_email_report_recipients',
			[
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => [ $this, 'sanitize_email_recipients' ],
			]
		);

		// Add settings fields directly (no longer via hook).
		add_settings_field(
			'simple_history_email_report_enabled',
			Helpers::get_settings_field_title_output( __( 'Enable', 'simple-history' ), 'mark_email_unread' ),
			[ $this, 'settings_field_enabled' ],
			self::SETTINGS_PAGE_SLUG,
			'simple_history_email_report_section'
		);

		add_settings_field(
			'simple_history_email_report_recipients',
			Helpers::get_settings_field_title_output( __( 'Recipients', 'simple-history' ), 'group_add' ),
			[ $this, 'settings_field_recipients' ],
			self::SETTINGS_PAGE_SLUG,
			'simple_history_email_report_section'
		);

		add_settings_field(
			'simple_history_email_report_preview',
			Helpers::get_settings_field_title_output( __( 'Preview', 'simple-history' ), 'preview' ),
			[ $this, 'settings_field_preview' ],
			self::SETTINGS_PAGE_SLUG,
			'simple_history_email_report_section'
		);
	}

	/**
	 * Output for the last content of the email report settings section.
	 */
	public function settings_section_output_last() {
		echo '<p>💡 ' . esc_html__( 'Pro tip: The digest helps you catch unauthorized changes even when you\'re away from your site.', 'simple-history' ) . '</p>';
	}

	/**
	 * Output for the email report settings section.
	 */
	public function settings_section_output() {
		?>
		<p>
			<strong><?php esc_html_e( 'Stay on top of your site without logging in.', 'simple-history' ); ?></strong>
		</p>
		<?php
		$this->output_preview_thumbnail();
	}

	/**
	 * Get the URL of the HTML preview of the email.
	 *
	 * @return string URL, unescaped.
	 */
	private function get_preview_url() {
		return add_query_arg(
			[
				'_wpnonce' => wp_create_nonce( 'wp_rest' ),
			],
			rest_url( 'simple-history/v1/email-report/preview/html' )
		);
	}

	/**
	 * Output a scaled-down live preview of the email next to the settings.
	 *
	 * Shown only while the email is off: it is there to make the case for turning it on,
	 * and it builds a full report, so users who already get the email do not pay for it.
	 * Hidden by CSS when the settings card is too narrow for it.
	 *
	 * The iframe gets its src from the script below only when the thumbnail is visible.
	 * With src in the markup, browsers load it even when hidden: loading="lazy" does not
	 * apply to display:none iframes.
	 */
	private function output_preview_thumbnail() {
		if ( $this->is_email_reports_enabled() ) {
			return;
		}

		$preview_url = $this->get_preview_url();

		?>
		<aside class="sh-EmailReportThumbnail" aria-labelledby="sh-EmailReportThumbnail-label" hidden>
			<p class="sh-EmailReportThumbnail-label" id="sh-EmailReportThumbnail-label">
				<?php esc_html_e( 'Preview', 'simple-history' ); ?>
			</p>
			<p class="sh-EmailReportThumbnail-description">
				<?php esc_html_e( 'Using real data from the last 7 days.', 'simple-history' ); ?>
			</p>
			<a class="sh-EmailReportThumbnail-link" href="<?php echo esc_url( $preview_url ); ?>" target="_blank">
				<span class="sh-EmailReportThumbnail-frame">
					<iframe
						class="sh-EmailReportThumbnail-iframe"
						data-src="<?php echo esc_url( $preview_url ); ?>"
						title="<?php esc_attr_e( 'Preview of the weekly email', 'simple-history' ); ?>"
						tabindex="-1"
						aria-hidden="true"
						scrolling="no"
					></iframe>
				</span>
				<span class="sh-EmailReportThumbnail-open"><?php esc_html_e( 'Open full size', 'simple-history' ); ?></span>
			</a>
		</aside>
		<script>
			( function () {
				var thumbnail = document.currentScript.previousElementSibling;
				var iframe = thumbnail.querySelector( 'iframe' );

				thumbnail.hidden = false;

				function loadIfVisible() {
					if ( iframe.src || getComputedStyle( thumbnail ).display === 'none' ) {
						return;
					}

					iframe.src = iframe.dataset.src;
				}

				loadIfVisible();
				window.addEventListener( 'resize', loadIfVisible );
			} )();
		</script>
		<?php
	}

	/**
	 * Output for the preview and test setting field.
	 */
	public function settings_field_preview() {
		$current_user = wp_get_current_user();
		$preview_url  = $this->get_preview_url();
		?>
		<div>
			<p>
				<a href="<?php echo esc_url( $preview_url ); ?>" target="_blank" class="button button-link">
					<?php esc_html_e( 'Show email preview', 'simple-history' ); ?>
				</a>
				|
				<button type="button" class="button button-link" id="simple-history-email-test">
					<?php
					printf(
						// translators: %s: Current user's email address.
						esc_html__( 'Send test email to %s', 'simple-history' ),
						esc_html( $current_user->user_email )
					);
					?>
				</button>
			</p>
		</div>
		<script>
			jQuery(document).ready(function($) {
				// Handle test email
				$('#simple-history-email-test').on('click', function() {
					wp.apiFetch({
						path: '/simple-history/v1/email-report/preview/email',
						method: 'POST'
					}).then(function(response) {
						alert(response.message);
					}).catch(function(error) {
						alert('<?php esc_html_e( 'Failed to send test email.', 'simple-history' ); ?>');
					});
				});
			});
		</script>
		<?php
	}

	/**
	 * Output for the pro tip field.
	 */
	public function settings_field_pro_tip() {
		?>
		<div class="sh-EmailReportProTip">
			<p>
				💡 <?php esc_html_e( 'Pro tip: The digest helps you catch unauthorized changes even when you\'re away from your site.', 'simple-history' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Sanitize email recipients.
	 *
	 * Detects all emails in textarea and sanitizes them.
	 * Emails are separated by spaces, commas or newlines.
	 * Final result is a string with one email address per line (separated by \n).
	 *
	 * @param string $textarea_contents Textarea contents.
	 * @return string
	 */
	public function sanitize_email_recipients( $textarea_contents ) {
		// First remove tags and scripts etc.
		$textarea_contents = sanitize_textarea_field( $textarea_contents );

		// Convert to array and sanitize each email.
		// Spaces or newlines are valid splits.
		$textarea_contents = preg_split( '/[\s,]+/', $textarea_contents );

		// Validate each item using WordPress is_email() function.
		$textarea_contents = array_filter(
			$textarea_contents,
			'is_email'
		);

		// Remove duplicates and reindex array.
		$textarea_contents = array_values( array_unique( $textarea_contents ) );

		// Join back to string.
		$textarea_contents = implode( "\n", $textarea_contents );

		return $textarea_contents;
	}

	/**
	 * Check if email reports are enabled.
	 *
	 * @return bool
	 */
	private function is_email_reports_enabled() {
		return get_option( 'simple_history_email_report_enabled', false );
	}

	/**
	 * Get email report recipients.
	 *
	 * @return string One email address per line (separated by \n).
	 */
	private function get_email_report_recipients() {
		return get_option( 'simple_history_email_report_recipients', '' );
	}

	/**
	 * Output for the enabled setting field.
	 */
	public function settings_field_enabled() {
		$enabled = $this->is_email_reports_enabled();
		?>
		<label>
			<input
				type="checkbox"
				name="simple_history_email_report_enabled"
				value="1"
				<?php checked( $enabled ); ?>
			/>
			<?php esc_html_e( 'Enable weekly digest', 'simple-history' ); ?>
		</label>

		<p style="margin-top: 1em;">
			<?php esc_html_e( 'Every Monday, get a summary of:', 'simple-history' ); ?>
		</p>

		<ul style="list-style: none; padding: 0; margin: 0.5em 0 0 0;">
			<li><span class="dashicons dashicons-yes" style="color: #00a32a; margin-inline-end: 0.25em;"></span><?php esc_html_e( 'Total event count and daily breakdown', 'simple-history' ); ?></li>
			<li><span class="dashicons dashicons-yes" style="color: #00a32a; margin-inline-end: 0.25em;"></span><?php esc_html_e( 'Number of posts and pages created or updated', 'simple-history' ); ?></li>
			<li><span class="dashicons dashicons-yes" style="color: #00a32a; margin-inline-end: 0.25em;"></span><?php esc_html_e( 'Login statistics (successful and failed)', 'simple-history' ); ?></li>
			<li><span class="dashicons dashicons-yes" style="color: #00a32a; margin-inline-end: 0.25em;"></span><?php esc_html_e( 'User creation and profile update counts', 'simple-history' ); ?></li>
			<li><span class="dashicons dashicons-yes" style="color: #00a32a; margin-inline-end: 0.25em;"></span><?php esc_html_e( 'Plugin activation and deactivation counts', 'simple-history' ); ?></li>
			<li><span class="dashicons dashicons-yes" style="color: #00a32a; margin-inline-end: 0.25em;"></span><?php esc_html_e( 'WordPress core update count', 'simple-history' ); ?></li>
		</ul>
		<?php
	}

	/**
	 * Output for the email recipients field.
	 */
	public function settings_field_recipients() {
		$recipients         = $this->get_email_report_recipients();
		$current_user_email = wp_get_current_user()->user_email;
		?>
		<p>
			<?php esc_html_e( 'Add team members to keep everyone informed.', 'simple-history' ); ?>
		</p>
		<textarea 
			data-simple-history-email-report-recipients
			data-simple-history-current-user-email="<?php echo esc_attr( $current_user_email ); ?>"
			placeholder="email@example.com&#10;another@example.com"
			style="field-sizing: content; min-width: 20rem; min-height: 3rem;" 
			name="simple_history_email_report_recipients" 
			id="simple_history_email_report_recipients" 
			class="regular-text" 
			rows="5" 
			cols="50"
		><?php echo esc_textarea( $recipients ); ?></textarea>
		<p class="description">
			<?php esc_html_e( 'Enter one email address per line.', 'simple-history' ); ?>
		</p>
		<?php
	}

	/**
	 * Schedule the email report.
	 */
	public function schedule_email_report() {
		// Bail if email reports are not enabled.
		if ( ! $this->is_email_reports_enabled() ) {
			return;
		}

		// Bail if email report is already scheduled.
		if ( wp_next_scheduled( 'simple_history/email_report' ) ) {
			return;
		}

		// Schedule for next Monday at 6:00 AM in WordPress timezone.
		$next_monday = new \DateTimeImmutable( 'next monday 6:00:00', wp_timezone() );
		wp_schedule_event( $next_monday->getTimestamp(), 'weekly', 'simple_history/email_report' );
	}

	/**
	 * Unschedule the email report.
	 */
	public function unschedule_email_report() {
		wp_clear_scheduled_hook( 'simple_history/email_report' );
	}

	/**
	 * Handle when email report enabled setting is updated.
	 *
	 * @param mixed $_old_value Old value (unused).
	 * @param mixed $new_value New value.
	 */
	public function on_email_report_enabled_updated( $_old_value, $new_value ) {
		if ( $new_value ) {
			$this->schedule_email_report();
		} else {
			$this->unschedule_email_report();
		}
	}

	/**
	 * Send the email report.
	 */
	public function send_email_report() {
		// Check if email report is enabled.
		if ( ! $this->is_email_reports_enabled() ) {
			return;
		}

		$recipients = $this->get_email_report_recipients();
		if ( empty( $recipients ) ) {
			return;
		}

		// Convert from newline string to array.
		$recipients = explode( "\n", $recipients );

		// Get stats for last complete week (Monday-Sunday).
		// Sent on Mondays, shows previous Mon-Sun, excludes current Monday.
		$date_range = Date_Helper::get_last_complete_week_range();
		$date_from  = $date_range['from'];
		$date_to    = $date_range['to'];

		$report_data = $this->get_summary_report_data( $date_from, $date_to, false );

		$email_content = $this->render_report_template( 'email-summary-report.php', $report_data );
		$text_content  = $this->render_report_template( 'email-summary-report-text.php', $report_data );

		$subject = $this->get_email_subject( false );

		$headers = [ 'Content-Type: text/html; charset=UTF-8' ];

		// Send to each recipient.
		foreach ( $recipients as $recipient ) {
			$this->send_report_email(
				$recipient,
				$subject,
				$email_content,
				$text_content,
				$headers
			);
		}

		$this->store_period_total( $report_data['total_events_this_week'] ?? 0, $date_from, $date_to );
	}
}
