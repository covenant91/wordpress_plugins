<?php
/**
 * X (Twitter) API v2 handler with OAuth 1.0a signature built from scratch.
 */
class WSP_Twitter {

	const TWEET_URL  = 'https://api.twitter.com/2/tweets';
	const MEDIA_URL  = 'https://upload.twitter.com/1.1/media/upload.json';
	const TWEET_LIMIT = 280;

	/**
	 * Publish a tweet.
	 *
	 * @param int    $post_id
	 * @param string $caption
	 * @return array { success: bool, social_id: string|null, error: string|null }
	 */
	public function publish( $post_id, $caption ) {
		$creds = $this->get_credentials();
		if ( is_wp_error( $creds ) ) {
			return $this->error( $creds->get_error_message() );
		}

		$post_url = get_permalink( $post_id );
		$text     = $this->build_tweet_text( $caption, $post_url );

		$payload = array( 'text' => $text );

		// Attach image if available.
		$image_url = WSP_Helpers::get_featured_image_url( $post_id );
		if ( $image_url ) {
			$media_id = $this->upload_media( $image_url, $creds );
			if ( $media_id ) {
				$payload['media'] = array( 'media_ids' => array( $media_id ) );
			}
		}

		$body     = wp_json_encode( $payload );
		$auth     = $this->oauth_header( 'POST', self::TWEET_URL, array(), $creds );
		$response = wp_remote_post( self::TWEET_URL, array(
			'timeout' => 15,
			'headers' => array(
				'Authorization' => $auth,
				'Content-Type'  => 'application/json',
			),
			'body' => $body,
		) );

		$result = $this->parse_response( $response );

		if ( $result['success'] ) {
			$this->increment_monthly_count();
		}

		return $result;
	}

	// -------------------------------------------------------------------------
	// Tweet text helpers
	// -------------------------------------------------------------------------

	/**
	 * Build tweet text, truncating caption and appending URL if it fits.
	 * X counts URLs as 23 characters regardless of length.
	 *
	 * @param string $caption
	 * @param string $url
	 * @return string
	 */
	private function build_tweet_text( $caption, $url ) {
		$url_length    = 23; // X t.co wrapped URL length
		$separator     = ' ';
		$max_text_len  = self::TWEET_LIMIT - $url_length - strlen( $separator );

		if ( mb_strlen( $caption ) <= $max_text_len ) {
			return $caption . $separator . $url;
		}

		$truncated = mb_substr( $caption, 0, $max_text_len - 1 ) . '…';
		return $truncated . $separator . $url;
	}

	// -------------------------------------------------------------------------
	// Media upload
	// -------------------------------------------------------------------------

