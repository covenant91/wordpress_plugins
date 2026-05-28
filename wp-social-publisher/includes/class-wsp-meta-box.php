<?php
/**
 * Classic editor meta box for selecting social platforms per post.
 */
class WSP_Meta_Box {

	/** @var WSP_Loader */
	private $loader;

	public function __construct( WSP_Loader $loader ) {
		$this->loader = $loader;
		$this->register_hooks();
	}

	private function register_hooks() {
		$this->loader->add_action( 'add_meta_boxes', $this, 'add_meta_boxes' );
		$this->loader->add_action( 'save_post',      $this, 'save_post', 10, 2 );
	}

	public function add_meta_boxes() {
		$settings   = get_option( 'wsp_settings', array() );
		$post_types = isset( $settings['defaults']['enabled_post_types'] )
			? (array) $settings['defaults']['enabled_post_types']
			: array( 'post' );

		foreach ( $post_types as $post_type ) {
			add_meta_box(
				'wsp-social-publisher',
				__( 'Publish to Social Media', 'wp-social-publisher' ),
				array( $this, 'render' ),
				sanitize_key( $post_type ),
				'side',
				'default'
			);
		}
	}

	/**
	 * Render the meta box HTML.
	 *
	 * @param WP_Post $post
	 */
	public function render( $post ) {
		wp_nonce_field( 'wsp_meta_box_save', 'wsp_meta_box_nonce' );

		$channels = (array) get_post_meta( $post->ID, '_wsp_channels', true );
		$captions = (array) get_post_meta( $post->ID, '_wsp_captions', true );

		$platforms = array(
			'facebook'  => array( 'label' => __( 'Facebook', 'wp-social-publisher' ),  'color' => '#1877F2', 'limit' => 63206 ),
			'instagram' => array( 'label' => __( 'Instagram', 'wp-social-publisher' ), 'color' => '#E1306C', 'limit' => 2200 ),
			'linkedin'  => array( 'label' => __( 'LinkedIn', 'wp-social-publisher' ),  'color' => '#0A66C2', 'limit' => 3000 ),
			'twitter'   => array( 'label' => __( 'X (Twitter)', 'wp-social-publisher' ), 'color' => '#000000', 'limit' => 280 ),
		);

		// Show published-to notice if post is already published.
		if ( 'publish' === $post->post_status ) {
			$already_sent = array();
			foreach ( array_keys( $platforms ) as $p ) {
				if ( WSP_Post_Log::already_sent( $post->ID, $p ) ) {
					$already_sent[] = $platforms[ $p ]['label'];
				}
			}
			if ( $already_sent ) {
				echo '<p class="smp-already-sent"><strong>'
					. esc_html__( 'Already published to:', 'wp-social-publisher' )
					. '</strong> ' . esc_html( implode( ', ', $already_sent ) ) . '</p>';
			}
		}

		echo '<div class="smp-meta-box">';
		foreach ( $platforms as $slug => $info ) {
			$checked = in_array( $slug, $channels, true );
			$caption = isset( $captions[ $slug ] ) ? $captions[ $slug ] : '';
			?>
			<div class="smp-platform-row">
				<label class="smp-platform-label">
					<span class="smp-platform-dot" style="background:<?php echo esc_attr( $info['color'] ); ?>"></span>
					<input
						type="checkbox"
						name="wsp_channels[]"
						value="<?php echo esc_attr( $slug ); ?>"
						<?php checked( $checked ); ?>
						data-platform="<?php echo esc_attr( $slug ); ?>"
					/>
					<?php echo esc_html( $info['label'] ); ?>
				</label>
				<div class="smp-caption-wrap" <?php echo $checked ? '' : 'style="display:none"'; ?>>
					<textarea
						name="wsp_captions[<?php echo esc_attr( $slug ); ?>]"
						class="smp-caption widefat"
						rows="3"
						maxlength="<?php echo esc_attr( $info['limit'] ); ?>"
						data-limit="<?php echo esc_attr( $info['limit'] ); ?>"
						placeholder="<?php esc_attr_e( 'Custom caption (optional)', 'wp-social-publisher' ); ?>"
					><?php echo esc_textarea( $caption ); ?></textarea>
					<span class="smp-char-count"></span>
				</div>
			</div>
			<?php
		}
		echo '</div>';
	}

	/**
	 * Save meta box values on post save.
	 *
	 * @param int     $post_id
	 * @param WP_Post $post
	 */
	public function save_post( $post_id, $post ) {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( ! isset( $_POST['wsp_meta_box_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wsp_meta_box_nonce'] ) ), 'wsp_meta_box_save' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( wp_is_post_autosave( $post ) || wp_is_post_revision( $post ) ) {
			return;
		}

		$allowed_platforms = array( 'facebook', 'instagram', 'linkedin', 'twitter' );

		// Channels.
		$channels = array();
		if ( isset( $_POST['wsp_channels'] ) && is_array( $_POST['wsp_channels'] ) ) {
			foreach ( $_POST['wsp_channels'] as $ch ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
				$ch = sanitize_key( $ch );
				if ( in_array( $ch, $allowed_platforms, true ) ) {
					$channels[] = $ch;
				}
			}
		}
		update_post_meta( $post_id, '_wsp_channels', $channels );

		// Captions.
		$captions = array();
		if ( isset( $_POST['wsp_captions'] ) && is_array( $_POST['wsp_captions'] ) ) {
			foreach ( $_POST['wsp_captions'] as $platform => $caption ) {
				$platform = sanitize_key( $platform );
				if ( in_array( $platform, $allowed_platforms, true ) ) {
					$captions[ $platform ] = wp_kses_post( wp_unslash( $caption ) );
				}
			}
		}
		update_post_meta( $post_id, '_wsp_captions', $captions );
	}
}
