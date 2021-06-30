<?php
$blockonomics = new Blockonomics;
$cryptos = $blockonomics->getActiveCurrencies();
$order_id = $_REQUEST['select_crypto'];
$order_url = $blockonomics->get_parameterized_wc_url(array('show_order'=>$order_id))
?>
<div class="bnomics-order-container">
  <div class="bnomics-select-container">
    <table width="100%">
      <tr>
        <?php
        foreach ($cryptos as $code => $crypto) {
          $order_url = add_query_arg('crypto', $code, $order_url);
        ?>
          <td onclick="window.location='<?php echo $order_url;?>'" class="bnomics-select-options">
            <p class='hide-on-mobile'>
              <?=__('Pay With', 'blockonomics-bitcoin-payments')?>
            </p>
            <span class="bnomics-icon-<?php echo $code;?> bnomics-rotate-<?php echo $code;?>"></span>
            <b class='show-on-mobile'><?php echo $crypto['name'];?></b>
            
            <p class='hide-on-mobile' >
              <?php echo $crypto['name'];?><br>
              <b><?php echo $code;?></b>
            </p>
          </td>
        <?php 
        }
        ?>
      </tr>
    </table>
  </div>
</div>
