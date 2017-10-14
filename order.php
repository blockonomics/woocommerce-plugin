<html ng-app="shopping-cart-demo">
  <head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
    <title>Bitcoin Payment - Powered by Blockonomics</title>
    <meta name="description" content="">
    <meta name="viewport" content="width=device-width, initial-scale=1">
<?php
//call the wp head so  you can get most of your wordpress
get_header();
?>
<style>
    [ng\:cloak], [ng-cloak], [data-ng-cloak], [x-ng-cloak], .ng-cloak, .x-ng-cloak {
  display: none !important;
}

.spinner {
  width: 60px;
  height: 60px;
  margin: 60px;
  animation: rotate 1.4s infinite ease-in-out, background 1.4s infinite ease-in-out alternate;
}

@keyframes rotate {
  0% {
    transform: perspective(120px) rotateX(0deg) rotateY(0deg);
  }
  50% {
    transform: perspective(120px) rotateX(-180deg) rotateY(0deg);
  }
  100% {
    transform: perspective(120px) rotateX(-180deg) rotateY(-180deg);
  }
}
@keyframes background {
  0% {
  background-color: #27ae60;
  }
  50% {
    background-color: #9b59b6;
  }
  100% {
    background-color: #c0392b;
  }
}


table {
  table-layout: fixed;
  border-collapse:initial;
}
@media screen and (max-width: 800px) {
  table {
  }
  table td {
    display: block;
  }
}
</style>
  </head>

  <body ng-controller="CheckoutController">

<?php if(get_option('blockonomics_altcoins')) : ?>
<div style="max-width: 1000px;" class="aligncenter">
<?php else : ?>
<div style="max-width: 600px;" class="aligncenter">
<?php endif;?>
  <div >
  <!-- heading row -->
  <div>
    <div >
      <span ng-cloak> Order# {{order.order_id}}</span>
      <span class="alignright ng-cloak" ng-hide="order.status != -1 || altcoin_waiting">{{clock*1000 | date:'mm:ss' : 'UTC'}}</span>
    </div>
    <div ng-cloak ng-hide="order.status != -1 || altcoin_waiting">
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
    background:#666;
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
       <table>
      <tr><td ng-hide="altcoin_waiting" ng-cloak>
      <table>
      <tr>
      <td colspan="2" style="padding-right:20px;">
        <!-- Status -->
        <h5 ng-show="order.status != -1" for="invoice-amount" style="margin-top:15px;" ng-cloak>Status</h5>
        <div class="" style="margin-bottom:10px;" >
          <h3 ng-show="order.status == -1" ng-cloak >To pay, send exact amount of BTC to the given address</h3>
          <span style="color: rgb(239, 121, 79)" ng-show="order.status == -3" ng-cloak> Payment Expired (Use browser back button and try again)</span>
          <span style="color: rgb(239, 121, 79)" ng-show="order.status == -2" ng-cloak> Payment Error</span>
          <span ng-show="order.status == 0" ng-cloak> Unconfirmed</span>
          <span ng-show="order.status == 1" ng-cloak> Partially Confirmed</span>
          <span ng-show="order.status >= 2" ng-cloak >Confirmed</span>
        </div>
      </td>
      </tr>
      <tr><td style="text-align: center;">
        <!-- address-->
        <div >
          <h5  for="btn-address">Bitcoin Address</h5>
        </div>

        <!-- QR Code -->
        <div style="margin-bottom: 5px;">
          <div class="">
            <div class="">
              <a href="bitcoin:{{order.address}}?amount={{order.satoshi/1.0e8}}">
              <qrcode data="bitcoin:{{order.address}}?amount={{order.satoshi/1.0e8}}" size="160">
              <canvas class="qrcode"></canvas>
              </qrcode>
              </a>
            </div>
          </div>
          <h5 style="margin-top: 5px;">Click on the QR code above to open in bitcoin wallet</h5>
        </div>


      </td>
      <td style="vertical-align:top;padding-right:10px;text-align:center;">
        <h5 for="invoice-amount">Amount</h5>
        <div class="">
          <span ng-show="order.satoshi" ng-cloak>{{order.satoshi/1.0e8}}</span>
          <small>BTC</small> ⇌
          <span ng-cloak>{{order.value}}</span>
          <small ng-cloak>{{order.currency}}</small>
        </div>
      </td>
      </tr>
      <tr><td style="text-align: center;" colspan="2">
        <input style="text-align:center; width:100%; border:1px solid grey; box-shadow: none; background-color:unset;"type="text" ng-value="order.address" readonly="readonly">
      </td></tr>
</table>
      </td>
      <?php if(get_option('blockonomics_altcoins')) : ?>
      <td rowspan="2" ng-hide="altcoin_waiting" style="vertical-align:middle;padding-left:20px;text-align:center;" ng-cloak>
    <h3> OR you can </h3>
          <div >
      <a ng-click="pay_altcoins()" href=""><img  style="margin: auto;" src="https://shapeshift.io/images/shifty/small_dark_altcoins.png"  class="ss-button"></a>
      <div style="text-align: left; max-width: 200px; margin: 10px auto 0 auto;">
        <h5>You can pay with Ethereum, Bitcoin Cash, Dash, and many others through Shapeshift!</h5>
        <h5><strong>Note: </strong>Shapeshift charges a 0.001 BTC fee for conversion. Not recommended for smaller amounts.</h5>
      </div>
      </div>
      </td>
      <td rowspan="2" ng-show="altcoin_waiting" ng-cloak><h3> Waiting for BTC payment from shapeshift altcoin conversion </h3><div class="spinner"></div><h5><a href="" ng-click="altcoin_waiting=false"> Click here</a> to cancel and go back </h5></td>
<?php endif; ?>
    </tr>
  </table>

  </div>
    <script src="<?php echo plugins_url('js/angular.min.js', __FILE__);?>"></script>
    <script src="<?php echo plugins_url('js/angular-resource.min.js', __FILE__);?>"></script>
    <script src="<?php echo plugins_url('js/app.js', __FILE__);?>"></script>
    <script src="<?php echo plugins_url('js/angular-qrcode.js', __FILE__);?>"></script>
    <script src="<?php echo plugins_url('js/vendors.min.js', __FILE__);?>"></script>
    <script src="<?php echo plugins_url('js/reconnecting-websocket.min.js', __FILE__);?>"></script>
<?php
//call the wp foooter
get_footer();
?>
  </body>
</html>
