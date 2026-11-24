<?php
$blockonomics = new Blockonomics;
$cryptos = $blockonomics->getCachedActiveCurrencies();
if (!is_array($cryptos)) {
    $cryptos = [];
}
$order_id = isset($_REQUEST["select_crypto"]) ? sanitize_text_field(wp_unslash($_REQUEST["select_crypto"])) : "";
// select_crypto is an encrypt_hash() value: even-length hex only
if (strlen($order_id) % 2 || !ctype_xdigit($order_id)) {
    $order_id = "";
}
$order_url = $blockonomics->get_parameterized_wc_url('page',array('show_order'=>$order_id))
?>
<div class="woocommerce bnomics-order-container">
  <div class="bnomics-select-container">
    <?php
    foreach ($cryptos as $code => $crypto) {
      $order_url = add_query_arg('crypto', $code, $order_url);
    ?>
      <a href="<?php echo esc_url($order_url);?>">
        <button class="bnomics-select-options woocommerce-button button">
          <img class="bnomics-crypto-icon" src="<?php echo plugins_url('../img/' . $code . '.svg', __FILE__); ?>" alt="<?php echo $crypto['name']; ?>">
          <span><?=__('Pay with', 'blockonomics-bitcoin-payments')?> <?php echo $crypto['name'];?></span>
        </button>
      </a>
    <?php
    }
    ?>
  </div>
</div>
