<?php get_header();?>

<div ng-app="shopping-cart-demo">
  <div ng-controller="CheckoutController">

    <?php if (get_option('blockonomics_altcoins')) : ?>
    <div class="bnomics-order-container" style="max-width: 700px;">
    <?php else : ?>
    <div class="bnomics-order-container" style="max-width: 700px;">
    <?php endif;?>

      <!-- Heading row -->
      <div class="bnomics-order-heading">
        <div class="bnomics-order-heading-wrapper">
		  <?php if (get_option('blockonomics_altcoins')) : ?>
          <div class="bnomics-payment-option">
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

      <!-- Amount row -->
      <div class="bnomics-order-panel">
        <div class="bnomics-order-info">

          <div class="bnomics-bitcoin-pane" ng-hide="show_altcoin != 0" ng-init="show_altcoin=0">

            <div class="bnomics-btc-info">
              <!-- QR and Amount -->
              <div class="bnomics-qr-code">
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
  		            <span class="bnomics-order-status-title" ng-show="order.status == -1" ng-cloak ><?=__('To confirm your order, please send the exact amount of <strong>BTC</strong> to the given address', 'blockonomics-bitcoin-payments')?></span>
  		            <span class="warning bnomics-status-warning" ng-show="order.status == -3" ng-cloak><?=__('Payment Expired (Use the browser back button and try again)', 'blockonomics-bitcoin-payments')?></span>
  		            <span class="warning bnomics-status-warning" ng-show="order.status == -2" ng-cloak><?=__('Payment Error', 'blockonomics-bitcoin-payments')?></span>
  		            <span ng-show="order.status == 0" ng-cloak><?=__('Unconfirmed', 'blockonomics-bitcoin-payments')?></span>
  		            <span ng-show="order.status == 1" ng-cloak><?=__('Partially Confirmed', 'blockonomics-bitcoin-payments')?></span>
  		            <span ng-show="order.status >= 2" ng-cloak ><?=__('Confirmed', 'blockonomics-bitcoin-payments')?></span>
  		          </div>
                    <h4 class="bnomics-amount-title" for="invoice-amount">
  				  	       {{order.satoshi/1.0e8}} BTC
                    </h4>
                    <div class="bnomics-amount-wrapper">
  				            <hr class="bnomics-amount-seperator"> ≈
                      <span ng-cloak>{{order.value}}</span>
                      <small ng-cloak>{{order.currency}}</small>
                    </div>
  			      <!-- Bitcoin Address -->
  		          <div class="bnomics-address">
  		            <input ng-click="btc_address_click()" id="bnomics-address-input" class="bnomics-address-input" type="text" ng-value="order.address" readonly="readonly"><img ng-click="btc_address_click()" src="https://cdn4.iconfinder.com/data/icons/linecon/512/copy-24.png" class="bnomics-copy-icon">
  		          </div>
                <div class="bnomics-copy-text" ng-show="copyshow" ng-cloak>Copied to clipboard</div>
  				  <!-- Countdown Timer -->
  		          <div ng-cloak ng-hide="order.status != -1" class="bnomics-progress-bar-wrapper">
  		            <div class="bnomics-progress-bar-container">
  		              <div class="bnomics-progress-bar" style="width: {{progress}}%;"></div>
  		            </div>
  		          </div>
  				      <span class="ng-cloak bnomics-time-left" ng-hide="order.status != -1">{{clock*1000 | date:'mm:ss' : 'UTC'}} min left to pay your order</span>
				      </div>
				<!-- Blockonomics Credit -->
		        <div class="bnomics-powered-by">
		          <?=__('Powered by ', 'blockonomics-bitcoin-payments')?>Blockonomics
		        </div>
              </div>
            </div>
          </div>

          <?php if (get_option('blockonomics_altcoins')) : ?>
          <div class="bnomics-altcoin-pane" ng-style="{'border-left': (altcoin_waiting)?'none':''}" ng-hide="show_altcoin != 1">
      			<div class="bnomics-altcoin-bg">
                <div ng-hide="altcoin_waiting" ng-cloak>
  			         <div class="bnomics-altcoin-info-wrapper">
  		            <span class="bnomics-altcoin-info" ><?=__('Select your preferred <strong>Altcoin</strong> then click on the button below.', 'blockonomics-bitcoin-payments')?></span>
  			         </div>
  			         </br>
                 <!-- Coin Select -->
                 <div class="bnomics-address">
                   <select ng-model="altcoinselect" ng-options="x for (x, y) in altcoins" ng-init="altcoinselect='Ethereum'"></select>
                 </div>
                 <div class="bnomics-altcoin-button-wrapper">
                  <a ng-click="pay_altcoins()" href=""><button><?=__('Pay with', 'blockonomics-bitcoin-payments')?> {{altcoinselect}}</button></a>
                 </div>
                </div>

                <div class="bnomics-altcoin-waiting" ng-show="altcoin_waiting" ng-cloak>
                   <!-- Alt Order Status -->
                  <div class="bnomics-order-status-wrapper">
                    <span class="bnomics-order-status-title" ng-show="order.status == -1 && order.altstatus != 1" ng-cloak ><?=__('To confirm your order, please send the exact amount of', 'blockonomics-bitcoin-payments')?> <strong>{{altcoinselect}}</strong> <?=__('to the given address', 'blockonomics-bitcoin-payments')?></span>
                    <span class="warning bnomics-status-warning" ng-show="order.status == -3" ng-cloak><?=__('Payment Expired (Use the browser back button and try again)', 'blockonomics-bitcoin-payments')?></span>
                    <span class="warning bnomics-status-warning" ng-show="order.status == -2" ng-cloak><?=__('Payment Error', 'blockonomics-bitcoin-payments')?></span>
                    <span ng-show="order.status == 0" ng-cloak><?=__('Unconfirmed', 'blockonomics-bitcoin-payments')?></span>
                    <span ng-show="order.status == 1" ng-cloak><?=__('Partially Confirmed', 'blockonomics-bitcoin-payments')?></span>
                    <span ng-show="order.status >= 2" ng-cloak ><?=__('Confirmed', 'blockonomics-bitcoin-payments')?></span>
                    <span ng-show="order.altstatus == 1" ng-cloak ><?=__('Thank you for your payment. Please wait while Blockonomics confirms your order', 'blockonomics-bitcoin-payments')?></span>
                    <span ng-show="order.altstatus == 1" ng-cloak >TXID: {{order.alttxid}}</span>
                  </div>
                      <h4 class="bnomics-amount-title" for="invoice-amount">
                       {{order.altamount}} {{order.altsymbol}}
                      </h4>
                      <div class="bnomics-amount-wrapper">
                        <hr class="bnomics-amount-seperator"> ≈
                        <span ng-cloak>{{order.value}}</span>
                        <small ng-cloak>{{order.currency}}</small>
                      </div>
                   <!-- Alt Address -->
                   <div class="bnomics-address">
                     <input ng-click="alt_address_click()" id="bnomics-alt-address-input" class="bnomics-address-input" type="text" ng-value="order.altaddress" readonly="readonly"><img ng-click="alt_address_click()" src="https://cdn4.iconfinder.com/data/icons/linecon/512/copy-24.png" class="bnomics-copy-icon">
                   </div>
                   <div class="bnomics-copy-text" ng-show="copyshow" ng-cloak><?=__('Copied to clipboard', 'blockonomics-bitcoin-payments')?></div>
                   <!-- Countdown Timer -->
                   <div ng-cloak ng-hide="order.status != -1" class="bnomics-progress-bar-wrapper">
                     <div class="bnomics-progress-bar-container">
                       <div class="bnomics-progress-bar" style="width: {{progress}}%;"></div>
                     </div>
                   </div>
                   <span class="ng-cloak bnomics-time-left" ng-hide="order.status != -1">{{clock*1000 | date:'mm:ss' : 'UTC'}} min left to pay your order</span>
                   <div class="bnomics-altcoin-cancel"><a href="" ng-click="altcoin_waiting=false"> <?=__('Click here', 'blockonomics-bitcoin-payments')?></a> <?=__('to cancel', 'blockonomics-bitcoin-payments')?> </div>
                </div>
      			</div>
            <!-- Blockonomics Credit -->
            <div class="bnomics-powered-by">
              <?=__('Powered by ', 'blockonomics-bitcoin-payments')?>Blockonomics
            </div>
          </div>
          <?php endif ?>

        </div>
      </div>
    </div>
    <script>
    var blockonomics_time_period=<?php echo get_option('blockonomics_timeperiod', 10); ?>;
    var plugin_dir_create='<?php echo plugins_url('php/flyp/createOrder.php', __FILE__);  ?>';
    var plugin_dir_check='<?php echo plugins_url('php/flyp/checkOrder.php', __FILE__);  ?>';
    var plugin_dir_limit='<?php echo plugins_url('php/flyp/fetchLimits.php', __FILE__);  ?>';
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