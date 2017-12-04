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
        <div>
          <div >
            <span ng-cloak> <?=__('Order#', 'blockonomics-woocommerce')?> {{order.order_id}}</span>
            <span class="alignright ng-cloak" ng-hide="order.status != -1 || altcoin_waiting">{{clock*1000 | date:'mm:ss' : 'UTC'}}</span>
          </div>

          <div ng-cloak ng-hide="order.status != -1 || altcoin_waiting">
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
            <h4 ng-show="order.status != -1" for="invoice-amount" style="margin-top:15px;" ng-cloak><?=__('Status', 'blockonomics-woocommerce')?></h4>
            <div>
              <h4 ng-show="order.status == -1" ng-cloak ><?=__('To pay, send exact amount of BTC to the given address', 'blockonomics-woocommerce')?></h4>
              <span class="warning" ng-show="order.status == -3" ng-cloak><?=__('Payment Expired (Use browser back button and try again)', 'blockonomics-woocommerce')?></span>
              <span class="warning" ng-show="order.status == -2" ng-cloak><?=__('Payment Error', 'blockonomics-woocommerce')?></span>
              <span ng-show="order.status == 0" ng-cloak><?=__('Unconfirmed', 'blockonomics-woocommerce')?></span>
              <span ng-show="order.status == 1" ng-cloak><?=__('Partially Confirmed', 'blockonomics-woocommerce')?></span>
              <span ng-show="order.status >= 2" ng-cloak ><?=__('Confirmed', 'blockonomics-woocommerce')?></span>
            </div>

            <div class="bnomics-btc-info">
              <!-- QR and Amount -->
              <div class="bnomics-qr-code">
                <h5  for="btn-address"><?=__('Bitcoin Address', 'blockonomics-woocommerce')?></h5>
                <a href="bitcoin:{{order.address}}?amount={{order.satoshi/1.0e8}}">
                  <qrcode data="bitcoin:{{order.address}}?amount={{order.satoshi/1.0e8}}" size="160">
                    <canvas class="qrcode"></canvas>
                  </qrcode>
                </a>
                <h5><?=__('Click on the QR code above to open in wallet', 'blockonomics-woocommerce')?></h5>
              </div>

              <!-- BTC Amount -->
              <div class="bnomics-amount">
                <h4 for="invoice-amount"><?=__('Amount', 'blockonomics-woocommerce')?></h4>
                <div class="">
                  <span ng-show="order.satoshi" ng-cloak>{{order.satoshi/1.0e8}}</span>
                  <small>BTC</small> ⇌
                  <span ng-cloak>{{order.value}}</span>
                  <small ng-cloak>{{order.currency}}</small>
                </div>
              </div>
            </div>

            <!-- Bitcoin Address -->
            <div class="bnomics-address">
              <input type="text" ng-value="order.address" readonly="readonly">
            </div>
          </div>


          <?php if (get_option('blockonomics_altcoins')) : ?>
          <div class="bnomics-altcoin-pane" ng-style="{'border-left': (altcoin_waiting)?'none':''}">

            <div ng-hide="altcoin_waiting" ng-cloak>
              <h4> <?=__('OR you can ', 'blockonomics-woocommerce')?></h4>
              <div>
                <a ng-click="pay_altcoins()" href=""><img  style="margin: auto;" src="https://shapeshift.io/images/shifty/small_dark_altcoins.png"  class="ss-button"></a>
                <div>
                  <h5><?=__('Ethereum, Bitcoin Cash, Dash and many others supported', 'blockonomics-woocommerce')?></h5>
                </div>
              </div>
            </div>

            <div ng-show="altcoin_waiting" ng-cloak>
              <h4><?=__('Waiting for BTC payment from shapeshift altcoin conversion ', 'blockonomics-woocommerce')?></h4>
              <div class="bnomics-spinner"></div>
              <h4><a href="" ng-click="altcoin_waiting=false"> Click here</a> to cancel and go back </h4>
            </div>

          </div>
          <?php endif ?>

        </div>

      </div>
    </div>

    <script src="<?php echo plugins_url('js/angular.min.js', __FILE__);?>"></script>
    <script src="<?php echo plugins_url('js/angular-resource.min.js', __FILE__);?>"></script>
    <script src="<?php echo plugins_url('js/app.js', __FILE__);?>"></script>
    <script src="<?php echo plugins_url('js/angular-qrcode.js', __FILE__);?>"></script>
    <script src="<?php echo plugins_url('js/vendors.min.js', __FILE__);?>"></script>
    <script src="<?php echo plugins_url('js/reconnecting-websocket.min.js', __FILE__);?>"></script>
  </div>
</div>

<?php get_footer();?>
