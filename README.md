Features
--------
- Accept bitcoin payments on your website with ease
- Payments go directly in your own bitcoin wallet
- All HD wallet like trezor, blockchain.info, mycelium supported
- No approvals of API key/documentation required
- Uses [blockonomics API](https://www.blockonomics.co/views/api.html)

Blockonomics Setup
-----------------
- Complete [blockonomics merchant wizard](https://www.blockonomics.co/merchants) 
- Get API key from Wallet Watcher > Settings


Woocommerce Setup
-----------------
- Make sure you have [woocommerce](https://wordpress.org/plugins/woocommerce/) plugin installed on your wordpress site
- Upload blockonomics.zip from [releases](https://github.com/blockonomics/woocommerce-plugin/releases) using Plugins > Add new 
- Activate the plugin
- You should be able see Blockonomics submenu inside Settings.  
 ![Settings Panel](panel.png?raw=true)  
 ![Blockonomics Settings](settings.png?raw=true)
- Put API key from [Blockonomics Setup](#blockonomics-setup) here
- Copy callback url and put into blockonomics [merchants](https://www.blockonomics.co/merchants)


Try checkout product , and you will see pay with bitcoin option.
Use bitcoin to pay and enjoy !

-------------------

Note: If you are running wordpress on localhost, you need Dynamic
DNS/public IP pointing to your localhost.
This is because blockonomics.co will requires the callback to be a public url.
