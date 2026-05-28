<?php
/**
 * LinkedIn UGC Posts API handler.
 */
class WSP_LinkedIn {

	const API_URL = 'https://api.linkedin.com/v2/ugcPosts';

	/**
	 * Publish a post to LinkedIn.
	 *
	 * @param int    $post_id
	 * @param string $caption
	 * @return array { success: bool, social_id: string|null, error: string|null }
	 */
	public function publish( $post_id, $caption ) {
		$token_mgr = new WSP_Token_Manager();
		$token     = $token_mgr->get_token( 'linkedin', 'access_token' );
		$settings  = get_option( 'wsp_settings', array() );

		$urn      = isset( $settings['linkedin']['urn'] )      ? sanitize_text_field( $settings['linkedin']['urn'] )      : '';
		$urn_type = isset( $settings['linkedin']['urn_type'] ) ? sanitize_text_field( $settings['linkedin']['urn_type'] ) : 'person';

		if ( ! $token || ! $urn ) {
			return $this->error( 'LinkedIn access token or URN not configured.' );
		}

		$author   = ( 'organization' === $urn_type )
			? 'urn:li:organization:' . $urn
			: 'urn:li:person:' . $urn;

		$post_url = get_permalink( $post_id );

		$payload = array(
			'author'          => $author,
			'lifecycleState'  => 'PUBLISHED',
			'specificContent' => array(
				'com.linkedin.ugc.ShareContent' => array(
					'shareCommentary'    => array( 'text' => $caption ),
					'shareMediaCategory' => 'ARTICLE',
					'media'              => array(
						array(
							'status'      => 'READY',
							'originalUrl' => $post_url,
						),
					),
				),
			),
			'visibility' => array(
				'com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC',
			),
		);

		$response = wp_remote_post( self::API_URL, array(
			'timeout' => 15,
			'headers' => array(
				'Authorization' => 'Bearer ' . $token,
				'Content-Type'  => 'application/json',
				'X-Restli-Protocol-Version' => '2.0.0',
			),
			'body' => wp_json_encode( $payload ),
		) );

		return $this->parse_response( $response );
	}

	/**
	 * @param array|WP_Error $response
	 * @return array
	 */
	private function parse_response( $response ) {
		if ( is_wp_error( $response ) ) {
			error_log( '[WSP LinkedIn] HTTP error: ' . $response->get_error_message() );
			return $this->error( $response->get_error_message() );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		// LinkedIn returns 201 on success.
		if ( ! in_array( (int) $code, array( 200, 201 ), true ) ) {
			$msg = isset( $data['message'] ) ? $data['message'] : "HTTP {$code}";
			error_log( '[WSP LinkedIn] API error: ' . $body );

			if ( 401 === (int) $code ) {
				return $this->error( 'LinkedIn token expired or invalid. ' . $msg );
			}
			if ( 403 === (int) $code ) {
				return $this->error( 'LinkedIn permission denied. ' . $msg );
			}
			return $this->error( $msg );
		}

		// ID is in the X-RestLi-Id response header or body 'id'.
		$headers   = wp_remote_retrieve_headers( $response );
		$social_id = isset( $headers['x-restli-id'] ) ? $headers['x-restli-id'] : ( isset( $data['id'] ) ? $data['id'] : null );

		return array( 'success' => true, 'social_id' => $social_id, 'error' => null );
	}

	/** @return array */
	private function error( $msg ) {
		return array( 'success' => false, 'social_id' => null, 'error' => $msg );
	}

	/**
	 * Test connection by fetching the authenticated user profile.
	 *
	 * @return array { success: bool, message: string }
	 */
	public function test_connection() {
		$token_mgr = new WSP_Token_Manager();
		$token     = $token_mgr->get_token( 'linkedin', 'access_token' );

		if ( ! $token ) {
			return array( 'success' => false, 'message' => __( 'LinkedIn access token not configured.', 'wp-social-publisher' ) );
		}

		$response = wp_remote_get( 'https://api.linkedin.com/v2/me', array(
			'timeout' => 10,
			'headers' => array(
				'Authorization' => 'Bearer ' . $token,
			),
		) );

		if ( is_wp_error( $response ) ) {
			return array( 'success' => false, 'message' => $response->get_error_message() );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== (int) $code ) {
			$msg = isset( $data['message'] ) ? $data['message'] : "HTTP {$code}";
			return array( 'success' => false, 'message' => $msg );
		}

		$first = isset( $data['localizedFirstName'] ) ? $data['localizedFirstName'] : '';
		$last  = isset( $data['localizedLastName'] )  ? $data['localizedLastName']  : '';
		return array( 'success' => true, 'message' => sprintf( __( 'Connected as %s', 'wp-social-publisher' ), trim( "$first $last" ) ) );
	}
}
