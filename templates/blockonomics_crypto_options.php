<?php
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
        <table width="100%">
          <tr>
              <td class="bnomics-select-options" ng-repeat="(active_code, active_crypto) in active_cryptos" ng-click="select_blockonomics_crypto(active_code)">
                <p>
                  <?=__('Pay With', 'blockonomics-bitcoin-payments')?>
                </p>
                <span class="icon-{{active_code}} rotateimg{{active_code}}"></span>
                <p>
                  {{active_crypto.name}}<br>
                  <b>{{active_code}}</b>
                </p>
              </td>
          </tr>
        </table>
      </div>

    </div>
  </div>
</div>
