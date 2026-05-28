<?php
/**
 * Facebook OAuth 2.0 flow handler.
 *
 * Flow:
 * 1. Admin clicks "Connect Facebook" → wsp_oauth_facebook_init → redirect to FB dialog
 * 2. Facebook redirects back → wsp_oauth_facebook_callback → exchange code → long-lived token
 * 3. If multiple pages: redirect to page selector screen
 * 4. Admin selects page → wsp_oauth_facebook_select_page → save tokens
 */
class WSP_OAuth_Facebook {

	const API_BASE  = 'https://graph.facebook.com/v18.0';
	const AUTH_URL  = 'https://www.facebook.com/v18.0/dialog/oauth';
	const SCOPE     = 'pages_manage_posts,pages_read_engagement,pages_show_list';

	/**
	 * The WordPress callback URL registered in the Facebook app.
	 *
	 * @return string
	 */
	public function callback_url() {
		return admin_url( 'admin-post.php?action=wsp_oauth_facebook_callback' );
	}

	/**
	 * Step 1 — redirect admin to Facebook authorization dialog.
	 * Hooked to admin_post_wsp_oauth_facebook_init.
	 */
	public function init_oauth() {
		check_admin_referer( 'wsp_oauth_facebook_init', '_wpnonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'wp-social-publisher' ) );
		}

		$settings = get_option( 'wsp_settings', array() );
		$app_id   = $settings['facebook']['app_id'] ?? '';
		if ( ! $app_id ) {
			$this->redirect_with_error( 'facebook', __( 'Enter your Facebook App ID first, then save settings.', 'wp-social-publisher' ) );
		}

		$state = wp_generate_uuid4();
		set_transient( 'wsp_oauth_fb_state_' . get_current_user_id(), $state, 600 );

		$url = self::AUTH_URL . '?' . http_build_query( array(
			'client_id'     => $app_id,
			'redirect_uri'  => $this->callback_url(),
			'scope'         => self::SCOPE,
			'response_type' => 'code',
			'state'         => $state,
		) );

		wp_redirect( $url );
		exit;
	}

