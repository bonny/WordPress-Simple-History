<?php

namespace Simple_History\Services;

use Simple_History\Integrations\Integrations_Manager;

/**
 * Service for managing log forwarding integrations.
 *
 * This service registers and initializes the integrations system
 * that allows Simple History to forward events to external systems.
 *
 * @since 4.4.0
 */
class Integrations_Service extends Service {
	/**
	 * The integrations manager instance.
	 *
	 * @var Integrations_Manager|null
	 */
	private ?Integrations_Manager $integrations_manager = null;

	/**
	 * Called when service is loaded.
	 */
	public function loaded() {
		// Initialize the integrations manager.
		$this->integrations_manager = new Integrations_Manager( $this->simple_history );
		$this->integrations_manager->loaded();

		// Make the manager available to other parts of the system.
		$this->simple_history->integrations_manager = $this->integrations_manager;

		/**
		 * Fires after the integrations service is loaded.
		 *
		 * @since 4.4.0
		 *
		 * @param Integrations_Manager $integrations_manager The integrations manager instance.
		 * @param Integrations_Service $service This service instance.
		 */
		do_action( 'simple_history/integrations/service_loaded', $this->integrations_manager, $this );
	}

	/**
	 * Get the integrations manager instance.
	 *
	 * @return Integrations_Manager|null The integrations manager or null if not loaded.
	 */
	public function get_integrations_manager() {
		return $this->integrations_manager;
	}
}
