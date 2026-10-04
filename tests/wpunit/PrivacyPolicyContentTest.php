<?php

use Simple_History\Services\Privacy_Data_Handler;

/**
 * Test the suggested privacy policy text.
 *
 * Run with:
 * docker compose run --rm php-cli vendor/bin/codecept run wpunit PrivacyPolicyContentTest
 */
class PrivacyPolicyContentTest extends \Codeception\TestCase\WPTestCase {
	public function test_content_covers_logged_data_ip_masking_and_retention() {
		$content = Privacy_Data_Handler::get_privacy_policy_content();

		$this->assertStringContainsString( '<p class="privacy-policy-tutorial">', $content );
		$this->assertStringContainsString( 'username, email address, user role', $content );
		$this->assertStringContainsString( '192.168.1.23 is saved as 192.168.1.x', $content );
		$this->assertStringContainsString( '[number] days', $content );
		$this->assertStringContainsString( 'ipinfo.io', $content );
	}

	public function test_content_covers_people_who_did_not_make_the_change() {
		$content = Privacy_Data_Handler::get_privacy_policy_content();

		$this->assertStringContainsString( 'commenter&#039;s name, email address, website, IP address', $content );
		$this->assertStringContainsString( 'creates, edits or deletes a user account', $content );
	}

	public function test_content_does_not_follow_settings() {
		// WordPress notifies admins whenever the text changes, so it must not depend on settings.
		$before = Privacy_Data_Handler::get_privacy_policy_content();

		add_filter( 'simple_history/privacy/anonymize_ip_address', '__return_false' );
		update_option( 'simple_history_retention_days', 7 );

		$this->assertSame( $before, Privacy_Data_Handler::get_privacy_policy_content() );

		remove_filter( 'simple_history/privacy/anonymize_ip_address', '__return_false' );
	}

	public function test_content_promises_no_erasure() {
		$content = Privacy_Data_Handler::get_privacy_policy_content();

		$this->assertStringNotContainsString( 'erase', strtolower( $content ) );
		$this->assertStringNotContainsString( 'anonymi', strtolower( $content ) );
	}
}
