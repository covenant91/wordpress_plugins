<?php
/**
 * Handles plugin deactivation.
 */
class WSP_Deactivator {

	/**
	 * Clear scheduled cron events on deactivation.
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( 'wsp_token_check' );
		wp_clear_scheduled_hook( 'wsp_purge_logs' );

		// Clear any pending per-post publish events.
		$crons = _get_cron_array();
		if ( ! is_array( $crons ) ) {
			return;
		}
		foreach ( $crons as $timestamp => $cron_hooks ) {
			foreach ( $cron_hooks as $hook => $events ) {
				if ( strpos( $hook, 'wsp_publish_' ) === 0 ) {
					foreach ( $events as $key => $event ) {
						wp_unschedule_event( $timestamp, $hook, $event['args'] );
					}
				}
			}
		}

		flush_rewrite_rules();
	}
}
