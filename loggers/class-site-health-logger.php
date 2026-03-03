<?php

namespace Simple_History\Loggers;

use Simple_History\Event_Details\Event_Details_Group;
use Simple_History\Event_Details\Event_Details_Group_Table_Formatter;
use Simple_History\Event_Details\Event_Details_Item;
use Simple_History\Log_Initiators;

/**
 * Logs Site Health status changes (issues detected, resolved, or changed severity).
 *
 * @package SimpleHistory
 */
class Site_Health_Logger extends Logger {
	/** @var string Logger slug */
	public $slug = 'SiteHealthLogger';

	/**
	 * Return logger info.
	 *
	 * @return array
	 */
	public function get_info() {
		return array(
			'name'        => _x( 'Site Health Logger', 'SiteHealthLogger', 'simple-history' ),
			'type'        => 'core',
			'description' => __( 'Logs changes in Site Health test results', 'simple-history' ),
			'capability'  => 'manage_options',
			'messages'    => array(
				'site_health_issue_detected' => __( 'Detected Site Health issue: {site_health_label}', 'simple-history' ),
				'site_health_issue_resolved' => __( 'Resolved Site Health issue: {site_health_label}', 'simple-history' ),
				'site_health_issue_changed'  => __( 'Changed Site Health issue severity: {site_health_label}', 'simple-history' ),
			),
			'labels'      => array(
				'search' => array(
					'label'     => _x( 'Site Health', 'Site Health logger: search', 'simple-history' ),
					'label_all' => _x( 'All Site Health changes', 'Site Health logger: search', 'simple-history' ),
					'options'   => array(
						_x( 'Issues detected', 'Site Health logger: search', 'simple-history' ) => array(
							'site_health_issue_detected',
						),
						_x( 'Issues resolved', 'Site Health logger: search', 'simple-history' ) => array(
							'site_health_issue_resolved',
						),
						_x( 'Issues changed severity', 'Site Health logger: search', 'simple-history' ) => array(
							'site_health_issue_changed',
						),
					),
				),
			),
		);
	}

	/**
	 * Called when logger is loaded.
	 */
	public function loaded() {
		add_filter( 'site_status_test_result', array( $this, 'on_site_status_test_result' ) );
	}

	/**
	 * Called for each Site Health test result.
	 * Compares the new status against the previously stored status and logs changes.
	 *
	 * @param array $result Test result array from WP_Site_Health::perform_test().
	 * @return array Unmodified result (this is a filter).
	 */
	public function on_site_status_test_result( $result ) {
		if ( ! is_array( $result ) || empty( $result['test'] ) || empty( $result['status'] ) ) {
			return $result;
		}

		$test_id    = $result['test'];
		$new_status = $result['status'];

		$option_key     = "simplehistory_{$this->get_slug()}_test_results";
		$stored_results = get_option( $option_key );

		if ( ! is_array( $stored_results ) ) {
			$stored_results = array();
		}

		$previous_status = $stored_results[ $test_id ] ?? null;

		// No change — skip DB write and logging.
		if ( $previous_status === $new_status ) {
			return $result;
		}

		// Update stored status only when something changed or on first run.
		$stored_results[ $test_id ] = $new_status;

		// Autoload disabled since this option is only accessed during Site Health checks.
		update_option( $option_key, $stored_results, false );

		// First run for this test — store as baseline, don't log.
		// Pre-existing issues shouldn't appear as new events.
		if ( $previous_status === null ) {
			return $result;
		}

		// Status changed — determine which message to use.
		if ( $new_status === 'good' ) {
			$this->log_status_change( 'site_health_issue_resolved', $result, $new_status, $previous_status );
		} elseif ( $previous_status === 'good' ) {
			$this->log_status_change( 'site_health_issue_detected', $result, $new_status, $previous_status );
		} else {
			$this->log_status_change( 'site_health_issue_changed', $result, $new_status, $previous_status );
		}

		return $result;
	}

	/**
	 * Log a Site Health status change.
	 *
	 * @param string $message_key Message key to log.
	 * @param array  $result      Test result array.
	 * @param string $new_status  New status (good, recommended, critical).
	 * @param string $old_status  Previous status.
	 */
	private function log_status_change( $message_key, $result, $new_status, $old_status ) {
		$context = array(
			'site_health_test'            => $result['test'],
			'site_health_label'           => $result['label'] ?? $result['test'],
			'site_health_status'          => $new_status,
			'site_health_previous_status' => $old_status,
			'site_health_badge_label'     => strtolower( $result['badge']['label'] ?? '' ),
			'_initiator'                  => Log_Initiators::WORDPRESS,
		);

		if ( $new_status === 'critical' ) {
			$this->warning_message( $message_key, $context );
		} elseif ( $new_status === 'good' ) {
			$this->info_message( $message_key, $context );
		} else {
			$this->notice_message( $message_key, $context );
		}
	}

	/**
	 * Append status details to the log row output.
	 *
	 * @param object $row Log row.
	 * @return Event_Details_Group
	 */
	public function get_log_row_details_output( $row ) {
		$context = $row->context ?? array();

		$group = new Event_Details_Group();
		$group->set_formatter( new Event_Details_Group_Table_Formatter() );

		$group->add_item(
			new Event_Details_Item( 'site_health_status', __( 'Current status', 'simple-history' ) )
		);

		$group->add_item(
			new Event_Details_Item( 'site_health_previous_status', __( 'Previous status', 'simple-history' ) )
		);

		$group->add_item(
			new Event_Details_Item( 'site_health_badge_label', __( 'Category', 'simple-history' ) )
		);

		return $group;
	}

	/**
	 * Get action links for a Site Health event.
	 *
	 * @param object $row Log row object.
	 * @return array Array of action link arrays.
	 */
	public function get_action_links( $row ) {
		if ( ! current_user_can( 'view_site_health_checks' ) ) {
			return [];
		}

		return [
			[
				'url'    => admin_url( 'site-health.php' ),
				'label'  => __( 'View Site Health', 'simple-history' ),
				'action' => 'view',
			],
		];
	}
}
