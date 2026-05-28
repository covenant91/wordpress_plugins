<?php
/**
 * Tests for WSP_Helpers utility functions.
 */
class Test_WSP_Helpers extends WP_UnitTestCase {

	// -------------------------------------------------------------------------
	// truncate_caption
	// -------------------------------------------------------------------------

	public function test_short_caption_unchanged() {
		$text   = 'Hello world';
		$result = WSP_Helpers::truncate_caption( $text, 100 );
		$this->assertSame( $text, $result );
	}

	public function test_truncation_respects_limit() {
		$text   = str_repeat( 'a ', 200 ); // 400 chars
		$result = WSP_Helpers::truncate_caption( $text, 280 );
		$this->assertLessThanOrEqual( 285, strlen( $result ), 'Truncated string + ellipsis should not vastly exceed limit' );
	}

	public function test_truncation_ends_with_ellipsis() {
		$text   = str_repeat( 'word ', 100 );
		$result = WSP_Helpers::truncate_caption( $text, 50 );
		$this->assertStringEndsWith( '…', $result );
	}

	// -------------------------------------------------------------------------
	// append_url
	// -------------------------------------------------------------------------

	public function test_url_appended_when_absent() {
		$caption = 'My post caption';
		$url     = 'https://example.com/my-post';
		$result  = WSP_Helpers::append_url( $caption, $url );
		$this->assertStringContainsString( $url, $result );
	}

	public function test_url_not_duplicated_when_present() {
		$url     = 'https://example.com/my-post';
		$caption = 'Check out ' . $url;
		$result  = WSP_Helpers::append_url( $caption, $url );
		$this->assertSame( 1, substr_count( $result, $url ), 'URL should appear exactly once' );
	}

	// -------------------------------------------------------------------------
	// normalise_hashtags
	// -------------------------------------------------------------------------

	public function test_hashtags_normalised_without_hash() {
		$result = WSP_Helpers::normalise_hashtags( 'wordpress, coding, webdev' );
		$this->assertSame( '#wordpress #coding #webdev', $result );
	}

	public function test_existing_hash_not_doubled() {
		$result = WSP_Helpers::normalise_hashtags( '#php, laravel' );
		$this->assertSame( '#php #laravel', $result );
	}

	public function test_invalid_chars_stripped() {
		$result = WSP_Helpers::normalise_hashtags( 'hello world!, foo-bar' );
		$this->assertSame( '#helloworld #foobar', $result );
	}

	public function test_empty_input_returns_empty() {
		$this->assertSame( '', WSP_Helpers::normalise_hashtags( '' ) );
	}

	// -------------------------------------------------------------------------
	// get_featured_image_url
	// -------------------------------------------------------------------------

	public function test_returns_null_when_no_thumbnail() {
		$post_id = self::factory()->post->create();
		$url     = WSP_Helpers::get_featured_image_url( $post_id );
		$this->assertNull( $url );
	}

	// -------------------------------------------------------------------------
	// is_local_url
	// -------------------------------------------------------------------------

	public function test_localhost_detected() {
		$this->assertTrue( WSP_Helpers::is_local_url( 'http://localhost/wp' ) );
	}

	public function test_local_tld_detected() {
		$this->assertTrue( WSP_Helpers::is_local_url( 'http://mysite.local/wp' ) );
	}

	public function test_public_url_not_local() {
		$this->assertFalse( WSP_Helpers::is_local_url( 'https://example.com/image.jpg' ) );
	}
}
