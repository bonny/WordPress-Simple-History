<?php

namespace Simple_History\Integrations;

use Simple_History\Services\Service;
use Simple_History\Integrations\Interfaces\Integration_Interface;
use Simple_History\Integrations\Integrations\File_Integration;

/**
 * Manages all log forwarding integrations.
 *
 * This service coordinates the registration and processing of integrations
 * that forward Simple History events to external systems.
 *
 * @since 4.4.0
 */
class Integrations_Manager extends Service {
	/**
	 * Array of registered integrations.
	 *
	 * @var Integration_Interface[]
	 */
	private array $integrations = [];

	/**
	 * Alert rules engine instance.
	 *
	 * @var Alert_Rules_Engine|null
	 */
	private ?Alert_Rules_Engine $rules_engine = null;

	/**
	 * Called when service is loaded.
	 */
	public function loaded() {
		// Register core integrations.
		$this->register_core_integrations();

		// Hook for premium integrations to register themselves.
		do_action( 'simple_history/integrations/register', $this );

		// Hook into the logging system to process events.
		add_action( 'simple_history/log/inserted', [ $this, 'process_logged_event' ], 10, 3 );

		// Add settings page integration.
		add_filter( 'simple_history/settings_tabs', [ $this, 'add_settings_tab' ] );
	}

	/**
	 * Register core integrations that come with the free plugin.
	 */
	private function register_core_integrations() {
		$this->register_integration( new File_Integration() );
	}

	/**
	 * Register an integration.
	 *
	 * @param Integration_Interface $integration The integration to register.
	 * @return bool True on success, false if already registered.
	 */
	public function register_integration( Integration_Interface $integration ) {
		$slug = $integration->get_slug();

		if ( isset( $this->integrations[ $slug ] ) ) {
			return false; // Already registered.
		}

		$this->integrations[ $slug ] = $integration;

		/**
		 * Fired when an integration is registered.
		 *
		 * @param Integration_Interface $integration The registered integration.
		 * @param Integrations_Manager $manager This manager instance.
		 */
		do_action( 'simple_history/integrations/registered', $integration, $this );

		return true;
	}

	/**
	 * Get a registered integration by slug.
	 *
	 * @param string $slug The integration slug.
	 * @return Integration_Interface|null The integration or null if not found.
	 */
	public function get_integration( $slug ) {
		return $this->integrations[ $slug ] ?? null;
	}

	/**
	 * Get all registered integrations.
	 *
	 * @return Integration_Interface[] Array of integrations.
	 */
	public function get_integrations() {
		return $this->integrations;
	}

	/**
	 * Get all enabled integrations.
	 *
	 * @return Integration_Interface[] Array of enabled integrations.
	 */
	public function get_enabled_integrations() {
		return array_filter( $this->integrations, function( $integration ) {
			return $integration->is_enabled();
		} );
	}

	/**
	 * Process a logged event and send to enabled integrations.
	 *
	 * @param array $context Context data for the event.
	 * @param array $data Event data.
	 * @param mixed $logger Logger instance that created the event.
	 */
	public function process_logged_event( $context, $data, $logger ) {
		$enabled_integrations = $this->get_enabled_integrations();

		if ( empty( $enabled_integrations ) ) {
			return;
		}

		// Prepare event data for integrations.
		$event_data = $this->prepare_event_data( $context, $data, $logger );

		foreach ( $enabled_integrations as $integration ) {
			$this->send_to_integration( $integration, $event_data );
		}
	}

	/**
	 * Prepare event data for sending to integrations.
	 *
	 * @param array $context Context data for the event.
	 * @param array $data Event data.
	 * @param mixed $logger Logger instance that created the event.
	 * @return array Prepared event data.
	 */
	private function prepare_event_data( $context, $data, $logger ) {
		return [
			'id' => $data['id'] ?? null,
			'date' => $data['date'] ?? current_time( 'mysql' ),
			'logger' => $data['logger'] ?? '',
			'level' => $data['level'] ?? 'info',
			'message' => $data['message'] ?? '',
			'initiator' => $data['initiator'] ?? '',
			'context' => $context,
			'logger_instance' => $logger,
		];
	}

