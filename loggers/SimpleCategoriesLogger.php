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
				'core_updated' => __('Updated WordPress to {new_version} from {prev_version}', 'simple-history'),
                                'core_auto_updated' => __('WordPress auto-updated to {new_version} from {prev_version}', 'simple-history'),
                                "core_db_version_updated" => __('WordPress database version updated to {new_version} from {prev_version}', 'simple-history')
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


	 * Fires after a term is deleted from the database and the cache is cleaned.
	 *
	 * @since 2.5.0
	 *
	 * @param int     $term         Term ID.
	 * @param int     $tt_id        Term taxonomy ID.
	 * @param string  $taxonomy     Taxonomy slug.
	 * @param mixed   $deleted_term Copy of the already-deleted term, in the form specified
	 *                              by the parent function. WP_Error otherwise.
	do_action( 'delete_term', $term, $tt_id, $taxonomy, $deleted_term );


	 * Fires after a term has been updated, and the term cache has been cleaned.
	 *
	 * @since 2.3.0
	 *
	 * @param int    $term_id  Term ID.
	 * @param int    $tt_id    Term taxonomy ID.
	 * @param string $taxonomy Taxonomy slug.
	do_action( "edited_term", $term_id, $tt_id, $taxonomy );
	
	*/


	public function loaded() {

		add_action( 'created_term',  array( $this, "on_created_term"), 10, 3 );
		add_action( 'delete_term',  array( $this, "on_delete_term"), 10, 4 );
		add_action( "edited_term",  array( $this, "on_edited_term"), 10, 3 );	

	}

	function on_created_term( $term_id = null, $tt_id = null, $taxonomy = null ) {

		$this->debug(
			"on_created_term",
			array(
				"term_id" => $term_id,
				"tt_id" => $tt_id,
				"taxonomy" => $taxonomy
			)
		);

	}



	function on_delete_term( $term = null, $tt_id = null, $taxonomy = null, $deleted_term = null ) {

		$this->debug(
			"on_delete_term",
			array(
				"term" => $term,
				"tt_id" => $tt_id,
				"taxonomy" => $taxonomy,
				"deleted_term" => $deleted_term
			)
		);

	}



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

}
