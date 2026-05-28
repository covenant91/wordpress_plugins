<?php
defined( 'ABSPATH' ) || die();

$settings        = get_option( 'wsp_settings', array() );
$is_connected    = ! empty( $settings['facebook']['connected'] );
$connected_name  = $settings['facebook']['connected_name'] ?? '';
$ig_connected    = ! empty( $settings['instagram']['connected'] );
$token_mgr       = new WSP_Token_Manager();
$has_app_secret  = ! empty( $settings['facebook']['app_secret'] );
$oauth_fb        = new WSP_OAuth_Facebook();

// Show OAuth result notices.
if ( isset( $_GET['oauth_success'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
	echo '<div class="notice notice-success is-dismissible"><p>'
		. esc_html__( 'Facebook connected successfully!', 'wp-social-publisher' )
		. '</p></div>';
}
if ( isset( $_GET['oauth_disconnected'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
	echo '<div class="notice notice-info is-dismissible"><p>'
		. esc_html__( 'Facebook disconnected.', 'wp-social-publisher' )
		. '</p></div>';
}
if ( isset( $_GET['oauth_error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
	echo '<div class="notice notice-error is-dismissible"><p>'
		. esc_html( urldecode( sanitize_text_field( wp_unslash( $_GET['oauth_error'] ) ) ) )
		. '</p></div>';
}

// Page selector step.
if ( isset( $_GET['oauth_step'] ) && 'select_page' === $_GET['oauth_step'] ) { // phpcs:ignore WordPress.Security.NonceVerification
	echo ( new WSP_OAuth_Facebook() )->render_page_selector();
	return;
}
?>

<?php if ( $is_connected ) : ?>
<div class="wsp-connected-banner">
	<span class="wsp-connected-dot"></span>
	<strong><?php esc_html_e( 'Facebook connected as:', 'wp-social-publisher' ); ?></strong>
	<?php echo esc_html( $connected_name ); ?>
	<?php if ( ! empty( $settings['facebook']['token_expiry'] ) ) : ?>
		<span class="wsp-expiry-note">
			— <?php printf( esc_html__( 'token expires %s', 'wp-social-publisher' ), esc_html( $settings['facebook']['token_expiry'] ) ); ?>
		</span>
	<?php endif; ?>
	<?php
	$disconnect_url = wp_nonce_url(
		admin_url( 'admin-post.php?action=wsp_oauth_facebook_disconnect' ),
		'wsp_oauth_facebook_disconnect'
	);
	?>
	<a href="<?php echo esc_url( $disconnect_url ); ?>" class="button button-small wsp-disconnect-btn" style="margin-left:16px"
	   onclick="return confirm('<?php esc_attr_e( 'Disconnect Facebook?', 'wp-social-publisher' ); ?>')">
		<?php esc_html_e( 'Disconnect', 'wp-social-publisher' ); ?>
	</a>
</div>

<?php if ( $ig_connected ) : ?>
<div class="wsp-connected-banner" style="margin-top:8px">
	<span class="wsp-connected-dot" style="background:#E1306C"></span>
	<strong><?php esc_html_e( 'Instagram connected to page:', 'wp-social-publisher' ); ?></strong>
	<?php echo esc_html( $settings['instagram']['connected_name'] ?? $connected_name ); ?>
	<span style="color:#787c82;font-size:12px;margin-left:8px">
		(<?php esc_html_e( 'ID:', 'wp-social-publisher' ); ?> <?php echo esc_html( $settings['instagram']['user_id'] ?? '' ); ?>)
	</span>
</div>
<?php else : ?>
<div class="notice notice-warning inline" style="margin-top:8px;padding:8px 12px">
	<p style="margin:0"><?php esc_html_e( 'Instagram not detected. Make sure your Instagram Business/Creator account is linked to this Facebook Page in Meta Business Settings.', 'wp-social-publisher' ); ?></p>
</div>
<?php endif; ?>

<hr style="margin:20px 0" />
<p style="color:#50575e"><?php esc_html_e( 'To switch pages or reconnect after token expiry, click "Disconnect" then connect again.', 'wp-social-publisher' ); ?></p>

<?php else : ?>

<table class="form-table">
	<tr>
		<th><label for="wsp_fb_app_id"><?php esc_html_e( 'Facebook App ID', 'wp-social-publisher' ); ?></label></th>
		<td>
			<input type="text" id="wsp_fb_app_id" name="wsp_fb_app_id" class="regular-text"
				value="<?php echo esc_attr( $settings['facebook']['app_id'] ?? '' ); ?>" />
			<p class="description"><?php esc_html_e( 'From your Meta Developer app → Settings → Basic.', 'wp-social-publisher' ); ?></p>
		</td>
	</tr>
	<tr>
		<th><label for="wsp_fb_app_secret"><?php esc_html_e( 'Facebook App Secret', 'wp-social-publisher' ); ?></label></th>
		<td>
			<input type="password" id="wsp_fb_app_secret" name="wsp_fb_app_secret" class="regular-text"
				placeholder="<?php esc_attr_e( 'Leave blank to keep existing', 'wp-social-publisher' ); ?>" />
			<?php if ( $has_app_secret ) : ?>
				<span class="wsp-saved-badge">&#10003; <?php esc_html_e( 'Saved', 'wp-social-publisher' ); ?></span>
			<?php endif; ?>
			<p class="description"><?php esc_html_e( 'Required to initiate the OAuth flow.', 'wp-social-publisher' ); ?></p>
		</td>
	</tr>
</table>

<div style="margin:16px 0">
	<p><?php esc_html_e( 'After saving App ID and Secret above, click the button to authorize via Facebook login:', 'wp-social-publisher' ); ?></p>
	<?php
	$connect_url = wp_nonce_url(
		admin_url( 'admin-post.php?action=wsp_oauth_facebook_init' ),
		'wsp_oauth_facebook_init'
	);
	?>
	<a href="<?php echo esc_url( $connect_url ); ?>" class="button button-primary wsp-connect-btn wsp-connect-facebook">
		&#x1F4F7; <?php esc_html_e( 'Connect with Facebook', 'wp-social-publisher' ); ?>
	</a>
	<p class="description" style="margin-top:8px">
		<?php
		printf(
			wp_kses(
				/* translators: %s callback URL */
				__( 'Make sure <code>%s</code> is added as a Valid OAuth Redirect URI in your Meta app → Facebook Login for Business → Settings.', 'wp-social-publisher' ),
				array( 'code' => array() )
			),
			esc_html( admin_url( 'admin-post.php?action=wsp_oauth_facebook_callback' ) )
		);
		?>
	</p>
</div>

<?php endif; ?>
