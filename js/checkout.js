'use strict';
class Blockonomics {

    constructor({
        checkout_id = 'blockonomics_checkout',
        crypto = 'btc',
        order_id,
        crypto_amount,
        crypto_address,
        fiat_currency,
        fiat_amount,
        finish_order_url,
        time_period = 10
    }={}) {

        // Internal Params
        this.cryptos = {
            btc: {
                code: 'btc',
                name: 'Bitcoin',
                uri: 'bitcoin'
            },
            bch: {
                code: 'bch',
                name: 'Bitcoin Cash',
                uri: 'bitcoincash'
            }
        }

        // User Params
        this.checkout_id = checkout_id
        this.crypto = this.cryptos[crypto]
        this.order_id = order_id
        this.crypto_amount = crypto_amount
        this.crypto_address = crypto_address
        this.fiat_currency = fiat_currency
        this.fiat_amount = fiat_amount
        this.finish_order_url = finish_order_url
        this.time_period = time_period

        // Computed Properties
        this.progress = {
            total_time: this.time_period * 60,
            interval: null,
            clock: this.time_period * 60,
            percent: 100
        }
    }

    async init() {
        this.container = document.getElementById(this.checkout_id);
        if (!this.container) {
            throw Error(`Blockonomics Initialisation Error: Container #${this.checkout_id} was not found!`)
        }

        await this.load_external_css()

        this.create_bindings()

        await this.load_external_scripts()
        
        this.progress.interval = setInterval(() => this.tick(), 1000)
        this._spinner_wrapper.style.display = 'none'
        this._order_panel.style.display = 'block'
        this.generate_qr()
        this.connect_to_ws()
    }

    load_external_css() {
        // Placeholder Function, may be used in future
    }

    load_external_scripts() {
        let scripts = [
            "https://cdnjs.cloudflare.com/ajax/libs/reconnecting-websocket/1.0.0/reconnecting-websocket.min.js",
            "https://cdnjs.cloudflare.com/ajax/libs/qrious/4.0.2/qrious.min.js"
        ]

        
        for(let i=0; i<scripts.length; i++) {
            window.bnomics_external_script_loaded = false

            let script = document.createElement('script')
            script.setAttribute('src', scripts[i])
            script.setAttribute('type', 'text/javascript')
            script.classList.add('controlled-by-bnomics')
            script.setAttribute("async", true)
            script.addEventListener('load', () => {script.setAttribute('bnomics-loaded', 'true')})
            script.addEventListener('error', () => {script.setAttribute('bnomics-error', 'true')})

            document.body.appendChild(script)
        }

        return new Promise( (resolve, reject) => {
            let check_script = () => {

                let is_loaded = true
                document.querySelectorAll('script.controlled-by-bnomics').forEach(script => {
                    if (!script.hasAttribute('bnomics-loaded') && !script.hasAttribute('bnomics-error')) {
                        is_loaded = false
                    }
                })

                if (is_loaded) resolve()
                else setTimeout(check_script, 1000)
            }

            setTimeout(check_script, 1000)
        })

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
        this._address_copy = this.container.querySelector('.bnomics-address-copy')

        this._progress_bar = this.container.querySelector('.bnomics-progress-bar')
        this._time_left = this.container.querySelector('.bnomics-time-left')

        this._try_again = this.container.querySelector('#bnomics-try-again')
        this._qr_code = this.container.querySelector('#bnomics-qr-code')
        
        // Hide Panels
        this._order_expired_wrapper.style.display = 'none'
        this._order_panel.style.display = 'none'
        this._copy_amount_text.style.display = 'none'
        this._copy_address_text.style.display = 'none'

        // Click Bindings

        //Copy bitcoin address to clipboard
        this._address_input.addEventListener('click', () => this.copy_to_clipboard("bnomics-address-copy"))
        
        //Copy bitcoin amount to clipboard
        this._amount_input.addEventListener('click', () => this.copy_to_clipboard("bnomics-amount-copy"))

        //Reload the page if user clicks try again after the order expires
        this._try_again.addEventListener('click', () => location.reload())
    }

    generate_qr() {
        const data = `${this.crypto.uri}:${this.crypto_address}?amount=${this.crypto_amount/1.0e8}`
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
            this._progress_bar.style.width = `${this.progress.percent}%`
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
        var ws = new ReconnectingWebSocket("wss://" + (this.crypto.code == 'btc' ? 'www' : this.crypto.code) + ".blockonomics.co/payment/" + this.crypto_address);
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
        if (divid == "bnomics-address-copy") {
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
        window.location.href = this.finish_order_url
    }
}

window.Blockonomics = Blockonomics
