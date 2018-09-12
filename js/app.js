service = angular.module("shoppingcart.services", ["ngResource"]);

service.factory('Order', function ($resource) {
  //There are two styles of callback url in 
  //woocommerce, we have to support both
  //https://docs.woocommerce.com/document/wc_api-the-woocommerce-api-callback/
  var param = getParameterByNameBlocko('wc-api');
  if (param)
    param = {"wc-api" : param};
  else
    param = {};
  var item = $resource(window.location.pathname, param);
  return item;
});

app = angular.module("shopping-cart-demo", ["monospaced.qrcode",  "shoppingcart.services"]);


app.config(function ($compileProvider) {
  $compileProvider.aHrefSanitizationWhitelist(/^\s*(https?|ftp|mailto|data|chrome-extension|bitcoin):/);
  // Angular before v1.2 uses $compileProvider.urlSanitizationWhitelist(...)
});

function getParameterByNameBlocko(name, url) {
    if (!url) {
      url = window.location.href;
    }
    name = name.replace(/[\[\]]/g, "\\$&");
    var regex = new RegExp("[?&]" + name + "(=([^&#]*)|&|#|$)"),
        results = regex.exec(url);
    if (!results) return null;
    if (!results[2]) return '';
    return decodeURIComponent(results[2].replace(/\+/g, " "));
}



app.controller('CheckoutController', function($scope, $interval, Order, $httpParamSerializer, $http,  $timeout) {
  //get order id from url
  $scope.address =  getParameterByNameBlocko("show_order")
  var totalProgress = 100;
  //blockonomics_time_period is defined on JS file as global var
  var totalTime = blockonomics_time_period * 60;
  $scope.getJson = function(data){
    return JSON.parse(data);
  };
  $scope.finish_order_url = function() {
    var params = getParameterByNameBlocko('wc-api');
    if (params)
      params = {"wc-api" : params};
    else
      params = {};
    params.finish_order = $scope.address;
    url = window.location.pathname;
    var serializedParams = $httpParamSerializer(params);
    if (serializedParams.length > 0) {
        url += ((url.indexOf('?') === -1) ? '?' : '&') + serializedParams;
    }
    return url;
  }

  $scope.tick = function() {
    $scope.clock = $scope.clock-1;
    $scope.progress = Math.floor($scope.clock*totalProgress/totalTime);
    if ($scope.clock < 0)
    {
      $scope.clock = 0;
      //Order expired
      $scope.order.status = -3;
    }
    $scope.progress = Math.floor($scope.clock*totalProgress/totalTime);
  };
  $scope.pay_altcoins = function() {
    $scope.altcoin_waiting = true;
    // var altcoin = getAltKeyByValue($scope.altcoins, $scope.altcoinselect);
    // var amount = $scope.order.satoshi/1.0e8;
    // var address = $scope.order.address;
    // $http({
    // method: 'POST',
    // data: {'altcoin': altcoin, 'amount': amount, 'address': address},
    // url: '../../wp-content/plugins/woocommerce-plugin-master/php/flyp/createOrder.php' //Rather pass plugin url
    // }).then(function successCallback(response) {
    //     $scope.order.altaddress = response.data;
    //   }, function errorCallback(response) {
    //     //console.log(response);
    //   });
  }
  function getAltKeyByValue(object, value) {
    return Object.keys(object).find(key => object[key] === value);
  }
  if ( typeof $scope.address != 'undefined'){
    Order.get({"get_order":$scope.address}, function(data){
      $scope.order = data;
      $scope.order.address = $scope.address;
      $scope.order.altcoin = $scope.altcoin;
      //Listen on websocket for payment notification
      //After getting notification,  refresh page
      if($scope.order.status == -1){
        $scope.clock = $scope.order.timestamp + totalTime - Math.floor(Date.now() / 1000); 
        //Mark order as expired if we ran out of time
        if ($scope.clock < 0)
        {
          $scope.order.status = -3;
          return;
        }
        $scope.tick_interval  = $interval($scope.tick, 1000);
        //Websocket
        var ws = new ReconnectingWebSocket("wss://www.blockonomics.co/payment/" + $scope.order.address + "?timestamp=" + $scope.order.timestamp);
        ws.onmessage = function (evt) {
          ws.close();
          $interval(function(){
            //Redirect to order received page
            window.location = $scope.finish_order_url();
            //Wait for 2 seconds for order status
                  //to update on server
          }, 2000, 1);
        }
      }
    });
  }
  $scope.copyshow = false;
  //Order Form Copy To Clipboard
  $scope.btc_address_click = function() {
    var copyText = document.getElementById("bnomics-address-input");
    copyText.select();
    document.execCommand("copy");
    //Open Message
    $scope.copyshow = true;
    $timeout(function() {
        $scope.copyshow = false;
     }, 2000); 
  }
  $scope.alt_address_click = function() {
    var copyText = document.getElementById("bnomics-alt-address-input");
    copyText.select();
    document.execCommand("copy");  
    $scope.copyshow = true;
    $timeout(function() {
        $scope.copyshow = false;
     }, 2000); 
  }

  $scope.altcoins = {"ETH": "Ethereum", "LTC": "Litecoin", "ZEC": "Z Cash"};

});