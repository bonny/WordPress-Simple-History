<?php

/**
 * Simple History Modules Widgets Class
 *
 * @since 1.1
 * 
 * @package Simple History
 * @subpackage Modules
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'Simple_History_Module_Widgets' ) ) :

/**
 * Plugin class
 */
class Simple_History_Module_Widgets extends Simple_History_Module {

	function __construct(){
		parent::__construct( array(
			'id'          => 'widgets',
			'title'       => __('Widgets', 'simple-history'),
			'description' => __('Log events for the Widgets section of your WP install.', 'simple-history'),
			'tabs'        => array(
				'supports' => array(
					__('Adding, updating and deleting widgets in/from a sidebar.', 'simple-history'),
					),
				'lacks'    => array(
					__('Moving widgets between sidebars.',                         'simple-history'),
					__('Setting a widget to active/inactive.',                     'simple-history'),
					)
				)
			)
		);
	}

	function add_actions(){

		// Widget create/update/remove
		add_action( 'sidebar_admin_setup', array( $this, 'widget_setup' ), 10 );

		/**
		 * NOTE: Need to find hook for activate/deactivate widgets
		 */
	}

	/** Widgets ******************************************************/

	/**
	 * Log adding/updating/removing widgets to/in/from sidebar
	 *
	 * Hooked into sidebar_admin_setup action
	 * 
	 * @since 1.1
	 */
	public function widget_setup(){
		global $wp_registered_widgets, $wp_registered_sidebars;

		// We need all these variables
		if ( ! isset( $_POST['id_base'] ) && ! isset( $_POST['widget-id'] ) && ! isset( $_POST['sidebar'] ) )
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
		if ( ! $title )
			$title = esc_html( strip_tags( $wp_registered_widgets[$_POST['widget-id']]['name'] ) );

		/**
		 * Define event action
		 */
		// Translators: 1. Type, 2. Widget name, 3. Sidebar name

		// Widget removed
		if ( isset( $_POST['delete_widget'] ) )
			$action = __('%1$s %2$s removed from sidebar %3$s', 'simple-history', 'Widget removed');

		// Widget updated
		elseif ( isset( $wp_registered_widgets[$_POST['widget-id']] ) )
			$action = __('%1$s %2$s updated in sidebar %3$s',   'simple-history', 'Widget updated');
		
		// Widget added
		else
			$action = __('%1$s %2$s added to sidebar %3$s',     'simple-history', 'Widget added'  );

		$this->log( array( 
			'action' => sprintf( $action, '%1$s', '%2$s', $wp_registered_sidebars[$_POST['sidebar']]['name'] ),
			'type'   => 'widget',
			'name'   => $title,
			'id'     => $_POST['widget-id']
		) );
	}
}

new Simple_History_Module_Widgets();

endif; // class_exists
