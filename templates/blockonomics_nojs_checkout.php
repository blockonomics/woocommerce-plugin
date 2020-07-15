<?php
$orders = get_option('blockonomics_orders');
$order_id = $_REQUEST['show_order'];
$crypto = $_REQUEST['crypto'];
foreach ($orders[$order_id] as $addr => $order){
  if ($order['crypto'] == $crypto) break;
}
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
                  <span class="bnomics-order-status-title">To confirm your order, please send the exact amount of <strong><?php echo strtoupper($order['crypto'])?></strong> to the given address</span>
                </div>
                    <h4 class="bnomics-amount-title" for="invoice-amount">
                     <?php
                     if($order['satoshi'] < 10000){
                       echo rtrim(number_format($order['satoshi']/1.0e8, 8),0);
                     }else{
                       echo $order['satoshi']/1.0e8;
                     }
                     ?> <?php echo strtoupper($order['crypto'])?>
                    </h4>
                    <div class="bnomics-amount-wrapper">
                      <hr class="bnomics-amount-seperator"> ≈
                      <span><?php echo $order['value']?></span>
                      <small><?php echo $order['currency']?></small>
                    </div>
              <!-- Bitcoin Address -->
                <div class="bnomics-address">
                  <input id="bnomics-address-input" class="bnomics-address-input" type="text" readonly="readonly" value="<?php echo $order['addr'] ?>">
                </div>
              </div>
        <!-- Blockonomics Credit -->
			<div class="bnomics-how-to-pay">
				<a href="https://blog.blockonomics.co/how-to-pay-a-bitcoin-invoice-abf4a04d041c" target="_blank">How to pay | Check reviews of this shop</a>
			</div>
      <br>
      <div>
        <a href="?payment_check=<?php echo $order['order_id'];?>&crypto=<?php echo $order['crypto'];?>">Please click here if already paid</a>
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