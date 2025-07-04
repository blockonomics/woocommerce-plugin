<?php

defined( 'ABSPATH' ) || exit;

class FormFields {
    public static function init_form_fields($callback_url = '') {
        $blockonomics = new Blockonomics;
        $cryptos = $blockonomics->getSupportedCurrencies();

        // Get the current API key and any stored metadata
        $api_key = get_option('blockonomics_api_key');
        $store_name = get_option('blockonomics_store_name');
        // $enabled_cryptos = get_option('blockonomics_enabled_cryptos', array());
        if ($store_name) {
            $subtitle = $store_name;
        } else {
            $subtitle = __('<br>', 'blockonomics-bitcoin-payments');
        }

        $form_fields = array(
            'enabled' => array(
                'title' => __('Checkout<p class="block-title-desc">Payment method settings for the woocomerce checkout page</p>', 'blockonomics-bitcoin-payments'),
                'subtitle' => __('Enable Blockonomics plugin', 'blockonomics-bitcoin-payments'),
                'type' => 'checkbox',
                'label' => __('Enable Blockonomics as a payment method during checkout', 'blockonomics-bitcoin-payments'),
                'default' =>  get_option('blockonomics_api_key') ? 'yes' : 'no' 
            ),
            'title' => array(
                'subtitle' => __('Title', 'blockonomics-bitcoin-payments'),
                'type' => 'text',
                'description' => __('Payment method for <i>bitcoin</i> displayed to the user during checkout.', 'blockonomics-bitcoin-payments'),
                'default' => __('Bitcoin', 'blockonomics-bitcoin-payments'),
                'placeholder' => __('Title', 'blockonomics-bitcoin-payments')
            ),
            'description' => array(
                'subtitle' => __('Description', 'blockonomics-bitcoin-payments'),
                'type' => 'text',
                'description' => __('Payment method <i>description</i> displayed to the user during checkout.', 'blockonomics-bitcoin-payments'),
                'default' => '',
                'placeholder' => __('Description', 'blockonomics-bitcoin-payments')
            ),
            'api_key' => array(
                'title' => __('
                    Store
                    <p class="block-title-desc">To enable various cryptos go to <a href="https://blockonomics.co/dashboard#/store" target="_blank">Stores</a></p>
                    ', 'blockonomics-bitcoin-payments'),
                'subtitle' =>  $subtitle,
                'type' => 'apikey',
                'default' => get_option('blockonomics_api_key'),
                'placeholder' => __('API key', 'blockonomics-bitcoin-payments'),
            ),
            'testsetup' => array(
                'id'    => 'testsetup',
                'type'  => 'testsetup',
            )
        );


        $form_fields['bitcoin_discount'] = array(
            'title' => __('Advanced<p class="block-title-desc">Setting for advanced control</p>', 'blockonomics-bitcoin-payments'),
            'type' => 'number',
            'description' => __('Give customers discount for paying via bitcoin(0-20)', 'blockonomics-bitcoin-payments'),
            'subtitle' => __('Bitcoin Payment Discount %', 'blockonomics-bitcoin-payments'),
            'default' => get_option('blockonomics_bitcoin_discount', 0),
            'add_divider' => true,
            'placeholder' => __('Bitcoin Payment Discount %', 'blockonomics-bitcoin-payments'),
            'custom_attributes' => ['step' => '0.1', 'min' => '0', 'max' => '20']
        );
        $form_fields['extra_margin'] = array(
            'title' => __('', 'blockonomics-bitcoin-payments'),
            'type' => 'number',
            'description' => __('Increase live fiat to BTC rate by small percent (0-4%)', 'blockonomics-bitcoin-payments'),
            'subtitle' => __('Extra Currency Rate Margin %', 'blockonomics-bitcoin-payments'),
            'default' => get_option('blockonomics_extra_margin', 0),
            'placeholder' => __('Extra Currency Rate Margin %', 'blockonomics-bitcoin-payments'),
            'custom_attributes' => ['step' => '0.01', 'min' => '0', 'max' => '4']
        );
        $form_fields['underpayment_slack'] = array(
            'title' => __('', 'blockonomics-bitcoin-payments'),
            'type' => 'number',
            'label' => __('Under Payment', 'blockonomics-bitcoin-payments'),
            'description' => __('Allow payments that are off by a small percentage', 'blockonomics-bitcoin-payments'),
            'subtitle' => __('Underpayment Slack %', 'blockonomics-bitcoin-payments'),
            'default' =>  get_option('blockonomics_underpayment_slack', 0),
            'placeholder' => __('Underpayment Slack %', 'blockonomics-bitcoin-payments'),
            'custom_attributes' => ['step' => '0.1', 'min' => '0', 'max' => '20']
        );
        $form_fields['enable_bch'] = array(
            'title' => __('', 'blockonomics-bitcoin-payments'),
            'type' => 'checkbox',
            'subtitle' => __('Enable Bitcoin Cash (BCH)', 'blockonomics-bitcoin-payments'),
            'label' => __('Allow customers to pay with Bitcoin Cash', 'blockonomics-bitcoin-payments'),
            'default' => 'no',
        );
        $form_fields['no_javascript'] = array(
            'title' => __('', 'blockonomics-bitcoin-payments'),
            'type' => 'checkbox',
            'subtitle' => __('No Javascript checkout page', 'blockonomics-bitcoin-payments'),
            'label' => __('Enable this if you have majority customer that uses tor like browser that blocks JS', 'blockonomics-bitcoin-payments'),
            'default' => get_option('blockonomics_nojs') == 1 ? 'yes' : 'no',
        );
        $form_fields['partial_payment'] = array(
            'title' => __('', 'blockonomics-bitcoin-payments'),
            'type' => 'checkbox',
            'subtitle' => __('Partial Payments', 'blockonomics-bitcoin-payments'),
            'label' => __('Allow customer to pay order via multiple payment  ', 'blockonomics-bitcoin-payments'),
            'default' => $blockonomics->is_partial_payments_active() ? 'yes' : 'no',
        );
        $form_fields['network_confirmation'] = array(
            'title' => __('', 'blockonomics-bitcoin-payments'),
            'subtitle' => __('Network Confirmations', 'blockonomics-bitcoin-payments'),
            'type' => 'select',
            'description' => __('Network Confirmations required for payment to complete', 'blockonomics-bitcoin-payments'),
            'default' => get_option('blockonomics_network_confirmation', 2),
            'options' => array(
                '2' => __('2(Recommended)', 'blockonomics-bitcoin-payments'),
                '1' => __('1', 'blockonomics-bitcoin-payments'),
                '0' => __('0', 'blockonomics-bitcoin-payments'),
            ),
        );
        $form_fields['call_backurls'] = array(
            'title' => __('', 'blockonomics-bitcoin-payments'),
            'type' => 'text',
            'description' => __('You need this callback URL to setup multiple stores', 'blockonomics-bitcoin-payments'),
            'subtitle' => __('Callback URL', 'blockonomics-bitcoin-payments'),
            'default' => $callback_url,
            'disabled' => true,
            'css' => 'width:100%;',
        );
        return $form_fields;
    }
}
