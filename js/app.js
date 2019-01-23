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
app.controller('CheckoutController', function($scope, $interval, Order, AltcoinNew, AltcoinAccept, AltcoinLimits, WpAjax, $httpParamSerializer, $timeout) {
    //get order id from url
    $scope.address = getParameterByNameBlocko("show_order");
    var totalProgress = 100;
    var interval;
    var address_present = false;
    //blockonomics_time_period is defined on JS file as global var
    var totalTime = blockonomics_time_period * 60;

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

    $scope.alt_track_url = function(uuid) {
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
        var amount = $scope.order.satoshi / 1.0e8;
        var address = $scope.order.address;
        var order_id = $scope.order.order_id;
        create_order(altcoin, amount, address, order_id);
    }

    //Altcoin Create
    function create_order(altcoin, amount, address, order_id) {
        (function(promises) {
            return new Promise((resolve, reject) => {
                Promise.all(promises)
                    .then(values => {
                        var alt_minimum = values[0]['min'];
                        var alt_maximum = values[0]['max'];
                        //Min/Max Check
                        if (amount <= alt_minimum) {
                            window.location = $scope.alt_track_url('low');
                        } else if (amount >= alt_maximum) {
                            window.location = $scope.alt_track_url('high');
                        } else {
                            WpAjax.get({
                                action: 'save_uuid',
                                address: values[1]['order']['destination'],
                                uuid: values[1]['order']['uuid']
                            });
                            window.location = $scope.alt_track_url(values[1]['order']['uuid']);
                        }
                        resolve(values);
                    })
                    .catch(err => {
                        console.dir(err);
                        throw err;
                    });
            });
        })([
            new Promise((resolve, reject) => {
                var response = AltcoinLimits.query({
                    coin: altcoin
                });
                resolve(response);
            }),
            new Promise((resolve, reject) => {
                AltcoinNew.save({
                        "order": {
                            "from_currency": altcoin,
                            "to_currency": "BTC",
                            "ordered_amount": amount,
                            "destination": address
                        }
                    })
                    .$promise.then(function(order_new) {
                        AltcoinAccept.save({
                                "uuid": order_new.order.uuid
                            })
                            .$promise.then(function(order_accept) {
                                resolve(order_accept);
                            })
                    })
            })
        ]);
    }

    function getAltKeyByValue(object, value) {
        return Object.keys(object).find(key => object[key] === value);
    }

    if (typeof $scope.address != 'undefined') {
        Order.get({
            "get_order": $scope.address
        }, function(data) {
            $scope.order = data;
            $scope.order.address = $scope.address;
            $scope.order.altcoin = $scope.altcoin;
            //Listen on websocket for payment notification
            //After getting notification,  refresh page
            if ($scope.order.status == -1) {
                $scope.clock = $scope.order.timestamp + totalTime - Math.floor(Date.now() / 1000);
                //Mark order as expired if we ran out of time
                if ($scope.clock < 0) {
                    $scope.order.status = -3;
                    return;
                }
                $scope.tick_interval = $interval($scope.tick, 1000);
                //Websocket
                var ws = new ReconnectingWebSocket("wss://www.blockonomics.co/payment/" + $scope.order.address + "?timestamp=" + $scope.order.timestamp);
                ws.onmessage = function(evt) {
                    ws.close();
                    $interval(function() {
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

    $scope.altcoins = {
        "ETH": "Ethereum",
        "LTC": "Litecoin"
    };
});

//AltcoinController
app.controller('AltcoinController', function($scope, $interval, Order, AltcoinCheck, AltcoinInfo, AltcoinAddRefund, WpAjax, $timeout) {
    var totalProgress = 100;
    var alt_totalTime = 0;
    var interval;
    var given_uuid = get_uuid;
    var send_email = false;
    $scope.altsymbol = 'ETH';
    var address_present = false;

    $scope.alt_tick = function() {
        $scope.alt_clock = $scope.alt_clock - 1;
        $scope.alt_progress = Math.floor($scope.alt_clock * totalProgress / alt_totalTime);
        if ($scope.alt_clock < 0) {
            $scope.alt_clock = 0;
            //Order expired
            $interval.cancel($scope.alt_tick_interval);
        }
        $scope.alt_progress = Math.floor($scope.alt_clock * totalProgress / alt_totalTime);
    };

    function sendEmail() {
        WpAjax.get({
            action: 'send_email',
            order_id: $scope.order.order_id,
            order_link: $scope.order.pagelink,
            order_coin: $scope.altcoinselect,
            order_coin_sym: $scope.order.altsymbol
        });
        send_email = false;
    }

    function updateAltcoinStatus(status, cancel_interval = false) {
        $scope.order.altstatus = status;
        if (cancel_interval == true) {
            $interval.cancel(interval);
        }
    }

    function startCheckOrder(uuid) {
        interval = $interval(function(response) {
            checkOrder(uuid);
        }, 10000);
    }

    function checkOrder(uuid) {
        var response = AltcoinCheck.save({
                'uuid': uuid
            })
            .$promise.then(function successCallback(data) {
                var payment_status = data['payment_status'];
                switch (payment_status) {
                    case "PAYMENT_RECEIVED":
                    case "PAYMENT_CONFIRMED":
                        updateAltcoinStatus('received', true);
                        break;
                    case "OVERPAY_RECEIVED":
                    case "UNDERPAY_RECEIVED":
                    case "OVERPAY_CONFIRMED":
                    case "UNDERPAY_CONFIRMED":
                        var status = data['status'];
                        switch (status) {
                            case "EXPIRED": //Orders not refundable (Extremely Low)
                                updateAltcoinStatus('refunded', true);
                                break;
                            case "REFUNDED":
                                if (data['txid']) {
                                    updateAltcoinStatus('refunded-txid', true);
                                    $scope.order.alttxid = data['txid'];
                                    $scope.order.alttxurl = data['txurl'];
                                } else {
                                    updateAltcoinStatus('refunded');
                                }
                                break;
                            default:
                                if (send_email == true) {
                                    sendEmail();
                                }
                                if (address_present == true) {
                                    updateAltcoinStatus('refunded');
                                } else {
                                    updateAltcoinStatus('add_refund', true);
                                }
                                break;
                        }
                    default:
                        var status = data['status'];
                        switch (status) {
                            case "WAITING_FOR_DEPOSIT":
                                updateAltcoinStatus('waiting');
                                break;
                            case "EXPIRED":
                                updateAltcoinStatus('expired', true);
                                break;
                        }
                }
            });
    }
    //Altcoin Info
    function infoOrder(uuid) {
        $scope.altuuid = uuid;
        // $scope.order.pagelink = window.location.href;
        var response = AltcoinInfo.save({
                'uuid': uuid
            })
            .$promise.then(function successCallback(data) {
                WpAjax.get({
                        action: 'fetch_order_id',
                        address: data.order.destination
                    })
                    .$promise.then(function(response) {
                        $scope.order.order_id = response.id;
                    });
                Order.get({
                    "get_order": data.order.destination
                }, function(order) {
                    $scope.order = order;
                    $scope.order.altaddress = data.deposit_address;
                    $scope.order.altamount = data.order.invoiced_amount;
                    $scope.order.destination = data.order.destination;
                    var altsymbol = data.order.from_currency;
                    alt_totalTime = data.expires;
                    $scope.alt_clock = data.expires;
                    $scope.alt_tick_interval = $interval($scope.alt_tick, 1000);
                    $scope.order.altsymbol = altsymbol;
                    $scope.altcoinselect = $scope.altcoins[altsymbol];
                    startCheckOrder(uuid);
                    var refund_address = data.refund_address;
                    if (refund_address) {
                        var txid = data.txid;
                        if (txid) {
                            updateAltcoinStatus('refunded-txid', true);
                            $scope.order.alttxid = data.txid;
                            $scope.order.alturl = data.txurl;
                        } else {
                            updateAltcoinStatus('refunded-txid');
                            address_present = true;
                        }
                    } else {
                        var payment_status = data.payment_status;
                        switch (payment_status) {
                            case "PAYMENT_RECEIVED":
                            case "PAYMENT_CONFIRMED":
                                updateAltcoinStatus('received', true);
                                break;
                            case "OVERPAY_RECEIVED":
                            case "UNDERPAY_RECEIVED":
                            case "OVERPAY_CONFIRMED":
                            case "UNDERPAY_CONFIRMED":
                                updateAltcoinStatus('add_refund', true);
                                break;
                            default:
                                var status = data.status;
                                switch (status) {
                                    case "WAITING_FOR_DEPOSIT":
                                        updateAltcoinStatus('waiting');
                                        break;
                                    case "EXPIRED":
                                        updateAltcoinStatus('expired', true);
                                        break;
                                }

                        }
                    }
                });
            });
    }
    //Check UUID in request
    var given_uuid = get_uuid;
    if (given_uuid != '') {
        if (given_uuid == 'low' || given_uuid == 'high') {
            $scope.order.altstatus = 'low_high';
            $scope.lowhigh = given_uuid;
        } else {
            infoOrder(given_uuid);
        }
    }
    //Add Refund Address click
    $scope.add_refund_click = function() {
        var refund_address = document.getElementById("bnomics-refund-input").value;
        uuid = $scope.altuuid;
        var response = AltcoinAddRefund.save({
                'uuid': uuid,
                'address': refund_address
            })
            .$promise.then(function successCallback(data) {
                if (data['result'] == 'ok') {
                    address_present = true;
                    $scope.order.altstatus = 'refunded';
                    startCheckOrder(uuid);
                }
            });
    }
    //Go Back click
    $scope.go_back = function() {
        window.history.back();
    }
    //Altcoin Form Copy To Clipboard
    $scope.copyshow = false;
    $scope.alt_address_click = function() {
        var copyText = document.getElementById("bnomics-alt-address-input");
        copyText.select();
        document.execCommand("copy");
        $scope.copyshow = true;
        $timeout(function() {
            $scope.copyshow = false;
        }, 2000);
    }
    //Altcoin List
    $scope.altcoins = {
        "ETH": "Ethereum",
        "LTC": "Litecoin"
    };
});