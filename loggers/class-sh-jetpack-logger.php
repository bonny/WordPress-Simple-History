<?php

/**
 * Logger for Automattic Jetpack plugin.
 *
 * @package SimpleHistory
 */

defined( 'ABSPATH' ) || die();

/**
 * Logger for plugin Jetpack.
 */
class SH_Jetpack_Logger extends SimpleLogger {


	/**
	 * Logger slug.
	 *
	 * @var string
	 */
	public $slug = __CLASS__;

	/**
	 * Return info about logger.
	 *
	 * @return array Array with plugin info.
	 */
	public function getInfo() {
		$arr_info = array(
			'name'        => _x( 'Plugin: Jetpack Logger', 'Logger: Jetpack', 'simple-history' ),
			'description' => _x( 'Log Jetpack settings changes', 'Logger: Jetpack', 'simple-history' ),
			'capability'  => 'manage_options',
			'name_via'    => _x( 'Using plugin Jetpack', 'Logger: Jetpack', 'simple-history' ),
			'messages'    => array(
				'module_activated'   => _x( 'Activated Jetpack module "{module_name}"', 'Logger: Jetpack', 'simple-history' ),
				'module_deactivated' => _x( 'Deactivated Jetpack module "{module_name}"', 'Logger: Jetpack', 'simple-history' ),
			),
		);

		return $arr_info;
	}

	/**
	 * Called when logger is loaded.
	 */
	public function loaded() {
		add_action( 'jetpack_activate_module', array( $this, 'on_jetpack_activate_module' ), 10, 2 );
		add_action( 'jetpack_deactivate_module', array( $this, 'on_jetpack_deactivate_module' ), 10, 2 );
	}

	/**
	 * Get array with all Jetpack modules and info about them.
	 *
	 * @return array Array with info.
	 */
	private function get_jetpack_modules() {
		// Check that Jetpack has the needed methods.
		if ( ! method_exists( 'Jetpack', 'get_available_modules' ) || ! method_exists( 'Jetpack', 'get_module' ) ) {
			return false;
		}

		$available_modules = Jetpack::get_available_modules();
		$available_modules_with_info = array();

		foreach ( $available_modules as $module_slug ) {
			$module = Jetpack::get_module( $module_slug );
			if ( ! $module ) {
				continue;
			}

			$available_modules_with_info[ $module_slug ] = $module;
		}

		return $available_modules_with_info;
	}

	/**
	 * Get info about a Jetpack module.
	 *
	 * @param string $slug Slug of module to get info for.
	 *
	 * @return Array Array with module info.
	 */
	private function get_jetpack_module( $slug = null ) {
		if ( empty( $slug ) ) {
			return false;
		}

		$modules = $this->get_jetpack_modules();

		return isset( $modules[ $slug ] ) ? $modules[ $slug ] : false;
	}

	/**
	 * Called when a module is activated.
	 */
	public function on_jetpack_activate_module( $module_slug = null, $success = null ) {
		if ( true !== $success ) {
			return;
		}

		$context = array();

		$module = $this->get_jetpack_module( $module_slug );

		if ( $module ) {
			$context['module_slug'] = $module_slug;
			$context['module_name'] = $module['name'];
			$context['module_description'] = $module['description'];
		}

		$this->infoMessage(
			'module_activated',
			$context
		);
	}

	/**
	 * Called when a module is deactivated.
	 */
	public function on_jetpack_deactivate_module( $module_slug = null, $success = null ) {
		if ( true !== $success ) {
			return;
		}

		$context = array();

		$module = $this->get_jetpack_module( $module_slug );

		if ( $module ) {
			$context['module_slug'] = $module_slug;
			$context['module_name'] = $module['name'];
			$context['module_description'] = $module['description'];
		}

		$this->infoMessage(
			'module_deactivated',
			$context
		);
	}
}
