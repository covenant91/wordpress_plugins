<?php
/**
 * Tests for WSP_Token_Manager.
 */
class Test_WSP_Token_Manager extends WP_UnitTestCase {

	/** @var WSP_Token_Manager */
	private $mgr;

	public function set_up() {
		parent::set_up();
		$this->mgr = new WSP_Token_Manager();
	}

	public function test_encrypt_decrypt_roundtrip() {
		$original  = 'super-secret-token-12345';
		$encrypted = $this->mgr->encrypt( $original );

		$this->assertNotEmpty( $encrypted, 'Encrypted value should not be empty' );
		$this->assertNotSame( $original, $encrypted, 'Encrypted value should differ from plaintext' );

		$decrypted = $this->mgr->decrypt( $encrypted );
		$this->assertSame( $original, $decrypted, 'Decrypted value should match original' );
	}

	public function test_encrypt_empty_string() {
		$this->assertSame( '', $this->mgr->encrypt( '' ) );
		$this->assertSame( '', $this->mgr->decrypt( '' ) );
	}

	public function test_expiry_check_days_remaining() {
		$future_date = gmdate( 'Y-m-d', strtotime( '+30 days' ) );
		$settings    = get_option( 'wsp_settings', array() );
		$settings['linkedin']['token_expiry'] = $future_date;
		update_option( 'wsp_settings', $settings );

		$days = $this->mgr->check_expiry( 'linkedin' );

		$this->assertNotNull( $days, 'Days should not be null when expiry is set' );
		$this->assertGreaterThanOrEqual( 29, $days );
		$this->assertLessThanOrEqual( 30, $days );
	}

	public function test_expiry_check_returns_null_when_unset() {
		$settings = get_option( 'wsp_settings', array() );
		$settings['linkedin']['token_expiry'] = '';
		update_option( 'wsp_settings', $settings );

		$days = $this->mgr->check_expiry( 'linkedin' );
		$this->assertNull( $days, 'Days should be null when no expiry is stored' );
	}

	public function test_expiry_notice_sent_at_threshold() {
		// Reset any queued mail.
		reset_phpmailer_instance();

		$expiry_date = gmdate( 'Y-m-d', strtotime( '+5 days' ) );
		$settings    = get_option( 'wsp_settings', array() );
		$settings['linkedin']['token_expiry'] = $expiry_date;
		update_option( 'wsp_settings', $settings );

		$this->mgr->run_health_check();

		$mailer = tests_retrieve_phpmailer_instance();
		$this->assertNotEmpty( $mailer->mock_sent, 'Admin email should be sent when token expires within 7 days' );
		$this->assertStringContainsString( 'LinkedIn', $mailer->mock_sent[0]['subject'] );
	}

	public function test_get_token_returns_empty_for_missing_platform() {
		$token = $this->mgr->get_token( 'nonexistent_platform' );
		$this->assertSame( '', $token, 'Should return empty string for unknown platform' );
	}

	public function test_save_and_get_token() {
		$plain = 'my-test-access-token';
		$this->mgr->save_token( 'linkedin', $plain, 'access_token' );

		$retrieved = $this->mgr->get_token( 'linkedin', 'access_token' );
		$this->assertSame( $plain, $retrieved, 'Retrieved token should match saved token' );
	}
}
