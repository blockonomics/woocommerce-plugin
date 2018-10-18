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
  $compileProvider.aHrefSanitizationWhitelist(/^\s*(https?|ftp|mailto|data|chrome-extension|bitcoin|ethereum|litecoin):/);
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
  $scope.address =  getParameterByNameBlocko("show_order");
  var totalProgress = 100;
  //blockonomics_time_period is defined on JS file as global var
  var totalTime = blockonomics_time_period * 60;
  var alt_totalTime = 0;
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
  $scope.alt_tick = function() {
    $scope.alt_clock = $scope.alt_clock-1;
    $scope.alt_progress = Math.floor($scope.alt_clock*totalProgress/alt_totalTime);
    if ($scope.alt_clock < 0)
    {
      $scope.alt_clock = 0;
      //Order expired
      $interval.cancel(interval);
      $scope.order.altstatus = -3;
    }
    $scope.alt_progress = Math.floor($scope.alt_clock*totalProgress/alt_totalTime);
  };
  var interval;
  var given_uuid=get_uuid;
  var send_email = false;
  $scope.altsymbol = 'ETH';
  function checkOrder(uuid){
    $http({
          method: 'GET',
          params: {'action': 'check_order', 'uuid': uuid},
          url: my_ajax_object.ajax_url
          }).then(function successCallback(response) {
            if(response.data['status'] == "WAITING_FOR_DEPOSIT"){
              $scope.order.altstatus = 0;
            }
            if(response.data['status'] == "DEPOSIT_RECEIVED"){
              if(send_email == true){
                var order_id = $scope.order.order_id;
                var order_link = $scope.order.pagelink;
                var order_coin = $scope.altcoinselect;
                var order_coin_sym = $scope.order.altsymbol;
                //Send Email
                $http({
                  method: 'GET',
                  params: {'action': 'send_email', 'order_id':order_id, 'order_link':order_link, 'order_coin':order_coin, 'order_coin_sym':order_coin_sym},
                  url: my_ajax_object.ajax_url
                  });
                send_email = false;
              }
              $scope.order.altstatus = 1;
            }
            if(response.data['status'] == "DEPOSIT_CONFIRMED"){
              $scope.order.altstatus = 2;
            }
            if(response.data['status'] == "EXECUTED"){
              $scope.order.altstatus = 3;
              $interval.cancel(interval);
            }
            if(response.data['status'] == "REFUNDED"){
              $scope.order.altstatus = -1;
              $interval.cancel(interval);
            }
            if(response.data['status'] == "CANCELED"){
              $scope.order.altstatus = -2;
              $interval.cancel(interval);
            }
            if(response.data['status'] == "EXPIRED"){
              $scope.order.altstatus = -3;
              $interval.cancel(interval);
            }
            }, function errorCallback(response) {
              //console.log(response);
            });
  }

  $scope.pay_altcoins = function() {
    $interval.cancel(interval);
    $interval.cancel($scope.alt_tick_interval);
    $scope.order.altaddress = '';
    $scope.order.altamount = '';
    $scope.order.altstatus = 0;
    $scope.altcoin_waiting = true;
    $scope.alt_clock = 1200;
    send_email = true;
    var altcoin = getAltKeyByValue($scope.altcoins, $scope.altcoinselect);
    $scope.order.altsymbol = getAltKeyByValue($scope.altcoins, $scope.altcoinselect);
    var amount = $scope.order.satoshi/1.0e8;
    var address = $scope.order.address;
    var order_id = $scope.order.order_id;
    $http({
    method: 'GET',
    params: {'action': 'fetch_limit', 'altcoin': altcoin},
    url: my_ajax_object.ajax_url
    }).then(function successCallback(response) {
      var alt_minimum = response.data['min'];
      var alt_maximum = response.data['max'];
      if(amount >= alt_minimum && amount <= alt_maximum){
        $http({
        method: 'GET',
        params: {'action': 'create_order', 'altcoin': altcoin, 'amount': amount, 'address': address, 'order_id':order_id},
        url: my_ajax_object.ajax_url
        }).then(function successCallback(response) {
          alt_totalTime = response.data['expires'];
          $scope.alt_clock = response.data['expires'];
          $scope.alt_tick_interval  = $interval($scope.alt_tick, 1000);
          $scope.order.altaddress = response.data['deposit_address'];
          if($scope.order.altsymbol == 'ETH'){
            $scope.order.altaddress_link = 'https://etherscan.io/address/' + response.data['deposit_address'];
          }
          if($scope.order.altsymbol == 'LTC'){
            $scope.order.altaddress_link = 'https://chainz.cryptoid.info/ltc/address.dws?' + response.data['deposit_address'];
          }
          $scope.order.altamount = response.data['order']['invoiced_amount'];
          var uuid = response.data['order']['uuid'];
          $scope.order.pagelink = window.location.href + '&uuid=' + uuid;
          $scope.altuuid = uuid;
            interval = $interval(function(response) {
              checkOrder(uuid);
            }, 10000);
          }, function errorCallback(response) {
            //console.log(response);
          });
      }else if(amount <= alt_minimum){
        //Min/Max Error
        $scope.order.altstatus = -4;
        $scope.lowhigh = "low";
      }else{
        $scope.order.altstatus = -4;
        $scope.lowhigh = "high";        
      }
      }, function errorCallback(response) {
        //console.log(response);
      });
  }
  function getAltKeyByValue(object, value) {
    return Object.keys(object).find(key => object[key] === value);
  }
  function infoOrder(uuid) {
    $scope.altuuid = uuid;
    $http({
    method: 'GET',
    params: {'action': 'info_order', 'uuid': uuid},
    url: my_ajax_object.ajax_url
    }).then(function successCallback(response) {
        $scope.order.altaddress = response.data['deposit_address'];
        if(response.data['order']['from_currency'] == 'ETH'){
          $scope.order.altaddress_link = 'https://etherscan.io/address/' + response.data['deposit_address'];
        }
        if(response.data['order']['from_currency'] == 'LTC'){
          $scope.order.altaddress_link = 'https://chainz.cryptoid.info/ltc/address.dws?' + response.data['deposit_address'];
        }
        $scope.order.altamount = response.data['order']['invoiced_amount'];
        var altsymbol = response.data['order']['from_currency'];
        $scope.order.altsymbol = altsymbol;
        $scope.altcoinselect = $scope.altcoins[altsymbol];
        alt_totalTime = response.data['expires'];
        $scope.alt_clock = response.data['expires'];
        interval = $interval(function(response) {
          checkOrder(uuid);
        }, 10000);
        $scope.alt_tick_interval  = $interval($scope.alt_tick, 1000);
            if(response.data['status'] == "WAITING_FOR_DEPOSIT"){
              $scope.order.altstatus = 0;
            }
            if(response.data['status'] == "DEPOSIT_RECEIVED"){
              $scope.order.altstatus = 1;
            }
            if(response.data['status'] == "DEPOSIT_CONFIRMED"){
              $scope.order.altstatus = 2;
            }
            if(response.data['status'] == "EXECUTED"){
              $scope.order.altstatus = 3;
              $interval.cancel(interval);
            }
            if(response.data['status'] == "REFUNDED"){
              $scope.order.altstatus = -1;
              $interval.cancel(interval);
            }
            if(response.data['status'] == "CANCELED"){
              $scope.order.altstatus = -2;
              $interval.cancel(interval);
            }
            if(response.data['status'] == "EXPIRED"){
              $scope.order.altstatus = -3;
              $interval.cancel(interval);
            }
      }, function errorCallback(response) {
        //console.log(response);
      });
  }
  if ( typeof $scope.address != 'undefined'){
    Order.get({"get_order":$scope.address}, function(data){
      $scope.order = data;
      $scope.order.address = $scope.address;
      $scope.order.altcoin = $scope.altcoin;
      var order_id = $scope.order.order_id;
      if(given_uuid!=''){
        $scope.order.pagelink = window.location.href;
        infoOrder(given_uuid);
      }else{
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

  $scope.altcoins = {"ETH": "Ethereum", "LTC": "Litecoin"};

  $scope.page_link_click = function() {
    var copyText = document.getElementById("bnomics-page-link-input");
    copyText.select();
    document.execCommand("copy");  
    $scope.copyshow = true;
    $timeout(function() {
        $scope.copyshow = false;
     }, 2000); 
  }
});