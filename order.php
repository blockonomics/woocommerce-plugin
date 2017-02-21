<html ng-app="shopping-cart-demo">
  <head>
    <meta charset="utf-8">
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
<div class="form">
  <div class="col-md-7 col-md-offset-3 invoice">
  <!-- heading row -->
  <div class="row">
    <div class="invoice-heading"> 
      <span class="ng-binding"> Order# {{order.order_id}}</span>
      <span ng-show="order.status == -1" class="invoice-heading-right" >{{clock*1000 | date:'mm:ss' : 'UTC'}}</span>
    </div>
    <div class="" ng-hide="order.status != -1">
      <div class="progress" role="progressbar" aria-valuenow="70" aria-valuemin="0" aria-valuemax="100" style="width:{{progress}}%">
      </div>
    </div>
  </div>
    <!-- Amount row -->
    <div class="row">

      <div class="col-xs-12">
        <!-- Status -->
        <label ng-show="order.status != -1" for="invoice-amount" style="margin-top:15px;" >Status</label>
        <div class="value ng-binding" style="margin-bottom:10px;" >
          <strong style="color: #956431;" ng-show="order.status == -1" >To
            pay, send exact amount of BTC to the given address</strong>
          <strong style="color: rgb(239, 121, 79)" ng-show="order.status == -3"> Payment Expired</strong>
          <strong style="color: rgb(239, 121, 79)" ng-show="order.status == -2"> Payment Error</strong>
          <strong style="color: #956431;" ng-show="order.status == 0"> Unconfirmed</strong>
          <strong style="color: #956431;" ng-show="order.status == 1"> Partially Confirmed</strong>
          <strong style="color: #956431;" ng-show="order.status >= 2" >Confirmed</strong>
        </div>
      </div>

      <div class="col-xs-6 invoice-amount"  style="border-right:#ccc 1px solid;">
        <label for="invoice-amount">Amount</label>
        <div class="value ng-binding">
          <strong style="color: #956431;">{{order.satoshi/1.0e8}}</strong>
          <small>BTC</small> ⇌
          <strong style="color: #956431;">{{order.value}}</strong> 
          <small>{{order.currency}}</small>
        </div>


        <!-- address-->
        <div class="row">
          <label class="col-xs-6" style="margin-bottom:15px;margin-top:15px;" for="btn-address">Bitcoin Address</label>
        </div>

        <!-- QR Code -->
        <div class="row qr-code-box">
          <div class="col-xs-5 qr-code">
            <div class="qr-enclosure">
              <a href="bitcoin:{{order.address}}?amount={{order.satoshi/1.0e8}}">
              <qrcode data="bitcoin:{{order.address}}?amount={{order.satoshi/1.0e8}}" size="220">
              <canvas class="qrcode"></canvas>
              </qrcode>
              </a>
            </div>
          </div>
        </div>
      </div>

      <div class="col-xs-6 invoice-status">
        <label ng-hide="order.status == -1" for="invoice-amount" >Payment Details</label>
        <div ng-show="order.status !=-1 " class="value ng-binding" style="margin-bottom:10px;" >
          Transaction : <a style="font-weight:bold;color: #956431;" target="_blank"
                           href="http://www.blockonomics.co/api/tx?txid={{order.txid}}&addr={{order.address}}">{{order.txid |limitTo: 20}}</a>
        </div>
      </div>

    </div>

          <div class="row">
        <input type="text" class="invoice-address" ng-value="order.address" readonly="readonly">
      </div>
  </div>
  </div>
</div>
    <script src="<?php echo plugins_url('js/angular.js', __FILE__ );?>"></script>
    <script src="<?php echo plugins_url('js/angular-resource.js', __FILE__ );?>"></script>
    <script src="<?php echo plugins_url('js/app.js', __FILE__ );?>"></script>
    <script src="<?php echo plugins_url('js/angular-qrcode.js', __FILE__ );?>"></script>
    <script src="<?php echo plugins_url('js/vendors.min.js', __FILE__ );?>"></script>
<?php
//call the wp foooter
get_footer();
?>
  </body>
</html>
