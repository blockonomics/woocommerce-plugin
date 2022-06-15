<?php
$blockonomics = new Blockonomics;
$crypto = isset($_REQUEST["crypto"]) ? sanitize_key($_REQUEST["crypto"]) : "";
$order_hash = isset($_REQUEST["show_order"]) ? sanitize_text_field(wp_unslash($_REQUEST["show_order"])) : "";
$order_id = $blockonomics->decrypt_hash($order_hash);
$order = $blockonomics->get_order_by_id_and_crypto($order_id, $crypto);

if ($order['status'] >= 0){
  // Payment is recevied
  $blockonomics->redirect_finish_order($order_id);
} else if ($order['status'] == -2) {
  // Partial payment is recevied
  $blockonomics->redirect_error_page($order_id, 'paid_amount_is_less');
} else {
  // Display Checkout page
  if($order['satoshi'] < 10000){
    $order_amount = rtrim(number_format($order['satoshi']/1.0e8, 8),0);
  } else{
    $order_amount = $order['satoshi']/1.0e8;
  }

  $cryptos = $blockonomics->getActiveCurrencies();
  $crypto = $cryptos[$crypto];
?>

<div id="blockonomics_checkout"></div>

<script type="text/javascript">
  let is_initialised = false

  setTimeout(() => {
    if (!is_initialised) 
      window.location.href = '<?php echo $blockonomics->get_order_error_url($order_id, 'display_error'); ?>'
  }, 30000);

  let blockonomics = new Blockonomics({
  	checkout_id: 'blockonomics_checkout',
  	crypto: '<?php echo $crypto['code']; ?>',
  	order_id: '<?php echo $order_id; ?>',
  	crypto_amount: <?php echo $order['satoshi']; ?>,
  	crypto_address: '<?php echo $order['address']; ?>',
  	fiat_currency: '<?php echo $order['currency']; ?>',
  	fiat_amount: <?php echo $order['value']; ?>,
  	finish_order_url: "<?php echo $blockonomics->get_wc_order_received_url($order_id); ?>",
  	time_period: <?php echo get_option('blockonomics_timeperiod', 10); ?>,
  	text_pay_amount: '<?=__('To pay, send exactly this '.strtoupper($crypto['code']).' amount', 'blockonomics-bitcoin-payments')?>',
  	text_pay_address: '<?=__('To this '.strtolower($crypto['name']).' address', 'blockonomics-bitcoin-payments')?>',
  	text_open_copied: '<?=__('Copied to clipboard', 'blockonomics-bitcoin-payments')?>',
  	text_open_wallet: '<?=__('Open in wallet', 'blockonomics-bitcoin-payments')?>',
    text_payment_expired: '<?=__('Payment Expired', 'blockonomics-bitcoin-payments')?>',
    text_try_again: '<?=__('Click here to try again', 'blockonomics-bitcoin-payments')?>',
    text_check_review: '<?=__('How do I pay? | Check reviews of this shop', 'blockonomics-bitcoin-payments')?>'
  });

  blockonomics.init();
  is_initialised = true;
</script>

<?php
}
