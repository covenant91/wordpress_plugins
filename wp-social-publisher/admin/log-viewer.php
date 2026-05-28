<?php
defined( 'ABSPATH' ) || die();

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Activity log WP_List_Table implementation.
 */
class WSP_Log_List_Table extends WP_List_Table {

	public function __construct() {
		parent::__construct( array(
			'singular' => 'log',
			'plural'   => 'logs',
			'ajax'     => false,
		) );
	}

	public function get_columns() {
		return array(
			'cb'        => '<input type="checkbox" />',
			'post'      => __( 'Post', 'wp-social-publisher' ),
			'platform'  => __( 'Platform', 'wp-social-publisher' ),
			'status'    => __( 'Status', 'wp-social-publisher' ),
			'caption'   => __( 'Caption', 'wp-social-publisher' ),
			'social_id' => __( 'Social ID', 'wp-social-publisher' ),
			'date'      => __( 'Date', 'wp-social-publisher' ),
		);
	}

	public function get_sortable_columns() {
		return array(
			'date'     => array( 'created_at', true ),
			'platform' => array( 'platform', false ),
			'status'   => array( 'status', false ),
		);
	}

	protected function get_bulk_actions() {
		return array( 'delete' => __( 'Delete', 'wp-social-publisher' ) );
	}

	protected function column_default( $item, $column_name ) {
		return esc_html( $item->$column_name ?? '' );
	}

	protected function column_cb( $item ) {
		return '<input type="checkbox" name="log_ids[]" value="' . (int) $item->id . '" />';
	}

	protected function column_post( $item ) {
		$post = get_post( $item->post_id );
		if ( ! $post ) {
			return esc_html( '#' . $item->post_id );
		}
		$edit_url = get_edit_post_link( $post->ID );
		return '<a href="' . esc_url( $edit_url ) . '">' . esc_html( $post->post_title ) . '</a>';
	}

	protected function column_platform( $item ) {
		$labels = array(
			'facebook'  => 'Facebook',
			'instagram' => 'Instagram',
			'linkedin'  => 'LinkedIn',
			'twitter'   => 'X (Twitter)',
		);
		$label = $labels[ $item->platform ] ?? ucfirst( $item->platform );
		return '<span class="smp-badge smp-badge-' . esc_attr( $item->platform ) . '">' . esc_html( $label ) . '</span>';
	}

	protected function column_status( $item ) {
		$output = '<span class="smp-badge smp-badge-' . esc_attr( $item->status ) . '">' . esc_html( $item->status ) . '</span>';

		if ( 'failed' === $item->status ) {
			$retry_url = '#';
			$output   .= ' <button type="button" class="button button-small smp-retry-btn"'
				. ' data-log-id="' . (int) $item->id . '"'
				. ' data-nonce="' . esc_attr( wp_create_nonce( 'wsp_admin_nonce' ) ) . '">'
				. esc_html__( 'Retry', 'wp-social-publisher' )
				. '</button>';
		}

		if ( $item->error_msg ) {
			$output .= '<p style="color:#d63638;font-size:11px;margin:2px 0 0">' . esc_html( $item->error_msg ) . '</p>';
		}

		return $output;
	}

	protected function column_caption( $item ) {
		$cap = $item->caption ?? '';
		if ( strlen( $cap ) > 80 ) {
			return '<span class="smp-caption-cell" title="' . esc_attr( $cap ) . '">'
				. esc_html( substr( $cap, 0, 80 ) ) . '…</span>';
		}
		return '<span class="smp-caption-cell">' . esc_html( $cap ) . '</span>';
	}

	protected function column_date( $item ) {
		return esc_html( get_date_from_gmt( $item->created_at, get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) );
	}

