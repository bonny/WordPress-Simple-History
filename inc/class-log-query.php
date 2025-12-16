<?php

namespace Simple_History;

use Simple_History\Helpers;
use Simple_History\Date_Helper;
use Simple_History\Services;

/**
 * Queries the Simple History Log.
 *
 * @example Basic positive filtering (inclusion).
 * ```php
 * $log_query = new \Simple_History\Log_Query();
 *
 * // Get only info and warning level events
 * $results = $log_query->query([
 *     'posts_per_page' => 50,
 *     'loglevels' => ['info', 'warning'],
 * ]);
 *
 * // Search for events containing "updated"
 * $results = $log_query->query([
 *     'search' => 'updated',
 * ]);
 * ```
 *
 * @example Basic negative filtering (exclusion).
 * ```php
 * // Exclude debug level events
 * $results = $log_query->query([
 *     'exclude_loglevels' => ['debug'],
 * ]);
 *
 * // Exclude events containing "cron"
 * $results = $log_query->query([
 *     'exclude_search' => 'cron',
 * ]);
 *
 * // Exclude WP-Cron events
 * $results = $log_query->query([
 *     'exclude_initiator' => 'wp_cron',
 * ]);
 * ```
 *
 * @example Combining positive and negative filters.
 * ```php
 * // Get info events, but exclude those containing "cron"
 * $results = $log_query->query([
 *     'loglevels' => ['info'],
 *     'exclude_search' => 'cron',
 * ]);
 *
 * // Important events only (no debug, no cron jobs)
 * $results = $log_query->query([
 *     'exclude_loglevels' => ['debug'],
 *     'exclude_initiator' => ['wp_cron', 'wp_cli'],
 * ]);
 * ```
 *
 * @example Conflict resolution: exclusion takes precedence.
 * ```php
 * // When same value in both filters, exclusion wins
 * $results = $log_query->query([
 *     'loggers' => ['SimplePluginLogger', 'SimpleUserLogger'],
 *     'exclude_loggers' => ['SimpleUserLogger'],
 * ]);
 * // Result: Only SimplePluginLogger events
 * ```
 *
 * @example Surrounding events (show events before and after a specific event).
 * ```php
 * // Get 5 events before and 5 events after event ID 123 (11 total).
 * // This is useful for debugging to see what happened around a specific event.
 * // Note: This bypasses logger permissions and shows raw chronological events.
 * $results = $log_query->query([
 *     'surrounding_event_id' => 123,
 *     'surrounding_count' => 5,
 * ]);
 * // Result includes 'center_event_id' in the return array to identify the target event.
 * ```
 *
 * @see Documentation: docs/filters-usage-examples.md
 */
class Log_Query {
	/**
	 * Query the log.
	 *
	 * @param string|array|object $args {
	 *    Optional. Array or string of arguments for querying the log.
	 *
	 *    Pagination and Result Type.
	 *
	 *      @type string $type Type of query. Accepts 'overview', 'occasions', or 'single'. Default 'overview'.
	 *      @type int $posts_per_page Number of posts to show per page. Default is 10.
	 *      @type int $paged Page to show. 1 = first page. Default 1.
	 *      @type array $post__in Array. Only get posts that are in array. Default null.
	 *      @type int $max_id_first_page If max_id_first_page is set then only get rows that have id equal or lower than this, to make
	 *                                      sure that the first page of results is not too large. Default null.
	 *      @type int $since_id If since_id is set the rows returned will only be rows with an ID greater than (i.e. more recent than) since_id. Default null.
	 *
	 *    Date Filters.
	 *
	 *      @type int|string $date_from From date, as unix timestamp integer or as a format compatible with strtotime, for example 'Y-m-d H:i:s'. Default null.
	 *      @type int|string $date_to To date, as unix timestamp integer or as a format compatible with strtotime, for example 'Y-m-d H:i:s'. Default null.
	 *      @type array|string $months Months in format "Y-m". Default null.
	 *      @type array|string $dates Dates in format "month:2015-06" for june 2015 or "lastdays:7" for the last 7 days. Default null.
	 *
	 *    Inclusion Filters (what to show).
	 *
	 *      @type string $search Text to search for. Message, logger and level are searched for in main table. Values are searched for in context table. Default null.
	 *      @type string|array $loglevels Log levels to include. Comma separated string or array. Defaults to all. Default null.
	 *      @type string|array $loggers Loggers to include. Comma separated string or array. Default null = all the user can read.
	 *      @type string|array $messages Messages to include. Array or string with comma separated in format "LoggerSlug:Message", e.g. "SimplePluginLogger:plugin_activated,SimplePluginLogger:plugin_deactivated". Default null = show all messages.
	 *      @type int $user Single user ID as number. Default null.
	 *      @type string|array $users User IDs, comma separated string or array. Default null.
	 *      @type string|array $initiator Initiator to filter by. Single string or array of initiators. Default null.
	 *
	 *    Exclusion Filters (what to hide).
	 *      When both inclusion and exclusion filters are specified for the same field, exclusion takes precedence.
	 *
	 *      @type string $exclude_search Text to exclude. Events containing these words will be hidden. Default null.
	 *      @type string|array $exclude_loglevels Log levels to exclude. Comma separated string or array. Default null.
	 *      @type string|array $exclude_loggers Loggers to exclude. Comma separated string or array. Default null.
	 *      @type string|array $exclude_messages Messages to exclude. Array or string with comma separated in format "LoggerSlug:Message". Default null.
	 *      @type int $exclude_user Single user ID to exclude. Default null.
	 *      @type string|array $exclude_users User IDs to exclude, comma separated string or array. Default null.
	 *      @type string|array $exclude_initiator Initiator(s) to exclude. Single string or array of initiators. Default null.
	 *
	 *    Other Options.
	 *
	 *      @type boolean $include_sticky Include sticky events in the result set. Default false.
	 *      @type boolean $only_sticky Only return sticky events. Default false.
	 *      @type array $context_filters Context filters as key-value pairs. Default null.
	 *      @type boolean $ungrouped Return ungrouped events without occasions grouping. Default false.
	 *
	 *    Surrounding Events (Admin Only - bypasses logger permissions).
	 *
	 *      @type int $surrounding_event_id The center event ID to get surrounding events for. When set, returns events
	 *                                       chronologically before and after this event, ignoring all other filters.
	 *      @type int $surrounding_count Number of events to return before AND after the center event. Default 5.
	 *                                    Total events returned = surrounding_count * 2 + 1 (before + center + after).
	 * }
	 * @return array|\WP_Error Query results or WP_Error on database error.
	 * @throws \InvalidArgumentException If invalid query type.
	 */
	public function query( $args = [] ) {
		$args = wp_parse_args( $args );

		// Check for surrounding events query (special mode that bypasses normal filtering).
		if ( isset( $args['surrounding_event_id'] ) ) {
			return $this->query_surrounding_events( $args );
		}

		// Determine kind of query.
		$type = $args['type'] ?? 'overview';

		if ( $type === 'overview' || $type === 'single' ) {
			$result = $this->query_overview( $args );
		} elseif ( $type === 'occasions' ) {
			$result = $this->query_occasions( $args );
		} else {
			throw new \InvalidArgumentException( 'Invalid query type' );
		}

		// Auto-recover from missing tables.
		if ( is_wp_error( $result ) ) {
			$db_error = $result->get_error_data( 'simple_history_db_error' )['db_error'] ?? '';

			if ( Services\Setup_Database::is_table_missing_error( $db_error ) ) {
				// Try to recreate tables.
				$recreated = Services\Setup_Database::recreate_tables_if_missing();

				if ( $recreated ) {
					// Retry the query after recreating tables.
					if ( $type === 'overview' || $type === 'single' ) {
						$result = $this->query_overview( $args );
					} elseif ( $type === 'occasions' ) {
						$result = $this->query_occasions( $args );
					}
				}
			}
		}

		return $result;
	}

	/**
	 * Query history using a query that uses full group by,
	 * making it compatible with both MySQL 5.5, 5.7 and MariaDB.
	 *
	 * Subsequent occasions query thanks to the answer Stack Overflow thread:
	 * http://stackoverflow.com/questions/13566303/how-to-group-subsequent-rows-based-on-a-criteria-and-then-count-them-mysql/13567320#13567320
	 *
	 * @param string|array|object $args Arguments.
	 * @return array|\WP_Error Log rows or WP_Error on database error.
	 * @throws \ErrorException If invalid DB engine.
	 */
	public function query_overview( $args ) {
		// Force simple query for ungrouped results.
		if ( ! empty( $args['ungrouped'] ) ) {
			return $this->query_overview_simple( $args );
		}

		$db_engine = $this->get_db_engine();

		if ( $db_engine === 'mysql' ) {
			// Call usual method.
			return $this->query_overview_mysql( $args );
		} elseif ( $db_engine === 'sqlite' ) {
			// Call sqlite method.
			return $this->query_overview_simple( $args );
		} else {
			throw new \ErrorException( 'Invalid DB engine' );
		}
	}

