<?php
include_once plugin_dir_path(__FILE__) . 'php' . DIRECTORY_SEPARATOR . 'Blockonomics.php';
$blockonomics = new Blockonomics;
?>
<div id="active_cryptos" data-active_cryptos='<?php echo json_encode($blockonomics->getActiveCurrencies()); ?>'></div>

<div ng-app="BlockonomicsApp">
  <div ng-controller="CryptoOptionsController">
    <div class="bnomics-order-container">
      <!-- Spinner -->
      <div class="bnomics-spinner-wrapper" ng-show="spinner" ng-cloak>
        <div class="bnomics-spinner"></div>
      </div>

      <!-- Display Error -->
      <div id="display-error" class="bnomics-display-error" ng-hide="no_display_error">
        <h2><?=__('Display Error', 'blockonomics-bitcoin-payments')?></h2>
        <p><?=__('Unable to render correctly, Note to Administrator: Please enable lite mode in the Blockonomics plugin.', 'blockonomics-bitcoin-payments')?></p>
      </div>
      
      <!-- Blockonomics Currency Select -->
      <div class="bnomics-select-container" ng-show="crypto_selecter" ng-cloak>
        <h2><?=__('Pay With', 'blockonomics-bitcoin-payments')?></h2>
        <table width="100%">
          <tr class="bnomics-select-options" ng-repeat="(active_code, active_crypto) in active_cryptos" ng-click="select_blockonomics_crypto(active_code)">
              <td align="left"><img ng-src="<?php echo plugins_url('img', dirname(__FILE__));?>/{{active_code}}.png" class="rotateimg{{active_code}}" alt="{{active_crypto.name}} Logo"> <h3>{{active_crypto.name}}</h3> <span class="bnomics-select-crypto-button"><button type="button" class="btn btn-lg bnomics-select-crypto-code">{{active_code}}</button></span></td>
          </tr>
        </table>
      </div>

    </div>
  </div>
</div>