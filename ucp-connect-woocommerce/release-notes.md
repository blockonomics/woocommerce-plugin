### What's New in v2.0.1

**Critical Fix**: Added missing `Nonce` header to WooCommerce Store API requests.

The WooCommerce Store API requires `wp_create_nonce('wc_store_api')` for authentication. This was missing in v2.0.0, causing 401 errors on all checkout operations.

### Installation
Download `ucp-connect-woocommerce-2.0.1.zip` and install/update via your WordPress Plugins dashboard.
