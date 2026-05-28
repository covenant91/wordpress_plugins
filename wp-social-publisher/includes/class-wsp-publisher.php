<?php
/**
 * Core dispatch orchestrator. Hooks into post publish and schedules async cron events.
 */
class WSP_Publisher {

	/** @var WSP_Loader */
	private $loader;

	/** @var string[] */
	private $platforms = array( 'facebook', 'instagram', 'linkedin', 'twitter' );

	/**
	 * Stores post IDs that are mid-transition to 'publish' via REST API.
	 * Keyed by post ID, value is the previous status.
	 * @var string[]
	 */
	private $pending_rest_publish = array();

	public function __construct( WSP_Loader $loader ) {
		$this->loader = $loader;
		$this->register_hooks();
	}

	private function register_hooks() {
		// Step 1 (both editors): record when a post transitions TO publish.
		$this->loader->add_action( 'transition_post_status', $this, 'record_transition', 10, 3 );

		// Step 2a — Classic Editor: wp_after_insert_post fires after save_post
		// which writes meta synchronously before this hook.
		$this->loader->add_action( 'wp_after_insert_post', $this, 'dispatch_classic', 10, 4 );

		// Step 2b — Gutenberg REST API: rest_after_insert_{type} fires after
		// WP_REST_Posts_Controller::update_additional_fields_for_object() which
		// is where register_post_meta REST fields are written.
		$this->loader->add_action( 'rest_after_insert_post', $this, 'dispatch_rest', 10, 1 );

		// Register per-platform cron handlers.
		foreach ( $this->platforms as $platform ) {
			$this->loader->add_action( "wsp_publish_{$platform}", $this, "publish_to_{$platform}", 10, 1 );
		}

		// Log purge cron.
		$this->loader->add_action( 'wsp_purge_logs', $this, 'purge_logs' );
	}

	/**
	 * Record when any post transitions TO 'publish' (fires before meta is saved in REST).
	 *
	 * @param string  $new_status
	 * @param string  $old_status
	 * @param WP_Post $post
	 */
	public function record_transition( $new_status, $old_status, $post ) {
		if ( 'publish' === $new_status && 'publish' !== $old_status ) {
			$this->pending_rest_publish[ $post->ID ] = $old_status;
			error_log( "[WSP] record_transition post_id={$post->ID} {$old_status}→{$new_status} is_rest=" . ( defined( 'REST_REQUEST' ) && REST_REQUEST ? 'yes' : 'no' ) );
		}
	}

	/**
	 * Classic Editor dispatch — fires after wp_insert_post (meta already written).
	 * Skip REST requests; those are handled by dispatch_rest().
	 *
	 * @param int          $post_id
	 * @param WP_Post      $post
	 * @param bool         $update
	 * @param WP_Post|null $post_before
	 */
	public function dispatch_classic( $post_id, $post, $update, $post_before ) {
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return; // Gutenberg handled separately.
		}
		if ( ! isset( $this->pending_rest_publish[ $post_id ] ) ) {
			return;
		}
		unset( $this->pending_rest_publish[ $post_id ] );
		error_log( "[WSP] dispatch_classic post_id={$post_id}" );
		$this->schedule_for_post( $post );
	}

	/**
	 * Gutenberg REST dispatch — fires after all REST meta fields are written.
	 *
	 * @param WP_Post $post
	 */
	public function dispatch_rest( $post ) {
		if ( ! isset( $this->pending_rest_publish[ $post->ID ] ) ) {
			return;
		}
		unset( $this->pending_rest_publish[ $post->ID ] );
		error_log( "[WSP] dispatch_rest post_id={$post->ID}" );
		$this->schedule_for_post( $post );
	}

	/**
	 * Core scheduling logic — called by dispatch_classic() and dispatch_rest()
	 * once meta is confirmed to be written.
	 *
	 * @param WP_Post $post
	 */
	private function schedule_for_post( $post ) {
		if ( wp_is_post_autosave( $post ) || wp_is_post_revision( $post ) ) {
			error_log( '[WSP] SKIP: autosave or revision' );
			return;
		}

		$settings   = get_option( 'wsp_settings', array() );
		$post_types = isset( $settings['defaults']['enabled_post_types'] )
			? (array) $settings['defaults']['enabled_post_types']
			: array( 'post' );

		if ( ! in_array( $post->post_type, $post_types, true ) ) {
			error_log( "[WSP] SKIP: post type '{$post->post_type}' not in enabled types: " . implode( ',', $post_types ) );
			return;
		}

		// Bypass the object cache entirely — Gutenberg REST saves meta in the same
		// request so the cache may still hold the pre-save (empty) value.
		global $wpdb;
		$raw_channels = $wpdb->get_var( $wpdb->prepare(
			"SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = '_wsp_channels' LIMIT 1",
			$post->ID
		) );
		$channels = $raw_channels ? maybe_unserialize( $raw_channels ) : array();
		error_log( '[WSP] channels (direct DB): ' . print_r( $channels, true ) );
		if ( empty( $channels ) || ! is_array( $channels ) ) {
			error_log( '[WSP] SKIP: no channels selected' );
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
