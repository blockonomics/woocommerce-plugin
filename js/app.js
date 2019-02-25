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

var flyp_base = 'https://flyp.me/api/v1';

service.factory('AltcoinNew', function($resource) {
    var rsc = $resource(flyp_base + '/order/new');
    return rsc;
});

service.factory('AltcoinAccept', function($resource) {
    var rsc = $resource(flyp_base + '/order/accept');
    return rsc;
});

service.factory('AltcoinCheck', function($resource) {
    var rsc = $resource(flyp_base + '/order/check');
    return rsc;
});

service.factory('AltcoinInfo', function($resource) {
    var rsc = $resource(flyp_base + '/order/info');
    return rsc;
});

service.factory('AltcoinAddRefund', function($resource) {
    var rsc = $resource(flyp_base + '/order/addrefund');
    return rsc;
});

service.factory('AltcoinLimits', function($resource) {
    var rsc = $resource(flyp_base + '/order/limits/:coin/BTC', {
        coin: '@coin'
    });
    return rsc;
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
app.controller('CheckoutController', function($scope, $interval, Order, $httpParamSerializer, $timeout) {
    //get order id from url
    $scope.address = getParameterByNameBlocko("show_order");
    var totalProgress = 100;
    $scope.copyshow = false;
    //blockonomics_time_period is defined on JS file as global var
    var totalTime = blockonomics_time_period * 60;

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

    //Create url for altcoin payment
    $scope.alt_track_url = function(altcoin, amount, address, order_id) {
        var params = getParameterByNameBlocko('wc-api');
        if (params)
            params = {
                "wc-api": params
            };
        else
            params = {};
        params.uuid = 'create';
        params.altcoin = altcoin;
        params.amount = amount;
        params.address = address;
        params.order_id = order_id;
        url = window.location.pathname;
        var serializedParams = $httpParamSerializer(params);
        if (serializedParams.length > 0) {
            url += ((url.indexOf('?') === -1) ? '?' : '&') + serializedParams;
        }
        return url;
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

    //Pay with altcoin button clicked
    $scope.pay_altcoins = function() {
        $interval.cancel($scope.alt_tick_interval);
        $scope.order.altaddress = '';
        $scope.order.altamount = '';
        $scope.altcoin_waiting = true;
        $scope.alt_clock = 600;
        var altcoin = getAltKeyByValue($scope.altcoins, $scope.altcoinselect);
        $scope.order.altsymbol = getAltKeyByValue($scope.altcoins, $scope.altcoinselect);
        var amount = $scope.order.satoshi / 1.0e8;
        var address = $scope.order.address;
        var order_id = $scope.order.order_id;
        //Forward user to altcoin tracking page with details
        window.location = $scope.alt_track_url(altcoin, amount, address, order_id);
    }

    //Fetch the altcoin symbol from name
    function getAltKeyByValue(object, value) {
        return Object.keys(object).find(key => object[key] === value);
    }

    //Check if the bitcoin address is present
    if (typeof $scope.address != 'undefined') {
        //Fetch the order using address
        Order.get({
            "get_order": $scope.address
        }, function(data) {
            $scope.order = data;
            $scope.order.address = $scope.address;
            $scope.order.altcoin = $scope.altcoin;
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
                    $interval(function() {
                        //Redirect to order received page if message from socket
                        window.location = $scope.finish_order_url();
                    //Wait for 2 seconds for order status to update on server
                    }, 2000, 1);
                }
            }
        });
    }

    //Copy bitcoin address to clipboard
    $scope.btc_address_click = function() {
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
    //Define supported altcoins
    $scope.altcoins = {
        "ETH": "Ethereum",
        "LTC": "Litecoin"
    };
});

//AltcoinController
app.controller('AltcoinController', function($scope, $interval, Order, AltcoinNew, AltcoinAccept, AltcoinLimits, AltcoinCheck, AltcoinInfo, AltcoinAddRefund, WpAjax, $timeout, $httpParamSerializer) {
    var totalProgress = 100;
    var alt_totalTime = 0;
    var check_interval;
    var send_email = false;
    $scope.altsymbol = 'ETH';
    $scope.copyshow = false;
    $scope.spinner = true;

    //Check UUID in request
    if(getParameterByNameBlocko("uuid") == 'create'){
        //Create a new altcoin order
        create_order(getParameterByNameBlocko("altcoin"), getParameterByNameBlocko("amount"), getParameterByNameBlocko("address"), getParameterByNameBlocko("order_id"));
    } 
    else {
        //Check the info for altcoin order
        info_order(getParameterByNameBlocko("uuid"));
    }

    //Create url for refund page
    $scope.alt_refund_url = function(uuid) {
        var params = getParameterByNameBlocko('wc-api');
        if (params)
            params = {
                "wc-api": params
            };
        else
            params = {};
        params.uuid = uuid;
        url = window.location.pathname;
        var serializedParams = $httpParamSerializer(params);
        if (serializedParams.length > 0) {
            url += ((url.indexOf('?') === -1) ? '?' : '&') + serializedParams;
        }
        return url;
    }

    //Increment altcoin timer
    $scope.alt_tick = function() {
        $scope.alt_clock = $scope.alt_clock - 1;
        $scope.alt_progress = Math.floor($scope.alt_clock * totalProgress / alt_totalTime);
        if ($scope.alt_clock < 0) {
            $scope.alt_clock = 0;
            //Altcoin order expired
            $interval.cancel($scope.alt_tick_interval);
        }
        $scope.alt_progress = Math.floor($scope.alt_clock * totalProgress / alt_totalTime);
    };

    //Send altcoin refund email 
    function send_refund_email() {
        WpAjax.get({
            action: 'send_email',
            order_id: $scope.order.order_id,
            order_link: $scope.refundlink,
            order_coin: $scope.altcoinselect,
            order_coin_sym: $scope.order.altsymbol
        });
    }

    //Update the altcoin status
    function update_altcoin_status(status) {
        $scope.order.altstatus = status;
    }

    function get_uuid() {
        if($scope.altuuid) {
            uuid = $scope.altuuid;
        } else {
            uuid = getParameterByNameBlocko("uuid");
        }
        return uuid;
    }

    function wait_for_refund() {
        //Make sure only one interval is running
        stop_interval();
        uuid = get_uuid();
        check_interval = $interval(function(response) {
            info_order(uuid);
        }, 30000);
    }

    //Start checking the altcoin payment status every 10 sec
    function start_check_order() {
        //Make sure only one interval is running
        stop_interval();
        uuid = get_uuid();
        check_interval = $interval(function(response) {
            check_order(uuid);
        }, 10000);
    }

    //Stop checking the altcoin payment status every 10 sec
    function stop_interval() {
        $interval.cancel(check_interval);
    }

    //Check the altcoin payment status
    function check_order(uuid) {
        var response = AltcoinCheck.save({
                'uuid': uuid
            },function successCallback(data) {
                process_alt_response(data);
            });
    }

    //Check the full altcoin payment info
    function info_order(uuid) {
        //Fetch the altcoin info using uuid
        var response = AltcoinInfo.save({
                'uuid': uuid
            },function successCallback(data) {
                $scope.order = {};
                $scope.order.altaddress = data.deposit_address;
                $scope.order.altamount = data.order.invoiced_amount;
                $scope.order.destination = data.order.destination;
                var altsymbol = data.order.from_currency;
                alt_totalTime = data.expires;
                $scope.alt_clock = data.expires;
                $scope.alt_tick_interval = $interval($scope.alt_tick, 1000);
                $scope.order.altsymbol = altsymbol;
                $scope.altcoinselect = $scope.altcoins[altsymbol];
                $scope.spinner = false;

                process_alt_response(data);
                //Fetch the order id using bitcoin address
                Order.get({
                    "get_order": data.order.destination
                }, function(order) {
                    $scope.order.order_id = order.order_id;
                });
            });
    }

    //Create the altcoin order
    function create_order(altcoin, amount, address, order_id) {
        (function(promises) {
            return new Promise((resolve, reject) => {
                //Wait for both the altcoin limits and new altcoin order uuid
                Promise.all(promises)
                    .then(values => {
                        $scope.order = {};
                        $scope.order.order_id = order_id;
                        //Hide the spinner
                        $scope.spinner = false;
                        var alt_minimum = values[0].min;
                        var alt_maximum = values[0].max;
                        //Compare the min/max limits for altcoin payments with the order amount
                        if(amount <= alt_minimum) {
                            //Order amount too low for altcoin payment
                            update_altcoin_status('low_high');
                            $scope.lowhigh = 'low';
                            //Promise is run outside of the turn Angular sees so we need to tell
                            //Angular to update all of our bindings as data has changed
                            $scope.$apply();
                        }else if(amount >= alt_maximum) {
                            //Order amount too high for altcoin payment
                            update_altcoin_status('low_high');
                            $scope.lowhigh = 'high';
                            //Promise is run outside of the turn Angular sees so we need to tell
                            //Angular to update all of our bindings as data has changed
                            $scope.$apply();
                        }else{
                            var uuid = values[1].order.uuid;
                            //Save the altcoin uuid to database
                            WpAjax.get({
                                action: 'save_uuid',
                                address: address,
                                uuid: uuid
                            });
                            $scope.altuuid = uuid;
                            $scope.refundlink = $scope.alt_refund_url(uuid);
                            //Accept the altcoin order using the uuid
                            AltcoinAccept.save({
                                    "uuid": uuid
                                },function(order_accept) {
                                    //Display altcoin order info
                                    $scope.order.altaddress = order_accept.deposit_address;
                                    $scope.order.altamount = order_accept.order.invoiced_amount;
                                    $scope.order.destination = order_accept.order.destination;
                                    var altsymbol = order_accept.order.from_currency;
                                    alt_totalTime = order_accept.expires;
                                    $scope.alt_clock = order_accept.expires;
                                    $scope.alt_tick_interval = $interval($scope.alt_tick, 1000);
                                    $scope.order.altsymbol = altsymbol;
                                    $scope.altcoinselect = $scope.altcoins[altsymbol];
                                    //Only send email if create order
                                    send_email = true;
                                    //Update altcoin status to waiting
                                    update_altcoin_status('waiting');
                                    //Start checking the order status
                                    start_check_order(uuid);
                                });
                        }
                    })
                    .catch(err => {
                        console.dir(err);
                        throw err;
                    });
            });
        })([
            new Promise((resolve, reject) => {
                //Fetch altcoin min/max limits
                AltcoinLimits.get({coin: altcoin},function(order_limits) {
                    resolve(order_limits);
                });
            }),
            new Promise((resolve, reject) => {
                //Create the new altcoin order
                AltcoinNew.save({
                        "order": {
                            "from_currency": altcoin,
                            "to_currency": "BTC",
                            "ordered_amount": amount,
                            "destination": address
                        }
                    },function(order_new) {
                        //Resolve the new altcoin order uuid
                        resolve(order_new);
                    });
            })
        ]);
    }

    //Process altcoin response
    function process_alt_response(data) {
        switch (data.payment_status) {
            case "PAYMENT_RECEIVED":
            case "PAYMENT_CONFIRMED":
                update_altcoin_status('received');
                stop_interval();
                break;
            case "OVERPAY_RECEIVED":
            case "UNDERPAY_RECEIVED":
            case "OVERPAY_CONFIRMED":
            case "UNDERPAY_CONFIRMED":
                if ('refund_address' in data) {
                    if ('txid'in data) {
                        //Refund has been sent
                        update_altcoin_status('refunded-txid');
                        stop_interval();
                        $scope.order.alttxid = data.txid;
                        $scope.order.alturl = data.txurl;
                        break;
                    } else {
                        //Refund is being processed
                        wait_for_refund();
                        $scope.altuuid = uuid;
                        update_altcoin_status('refunded');
                        break;
                    }
                }
                //Refund address has not been added
                update_altcoin_status('add_refund');
                stop_interval();
                //Send email if not sent
                if (send_email) {
                    send_refund_email();
                    send_email = false;
                }
                break;
            default:
                switch (data['status']) {
                    case "WAITING_FOR_DEPOSIT":
                        update_altcoin_status('waiting');
                        start_check_order();
                        break;
                    case "EXPIRED":
                        update_altcoin_status('expired');
                        stop_interval();
                        break;
                }
        }
    }

    //Add a refund address to altcoin order
    $scope.add_refund_click = function() {
        var refund_address = document.getElementById("bnomics-refund-input").value;
        uuid = $scope.altuuid;
        var response = AltcoinAddRefund.save({
                'uuid': uuid,
                'address': refund_address
            },function successCallback(data) {
                if(data.result == 'ok') {
                    update_altcoin_status('refunded');
                    info_order(uuid);
                }
            });
    }

    //Go back to bitcoin payment page
    $scope.go_back = function() {
        window.history.back();
    }

    //Copy altcoin address to clipboard
    $scope.alt_address_click = function() {
        var copyText = document.getElementById("bnomics-alt-address-input");
        copyText.select();
        document.execCommand("copy");
        $scope.copyshow = true;
        $timeout(function() {
            $scope.copyshow = false;
        }, 2000);
    }

    //Define supported altcoins
    $scope.altcoins = {
        "ETH": "Ethereum",
        "LTC": "Litecoin"
    };
});