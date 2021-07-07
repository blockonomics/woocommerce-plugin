<?php
$blockonomics = new Blockonomics;
$cryptos = $blockonomics->getActiveCurrencies();
$order_id = $_REQUEST['select_crypto'];
$order_url = $blockonomics->get_parameterized_wc_url(array('show_order'=>$order_id))
?>
<div class="woocommerce bnomics-order-container">
  <div class="bnomics-select-container">
    <table width="100%">
      <tr>
        <?php
        foreach ($cryptos as $code => $crypto) {
          $order_url = add_query_arg('crypto', $code, $order_url);
        ?>
          <button onclick="window.location='<?php echo $order_url;?>'" class="bnomics-select-options woocommerce-button button">
            <span class="bnomics-icon-<?php echo $code;?> bnomics-rotate-<?php echo $code;?>"></span>
            <span class="vertical-line">
              <?=__('Pay With', 'blockonomics-bitcoin-payments')?>
              <?php echo $crypto['name'];?>
            </span>
        </button>
        <?php 
        }
        ?>

      </tr>
    </table>
  </div>
</div>
