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
      $interval.cancel($scope.alt_tick_interval);
    }
    $scope.alt_progress = Math.floor($scope.alt_clock*totalProgress/alt_totalTime);
  };
  var interval;
  var given_uuid=get_uuid;
  var given_ajax_url = ajax_object.ajax_url;
  var send_email = false;
  $scope.altsymbol = 'ETH';
  function checkOrder(uuid){
    $http({
          method: 'GET',
          params: {'action': 'check_order', 'uuid': uuid},
          url: given_ajax_url
          }).then(function successCallback(response) {
            if(response.data['payment_status'] == "PAYMENT_RECEIVED" || response.data['payment_status'] == "PAYMENT_CONFIRMED"){
              $scope.order.altstatus = 'received';
              $interval.cancel(interval);
            }else if(response.data['payment_status'] == "OVERPAY_RECEIVED" || response.data['payment_status'] == "UNDERPAY_RECEIVED" || response.data['payment_status'] == "OVERPAY_CONFIRMED" || response.data['payment_status'] == "UNDERPAY_CONFIRMED"){
              if(response.data['status'] == "EXPIRED"){
                $scope.order.altstatus = 'refunded';
                $interval.cancel(interval);
              }else if(response.data['status'] == "REFUNDED"){
              if(response.data['txid']){
                $scope.order.altstatus = 'refunded-txid';
                $scope.order.alttxid = response.data['txid'];
                $scope.order.alttxurl = response.data['txurl'];
                $interval.cancel(interval);
              }else{
                $scope.order.altstatus = 'refunded';
              }
              }else{
                if(send_email == true){
                  var order_id = $scope.order.order_id;
                  var order_link = $scope.order.pagelink;
                  var order_coin = $scope.altcoinselect;
                  var order_coin_sym = $scope.order.altsymbol;
                  //Send Email
                  $http({
                    method: 'GET',
                    params: {'action': 'send_email', 'order_id':order_id, 'order_link':order_link, 'order_coin':order_coin, 'order_coin_sym':order_coin_sym},
                    url: given_ajax_url
                    });
                  send_email = false;
                }
                if(address_present == true){
                  $scope.order.altstatus = 'refunded';
                }else{
                  $scope.order.altstatus = 'add_refund';
                  $interval.cancel(interval);
                }
              }
            }else if(response.data['status'] == "WAITING_FOR_DEPOSIT"){
              $scope.order.altstatus = 'waiting';
            }else if(response.data['status'] == "EXPIRED"){
              $scope.order.altstatus = 'expired';
              $interval.cancel(interval);
            }else if(response.data['status'] == "REFUNDED"){
              if(response.data['txid']){
                $scope.order.altstatus = 'refunded-txid';
                $scope.order.alttxid = response.data['txid'];
                $scope.order.alttxurl = response.data['txurl'];
                $interval.cancel(interval);
              }else{
                $scope.order.altstatus = 'refunded';
              }
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
    $scope.altcoin_waiting = true;
    $scope.alt_clock = 600;
    send_email = true;
    var altcoin = getAltKeyByValue($scope.altcoins, $scope.altcoinselect);
    $scope.order.altsymbol = getAltKeyByValue($scope.altcoins, $scope.altcoinselect);
    var amount = $scope.order.satoshi/1.0e8;
    var address = $scope.order.address;
    var order_id = $scope.order.order_id;
    create_order(altcoin, amount, address, order_id);
  }
  function getAltKeyByValue(object, value) {
    return Object.keys(object).find(key => object[key] === value);
  }
  function create_order(altcoin, amount, address, order_id){
    ( function( promises ){
      return new Promise( ( resolve, reject ) => {
          Promise.all( promises )
              .then( values => {
                var alt_minimum = values[0]['min'];
                var alt_maximum = values[0]['max'];
                //Min/Max Check
                if(amount <= alt_minimum){
                    window.location.search = '?uuid='+ 'low';
                }else if(amount >= alt_maximum){
                    window.location.search = '?uuid='+ 'high';        
                }else{
                    window.location.search = '?uuid='+ values[1]['order']['uuid'];                   
                }
                resolve( values );
              })
              .catch( err => {
                  console.dir( err );
                  throw err;
              });
      });
  })([ 
      new Promise( ( resolve, reject ) => {
          $http({
          method: 'GET',
          params: {'action': 'fetch_limit', 'altcoin': altcoin},
          url: given_ajax_url
          }).then(function successCallback(response) {
            resolve( response.data );
            }, function errorCallback(response) {
              //console.log(response);
            });
      }),
      new Promise( ( resolve, reject ) => {
        $http({
            method: 'GET',
            params: {'action': 'create_order', 'altcoin': altcoin, 'amount': amount, 'address': address, 'order_id':order_id},
            url: given_ajax_url
            }).then(function successCallback(response) {
              resolve( response.data );
              }, function errorCallback(response) {
                //console.log(response);
              });
      })
   ]);
  }
  var address_present = false;
  function infoOrder(uuid) {
    $scope.altuuid = uuid;
    $http({
    method: 'GET',
    params: {'action': 'info_order', 'uuid': uuid},
    url: given_ajax_url
    }).then(function successCallback(response) {
        $scope.order.altaddress = response.data['deposit_address'];
        $scope.order.altamount = response.data['order']['invoiced_amount'];
        $scope.order.destination = response.data['order']['destination'];
        var altsymbol = response.data['order']['from_currency'];
        response.data['order']['expires'];
        alt_totalTime = response.data['expires'];
        $scope.alt_clock = response.data['expires'];
        $scope.alt_tick_interval  = $interval($scope.alt_tick, 1000);
        $scope.order.altsymbol = altsymbol;
        $scope.altcoinselect = $scope.altcoins[altsymbol];
        interval = $interval(function(response) {
          checkOrder(uuid);
        }, 10000);
            if(response.data['payment_status'] == "PAYMENT_RECEIVED" || response.data['payment_status'] == "PAYMENT_CONFIRMED"){
              $scope.order.altstatus = 'received';
              $interval.cancel(interval);
            }else if(response.data['status'] == "REFUNDED" || response.data['refund_address']){
              if(response.data['txid']){
                $scope.order.altstatus = 'refunded-txid';
                $scope.order.alttxid = response.data['txid'];
                $scope.order.alturl = response.data['txurl'];
                $interval.cancel(interval);
              }else{
                $scope.order.altstatus = 'refunded';
                if(response.data['refund_address']){
                  address_present = true;
                }
              }
            }else if(response.data['payment_status'] == "OVERPAY_RECEIVED" || response.data['payment_status'] == "UNDERPAY_RECEIVED" || response.data['payment_status'] == "OVERPAY_CONFIRMED" || response.data['payment_status'] == "UNDERPAY_CONFIRMED"){
              if(response.data['refund_address']){
                $scope.order.altstatus = 'refunded';
              }else{
                $scope.order.altstatus = 'add_refund';
                $interval.cancel(interval);
              }
            }else if(response.data['status'] == "WAITING_FOR_DEPOSIT"){
              $scope.order.altstatus = 'waiting';
            }else if(response.data['status'] == "EXPIRED"){
              $scope.order.altstatus = 'expired';
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
        if(given_uuid == 'low' || given_uuid == 'high'){
          $scope.order.altstatus = 'low_high';
          $scope.lowhigh = given_uuid;
        }else{
          infoOrder(given_uuid);
        }
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
  //Add Refund Address click
  $scope.add_refund_click = function() {
    var refund_address = document.getElementById("bnomics-refund-input").value;
    uuid = $scope.altuuid;
    $http({
    method: 'GET',
    params: {'action': 'add_refund', 'uuid': uuid, 'address': refund_address},
    url: given_ajax_url
    }).then(function successCallback(response) {
        if(response.data['result'] == 'ok'){
          address_present = true;
          $scope.order.altstatus = 'refunded';
          interval = $interval(function(response) {
              checkOrder(uuid);
            }, 10000);
        }
    });
  }
  //Go Back click
  $scope.go_back = function() {
    window.history.back();
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