document.addEventListener('DOMContentLoaded', function() {
    // Initialize BlockonomicsAdmin class
    const admin = new BlockonomicsAdmin();
    admin.init();
});

class BlockonomicsAdmin {
    constructor() {
        // Store DOM elements
        this.elements = {
            form: document.getElementById('mainform'),
            apiKey: document.querySelector('input[name="woocommerce_blockonomics_api_key"]'),
            testSetup: document.getElementById('test-setup-btn'),
            spinner: document.querySelector('.test-spinner'),
            notifications: {
                apiKey: document.getElementById('api-key-notification-box'),
                testSetup: document.getElementById('test-setup-notification-box')
            },
            pluginEnabled: document.getElementById('woocommerce_blockonomics_enabled')
        };

        // State management
        this.state = {
            apiKeyChanged: false,
            otherSettingsChanged: false
        };

        // Configuration
        this.config = {
            baseUrl: blockonomics_params.ajaxurl,
        };

        // Initialize crypto DOM elements
        this.cryptoElements = {
            success: document.querySelector('.notice-success'),
            successText: document.querySelector('.notice-success .successText'),
            error: document.querySelector('.notice-error'),
            errorText: document.querySelector('.notice-error .errorText')
        };
    }

    init() {
        this.attachEventListeners();
    }


    attachEventListeners() {
        // Form related listeners
        this.elements.form.addEventListener('input', (event) => {
            if (event.target !== this.elements.apiKey) {
                this.state.otherSettingsChanged = true;
            }
        });

        this.elements.apiKey.addEventListener('change', () => {
            this.state.apiKeyChanged = true;
        });

        this.elements.form.addEventListener('submit', (e) => this.handleFormSubmit(e));

        // Test setup button listener
        if (this.elements.testSetup) {
            this.elements.testSetup.addEventListener('click', (e) => this.handleTestSetup(e));
        }

        // Prevent accidental navigation
        window.addEventListener('beforeunload', (event) => {
            event.stopImmediatePropagation();
        });
    }

    async handleTestSetup(event) {
        event.preventDefault();

        if (!this.validateApiKey()) return;
        if (this.shouldSkipTestSetup()) return;

        this.updateUIBeforeTest();

        try {
            if (this.state.apiKeyChanged) {
                await this.saveApiKey();
            }
            const cryptoElements = this.cryptoElements;
            cryptoElements.success.style.display = 'none';
            cryptoElements.successText.innerHTML = '';
            cryptoElements.error.style.display = 'none';
            cryptoElements.errorText.innerHTML = '';
            const result = await this.performTestSetup();
            this.handleTestSetupResponse(result);
        } catch (error) {
            console.error('Test setup failed:', error);
        } finally {
            this.updateUIAfterTest();
        }
    }

    validateApiKey() {
        if (this.elements.apiKey.value.trim() === '') {
            this.showApiKeyError();
            return false;
        }
        return true;
    }

    shouldSkipTestSetup() {
        if (this.state.otherSettingsChanged && !this.state.apiKeyChanged) {
            this.elements.notifications.testSetup.style.display = 'block';
            return true;
        }
        return false;
    }

    updateUIBeforeTest() {
        this.elements.notifications.apiKey.style.display = 'none';
        this.elements.notifications.testSetup.style.display = 'none';
        this.elements.spinner.style.display = 'block';
        this.elements.testSetup.disabled = true;
    }

    updateUIAfterTest() {
        this.elements.spinner.style.display = 'none';
        this.elements.testSetup.disabled = false;
    }

    async saveApiKey() {
        const formData = new FormData(this.elements.form);
        formData.append('woocommerce_blockonomics_api_key', this.elements.apiKey.value);
        formData.append('save', 'Save changes');

        const response = await fetch(this.elements.form.action, {
            method: 'POST',
            body: formData
        });

        if (!response.ok) {
            throw new Error('Failed to save API key');
        }

        this.state.apiKeyChanged = false;
        this.state.otherSettingsChanged = false;
    }

    async performTestSetup() {
        const response = await fetch(`${this.config.baseUrl}?${new URLSearchParams({ action: "test_setup", _ajax_nonce: blockonomics_params.test_setup_nonce })}`);
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return await response.json();
    }

    handleTestSetupResponse(result) {
        this.updateCryptoStatus(result);
        this.updateMetadata(result);
    }

    updateCryptoStatus(cryptoResults) {
        const cryptoElements = this.cryptoElements;

        if (cryptoResults && cryptoResults.error) {
            // Handle string error message
            cryptoElements.error.style.display = 'block';
            cryptoElements.errorText.innerHTML = cryptoResults.error;
            return;
        }

        if (cryptoResults.success_messages) {
            // Success cases
            cryptoElements.success.style.display = 'block';
            for (let index = 0; index < cryptoResults.success_messages.length; index++) {
                const crypto = cryptoResults.success_messages[index];
                cryptoElements.successText.innerHTML += crypto;
                if (index < cryptoResults.success_messages.length - 1) {
                    cryptoElements.successText.innerHTML += '</br>';
                }
            }
        }
        
        if (cryptoResults.error_messages) {
            // Error cases
            cryptoElements.error.style.display = 'block';
            for (let index = 0; index < cryptoResults.error_messages.length; index++) {
                const crypto = cryptoResults.error_messages[index];
                cryptoElements.errorText.innerHTML += crypto;
                if (index < cryptoResults.error_messages.length - 1) {
                    cryptoElements.errorText.innerHTML += '</br>';
                }
            }
        }
    }

    updateMetadata(result) {
        // Update store name and crypto icons after successful test setup
        const storeNameDisplay = document.getElementById('store-name-display');
        if (storeNameDisplay && result.store_name) {
            let html = result.store_name;

            // Add crypto icons for enabled cryptos
            if (result.enabled_cryptos && result.enabled_cryptos.length > 0) {
                const pluginUrl = blockonomics_params.plugin_url || '';
                result.enabled_cryptos.forEach(code => {
                    code = code.toLowerCase();
                    if (['btc', 'usdt'].includes(code)) {
                        html += ` <img src="${pluginUrl}img/${code}.svg" alt="${code.toUpperCase()}" style="height:18px;vertical-align:middle;margin-left:4px;" title="${code.toUpperCase()}" />`;
                    }
                });
            }

            storeNameDisplay.querySelector('strong').innerHTML = html;
            storeNameDisplay.style.display = '';
        }
    }

    handleFormSubmit(e) {
        if (!this.validateApiKey()) {
            e.preventDefault();
            return;
        }

        if (this.state.apiKeyChanged) {
            this.elements.pluginEnabled.checked = true;
        }

        this.elements.notifications.apiKey.style.display = 'none';
    }

    showApiKeyError() {
        this.elements.notifications.apiKey.style.display = 'block';
        const apiKeyRow = document.getElementById("apikey-row");
        if (apiKeyRow) {
            apiKeyRow.scrollIntoView();
            window.scrollBy(0, -100);
        }
    }
}