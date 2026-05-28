<?php
/**
 * Registers the Gutenberg sidebar and exposes post meta to the REST API.
 */
class WSP_Gutenberg {

	/** @var WSP_Loader */
	private $loader;

	public function __construct( WSP_Loader $loader ) {
		$this->loader = $loader;
		$this->register_hooks();
	}

	private function register_hooks() {
		$this->loader->add_action( 'init',                      $this, 'register_post_meta' );
		$this->loader->add_action( 'enqueue_block_editor_assets', $this, 'enqueue_sidebar' );
	}

	/**
	 * Register _wsp_channels and _wsp_captions as REST-accessible post meta.
	 */
	public function register_post_meta() {
		$settings   = get_option( 'wsp_settings', array() );
		$post_types = isset( $settings['defaults']['enabled_post_types'] )
			? (array) $settings['defaults']['enabled_post_types']
			: array( 'post' );

		foreach ( $post_types as $post_type ) {
			register_post_meta(
				sanitize_key( $post_type ),
				'_wsp_channels',
				array(
					'show_in_rest'  => array(
						'schema' => array(
							'type'  => 'array',
							'items' => array( 'type' => 'string' ),
						),
					),
					'single'        => true,
					'type'          => 'array',
					'auth_callback' => function() {
						return current_user_can( 'edit_posts' );
					},
				)
			);

			register_post_meta(
				sanitize_key( $post_type ),
				'_wsp_captions',
				array(
					'show_in_rest'  => array(
						'schema' => array(
							'type'                 => 'object',
							'additionalProperties' => array( 'type' => 'string' ),
						),
					),
					'single'        => true,
					'type'          => 'object',
					'auth_callback' => function() {
						return current_user_can( 'edit_posts' );
					},
				)
			);
		}
	}

	/**
	 * Enqueue the Gutenberg sidebar script.
	 */
	public function enqueue_sidebar() {
		wp_enqueue_script(
			'wsp-gutenberg-sidebar',
			WSP_PLUGIN_URL . 'assets/build/sidebar.js',
			array( 'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-i18n' ),
			WSP_VERSION,
			true
		);
		wp_enqueue_style(
			'wsp-gutenberg-sidebar',
			WSP_PLUGIN_URL . 'assets/admin.css',
			array(),
			WSP_VERSION
		);

		// Pass already-sent platforms to JS for the "already published" notice.
		global $post;
		$already_sent = array();
		if ( $post && $post->ID ) {
			foreach ( array( 'facebook', 'instagram', 'linkedin', 'twitter' ) as $platform ) {
				if ( WSP_Post_Log::already_sent( $post->ID, $platform ) ) {
					$already_sent[] = $platform;
				}
			}
		}
		wp_localize_script( 'wsp-gutenberg-sidebar', 'wspData', array(
			'alreadySent' => $already_sent,
		) );
	}
}
