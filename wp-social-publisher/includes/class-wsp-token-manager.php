<?php
/**
 * Handles token encryption, storage, expiry checks, and admin notices.
 */
class WSP_Token_Manager {

	/** @var string[] */
	private $platforms = array( 'facebook', 'instagram', 'linkedin', 'twitter' );

	/**
	 * Register hooks via the loader.
	 *
	 * @param WSP_Loader $loader
	 */
	public function register_hooks( WSP_Loader $loader ) {
		$loader->add_action( 'wsp_token_check', $this, 'run_health_check' );
	}

	// -------------------------------------------------------------------------
	// Encryption helpers
	// -------------------------------------------------------------------------

	/**
	 * Derive a 32-byte key from WordPress secret constants.
	 *
	 * @return string
	 */
	private function get_key() {
		$auth_key        = defined( 'AUTH_KEY' )        ? AUTH_KEY        : 'fallback-auth-key';
		$secure_auth_key = defined( 'SECURE_AUTH_KEY' ) ? SECURE_AUTH_KEY : 'fallback-secure-key';
		return hash( 'sha256', $auth_key . $secure_auth_key, true ); // raw 32 bytes
	}

	/**
	 * Encrypt a plaintext value.
	 *
	 * @param string $value
	 * @return string  Base64-encoded IV+ciphertext, or empty string on failure.
	 */
	public function encrypt( $value ) {
		if ( '' === $value ) {
			return '';
		}
		$iv     = openssl_random_pseudo_bytes( 16 );
		$cipher = openssl_encrypt( $value, 'AES-256-CBC', $this->get_key(), OPENSSL_RAW_DATA, $iv );
		if ( false === $cipher ) {
			return '';
		}
		return base64_encode( $iv . $cipher ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Decrypt a previously encrypted value.
	 *
	 * @param string $encoded
	 * @return string  Plaintext, or empty string on failure.
	 */
	public function decrypt( $encoded ) {
		if ( '' === $encoded ) {
			return '';
		}
		$raw = base64_decode( $encoded, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		if ( false === $raw || strlen( $raw ) < 17 ) {
			return '';
		}
		$iv     = substr( $raw, 0, 16 );
		$cipher = substr( $raw, 16 );
		$plain  = openssl_decrypt( $cipher, 'AES-256-CBC', $this->get_key(), OPENSSL_RAW_DATA, $iv );
		return ( false === $plain ) ? '' : $plain;
	}

	// -------------------------------------------------------------------------
	// Token access
	// -------------------------------------------------------------------------

	/**
	 * Retrieve and decrypt a stored token.
	 *
	 * @param string $platform
	 * @param string $key       Sub-key within the platform settings, e.g. 'page_token'.
	 * @return string  Plaintext token, or empty string if missing.
	 */
	public function get_token( $platform, $key = 'access_token' ) {
		$settings = get_option( 'wsp_settings', array() );
		$encoded  = isset( $settings[ $platform ][ $key ] ) ? $settings[ $platform ][ $key ] : '';
		return $encoded ? $this->decrypt( $encoded ) : '';
	}

	/**
	 * Encrypt and save a token.
	 *
	 * @param string $platform
	 * @param string $token
	 * @param string $key
	 */
	public function save_token( $platform, $token, $key = 'access_token' ) {
		$settings = get_option( 'wsp_settings', array() );
		if ( ! isset( $settings[ $platform ] ) ) {
			$settings[ $platform ] = array();
		}
		$settings[ $platform ][ $key ] = $this->encrypt( $token );
		update_option( 'wsp_settings', $settings );
	}

	// -------------------------------------------------------------------------
	// Expiry management
	// -------------------------------------------------------------------------

	/**
	 * Return days until a platform token expires.
	 *
	 * @param string $platform
	 * @return int|null  Days remaining, or null if no expiry set.
	 */
	public function check_expiry( $platform ) {
		$settings = get_option( 'wsp_settings', array() );
		$expiry   = isset( $settings[ $platform ]['token_expiry'] ) ? $settings[ $platform ]['token_expiry'] : '';
		if ( ! $expiry ) {
			return null;
		}
		$ts   = strtotime( $expiry );
		$diff = $ts - time();
		return (int) floor( $diff / DAY_IN_SECONDS );
	}

	/**
	 * Send an admin email warning about an expiring token.
	 *
	 * @param string $platform
	 * @param int    $days_remaining
	 */
	public function send_expiry_notice( $platform, $days_remaining ) {
		$admin_email = get_option( 'admin_email' );
		$site_name   = get_bloginfo( 'name' );

		$subject = sprintf(
			/* translators: 1: site name, 2: platform name */
			__( '[%1$s] %2$s token expires in %3$d day(s)', 'wp-social-publisher' ),
			$site_name,
			ucfirst( $platform ),
			$days_remaining
		);

		$message = sprintf(
			/* translators: 1: platform name, 2: days remaining, 3: settings URL */
			__( "Your %1\$s access token expires in %2\$d day(s).\n\nPlease refresh it at: %3\$s", 'wp-social-publisher' ),
			ucfirst( $platform ),
			$days_remaining,
			admin_url( 'options-general.php?page=wp-social-publisher&tab=' . $platform )
		);

		wp_mail( $admin_email, $subject, $message );
	}

	/**
	 * Daily cron callback: check all platform token expiries.
	 */
	public function run_health_check() {
		foreach ( $this->platforms as $platform ) {
			$days = $this->check_expiry( $platform );
			if ( null !== $days && $days <= 7 ) {
				$this->send_expiry_notice( $platform, $days );
			}
		}
	}
}
