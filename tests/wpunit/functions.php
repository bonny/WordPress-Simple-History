<?php

namespace Simple_History\tests;

use Simple_History\Simple_History;

/**
 * Get the latest row from the events table, date or contents does not matter.
 * 
 * @param bool $unset_fields Unset some fields that are different on each run, making it difficult to compare.
 * @return array
 */
function get_latest_row( $unset_fields = true ) {
	global $wpdb;
	$db_table = Simple_History::get_instance()->get_events_table_name();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$latest_row = $wpdb->get_row( "SELECT * FROM {$db_table} ORDER BY id DESC", ARRAY_A );

	if ( $unset_fields ) {
		unset( $latest_row['id'], $latest_row['date'], $latest_row['occasionsID'] );
	}

	return $latest_row;
}

/**
 * Get the latest context from the contexts table.
 * 
 * @param bool $unset_fields Unset some fields that are different on each run, making it difficult to compare.
 * @return array
 */
function get_latest_context( $unset_fields = true ) {
	$latest_row = get_latest_row( false );

	global $wpdb;
	$db_table_contexts = Simple_History::get_instance()->get_contexts_table_name();
	$latest_context = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM {$db_table_contexts} WHERE history_id = %d ORDER BY `key` ASC",
			$latest_row['id']
		),
		ARRAY_A
	);

	if ( $unset_fields ) {
		$latest_context = array_map(
			function( $value ) {
				unset( $value['context_id'], $value['history_id'] );
				return $value;
			},
			$latest_context
		);
	}

	return $latest_context;
}
