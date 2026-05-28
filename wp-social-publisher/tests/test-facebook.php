<?php
/**
 * Tests for WSP_Facebook.
 * Uses wp_remote_post mock via pre_http_request filter.
 */
class Test_WSP_Facebook extends WP_UnitTestCase {

	private $fb;
	private $mock_response;

	public function set_up() {
		parent::set_up();
		$this->fb = new WSP_Facebook();

		// Store valid credentials in settings.
		$token_mgr = new WSP_Token_Manager();
		$settings  = get_option( 'wsp_settings', array() );
		$settings['facebook']['page_id']    = '123456789';
		$settings['facebook']['page_token'] = $token_mgr->encrypt( 'fake-page-token' );
		update_option( 'wsp_settings', $settings );

		add_filter( 'pre_http_request', array( $this, 'mock_http_request' ), 10, 3 );
	}

	public function tear_down() {
		remove_filter( 'pre_http_request', array( $this, 'mock_http_request' ) );
		parent::tear_down();
	}

	public function mock_http_request( $preempt, $args, $url ) {
		return $this->mock_response;
	}

	public function test_successful_publish_returns_social_id() {
		$this->mock_response = array(
			'response' => array( 'code' => 200 ),
			'body'     => json_encode( array( 'id' => '123456789_987654321' ) ),
			'headers'  => array(),
		);

		$post_id = self::factory()->post->create();
		$result  = $this->fb->publish( $post_id, 'Test caption' );

		$this->assertTrue( $result['success'] );
		$this->assertSame( '123456789_987654321', $result['social_id'] );
		$this->assertNull( $result['error'] );
	}

	public function test_401_returns_token_expired_error() {
		$this->mock_response = array(
			'response' => array( 'code' => 401 ),
			'body'     => json_encode( array( 'error' => array( 'message' => 'Invalid OAuth token' ) ) ),
			'headers'  => array(),
		);

		$post_id = self::factory()->post->create();
		$result  = $this->fb->publish( $post_id, 'Test caption' );

		$this->assertFalse( $result['success'] );
		$this->assertStringContainsString( 'expired', $result['error'] );
	}

	public function test_403_returns_permission_error() {
		$this->mock_response = array(
			'response' => array( 'code' => 403 ),
			'body'     => json_encode( array( 'error' => array( 'message' => 'Forbidden' ) ) ),
			'headers'  => array(),
		);

		$post_id = self::factory()->post->create();
		$result  = $this->fb->publish( $post_id, 'Test caption' );

		$this->assertFalse( $result['success'] );
		$this->assertStringContainsString( 'permission', $result['error'] );
	}

	public function test_featured_image_uses_photos_endpoint() {
		$this->mock_response = array(
			'response' => array( 'code' => 200 ),
			'body'     => json_encode( array( 'id' => 'photo_123' ) ),
			'headers'  => array(),
		);

		$post_id  = self::factory()->post->create();
		$image_id = self::factory()->attachment->create_upload_object(
			DIR_TESTDATA . '/images/test-image.png', $post_id
		);
		set_post_thumbnail( $post_id, $image_id );

		$captured_url = null;
		add_filter( 'pre_http_request', function( $pre, $args, $url ) use ( &$captured_url ) {
			$captured_url = $url;
			return $this->mock_response;
		}, 20, 3 );

		$this->fb->publish( $post_id, 'Test' );
		$this->assertStringContainsString( '/photos', $captured_url ?? '' );
	}

	public function test_no_credentials_returns_error() {
		$settings = get_option( 'wsp_settings', array() );
		$settings['facebook']['page_token'] = '';
		$settings['facebook']['page_id']    = '';
		update_option( 'wsp_settings', $settings );

		$post_id = self::factory()->post->create();
		$result  = $this->fb->publish( $post_id, 'Test' );

		$this->assertFalse( $result['success'] );
		$this->assertNotEmpty( $result['error'] );
	}
}
