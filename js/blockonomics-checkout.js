'use strict';
class Blockonomics {
    constructor() {
        this.container = document.getElementById('blockonomics_checkout');
        const dataset = this.container ? this.container.dataset : {};
        if (!dataset.cryptoAddress || !dataset.cryptoCode) {
            throw Error('Blockonomics Initialisation Error: Required data attributes are missing from container element.');
        }
        this.data = {
            time_left: Number(dataset.timeLeft) || 0,
            crypto: { code: dataset.cryptoCode },
            crypto_address: dataset.cryptoAddress,
            finish_order_url: dataset.finishOrderUrl,
            get_order_amount_url: dataset.getOrderAmountUrl
        };

        const find = (selector) => this.container.querySelector(selector);
        this._amount = find('#bnomics-amount');
        this._amount_box = find('#bnomics-amount-box');
        this._time_left = find('.bnomics-time-left');
        this._wallet_link = find('a.bnomics-wallet-btn');

        this.container.querySelectorAll('.bnomics-copy').forEach((button) =>
            button.addEventListener('click', () => this.copy(button))
        );

        this.reset_progress();
        find('.bnomics-spinner-wrapper').style.display = 'none';
        find('.bnomics-display-error').style.display = 'none';
        find('.bnomics-pay').style.display = 'block';
        new QRious({ element: find('#bnomics-qr-code'), value: this.data.crypto_address, size: 160 });
        this.connect_to_ws();
    }

    copy(button) {
        const text = document.getElementById(button.dataset.copy).textContent;
        const box = button.closest('.bnomics-box');
        const done = () => {
            box.classList.add('is-copied');
            setTimeout(() => box.classList.remove('is-copied'), 1600);
        };
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(done);
            return;
        }
        const textarea = document.createElement('textarea');
        textarea.value = text;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        textarea.remove();
        done();
    }

    reset_progress() {
        this.clock = this.data.time_left + 1;
        this.tick();
        this.interval = setInterval(() => this.tick(), 1000);
    }

    tick() {
        this.clock = this.clock - 1;
        if (this.clock < 0) {
            this.clock = 0;
            this.refresh_order();
            return;
        }
        const pad = (n) => String(n).padStart(2, '0');
        this._time_left.textContent = `${pad(Math.floor(this.clock / 60))}:${pad(this.clock % 60)}`;
    }

    connect_to_ws() {
        const host = this.data.crypto.code == 'btc' ? 'www' : this.data.crypto.code;
        const ws = new ReconnectingWebSocket(`wss://${host}.blockonomics.co/payment/${this.data.crypto_address}`);
        ws.onmessage = () => {
            ws.close();
            clearInterval(this.interval);
            this.show_detected();
            // Wait for 2 seconds for order status to update on server
            setTimeout(() => (window.location.href = this.data.finish_order_url), 2000);
        };
    }

    show_detected() {
        const pay = this.container.querySelector('.bnomics-pay');
        const detected = this.container.querySelector('.bnomics-detected');
        detected.style.minHeight = pay.offsetHeight + 'px';
        pay.style.display = 'none';
        detected.style.display = 'flex';
    }

    _wipe_in() {
        this._wipe = document.createElement('span');
        this._wipe.className = 'bnomics-wipe';
        this._amount_box.appendChild(this._wipe);
        setTimeout(() => (this._wipe.style.width = '100%'), 10);
    }

    _wipe_out() {
        const wipe = this._wipe;
        wipe.style.left = '100%';
        wipe.style.width = '0';
        setTimeout(() => wipe.remove(), 300);
    }

    refresh_order() {
        clearInterval(this.interval);
        this._wipe_in();
        fetch(this.data.get_order_amount_url, { method: 'GET' })
            .then((res) => res.json())
            .then((res) => {
                if (res.error) {
                    throw Error(res.error);
                }
                this._amount.textContent = res.order_amount;
                this._wallet_link.setAttribute('href', res.payment_uri);
                this.data.time_left = res.time_left;
                this._wipe_out();
                this.reset_progress();
            })
            .catch((err) => {
                console.log('Blockonomics AJAX Error: ', err);
                location.reload();
            });
    }
}

new Blockonomics();
