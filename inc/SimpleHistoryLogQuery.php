<?php

defined( 'ABSPATH' ) or die();

/**
 * Queries the Simple History Log
 */
class SimpleHistoryLogQuery {

	public function __construct() {

		/*
		if ( is_array($args) && ! empty($args) ) {

			return $this->query($args);

		}
		*/

	}

	public function query($args) {

		$defaults = array(

			// overview | occasions
			"type" => "overview",

			// Number of posts to show per page. 0 to show all.
			"posts_per_page" => 0,

			// Page to show. 1 = first page
			"paged" => 1,

			// Array. Only get posts that are in array.
			"post__in" => null,

			// array or html
			"format" => "array",

			// If max_id_first_page is set then only get rows
			// that have id equal or lower than this, to make
			"max_id_first_page" => null,

			// if since_id is set the rows returned will only be rows with an ID greater than (i.e. more recent than) since_id
			"since_id" => null,

			// date range
			// in unix datetime or Y-m-d H:i (or format compatible with strtotime())
			"date_from" => null,
			"date_to" => null,

			// months in format "Y-m"
			// array or comma separated
			"months" => null,

			// dates in format
			// "month:2015-06" for june 2015
			// "lastdays:7" for the last 7 days
			"dates" => null,

			// search
			"search" => null,

			// log levels to include. comma separated or as array. defaults to all.
			"loglevels" => null,

			// loggers to include. comma separated. defaults to all the user can read
			"loggers" => null,

			"messages" => null,

			// userID as number
			"user" => null,

			// user ids, comma separated
			"users" => null,

			// Can also contain:
			// occasionsCount
			// occasionsCountMaxReturn
			// occasionsID

			// If rows should be returned, or the actualy sql query used
			"returnQuery" => false

		);

		$args = wp_parse_args( $args, $defaults );
		// sf_d($args, "Run log query with args");

		$cache_key = "SimpleHistoryLogQuery_" . md5(serialize( $args )) . "_get_" . md5(serialize( $_GET )) . "_userid_" . get_current_user_id();
		$cache_group =  "simple-history-" . SimpleHistory::get_cache_incrementor();
		$arr_return = wp_cache_get($cache_key, $cache_group);

		if ( false !== $arr_return ) {
			return $arr_return;
		}

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
		$table_name_contexts = $wpdb->prefix . SimpleHistory::DBTABLE_CONTEXTS;

		$where = "1 = 1";
		$limit = "";
		$inner_where = "1 = 1";

		if ( "overview" === $args["type"] || "single" === $args["type"] ) {

			// Set variables used by query
			$sql_set_var = "SET @a:='', @counter:=1, @groupby:=0";
			$wpdb->query( $sql_set_var );

			// New and slightly faster query
			// 1 = where
			// 2 = limit
			// 3 = db name
			// 4 = where for inner calc sql query thingie
			// 5 = db name contexts
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

					# First/inner where
					WHERE
						%4$s

					ORDER BY id DESC, date DESC
				) AS t ON t.id = h.id

				WHERE
					# AND DATE >= DATE(NOW()) - INTERVAL 7 DAY
					# Outer/Second where
					%1$s

				GROUP BY repeated
				ORDER BY id DESC, date DESC
				%2$s
			';

			$sh = SimpleHistory::get_instance();

			// Only include loggers that the current user can view
			// @TODO: this causes error if user has no access to any logger at all
			$sql_loggers_user_can_view = $sh->getLoggersThatUserCanRead(get_current_user_id(), "sql");
			$inner_where .= " AND logger IN {$sql_loggers_user_can_view}";

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

			$where .= " AND h.id < " . (int) $args["logRowID"];
			$where .= " AND h.occasionsID = '" . esc_sql( $args["occasionsID"] ) . "'";

			if ( isset( $args["occasionsCountMaxReturn"] ) && (int) $args["occasionsCountMaxReturn"] < (int) $args["occasionsCount"] ) {

				// Limit to max nn events if occasionsCountMaxReturn is set.
				// Used in gui to prevent top many events returned, that can stall the browser.
				$limit = "LIMIT " . (int) $args["occasionsCountMaxReturn"];

			} else {

				// Regular limit that gets all occasions
				$limit = "LIMIT " . (int) $args["occasionsCount"];

			}

			// [logRowID] =&gt; 353
			// [occasionsID] =&gt; 73b06d5740d15e35079b6aa024255cb3
			// [occasionsCount] =&gt; 18

		}

