<?php

/**
 * Simple History Modules Gravity Forms Class
 *
 * Extend Simple History for Gravity Forms events
 * Version 1.6.9
 *
 * @since 1.1
 * 
 * @package Simple History Modules
 * @subpackage Modules
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'Simple_History_Module_GravityForms' ) ) :

/**
 * Plugin class
 */
class Simple_History_Module_GravityForms extends Simple_History_Module {

	function __construct(){
		parent::__construct( array(
			'id'     => 'gravityforms',
			'title'  => __('Gravity Forms', 'simple-history'),
			'plugin' => 'gravityforms/gravityforms.php',
			'tabs'   => array(
				'supports' => array(
					__('Creating, editing and deleting a form.',                                  'simple-history'),
					__('Deleting a field from an existing form.',                                 'simple-history'),
					__('Submitting, editing and deleting an entry.',                              'simple-history'),
					__('Changing the status of an entry, including read/unread and star/unstar.', 'simple-history'),
				),
				'lacks'    => array(
					__('Duplicating a form.',                                                     'simple-history'),
					__('Setting a form to active/inactive.',                                      'simple-history'),
				)
			)
		) );
	}

	function add_events(){
	
		// No common Gravity Forms events
		$events = array();

		return $events;
	}

	function add_actions(){

		/** 
		 * NOTE: Setting form active/inactive not loggable without 
		 * proper hook at RGFormsModel::update_form_active()
		 */

		// Form & Fields create/update/delete
		add_action( 'gform_after_save_form',     array( $this, 'form_saved'              ), 10, 2 );
		add_action( 'gform_before_delete_form',  array( $this, 'form_deleted'            ), 10, 1 );
		add_action( 'gform_before_delete_field', array( $this, 'form_field_deleted'      ), 10, 2 );

		// Entries create/update/delete
		add_action( 'gform_after_submission',    array( $this, 'entry_submitted'         ), 10, 2 );
		add_action( 'gform_after_update_entry',  array( $this, 'entry_updated'           ), 10, 2 );
		add_action( 'gform_delete_lead',         array( $this, 'entry_deleted'           ), 10, 1 );
		add_action( 'gform_update_status',       array( $this, 'entry_status_transition' ), 10, 3 );
		add_action( 'gform_update_is_starred',   array( $this, 'entry_star'              ), 10, 3 );
		add_action( 'gform_update_is_read',      array( $this, 'entry_read'              ), 10, 3 );
	}

	/** Helpers ******************************************************/

	/**
	 * Return form
	 *
	 * @since 1.1
	 * 
	 * @param int $form_id Form ID
	 * @return array Form data
	 */
	function get_form( $form_id ){
		return RGFormsModel::get_form_meta( $form_id );
	}

	/**
	 * Return form entry
	 *
	 * @since 1.1
	 * 
	 * @param int $entry_id Entry ID
	 * @return array Entry data
	 */
	function get_entry( $entry_id ){
		return RGFormsModel::get_lead( $entry_id );
	}

	/**
	 * Return form field
	 *
	 * @since 1.1
	 * 
	 * @param int $form_id Form ID
	 * @param int $field_id Field ID
	 * @return array Field data
	 */
	function get_field( $form_id, $field_id ){
		$form = $this->get_form( $form_id );
		return RGFormsModel::get_field( $form, $field_id );
	}

	/**
	 * Return form title
	 *
	 * @since 1.1
	 *
	 * @todo Apply title filters?
	 * 
	 * @param int $form_id Form ID
	 * @return string Form title
	 */
	function get_form_title( $form_id ){
		$form = $this->get_form( $form_id );
		return $form['title'];
	}

	/**
	 * Form logger. Requires form ID
	 *
	 * @since 1.1
	 *
	 * @param int $form_id Form ID
	 * @param string $action Log message
	 */
	function log_form( $form_id, $action ){
		$this->log( array(
			'action' => $action,
			'type'   => 'form',
			'name'   => $this->get_form_title( $form_id ),
			'id'     => $form_id
		) );
	}

	/**
	 * Entry logger. Requires entry ID or entry array
	 *
	 * @since 1.1
	 *
	 * @todo Make anonymous less anonymous with IP and name form fields
	 * 
	 * @param int|array $entry Entry ID or data array
	 * @param string $action Log message
	 */
	function log_entry( $entry, $action ){
		if ( is_numeric( $entry ) )
			$entry = $this->get_entry( $entry );

		// Provide action with entry author
		if ( false !== strpos( $action, '%3$s' ) ) {
			if ( ! is_null( $entry['created_by'] ) ) {
				$user   = get_userdata( $user_id );
				$author = $user->user_nicename; // Display name?
			} else {
				$author = __('Anonymous', 'simple-history');
			}

			$action = sprintf( $action, '%1$s', '%2$s', $author );
		}

		$this->log( array(
			'action' => $action,
			'type'   => 'form entry',
			'name'   => $this->get_form_title( $entry['form_id'] ),
			'id'     => $entry['id']
		) );
	}

	/** Form & Fields ************************************************/

	/**
	 * Log creating/updating forms
	 *
	 * Hooked into gform_after_save_form action
	 *
	 * @since 1.1
	 * 
	 * @todo Log duplicating forms
	 *
	 * @param array $form Form data
	 * @param boolean $is_new Is form created
	 */
	function form_saved( $form, $is_new ){
		if ( $is_new )
			$action = $this->events->new;
		else
			$action = $this->events->edit;

		$this->log_form( $form['id'], $action );
	}

