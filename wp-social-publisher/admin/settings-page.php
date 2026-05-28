<?php
defined( 'ABSPATH' ) || die();

$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'facebook'; // phpcs:ignore WordPress.Security.NonceVerification
$tabs = array(
	'facebook' => __( 'Facebook / Instagram', 'wp-social-publisher' ),
	'linkedin' => __( 'LinkedIn', 'wp-social-publisher' ),
	'twitter'  => __( 'X (Twitter)', 'wp-social-publisher' ),
	'defaults' => __( 'Defaults', 'wp-social-publisher' ),
);

$settings = get_option( 'wsp_settings', array() );

// Handle form save (only for tabs that still have manual fields).
if ( isset( $_POST['wsp_save_settings'] ) ) {
	check_admin_referer( 'wsp_settings_save', 'wsp_settings_nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Insufficient permissions.', 'wp-social-publisher' ) );
	}

	$token_mgr = new WSP_Token_Manager();

	if ( 'facebook' === $active_tab ) {
		// App ID and Secret only — page token/ID are written by the OAuth flow.
		$settings['facebook']['app_id'] = sanitize_text_field( wp_unslash( $_POST['wsp_fb_app_id'] ?? '' ) );
		$fb_secret = sanitize_text_field( wp_unslash( $_POST['wsp_fb_app_secret'] ?? '' ) );
		if ( $fb_secret ) {
			$settings['facebook']['app_secret'] = $token_mgr->encrypt( $fb_secret );
		}
	}

	if ( 'linkedin' === $active_tab ) {
		$settings['linkedin']['client_id']    = sanitize_text_field( wp_unslash( $_POST['wsp_li_client_id'] ?? '' ) );
		$settings['linkedin']['urn']          = sanitize_text_field( wp_unslash( $_POST['wsp_li_urn'] ?? '' ) );
		$settings['linkedin']['urn_type']     = ( isset( $_POST['wsp_li_urn_type'] ) && 'organization' === $_POST['wsp_li_urn_type'] ) ? 'organization' : 'person';
		$settings['linkedin']['token_expiry'] = sanitize_text_field( wp_unslash( $_POST['wsp_li_token_expiry'] ?? '' ) );
		$li_secret = sanitize_text_field( wp_unslash( $_POST['wsp_li_client_secret'] ?? '' ) );
		if ( $li_secret ) {
			$settings['linkedin']['client_secret'] = $token_mgr->encrypt( $li_secret );
		}
		$li_token = sanitize_text_field( wp_unslash( $_POST['wsp_li_access_token'] ?? '' ) );
		if ( $li_token ) {
			$settings['linkedin']['access_token'] = $token_mgr->encrypt( $li_token );
		}
	}

	if ( 'twitter' === $active_tab ) {
		// Consumer Key and Secret only — access tokens are written by the OAuth flow.
		$settings['twitter']['consumer_key'] = sanitize_text_field( wp_unslash( $_POST['wsp_tw_consumer_key'] ?? '' ) );
		$tw_secret = sanitize_text_field( wp_unslash( $_POST['wsp_tw_consumer_secret'] ?? '' ) );
		if ( $tw_secret ) {
			$settings['twitter']['consumer_secret'] = $token_mgr->encrypt( $tw_secret );
		}
	}

	if ( 'defaults' === $active_tab ) {
		$settings['defaults']['auto_append_url']    = isset( $_POST['wsp_auto_append_url'] );
		$settings['defaults']['log_retention_days'] = max( 1, (int) ( $_POST['wsp_log_retention_days'] ?? 90 ) );

		$allowed_types = array_keys( get_post_types( array( 'public' => true ) ) );
		$enabled_types = array();
		if ( isset( $_POST['wsp_post_types'] ) && is_array( $_POST['wsp_post_types'] ) ) {
			foreach ( $_POST['wsp_post_types'] as $pt ) {
				$pt = sanitize_key( $pt );
				if ( in_array( $pt, $allowed_types, true ) ) {
					$enabled_types[] = $pt;
				}
			}
		}
		$settings['defaults']['enabled_post_types'] = $enabled_types;

		$platforms = array( 'facebook', 'instagram', 'linkedin', 'twitter' );
		foreach ( $platforms as $p ) {
			$settings['defaults']['hashtags'][ $p ] = sanitize_text_field( wp_unslash( $_POST[ "wsp_hashtags_{$p}" ] ?? '' ) );
		}
	}

	update_option( 'wsp_settings', $settings );
	echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'wp-social-publisher' ) . '</p></div>';
}

