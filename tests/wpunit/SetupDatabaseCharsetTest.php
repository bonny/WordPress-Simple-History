<?php

use Simple_History\Simple_History;
use Simple_History\Services\Setup_Database;

/**
 * Verifies fresh-install table creation uses $wpdb->get_charset_collate() and
 * a prefix index on the contexts `key` column (191 chars) — matching WordPress
 * core's pattern since 4.2 so the tables can store 4-byte UTF-8 (emoji) and
 * stay portable between MariaDB/MySQL versions.
 *
 * The test forces the setup steps to replay from db_version 0 and captures the
 * CREATE TABLE SQL via filters, asserting on the strings without depending on
 * actual table creation. (The WP test framework rewrites CREATE TABLE to
 * CREATE TEMPORARY TABLE which would otherwise shadow base tables and make
 * direct schema inspection unreliable.)
 *
 * @coversDefaultClass Simple_History\Services\Setup_Database
 */
class SetupDatabaseCharsetTest extends \Codeception\TestCase\WPTestCase {
	/** @var Simple_History */
	private $simple_history;

	/** @var string[] CREATE TABLE statements captured during replay. */
	private $captured_creates = [];

	public function setUp(): void {
		parent::setUp();

		$this->simple_history = Simple_History::get_instance();
		$this->captured_creates = [];

		$this->replay_setup_and_capture_creates();
	}

	/**
	 * Re-runs every setup_* step from db_version 0 and captures every
	 * CREATE TABLE statement issued — both the ones routed through dbDelta
	 * and the ones run directly via $wpdb->query().
	 */
	private function replay_setup_and_capture_creates() {
		$capture_dbdelta = function ( $queries ) {
			foreach ( (array) $queries as $q ) {
				$this->captured_creates[] = (string) $q;
			}
			return $queries;
		};

		$capture_direct = function ( $query ) {
			$normalized = ltrim( (string) $query );

			if (
				stripos( $normalized, 'CREATE TABLE' ) === 0
				|| stripos( $normalized, 'CREATE TEMPORARY TABLE' ) === 0
			) {
				$this->captured_creates[] = (string) $query;
			}

			return $query;
		};

		add_filter( 'dbdelta_create_queries', $capture_dbdelta );
		add_filter( 'query', $capture_direct );

		try {
			delete_option( 'simple_history_db_version' );

			$setup_service = $this->simple_history->get_service( Setup_Database::class );
			$this->assertInstanceOf( Setup_Database::class, $setup_service );

			$setup_service->run_setup_steps();
		} finally {
			remove_filter( 'dbdelta_create_queries', $capture_dbdelta );
			remove_filter( 'query', $capture_direct );
		}
	}

	/**
	 * Find every captured CREATE TABLE statement for a given table name.
	 *
	 * Matches both `CREATE TABLE` and `CREATE TEMPORARY TABLE` (the latter is
	 * what the WP test framework rewrites to), with or without IF NOT EXISTS.
	 *
	 * @param string $table_name Fully qualified table name.
	 * @return string[] All captured statements targeting that table.
	 */
	private function find_creates_for( $table_name ) {
		$pattern = '/^CREATE\s+(?:TEMPORARY\s+)?TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?' . preg_quote( $table_name, '/' ) . '`?\b/i';

		return array_values( array_filter(
			$this->captured_creates,
			static fn ( $sql ) => preg_match( $pattern, ltrim( $sql ) ) === 1
		) );
	}

	/**
	 * Every CREATE TABLE the setup steps issue must end with the charset
	 * clause from $wpdb->get_charset_collate() — same call WP core uses in
	 * wp-admin/includes/schema.php.
	 */
	public function test_events_table_creates_include_charset_collate() {
		global $wpdb;

		$creates = $this->find_creates_for( $this->simple_history->get_events_table_name() );
		$this->assertNotEmpty( $creates, 'Setup should issue at least one CREATE for the events table when replayed from db_version 0' );

		$expected_charset = $wpdb->charset;
		$this->assertNotEmpty( $expected_charset, '$wpdb->charset should be set in the test env' );

		foreach ( $creates as $sql ) {
			$this->assertStringContainsString(
				"CHARACTER SET {$expected_charset}",
				$sql,
				"Events CREATE TABLE should include 'CHARACTER SET {$expected_charset}' from \$wpdb->get_charset_collate(); old code hardcoded utf8.\nSQL: {$sql}"
			);
		}
	}

