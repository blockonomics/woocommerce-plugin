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
        $this->method_description = __( 'Secure and user-friendly Bitcoin payment gateway for direct merchant transactions.', 'blockonomics-bitcoin-payments' );

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
    }


    public function init_form_fields() {
        require_once 'form_fields.php';
        $this->form_fields = FormFields::init_form_fields($this->get_callback_url());
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

    public function generate_text_html( $key, $data ) {
		$field_key = $this->get_field_key( $key );
		$defaults  = array(
			'title'             => '',
			'disabled'          => false,
			'class'             => '',
			'css'               => '',
			'placeholder'       => '',
			'type'              => 'text',
			'desc_tip'          => false,
			'description'       => '',
			'custom_attributes' => array(),
		);

		$data = wp_parse_args( $data, $defaults );

		ob_start();
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr( $field_key ); ?>"><?php echo wp_kses_post( $data['title'] ); ?> <?php echo $this->get_tooltip_html( $data ); // WPCS: XSS ok. ?></label>
			</th>
			<td class="forminp">
				<fieldset>
                    <?php if ( ! empty( $data['subtitle'] ) ) : ?>
                        <p style="margin-bottom: 8px;">
                            <strong>
                                <?php echo wp_kses_post( $data['subtitle'] ); ?>
                            </strong>
                        </p>
                    <?php endif; ?>
					<legend class="screen-reader-text"><span><?php echo wp_kses_post( $data['title'] ); ?></span></legend>
					<input class="input-text regular-input <?php echo esc_attr( $data['class'] ); ?>" type="<?php echo esc_attr( $data['type'] ); ?>" name="<?php echo esc_attr( $field_key ); ?>" id="<?php echo esc_attr( $field_key ); ?>" style="<?php echo esc_attr( $data['css'] ); ?>" value="<?php echo esc_attr( $this->get_option( $key ) ); ?>" placeholder="<?php echo esc_attr( $data['placeholder'] ); ?>" <?php disabled( $data['disabled'], true ); ?> <?php echo $this->get_custom_attribute_html( $data ); // WPCS: XSS ok. ?> />
                    <?php echo $this->get_description_html( $data ); // WPCS: XSS ok. ?>
				</fieldset>
			</td>
		</tr>
		<?php

		return ob_get_clean();
	}

    public function generate_checkbox_html( $key, $data ) {
		$field_key = $this->get_field_key( $key );
		$defaults  = array(
			'title'             => '',
			'label'             => '',
			'disabled'          => false,
			'class'             => '',
			'css'               => '',
			'type'              => 'text',
			'desc_tip'          => false,
			'description'       => '',
			'custom_attributes' => array(),
		);

		$data = wp_parse_args( $data, $defaults );

		if ( ! $data['label'] ) {
			$data['label'] = $data['title'];
		}

		ob_start();
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr( $field_key ); ?>"><?php echo wp_kses_post( $data['title'] ); ?> <?php echo $this->get_tooltip_html( $data ); // WPCS: XSS ok. ?></label>
			</th>
			<td class="forminp">
				<fieldset>
                    <?php if ( ! empty( $data['subtitle'] ) ) : ?>
                        <p style="margin-bottom: 8px;">
                            <strong>
                                <?php echo wp_kses_post( $data['subtitle'] ); ?>
                            </strong>
                        </p>
                    <?php endif; ?>
					<legend class="screen-reader-text"><span><?php echo wp_kses_post( $data['title'] ); ?></span></legend>
					<label for="<?php echo esc_attr( $field_key ); ?>">
					<input <?php disabled( $data['disabled'], true ); ?> class="<?php echo esc_attr( $data['class'] ); ?>" type="checkbox" name="<?php echo esc_attr( $field_key ); ?>" id="<?php echo esc_attr( $field_key ); ?>" style="<?php echo esc_attr( $data['css'] ); ?>" value="1" <?php checked( $this->get_option( $key ), 'yes' ); ?> <?php echo $this->get_custom_attribute_html( $data ); // WPCS: XSS ok. ?> /> <?php echo wp_kses_post( $data['label'] ); ?></label><br/>
					<?php echo $this->get_description_html( $data ); // WPCS: XSS ok. ?>
				</fieldset>
			</td>
		</tr>
		<?php

		return ob_get_clean();
	}

    public function generate_select_html( $key, $data ) {
		$field_key = $this->get_field_key( $key );
		$defaults  = array(
			'title'             => '',
			'disabled'          => false,
			'class'             => '',
			'css'               => '',
			'placeholder'       => '',
			'type'              => 'text',
			'desc_tip'          => false,
			'description'       => '',
			'custom_attributes' => array(),
			'options'           => array(),
		);

		$data  = wp_parse_args( $data, $defaults );
		$value = $this->get_option( $key );

		ob_start();
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr( $field_key ); ?>"><?php echo wp_kses_post( $data['title'] ); ?> <?php echo $this->get_tooltip_html( $data ); // WPCS: XSS ok. ?></label>
			</th>
			<td class="forminp">
				<fieldset>
                    <?php if ( ! empty( $data['subtitle'] ) ) : ?>
                        <p style="margin-bottom: 8px;">
                            <strong>
                                <?php echo wp_kses_post( $data['subtitle'] ); ?>
                            </strong>
                        </p>
                    <?php endif; ?>
					<legend class="screen-reader-text"><span><?php echo wp_kses_post( $data['title'] ); ?></span></legend>
					<select class="select <?php echo esc_attr( $data['class'] ); ?>" name="<?php echo esc_attr( $field_key ); ?>" id="<?php echo esc_attr( $field_key ); ?>" style="<?php echo esc_attr( $data['css'] ); ?>" <?php disabled( $data['disabled'], true ); ?> <?php echo $this->get_custom_attribute_html( $data ); // WPCS: XSS ok. ?>>
						<?php foreach ( (array) $data['options'] as $option_key => $option_value ) : ?>
							<?php if ( is_array( $option_value ) ) : ?>
								<optgroup label="<?php echo esc_attr( $option_key ); ?>">
									<?php foreach ( $option_value as $option_key_inner => $option_value_inner ) : ?>
										<option value="<?php echo esc_attr( $option_key_inner ); ?>" <?php selected( (string) $option_key_inner, esc_attr( $value ) ); ?>><?php echo esc_html( $option_value_inner ); ?></option>
									<?php endforeach; ?>
								</optgroup>
							<?php else : ?>
								<option value="<?php echo esc_attr( $option_key ); ?>" <?php selected( (string) $option_key, esc_attr( $value ) ); ?>><?php echo esc_html( $option_value ); ?></option>
							<?php endif; ?>
						<?php endforeach; ?>
					</select>
					<?php echo $this->get_description_html( $data ); // WPCS: XSS ok. ?>
				</fieldset>
			</td>
		</tr>
		<?php

		return ob_get_clean();
	}

    public function generate_number_html( $key, $data ) {
		$data['type'] = 'number';
		return $this->generate_text_html( $key, $data );
	}


    public function generate_temp_wallet_html($key, $data){

        $field_key = $this->get_field_key( $key );
        $btc_enabled = get_option("blockonomics_btc");
        $total_received_formatted = 00;
        $apikey = get_option('blockonomics_api_key');

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
                        <div style="font-size: 14px; font-weight: bold;margin-bottom: 10px;">
                            <?php echo $apikey ? "Store wallet": "Temporary Wallet"; ?>
                        </div>
                        <?php if ( ! empty( $apikey ) ): ?>
                            <div style="font-size: 14px; color: #646970; margin-bottom: 10px;">
                                Your Blockonomics store is successfully configured with your wallet.
                                You are all set to receive funds directly into your wallet.
                            </div>
                        <?php else  : ?>
                            <div style="font-size: 14px; color: #646970; margin-bottom: 10px;">
                                Accepting fund with temporary wallet. You can setup a
                                Blockonomics store to use your own wallet.
                            </div>
                            <a href="https://help.blockonomics.co/support/solutions/articles/33000248575-wordpress-woocommerce-integration-faq-" target="_blank" style="color: green; text-decoration: none; font-size: 14px;">Learn More</a>
                        <?php endif; ?>
                    </div>
                    <?php if (floatval($total_received_formatted) > 0): ?>
                        <input id="temp-wallet-input" type="text" style="width: 200px; margin-left:50px;text-align:right;"value="<?php echo __($total_received_formatted, 'blockonomics-bitcoin-payments') ?> BTC" readonly>
                    <?php endif; ?>
                </div>
			</td>
		</tr>
        <?php
        return ob_get_clean();
    }
    public function generate_testsetup_html() {
        ob_start();
        ?>
       <tr valign="top">
            <td class="forminp">
                <div class="bnomics-options-margin-top">
                    <div>
                        <p style="margin-bottom: 8px;">
                            <strong>
                                Test Setup
                            </strong>
                        </p>
                        <div class="bnomics-options-margin-top">
                            Test the setup to ensure it is working correctly
                        </div>
                        <div>
                            <?php
                                $blockonomics = new Blockonomics;
                                $cryptos = $blockonomics->getSupportedCurrencies();
                                foreach ($cryptos as $currencyCode => $crypto) {
                                    echo '<p class="notice notice-success ' . $currencyCode . '-success-notice" style="display:none;width:400px;">'.$currencyCode.': Success</p>';
                                    echo '<p class="notice notice-error ' . $currencyCode . '-error-notice" style="width:400px;display:none;">';
                                    echo $currencyCode.' :';
                                    echo '<span class="errorText"></span><br />';
                                    echo 'Please consult <a href="http://help.blockonomics.co/support/solutions/articles/33000215104-unable-to-generate-new-address" target="_blank">this troubleshooting article</a>.';
                                    echo '</p>';
                                }
                            ?>
                        </div>
                        <div class="flex-display">
                            <input type="button" id="test-setup-btn" class="button-primary" value="<?php echo __("Test Setup", 'blockonomics-bitcoin-payments') ?>" />
                            <div class="test-spinner"></div>
                        </div>
                    </div>
                </td>
        </tr>
        <?php
        return ob_get_clean();
    }
    public function generate_apikey_html($key, $data) {
        $field_key = $this->get_field_key( $key );
        $defaults  = array(
            'title'             => '',
            'disabled'          => false,
            'class'             => '',
            'css'               => '',
            'placeholder'       => '',
            'type'              => 'text',
            'desc_tip'          => false,
            'description'       => '',
            'custom_attributes' => array(),
        );

        $data = wp_parse_args( $data, $defaults );

        ob_start();
        ?>
        
        <tr valign="top">
            <th scope="row" class="titledesc" rowspan="2">
                <label for="<?php echo esc_attr( $field_key ); ?>"><?php echo wp_kses_post( $data['title'] ); ?> <?php echo $this->get_tooltip_html( $data ); // WPCS: XSS ok. ?></label>
            </th>

            <td class="forminp">
                <fieldset>
                    <?php if ( ! empty( $data['subtitle'] ) ) : ?>
                        <p style="margin-bottom: 8px;">
                            <strong>
                                <?php echo wp_kses_post( $data['subtitle'] ); ?>
                            </strong>
                        </p>
                    <?php endif; ?>
                    <?php echo $this->get_description_html( $data ); // WPCS: XSS ok. ?>
                    <legend class="screen-reader-text"><span><?php echo wp_kses_post( $data['title'] ); ?></span></legend>
                    <input class="input-text regular-input <?php echo esc_attr( $data['class'] ); ?>" type="text" name="<?php echo esc_attr( $field_key ); ?>" id="<?php echo esc_attr( $field_key ); ?>" style="<?php echo esc_attr( $data['css'] ); ?>" value="<?php echo esc_attr( $this->get_option( $key ) ); ?>" placeholder="<?php echo esc_attr( $data['placeholder'] ); ?>" <?php disabled( $data['disabled'], true ); ?> <?php echo $this->get_custom_attribute_html( $data ); // WPCS: XSS ok. ?> />
                </fieldset>

                <button name="save" id="save-api-key-button" class="button-primary woocommerce-save-button" type="submit" value="Save changes">Save API key</button>
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
        update_option('blockonomics_margin', (int)parent::get_option('extra_margin'));
        update_option('blockonomics_underpayment_slack', (int)parent::get_option('underpayment_slack'));
        update_option('blockonomics_partial_payments', parent::get_option('partial_payment') == 'yes' ? 1 : 0);
        update_option('blockonomics_api_key', parent::get_option('api_key'));
        update_option('blockonomics_nojs', parent::get_option('no_javascript') == 'yes' ? 1 : 0);

        parent::update_option('call_backurls', $this->get_callback_url());
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
        } else if ($get_amount && $crypto) {
            $order_id = $blockonomics->decrypt_hash($get_amount);
            $blockonomics->get_order_amount_info($order_id, $crypto);
        } else if ($secret && $addr && isset($status) && $value && $txid) {
            $blockonomics->process_callback($secret, $addr, $status, $value, $txid, $rbf);
        }

        exit();
    }
}
