<?php

namespace Simple_History\Services;

use Simple_History\Channels\Channels_Manager;

/**
 * Service for managing log forwarding channels.
 *
 * This service registers and initializes the channels system
 * that allows Simple History to forward events to external systems.
 *
 * @since 4.4.0
 */
class Channels_Service extends Service {
	/**
	 * The channels manager instance.
	 *
	 * @var Channels_Manager|null
	 */
	private ?Channels_Manager $channels_manager = null;

	/**
	 * Called when service is loaded.
	 */
	public function loaded() {
		// Initialize the channels manager.
		$this->channels_manager = new Channels_Manager( $this->simple_history );
		$this->channels_manager->loaded();

		/**
		 * Fires after the channels service is loaded.
		 *
		 * @since 4.4.0
		 *
		 * @param Channels_Manager $channels_manager The channels manager instance.
		 * @param Channels_Service $service This service instance.
		 */
		do_action( 'simple_history/channels/service_loaded', $this->channels_manager, $this );
	}

	/**
	 * Get the channels manager instance.
	 *
	 * @return Channels_Manager|null The channels manager or null if not loaded.
	 */
	public function get_channels_manager() {
		return $this->channels_manager;
	}
}
