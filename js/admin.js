document.addEventListener('DOMContentLoaded', function() {
    try {
        const cryptoDOM = {};

        const testSetupBtn = document.getElementById('test-setup-btn');
        const spinner = document.querySelector('.test-spinner');

        const baseUrl = blockonomics_params.ajaxurl;
        const apikey = blockonomics_params.apikey || "";
        const activeCurrencies = { 'btc': true, 'bch': true };
       
        window.addEventListener('beforeunload', function(event) {
            event.stopImmediatePropagation();
        });

        for (let code in activeCurrencies) {
            cryptoDOM[code] = {
                checkbox: document.getElementById(`woocommerce_blockonomics_${code}_enabled`),
                success: document.querySelector(`.${code}-success-notice`),
                error: document.querySelector(`.${code}-error-notice`),
                errorText: document.querySelector(`.${code}-error-notice .errorText`)
            };

            if (
                !cryptoDOM[code].success || 
                !cryptoDOM[code].error || 
                !cryptoDOM[code].checkbox || 
                !cryptoDOM[code].errorText) {
                continue;
            }

            cryptoDOM[code].success.style.display = 'none';
            cryptoDOM[code].error.style.display = 'none';

            cryptoDOM[code].checkbox.addEventListener('change', (event) => {
                cryptoDOM[code].success.style.display = 'none';
                cryptoDOM[code].error.style.display = 'none';
            });
        }

        if (testSetupBtn) {
            testSetupBtn.addEventListener('click', async function(event) {
                event.preventDefault();

                if (spinner) {
                    spinner.style.display = 'block';
                }
                testSetupBtn.disabled = true;
                
                const payload = { api_key: apikey, action: "test_setup" };

                for (let code in activeCurrencies) {
                    const node = cryptoDOM[code].checkbox;
                    const checked = node ? node.checked : false;
                    payload[`${code}_active`] = checked;
                }

                let errorResults = {};

                try {
                    const res = await fetch(`${baseUrl}?${new URLSearchParams(payload)}`);
                    if (!res.ok) {
                        throw new Error('Network response was not ok');
                    }
                    errorResults = await res.json();
                } catch (error) {
                    console.error('Error:', error);
                } finally {
                    spinner.style.display = 'none';
                    testSetupBtn.disabled = false;

                    for (let code in errorResults) {
                        const result = errorResults[code];

                        if (!result) {
                            cryptoDOM[code].success.style.display = 'block';
                            cryptoDOM[code].error.style.display = 'none';
                        } else {
                            cryptoDOM[code].success.style.display = 'none';
                            cryptoDOM[code].error.style.display = 'block';
                            cryptoDOM[code].errorText.innerText = result;
                        }
                    }
                }
            });
        }

       
    } catch (e) {
        console.log("Error in admin settings", e);
    }
});