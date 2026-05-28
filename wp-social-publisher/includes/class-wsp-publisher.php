<?php
/**
 * Core dispatch orchestrator. Hooks into post publish and schedules async cron events.
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
		$this->loader->add_action( 'transition_post_status', $this, 'maybe_schedule_publish', 10, 3 );

		// Register per-platform cron handlers. Post ID is passed as a cron argument.
		foreach ( $this->platforms as $platform ) {
			$this->loader->add_action( "wsp_publish_{$platform}", $this, "publish_to_{$platform}", 10, 1 );
		}

		// Log purge cron.
		$this->loader->add_action( 'wsp_purge_logs', $this, 'purge_logs' );
	}

	/**
	 * Fired on every post status transition. Schedules async publish only when appropriate.
	 *
	 * @param string  $new_status
	 * @param string  $old_status
	 * @param WP_Post $post
	 */
	public function maybe_schedule_publish( $new_status, $old_status, $post ) {
		// Only act on transitions TO 'publish'.
		if ( 'publish' !== $new_status ) {
			return;
		}
		// Skip re-publishing already-published posts.
		if ( 'publish' === $old_status ) {
			return;
		}
		if ( wp_is_post_autosave( $post ) || wp_is_post_revision( $post ) ) {
			return;
		}

		$settings     = get_option( 'wsp_settings', array() );
		$post_types   = isset( $settings['defaults']['enabled_post_types'] )
			? (array) $settings['defaults']['enabled_post_types']
			: array( 'post' );

		if ( ! in_array( $post->post_type, $post_types, true ) ) {
			return;
		}

		$channels = get_post_meta( $post->ID, '_wsp_channels', true );
		if ( empty( $channels ) || ! is_array( $channels ) ) {
			return;
		}

		$captions = get_post_meta( $post->ID, '_wsp_captions', true );
		if ( ! is_array( $captions ) ) {
			$captions = array();
		}

		foreach ( $channels as $platform ) {
			$platform = sanitize_key( $platform );
			if ( ! in_array( $platform, $this->platforms, true ) ) {
				continue;
			}
			// Idempotency: skip if already sent.
			if ( WSP_Post_Log::already_sent( $post->ID, $platform ) ) {
				continue;
			}

			$caption = isset( $captions[ $platform ] ) ? $captions[ $platform ] : '';
			if ( empty( $caption ) ) {
				$caption = $this->build_default_caption( $post, $platform );
			}

			WSP_Post_Log::insert( $post->ID, $platform, $caption );

			// 5-second delay prevents the publish action from timing out.
			wp_schedule_single_event(
				time() + 5,
				"wsp_publish_{$platform}",
				array( $post->ID )
			);
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
		$settings  = get_option( 'wsp_settings', array() );
		$caption   = $post->post_title;
		$excerpt   = has_excerpt( $post->ID )
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

		$auto_url = isset( $settings['defaults']['auto_append_url'] )
			? (bool) $settings['defaults']['auto_append_url']
			: true;
		if ( $auto_url ) {
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
	 * Call the API class and update the log.
	 *
	 * @param int    $post_id
	 * @param string $platform
	 * @param object $api      Must implement publish( $post_id, $caption ): array
	 */
	private function dispatch( $post_id, $platform, $api ) {
		$log = WSP_Post_Log::get_log_pending( $post_id, $platform );
		$caption = $log ? $log->caption : $this->build_default_caption( get_post( $post_id ), $platform );
		$result  = $api->publish( $post_id, $caption );
		WSP_Post_Log::update_result( $post_id, $platform, $result );
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