		// Determine limit
		// Both posts_per_page and paged must be set
		$is_limit_query = ( is_numeric( $args["posts_per_page"] ) && $args["posts_per_page"] > 0 );
		$is_limit_query = $is_limit_query && ( is_numeric( $args["paged"] ) && $args["paged"] > 0 );
		if ($is_limit_query) {
			$limit_offset = ($args["paged"] - 1) * $args["posts_per_page"];
			$limit .= sprintf('LIMIT %1$d, %2$d', $limit_offset, $args["posts_per_page"] );
		}

		// Determine where
		if ( $args["post__in"] && is_array( $args["post__in"] ) ) {

			// make sure all vals are integers
			$args["post__in"] = array_map("intval", $args["post__in"]);

			$inner_where .= sprintf(' AND id IN (%1$s)', implode(",", $args["post__in"]));

		}

		// If max_id_first_page is then then only include rows
		// with id equal to or earlier
		if ( isset($args["max_id_first_page"]) && is_numeric($args["max_id_first_page"]) ) {

			$max_id_first_page = (int) $args["max_id_first_page"];
			$inner_where .= sprintf(
				' AND id <= %1$d',
				$max_id_first_page
			);

		}

		if ( isset($args["since_id"]) && is_numeric($args["since_id"]) ) {

			$since_id = (int) $args["since_id"];
			/*
			$where .= sprintf(
				' AND t.id > %1$d',
				$since_id
			);
			*/
			// Add where to inner because that's faster
			$inner_where .= sprintf(
				' AND id > %1$d',
				$since_id
			);

		}

		// Append date where
		if ( ! empty( $args["date_from"] ) ) {

			// date_from=2014-08-01
			// if date is not numeric assume Y-m-d H:i-format
			$date_from = $args["date_from"];
			if ( ! is_numeric( $date_from ) ) {
				$date_from = strtotime( $date_from );
			}

			$inner_where .= "\n" . sprintf(' AND date >= "%1$s"', esc_sql( date( 'Y-m-d H:i:s', $date_from ) ) );

		}

		if ( ! empty( $args["date_to"] ) ) {

			// date_to=2014-08-01
			// if date is not numeric assume Y-m-d H:i-format
			$date_to = $args["date_to"];
			if ( ! is_numeric( $date_to ) ) {
				$date_to = strtotime( $date_to );
			}

			$inner_where .= "\n" . sprintf(' AND date <= "%1$s"', date( 'Y-m-d H:i:s', $date_to ) );

		}

		/*
		AND date >= "2015-01-01 00:00:00" AND date <= "2015-01-31 00:00:00"
		*/
		#echo $inner_where;exit;

		// dats
		// if months they translate to $args["months"] because we already have support for that
		// can't use months and dates and the same time
		if ( ! empty( $args["dates"] ) ) {

			if ( is_array( $args["dates"] ) ) {
				$arr_dates = $args["dates"];
			} else {
				$arr_dates = explode(",", $args["dates"]);
			}

			$args["months"] = array();
			$args["lastdays"] = 0;

			foreach ( $arr_dates as $one_date ) {

				// If begins with "month:" then strip string and keep only month numbers
				if ( strpos($one_date, "month:") === 0 ) {
					$args["months"][] = substr($one_date, strlen("month:"));
				} else if ( strpos($one_date, "lastdays:") === 0 ) {
					// Only keep largest lastdays value
					$args["lastdays"] = max($args["lastdays"], substr($one_date, strlen("lastdays:")));
					#$args["lastdays"][] = substr($one_date, strlen("lastdays:"));
				}

			}


		}

