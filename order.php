<?php get_header();?>

<div ng-app="shopping-cart-demo">
  <div ng-controller="CheckoutController">
    <div id="paywrapper" class="payment-wrapper center">
      <div ng-hide="altcoin_waiting">
        <h3><?=__('Order#', 'blockonomics-bitcoin-payments')?> {{order.order_id}}</h3>
        <div class="clear"></div>

        <div class="info center">
          <div>
            <p ng-show="order.status == -1" ng-cloak ><?=__('To pay, send exact amount of BTC to the given address', 'blockonomics-bitcoin-payments')?></p>
            <span class="warning" ng-show="order.status == -3" ng-cloak><?=__('Payment Expired (Use browser back button and try again)', 'blockonomics-bitcoin-payments')?></span>
            <span class="warning" ng-show="order.status == -2" ng-cloak><?=__('Payment Error', 'blockonomics-bitcoin-payments')?></span>
          </div>

          <h2>{{order.satoshi/1.0e8}} BTC</h2>
          <hr id="divider">
          <p id="fiat">&asymp; {{order.value}} {{order.currency}}</p>
          <div class="address"><b>{{order.address}}</b></div>

          <div class="time-left" ng-cloak ng-hide="order.status != -1 || altcoin_waiting">
            <div class="bnomics-progress-bar-container">
              <div class="bnomics-progress-bar" style="width: {{progress}}%;"></div>
            </div>
            <p><span class="ng-cloak" ng-hide="order.status != -1 || altcoin_waiting">{{clock*1000 | date:'mm:ss' : 'UTC'}}</span> min left to pay your order</p>
          </div>

          <p class="powered">Powered by Blockonomics</p>
        </div>

        <div class="qr-code-wrapper">
          <a  id="btc-address-a" href="bitcoin:{{order.address}}?amount={{order.satoshi/1.0e8}}">
            <qrcode data="bitcoin:{{order.address}}?amount={{order.satoshi/1.0e8}}" size="170">
              <canvas class="qrcode"></canvas>
            </qrcode>
          </a>

          <a id="btc-address-a" href="bitcoin:{{order.address}}?amount={{order.satoshi/1.0e8}}">
            <div id="qrcode"></div>
          </a>

          <p><?=__('Click on the QR code above to open in wallet', 'blockonomics-bitcoin-payments')?></p>

          <?php if (get_option('blockonomics_altcoins')) : ?>
          <div class="bnomics-altcoin-pane">
            <a ng-click="pay_altcoins()" href=""><img style="margin: auto;" src="https://shapeshift.io/images/shifty/small_dark_altcoins.png" class="ss-button"></a>
          </div>
          <?php endif ?>

          <p class="powered">Powered by Blockonomics</p>
        </div>

      </div>

      <div ng-show="altcoin_waiting" ng-cloak>
        <h4><?=__('Waiting for BTC payment from shapeshift altcoin conversion ', 'blockonomics-bitcoin-payments')?></h4>
        <div class="bnomics-spinner"></div>
        <h4><a href="" ng-click="altcoin_waiting=false"> Click here</a> to cancel and go back </h4>
      </div>

    </div>
    <div class="clear"></div>
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
