<?php
$lite_version = get_option('blockonomics_lite');
if($lite_version){
?>
  <link rel="stylesheet" type="text/css" href="<?php echo plugins_url('css/order.css', dirname(__FILE__));?>">
  <link rel="stylesheet" type="text/css" href="<?php echo plugins_url('css/cryptofont/cryptofont.min.css', dirname(__FILE__));?>">
  <link rel="stylesheet" type="text/css" href="<?php echo plugins_url('css/icons/icons.css', dirname(__FILE__));?>">
<?php
}else{
  get_header();
}
?>
  <div>
  <div class="bnomics-order-container">
    <?php
      $orders = get_option('blockonomics_orders');
      $address = $_REQUEST["payment_check"];
      $order = $orders[$address];

      if ($order['status'] >= 0){
        wp_redirect('?wc-api=WC_Gateway_Blockonomics&finish_order=' . $address);
      }else if ($order['status'] == -1){
        echo '<div class="bnomics-order-id"><span class="bnomics-order-number">ORDER #'. $order['order_id'] .'</span></div><br><p>Payment has not been detected yet</p><a href="/?wc-api=WC_Gateway_Blockonomics&show_order='. $address.'">Click here to go back</a>';
      }else {
        echo '<div class="bnomics-order-id"><span class="bnomics-order-number">ORDER #'. $order['order_id'] .'</span></div><br><p>It seems that there is a problem with your payment, please contact support</p>';
      }
     ?>
  </div>
  </div>
  <?php
  if(!isset($lite_version)){
    get_footer();
  }
  ?>
