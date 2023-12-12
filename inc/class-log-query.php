<?php

namespace Simple_History;

use Simple_History\Helpers;

/**
 * Queries the Simple History Log.
 *
 * Todo
 * - [x] Convert $inner_where and $where to arrays, instead of concating.
 *  - [ ] Should array be array of arrays where each AND clause contains a description? Could be useful for debugging + be a little bit prepared for future support for both "AND" and "OR" clauses.
 * - Add "prepare args" function.
 * - Add "get where clause" function that returns the where clause.
 * - Add "get sql_query" function that returns the sql query.
 */
class Log_Query {
	/**
	 * Query the log.
	 *
	 * @param string|array|object $args {
	 *    Optional. Array or string of arguments for querying the log.
	 *      @type string $type Type of query. Accepts 'overview', 'occasions', or 'single'. Default 'overview'.
	 *      @type int $posts_per_page Number of posts to show per page. Default is 10.
	 *      @type int $paged Page to show. 1 = first page. Default 1.
	 *      @type array $post__in Array. Only get posts that are in array. Default null.
	 *      @type string $format Array or html. Default 'array'.
	 *      @type int $max_id_first_page If max_id_first_page is set then only get rows that have id equal or lower than this, to make
	 *                                      sure that the first page of results is not too large. Default null.
	 *      @type int $since_id If since_id is set the rows returned will only be rows with an ID greater than (i.e. more recent than) since_id. Default null.
	 *      @type int|string $date_from From date, as unix timestamp integer or as a format compatible with strtotime, for example 'Y-m-d H:i:s'. Default null.
	 *      @type int|string $date_to To date, as unix timestamp integer or as a format compatible with strtotime, for example 'Y-m-d H:i:s'. Default null.
	 *      @type array|string $months Months in format "Y-m". Default null.
	 *      @type array|string $dates Dates in format "month:2015-06" for june 2015 or "lastdays:7" for the last 7 days. Default null.
	 *      @type string $search Text to search for. Message, logger and level are searched for in main table. Values are searched for in context table. Default null.
	 *      @type string $loglevels Log levels to include. Comma separated or as array. Defaults to all. Default null.
	 *      @type string $loggers Loggers to include. Comma separated or array. Default null = all the user can read.
	 *      @type string $messages Messages to include. Array or string with commaa separated in format "LoggerSlug:Message", e.g. "SimplePluginLogger:plugin_activated,SimplePluginLogger:plugin_deactivated". Default null = show all messages.
	 *      @type int $user Single user ID as number. Default null.
	 *      @type string $users User IDs, comma separated or array. Default null.
	 * }
	 * @return array
	 */
	public function query( $args ) {
		global $wpdb;

		/** @var Simple_History Simple History instance. */
		$simple_history = Simple_History::get_instance();

		/** @var string SQL Template to use. Template used depends on $args['type'].  */
		$sql_tmpl = null;

		/** @var array Query arguments. */
		$args = wp_parse_args(
			$args,
			[
				// overview | occasions | single.
				// When type is occasions then logRowID, occasionsID, occasionsCount, occasionsCountMaxReturn are required.
				// TODO: Add default for above required args.
				'type' => 'overview',

				// Number of posts to show per page. 0 to show all.
				'posts_per_page' => 10,

				// Page to show. 1 = first page.
				'paged' => 1,

				// Array. Only get posts that are in array.
				'post__in' => [],

				// array or html.
				'format' => 'array',

				// If max_id_first_page is set then only get rows
				// that have id equal or lower than this, to make.
				'max_id_first_page' => null,

				// if since_id is set the rows returned will only be rows with an ID greater than (i.e. more recent than) since_id.
				'since_id' => null,

				/**
				 * From date, as unix timestamp integer or as a format compatible with strtotime, for example 'Y-m-d H:i:s'.
				 *
				 * @var int|string
				 */
				'date_from' => null,

				/**
				* To date, as unix timestamp integer or as a format compatible with strtotime, for example 'Y-m-d H:i:s'.
				*
				* @var int|string
				*/
			   'date_to' => null,

				// months in format "Y-m"
				// array or comma separated.
				'months' => null,

				// dates in format
				// "month:2015-06" for june 2015
				// "lastdays:7" for the last 7 days.
				'dates' => null,

				/**
				 * Text to search for.
				 * Message, logger and level are searched for in main table.
				 * Values are searched for in context table.
				 *
				 * @var string
				 */
				'search' => null,

				// log levels to include. comma separated or as array. defaults to all.
				'loglevels' => null,

				// loggers to include. comma separated. defaults to all the user can read.
				'loggers' => null,

				'messages' => null,

				// userID as number.
				'user' => null,

				// User ids, comma separated or array.
				'users' => null,

			// Can also contain:
			// logRowID
			// occasionsCount
			// occasionsCountMaxReturn
			// occasionsID.
			]
		);

		// Create cache key based on args and request and current user.
		$cache_key = 'SimpleHistoryLogQuery_' . md5( serialize( $args ) ) . '_get_' . md5( serialize( $_GET ) ) . '_userid_' . get_current_user_id();
		$cache_group = 'simple-history-' . Helpers::get_cache_incrementor();

		/** @var array Return value. */
		$arr_return = wp_cache_get( $cache_key, $cache_group );

		// Return cached value if it exists.
		if ( false !== $arr_return ) {
			$arr_return['cached_result'] = true;
			return $arr_return;
		}

		// Parse and prepare args.
		$args = $this->prepare_args( $args );

		/** @var string Table name for events. */
		$table_history = $simple_history->get_events_table_name();

		/** @var string Table name for contexts. */
		$table_contexts = $simple_history->get_contexts_table_name();

		/** @var array Where clauses for outer query. */
		$outer_where = [];

		/** @var array Where clause for inner query. */
		$inner_where = [];

		/** @var string Limit clause. */
		$limit = '';

		if ( 'overview' === $args['type'] || 'single' === $args['type'] ) {
			/*
			Subequent occasions query thanks to the answer Stack Overflow thread:
			http://stackoverflow.com/questions/13566303/how-to-group-subsequent-rows-based-on-a-criteria-and-then-count-them-mysql/13567320#13567320

			Similar questions that I didn't manage to understand, work, or did try:
			- http://stackoverflow.com/questions/23651176/mysql-query-if-dates-are-subsequent
			- http://stackoverflow.com/questions/17651868/mysql-group-by-subsequent
			- http://stackoverflow.com/questions/4495242/mysql-number-of-subsequent-occurrences
			- http://stackoverflow.com/questions/20446242/postgresql-group-subsequent-rows
			- http://stackoverflow.com/questions/17061156/mysql-group-by-range
			- http://stackoverflow.com/questions/6602006/complicated-query-with-group-by-and-range-of-prices-in-mysql
			*/

			// Set variables used by query.
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->query( 'SET @a:=NULL, @counter:=1, @groupby:=0' );

			/**
			 * @var string $sql_tmpl SQL template for overview or single event query.
			 *
			 * Template uses number argument to sprintf to insert values.
			 * Arguments:
			 * 1 = where clause.
			 * 2 = limit clause.
			 * 3 = table name for events.
			 * 4 = where clause for inner query.
			 * 5 = table name for contexts.
			 */
			$sql_tmpl = '
				/*NO_SELECT_FOUND_ROWS*/
				SELECT
					SQL_CALC_FOUND_ROWS
					h.id,
					h.logger,
					h.level,
					h.date,
					h.message,
					h.initiator,
					h.occasionsID,
					count(t.repeated) AS subsequentOccasions,
					t.rep,
					t.repeated,
					t.occasionsIDType,
					c1.value AS context_message_key

				FROM %3$s AS h

				LEFT OUTER JOIN %5$s AS c1 ON (c1.history_id = h.id AND c1.key = "_message_key")

				INNER JOIN (
					SELECT
						id,
						IF(@a=occasionsID,@counter:=@counter+1,@counter:=1) AS rep,
						IF(@counter=1,@groupby:=@groupby+1,@groupby) AS repeated,
						@a:=occasionsID occasionsIDType
					FROM %3$s AS h2

					# Inner where.
					%4$s

					ORDER BY id DESC, date DESC
				) AS t ON t.id = h.id

				# Outer where.
				%1$s

				GROUP BY repeated
				ORDER BY id DESC, date DESC

				# Limit clause.
				%2$s
			';

			// Only include loggers that the current user can view
			// @TODO: this causes error if user has no access to any logger at all.
			$sql_loggers_user_can_view = $simple_history->get_loggers_that_user_can_read( get_current_user_id(), 'sql' );
			$inner_where[] = "logger IN {$sql_loggers_user_can_view}";
		} elseif ( 'occasions' === $args['type'] ) {
			// Query template
			// 1 = where.
			// 2 = limit.
			// 3 = db name.
			$sql_tmpl = '
				SELECT h.*,
					# fake columns that exist in overview query
					1 as subsequentOccasions
				FROM %3$s AS h
				WHERE %1$s
				ORDER BY id DESC
				%2$s
			';

			// Get rows with id lower than logRowID, i.e. previous rows.
			$outer_where[] = 'h.id < ' . (int) $args['logRowID'];

			// Get rows with occasionsID equal to occasionsID.
			$outer_where[] = "h.occasionsID = '" . esc_sql( $args['occasionsID'] ) . "'";

			if ( isset( $args['occasionsCountMaxReturn'] ) && $args['occasionsCountMaxReturn'] < $args['occasionsCount'] ) {
				// Limit to max nn events if occasionsCountMaxReturn is set.
				// Used in gui to prevent to many events returned, that can stall the browser.
				$limit = 'LIMIT ' . $args['occasionsCountMaxReturn'];
			} else {
				// Regular limit that gets all occasions.
				$limit = 'LIMIT ' . $args['occasionsCount'];
			}
		} // End if().

		// Determine limit.
		$limit_offset = ( $args['paged'] - 1 ) * $args['posts_per_page'];
		$limit = sprintf( 'LIMIT %1$d, %2$d', $limit_offset, $args['posts_per_page'] );

		// Add post__in where.
		if ( sizeof( $args['post__in'] ) > 0 ) {
			$inner_where[] = sprintf( 'id IN (%1$s)', implode( ',', $args['post__in'] ) );
		}

		// If max_id_first_page is then then only include rows
		// with id equal to or earlier.
		if ( isset( $args['max_id_first_page'] ) ) {
			$inner_where[] = sprintf(
				'id <= %1$d',
				$args['max_id_first_page']
			);
		}

		if ( isset( $args['since_id'] ) ) {
			// Add clause to inner where because that's faster.
			$inner_where[] = sprintf(
				'id > %1$d',
				(int) $args['since_id'],
			);
		}

		// Append date where clause.
		// If date_from is set it is a timestamp.
		if ( ! empty( $args['date_from'] ) ) {
			$inner_where[] = sprintf( 'date >= "%1$s"', gmdate( 'Y-m-d H:i:s', $args['date_from'] ) );
		}

		// Date to.
		// If date_to is set it is a timestamp.
		if ( ! empty( $args['date_to'] ) ) {
			$inner_where[] = sprintf( 'date <= "%1$s"', gmdate( 'Y-m-d H:i:s', $args['date_to'] ) );
		}

		// If "months" they translate to $args["months"] because we already have support for that
		// can't use months and dates and the same time.
		if ( ! empty( $args['dates'] ) ) {
			if ( is_array( $args['dates'] ) ) {
				$arr_dates = $args['dates'];
			} else {
				$arr_dates = explode( ',', $args['dates'] );
			}

			/*
				$arr_dates can be a month:

				Array
				(
					[0] => month:2021-11
				)

				$arr_dates can be a number of days:
				Array
				(
					[0] => lastdays:7
				)

				$arr_dates can be allDates
				Array
				(
					[0] => allDates
				)
			*/

			$args['months'] = array();
			$args['lastdays'] = 0;

			foreach ( $arr_dates as $one_date ) {
				if ( strpos( $one_date, 'month:' ) === 0 ) {
					// If begins with "month:" then strip string and keep only month numbers.
					$args['months'][] = substr( $one_date, strlen( 'month:' ) );
					// If begins with "lastdays:" then strip string and keep only number of days.
				} elseif ( strpos( $one_date, 'lastdays:' ) === 0 ) {
					// Only keep largest lastdays value.
					$args['lastdays'] = max( $args['lastdays'], substr( $one_date, strlen( 'lastdays:' ) ) );
				}
			}
		}

		// Add where clause for "lastdays", as int.
		if ( ! empty( $args['lastdays'] ) ) {
			$inner_where[] = sprintf(
				'
				# lastdays
				date >= DATE(NOW()) - INTERVAL %d DAY
			',
				$args['lastdays']
			);
		}

		// months, in format "Y-m".
		if ( ! empty( $args['months'] ) ) {
			if ( is_array( $args['months'] ) ) {
				$arr_months = $args['months'];
			} else {
				$arr_months = explode( ',', $args['months'] );
			}

			$sql_months = "\n" . '
				# sql_months
				(
			';

			foreach ( $arr_months as $one_month ) {
				// beginning of month
				// $ php -r ' echo date("Y-m-d H:i", strtotime("2014-08") ) . "\n";
				// >> 2014-08-01 00:00.
				$date_month_beginning = strtotime( $one_month );

				// end of month
				// $ php -r ' echo date("Y-m-d H:i", strtotime("2014-08 + 1 month") ) . "\n";'
				// >> 2014-09-01 00:00.
				$date_month_end = strtotime( "{$one_month} + 1 month" );

				$sql_months .= sprintf(
					'
					(
						date >= "%1$s"
						AND date <= "%2$s"
					)

					OR
					',
					gmdate( 'Y-m-d H:i:s', $date_month_beginning ), // 1
					gmdate( 'Y-m-d H:i:s', $date_month_end ) // 2
				);
			}

			$sql_months = trim( $sql_months );
			$sql_months = rtrim( $sql_months, ' OR ' );

			$sql_months .= '
				# end sql_months and wrap
				)
			';

			$inner_where[] = $sql_months;
		} // End if().

		// Search.
		if ( ! empty( $args['search'] ) ) {
			$str_search_conditions = '';
			$arr_search_words = preg_split( '/[\s,]+/', $args['search'] );

			// create array of all searched words
			// split both spaces and commas and such.
			$arr_sql_like_cols = array( 'message', 'logger', 'level' );

			foreach ( $arr_sql_like_cols as $one_col ) {
				$str_sql_search_words = '';

				foreach ( $arr_search_words as $one_search_word ) {
					$str_like = esc_sql( $wpdb->esc_like( $one_search_word ) );

					$str_sql_search_words .= sprintf(
						' AND %1$s LIKE "%2$s" ',
						$one_col,
						"%{$str_like}%"
					);
				}

				$str_sql_search_words = ltrim( $str_sql_search_words, ' AND ' );

				$str_search_conditions .= "\n" . sprintf(
					'   OR ( %1$s ) ',
					$str_sql_search_words
				);
			}

			$str_search_conditions = preg_replace( '/^OR /', ' ', trim( $str_search_conditions ) );

			// Also search contexts.
			$str_search_conditions .= "\n   OR ( ";
			foreach ( $arr_search_words as $one_search_word ) {
				$str_like = esc_sql( $wpdb->esc_like( $one_search_word ) );

				$str_search_conditions .= "\n" . sprintf(
					'	id IN ( SELECT history_id FROM %1$s AS c WHERE c.value LIKE "%2$s" ) AND ',
					$table_contexts, // 1
					'%' . $str_like . '%' // 2
				);
			}
			$str_search_conditions = preg_replace( '/ AND $/', '', $str_search_conditions );

			$str_search_conditions .= "\n   ) "; // end OR for contexts.

			$inner_where[] = "\n(\n {$str_search_conditions} \n ) ";
		}// End if().

		// "loglevels", array with loglevels.
		// e.g. info, debug, and so on.
		if ( ! empty( $args['loglevels'] ) ) {
			$sql_loglevels = '';

			foreach ( $args['loglevels'] as $one_loglevel ) {
				$sql_loglevels .= sprintf( ' "%s", ', esc_sql( $one_loglevel ) );
			}

			// Remove last comma.
			$sql_loglevels = rtrim( $sql_loglevels, ' ,' );

			// Add to where in clause.
			$inner_where[] = "level IN ({$sql_loglevels})";
		}

		// messages.
		if ( ! empty( $args['messages'] ) ) {
			// Create sql where based on loggers and messages.
			$sql_messages_where = '(';

			foreach ( $args['messages'] as $logger_slug => $logger_messages ) {
				$sql_logger_messages_in = '';

				foreach ( $logger_messages as $one_logger_message ) {
					$sql_logger_messages_in .= sprintf( '"%s",', esc_sql( $one_logger_message ) );
				}

				$sql_logger_messages_in = rtrim( $sql_logger_messages_in, ' ,' );
				$sql_logger_messages_in = "\n AND c1.value IN ({$sql_logger_messages_in}) ";

				$sql_messages_where .= sprintf(
					'
					(
						h.logger = "%1$s"
						%2$s
					)
					OR ',
					esc_sql( $logger_slug ),
					$sql_logger_messages_in
				);
			}

			// Remove last 'OR '.
			$sql_messages_where = preg_replace( '/OR $/', '', $sql_messages_where );

			$sql_messages_where .= "\n )";
			$outer_where[] = $sql_messages_where;
		} // End if().

		// loggers, comma separated or array.
		// http://playground-root.ep/wp-admin/admin-ajax.php?action=simple_history_api&type=overview&format=&posts_per_page=10&paged=1&max_id_first_page=27273&SimpleHistoryLogQuery-showDebug=0&loggers=SimpleCommentsLogger,SimpleCoreUpdatesLogger.
		if ( ! empty( $args['loggers'] ) ) {
			$sql_loggers = '';

			foreach ( $args['loggers'] as $one_logger ) {
				$sql_loggers .= sprintf( ' "%s", ', esc_sql( $one_logger ) );
			}

			// Remove last comma.
			$sql_loggers = rtrim( $sql_loggers, ' ,' );

			// Add to where in clause.
			$inner_where[] = "logger IN ({$sql_loggers}) ";
		}

		// Add where for a single user ID.
		if ( isset( $args['user'] ) ) {
			$inner_where[] = sprintf(
				'id IN ( SELECT history_id FROM %1$s AS c WHERE c.key = "_user_id" AND c.value = %2$s )',
				$table_contexts, // 1
				$args['user'], // 2
			);
		}

		// Users, array with user ids.
		if ( isset( $args['users'] ) ) {
			$inner_where[] = sprintf(
				'id IN ( SELECT history_id FROM %1$s AS c WHERE c.key = "_user_id" AND c.value IN (%2$s) )',
				$table_contexts, // 1
				implode( ',', $args['users'] ), // 2
			);
		}

		/**
		 * Filter the sql template
		 *
		 * @since 2.0
		 *
		 * @param string $sql_tmpl
		 */
		$sql_tmpl = apply_filters( 'simple_history/log_query_sql_template', $sql_tmpl );

		// Create where string.
		$outer_where_array = $outer_where;
		$outer_where = implode( "\nAND ", $outer_where );

		// Append where to sql template.
		if ( ! empty( $outer_where ) ) {
			$outer_where = "\nWHERE {$outer_where}";
		}

		/**
		 * Filter the sql template where clause
		 *
		 * @since 2.0
		 *
		 * @param string $where
		 */
		$outer_where = apply_filters( 'simple_history/log_query_sql_where', $outer_where );

		/**
		 * Filter the sql template limit
		 *
		 * @since 2.0
		 *
		 * @param string $limit
		 */
		$limit = apply_filters( 'simple_history/log_query_limit', $limit );

		// Create where string.
		$inner_where_array = $inner_where;
		$inner_where = implode( "\nAND ", $inner_where );

		// Append where to sql template.
		if ( ! empty( $inner_where ) ) {
			$inner_where = "\nWHERE {$inner_where}";
		}

		/**
		 * Filter the sql template limit
		 *
		 * @since 2.0
		 *
		 * @param string $limit
		 */
		$inner_where = apply_filters( 'simple_history/log_query_inner_where', $inner_where );

		$sql = sprintf(
			$sql_tmpl, // sprintf template.
			$outer_where,  // 1
			$limit, // 2
			$table_history, // 3
			$inner_where, // 4
			$table_contexts // 5
		);

		/**
		 * Filter the final sql query
		 *
		 * @since 2.0
		 *
		 * @param string $sql
		 */
		$sql = apply_filters( 'simple_history/log_query_sql', $sql );

		/** @var array<string,object> */
		$log_rows = $wpdb->get_results( $sql, OBJECT_K ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		// Find total number of rows that we would have gotten without pagination
		// This is the number of rows with occasions taken into consideration.
		$sql_found_rows = 'SELECT FOUND_ROWS()';
		$total_found_rows = (int) $wpdb->get_var( $sql_found_rows ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		// Add context.
		$post_ids = wp_list_pluck( $log_rows, 'id' );

		if ( empty( $post_ids ) ) {
			$context_results = array();
		} else {
			$sql_context = sprintf( 'SELECT * FROM %2$s WHERE history_id IN (%1$s)', join( ',', $post_ids ), $table_contexts );
			$context_results = $wpdb->get_results( $sql_context ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		foreach ( $context_results as $context_row ) {
			if ( ! isset( $log_rows[ $context_row->history_id ]->context ) ) {
				$log_rows[ $context_row->history_id ]->context = array();
			}

			$log_rows[ $context_row->history_id ]->context[ $context_row->key ] = $context_row->value;
		}

		// Remove id from keys, because they are cumbersome when working with JSON.
		$log_rows = array_values( $log_rows );

		/** @var null|int */
		$min_id = null;

		/** @var null|int */
		$max_id = null;

		if ( count( $log_rows ) ) {
			// Max id is simply the id of the first row.
			$max_id = reset( $log_rows )->id;

			// Min id = to find the lowest id we must take occasions into consideration.
			$last_row = end( $log_rows );

			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$last_row_occasions_count = (int) $last_row->subsequentOccasions - 1;

			if ( $last_row_occasions_count === 0 ) {
				// Last row did not have any more occasions, so get min_id directly from the row.
				$min_id = $last_row->id;
			} else {
				// Last row did have occasions, so fetch all occasions, and find id of last one.
				$db_table = $simple_history->get_events_table_name();
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

				$results = $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

				// the last occasion has the id we consider last in this paged result.
				$min_id = end( $results )->id;
			}
		} // End if().

		// Calc pages.
		$pages_count = Ceil( $total_found_rows / $args['posts_per_page'] );

		// Create array to return.
		// Make all rows a sub key because we want to add some meta info too.
		$log_rows_count = count( $log_rows );
		$page_rows_from = ( $args['paged'] * $args['posts_per_page'] ) - $args['posts_per_page'] + 1;
		$page_rows_to = $page_rows_from + $log_rows_count - 1;
		$arr_return = [
			'total_row_count' => $total_found_rows,
			'pages_count' => $pages_count,
			'page_current' => $args['paged'],
			'page_rows_from' => $page_rows_from,
			'page_rows_to' => $page_rows_to,
			'max_id' => (int) $max_id,
			'min_id' => (int) $min_id,
			'log_rows_count' => $log_rows_count,
			'log_rows' => $log_rows,
			// Add sql query to debug.
			// 'sql' => $sql, // .
			'outer_where_array' => $outer_where_array,
			'inner_where_array' => $inner_where_array,
		];

		wp_cache_set( $cache_key, $arr_return, $cache_group );

		return $arr_return;
	}

	/**
	 * Prepare arguments, i.e. checking that they are valid,
	 * of the correct type, etc.
	 *
	 * @param array $args Argument.
	 * @return array
	 * @throws \InvalidArgumentException If invalid type.
	 */
	protected function prepare_args( $args ) {
		// Type must be string and any of "overview", "occasions", "single".
		if ( ! is_string( $args['type'] ) && ! in_array( $args['type'], [ 'overview', 'occasions', 'single' ], true ) ) {
			throw new \InvalidArgumentException( 'Invalid type' );
		}

		// If occasionsCountMaxReturn is set then it must be an integer.
		if ( isset( $args['occasionsCountMaxReturn'] ) && ! is_numeric( $args['occasionsCountMaxReturn'] ) ) {
			throw new \InvalidArgumentException( 'Invalid occasionsCountMaxReturn' );
		} elseif ( isset( $args['occasionsCountMaxReturn'] ) ) {
			$args['occasionsCountMaxReturn'] = (int) $args['occasionsCountMaxReturn'];
		}

		// If occasionsCount is set then it must be an integer.
		if ( isset( $args['occasionsCount'] ) && ! is_numeric( $args['occasionsCount'] ) ) {
			throw new \InvalidArgumentException( 'Invalid occasionsCount' );
		} elseif ( isset( $args['occasionsCount'] ) ) {
			$args['occasionsCount'] = (int) $args['occasionsCount'];
		}

		// posts_per_page must be set and must be a positive integer.
		if ( ! isset( $args['posts_per_page'] ) || ! is_numeric( $args['posts_per_page'] ) && $args['posts_per_page'] < 1 ) {
			throw new \InvalidArgumentException( 'Invalid posts_per_page' );
		} else {
			$args['posts_per_page'] = (int) $args['posts_per_page'];
		}

		// paged must be set and must be a positive integer.
		if ( ! isset( $args['paged'] ) || ! is_numeric( $args['paged'] ) || $args['paged'] < 1 ) {
			throw new \InvalidArgumentException( 'Invalid paged' );
		} else {
			$args['paged'] = (int) $args['paged'];
		}

		// "post__in" must be array and must only contain integers.
		if ( isset( $args['post__in'] ) && ! is_array( $args['post__in'] ) ) {
			throw new \InvalidArgumentException( 'Invalid post__in' );
		} elseif ( isset( $args['post__in'] ) ) {
			$args['post__in'] = array_map( 'intval', $args['post__in'] );
			$args['post__in'] = array_filter( $args['post__in'] );
		}

		// "max_id_first_page" must be integer.
		if ( isset( $args['max_id_first_page'] ) && ! is_numeric( $args['max_id_first_page'] ) ) {
			throw new \InvalidArgumentException( 'Invalid max_id_first_page' );
		} elseif ( isset( $args['max_id_first_page'] ) ) {
			$args['max_id_first_page'] = (int) $args['max_id_first_page'];
		}

		// "since_id" must be integer.
		if ( isset( $args['since_id'] ) && ! is_numeric( $args['since_id'] ) ) {
			throw new \InvalidArgumentException( 'Invalid since_id' );
		} elseif ( isset( $args['since_id'] ) ) {
			$args['since_id'] = (int) $args['since_id'];
		}

		// "date_from" must be timestamp or string. If string then convert to timestamp.
		if ( isset( $args['date_from'] ) && ! is_numeric( $args['date_from'] ) ) {
			$args['date_from'] = strtotime( $args['date_from'] );
		} elseif ( isset( $args['date_from'] ) && is_numeric( $args['date_from'] ) ) {
			$args['date_from'] = (int) $args['date_from'];
		} elseif ( isset( $args['date_from'] ) && is_string( $args['date_from'] ) ) {
			$args['date_from'] = (int) $args['date_from'];
		} elseif ( isset( $args['date_from'] ) ) {
			throw new \InvalidArgumentException( 'Invalid date_from' );
		}

		// "date_to" must be timestamp or string. If string then convert to timestamp.
		if ( isset( $args['date_to'] ) && ! is_numeric( $args['date_to'] ) ) {
			$args['date_to'] = strtotime( $args['date_to'] );
		} elseif ( isset( $args['date_to'] ) && is_string( $args['date_to'] ) ) {
			$args['date_to'] = (int) $args['date_to'];
		} elseif ( isset( $args['date_to'] ) ) {
			throw new \InvalidArgumentException( 'Invalid date_to' );
		}

		// "search" must be string.
		if ( isset( $args['search'] ) && ! is_string( $args['search'] ) ) {
			throw new \InvalidArgumentException( 'Invalid search' );
		}

		// "loglevels" must be comma separeated string "info,debug"
		// or array of log level strings.
		if ( isset( $args['loglevels'] ) && ! is_string( $args['loglevels'] ) && ! is_array( $args['loglevels'] ) ) {
			throw new \InvalidArgumentException( 'Invalid loglevels' );
		} elseif ( isset( $args['loglevels'] ) && is_string( $args['loglevels'] ) ) {
			$args['loglevels'] = explode( ',', $args['loglevels'] );
		}

		// Make sure loglevels are trimed, strings, and empty vals removed.
		if ( isset( $args['loglevels'] ) ) {
			$args['loglevels'] = array_map( 'trim', $args['loglevels'] );
			$args['loglevels'] = array_map( 'strval', $args['loglevels'] );
			$args['loglevels'] = array_filter( $args['loglevels'] );
		}

		// "messages" is string with comma separated loggers and messages,
		// or array with comma separated loggers and messages.
		// Array example:
		// Array
		// (
		// [0] => SimpleCommentsLogger:anon_comment_added,SimpleCommentsLogger:user_comment_added,SimpleCommentsLogger:anon_trackback_added,SimpleCommentsLogger:user_trackback_added,SimpleCommentsLogger:anon_pingback_added,SimpleCommentsLogger:user_pingback_added,SimpleCommentsLogger:comment_edited,SimpleCommentsLogger:trackback_edited,SimpleCommentsLogger:pingback_edited,SimpleCommentsLogger:comment_status_approve,SimpleCommentsLogger:trackback_status_approve,SimpleCommentsLogger:pingback_status_approve,SimpleCommentsLogger:comment_status_hold,SimpleCommentsLogger:trackback_status_hold,SimpleCommentsLogger:pingback_status_hold,SimpleCommentsLogger:comment_status_spam,SimpleCommentsLogger:trackback_status_spam,SimpleCommentsLogger:pingback_status_spam,SimpleCommentsLogger:comment_status_trash,SimpleCommentsLogger:trackback_status_trash,SimpleCommentsLogger:pingback_status_trash,SimpleCommentsLogger:comment_untrashed,SimpleCommentsLogger:trackback_untrashed,SimpleCommentsLogger:pingback_untrashed,SimpleCommentsLogger:comment_deleted,SimpleCommentsLogger:trackback_deleted,SimpleCommentsLogger:pingback_deleted
		// [1] => SimpleCommentsLogger:SimpleCommentsLogger:comment_status_spam,SimpleCommentsLogger:trackback_status_spam,SimpleCommentsLogger:pingback_status_spam
		// )
		if ( isset( $args['messages'] ) && ! is_string( $args['messages'] ) && ! is_array( $args['messages'] ) ) {
			throw new \InvalidArgumentException( 'Invalid messages' );
		} elseif ( isset( $args['messages'] ) && is_string( $args['messages'] ) ) {
			$args['messages'] = explode( ',', $args['messages'] );
		} elseif ( isset( $args['messages'] ) && is_array( $args['messages'] ) ) {
			// Turn multi dimensional array into single array with strings.
			$arr_messages = [];
			foreach ( $args['messages'] as $one_arr_messages_row ) {
				$arr_messages = array_merge( $arr_messages, explode( ',', $one_arr_messages_row ) );
			}

			$args['messages'] = $arr_messages;
		}

		// Make sure messages are trimed, strings, and empty vals removed.
		if ( isset( $args['messages'] ) ) {
			$args['messages'] = array_map( 'trim', $args['messages'] );
			$args['messages'] = array_map( 'strval', $args['messages'] );
			$args['messages'] = array_filter( $args['messages'] );

			$arr_loggers_and_messages = [];

			// Transform to format where
			// - key = logger slug.
			// - value = array of logger messages..
			foreach ( $args['messages'] as $one_row_logger_and_message ) {
				$arr_one_logger_and_message = explode( ':', $one_row_logger_and_message );

				if ( ! isset( $arr_loggers_and_messages[ $arr_one_logger_and_message[0] ] ) ) {
					$arr_loggers_and_messages[ $arr_one_logger_and_message[0] ] = array();
				}

				$arr_loggers_and_messages[ $arr_one_logger_and_message[0] ][] = $arr_one_logger_and_message[1];
			}

			$args['messages'] = $arr_loggers_and_messages;
		}

		// "loggers", comma separated string or array with strings.
		// Example format: "AvailableUpdatesLogger,SimpleuserLogger".
		if ( isset( $args['loggers'] ) && ! is_string( $args['loggers'] ) && ! is_array( $args['loggers'] ) ) {
			throw new \InvalidArgumentException( 'Invalid loggers' );
		} elseif ( isset( $args['loggers'] ) && is_string( $args['loggers'] ) ) {
			$args['loggers'] = explode( ',', $args['loggers'] );
		}

		// "user" must be integer.
		if ( isset( $args['user'] ) && ! is_numeric( $args['user'] ) ) {
			throw new \InvalidArgumentException( 'Invalid user' );
		} elseif ( isset( $args['user'] ) ) {
			$args['user'] = (int) $args['user'];
		}

		// "users" must be comma separated string or array with integers.
		if ( isset( $args['users'] ) && ! is_string( $args['users'] ) && ! is_array( $args['users'] ) ) {
			throw new \InvalidArgumentException( 'Invalid users' );
		} elseif ( isset( $args['users'] ) && is_string( $args['users'] ) ) {
			$args['users'] = explode( ',', $args['users'] );
		}

		// Make sure users are integers and remove empty vals.
		if ( isset( $args['users'] ) ) {
			$args['users'] = array_map( 'intval', $args['users'] );
			$args['users'] = array_filter( $args['users'] );
		}

		return $args;
	}
}
