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
		add_action( 'simple_history_email_report', [ $this, 'send_email_report' ] );
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
	 * REST API endpoint for sending preview email.
	 */
	public function rest_preview_email() {
		$current_user = wp_get_current_user();
		$date_from = strtotime( '-7 days' );
		$date_to = time();

		// Get stats for test email.
		$events_stats = new Events_Stats();

		$stats = [
			'total_logged_events_count' => Helpers::get_total_logged_events_count(),
			'period_stats' => [
				'total_events' => $events_stats->get_total_events( $date_from, $date_to ),
				'total_users' => $events_stats->get_total_users( $date_from, $date_to ),
				'most_active_users' => $events_stats->get_top_users( $date_from, $date_to, 5 ),
				'pages_created' => $events_stats->get_posts_pages_created( $date_from, $date_to ),
				'pages_updated' => $events_stats->get_posts_pages_updated( $date_from, $date_to ),
				'pages_deleted' => $events_stats->get_posts_pages_deleted( $date_from, $date_to ),
				'pages_trashed' => $events_stats->get_posts_pages_trashed( $date_from, $date_to ),
				'peak_days' => $events_stats->get_peak_days( $date_from, $date_to ),
				'peak_times' => $events_stats->get_peak_activity_times( $date_from, $date_to ),
				'activity_overview' => $events_stats->get_activity_overview_by_date( $date_from, $date_to ),
				'failed_logins' => $events_stats->get_failed_logins_count( $date_from, $date_to ),
				'successful_logins' => $events_stats->get_successful_logins_count( $date_from, $date_to ),
				'user_added' => $events_stats->get_user_added_count( $date_from, $date_to ),
				'user_removed' => $events_stats->get_user_removed_count( $date_from, $date_to ),
				'user_updated' => $events_stats->get_user_updated_count( $date_from, $date_to ),
			],
		];

		ob_start();
		include SIMPLE_HISTORY_PATH . 'templates/email-report-preview.php';
		$email_content = ob_get_clean();

		$site_name = get_bloginfo( 'name' );
		$subject = sprintf(
			// translators: %s: Site name.
			__( 'Simple History: Weekly Activity Summary for %s', 'simple-history' ),
			$site_name
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

		// Get stats for preview.
		$events_stats = new Events_Stats();

		$stats = [
			'total_logged_events_count' => Helpers::get_total_logged_events_count(),
			'period_stats' => [
				'total_events' => $events_stats->get_total_events( $date_from, $date_to ),
				'total_users' => $events_stats->get_total_users( $date_from, $date_to ),
				'most_active_users' => $events_stats->get_top_users( $date_from, $date_to, 5 ),
				'pages_created' => $events_stats->get_posts_pages_created( $date_from, $date_to ),
				'pages_updated' => $events_stats->get_posts_pages_updated( $date_from, $date_to ),
				'pages_deleted' => $events_stats->get_posts_pages_deleted( $date_from, $date_to ),
				'pages_trashed' => $events_stats->get_posts_pages_trashed( $date_from, $date_to ),
				'peak_days' => $events_stats->get_peak_days( $date_from, $date_to ),
				'peak_times' => $events_stats->get_peak_activity_times( $date_from, $date_to ),
				'activity_overview' => $events_stats->get_activity_overview_by_date( $date_from, $date_to ),
				'failed_logins' => $events_stats->get_failed_logins_count( $date_from, $date_to ),
				'successful_logins' => $events_stats->get_successful_logins_count( $date_from, $date_to ),
				'user_added' => $events_stats->get_user_added_count( $date_from, $date_to ),
				'user_removed' => $events_stats->get_user_removed_count( $date_from, $date_to ),
				'user_updated' => $events_stats->get_user_updated_count( $date_from, $date_to ),
			],
		];

		ob_start();
		include SIMPLE_HISTORY_PATH . 'templates/email-report-preview.php';
		$html_content = ob_get_clean();

		// Set content type to HTML.
		header( 'Content-Type: text/html; charset=UTF-8' );

		echo $html_content;
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
			<a href="<?php echo esc_url( $preview_url ); ?>" target="_blank" class="button">
				<?php esc_html_e( 'Show email preview', 'simple-history' ); ?>
			</a>
			|
			<button type="button" class="button" id="simple-history-email-test">
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
	 * Check if this is an email preview request and handle it.
	 */
	public function maybe_handle_email_preview() {
		// Bail if not a preview request.
		if ( ! isset( $_GET['simple_history_email_preview'] ) || ! isset( $_GET['_wpnonce'] ) ) {
			return;
		}

		// Verify nonce.
		if ( ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'simple_history_email_preview' ) ) {
			wp_die( esc_html__( 'Invalid nonce.', 'simple-history' ) );
		}

		// Check user capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'simple-history' ) );
		}

		$date_from = strtotime( '-7 days' );
		$date_to = time();

		// Get stats for preview.
		$events_stats = new Events_Stats();

		$stats = [
			'total_logged_events_count' => Helpers::get_total_logged_events_count(),
			'period_stats' => [
				'total_events' => $events_stats->get_total_events( $date_from, $date_to ),
				'total_users' => $events_stats->get_total_users( $date_from, $date_to ),
				'most_active_users' => $events_stats->get_top_users( $date_from, $date_to, 5 ),
				'pages_created' => $events_stats->get_posts_pages_created( $date_from, $date_to ),
				'pages_updated' => $events_stats->get_posts_pages_updated( $date_from, $date_to ),
				'pages_deleted' => $events_stats->get_posts_pages_deleted( $date_from, $date_to ),
				'pages_trashed' => $events_stats->get_posts_pages_trashed( $date_from, $date_to ),
				'peak_days' => $events_stats->get_peak_days( $date_from, $date_to ),
				'peak_times' => $events_stats->get_peak_activity_times( $date_from, $date_to ),
				'activity_overview' => $events_stats->get_activity_overview_by_date( $date_from, $date_to ),
				'failed_logins' => $events_stats->get_failed_logins_count( $date_from, $date_to ),
				'successful_logins' => $events_stats->get_successful_logins_count( $date_from, $date_to ),
				'user_added' => $events_stats->get_user_added_count( $date_from, $date_to ),
				'user_removed' => $events_stats->get_user_removed_count( $date_from, $date_to ),
				'user_updated' => $events_stats->get_user_updated_count( $date_from, $date_to ),
			],
		];

		// Include the preview template.
		load_template( SIMPLE_HISTORY_PATH . 'templates/email-report-preview.php', false, array() );
		exit;
	}

	/**
	 * Sanitize email recipients.
	 *
	 * Detects all emails in textarea and sanitizes them.
	 * Emails are separated by spaces, commas or newlines.
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
	 * Output for the enabled setting field.
	 */
	public function settings_field_enabled() {
		$enabled = get_option( 'simple_history_email_report_enabled', false );
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
		$recipients = get_option( 'simple_history_email_report_recipients', '' );
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
		if ( ! wp_next_scheduled( 'simple_history_email_report' ) ) {
			// Schedule for next Monday at 8:00 AM.
			$next_monday = strtotime( 'next monday 8:00:00' );
			wp_schedule_event( $next_monday, 'weekly', 'simple_history_email_report' );
		}
	}

	/**
	 * Send the email report.
	 */
	public function send_email_report() {
		// Check if email report is enabled.
		if ( ! get_option( 'simple_history_email_report_enabled', false ) ) {
			return;
		}

		$recipients = get_option( 'simple_history_email_report_recipients', '' );
		if ( empty( $recipients ) ) {
			return;
		}

		// Convert from newline string to array.
		$recipients = explode( "\n", $recipients );

		// Get stats for the last 7 days.
		$date_from = strtotime( '-7 days' );
		$date_to = time();

		// Get stats for email.
		$events_stats = new Events_Stats();

		$stats = [
			'total_logged_events_count' => Helpers::get_total_logged_events_count(),
			'period_stats' => [
				'total_events' => $events_stats->get_total_events( $date_from, $date_to ),
				'total_users' => $events_stats->get_total_users( $date_from, $date_to ),
				'most_active_users' => $events_stats->get_top_users( $date_from, $date_to, 5 ),
				'pages_created' => $events_stats->get_posts_pages_created( $date_from, $date_to ),
				'pages_updated' => $events_stats->get_posts_pages_updated( $date_from, $date_to ),
				'pages_deleted' => $events_stats->get_posts_pages_deleted( $date_from, $date_to ),
				'pages_trashed' => $events_stats->get_posts_pages_trashed( $date_from, $date_to ),
				'peak_days' => $events_stats->get_peak_days( $date_from, $date_to ),
				'peak_times' => $events_stats->get_peak_activity_times( $date_from, $date_to ),
				'activity_overview' => $events_stats->get_activity_overview_by_date( $date_from, $date_to ),
				'failed_logins' => $events_stats->get_failed_logins_count( $date_from, $date_to ),
				'successful_logins' => $events_stats->get_successful_logins_count( $date_from, $date_to ),
				'user_added' => $events_stats->get_user_added_count( $date_from, $date_to ),
				'user_removed' => $events_stats->get_user_removed_count( $date_from, $date_to ),
				'user_updated' => $events_stats->get_user_updated_count( $date_from, $date_to ),
			],
		];

		ob_start();
		include SIMPLE_HISTORY_PATH . 'templates/email-report-preview.php';
		$email_content = ob_get_clean();

		$site_name = get_bloginfo( 'name' );
		$subject = sprintf(
			// translators: %s: Site name.
			__( '[%s] Website Statistics Report', 'simple-history' ),
			$site_name
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
