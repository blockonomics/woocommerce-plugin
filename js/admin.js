document.addEventListener('DOMContentLoaded', function() {
    try {
        const cryptoDOM = {};

        const testSetupBtn = document.getElementById('test-setup-btn');
        const spinner = document.querySelector('.test-spinner');

        const apikeyInput = document.querySelector('input[name="woocommerce_blockonomics_api_key"]');
        const testSetupNotificationDOM = document.getElementById('test-setup-notification-box');
        const blockonomicsPluginEnabledDOM = document.getElementById('woocommerce_blockonomics_enabled');
        let hasBlockonomicsApiKeyChanged = false;
        let hasOtherSettingsChanged = false;


        apikeyInput.addEventListener('change', () => {
            hasBlockonomicsApiKeyChanged = true;
        });

        const baseUrl = blockonomics_params.ajaxurl;
        const activeCurrencies = { 'btc': true, 'bch': true };

        // let formChanged = false;
        const form = document.getElementById("mainform");
       
       
        window.addEventListener('beforeunload', (event) => {
            event.stopImmediatePropagation();
        });
        
        form.addEventListener("input", (event) => {
            if (event.target !== apikeyInput) {
                hasBlockonomicsApiKeyChanged  = true;
            } else {
                hasOtherSettingsChanged = true;
            }
        });

        apikeyInput.addEventListener('change', () => {
            hasBlockonomicsApiKeyChanged = true;
        });

        form.addEventListener("submit",function(e){ 
            if(apikeyInput.value === '') {
                document.getElementById("api-key-notification-box").style.display = 'block';
                document.getElementById("apikey-row").scrollIntoView();
                window.scrollBy(0, -100);
                e.preventDefault();
                return;
            }
            if (hasBlockonomicsApiKeyChanged) {
                blockonomicsPluginEnabledDOM.checked = true;
            }
            document.getElementById("api-key-notification-box").style.display = 'none';
  
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

                // Check if API key is empty
                if (apikeyInput.value.trim() === '') {
                    const apiKeyNotificationBox = document.getElementById("api-key-notification-box");
                    if (apiKeyNotificationBox) {
                        apiKeyNotificationBox.style.display = 'block';
                        const apikeyRow = document.getElementById("apikey-row");
                        if (apikeyRow) {
                            apikeyRow.scrollIntoView();
                            window.scrollBy(0, -100);
                        }
                    }
                    return;
                }

                // Other settings changed but not API key -> dont run Test setup
                if (hasOtherSettingsChanged && !hasBlockonomicsApiKeyChanged) {
                    if (testSetupNotificationDOM) {
                        testSetupNotificationDOM.style.display = 'block';
                    }
                    return;
                }


                // Hide API key notification if it was previously shown
                const apiKeyNotificationBox = document.getElementById("api-key-notification-box");
                if (apiKeyNotificationBox) {
                    apiKeyNotificationBox.style.display = 'none';
                }

                if (testSetupNotificationDOM) {
                    testSetupNotificationDOM.style.display = 'none';
                }

                if (spinner) {
                    spinner.style.display = 'block';
                }
                testSetupBtn.disabled = true;
                
                // If API key has changed, save it first
                if (hasBlockonomicsApiKeyChanged) {
                    const saveApiKeyPayload = new FormData(form);
                    saveApiKeyPayload.append('woocommerce_blockonomics_api_key', apikeyInput.value);
                    saveApiKeyPayload.append('save', 'Save changes');

                    // // Both API key and other settings changed
                    // if (hasOtherSettingsChanged) {
                    //     if (!confirm("Other settings have changed. Proceeding will only save the API key. Do you want to continue?")) {
                    //         spinner.style.display = 'none';
                    //         testSetupBtn.disabled = false;
                    //         return;
                    //     }
                    //     // User chose to proceed, reset hasOtherSettingsChanged
                    //     hasOtherSettingsChanged = false;
                    // }


                    try {
                        const saveRes = await fetch(form.action, {
                            method: 'POST',
                            body: saveApiKeyPayload
                        });
                        if (!saveRes.ok) {
                            throw new Error('Failed to save API key');
                        }
                        hasBlockonomicsApiKeyChanged = false;
                        hasOtherSettingsChanged  = false;  // Reset formChanged as we've just saved the API key
                    } catch (error) {
                        console.error('Error saving API key:', error);
                        spinner.style.display = 'none';
                        testSetupBtn.disabled = false;
                        return;
                    }
                }

                const payload = { action: "test_setup" };

                let result = {};

                try {
                    const res = await fetch(`${baseUrl}?${new URLSearchParams(payload)}`);
                    if (!res.ok) {
                        throw new Error('Network response was not ok');
                    }
                    result = await res.json();
                } catch (error) {
                    console.error('Error:', error);
                } finally {
                    spinner.style.display = 'none';
                    testSetupBtn.disabled = false;

                    for (let code in result.crypto) {
                        const cryptoResult = result.crypto[code];

                        if (cryptoResult === false) {
                            cryptoDOM[code].success.style.display = 'block';
                            cryptoDOM[code].error.style.display = 'none';
                        } else {
                            cryptoDOM[code].success.style.display = 'none';
                            cryptoDOM[code].error.style.display = 'block';
                            cryptoDOM[code].errorText.innerText = cryptoResult;
                        }
                    }
                }
            });
        }

       
    } catch (e) {
        console.log("Error in admin settings", e);
    }
});