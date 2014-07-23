<?php

/**
 * Queries the Simple History Log
 */ 
class SimpleHistoryLogQuery {
	
	public function __construct($args = array()) {

		if ( is_array($args) && ! empty($args) ) {

			$this->query($args);

		}

	}

	public function query($args) {
		
		$defaults = array(
			// Number of posts to show per page. -1 to show all.
			"posts_per_page" => 5,
			// Page to show. 1 = first page
			"paged" => 1,
			// Free text search
			"s" => null,
			// Array with logger names, to show only items from those loggers
			// Default = null = all loggers.
			"logger" => null,
			// array with loglevels to get, as specified in SimpleLoggerLogLevels
			"loglevel" => null,
			// Array. Only get posts that are in array.
			"post__in" => null,
			// array or html
			"format" => "array" 
		);

		$args = wp_parse_args( $args, $defaults );

		#sf_d($args, "Run log query with args");

		/*
		Subequent occasions query thanks to this Stack Overflow thread:
		http://stackoverflow.com/questions/13566303/how-to-group-subsequent-rows-based-on-a-criteria-and-then-count-them-mysql/13567320#13567320
		Similar questions that I didn't manage to understart, work, or did try:
		- http://stackoverflow.com/questions/23651176/mysql-query-if-dates-are-subsequent
		- http://stackoverflow.com/questions/17651868/mysql-group-by-subsequent
		- http://stackoverflow.com/questions/4495242/mysql-number-of-subsequent-occurrences
		- http://stackoverflow.com/questions/20446242/postgresql-group-subsequent-rows
		- http://stackoverflow.com/questions/17061156/mysql-group-by-range
		- http://stackoverflow.com/questions/6602006/complicated-query-with-group-by-and-range-of-prices-in-mysql
		*/

		global $wpdb;

		// Set variables used by query
		$wpdb->query("SET @a:='', @counter:=1, @groupby:=0");

		// Query template
		// 1 = where
		// 2 = limit
		$sql_tmpl = '
			SELECT 
				SQL_CALC_FOUND_ROWS
				t.id,
				t.logger,
				t.level,
				t.date,
				t.message,
				t.type,
				t.initiator,
				t.occasionsID,
				count(REPEATED) AS subsequentOccasions,
				t.rep,
				t.repeated,
				t.occasionsIDType
			FROM 
				(
					SELECT 
						id, 
						logger, 
						level, 
						message, 
						type,
						initiator,
						occasionsID, 
						date, 
						IF(@a=occasionsID,@counter:=@counter+1,@counter:=1) AS rep,
						IF(@counter=1,@groupby:=@groupby+1,@groupby) AS repeated,
						@a:=occasionsID occasionsIDType 
					FROM wp_simple_history

					#Add where here?
					#WHERE 
					#	( logger = "SimpleLogger" AND message LIKE "%cron%")

					ORDER BY id DESC
				) AS t
			WHERE %1$s
			GROUP BY repeated
			ORDER BY id DESC
			%2$s
		';

		$where = "1 = 1";
		$limit = "";

		// Determine where-conditions

		// Determine limit
		// Both posts_per_pae and paged must be set
		$is_limit_query = ( is_numeric( $args["posts_per_page"] ) && $args["posts_per_page"] > 0 );
		$is_limit_query = $is_limit_query && ( is_numeric( $args["paged"] ) && $args["paged"] > 0 );
		if ($is_limit_query) {
			$limit_offset = ($args["paged"] - 1) * $args["posts_per_page"];
			$limit .= sprintf('LIMIT %1$d, %2$d', $limit_offset, $args["posts_per_page"] );
		}

		$sql = sprintf($sql_tmpl, $where, $limit);
		#sf_d($sql, '$sql');

		$log_rows = $wpdb->get_results($sql, OBJECT_K);
		$num_rows = sizeof($log_rows);

		// Find total number of rows that we would have gotten without pagination
		// This is the number of rows with occasions taken into consideration
		$sql_found_rows = 'SELECT FOUND_ROWS()';
		$total_found_rows = (int) $wpdb->get_var( $sql_found_rows );
		
		// Add context
		$post_ids = wp_list_pluck( $log_rows, "id" );

		if ( empty($post_ids) ) {
			$context_results = array();
		} else {
			$sql_context = sprintf('SELECT * FROM wp_simple_history_contexts WHERE history_id IN (%1$s)', join(",", $post_ids));
			$context_results = $wpdb->get_results($sql_context);
		}

		foreach ( $context_results as $context_row ) {

			if ( ! isset( $log_rows[ $context_row->history_id ]->context ) ) {
				$log_rows[ $context_row->history_id ]->context = array();
			}

			$log_rows[ $context_row->history_id ]->context[ $context_row->key ] = $context_row->value;

		}

		// Remove id from keys, because they are cumbersome when working with JSON
		$log_rows = array_values($log_rows);

		// Max id is simply the id of the first row
		$max_id = reset($log_rows)->id;

		// Min id = to find the lowest id we must take occasions into consideration
		$min_id = null;
		$last_row = end($log_rows);
		$last_row_occasions_count = (int) $last_row->subsequentOccasions - 1;
		if ($last_row_occasions_count === 0) {

			// Last row did not have any more occasions, so get min_id directly from the row
			$min_id = $last_row->id;

		} else {
			
			// Last row did have occaions, so fetch all occasions, and find id of last one
			$db_table = $wpdb->prefix . SimpleHistory::DBTABLE;
			$sql = sprintf(
				'
					SELECT id, date, occasionsID
					FROM %1$s 
					WHERE id <= %2$s
					ORDER BY id DESC
					LIMIT %3$s
				',
				$db_table,
				$last_row->id,
				$last_row_occasions_count + 1
			);
			
			$results = $wpdb->get_results( $sql );

			// the last occassion has the id we consider last in this paged result
			$min_id = end($results)->id;

		}

		// Calc pages
		$pages_count = Ceil ( $total_found_rows / (int) $args["posts_per_page"] );

		// Create array to return
		// Make all rows a sub key because we want to add some meta info too
		$arr_return = array(
			"total_row_count" => $total_found_rows,
			"pages_count" => $pages_count,
			"max_id" => $max_id,
			"min_id" => $min_id,
			"log_rows" => $log_rows,
		);

		#sf_d($arr_return, '$arr_return');exit;

		return $arr_return;
	
	} // query

} // class

