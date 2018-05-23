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
			'name' => __( 'Categories Logger', 'simple-history' ),
			'description' => 'Logs changes to categories, tags, and taxonomies',
			'messages' => array(
				'created_term' => __( 'Added term "{term_name}" in taxonomy "{term_taxonomy}"', 'simple-history' ),
				'deleted_term' => __( 'Deleted term "{term_name}" from taxonomy "{term_taxonomy}"', 'simple-history' ),
				'edited_term' => __( 'Edited term "{to_term_name}" in taxonomy "{to_term_taxonomy}"', 'simple-history' ),
			),
			/*
			"labels" => array(
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

	/**
	 * Called when the logger is loaded.
	 */
	public function loaded() {
		// Fires after a new term is created, and after the term cache has been cleaned..
		add_action( 'created_term',  array( $this, 'on_created_term' ), 10, 3 );

		// Hook to this filter to see if it will cause a hierarchy loop.
		add_action( 'delete_term',  array( $this, 'on_delete_term' ), 10, 4 );

		// Filter the term parent.
		add_action( 'wp_update_term_parent',  array( $this, 'on_wp_update_term_parent' ), 10, 5 );
	}

	/**
	 * Filter the term parent.
	 * Only way for Simple History to get both old and new term name.
	 * For example 'edited_term' does not contain enough info to know what the term was called before the update.
	 *
	 * @param int    $parent      ID of the parent term.
	 * @param int    $term_id     Term ID.
	 * @param string $taxonomy    Taxonomy slug.
	 * @param array  $parsed_args An array of potentially altered update arguments for the given term.
	 * @param array  $term_update_args        An array of update arguments for the given term.
	 */
	function on_wp_update_term_parent( $parent = null, $term_id = null, $taxonomy = null, $parsed_args = null, $term_update_args = null ) {

		$term_before_edited = get_term_by( 'id', $term_id, $taxonomy );

		if ( ! $term_before_edited || empty( $term_update_args ) ) {
			return $parent;
		}

		$term_id = $term_before_edited->term_id;

		$from_term_name = $term_before_edited->name;
		$from_term_taxonomy = $term_before_edited->taxonomy;
		$from_term_slug = $term_before_edited->slug;
		$from_term_description = $term_before_edited->description;

		$to_term_name = $term_update_args['name'];
		$to_term_taxonomy = $term_update_args['taxonomy'];
		$to_term_slug = $term_update_args['slug'];
		$to_term_description = $term_update_args['description'];

		$do_log_term = $this->ok_to_log_taxonomy( $from_term_taxonomy );

		if ( ! $do_log_term ) {
			return $parent;
		}

		$this->infoMessage(
			'edited_term',
			array(
				'term_id' => $term_id,
				'from_term_name' => $from_term_name,
				'from_term_taxonomy' => $from_term_taxonomy,
				'from_term_slug' => $from_term_slug,
				'from_term_description' => $from_term_description,
				'to_term_name' => $to_term_name,
				'to_term_taxonomy' => $to_term_taxonomy,
				'to_term_slug' => $to_term_slug,
				'to_term_description' => $to_term_description,
			)
		);

		return $parent;

	}

	/**
	 * Check if it's ok to log a taxonomy.
	 * We skip some taxonomies, for example Polylang translation terms that fill the log with
	 * messages like 'Edited term "pll_5a3643a142c80" in taxonomy "post_translations"' otherwise.
	 *
	 * @since 2.21
	 * @param string $from_term_taxonomy Slug of taxonomy.
	 * @return bool True or false.
	 */
	function ok_to_log_taxonomy( $from_term_taxonomy = '' ) {
		if ( empty( $from_term_taxonomy ) ) {
			return false;
		}

		$skip_taxonomies = $this->get_skip_taxonomies();

		$do_log = ! in_array( $from_term_taxonomy, $skip_taxonomies, true );

		return $do_log;
	}

	/**
	 * Get taxonomies to skip.
	 *
	 * @since 2.21
	 * @return array Array with taxonomies.
	 */
	function get_skip_taxonomies() {

		$taxonomies_to_skip = array(
			// Polylang taxonomies used to store translation mappings.
			'post_translations',
			'term_translations',
		);

		$taxonomies_to_skip = apply_filters( 'simple_history/categories_logger/skip_taxonomies', $taxonomies_to_skip );

		return $taxonomies_to_skip;
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

		$term = get_term_by( 'id', $term_id, $taxonomy );

		if ( ! $term ) {
			return;
		}

		$term_name = $term->name;
		$term_taxonomy = $term->taxonomy;
		$term_id = $term->term_id;

		$do_log_term = $this->ok_to_log_taxonomy( $term_taxonomy );

		if ( ! $do_log_term ) {
			return;
		}

		$this->infoMessage(
			'created_term',
			array(
				'term_id' => $term_id,
				'term_name' => $term_name,
				'term_taxonomy' => $term_taxonomy,
			)
		);

	}


	/**
	 * Fires after a term is deleted from the database and the cache is cleaned.
	 *
	 * @param int    $term         Term ID.
	 * @param int    $tt_id        Term taxonomy ID.
	 * @param string $taxonomy     Taxonomy slug.
	 * @param mixed  $deleted_term Copy of the already-deleted term, in the form specified
	 *                              by the parent function. WP_Error otherwise.
	 */
	function on_delete_term( $term = null, $tt_id = null, $taxonomy = null, $deleted_term = null ) {

		if ( is_wp_error( $deleted_term ) ) {
			return;
		}

		$term_name = $deleted_term->name;
		$term_taxonomy = $deleted_term->taxonomy;
		$term_id = $deleted_term->term_id;

		$do_log_term = $this->ok_to_log_taxonomy( $term_taxonomy );

		if ( ! $do_log_term ) {
			return;
		}

		$this->infoMessage(
			'deleted_term',
			array(
				'term_id' => $term_id,
				'term_name' => $term_name,
				'term_taxonomy' => $term_taxonomy,
			)
		);

	}

	/**
	 * Modify plain output to include link to term and taxonomy.
	 *
	 * @param array $row Row data.
	 */
	public function getLogRowPlainTextOutput( $row ) {
		$context = $row->context;
		$message_key = isset( $context['_message_key'] ) ? $context['_message_key'] : null;

		// Default to original log message.
		$message = $row->message;

		// Get term that was created, edited, or removed.
		$term_id = isset( $context['term_id'] ) ? (int) $context['term_id'] : null;

		// Get taxonomy for term.
		if ( 'created_term' === $message_key  || 'deleted_term' === $message_key ) {
			$term_taxonomy = isset( $context['term_taxonomy'] ) ? (string) $context['term_taxonomy'] : null;
		} elseif ( 'edited_term' === $message_key ) {
			$term_taxonomy = isset( $context['from_term_taxonomy'] ) ? (string) $context['from_term_taxonomy'] : null;
		}

		$tax_edit_link = add_query_arg(
			array(
				'taxonomy' => $term_taxonomy,
			),
			admin_url( 'term.php' )
		);

		$context['tax_edit_link'] = $tax_edit_link;

		$term_object = get_term( $term_id, $term_taxonomy );

		if ( is_wp_error( $term_object ) ) {
			return $this->interpolate( $message, $context, $row );
		}

		$term_edit_link = get_edit_tag_link( $term_id, $term_object->taxonomy );
		$context['term_edit_link'] = $term_edit_link;

		if ( 'created_term' === $message_key && ! empty( $term_edit_link ) && ! empty( $tax_edit_link ) ) {
			$message = _x(
				'Added term <a href="{term_edit_link}">"{term_name}"</a> in taxonomy <a href="{tax_edit_link}">"{term_taxonomy}"</a>',
				'Categories logger: detailed plain text output for created term',
				'simple-history'
			);
		} elseif ( 'deleted_term' === $message_key && ! empty( $tax_edit_link ) ) {
			$message = _x(
				'Deleted term "{term_name}" from taxonomy <a href="{tax_edit_link}">"{term_taxonomy}"</a>',
				'Categories logger: detailed plain text output for deleted term',
				'simple-history'
			);
		} elseif ( 'edited_term' === $message_key && ! empty( $term_edit_link ) && ! empty( $tax_edit_link ) ) {
			$message = _x(
				'Edited term <a href="{term_edit_link}">"{to_term_name}"</a> in taxonomy <a href="{tax_edit_link}">"{to_term_taxonomy}"</a>',
				'Categories logger: detailed plain text output for edited term',
				'simple-history'
			);
		}

		return $this->interpolate( $message, $context, $row );
	}

}
