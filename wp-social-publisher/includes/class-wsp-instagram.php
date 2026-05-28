<?php
/**
 * Instagram Graph API handler.
 */
class WSP_Instagram {

	const API_BASE = 'https://graph.facebook.com/v18.0';

	/**
	 * Publish a post to Instagram (two-step: create container → publish).
	 *
	 * @param int    $post_id
	 * @param string $caption
	 * @return array { success: bool, social_id: string|null, error: string|null, status?: string }
	 */
	public function publish( $post_id, $caption ) {
		$image_url = WSP_Helpers::get_featured_image_url( $post_id );

		if ( ! $image_url ) {
			return array(
				'success'   => false,
				'social_id' => null,
				'error'     => __( 'No featured image found. Instagram requires an image.', 'wp-social-publisher' ),
				'status'    => 'skipped',
			);
		}

		if ( WSP_Helpers::is_local_url( $image_url ) ) {
			return array(
				'success'   => false,
				'social_id' => null,
				'error'     => __( 'Featured image URL is not publicly accessible (local/staging site). Instagram requires a public URL.', 'wp-social-publisher' ),
				'status'    => 'skipped',
			);
		}

		$token_mgr = new WSP_Token_Manager();
		$token     = $token_mgr->get_token( 'facebook', 'page_token' ); // Instagram uses FB page token
		$settings  = get_option( 'wsp_settings', array() );
		$user_id   = isset( $settings['instagram']['user_id'] ) ? sanitize_text_field( $settings['instagram']['user_id'] ) : '';

		if ( ! $token || ! $user_id ) {
			return $this->error( 'Instagram user ID or access token not configured.' );
		}

		// Step 1: Create media container.
		$container_id = $this->create_container( $user_id, $token, $image_url, $caption );
		if ( is_wp_error( $container_id ) ) {
			return $this->error( $container_id->get_error_message() );
		}
		if ( ! $container_id ) {
			return $this->error( 'Instagram: failed to create media container.' );
		}

		// Step 2: Publish the container.
		return $this->publish_container( $user_id, $token, $container_id );
	}

	/**
	 * POST /{IG_USER_ID}/media to create a container.
	 *
	 * @return string|WP_Error  Container creation_id or WP_Error.
	 */
	private function create_container( $user_id, $token, $image_url, $caption ) {
		$url      = self::API_BASE . '/' . rawurlencode( $user_id ) . '/media';
		$response = wp_remote_post( $url, array(
			'timeout' => 15,
			'body'    => array(
				'image_url'    => $image_url,
				'caption'      => $caption,
				'access_token' => $token,
			),
		) );

		if ( is_wp_error( $response ) ) {
			error_log( '[WSP Instagram] Container create HTTP error: ' . $response->get_error_message() );
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( 200 !== (int) $code ) {
			$msg = isset( $data['error']['message'] ) ? $data['error']['message'] : "HTTP {$code}";
			error_log( '[WSP Instagram] Container create error: ' . $body );
			return new WP_Error( 'instagram_container', $msg );
		}

		return isset( $data['id'] ) ? $data['id'] : null;
	}

	/**
	 * POST /{IG_USER_ID}/media_publish to publish the container.
	 *
	 * @return array
	 */
	private function publish_container( $user_id, $token, $creation_id ) {
		$url      = self::API_BASE . '/' . rawurlencode( $user_id ) . '/media_publish';
		$response = wp_remote_post( $url, array(
			'timeout' => 15,
			'body'    => array(
				'creation_id'  => $creation_id,
				'access_token' => $token,
			),
		) );

		if ( is_wp_error( $response ) ) {
			error_log( '[WSP Instagram] Publish HTTP error: ' . $response->get_error_message() );
			return $this->error( $response->get_error_message() );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( 200 !== (int) $code ) {
			$msg = isset( $data['error']['message'] ) ? $data['error']['message'] : "HTTP {$code}";
			error_log( '[WSP Instagram] Publish error: ' . $body );
			return $this->error( $msg );
		}

		$social_id = isset( $data['id'] ) ? $data['id'] : null;
		return array( 'success' => true, 'social_id' => $social_id, 'error' => null );
	}

	/** @return array */
	private function error( $msg ) {
		return array( 'success' => false, 'social_id' => null, 'error' => $msg );
	}

	/**
	 * Test connection by fetching basic IG account info.
	 *
	 * @return array { success: bool, message: string }
	 */
	public function test_connection() {
		$token_mgr = new WSP_Token_Manager();
		$token     = $token_mgr->get_token( 'facebook', 'page_token' );
		$settings  = get_option( 'wsp_settings', array() );
		$user_id   = isset( $settings['instagram']['user_id'] ) ? sanitize_text_field( $settings['instagram']['user_id'] ) : '';

		if ( ! $token || ! $user_id ) {
			return array( 'success' => false, 'message' => __( 'Instagram user ID or access token not configured.', 'wp-social-publisher' ) );
		}

		$url      = self::API_BASE . '/' . rawurlencode( $user_id ) . '?fields=username&access_token=' . rawurlencode( $token );
		$response = wp_remote_get( $url, array( 'timeout' => 10 ) );

		if ( is_wp_error( $response ) ) {
			return array( 'success' => false, 'message' => $response->get_error_message() );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== (int) $code ) {
			$msg = isset( $data['error']['message'] ) ? $data['error']['message'] : "HTTP {$code}";
			return array( 'success' => false, 'message' => $msg );
		}

		$username = isset( $data['username'] ) ? $data['username'] : __( 'unknown', 'wp-social-publisher' );
		return array( 'success' => true, 'message' => sprintf( __( 'Connected as @%s', 'wp-social-publisher' ), $username ) );
	}
}
