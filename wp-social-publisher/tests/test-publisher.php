<?php
/**
 * Tests for WSP_Publisher.
 */
class Test_WSP_Publisher extends WP_UnitTestCase {

	/** @var WSP_Publisher */
	private $publisher;

	public function set_up() {
		parent::set_up();
		$loader          = new WSP_Loader();
		$this->publisher = new WSP_Publisher( $loader );
		$loader->run();
	}

	public function test_publish_fires_on_status_transition() {
		$post_id = self::factory()->post->create( array( 'post_status' => 'draft' ) );
		update_post_meta( $post_id, '_wsp_channels', array( 'facebook' ) );

		do_action( 'transition_post_status', 'publish', 'draft', get_post( $post_id ) );

		$crons = _get_cron_array();
		$found = false;
		foreach ( $crons as $events ) {
			if ( isset( $events[ 'wsp_publish_facebook' ] ) ) {
				foreach ( $events['wsp_publish_facebook'] as $event ) {
					if ( in_array( $post_id, $event['args'], true ) ) {
						$found = true;
					}
				}
			}
		}
		$this->assertTrue( $found, 'wsp_publish_facebook cron event should be scheduled' );
	}

	public function test_no_publish_on_autosave() {
		$post_id = self::factory()->post->create( array( 'post_status' => 'draft' ) );
		update_post_meta( $post_id, '_wsp_channels', array( 'facebook' ) );

		// Simulate autosave by adding the autosave filter.
		add_filter( 'wp_is_post_autosave', '__return_true' );
		do_action( 'transition_post_status', 'publish', 'draft', get_post( $post_id ) );
		remove_filter( 'wp_is_post_autosave', '__return_true' );

		// No log entries at all (cleaner check since table starts empty per test).
		$log_count = WSP_Post_Log::count_logs( array() );
		$this->assertSame( 0, $log_count, 'No log entry should be created on autosave' );
	}

	public function test_no_publish_on_revision() {
		$parent_id   = self::factory()->post->create();
		$revision_id = wp_save_post_revision( $parent_id );
		update_post_meta( $revision_id, '_wsp_channels', array( 'facebook' ) );

		do_action( 'transition_post_status', 'publish', 'draft', get_post( $revision_id ) );

		$log_count = WSP_Post_Log::count_logs( array() );
		$this->assertSame( 0, $log_count, 'No log entry should be created for revisions' );
	}

	public function test_correct_platforms_dispatched() {
		$post_id = self::factory()->post->create( array( 'post_status' => 'draft' ) );
		update_post_meta( $post_id, '_wsp_channels', array( 'facebook', 'linkedin' ) );

		do_action( 'transition_post_status', 'publish', 'draft', get_post( $post_id ) );

		$fb_logged  = WSP_Post_Log::count_logs( array( 'platform' => 'facebook' ) );
		$li_logged  = WSP_Post_Log::count_logs( array( 'platform' => 'linkedin' ) );
		$ig_logged  = WSP_Post_Log::count_logs( array( 'platform' => 'instagram' ) );

		$this->assertGreaterThan( 0, $fb_logged, 'Facebook log entry should exist' );
		$this->assertGreaterThan( 0, $li_logged, 'LinkedIn log entry should exist' );
		$this->assertSame( 0, $ig_logged, 'Instagram should NOT be logged when not selected' );
	}

	public function test_cron_events_scheduled() {
		$post_id = self::factory()->post->create( array( 'post_status' => 'draft' ) );
		update_post_meta( $post_id, '_wsp_channels', array( 'twitter' ) );

		do_action( 'transition_post_status', 'publish', 'draft', get_post( $post_id ) );

		$scheduled = wp_next_scheduled( 'wsp_publish_twitter', array( $post_id ) );
		$this->assertNotFalse( $scheduled, 'wsp_publish_twitter should be scheduled with correct post ID arg' );
	}

	public function test_no_duplicate_on_republish() {
		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		update_post_meta( $post_id, '_wsp_channels', array( 'facebook' ) );

		// Simulate an already-sent entry.
		WSP_Post_Log::insert( $post_id, 'facebook', 'first post' );
		global $wpdb;
		$wpdb->update( $wpdb->prefix . 'wsp_post_log', array( 'status' => 'sent' ), array( 'post_id' => $post_id ) );

		// Try to re-publish.
		do_action( 'transition_post_status', 'publish', 'draft', get_post( $post_id ) );

		$count = WSP_Post_Log::count_logs( array( 'platform' => 'facebook' ) );
		$this->assertSame( 1, $count, 'Should not create a duplicate log entry on re-publish' );
	}
}
