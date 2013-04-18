<?php

/**
 * Simple History Extender Gravity Forms Class
 *
 * Extend Simple History for Gravity Forms events
 * Version 1.6.9
 *
 * @since 0.0.1
 * 
 * @package Simple History Extender
 * @subpackage Modules
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

if ( !class_exists( 'Simple_History_Extend_GravityForms' ) ) :

/**
 * Plugin class
 */
class Simple_History_Extend_GravityForms extends Simple_History_Extend {

	function __construct(){
		parent::__construct( array(
			'id'     => 'gravityforms',
			'title'  => __('Gravity Forms', 'sh-extender'),
			'plugin' => 'gravityforms/gravityforms.php',
			'tabs'   => array(
				'supports' => array(
					__('Creating, editing and deleting a form.', 'sh-extender'),
					__('Deleting a field from an existing form.', 'sh-extender'),
					__('Submitting, editing and deleting an entry.', 'sh-extender'),
					__('Changing the status of an entry, including read/unread and star/unstar.', 'sh-extender')
					),
				'lacks'    => array(
					__('Duplicating a form.', 'sh-extender'),
					__('Setting a form to active/inactive.', 'sh-extender')
					)
				)
			)
		);
	}

	function add_events(){
		$events = array(
			'star'   => __('starred', 'sh-extender'),
			'unstar' => __('unstarred', 'sh-extender'),
			'read'   => __('marked as read', 'sh-extender'),
			'unread' => __('marked as unread', 'sh-extender'),
			);

		return $events;
	}

	function add_actions(){

		// Form & Fields create/update/delete
		add_action( 'gform_after_save_form',     array( $this, 'after_save_form'     ), 10, 2 );
		add_action( 'gform_before_delete_form',  array( $this, 'before_delete_form'  ), 10, 1 );
		add_action( 'gform_before_delete_field', array( $this, 'before_delete_field' ), 10, 2 );

		/** 
		 * NOTE: Setting form active/inactive not loggable without 
		 * proper hook at RGFormsModel::update_form_active()
		 */

		// Entries create/update/delete
		add_action( 'gform_after_submission',    array( $this, 'after_submission'    ), 10, 2 );
		add_action( 'gform_after_update_entry',  array( $this, 'after_update_entry'  ), 10, 2 );
		add_action( 'gform_delete_lead',         array( $this, 'delete_entry'        ), 10, 1 );
		add_action( 'gform_update_status',       array( $this, 'update_status'       ), 10, 3 );
		add_action( 'gform_update_is_starred',   array( $this, 'update_is_starred'   ), 10, 3 );
		add_action( 'gform_update_is_read',      array( $this, 'update_is_read'      ), 10, 3 );
	}

	/** Helpers ******************************************************/

	function get_form( $id ){
		return RGFormsModel::get_form_meta( $id );
	}

	function get_entry( $id ){
		return RGFormsModel::get_lead( $id );
	}

	function get_field( $form_id, $id ){
		$form = $this->get_form( $form_id );
		return RGFormsModel::get_field( $form, $id );
	}

	function form_title( $form_id ){
		$form = $this->get_form( $form_id );
		return $form['title'];
	}

	function entry_form_title( $entry_id ){
		$entry = $this->get_entry( $entry_id );
		$form  = $this->get_form( $entry['form_id'] );
		return $form['title'];
	}

	function created_by( $entry_id, $trailing_space = true ){
		$entry   = $this->get_entry( $entry_id );
		$user_id = $entry['created_by'];

		if ( !is_null( $user_id ) ){
			$user = get_userdata( $user_id );
			$from = sprintf( __('from %s', 'sh-extender'), $user->user_login );
		} else
			$from = __('from unknown', 'sh-extender');

		return $from . ( $trailing_space ? ' ' : '' );
	}

	function extend_form( $form_id, $action ){
		$this->extend( array(
			'action' => $action,
			'type'   => __('Form', 'sh-extender'),
			'name'   => $this->form_title( $form_id ),
			'id'     => $form_id
			) );
	}

	function extend_entry( $entry_id, $action, $created_by = true ){
		$this->extend( array(
			'action' => $created_by ? $this->created_by( $entry_id ) . $action : $action,
			'type'   => __('Form entry', 'sh-extender'),
			'name'   => $this->entry_form_title( $entry_id ),
			'id'     => $entry_id
			) );
	}

	/** Form & Fields create/update/delete ***************************/

	/**
	 * @todo Get it working for creating form duplicate
	 */
	function after_save_form( $form, $is_new ){
		$this->extend_form( $form['id'], $is_new ? $this->events['new'] : $this->events['edit'] );
	}

	function before_delete_form( $form_id ){
		$entries = RGFormsModel::get_lead_count( $form_id, '' );

		$this->extend_form( 
			$form_id,
			0 == $entries 
				? __('without entries deleted', 'sh-extender')
				: sprintf( __('with %d entries deleted', 'sh-extender'), $entries )
			);
	}

	function before_delete_field( $form_id, $field_id ){
		$field = $this->get_field( $form_id, $field_id );

		$this->extend_form( 
			$form_id,
			sprintf( __('field %s deleted', 'sh-extender'), $field['label'] .' (ID: '. $field_id .')' )
			);
	}

	/** Entries create/update/delete *********************************/

	function after_submission( $entry, $form ){
		$this->extend_entry( $entry['id'], $this->events['submit'], false );
	}

	function after_update_entry( $form, $entry_id ){
		$this->extend_entry( $entry_id, $this->events['edit'] );
	}

	function delete_entry( $entry_id ){
		$this->extend_entry( $entry_id, $this->events['delete'] );
	}

	function update_status( $entry_id, $new_value, $old_value ){
		if ( $old_value !== $new_value ){

			switch ( $new_value ){
				case 'spam':
					$action = $this->events['spam'];
					break;

				case 'trash':
					$action = $this->events['trash'];
					break;

				case 'active':
					switch ( $old_value ){
						case 'trash' :
							$action = $this->events['untrash'];
							break;

						case 'spam' :
							$action = $this->events['unspam'];
							break;

						default :
							$action = __('restored', 'sh-extender');
					}
					break;

				default:
					$action = __('changed status', 'sh-extender');
			}

			$this->extend_entry( $entry_id, $action );
		}
	}

	function update_is_starred( $entry_id, $new_value, $old_value ){
		$this->extend_entry( $entry_id, 1 == $new_value ? $this->events['star'] : $this->events['unstar'] );
	}

	function update_is_read( $entry_id, $new_value, $old_value ){
		$this->extend_entry( $entry_id, 1 == $new_value ? $this->events['read'] : $this->events['unread'] );
	}
	
}

new Simple_History_Extend_GravityForms();

endif; // class_exists