	public function test_contexts_table_creates_include_charset_collate() {
		global $wpdb;

		$creates = $this->find_creates_for( $this->simple_history->get_contexts_table_name() );
		$this->assertNotEmpty( $creates, 'Setup should issue a CREATE for the contexts table when replayed from db_version 0' );

		$expected_charset = $wpdb->charset;

		foreach ( $creates as $sql ) {
			$this->assertStringContainsString(
				"CHARACTER SET {$expected_charset}",
				$sql,
				"Contexts CREATE TABLE should include 'CHARACTER SET {$expected_charset}'.\nSQL: {$sql}"
			);
		}
	}

	/**
	 * Belt and braces: on any host where $wpdb->charset is utf8mb4 (every
	 * modern install), the captured SQL must specifically be utf8mb4 — the
	 * old hardcoded 'CHARSET=utf8' string must never appear.
	 */
	public function test_create_table_sql_is_utf8mb4_when_supported() {
		global $wpdb;

		if ( $wpdb->charset !== 'utf8mb4' ) {
			$this->markTestSkipped( 'Host $wpdb->charset is not utf8mb4 — fix degrades gracefully and there is nothing stricter to assert here.' );
		}

		$all = implode( "\n", $this->captured_creates );

		$this->assertStringContainsString( 'CHARACTER SET utf8mb4', $all, 'CREATE TABLE statements must specify utf8mb4 on a utf8mb4 host' );
		$this->assertStringNotContainsString( 'CHARSET=utf8 ', $all, 'Old hardcoded CHARSET=utf8 must not appear' );
		$this->assertStringNotContainsString( "CHARSET=utf8;", $all, 'Old hardcoded CHARSET=utf8 must not appear' );
		$this->assertStringNotContainsString( 'CHARACTER SET=utf8 ', $all );
		$this->assertStringNotContainsString( "CHARACTER SET=utf8;", $all );
	}

	/**
	 * The contexts table's `key` index must be a 191-char prefix index so the
	 * entry stays under InnoDB's 767-byte limit on older row formats
	 * (255 * 4 = 1020 bytes is too large; 191 * 4 = 764 bytes fits).
	 */
	public function test_contexts_key_index_uses_191_prefix() {
		$creates = $this->find_creates_for( $this->simple_history->get_contexts_table_name() );
		$this->assertNotEmpty( $creates );

		$contexts_sql = implode( "\n", $creates );

		$this->assertMatchesRegularExpression(
			'/KEY\s+`key`\s*\(\s*`key`\s*\(191\)\s*\)/i',
			$contexts_sql,
			"Contexts `key` index should be prefixed to 191 chars (utf8mb4 needs floor(767/4) = 191).\nSQL:\n{$contexts_sql}"
		);
	}

	/**
	 * Sanity check that the prefix index doesn't break common access patterns:
	 * the loggers store short context keys like `_user_id`, and `WHERE key = ?`
	 * lookups must still use the index and return the right rows.
	 */
	public function test_short_keys_still_query_through_prefix_index() {
		SimpleLogger()->info(
			'Charset prefix-index sanity check',
			[ '_user_id' => 12345 ]
		);

		global $wpdb;
		$contexts_table = $this->simple_history->get_contexts_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT value FROM {$contexts_table} WHERE `key` = %s ORDER BY context_id DESC LIMIT 1",
				'_user_id'
			)
		);

		$this->assertNotNull( $row, 'Lookup by short context key should still find the row' );
		$this->assertSame( '12345', $row->value );
	}
}
