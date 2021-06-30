<?php
$blockonomics = new Blockonomics;
$cryptos = $blockonomics->getActiveCurrencies();
$order_id = isset($_REQUEST["select_crypto"]) ? sanitize_text_field($_REQUEST["select_crypto"]) : "";
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
          <td class="bnomics-select-options">
            <a href="<?php echo $order_url;?>" style="color: inherit; text-decoration: inherit;">
              <p>
                <?=__('Pay With', 'blockonomics-bitcoin-payments')?>
              </p>
              <span class="bnomics-icon-<?php echo $code;?> bnomics-rotate-<?php echo $code;?>"></span>
              <p>
                <?php echo $crypto['name'];?><br>
                <b><?php echo $code;?></b>
              </p>
            </a>
          </td>
        <?php 
        }
        ?>
      </tr>
    </table>
  </div>
</div>
