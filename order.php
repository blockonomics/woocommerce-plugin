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
<div class="aligncenter" style="width:45%">
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
      <tr><td style="width:70%">
      <table> 
      <tr>
      <td colspan="2" style="padding-right:20px;">
        <!-- Status -->
        <h5 ng-show="order.status != -1" for="invoice-amount" style="margin-top:15px;" ng-cloak>Status</h5>
        <div class="" style="margin-bottom:10px;" >
          <h3 ng-show="order.status == -1" ng-cloak >To pay, send exact amount of BTC to the given address</h3>
          <span style="color: rgb(239, 121, 79)" ng-show="order.status == -3" ng-cloak> Payment Expired</span>
          <span style="color: rgb(239, 121, 79)" ng-show="order.status == -2" ng-cloak> Payment Error</span>
          <span ng-show="order.status == 0" ng-cloak> Unconfirmed</span>
          <span ng-show="order.status == 1" ng-cloak> Partially Confirmed</span>
          <span ng-show="order.status >= 2" ng-cloak >Confirmed</span>
        </div>
      </td>
      </tr>
      <tr><td>
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
        </div>
      </td>
      <td style="vertical-align:top;padding-right:20px;"> 
        <h5 for="invoice-amount">Amount</h5>
        <div class="">
          <span ng-show="order.satoshi" ng-cloak>{{order.satoshi/1.0e8}}</span>
          <small>BTC</small> ⇌
          <span ng-cloak>{{order.value}}</span> 
          <small ng-cloak>{{order.currency}}</small>
        </div>
      </td>
      </tr>
      <tr><td colspan="2">
        <input type="text" ng-value="order.address" readonly="readonly">
      </td></tr>      
</table>
      </td>
      <td rowspan="2" style="vertical-align:middle;padding-left:20px;border-left: 2px ridge">
    <h3> OR you can </h3>
          <div >
     <script>function shapeshift_click(a,e){e.preventDefault();var link=a.href;window.open(link,'1418115287605','width=700,height=500,toolbar=0,menubar=0,location=0,status=1,scrollbars=1,resizable=0,left=0,top=0');return false;}</script> <a onclick="shapeshift_click(this, event);" href="https://shapeshift.io/shifty.html?destination={{order.address}}&amp;amount={{order.satoshi/1.0e8}}&amp;output=BTC"><img  src="https://shapeshift.io/images/shifty/small_dark_altcoins.png"  class="ss-button"></a>
      </div>
      </td>
    </tr>
  </table>

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
