<?php
defined( 'ABSPATH' ) or exit;
add_action( 'plugins_loaded', 'wc_filter_orders_by_bitcoin_address' );

class WC_Filter_Orders_By_Address {
	protected static $instance;
	public function __construct() {
		if ( is_admin() ) {
			// add bulk order filter for exported / non-exported orders
			add_action( 'restrict_manage_posts', array( $this, 'filter_orders') , 20 );
			add_filter( 'request',               array( $this, 'filter_orders_by_address_or_txid' ) );	

		}
	}
	public function filter_orders() {
		global $typenow;
		if ( 'shop_order' === $typenow ) {
			?>
			<input size='26' value="<?php if(isset( $_GET['filter_by'] )) echo($_GET['filter_by']); ?>" type='name' placeholder='Filter by crypto address/txid' name='filter_by'>
			<?php
		}
	}
	public function filter_orders_by_address_or_txid( $vars ) {
		global $typenow;
		if ( 'shop_order' === $typenow && isset( $_GET['filter_by'] ) && ! empty( $_GET['filter_by'])){
			$vars['meta_value'] = wc_clean( $_GET['filter_by'] );
		}
		return $vars;
	}
	
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
}

function wc_filter_orders_by_bitcoin_address() {
    return WC_Filter_Orders_By_Address::instance();
}
