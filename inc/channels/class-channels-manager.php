<?php

namespace Simple_History\Channels;

use Simple_History\Services\Service;
use Simple_History\Channels\Interfaces\Channel_Interface;

/**
 * Manages all log forwarding channels.
 *
 * This service coordinates the registration and processing of channels
 * that forward Simple History events to external systems.
 *
 * @since 4.4.0
 */
class Channels_Manager extends Service {
	/**
	 * Array of registered channels.
	 *
	 * @var Channel_Interface[]
	 */
	private array $channels = [];

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
		// Register core channels.
		$this->register_core_channels();

		// Hook for premium channels to register themselves.
		do_action( 'simple_history/channels/register', $this );

		// Hook into the logging system to process events.
		add_action( 'simple_history/log/inserted', [ $this, 'process_logged_event' ], 10, 3 );
	}

	/**
	 * Register core channels that come with the free plugin.
	 */
	private function register_core_channels() {
		$this->register_channel( new File_Channel() );
	}

	/**
	 * Register an channel.
	 *
	 * @param Channel_Interface $channel The channel to register.
	 * @return bool True on success, false if already registered.
	 */
	public function register_channel( Channel_Interface $channel ) {
		$slug = $channel->get_slug();

		if ( isset( $this->channels[ $slug ] ) ) {
			return false; // Already registered.
		}

		$this->channels[ $slug ] = $channel;

		// Call loaded() to allow channel to register hooks.
		// This is separate from construction to keep instantiation side-effect free.
		$channel->loaded();

		/**
		 * Fired when an channel is registered.
		 *
		 * @param Channel_Interface $channel The registered channel.
		 * @param Channels_Manager $manager This manager instance.
		 */
		do_action( 'simple_history/channels/registered', $channel, $this );

		return true;
	}

	/**
	 * Get a registered channel by slug.
	 *
	 * @param string $slug The channel slug.
	 * @return Channel_Interface|null The channel or null if not found.
	 */
	public function get_channel( $slug ) {
		return $this->channels[ $slug ] ?? null;
	}

	/**
	 * Get all registered channels.
	 *
	 * @return Channel_Interface[] Array of channels.
	 */
	public function get_channels() {
		return $this->channels;
	}

	/**
	 * Get all enabled channels.
	 *
	 * @return Channel_Interface[] Array of enabled channels.
	 */
	public function get_enabled_channels() {
		return array_filter(
			$this->channels,
			function ( $channel ) {
				return $channel->is_enabled();
			}
		);
	}

	/**
	 * Process a logged event and send to enabled channels.
	 *
	 * @param array $context Context data for the event.
	 * @param array $data Event data.
	 * @param mixed $logger Logger instance that created the event.
	 */
	public function process_logged_event( $context, $data, $logger ) {
		$enabled_channels = $this->get_enabled_channels();

		if ( empty( $enabled_channels ) ) {
			return;
		}

		// Prepare event data for channels.
		$event_data = $this->prepare_event_data( $context, $data, $logger );

		foreach ( $enabled_channels as $channel ) {
			$this->send_to_channel( $channel, $event_data );
		}
	}

	/**
	 * Prepare event data for sending to channels.
	 *
	 * @param array $context Context data for the event.
	 * @param array $data Event data.
	 * @param mixed $logger Logger instance that created the event.
	 * @return array Prepared event data.
	 */
	private function prepare_event_data( $context, $data, $logger ) {
		return [
			'id'              => $data['id'] ?? null,
			'date'            => $data['date'] ?? current_time( 'mysql' ),
			'logger'          => $data['logger'] ?? '',
			'level'           => $data['level'] ?? 'info',
			'message'         => $data['message'] ?? '',
			'initiator'       => $data['initiator'] ?? '',
			'context'         => $context,
			'logger_instance' => $logger,
		];
	}

	/**
	 * Send event data to a specific channel.
	 *
	 * @param Channel_Interface $channel The channel to send to.
	 * @param array             $event_data The event data to send.
	 */
	private function send_to_channel( Channel_Interface $channel, $event_data ) {
		// Check if the event should be sent based on alert rules.
		if ( ! $channel->should_send_event( $event_data ) ) {
			return;
		}

		// Format the message for the channel.
		$formatted_message = $this->format_message_for_channel( $channel, $event_data );

		// Determine if we should process async or sync.
		if ( $channel->supports_async() && $this->should_process_async( $channel ) ) {
			$this->queue_for_async_processing( $channel, $event_data, $formatted_message );
		} else {
			$this->send_sync( $channel, $event_data, $formatted_message );
		}
	}

	/**
	 * Format a message for a specific channel.
	 *
	 * @param Channel_Interface $channel The channel.
	 * @param array             $event_data The event data.
	 * @return string The formatted message.
	 */
	private function format_message_for_channel( Channel_Interface $channel, $event_data ) {
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
	 * Check if a channel should be processed asynchronously.
	 *
	 * @param Channel_Interface $channel The channel.
	 * @return bool True if should process async.
	 */
	private function should_process_async( Channel_Interface $channel ) {
		// For now, always process async if supported.
		// This could be configurable in the future.
		return true;
	}

	/**
	 * Queue an event for asynchronous processing.
	 *
	 * @param Channel_Interface $channel The channel.
	 * @param array             $event_data The event data.
	 * @param string            $formatted_message The formatted message.
	 */
	private function queue_for_async_processing( Channel_Interface $channel, $event_data, $formatted_message ) {
		// TODO: Implement async queue system using WordPress cron.
		// For now, fall back to synchronous processing.
		$this->send_sync( $channel, $event_data, $formatted_message );
	}

	/**
	 * Send an event synchronously to an channel.
	 *
	 * @param Channel_Interface $channel The channel.
	 * @param array             $event_data The event data.
	 * @param string            $formatted_message The formatted message.
	 */
	private function send_sync( Channel_Interface $channel, $event_data, $formatted_message ) {
		try {
			$channel->send_event( $event_data, $formatted_message );
		} catch ( \Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			// Errors are tracked by individual channels via their error handling.
		}
	}
}