	/**
	 * Simplified version of query_overview_mysql() that returns ungrouped events.
	 * This query does not group events by occasions, returning each event individually.
	 * Originally created for SQLite compatibility but useful for any ungrouped display.
	 *
	 * @param string|array|object $args Arguments.
	 * @return array|\WP_Error Log rows or WP_Error on database error.
	 * @throws \Exception If error when performing query.
	 */
	protected function query_overview_simple( $args ) {
		$args = $this->prepare_args( $args );

		// Create cache key based on args and current user.
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
		$cache_key   = md5( __METHOD__ . serialize( $args ) ) . '_userid_' . get_current_user_id();
		$cache_group = Helpers::get_cache_group();

		/** @var array|false Return value. */
		$arr_return = wp_cache_get( $cache_key, $cache_group );

		// Return cached value if it exists.
		if ( false !== $arr_return ) {
			$arr_return['cached_result'] = true;
			return $arr_return;
		}

		global $wpdb;

		$Simple_History = Simple_History::get_instance();

		/**
		 * @var string SQL template used to get all events from the ones
		 *             found in statement sql_statement_max_ids_and_count_template.
		 *             This final statement gets all columns we finally need.
		 */
		$sql_statement_log_rows = '
			SELECT
				simple_history_1.id,
				simple_history_1.logger,
				simple_history_1.level,
				simple_history_1.date,
				simple_history_1.message,
				simple_history_1.initiator,
				simple_history_1.occasionsID,
				1 AS repeatCount,
				1 AS subsequentOccasions
			FROM %1$s AS simple_history_1
			%2$s
			ORDER BY simple_history_1.date DESC, simple_history_1.id DESC
			%3$s
		';

		$inner_where_array  = $this->get_inner_where( $args );
		$inner_where_string = empty( $inner_where_array ) ? '' : "\nWHERE " . implode( "\nAND ", $inner_where_array );

		/** @var int $limit_offset */
		$limit_offset = ( $args['paged'] - 1 ) * $args['posts_per_page'];

		/** @var string Limit clause. */
		$limit_clause = sprintf( 'LIMIT %1$d, %2$d', $limit_offset, $args['posts_per_page'] );

		$sql_query_log_rows = sprintf(
			$sql_statement_log_rows,
			$Simple_History->get_events_table_name(), // 1
			$inner_where_string, // 2
			$limit_clause // 3
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
		$result_log_rows = $wpdb->get_results( $sql_query_log_rows, OBJECT_K );

		if ( ! empty( $wpdb->last_error ) ) {
			return new \WP_Error(
				'simple_history_db_error',
				__( 'Database query failed.', 'simple-history' ),
				array( 'db_error' => $wpdb->last_error )
			);
		}

		// Append context to log rows.
		$result_log_rows = $this->add_contexts_to_log_rows( $result_log_rows );

		// Re-index array.
		$result_log_rows = array_values( $result_log_rows );

		// Like $sql_statement_log_rows but all columns is replaced by a single COUNT(*).
		$sql_statement_log_rows_count = '
			SELECT count(*) as count
			FROM %1$s AS simple_history_1
			%2$s
			ORDER BY simple_history_1.date DESC, simple_history_1.id DESC
		';

		$sql_query_log_rows_count = sprintf(
			$sql_statement_log_rows_count,
			$Simple_History->get_events_table_name(), // 1
			$inner_where_string, // 2
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$total_found_rows = $wpdb->get_var( $sql_query_log_rows_count );

		// Calc pages.
		$pages_count = Ceil( $total_found_rows / $args['posts_per_page'] );

		// Calc pagination info.
		$log_rows_count = count( $result_log_rows );
		$page_rows_from = ( $args['paged'] * $args['posts_per_page'] ) - $args['posts_per_page'] + 1;
		$page_rows_to   = $page_rows_from + $log_rows_count - 1;

		// Get maxId, minId, and maxDate.
		// MaxId is the id of the first row in the result (i.e. the latest entry).
		// MinId is the id of the last row in the result (i.e. the oldest entry).
		// MaxDate is the date of the first row (for accurate new event detection with date ordering).
		$min_id   = null;
		$max_id   = null;
		$max_date = null;
		if ( sizeof( $result_log_rows ) > 0 ) {
			$max_id   = $result_log_rows[0]->id;
			$min_id   = $result_log_rows[ count( $result_log_rows ) - 1 ]->id;
			$max_date = $result_log_rows[0]->date;
		}

		// Create array to return.
		// Add log rows to sub key 'log_rows' because meta info is also added.
		$arr_return = [
			'total_row_count' => (int) $total_found_rows,
			'pages_count'     => $pages_count,
			'page_current'    => $args['paged'],
			'page_rows_from'  => $page_rows_from,
			'page_rows_to'    => $page_rows_to,
			'max_id'          => (int) $max_id,
			'min_id'          => (int) $min_id,
			'max_date'        => $max_date,
			'log_rows_count'  => $log_rows_count,
			'log_rows'        => $result_log_rows,
		];

		wp_cache_set( $cache_key, $arr_return, $cache_group );

		return $arr_return;
	}

	/**
	 * @param string|array|object $args Arguments.
	 * @return array|\WP_Error Log rows or WP_Error on database error.
	 * @throws \Exception If error when performing query.
	 */
	protected function query_overview_mysql( $args ) {
		// Parse and prepare args.
		$args = $this->prepare_args( $args );

		// Create cache key based on args and current user.
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
		$cache_key   = md5( __METHOD__ . serialize( $args ) ) . '_userid_' . get_current_user_id();
		$cache_group = Helpers::get_cache_group();

		/** @var array|false Return value. */
		$arr_return = wp_cache_get( $cache_key, $cache_group );

		// Return cached value if it exists.
		if ( false !== $arr_return ) {
			$arr_return['cached_result'] = true;
			return $arr_return;
		}

		global $wpdb;

		$Simple_History = Simple_History::get_instance();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( 'SET @a:=NULL, @counter:=1, @groupby:=0, SQL_BIG_SELECTS=1' );

		/**
		 *  @var string SQL statement that will be used for inner join.
		 *
		 * Template uses number argument to sprintf to insert values.
		 * Arguments:
		 * 1 = table name for events.
		 * 2 = table name for contexts.
		 * 2 = where clause.
		 *
		 * TODO: Add where for messages. Check that both logger and key are correct.
		 */
		$inner_sql_statement_template = '

			## START INNER_SQL_QUERY_STATEMENT
			SELECT
				id,
				#message,
				IF(@a=occasionsID,@counter:=@counter+1,@counter:=1) AS repeatCount,
				IF(@counter=1,@groupby:=@groupby+1,@groupby) AS repeated,
				@a:=occasionsId,
				contexts.value as context_message_key
			FROM %1$s AS h2

			# Join column with message key so its searchable/filterable.
			LEFT OUTER JOIN %2$s AS contexts ON (contexts.history_id = h2.id AND contexts.key = \'_message_key\')

			# Where statement.
			%3$s

			ORDER BY date DESC, id DESC
			## END INNER_SQL_QUERY_STATEMENT

		';

		$inner_where_array  = $this->get_inner_where( $args );
		$inner_where_string = empty( $inner_where_array ) ? '' : "\nWHERE " . implode( "\nAND ", $inner_where_array );

		$inner_sql_query_statement = sprintf(
			$inner_sql_statement_template,
			$Simple_History->get_events_table_name(), // 1
			$Simple_History->get_contexts_table_name(), // 2
			$inner_where_string // 3
		);

		/**
		 * @var string SQL statement template used to get IDs of all events.
		 *
		 * Template uses number argument to sprintf to insert values.
		 * Arguments:
		 * 1 = table name for events.
		 * 2 = table name for contexts.
		 * 3 = Inner join SQL query.
		 * 4 = where clause for outer query.
		 * 5 = limit clause.
		 */
		$sql_statement_max_ids_and_count_template = '

			## START SQL_STATEMENT_MAX_IDS_AND_COUNT_TEMPLATE
			SELECT 
				max(h.id) as maxId,
				min(h.id) as minId,
				max(historyWithRepeated.repeatCount) as repeatCount,
			max(h.date) as maxDate
			FROM %1$s AS h

			INNER JOIN (
				%3$s
			) as historyWithRepeated ON historyWithRepeated.id = h.id

			# Outer where
			%4$s
			
			GROUP BY historyWithRepeated.repeated
			ORDER by maxDate DESC, maxId DESC

			# Limit
			%5$s

			## END SQL_STATEMENT_MAX_IDS_AND_COUNT_TEMPLATE
		';

		/** @var string Inner where clause, including "where" if has values. */
		$inner_where_string = '';

		$inner_where_array = $this->get_inner_where( $args );
		if ( ! empty( $inner_where_array ) ) {
			$inner_where_string = "\nWHERE\n" . implode( "\nAND ", $inner_where_array );
		}

		/** @var string Outer where clause, including "where" if has values. */
		$outer_where_string = '';

		$outer_where_array = $this->get_outer_where( $args );
		if ( ! empty( $outer_where_array ) ) {
			$outer_where_string = "\nWHERE " . implode( "\nAND ", $outer_where_array );
		}

		/** @var int $limit_offset */
		$limit_offset = ( $args['paged'] - 1 ) * $args['posts_per_page'];

		/** @var string Limit clause. */
		$limit_clause = sprintf( 'LIMIT %1$d, %2$d', $limit_offset, $args['posts_per_page'] );

		$max_ids_and_count_sql_statement = sprintf(
			$sql_statement_max_ids_and_count_template,
			$Simple_History->get_events_table_name(), // 1
			$Simple_History->get_contexts_table_name(), // 2
			$inner_sql_query_statement, // 3
			$outer_where_string, // 4
			$limit_clause // 5 Limit clause.
		);

		/**
		 * @var string SQL template used to get all events from the ones
		 *             found in statement sql_statement_max_ids_and_count_template.
		 *             This final statement gets all columns we finally need.
		 */
		$sql_statement_log_rows = '

			## START SQL_STATEMENT_LOG_ROWS
			SELECT
				simple_history_1.id,
				maxId,
				minId,
				simple_history_1.logger,
				simple_history_1.level,
				simple_history_1.date,
				simple_history_1.message,
				simple_history_1.initiator,
				simple_history_1.occasionsID,
				repeatCount,
				repeatCount AS subsequentOccasions

			FROM %1$s AS simple_history_1

			INNER JOIN (
				%2$s
			) AS max_ids_and_count ON simple_history_1.id = max_ids_and_count.maxId

			ORDER BY simple_history_1.date DESC, simple_history_1.id DESC
			## END SQL_STATEMENT_LOG_ROWS

		';

		$sql_query_log_rows = sprintf(
			$sql_statement_log_rows,
			$Simple_History->get_events_table_name(), // 1
			$max_ids_and_count_sql_statement // 2
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$result_log_rows = $wpdb->get_results( $sql_query_log_rows, OBJECT_K );

		if ( ! empty( $wpdb->last_error ) ) {
			return new \WP_Error(
				'simple_history_db_error',
				__( 'Database query failed.', 'simple-history' ),
				array( 'db_error' => $wpdb->last_error )
			);
		}

		// Append context to log rows.
		$result_log_rows = $this->add_contexts_to_log_rows( $result_log_rows );

		// Re-index array.
		$result_log_rows = array_values( $result_log_rows );

		// Get max id, min id, and max date.
		// Max id is the id of the first row in the result (i.e. the latest entry).
		// Min id is the minId value of the last row in the result (i.e. the oldest entry).
		// Max date is the date of the first row (for accurate new event detection with date ordering).
		$min_id   = null;
		$max_id   = null;
		$max_date = null;
		if ( sizeof( $result_log_rows ) > 0 ) {
			$max_id   = $result_log_rows[0]->id;
			$min_id   = $result_log_rows[ count( $result_log_rows ) - 1 ]->minId;
			$max_date = $result_log_rows[0]->date;
		}

		// Like $sql_statement_log_rows but all columns is replaced by a single COUNT(*).
		$sql_statement_log_rows_count = '
			## START SQL_STATEMENT_LOG_ROWS
			SELECT
				count(*) as count

			FROM %1$s AS simple_history_1

			INNER JOIN (
				%2$s
			) AS max_ids_and_count ON simple_history_1.id = max_ids_and_count.maxId

			ORDER BY simple_history_1.date DESC, simple_history_1.id DESC
			## END SQL_STATEMENT_LOG_ROWS
		';

		// Create $max_ids_and_count_sql_statement without limit,
		// to get count(*).
		$max_ids_and_count_without_limit_sql_statement = sprintf(
			$sql_statement_max_ids_and_count_template,
			$Simple_History->get_events_table_name(), // 1
			$Simple_History->get_contexts_table_name(), // 2
			$inner_sql_query_statement, // 3
			$outer_where_string, // 4
			'', // 5 Limit clause.
		);

		$sql_query_log_rows_count = sprintf(
			$sql_statement_log_rows_count,
			$Simple_History->get_events_table_name(), // 1
			$max_ids_and_count_without_limit_sql_statement // 2
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$total_found_rows = $wpdb->get_var( $sql_query_log_rows_count );

		// Calc pages.
		$pages_count = Ceil( $total_found_rows / $args['posts_per_page'] );

		// Calc pagination info.
		$log_rows_count = count( $result_log_rows );
		$page_rows_from = ( $args['paged'] * $args['posts_per_page'] ) - $args['posts_per_page'] + 1;
		$page_rows_to   = $page_rows_from + $log_rows_count - 1;

		// Prepend sticky events to the result.
		// Sticky events are added first in the result set and does not
		// count towards the total found rows or modify pagination, etc.
		if ( $args['include_sticky'] ) {
			$sticky_events = $this->get_sticky_events();

			if ( ! empty( $sticky_events ) ) {
				$query_sticky_events = $this->query(
					[
						'post__in' => $sticky_events,
					]
				);

				$sticky_log_rows = $query_sticky_events['log_rows'];

				// Append sticky_appended=true to each event,
				// so we on client side can differentiate between sticky events and other events.
				$sticky_log_rows = array_map(
					function ( $log_row ) {
						$log_row->sticky_appended = true;

						return $log_row;
					},
					$sticky_log_rows
				);

				// Prepend sticky events to the result, at the top.
				$result_log_rows = array_merge( $sticky_log_rows, $result_log_rows );
			}
		}

		// Create array to return.
		// Add log rows to sub key 'log_rows' because meta info is also added.
		$arr_return = [
			'total_row_count' => (int) $total_found_rows,
			'pages_count'     => $pages_count,
			'page_current'    => $args['paged'],
			'page_rows_from'  => $page_rows_from,
			'page_rows_to'    => $page_rows_to,
			'max_id'          => (int) $max_id,
			'min_id'          => (int) $min_id,
			'max_date'        => $max_date,
			'log_rows_count'  => $log_rows_count,
			// Remove id from keys, because they are cumbersome when working with JSON.
			'log_rows'        => $result_log_rows,
		];

		wp_cache_set( $cache_key, $arr_return, $cache_group );

		return $arr_return;
	}

	/**
	 * Get occasions for a single event.
	 *
	 * Required args are:
	 * - occasionsID: The id to get occasions for.
	 * - occasionsCount: The number of occasions to get.
	 * - occasionsCountMaxReturn: The max number of occasions to return.
	 *
	 * Does not take filters/where into consideration.
	 *
	 * @param string|array|object $args Arguments.
	 * @return array|\WP_Error Log rows or WP_Error on database error.
	 */
	protected function query_occasions( $args ) {
		// Create cache key based on args and current user.
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
		$cache_key   = 'SimpleHistoryLogQuery_' . md5( serialize( $args ) ) . '_userid_' . get_current_user_id();
		$cache_group = Helpers::get_cache_group();

		/** @var array Return value. */
		$arr_return = wp_cache_get( $cache_key, $cache_group );

		// Return cached value if it exists.
		if ( false !== $arr_return ) {
			$arr_return['cached_result'] = true;
			return $arr_return;
		}

		$simpe_history       = Simple_History::get_instance();
		$events_table_name   = $simpe_history->get_events_table_name();
		$contexts_table_name = $simpe_history->get_contexts_table_name();

		$args = wp_parse_args(
			$args,
			[
				'type'                    => 'occasions',
				'logRowID'                => null,
				'occasionsID'             => null,
				'occasionsCount'          => null,
				'occasionsCountMaxReturn' => null,
			]
		);

		$args = $this->prepare_args( $args );

		// Get occasions for a single event.
		// Args must contain:
		// - occasionsID: The id to get occasions for
		// - occasionsCount: The number of occasions to get.
		// - occasionsCountMaxReturn: The max number of occasions to return,
		// if occasionsCount is very large and we do not want to get all occasions.

		/**
		 * @var string $sql_statement_template SQL template for occasions query.
		 * Template uses number argument to sprintf to insert values.
		 * Arguments:
		 * 1 = where clause.
		 * 2 = limit clause.
		 * 3 = table name for events.
		 */
		$sql_statement_template = '
			SELECT 
				h.id,
				h.logger,
				h.level,
				h.date,
				h.message,
				h.initiator,
				h.occasionsID,
				c1.value AS context_message_key,
				# Hard code subsequentOccasions column that exist in overview query
				1 as subsequentOccasions
			FROM %3$s AS h
			
			# Add context message key
			LEFT OUTER JOIN %4$s AS c1 ON (c1.history_id = h.id AND c1.key = "_message_key")
			
			# Where
			%1$s
			
			ORDER BY date DESC, id DESC
			%2$s
		';

		/** @var array Where clauses for outer query. */
		$outer_where = [];

		// Get rows with id lower than logRowID, i.e. previous rows.
		$outer_where[] = 'h.id < ' . (int) $args['logRowID'];

		// Get rows with occasionsID equal to occasionsID.
		$outer_where[] = "h.occasionsID = '" . esc_sql( $args['occasionsID'] ) . "'";

		if ( isset( $args['occasionsCountMaxReturn'] ) && $args['occasionsCountMaxReturn'] < $args['occasionsCount'] ) {
			// Limit to max nn events if occasionsCountMaxReturn is set.
			// Used for example in GUI to prevent to many events returned, that can stall the browser.
			$limit = 'LIMIT ' . $args['occasionsCountMaxReturn'];
		} else {
			// Regular limit that gets all occasions.
			$limit = 'LIMIT ' . $args['occasionsCount'];
		}

		// Create where string.
		$outer_where = implode( "\nAND ", $outer_where );

		// Append where to sql template.
		if ( ! empty( $outer_where ) ) {
			$outer_where = "\nWHERE {$outer_where}";
		}

		/** @var string SQL generated from template. */
		$sql_query = sprintf(
			$sql_statement_template, // sprintf template.
			$outer_where,  // 1
			$limit, // 2
			$events_table_name, // 3
			$contexts_table_name // 4
		);

		global $wpdb;

		/** @var array<string,object> Log rows matching where queries. */
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$log_rows = $wpdb->get_results( $sql_query, OBJECT_K );

		$log_rows = $this->add_contexts_to_log_rows( $log_rows );

		return [
			// Remove id from keys, because they are cumbersome when working with JSON.
			'log_rows' => array_values( $log_rows ),
			'sql'      => $sql_query,
		];
	}

	/**
	 * Query for surrounding events around a specific event ID.
	 *
	 * This method returns events before and after a specific event in reverse
	 * chronological order (newest first), matching the main event log display.
	 * It bypasses logger, user, and other filters for debugging scenarios.
	 *
	 * IMPORTANT: This method bypasses normal logger permission checks and returns
	 * ALL events. Permission checking should be done by the caller (REST API or
	 * WP-CLI) before calling this method.
	 *
	 * @param array $args {
	 *     Query arguments.
	 *
	 *     @type int $surrounding_event_id Required. The center event ID.
	 *     @type int $surrounding_count    Optional. Number of events before AND after. Default 5.
	 * }
	 * @return array|\WP_Error {
	 *     Query results array or WP_Error on failure.
	 *
	 *     @type array  $log_rows         Array of event objects (after + center + before, newest first).
	 *     @type int    $center_event_id  The ID of the center event.
	 *     @type int    $total_row_count  Total number of events returned.
	 *     @type int    $events_before    Count of events before center.
	 *     @type int    $events_after     Count of events after center.
	 *     @type int    $max_id           Highest event ID in results.
	 *     @type int    $min_id           Lowest event ID in results.
	 *     @type string $max_date         Date of most recent event.
	 * }
	 */
	protected function query_surrounding_events( $args ) {
		global $wpdb;

		$simple_history    = Simple_History::get_instance();
		$events_table_name = $simple_history->get_events_table_name();

		// Parse arguments with defaults.
		$args = wp_parse_args(
			$args,
			[
				'surrounding_event_id' => null,
				'surrounding_count'    => 5,
			]
		);

		// Validate surrounding_event_id.
		if ( ! isset( $args['surrounding_event_id'] ) || ! is_numeric( $args['surrounding_event_id'] ) ) {
			return new \WP_Error(
				'invalid_surrounding_event_id',
				__( 'Invalid surrounding_event_id parameter.', 'simple-history' ),
				[ 'status' => 400 ]
			);
		}

		$center_event_id = (int) $args['surrounding_event_id'];

		// Validate surrounding_count (must be positive integer, max 50).
		$surrounding_count = (int) $args['surrounding_count'];
		if ( $surrounding_count < 1 ) {
			$surrounding_count = 5;
		}
		if ( $surrounding_count > 50 ) {
			$surrounding_count = 50;
		}

		// First, verify the center event exists and get its data.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$center_event = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT id, date FROM %i WHERE id = %d',
				$events_table_name,
				$center_event_id
			)
		);

		if ( ! $center_event ) {
			return new \WP_Error(
				'event_not_found',
				__( 'The specified event was not found.', 'simple-history' ),
				[ 'status' => 404 ]
			);
		}

		// Get events AFTER the center event (newer, higher IDs).
		// Order by id ASC to get the events closest to center first (lowest IDs above center),
		// then reverse so newest is first for display (matching the main event log order).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$events_after = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT
					id, logger, level, date, message, initiator, occasionsID,
					1 AS repeatCount, 1 AS subsequentOccasions
				FROM %i
				WHERE id > %d
				ORDER BY id ASC
				LIMIT %d',
				$events_table_name,
				$center_event_id,
				$surrounding_count
			),
			OBJECT_K
		);

		// Reverse to get newest first (DESC order) for consistent display with main log.
		// Example: Query returns [2976, 2977, 2978] (ASC), reverse to [2978, 2977, 2976] (DESC).
		$events_after = array_reverse( $events_after, true );

		// Get the center event with full data.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$center_event_full = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT
					id, logger, level, date, message, initiator, occasionsID,
					1 AS repeatCount, 1 AS subsequentOccasions
				FROM %i
				WHERE id = %d',
				$events_table_name,
				$center_event_id
			),
			OBJECT_K
		);

