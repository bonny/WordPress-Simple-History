<?php

/**
 * Simple History Extender Widgets Class
 *
 * @since 0.0.1
 * 
 * @package Simple History Extender
 * @subpackage Modules
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

if ( !class_exists( 'Simple_History_Extend_Widgets' ) ) :

/**
 * Plugin class
 */
class Simple_History_Extend_Widgets extends Simple_History_Extend {

	function __construct(){
		parent::__construct( array(
			'id'          => 'widgets',
			'title'       => __('Widgets', 'sh-extender'),
			'plugin'      => false,
			'description' => __('Log events for the Widgets section of your WP install.', 'sh-extender'),
			'tabs'        => array(
				'supports' => array(
					__('Adding, updating and deleting widgets in/from a sidebar.', 'sh-extender'),
					),
				'lacks'    => array(
					__('Moving widgets between sidebars.', 'sh-extender'),
					__('Setting a widget to active/inactive.', 'sh-extender')
					)
				)
			)
		);
	}

	function add_actions(){

		// Widget create/update/remove
		add_action( 'sidebar_admin_setup', array( $this, 'widgets_setup' ), 10 );

		/**
		 * NOTE: Need to find hook for activate/deactivate widgets
		 */
	}

	/** Widgets ******************************************************/

	/**
	 * Log event where widget is added, updated or removed in/from sidebar
	 * 
	 * @return void
	 */
	public function widgets_setup(){
		global $wp_registered_widgets, $wp_registered_sidebars;

		// We need all these variables
		if ( !isset( $_POST['id_base'] ) && !isset( $_POST['widget-id'] ) && !isset( $_POST['sidebar'] ) )
			return;

		/** 
		 * Get the number of the main widget reference in the widget list. 
		 * It's allways one smaller (-1) than our current new widget instance.
		 */
		$number = absint( substr( $_POST['widget-id'], strlen( $_POST['id_base'] ) + 1 ) ) - 1;
		$title = false;

		// Find the widget name from the list of available widgets
		foreach ( $wp_registered_widgets as $widget ){
			if ( $_POST['id_base'] .'-'. $number == $widget['id'] ){
				$title = esc_html( strip_tags( $widget['name'] ) );
				break;
			}
		}

		// Fetch widget name from previous save
		if ( !$title )
 			$title = esc_html( strip_tags( $wp_registered_widgets[$_POST['widget-id']]['name'] ) );

 		// Define the action
		if ( isset( $_POST['delete_widget'] ) )
			$action = __('removed from sidebar %s', 'sh-extender');
		elseif ( isset( $wp_registered_widgets[$_POST['widget-id']] ) )
			$action = __('updated in sidebar %s', 'sh-extender');
		else
			$action = __('added to sidebar %s', 'sh-extender');

		// Extend SH
		$this->extend( array( 
			'action' => sprintf( $action, $wp_registered_sidebars[$_POST['sidebar']]['name'] ),
			'type'   => __('Widget'),
			'name'   => $title,
			'id'     => $_POST['widget-id']
			) );
	}
}

new Simple_History_Extend_Widgets();

endif; // class_exists
