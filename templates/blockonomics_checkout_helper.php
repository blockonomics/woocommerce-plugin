<?php

$blockonomics = new Blockonomics;
$is_no_js = $blockonomics->is_nojs_active();
$crypto = isset($_REQUEST["crypto"]) ? sanitize_key($_REQUEST["crypto"]) : "";
$order_hash = isset($_REQUEST["show_order"]) ? sanitize_text_field(wp_unslash($_REQUEST["show_order"])) : "";
$order_id = $blockonomics->decrypt_hash($order_hash);
$order = $blockonomics->get_order_by_id_and_crypto($order_id, $crypto);

include(BLOCKONOMICS_PLUGIN_DIR.'templates/_blockonomics_error_helper.php');

if (!$has_error) {
  if ($order['status'] >= 0){
    // Payment is recevied
    $blockonomics->redirect_finish_order($order_id);
  }

  // Display Checkout Page
  if($order['satoshi'] < 10000){
    $order_amount = rtrim(number_format($order['satoshi']/1.0e8, 8),0);
  } else{
    $order_amount = $order['satoshi']/1.0e8;
  }

  $cryptos = $blockonomics->getActiveCurrencies();
  $crypto = $cryptos[$crypto];

  $payment_uri = $crypto['uri'] . ":" . $order['address'] . "?amount=" . $order_amount;
  $qrcode_url = $blockonomics->get_parameterized_wc_url(array('qrcode'=>$crypto['uri'] . ':' .$order['address'].'?amount='.$order_amount));
?>

<div id="blockonomics_checkout" <?php if($is_no_js){ ?>class="no-js"<? }?>>
  <div
    class="blockonomics-data" 
    data-crypto='<?php echo json_encode($crypto); ?>'
    data-crypto_address="<?php echo $order['address']; ?>"
    data-time_period="<?php echo get_option('blockonomics_timeperiod', 10); ?>"
    data-finish_order_url="<?php echo $blockonomics->get_wc_order_received_url($order_id); ?>"
    data-payment_uri="<?php echo $payment_uri; ?>"
  ></div>

<?php
    $template_name = "blockonomics_checkout";
    if ($is_no_js) {
      $template_name = "blockonomics_nojs_checkout";
    }
    
    set_query_var('order', $order);
    set_query_var('order_id', $order_id);
    set_query_var('order_amount', $order_amount);
    set_query_var('crypto', $crypto);
    set_query_var('payment_uri', $payment_uri);
    set_query_var('qrcode_url', $qrcode_url);

    if ($template = locate_template($template_name) ) {
      load_template($template_name);
    } else {
      load_template(BLOCKONOMICS_PLUGIN_DIR.'templates/'.$template_name.'.php');
    }
?>
  
</div>
<?php 
  if(!$is_no_js) {
?>
  
  <script type="text/javascript">
    let blockonomics = new Blockonomics();
    blockonomics.init();
  </script>

<?php
  }
}
