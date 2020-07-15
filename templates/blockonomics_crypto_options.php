<div id="active_cryptos" data-active_cryptos='<?php echo json_encode(get_option('blockonomics_active_cryptos')); ?>'></div>

<div ng-app="shopping-cart-demo">
  <div ng-controller="CryptoOptionsController">
    <div class="bnomics-order-container">
      <!-- Spinner -->
      <div class="bnomics-spinner-wrapper" ng-show="spinner" ng-cloak>
        <div class="bnomics-spinner"></div>
      </div>
      <!-- Display Error -->
      <div id="display-error" ng-hide="no_display_error">
        <h2>Display Error</h2>
        <p>Unable to render correctly, Note to Administrator: Please enable lite mode in Blockonomics plugin.</p>
      </div>
      <!-- Blockonomics Currency Select -->
      <div class="bnomics-select-container" ng-show="crypto_selecter" ng-cloak>
        <h2>Pay With</h2>
        <table width="100%">
          <tr class="bnomics-select-options" ng-repeat="(active_code, active_crypto) in active_cryptos" ng-click="select_blockonomics_crypto(active_code)">
              <td align="left"><img ng-src="<?php echo plugins_url('img', dirname(__FILE__));?>/{{active_code}}.png" class="rotateimg{{active_code}}" alt="{{active_crypto.name}} Logo"> <h3>{{active_crypto.name}}</h3> <span class="bnomics-select-crypto-button"><button type="button" class="btn btn-lg bnomics-select-crypto-code">{{active_code}}</button></span></td>
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