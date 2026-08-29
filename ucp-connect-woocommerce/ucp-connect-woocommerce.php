<?php
/**
 * Plugin Name: UCP Connect for WooCommerce
 * Plugin URI:  https://ucp.dev/
 * Description: Exposes a WooCommerce store's inventory as a Universal Commerce Protocol (UCP) endpoint.
 * Version:     2.0.1
 * Author:      Agentic Commerce Team
 * Author URI:  https://github.com/Universal-Commerce-Protocol
 * Text Domain: ucp-connect-wc
 * Domain Path: /languages
 * License:     GPLv2 or later
 */

if (!defined('ABSPATH')) {
	exit;
}

// Define plugin constants.
define('UCP_CONNECT_VERSION', '2.0.1');
define('UCP_CONNECT_PATH', plugin_dir_path(__FILE__));
define('UCP_CONNECT_URL', plugin_dir_url(__FILE__));

/**
 * Admin notice if WooCommerce is not installed.
 */
function ucp_connect_woocommerce_missing_notice()
{
	?>
	<div class="notice notice-error">
		<p><strong>UCP Connect for WooCommerce:</strong> This plugin requires WooCommerce to be installed and activated.
			Please install <a
				href="<?php echo esc_url(admin_url('plugin-install.php?s=woocommerce&tab=search&type=term')); ?>">WooCommerce</a>
			first.</p>
	</div>
	<?php
}

/**
 * Initialize plugin after all plugins are loaded
 */
function ucp_connect_init()
{
	// Check if WooCommerce is active
	if (!class_exists('WooCommerce')) {
		add_action('admin_notices', 'ucp_connect_woocommerce_missing_notice');
		return;
	}

	// Include core classes.
	require_once UCP_CONNECT_PATH . 'includes/class-ucp-api.php';
	require_once UCP_CONNECT_PATH . 'includes/class-ucp-mapper.php';
	require_once UCP_CONNECT_PATH . 'includes/class-ucp-cart-manager.php';
	require_once UCP_CONNECT_PATH . 'includes/class-ucp-mcp.php';
	require_once UCP_CONNECT_PATH . 'includes/class-ucp-webmcp.php';

	// Initialize the plugin.
	UCP_Connect_WooCommerce::instance();
}

// Hook into plugins_loaded to ensure WooCommerce is available
add_action('plugins_loaded', 'ucp_connect_init');

/**
 * Main instance of the plugin.
 */
class UCP_Connect_WooCommerce
{

	/**
	 * The single instance of the class.
	 */
	protected static $_instance = null;

	/**
	 * Main Instance.
	 */
	public static function instance()
	{
		if (is_null(self::$_instance)) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Constructor.
	 */
	public function __construct()
	{
		// Load core classes
		require_once UCP_CONNECT_PATH . 'includes/class-ucp-api.php';
		require_once UCP_CONNECT_PATH . 'includes/class-ucp-mapper.php';
		require_once UCP_CONNECT_PATH . 'includes/class-ucp-cart-manager.php';
		require_once UCP_CONNECT_PATH . 'includes/class-ucp-store-api.php';
		require_once UCP_CONNECT_PATH . 'includes/class-ucp-webmcp.php';
		require_once UCP_CONNECT_PATH . 'includes/class-ucp-mcp.php';

		$this->init_hooks();
	}

	/**
	 * Hook into actions and filters.
	 */
	private function init_hooks()
	{
		add_action('rest_api_init', array($this, 'register_api_routes'));

		// Initialize WebMCP for browser-based agents
		new UCP_WebMCP();


		// Initialize MCP server for server-to-server communication
		add_action('rest_api_init', array($this, 'register_mcp_routes'));

		// Add support for /.well-known/ucp discovery
		add_action('init', array($this, 'handle_well_known_discovery'));
	}

	/**
	 * Handle /.well-known/ucp requests for Agentic Discovery.
	 */
	public function handle_well_known_discovery()
	{
		if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/.well-known/ucp') !== false) {
			header('Content-Type: application/json');
			$api = new UCP_API();
			$response = $api->get_discovery(new WP_REST_Request());
			echo json_encode($response->get_data());
			exit;
		}
	}

	/**
	 * Register REST API routes.
	 */
	public function register_api_routes()
	{
		$api = new UCP_API();
		$api->register_routes();
	}

	/**
	 * Register MCP server routes.
	 */
	public function register_mcp_routes()
	{
		$mcp = new UCP_MCP_Server();
		$mcp->register_routes();
	}
}