		// Get events BEFORE the center event (older, lower IDs).
		// Order by id DESC to get newest (closest to center) first.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$events_before = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT
					id, logger, level, date, message, initiator, occasionsID,
					1 AS repeatCount, 1 AS subsequentOccasions
				FROM %i
				WHERE id < %d
				ORDER BY id DESC
				LIMIT %d',
				$events_table_name,
				$center_event_id,
				$surrounding_count
			),
			OBJECT_K
		);

		// Combine all events: after + center + before (reverse chronological order, newest first).
		$all_events = $events_after + $center_event_full + $events_before;

		// Add context data to all events.
		$all_events = $this->add_contexts_to_log_rows( $all_events );

		// Convert to indexed array.
		$log_rows = array_values( $all_events );

		// Calculate metadata.
		$events_before_count = count( $events_before );
		$events_after_count  = count( $events_after );
		$total_count         = count( $log_rows );

		// Get max/min IDs and max date.
		$max_id   = null;
		$min_id   = null;
		$max_date = null;

		if ( $total_count > 0 ) {
			// Events are in reverse chronological order (newest first), so:
			// - max_id is the first event (newest, highest ID).
			// - min_id is the last event (oldest, lowest ID).
			$max_id   = (int) $log_rows[0]->id;
			$min_id   = (int) $log_rows[ $total_count - 1 ]->id;
			$max_date = $log_rows[0]->date;
		}

		return [
			'log_rows'        => $log_rows,
			'center_event_id' => $center_event_id,
			'total_row_count' => $total_count,
			'events_before'   => $events_before_count,
			'events_after'    => $events_after_count,
			'max_id'          => $max_id,
			'min_id'          => $min_id,
			'max_date'        => $max_date,
			'log_rows_count'  => $total_count,
			// Standard pagination fields (not really applicable but included for consistency).
			'pages_count'     => 1,
			'page_current'    => 1,
			'page_rows_from'  => 1,
			'page_rows_to'    => $total_count,
		];
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
		/** @var array Query arguments. */
		$args = wp_parse_args(
			$args,
			[
				// overview | occasions | single.
				// When type is occasions then logRowID, occasionsID, occasionsCount, occasionsCountMaxReturn are required.
				'type'              => 'overview',

				// Number of posts to show per page. 0 to show all.
				'posts_per_page'    => 10,

				// Page to show. 1 = first page.
				'paged'             => 1,

				// Array. Only get posts that are in array.
				'post__in'          => [],

				// If max_id_first_page is set then only get rows
				// that have id equal or lower than this, to make.
				'max_id_first_page' => null,

				// if since_id is set the rows returned will only be rows with an ID greater than (i.e. more recent than) since_id.
				'since_id'          => null,

				// if since_date is set, used together with since_id to accurately detect new events with date ordering.
				// Only returns events with date > since_date OR (date = since_date AND id > since_id).
				'since_date'        => null,
				/**
				 * From date, as unix timestamp integer or as a format compatible with strtotime, for example 'Y-m-d H:i:s'.
				 *
				 * @var int|string
				 */
				'date_from'         => null,

				/**
				* To date, as unix timestamp integer or as a format compatible with strtotime, for example 'Y-m-d H:i:s'.
				*
				* @var int|string
				*/
				'date_to'           => null,

				// months in format "Y-m"
				// array or comma separated.
				'months'            => null,

				// dates in format
				// "month:2015-06" for june 2015
				// "lastdays:7" for the last 7 days.
				'dates'             => null,

				/**
				 * Text to search for.
				 * Message, logger and level are searched for in main table.
				 * Values are searched for in context table.
				 *
				 * @var string
				 */
				'search'            => null,

				// log levels to include. comma separated or as array. defaults to all.
				'loglevels'         => null,

				// loggers to include. comma separated. defaults to all the user can read.
				'loggers'           => null,

				'messages'          => null,

				// userID as number.
				'user'              => null,

				// User ids, comma separated or array.
				'users'             => null,

				// Initiator to filter by.
				'initiator'         => null,

				// Should sticky events be included in the result set.
				'include_sticky'    => false,

				// Only return sticky events.
				'only_sticky'       => false,

				// Context filters as key-value pairs.
				'context_filters'   => null,

				// Return ungrouped events without occasions grouping.
				'ungrouped'         => false,

				// Exclusion filters - hide events matching these criteria.
				// Text to exclude from search.
				'exclude_search'    => null,

				// Log levels to exclude, comma separated or array.
				'exclude_loglevels' => null,

				// Loggers to exclude, comma separated or array.
				'exclude_loggers'   => null,

				// Messages to exclude, comma separated or array in format "LoggerSlug:Message".
				'exclude_messages'  => null,

				// Single user ID to exclude.
				'exclude_user'      => null,

				// User IDs to exclude, comma separated or array.
				'exclude_users'     => null,

				// Initiator(s) to exclude.
				'exclude_initiator' => null,

			// Can also contain:
			// logRowID
			// occasionsCount
			// occasionsCountMaxReturn
			// occasionsID.
			]
		);

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

		// If posts_per_page is set then it must be a positive integer.
		if ( isset( $args['posts_per_page'] ) && ( ! is_numeric( $args['posts_per_page'] ) || $args['posts_per_page'] < 1 ) ) {
			throw new \InvalidArgumentException( 'Invalid posts_per_page' );
		} elseif ( isset( $args['posts_per_page'] ) ) {
			$args['posts_per_page'] = (int) $args['posts_per_page'];
		}

		// paged must be must be a positive integer.
		if ( isset( $args['paged'] ) && ( ! is_numeric( $args['paged'] ) || $args['paged'] < 1 ) ) {
			throw new \InvalidArgumentException( 'Invalid paged' );
		} elseif ( isset( $args['paged'] ) ) {
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

		// "since_date" must be valid date string in format Y-m-d H:i:s.
		if ( isset( $args['since_date'] ) ) {
			if ( ! is_string( $args['since_date'] ) ) {
				throw new \InvalidArgumentException( 'Invalid since_date: must be a string' );
			}

			// Strict format validation to prevent SQL injection.
			$parsed_date = \DateTime::createFromFormat( 'Y-m-d H:i:s', $args['since_date'] );
			if ( ! $parsed_date || $parsed_date->format( 'Y-m-d H:i:s' ) !== $args['since_date'] ) {
				throw new \InvalidArgumentException( 'Invalid since_date format. Use Y-m-d H:i:s (e.g., 2024-01-15 14:30:00)' );
			}
		}

		// "date_from" must be timestamp or string. If string then convert to timestamp.
		// Uses WordPress timezone for date parsing to ensure correct day boundaries.
		if ( isset( $args['date_from'] ) && is_numeric( $args['date_from'] ) ) {
			$args['date_from'] = (int) $args['date_from'];
		} elseif ( isset( $args['date_from'] ) && is_string( $args['date_from'] ) ) {
			// If value is "2025-03-29" that means the beginning of the day on 2025-03-29 in WordPress timezone.
			$is_start_of_day_date_format = $this->is_valid_date_format( $args['date_from'], 'Y-m-d' );
			if ( $is_start_of_day_date_format ) {
				// Parse date in WordPress timezone and get start of day (00:00:00).
				$date              = new \DateTimeImmutable( $args['date_from'] . ' 00:00:00', wp_timezone() );
				$args['date_from'] = $date->getTimestamp();
			} else {
				// Parse datetime string in WordPress timezone.
				$date              = new \DateTimeImmutable( $args['date_from'], wp_timezone() );
				$args['date_from'] = $date->getTimestamp();
			}
		} elseif ( isset( $args['date_from'] ) ) {
			throw new \InvalidArgumentException( 'Invalid date_from' );
		}

		// "date_to" must be timestamp or string. If string then convert to timestamp.
		// Uses WordPress timezone for date parsing to ensure correct day boundaries.
		if ( isset( $args['date_to'] ) && is_numeric( $args['date_to'] ) ) {
			$args['date_to'] = (int) $args['date_to'];
		} elseif ( isset( $args['date_to'] ) && is_string( $args['date_to'] ) ) {
			// If value is "2025-03-29" that means the end of the day on 2025-03-29 in WordPress timezone.
			$is_start_of_day_date_format = $this->is_valid_date_format( $args['date_to'], 'Y-m-d' );
			if ( $is_start_of_day_date_format ) {
				// Parse date in WordPress timezone and get end of day (23:59:59).
				$date            = new \DateTimeImmutable( $args['date_to'] . ' 23:59:59', wp_timezone() );
				$args['date_to'] = $date->getTimestamp();
			} else {
				// Parse datetime string in WordPress timezone.
				$date            = new \DateTimeImmutable( $args['date_to'], wp_timezone() );
				$args['date_to'] = $date->getTimestamp();
			}
		} elseif ( isset( $args['date_to'] ) ) {
			throw new \InvalidArgumentException( 'Invalid date_to' );
		}

		// "search" must be string.
		if ( isset( $args['search'] ) && ! is_string( $args['search'] ) ) {
			throw new \InvalidArgumentException( 'Invalid search' );
		}

		// "loglevels" must be comma separated string "info,debug"
		// or array of log level strings.
		if ( isset( $args['loglevels'] ) && ! is_string( $args['loglevels'] ) && ! is_array( $args['loglevels'] ) ) {
			throw new \InvalidArgumentException( 'Invalid loglevels' );
		} elseif ( isset( $args['loglevels'] ) && is_string( $args['loglevels'] ) ) {
			$args['loglevels'] = explode( ',', $args['loglevels'] );
		}

		// Make sure loglevels are trimmed, strings, and empty vals removed.
		if ( isset( $args['loglevels'] ) ) {
			$args['loglevels'] = array_map( 'trim', $args['loglevels'] );
			$args['loglevels'] = array_map( 'strval', $args['loglevels'] );
			$args['loglevels'] = array_filter( $args['loglevels'] );
		}

		// phpcs:ignore Squiz.PHP.CommentedOutCode.Found
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

		// Make sure messages are trimmed, strings, and empty vals removed.
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

				// Skip malformed entries without colon (must have at least logger:message format).
				if ( count( $arr_one_logger_and_message ) < 2 ) {
					continue;
				}

				$logger_slug = $arr_one_logger_and_message[0];
				$message_key = $arr_one_logger_and_message[1];

				if ( ! isset( $arr_loggers_and_messages[ $logger_slug ] ) ) {
					$arr_loggers_and_messages[ $logger_slug ] = array();
				}

				$arr_loggers_and_messages[ $logger_slug ][] = $message_key;
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

		// "initiator" must be string or array of strings and contain valid initiator constants.
		if ( isset( $args['initiator'] ) ) {
			if ( is_string( $args['initiator'] ) ) {
				// Single initiator - validate it's a valid constant.
				if ( ! in_array( $args['initiator'], Log_Initiators::get_valid_initiators(), true ) ) {
					throw new \InvalidArgumentException( 'Invalid initiator value' );
				}
			} elseif ( is_array( $args['initiator'] ) ) {
				// Multiple initiators - validate each one and filter out empty values.
				$args['initiator'] = array_filter( $args['initiator'] );
				foreach ( $args['initiator'] as $initiator ) {
					if ( ! is_string( $initiator ) || ! in_array( $initiator, Log_Initiators::get_valid_initiators(), true ) ) {
						throw new \InvalidArgumentException( 'Invalid initiator value: ' . esc_html( $initiator ) );
					}
				}
			} else {
				throw new \InvalidArgumentException( 'Invalid initiator type' );
			}
		}

		// Process exclusion filters using the same validation logic as inclusion filters.
		// "exclude_search" must be string.
		if ( isset( $args['exclude_search'] ) && ! is_string( $args['exclude_search'] ) ) {
			throw new \InvalidArgumentException( 'Invalid exclude_search' );
		}

		// "exclude_loglevels", comma separated string or array with strings.
		if ( isset( $args['exclude_loglevels'] ) && ! is_string( $args['exclude_loglevels'] ) && ! is_array( $args['exclude_loglevels'] ) ) {
			throw new \InvalidArgumentException( 'Invalid exclude_loglevels' );
		} elseif ( isset( $args['exclude_loglevels'] ) && is_string( $args['exclude_loglevels'] ) ) {
			$args['exclude_loglevels'] = explode( ',', $args['exclude_loglevels'] );
		}

		// Make sure exclude_loglevels are trimmed, strings, and empty vals removed.
		if ( isset( $args['exclude_loglevels'] ) ) {
			$args['exclude_loglevels'] = array_map( 'trim', $args['exclude_loglevels'] );
			$args['exclude_loglevels'] = array_map( 'strval', $args['exclude_loglevels'] );
			$args['exclude_loglevels'] = array_filter( $args['exclude_loglevels'] );
		}

		// "exclude_loggers", comma separated string or array with strings.
		if ( isset( $args['exclude_loggers'] ) && ! is_string( $args['exclude_loggers'] ) && ! is_array( $args['exclude_loggers'] ) ) {
			throw new \InvalidArgumentException( 'Invalid exclude_loggers' );
		} elseif ( isset( $args['exclude_loggers'] ) && is_string( $args['exclude_loggers'] ) ) {
			$args['exclude_loggers'] = explode( ',', $args['exclude_loggers'] );
		}

		// Make sure exclude_loggers are trimmed, strings, and empty vals removed.
		if ( isset( $args['exclude_loggers'] ) ) {
			$args['exclude_loggers'] = array_map( 'trim', $args['exclude_loggers'] );
			$args['exclude_loggers'] = array_map( 'strval', $args['exclude_loggers'] );
			$args['exclude_loggers'] = array_filter( $args['exclude_loggers'] );
		}

		// "exclude_messages" is string with comma separated loggers and messages, or array.
		if ( isset( $args['exclude_messages'] ) && ! is_string( $args['exclude_messages'] ) && ! is_array( $args['exclude_messages'] ) ) {
			throw new \InvalidArgumentException( 'Invalid exclude_messages' );
		} elseif ( isset( $args['exclude_messages'] ) && is_string( $args['exclude_messages'] ) ) {
			$args['exclude_messages'] = explode( ',', $args['exclude_messages'] );
		} elseif ( isset( $args['exclude_messages'] ) && is_array( $args['exclude_messages'] ) ) {
			// Turn multi dimensional array into single array with strings.
			$arr_exclude_messages = [];
			foreach ( $args['exclude_messages'] as $one_arr_messages_row ) {
				$arr_exclude_messages = array_merge( $arr_exclude_messages, explode( ',', $one_arr_messages_row ) );
			}

			$args['exclude_messages'] = $arr_exclude_messages;
		}

		// Make sure exclude_messages are trimmed, strings, and empty vals removed.
		// Transform to format where key = logger slug, value = array of logger messages.
		if ( isset( $args['exclude_messages'] ) ) {
			$args['exclude_messages'] = array_map( 'trim', $args['exclude_messages'] );
			$args['exclude_messages'] = array_map( 'strval', $args['exclude_messages'] );
			$args['exclude_messages'] = array_filter( $args['exclude_messages'] );

			$arr_exclude_loggers_and_messages = [];

			foreach ( $args['exclude_messages'] as $one_row_logger_and_message ) {
				$arr_one_logger_and_message = explode( ':', $one_row_logger_and_message );

				// Skip malformed entries without colon (must have at least logger:message format).
				if ( count( $arr_one_logger_and_message ) < 2 ) {
					continue;
				}

				$logger_slug = $arr_one_logger_and_message[0];
				$message_key = $arr_one_logger_and_message[1];

				if ( ! isset( $arr_exclude_loggers_and_messages[ $logger_slug ] ) ) {
					$arr_exclude_loggers_and_messages[ $logger_slug ] = array();
				}

				$arr_exclude_loggers_and_messages[ $logger_slug ][] = $message_key;
			}

			$args['exclude_messages'] = $arr_exclude_loggers_and_messages;
		}

		// "exclude_user" must be integer.
		if ( isset( $args['exclude_user'] ) && ! is_numeric( $args['exclude_user'] ) ) {
			throw new \InvalidArgumentException( 'Invalid exclude_user' );
		} elseif ( isset( $args['exclude_user'] ) ) {
			$args['exclude_user'] = (int) $args['exclude_user'];
		}

		// "exclude_users" must be comma separated string or array with integers.
		if ( isset( $args['exclude_users'] ) && ! is_string( $args['exclude_users'] ) && ! is_array( $args['exclude_users'] ) ) {
			throw new \InvalidArgumentException( 'Invalid exclude_users' );
		} elseif ( isset( $args['exclude_users'] ) && is_string( $args['exclude_users'] ) ) {
			$args['exclude_users'] = explode( ',', $args['exclude_users'] );
		}

		// Make sure exclude_users are integers and remove empty vals.
		if ( isset( $args['exclude_users'] ) ) {
			$args['exclude_users'] = array_map( 'intval', $args['exclude_users'] );
			$args['exclude_users'] = array_filter( $args['exclude_users'] );
		}

		// "exclude_initiator" must be string or array of strings and contain valid initiator constants.
		if ( isset( $args['exclude_initiator'] ) ) {
			if ( is_string( $args['exclude_initiator'] ) ) {
				// Single initiator - validate it's a valid constant.
				if ( ! in_array( $args['exclude_initiator'], Log_Initiators::get_valid_initiators(), true ) ) {
					throw new \InvalidArgumentException( 'Invalid exclude_initiator value' );
				}
			} elseif ( is_array( $args['exclude_initiator'] ) ) {
				// Multiple initiators - validate each one and filter out empty values.
				$args['exclude_initiator'] = array_filter( $args['exclude_initiator'] );
				foreach ( $args['exclude_initiator'] as $initiator ) {
					if ( ! is_string( $initiator ) || ! in_array( $initiator, Log_Initiators::get_valid_initiators(), true ) ) {
						throw new \InvalidArgumentException( 'Invalid exclude_initiator value: ' . esc_html( $initiator ) );
					}
				}
			} else {
				throw new \InvalidArgumentException( 'Invalid exclude_initiator type' );
			}
		}

		return $args;
	}

	/**
	 * Add context to log rows.
	 * Gets all ids from log rows and then fetches context for those ids
	 * in a single query.
	 *
	 * @param array $log_rows Log rows to append context to.
	 * @return array Log rows with context added.
	 */
	protected function add_contexts_to_log_rows( $log_rows ) {
		// Bail if no log rows.
		if ( sizeof( $log_rows ) === 0 ) {
			return $log_rows;
		}

		global $wpdb;

		$simple_history = Simple_History::get_instance();
		$table_contexts = $simple_history->get_contexts_table_name();

		$post_ids = wp_list_pluck( $log_rows, 'id' );

		$sql_context_query = sprintf(
			'SELECT history_id, `key`, value FROM %2$s WHERE history_id IN (%1$s)',
			join( ',', $post_ids ),
			$table_contexts
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$context_results = $wpdb->get_results( $sql_context_query );

		foreach ( $context_results as $context_row ) {
			if ( ! isset( $log_rows[ $context_row->history_id ]->context ) ) {
				$log_rows[ $context_row->history_id ]->context = [];
			}

			$log_rows[ $context_row->history_id ]->context[ $context_row->key ] = $context_row->value;
		}

		// Move up _message_key from context row to main row as context_message_key.
		// This is because that's the way it was before SQL was rewritten
		// to support FULL_GROUP_BY in December 2023.
		foreach ( $log_rows as $log_row ) {
			if ( isset( $log_row->context_message_key ) ) {
				continue;
			}

			$log_row->context_message_key = null;

			if ( isset( $log_row->context['_message_key'] ) ) {
				$log_row->context_message_key = $log_row->context['_message_key'];
			}
		}

		return $log_rows;
	}

	/**
	 * Get max and min ids for a set of log rows.
	 *
	 * @param array $log_rows Log rows.
	 * @return array<null|int,null|int> Array with max and min id.
	 */
	protected function get_max_min_ids( $log_rows ) {
		/** @var null|int $min_id */
		$min_id = null;

		/** @var null|int $max_id */
		$max_id = null;

		// Bail of no log rows.
		if ( sizeof( $log_rows ) === 0 ) {
			return [
				$max_id,
				$min_id,
			];
		}

		global $wpdb;

		$events_table_name = Simple_History::get_instance()->get_events_table_name();

		// Max id is simply the id of the first/most recent row.
		$max_id = reset( $log_rows )->id;

		// Min id = to find the lowest id we must take occasions into consideration.
		$last_row = end( $log_rows );

		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$last_row_occasions_count = (int) $last_row->subsequentOccasions - 1;

		if ( $last_row_occasions_count === 0 ) {
			// Last row did not have any more occasions, so get min_id directly from the row.
			$min_id = (int) $last_row->id;
		} else {
			// Last row did have occasions, so fetch all occasions, and find id of last one.
			$sql = sprintf(
				'
						SELECT id, date, occasionsID
						FROM %1$s
						WHERE id <= %2$d
						ORDER BY date DESC, id DESC
						LIMIT %3$d
					',
				$events_table_name,
				$last_row->id,
				$last_row_occasions_count + 1
			);

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
			$results = $wpdb->get_results( $sql );

			// the last occasion has the id we consider last in this paged result.
			$min_id = (int) end( $results )->id;
		}

		return [
			$max_id,
			$min_id,
		];
	}

	/**
	 * Find logger and translated message that matches search string.
	 * The search string in split into words and all words must be found in the translated text.
	 * The message can contain more text/words than the words in the search string, and partial matches
	 * are fine.
	 *
	 * Swedish examples:
	 *
	 * Search phrase "tillägg uppdaterade":
	 * - Should match logger "SimplePluginLogger", message key "plugin_updated", message "uppdaterade tillägget "{plugin_name}" till {plugin_version} från {plugin_prev_version}"
	 * - Should match logger "SimplePluginLogger", message key "plugin_bulk_updated", message "uppdaterade tillägget "{plugin_name}" till {plugin_version} från {plugin_prev_version}"
	 *
	 * Search phrase "misslyckades logga in":
	 * - Should match logger "SimpleUserLogger", message key "user_login_failed", message "misslyckades att logga in med användarnamnet "{login}" (felaktigt lösenord angavs)"
	 * - Should match logger "SimpleUserLogger", message key "user_unknown_login_failed", message "misslyckades att logga in med användarnamnet "{failed_username}" (användarnamnet finns inte)"
	 *
	 * @param string $searchstring Search string, for example "misslyckades logga in".
	 * @return array<int,array> Array with logger and message that matched search string.
	 */
	protected function match_logger_messages_with_search( $searchstring ) {
		$Simple_History = Simple_History::get_instance();

		$loggers_user_can_read = $Simple_History->get_loggers_that_user_can_read();

		$searchstring = strtolower( trim( $searchstring ) );

		/** @var array<int,array<int,array>> Array with found logger, message key, translated message, and untranslated message. */
		$found_matches = [];

		if ( empty( $searchstring ) ) {
			return [];
		}

		$words = preg_split( '/[\s,]+/', $searchstring );

		foreach ( $loggers_user_can_read as $one_logger ) {
			/** @var \Simple_History\Loggers\Logger $logger_instance */
			$logger_instance = $one_logger['instance'];
			$one_logger_slug = $logger_instance->get_slug();
			$one_logger_name = $one_logger['name'];

			/** @var array<string,array> */
			$logger_instance_messages = $logger_instance->get_messages();

			foreach ( $logger_instance_messages as $one_message_key => $one_message ) {
				$translated_text = strtolower( $one_message['translated_text'] );

				// Check if every word in search string exists in translated text.
				$all_words_found = true;

				foreach ( $words as $one_word ) {
					if ( strpos( $translated_text, $one_word ) === false ) {
						$all_words_found = false;
						break;
					}
				}

				if ( $all_words_found ) {
					$found_matches[] = [
						'logger_name'       => $one_logger_name,
						'logger_slug'       => $one_logger_slug,
						'message_key'       => $one_message_key,
						'translated_text'   => $translated_text,
						'untranslated_text' => strtolower( $one_message['untranslated_text'] ),
					];
				}
			}
		}

		return $found_matches;
	}

	/**
	 * Get inner where clause as array where each item in the array is a where clause statement, without the "WHERE" keyword or "AND" keyword.
	 *
	 * This function is used by both MySQL and SQLite.
	 *
	 * Example of array contents:
	 *
	 * Array
	 * (
	 *  [0] => logger IN ('AvailableUpdatesLogger', 'FileEditsLogger', 'Plugin_ACF', 'Plugin_BeaverBuilder', 'Plugin_DuplicatePost', 'Plugin_LimitLoginAttempts', 'Plugin_Redirection', 'PluginEnableMediaReplaceLogger', 'PluginUserSwitchingLogger', 'PluginWPCrontrolLogger', 'SH_Jetpack_Logger', 'SH_Privacy_Logger', 'SH_Translations_Logger', 'SimpleCategoriesLogger', 'SimpleCommentsLogger', 'SimpleCoreUpdatesLogger', 'SimpleExportLogger', 'SimpleLogger', 'SimpleMediaLogger', 'SimpleMenuLogger', 'SimpleOptionsLogger', 'SimplePluginLogger', 'SimplePostLogger', 'SimpleThemeLogger', 'SimpleUserLogger', 'SimpleHistoryLogger', 'WPMailLogger', 'WPHTTPRequestsLogger', 'WPCronLogger', 'WooCommerceLogger')
	 *  [1] => date >= DATE(NOW() - INTERVAL 30 DAY)
	 * )
	 *
	 * @param array $args Query arguments, as passed to query().
	 * @return array<string> Where clauses.
	 */
	protected function get_inner_where( $args ) {
		global $wpdb;

		$simple_history      = Simple_History::get_instance();
		$contexts_table_name = $simple_history->get_contexts_table_name();
		$db_engine           = $this->get_db_engine();

		$inner_where = [];

		// Only include loggers that the current user can view
		// TODO: this causes error if user has no access to any logger at all.
		$sql_loggers_user_can_view = $simple_history->get_loggers_that_user_can_read( get_current_user_id(), 'sql' );
		$inner_where[]             = "logger IN {$sql_loggers_user_can_view}";

		// Add post__in where.
		if ( isset( $args['post__in'] ) && sizeof( $args['post__in'] ) > 0 ) {
			$inner_where[] = sprintf( 'id IN (%1$s)', implode( ',', $args['post__in'] ) );
		}

		// If max_id_first_page is then then only include rows
		// with id equal to or earlier than this, i.e. older than this.
		if ( isset( $args['max_id_first_page'] ) ) {
			$inner_where[] = sprintf(
				'id <= %1$d',
				$args['max_id_first_page']
			);
		}

		// Add where clause for since_id and since_date.
		// When both are provided, we want events that would appear ABOVE the current view.
		// With ORDER BY date DESC, id DESC, that means:
		// - Events with date > since_date (newer date).
		// - OR events with date = since_date AND id > since_id (same date but higher ID).
		if ( isset( $args['since_date'] ) && isset( $args['since_id'] ) ) {
			$inner_where[] = $wpdb->prepare(
				'(date > %s OR (date = %s AND id > %d))',
				$args['since_date'],
				$args['since_date'],
				$args['since_id']
			);
		} elseif ( isset( $args['since_id'] ) ) {
			// Fallback to ID-only for backward compatibility
			// (though this is less accurate with date ordering).
			$inner_where[] = sprintf(
				'id > %1$d',
				(int) $args['since_id'],
			);
		}

		// Append date where clause.
		// If date_from is set it is a timestamp.
		if ( ! empty( $args['date_from'] ) ) {
			$inner_where[] = sprintf( 'date >= \'%1$s\'', gmdate( 'Y-m-d H:i:s', $args['date_from'] ) );
		}

		// Date to.
		// If date_to is set it is a timestamp.
		if ( ! empty( $args['date_to'] ) ) {
			$inner_where[] = sprintf( 'date <= \'%1$s\'', gmdate( 'Y-m-d H:i:s', $args['date_to'] ) );
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

			$args['months']    = [];
			$args['lastdays']  = 0;
			$args['yesterday'] = false;

			foreach ( $arr_dates as $one_date ) {
				if ( strpos( $one_date, 'month:' ) === 0 ) {
					// If begins with "month:" then strip string and keep only month numbers.
					$args['months'][] = substr( $one_date, strlen( 'month:' ) );
					// If begins with "lastdays:" then strip string and keep only number of days.
				} elseif ( strpos( $one_date, 'lastdays:' ) === 0 ) {
					// Only keep largest lastdays value.
					$args['lastdays'] = max( $args['lastdays'], substr( $one_date, strlen( 'lastdays:' ) ) );
				} elseif ( $one_date === 'yesterday' ) {
					$args['yesterday'] = true;
				}
			}
		}

		// Add where clause for "lastdays", as int.
		// Uses Date_Helper to ensure WordPress timezone is respected.
		if ( ! empty( $args['lastdays'] ) ) {
			// Validate lastdays is a positive integer.
			$lastdays = (int) $args['lastdays'];
			if ( $lastdays > 0 ) {
				$timestamp     = Date_Helper::get_last_n_days_start_timestamp( $lastdays );
				$inner_where[] = sprintf( 'date >= \'%1$s\'', gmdate( 'Y-m-d H:i:s', $timestamp ) );
			}
		}

		// Add where clause for "yesterday".
		// Uses Date_Helper which respects WordPress timezone.
		if ( ! empty( $args['yesterday'] ) ) {
			$range         = Date_Helper::get_last_n_complete_days_range( 1 );
			$inner_where[] = sprintf(
				'(date >= \'%1$s\' AND date <= \'%2$s\')',
				gmdate( 'Y-m-d H:i:s', $range['from'] ),
				gmdate( 'Y-m-d H:i:s', $range['to'] )
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
				(
			';

			foreach ( $arr_months as $one_month ) {
				// Beginning of month in WordPress timezone.
				// For "2014-08", this is 2014-08-01 00:00:00 in WordPress timezone.
				$date_month_beginning_obj = new \DateTimeImmutable( $one_month . '-01 00:00:00', wp_timezone() );
				$date_month_beginning     = $date_month_beginning_obj->getTimestamp();

				// End of month in WordPress timezone.
				// Add 1 month to get the start of the next month, then subtract 1 second to get end of current month.
				// For "2014-08", this is 2014-08-31 23:59:59 in WordPress timezone.
				$date_month_end_obj = $date_month_beginning_obj->modify( '+1 month' )->modify( '-1 second' );
				$date_month_end     = $date_month_end_obj->getTimestamp();

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
				)
			';

			$inner_where[] = $sql_months;
		}

		// Search.
		$inner_where = $this->add_search_to_inner_where_query( $inner_where, $args );

		// "loglevels", array with loglevels.
		// e.g. info, debug, and so on.
		if ( ! empty( $args['loglevels'] ) ) {
			// Create placeholders for prepared statement.
			$placeholders  = implode( ', ', array_fill( 0, count( $args['loglevels'] ), '%s' ) );
			$inner_where[] = $wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Dynamic placeholders in $placeholders variable matched with spread operator
				"level IN ({$placeholders})",
				...$args['loglevels']
			);
		}

		// loggers, comma separated or array.
		// Example REST API call: /wp-json/simple-history/v1/events?per_page=10&page=1&loggers=SimpleCommentsLogger,SimpleCoreUpdatesLogger.
		if ( ! empty( $args['loggers'] ) ) {
			// Create placeholders for prepared statement.
			$placeholders  = implode( ', ', array_fill( 0, count( $args['loggers'] ), '%s' ) );
			$inner_where[] = $wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Dynamic placeholders in $placeholders variable matched with spread operator
				"logger IN ({$placeholders})",
				...$args['loggers']
			);
		}

		// Add where for a single user ID.
		if ( isset( $args['user'] ) ) {
			$inner_where[] = $wpdb->prepare(
				'id IN ( SELECT history_id FROM ' . $contexts_table_name . ' AS c WHERE c.key = %s AND c.value = %s )', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				'_user_id',
				$args['user']
			);
		}

		// Users, array with user ids.
		if ( isset( $args['users'] ) ) {
			// Create placeholders for prepared statement.
			$placeholders = implode( ', ', array_fill( 0, count( $args['users'] ), '%s' ) );

			// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Dynamic placeholders in $placeholders variable matched with spread operator
			$inner_where[] = $wpdb->prepare(
				'id IN ( SELECT history_id FROM ' . $contexts_table_name . ' AS c WHERE c.key = %s AND c.value IN (' . $placeholders . ') )', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				'_user_id',
				...$args['users']
			);
		}

		// If only_sticky is true, only return sticky events.
		if ( ! empty( $args['only_sticky'] ) ) {
			$inner_where[] = sprintf(
				'id IN ( SELECT history_id FROM %1$s AS c WHERE c.key = \'_sticky\' )',
				$contexts_table_name
			);
		}

		// Add where clause for initiator filter.
		if ( isset( $args['initiator'] ) ) {
			if ( is_string( $args['initiator'] ) ) {
				// Single initiator.
				$inner_where[] = sprintf(
					'initiator = \'%s\'',
					esc_sql( $args['initiator'] )
				);
			} elseif ( is_array( $args['initiator'] ) && ! empty( $args['initiator'] ) ) {
				// Multiple initiators - use IN clause.
				$escaped_initiators = array_map( 'esc_sql', $args['initiator'] );
				$inner_where[]      = sprintf(
					'initiator IN (\'%s\')',
					implode( '\',\'', $escaped_initiators )
				);
			}
		}

		// Add where clause for context filters.
		if ( ! empty( $args['context_filters'] ) && is_array( $args['context_filters'] ) ) {
			foreach ( $args['context_filters'] as $context_key => $context_value ) {
				$inner_where[] = $wpdb->prepare(
					'id IN ( SELECT history_id FROM ' . $contexts_table_name . ' AS c WHERE c.key = %s AND c.value = %s )', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					$context_key,
					$context_value
				);
			}
		}

		// Exclusion filters - add NOT IN clauses to hide events matching these criteria.
		// Exclusions are processed after inclusions so they can filter out included items.
		// When both inclusion and exclusion filters are specified, the SQL AND logic ensures exclusion takes precedence.
		// "exclude_search" - text to exclude from search results.
		$inner_where = $this->add_exclude_search_to_inner_where_query( $inner_where, $args );

		// "exclude_loglevels" - array with log levels to exclude.
		if ( ! empty( $args['exclude_loglevels'] ) ) {
			// Create placeholders for prepared statement.
			$placeholders  = implode( ', ', array_fill( 0, count( $args['exclude_loglevels'] ), '%s' ) );
			$inner_where[] = $wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Dynamic placeholders in $placeholders variable matched with spread operator
				"level NOT IN ({$placeholders})",
				...$args['exclude_loglevels']
			);
		}

		// "exclude_loggers" - array with logger slugs to exclude.
		if ( ! empty( $args['exclude_loggers'] ) ) {
			// Create placeholders for prepared statement.
			$placeholders  = implode( ', ', array_fill( 0, count( $args['exclude_loggers'] ), '%s' ) );
			$inner_where[] = $wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Dynamic placeholders in $placeholders variable matched with spread operator
				"logger NOT IN ({$placeholders})",
				...$args['exclude_loggers']
			);
		}

		// "exclude_messages" - array with logger:message pairs to exclude.
		if ( ! empty( $args['exclude_messages'] ) && is_array( $args['exclude_messages'] ) ) {
			$sql_exclude_messages_parts = [];

			foreach ( $args['exclude_messages'] as $exclude_logger_slug => $exclude_logger_messages ) {
				foreach ( $exclude_logger_messages as $one_exclude_message_key ) {
					$sql_exclude_messages_parts[] = $wpdb->prepare(
						'NOT ( logger = %s AND contexts.value = %s )',
						$exclude_logger_slug,
						$one_exclude_message_key
					);
				}
			}

			if ( ! empty( $sql_exclude_messages_parts ) ) {
				$inner_where[] = implode( ' AND ', $sql_exclude_messages_parts );
			}
		}

		// "exclude_user" - single user ID to exclude.
		if ( isset( $args['exclude_user'] ) ) {
			$inner_where[] = $wpdb->prepare(
				'id NOT IN ( SELECT history_id FROM ' . $contexts_table_name . ' AS c WHERE c.key = %s AND c.value = %s )', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				'_user_id',
				$args['exclude_user']
			);
		}

		// "exclude_users" - array with user IDs to exclude.
		if ( isset( $args['exclude_users'] ) && ! empty( $args['exclude_users'] ) ) {
			// Create placeholders for prepared statement.
			$placeholders = implode( ', ', array_fill( 0, count( $args['exclude_users'] ), '%s' ) );
			// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Dynamic placeholders in $placeholders variable matched with spread operator
			$inner_where[] = $wpdb->prepare(
				'id NOT IN ( SELECT history_id FROM ' . $contexts_table_name . ' AS c WHERE c.key = %s AND c.value IN (' . $placeholders . ') )', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				'_user_id',
				...$args['exclude_users']
			);
		}

		// "exclude_initiator" - initiator(s) to exclude.
		if ( isset( $args['exclude_initiator'] ) ) {
			if ( is_string( $args['exclude_initiator'] ) ) {
				// Single initiator.
				$inner_where[] = sprintf(
					'initiator != \'%s\'',
					esc_sql( $args['exclude_initiator'] )
				);
			} elseif ( is_array( $args['exclude_initiator'] ) && ! empty( $args['exclude_initiator'] ) ) {
				// Multiple initiators - use NOT IN clause.
				$escaped_initiators = array_map( 'esc_sql', $args['exclude_initiator'] );
				$inner_where[]      = sprintf(
					'initiator NOT IN (\'%s\')',
					implode( '\',\'', $escaped_initiators )
				);
			}
		}

		/**
		 * Filter the default boxes to output in the sidebar
		 *
		 * @since 4.17.0
		 *
		 * @param array $inner_where The inner where array.
		 * @param array $args The arguments passed to the query.
		 */
		$inner_where = apply_filters( 'simple_history/log_query_inner_where_array', $inner_where, $args );

		return $inner_where;
	}

	/**
	 * Get outer where clause.
	 *
	 * @param array $args Arguments.
	 * @return array<string> Where clauses.
	 */
	protected function get_outer_where( $args ) {
		global $wpdb;

		$outer_where = [];

		// messages.
		if ( ! empty( $args['messages'] ) ) {
			// Create sql where based on loggers and messages.
			$sql_messages_where_parts = [];

			foreach ( $args['messages'] as $logger_slug => $logger_messages ) {
				// Create placeholders for prepared statement.
				$placeholders = implode( ', ', array_fill( 0, count( $logger_messages ), '%s' ) );

				// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Dynamic placeholders in $placeholders variable matched with spread operator
				$sql_messages_where_parts[] = $wpdb->prepare(
					'(h.logger = %s AND context_message_key IN (' . $placeholders . '))', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					$logger_slug,
					...$logger_messages
				);
			}

			// Join all parts with OR.
			$sql_messages_where = '(' . implode( ' OR ', $sql_messages_where_parts ) . ')';
			$outer_where[]      = $sql_messages_where;
		}

		return $outer_where;
	}

	/**
	 * Get db engine in use.
	 * Default is "mysql", which supports both MySQL and MariaDB.
	 * Can also return "sqlite", which means that plugin
	 * https://wordpress.org/plugins/sqlite-database-integration/ is in use,
	 * and we need to use SQLite specific SQL at some places.
	 *
	 * @return string "mysql" or "sqlite"
	 */
	public static function get_db_engine() {
		return defined( 'DB_ENGINE' ) && constant( 'DB_ENGINE' ) === 'sqlite' ? 'sqlite' : 'mysql';
	}

	/**
	 * Add search queries to inner where array.
	 *
	 * @param array $inner_where Existing inner where query.
	 * @param array $args Arguments passed to API.
	 * @return array $inner_where, possibly modified.
	 */
	private function add_search_to_inner_where_query( $inner_where, $args ) {
		if ( ! isset( $args['search'] ) ) {
			return $inner_where;
		}

		global $wpdb;

		$contexts_table_name = Simple_History::get_instance()->get_contexts_table_name();

		/** @var string $str_search_conditions
		 * Example:
		 * ```
		 * ( message LIKE "%uppdaterade%"  AND message LIKE "%tillägg%"  )
		 * OR ( logger LIKE "%uppdaterade%"  AND logger LIKE "%tillägg%"  )
		 * OR ( level LIKE "%uppdaterade%"  AND level LIKE "%tillägg%"  )
		 * OR (
		 *   id IN ( SELECT history_id FROM wp_simple_history_contexts AS c WHERE c.value LIKE "%uppdaterade%" ) AND
		 *   id IN ( SELECT history_id FROM wp_simple_history_contexts AS c WHERE c.value LIKE "%tillägg%" )
		 *  )
		 * ```
		 */
		$str_search_conditions = '';

		$arr_search_words = preg_split( '/[\s,]+/', $args['search'] );

		// create array of all searched words
		// split both spaces and commas and such.
		$arr_sql_like_cols = [ 'message', 'logger', 'level' ];

		foreach ( $arr_sql_like_cols as $one_col ) {
			$str_sql_search_words = '';

			foreach ( $arr_search_words as $one_search_word ) {
				$str_like = esc_sql( $wpdb->esc_like( $one_search_word ) );

				$str_sql_search_words .= sprintf(
					' AND %1$s LIKE \'%2$s\' ',
					$one_col,
					"%{$str_like}%"
				);
			}

			$str_sql_search_words = ltrim( $str_sql_search_words, ' AND ' );

			$str_search_conditions .= "\n" . sprintf(
				' OR ( %1$s ) ',
				$str_sql_search_words
			);
		}

		// Remove first " OR ".
		$str_search_conditions = preg_replace( '/^OR /', ' ', trim( $str_search_conditions ) );

		// Also search contexts. Adds a OR for the first context and AND for the rest.
		$str_search_conditions .= "\n   OR ( ";
		foreach ( $arr_search_words as $one_search_word ) {
			$str_like = esc_sql( $wpdb->esc_like( $one_search_word ) );

			$str_search_conditions .= "\n" . sprintf(
				' id IN ( SELECT history_id FROM %1$s AS c WHERE c.value LIKE \'%2$s\' ) AND ',
				$contexts_table_name, // 1
				'%' . $str_like . '%' // 2
			);
		}

		$str_search_conditions  = preg_replace( '/ AND $/', '', $str_search_conditions );
		$str_search_conditions .= "\n   ) "; // end OR for contexts.

		/**
		 * Search for a string in the log messages with support for translated message.
		 * https://github.com/bonny/WordPress-Simple-History/issues/277
		 */
		$logger_messages_with_search_string_matches = $this->match_logger_messages_with_search( $args['search'] );
		foreach ( $logger_messages_with_search_string_matches as $one_logger_message ) {
			$str_search_conditions .= "\n OR ( logger = '{$one_logger_message['logger_slug']}' AND contexts.value = '{$one_logger_message['message_key']}' ) ";
		}

		$inner_where[] = "\n(\n {$str_search_conditions} \n ) ";

		return $inner_where;
	}

	/**
	 * Add exclude search queries to inner where array.
	 *
	 * This method builds WHERE conditions to exclude events containing the specified search terms.
	 * It mirrors the logic of add_search_to_inner_where_query() but uses NOT logic instead.
	 *
	 * @param array $inner_where Existing inner where query.
	 * @param array $args Arguments passed to API.
	 * @return array $inner_where, possibly modified.
	 */
	private function add_exclude_search_to_inner_where_query( $inner_where, $args ) {
		if ( ! isset( $args['exclude_search'] ) || empty( $args['exclude_search'] ) ) {
			return $inner_where;
		}

		global $wpdb;

		$contexts_table_name = Simple_History::get_instance()->get_contexts_table_name();

		/** @var string $str_exclude_conditions
		 * Example SQL for exclude_search "error warning":
		 * ```
		 * NOT (
		 *   ( message LIKE "%error%" AND message LIKE "%warning%" )
		 *   OR ( logger LIKE "%error%" AND logger LIKE "%warning%" )
		 *   OR ( level LIKE "%error%" AND level LIKE "%warning%" )
		 *   OR (
		 *     id IN ( SELECT history_id FROM contexts WHERE value LIKE "%error%" ) AND
		 *     id IN ( SELECT history_id FROM contexts WHERE value LIKE "%warning%" )
		 *   )
		 * )
		 * ```
		 */
		$str_exclude_conditions = '';

		$arr_exclude_words = preg_split( '/[\s,]+/', $args['exclude_search'] );

		// Create array of all searched words, split by spaces and commas.
		$arr_sql_like_cols = [ 'message', 'logger', 'level' ];

		foreach ( $arr_sql_like_cols as $one_col ) {
			$str_sql_exclude_words = '';

			foreach ( $arr_exclude_words as $one_exclude_word ) {
				$str_like = esc_sql( $wpdb->esc_like( $one_exclude_word ) );

				$str_sql_exclude_words .= sprintf(
					' AND %1$s LIKE \'%2$s\' ',
					$one_col,
					"%{$str_like}%"
				);
			}

			$str_sql_exclude_words = ltrim( $str_sql_exclude_words, ' AND ' );

			$str_exclude_conditions .= "\n" . sprintf(
				' OR ( %1$s ) ',
				$str_sql_exclude_words
			);
		}

		// Remove first " OR ".
		$str_exclude_conditions = preg_replace( '/^OR /', ' ', trim( $str_exclude_conditions ) );

		// Also exclude from contexts. Adds a OR for the first context and AND for the rest.
		$str_exclude_conditions .= "\n   OR ( ";
		foreach ( $arr_exclude_words as $one_exclude_word ) {
			$str_like = esc_sql( $wpdb->esc_like( $one_exclude_word ) );

			$str_exclude_conditions .= "\n" . sprintf(
				' id IN ( SELECT history_id FROM %1$s AS c WHERE c.value LIKE \'%2$s\' ) AND ',
				$contexts_table_name, // 1
				'%' . $str_like . '%' // 2
			);
		}

		$str_exclude_conditions  = preg_replace( '/ AND $/', '', $str_exclude_conditions );
		$str_exclude_conditions .= "\n   ) "; // end OR for contexts.

		/**
		 * Exclude events matching translated logger messages.
		 */
		$logger_messages_with_exclude_string_matches = $this->match_logger_messages_with_search( $args['exclude_search'] );
		foreach ( $logger_messages_with_exclude_string_matches as $one_logger_message ) {
			$str_exclude_conditions .= "\n OR ( logger = '{$one_logger_message['logger_slug']}' AND contexts.value = '{$one_logger_message['message_key']}' ) ";
		}

		// Wrap everything in NOT to exclude matching events.
		$inner_where[] = "\nNOT (\n {$str_exclude_conditions} \n ) ";

		return $inner_where;
	}

	/**
	 * Check if a date string is in the specified format.
	 *
	 * Example:
	 * Function returns true for dates like "2024-03-29" and false for dates like "2024/03/29"
	 * or "29-03-2024".
	 *
	 * @param string $date_string The date string to check.
	 * @param string $format The format to check the date string against. Default is "Y-m-d" (which means for example "2024-03-29").
	 * @return bool True if the date string is in the specified format, false otherwise.
	 */
	protected function is_valid_date_format( $date_string, $format = 'Y-m-d' ) {
		$d = \DateTime::createFromFormat( $format, $date_string );
		return $d && $d->format( $format ) === $date_string;
	}

	/**
	 * Get all sticky events. Does not take user capability into account.
	 *
	 * @return array<int> Array of sticky event IDs.
	 */
	protected function get_sticky_events() {
		global $wpdb;

		$simple_history = Simple_History::get_instance();
		$contexts_table = $simple_history->get_contexts_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$results = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT history_id, value FROM %i WHERE `key` = %s',
				$contexts_table,
				'_sticky'
			)
		);

		return $results;
	}
}
