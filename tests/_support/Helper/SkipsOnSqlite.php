<?php

namespace Helper;

use Simple_History\Log_Query;

/**
 * Skip a test when the suite is running against SQLite.
 *
 * Simple History supports both MySQL/MariaDB and SQLite, and a few things are
 * deliberately not implemented on SQLite. The suite can run on either engine
 * (`codecept run wpunit --env sqlite`), so those tests need to step aside
 * rather than fail.
 *
 * The check reads the same DB_ENGINE constant that Log_Query branches on, so a
 * test skips for exactly the condition that changes the behaviour it asserts.
 *
 * Use it for behaviour SQLite genuinely does not have. Do not use it to quiet a
 * test that only fails when the whole suite runs: that is the SQLite run's test
 * isolation leaking, not a property of SQLite, and hiding it loses real coverage.
 */
trait SkipsOnSqlite {
	/**
	 * Skip the running test when the database engine is SQLite.
	 *
	 * Say what SQLite does instead and name the code that decides it, so
	 * whoever implements the missing behaviour knows which skips to delete.
	 *
	 * @param string $why Why this test cannot pass on SQLite.
	 */
	protected function skip_on_sqlite( $why ) {
		if ( Log_Query::get_db_engine() !== 'sqlite' ) {
			return;
		}

		$this->markTestSkipped( 'SQLite: ' . $why );
	}
}
