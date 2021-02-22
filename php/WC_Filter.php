<?php
defined( 'ABSPATH' ) or exit;

if ( is_admin() ) {
	// add bulk order filter for exported / non-exported orders
	add_action( 'restrict_manage_posts', 'filter_orders' , 20 );
	add_filter( 'request', 'filter_orders_by_address_or_txid' );	
}

function filter_orders() {
	global $typenow;
	if ( 'shop_order' === $typenow ) {
		?>
		<input size='26' value="<?php if(isset( $_GET['filter_by'] )) echo($_GET['filter_by']); ?>" type='name' placeholder='Filter by crypto address/txid' name='filter_by'>
		<?php
	}
}

function filter_orders_by_address_or_txid( $vars ) {
	global $typenow;
	if ( 'shop_order' === $typenow && isset( $_GET['filter_by'] ) && ! empty( $_GET['filter_by'])){
		$vars['meta_value'] = wc_clean( $_GET['filter_by'] );
	}
	return $vars;
}