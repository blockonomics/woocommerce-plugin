<?php
$lite_version = get_option('blockonomics_lite');
if($lite_version){
?>
  <link rel="stylesheet" type="text/css" href="<?php echo plugins_url('css/order.css', dirname(__FILE__));?>">
  <link rel="stylesheet" type="text/css" href="<?php echo plugins_url('css/cryptofont/cryptofont.min.css', dirname(__FILE__));?>">
  <link rel="stylesheet" type="text/css" href="<?php echo plugins_url('css/icons/icons.css', dirname(__FILE__));?>">
<?php
}else{
  get_header();
}
$orders = get_option('blockonomics_orders');
$address = $_REQUEST['show_order'];
$order = $orders[$address];
?>
<div ng-app="shopping-cart-demo">
  <div ng-controller="CheckoutController">
    <div class="bnomics-order-container">
      <!-- Heading row -->
      <div class="bnomics-order-heading">
        <div class="bnomics-order-heading-wrapper">
          <div class="bnomics-order-id">
            <span class="bnomics-order-number"><?php echo 'ORDER #' . $order['order_id']?></span>
          </div>
        </div>
      </div>
      <!-- Amount row -->
      <div class="bnomics-order-panel">
        <div class="bnomics-order-info">

          <div class="bnomics-bitcoin-pane">
            <div class="bnomics-btc-info">
              <!-- BTC Amount -->
              <div class="bnomics-amount">
              <div class="bnomics-bg">
                <!-- Order Status -->
                <div class="bnomics-order-status-wrapper">
                  <span class="bnomics-order-status-title"><?=__('To confirm your order, please send the exact amount of <strong>BTC</strong> to the given address', 'blockonomics-bitcoin-payments')?></span>
                </div>
                    <h4 class="bnomics-amount-title" for="invoice-amount">
                     <?php echo $order['satoshi']/1.0e8;?> BTC
                    </h4>
                    <div class="bnomics-amount-wrapper">
                      <hr class="bnomics-amount-seperator"> ≈
                      <span><?php echo $order['value']?></span>
                      <small><?php echo $order['currency']?></small>
                    </div>
              <!-- Bitcoin Address -->
                <div class="bnomics-address">
                  <input id="bnomics-address-input" class="bnomics-address-input" type="text" readonly="readonly" value="<?php echo $_GET['show_order'];?>">
                </div>
              </div>
        <!-- Blockonomics Credit -->
			<div class="bnomics-how-to-pay">
				<a href="https://blog.blockonomics.co/how-to-pay-a-bitcoin-invoice-abf4a04d041c" target="_blank">How to pay | Check reviews of this shop</a>
			</div>
      <br>
      <div>
        <a href="/?wc-api=WC_Gateway_Blockonomics&payment_check=<?php echo $address;?>">Please click here if already paid</a>
      </div>
            <div class="bnomics-powered-by">
              <?=__('Powered by ', 'blockonomics-bitcoin-payments')?>Blockonomics
            </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php get_footer();?>
