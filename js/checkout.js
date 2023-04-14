'use strict';
class Blockonomics {
    constructor({ checkout_id = 'blockonomics_checkout' } = {}) {
        // User Params
        this.checkout_id = checkout_id;

        // Initialise
        this.init();
    }

    init() {
        this.container = document.getElementById(this.checkout_id);
        if (!this.container) {
            throw Error(
                `Blockonomics Initialisation Error: Container #${this.checkout_id} was not found!`
            );
        }

        // Load data attributes
        // This assumes a constant/var `blockonomics_data` is defined before the script is called.
        try {
            this.data = JSON.parse(blockonomics_data);
        } catch (e) {
            if (e.toString().includes('ReferenceError')) {
                throw Error(
                    `Blockonomics Initialisation Error: Data Object was not found in Window. Please set blockonomics_data variable.`
                );
            }
            throw Error(
                `Blockonomics Initialisation Error: Data Object is not a valid JSON.`
            );
        }

        this.create_bindings();

        this.reset_progress();
        this._spinner_wrapper.style.display = 'none';
        this._order_panel.style.display = 'block';
        this.generate_qr();
        this.connect_to_ws();

        // Hide Display Error
        this._display_error_wrapper.style.display = 'none';
    }

    create_bindings() {
        this._spinner_wrapper = this.container.querySelector(
            '.bnomics-spinner-wrapper'
        );

        this._order_panel = this.container.querySelector(
            '.bnomics-order-panel'
        );

        this._amount_text = this.container.querySelector(
            '.bnomics-amount-text'
        );
        this._copy_amount_text = this.container.querySelector(
            '.bnomics-copy-amount-text'
        );
        this._amount_input = this.container.querySelector(
            '#bnomics-amount-input'
        );
        this._amount_copy = this.container.querySelector(
            '#bnomics-amount-copy'
        );

        this._address_text = this.container.querySelector(
            '.bnomics-address-text'
        );
        this._copy_address_text = this.container.querySelector(
            '.bnomics-copy-address-text'
        );
        this._address_input = this.container.querySelector(
            '#bnomics-address-input'
        );
        this._address_copy = this.container.querySelector(
            '#bnomics-address-copy'
        );

        this._time_left = this.container.querySelector('.bnomics-time-left');
        this._crypto_rate = this.container.querySelector(
            '#bnomics-crypto-rate'
        );

        this._refresh = this.container.querySelector('#bnomics-refresh');
        this._show_qr = this.container.querySelector('#bnomics-show-qr');
        this._qr_code_container =
            this.container.querySelector('.bnomics-qr-code');
        this._qr_code = this.container.querySelector('#bnomics-qr-code');
        this._qr_code_links =
            this.container.querySelectorAll('a.bnomics-qr-link');

        this._display_error_wrapper = this.container.querySelector(
            '.bnomics-display-error'
        );

        // Click Bindings

        // Copy bitcoin address to clipboard
        this._address_copy.addEventListener('click', (e) => {
            e.preventDefault();
            this.copy_to_clipboard('bnomics-address-input');
        });

        // Copy bitcoin amount to clipboard
        this._amount_copy.addEventListener('click', (e) => {
            e.preventDefault();
            this.copy_to_clipboard('bnomics-amount-input');
        });

        // QR Handler
        this._show_qr.addEventListener('click', (e) => {
            e.preventDefault();
            this.toggle_qr();
        });

        this._refresh.addEventListener('click', (e) => {
            e.preventDefault();
            this.refresh_order();
        });

        this.data.time_period = Number(this.data.time_period);
    }

    reset_progress() {
        this.progress = {
            total_time: this.data.time_period * 60,
            interval: null,
            clock: this.data.time_period * 60,
            percent: 100,
        };
        // Set the start time straight away
        this.progress.clock += 1;
        this.tick();
        this.progress.interval = setInterval(() => this.tick(), 1000);
    }

    toggle_qr() {
        if (getComputedStyle(this._qr_code_container).display == 'none') {
            this._qr_code_container.style.display = 'block';
        } else {
            this._qr_code_container.style.display = 'none';
        }
    }

    generate_qr() {
        const data = `${this.data.payment_uri}`;
        this._qr = new QRious({
            element: this._qr_code,
            value: data,
            size: 160,
        });
    }

    tick() {
        this.progress.clock = this.progress.clock - 1;

        this.progress.percent = Math.floor(
            (this.progress.clock * 100) / this.progress.total_time
        );
        if (this.progress.clock < 0) {
            this.progress.clock = 0;
            //Order expired
            this.refresh_order();
        } else {
            this._time_left.innerHTML = `${String(
                Math.floor(this.progress.clock / 60)
            ).padStart(2, '0')}:${String(this.progress.clock % 60).padStart(
                2,
                '0'
            )} min`;
        }
    }

    connect_to_ws() {
        //Connect and Listen on websocket for payment notification
        var ws = new ReconnectingWebSocket(
            'wss://' +
                (this.data.crypto.code == 'btc'
                    ? 'www'
                    : this.data.crypto.code) +
                '.blockonomics.co/payment/' +
                this.data.crypto_address
        );
        let $this = this;

        ws.onmessage = function (evt) {
            ws.close();

            setTimeout(
                function () {
                    //Redirect to order confirmation page if message from socket
                    $this.redirect_to_finish_order();
                    //Wait for 2 seconds for order status to update on server
                },
                2000,
                1
            );
        };
    }

    select_text(divid) {
        var selection = window.getSelection();
        var div = document.createRange();

        div.setStartBefore(document.getElementById(divid));
        div.setEndAfter(document.getElementById(divid));
        selection.removeAllRanges();
        selection.addRange(div);
    }

