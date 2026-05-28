<?php
/**
 * Admin menus, script enqueuing, and AJAX handlers.
 */
class WSP_Admin {

	/** @var WSP_Loader */
	private $loader;

	public function __construct( WSP_Loader $loader ) {
		$this->loader = $loader;
		$this->register_hooks();
	}

	private function register_hooks() {
		$this->loader->add_action( 'admin_menu',            $this, 'add_menus' );
		$this->loader->add_action( 'admin_enqueue_scripts', $this, 'enqueue_assets' );
		$this->loader->add_action( 'admin_notices',         $this, 'admin_notices' );

		// AJAX handlers.
		$this->loader->add_action( 'wp_ajax_wsp_test_connection', $this, 'ajax_test_connection' );
		$this->loader->add_action( 'wp_ajax_wsp_retry_publish',   $this, 'ajax_retry_publish' );

		// Facebook OAuth actions.
		$this->loader->add_action( 'admin_post_wsp_oauth_facebook_init',        $this, 'oauth_facebook_init' );
		$this->loader->add_action( 'admin_post_wsp_oauth_facebook_callback',    $this, 'oauth_facebook_callback' );
		$this->loader->add_action( 'admin_post_wsp_oauth_facebook_select_page', $this, 'oauth_facebook_select_page' );
		$this->loader->add_action( 'admin_post_wsp_oauth_facebook_disconnect',  $this, 'oauth_facebook_disconnect' );

		// Twitter OAuth actions.
		$this->loader->add_action( 'admin_post_wsp_oauth_twitter_init',       $this, 'oauth_twitter_init' );
		$this->loader->add_action( 'admin_post_wsp_oauth_twitter_callback',   $this, 'oauth_twitter_callback' );
		$this->loader->add_action( 'admin_post_wsp_oauth_twitter_disconnect', $this, 'oauth_twitter_disconnect' );
	}

	// -------------------------------------------------------------------------
	// Facebook OAuth delegates
	// -------------------------------------------------------------------------

	public function oauth_facebook_init()        { ( new WSP_OAuth_Facebook() )->init_oauth(); }
	public function oauth_facebook_callback()    { ( new WSP_OAuth_Facebook() )->handle_callback(); }
	public function oauth_facebook_select_page() { ( new WSP_OAuth_Facebook() )->handle_page_selection(); }

	public function oauth_facebook_disconnect() {
		check_admin_referer( 'wsp_oauth_facebook_disconnect' );
		if ( current_user_can( 'manage_options' ) ) {
			( new WSP_OAuth_Facebook() )->disconnect();
		}
		wp_redirect( admin_url( 'admin.php?page=wp-social-publisher&tab=facebook&oauth_disconnected=1' ) );
		exit;
	}

	// -------------------------------------------------------------------------
	// Twitter OAuth delegates
	// -------------------------------------------------------------------------

	public function oauth_twitter_init()     { ( new WSP_OAuth_Twitter() )->init_oauth(); }
	public function oauth_twitter_callback() { ( new WSP_OAuth_Twitter() )->handle_callback(); }

	public function oauth_twitter_disconnect() {
		check_admin_referer( 'wsp_oauth_twitter_disconnect' );
		if ( current_user_can( 'manage_options' ) ) {
			( new WSP_OAuth_Twitter() )->disconnect();
		}
		wp_redirect( admin_url( 'admin.php?page=wp-social-publisher&tab=twitter&oauth_disconnected=1' ) );
		exit;
	}

	public function add_menus() {
		// Top-level menu.
		add_menu_page(
			__( 'WP Social Publisher', 'wp-social-publisher' ),
			__( 'Social Media', 'wp-social-publisher' ),
			'manage_options',
			'wp-social-publisher',
			array( $this, 'render_settings_page' ),
			'dashicons-share',
			81
		);

		// Settings submenu (replaces generic top-level item).
		add_submenu_page(
			'wp-social-publisher',
			__( 'Settings', 'wp-social-publisher' ),
			__( 'Settings', 'wp-social-publisher' ),
			'manage_options',
			'wp-social-publisher',
			array( $this, 'render_settings_page' )
		);

		// Activity log submenu.
		add_submenu_page(
			'wp-social-publisher',
			__( 'Activity Log', 'wp-social-publisher' ),
			__( 'Activity Log', 'wp-social-publisher' ),
			'manage_options',
			'wsp-activity-log',
			array( $this, 'render_log_page' )
		);
	}

