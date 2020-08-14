(function () {
'use strict';

angular.module('BlockonomicsApp', ['ngResource', 'monospaced.qrcode'])
.controller('CryptoOptionsController', CryptoOptionsController)
.controller('CheckoutController', CheckoutController)
.factory('Order', Order)
.service('Url', Url)
.config(Config);

Config.$inject = ['$compileProvider'];
function Config($compileProvider) {
  $compileProvider.aHrefSanitizationWhitelist(/^\s*(https?|ftp|mailto|data|chrome-extension|bitcoin|bitcoincash):/);
}

CryptoOptionsController.$inject = ['$scope', 'Url'];
function CryptoOptionsController($scope, Url) {
    var active_cryptos_div = document.getElementById("active_cryptos");
    var active_cryptos = JSON.parse(active_cryptos_div.dataset.active_cryptos);
    $scope.no_display_error = true;
    $scope.active_cryptos = active_cryptos;
    $scope.crypto_selecter  = true;
    //fetch url params
    $scope.order_id = Url.get_parameter_by_name("select_crypto");

    //Select Blockonomics crypto
    $scope.select_blockonomics_crypto = function(blockonomics_crypto) {
        $scope.crypto_selecter  = false;
        $scope.spinner = true;
        if (typeof $scope.order_id != 'undefined') {
            window.location = Url.get_wc_endpoint('show_order', $scope.order_id, blockonomics_crypto);
        }
    }
}

CheckoutController.$inject = ['$scope', '$interval', 'Order', '$timeout', 'Url'];
function CheckoutController($scope, $interval, Order, $timeout, Url) {
    var time_period_div = document.getElementById("time_period");
    var blockonomics_time_period = time_period_div.dataset.time_period;
    var totalTime = blockonomics_time_period * 60;
    var totalProgress = 100;
    $scope.no_display_error = true;
    $scope.copyshow = false;
    //fetch url params
    $scope.order_id = Url.get_parameter_by_name("show_order");

    check_blockonomics_order();

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

    //Proccess the order data
    function proccess_order_data() {
        if($scope.order.crypto.code === 'btc'){
            var subdomain = 'www';
        }else{
            var subdomain = $scope.order.crypto.code;
        }
        //Check the status of the order
        if ($scope.order.status === -1) {
            $scope.clock = $scope.order.timestamp + totalTime - Math.floor(Date.now() / 1000);
            //Mark order as expired if we ran out of time
            if ($scope.clock < 0) {
                $scope.order.status = -3;
                return;
            }
            $scope.tick_interval = $interval($scope.tick, 1000);
            //Connect and Listen on websocket for payment notification
            var ws = new ReconnectingWebSocket("wss://" + subdomain + ".blockonomics.co/payment/" + $scope.order.addr + "?timestamp=" + $scope.order.timestamp);
            ws.onmessage = function(evt) {
                ws.close();
                $interval(function() {
                    //Redirect to order received page if message from socket
                    window.location = Url.get_wc_endpoint('finish_order', $scope.order_id);
                //Wait for 2 seconds for order status to update on server
                }, 2000, 1);
            }
        }
    }
    
    //Check if the blockonomics order is present
    function check_blockonomics_order() {
        $scope.spinner = true;
        if (typeof $scope.order_id != 'undefined') {
            //Fetch the order using order_id
            Order.get({
                "get_order": $scope.order_id,
                "crypto": Url.get_parameter_by_name("crypto")
            }, function(data) {
                $scope.spinner = false;
                if(data.addr !== undefined){
                    $scope.order = data;
                    // show the checkout page
                    proccess_order_data();
                    $scope.checkout_panel  = true;
                }else if($scope.crypto.code === 'btc'){
                    $scope.address_error_btc = true;
                }else if($scope.crypto.code === 'bch'){
                    $scope.address_error_bch = true;
                }
            });
        }
    }

    function select_text(divid)
    {
        var selection = window.getSelection();
        var div = document.createRange();

        div.setStartBefore(document.getElementById(divid));
        div.setEndAfter(document.getElementById(divid)) ;
        selection.removeAllRanges();
        selection.addRange(div);
    }

    function copy_to_clipboard(divid)
    {
        var textarea = document.createElement('textarea');
        textarea.id = 'temp_element';
        textarea.style.height = 0;
        document.body.appendChild(textarea);
        textarea.value = document.getElementById(divid).innerText;
        var selector = document.querySelector('#temp_element');
        selector.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);

        select_text(divid);

        if (divid == "bnomics-address-copy") {
            $scope.address_copyshow = true;
            $timeout(function() {
                $scope.address_copyshow = false;
                //Close copy to clipboard message after 2 sec
            }, 2000);
        }else{
            $scope.amount_copyshow = true;
            $timeout(function() {
                $scope.amount_copyshow = false;
                //Close copy to clipboard message after 2 sec
            }, 2000);            
        }
    }

    //Copy bitcoin address to clipboard
    $scope.blockonomics_address_click = function() {
        copy_to_clipboard("bnomics-address-copy");
    }

    //Copy bitcoin amount to clipboard
    $scope.blockonomics_amount_click = function() {
        copy_to_clipboard("bnomics-amount-copy");
    }
    //Reload the page if user clicks try again after the order expires
    $scope.try_again_click = function() {
        location.reload();
    }
}

Order.$inject = ['$resource', 'Url'];
function Order($resource, Url) {
    var item = $resource(Url.get_wc_endpoint());
    return item;
}

Url.$inject = ['$httpParamSerializer'];
function Url($httpParamSerializer) {
    var service = this;

    service.get_parameter_by_name = function(name, url) {
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

    //There are two styles of callback url in 
    //woocommerce, we have to support both
    //https://docs.woocommerce.com/document/wc_api-the-woocommerce-api-callback/
    service.get_wc_endpoint = function(new_param = '', order_id = '', crypto_code = '') {
        var params = service.get_parameter_by_name('wc-api');
        if (params)
            params = {
                "wc-api": params
            };
        else
            params = {};
        if (new_param) {
            params[new_param] = order_id;
        }
        if (crypto_code) {
            params.crypto = crypto_code;
        }
        var url = window.location.pathname;
        var serializedParams = $httpParamSerializer(params);
        if (serializedParams.length > 0) {
            url += ((url.indexOf('?') === -1) ? '?' : '&') + serializedParams;
        }
        return url;
    }
}

})();
