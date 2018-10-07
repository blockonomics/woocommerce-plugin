<?php get_header();?>
<div ng-app="shopping-cart-demo">
  <div ng-controller="CheckoutController">
    <div class="bnomics-order-container" style="max-width: 700px;">
      <!-- Heading row -->
      <div class="bnomics-order-heading">
        <div class="bnomics-order-heading-wrapper">
		  <?php if (get_option('blockonomics_altcoins')) : ?>
          <div class="bnomics-payment-option" ng-hide="altcoin_waiting == 1 || order.altstatus == 1 || order.altstatus == 2 || order.altstatus == 3">
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

          <div class="bnomics-bitcoin-pane" ng-hide="show_altcoin != 0" ng-init=
          <?php 
		    if(isset($_REQUEST['uuid'])){
		    	echo "show_altcoin=1";
		    }else{
		    	echo "show_altcoin=0";
		    }
		  ?> 
    	  >
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

                <div class="bnomics-altcoin-waiting" ng-show="altcoin_waiting" ng-init=
                  <?php 
				    if(isset($_REQUEST['uuid'])){
				    	echo "altcoin_waiting=true";
				    }else{
				    	echo "altcoin_waiting=false";
				    }
				  ?> 
				  ng-cloak>
	              <!-- Alt status WAITING_FOR_DEPOSIT -->
	              <div class="bnomics-btc-info" style="display: flex;flex-wrap: wrap;" ng-show="order.altstatus == 0" ng-cloak>
                    <div style="flex: 1">
                      <!-- QR -->
                      <div class="bnomics-qr-code">
                        <div class="bnomics-qr">
                                  <a href="{{altcoinselect}}:{{order.altaddress}}?amount={{order.altamount}}">
                                    <qrcode data="{{altcoinselect}}:{{order.altaddress}}?amount={{order.altamount}}" size="160" version="6">
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
                          <span class="bnomics-order-status-title" ng-show="order.altstatus == 0" ng-cloak ><?=__('To confirm your order, please send the exact amount of <strong>{{altcoinselect}}</strong> to the given address', 'blockonomics-bitcoin-payments')?></span>
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
    	                  <div ng-cloak ng-hide="order.altstatus != 0" class="bnomics-progress-bar-wrapper">
    	                    <div class="bnomics-progress-bar-container">
    	                      <div class="bnomics-progress-bar" style="width: {{alt_progress}}%;"></div>
    	                    </div>
    	                  </div>
    	                  <span class="ng-cloak bnomics-time-left" ng-hide="order.altstatus != 0">{{alt_clock*1000 | date:'mm:ss' : 'UTC'}} min left to pay your order</span>
                      </div>
                      <div class="bnomics-altcoin-cancel"><a href="" ng-click="altcoin_waiting=false"> <?=__('Click here', 'blockonomics-bitcoin-payments')?></a> <?=__('to go back', 'blockonomics-bitcoin-payments')?>
                      </div>
                      <!-- Blockonomics Credit -->
                      <div class="bnomics-powered-by">
                        <?=__('Powered by ', 'blockonomics-bitcoin-payments')?>Blockonomics
                      </div>
                    </div>
               	  </div>
               	  <div class="bnomics-altcoin-bg-color" ng-show="order.altstatus == 1 && altemail == false" ng-init=
                  <?php 
				    if(isset($_REQUEST['uuid'])){
				    	echo "altemail=true";
				    }else{
				    	echo "altemail=false";
				    }
				  ?> ng-cloak>
               	  	<h4>Received</h4>
               	  	<h4><i class="material-icons bnomics-alt-icon">check_circle</i></h4>
               	  	<?=__('Your payment has been received. You can track your order using the link sent to your email.', 'blockonomics-bitcoin-payments')?></div>
               	  <!-- Alt status  DEPOSIT_RECEIVED -->
              	  <div class="bnomics-status-flex bnomics-altcoin-bg-color" ng-show="order.altstatus == 1 && altemail == true" ng-cloak >
              	  	<h4>Processing</h4>
                	<h4><i class="cf bnomics-alt-icon" ng-hide="altcoinselect!='Ethereum'" ng-class={'cf-eth':'{{altcoinselect}}'!=''} ></i><i class="cf bnomics-alt-icon" ng-hide="altcoinselect!='Litecoin'" ng-class={'cf-ltc':'{{altcoinselect}}'!=''} ></i></h4>
                	<a href="{{order.altaddress_link}}"><p>{{altcoinselect}} Deposit Confirmation</p></a>
                	<p>This will take a while for the network to confirm your payment.</p>
            	  </div>
            	  <!-- Alt status DEPOSIT_CONFIRMED -->
              	  <div class="bnomics-status-flex bnomics-altcoin-bg-color" ng-show="order.altstatus == 2" ng-cloak >
              	  	<h4>Processing</h4>
                	<h4><i class="cf bnomics-alt-icon" ng-hide="altcoinselect!='Ethereum'" ng-class={'cf-eth':'{{altcoinselect}}'!=''} ></i><i class="cf bnomics-alt-icon" ng-hide="altcoinselect!='Litecoin'" ng-class={'cf-ltc':'{{altcoinselect}}'!=''} ></i></h4>
                	<a href="{{order.altaddress_link}}"><p>{{altcoinselect}} Deposit Confirmation</p></a>
                	<p>This will take a while for the network to confirm your payment.</p>
            	  </div>
            	  <!-- Alt status EXECUTED -->
              	  <div class="bnomics-status-flex bnomics-altcoin-bg-color" ng-show="order.altstatus == 3" ng-cloak >
              	  	<h4>Completed</h4>
	              	<h4><i class="material-icons bnomics-alt-icon">receipt</i></h4>
	                <a href="{{finish_order_url()}}"><p>View Order Confirmation</p></a>
            	  </div>
            	  <!-- Alt status REFUNDED -->
              	  <div class="bnomics-status-flex bnomics-altcoin-bg-color" ng-show="order.altstatus == -1" ng-cloak >
              	  	<h4>Refunded</h4>
	              	<h4><i class="material-icons bnomics-alt-icon">cached</i></h4>
	                <p><?=__('This payment has been refunded.', 'blockonomics-bitcoin-payments')?></p>
            	  </div>
            	  <!-- Alt status CANCELED -->
              	  <div class="bnomics-status-flex bnomics-altcoin-bg-color" ng-show="order.altstatus == -2" ng-cloak >
              	  	<h4>Canceled</h4>
	              	<h4><i class="material-icons bnomics-alt-icon">cancel</i></h4>
	                <p><?=__('This probably happened because you paid less amount than expected.<br>Please contact flyp.me with below order id for refund:', 'blockonomics-bitcoin-payments')?></p>
                  <p>{{altuuid}}</p>
            	  </div>
            	  <!-- Alt status EXPIRED -->
              	  <div class="bnomics-status-flex bnomics-altcoin-bg-color" ng-show="order.altstatus == -3" ng-cloak >
              	  	<h4>Expired</h4>
	              	<h4><i class="material-icons bnomics-alt-icon">timer</i></h4>
	                <p><?=__('Payment Expired (Use the browser back button and try again)', 'blockonomics-bitcoin-payments')?></p>
            	  </div>
                <!-- Alt Low/High -->
                  <div class="bnomics-status-flex bnomics-altcoin-bg-color" ng-show="order.altstatus == -4" ng-cloak >
                    <h4><?=__('Order amount too low/high for', 'blockonomics-bitcoin-payments')?> {{order.altsymbol}} <?=__('payment.', 'blockonomics-bitcoin-payments')?></h4>
                  <h4><i class="cf bnomics-alt-icon" ng-hide="altcoinselect!='Ethereum'" ng-class={'cf-eth':'{{altcoinselect}}'!=''} ></i><i class="cf bnomics-alt-icon" ng-hide="altcoinselect!='Litecoin'" ng-class={'cf-ltc':'{{altcoinselect}}'!=''} ></i></h4>
                  <p><?=__('Go back and use BTC to complete the payment.', 'blockonomics-bitcoin-payments')?></p>
                </div>
                <div class="bnomics-altcoin-cancel" ng-show="order.altstatus == -4"><a href="" ng-click="altcoin_waiting=false"> <?=__('Click here', 'blockonomics-bitcoin-payments')?></a> <?=__('to go back', 'blockonomics-bitcoin-payments')?>
                </div>
            	  <!-- Contact Flyp -->
            	  <div class="bnomics-altcoin-bg-color"  ng-show="order.altstatus == -1 || order.altstatus == -2 || order.altstatus == -3" ng-cloak>
	            		<p>uuid: {{altuuid}}</p>
            	  </div>
            	  <!-- Alt Link -->
            	  <div class="bnomics-altcoin-bg-color" ng-show="order.altstatus == 5" ng-cloak>
	                  <div class="bnomics-address">
	                    <input ng-click="page_link_click()" id="bnomics-page-link-input" class="bnomics-page-link-input" type="text" ng-value="order.pagelink" readonly="readonly">
	                    <span ng-click="page_link_click()" class="dashicons dashicons-admin-page bnomics-copy-icon"></span>
	                  </div>
	                  	<div class="bnomics-copy-text" ng-show="copyshow" ng-cloak><?=__('Copied to clipboard', 'blockonomics-bitcoin-payments')?></div>
	            		<?=__('To get back to this page, copy and use the above link.', 'blockonomics-bitcoin-payments')?>
            	  </div>
                </div>
      			</div>
            <!-- Blockonomics Credit -->
            <div class="bnomics-powered-by" ng-hide="order.altstatus == 0">
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
    <?php  
      wp_enqueue_script( 'angular', plugins_url('js/angular.min.js', __FILE__), array('jquery') );
      wp_enqueue_script( 'angular-resource', plugins_url('js/angular-resource.min.js', __FILE__), array('jquery') );
      wp_enqueue_script( 'app', plugins_url('js/app.js', __FILE__), array('jquery') );
                        wp_localize_script( 'app', 'my_ajax_object',
                            array( 'ajax_url' => admin_url( 'admin-ajax.php' ) ) );
      wp_enqueue_script( 'angular-qrcode', plugins_url('js/angular-qrcode.js', __FILE__), array('jquery') );
      wp_enqueue_script( 'vendors', plugins_url('js/vendors.min.js', __FILE__), array('jquery') );
      wp_enqueue_script( 'reconnecting-websocket', plugins_url('js/reconnecting-websocket.min.js', __FILE__), array('jquery') );
    ?>
  </div>
</div>

<?php get_footer();?>