	/**
	 * Send event data to a specific integration.
	 *
	 * @param Integration_Interface $integration The integration to send to.
	 * @param array $event_data The event data to send.
	 */
	private function send_to_integration( Integration_Interface $integration, $event_data ) {
		// Check if the event should be sent based on alert rules.
		if ( ! $integration->should_send_event( $event_data ) ) {
			return;
		}

		// Format the message for the integration.
		$formatted_message = $this->format_message_for_integration( $integration, $event_data );

		// Determine if we should process async or sync.
		if ( $integration->supports_async() && $this->should_process_async( $integration ) ) {
			$this->queue_for_async_processing( $integration, $event_data, $formatted_message );
		} else {
			$this->send_sync( $integration, $event_data, $formatted_message );
		}
	}

	/**
	 * Format a message for a specific integration.
	 *
	 * @param Integration_Interface $integration The integration.
	 * @param array $event_data The event data.
	 * @return string The formatted message.
	 */
	private function format_message_for_integration( Integration_Interface $integration, $event_data ) {
		// For now, use a simple format. This will be enhanced later.
		$message = $event_data['message'];
		
		// Interpolate context variables into the message.
		if ( ! empty( $event_data['context'] ) ) {
			foreach ( $event_data['context'] as $key => $value ) {
				if ( is_string( $value ) || is_numeric( $value ) ) {
					$message = str_replace( '{' . $key . '}', $value, $message );
				}
			}
		}

		return $message;
	}

	/**
	 * Check if an integration should be processed asynchronously.
	 *
	 * @param Integration_Interface $integration The integration.
	 * @return bool True if should process async.
	 */
	private function should_process_async( Integration_Interface $integration ) {
		// For now, always process async if supported.
		// This could be configurable in the future.
		return true;
	}

	/**
	 * Queue an event for asynchronous processing.
	 *
	 * @param Integration_Interface $integration The integration.
	 * @param array $event_data The event data.
	 * @param string $formatted_message The formatted message.
	 */
	private function queue_for_async_processing( Integration_Interface $integration, $event_data, $formatted_message ) {
		// TODO: Implement async queue system using WordPress cron.
		// For now, fall back to synchronous processing.
		$this->send_sync( $integration, $event_data, $formatted_message );
	}

	/**
	 * Send an event synchronously to an integration.
	 *
	 * @param Integration_Interface $integration The integration.
	 * @param array $event_data The event data.
	 * @param string $formatted_message The formatted message.
	 */
	private function send_sync( Integration_Interface $integration, $event_data, $formatted_message ) {
		try {
			$result = $integration->send_event( $event_data, $formatted_message );
			
			if ( ! $result ) {
				error_log( 'Simple History: Failed to send event to integration: ' . $integration->get_slug() );
			}
		} catch ( \Exception $e ) {
			error_log( 'Simple History: Exception sending event to integration ' . $integration->get_slug() . ': ' . $e->getMessage() );
		}
	}

	/**
	 * Add integrations tab to settings page.
	 *
	 * @param array $tabs Current settings tabs.
	 * @return array Modified settings tabs.
	 */
	public function add_settings_tab( $tabs ) {
		$tabs[] = [
			'slug' => 'integrations',
			'name' => __( 'Integrations', 'simple-history' ),
			'function' => [ $this, 'settings_tab_output' ],
		];

		return $tabs;
	}

	/**
	 * Output the integrations settings tab content.
	 */
	public function settings_tab_output() {
		echo '<h3>' . esc_html__( 'Log Forwarding & Integrations', 'simple-history' ) . '</h3>';
		echo '<p>' . esc_html__( 'Configure where Simple History sends your logs and events.', 'simple-history' ) . '</p>';

		echo '<div class="sh-integrations-list">';
		
		foreach ( $this->get_integrations() as $integration ) {
			$this->render_integration_card( $integration );
		}

		echo '</div>';
	}

	/**
	 * Render a single integration card in the settings.
	 *
	 * @param Integration_Interface $integration The integration to render.
	 */
	private function render_integration_card( Integration_Interface $integration ) {
		$is_enabled = $integration->is_enabled();
		$slug = $integration->get_slug();

		echo '<div class="sh-integration-card" data-integration="' . esc_attr( $slug ) . '">';
		echo '<h4>' . esc_html( $integration->get_name() ) . '</h4>';
		echo '<p>' . esc_html( $integration->get_description() ) . '</p>';
		
		echo '<div class="sh-integration-actions">';
		if ( $is_enabled ) {
			echo '<span class="sh-integration-status enabled">' . esc_html__( 'Enabled', 'simple-history' ) . '</span>';
		} else {
			echo '<span class="sh-integration-status disabled">' . esc_html__( 'Disabled', 'simple-history' ) . '</span>';
		}
		echo '</div>';

		echo '</div>';
	}
}