<?php
/**
 * Core dispatch orchestrator. Hooks into post publish and schedules async cron events.
 *
 * Architecture note: Gutenberg saves post meta AFTER rest_after_insert_post fires
 * (the meta is written by WP_REST_Posts_Controller after the action). To avoid race
 * conditions, we schedule a single wsp_process_post cron event at publish time.
 * By the time the cron fires (5+ seconds later), all meta is guaranteed to be in the DB.
 */
class WSP_Publisher {

	/** @var WSP_Loader */
	private $loader;

	/** @var string[] */
	private $platforms = array( 'facebook', 'instagram', 'linkedin', 'twitter' );

	public function __construct( WSP_Loader $loader ) {
		$this->loader = $loader;
		$this->register_hooks();
	}

	private function register_hooks() {
		// Detect fresh publish for both Classic Editor and Gutenberg.
		$this->loader->add_action( 'transition_post_status', $this, 'on_transition', 10, 3 );

		// Deferred processing cron — reads meta after it is fully written.
		$this->loader->add_action( 'wsp_process_post', $this, 'process_post', 10, 1 );

		// Per-platform cron callbacks.
		foreach ( $this->platforms as $platform ) {
			$this->loader->add_action( "wsp_publish_{$platform}", $this, "publish_to_{$platform}", 10, 1 );
		}

		// Log purge cron.
		$this->loader->add_action( 'wsp_purge_logs', $this, 'purge_logs' );
	}

	/**
	 * Fires on every post status transition. Schedules wsp_process_post
	 * when a post transitions TO publish for the first time.
	 *
	 * @param string  $new_status
	 * @param string  $old_status
	 * @param WP_Post $post
	 */
	public function on_transition( $new_status, $old_status, $post ) {
		if ( 'publish' !== $new_status || 'publish' === $old_status ) {
			return;
		}
		if ( wp_is_post_autosave( $post ) || wp_is_post_revision( $post ) ) {
			return;
		}

		$settings   = get_option( 'wsp_settings', array() );
		$post_types = isset( $settings['defaults']['enabled_post_types'] )
			? (array) $settings['defaults']['enabled_post_types']
			: array( 'post' );

		if ( ! in_array( $post->post_type, $post_types, true ) ) {
			return;
		}

		// Schedule a deferred job. Meta will be fully written by the time this fires.
		wp_schedule_single_event( time() + 10, 'wsp_process_post', array( $post->ID ) );
		error_log( "[WSP] Scheduled wsp_process_post for post_id={$post->ID}" );
	}

	/**
	 * Cron callback — runs 10+ seconds after publish, after all meta is written.
	 * Reads _wsp_channels, logs, and schedules per-platform publish events.
	 *
	 * @param int $post_id
	 */
	public function process_post( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post || 'publish' !== $post->post_status ) {
			error_log( "[WSP] process_post: post {$post_id} not found or not published" );
			return;
		}

		// Read channels directly from DB (cron is a fresh request, no cache issue).
		$channels = get_post_meta( $post_id, '_wsp_channels', true );
		error_log( '[WSP] process_post channels: ' . print_r( $channels, true ) );

		if ( empty( $channels ) || ! is_array( $channels ) ) {
			error_log( "[WSP] process_post: no channels for post {$post_id}" );
			return;
		}

		$captions = get_post_meta( $post_id, '_wsp_captions', true );
		if ( ! is_array( $captions ) ) {
			$captions = array();
		}

		foreach ( $channels as $platform ) {
			$platform = sanitize_key( $platform );
			if ( ! in_array( $platform, $this->platforms, true ) ) {
				continue;
			}
			if ( WSP_Post_Log::already_sent( $post_id, $platform ) ) {
				error_log( "[WSP] process_post: already sent to {$platform} for post {$post_id}" );
				continue;
			}

			$caption = isset( $captions[ $platform ] ) && $captions[ $platform ]
				? $captions[ $platform ]
				: $this->build_default_caption( $post, $platform );

			WSP_Post_Log::insert( $post_id, $platform, $caption );

			wp_schedule_single_event(
				time() + 5,
				"wsp_publish_{$platform}",
				array( $post_id )
			);
			error_log( "[WSP] process_post: scheduled wsp_publish_{$platform} for post {$post_id}" );
		}
	}

	/**
	 * Build a default caption from post title, excerpt, and settings hashtags.
	 *
	 * @param WP_Post $post
	 * @param string  $platform
	 * @return string
	 */
	private function build_default_caption( $post, $platform ) {
		$settings = get_option( 'wsp_settings', array() );
		$caption  = $post->post_title;
		$excerpt  = has_excerpt( $post->ID )
			? wp_strip_all_tags( $post->post_excerpt )
			: wp_trim_words( wp_strip_all_tags( $post->post_content ), 30 );

		if ( $excerpt ) {
			$caption .= "\n\n" . $excerpt;
		}

		$hashtags = isset( $settings['defaults']['hashtags'][ $platform ] )
			? trim( $settings['defaults']['hashtags'][ $platform ] )
			: '';
		if ( $hashtags ) {
			$caption .= "\n\n" . WSP_Helpers::normalise_hashtags( $hashtags );
		}

		if ( ! empty( $settings['defaults']['auto_append_url'] ) ) {
			$caption .= "\n\n" . get_permalink( $post->ID );
		}

		return $caption;
	}

	// -------------------------------------------------------------------------
	// Per-platform cron callbacks
	// -------------------------------------------------------------------------

	/** @param int $post_id */
	public function publish_to_facebook( $post_id ) {
		$this->dispatch( $post_id, 'facebook', new WSP_Facebook() );
	}

	/** @param int $post_id */
	public function publish_to_instagram( $post_id ) {
		$this->dispatch( $post_id, 'instagram', new WSP_Instagram() );
	}

	/** @param int $post_id */
	public function publish_to_linkedin( $post_id ) {
		$this->dispatch( $post_id, 'linkedin', new WSP_LinkedIn() );
	}

	/** @param int $post_id */
	public function publish_to_twitter( $post_id ) {
		$this->dispatch( $post_id, 'twitter', new WSP_Twitter() );
	}

	/**
	 * @param int    $post_id
	 * @param string $platform
	 * @param object $api
	 */
	private function dispatch( $post_id, $platform, $api ) {
		$log     = WSP_Post_Log::get_log_pending( $post_id, $platform );
		$caption = $log ? $log->caption : $this->build_default_caption( get_post( $post_id ), $platform );
		$result  = $api->publish( $post_id, $caption );
		WSP_Post_Log::update_result( $post_id, $platform, $result );
		error_log( "[WSP] dispatch {$platform} post {$post_id}: " . ( $result['success'] ? 'sent id=' . $result['social_id'] : 'FAILED ' . $result['error'] ) );
	}

	/**
	 * Purge old log entries. Hooked to wsp_purge_logs cron.
	 */
	public function purge_logs() {
		$settings = get_option( 'wsp_settings', array() );
		$days     = isset( $settings['defaults']['log_retention_days'] )
			? (int) $settings['defaults']['log_retention_days']
			: 90;
		WSP_Post_Log::purge_old_logs( $days );
	}
}
