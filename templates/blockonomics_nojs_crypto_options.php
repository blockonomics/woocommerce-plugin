<?php
$orders = get_option('blockonomics_orders');
$order_id = $_REQUEST['select_crypto'];
?>
<div ng-app="shopping-cart-demo">
  <div ng-controller="CryptoOptionsController">
    <div class="bnomics-order-container">
      <!-- Blockonomics Currency Select -->
      <div class="bnomics-select-container">
        <h2>Pay With</h2>
        <table width="100%">
          <tr class="bnomics-select-options" >
              <td align="left"><a href="?show_order=<?php echo $order_id;?>&crypto=btc" style="width: 100%;"><img src="<?php echo plugins_url('img', dirname(__FILE__));?>/btc.png" class="rotateimgbtc" alt="Bitcoin Logo"> <h3>Bitcoin</h3> <span class="bnomics-select-crypto-button"><button type="button" class="btn btn-lg bnomics-select-crypto-code">btc</button></span></a></td>
          </tr>
          <tr class="bnomics-select-options" >
              <td align="left"><a href="?show_order=<?php echo $order_id;?>&crypto=bch" style="width: 100%;"><img src="<?php echo plugins_url('img', dirname(__FILE__));?>/bch.png" class="rotateimgbch" alt="Bitcoin Cash Logo"> <h3>Bitcoin Cash</h3> <span class="bnomics-select-crypto-button"><button type="button" class="btn btn-lg bnomics-select-crypto-code">bch</button></span></a></td>
          </tr>
        </table>
      </div>
      <!-- Blockonomics How to pay + Credit -->
      <div class="bnomics-powered-by">
        <a href="https://blog.blockonomics.co/how-to-pay-a-bitcoin-invoice-abf4a04d041c" target="_blank">How do I pay? | Check reviews of this shop</a><br>
        <div class="bnomics-powered-by-text bnomics-grey" >Powered by Blockonomics</div>
      </div>
    </div>
  </div>
</div>