	/**
	 * Download an image from $url and upload it to X media endpoint.
	 *
	 * @param string $image_url
	 * @param array  $creds
	 * @return string|null  media_id_string or null on failure.
	 */
	private function upload_media( $image_url, $creds ) {
		// Fetch the image bytes.
		$img_response = wp_remote_get( $image_url, array( 'timeout' => 30 ) );
		if ( is_wp_error( $img_response ) ) {
			error_log( '[WSP Twitter] Image fetch error: ' . $img_response->get_error_message() );
			return null;
		}

		$img_body    = wp_remote_retrieve_body( $img_response );
		$content_type = wp_remote_retrieve_header( $img_response, 'content-type' );
		$b64         = base64_encode( $img_body ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode

		// Multipart upload to v1.1 endpoint (v2 media upload not yet available).
		$boundary = wp_generate_uuid4();
		$body_raw  = "--{$boundary}\r\n";
		$body_raw .= "Content-Disposition: form-data; name=\"media_data\"\r\n\r\n";
		$body_raw .= $b64 . "\r\n";
		$body_raw .= "--{$boundary}--\r\n";

		$auth     = $this->oauth_header( 'POST', self::MEDIA_URL, array(), $creds );
		$response = wp_remote_post( self::MEDIA_URL, array(
			'timeout' => 30,
			'headers' => array(
				'Authorization' => $auth,
				'Content-Type'  => 'multipart/form-data; boundary=' . $boundary,
			),
			'body' => $body_raw,
		) );

		if ( is_wp_error( $response ) ) {
			error_log( '[WSP Twitter] Media upload HTTP error: ' . $response->get_error_message() );
			return null;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		return isset( $data['media_id_string'] ) ? $data['media_id_string'] : null;
	}

	// -------------------------------------------------------------------------
	// OAuth 1.0a
	// -------------------------------------------------------------------------

	/**
	 * Build the OAuth 1.0a Authorization header.
	 *
	 * @param string $method    HTTP method (POST/GET)
	 * @param string $url       Full request URL (no query string)
	 * @param array  $params    Extra query/body params to include in base string
	 * @param array  $creds     Keys: consumer_key, consumer_secret, access_token, access_token_secret
	 * @return string           Value for the Authorization header
	 */
	private function oauth_header( $method, $url, $params, $creds ) {
		$oauth = array(
			'oauth_consumer_key'     => $creds['consumer_key'],
			'oauth_nonce'            => bin2hex( random_bytes( 16 ) ),
			'oauth_signature_method' => 'HMAC-SHA1',
			'oauth_timestamp'        => (string) time(),
			'oauth_token'            => $creds['access_token'],
			'oauth_version'          => '1.0',
		);

		// Merge all parameters for signature base string.
		$all_params = array_merge( $params, $oauth );
		ksort( $all_params );

		$param_string = implode( '&', array_map(
			function( $k, $v ) {
				return $this->percent_encode( $k ) . '=' . $this->percent_encode( $v );
			},
			array_keys( $all_params ),
			array_values( $all_params )
		) );

		$base_string = strtoupper( $method ) . '&'
			. $this->percent_encode( $url ) . '&'
			. $this->percent_encode( $param_string );

		$signing_key = $this->percent_encode( $creds['consumer_secret'] ) . '&'
			. $this->percent_encode( $creds['access_token_secret'] );

		$signature = base64_encode( hash_hmac( 'sha1', $base_string, $signing_key, true ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode

		$oauth['oauth_signature'] = $signature;
		ksort( $oauth );

		$header_parts = array_map(
			function( $k, $v ) {
				return $this->percent_encode( $k ) . '="' . $this->percent_encode( $v ) . '"';
			},
			array_keys( $oauth ),
			array_values( $oauth )
		);

		return 'OAuth ' . implode( ', ', $header_parts );
	}

	/**
	 * RFC 3986 percent-encode a string.
	 *
	 * @param string $str
	 * @return string
	 */
	private function percent_encode( $str ) {
		return str_replace( array( '+', '%7E' ), array( '%20', '~' ), rawurlencode( $str ) );
	}

	// -------------------------------------------------------------------------
	// Response / credentials helpers
	// -------------------------------------------------------------------------

	/**
	 * @return array|WP_Error
	 */
	private function get_credentials() {
		$token_mgr = new WSP_Token_Manager();
		$settings  = get_option( 'wsp_settings', array() );

		$consumer_key        = isset( $settings['twitter']['consumer_key'] ) ? sanitize_text_field( $settings['twitter']['consumer_key'] ) : '';
		$consumer_secret     = $token_mgr->get_token( 'twitter', 'consumer_secret' );
		$access_token        = isset( $settings['twitter']['access_token'] ) ? sanitize_text_field( $settings['twitter']['access_token'] ) : '';
		$access_token_secret = $token_mgr->get_token( 'twitter', 'access_token_secret' );

		if ( ! $consumer_key || ! $consumer_secret || ! $access_token || ! $access_token_secret ) {
			return new WP_Error( 'wsp_twitter_config', 'X (Twitter) credentials not fully configured.' );
		}

		return compact( 'consumer_key', 'consumer_secret', 'access_token', 'access_token_secret' );
	}

	/**
	 * @param array|WP_Error $response
	 * @return array
	 */
	private function parse_response( $response ) {
		if ( is_wp_error( $response ) ) {
			error_log( '[WSP Twitter] HTTP error: ' . $response->get_error_message() );
			return $this->error( $response->get_error_message() );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! in_array( (int) $code, array( 200, 201 ), true ) ) {
			$msg = isset( $data['detail'] ) ? $data['detail'] : ( isset( $data['title'] ) ? $data['title'] : "HTTP {$code}" );
			error_log( '[WSP Twitter] API error: ' . $body );
			return $this->error( $msg );
		}

		$social_id = isset( $data['data']['id'] ) ? $data['data']['id'] : null;
		return array( 'success' => true, 'social_id' => $social_id, 'error' => null );
	}

	/** @return array */
	private function error( $msg ) {
		return array( 'success' => false, 'social_id' => null, 'error' => $msg );
	}

	/**
	 * Increment the monthly tweet counter (stored as a transient).
	 */
	private function increment_monthly_count() {
		$key   = 'wsp_twitter_monthly_count_' . gmdate( 'Y_m' );
		$count = (int) get_transient( $key );
		set_transient( $key, $count + 1, MONTH_IN_SECONDS );
	}

	/**
	 * Get the current month's tweet count (for the admin dashboard).
	 *
	 * @return int
	 */
	public static function get_monthly_count() {
		$key = 'wsp_twitter_monthly_count_' . gmdate( 'Y_m' );
		return (int) get_transient( $key );
	}

	/**
	 * Test the connection by verifying credentials via a GET to /2/users/me.
	 *
	 * @return array { success: bool, message: string }
	 */
	public function test_connection() {
		$creds = $this->get_credentials();
		if ( is_wp_error( $creds ) ) {
			return array( 'success' => false, 'message' => $creds->get_error_message() );
		}

		$url      = 'https://api.twitter.com/2/users/me';
		$auth     = $this->oauth_header( 'GET', $url, array(), $creds );
		$response = wp_remote_get( $url, array(
			'timeout' => 10,
			'headers' => array( 'Authorization' => $auth ),
		) );

		if ( is_wp_error( $response ) ) {
			return array( 'success' => false, 'message' => $response->get_error_message() );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== (int) $code ) {
			$msg = isset( $data['detail'] ) ? $data['detail'] : "HTTP {$code}";
			return array( 'success' => false, 'message' => $msg );
		}

		$username = isset( $data['data']['username'] ) ? $data['data']['username'] : 'unknown';
		return array( 'success' => true, 'message' => sprintf( __( 'Connected as @%s', 'wp-social-publisher' ), $username ) );
	}
}
