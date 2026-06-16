=== Blockonomics Bitcoin Payments ===
**Tags:** bitcoin, bitcoin payments, usdt payments, woocommerce, cryptocurrency
**Requires at least:** 5.6
**Tested up to:** 6.9
**Require PHP:** 7.4
**WC requires at least:** 7.0
**WC tested up to:** 10.4.3
**Stable tag:** 3.9.1
License: MIT
License URI: http://opensource.org/licenses/MIT

Accept bitcoin payments on your WooCommerce-powered website with Blockonomics.

== Description ==

- Accept bitcoin payments on your website with ease
- No security risk, payments go directly into your own bitcoin wallet
- All major HD wallets like trezor, blockchain.info, mycelium supported
- No approvals of API key/documentation required
- Supports all major fiat currencies

## Installation

Follow these instructions to start accepting Bitcoin with Blockonomics on your WordPress/WooCommerce store.

### 1) Install Blockonomics Plugin

1. In your WordPress dashboard, go to Plugins and click on Add New Plugin.
2. Search for "WordPress Bitcoin Payments - Blockonomics". In Search Results, next to our plugin click on "Install Now".
3. Once installed, click on "Activate" to activate our plugin.

### 2) Automatic Store Setup (Recommended)

1. Once activated, you will be redirected to the Plugins page. You will see a notification banner. Click on "Account Setup page" to complete the setup.
2. Follow the instructions in the Account setup page to get the API Key from Blockonomics Stores dashboard and paste it in. Then click on "Continue".
3. Enter a Store name that's easy for you to remember. Then click on "Continue".
4. A store will be created in your Blockonomics account linking your WordPress/WooCommerce store. Click on "Done".
5. You will be redirected to WooCommerce > Settings > Payments > Blockonomics. Here in the "Store" section click on "Test Setup".
6. Once successful, you should see a green checkmark next to "BTC" indicating that your Blockonomics plugin is ready to accept Bitcoin payments.

### 3) Manual Setup

(If you have already completed Automatic Store Setup, you can skip this section.)

**Configure API Key:**
1. Go to Dashboard > Stores in Blockonomics.
2. Copy the API Key.
3. In your WordPress Dashboard, go to WooCommerce > Settings > Payments > Blockonomics Bitcoin, paste the API Key and click "Save changes".

For detailed steps on adding Wallet & Store, please refer to the official support article.

## FAQ

### How do I edit text/customize appearance of the checkout page?

Please consult this article to find out how to customize the checkout page as desired:
<https://help.blockonomics.co/support/solutions/articles/33000243991-customizing-branding-checkout-page-appreance>

### Orders are not getting marked Paid on payment. How do I fix?

Blockonomics server sends payment callbacks to your system to update transaction status. If these payment callbacks are successful, your orders get marked as Paid.

Most common reason for callbacks failing is your webhost blocking incoming callbacks thinking they are from bots, or you have a DDoS protection in place. Please consult this article on how to debug and fix this:
<https://help.blockonomics.co/solution/articles/33000219539-order-status-not-changing-ddos-protection>

### My customers use TOR browser and don't have JavaScript enabled. What to do?

You can run our plugin in No JavaScript mode by checking the "No JavaScript checkout page" option in
WordPress Admin Dashboard > WooCommerce > Settings > Payments > Blockonomics Bitcoin > Advanced.
== Screenshots ==

1. Settings panel
2. Blockonomics configuration
3. Checkout screen
 
