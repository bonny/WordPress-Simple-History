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
			"post__in" => null
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
				t.id,
				t.logger,
				t.level,
				t.message,
				t.occasionsID,
				count(REPEATED) AS subsequentOccasions,
				t.date,
				t.rep,
				t.repeated,
				t.type
			FROM 
				(
					SELECT 
						id, 
						logger, 
						level, 
						message, 
						occasionsID, 
						date, 
						IF(@a=occasionsID,@counter:=@counter+1,@counter:=1) AS rep,
						IF(@counter=1,@groupby:=@groupby+1,@groupby) AS repeated,
						@a:=occasionsID type 
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
		
		// Add context
		$post_ids = wp_list_pluck( $log_rows, "id" );

		$sql_context = sprintf('SELECT * FROM wp_simple_history_contexts WHERE history_id IN (%1$s)', join(",", $post_ids));
		$context_results = $wpdb->get_results($sql_context);

		foreach ( $context_results as $context_row ) {

			if ( ! isset( $log_rows[ $context_row->history_id ]->context ) ) {
				$log_rows[ $context_row->history_id ]->context = array();
			}

			$log_rows[ $context_row->history_id ]->context[ $context_row->key ] = $context_row->value;

		}

		return $log_rows;
	
	} // query

} // class

