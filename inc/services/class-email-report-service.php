<?php

namespace Simple_History\Services;

use Automattic\Jetpack\Import\Endpoints\Template_Part;
use Simple_History\Simple_History;
use Simple_History\Helpers;
use Simple_History\Services\Stats_Service;
use Simple_History\Events_Stats;

/**
 * Service that handles email reports.
 */
class Email_Report_Service extends Service {
	/**
	 * @inheritdoc
	 */
	public function loaded() {
		// Only load if experimental features are enabled.
		if ( ! Helpers::experimental_features_is_enabled() ) {
			return;
		}

		// Register settings.
		add_action( 'admin_init', [ $this, 'register_settings' ] );

		// Add settings fields to general section.
		add_action( 'simple_history/settings_page/general_section_output', [ $this, 'on_general_section_output' ] );

		// Register REST API endpoints.
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );

		// Schedule email report.
		add_action( 'init', [ $this, 'schedule_email_report' ] );
		add_action( 'simple_history/email_report', [ $this, 'send_email_report' ] );
	}

	/**
	 * Register REST API routes.
	 */
	public function register_rest_routes() {
		register_rest_route(
			'simple-history/v1',
			'/email-report/preview/email',
			[
				'methods' => [ \WP_REST_Server::CREATABLE, \WP_REST_Server::READABLE ],
				'callback' => [ $this, 'rest_preview_email' ],
				'permission_callback' => [ $this, 'rest_permission_callback' ],
			]
		);

		register_rest_route(
			'simple-history/v1',
			'/email-report/preview/html',
			[
				'methods' => \WP_REST_Server::READABLE,
				'callback' => [ $this, 'rest_preview_html' ],
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
					'name' => '',
					'count' => 0,
				]
			);
		}

		for ( $i = 0; $i < $limit; $i++ ) {
			if ( isset( $items[ $i ] ) ) {
				$item = $items[ $i ];
				$result[] = [
					'name' => is_object( $item ) ? $item->$name_key : $item[ $name_key ],
					'count' => is_object( $item ) ? $item->$count_key : $item[ $count_key ],
				];
			} else {
				$result[] = [
					'name' => '',
					'count' => 0,
				];
			}
		}

		return $result;
	}

	/**
	 * Get summary report data for a given date range.
	 *
	 * @param int $date_from Start timestamp.
	 * @param int $date_to End timestamp.
	 * @return array
	 */
	public function get_summary_report_data( $date_from, $date_to ) {
		// Get stats for the specified period.
		$events_stats = new Events_Stats();

		// Get basic site info.
		$stats = [
			'site_name' => get_bloginfo( 'name' ),
			'site_url' => get_bloginfo( 'url' ),
			'site_url_domain' => parse_url( get_bloginfo( 'url' ), PHP_URL_HOST ),
			'date_range' => sprintf(
				/* translators: 1: start date, 2: end date */
				__( '%1$s to %2$s', 'simple-history' ),
				date_i18n( get_option( 'date_format' ), $date_from ),
				date_i18n( get_option( 'date_format' ), $date_to )
			),
			'total_events_since_install' => Helpers::get_total_logged_events_count(),
		];

		// Get total events for this week.
		$stats['total_events_this_week'] = $events_stats->get_total_events( $date_from, $date_to );

		// Get most active days and format them for the template.
		$peak_days = $events_stats->get_peak_days( $date_from, $date_to );
		if ( $peak_days && is_array( $peak_days ) ) {
			// Sort by count descending to get the most active days first.
			usort(
				$peak_days,
				function ( $a, $b ) {
					return $b->count - $a->count;
				}
			);
		}

		$stats['most_active_days'] = $this->prepare_top_items( $peak_days, 3, 'day_name', 'count' );

		// Get most active users and format them for the template.
		$top_users = $events_stats->get_top_users( $date_from, $date_to, 3 );
		$stats['most_active_users'] = $this->prepare_top_items( $top_users, 3, 'display_name', 'count' );

		return $stats;
	}

	/**
	 * REST API endpoint for sending preview email.
	 */
	public function rest_preview_email() {
		$current_user = wp_get_current_user();
		$date_from = strtotime( '-7 days' );
		$date_to = time();

		ob_start();
		load_template(
			SIMPLE_HISTORY_PATH . 'templates/email-summary-report.php',
			false,
			$this->get_summary_report_data( $date_from, $date_to )
		);
		$email_content = ob_get_clean();

		$subject = sprintf(
			// translators: %s: Site name.
			__( 'Simple History: Weekly Activity Summary for %s', 'simple-history' ),
			get_bloginfo( 'name' )
		);

		$headers = [ 'Content-Type: text/html; charset=UTF-8' ];

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
					'message' => __( 'Test email sent successfully.', 'simple-history' ),
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
		$date_from = strtotime( '-7 days' );
		$date_to = time();

		// Set content type to HTML.
		header( 'Content-Type: text/html; charset=UTF-8' );

		load_template(
			SIMPLE_HISTORY_PATH . 'templates/email-summary-report.php',
			false,
			$this->get_summary_report_data( $date_from, $date_to )
		);

		exit;
	}

	/**
	 * Register settings for email report.
	 */
	public function register_settings() {
		$settings_general_option_group = Simple_History::SETTINGS_GENERAL_OPTION_GROUP;
		$settings_menu_slug = Simple_History::SETTINGS_MENU_SLUG;

		// Add settings section for email reports.
		Helpers::add_settings_section(
			'simple_history_email_report_section',
			[ __( 'Email Reports (experimental)', 'simple-history' ), 'mark_email_unread' ],
			[ $this, 'settings_section_output' ],
			$settings_menu_slug
		);

		register_setting(
			$settings_general_option_group,
			'simple_history_email_report_enabled',
			[
				'type' => 'boolean',
				'default' => false,
				'sanitize_callback' => 'rest_sanitize_boolean',
			]
		);

		register_setting(
			$settings_general_option_group,
			'simple_history_email_report_recipients',
			[
				'type' => 'string',
				'default' => '',
				'sanitize_callback' => [ $this, 'sanitize_email_recipients' ],
			]
		);
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
			Helpers::get_settings_field_title_output( __( 'Enable', 'simple-history' ), 'email' ),
			[ $this, 'settings_field_enabled' ],
			$settings_menu_slug,
			'simple_history_email_report_section'
		);

		add_settings_field(
			'simple_history_email_report_recipients',
			Helpers::get_settings_field_title_output( __( 'Recipients', 'simple-history' ), 'groups' ),
			[ $this, 'settings_field_recipients' ],
			$settings_menu_slug,
			'simple_history_email_report_section'
		);
	}

	/**
	 * Output for the email report settings section.
	 */
	public function settings_section_output() {
		echo '<p>' . esc_html__( 'Configure automatic email reports with website statistics. Reports are sent every Monday morning.', 'simple-history' ) . '</p>';

		$current_user = wp_get_current_user();
		$preview_url = add_query_arg(
			[
				'_wpnonce' => wp_create_nonce( 'wp_rest' ),
			],
			rest_url( 'simple-history/v1/email-report/preview/html' )
		);
		?>
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
			<?php esc_html_e( 'Send an email report with website statistics every Monday morning.', 'simple-history' ); ?>
		</label>
		<?php
	}

	/**
	 * Output for the email recipients field.
	 */
	public function settings_field_recipients() {
		$recipients = $this->get_email_report_recipients();
		$current_user_email = wp_get_current_user()->user_email;
		?>
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
		if ( ! wp_next_scheduled( 'simple_history/email_report' ) ) {
			// Schedule for next Monday at 8:00 AM.
			$next_monday = strtotime( 'next monday 8:00:00' );
			wp_schedule_event( $next_monday, 'weekly', 'simple_history/email_report' );
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

		// Get stats for the last 7 days.
		$date_from = strtotime( '-7 days' );
		$date_to = time();

		ob_start();
		load_template(
			SIMPLE_HISTORY_PATH . 'templates/email-summary-report.php',
			false,
			$this->get_summary_report_data( $date_from, $date_to )
		);
		$email_content = ob_get_clean();

		$subject = sprintf(
			// translators: %s: Site name.
			__( '[%s] Website Statistics Report', 'simple-history' ),
			get_bloginfo( 'name' )
		);

		$headers = [ 'Content-Type: text/html; charset=UTF-8' ];

		// Send to each recipient.
		foreach ( $recipients as $recipient ) {
			wp_mail(
				$recipient,
				$subject,
				$email_content,
				$headers
			);
		}
	}
}
