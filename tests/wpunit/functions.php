<?php

namespace Simple_History\tests;

use Simple_History\Simple_History;

/**
 * @param bool $unset_fields Unset some fields that are different on each run, making it difficult to compare.
 * @return array
 */
function get_latest_row( $unset_fields = true ) {
	global $wpdb;
	$db_table = $wpdb->prefix . Simple_History::DBTABLE;
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$latest_row = $wpdb->get_row( "SELECT * FROM {$db_table} ORDER BY id DESC", ARRAY_A );

	if ( $unset_fields ) {
		unset( $latest_row['id'], $latest_row['date'], $latest_row['occasionsID'] );
	}

	return $latest_row;
}

function get_latest_context( $unset_fields = true ) {
	$latest_row = get_latest_row( false );

	global $wpdb;
	$dbtable_contexts = Simple_History::$dbtable_contexts;
	// // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$latest_context = $wpdb->get_results( "SELECT * FROM {$dbtable_contexts} WHERE history_id = {$latest_row['id']} ORDER BY `key` ASC", ARRAY_A );

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
