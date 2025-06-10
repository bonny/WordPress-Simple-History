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
		// Register settings.
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		// Add settings fields to general section.
		add_action( 'simple_history/settings_page/general_section_output', [ $this, 'on_general_section_output' ] );
		// Handle email preview.
		add_action( 'admin_init', [ $this, 'maybe_handle_email_preview' ] );
		// Schedule email report.
		add_action( 'init', [ $this, 'schedule_email_report' ] );
		add_action( 'simple_history_email_report', [ $this, 'send_email_report' ] );
	}

	/**
	 * Check if this is an email preview request and handle it.
	 */
	public function maybe_handle_email_preview() {
		// Check if this is a preview request.
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

		// sh_d( 'stats', $stats );

		// Include the preview template.
		load_template( SIMPLE_HISTORY_PATH . 'templates/email-report-preview.php', false, array() );
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
			[ __( 'Email Reports', 'simple-history' ), 'mark_email_unread' ],
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

		$preview_url = add_query_arg(
			[
				'simple_history_email_preview' => '1',
				'_wpnonce' => wp_create_nonce( 'simple_history_email_preview' ),
			],
			admin_url()
		);

		?>
		<p>
			<a href="<?php echo esc_url( $preview_url ); ?>" target="_blank" class="sh-ExternalLink">
				<?php esc_html_e( 'Show email preview', 'simple-history' ); ?>
			</a>
		</p>
		<?php
	}

	/**
	 * Sanitize email recipients.
	 *
	 * @param string $value Comma-separated list of email addresses.
	 * @return string Sanitized comma-separated list of email addresses.
	 */
	public function sanitize_email_recipients( $value ) {
		$emails = array_map( 'trim', explode( ',', $value ) );
		$valid_emails = array_filter( $emails, 'is_email' );
		return implode( ',', $valid_emails );
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
	 * Output for the recipients setting field.
	 */
	public function settings_field_recipients() {
		$recipients = get_option( 'simple_history_email_report_recipients', '' );
		?>
		<input type="text" 
			   name="simple_history_email_report_recipients" 
			   value="<?php echo esc_attr( $recipients ); ?>" 
			   class="regular-text"
		/>
		<p class="description">
			<?php esc_html_e( 'Comma-separated list of email addresses to receive the report.', 'simple-history' ); ?>
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
		if ( ! get_option( 'simple_history_email_report_enabled', false ) ) {
			return;
		}

		$recipients = get_option( 'simple_history_email_report_recipients', '' );
		if ( empty( $recipients ) ) {
			return;
		}

		$stats_service = new Stats_Service( $this->simple_history );
		$date_from = strtotime( '-7 days' );
		$date_to = time();
		$stats = $stats_service->init_stats( $date_from, $date_to );

		ob_start();
		include SIMPLE_HISTORY_PATH . 'templates/email-report-preview.php';
		$email_content = ob_get_clean();

		$site_name = get_bloginfo( 'name' );
		/* translators: %s: Site name. */
		$subject = sprintf(
			__( '[%s] Website Statistics Report', 'simple-history' ),
			$site_name
		);

		$headers = [ 'Content-Type: text/html; charset=UTF-8' ];
		wp_mail(
			$recipients,
			$subject,
			$email_content,
			$headers
		);
	}
}