	/**
	 * Enqueue admin CSS and JS only on plugin pages.
	 *
	 * @param string $hook
	 */
	public function enqueue_assets( $hook ) {
		$plugin_pages = array(
			'toplevel_page_wp-social-publisher',
			'social-media_page_wsp-activity-log',
		);
		// Also enqueue on post edit screens for the classic editor meta box.
		$is_post_screen = in_array( $hook, array( 'post.php', 'post-new.php' ), true );

		if ( ! in_array( $hook, $plugin_pages, true ) && ! $is_post_screen ) {
			return;
		}

		wp_enqueue_style(
			'wsp-admin',
			WSP_PLUGIN_URL . 'assets/admin.css',
			array(),
			WSP_VERSION
		);
		wp_enqueue_script(
			'wsp-admin',
			WSP_PLUGIN_URL . 'assets/admin.js',
			array( 'jquery' ),
			WSP_VERSION,
			true
		);
		wp_localize_script( 'wsp-admin', 'wspAdmin', array(
			'nonce'     => wp_create_nonce( 'wsp_admin_nonce' ),
			'testing'   => __( 'Testing…', 'wp-social-publisher' ),
			'testLabel' => __( 'Test Connection', 'wp-social-publisher' ),
			'error'     => __( 'Request failed. Please try again.', 'wp-social-publisher' ),
		) );
	}

	/**
	 * Show admin notices for configuration issues.
	 */
	public function admin_notices() {
		// WP Cron disabled notice.
		if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
			echo '<div class="notice notice-warning"><p>'
				. esc_html__( 'WP Social Publisher: WP Cron is disabled on this site. Social publishing requires WP Cron. Please enable it or set up a server-side cron job.', 'wp-social-publisher' )
				. '</p></div>';
		}
	}

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-social-publisher' ) );
		}
		require_once WSP_PLUGIN_DIR . 'admin/settings-page.php';
	}

	public function render_log_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-social-publisher' ) );
		}
		require_once WSP_PLUGIN_DIR . 'admin/log-viewer.php';
	}

	// -------------------------------------------------------------------------
	// AJAX handlers
	// -------------------------------------------------------------------------

	public function ajax_test_connection() {
		check_ajax_referer( 'wsp_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'wp-social-publisher' ) ) );
		}

		$platform = isset( $_POST['platform'] ) ? sanitize_key( $_POST['platform'] ) : '';
		$map      = array(
			'facebook'  => 'WSP_Facebook',
			'instagram' => 'WSP_Instagram',
			'linkedin'  => 'WSP_LinkedIn',
			'twitter'   => 'WSP_Twitter',
		);

		if ( ! isset( $map[ $platform ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Unknown platform.', 'wp-social-publisher' ) ) );
		}

		$class  = $map[ $platform ];
		$api    = new $class();
		$result = $api->test_connection();

		if ( $result['success'] ) {
			wp_send_json_success( array( 'message' => $result['message'] ) );
		} else {
			wp_send_json_error( array( 'message' => $result['message'] ) );
		}
	}

	public function ajax_retry_publish() {
		check_ajax_referer( 'wsp_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'wp-social-publisher' ) ) );
		}

		$log_id = isset( $_POST['log_id'] ) ? (int) $_POST['log_id'] : 0;
		if ( ! $log_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid log entry.', 'wp-social-publisher' ) ) );
		}

		$log = WSP_Post_Log::get_log( $log_id );
		if ( ! $log ) {
			wp_send_json_error( array( 'message' => __( 'Log entry not found.', 'wp-social-publisher' ) ) );
		}

		$allowed = array( 'facebook', 'instagram', 'linkedin', 'twitter' );
		if ( ! in_array( $log->platform, $allowed, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid platform.', 'wp-social-publisher' ) ) );
		}

		// Reset status to pending and re-schedule.
		global $wpdb;
		$table = $wpdb->prefix . 'wsp_post_log';
		$wpdb->update(
			$table,
			array( 'status' => 'pending' ),
			array( 'id' => $log_id ),
			array( '%s' ),
			array( '%d' )
		);

		wp_schedule_single_event(
			time() + 5,
			'wsp_publish_' . $log->platform,
			array( (int) $log->post_id )
		);

		wp_send_json_success( array( 'message' => __( 'Retry scheduled.', 'wp-social-publisher' ) ) );
	}
}
