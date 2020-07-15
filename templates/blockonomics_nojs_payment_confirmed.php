<div>
  <div class="bnomics-order-container">
    <?php
      $orders = get_option('blockonomics_orders');
      $order_id = $_REQUEST['payment_check'];
      $crypto = $_REQUEST['crypto'];
      foreach ($orders[$order_id] as $addr => $order){
        if ($order['crypto'] == $crypto) break;
      }

      if ($order['status'] >= 0){
        wp_redirect('?wc-api=WC_Gateway_Blockonomics&finish_order=' . $order['order_id']);
      }else if ($order['status'] == -1){
        echo '<div class="bnomics-order-id"><span class="bnomics-order-number">ORDER #'. $order['order_id'] .'</span></div><br><p>Payment has not been detected yet</p><a href="?show_order='. $order['order_id'].'&crypto='.$order['crypto'].'">Click here to go back</a>';
      }else {
        echo '<div class="bnomics-order-id"><span class="bnomics-order-number">ORDER #'. $order['order_id'] .'</span></div><br><p>It seems that there is a problem with your payment, please contact support</p>';
      }
     ?>
  </div>
</div>