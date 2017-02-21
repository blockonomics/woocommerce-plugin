service = angular.module("shoppingcart.services", ["ngResource"]);

service.factory('Order', function ($resource) {
  var item = $resource('', {"wc-api": 'WC_Gateway_Blockonomics'});
  return item;
});

app = angular.module("shopping-cart-demo", ["monospaced.qrcode", "ngRoute", "shoppingcart.services"]);


app.config(function ($compileProvider) {
  $compileProvider.aHrefSanitizationWhitelist(/^\s*(https?|ftp|mailto|data|chrome-extension|bitcoin):/);
  // Angular before v1.2 uses $compileProvider.urlSanitizationWhitelist(...)
});


app.controller('CheckoutController', function($scope, $location, $interval, $rootScope, Order, $route) {
  //get order id from url
  current_p = $location.path().substr(1);
  $scope.order_id = current_p;
  var totalProgress = 100;
  var totalTime = 10*60; //10m

  $scope.getJson = function(data){
    return JSON.parse(data);
  };

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

  if ( $scope.order_id != 'undefined'){
    Order.get({"order_id":$scope.order_id}, function(data){
      $scope.order = data;

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
        var ws = new WebSocket("ws://localhost:8080/payment/" + $scope.order.address + "?timestamp=" + $scope.order.timestamp);
        ws.onmessage = function (evt) {
          $interval(function(){
            $route.reload();
          }, 2000, 1);
        }
      }
    });
  }
});

