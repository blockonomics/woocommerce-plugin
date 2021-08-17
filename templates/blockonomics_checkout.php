<?php
$blockonomics = new Blockonomics;
?>
<div id="active_cryptos" data-active_cryptos='<?php echo json_encode($blockonomics->getActiveCurrencies()); ?>'></div>
<div id="time_period" data-time_period="<?php echo get_option('blockonomics_timeperiod', 10); ?>"></div>

<div ng-app="BlockonomicsApp">
  <div ng-controller="CheckoutController">
    <div class="bnomics-order-container">
      <!-- Heading row -->
      <div class="bnomics-order-heading">
        <div class="bnomics-order-heading-wrapper">
          <div class="bnomics-order-id">
            <span class="bnomics-order-number" ng-cloak><?=__('Order #', 'blockonomics-bitcoin-payments')?>{{order_id}}</span>
          </div>
        </div>
      </div>
      <!-- Spinner -->
      <div class="bnomics-spinner-wrapper" ng-show="spinner" ng-cloak>
        <div class="bnomics-spinner"></div>
      </div>
      <!-- Display Error -->
      <div id="display-error" class="bnomics-display-error" ng-hide="no_display_error">
        <h2><?=__('Display Error', 'blockonomics-bitcoin-payments')?></h2>
        <p><?=__('Unable to render correctly, Note to Administrator: Please enable lite mode in the Blockonomics plugin.', 'blockonomics-bitcoin-payments')?></p>
      </div>
      <!-- BTC Address Error -->
      <div id="address-error-btc" ng-show="address_error['btc']" ng-cloak>
        <h2><?=__('Could not generate new Bitcoin address', 'blockonomics-bitcoin-payments')?></h2>
        <p><?=__('Note to webmaster: Please login to your admin panel, navigate to Settings > Blockonomics > Currencies and click <i>Test Setup</i> to diagnose the issue.', 'blockonomics-bitcoin-payments')?></p>
      </div>
      <!-- BCH Address Error -->
      <div id="address-error-bch" ng-show="address_error['bch']" ng-cloak>
        <h2><?=__('Could not generate new Bitcoin Cash address', 'blockonomics-bitcoin-payments')?></h2>
        <p><?=__('Note to webmaster: Please follow the instructions <a href="https://help.blockonomics.co/en/support/solutions/articles/33000253348-bch-setup-on-woocommerce" target="_blank">here</a> to configure BCH payments.', 'blockonomics-bitcoin-payments')?></p>
      </div>
      <!-- Gap limit + Duplicate Address Error -->
      <div id="address-error-message" ng-show="error_message" ng-cloak>
        <h2><?=__('Could not generate new address', 'blockonomics-bitcoin-payments')?></h2>
       <p>{{error_message}}</p>
      </div>
      <!-- Payment Expired -->
      <div class="bnomics-order-expired-wrapper" ng-show="order.status == -3" ng-cloak>
        <h3 class="warning bnomics-status-warning"><?=__('Payment Expired', 'blockonomics-bitcoin-payments')?></h3><br>
        <p><a ng-click="try_again_click()"><?=__('Click here to try again', 'blockonomics-bitcoin-payments')?></a></p>
      </div>
      <!-- Payment Error -->
      <div class="bnomics-order-error-wrapper" ng-show="order.status == -2" ng-cloak>
        <h3 class="warning bnomics-status-warning"><?=__('Paid order BTC amount is less than expected. Contact merchant', 'blockonomics-bitcoin-payments')?></h3>
      </div>
      <!-- Blockonomics Checkout Panel -->
      <div class="bnomics-order-panel" ng-show="order.status == -1" ng-cloak>
        <div class="bnomics-order-info">
          <div class="bnomics-bitcoin-pane">
            <div class="bnomics-btc-info">
              <!-- Left Side -->
              <!-- QR and Open in wallet -->
              <div class="bnomics-qr-code">
                <div class="bnomics-qr">
                  <a href="{{crypto.uri}}:{{order.address}}?amount={{order.satoshi/1.0e8}}" target="_blank">
                    <qrcode data="{{crypto.uri}}:{{order.address}}?amount={{order.satoshi/1.0e8}}" size="160" version="6">
                      <canvas class="qrcode"></canvas>
                    </qrcode>
                  </a>
                </div>
                <div class="bnomics-qr-code-hint"><a href="{{crypto.uri}}:{{order.address}}?amount={{order.satoshi/1.0e8}}" target="_blank"><?=__('Open in wallet', 'blockonomics-bitcoin-payments')?></a></div>
              </div>
              <!-- Right Side -->
              <div class="bnomics-amount">
                <div class="bnomics-bg">
                  <!-- Order Amounts -->
                  <div class="bnomics-amount">
                    <div class="bnomics-amount-text" ng-hide="amount_copyshow" ng-cloak><?=__('To pay, send exactly this {{crypto.code | uppercase}} amount', 'blockonomics-bitcoin-payments')?></div>
                    <div class="bnomics-copy-amount-text" ng-show="amount_copyshow" ng-cloak><?=__('Copied to clipboard', 'blockonomics-bitcoin-payments')?></div>
                    <ul ng-click="blockonomics_amount_click()" id="bnomics-amount-input" class="bnomics-amount-input">
                        <li id="bnomics-amount-copy">{{order.satoshi/1.0e8}}</li>
                        <li>{{crypto.code | uppercase}}</li>
                        <li class="bnomics-grey"> ≈ </li>
                        <li class="bnomics-grey">{{order.value}}</li>
                        <li class="bnomics-grey">{{order.currency}}</li>
                    </ul>
                  </div>
                  <!-- Order Address -->
                  <div class="bnomics-address">
                    <div class="bnomics-address-text" ng-hide="address_copyshow" ng-cloak><?=__('To this {{crypto.name | lowercase}} address', 'blockonomics-bitcoin-payments')?></div>
                    <div class="bnomics-copy-address-text" ng-show="address_copyshow" ng-cloak><?=__('Copied to clipboard', 'blockonomics-bitcoin-payments')?></div>
                    <ul ng-click="blockonomics_address_click()" id="bnomics-address-input" class="bnomics-address-input">
                          <li id="bnomics-address-copy">{{order.address}}</li>
                    </ul>
                  </div>
                  <!-- Order Countdown Timer -->
                  <div class="bnomics-progress-bar-wrapper">
                    <div class="bnomics-progress-bar-container">
                      <div class="bnomics-progress-bar" style="width: {{progress}}%;"></div>
                    </div>
                  </div>
                  <span class="ng-cloak bnomics-time-left">{{clock*1000 | date:'mm:ss' : 'UTC'}} min</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <!-- Blockonomics How to pay + Credit -->
      <div class="bnomics-powered-by">
        <a href="https://blog.blockonomics.co/how-to-pay-a-bitcoin-invoice-abf4a04d041c" target="_blank"><?=__('How do I pay? | Check reviews of this shop', 'blockonomics-bitcoin-payments')?></a><br>
        <div class="bnomics-powered-by-text bnomics-grey"><?=__('Powered by Blockonomics', 'blockonomics-bitcoin-payments')?></div>
      </div>
    </div>
  </div>
</div>
