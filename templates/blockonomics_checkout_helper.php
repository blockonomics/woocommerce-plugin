<?php

  $blockonomics = new Blockonomics;
  $is_no_js = $blockonomics->is_nojs_active();

  // Display Checkout Page
  if ($order['satoshi'] < 10000){
    $order_amount = rtrim(number_format($order['satoshi']/1.0e8, 8),0);
  } else {
    $order_amount = $order['satoshi']/1.0e8;
  }

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
  $template_name = ($is_no_js) ? "blockonomics_nojs_checkout" : "blockonomics_checkout";
  
  $context = array(
    'order' => $order,
    'order_id' => $order_id,
    'order_amount' => $order_amount,
    'crypto' => $crypto,
    'payment_uri' => $payment_uri,
    'qrcode_url' => $qrcode_url
  );
  
  $blockonomics->set_template_context($context);

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
?>
