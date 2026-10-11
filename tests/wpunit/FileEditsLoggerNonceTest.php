<?php

use Simple_History\Loggers\File_Edits_Logger;
use Simple_History\Simple_History;

/**
 * The file edits logger runs before WordPress core's own handler, so it must
 * check the same nonce core does. Otherwise a forged request that core rejects
 * is still logged as a file edit, with attacker-chosen contents.
 *
 * Run with:
 * docker compose run --rm php-cli vendor/bin/codecept run wpunit FileEditsLoggerNonceTest
 */
class FileEditsLoggerNonceTest extends \Codeception\TestCase\WPTestCase {
	/** @var File_Edits_Logger */
	private $logger;

	/** @var array Every File_Edits_Logger write this test saw. */
	private $writes = [];

	public function setUp(): void {
		parent::setUp();

		$this->logger = new File_Edits_Logger( Simple_History::get_instance() );

		$this->writes = [];
		add_filter( 'simple_history/log_insert_data_and_context', [ $this, 'capture_write' ], 10, 2 );

		wp_set_current_user( $this->factory->user->create( [ 'role' => 'administrator' ] ) );

		if ( is_multisite() ) {
			grant_super_admin( get_current_user_id() );
		}
	}

	public function tearDown(): void {
		remove_filter( 'simple_history/log_insert_data_and_context', [ $this, 'capture_write' ], 10 );
		$_POST = [];
		parent::tearDown();
	}

	public function capture_write( $data_and_context, $instance ) {
		if ( $instance instanceof File_Edits_Logger ) {
			$this->writes[] = $data_and_context;
		}

		return $data_and_context;
	}

	private function post_plugin_edit( $nonce ) {
		$file = 'simple-history/readme.txt';

		$_POST = [
			'file'       => $file,
			'plugin'     => 'simple-history/index.php',
			'newcontent' => 'Changed contents ' . wp_generate_password( 12, false ),
			'nonce'      => $nonce,
		];

		$this->logger->on_file_edit_ajax();
	}

	public function test_plugin_edit_with_valid_nonce_is_logged() {
		if ( ! current_user_can( 'edit_plugins' ) ) {
			$this->markTestSkipped( 'edit_plugins is not available in this environment (DISALLOW_FILE_EDIT?).' );
		}

		$this->post_plugin_edit( wp_create_nonce( 'edit-plugin_simple-history/readme.txt' ) );

		$this->assertCount( 1, $this->writes );
	}

	public function test_plugin_edit_without_valid_nonce_is_not_logged() {
		if ( ! current_user_can( 'edit_plugins' ) ) {
			$this->markTestSkipped( 'edit_plugins is not available in this environment (DISALLOW_FILE_EDIT?).' );
		}

		$this->post_plugin_edit( 'not-a-valid-nonce' );
		$this->post_plugin_edit( '' );
		$this->post_plugin_edit( wp_create_nonce( 'edit-plugin_some-other/file.php' ) );

		$this->assertCount( 0, $this->writes );
	}
}