$token_mgr = new WSP_Token_Manager();
?>
<div class="wrap">
	<h1><?php esc_html_e( 'WP Social Publisher — Settings', 'wp-social-publisher' ); ?></h1>

	<ul class="wsp-tab-nav">
		<?php foreach ( $tabs as $slug => $label ) : ?>
		<li>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=wp-social-publisher&tab=' . $slug ) ); ?>"
			   class="<?php echo $active_tab === $slug ? 'active' : ''; ?>">
				<?php echo esc_html( $label ); ?>
			</a>
		</li>
		<?php endforeach; ?>
	</ul>

	<div class="wsp-settings-section">
		<form method="post">
			<?php wp_nonce_field( 'wsp_settings_save', 'wsp_settings_nonce' ); ?>
			<?php if ( 'facebook' === $active_tab ) : require WSP_PLUGIN_DIR . 'admin/partials/settings-facebook.php'; endif; ?>
			<?php if ( 'linkedin' === $active_tab ) : require WSP_PLUGIN_DIR . 'admin/partials/settings-linkedin.php'; endif; ?>
			<?php if ( 'twitter' === $active_tab )  : require WSP_PLUGIN_DIR . 'admin/partials/settings-twitter.php';  endif; ?>
			<?php if ( 'defaults' === $active_tab ) : ?>
			<table class="form-table">
				<tr>
					<th><?php esc_html_e( 'Auto-append post URL', 'wp-social-publisher' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="wsp_auto_append_url" value="1"
								<?php checked( ! empty( $settings['defaults']['auto_append_url'] ) ); ?> />
							<?php esc_html_e( 'Append post URL to default captions', 'wp-social-publisher' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Log retention (days)', 'wp-social-publisher' ); ?></th>
					<td>
						<input type="number" name="wsp_log_retention_days" min="1" max="3650"
							value="<?php echo esc_attr( $settings['defaults']['log_retention_days'] ?? 90 ); ?>" class="small-text" />
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Enable social panel for', 'wp-social-publisher' ); ?></th>
					<td>
						<?php
						$enabled_types = isset( $settings['defaults']['enabled_post_types'] ) ? (array) $settings['defaults']['enabled_post_types'] : array( 'post' );
						foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $pt ) :
						?>
						<label style="display:block;margin-bottom:4px">
							<input type="checkbox" name="wsp_post_types[]" value="<?php echo esc_attr( $pt->name ); ?>"
								<?php checked( in_array( $pt->name, $enabled_types, true ) ); ?> />
							<?php echo esc_html( $pt->label ); ?>
						</label>
						<?php endforeach; ?>
					</td>
				</tr>
				<?php foreach ( array( 'facebook' => 'Facebook', 'instagram' => 'Instagram', 'linkedin' => 'LinkedIn', 'twitter' => 'X (Twitter)' ) as $p => $label ) : ?>
				<tr>
					<th><?php printf( esc_html__( '%s default hashtags', 'wp-social-publisher' ), esc_html( $label ) ); ?></th>
					<td>
						<input type="text" name="wsp_hashtags_<?php echo esc_attr( $p ); ?>"
							value="<?php echo esc_attr( $settings['defaults']['hashtags'][ $p ] ?? '' ); ?>"
							class="regular-text"
							placeholder="<?php esc_attr_e( 'wordpress, webdev, blogging', 'wp-social-publisher' ); ?>" />
						<p class="description"><?php esc_html_e( 'Comma-separated, # optional.', 'wp-social-publisher' ); ?></p>
					</td>
				</tr>
				<?php endforeach; ?>
			</table>
			<?php endif; ?>
			<?php if ( in_array( $active_tab, array( 'facebook', 'twitter', 'linkedin', 'defaults' ), true ) ) : ?>
			<p class="submit">
				<input type="submit" name="wsp_save_settings" class="button-primary"
					value="<?php esc_attr_e( 'Save Settings', 'wp-social-publisher' ); ?>" />
			</p>
			<?php endif; ?>
		</form>
	</div>
</div>