	public function prepare_items() {
		$per_page = 20;
		$paged    = $this->get_pagenum();

		// phpcs:disable WordPress.Security.NonceVerification
		$args = array(
			'platform'  => isset( $_GET['wsp_platform'] ) ? sanitize_key( $_GET['wsp_platform'] ) : '',
			'status'    => isset( $_GET['wsp_status'] )   ? sanitize_key( $_GET['wsp_status'] )   : '',
			'date_from' => isset( $_GET['wsp_date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['wsp_date_from'] ) ) : '',
			'date_to'   => isset( $_GET['wsp_date_to'] )   ? sanitize_text_field( wp_unslash( $_GET['wsp_date_to'] ) )   : '',
			'per_page'  => $per_page,
			'paged'     => $paged,
		);
		// phpcs:enable

		$total = WSP_Post_Log::count_logs( $args );
		$this->items = WSP_Post_Log::get_logs( $args );

		$this->set_pagination_args( array(
			'total_items' => $total,
			'per_page'    => $per_page,
			'total_pages' => ceil( $total / $per_page ),
		) );

		$this->_column_headers = array(
			$this->get_columns(),
			array(),
			$this->get_sortable_columns(),
		);
	}
}

// Handle bulk delete.
if ( isset( $_POST['action'] ) && 'delete' === $_POST['action'] && ! empty( $_POST['log_ids'] ) ) {
	check_admin_referer( 'bulk-logs' );
	if ( current_user_can( 'manage_options' ) ) {
		foreach ( (array) $_POST['log_ids'] as $id ) {
			WSP_Post_Log::delete_log( (int) $id );
		}
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Selected entries deleted.', 'wp-social-publisher' ) . '</p></div>';
	}
}

$table = new WSP_Log_List_Table();
$table->prepare_items();
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Social Media — Activity Log', 'wp-social-publisher' ); ?></h1>

	<form method="get" style="margin-bottom:12px">
		<input type="hidden" name="page" value="wsp-activity-log" />
		<select name="wsp_platform">
			<option value=""><?php esc_html_e( '— All Platforms —', 'wp-social-publisher' ); ?></option>
			<?php foreach ( array( 'facebook', 'instagram', 'linkedin', 'twitter' ) as $p ) : ?>
			<option value="<?php echo esc_attr( $p ); ?>"
				<?php selected( isset( $_GET['wsp_platform'] ) ? sanitize_key( $_GET['wsp_platform'] ) : '', $p ); ?>>
				<?php echo esc_html( ucfirst( $p ) ); ?>
			</option>
			<?php endforeach; ?>
		</select>
		<select name="wsp_status">
			<option value=""><?php esc_html_e( '— All Statuses —', 'wp-social-publisher' ); ?></option>
			<?php foreach ( array( 'sent', 'failed', 'skipped', 'pending' ) as $s ) : ?>
			<option value="<?php echo esc_attr( $s ); ?>"
				<?php selected( isset( $_GET['wsp_status'] ) ? sanitize_key( $_GET['wsp_status'] ) : '', $s ); ?>>
				<?php echo esc_html( ucfirst( $s ) ); ?>
			</option>
			<?php endforeach; ?>
		</select>
		<input type="date" name="wsp_date_from"
			value="<?php echo esc_attr( isset( $_GET['wsp_date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['wsp_date_from'] ) ) : '' ); ?>"
			placeholder="<?php esc_attr_e( 'From date', 'wp-social-publisher' ); ?>" />
		<input type="date" name="wsp_date_to"
			value="<?php echo esc_attr( isset( $_GET['wsp_date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['wsp_date_to'] ) ) : '' ); ?>"
			placeholder="<?php esc_attr_e( 'To date', 'wp-social-publisher' ); ?>" />
		<input type="submit" class="button" value="<?php esc_attr_e( 'Filter', 'wp-social-publisher' ); ?>" />
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=wsp-activity-log' ) ); ?>" class="button">
			<?php esc_html_e( 'Reset', 'wp-social-publisher' ); ?>
		</a>
	</form>

	<form method="post">
		<?php wp_nonce_field( 'bulk-logs' ); ?>
		<?php $table->display(); ?>
	</form>
</div>

<script>
(function($){
	$(document).on('click', '.smp-retry-btn', function(){
		var $btn   = $(this);
		var logId  = $btn.data('log-id');
		var nonce  = $btn.data('nonce');
		$btn.prop('disabled', true).text('<?php echo esc_js( __( 'Retrying…', 'wp-social-publisher' ) ); ?>');
		$.post(ajaxurl, { action: 'wsp_retry_publish', log_id: logId, nonce: nonce }, function(r){
			if(r.success){
				$btn.closest('tr').find('.smp-badge').text('pending').removeClass().addClass('smp-badge smp-badge-pending');
				$btn.remove();
			} else {
				$btn.prop('disabled', false).text('<?php echo esc_js( __( 'Retry', 'wp-social-publisher' ) ); ?>');
				alert(r.data.message);
			}
		});
	});
}(jQuery));
</script>
