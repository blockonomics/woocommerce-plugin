<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * UCP MCP Server.
 * Implements a JSON-RPC 2.0 endpoint for agentic tools.
 */
class UCP_MCP_Server
{

    /**
     * Register routes.
     */
    public function register_routes()
    {
        register_rest_route('ucp/v1', '/mcp', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_request'),
            'permission_callback' => '__return_true',
        ));
    }

    /**
     * Handle JSON-RPC request.
     */
    public function handle_request($request)
    {
        $body = $request->get_json_params();

        // Basic JSON-RPC validation
        if (empty($body['jsonrpc']) || $body['jsonrpc'] !== '2.0' || empty($body['method'])) {
            return new WP_Error('invalid_request', 'Invalid JSON-RPC request', array('status' => 400));
        }

        $method = $body['method'];
        $params = isset($body['params']) ? $body['params'] : array();
        $id = isset($body['id']) ? $body['id'] : null;

        $result = null;
        $error = null;

        try {
            switch ($method) {
                case 'initialize':
                    $result = array(
                        'protocolVersion' => '2024-11-05',
                        'capabilities' => array(
                            'tools' => array('listChanged' => false),
                            'resources' => array('listChanged' => false),
                        ),
                        'serverInfo' => array(
                            'name' => get_bloginfo('name'),
                            'version' => UCP_CONNECT_VERSION,
                        ),
                    );
                    break;
                case 'notifications/initialized':
                    // Client confirming initialization
                    $result = true;
                    break;
                case 'list_tools':
                case 'tools/list': // Standard MCP
                    $result = $this->list_tools();
                    break;
                case 'resources/list':
                    $result = array('resources' => array());
                    break;
                case 'call_tool':
                case 'tools/call': // Standard MCP
                    $result = $this->call_tool($params);
                    break;
                default:
                    $error = array('code' => -32601, 'message' => 'Method not found: ' . $method);
            }
        } catch (Exception $e) {
            $error = array('code' => -32000, 'message' => $e->getMessage());
        }

        if ($error) {
            return array(
                'jsonrpc' => '2.0',
                'error' => $error,
                'id' => $id,
            );
        }

        // JSON-RPC 2.0 Specification:
        // A Notification is a Request object without an "id" member.
        // The Server MUST NOT reply to a Notification.
        if ($id === null) {
            return null; // Don't respond to notifications
        }

        return array(
            'jsonrpc' => '2.0',
            'result' => $result,
            'id' => $id,
        );
    }

    /**
     * List available tools (MCP capability).
     */
    private function list_tools()
    {
        return array(
            'tools' => array(
                array(
                    'name' => 'search_products',
                    'description' => 'Search for products in the catalog.',
                    'inputSchema' => array(
                        'type' => 'object',
                        'properties' => array(
                            'query' => array('type' => 'string'),
                        ),
                        'required' => array('query'),
                    ),
                ),
                array(
                    'name' => 'create_checkout',
                    'description' => 'Create a checkout session. Returns a "payment_url" link that YOU MUST present to the user to complete the purchase.',
                    'inputSchema' => array(
                        'type' => 'object',
                        'properties' => array(
                            'items' => array(
                                'type' => 'array',
                                'items' => array(
                                    'type' => 'object',
                                    'properties' => array(
                                        'id' => array('type' => 'integer'),
                                        'quantity' => array('type' => 'integer'),
                                    ),
                                ),
                            ),
                        ),
                        'required' => array('items'),
                    ),
                ),
                array(
                    'name' => 'update_checkout',
                    'description' => 'Update an existing checkout with buyer info.',
                    'inputSchema' => array(
                        'type' => 'object',
                        'properties' => array(
                            'checkout_id' => array('type' => 'string'),
                            'email' => array('type' => 'string'),
                        ),
                        'required' => array('checkout_id'),
                    ),
                ),
                array(
                    'name' => 'complete_checkout',
                    'description' => 'Finalizes the checkout and creates a WooCommerce order. Returns an order ID and a payment URL.',
                    'inputSchema' => array(
                        'type' => 'object',
                        'properties' => array(
                            'checkout_id' => array('type' => 'string', 'description' => 'Checkout ID returned by create_checkout or update_checkout.'),
                        ),
                        'required' => array('checkout_id'),
                    ),
                ),
                array(
                    'name' => 'pay_with_bitcoin',
                    'description' => 'Finalizes checkout and returns a Bitcoin payment address, BTC amount, and a bitcoin: URI. Present the address and amount to the customer so they can send Bitcoin to complete the purchase.',
                    'inputSchema' => array(
                        'type' => 'object',
                        'properties' => array(
                            'checkout_id' => array('type' => 'string', 'description' => 'Checkout ID returned by create_checkout or update_checkout.'),
                        ),
                        'required' => array('checkout_id'),
                    ),
                ),
            ),
        );
    }

    /**
     * Execute a tool.
     */
    private function call_tool($params)
    {
        $tool_name = isset($params['name']) ? $params['name'] : '';
        $args = isset($params['arguments']) ? $params['arguments'] : array();

        switch ($tool_name) {
            case 'search_products':
                // Reuse REST logic logic
                $api = new UCP_API();
                $req = new WP_REST_Request();
                $req->set_body_params($args);
                $res = $api->search_products($req);
                return $res->get_data();

            case 'create_checkout':
                // Handle checkout creation
                $api = new UCP_API();
                $req = new WP_REST_Request();
                $req->set_body_params($args);
                $res = $api->create_checkout($req);
                return $res->get_data();

            case 'update_checkout':
                $api = new UCP_API();
                $req = new WP_REST_Request();
                $checkout_id = isset($args['checkout_id']) ? $args['checkout_id'] : '';
                $req->set_param('id', $checkout_id);
                $req->set_body_params($args);
                $res = $api->update_checkout($req);
                return $res->get_data();

            case 'complete_checkout':
                $store_api = new UCP_Store_API();
                $checkout_id = isset($args['checkout_id']) ? $args['checkout_id'] : '';
                return $store_api->complete_checkout($checkout_id);

            case 'pay_with_bitcoin':
                $store_api = new UCP_Store_API();
                $checkout_id = isset($args['checkout_id']) ? $args['checkout_id'] : '';
                return $store_api->pay_with_bitcoin($checkout_id);

            default:
                throw new Exception('Tool not found: ' . $tool_name);
        }
    }
}
