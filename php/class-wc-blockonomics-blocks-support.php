<?php
use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

defined( 'ABSPATH' ) || exit;

final class WC_Blockonomics_Blocks_Support extends AbstractPaymentMethodType {
    protected $name = 'blockonomics';

    public function initialize() {
		$this->settings = get_option( 'woocommerce_blockonomics_settings', [] );
	}

    public function is_active() {
		return filter_var( $this->get_setting( 'enabled', false ), FILTER_VALIDATE_BOOLEAN );
	}

    private function get_enable_for_virtual() {
		return filter_var( $this->get_setting( 'enable_for_virtual', false ), FILTER_VALIDATE_BOOLEAN );
	}

    public function get_payment_method_script_handles() {
		$asset_path = plugin_dir_path(__FILE__) . 'build' . DIRECTORY_SEPARATOR . 'block.asset.php';
		$version = get_plugin_data( __FILE__ )['Version'];
		$dependencies = [];

		if ( file_exists( $asset_path ) ) {
			$asset        = require $asset_path;
			$version      = is_array( $asset ) && isset( $asset['version'] )
				? $asset['version']
				: $version;
			$dependencies = is_array( $asset ) && isset( $asset['dependencies'] )
				? $asset['dependencies']
				: $dependencies;
		}

        wp_register_script(
            'bnomics-blocks-integration',
            plugins_url('../build/block.js', __FILE__),
            array(),
            get_plugin_data( __FILE__ )['Version'],
            true
        );

		return [ 'bnomics-blocks-integration' ];
    }

    public function get_payment_method_data() {
		return [
			'title'                    => $this->get_setting( 'title' ),
			'description'              => $this->get_setting( 'description' ),
			'enableForVirtual'         => $this->get_enable_for_virtual(),
			'supports'                 => $this->get_supported_features(),
		];
	}
}

?>