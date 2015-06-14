<?php

class SampleTest extends WP_UnitTestCase {

	function test_sample() {

		// replace this with some actual testing code
		$this->assertTrue( true );
	}

	function test_history_setup() {

		$this->assertTrue( class_exists("SimpleHistory") );
		$this->assertTrue( class_exists("SimpleHistoryLogQuery") );

		$this->assertTrue( defined("SIMPLE_HISTORY_VERSION") );
		$this->assertTrue( defined("SIMPLE_HISTORY_PATH") );
		$this->assertTrue( defined("SIMPLE_HISTORY_BASENAME") );
		$this->assertTrue( defined("SIMPLE_HISTORY_FILE") );

		$this->assertFalse( defined("SIMPLE_HISTORY_DEV") );

	}

}