	/**
	 * Log deleting forms
	 *
	 * Hooked into gform_before_delete_form action
	 * 
	 * @since 1.1
	 *
	 * @todo Use hook after deletion 
	 * 
	 * @param int $form_id Form ID
	 */
	function form_delete( $form_id ){
		$entries = RGFormsModel::get_lead_count( $form_id, '' );

		// Has deleted form entries?
		if ( empty( $entries ) )
			$action = __('%1$s %2$s without entries deleted', 'simple-history');
		else {
			$action = sprintf( 
				__('%1$s %2$s with %3$d entries deleted', 'simple-history'), 
				'%1$s', '%2$s', $entries 
			);
		}

		$this->log_form( $form_id, $action );
	}

	/**
	 * Log deleting form fields
	 *
	 * Hooked into gform_before_delete_field action
	 *
	 * @since 1.1
	 * 
	 * @param int $form_id Form ID
	 * @param int $field_id Form field ID
	 */
	function form_field_deleted( $form_id, $field_id ){
		$field = $this->get_field( $form_id, $field_id );

		$this->log_form( 
			$form_id, 
			sprintf( 
				__('%1$s %2$s field %3$s deleted', 'simple-history'), 
				'%1$s', '%2$s', $field['label'] .' (ID: '. $field_id .')' 
			)
		);
	}

	/** Entry ********************************************************/

	/**
	 * Log submitting entries
	 *
	 * Hooked into gform_after_submission action
	 *
	 * @since 1.1
	 * 
	 * @param array $entry Entry data
	 * @param array $form Form data ?
	 */
	function entry_submitted( $entry, $form ){
		// Translators: 1. Type, 2. Form title, 3. Entry author
		$this->log_entry( $entry['id'], __('%1$s for %2$s submitted by %3$s', 'simple-history') );
	}

	/**
	 * Log updating entries
	 *
	 * Hooked into gform_after_update_entry action
	 *
	 * @since 1.1
	 * 
	 * @param array $form Form data ?
	 * @param int $entry_id Entry ID
	 */
	function entry_updated( $form, $entry_id ){
		// Translators: 1. Type, 2. Form title, 3. Entry author
		$this->log_entry( $entry_id, __('%1$s by %3$s for %2$s edited', 'simple-history') );
	}

	/**
	 * Log deleting entries
	 *
	 * Hooked into gform_delete_lead action
	 *
	 * @since 1.1
	 * 
	 * @param array $form Form data ?
	 * @param int $entry_id Entry ID
	 */
	function entry_deleted( $entry_id ){
		// Translators: 1. Type, 2. Form title, 3. Entry author
		$this->log_entry( $entry_id, __('%1$s by %3$s for %2$s deleted', 'simple-history') );
	}

	/**
	 * Log changing entry status
	 *
	 * Hooked into gform_update_status action
	 *
	 * @since 1.1
	 * 
	 * @param int $entry_id Entry ID
	 * @param string $new New entry status
	 * @param string $old Previous entry status
	 */
	function entry_status_transition( $entry_id, $new, $old ){
		// Bail when nothing changed
		if ( $old === $new ) return;

		// Translators: 1. Type, 2. Form title, 3. Entry author

		// Entry marked as spam
		if ( 'spam' == $new )
			$action = _x('%1$s by %3$s for %2$s marked as spam',   'Spam form entry',      'simple-history');

		// Entry trashed
		elseif ( 'trash' == $new )
			$action = _x('%1$s by %3$s for %2$s trashed',          'Form entry trashed',   'simple-history');

		// Entry unmarked as spam
		elseif ( 'spam' == $old )
			$action = _x('%1$s by %3$s for %2$s unmarked as spam', 'Unspam form entry',    'simple-history');

		// Entry untrashed
		elseif ( 'trash' == $old )
			$action = _x('%1$s by %3$s for %2$s untrashed',        'Form entry untrashed', 'simple-history');

		// Entry restored
		elseif ( 'active' == $new )
			$action = _x('%1$s by %3$s for %2$s restored',         'Form entry restored',  'simple-history');

		// Other/custom status change
		else {
			$action = sprintf( 	
				_x('%1$s by %3$s on %2$s changed status from %4$s to %5$s', 'Form entry status changed', 'simple-history'),
				'%1$s', '%2$s', '%3$s', $old, $new
			);
		}

		$this->log_entry( $entry_id, $action );
	}

	/**
	 * Log marking/unmarking entries with star
	 *
	 * Hooked in gform_update_is_starred action
	 *
	 * @since 1.1
	 * 
	 * @param int $entry_id Entry ID
	 * @param int $new New value
	 * @param int $old Previous value
	 */
	function entry_star( $entry_id, $new, $old ){
		if ( 1 == $new )
			$action = __('%1$s by %3$s for %2$s marked with star',   'simple-history');
		else
			$action = __('%1$s by %3$s for %2$s unmarked with star', 'simple-history');

		$this->log_entry( $entry_id, $action );
	}

	/**
	 * Log marking entries as read/unread
	 *
	 * Hooked in gform_update_is_read action
	 *
	 * @since 1.1
	 * 
	 * @param int $entry_id Entry ID
	 * @param int $new New value
	 * @param int $old Previous value
	 */
	function entry_read( $entry_id, $new, $old ){
		if ( 1 == $new )
			$action = __('%1$s by %3$s for %2$s marked as read',   'simple-history');
		else
			$action = __('%1$s by %3$s for %2$s marked as unread', 'simple-history');

		$this->log_entry( $entry_id, $action );
	}	
}

new Simple_History_Module_GravityForms();

endif; // class_exists
