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
            btc: {
                success: document.querySelector('.btc-success-notice'),
                error: document.querySelector('.btc-error-notice'),
                errorText: document.querySelector('.btc-error-notice .errorText')
            }
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
        const btcResult = cryptoResults.btc;
        const btcElements = this.cryptoElements.btc;

        if (!btcElements) return;

        if (btcResult === false) {
            // Success case
            btcElements.error.style.display = 'none';
            btcElements.success.style.display = 'block';
        } else {
            // Error case
            btcElements.success.style.display = 'none';
            btcElements.error.style.display = 'block';
            if (typeof btcResult === 'string') {
                btcElements.errorText.innerHTML = btcResult;
            }
        }
    }

    updateMetadata(result) {
        const apiKeyRow = this.elements.apiKey.closest('tr');
        const descriptionField = apiKeyRow?.querySelector('.description');

        if (!descriptionField) return;

        if (result.metadata_cleared) {
            descriptionField.textContent = '';
            this.config.activeCurrencies = ['btc'];
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