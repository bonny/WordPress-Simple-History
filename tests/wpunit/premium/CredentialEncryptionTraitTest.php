<?php

use Helper\PremiumTestCase;
use Simple_History\AddOns\Pro\Channels\Credential_Encryption_Trait;

/**
 * Issue 292, gap 2: the credential encryption trait protects every stored
 * channel secret and had no tests.
 *
 * Run with:
 * docker compose run --rm php-cli vendor/bin/codecept run wpunit premium/CredentialEncryptionTraitTest
 *
 * @group premium
 */
class CredentialEncryptionTraitTest extends PremiumTestCase {
	/** @var object Anonymous class using the trait, exposing its protected methods. */
	private $subject;

	public function setUp(): void {
		parent::setUp();
		$this->activate_premium();

		$this->subject = new class() {
			use Credential_Encryption_Trait;

			/** @var array<string,string> Stand-in for the channel's stored settings. */
			public $settings = [];

			public function get_setting( $key, $default = '' ) {
				return $this->settings[ $key ] ?? $default;
			}

			public function encrypt( string $value ): string {
				return $this->encrypt_credential( $value );
			}

			public function decrypt( string $value ): string {
				return $this->decrypt_credential( $value );
			}

			public function is_encrypted( string $value ): bool {
				return $this->is_encrypted_value( $value );
			}

			public function sanitize( string $new_value, string $key ): string {
				return $this->sanitize_encrypted_credential( $new_value, $key );
			}
		};
	}

	public function test_round_trip_returns_the_original_credential() {
		$secret    = 'xoxb-1234-abcd_EFGH:token@example';
		$encrypted = $this->subject->encrypt( $secret );

		$this->assertNotSame( $secret, $encrypted );
		$this->assertSame( $secret, $this->subject->decrypt( $encrypted ) );
	}

	public function test_each_encryption_uses_a_fresh_iv() {
		$a = $this->subject->encrypt( 'same-secret' );
		$b = $this->subject->encrypt( 'same-secret' );

		$this->assertNotSame( $a, $b );
		$this->assertSame( 'same-secret', $this->subject->decrypt( $a ) );
		$this->assertSame( 'same-secret', $this->subject->decrypt( $b ) );
	}

	public function test_empty_values_stay_empty() {
		$this->assertSame( '', $this->subject->encrypt( '' ) );
		$this->assertSame( '', $this->subject->decrypt( '' ) );
	}

	public function test_is_encrypted_value_detects_only_our_ciphertext() {
		$this->assertTrue( $this->subject->is_encrypted( $this->subject->encrypt( 'secret' ) ) );
		$this->assertFalse( $this->subject->is_encrypted( 'plain-text-token_123' ) );
		$this->assertFalse( $this->subject->is_encrypted( base64_encode( 'short' ) ) );
		$this->assertFalse( $this->subject->is_encrypted( base64_encode( random_bytes( 48 ) ) ) );
	}

	public function test_legacy_plain_text_credential_is_returned_as_is() {
		$this->assertSame( 'my-legacy_token@host:1', $this->subject->decrypt( 'my-legacy_token@host:1' ) );
	}

	public function test_value_encrypted_with_another_key_decrypts_to_empty_not_garbage() {
		$other_key = hash( 'sha256', 'not-this-sites-key', true );
		$iv        = random_bytes( 16 );
		$cipher    = openssl_encrypt( 'salt' . 'secret', 'AES-256-CBC', $other_key, OPENSSL_RAW_DATA, $iv );
		$foreign   = base64_encode( $iv . $cipher );

		$this->assertSame( '', $this->subject->decrypt( $foreign ) );
		$this->assertFalse( $this->subject->is_encrypted( $foreign ) );
	}

	public function test_sanitize_encrypts_a_new_plain_text_value() {
		$stored = $this->subject->sanitize( 'new-secret', 'token' );

		$this->assertTrue( $this->subject->is_encrypted( $stored ) );
		$this->assertSame( 'new-secret', $this->subject->decrypt( $stored ) );
	}

	public function test_sanitize_does_not_double_encrypt_an_already_encrypted_value() {
		$encrypted = $this->subject->encrypt( 'secret' );

		$this->assertSame( $encrypted, $this->subject->sanitize( $encrypted, 'token' ) );
	}

	public function test_sanitize_keeps_the_existing_encrypted_value_when_the_field_is_left_empty() {
		$existing                          = $this->subject->encrypt( 'secret' );
		$this->subject->settings['token'] = $existing;

		$this->assertSame( $existing, $this->subject->sanitize( '', 'token' ) );
	}

	public function test_sanitize_migrates_a_legacy_plain_text_value_when_the_field_is_left_empty() {
		$this->subject->settings['token'] = 'legacy-plain_token';

		$stored = $this->subject->sanitize( '', 'token' );

		$this->assertTrue( $this->subject->is_encrypted( $stored ) );
		$this->assertSame( 'legacy-plain_token', $this->subject->decrypt( $stored ) );
	}

	public function test_sanitize_returns_empty_when_nothing_is_stored_and_nothing_is_entered() {
		$this->assertSame( '', $this->subject->sanitize( '', 'token' ) );
	}
}
