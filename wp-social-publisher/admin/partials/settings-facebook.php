<?php defined( 'ABSPATH' ) || die(); ?>
<table class="form-table">
	<tr>
		<th><label for="wsp_fb_app_id"><?php esc_html_e( 'Facebook App ID', 'wp-social-publisher' ); ?></label></th>
		<td>
			<input type="text" id="wsp_fb_app_id" name="wsp_fb_app_id" class="regular-text"
				value="<?php echo esc_attr( $settings['facebook']['app_id'] ?? '' ); ?>" />
		</td>
	</tr>
	<tr>
		<th><label for="wsp_fb_app_secret"><?php esc_html_e( 'Facebook App Secret', 'wp-social-publisher' ); ?></label></th>
		<td>
			<input type="password" id="wsp_fb_app_secret" name="wsp_fb_app_secret" class="regular-text"
				placeholder="<?php esc_attr_e( 'Leave blank to keep existing', 'wp-social-publisher' ); ?>" />
		</td>
	</tr>
	<tr>
		<th><label for="wsp_fb_page_token"><?php esc_html_e( 'Page Access Token', 'wp-social-publisher' ); ?></label></th>
		<td>
			<input type="password" id="wsp_fb_page_token" name="wsp_fb_page_token" class="regular-text"
				placeholder="<?php esc_attr_e( 'Leave blank to keep existing', 'wp-social-publisher' ); ?>" />
		</td>
	</tr>
	<tr>
		<th><label for="wsp_fb_page_id"><?php esc_html_e( 'Facebook Page ID', 'wp-social-publisher' ); ?></label></th>
		<td>
			<input type="text" id="wsp_fb_page_id" name="wsp_fb_page_id" class="regular-text"
				value="<?php echo esc_attr( $settings['facebook']['page_id'] ?? '' ); ?>" />
			<button type="button" class="button smp-test-connection" data-platform="facebook">
				<?php esc_html_e( 'Test Connection', 'wp-social-publisher' ); ?>
			</button>
			<span class="smp-test-result"></span>
		</td>
	</tr>
	<tr>
		<th colspan="2"><h3 style="margin:0"><?php esc_html_e( 'Instagram', 'wp-social-publisher' ); ?></h3></th>
	</tr>
	<tr>
		<th><label for="wsp_ig_user_id"><?php esc_html_e( 'Instagram User ID', 'wp-social-publisher' ); ?></label></th>
		<td>
			<input type="text" id="wsp_ig_user_id" name="wsp_ig_user_id" class="regular-text"
				value="<?php echo esc_attr( $settings['instagram']['user_id'] ?? '' ); ?>" />
			<button type="button" class="button smp-test-connection" data-platform="instagram">
				<?php esc_html_e( 'Test Connection', 'wp-social-publisher' ); ?>
			</button>
			<span class="smp-test-result"></span>
			<p class="description">
				<?php esc_html_e( 'Instagram uses the same Page Access Token as Facebook. The account must be Business or Creator type and linked to your Facebook Page.', 'wp-social-publisher' ); ?>
			</p>
		</td>
	</tr>
</table>