    copy_to_clipboard(divid) {
        var textarea = document.createElement('textarea');
        textarea.id = 'temp_element';
        textarea.style.height = 0;
        document.body.appendChild(textarea);
        textarea.value = document.getElementById(divid).value;

        var selector = document.querySelector('#temp_element');
        selector.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);

        this.select_text(divid);

        let $this = this;
        if (divid == 'bnomics-address-input') {
            this._address_text.style.display = 'none';
            this._copy_address_text.style.display = 'block';
            setTimeout(function () {
                $this._address_text.style.display = 'block';
                $this._copy_address_text.style.display = 'none';
                //Close copy to clipboard message after 2 sec
            }, 2000);
        } else {
            this._amount_text.style.display = 'none';
            this._copy_amount_text.style.display = 'block';

            setTimeout(function () {
                $this._amount_text.style.display = 'block';
                $this._copy_amount_text.style.display = 'none';
                //Close copy to clipboard message after 2 sec
            }, 2000);
        }
    }

    redirect_to_finish_order() {
        window.location.href = this.data.finish_order_url;
    }

    _create_loading_rectangle(ref, target) {
        let style = window.getComputedStyle(ref);

        let target_position = target.getBoundingClientRect();
        let ref_position = ref.getBoundingClientRect();

        let position_x = ref_position.x - target_position.x;
        let position_y = ref_position.y - target_position.y;

        let ele = document.createElement('div');
        ele.classList.add('bnomics-copy-container-animation-rectangle');

        let border = {
            left: parseFloat(style.borderLeftWidth.replace('px', '')),
            right: parseFloat(style.borderRightWidth.replace('px', '')),
            top: parseFloat(style.borderTopWidth.replace('px', '')),
            bottom: parseFloat(style.borderBottomWidth.replace('px', '')),
        };

        // Initial Parameters
        ele.style.width = 0;
        ele.style.height =
            ref_position.height - border.top - border.bottom + 'px';
        ele.style.top = position_y + border.top + 'px';
        ele.style.left = position_x + border.left + 'px';
        ele.style.borderTopLeftRadius = style.borderTopLeftRadius;
        ele.style.borderTopRightRadius = style.borderTopLeRightdius;
        ele.style.borderBottomLeftRadius = style.borderBottomLeftRadius;
        ele.style.borderBottomRightRadius = style.borderBottomRightRadius;
        ele.style.backgroundColor = window.getComputedStyle(
            document.body
        ).backgroundColor;

        target.appendChild(ele);
        setTimeout(
            () =>
                (ele.style.width =
                    ref_position.width - border.left - border.right + 'px'),
            10
        );

        return ele;
    }

    _remove_loading_rectangle(ele) {
        let style = window.getComputedStyle(ele);
        let width = parseFloat(style.width.replace('px', ''));
        let left = parseFloat(style.left.replace('px', ''));

        setTimeout(() => {
            (ele.style.left = width + left + 'px'), (ele.style.width = '0px');
        }, 10);
        setTimeout(() => ele.remove(), 300);
    }

    _animate_price_update() {
        let parent_container = this._crypto_rate.closest('th');
        let container = this._crypto_rate.closest(
            '.bnomics-crypto-price-timer'
        );

        parent_container.setAttribute(
            'data-bnomics-overflow',
            parent_container.style.overflow
        );
        parent_container.style.overflow = 'hidden';

        container.style.position = 'relative';
        container.style.top = 0;
        setTimeout(
            () => (container.style.top = parent_container.clientHeight + 'px'),
            10
        );
    }

    _deanimate_price_update() {
        let parent_container = this._crypto_rate.closest('th');
        let container = this._crypto_rate.closest(
            '.bnomics-crypto-price-timer'
        );

        container.style.top = '0';

        setTimeout(() => {
            container.style.top = null;
            container.style.position = null;
            parent_container.style.overflow = parent_container.getAttribute(
                'data-bnomics-overflow'
            );
            parent_container.removeAttribute('data-bnomics-overflow');
        }, 300);
    }

    _set_refresh_loading(loading = false) {
        if (loading) {
            this._refresh.classList.add('spin');
            this._refresh.setAttribute('disabled', 'disabled');
            this._active_loading_rect = this._create_loading_rectangle(
                this._amount_input,
                this.container.querySelector('#bnomics-amount-copy-container')
            );
            this._animate_price_update();
        } else {
            this._refresh.classList.remove('spin');
            this._refresh.removeAttribute('disabled');
            this._remove_loading_rectangle(this._active_loading_rect);
            this._deanimate_price_update();
        }
    }

    refresh_order() {
        this._set_refresh_loading(true);

        // Stop Progress Counter
        clearInterval(this.progress.interval);

        fetch(this.data.get_order_amount_url, { method: 'GET' })
            .then((res) => {
                if (!res.ok) {
                    location.reload();
                } else {
                    return res.json();
                }
            })
            .then((res) => {
                this._update_order_params(res);
                this._set_refresh_loading(false);
            })
            .catch((err) => {
                // Enable the button anyways so that user can retry
                this._set_refresh_loading(false);

                // Log to Console for Debuggin by Admin as it's probably a CORS, Network or JSON Decode Issue
                console.log('Blockonomics AJAX Error: ', err);

                // Fallback
                location.reload();
            });
    }

    _update_order_params(data) {
        // Updates the Dynamic Parts of Page
        this._amount_input.value = data.order_amount;
        this._crypto_rate.innerHTML = data.crypto_rate_str;

        // Update QR Code
        this.data.payment_uri = data.payment_uri;
        this.generate_qr();
        this._qr_code_links.forEach((ele) =>
            ele.setAttribute('href', data.payment_uri)
        );

        this.reset_progress();
    }
}

// Automatically trigger only after DOM is loaded
new Blockonomics();
