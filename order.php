<?php get_header();?>
<div ng-app="shopping-cart-demo">
  <div ng-controller="CheckoutController">
    <div class="bnomics-order-container" style="max-width: 700px;">
      <!-- Heading row -->
      <div class="bnomics-order-heading">
        <div class="bnomics-order-heading-wrapper">
          <?php if (get_option('blockonomics_altcoins')) : ?>
          <div class="bnomics-payment-option" ng-hide="altcoin_waiting == 1">
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

          <div class="bnomics-bitcoin-pane" ng-hide="show_altcoin != 0" ng-init=<?php if(isset($_REQUEST['uuid'])){echo "show_altcoin=1";}else{echo "show_altcoin=0";}?>>
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
                  <input ng-click="btc_address_click()" id="bnomics-address-input" class="bnomics-address-input" type="text" ng-value="order.address" readonly="readonly">
                  <i ng-click="btc_address_click()" class="material-icons bnomics-copy-icon">file_copy</i>
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
                <div class="bnomics-altcoin-bg-color" ng-hide="altcoin_waiting" ng-cloak>
                 <div class="bnomics-altcoin-info-wrapper">
                  <span class="bnomics-altcoin-info" ><?=__('Select your preferred <strong>Altcoin</strong> then click on the button below.', 'blockonomics-bitcoin-payments')?></span>
                 </div>
                 </br>
                 <!-- Coin Select -->
                 <div class="bnomics-address">
                   <select ng-model="altcoinselect" ng-options="x for (x, y) in altcoins" ng-init="altcoinselect='Ethereum'"></select>
                 </div>
                 <div class="bnomics-altcoin-button-wrapper">
                  <a ng-click="pay_altcoins()" href=""><button><i class="cf" ng-hide="altcoinselect!='Ethereum'" ng-class={'cf-eth':'{{altcoinselect}}'!=''} ></i><i class="cf" ng-hide="altcoinselect!='Litecoin'" ng-class={'cf-ltc':'{{altcoinselect}}'!=''} ></i> <?=__('Pay with', 'blockonomics-bitcoin-payments')?> {{altcoinselect}}</button></a>
                 </div>
                </div>

                <div class="bnomics-altcoin-waiting" ng-show="altcoin_waiting" ng-init=<?php if(isset($_REQUEST['uuid'])){echo "altcoin_waiting=true";}else{echo "altcoin_waiting=false";}?> ng-cloak>
                  
                <!-- Alt status WAITING_FOR_DEPOSIT -->
                <div class="bnomics-btc-info" style="display: flex;flex-wrap: wrap;" ng-show="order.altstatus == 'waiting'" ng-cloak>
                    <div style="flex: 1">
                      <!-- QR -->
                      <div class="bnomics-qr-code">
                        <div class="bnomics-qr">
                                  <a href="{{altcoinselect}}:{{order.altaddress}}?amount={{order.altamount}}&value={{order.altamount}}">
                                    <qrcode data="{{altcoinselect}}:{{order.altaddress}}?amount={{order.altamount}}&value={{order.altamount}}" size="160" version="6">
                                      <canvas class="qrcode"></canvas>
                                    </qrcode>
                                  </a>
                        </div>
                        <div class="bnomics-qr-code-hint"><?=__('Click on the QR code to open in the wallet', 'blockonomics-bitcoin-payments')?></div>
                      </div>
                    </div>
                    <div style="flex: 2;">
                      <div class="bnomics-altcoin-bg-color">
                        <!-- Alt Order Status -->
                        <div class="bnomics-order-status-wrapper">
                          <span class="bnomics-order-status-title" ng-show="order.altstatus == 'waiting'" ng-cloak ><?=__('To confirm your order, please send the exact amount of <strong>{{altcoinselect}}</strong> to the given address', 'blockonomics-bitcoin-payments')?></span>
                        </div>
                        <h4 class="bnomics-amount-title" for="invoice-amount">
                         {{order.altamount}} {{order.altsymbol}}
                        </h4>
                        <!-- Alt Address -->
                        <div class="bnomics-address">
                          <input ng-click="alt_address_click()" id="bnomics-alt-address-input" class="bnomics-address-input" type="text" ng-value="order.altaddress" readonly="readonly">
                         <i ng-click="alt_address_click()" class="material-icons bnomics-copy-icon">file_copy</i>
                        </div>
                        <div class="bnomics-copy-text" ng-show="copyshow" ng-cloak><?=__('Copied to clipboard', 'blockonomics-bitcoin-payments')?></div>
                        <!-- Countdown Timer -->
                        <div ng-cloak ng-hide="order.altstatus != 'waiting'  || alt_clock <= 0" class="bnomics-progress-bar-wrapper">
                          <div class="bnomics-progress-bar-container">
                            <div class="bnomics-progress-bar" style="width: {{alt_progress}}%;"></div>
                          </div>
                        </div>
                        <span class="ng-cloak bnomics-time-left" ng-hide="order.altstatus != 'waiting' || alt_clock <= 0">{{alt_clock*1000 | date:'mm:ss' : 'UTC'}} min left to pay your order</span>
                      </div>
                      <div class="bnomics-altcoin-cancel"><a href="" ng-click="altcoin_waiting=false"> <?=__('Click here', 'blockonomics-bitcoin-payments')?></a> <?=__('to go back', 'blockonomics-bitcoin-payments')?>
                      </div>
                      <!-- Blockonomics Credit -->
                      <div class="bnomics-powered-by">
                        <?=__('Powered by ', 'blockonomics-bitcoin-payments')?>Blockonomics
                      </div>
                    </div>
                  </div>
                <!-- Alt status RECEIVED -->
                  <div class="bnomics-altcoin-bg-color" ng-show="order.altstatus == 'received'" ng-cloak>
                    <h4>Received</h4>
                    <h4><i class="material-icons bnomics-alt-icon">check_circle</i></h4>
                    <?=__('Your payment has been received and your order will be processed shortly.', 'blockonomics-bitcoin-payments')?>
                  </div>
                <!-- Alt status ADD_REFUND -->
                  <div class="bnomics-status-flex bnomics-altcoin-bg-color" ng-show="order.altstatus == 'add_refund'" ng-cloak >
                    <h4>Refund Required</h4>
                  <p><?=__('Your order couldn\'t be processed as you paid less than expected.<br>The amount you paid will be refunded.', 'blockonomics-bitcoin-payments')?></p>
                  <h4><i class="material-icons bnomics-alt-icon">error</i></h4>
                  <p><?=__('Enter your refund address and click the button below to recieve your refund.', 'blockonomics-bitcoin-payments')?></p>
                  <input type="text" id="bnomics-refund-input" placeholder="{{order.altsymbol}} Address"><br>
                  <button id="alt-refund-button" ng-click="add_refund_click()">Refund</button>
                </div>
                <!-- Alt status REFUNDED no txid-->
                  <div class="bnomics-status-flex bnomics-altcoin-bg-color" ng-show="order.altstatus == 'refunded'" ng-cloak >
                    <h4>Refund Submitted</h4>
                     <p><?=__('Your refund details have been submitted. You should recieve your refund shortly.', 'blockonomics-bitcoin-payments')?></p>
                  <h4><i class="material-icons bnomics-alt-icon">autorenew</i></h4>
                  <p><?=__('If you don\'t get refunded in a few hours, contact <a href="mailto:hello@flyp.me">hello@flyp.me</a> with the following uuid:', 'blockonomics-bitcoin-payments')?><br>
                  <span id="alt-uuid">{{altuuid}}</span></p>
                </div>
                <!-- Alt status REFUNDED with txid-->
                  <div class="bnomics-status-flex bnomics-altcoin-bg-color" ng-show="order.altstatus == 'refunded-txid'" ng-cloak >
                    <h4>Refunded</h4>
                  <h4><i class="material-icons bnomics-alt-icon">autorenew</i></h4>
                  <p><?=__('This payment has been refunded.', 'blockonomics-bitcoin-payments')?></p>
                  <div><?=__('Refund Details:', 'blockonomics-bitcoin-payments')?>
                  <div style="text-align: left; font-size: 0.8em; font-weight: bold;"><?=__('Transaction ID:', 'blockonomics-bitcoin-payments')?></div> <div style="text-align: left; font-size: 0.8em;" id="alt-refund-txid">{{order.alttxid}}</div>
                  <div style="text-align: left; font-size: 0.8em; font-weight: bold;"><?=__('Transaction URL:', 'blockonomics-bitcoin-payments')?></div> <div style="text-align: left; font-size: 0.8em;" id="alt-refund-url"><a href="{{order.alturl}}" target="_blank">{{order.alturl}}</a></div></div>
                </div>
                <!-- Alt status EXPIRED -->
                  <div class="bnomics-status-flex bnomics-altcoin-bg-color" ng-show="order.altstatus == 'expired'" ng-cloak >
                    <h4>Expired</h4>
                  <h4><i class="material-icons bnomics-alt-icon">timer</i></h4>
                  <p><?=__('Payment Expired. Use the browser back button and try again.', 'blockonomics-bitcoin-payments')?></p>
                </div>
                <!-- Alt Error Low/High -->
                  <div class="bnomics-status-flex bnomics-altcoin-bg-color" ng-show="order.altstatus == 'low_high'" ng-cloak >
                    <h4>Error</h4>
                  <h4><i class="material-icons bnomics-alt-icon">error</i></h4>
                  <p><?=__('Order amount too <strong>{{lowhigh}}</strong> for {{order.altsymbol}} payment.', 'blockonomics-bitcoin-payments')?></p>
                  <p><a href="" ng-click="altcoin_waiting=false"> <?=__('Click here', 'blockonomics-bitcoin-payments')?></a> <?=__('to go back and use BTC to complete the payment.', 'blockonomics-bitcoin-payments')?></p>
                </div>
                
                </div>
            </div>
            <!-- Blockonomics Credit -->
            <div class="bnomics-powered-by" ng-hide="order.altstatus == 'waiting'">
              <?=__('Powered by ', 'blockonomics-bitcoin-payments')?>Blockonomics
            </div>
          </div>
          <?php endif ?>

        </div>
      </div>
    </div>
    <script>
    var blockonomics_time_period=<?php echo get_option('blockonomics_timeperiod', 10); ?>;
    </script>
    <script>
    var get_uuid="<?php             
            if(isset($_REQUEST['uuid'])){
              echo $_REQUEST['uuid'];
            } ?>";
    </script>
  </div>
</div>

<?php get_footer();?>