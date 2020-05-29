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
?>

<div id="active_currencies" data-active_currencies='<?php echo json_encode(get_option('blockonomics_active_currency')); ?>'></div>

<div ng-app="shopping-cart-demo">
  <div ng-controller="CheckoutController">
    <div class="bnomics-order-container">
    <!-- Blockonomics Currency Selecter -->
    <div class="bnomics-select-container" ng-show="currency_selecter" ng-cloak>
          <h2 id="paytitle">Pay With</h2>
          <table width="100%">
            <tr class="bnomics-select-options" ng-click="select_blockonomics_currency('BTC')">
               <td align="left"><img src="<?php echo plugins_url('img/btc.png', dirname(__FILE__));?>" class="rotateimgbtc" alt="btc Logo"> <h3>Bitcoin</h3> <span class="bnomics-select-currency-button"><button type="button" class="btn btn-lg bnomics-select-currency-code">BTC</button></span></td>
            </tr>
            <tr class="bnomics-select-options" ng-click="select_blockonomics_currency('BCH')">
            <td align="left"><img src="<?php echo plugins_url('img/bch.png', dirname(__FILE__));?>" class="rotateimgbch" alt="bch Logo"> <h3>Bitcoin Cash</h3> <span class="bnomics-select-currency-button"><button type="button" class="btn btn-lg bnomics-select-currency-code">BCH</button></span></td>
            </tr>
          </table>
          <div class="bnomics-how-to-pay">
				    <a href="https://blog.blockonomics.co/how-to-pay-a-bitcoin-invoice-abf4a04d041c" target="_blank">How to pay | Check reviews of this shop</a>
          </div>
          <div class="bnomics-powered-by">
              <?=__('Powered by ', 'blockonomics-bitcoin-payments')?>Blockonomics
            </div>
      </div>
     <!-- Spinner -->
    <div class="bnomics-spinner" ng-show="spinner" ng-cloak><div class="bnomics-ring"><div></div><div></div><div></div><div></div></div></div>
    <div ng-show="payment">
      <!-- Heading row -->
      <div class="bnomics-order-heading">
        <div class="bnomics-order-heading-wrapper">
          <?php if (get_option('blockonomics_altcoins')) : ?>
          <div class="bnomics-payment-option" ng-hide="order.status == -3">
            <span class="bnomics-paywith-label" ng-cloak> <?=__('Pay with', 'blockonomics-bitcoin-payments')?> </span>
            <span>
              <span class="bnomics-paywith-option bnomics-paywith-btc" ng-class={'bnomics-paywith-selected':show_altcoin=='0'} ng-click="show_altcoin=0">BTC</span><span class="bnomics-paywith-option bnomics-paywith-altcoin" ng-class={'bnomics-paywith-selected':show_altcoin=='1'} ng-click="show_altcoin=1">Altcoins</span>     
            </span>
          </div><br>
          <?php endif;?>
          <div class="bnomics-order-id">
            <span class="bnomics-order-number" ng-cloak> <?=__('Order #', 'blockonomics-bitcoin-payments')?>{{order.order_id}}</span>
          </div>
        </div>
      </div>
      <!-- Spinner -->
      <div class="bnomics-spinner" ng-show="spinner" ng-cloak><div class="bnomics-ring"><div></div><div></div><div></div><div></div></div></div>
      <!-- Amount row -->
      <div class="bnomics-order-panel">
        <div class="bnomics-order-info">

          <div class="bnomics-bitcoin-pane" ng-hide="show_altcoin != 0" ng-init="show_altcoin=0;" ng-cloak>
            <div class="bnomics-btc-info">
              <!-- QR and Amount -->
              <div class="bnomics-qr-code" ng-hide="order.status == -3">
                <div class="bnomics-qr">
                          <a href="bitcoin:{{order.address}}?amount={{order.satoshi/1.0e8}}">
                            <qrcode data="bitcoin:{{order.address}}?amount={{order.satoshi/1.0e8}}" size="160" version="6">
                              <canvas class="qrcode"></canvas>
                            </qrcode>
                          </a>
                </div>
                <div class="bnomics-qr-code-hint"><?=__('Click on the QR code to open in the wallet', 'blockonomics-bitcoin-payments')?></div>
              </div>
              <!-- BTC Amount -->
              <div class="bnomics-amount">
              <div class="bnomics-bg">
                <!-- Order Status -->
                <div class="bnomics-order-status-wrapper">
                  <span class="bnomics-order-status-title" ng-show="order.status == -1" ng-cloak ><?=__('To confirm your order, please send the exact amount of <strong>{{currency}}</strong> to the given address', 'blockonomics-bitcoin-payments')?></span>
                  <span class="warning bnomics-status-warning" ng-show="order.status == -3" ng-cloak><?=__('<b>PAYMENT EXPIRED</b> <br /><br /><a href="javascript:history.back()">Click here</a> to try again.<br /><br /><div>If you already paid, your order will be processed automatically. <br />You can safely close this window.</div>', 'blockonomics-bitcoin-payments')?></span>
                  <span class="warning bnomics-status-warning" ng-show="order.status == -2" ng-cloak><?=__('Payment Error', 'blockonomics-bitcoin-payments')?></span>
                </div>
                    <h4 ng-click="crypto_amount_click()" class="bnomics-amount-title" for="invoice-amount" ng-hide="order.status == -3">
                     {{order.satoshi/1.0e8}} {{currency}}
                    </h4>
                    <div class="bnomics-ammount-copy-text" ng-hide="order.status == -3 || amountcopyshow == false" ng-cloak>Copied to clipboard</div>
                    <div class="bnomics-amount-wrapper" ng-hide="order.status == -3">
                      <hr class="bnomics-amount-seperator"> ≈
                      <span ng-cloak>{{order.value}}</span>
                      <small ng-cloak>{{order.currency}}</small>
                    </div>
              <!-- Bitcoin Address -->
                <div class="bnomics-address" ng-hide="order.status == -3">
                  <input ng-click="crypto_address_click()" id="bnomics-address-input" class="bnomics-address-input" type="text" value="{{order.address}}" readonly="readonly">
                </div>
                <div class="bnomics-copy-text" ng-hide="order.status == -3 || copyshow == false" ng-cloak>Copied to clipboard</div>
            <!-- Countdown Timer -->
                <div ng-cloak ng-hide="order.status != -1" class="bnomics-progress-bar-wrapper">
                  <div class="bnomics-progress-bar-container">
                    <div class="bnomics-progress-bar" style="width: {{progress}}%;"></div>
                  </div>
                </div>
                <span class="ng-cloak bnomics-time-left" ng-hide="order.status != -1">{{clock*1000 | date:'mm:ss' : 'UTC'}} min left to pay your order</span>
              </div>
        <!-- Blockonomics Credit -->
			<div class="bnomics-how-to-pay" ng-hide="order.status != -1">
				<a href="https://blog.blockonomics.co/how-to-pay-a-bitcoin-invoice-abf4a04d041c" target="_blank">How to pay | Check reviews of this shop</a>
			</div>
            <div class="bnomics-powered-by">
              <?=__('Powered by ', 'blockonomics-bitcoin-payments')?>Blockonomics
            </div>
              </div>
            </div>
          </div>
          <!-- Display Error -->
          <div class="bnomics-display-error" style="text-align: center;" ng-hide="display_problems">
            <h4>Display Error</h4>
            <h4><i class="material-icons bnomics-alt-icon">error</i></h4>
            <?= __('Unable to render correctly, Note to Administrator: Please enable lite mode in Blockonomics plugin.', 'blockonomics-bitcoin-payments') ?>
          </div>
        </div>
      </div>
    </div>
    <script>
    var blockonomics_time_period=<?php echo get_option('blockonomics_timeperiod', 10); ?>;
    </script>
    <script>
    var get_uuid="<?php if(isset($_REQUEST['uuid'])){echo $_REQUEST['uuid'];} ?>";
    </script>
    </div>
  </div>
</div>
<?php 
if($lite_version){
?>
  <script>var ajax_object = {ajax_url:"<?php echo admin_url( 'admin-ajax.php' ); ?>", wc_url:"<?php echo WC()->api_request_url('WC_Gateway_Blockonomics'); ?>"};
  </script>
  <script src="<?php echo plugins_url('js/angular.min.js', dirname(__FILE__));?>"></script>
  <script src="<?php echo plugins_url('js/angular-resource.min.js', dirname(__FILE__));?>"></script>
  <script src="<?php echo plugins_url('js/app.js', dirname(__FILE__));?>"></script>
  <script src="<?php echo plugins_url('js/angular-qrcode.js', dirname(__FILE__));?>"></script>
  <script src="<?php echo plugins_url('js/vendors.min.js', dirname(__FILE__));?>"></script>
  <script src="<?php echo plugins_url('js/reconnecting-websocket.min.js', dirname(__FILE__));?>"></script>
<?php
}else{
  get_footer();
}
?>
