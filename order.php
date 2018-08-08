<?php get_header();?>

<div ng-app="shopping-cart-demo">
  <div ng-controller="CheckoutController">

    <?php if (get_option('blockonomics_altcoins')) : ?>
    <div class="bnomics-order-container" style="max-width: 800px;">
    <?php else : ?>
    <div class="bnomics-order-container" style="max-width: 600px;">
    <?php endif;?>

      <!-- Heading row -->
      <div class="bnomics-order-heading">
        <div class="bnomics-order-heading-wrapper">
          <div class="bnomics-order-id">
            <span class="bnomics-order-number" ng-cloak> <?=__('Order#', 'blockonomics-bitcoin-payments')?> {{order.order_id}}</span>
            <span class="alignright ng-cloak bnomics-time-left" ng-hide="order.status != -1 || altcoin_waiting">{{clock*1000 | date:'mm:ss' : 'UTC'}}</span>
          </div>

          <div ng-cloak ng-hide="order.status != -1 || altcoin_waiting" class="bnomics-progress-bar-wrapper">
            <div class="bnomics-progress-bar-container">
              <div class="bnomics-progress-bar" style="width: {{progress}}%;"></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Amount row -->
      <div class="bnomics-order-panel">
        <div class="bnomics-order-info">

          <div class="bnomics-bitcoin-pane" ng-hide="altcoin_waiting">
            <!-- Order Status -->
            <h4 class="bnomics-order-status-title" ng-show="order.status != -1" for="invoice-amount" style="margin-top:15px;" ng-cloak><?=__('Status', 'blockonomics-bitcoin-payments')?></h4>
            <div class="bnomics-order-status-wrapper">
              <h4 class="bnomics-order-status-title" ng-show="order.status == -1" ng-cloak ><?=__('To pay, send exact amount of BTC to the given address', 'blockonomics-bitcoin-payments')?></h4>
              <span class="warning bnomics-status-warning" ng-show="order.status == -3" ng-cloak><?=__('Payment Expired (Use browser back button and try again)', 'blockonomics-bitcoin-payments')?></span>
              <span class="warning bnomics-status-warning" ng-show="order.status == -2" ng-cloak><?=__('Payment Error', 'blockonomics-bitcoin-payments')?></span>
              <span ng-show="order.status == 0" ng-cloak><?=__('Unconfirmed', 'blockonomics-bitcoin-payments')?></span>
              <span ng-show="order.status == 1" ng-cloak><?=__('Partially Confirmed', 'blockonomics-bitcoin-payments')?></span>
              <span ng-show="order.status >= 2" ng-cloak ><?=__('Confirmed', 'blockonomics-bitcoin-payments')?></span>
            </div>

            <div class="bnomics-btc-info">
              <!-- QR and Amount -->
              <div class="bnomics-qr-code">
                <h5 class="bnomics-qr-code-title" for="btn-address"><?=__('Bitcoin Address', 'blockonomics-bitcoin-payments')?></h5>
                <a href="bitcoin:{{order.address}}?amount={{order.satoshi/1.0e8}}">
                  <qrcode data="bitcoin:{{order.address}}?amount={{order.satoshi/1.0e8}}" size="160" version="6">
                    <canvas class="qrcode"></canvas>
                  </qrcode>
                </a>
                <h5 class="bnomics-qr-code-hint"><?=__('Click on the QR code above to open in wallet', 'blockonomics-bitcoin-payments')?></h5>
              </div>

              <!-- BTC Amount -->
              <div class="bnomics-amount">
                <h4 class="bnomics-amount-title" for="invoice-amount"><?=__('Amount', 'blockonomics-bitcoin-payments')?></h4>
                <div class="bnomics-amount-wrapper">
                  <span ng-show="order.satoshi" ng-cloak>{{order.satoshi/1.0e8}}</span>
                  <small>BTC</small> ⇌
                  <span ng-cloak>{{order.value}}</span>
                  <small ng-cloak>{{order.currency}}</small>
                </div>
              </div>
            </div>

            <!-- Bitcoin Address -->
            <div class="bnomics-address">
              <input class="bnomics-address-input" type="text" ng-value="order.address" readonly="readonly">
            </div>
            <div class="bnomics-powered-by">
              <?=__('Powered by ', 'blockonomics-bitcoin-payments')?><a href='https://www.blockonomics.co/' target='_blank'>blockonomics</a>
            </div>
          </div>

          <?php if (get_option('blockonomics_altcoins')) : ?>
          <div class="bnomics-altcoin-pane" ng-style="{'border-left': (altcoin_waiting)?'none':''}">

            <div ng-hide="altcoin_waiting" ng-cloak>
              <h4 class="bnomics-altcoin-hint"> <?=__('OR you can ', 'blockonomics-bitcoin-payments')?></h4>
              <div class="bnomics-altcoin-button-wrapper">
                <a ng-click="pay_altcoins()" href=""><img  style="margin: auto;" src="https://shapeshift.io/images/shifty/small_dark_altcoins.png"  class="ss-button"></a>
                <div class="bnomics-altcoin-info-wrapper">
                  <h5 class="bnomics-altcoin-info"><?=__('Ethereum, Bitcoin Cash, Dash and many others supported', 'blockonomics-bitcoin-payments')?></h5>
                </div>
              </div>
            </div>

            <div class="bnomics-altcoin-waiting-wrapper" ng-show="altcoin_waiting" ng-cloak>
              <h4 class="bnomics-altcoin-waiting-info"><?=__('Waiting for BTC payment from shapeshift altcoin conversion ', 'blockonomics-bitcoin-payments')?></h4>
              <div class="bnomics-spinner"></div>
              <h4 class="bnomics-altcoin-cancel"><a href="" ng-click="altcoin_waiting=false"> Click here</a> to cancel and go back </h4>
            </div>

          </div>
          <?php endif ?>

        </div>
      </div>
    </div>
    <script>
    var blockonomics_time_period=<?php echo get_option('blockonomics_timeperiod', 10); ?>;
    </script>
    <script src="<?php echo plugins_url('js/angular.min.js', __FILE__);?>"></script>
    <script src="<?php echo plugins_url('js/angular-resource.min.js', __FILE__);?>"></script>
    <script src="<?php echo plugins_url('js/app.js', __FILE__);?>"></script>
    <script src="<?php echo plugins_url('js/angular-qrcode.js', __FILE__);?>"></script>
    <script src="<?php echo plugins_url('js/vendors.min.js', __FILE__);?>"></script>
    <script src="<?php echo plugins_url('js/reconnecting-websocket.min.js', __FILE__);?>"></script>
  </div>
</div>

<?php get_footer();?>
