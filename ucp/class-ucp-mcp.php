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

    public function register_routes()
    {
        register_rest_route('ucp/v1', '/mcp', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_request'),
            'permission_callback' => '__return_true',
        ));
    }

    public function handle_request($request)
    {
        $body = $request->get_json_params();

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
                            'version' => '1.0.0',
                        ),
                    );
                    break;
                case 'notifications/initialized':
                    $result = true;
                    break;
                case 'list_tools':
                case 'tools/list':
                    $result = $this->list_tools();
                    break;
                case 'resources/list':
                    $result = array('resources' => array());
                    break;
                case 'call_tool':
                case 'tools/call':
                    $tool_data = $this->call_tool($params);
                    // Tools can return a pre-built content array (e.g. image results).
                    if (isset($tool_data['__mcp_content'])) {
                        $result = array('content' => $tool_data['__mcp_content'], 'isError' => false);
                    } else {
                        $result = array(
                            'content' => array(
                                array('type' => 'text', 'text' => wp_json_encode($tool_data)),
                            ),
                            'isError' => false,
                        );
                    }
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

        // A Notification is a Request object without an "id" member.
        // The Server MUST NOT reply to a Notification.
        if ($id === null) {
            return null;
        }

        return array(
            'jsonrpc' => '2.0',
            'result' => $result,
            'id' => $id,
        );
    }

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
                array(
                    'name' => 'get_product_images',
                    'description' => 'Fetch images for a product by ID. Returns the images inline so they can be displayed.',
                    'inputSchema' => array(
                        'type' => 'object',
                        'properties' => array(
                            'product_id' => array('type' => 'integer', 'description' => 'Product ID from search results.'),
                        ),
                        'required' => array('product_id'),
                    ),
                ),
                array(
                    'name' => 'get_order_status',
                    'description' => 'Check the current status of an order. Works before and after payment.',
                    'inputSchema' => array(
                        'type' => 'object',
                        'properties' => array(
                            'order_id'  => array('type' => 'integer', 'description' => 'Order ID returned by pay_with_bitcoin or complete_checkout.'),
                            'order_key' => array('type' => 'string',  'description' => 'Order key from the track_url (the ?key=... parameter).'),
                        ),
                        'required' => array('order_id', 'order_key'),
                    ),
                ),
            ),
        );
    }

    private function call_tool($params)
    {
        $tool_name = isset($params['name']) ? $params['name'] : '';
        $args = isset($params['arguments']) ? $params['arguments'] : array();

        switch ($tool_name) {
            case 'search_products':
                $api = new UCP_API();
                $req = new WP_REST_Request('POST');
                $req->set_header('Content-Type', 'application/json');
                $req->set_body(wp_json_encode($args));
                $res = $api->search_products($req);
                if (is_wp_error($res)) throw new Exception($res->get_error_message());
                return $res->get_data();

            case 'create_checkout':
                $api = new UCP_API();
                $req = new WP_REST_Request('POST');
                $req->set_header('Content-Type', 'application/json');
                $req->set_body(wp_json_encode($args));
                $res = $api->create_checkout($req);
                if (is_wp_error($res)) throw new Exception($res->get_error_message());
                return $res->get_data();

            case 'update_checkout':
                $api = new UCP_API();
                $req = new WP_REST_Request('POST');
                $req->set_header('Content-Type', 'application/json');
                $checkout_id = isset($args['checkout_id']) ? $args['checkout_id'] : '';
                $req->set_param('id', $checkout_id);
                $req->set_body(wp_json_encode($args));
                $res = $api->update_checkout($req);
                if (is_wp_error($res)) throw new Exception($res->get_error_message());
                return $res->get_data();

            case 'complete_checkout':
                $store_api = new UCP_Store_API();
                $checkout_id = isset($args['checkout_id']) ? $args['checkout_id'] : '';
                return $store_api->complete_checkout($checkout_id);

            case 'pay_with_bitcoin':
                $store_api = new UCP_Store_API();
                $checkout_id = isset($args['checkout_id']) ? $args['checkout_id'] : '';
                return $store_api->pay_with_bitcoin($checkout_id);

            case 'get_product_images':
                $product_id = absint($args['product_id'] ?? 0);
                $product = wc_get_product($product_id);
                if (!$product) throw new Exception('Product not found: ' . $product_id);

                $mapper = new UCP_Mapper();
                $data   = $mapper->map_product_to_item($product);
                $images = $data['images'] ?? array();

                $content = array(
                    array('type' => 'text', 'text' => $product->get_name() . ' — ' . count($images) . ' image(s)'),
                );

                foreach (array_slice($images, 0, 4) as $url) {
                    $response = wp_remote_get($url, array('timeout' => 10));
                    if (is_wp_error($response)) continue;
                    $body = wp_remote_retrieve_body($response);
                    $mime = wp_remote_retrieve_header($response, 'content-type');
                    // Strip charset suffix if present (e.g. "image/jpeg; charset=...")
                    $mime = strtok($mime ?: 'image/jpeg', ';');
                    if ($body) {
                        $content[] = array(
                            'type'     => 'image',
                            'data'     => base64_encode($body),
                            'mimeType' => trim($mime),
                        );
                    }
                }

                return array('__mcp_content' => $content);

            case 'get_order_status':
                $api = new UCP_API();
                $req = new WP_REST_Request('GET');
                $req->set_param('id', isset($args['order_id']) ? $args['order_id'] : 0);
                $req->set_param('key', isset($args['order_key']) ? $args['order_key'] : '');
                $res = $api->get_order_status($req);
                if (is_wp_error($res)) throw new Exception($res->get_error_message());
                return $res->get_data();

            default:
                throw new Exception('Tool not found: ' . $tool_name);
        }
    }
}
