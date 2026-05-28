<?php
/**
 * X (Twitter) OAuth 1.0a three-legged flow handler.
 *
 * Flow:
 * 1. Admin clicks "Connect X" → wsp_oauth_twitter_init
 *    → POST /oauth/request_token → redirect to authorize URL
 * 2. X redirects back → wsp_oauth_twitter_callback
 *    → POST /oauth/access_token → save tokens
 */
class WSP_OAuth_Twitter {

	const REQUEST_TOKEN_URL = 'https://api.twitter.com/oauth/request_token';
	const AUTHORIZE_URL     = 'https://api.twitter.com/oauth/authorize';
	const ACCESS_TOKEN_URL  = 'https://api.twitter.com/oauth/access_token';

	/**
	 * @return string
	 */
	public function callback_url() {
		return admin_url( 'admin-post.php?action=wsp_oauth_twitter_callback' );
	}

	/**
	 * Step 1 — get request token and redirect to X.
	 * Hooked to admin_post_wsp_oauth_twitter_init.
	 */
	public function init_oauth() {
		check_admin_referer( 'wsp_oauth_twitter_init', '_wpnonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'wp-social-publisher' ) );
		}

		$creds = $this->get_app_credentials();
		if ( is_wp_error( $creds ) ) {
			$this->redirect_with_error( $creds->get_error_message() );
		}

		// POST to /oauth/request_token.
		$response = wp_remote_post(
			self::REQUEST_TOKEN_URL,
			array(
				'timeout' => 15,
				'headers' => array(
					'Authorization' => $this->oauth_header(
						'POST',
						self::REQUEST_TOKEN_URL,
						array( 'oauth_callback' => $this->callback_url() ),
						$creds['consumer_key'],
						$creds['consumer_secret'],
						'', // no access token yet
						''  // no access token secret yet
					),
				),
				'body' => '',
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->redirect_with_error( $response->get_error_message() );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		if ( 200 !== (int) $code ) {
			$this->redirect_with_error( sprintf( __( 'X API error (HTTP %d): %s', 'wp-social-publisher' ), $code, $body ) );
		}

		parse_str( $body, $token_data );
		$oauth_token        = $token_data['oauth_token'] ?? '';
		$oauth_token_secret = $token_data['oauth_token_secret'] ?? '';

		if ( ! $oauth_token ) {
			$this->redirect_with_error( __( 'Failed to get request token from X.', 'wp-social-publisher' ) );
		}

		// Save the request token secret for use in the callback.
		set_transient( 'wsp_oauth_tw_secret_' . get_current_user_id(), $oauth_token_secret, 600 );

		wp_redirect( self::AUTHORIZE_URL . '?oauth_token=' . rawurlencode( $oauth_token ) );
		exit;
	}

	/**
	 * Step 2 — X redirects here after user authorizes.
	 * Hooked to admin_post_wsp_oauth_twitter_callback.
	 */
	public function handle_callback() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'wp-social-publisher' ) );
		}

		// Check if user denied.
		if ( isset( $_GET['denied'] ) ) {
			$this->redirect_with_error( __( 'X authorization was denied.', 'wp-social-publisher' ) );
		}

		$oauth_token    = sanitize_text_field( wp_unslash( $_GET['oauth_token'] ?? '' ) );
		$oauth_verifier = sanitize_text_field( wp_unslash( $_GET['oauth_verifier'] ?? '' ) );

		if ( ! $oauth_token || ! $oauth_verifier ) {
			$this->redirect_with_error( __( 'Missing OAuth token or verifier.', 'wp-social-publisher' ) );
		}

		$request_token_secret = get_transient( 'wsp_oauth_tw_secret_' . get_current_user_id() );
		if ( ! $request_token_secret ) {
			$this->redirect_with_error( __( 'Session expired. Please try connecting again.', 'wp-social-publisher' ) );
		}
		delete_transient( 'wsp_oauth_tw_secret_' . get_current_user_id() );

		$creds = $this->get_app_credentials();
		if ( is_wp_error( $creds ) ) {
			$this->redirect_with_error( $creds->get_error_message() );
		}

		// Exchange for permanent access token.
		$response = wp_remote_post(
			self::ACCESS_TOKEN_URL,
			array(
				'timeout' => 15,
				'headers' => array(
					'Authorization' => $this->oauth_header(
						'POST',
						self::ACCESS_TOKEN_URL,
						array( 'oauth_verifier' => $oauth_verifier ),
						$creds['consumer_key'],
						$creds['consumer_secret'],
						$oauth_token,
						$request_token_secret
					),
				),
				'body' => 'oauth_verifier=' . rawurlencode( $oauth_verifier ),
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->redirect_with_error( $response->get_error_message() );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		if ( 200 !== (int) $code ) {
			$this->redirect_with_error( sprintf( __( 'X API error (HTTP %d): %s', 'wp-social-publisher' ), $code, $body ) );
		}

		parse_str( $body, $access_data );
		$access_token        = $access_data['oauth_token'] ?? '';
		$access_token_secret = $access_data['oauth_token_secret'] ?? '';
		$screen_name         = $access_data['screen_name'] ?? '';

		if ( ! $access_token || ! $access_token_secret ) {
			$this->redirect_with_error( __( 'Failed to get access token from X.', 'wp-social-publisher' ) );
		}

		// Save tokens.
		$token_mgr = new WSP_Token_Manager();
		$settings  = get_option( 'wsp_settings', array() );
		$settings['twitter']['access_token']        = $access_token;
		$settings['twitter']['access_token_secret'] = $token_mgr->encrypt( $access_token_secret );
		$settings['twitter']['connected']           = true;
		$settings['twitter']['connected_name']      = '@' . $screen_name;
		update_option( 'wsp_settings', $settings );

		wp_redirect( admin_url( 'admin.php?page=wp-social-publisher&tab=twitter&oauth_success=1' ) );
		exit;
	}

	/**
	 * Disconnect X.
	 */
	public function handle_disconnect() {
		check_admin_referer( 'wsp_oauth_twitter_disconnect', '_wpnonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'wp-social-publisher' ) );
		}
		$this->disconnect();
		wp_redirect( admin_url( 'admin.php?page=wp-social-publisher&tab=twitter&oauth_disconnected=1' ) );
		exit;
	}

	public function disconnect() {
		$settings = get_option( 'wsp_settings', array() );
		$settings['twitter']['access_token']        = '';
		$settings['twitter']['access_token_secret'] = '';
		$settings['twitter']['connected']           = false;
		$settings['twitter']['connected_name']      = '';
		update_option( 'wsp_settings', $settings );
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/** @return array|WP_Error */
	private function get_app_credentials() {
		$token_mgr       = new WSP_Token_Manager();
		$settings        = get_option( 'wsp_settings', array() );
		$consumer_key    = $settings['twitter']['consumer_key'] ?? '';
		$consumer_secret = $token_mgr->get_token( 'twitter', 'consumer_secret' );

		if ( ! $consumer_key || ! $consumer_secret ) {
			return new WP_Error(
				'wsp_tw_config',
				__( 'Enter your X Consumer Key and Consumer Secret first, then save settings.', 'wp-social-publisher' )
			);
		}

		return compact( 'consumer_key', 'consumer_secret' );
	}

	/**
	 * Build OAuth 1.0a Authorization header.
	 *
	 * @param string $method
	 * @param string $url
	 * @param array  $extra_params   Additional OAuth params (e.g. oauth_callback, oauth_verifier)
	 * @param string $consumer_key
	 * @param string $consumer_secret
	 * @param string $token
	 * @param string $token_secret
	 * @return string
	 */
	private function oauth_header( $method, $url, $extra_params, $consumer_key, $consumer_secret, $token, $token_secret ) {
		$oauth = array_merge( array(
			'oauth_consumer_key'     => $consumer_key,
			'oauth_nonce'            => bin2hex( random_bytes( 16 ) ),
			'oauth_signature_method' => 'HMAC-SHA1',
			'oauth_timestamp'        => (string) time(),
			'oauth_version'          => '1.0',
		), $extra_params );

		if ( $token ) {
			$oauth['oauth_token'] = $token;
		}

		ksort( $oauth );

		$param_string = implode( '&', array_map(
			function( $k, $v ) { return $this->pct( $k ) . '=' . $this->pct( $v ); },
			array_keys( $oauth ),
			array_values( $oauth )
		) );

		$base   = strtoupper( $method ) . '&' . $this->pct( $url ) . '&' . $this->pct( $param_string );
		$key    = $this->pct( $consumer_secret ) . '&' . $this->pct( $token_secret );
		$sig    = base64_encode( hash_hmac( 'sha1', $base, $key, true ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		$oauth['oauth_signature'] = $sig;
		ksort( $oauth );

		$parts = array_map(
			function( $k, $v ) { return $this->pct( $k ) . '="' . $this->pct( $v ) . '"'; },
			array_keys( $oauth ),
			array_values( $oauth )
		);

		return 'OAuth ' . implode( ', ', $parts );
	}

	/** RFC 3986 percent encode. */
	private function pct( $str ) {
		return str_replace( array( '+', '%7E' ), array( '%20', '~' ), rawurlencode( $str ) );
	}

	/** @param string $message */
	private function redirect_with_error( $message ) {
		wp_redirect( admin_url( 'admin.php?page=wp-social-publisher&tab=twitter&oauth_error=' . rawurlencode( $message ) ) );
		exit;
	}
}
