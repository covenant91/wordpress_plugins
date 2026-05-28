<?php defined( 'ABSPATH' ) || die();
$token_mgr         = new WSP_Token_Manager();
$expiry_days       = $token_mgr->check_expiry( 'linkedin' );
$has_client_secret = ! empty( $settings['linkedin']['client_secret'] );
$has_access_token  = ! empty( $settings['linkedin']['access_token'] );
?>
<div class="notice notice-info inline" style="margin:0 0 16px;padding:8px 12px">
	<p style="margin:0">
		<strong><?php esc_html_e( 'Important:', 'wp-social-publisher' ); ?></strong>
		<?php esc_html_e( 'Click "Save Settings" before using "Test Connection".', 'wp-social-publisher' ); ?>
	</p>
</div>
<table class="form-table">
	<tr>
		<th><label for="wsp_li_client_id"><?php esc_html_e( 'Client ID', 'wp-social-publisher' ); ?></label></th>
		<td>
			<input type="text" id="wsp_li_client_id" name="wsp_li_client_id" class="regular-text"
				value="<?php echo esc_attr( $settings['linkedin']['client_id'] ?? '' ); ?>" />
		</td>
	</tr>
	<tr>
		<th><label for="wsp_li_client_secret"><?php esc_html_e( 'Client Secret', 'wp-social-publisher' ); ?></label></th>
		<td>
			<input type="password" id="wsp_li_client_secret" name="wsp_li_client_secret" class="regular-text wsp-field-track"
				placeholder="<?php esc_attr_e( 'Leave blank to keep existing', 'wp-social-publisher' ); ?>" />
			<?php if ( $has_client_secret ) : ?><span class="wsp-saved-badge">&#10003; <?php esc_html_e( 'Saved', 'wp-social-publisher' ); ?></span><?php endif; ?>
		</td>
	</tr>
	<tr>
		<th><label for="wsp_li_access_token"><?php esc_html_e( 'Access Token', 'wp-social-publisher' ); ?></label></th>
		<td>
			<input type="password" id="wsp_li_access_token" name="wsp_li_access_token" class="regular-text wsp-field-track"
				placeholder="<?php esc_attr_e( 'Leave blank to keep existing', 'wp-social-publisher' ); ?>" />
			<?php if ( $has_access_token ) : ?><span class="wsp-saved-badge">&#10003; <?php esc_html_e( 'Saved', 'wp-social-publisher' ); ?></span><?php endif; ?>
		</td>
	</tr>
	<tr>
		<th><label for="wsp_li_token_expiry"><?php esc_html_e( 'Token Expiry Date', 'wp-social-publisher' ); ?></label></th>
		<td>
			<input type="date" id="wsp_li_token_expiry" name="wsp_li_token_expiry" class="regular-text"
				value="<?php echo esc_attr( $settings['linkedin']['token_expiry'] ?? '' ); ?>" />
			<?php if ( null !== $expiry_days ) : ?>
			<span style="margin-left:8px;color:<?php echo $expiry_days <= 7 ? '#d63638' : '#50575e'; ?>">
				<?php
				if ( $expiry_days < 0 ) {
					esc_html_e( 'Token has expired!', 'wp-social-publisher' );
				} else {
					printf( esc_html__( '%d day(s) remaining', 'wp-social-publisher' ), (int) $expiry_days );
				}
				?>
			</span>
			<?php endif; ?>
		</td>
	</tr>
	<tr>
		<th><label for="wsp_li_urn"><?php esc_html_e( 'Person / Organization ID', 'wp-social-publisher' ); ?></label></th>
		<td>
			<input type="text" id="wsp_li_urn" name="wsp_li_urn" class="regular-text"
				value="<?php echo esc_attr( $settings['linkedin']['urn'] ?? '' ); ?>"
				placeholder="<?php esc_attr_e( 'Numeric ID only, e.g. 12345678', 'wp-social-publisher' ); ?>" />
		</td>
	</tr>
	<tr>
		<th><?php esc_html_e( 'Post as', 'wp-social-publisher' ); ?></th>
		<td>
			<label style="margin-right:12px">
				<input type="radio" name="wsp_li_urn_type" value="person"
					<?php checked( ( $settings['linkedin']['urn_type'] ?? 'person' ) === 'person' ); ?> />
				<?php esc_html_e( 'Person', 'wp-social-publisher' ); ?>
			</label>
			<label>
				<input type="radio" name="wsp_li_urn_type" value="organization"
					<?php checked( ( $settings['linkedin']['urn_type'] ?? 'person' ) === 'organization' ); ?> />
				<?php esc_html_e( 'Organization', 'wp-social-publisher' ); ?>
			</label>
			<button type="button" class="button smp-test-connection" data-platform="linkedin" style="margin-left:16px">
				<?php esc_html_e( 'Test Connection', 'wp-social-publisher' ); ?>
			</button>
			<span class="smp-test-result"></span>
		</td>
	</tr>
</table>
