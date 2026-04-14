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
        $this->method_description = __( 'Accept crypto payments. Payments go directly to your wallet.', 'blockonomics-bitcoin-payments' );

        include_once 'Blockonomics.php';
        $blockonomics = new Blockonomics;
        $this->icon = plugins_url('img', dirname(__FILE__)) . '/logo.png';

        // control icon size in WooCommerce checkout payment method list, file is 100x100 we want 36x36
        add_filter('woocommerce_gateway_icon', array($this, 'resize_payment_icon'), 10, 2);

        $this->has_fields        = false;
        $this->order_button_text = __('Pay with crypto', 'blockonomics-bitcoin-payments');
    
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

    /* Resize the payment gateway icon in WooCommerce checkout.
     *
     * @param string $icon_html The icon HTML.
     * @param string $gateway_id The gateway ID.
     * @return string Modified icon HTML with max-height style.
     */
    public function resize_payment_icon($icon_html, $gateway_id) {
        if ($gateway_id === $this->id) {
            // 36x36 looks good enough
            $icon_html = str_replace('<img', '<img style="max-height:36px;width:auto;"', $icon_html);
        }
        return $icon_html;
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
        if (isset($data['add_divider']) && $data['add_divider']) {
        ?>
            <tr valign="top">
                <td colspan="2"><hr /></td>
            </tr>
        <?php } ?>
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

        $divider_html = '';
        if (isset($data['add_divider']) && $data['add_divider']) {
            ob_start();
            ?>
            <tr valign="top">
                <td colspan="2"><hr /></td>
            </tr>
            <?php
            $divider_html = ob_get_clean();
        }
		return $divider_html . $this->generate_text_html( $key, $data );
	}

    public function generate_testsetup_html() {
        ob_start();
        ?>
       <tr valign="top">
            <td class="forminp">
                <div class="bnomics-options-margin-top">
                        <div>
                            <?php
                                echo '<p class="notice notice-success" style="display:none;">';
                                echo '<span class="successText"></span><br />';
                                echo '</p>';
                                echo '<p class="notice notice-error" style="display:none;">';
                                echo '<span class="errorText"></span><br />';
                                echo '</p>';
                            ?>
                        </div>
                        <div class="flex-display">
                            <input type="button" id="test-setup-btn" class="button-primary" value="<?php echo __("Test Setup", 'blockonomics-bitcoin-payments') ?>" />
                            <div class="test-spinner"></div>
                        </div>
                        <div id="test-setup-notification-box">
                            <span class="text">
                                Settings have not been saved, please save the changes before testing the setup.
                            </span>
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
            <td colspan="2"><hr /></td>
        </tr>
        
        <tr valign="top" id="apikey-row">
            <th scope="row" class="titledesc" rowspan="2">
                <label for="<?php echo esc_attr( $field_key ); ?>"><?php echo wp_kses_post( $data['title'] ); ?> <?php echo $this->get_tooltip_html( $data ); // WPCS: XSS ok. ?></label>
            </th>

            <td class="forminp">
                <fieldset>
                    <p id="store-name-display" style="margin-bottom: 8px;<?php echo empty($data['subtitle']) ? 'display:none;' : ''; ?>">
                        <strong><?php echo wp_kses_post( $data['subtitle'] ); ?></strong>
                    </p>
                    <?php echo $this->get_description_html( $data ); // WPCS: XSS ok. ?>
                    <legend class="screen-reader-text"><span><?php echo wp_kses_post( $data['title'] ); ?></span></legend>
                    <input class="input-text regular-input <?php echo esc_attr( $data['class'] ); ?>" type="text" name="<?php echo esc_attr( $field_key ); ?>" id="<?php echo esc_attr( $field_key ); ?>" style="<?php echo esc_attr( $data['css'] ); ?>" value="<?php echo esc_attr( $this->get_option( $key ) ); ?>" placeholder="<?php echo esc_attr( $data['placeholder'] ); ?>" <?php disabled( $data['disabled'], true ); ?> <?php echo $this->get_custom_attribute_html( $data ); // WPCS: XSS ok. ?> />
                </fieldset>

                <!-- <button name="save" id="save-api-key-button" class="button-primary woocommerce-save-button" type="submit" value="Save changes">Save API key</button> -->
                <div id="api-key-notification-box">
                    <span class="text">
                        Please enter a valid API key.
                    </span>
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
        $supportedCurrencies = $blockonomics->getSupportedCurrencies();

        foreach ($supportedCurrencies as $code => $currency) {
            if ($code === 'btc') {
                update_option('blockonomics_' . $code, 1);
            } elseif ($code === 'bch') {
                $isEnabled = $this->get_option('enable_bch') === 'yes' ? 1 : 0;
                update_option('blockonomics_' . $code, $isEnabled);
            }
        }
        update_option('blockonomics_bitcoin_discount', floatval($this->get_option('bitcoin_discount')));
        update_option('blockonomics_margin', floatval($this->get_option('extra_margin')));
        update_option('blockonomics_underpayment_slack', floatval($this->get_option('underpayment_slack')));
        update_option('blockonomics_partial_payments', $this->get_option('partial_payment') == 'yes' ? 1 : 0);
        update_option('blockonomics_api_key', $this->get_option('api_key'));
        update_option('blockonomics_accent_color', sanitize_hex_color($this->get_option('accent_color')) ?: '');
        update_option('blockonomics_nojs', $this->get_option('no_javascript') == 'yes' ? 1 : 0);
        update_option('blockonomics_network_confirmation', $this->get_option('network_confirmation'));
        $this->update_option('call_backurls', $this->get_callback_url());

        // Fetch and cache the store_uid for the checkout widget.
        // Re-instantiate so the constructor picks up the freshly-saved api_key.
        $blockonomics_fresh = new Blockonomics;
        $store_uid = $blockonomics_fresh->get_store_uid($this->get_callback_url());
        if ($store_uid) {
            update_option('blockonomics_store_uid', $store_uid);
        }

        return true;
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
        include_once 'Blockonomics.php';
        $blockonomics = new Blockonomics;

        // New checkout flow: Blockonomics sends a POST with a JSON body containing platform_order_id.
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $raw_body = file_get_contents('php://input');
            if (!empty($raw_body)) {
                $json_body = json_decode($raw_body, true);
                if (json_last_error() === JSON_ERROR_NONE && isset($json_body['wp_order_id'])) {
                    $blockonomics->process_checkout_callback($json_body);
                    // process_checkout_callback() always calls exit()
                }
            }
        }

        $crypto = isset($_GET["crypto"]) ? sanitize_key($_GET['crypto']) : "";
        $finish_order = isset($_GET["finish_order"]) ? sanitize_text_field(wp_unslash($_GET['finish_order'])) : "";
        $get_amount = isset($_GET['get_amount']) ? sanitize_text_field(wp_unslash($_GET['get_amount'])) : "";
        $secret = isset($_GET['secret']) ? sanitize_text_field(wp_unslash($_GET['secret'])) : "";
        $addr = isset($_GET['addr']) ? sanitize_text_field(wp_unslash($_GET['addr'])) : "";
        $status = isset($_GET['status']) ? intval($_GET['status']) : "";
        $value = isset($_GET['value']) ? absint($_GET['value']) : "";
        $txid = isset($_GET['txid']) ? sanitize_text_field(wp_unslash($_GET['txid'])) : "";
        $rbf = isset($_GET['rbf']) ? wp_validate_boolean(intval(wp_unslash($_GET['rbf']))) : "";
        $txhash = isset($_GET["txhash"]) ? sanitize_text_field(wp_unslash($_GET['txhash'])) : "";

        if ($finish_order) {
            $order_id = $blockonomics->decrypt_hash($finish_order);
            if ($crypto == "usdt"){
                $blockonomics->process_token_order($order_id, $crypto, $txhash);
            }
            $blockonomics->redirect_finish_order($order_id);
        } else if ($get_amount && $crypto) {
            $order_id = $blockonomics->decrypt_hash($get_amount);
            $blockonomics->get_order_amount_info($order_id, $crypto);
        } else if ($secret && $addr && isset($status) && $value && $txid) {
            $blockonomics->process_callback($secret, $crypto, $addr, $status, $value, $txid, $rbf);
        }

        exit();
    }
}
