<?php
defined( 'ABSPATH' ) || die();

$settings        = get_option( 'wsp_settings', array() );
$is_connected    = ! empty( $settings['twitter']['connected'] );
$connected_name  = $settings['twitter']['connected_name'] ?? '';
$monthly_count   = WSP_Twitter::get_monthly_count();
$token_mgr       = new WSP_Token_Manager();
$has_secret      = ! empty( $settings['twitter']['consumer_secret'] );

// OAuth result notices.
if ( isset( $_GET['oauth_success'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
	echo '<div class="notice notice-success is-dismissible"><p>'
		. esc_html__( 'X (Twitter) connected successfully!', 'wp-social-publisher' )
		. '</p></div>';
}
if ( isset( $_GET['oauth_disconnected'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
	echo '<div class="notice notice-info is-dismissible"><p>'
		. esc_html__( 'X (Twitter) disconnected.', 'wp-social-publisher' )
		. '</p></div>';
}
if ( isset( $_GET['oauth_error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
	echo '<div class="notice notice-error is-dismissible"><p>'
		. esc_html( urldecode( sanitize_text_field( wp_unslash( $_GET['oauth_error'] ) ) ) )
		. '</p></div>';
}
?>

<div class="wsp-twitter-count">
	<?php esc_html_e( 'Tweets this month:', 'wp-social-publisher' ); ?>
	<strong><?php echo (int) $monthly_count; ?></strong>
	/ 1,500
	<em style="font-size:11px;color:#787c82"><?php esc_html_e( '(free tier limit)', 'wp-social-publisher' ); ?></em>
</div>

<?php if ( $is_connected ) : ?>

<div class="wsp-connected-banner">
	<span class="wsp-connected-dot" style="background:#000"></span>
	<strong><?php esc_html_e( 'X connected as:', 'wp-social-publisher' ); ?></strong>
	<?php echo esc_html( $connected_name ); ?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;margin-left:16px">
		<?php wp_nonce_field( 'wsp_oauth_twitter_disconnect' ); ?>
		<input type="hidden" name="action" value="wsp_oauth_twitter_disconnect" />
		<button type="submit" class="button button-small wsp-disconnect-btn">
			<?php esc_html_e( 'Disconnect', 'wp-social-publisher' ); ?>
		</button>
	</form>
</div>
<hr style="margin:20px 0" />
<p style="color:#50575e"><?php esc_html_e( 'To connect a different account, disconnect first then reconnect.', 'wp-social-publisher' ); ?></p>

<?php else : ?>

<table class="form-table">
	<tr>
		<th><label for="wsp_tw_consumer_key"><?php esc_html_e( 'Consumer Key (API Key)', 'wp-social-publisher' ); ?></label></th>
		<td>
			<input type="text" id="wsp_tw_consumer_key" name="wsp_tw_consumer_key" class="regular-text"
				value="<?php echo esc_attr( $settings['twitter']['consumer_key'] ?? '' ); ?>" />
		</td>
	</tr>
	<tr>
		<th><label for="wsp_tw_consumer_secret"><?php esc_html_e( 'Consumer Secret (API Secret)', 'wp-social-publisher' ); ?></label></th>
		<td>
			<input type="password" id="wsp_tw_consumer_secret" name="wsp_tw_consumer_secret" class="regular-text"
				placeholder="<?php esc_attr_e( 'Leave blank to keep existing', 'wp-social-publisher' ); ?>" />
			<?php if ( $has_secret ) : ?>
				<span class="wsp-saved-badge">&#10003; <?php esc_html_e( 'Saved', 'wp-social-publisher' ); ?></span>
			<?php endif; ?>
		</td>
	</tr>
</table>

<div style="margin:16px 0">
	<p><?php esc_html_e( 'After saving Consumer Key and Secret above, click the button to authorize via X login:', 'wp-social-publisher' ); ?></p>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'wsp_oauth_twitter_init' ); ?>
		<input type="hidden" name="action" value="wsp_oauth_twitter_init" />
		<button type="submit" class="button button-primary wsp-connect-btn wsp-connect-twitter">
			&#x1D54F; <?php esc_html_e( 'Connect with X', 'wp-social-publisher' ); ?>
		</button>
	</form>
	<p class="description" style="margin-top:8px">
		<?php
		printf(
			wp_kses(
				/* translators: %s callback URL */
				__( 'Add <code>%s</code> as the Callback URL in your X app → User authentication settings.', 'wp-social-publisher' ),
				array( 'code' => array() )
			),
			esc_html( admin_url( 'admin-post.php?action=wsp_oauth_twitter_callback' ) )
		);
		?>
	</p>
</div>

<?php endif; ?>
