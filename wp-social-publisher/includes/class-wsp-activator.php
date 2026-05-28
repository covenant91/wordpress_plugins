<?php
/**
 * Handles plugin activation.
 */
class WSP_Activator {

	/**
	 * Create DB table, set default options, schedule cron.
	 */
	public static function activate() {
		self::create_table();
		self::set_defaults();
		self::schedule_cron();
		update_option( 'wsp_version', WSP_VERSION );
	}

	private static function create_table() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table      = $wpdb->prefix . 'wsp_post_log';
		$charset    = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			post_id     BIGINT(20) UNSIGNED NOT NULL,
			platform    VARCHAR(20)         NOT NULL,
			status      VARCHAR(20)         NOT NULL DEFAULT 'pending',
			social_id   VARCHAR(255)                 DEFAULT NULL,
			error_msg   TEXT                         DEFAULT NULL,
			caption     TEXT                         DEFAULT NULL,
			created_at  DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY post_id  (post_id),
			KEY platform (platform),
			KEY status   (status)
		) {$charset};";

		dbDelta( $sql );
	}

	private static function set_defaults() {
		$defaults = array(
			'facebook'          => array(
				'app_id'       => '',
				'app_secret'   => '',
				'page_token'   => '',
				'page_id'      => '',
			),
			'instagram'         => array(
				'user_id'      => '',
			),
			'linkedin'          => array(
				'client_id'    => '',
				'client_secret' => '',
				'access_token' => '',
				'urn'          => '',
				'urn_type'     => 'person',
				'token_expiry' => '',
			),
			'twitter'           => array(
				'consumer_key'        => '',
				'consumer_secret'     => '',
				'access_token'        => '',
				'access_token_secret' => '',
			),
			'defaults'          => array(
				'auto_append_url'    => true,
				'log_retention_days' => 90,
				'enabled_post_types' => array( 'post' ),
				'hashtags'           => array(
					'facebook'  => '',
					'instagram' => '',
					'linkedin'  => '',
					'twitter'   => '',
				),
			),
		);

		add_option( 'wsp_settings', $defaults );
	}

	private static function schedule_cron() {
		if ( ! wp_next_scheduled( 'wsp_token_check' ) ) {
			wp_schedule_event( time(), 'daily', 'wsp_token_check' );
		}
		if ( ! wp_next_scheduled( 'wsp_purge_logs' ) ) {
			wp_schedule_event( time(), 'daily', 'wsp_purge_logs' );
		}
	}
}
