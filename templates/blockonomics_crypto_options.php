<?php
$blockonomics = new Blockonomics;
$cryptos = $blockonomics->getActiveCurrencies();
$order_id = $_REQUEST['select_crypto'];
$order_url = $blockonomics->get_parameterized_wc_url(array('show_order'=>$order_id))
?>
<div class="woocommerce bnomics-order-container">
  <div class="bnomics-select-container">
    <tr>
      <?php
      foreach ($cryptos as $code => $crypto) {
        $order_url = add_query_arg('crypto', $code, $order_url);
      ?>
        <form action="<?php echo $order_url;?>" method="get">
          <input type="hidden" name="show_order" value="<?php echo $order_id;?>" />
          <input type="hidden" name="crypto" value="<?php echo $code;?>" />
          
          <button class="bnomics-select-options woocommerce-button button">
            <span class="bnomics-icon-<?php echo $code;?> bnomics-rotate-<?php echo $code;?>"></span>
            <span class="vertical-line">
              <?=__('Pay with', 'blockonomics-bitcoin-payments')?>
              <?php echo $crypto['name'];?>
            </span>
          </button>
        </form>
      <?php 
      }
      ?>
    </tr>
  </div>
</div>
