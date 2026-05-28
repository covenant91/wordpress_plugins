<?php defined( 'ABSPATH' ) || die();
$monthly_count = WSP_Twitter::get_monthly_count();
?>
<div class="wsp-twitter-count">
	<?php esc_html_e( 'Tweets this month:', 'wp-social-publisher' ); ?>
	<strong><?php echo (int) $monthly_count; ?></strong>
	/ 1,500
	<em style="font-size:11px;color:#787c82"><?php esc_html_e( '(free tier limit)', 'wp-social-publisher' ); ?></em>
</div>
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
		</td>
	</tr>
	<tr>
		<th><label for="wsp_tw_access_token"><?php esc_html_e( 'Access Token', 'wp-social-publisher' ); ?></label></th>
		<td>
			<input type="text" id="wsp_tw_access_token" name="wsp_tw_access_token" class="regular-text"
				value="<?php echo esc_attr( $settings['twitter']['access_token'] ?? '' ); ?>" />
		</td>
	</tr>
	<tr>
		<th><label for="wsp_tw_access_token_secret"><?php esc_html_e( 'Access Token Secret', 'wp-social-publisher' ); ?></label></th>
		<td>
			<input type="password" id="wsp_tw_access_token_secret" name="wsp_tw_access_token_secret" class="regular-text"
				placeholder="<?php esc_attr_e( 'Leave blank to keep existing', 'wp-social-publisher' ); ?>" />
			<button type="button" class="button smp-test-connection" data-platform="twitter" style="display:block;margin-top:8px">
				<?php esc_html_e( 'Test Connection', 'wp-social-publisher' ); ?>
			</button>
			<span class="smp-test-result"></span>
		</td>
	</tr>
</table>
