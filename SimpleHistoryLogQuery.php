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
			// overview | occasions
			"type" => "overview",
			// Number of posts to show per page. 0 to show all.
			"posts_per_page" => 0,
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
			"format" => "array",
			// If max_id_first_page is set then only get rows
			// that have id equal or lower than this, to make
			// 
			"max_id_first_page" => null
		);

		$args = wp_parse_args( $args, $defaults );
		// sf_d($args, "Run log query with args");

		/*
		Subequent occasions query thanks to this Stack Overflow thread:
		http://stackoverflow.com/questions/13566303/how-to-group-subsequent-rows-based-on-a-criteria-and-then-count-them-mysql/13567320#13567320
		Similar questions that I didn't manage to understand, work, or did try:
		- http://stackoverflow.com/questions/23651176/mysql-query-if-dates-are-subsequent
		- http://stackoverflow.com/questions/17651868/mysql-group-by-subsequent
		- http://stackoverflow.com/questions/4495242/mysql-number-of-subsequent-occurrences
		- http://stackoverflow.com/questions/20446242/postgresql-group-subsequent-rows
		- http://stackoverflow.com/questions/17061156/mysql-group-by-range
		- http://stackoverflow.com/questions/6602006/complicated-query-with-group-by-and-range-of-prices-in-mysql
		*/

		global $wpdb;

		$table_name = $wpdb->prefix . SimpleHistory::DBTABLE;

		$where = "1 = 1";
		$limit = "";
		$inner_where = "";

		if ( "overview" === $args["type"] || "single" === $args["type"] ) {

			// Set variables used by query
			$sql_set_var = "SET @a:='', @counter:=1, @groupby:=0";
			$wpdb->query( $sql_set_var );

			// Query template
			// 1 = where
			// 2 = limit
			// 3 = db name
			// 4 = where for inner calc sql query thingie
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
						FROM %3$s

						# Add where here
						WHERE 1 = 1 
						%4$s

						ORDER BY id DESC
					) AS t
				WHERE %1$s
				GROUP BY repeated
				ORDER BY id DESC
				%2$s
			';

			$sh = $GLOBALS["simple_history"];

			// Array with the loggers that the current user can view		
			$arr_loggers_user_can_view = $sh->getLoggersThatUserCanRead(get_current_user_id());
			$arr_logger_slugs = array();
			$inner_where = " AND logger IN (";
			foreach ($arr_loggers_user_can_view as $one_logger) {
				
				$inner_where .= sprintf(
					'"%1$s", ',
					$one_logger["instance"]->slug
				);

			}
			$inner_where = rtrim($inner_where, " ,");
			$inner_where .= ")";


		} else if ( "occasions" === $args["type"] ) {

			// Query template
			// 1 = where
			// 2 = limit
			// 3 = db name
			$sql_tmpl = '
				SELECT h.*,
					# fake columns that exist in overview query
					1 as subsequentOccasions
				FROM %3$s AS h
				WHERE %1$s
				ORDER BY id DESC
				%2$s
			';

			$where .= " AND h.id <= " . (int) $args["logRowID"];
			$where .= " AND h.occasionsID = '" . esc_sql( $args["occasionsID"] ) . "'";

			$limit = "LIMIT " . (int) $args["occasionsCount"];

			// [logRowID] =&gt; 353
			// [occasionsID] =&gt; 73b06d5740d15e35079b6aa024255cb3
			// [occasionsCount] =&gt; 18

		}
		
		// Determine limit
		// Both posts_per_pae and paged must be set
		$is_limit_query = ( is_numeric( $args["posts_per_page"] ) && $args["posts_per_page"] > 0 );
		$is_limit_query = $is_limit_query && ( is_numeric( $args["paged"] ) && $args["paged"] > 0 );
		if ($is_limit_query) {
			$limit_offset = ($args["paged"] - 1) * $args["posts_per_page"];
			$limit .= sprintf('LIMIT %1$d, %2$d', $limit_offset, $args["posts_per_page"] );
		}

		// Determine where
		if ( $args["post__in"] && is_array( $args["post__in"] ) ) {

			$where .= sprintf(' AND t.id IN (%1$s)', implode(",", $args["post__in"]));

		}

		// If max_id_first_page is then then only include rows
		// with id equal to or earlier
		if ( isset($args["max_id_first_page"]) && is_numeric($args["max_id_first_page"]) ) {
			
			$max_id_first_page = (int) $args["max_id_first_page"];
			$where .= sprintf(
				' AND t.id <= %1$d',
				$max_id_first_page
			);

		}

		/**
		 * Filter the sql template
		 *
		 * @since 2.0
		 *
		 * @param string $sql_tmpl
		 */
		$sql_tmpl = apply_filters("simple_history/log_query_sql_template", $sql_tmpl);

		/**
		 * Filter the sql template where clause
		 *
		 * @since 2.0
		 *
		 * @param string $where
		 */
		$where = apply_filters("simple_history/log_query_sql_where", $where);

		/**
		 * Filter the sql template limit
		 *
		 * @since 2.0
		 *
		 * @param string $limit
		 */
		$limit = apply_filters("simple_history/log_query_limit", $limit);

		/**
		 * Filter the sql template limit
		 *
		 * @since 2.0
		 *
		 * @param string $limit
		 */
		$inner_where = apply_filters("simple_history/log_query_inner_where", $inner_where);

		$sql = sprintf($sql_tmpl, $where, $limit, $table_name, $inner_where);
		#echo($sql);exit;

		/**
		 * Filter the final sql query
		 *
		 * @since 2.0
		 *
		 * @param string $sql
		 */
		$sql = apply_filters("simple_history/log_query_sql", $sql);

		if (isset($_GET["SimpleHistoryLogQuery-showDebug"]) && $_GET["SimpleHistoryLogQuery-showDebug"]) {

			echo $sql_set_var;
			echo $sql;
			exit;

		}

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

			// the last occasion has the id we consider last in this paged result
			$min_id = end($results)->id;

		}

		// Calc pages
		if ( $args["posts_per_page"] ) {
			$pages_count = Ceil ( $total_found_rows / (int) $args["posts_per_page"] );
		} else {
			$pages_count = 1;
		}

		// Create array to return
		// Make all rows a sub key because we want to add some meta info too
		$log_rows_count = sizeof( $log_rows );
		$page_rows_from = ( (int) $args["paged"] * (int) $args["posts_per_page"] ) - (int) $args["posts_per_page"] + 1;
		$page_rows_to = $page_rows_from + $log_rows_count - 1;
		$arr_return = array(
			"total_row_count" => $total_found_rows,
			"pages_count" => $pages_count,
			"page_current" => (int) $args["paged"],
			"page_rows_from" => $page_rows_from,
			"page_rows_to" => $page_rows_to,
			"max_id" => (int) $max_id,
			"min_id" => (int) $min_id,
			"log_rows_count" => $log_rows_count,
			"log_rows" => $log_rows,
		);

		#sf_d($arr_return, '$arr_return');exit;

		return $arr_return;
	
	} // query

} // class

