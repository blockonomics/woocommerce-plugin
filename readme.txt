=== Blockonomics Bitcoin Payments ===
Tags: bitcoin, blockonomics, woocommerce, ecommerce, payments
Requires at least: 3.0.1
Tested up to: 4.7.3
Stable tag: 1.0.1
License: MIT
License URI: http://opensource.org/licenses/MIT

Accept bitcoin payments on your WooCommerce-powered website with Blockonomics.

== Description ==

- Accept bitcoin payments on your website with ease
- No security risk, payments go directly into your own bitcoin wallet
- All major HD wallets like trezor, blockchain.info, mycelium supported
- No approvals of API key/documentation required
- Supports all major fiat currencies

== Installation ==

= Blockonomics Setup =
- Complete [blockonomics merchant wizard](https://www.blockonomics.co/merchants) 
- Get API key from Wallet Watcher > Settings

= Woocommerce Setup =
- Make sure you have [woocommerce](https://wordpress.org/plugins/woocommerce/) plugin installed on your wordpress site
- Activate the plugin
- You should be able see Blockonomics submenu inside Settings.  
- Put Blockonomics API key here
- Copy callback url and put into blockonomics [merchants](https://www.blockonomics.co/merchants)

Try checkout product , and you will see pay with bitcoin option.
Use bitcoin to pay and enjoy !

== Frequently Asked Questions ==

= I am getting empty order page after checkout, what to do ? =
Your webhost is blocking outgoing HTTP connections. Blockonomics requires an outgoing HTTP POST to generate new address. Check with your webhost to allow this.

= My order page is repeatedly refreshing on payment, how to fix this? =
Your webhost is blocking incoming callbacks from bots, our you have a DDOS protection in place that is causing this. Blockonomics server uses curl for payment callbacks and cannot emulate a browser accessing your website. Remove the DDOS protection for blockonomics.co 

== Screenshots ==

1. Settings panel
2. Blockonomics configuration
3. Checkout screen
 
