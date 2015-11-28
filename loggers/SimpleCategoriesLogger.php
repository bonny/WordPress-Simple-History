<?php

defined( 'ABSPATH' ) or die();

/**
 * Logs changes to categories and tags and taxonomies
 */
class SimpleCategoriesLogger extends SimpleLogger {

	public $slug = __CLASS__;

	/**
	 * Get array with information about this logger
	 * 
	 * @return array
	 */
	function getInfo() {

		$arr_info = array(			
			"name" => __("Categories Logger", "simple-history"),
			"description" => "Logs changes to categories, tags, and taxonomies",
			"messages" => array(
				'created_term' => __('Added term "{term_name}" in taxonomy "{term_taxonomy}"', 'simple-history'),
				'deleted_term' => __('Deleted term "{term_name}" from taxonomy "{term_taxonomy}"', 'simple-history'),
				'edited_term' => __('Edited term "{to_term_name}" in taxonomy "{to_term_taxonomy}"', 'simple-history'),
			),
			/*"labels" => array(
				"search" => array(
					"label" => _x("WordPress Core", "User logger: search", "simple-history"),
					"options" => array(
						_x("WordPress core updates", "User logger: search", "simple-history") => array(
							"core_updated",
							"core_auto_updated"
						),						
					)
				) // end search array
			) // end labels
			*/
		);
		
		return $arr_info;

	}

	/*

	 * Fires after a new term is created, and after the term cache has been cleaned.
	 *
	 * @since 2.3.0
	 *
	 * @param int    $term_id  Term ID.
	 * @param int    $tt_id    Term taxonomy ID.
	 * @param string $taxonomy Taxonomy slug.
	do_action( 'created_term', $term_id, $tt_id, $taxonomy );



	 * Fires after a term has been updated, and the term cache has been cleaned.
	 *
	 * @since 2.3.0
	 *
	 * @param int    $term_id  Term ID.
	 * @param int    $tt_id    Term taxonomy ID.
	 * @param string $taxonomy Taxonomy slug.
	do_action( "edited_term", $term_id, $tt_id, $taxonomy );
	
	 * Filter the term parent.
	 *
	 * Hook to this filter to see if it will cause a hierarchy loop.
	 *
	 * @since 3.1.0
	 *
	 * @param int    $parent      ID of the parent term.
	 * @param int    $term_id     Term ID.
	 * @param string $taxonomy    Taxonomy slug.
	 * @param array  $parsed_args An array of potentially altered update arguments for the given term.
	 * @param array  $args        An array of update arguments for the given term.
	$parent = apply_filters( 'wp_update_term_parent', $args['parent'], $term_id, $taxonomy, $parsed_args, $args );

	*/

	public function loaded() {

		add_action( 'created_term',  array( $this, "on_created_term"), 10, 3 );
		add_action( 'delete_term',  array( $this, "on_delete_term"), 10, 4 );
		add_action( 'wp_update_term_parent',  array( $this, "on_wp_update_term_parent"), 10, 5 );
	
		// This action does not contain enough info to know what the term was called before the update
		// add_action( "edited_term",  array( $this, "on_edited_term"), 10, 3 );	

	}

	/*
	 * Filter the term parent. 
	 * Only way for Simple History to get both old and new term name
	 *
	 * @param int    $parent      ID of the parent term.
	 * @param int    $term_id     Term ID.
	 * @param string $taxonomy    Taxonomy slug.
	 * @param array  $parsed_args An array of potentially altered update arguments for the given term.
	 * @param array  $args        An array of update arguments for the given term.
	 */
	function on_wp_update_term_parent( $parent = null, $term_id = null, $taxonomy = null, $parsed_args = null, $term_update_args = null ) {

		$term_before_edited = get_term_by( "id", $term_id, $taxonomy );

		if ( ! $term_before_edited || empty( $term_update_args ) ) {
			return $parent;
		}

		$term_id = $term_before_edited->term_id;
	
		$from_term_name = $term_before_edited->name;
		$from_term_taxonomy = $term_before_edited->taxonomy;
		$from_term_slug = $term_before_edited->slug;
		$from_term_description = $term_before_edited->description;

		$to_term_name = $term_update_args["name"];
		$to_term_taxonomy = $term_update_args["taxonomy"];
		$to_term_slug = $term_update_args["slug"];
		$to_term_description = $term_update_args["description"];

		$this->infoMessage(
			"edited_term",
			array(
				"term_id" => $term_id,
				"from_term_name" => $from_term_name,
				"from_term_taxonomy" => $from_term_taxonomy,
				"from_term_slug" => $from_term_slug,
				"from_term_slug" => $from_term_description,
				"to_term_name" => $to_term_name,
				"to_term_taxonomy" => $to_term_taxonomy,
				"to_term_slug" => $to_term_slug,
				"to_term_description" => $to_term_description,
				#"term_update_args" => $term_update_args,
				#"term_before_edited" => $term_before_edited
				#"parent" => $parent,
				#"taxonomy" => $taxonomy,
				#"parsed_args" => $parsed_args,
				#"term_update_args" => $term_update_args
			)
		);

		return $parent;

	}

	/*		
	 * Fires after a new term is created, and after the term cache has been cleaned.
	 *
	 * @since 2.3.0
	 *
	 * @param int    $term_id  Term ID.
	 * @param int    $tt_id    Term taxonomy ID.
	 * @param string $taxonomy Taxonomy slug.
	 */
	function on_created_term( $term_id = null, $tt_id = null, $taxonomy = null ) {

		$term = get_term_by( "id", $term_id, $taxonomy );

		if ( ! $term ) {
			return;
		}

		$term_name = $term->name;
		$term_taxonomy = $term->taxonomy;
		$term_id = $term->term_id;

		$this->infoMessage(
			"created_term",
			array(
				"term_id" => $term_id,
				"term_name" => $term_name,
				"term_taxonomy" => $term_taxonomy,
			)
		);

	}


	 /*
	 * Fires after a term is deleted from the database and the cache is cleaned.
	 *
	 * @param int     $term         Term ID.
	 * @param int     $tt_id        Term taxonomy ID.
	 * @param string  $taxonomy     Taxonomy slug.
	 * @param mixed   $deleted_term Copy of the already-deleted term, in the form specified
	 *                              by the parent function. WP_Error otherwise.
	 */
	function on_delete_term( $term = null, $tt_id = null, $taxonomy = null, $deleted_term = null ) {

		if ( is_wp_error( $deleted_term ) ) {
			return;
		}

		$term_name = $deleted_term->name;
		$term_taxonomy = $deleted_term->taxonomy;
		$term_id = $deleted_term->term_id;

		$this->infoMessage(
			"deleted_term",
			array(
				"term_id" => $term_id,
				"term_name" => $term_name,
				"term_taxonomy" => $term_taxonomy,
				// "deleted_term" => $deleted_term,
			)
		);

	}


	/*
	function on_edited_term( $term_id = null, $tt_id = null, $taxonomy ) {

		$this->debug(
			"on_edited_term",
			array(
				"term_id" => $term_id,
				"tt_id" => $tt_id,
				"taxonomy" => $taxonomy
			)
		);

	}
	*/

}
