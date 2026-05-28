<?php
/**
 * Facebook Graph API handler.
 */
class WSP_Facebook {

	const API_BASE = 'https://graph.facebook.com/v18.0';

	/**
	 * Publish a post to Facebook.
	 *
	 * @param int    $post_id
	 * @param string $caption
	 * @return array { success: bool, social_id: string|null, error: string|null }
	 */
	public function publish( $post_id, $caption ) {
		$token_mgr   = new WSP_Token_Manager();
		$page_token  = $token_mgr->get_token( 'facebook', 'page_token' );
		$settings    = get_option( 'wsp_settings', array() );
		$page_id     = isset( $settings['facebook']['page_id'] ) ? sanitize_text_field( $settings['facebook']['page_id'] ) : '';

		if ( ! $page_token || ! $page_id ) {
			return $this->error( 'Facebook page token or page ID not configured.' );
		}

		$image_url = WSP_Helpers::get_featured_image_url( $post_id );
		$post_url  = get_permalink( $post_id );

		if ( $image_url ) {
			return $this->publish_photo( $page_id, $page_token, $image_url, $caption );
		}

		return $this->publish_feed( $page_id, $page_token, $caption, $post_url );
	}

	/**
	 * Post to /{PAGE_ID}/feed with a link.
	 */
	private function publish_feed( $page_id, $token, $message, $link ) {
		$url      = self::API_BASE . '/' . rawurlencode( $page_id ) . '/feed';
		$response = wp_remote_post( $url, array(
			'timeout' => 15,
			'body'    => array(
				'message'      => $message,
				'link'         => $link,
				'access_token' => $token,
			),
		) );
		return $this->parse_response( $response );
	}

	/**
	 * Post to /{PAGE_ID}/photos with an image URL.
	 */
	private function publish_photo( $page_id, $token, $image_url, $caption ) {
		$url      = self::API_BASE . '/' . rawurlencode( $page_id ) . '/photos';
		$response = wp_remote_post( $url, array(
			'timeout' => 15,
			'body'    => array(
				'url'          => $image_url,
				'caption'      => $caption,
				'access_token' => $token,
			),
		) );
		return $this->parse_response( $response );
	}

	/**
	 * Parse a wp_remote_post() response into the standard result array.
	 *
	 * @param array|WP_Error $response
	 * @return array
	 */
	private function parse_response( $response ) {
		if ( is_wp_error( $response ) ) {
			error_log( '[WSP Facebook] HTTP error: ' . $response->get_error_message() );
			return $this->error( $response->get_error_message() );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( 200 !== (int) $code ) {
			$api_msg = isset( $data['error']['message'] ) ? $data['error']['message'] : "HTTP {$code}";
			error_log( '[WSP Facebook] API error: ' . $body );

			if ( 401 === (int) $code ) {
				return $this->error( 'Facebook token expired or invalid. ' . $api_msg );
			}
			if ( 403 === (int) $code ) {
				return $this->error( 'Facebook permission denied. ' . $api_msg );
			}
			return $this->error( $api_msg );
		}

		$social_id = isset( $data['id'] ) ? $data['id'] : null;
		return array( 'success' => true, 'social_id' => $social_id, 'error' => null );
	}

	/** @return array */
	private function error( $msg ) {
		return array( 'success' => false, 'social_id' => null, 'error' => $msg );
	}

	/**
	 * Test the connection by fetching basic page info.
	 *
	 * @return array { success: bool, message: string }
	 */
	public function test_connection() {
		$token_mgr  = new WSP_Token_Manager();
		$page_token = $token_mgr->get_token( 'facebook', 'page_token' );
		$settings   = get_option( 'wsp_settings', array() );
		$page_id    = isset( $settings['facebook']['page_id'] ) ? sanitize_text_field( $settings['facebook']['page_id'] ) : '';

		if ( ! $page_token || ! $page_id ) {
			return array( 'success' => false, 'message' => __( 'Page token or Page ID not configured.', 'wp-social-publisher' ) );
		}

		$url      = self::API_BASE . '/' . rawurlencode( $page_id ) . '?fields=name&access_token=' . rawurlencode( $page_token );
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

		$name = isset( $data['name'] ) ? $data['name'] : __( 'Unknown page', 'wp-social-publisher' );
		return array( 'success' => true, 'message' => sprintf( __( 'Connected to page: %s', 'wp-social-publisher' ), $name ) );
	}
}
