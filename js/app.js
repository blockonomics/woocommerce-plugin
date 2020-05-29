service = angular.module("shoppingcart.services", ["ngResource"]);

service.factory('Order', function($resource) {
    //There are two styles of callback url in 
    //woocommerce, we have to support both
    //https://docs.woocommerce.com/document/wc_api-the-woocommerce-api-callback/
    var param = getParameterByNameBlocko('wc-api');
    if (param)
        param = {
            "wc-api": param
        };
        else
            param = {};
        var item = $resource(window.location.pathname, param);
        return item;
    });

service.factory('WpAjax', function($resource) {
    var rsc = $resource(ajax_object.ajax_url);
    return rsc;
});

app = angular.module("shopping-cart-demo", ["monospaced.qrcode", "shoppingcart.services"]);


app.config(function($compileProvider) {
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

//CheckoutController
app.controller('CheckoutController', function($scope, $interval, Order, $httpParamSerializer, $timeout, WpAjax) {
    //get order id from url
    $scope.order_id = getParameterByNameBlocko("show_order");
    var totalProgress = 100;
    $scope.copyshow = false;
    $scope.amountcopyshow = false;
    //blockonomics_time_period is defined on JS file as global var
    var totalTime = blockonomics_time_period * 60;
    $scope.display_problems = true;
    //Create url when the order is received 
    $scope.finish_order_url = function() {
        var params = getParameterByNameBlocko('wc-api');
        if (params)
            params = {
                "wc-api": params
            };
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
    
    // Select Blockonomics currency
    $scope.select_blockonomics_currency = function(blockonomics_currency) {
        $scope.currency = blockonomics_currency;
        $scope.currency_selecter  = false;
    }

    var active_currencies_div = document.getElementById("active_currencies");
    var active_currencies = JSON.parse(active_currencies_div.dataset.active_currencies);
    $scope.active_currencies = active_currencies;
    if($scope.active_currencies.bch.enabled == true){
            $scope.currency_selecter  = true;
    }else{
            $scope.currency_selecter  = false;
            $scope.currency = 'BTC'
    }



    //Increment bitcoin timer 
    $scope.tick = function() {
        $scope.clock = $scope.clock - 1;
        $scope.progress = Math.floor($scope.clock * totalProgress / totalTime);
        if ($scope.clock < 0) {
            $scope.clock = 0;
            //Order expired
            $scope.order.status = -3;
        }
        $scope.progress = Math.floor($scope.clock * totalProgress / totalTime);
    };

    //Check if the bitcoin address is present
    if (typeof $scope.order_id != 'undefined') {
        //Fetch the order using address
        Order.get({
            "get_order": $scope.order_id
        }, function(data) {
            $scope.order = data;
            //Check the status of the order
            if ($scope.order.status == -1) {
                $scope.clock = $scope.order.timestamp + totalTime - Math.floor(Date.now() / 1000);
                //Mark order as expired if we ran out of time
                if ($scope.clock < 0) {
                 $scope.order.status = -3;
                 return;
             }
             $scope.tick_interval = $interval($scope.tick, 1000);
                //Connect and Listen on websocket for payment notification
                var ws = new ReconnectingWebSocket("wss://www.blockonomics.co/payment/" + $scope.order.address + "?timestamp=" + $scope.order.timestamp);
                ws.onmessage = function(evt) {
                    ws.close();
                    $timeout(function() {
                        //Redirect to order received page if message from socket
                        window.location = $scope.finish_order_url();
                    //Wait for 2 seconds for order status to update on server
                }, 2000, 1);
                }
            }
        });
    }

    //Copy bitcoin address to clipboard
    $scope.crypto_address_click = function() {
        var copyText = document.getElementById("bnomics-address-input");
        copyText.select();
        document.execCommand("copy");
        //Open copy clipboard message
        $scope.copyshow = true;
        $timeout(function() {
            $scope.copyshow = false;
        //Close copy to clipboard message after 2 sec
    }, 2000);
    }

            //Copy bitcoin amount to clipboard
            $scope.crypto_amount_click = function() {
                navigator.clipboard.writeText($scope.order.satoshi/1.0e8);
                //Open copy clipboard message
                $scope.amountcopyshow = true;
                $timeout(function() {
                    $scope.amountcopyshow = false;
                //Close copy to clipboard message after 2 sec
            }, 2000);
            }
});
