service = angular.module("shoppingcart.services", ["ngResource"]);
service.factory('Products', function ($resource) {
  var cart_item = $resource('php/productlist.php');
  return cart_item;
});

service.factory('AddItem', function ($resource) {
  var cart_item = $resource('php/additem.php');
  return cart_item;
});

service.factory('RemoveItem', function ($resource) {
  var cart_item = $resource('php/removeitem.php');
  return cart_item;
});

service.factory('Cart', function ($resource) {
  var cart_item = $resource('php/cart.php');
  return cart_item;
});

service.factory('EmptyCart', function ($resource) {
  var cart_item = $resource('php/emptycart.php');
  return cart_item;
});

service.factory('CreateInvoice', function ($resource) {
  var cart_item = $resource('php/createinvoice.php');
  return cart_item;
});

service.factory('Invoice', function ($resource) {
  var cart_item = $resource('php/invoice.php');
  return cart_item;
});

app = angular.module("shopping-cart-demo", ["monospaced.qrcode", "ngRoute", "shoppingcart.services"]);

app.config(function ($routeProvider) {
  $routeProvider
    .when('/', {templateUrl: 'views/shoppinglist.html', controller: ShoppingListController})
    .when('/invoice', {templateUrl: 'views/invoice.html', controller: CheckoutController})
    .when('/status', {templateUrl: 'views/status.html', controller: StatusController});
});

app.config(function ($compileProvider) {
  $compileProvider.aHrefSanitizationWhitelist(/^\s*(https?|ftp|mailto|data|chrome-extension|bitcoin):/);
  // Angular before v1.2 uses $compileProvider.urlSanitizationWhitelist(...)
});

function ShoppingListController($scope, $window, $location, $rootScope, 
    Products, AddItem, RemoveItem, EmptyCart, Cart, CreateInvoice) {
  $scope.itemList = Products.query();
  $scope.cart = Cart.query();
  $scope.total = 0;

  $scope.addItem = function(code, quantity){
    $scope.total = 0;
    $scope.cart = AddItem.query({"code":code, "quantity":quantity});
  };

  $scope.sum = function(value){
    $scope.total += value;
    $scope.total = +(Math.round($scope.total + "e+2")  + "e-2")
  };

  $scope.removeItem = function(code){
    $scope.cart = RemoveItem.query({"code":code});
  };

  $scope.emptyCart = function(){
    $scope.cart = EmptyCart.query();
  };

  $scope.checkoutCart = function(){
    CreateInvoice.get({}, function(order){
      window.location.href = "index.html#/invoice?order_id=" + order.order_id; 
    });
  };
}

function CheckoutController($scope, $location, $interval, $rootScope, Invoice, $route) {
  //get order id from url
  current_p = $location.path();
  current_s = $location.search();

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
      $scope.invoice.status = -3;
    }
    $scope.progress = Math.floor($scope.clock*totalProgress/totalTime);
  };

  if ( current_p == "/invoice" && typeof current_s.order_id != 'undefined'){
    Invoice.get({"order_id":current_s.order_id}, function(data){
      $scope.invoice = data;

      //Listen on websocket for payment notification
      //After getting notification,  refresh page
      if($scope.invoice.status == -1){
        $scope.clock = $scope.invoice.timestamp + totalTime - Math.floor(Date.now() / 1000); 
        //Mark order as expired if we ran out of time
        if ($scope.clock < 0)
        {
          $scope.invoice.status = -3;
          return;
        }
        $scope.tick_interval  = $interval($scope.tick, 1000);
        //Websocket
        var ws = new WebSocket("ws://localhost:8080/payment/" + $scope.invoice.addr + "?timestamp=" + $scope.invoice.timestamp);
        ws.onmessage = function (evt) {
          $interval(function(){
            $route.reload();
          }, 2000, 1);
        }
      }
    });
  }
}

function StatusController($scope, $window, $location, $rootScope) {
  $scope.checkStatus = function(){
    window.location.href = "index.html#/invoice?order_id=" + $scope.order_id; 
  };
}
