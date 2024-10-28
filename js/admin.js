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
            // Get active currencies from metadata or default to BTC
            activeCurrencies: this.getActiveCurrencies()
        };

        // Initialize crypto DOM elements
        this.cryptoElements = this.initializeCryptoElements();
    }

    getActiveCurrencies() {
        const enabledCryptos = blockonomics_params.enabled_cryptos; // This should be passed from PHP
        if (enabledCryptos) {
            return enabledCryptos.split(',');
        }
        return ['btc']; // Default to BTC if no currencies are set
    }

    init() {
        this.attachEventListeners();
        this.initializeCryptoDisplay();
    }

    initializeCryptoElements() {
        const elements = {};

        this.config.activeCurrencies.forEach(code => {
            elements[code] = {
                checkbox: document.getElementById(`woocommerce_blockonomics_${code}_enabled`),
                success: document.querySelector(`.${code}-success-notice`),
                error: document.querySelector(`.${code}-error-notice`),
                errorText: document.querySelector(`.${code}-error-notice .errorText`)
            };
        });

        return elements;
    }

    initializeCryptoDisplay() {
        Object.values(this.cryptoElements).forEach(crypto => {
            if (!this.validateCryptoElements(crypto)) return;

            crypto.success.style.display = 'none';
            crypto.error.style.display = 'none';

            crypto.checkbox.addEventListener('change', () => {
                crypto.success.style.display = 'none';
                crypto.error.style.display = 'none';
            });
        });
    }

    validateCryptoElements(crypto) {
        return crypto.success && crypto.error && crypto.checkbox && crypto.errorText;
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
        const response = await fetch(`${this.config.baseUrl}?${new URLSearchParams({ action: "test_setup" })}`);
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return await response.json();
    }

    handleTestSetupResponse(result) {
        this.updateCryptoStatus(result.crypto);
        this.updateMetadata(result);
    }

    updateCryptoStatus(cryptoResults) {
        // If no cryptos are enabled, show generic message
        if (Object.values(cryptoResults).every(result => result === false)) {
            Object.values(this.cryptoElements).forEach(elements => {
                elements.error.style.display = 'none';
                elements.success.style.display = 'none';
            });
            // Show generic message only in BTC error element

            const btcElements = this.cryptoElements['btc'];
            if (btcElements) {
                btcElements.error.style.display = 'block';
                btcElements.errorText.innerText = 'No crypto enabled for this store';
                btcElements.success.style.display = 'none';
            }
            return;
        }

        Object.entries(cryptoResults).forEach(([code, result]) => {
            const elements = this.cryptoElements[code];
            if (!elements) return;

            elements.success.style.display = result === false ? 'block' : 'none';
            elements.error.style.display = typeof result === 'string' ? 'block' : 'none';
            if (typeof result === 'string') {
                elements.errorText.innerHTML = result;
            }
        });
    }

    updateMetadata(result) {
        const apiKeyRow = this.elements.apiKey.closest('tr');
        const descriptionField = apiKeyRow?.querySelector('.description');

        if (!descriptionField) return;

        if (result.metadata_cleared) {
            descriptionField.textContent = '';
            // Reset to BTC only when metadata is cleared
            this.config.activeCurrencies = ['btc'];
            this.cryptoElements = this.initializeCryptoElements();
            this.initializeCryptoDisplay();
        } else if (result.store_data) {
            let displayText = result.store_data.name || '';

            // Only show enabled crypto info if currencies are actually enabled
            if (result.store_data.enabled_cryptos && result.store_data.enabled_cryptos !== '') {
                this.config.activeCurrencies = result.store_data.enabled_cryptos.toLowerCase().split(',');
                displayText += ' Enabled crypto: ';  // Changed format to match form fields
                displayText += this.config.activeCurrencies
                    .map(code => blockonomics_params.currencies[code]?.name || code.toUpperCase())
                    .join(' ');
            }
            descriptionField.textContent = displayText;
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