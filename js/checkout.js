'use strict';
class Blockonomics {

    constructor({
        checkout_id = 'blockonomics_checkout'
    }={}) {

        // User Params
        this.checkout_id = checkout_id

        // Initialise
        this.init()
    }

    init() {
        this.container = document.getElementById(this.checkout_id);
        if (!this.container) {
            throw Error(`Blockonomics Initialisation Error: Container #${this.checkout_id} was not found!`)
        }

        // Load data attributes
        // This assumes a constant/var `blockonomics_data` is defined before the script is called.
        try {
            this.data = JSON.parse(blockonomics_data)
        } catch(e) {
            if (e.toString().includes('ReferenceError')) {
                throw Error(`Blockonomics Initialisation Error: Data Object was not found in Window. Please set blockonomics_data variable.`)
            }
            throw Error(`Blockonomics Initialisation Error: Data Object is not a valid JSON.`)
        }

        this.create_bindings()
        
        this.reset_progress()
        this._spinner_wrapper.style.display = 'none'
        this._order_panel.style.display = 'block'
        this.generate_qr()
        this.connect_to_ws()
        
        // Hide Display Error
        this._display_error_wrapper.style.display = "none"
    }

    create_bindings() {
        this._spinner_wrapper = this.container.querySelector('.bnomics-spinner-wrapper')

        this._order_expired_wrapper = this.container.querySelector('.bnomics-order-expired-wrapper')
        this._order_panel = this.container.querySelector('.bnomics-order-panel')

        this._amount_text = this.container.querySelector('.bnomics-amount-text')
        this._copy_amount_text = this.container.querySelector('.bnomics-copy-amount-text')
        this._amount_input = this.container.querySelector('#bnomics-amount-input')
        this._amount_copy = this.container.querySelector('#bnomics-amount-copy')

        this._address_text = this.container.querySelector('.bnomics-address-text')
        this._copy_address_text = this.container.querySelector('.bnomics-copy-address-text')
        this._address_input = this.container.querySelector('#bnomics-address-input')
        this._address_copy = this.container.querySelector('#bnomics-address-copy')

        this._time_left = this.container.querySelector('.bnomics-time-left')
        this._crypto_rate = this.container.querySelector('#bnomics-crypto-rate')

        this._try_again = this.container.querySelector('#bnomics-try-again')
        this._refresh = this.container.querySelector('#bnomics-refresh')
        this._show_qr = this.container.querySelector('#bnomics-show-qr')
        this._qr_code_container = this.container.querySelector('.bnomics-qr-code')
        this._qr_code = this.container.querySelector('#bnomics-qr-code')

        this._display_error_wrapper = this.container.querySelector(".bnomics-display-error")

        // Click Bindings

        // Copy bitcoin address to clipboard
        this._address_copy.addEventListener('click', (e) => {
            e.preventDefault()
            this.copy_to_clipboard("bnomics-address-input")
        })
        
        // Copy bitcoin amount to clipboard
        this._amount_copy.addEventListener('click', (e) => {
            e.preventDefault()
            this.copy_to_clipboard("bnomics-amount-input")
        })
        
        // QR Handler
        this._show_qr.addEventListener('click', (e) => {
            e.preventDefault()
            this.toggle_qr()
        })
            

        //Reload the page if user clicks try again after the order expires
        this._try_again.addEventListener('click', (e) => {
            e.preventDefault()
            location.reload()
        })
        
        this._refresh.addEventListener('click', (e) => {
            e.preventDefault()
            this.refresh_order()
        })

        this.data.time_period = Number(this.data.time_period)
    }

    reset_progress() {
        this.progress = {
            total_time: this.data.time_period * 60,
            interval: null,
            clock: this.data.time_period * 60,
            percent: 100
        }

        this.progress.interval = setInterval(() => this.tick(), 1000)
    }

    toggle_qr() {
        if (getComputedStyle(this._qr_code_container).display == "none") {
            this._qr_code_container.style.display = "block"
        } else {
            this._qr_code_container.style.display = "none"
        }
    }

    generate_qr() {
        const data = `${this.data.payment_uri}`
        this._qr =  new QRious({
            element: this._qr_code,
            value: data,
            size: 160
        });
    }

