<?php

use Simple_History\Helpers;
use Simple_History\Log_Query;
use Simple_History\Loggers\Logger;

class OccasionsTest extends \Codeception\TestCase\WPTestCase {
	function test_occasions() {
		$admin_user_id = $this->factory->user->create(
			array(
				'role' => 'administrator',
			)
		);

		wp_set_current_user( $admin_user_id );

		$context = array(
			'_occasionsID' => 'my_custom_occasions_id',
		);

		// First occasion.
		SimpleLogger()->notice(
			'Custom message with custom occasions notice 1',
			$context
		);

		SimpleLogger()->notice(
			'Custom message with custom occasions, notice 2',
			$context
		);

		$query_args = array(
			'posts_per_page' => 2,
		);

		$log_query = new Log_Query();
		$query_results = $log_query->query( $query_args );
		$this->assertEquals(
			2, 
			$query_results['log_rows'][0]->subsequentOccasions, // subsequentOccasions
			'One occasion'
		);
		
		// Second occasion.
		$context['_occasionsID'] = 'another_custom_occasions_id';

		$num_occasions_to_add = 10;
		for ($i = 0; $i < $num_occasions_to_add; $i++) {
			SimpleLogger()->notice(
				'Another custom message with custom occasions id ' . $i,
				$context
			);
		}

		$log_query = new Log_Query();
		$query_results = $log_query->query( $query_args );
		$this->assertEquals(
			$num_occasions_to_add, 
			$query_results['log_rows'][0]->subsequentOccasions, // subsequentOccasions
			$num_occasions_to_add . ' occasions'
		);
	}
}
