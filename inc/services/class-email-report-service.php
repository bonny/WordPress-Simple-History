<?php

namespace Simple_History\Services;

use Simple_History\Simple_History;
use Simple_History\Helpers;
use Simple_History\Events_Stats;
use Simple_History\Date_Helper;

/**
 * Service that handles email reports.
 */
class Email_Report_Service extends Service {
	/**
	 * @inheritdoc
	 */
	public function loaded() {
		// Register settings with priority 10 to ensure it loads before RSS feed (priority 15).
		add_action( 'admin_menu', [ $this, 'register_settings' ], 13 );

		// Add settings fields to general section.
		add_action( 'simple_history/settings_page/general_section_output', [ $this, 'on_general_section_output' ] );

		// Register REST API endpoints.
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );

		// Schedule email report.
		add_action( 'init', [ $this, 'schedule_email_report' ] );
		add_action( 'simple_history/email_report', [ $this, 'send_email_report' ] );

		// Handle enable/disable of email reports.
		add_action( 'update_option_simple_history_email_report_enabled', [ $this, 'on_email_report_enabled_updated' ], 10, 2 );
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
				if ( $day['count'] > $max_count ) {
					$max_count          = $day['count'];
					$busiest_day_number = $day['day_number'];
				}
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
				$busiest_day_name = isset( $day_names[ $busiest_day_number ] ) ? $day_names[ $busiest_day_number ] : __( 'No activity', 'simple-history' );
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

		// Get WordPress core statistics.
		$stats['wordpress_updates'] = $events_stats->get_wordpress_core_updates_count( $date_from, $date_to );

		// Add history admin URL.
		$stats['history_admin_url'] = \Simple_History\Helpers::get_history_admin_url();

		// Add settings URL for unsubscribe link.
		$stats['settings_url'] = admin_url( 'admin.php?page=simple_history_settings_page&selected-tab=general_settings_subtab_general&selected-sub-tab=general_settings_subtab_settings_general' );

		return $stats;
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

		ob_start();
		load_template(
			SIMPLE_HISTORY_PATH . 'templates/email-summary-report.php',
			false,
			$this->get_summary_report_data( $date_from, $date_to, true )
		);
		$email_content = ob_get_clean();

		$subject = $this->get_email_subject( true );

		$headers = [ 'Content-Type: text/html; charset=UTF-8' ];

		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.wp_mail_wp_mail -- Not bulk, this is a preview email sent to a single recipient.
		$sent = wp_mail(
			$current_user->user_email,
			$subject,
			$email_content,
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
		} else {
			return new \WP_Error(
				'email_send_failed',
				__( 'Failed to send test email.', 'simple-history' ),
				[ 'status' => 500 ]
			);
		}
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
		$settings_general_option_group = Simple_History::SETTINGS_GENERAL_OPTION_GROUP;
		$settings_menu_slug            = Simple_History::SETTINGS_MENU_SLUG;

		// Add settings section for email reports.
		Helpers::add_settings_section(
			'simple_history_email_report_section',
			[ __( 'Email Reports (Weekly Activity Digest)', 'simple-history' ), 'schedule_send', 'simple_history_email_report_section' ],
			[ $this, 'settings_section_output' ],
			$settings_menu_slug,
			[
				'callback_last' => [ $this, 'settings_section_output_last' ],
			],
		);

		register_setting(
			$settings_general_option_group,
			'simple_history_email_report_enabled',
			[
				'type'              => 'boolean',
				'default'           => false,
				'sanitize_callback' => 'rest_sanitize_boolean',
			]
		);

		register_setting(
			$settings_general_option_group,
			'simple_history_email_report_recipients',
			[
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => [ $this, 'sanitize_email_recipients' ],
			]
		);
	}

	/**
	 * Output for the last content of the email report settings section.
	 */
	public function settings_section_output_last() {
		echo '<p>💡 ' . esc_html__( 'Pro tip: The digest helps you catch unauthorized changes even when you\'re away from your site.', 'simple-history' ) . '</p>';
	}

	/**
	 * Add settings fields to general section.
	 *
	 * Function fired from action `simple_history/settings_page/general_section_output`.
	 */
	public function on_general_section_output() {
		$settings_menu_slug = Simple_History::SETTINGS_MENU_SLUG;

		add_settings_field(
			'simple_history_email_report_enabled',
			Helpers::get_settings_field_title_output( __( 'Enable', 'simple-history' ), 'mark_email_unread' ),
			[ $this, 'settings_field_enabled' ],
			$settings_menu_slug,
			'simple_history_email_report_section'
		);

		add_settings_field(
			'simple_history_email_report_recipients',
			Helpers::get_settings_field_title_output( __( 'Recipients', 'simple-history' ), 'group_add' ),
			[ $this, 'settings_field_recipients' ],
			$settings_menu_slug,
			'simple_history_email_report_section'
		);

		add_settings_field(
			'simple_history_email_report_preview',
			Helpers::get_settings_field_title_output( __( 'Preview', 'simple-history' ), 'preview' ),
			[ $this, 'settings_field_preview' ],
			$settings_menu_slug,
			'simple_history_email_report_section'
		);
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
	}

	/**
	 * Output for the preview and test setting field.
	 */
	public function settings_field_preview() {
		$current_user = wp_get_current_user();
		$preview_url  = add_query_arg(
			[
				'_wpnonce' => wp_create_nonce( 'wp_rest' ),
			],
			rest_url( 'simple-history/v1/email-report/preview/html' )
		);
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

		ob_start();
		load_template(
			SIMPLE_HISTORY_PATH . 'templates/email-summary-report.php',
			false,
			$this->get_summary_report_data( $date_from, $date_to, false )
		);
		$email_content = ob_get_clean();

		$subject = $this->get_email_subject( false );

		$headers = [ 'Content-Type: text/html; charset=UTF-8' ];

		// Send to each recipient.
		foreach ( $recipients as $recipient ) {
			// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.wp_mail_wp_mail -- Not bulk, this is the email that is sent to a short list of manually added recipients.
			wp_mail(
				$recipient,
				$subject,
				$email_content,
				$headers
			);
		}
	}
}
