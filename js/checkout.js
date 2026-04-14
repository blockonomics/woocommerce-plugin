'use strict';

(function (global) {

  var BASE_URL = 'https://whmcs.testblockonomics.com/';

  // ---------------------------------------------------------------------------
  // Entry point
  // ---------------------------------------------------------------------------

  function show(opts) {
    var options = {
      msg_area:          opts.msg_area,
      store_uid:         opts.store_uid          || '',
      platform_order_id: opts.platform_order_id  || '',
      amount:            opts.amount             || 0,
      currency:          opts.currency           || 'USD',
      cryptos:           opts.cryptos            || [],
      testnet:           opts.testnet            || false,
      timer:             opts.timer              || 3600,
      redirect_url:      opts.redirect_url       || '',
    };

    var container = document.getElementById(options.msg_area);
    if (!container) {
      console.error('BlockonomicsCheckout: container #' + options.msg_area + ' not found');
      return;
    }

    container.classList.add('bck-widget');

    // Runtime state
    var state = {
      cryptos:        [],     // payment options returned by API
      selected:       null,   // currently-shown crypto object
      ws:             null,   // reconnecting WebSocket wrapper
      timer_interval: null,
      clock:          0,
    };

    // -------------------------------------------------------------------------
    // DOM helpers
    // -------------------------------------------------------------------------

    function render(html) {
      container.innerHTML = html;
    }

    function escHtml(str) {
      return String(str)
        .replace(/&/g,  '&amp;')
        .replace(/</g,  '&lt;')
        .replace(/>/g,  '&gt;')
        .replace(/"/g,  '&quot;');
    }

    // -------------------------------------------------------------------------
    // State panels
    // -------------------------------------------------------------------------

    function showLoading() {
      render('<div class="blockonomics_message"><p>Loading payment details\u2026</p></div>');
    }

    function showError(msg) {
      render('<div class="blockonomics_error"><p>' + escHtml(msg) + '</p></div>');
    }

    function showSuccess() {
      cleanup();
      var orderLine = options.platform_order_id
        ? '<p>Order: <strong>' + escHtml(options.platform_order_id) + '</strong></p>'
        : '';
      render(
        '<div class="blockonomics_message">' +
          '<h3>Payment received!</h3>' +
          orderLine +
        '</div>'
      );
      if (options.redirect_url) {
        setTimeout(function () { window.location.href = options.redirect_url; }, 2000);
      }
    }

    // -------------------------------------------------------------------------
    // API
    // -------------------------------------------------------------------------

    function checkoutUrl() {
      return BASE_URL + '/api/checkout/' + encodeURIComponent(options.store_uid);
    }

    function buildPayload() {
      return JSON.stringify({
        platform_order_id: options.platform_order_id,
        amount:            options.amount,
        currency:          options.currency,
        cryptos:           options.cryptos,
        testnet:           options.testnet,
        timer:             options.timer,
      });
    }

    function fetchPayment() {
      showLoading();
      fetch(checkoutUrl(), {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    buildPayload(),
      })
        .then(function (res) {
          if (!res.ok) throw new Error('HTTP ' + res.status);
          return res.json();
        })
        .then(function (data) {
          if (!data.cryptos || !data.cryptos.length) {
            showError('No payment methods available for this store.');
            return;
          }
          state.cryptos = data.cryptos;
          selectCrypto(data.cryptos[0]);
        })
        .catch(function (err) {
          console.error('BlockonomicsCheckout:', err);
          showError('Could not load payment details. Please refresh and try again.');
        });
    }

    function refreshRate() {
      var btn = container.querySelector('#bck-refresh');
      if (btn) btn.disabled = true;
      stopTimer();

      fetch(checkoutUrl(), {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    buildPayload(),
      })
        .then(function (res) { return res.json(); })
        .then(function (data) {
          if (!data.cryptos || !data.cryptos.length) return;
          state.cryptos = data.cryptos;
          var currentCode = state.selected && state.selected.code;
          var match = null;
          for (var i = 0; i < data.cryptos.length; i++) {
            if (data.cryptos[i].code === currentCode) { match = data.cryptos[i]; break; }
          }
          selectCrypto(match || data.cryptos[0]);
        })
        .catch(function () {
          if (btn) btn.disabled = false;
          startTimer(options.timer);
        });
    }

    // -------------------------------------------------------------------------
    // Crypto selection & payment panel
    // -------------------------------------------------------------------------

    function selectCrypto(crypto) {
      stopTimer();
      closeWs();
      state.selected = crypto;
      renderPaymentPanel();
      startTimer(crypto.expires_in || options.timer);
      connectWs(crypto);
    }

    function renderPaymentPanel() {
      var crypto = state.selected;

      // Crypto tabs (only if multiple options)
      var tabsHtml = '';
      if (state.cryptos.length > 1) {
        tabsHtml = '<div class="bck-crypto-tabs">';
        for (var i = 0; i < state.cryptos.length; i++) {
          var c = state.cryptos[i];
          var active = c.code === crypto.code ? ' bck-tab-active' : '';
          tabsHtml += '<button class="bck-tab' + active + '" data-crypto="' + escHtml(c.code) + '">'
            + escHtml(c.code.toUpperCase()) + '</button>';
        }
        tabsHtml += '</div>';
      }

      var qrHtml = '<div class="bck-qr-wrap"><canvas id="bck-qr-canvas"></canvas></div>';

      var addrHtml =
        '<div class="bck-field">' +
          '<label>Send ' + escHtml(crypto.code.toUpperCase()) + ' to this address:</label>' +
          '<div class="bck-copy-wrap">' +
            '<input type="text" id="bck-address-input" readonly value="' + escHtml(crypto.address) + '">' +
            '<button class="bck-copy-btn" data-target="bck-address-input">Copy</button>' +
          '</div>' +
        '</div>';

      var amtHtml =
        '<div class="bck-field">' +
          '<label>Amount of ' + escHtml(crypto.code.toUpperCase()) + ' to send:</label>' +
          '<div class="bck-copy-wrap">' +
            '<input type="text" id="bck-amount-input" readonly value="' + escHtml(crypto.amount) + '">' +
            '<button class="bck-copy-btn" data-target="bck-amount-input">Copy</button>' +
          '</div>' +
        '</div>';

      var ratePrefix = crypto.rate_str
        ? '1 ' + escHtml(crypto.code.toUpperCase()) + ' = ' + escHtml(crypto.rate_str)
            + ' ' + escHtml(options.currency) + ', expires in '
        : 'Expires in ';

      var footerHtml =
        '<div class="bck-footer">' +
          '<small>' + ratePrefix + '<span class="time_remaining">--:--</span></small>' +
          '<button id="bck-refresh" title="Refresh rate">&#8635;</button>' +
        '</div>';

      var closeHtml =
        '<div class="blockonomics_close"><a href="#" id="bck-close">Close</a></div>';

      render(
        '<div class="blockonomics_message">' +
          tabsHtml + qrHtml + addrHtml + amtHtml + footerHtml + closeHtml +
        '</div>'
      );

      generateQr(crypto.payment_uri || crypto.address);
      bindPanelEvents();
    }

    // -------------------------------------------------------------------------
    // QR code
    // -------------------------------------------------------------------------

    function generateQr(data) {
      if (!data) return;
      function doGenerate() {
        var canvas = document.getElementById('bck-qr-canvas');
        if (canvas && window.QRious) {
          new window.QRious({ element: canvas, value: data, size: 160 });
        }
      }
      if (window.QRious) {
        doGenerate();
      } else {
        var s = document.createElement('script');
        s.src = BASE_URL + '/js/vendors/qrious.min.js';
        s.onload = doGenerate;
        document.head.appendChild(s);
      }
    }

    // -------------------------------------------------------------------------
    // Panel event bindings
    // -------------------------------------------------------------------------

    function bindPanelEvents() {
      // Copy buttons
      var copyBtns = container.querySelectorAll('.bck-copy-btn');
      for (var i = 0; i < copyBtns.length; i++) {
        (function (btn) {
          btn.addEventListener('click', function () {
            var targetId = btn.getAttribute('data-target');
            var input = document.getElementById(targetId);
            if (input) copyText(input.value, btn);
          });
        })(copyBtns[i]);
      }

      // Refresh rate
      var refreshBtn = container.querySelector('#bck-refresh');
      if (refreshBtn) {
        refreshBtn.addEventListener('click', function (e) {
          e.preventDefault();
          refreshRate();
        });
      }

      // Crypto tab switching
      var tabs = container.querySelectorAll('.bck-tab');
      for (var j = 0; j < tabs.length; j++) {
        (function (tab) {
          tab.addEventListener('click', function () {
            var code = tab.getAttribute('data-crypto');
            for (var k = 0; k < state.cryptos.length; k++) {
              if (state.cryptos[k].code === code) {
                selectCrypto(state.cryptos[k]);
                break;
              }
            }
          });
        })(tabs[j]);
      }

      // Close link
      var closeBtn = container.querySelector('#bck-close');
      if (closeBtn) {
        closeBtn.addEventListener('click', function (e) {
          e.preventDefault();
          cleanup();
          container.innerHTML = '';
        });
      }
    }

    // -------------------------------------------------------------------------
    // Clipboard
    // -------------------------------------------------------------------------

    function copyText(text, btn) {
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(function () { flashBtn(btn, 'Copied!'); });
      } else {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.style.cssText = 'position:fixed;opacity:0;top:0;left:0;';
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); } catch (e) { /* ignore */ }
        document.body.removeChild(ta);
        flashBtn(btn, 'Copied!');
      }
    }

    function flashBtn(btn, msg) {
      var orig = btn.textContent;
      btn.textContent = msg;
      setTimeout(function () { btn.textContent = orig; }, 1500);
    }

    // -------------------------------------------------------------------------
    // Countdown timer
    // -------------------------------------------------------------------------

    function startTimer(seconds) {
      state.clock = seconds;
      tickTimer();
      state.timer_interval = setInterval(tickTimer, 1000);
    }

    function stopTimer() {
      if (state.timer_interval) {
        clearInterval(state.timer_interval);
        state.timer_interval = null;
      }
    }

    function tickTimer() {
      var el = container.querySelector('.time_remaining');
      if (!el) return;
      if (state.clock <= 0) {
        stopTimer();
        refreshRate();
        return;
      }
      var m = Math.floor(state.clock / 60);
      var s = state.clock % 60;
      el.textContent = pad2(m) + ':' + pad2(s);
      state.clock--;
    }

    function pad2(n) { return n < 10 ? '0' + n : '' + n; }

    // -------------------------------------------------------------------------
    // WebSocket payment monitoring
    // -------------------------------------------------------------------------

    function connectWs(crypto) {
      if (!crypto.address) return;
      var wsUrl = BASE_URL.replace(/^http/, 'ws') + '/payment/' + encodeURIComponent(crypto.address);

      var ws = { conn: null, dead: false, retryTimer: null };

      function connect() {
        if (ws.dead) return;
        ws.conn = new WebSocket(wsUrl);

        ws.conn.onmessage = function () {
          ws.dead = true;
          ws.conn.close();
          showSuccess();
        };

        ws.conn.onclose = function () {
          if (!ws.dead) ws.retryTimer = setTimeout(connect, 5000);
        };

        ws.conn.onerror = function () { ws.conn.close(); };
      }

      ws.close_all = function () {
        ws.dead = true;
        clearTimeout(ws.retryTimer);
        if (ws.conn) ws.conn.close();
      };

      state.ws = ws;
      connect();
    }

    function closeWs() {
      if (state.ws) { state.ws.close_all(); state.ws = null; }
    }

    // -------------------------------------------------------------------------
    // Cleanup & kick off
    // -------------------------------------------------------------------------

    function cleanup() {
      stopTimer();
      closeWs();
    }

    fetchPayment();
  }

  // ---------------------------------------------------------------------------
  // Public API
  // ---------------------------------------------------------------------------
  global.BlockonomicsCheckout = { show: show };

}(window));
