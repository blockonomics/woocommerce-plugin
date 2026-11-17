'use strict';
// The payment page is complete without this script: QR code, amount, address and wallet
// link are rendered on the server. This adds the copy buttons, the countdown with its
// price refresh, and live payment detection.
(function () {
    const root = document.getElementById('blockonomics_checkout');
    if (!root || !root.dataset.cryptoAddress || !root.dataset.cryptoCode) {
        return;
    }
    const data = root.dataset;
    const find = (selector) => root.querySelector(selector);
    const amount = find('#bnomics-amount');
    const amountBox = find('#bnomics-amount-box');
    const walletLink = find('a.bnomics-wallet-btn');
    const qrTile = find('.bnomics-qr-tile');
    const rate = find('#bnomics-crypto-rate');
    const quote = find('.bnomics-quote');
    const refreshButton = find('.bnomics-refresh');
    const timeLeft = find('.bnomics-time-left');
    const announcer = find('.bnomics-sr[role="status"]');

    // Screen readers skip a message identical to the previous one, so a repeat gets a no-break space
    let lastMessage = '';
    const distinct = (message) => {
        if (message === lastMessage) {
            message += '\u00A0';
        }
        lastMessage = message;
        return message;
    };
    const announce = (message) => {
        announcer.textContent = '';
        requestAnimationFrame(() => (announcer.textContent = distinct(message)));
    };

    const timers = new WeakMap();

    // For pages not served over https, where navigator.clipboard does not exist
    function legacyCopy(text) {
        const field = Object.assign(document.createElement('textarea'), { value: text });
        field.setAttribute('readonly', '');
        field.style.cssText = 'position:fixed;top:0;opacity:0';
        document.body.append(field);
        field.select();
        try {
            return document.execCommand('copy');
        } finally {
            field.remove();
        }
    }

    async function copy(button) {
        const text = document.getElementById(button.dataset.copy).textContent.trim();
        let ok = true;
        try {
            await navigator.clipboard.writeText(text);
        } catch {
            ok = legacyCopy(text);
        }
        report(button, ok);
    }

    function report(button, ok) {
        const box = button.closest('.bnomics-box');
        const tip = box.querySelector('.bnomics-copied [role="status"]');
        clearTimeout(timers.get(button));
        tip.textContent = '';
        box.classList.add('is-reporting');
        box.classList.toggle('is-copied', ok);
        requestAnimationFrame(() => (tip.textContent = distinct(ok ? data.msgCopied : data.msgCopyFailed)));
        timers.set(button, setTimeout(() => {
            box.classList.remove('is-reporting');
            tip.textContent = '';
        }, ok ? 1600 : 2600));
    }

    root.querySelectorAll('.bnomics-copy').forEach((button) =>
        button.addEventListener('click', () => copy(button))
    );

    // The countdown is measured against a deadline: timers are paused while the tab is hidden (customer in the wallet app)
    let deadline = 0;
    let interval = null;

    function startClock(seconds) {
        deadline = Date.now() + seconds * 1000;
        clearInterval(interval);
        interval = setInterval(tick, 1000);
        tick();
    }

    function tick() {
        const left = Math.max(0, Math.round((deadline - Date.now()) / 1000));
        const pad = (n) => String(n).padStart(2, '0');
        timeLeft.textContent = `${pad(Math.floor(left / 60))}:${pad(left % 60)}`;
        if (left === 0) {
            refreshOrder(false);
        }
    }

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden && interval) {
            tick();
        }
    });

    let refreshing = false;

    // The colour behind the card. While a new price loads, the amount box is blanked in this colour.
    function backdropColor() {
        for (let element = root.parentElement; element; element = element.parentElement) {
            const color = getComputedStyle(element).backgroundColor;
            if (color !== 'rgba(0, 0, 0, 0)' && color !== 'transparent') {
                return color;
            }
        }
        return 'Canvas';
    }

    // Runs when the held price runs out, or with newPrice when the customer presses the refresh button
    function refreshOrder(newPrice) {
        if (refreshing) {
            return;
        }
        refreshing = true;
        clearInterval(interval);
        interval = null;
        quote.classList.add('is-loading');
        const wipe = document.createElement('span');
        wipe.className = 'bnomics-wipe';
        wipe.style.backgroundColor = backdropColor();
        amountBox.appendChild(wipe);
        setTimeout(() => (wipe.style.width = '100%'), 10);
        const url = new URL(data.getOrderAmountUrl, location.href);
        if (newPrice) {
            url.searchParams.set('refresh', '1');
        }
        // The sweep over the amount takes 300ms. Let it finish even when the answer arrives sooner,
        // or the button seems to do nothing.
        const sweepShown = new Promise((resolve) => setTimeout(resolve, 400));
        fetch(url, { method: 'GET' })
            .then((res) => res.json())
            .then((res) => sweepShown.then(() => res))
            .then((res) => {
                if (res.error) {
                    throw Error(res.error);
                }
                amount.textContent = res.order_amount;
                rate.textContent = res.crypto_rate_str;
                walletLink.setAttribute('href', res.payment_uri);
                qrTile.innerHTML = res.qrcode_svg;
                wipe.style.left = '100%';
                wipe.style.width = '0';
                setTimeout(() => wipe.remove(), 300);
                quote.classList.remove('is-loading');
                refreshing = false;
                announce(`${data.msgUpdated} ${res.order_amount} ${data.cryptoCode.toUpperCase()}`);
                startClock(res.time_left);
            })
            .catch((err) => {
                console.log('Blockonomics AJAX Error: ', err);
                location.reload();
            });
    }

    refreshButton.addEventListener('click', () => refreshOrder(true));

    function showDetected() {
        const pay = find('.bnomics-pay');
        const detected = find('.bnomics-detected');
        const hadFocus = pay.contains(document.activeElement);
        detected.style.minHeight = pay.offsetHeight + 'px';
        root.classList.add('is-detected');
        if (hadFocus) {
            detected.focus();
        }
        announce(find('.bnomics-detected-text').textContent);
    }

    const host = data.cryptoCode == 'btc' ? 'www' : data.cryptoCode;
    const ws = new ReconnectingWebSocket(`wss://${host}.blockonomics.co/payment/${data.cryptoAddress}`);
    ws.onmessage = () => {
        ws.close();
        clearInterval(interval);
        interval = null;
        showDetected();
        // Wait for 2 seconds for order status to update on server
        setTimeout(() => (window.location.href = data.finishOrderUrl), 2000);
    };

    root.querySelectorAll('[data-needs-js]').forEach((element) => (element.hidden = false));
    root.querySelectorAll('[data-without-js]').forEach((element) => (element.hidden = true));
    startClock(Number(data.timeLeft) || 0);
})();
