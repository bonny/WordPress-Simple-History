<?php

defined( 'ABSPATH' ) || die();

/**
 * Logs WordPress core updates
 */
class SimpleCoreUpdatesLogger extends SimpleLogger {


	public $slug = __CLASS__;

	public function loaded() {

		add_action( '_core_updated_successfully', array( $this, 'on_core_updated' ) );
		add_action( 'update_feedback', array( $this, 'on_update_feedback' ) );

		// Can't log db updates at the moment, because loaded() is not called yet when the action fires
		// add_action( 'wp_upgrade', array( $this, "on_wp_upgrade" ), 10, 2 );
	}

	 /**
	  * Fires after a site is fully upgraded.
	  * The database, that is.
	  *
	  * @param int $wp_db_version         The new $wp_db_version.
	  * @param int $wp_current_db_version The old (current) $wp_db_version.
	  */
	public function on_wp_upgrade( $wp_db_version, $wp_current_db_version ) {

		$this->debugMessage(
			'core_db_version_updated',
			array(
				'new_version' => $wp_db_version,
				'prev_version' => $wp_current_db_version,
			)
		);
	}

	/**
	 * We need to store the WordPress version we are updating from.
	 * 'update_feedback' is a suitable filter.
	 */
	public function on_update_feedback() {

		if ( ! empty( $GLOBALS['wp_version'] ) && ! isset( $GLOBALS[ 'simple_history_' . $this->slug . '_wp_version' ] ) ) {
			$GLOBALS[ 'simple_history_' . $this->slug . '_wp_version' ] = $GLOBALS['wp_version'];
		}
	}

	/**
	 * Get array with information about this logger
	 *
	 * @return array
	 */
	public function getInfo() {

		$arr_info = array(
			'name'        => __( 'Core Updates Logger', 'simple-history' ),
			'description' => __( 'Logs the update of WordPress (manual and automatic updates)', 'simple-history' ),
			'capability'  => 'update_core',
			'messages'    => array(
				'core_updated'            => __( 'Updated WordPress to {new_version} from {prev_version}', 'simple-history' ),
				'core_auto_updated'       => __( 'WordPress auto-updated to {new_version} from {prev_version}', 'simple-history' ),
				'core_db_version_updated' => __( 'WordPress database version updated to {new_version} from {prev_version}', 'simple-history' ),
			),
			'labels'      => array(
				'search' => array(
					'label' => _x( 'WordPress Core', 'User logger: search', 'simple-history' ),
					'options' => array(
						_x( 'WordPress core updates', 'User logger: search', 'simple-history' ) => array(
							'core_updated',
							'core_auto_updated',
						),
					),
				), // end search array
			), // end labels
		);

		return $arr_info;
	}

	/**
	 * Called when WordPress is updated
	 *
	 * @param string $new_wp_version
	 */
	public function on_core_updated( $new_wp_version ) {

		$old_wp_version = empty( $GLOBALS[ 'simple_history_' . $this->slug . '_wp_version' ] ) ? $GLOBALS['wp_version'] : $GLOBALS[ 'simple_history_' . $this->slug . '_wp_version' ];

		$auto_update = true;
		if ( $GLOBALS['pagenow'] == 'update-core.php' ) {
			$auto_update = false;
		}

		if ( $auto_update ) {
			$message = 'core_auto_updated';
		} else {
			$message = 'core_updated';
		}

		$this->noticeMessage(
			$message,
			array(
				'prev_version' => $old_wp_version,
				'new_version' => $new_wp_version,
			)
		);
	}
}