    tick() {
        this.progress.clock = this.progress.clock - 1;

        this.progress.percent = Math.floor(this.progress.clock * 100 / this.progress.total_time);
        if (this.progress.clock < 0) {
            this.progress.clock = 0;
            //Order expired
            this.order_expired()
        } else {
            this._time_left.innerHTML = `${String(Math.floor(this.progress.clock/60)).padStart(2, "0")}:${String(this.progress.clock%60).padStart(2, "0")} min`
        }
    }

    order_expired() {
        clearInterval(this.progress.interval)
        this._order_expired_wrapper.style.display = 'block'
        this._order_panel.style.display = 'none'
    }

    connect_to_ws() {
        //Connect and Listen on websocket for payment notification
        var ws = new ReconnectingWebSocket("wss://" + (this.data.crypto.code == 'btc' ? 'www' : this.data.crypto.code) + ".blockonomics.co/payment/" + this.data.crypto_address);
        let $this = this

        ws.onmessage = function(evt) {
            ws.close();

            setTimeout(function() {
                //Redirect to order confirmation page if message from socket
                $this.redirect_to_finish_order()
                //Wait for 2 seconds for order status to update on server
            }, 2000, 1);
        }
    }

    select_text(divid) {
        var selection = window.getSelection();
        var div = document.createRange();

        div.setStartBefore(document.getElementById(divid));
        div.setEndAfter(document.getElementById(divid)) ;
        selection.removeAllRanges();
        selection.addRange(div);
    }

    copy_to_clipboard(divid) {
        var textarea = document.createElement('textarea');
        textarea.id = 'temp_element';
        textarea.style.height = 0;
        document.body.appendChild(textarea);
        textarea.value = document.getElementById(divid).innerText;

        var selector = document.querySelector('#temp_element');
        selector.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);

        this.select_text(divid);

        let $this = this
        if (divid == "bnomics-address-input") {
            this._address_text.style.display = 'none'
            this._copy_address_text.style.display = 'block'
            setTimeout(function() {
                $this._address_text.style.display = 'block'
                $this._copy_address_text.style.display = 'none'
                //Close copy to clipboard message after 2 sec
            }, 2000);
        
        } else{
            this._amount_text.style.display = 'none'
            this._copy_amount_text.style.display = 'block'
            
            setTimeout(function() {
                $this._amount_text.style.display = 'block'
                $this._copy_amount_text.style.display = 'none'
                //Close copy to clipboard message after 2 sec
            }, 2000);
        }
    }

    redirect_to_finish_order() {
        window.location.href = this.data.finish_order_url
    }

    _set_refresh_loading(loading=false) {
        if(loading) {
            this._refresh.querySelector('.blockonomics-icon-refresh').classList.add('spin')
            this._refresh.setAttribute('disabled', 'disabled')
        } else {
            this._refresh.querySelector('.blockonomics-icon-refresh').classList.remove('spin')
            this._refresh.removeAttribute('disabled')
        }
    }

    refresh_order() {
        let url = `${this.data.api_url}?get_order=${this.get_url_param('show_order')}&crypto=${this.get_url_param('crypto')}`

        this._set_refresh_loading(true)

        // Stop Progress Counter
        clearInterval(this.progress.interval)
        
        fetch(url, {method: 'GET'}).then(
            res => {
                this._set_refresh_loading(false)
                if (res.status == 200) {
                    res.json().then(data => {
                        this._update_order_params(data)
                    },
                    () => {
                        this._fallback_refresh_order()
                    })
                } else {
                    // Non 200 Status Code
                    this._fallback_refresh_order()
                }
            },
            err => {
                // Blocked by Network Errors such as CORS, Offline, Server blocking request, etc
                this._set_refresh_loading(false)
                this._fallback_refresh_order()
            }
        )
    }

    _update_order_params(data) {
        // Updates the Dynamic Parts of Page
        
        const crypto_amount = this._satoshi_to_amount(parseFloat(data.satoshi))
        const fiat_conversion_rate = this._price_per_crypto(crypto_amount, parseFloat(data.value))

        this._amount_input.value = crypto_amount
        this._crypto_rate.innerHTML = fiat_conversion_rate

        this.reset_progress()
    }

    _fallback_refresh_order() {
        location.reload()
    }

    _satoshi_to_amount(satoshi) {
        return satoshi/1.0e8
    }

    _price_per_crypto(crypto, fiat_amount) {
        return Number(fiat_amount/crypto)
    }

    get_url_param(key) {
        let params = new URLSearchParams(window.location.search)
        return params.get(key)
    }
}

// Automatically trigger only after DOM is loaded
new Blockonomics()
