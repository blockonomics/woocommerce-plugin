<html ng-app="shopping-cart-demo">
  <head>
    <meta charset="utf-8">
		<style>
		[ng\:cloak], [ng-cloak], [data-ng-cloak], [x-ng-cloak], .ng-cloak, .x-ng-cloak {
	display: none !important;
}
		</style> 
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
    <title>Bitcoin Payment - Powered by Blockonomics</title>
    <meta name="description" content="">
    <meta name="viewport" content="width=device-width, initial-scale=1">
<?php
//call the wp head so  you can get most of your wordpress
get_header();
?>
  </head>

  <body ng-controller="CheckoutController">
<div class="aligncenter" style="width:35%">
  <div >
  <!-- heading row -->
  <div >
    <div > 
      <span ng-cloak> Order# {{order.order_id}}</span>
      <span class="alignright ng-cloak" ng-show="order.status == -1">{{clock*1000 | date:'mm:ss' : 'UTC'}}</span>
    </div>
    <div ng-hide="order.status != -1">
      <!-- div class="" role="progressbar" aria-valuenow="70" aria-valuemin="0" aria-valuemax="100" style="width:{{progress}}%" -->
<div style="
    width: 100%;
    height: 7px;
    background: #ddd;
    margin: 20px 0;
    overflow: hidden;
    position: relative;
">
  <div style="
    width: {{progress}}%;
    height: 7px;
    background:#f80;
    left: 0;
    position: absolute;
    top: 0;
"></div>
</div>
      </div>
    </div>
  </div>
    <!-- Amount row -->
    <div >

      <div >
        <!-- Status -->
        <label ng-show="order.status != -1" for="invoice-amount" style="margin-top:15px;" ng-cloak>Status</label>
        <div class="" style="margin-bottom:10px;" >
          <strong style="color: #956431;" ng-show="order.status == -1" ng-cloak >To
            pay, send exact amount of BTC to the given address</strong>
          <strong style="color: rgb(239, 121, 79)" ng-show="order.status == -3" ng-cloak> Payment Expired</strong>
          <strong style="color: rgb(239, 121, 79)" ng-show="order.status == -2" ng-cloak> Payment Error</strong>
          <strong style="color: #956431;" ng-show="order.status == 0" ng-cloak> Unconfirmed</strong>
          <strong style="color: #956431;" ng-show="order.status == 1" ng-cloak> Partially Confirmed</strong>
          <strong style="color: #956431;" ng-show="order.status >= 2" ng-cloak >Confirmed</strong>
        </div>
      </div>

      <div class="">
        <label for="invoice-amount">Amount</label>
        <div class="">
          <strong style="color: #956431;" ng-cloak>{{order.satoshi/1.0e8}}</strong>
          <small>BTC</small> ⇌
          <strong style="color: #956431;" ng-cloak>{{order.value}}</strong> 
          <small ng-cloak>{{order.currency}}</small>
        </div>


        <!-- address-->
        <div >
          <label class="" style="margin-bottom:15px;margin-top:15px;" for="btn-address">Bitcoin Address</label>
        </div>

        <!-- QR Code -->
        <div style="margin-bottom: 20px;">
          <div class="">
            <div class="">
              <a href="bitcoin:{{order.address}}?amount={{order.satoshi/1.0e8}}">
              <qrcode data="bitcoin:{{order.address}}?amount={{order.satoshi/1.0e8}}" size="220">
              <canvas class="qrcode"></canvas>
              </qrcode>
              </a>
            </div>
          </div>
        </div>
      </div>

      <div >
        <label ng-hide="order.status == -1" for="invoice-amount" ng-cloak >Payment Details</label>
        <div ng-show="order.status !=-1 " class="" style="margin-bottom:10px;" ng-cloak >
          Transaction : <a style="font-weight:bold;color: #956431;" target="_blank"
                           href="http://www.blockonomics.co/api/tx?txid={{order.txid}}&addr={{order.address}}">{{order.txid |limitTo: 20}}</a>
        </div>
      </div>

    </div>

          <div >
        <input type="text" class="" ng-value="order.address" readonly="readonly">
      </div>
  </div>
  </div>
</div>
    <script src="<?php echo plugins_url('js/angular.js', __FILE__);?>"></script>
    <script src="<?php echo plugins_url('js/angular-resource.js', __FILE__);?>"></script>
    <script src="<?php echo plugins_url('js/app.js', __FILE__);?>"></script>
    <script src="<?php echo plugins_url('js/angular-qrcode.js', __FILE__);?>"></script>
    <script src="<?php echo plugins_url('js/vendors.min.js', __FILE__);?>"></script>
<?php
//call the wp foooter
get_footer();
?>
  </body>
</html>
