<?php
/**
 * Handles reads and writes to the wsp_post_log database table.
 */
class WSP_Post_Log {

	/**
	 * @return string
	 */
	private static function table() {
		global $wpdb;
		return $wpdb->prefix . 'wsp_post_log';
	}

	/**
	 * Insert a pending log entry.
	 *
	 * @param int    $post_id
	 * @param string $platform
	 * @param string $caption
	 * @return int|false  Inserted row ID or false on failure.
	 */
	public static function insert( $post_id, $platform, $caption = '' ) {
		global $wpdb;
		$wpdb->insert(
			self::table(),
			array(
				'post_id'  => (int) $post_id,
				'platform' => sanitize_key( $platform ),
				'caption'  => $caption,
				'status'   => 'pending',
			),
			array( '%d', '%s', '%s', '%s' )
		);
		return $wpdb->insert_id ?: false;
	}

	/**
	 * Update the most recent pending row for a post+platform with the API result.
	 *
	 * @param int    $post_id
	 * @param string $platform
	 * @param array  $result   Keys: success (bool), social_id (string|null), error (string|null)
	 */
	public static function update_result( $post_id, $platform, array $result ) {
		global $wpdb;

		$status = $result['success'] ? 'sent' : 'failed';

		// Handle 'skipped' status passed directly via error key.
		if ( isset( $result['status'] ) && 'skipped' === $result['status'] ) {
			$status = 'skipped';
		}

		$wpdb->update(
			self::table(),
			array(
				'status'    => $status,
				'social_id' => isset( $result['social_id'] ) ? $result['social_id'] : null,
				'error_msg' => isset( $result['error'] ) ? $result['error'] : null,
			),
			array(
				'post_id'  => (int) $post_id,
				'platform' => sanitize_key( $platform ),
				'status'   => 'pending',
			),
			array( '%s', '%s', '%s' ),
			array( '%d', '%s', '%s' )
		);
	}

	/**
	 * Check if a post has already been successfully sent to a platform.
	 *
	 * @param int    $post_id
	 * @param string $platform
	 * @return bool
	 */
	public static function already_sent( $post_id, $platform ) {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM `{$table}` WHERE post_id = %d AND platform = %s AND status = 'sent'",
				(int) $post_id,
				sanitize_key( $platform )
			)
		);
		return (int) $count > 0;
	}

	/**
	 * Retrieve log entries with optional filters.
	 *
	 * @param array $args {
	 *   @type string $platform
	 *   @type string $status
	 *   @type string $date_from  Y-m-d
	 *   @type string $date_to    Y-m-d
	 *   @type int    $per_page   Default 20.
	 *   @type int    $paged      Default 1.
	 * }
	 * @return object[]
	 */
	public static function get_logs( array $args = array() ) {
		global $wpdb;
		$table = self::table();

		$where  = array( '1=1' );
		$values = array();

		if ( ! empty( $args['platform'] ) ) {
			$where[]  = 'platform = %s';
			$values[] = sanitize_key( $args['platform'] );
		}
		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'status = %s';
			$values[] = sanitize_key( $args['status'] );
		}
		if ( ! empty( $args['date_from'] ) ) {
			$where[]  = 'created_at >= %s';
			$values[] = sanitize_text_field( $args['date_from'] ) . ' 00:00:00';
		}
		if ( ! empty( $args['date_to'] ) ) {
			$where[]  = 'created_at <= %s';
			$values[] = sanitize_text_field( $args['date_to'] ) . ' 23:59:59';
		}

		$per_page = isset( $args['per_page'] ) ? max( 1, (int) $args['per_page'] ) : 20;
		$paged    = isset( $args['paged'] ) ? max( 1, (int) $args['paged'] ) : 1;
		$offset   = ( $paged - 1 ) * $per_page;

		$where_sql = implode( ' AND ', $where );

		if ( ! empty( $values ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM `{$table}` WHERE {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d",
					array_merge( $values, array( $per_page, $offset ) )
				)
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM `{$table}` WHERE {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d",
					$per_page,
					$offset
				)
			);
		}

		return $rows ?: array();
	}

	/**
	 * Count log entries matching filters (for pagination).
	 *
	 * @param array $args  Same keys as get_logs(), minus pagination.
	 * @return int
	 */
	public static function count_logs( array $args = array() ) {
		global $wpdb;
		$table = self::table();

		$where  = array( '1=1' );
		$values = array();

		if ( ! empty( $args['platform'] ) ) {
			$where[]  = 'platform = %s';
			$values[] = sanitize_key( $args['platform'] );
		}
		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'status = %s';
			$values[] = sanitize_key( $args['status'] );
		}
		if ( ! empty( $args['date_from'] ) ) {
			$where[]  = 'created_at >= %s';
			$values[] = sanitize_text_field( $args['date_from'] ) . ' 00:00:00';
		}
		if ( ! empty( $args['date_to'] ) ) {
			$where[]  = 'created_at <= %s';
			$values[] = sanitize_text_field( $args['date_to'] ) . ' 23:59:59';
		}

		$where_sql = implode( ' AND ', $where );

		if ( ! empty( $values ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			return (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM `{$table}` WHERE {$where_sql}",
					$values
				)
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}` WHERE {$where_sql}" );
	}

	/**
	 * Delete a single log entry by ID.
	 *
	 * @param int $id
	 */
	public static function delete_log( $id ) {
		global $wpdb;
		$wpdb->delete( self::table(), array( 'id' => (int) $id ), array( '%d' ) );
	}

	/**
	 * Delete log entries older than $days days. Called by wsp_purge_logs cron.
	 *
	 * @param int $days
	 */
	public static function purge_old_logs( $days ) {
		global $wpdb;
		$table = self::table();
		$days  = max( 1, (int) $days );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM `{$table}` WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
				$days
			)
		);
	}

	/**
	 * Get a single log entry by ID.
	 *
	 * @param int $id
	 * @return object|null
	 */
	public static function get_log( $id ) {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d", (int) $id )
		);
	}

	/**
	 * Get the most recent pending log entry for a post+platform (used by cron dispatch).
	 *
	 * @param int    $post_id
	 * @param string $platform
	 * @return object|null
	 */
	public static function get_log_pending( $post_id, $platform ) {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM `{$table}` WHERE post_id = %d AND platform = %s AND status = 'pending' ORDER BY id DESC LIMIT 1",
				(int) $post_id,
				sanitize_key( $platform )
			)
		);
	}
}