		// lastdays, as int
		if ( ! empty( $args["lastdays"] ) ) {

			$inner_where .= sprintf('
				# lastdays
				AND date >= DATE(NOW()) - INTERVAL %d DAY
			', $args["lastdays"]);

		}

		// months, in format "Y-m"
		if ( ! empty( $args["months"] ) ) {

			if ( is_array( $args["months"] ) ) {
				$arr_months = $args["months"];
			} else {
				$arr_months = explode(",", $args["months"]);
			}

			$sql_months = '
				# sql_months
				AND (
			';

			foreach ( $arr_months as $one_month ) {

				// beginning of month
				// $ php -r ' echo date("Y-m-d H:i", strtotime("2014-08") ) . "\n";
				// >> 2014-08-01 00:00
				$date_month_beginning = strtotime( $one_month );

				// end of month
				// $ php -r ' echo date("Y-m-d H:i", strtotime("2014-08 + 1 month") ) . "\n";'
				// >> 2014-09-01 00:00
				$date_month_end = strtotime( "{$one_month} + 1 month" );

				$sql_months .= sprintf(
					'
					(
						date >= "%1$s"
						AND date <= "%2$s"
					)

					OR
					',
					date( 'Y-m-d H:i:s', $date_month_beginning ), // 1
					date( 'Y-m-d H:i:s', $date_month_end ) // 2

				);

			}

			$sql_months = trim($sql_months);
			$sql_months = rtrim($sql_months, " OR ");

			$sql_months .= '
				# end sql_months and wrap
				)
			';

			$inner_where .= $sql_months;
			// echo $inner_where;exit;

		}

		// search
		if ( ! empty( $args["search"] ) ) {

			$search_words = $args["search"];
			$str_search_conditions = "";
			$arr_search_words = preg_split("/[\s,]+/", $search_words);

			// create array of all searched words
			// split both spaces and commas and such
			$arr_sql_like_cols = array("message", "logger", "level");

			foreach ( $arr_sql_like_cols as $one_col ) {

				$str_sql_search_words = "";

				foreach ($arr_search_words as $one_search_word) {

					if ( method_exists($wpdb, "esc_like") ) {
						$str_like = esc_sql( $wpdb->esc_like( $one_search_word ) );
					} else {
						$str_like = esc_sql( like_escape( $one_search_word ) );
					}

					$str_sql_search_words .= sprintf(
						' AND %1$s LIKE "%2$s" ',
						$one_col,
						"%{$str_like}%"
					);

				}

				$str_sql_search_words = ltrim($str_sql_search_words, ' AND ');

				$str_search_conditions .= "\n" . sprintf(
					'   OR ( %1$s ) ',
					$str_sql_search_words
				);

			}

			$str_search_conditions = preg_replace('/^OR /', " ", trim($str_search_conditions));

			// also search contexts
			$str_search_conditions .= "\n   OR ( ";
			foreach ($arr_search_words as $one_search_word) {

				if ( method_exists($wpdb, "esc_like") ) {
					$str_like = esc_sql( $wpdb->esc_like( $one_search_word ) );
				} else {
					$str_like = esc_sql( like_escape( $one_search_word ) );
				}

				$str_search_conditions .= "\n" . sprintf(
					'	id IN ( SELECT history_id FROM %1$s AS c WHERE c.value LIKE "%2$s" ) AND ',
					$table_name_contexts, // 1
					"%" . $str_like . "%" // 2
				);

			}
			$str_search_conditions = preg_replace('/ AND $/', "", $str_search_conditions);

			$str_search_conditions .= "\n   ) "; // end or for contexts

			$inner_where .= "\n AND \n(\n {$str_search_conditions} \n ) ";

			#echo $inner_where;exit;

		}

		// log levels
		// comma separated
		// http://playground-root.ep/wp-admin/admin-ajax.php?action=simple_history_api&type=overview&format=&posts_per_page=10&paged=1&max_id_first_page=27273&SimpleHistoryLogQuery-showDebug=0&loglevel=error,warn
		if ( ! empty( $args["loglevels"] ) ) {

			$sql_loglevels = "";

			if ( is_array( $args["loglevels"] ) ) {
				$arr_loglevels = $args["loglevels"];
			} else {
				$arr_loglevels = explode(",", $args["loglevels"]);
			}

			foreach ( $arr_loglevels as $one_loglevel ) {
				$sql_loglevels .= sprintf(' "%s", ', esc_sql( $one_loglevel ));
			}

			if ( $sql_loglevels ) {
				$sql_loglevels = rtrim( $sql_loglevels, " ," );
				$sql_loglevels = "\n AND level IN ({$sql_loglevels}) ";
			}

			$inner_where .= $sql_loglevels;

		}

		// messages
		if ( ! empty( $args["messages"] ) ) {

			#print_r($args["messages"]);exit;
			/*
			Array
			(
			    [0] => SimpleCommentsLogger:anon_comment_added,SimpleCommentsLogger:user_comment_added,SimpleCommentsLogger:anon_trackback_added,SimpleCommentsLogger:user_trackback_added,SimpleCommentsLogger:anon_pingback_added,SimpleCommentsLogger:user_pingback_added,SimpleCommentsLogger:comment_edited,SimpleCommentsLogger:trackback_edited,SimpleCommentsLogger:pingback_edited,SimpleCommentsLogger:comment_status_approve,SimpleCommentsLogger:trackback_status_approve,SimpleCommentsLogger:pingback_status_approve,SimpleCommentsLogger:comment_status_hold,SimpleCommentsLogger:trackback_status_hold,SimpleCommentsLogger:pingback_status_hold,SimpleCommentsLogger:comment_status_spam,SimpleCommentsLogger:trackback_status_spam,SimpleCommentsLogger:pingback_status_spam,SimpleCommentsLogger:comment_status_trash,SimpleCommentsLogger:trackback_status_trash,SimpleCommentsLogger:pingback_status_trash,SimpleCommentsLogger:comment_untrashed,SimpleCommentsLogger:trackback_untrashed,SimpleCommentsLogger:pingback_untrashed,SimpleCommentsLogger:comment_deleted,SimpleCommentsLogger:trackback_deleted,SimpleCommentsLogger:pingback_deleted
			    [1] => SimpleCommentsLogger:SimpleCommentsLogger:comment_status_spam,SimpleCommentsLogger:trackback_status_spam,SimpleCommentsLogger:pingback_status_spam
			)
			*/

			// Array with loggers and messages
			$arr_loggers_and_messages = array();

			// Tranform from get'et format to our own internal format
			foreach ( (array) $args["messages"] as $one_arr_messages_row ) {

				$arr_row_messages = explode(",", $one_arr_messages_row);
				#print_r($arr_row_messages);#exit;
				/*

				Array
				(
				    [0] => SimpleCommentsLogger:anon_comment_added
				    [1] => SimpleCommentsLogger:user_comment_added
				    [2] => SimpleCommentsLogger:anon_trackback_added
				*/
				foreach ($arr_row_messages as $one_row_logger_and_message) {

					$arr_one_logger_and_message = explode(":", $one_row_logger_and_message);

					if ( ! isset( $arr_loggers_and_messages[$arr_one_logger_and_message[0]] ) ) {
						$arr_loggers_and_messages[$arr_one_logger_and_message[0]] = array();
					}

					$arr_loggers_and_messages[ $arr_one_logger_and_message[0] ][] = $arr_one_logger_and_message[1];

				}

			}

			// Now create sql where based on loggers and messages
			$sql_messages_where = " AND (";
			#print_r($arr_loggers_and_messages);exit;
			foreach ($arr_loggers_and_messages as $logger_slug => $logger_messages) {
				$sql_messages_where .= sprintf(
					'
					(
						h.logger = "%1$s"
						AND c1.value IN (%2$s)
					)
					OR ',
					esc_sql( $logger_slug ),
					"'" . implode("','", $logger_messages) . "'"
				);
			}
			// remove last or
			$sql_messages_where = preg_replace('/OR $/', "", $sql_messages_where);

			$sql_messages_where .= "\n )";
			#echo $sql_messages_where;exit;
			$where .= $sql_messages_where;

			/*
			print_r($arr_loggers_and_messages);exit;
			Array
			(
			    [SimpleCommentsLogger] => Array
			        (
			            [0] => anon_comment_added
			            [1] => user_comment_added
			            [2] => anon_trackback_added
			            [3] => user_trackback_added
			            [4] => anon_pingback_added
			            [5] => user_pingback_added
			            [6] => comment_edited
			            [7] => trackback_edited
			            [8] => pingback_edited
			            [9] => comment_status_approve
			            [10] => trackback_status_approve
			            [11] => pingback_status_approve
			            [12] => comment_status_hold
			            [13] => trackback_status_hold
			            [14] => pingback_status_hold
			            [15] => comment_status_spam
			            [16] => trackback_status_spam
			            [17] => pingback_status_spam
			            [18] => comment_status_trash
			            [19] => trackback_status_trash
			            [20] => pingback_status_trash
			            [21] => comment_untrashed
			            [22] => trackback_untrashed
			            [23] => pingback_untrashed
			            [24] => comment_deleted
			            [25] => trackback_deleted
			            [26] => pingback_deleted
			        )

			    [SimpleUserLogger] => Array
			        (
			            [0] => SimpleUserLogger
			            [1] => SimpleUserLogger
			        )

			)

			*/

		}

		// loggers
		// comma separated
		// http://playground-root.ep/wp-admin/admin-ajax.php?action=simple_history_api&type=overview&format=&posts_per_page=10&paged=1&max_id_first_page=27273&SimpleHistoryLogQuery-showDebug=0&loggers=SimpleCommentsLogger,SimpleCoreUpdatesLogger
		if ( ! empty( $args["loggers"] ) ) {

			$sql_loggers = "";
			if ( is_array( $args["loggers"] ) ) {
				$arr_loggers = $args["loggers"];
			} else {
				$arr_loggers = explode(",", $args["loggers"]);
			}

			#print_r($args["loggers"]);exit;
			#print_r($arr_loggers);exit;
			/*
			Example of version with logger + message keys
			Array
			(
			    [0] => SimpleUserLogger:user_created
			    [1] => SimpleUserLogger:user_deleted
			)
			*/

			foreach ( $arr_loggers as $one_logger ) {
				$sql_loggers .= sprintf(' "%s", ', esc_sql( $one_logger ));
			}

			if ( $sql_loggers ) {
				$sql_loggers = rtrim( $sql_loggers, " ," );
				$sql_loggers = "\n AND logger IN ({$sql_loggers}) ";
			}

			$inner_where .= $sql_loggers;

		}

		// user, a single userID
		if ( ! empty( $args["user"] ) && is_numeric( $args["user"] ) ) {

			$userID = (int) $args["user"];
			$sql_user = sprintf(
				'
				AND id IN ( SELECT history_id FROM %1$s AS c WHERE c.key = "_user_id" AND c.value = %2$s )
				',
				$table_name_contexts, // 1
				$userID // 2
			);

			$inner_where .= $sql_user;

		}

		// users, comma separated
		if ( ! empty( $args["users"] ) && is_string( $args["users"] ) ) {

			$users = explode(",", $args["users"]);
			$users = array_map("intval", $users);

			if ( $users ) {

				$users_in = implode(",", $users);

				$sql_user = sprintf(
					'
					AND id IN ( SELECT history_id FROM %1$s AS c WHERE c.key = "_user_id" AND c.value IN (%2$s) )
					',
					$table_name_contexts, // 1
					$users_in // 2
				);

				$inner_where .= $sql_user;

				#echo $inner_where;exit;
			}

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

		$sql = sprintf(
			$sql_tmpl, // sprintf template
			$where,  // 1
			$limit, // 2
			$table_name, // 3
			$inner_where,// 4
			$table_name_contexts // 5
		);


		/**
		 * Filter the final sql query
		 *
		 * @since 2.0
		 *
		 * @param string $sql
		 */
		$sql = apply_filters("simple_history/log_query_sql", $sql);

		// Remove comments below to debug query (includes query in json result)
		// $include_query_in_result = true;
		if (isset($_GET["SimpleHistoryLogQuery-showDebug"]) && $_GET["SimpleHistoryLogQuery-showDebug"]) {

			echo "<pre>";
			echo $sql_set_var;
			echo $sql;
			exit;

		}

		// Only return sql query
		if ( $args["returnQuery"] ) {
			return $sql;
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
			$sql_context = sprintf('SELECT * FROM %2$s WHERE history_id IN (%1$s)', join(",", $post_ids), $table_name_contexts);
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
		$min_id = null;
		$max_id = null;

		if ( sizeof($log_rows) ) {

			// Max id is simply the id of the first row
			$max_id = reset($log_rows)->id;

			// Min id = to find the lowest id we must take occasions into consideration
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

		wp_cache_set($cache_key, $arr_return, $cache_group);

		return $arr_return;

	} // query

} // class
