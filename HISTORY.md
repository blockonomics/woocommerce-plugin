== Changelog ==

= 3.3  =
* Minor Security fixes


= 3.2  =
* Updated Test Setup logic
* Security fixes

= 3.1  =
* New Test Setup Interface with Currencies Tab
* Added Woocommerce filter to locate orders by txid/address

= 3.0  =
* Using DB table to store orders

= 2.4.1  =
* Fixes update issues with merchant having large volumes

= 2.4  =
* Zero confirm RBF payment are unsafe and are ignored

= 2.3  =
* Added noJS payment screen support

= 2.2  =
* Fixed issues with callbacks

= 2.1  =
* Fixed txid not showing order details
* Fixed issue with caching files

= 2.0  =
* Added BCH support
* Fixed payment expiry issues
* New UI of payment page and code refactor

= 1.8.5  =
* Added option for noJavascript checkout page

= 1.8.3  =
* Better handling callbacks errors

= 1.8.2  =
* Removed conflicts with mailchimp plugin

= 1.8.1  =
* Improved altcoin help text in case payment is not detected
* Added support for adding custom checkout pages in theme

= 1.8.0  =
* Added Help text on Payment Page
* Supporting zero/one blockchain confirmations
* Reusing same address on order expiry

= 1.7.8  =
* Improvements to flypme refund flow
* Minor bug fixes

= 1.7.7  =
* Automatically detects rendering issues on checkout screen 

= 1.7.6  =
* Added Lite mode rendering option 

= 1.7.5  =
* Checkout page performance improvements and bug fixes

= 1.7.4  =
* Added Underpayment slack options, advanced settings section

= 1.7.2  =
* Fixed issue with Bitcoin Image not showing, code refactoring

= 1.7.1  =
* Patch fix for save settings not working for users without APIKey

= 1.7.0  =
* Installation now only requires plugin activate
* Altcoin Code refactored 

= 1.6.8  =
* Added refunds to altcoin payments

= 1.6.7  =
* Fixes for comptability to Wordpress 5.0
* TOR callbacks supported

= 1.6.6  =
* Updated comptability to Wordpress 5.0

= 1.6.5  =
* Adding payment details in order email, review messages 

= 1.6.4  =
* Removed grey background conflicting with some themes

= 1.6.3  =
* Fixed bug with enqueue script

= 1.6.2  =
* New option, extra currency rate margin

= 1.6.1  =
* Altcoin integration enabled
* New checkout design having btc/altcoin tab
* using wp_remote function to avoid fopen errors

= 1.6.0  =
* Test Upgrade

= 1.5.1  =
* Test Setup is more intelligent
* Fixed typos in README

= 1.5.0  =
* Better Test Setup Diagnostics
* Updated description/links to tutorials

= 1.4.9  =
* Faster and easier installation process having Test Setup feature
* Showing QR code for bech32 addresses 
* Showing Bitcoin Address for all orders

= 1.4.8  =
* Improved error handling when unable to generate address

= 1.4.7  =
* Made compatible for internationalization through translate.wordpress.org

= 1.4.6  =
* Updated README for more description on bitcoin payments

= 1.4.5  =
* Fixed problem with visual composer themes
* Added extra help text on order confirmation page
* Fixed conflict with javascript method
* Updated plugin to fix problem with incorrect commit

= 1.4.4  =
* Fixed problem with visual composer themes
* Added extra help text on order confirmation page
* Fixed conflict with javascript method

= 1.4.3  =
* Added option to configure timeperiod of checkout timer
* Added functionality to regenerate callback URL
* Updates to README and snapshots

= 1.4.2 =
* Fixed bug with conflicting style of spinner

= 1.4.1 =
* Moved all styles to CSS file. Gives ability to control plugin appearance
* Comptatibility with WP 4.9.1

= 1.4.0 =
* Usability improvements to payment screen
* Added Spanish, french and german translation

= 1.3.9 =
* Support for altcoin payments through shapeshift (You need to enable this from Settings)
* Not marking order as failed on overpayment 
* Minified JS files and removed unused ones

= 1.3.8 =
* Support for altcoin payments through shapeshift (You need to enable this from Settings)
* Not marking order as failed on overpayment 

= 1.3.7 =
* Added paid/expected BTC custom fields 
* Updated checkout icon 

= 1.3.6 =
* Improved payment screen user interface
* Comptability with WP 4.8 
* Updated README

= 1.3.5 =
* Improved payment screen user interface 
* Updated README

= 1.3.4 =
* Fixed github repo URL
* Updated README

= 1.3.2  =
* Change in README

= 1.3.1  =
* Minor change in README

= 1.3  =
* Showing errors when unable to generate new address
* Removed unused code

= 1.2 =
* Showing received order page after receiving payment
* fixes problems with multisite and php7 compatibility