	/**
	 * Step 2 — Facebook redirects here with a code.
	 * Hooked to admin_post_wsp_oauth_facebook_callback.
	 */
	public function handle_callback() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'wp-social-publisher' ) );
		}

		// Validate state to prevent CSRF.
		$state       = sanitize_text_field( wp_unslash( $_GET['state'] ?? '' ) );
		$saved_state = get_transient( 'wsp_oauth_fb_state_' . get_current_user_id() );
		if ( ! $state || ! $saved_state || ! hash_equals( $saved_state, $state ) ) {
			$this->redirect_with_error( 'facebook', __( 'Invalid OAuth state. Please try connecting again.', 'wp-social-publisher' ) );
		}
		delete_transient( 'wsp_oauth_fb_state_' . get_current_user_id() );

		// Check for Facebook errors.
		if ( isset( $_GET['error'] ) ) {
			$msg = sanitize_text_field( wp_unslash( $_GET['error_description'] ?? $_GET['error'] ) );
			$this->redirect_with_error( 'facebook', $msg );
		}

		$code = sanitize_text_field( wp_unslash( $_GET['code'] ?? '' ) );
		if ( ! $code ) {
			$this->redirect_with_error( 'facebook', __( 'No authorization code received from Facebook.', 'wp-social-publisher' ) );
		}

		$settings   = get_option( 'wsp_settings', array() );
		$app_id     = $settings['facebook']['app_id'] ?? '';
		$token_mgr  = new WSP_Token_Manager();
		$app_secret = $token_mgr->get_token( 'facebook', 'app_secret' );

		if ( ! $app_id || ! $app_secret ) {
			$this->redirect_with_error( 'facebook', __( 'App ID or App Secret not configured.', 'wp-social-publisher' ) );
		}

		// Exchange code for short-lived user token.
		$token_response = wp_remote_get(
			self::API_BASE . '/oauth/access_token?' . http_build_query( array(
				'client_id'     => $app_id,
				'redirect_uri'  => $this->callback_url(),
				'client_secret' => $app_secret,
				'code'          => $code,
			) ),
			array( 'timeout' => 15 )
		);

		if ( is_wp_error( $token_response ) ) {
			$this->redirect_with_error( 'facebook', $token_response->get_error_message() );
		}

		$token_data  = json_decode( wp_remote_retrieve_body( $token_response ), true );
		$short_token = $token_data['access_token'] ?? '';
		if ( ! $short_token ) {
			$msg = $token_data['error']['message'] ?? __( 'Failed to get access token.', 'wp-social-publisher' );
			$this->redirect_with_error( 'facebook', $msg );
		}

		// Exchange short-lived for long-lived token (60 days).
		$ll_response = wp_remote_get(
			self::API_BASE . '/oauth/access_token?' . http_build_query( array(
				'grant_type'        => 'fb_exchange_token',
				'client_id'         => $app_id,
				'client_secret'     => $app_secret,
				'fb_exchange_token' => $short_token,
			) ),
			array( 'timeout' => 15 )
		);

		$ll_data     = json_decode( wp_remote_retrieve_body( $ll_response ), true );
		$long_token  = $ll_data['access_token'] ?? $short_token;
		$expires_in  = $ll_data['expires_in'] ?? 0;
		$expiry_date = $expires_in ? gmdate( 'Y-m-d', time() + (int) $expires_in ) : '';

		// Get list of pages the user manages.
		$pages_response = wp_remote_get(
			self::API_BASE . '/me/accounts?access_token=' . rawurlencode( $long_token ),
			array( 'timeout' => 15 )
		);
		$pages_data = json_decode( wp_remote_retrieve_body( $pages_response ), true );
		$pages      = $pages_data['data'] ?? array();

		if ( empty( $pages ) ) {
			$this->redirect_with_error( 'facebook', __( 'No Facebook Pages found. Make sure your account manages at least one Page.', 'wp-social-publisher' ) );
		}

		// Store temporarily for the page selection step.
		set_transient( 'wsp_oauth_fb_pages_' . get_current_user_id(), $pages, 600 );
		set_transient( 'wsp_oauth_fb_expiry_' . get_current_user_id(), $expiry_date, 600 );

		if ( 1 === count( $pages ) ) {
			// Only one page — auto-select it.
			$this->save_page( $pages[0], $expiry_date );
			wp_redirect( admin_url( 'admin.php?page=wp-social-publisher&tab=facebook&oauth_success=1' ) );
			exit;
		}

		// Multiple pages — show selector.
		wp_redirect( admin_url( 'admin.php?page=wp-social-publisher&tab=facebook&oauth_step=select_page' ) );
		exit;
	}

	/**
	 * Step 3 (multi-page) — admin submits page selection form.
	 * Hooked to admin_post_wsp_oauth_facebook_select_page.
	 */
	public function handle_page_selection() {
		check_admin_referer( 'wsp_oauth_fb_select_page' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'wp-social-publisher' ) );
		}

		$page_id    = sanitize_text_field( wp_unslash( $_POST['wsp_fb_page_id'] ?? '' ) );
		$pages      = get_transient( 'wsp_oauth_fb_pages_' . get_current_user_id() );
		$expiry     = get_transient( 'wsp_oauth_fb_expiry_' . get_current_user_id() );

		if ( ! $pages ) {
			$this->redirect_with_error( 'facebook', __( 'Session expired. Please connect again.', 'wp-social-publisher' ) );
		}

		$selected = null;
		foreach ( $pages as $page ) {
			if ( $page['id'] === $page_id ) {
				$selected = $page;
				break;
			}
		}

		if ( ! $selected ) {
			$this->redirect_with_error( 'facebook', __( 'Invalid page selection.', 'wp-social-publisher' ) );
		}

		$this->save_page( $selected, $expiry );

		delete_transient( 'wsp_oauth_fb_pages_' . get_current_user_id() );
		delete_transient( 'wsp_oauth_fb_expiry_' . get_current_user_id() );

		wp_redirect( admin_url( 'admin.php?page=wp-social-publisher&tab=facebook&oauth_success=1' ) );
		exit;
	}

	/**
	 * Save a selected page's token and fetch its Instagram business account.
	 *
	 * @param array  $page        Page data from /me/accounts
	 * @param string $expiry_date Y-m-d expiry date for the page token
	 */
	private function save_page( array $page, $expiry_date ) {
		$token_mgr   = new WSP_Token_Manager();
		$page_token  = $page['access_token'];
		$page_id     = $page['id'];
		$page_name   = $page['name'];

		// Fetch Instagram Business Account linked to this page.
		$ig_response = wp_remote_get(
			self::API_BASE . '/' . rawurlencode( $page_id ) . '?fields=instagram_business_account&access_token=' . rawurlencode( $page_token ),
			array( 'timeout' => 10 )
		);
		$ig_data    = json_decode( wp_remote_retrieve_body( $ig_response ), true );
		$ig_user_id = $ig_data['instagram_business_account']['id'] ?? '';

		$settings = get_option( 'wsp_settings', array() );
		$settings['facebook']['page_id']        = $page_id;
		$settings['facebook']['page_token']     = $token_mgr->encrypt( $page_token );
		$settings['facebook']['connected']      = true;
		$settings['facebook']['connected_name'] = $page_name;
		$settings['facebook']['token_expiry']   = $expiry_date;

		if ( $ig_user_id ) {
			$settings['instagram']['user_id']        = $ig_user_id;
			$settings['instagram']['connected']      = true;
			$settings['instagram']['connected_name'] = $page_name;
		}

		update_option( 'wsp_settings', $settings );
	}

	/**
	 * Disconnect Facebook (and Instagram since they share a token).
	 */
	public function handle_disconnect() {
		check_admin_referer( 'wsp_oauth_facebook_disconnect', '_wpnonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'wp-social-publisher' ) );
		}
		$this->disconnect();
		wp_redirect( admin_url( 'admin.php?page=wp-social-publisher&tab=facebook&oauth_disconnected=1' ) );
		exit;
	}

	public function disconnect() {
		$settings = get_option( 'wsp_settings', array() );
		$settings['facebook']['page_token']     = '';
		$settings['facebook']['page_id']        = '';
		$settings['facebook']['connected']      = false;
		$settings['facebook']['connected_name'] = '';
		$settings['facebook']['token_expiry']   = '';
		$settings['instagram']['connected']     = false;
		update_option( 'wsp_settings', $settings );
	}

	/**
	 * Render the page selector form (used in settings-facebook.php).
	 *
	 * @return string HTML
	 */
	public function render_page_selector() {
		$pages = get_transient( 'wsp_oauth_fb_pages_' . get_current_user_id() );
		if ( ! $pages ) {
			return '<p>' . esc_html__( 'Session expired. Please connect again.', 'wp-social-publisher' ) . '</p>';
		}

		ob_start();
		?>
		<div class="notice notice-info inline" style="padding:12px 16px">
			<p><strong><?php esc_html_e( 'Select the Facebook Page to publish from:', 'wp-social-publisher' ); ?></strong></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'wsp_oauth_fb_select_page' ); ?>
				<input type="hidden" name="action" value="wsp_oauth_facebook_select_page" />
				<select name="wsp_fb_page_id" style="margin-bottom:8px;display:block">
					<?php foreach ( $pages as $page ) : ?>
					<option value="<?php echo esc_attr( $page['id'] ); ?>">
						<?php echo esc_html( $page['name'] . ' (ID: ' . $page['id'] . ')' ); ?>
					</option>
					<?php endforeach; ?>
				</select>
				<input type="submit" class="button button-primary" value="<?php esc_attr_e( 'Connect this Page', 'wp-social-publisher' ); ?>" />
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	/** @param string $tab @param string $message */
	private function redirect_with_error( $tab, $message ) {
		wp_redirect( admin_url( 'admin.php?page=wp-social-publisher&tab=' . $tab . '&oauth_error=' . rawurlencode( $message ) ) );
		exit;
	}
}
