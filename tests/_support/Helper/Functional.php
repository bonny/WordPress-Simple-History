<?php

namespace Helper;

use Codeception\Module;

/**
 * Custom actions for functional tests.
 * All public methods declared here will be available in $I.
 */
class Functional extends Module {

	/**
	 * Drop a database table.
	 *
	 * @param string $table_name The table name (with prefix).
	 * @return void
	 */
	public function dropTable( string $table_name ): void {
		/** @var \lucatume\WPBrowser\Module\WPDb $wpdb */
		$wpdb = $this->getModule( 'lucatume\WPBrowser\Module\WPDb' );
		$pdo  = $wpdb->_getDbh();
		$pdo->exec( "DROP TABLE IF EXISTS {$table_name}" );
	}

	/**
	 * Drop Simple History tables.
	 *
	 * Convenience method for auto-recovery tests.
	 *
	 * @return void
	 */
	public function dropSimpleHistoryTables(): void {
		$this->dropTable( 'wp_simple_history' );
		$this->dropTable( 'wp_simple_history_contexts' );
	}
}
