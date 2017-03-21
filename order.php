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
<div class="aligncenter" style="width:35%">
  <div >
  <!-- heading row -->
  <div >
    <div > 
      <span class=""> Order# {{order.order_id}}</span>
      <span class="alignright" ng-show="order.status == -1"  >{{clock*1000 | date:'mm:ss' : 'UTC'}}</span>
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
        <label ng-show="order.status != -1" for="invoice-amount" style="margin-top:15px;" >Status</label>
        <div class="" style="margin-bottom:10px;" >
          <strong style="color: #956431;" ng-show="order.status == -1" >To
            pay, send exact amount of BTC to the given address</strong>
          <strong style="color: rgb(239, 121, 79)" ng-show="order.status == -3"> Payment Expired</strong>
          <strong style="color: rgb(239, 121, 79)" ng-show="order.status == -2"> Payment Error</strong>
          <strong style="color: #956431;" ng-show="order.status == 0"> Unconfirmed</strong>
          <strong style="color: #956431;" ng-show="order.status == 1"> Partially Confirmed</strong>
          <strong style="color: #956431;" ng-show="order.status >= 2" >Confirmed</strong>
        </div>
      </div>

      <div class="">
        <label for="invoice-amount">Amount</label>
        <div class="">
          <strong style="color: #956431;">{{order.satoshi/1.0e8}}</strong>
          <small>BTC</small> ⇌
          <strong style="color: #956431;">{{order.value}}</strong> 
          <small>{{order.currency}}</small>
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
        <label ng-hide="order.status == -1" for="invoice-amount" >Payment Details</label>
        <div ng-show="order.status !=-1 " class="" style="margin-bottom:10px;" >
          Transaction : <a style="font-weight:bold;color: #956431;" target="_blank"
                           href="http://www.blockonomics.co/api/tx?txid={{order.txid}}&addr={{order.address}}">{{order.txid |limitTo: 20}}</a>
        </div>
        <div>
<h5>Pay with altcoins</h5>
   <?php
$process= "05858c59c55d90fef999f53034848fd0c8f6b9ade3c95b29dd13c0d35e38b40a5f5331aef094806dcc5bc2c5ce1fb119eac50bb711fde8ff593d5b58fa814f34";
echo '<script>function shapeshift_click(a,e){e.preventDefault();var link=a.href;window.open(link,"1418115287605","width=700,height=500,toolbar=0,menubar=0,location=0,status=1,scrollbars=1,resizable=0,left=0,top=0");
return false;
window.setTimeout(closeWindow, 6000);
}</script> <a href="https://shapeshift.io/shifty.html?destination={{order.address}}&amp;apiKey='.$process.'&amp;amount={{order.satoshi/1.0e8}}" onclick="shapeshift_click(this, event);"><img class="ss-button" src="https://shapeshift.io/images/shifty/small_light_altcoins.png"></a>'
?>
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
