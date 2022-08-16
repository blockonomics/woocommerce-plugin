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
                throw Error(`Blockonomics Initialisation Error: Data Object was not found in Window. Please set window.blockonomics_data.`)
            }
            throw Error(`Blockonomics Initialisation Error: Data Object is not a valid JSON.`)
        }

        this.create_bindings()
        
        this.progress.interval = setInterval(() => this.tick(), 1000)
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
        this._address_copy = this.container.querySelector('.bnomics-address-copy')

        this._progress_bar = this.container.querySelector('.bnomics-progress-bar')
        this._time_left = this.container.querySelector('.bnomics-time-left')

        this._try_again = this.container.querySelector('#bnomics-try-again')
        this._qr_code = this.container.querySelector('#bnomics-qr-code')

        this._display_error_wrapper = this.container.querySelector(".bnomics-display-error")

        // Click Bindings

        //Copy bitcoin address to clipboard
        this._address_input.addEventListener('click', () => this.copy_to_clipboard("bnomics-address-copy"))
        
        //Copy bitcoin amount to clipboard
        this._amount_input.addEventListener('click', () => this.copy_to_clipboard("bnomics-amount-copy"))

        //Reload the page if user clicks try again after the order expires
        this._try_again.addEventListener('click', () => location.reload())

        this.data.time_period = Number(this.data.time_period)

        this.progress = {
            total_time: this.data.time_period * 60,
            interval: null,
            clock: this.data.time_period * 60,
            percent: 100
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
        window.location.href = this.data.finish_order_url
    }
}

// Automatically trigger only after DOM is loaded
addEventListener('DOMContentLoaded', () => new Blockonomics());
