<?php

/**
 * This class provides the functions needed for extending the WooCommerce
 * Payment Gateway class
 *
 * @class   WC_Gateway_Blockonomics
 * @extends WC_Payment_Gateway
 * @version 2.0.1
 * @author  Blockonomics Inc.
 */
class WC_Gateway_Blockonomics extends WC_Payment_Gateway
{
    public function __construct()
    {
        load_plugin_textdomain('blockonomics-bitcoin-payments', false, dirname(plugin_basename(__FILE__)) . '/languages/');

        $this->id   = 'blockonomics';
        $this->method_title = __( 'Blockonomics Bitcoin', 'blockonomics-bitcoin-payments' );
        $this->method_description = __( 'Blockonomics Bitcoin Description', 'blockonomics-bitcoin-payments' );

        include_once 'Blockonomics.php';
        $blockonomics = new Blockonomics;
        $active_cryptos = $blockonomics->getActiveCurrencies();

        if (isset($active_cryptos['btc']) && isset($active_cryptos['bch'])) {
            $this->icon = plugins_url('img', dirname(__FILE__)) . '/bitcoin-bch-icon.png';
        } elseif (isset($active_cryptos['btc'])) {
            $this->icon = plugins_url('img', dirname(__FILE__)) . '/bitcoin-icon.png';
        } elseif (isset($active_cryptos['bch'])) {
            $this->icon = plugins_url('img', dirname(__FILE__)) . '/bch-icon.png';
        }

        $this->has_fields        = false;
        $this->order_button_text = __('Pay with bitcoin', 'blockonomics-bitcoin-payments');

        $this->init_form_fields();
        $this->init_settings();

        $this->title       = $this->get_option('title');
        $this->description = $this->get_option('description');

        // Actions
        add_action(
            'woocommerce_update_options_payment_gateways_' . $this->id,
            array(
                $this,
                'process_admin_options'
            )
        );
        add_action(
            'woocommerce_receipt_blockonomics',
            array(
                $this,
                'receipt_page'
            )
        );

        // Payment listener/API hook
        add_action(
            'woocommerce_api_wc_gateway_blockonomics',
            array(
                $this,
                'handle_requests'
            )
        );

        add_action(
            'admin_init',
            array(
                $this,
                'initialise_warnings'
            )
        );
    }


