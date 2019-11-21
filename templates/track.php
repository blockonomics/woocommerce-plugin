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
<div ng-app="shopping-cart-demo">
  <div ng-controller="AltcoinController">
    <div class="bnomics-order-container">
      <!-- Heading row -->
      <div class="bnomics-order-heading">
        <div class="bnomics-order-heading-wrapper">
          <div class="bnomics-order-id">
            <span class="bnomics-order-number" ng-cloak><?= __('Order #', 'blockonomics-bitcoin-payments') ?>{{order.order_id}}</span>
          </div>
        </div>
      </div>
      <!-- Spinner -->
      <div class="bnomics-spinner" ng-show="spinner" ng-cloak><div class="bnomics-ring"><div></div><div></div><div></div><div></div></div></div>
      <!-- Amount row -->
      <div class="bnomics-order-panel">
        <div class="bnomics-order-info" ng-init="altcoin_waiting=true">
          <div class="bnomics-bitcoin-pane" ng-hide="show_altcoin != 0" ng-init="show_altcoin=1"></div>
          <div class="bnomics-altcoin-pane" ng-style="{'border-left': (altcoin_waiting)?'none':''}" ng-hide="show_altcoin != 1">
            <div class="bnomics-altcoin-waiting" ng-show="altcoin_waiting" ng-init="altcoin_waiting=true" ng-cloak>
              <!-- WAITING_FOR_DEPOSIT -->
              <div class="bnomics-btc-info" style="display: flex;flex-wrap: wrap;" ng-show="order.altstatus == 'waiting'" ng-cloak>
                <div style="flex: 1">
                  <!-- QR Code -->
                  <div class="bnomics-qr-code">
                    <div class="bnomics-qr">
                      <a href="{{altcoinselect}}:{{order.altaddress}}?amount={{order.altamount}}&value={{order.altamount}}">
                        <qrcode data="{{altcoinselect}}:{{order.altaddress}}?amount={{order.altamount}}&value={{order.altamount}}" size="160" version="6">
                          <canvas class="qrcode"></canvas>
                        </qrcode>
                      </a>
                    </div>
                    <div class="bnomics-qr-code-hint">
                      <?= __('Click on the QR code to open in the wallet', 'blockonomics-bitcoin-payments') ?>
                    </div>
                  </div>
                </div>
                <div style="flex: 2;">
                  <div class="bnomics-altcoin-bg-color">
                    <!-- Payment Text -->
                    <div class="bnomics-order-status-wrapper">
                      <span class="bnomics-order-status-title" ng-show="order.altstatus == 'waiting'" ng-cloak >
                        <?= __('To confirm your order, please send the exact amount of <strong>{{altcoinselect}}</strong> to the given address', 'blockonomics-bitcoin-payments') ?>
                      </span>
                    </div>
                    <h4 class="bnomics-amount-title" for="invoice-amount">
                      {{order.altamount}} {{order.altsymbol}}
                    </h4>
                    <!-- Altcoin Address -->
                    <div class="bnomics-address">
                      <input ng-click="alt_address_click()" id="bnomics-alt-address-input" class="bnomics-address-input" type="text" ng-value="order.altaddress" readonly="readonly">
                      <i ng-click="alt_address_click()" class="material-icons bnomics-copy-icon">file_copy</i>
                    </div>
                    <div class="bnomics-copy-text" ng-show="copyshow" ng-cloak>
                      <?= __('Copied to clipboard', 'blockonomics-bitcoin-payments') ?>
                    </div>
                    <!-- Countdown Timer -->
                    <div ng-cloak ng-hide="order.altstatus != 'waiting'  || alt_clock <= 0" class="bnomics-progress-bar-wrapper">
                      <div class="bnomics-progress-bar-container">
                        <div class="bnomics-progress-bar" style="width: {{alt_progress}}%;">
                        </div>
                      </div>
                    </div>
                    <span class="ng-cloak bnomics-time-left" ng-hide="order.altstatus != 'waiting' || alt_clock <= 0">{{alt_clock*1000 | date:'mm:ss' : 'UTC'}} min left to pay your order
                    </span>
                  </div>
                  <div class="bnomics-altcoin-cancel">
                    <p ng-hide='show_refund_info'><?= __('Already paid? ', 'blockonomics-bitcoin-payments') ?><a href="" ng-click="already_paid()"><?= __('Click here', 'blockonomics-bitcoin-payments') ?></a></p>
                    <p ng-show='show_refund_info'><?= __('We haven\'t detected your payment yet. Please wait a while for your transaction to confirm. If it is already confirmed, there might be a problem with paid amount. ', 'blockonomics-bitcoin-payments') ?><a href="" ng-click="get_refund()"><?= __('Click here ', 'blockonomics-bitcoin-payments') ?></a><?= __('to get refund', 'blockonomics-bitcoin-payments') ?></p>
                  </div>
                  <!-- Blockonomics Credit -->
                  <div class="bnomics-powered-by">
                    <?= __('Powered by ', 'blockonomics-bitcoin-payments') ?>Blockonomics
                  </div>
                </div>
              </div>
              <!-- RECEIVED -->
              <div class="bnomics-altcoin-bg-color" ng-show="order.altstatus == 'received'" ng-cloak>
                <h4>Received</h4>
                <h4><i class="material-icons bnomics-alt-icon">check_circle</i></h4>
                <?= __('Your payment has been received and your order will be processed shortly.', 'blockonomics-bitcoin-payments') ?>
              </div>
              <!-- ADD_REFUND -->
              <div class="bnomics-status-flex bnomics-altcoin-bg-color" ng-show="order.altstatus == 'add_refund'" ng-cloak >
                <h4>Refund Required</h4>
                <p ng-hide="hide_refund_reason"><?= __('Your order couldn\'t be processed as you didn\'t pay the exact expected amount.<br>The amount you paid will be refunded.', 'blockonomics-bitcoin-payments') ?></p>
                <h4><i class="material-icons bnomics-alt-icon">error</i></h4>
                <p id="bnomics-refund-message"><?= __('Enter your refund address and click the button below to recieve your refund.', 'blockonomics-bitcoin-payments') ?></p>
                <div id="bnomics-refund-errors"></div>
                <input type="text" id="bnomics-refund-input" placeholder="{{order.altsymbol}} Address">
                <br>
                <button id="alt-refund-button" ng-click="add_refund_click()">Refund</button>
              </div>
              <!-- REFUNDED -->
              <div class="bnomics-status-flex bnomics-altcoin-bg-color" ng-show="order.altstatus == 'refunded'" ng-cloak >
                <h4>Refund Submitted</h4>
                <div><?= __('Your refund details have been submitted. The refund will be automatically sent to', 'blockonomics-bitcoin-payments') ?> <b>{{altrefund}}</b></div>
                <h4><i class="material-icons bnomics-alt-icon">autorenew</i></h4>
                <div><?= __('If you don\'t get refunded in a few hours, contact <a href="mailto:support@flyp.me">support@flyp.me</a> with the following uuid:', 'blockonomics-bitcoin-payments') ?><br><span id="alt-uuid"><b>{{altuuid}}</b></span></div>
                <div><?= __('We have emailed you the information on this page. You can safely close this window or navigate away', 'blockonomics-bitcoin-payments') ?></div>
              </div>
              <!-- EXPIRED -->
              <div class="bnomics-status-flex bnomics-altcoin-bg-color" ng-show="order.altstatus == 'expired'" ng-cloak >
                <h4>Expired</h4>
                <h4><i class="material-icons bnomics-alt-icon">timer</i></h4>
                <p><?= __('Use the browser back button and try again.', 'blockonomics-bitcoin-payments') ?></p>
                <p><?= __('If you already paid,', 'blockonomics-bitcoin-payments') ?> <strong><a href="" ng-click="get_refund()"><?= __('click here', 'blockonomics-bitcoin-payments') ?></a></strong> <?= __('to get a refund.', 'blockonomics-bitcoin-payments') ?></p>
              </div>
              <!-- LOW/HIGH -->
              <div class="bnomics-status-flex bnomics-altcoin-bg-color" ng-show="order.altstatus == 'low_high'" ng-cloak >
                <h4>Error</h4>
                <h4><i class="material-icons bnomics-alt-icon">error</i></h4>
                <p><?= __('Order amount too <strong>{{lowhigh}}</strong> for {{order.altsymbol}} payment.', 'blockonomics-bitcoin-payments') ?></p>
                <p><a href="" ng-click="go_back()"><?= __('Click here', 'blockonomics-bitcoin-payments') ?></a> <?= __('to go back and use BTC to complete the payment.', 'blockonomics-bitcoin-payments') ?></p>
              </div>
            </div>
          <!-- Blockonomics Credit -->
          <div class="bnomics-powered-by" ng-hide="order.altstatus == 'waiting'"><?= __('Powered by ', 'blockonomics-bitcoin-payments') ?>Blockonomics</div>
        </div>
      </div>
    </div>
  </div>
  <script>
    var blockonomics_time_period=<?php echo get_option('blockonomics_timeperiod', 10); ?>;
  </script>
  <script>
    var get_uuid="<?php if (isset($_REQUEST['uuid'])) { echo $_REQUEST['uuid']; } ?>";
  </script>
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