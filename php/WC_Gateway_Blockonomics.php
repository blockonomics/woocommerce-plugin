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
            'woocommerce_update_options_payment_gateways_' . $this->id, array(
                $this,
                'process_admin_options'
            )
        );
        add_action(
            'woocommerce_receipt_blockonomics', array(
                $this,
                'receipt_page'
            )
        );

        // Payment listener/API hook
        add_action(
            'woocommerce_api_wc_gateway_blockonomics', array(
                $this,
                'handle_requests'
            )
        );

		
    }

    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable Blockonomics plugin', 'blockonomics-bitcoin-payments'),
                'type' => 'checkbox',
                'label' => __('Show bitcoin as an option to customers during checkout?', 'blockonomics-bitcoin-payments'),
                'default' => 'yes'
            ),
            'title' => array(
                'title' => __('Title', 'blockonomics-bitcoin-payments'),
                'type' => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'blockonomics-bitcoin-payments'),
                'default' => __('Bitcoin', 'blockonomics-bitcoin-payments')
            ),
            'description' => array(
                'title' => __( 'Description', 'blockonomics-bitcoin-payments' ),
                'type' => 'text',
                'description' => __('This controls the description which the user sees during checkout.', 'blockonomics-bitcoin-payments'),
                'default' => ''
            ),
            'tempwallet' => array(
                'title' => __('Temporary Wallet', 'blockonomics-bitcoin-payments'),
                'type' => 'text',
                'description' => __('Accepting funds with temporary wallet.you can setup a Blockonomics store to use your own wallet.', 'blockonomics-bitcoin-payments'),
                'default' => __($this->temp_wallet_amount(),'blockonomics-bitcoin-payments')
            ),
            'heading'=> array(
                'id'    => 'heading',
                'type'  => 'heading',
                'title' => __( 'Heading', 'blockonomics-bitcoin-payments' ),
            ),
            'apikey' => array(
                'title' => __( 'API Key', 'blockonomics-bitcoin-payments' ),
                'type' => 'text',
                'description' => __('To get your API Key, click Get Started for Free on https://blockonomics.co/merchants', 'blockonomics-bitcoin-payments'),
                'default' => get_option('blockonomics_api_key')
            ),
            'currency' => array(
                'id'    => 'currency',
                'type'  => 'currency',
                'title' => __( 'Currency', 'blockonomics-bitcoin-payments' ),
            ),
            'btc_enabled' => array(
                'title' => __('Currencies', 'blockonomics-bitcoin-payments'),
                'type' => 'checkbox',
                'label' => __('Bitcoin (BTC)', 'blockonomics-bitcoin-payments'),
                'description' => __('To configure, click Get Started for Free on https://blockonomics.co/merchants'),
                'default' => get_option('blockonomics_btc') == 1 ? 'yes' : 'no',
            ),
            'bch_enabled' => array(
                'title' => __('', 'blockonomics-bitcoin-payments'),
                'type' => 'checkbox',
                'label' => __('Bitcoin Cash (BCH)', 'blockonomics-bitcoin-payments'),
                'description' => __('To configure, click Get Started for Free on https://blockonomics.co/merchants'),
                'default' => get_option('blockonomics_bch') == 1 ? 'yes' : 'no',
            ),
            'timeperiod' => array(
                'title' => __('Advanced', 'blockonomics-bitcoin-payments'),
                'type' => 'select',
                'description' => __('Time period of countdown timer on payment page (in minutes)', 'blockonomics-bitcoin-payments'),
                'default' => get_option('blockonomics_timeperiod'),
                'options' => array(
                    '10' => __('10','blockonomics-bitcoin-payments'),
                    '15' => __('15','blockonomics-bitcoin-payments'),
                    '20' => __('20','blockonomics-bitcoin-payments'),
                    '25' => __('25','blockonomics-bitcoin-payments'),
                    '30' => __('30','blockonomics-bitcoin-payments'),
                ),
            ),
            'extra_margin' => array(
                'title' => __(' ', 'blockonomics-bitcoin-payments'),
                'type' => 'text',
                'description' => __('Extra Currency Rate Margin % (Increase live fiat to BTC rate by small percent)', 'blockonomics-bitcoin-payments'),
                'default' => get_option('blockonomics_margin'),
            ),
            'underpayment_slack' => array(
                'title' => __('', 'blockonomics-bitcoin-payments'),
                'type' => 'text',
                'label' => __('Under Payment', 'blockonomics-bitcoin-payments'),
                'description' => __('Underpayment Slack %.Allow payments that are off by a small percentage', 'blockonomics-bitcoin-payments'),
                'default' => get_option('blockonomics_underpayment_slack'),
            ),
            'no_javascript' => array(
                'title' => __( '', 'blockonomics-bitcoin-payments' ),
                'type' => 'checkbox',
                'label' => __('No JS ', 'blockonomics-bitcoin-payments'),
                'description' => __('To get your API Key, click Get Started for Free on https://blockonomics.co/merchants', 'blockonomics-bitcoin-payments'),
                'default' => get_option('blockonomics_nojs') == 1 ? 'yes' : 'no',
            ),
            'partialpayment' => array(
                'title' => __('', 'blockonomics-bitcoin-payments'),
                'type' => 'checkbox',
                'label' => __('Partial Payment ', 'blockonomics-bitcoin-payments'),
                'description' => __('Allow customer to pay order via multiple payement  ', 'blockonomics-bitcoin-payments'),
                'default' => get_option('blockonomics_partial_payments') == 1 ? 'yes' : 'no',
            ),
            'network_confirmation' => array(
                'title' => __('', 'blockonomics-bitcoin-payments'),
                'type' => 'select',
                'description' => __('Network Confirmations required for payment to complete', 'blockonomics-bitcoin-payments'),
                'default' => __(get_option('blockonomics_network_confirmation'), 'blockonomics-bitcoin-payments'),
                'options' => array(
                    '2' => __('2(Recommended)','blockonomics-bitcoin-payments'),
                    '1' => __('1','blockonomics-bitcoin-payments'),
                    '0' => __('0','blockonomics-bitcoin-payments'),
                ),
            ),
            'callBackurls' => array(
                'title' => __(' ', 'blockonomics-bitcoin-payments'),
                'type' => 'text',  // Ensure there's a handler for this type elsewhere in your code.
                'description' => __('Callback URL.You need this callback URL to setup multiple stores ', 'blockonomics-bitcoin-payments'),
                'default' => __($this->get_callback_url(), 'blockonomics-bitcoin-payments'),
                'disabled' => true,
            ),

        );
    }
    function display_admin_message($msg, $type)
    {
        add_settings_error('option_notice', 'option_notice', $msg, $type);
    }

    function get_started_message($domain = '', $label_class = 'bnomics-options-intendation', $message = 'To configure')
    {
        echo 
        "<label class=$label_class>".
            __("$message, click <b> Get Started for Free </b> on ", 'blockonomics-bitcoin-payments').
            '<a href="https://'.$domain.'blockonomics.co/merchants" target="_blank">'.
                __('https://'.$domain.'blockonomics.co/merchants', 'blockonomics-bitcoin-payments').
            '</a>
        </label>';
    }

    function success_message()
    {
        echo '<td colspan="2"class="notice notice-success bnomics-test-setup-message">'.__("Success", 'blockonomics-bitcoin-payments').'</td>';
    }

    function error_message($error)
    {
        echo 
        '<td colspan="2" class="notice notice-error bnomics-test-setup-message">'.$error.'.<br/>'.
            __("Please consult ", 'blockonomics-bitcoin-payments').
            '<a href="http://help.blockonomics.co/support/solutions/articles/33000215104-unable-to-generate-new-address" target="_blank">'.
            __("this troubleshooting article", 'blockonomics-bitcoin-payments').'</a>.
        </td>';
    }
    
    function temp_wallet_amount() {
        $btc_enabled = get_option("blockonomics_btc");
    
        // If BTC is enabled or the 'blockonomics_btc' option has never been set (which returns false), proceed.
        if ($btc_enabled || $btc_enabled === false) {
            // Fetch the amount in the smallest unit from the database and convert to BTC
            $temp_withdraw_amount = get_option('blockonomics_temp_withdraw_amount', 0); // Default to 0 if the option doesn't exist
            $total_received = $temp_withdraw_amount / 1.0e8;
    
            // Format the total received to ensure consistent decimal places (e.g., "0.00")
            $total_received_formatted = number_format($total_received, 8, '.', '');
            $total_received_formatted = 00;
            // Update the 'tempwallet' option with the formatted total received amount
            update_option("tempwallet", $total_received_formatted);

            return $total_received_formatted;
        }
    }
    private function get_callback_url()
    {
        $callback_secret = get_option('blockonomics_callback_secret');
        $callback_url = WC()->api_request_url('WC_Gateway_Blockonomics');
        $callback_url = add_query_arg('secret', $callback_secret, $callback_url);
        return $callback_url;
    }
    public function generate_heading_html($key, $value){
        ob_start();
        ?>
        <tr valign="top">
        <th scope="row" class="titledesc"></th>
        <td class="forminp">   
        <p>Setup Store on Blockonomics and paste the API key here </p>
        </td>
         </tr>
        <?php
        return ob_get_clean();
    }

    public function generate_currency_html( $key, $value ) {
        ob_start();
        
        ?>
            <tr valign="top">
                <th scope="row" class="titledesc"></th>
                <td class="forminp">
                <div class="bnomics-options-small-margin-top">
                        <p>Test the setup to ensure it is working correctly </p>
                        <input type="submit" class="button-primary" value="<?php echo __("Test Setup", 'blockonomics-bitcoin-payments')?>" />
                        <input type="hidden" name="page_options" value="blockonomics_bch, blockonomics_btc" />
                        <input type="hidden" name="action" value="update" />
                    </div>
                </td>
		    </tr>
            
            <?php wp_nonce_field('update-options');
            $blockonomics = new Blockonomics;
            $setup_errors = $blockonomics->testSetup();
            $btc_error = isset($setup_errors['btc']) ? $setup_errors['btc'] : 'false';
            $bch_error = isset($setup_errors['bch']) ? $setup_errors['bch'] : 'false';
            if (get_option('blockonomics_btc') == '1' && isset($btc_error)):
                            if ($btc_error):
                                error_message($btc_error);
                            else:
                                success_message();
                            endif;
                        endif; 
            //get_started_message('bch.');
            $bch_enabled = get_option("blockonomics_bch");
            if ($bch_enabled == '1' && isset($bch_error)):
                if ($bch_error):
                     error_message($bch_error);
                else:
                    success_message();
                endif; 
            endif;           
                        ?>
        <?php
        return ob_get_clean();
    }


    public function process_admin_options()
    {
        if (!parent::process_admin_options()) {
            return false;
        }
        update_option('blockonomics_bch', parent::get_option('bch_enabled')== 'yes' ? 1 : 0);
        update_option('blockonomics_btc', parent::get_option('btc_enabled')== 'yes' ? 1 : 0);
        update_option('blockonomics_timeperiod', parent::get_option('timeperiod'));
        update_option('blockonomics_margin', parent::get_option('extra_margin'));
        update_option('blockonomics_underpayment_slack', parent::get_option('underpayment_slack'));
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

        include_once 'Blockonomics.php';
        $blockonomics = new Blockonomics;

        if ($finish_order) {
            $order_id = $blockonomics->decrypt_hash($finish_order);
            $blockonomics->redirect_finish_order($order_id);
        }else if ($get_amount && $crypto) {
            $order_id = $blockonomics->decrypt_hash($get_amount);
            $blockonomics->get_order_amount_info($order_id, $crypto);
        }else if ($secret && $addr && isset($status) && $value && $txid) {
            $blockonomics->process_callback($secret, $addr, $status, $value, $txid, $rbf);
        }

        exit();
    }
}