    public function init_form_fields()
    {
        $blockonomics = new Blockonomics;
        $cryptos = $blockonomics->getSupportedCurrencies();
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable Blockonomics plugin', 'blockonomics-bitcoin-payments'),
                'type' => 'checkbox',
                'label' => __('Show bitcoin as an option to customers during checkout?', 'blockonomics-bitcoin-payments'),
                'default' => 'yes'
            ),
            'title-divider' => array(
                'id'    => 'title-divider',
                'type'  => 'divider'
            ),
            'title' => array(
                'title' => __('Title', 'blockonomics-bitcoin-payments'),
                'type' => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'blockonomics-bitcoin-payments'),
                'default' => __('Blockonomics ', 'blockonomics-bitcoin-payments')
            ),
            'description' => array(
                'title' => __('Description', 'blockonomics-bitcoin-payments'),
                'type' => 'text',
                'description' => __('This controls the description which the user sees during checkout.', 'blockonomics-bitcoin-payments'),
                'default' => ''
            ),
            'wallet-divider' => array(
                'id'    => 'wallet-divider',
                'type'  => 'divider'
            ),
            'tempwallet2' => array(
                'id'    => 'tempwallet2',
                'type'  => 'tempwallet2',
                'title' => __('Wallet<p class="block-title-desc">Wallet receving payment</p>', 'blockonomics-bitcoin-payments'),
                'description' => __('Wallet receving payement ', 'blockonomics-bitcoin-payments'),
            ),
            'api-divider' => array(
                'id'    => 'api-divider',
                'type'  => 'divider'
            ),
            'apikey' => array(
                'title' => __('
                    Store
                    <p class="block-title-desc">To use your own wallet and start withdrawing fund,you can setup a Blockonomics store</p>
                    ', 'blockonomics-bitcoin-payments'),
                'type' => 'text',
                'description' => __('Setup Store on <a href= "https://blockonomics.co/merchants" style="color: green;">Blockonomics</a> and paste API Key here', 'blockonomics-bitcoin-payments'),
                'default' => get_option('blockonomics_api_key'),
                
            )
        );

        $this->form_fields['currency-divider'] = array(
            'id'    => 'currency-divider',
            'type'  => 'divider'
        );


        $firstItem = true;
        foreach ($cryptos as $currencyCode => $crypto) {
            $title = $firstItem ? __('Currencies<p class="block-title-desc">Setting and testing currencies accepted </p>', 'blockonomics-bitcoin-payments') : '';
            $this->form_fields[$currencyCode . '_enabled'] = array(
                'title'   => $title,
                'type'    => 'checkbox',
                'label'   => __($crypto["name"] . ' (' . strtoupper($currencyCode) . ')', 'blockonomics-bitcoin-payments'),
                'default' => get_option('blockonomics_' . $currencyCode) == 1 ? 'yes' : 'no',
                'description' => __('
                    <p style="font-size: 14px; color: #646970;">Enable accepting '.$crypto["name"] .'</p>
                    <p class="notice notice-success ' . $currencyCode . '-sucess-notice" style="display:none;width:400px;margin:0;">Success</div>
                    <p class="notice notice-error ' . $currencyCode . '-error-notice" style="width:400px;margin:0;display:none;">
                        <span class="errorText"></span><br />
                        Please consult <a href="http://help.blockonomics.co/support/solutions/articles/33000215104-unable-to-generate-new-address" target="_blank">this troubleshooting article</a>.
                    </p>
                '),
            );
            $firstItem = false;
        }

        $this->form_fields['currency'] = array(
            'id'    => 'currency',
            'type'  => 'currency',
            'title' => __('Currency', 'blockonomics-bitcoin-payments'),
            
        );

        $this->form_fields['advanced-divider'] = array(
            'id'    => 'advanced-divider',
            'type'  => 'divider'
        );

        $this->form_fields['extra_margin'] = array(
            'title' => __('Advanced<p class="block-title-desc">Setting for advanced control</p>', 'blockonomics-bitcoin-payments'),
            'type' => 'text',
            'description' => __('Extra Currency Rate Margin % (Increase live fiat to BTC rate by small percent)', 'blockonomics-bitcoin-payments'),
            'default' => get_option('blockonomics_margin'),
            'placeholder' => __("Extra Margin %", 'blockonomics-bitcoin-payments'),
            
        );
        $this->form_fields['underpayment_slack'] = array(
            'title' => __('', 'blockonomics-bitcoin-payments'),
            'type' => 'text',
            'label' => __('Under Payment', 'blockonomics-bitcoin-payments'),
            'description' => __('Allow payments that are off by a small percentage', 'blockonomics-bitcoin-payments'),
            'default' => get_option('blockonomics_underpayment_slack'),
            'placeholder' => __("Underpayment Slack %", 'blockonomics-bitcoin-payments')
        );
        $this->form_fields['no_javascript'] = array(
            'title' => __('', 'blockonomics-bitcoin-payments'),
            'type' => 'checkbox',
            'label' => __('No Javascript checkout page', 'blockonomics-bitcoin-payments'),
            'description' => __('Enable this if you have majority customer that uses tor like browser that blocks JS', 'blockonomics-bitcoin-payments'),
            'default' => get_option('blockonomics_nojs') == 1 ? 'yes' : 'no',
        );
        $this->form_fields['partialpayment'] = array(
            'title' => __('', 'blockonomics-bitcoin-payments'),
            'type' => 'checkbox',
            'label' => __('Partial Payments ', 'blockonomics-bitcoin-payments'),
            'description' => __('Allow customer to pay order via multiple payement  ', 'blockonomics-bitcoin-payments'),
            'default' => get_option('blockonomics_partial_payments') == 1 ? 'yes' : 'no',
        );
        $this->form_fields['network_confirmation'] = array(
            'title' => __('', 'blockonomics-bitcoin-payments'),
            'type' => 'select',
            'description' => __('Network Confirmations required for payment to complete', 'blockonomics-bitcoin-payments'),
            'default' => __(get_option('blockonomics_network_confirmation'), 'blockonomics-bitcoin-payments'),
            'options' => array(
                '2' => __('2(Recommended)', 'blockonomics-bitcoin-payments'),
                '1' => __('1', 'blockonomics-bitcoin-payments'),
                '0' => __('0', 'blockonomics-bitcoin-payments'),
            ),
        );
        $this->form_fields['callBackurls'] = array(
            'title' => __('', 'blockonomics-bitcoin-payments'),
            'type' => 'text',
            'description' => __('Callback URL:You need this callback URL to setup multiple stores ', 'blockonomics-bitcoin-payments'),
            'default' => __($this->get_callback_url(), 'blockonomics-bitcoin-payments'),
            'disabled' => true,
            'css' => 'width:100%;',
        );
    }

    private function get_callback_url()
    {
        $callback_secret = get_option('blockonomics_callback_secret');
        $callback_url = WC()->api_request_url('WC_Gateway_Blockonomics');
        $callback_url = add_query_arg('secret', $callback_secret, $callback_url);
        return $callback_url;
    }

    public function generate_divider_html() {
        ob_start();
        ?>

        <tr valign="top">
            <td colspan="2"><hr /></td>
        </tr>

        <?php
        return ob_get_clean();
    }

    public function generate_tempwallet2_html($key, $data){

        $field_key = $this->get_field_key( $key );
        $btc_enabled = get_option("blockonomics_btc");
        $total_received_formatted = 00;

        // If BTC is enabled or the 'blockonomics_btc' option has never been set (which returns false), proceed.
        if ($btc_enabled || $btc_enabled === false) {
            // Fetch the amount in the smallest unit from the database and convert to BTC
            $temp_withdraw_amount = get_option('blockonomics_temp_withdraw_amount', 0); // Default to 0 if the option doesn't exist
            $total_received = $temp_withdraw_amount / 1.0e8;

            // // Format the total received to ensure consistent decimal places (e.g., "0.00")
            $total_received_formatted = number_format($total_received, 8, '.', '');
            // Update the 'tempwallet' option with the formatted total received amount
            update_option("tempwallet", $total_received_formatted);
        }

        $data = wp_parse_args( $data, array() );

        ob_start();
        ?>   
        <tr valign="top">
			<th scope="row" class="titledesc">
                <label for="<?php echo esc_attr( $field_key ); ?>"><?php echo wp_kses_post( $data['title'] ); ?></label>
			</th>
			<td class="forminp">
                <div id="temp-wallet-notification-box">
                    <span class="text">You have received fund in your temporary wallet. To withdraw the fund, set up your Blockonomics store.</span>
                </div>
                <div style="display: flex;align-items: flex-start;">
                    <div>
                        <div style="font-size: 14px; font-weight: bold;margin-bottom: 10px;">Store Wallet</div>
                        <div style="font-size: 14px; color: #646970; margin-bottom: 10px;">
                            Accepting fund with temporary wallet. You can setup a
                            Blockonomics store to use your own wallet.
                        </div>
                        <a href="#" style="color: green; text-decoration: none; font-size: 14px;">Learn More</a>
                    </div>
                    <?php if ($total_received_formatted != '0.00000000'): ?>
                    <input id="temp-wallet-input" type="text" style="width: 200px; margin-left:50px;text-align:right;"value="<?php echo __($total_received_formatted, 'blockonomics-bitcoin-payments') ?> BTC" readonly>
                    <?php endif; ?>
                </div>
			</td>
		</tr>
        <?php
        return ob_get_clean();
    }
    public function generate_currency_html($key, $value)
    {
        ob_start();
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc"></th>
            <td class="forminp">
                <div class="bnomics-options-small-margin-top">
                    <div style="display:flex;">
                        <input type="button" id="test-setup-btn" class="button-primary" style ="background-color: green;color:white;" value="<?php echo __("Test Setup", 'blockonomics-bitcoin-payments') ?>" />
                        <div class="test-spinner" style="display: none;margin-left:10px;"></div>
                    </div>
                </div>
            </td>
        </tr>
        <?php
        return ob_get_clean();
    }
     
    

    public function process_admin_options()
    {
        if (!parent::process_admin_options()) {
            return false;
        }

        $blockonomics = new Blockonomics;
        $activeCurrencies = $blockonomics->getSupportedCurrencies();

        foreach ($activeCurrencies as $code => $currency) {
            $optionName = 'blockonomics_' . strtolower($code);
            $isEnabled = parent::get_option($code . '_enabled') == 'yes' ? 1 : 0;
            update_option($optionName, $isEnabled);
        }

        update_option('blockonomics_timeperiod', (int)parent::get_option('timeperiod'));
        update_option('blockonomics_margin', (int)parent::get_option('extra_margin'));
        update_option('blockonomics_underpayment_slack', (int)parent::get_option('underpayment_slack'));
        update_option('blockonomics_partial_payments', parent::get_option('partialpayment') == 'yes' ? 1 : 0);
        update_option('blockonomics_api_key', parent::get_option('apikey'));
        update_option('blockonomics_nojs', parent::get_option('no_javascript') == 'yes' ? 1 : 0);

        parent::update_option('callBackurls', $this->get_callback_url());
    }

    // Woocommerce process payment, runs during the checkout
    public function process_payment($order_id)
    {
        include_once 'Blockonomics.php';
        $blockonomics = new Blockonomics;

        $order_url = $blockonomics->get_order_checkout_url($order_id);

        return array(
            'result'   => 'success',
            'redirect' => $order_url
        );
    }

    // Handles requests to the blockonomics page
    // Sanitizes all request/input data
    public function handle_requests()
    {
        $crypto = isset($_GET["crypto"]) ? sanitize_key($_GET['crypto']) : "";
        $finish_order = isset($_GET["finish_order"]) ? sanitize_text_field(wp_unslash($_GET['finish_order'])) : "";
        $get_amount = isset($_GET['get_amount']) ? sanitize_text_field(wp_unslash($_GET['get_amount'])) : "";
        $secret = isset($_GET['secret']) ? sanitize_text_field(wp_unslash($_GET['secret'])) : "";
        $addr = isset($_GET['addr']) ? sanitize_text_field(wp_unslash($_GET['addr'])) : "";
        $status = isset($_GET['status']) ? intval($_GET['status']) : "";
        $value = isset($_GET['value']) ? absint($_GET['value']) : "";
        $txid = isset($_GET['txid']) ? sanitize_text_field(wp_unslash($_GET['txid'])) : "";
        $rbf = isset($_GET['rbf']) ? wp_validate_boolean(intval(wp_unslash($_GET['rbf']))) : "";

        $test_setup = isset($_GET["test_setup"]) ? sanitize_text_field(wp_unslash($_GET['test_setup'])) : "";
        $api_key = isset($_GET["api_key"]) ? sanitize_text_field(wp_unslash($_GET['api_key'])) : "";
        $btc_active = isset($_GET["btc_active"]) ? $_GET["btc_active"] : false;
        $bch_active = isset($_GET["bch_active"]) ? $_GET["bch_active"] : false;

        include_once 'Blockonomics.php';
        $blockonomics = new Blockonomics;

        if ($finish_order) {
            $order_id = $blockonomics->decrypt_hash($finish_order);
            $blockonomics->redirect_finish_order($order_id);
        } else if ($get_amount && $crypto) {
            $order_id = $blockonomics->decrypt_hash($get_amount);
            $blockonomics->get_order_amount_info($order_id, $crypto);
        } else if ($secret && $addr && isset($status) && $value && $txid) {
            $blockonomics->process_callback($secret, $addr, $status, $value, $txid, $rbf);
        } else if ($test_setup) {
            $blockonomics->settingsTestsetup($api_key, $btc_active, $bch_active);
        }

        exit();
    }

    public function initialise_warnings() {
        if (
            isset($_GET['tab']) &&
            'checkout' === $_GET['tab'] &&
            isset($_GET['section']) &&
            'blockonomics' === $_GET['section']
        ) {
            add_action( 'admin_notices', [ $this, 'plugin_enabled_notice' ] );
        }
    }

    public function plugin_enabled_notice() {
		/* translators: %1$s Webhook secret page link, %2$s Webhook guide page link  */
		echo wp_kses_post( '<div class="notice notice-info"><p>' . sprintf( __( 'Blockonomics is enabled. You are not accepting Bitcoin payment with a temporary wallet. Optionally, %1$ssetup your Blockonomics store%2$s to accept payment to your own wallet. (5 minutes)', 'blockonomics-bitcoin-payments' ), '<a href="https://checkoutplugins.com/docs/stripe-card-payments/#webhook" target="_blank">', '</a>' ) . '</p></div>' );
	}
}
