<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WebMCP Frontend Integration using @mcp-b/global standard.
 * Exposes UCP tools to browser-based agents via navigator.modelContext.
 */
class UCP_WebMCP
{

    /**
     * Initialize WebMCP.
     */
    public function __construct()
    {
        // Only load on frontend
        if (!is_admin()) {
            add_action('wp_footer', array($this, 'inject_webmcp_bootstrap'), 5);
            add_action('wp_footer', array($this, 'inject_ucp_tools'), 10);
        }
    }

    /**
     * Inject @mcp-b/global polyfill from CDN.
     */
    public function inject_webmcp_bootstrap()
    {
        ?>
        <script>
            (function () {
                // Function to initialize or check for modelContext
                function initWebMCP() {
                    // Check if @mcp-b/global is already loaded
                    if (window.navigator?.modelContext) {
                        console.log('[UCP Connect] navigator.modelContext already available');
                        return;
                    }

                    console.log('[UCP Connect] Loading @mcp-b/global from CDN...');

                    // Load @mcp-b/global polyfill from unpkg
                    const script = document.createElement('script');
                    script.src = 'https://unpkg.com/@mcp-b/global@latest/dist/index.iife.js';
                    script.async = false; // Load synchronously to ensure it's ready
                    script.onload = function () {
                        console.log('[UCP Connect] @mcp-b/global loaded successfully');
                        window.dispatchEvent(new CustomEvent('ucp:webmcp-ready'));
                    };
                    script.onerror = function () {
                        console.error('[UCP Connect] Failed to load @mcp-b/global. WebMCP tools will not be available.');
                    };
                    document.head.appendChild(script);
                }

                // Wait for full page load, then give browser extensions 800ms to inject
                // their native navigator.modelContext before falling back to polyfill.
                function startWebMCP() {
                    if (window.navigator?.modelContext) {
                        console.log('[UCP Connect] navigator.modelContext already available (native)');
                        return;
                    }
                    setTimeout(initWebMCP, 800);
                }

                if (document.readyState === 'complete') {
                    startWebMCP();
                } else {
                    window.addEventListener('load', startWebMCP);
                }
            })();
        </script>
        <?php
    }

    /**
     * Inject UCP Commerce tools using @mcp-b/global standard.
     */
    public function inject_ucp_tools()
    {
        $rest_url = rest_url('ucp/v1');
        $nonce = wp_create_nonce('wp_rest');
        ?>
        <script>
            (function () {
                const restUrl = <?php echo wp_json_encode($rest_url); ?>;
                const nonce = <?php echo wp_json_encode($nonce); ?>;

                // Helper to call WordPress REST API
                async function callRestAPI(endpoint, method = 'POST', body = null) {
                    const options = {
                        method: method,
                        headers: {
                            'Content-Type': 'application/json',
                            'X-WP-Nonce': nonce
                        }
                    };

                    if (body) {
                        options.body = JSON.stringify(body);
                    }

                    const response = await fetch(restUrl + endpoint, options);
                    if (!response.ok) {
                        const errorText = await response.text();
                        throw new Error('API Error: ' + response.statusText + ' - ' + errorText);
                    }
                    return await response.json();
                }

                // Define UCP tools
                var ucpTools = [
                        {
                            name: 'search_products',
                            description: 'Search for products in this WooCommerce store. Returns a list of products matching the query. Pass an empty string "" to list all products.',
                            inputSchema: {
                                type: 'object',
                                properties: {
                                    query: {
                                        type: 'string',
                                        description: 'Search query (e.g., "running shoes", "leather jacket"). Leave empty to list all products.'
                                    }
                                },
                                required: ['query']
                            },
                            execute: async function (args) {
                                try {
                                    const result = await callRestAPI('/search', 'POST', { query: args.query });
                                    const count = result.items?.length || 0;
                                    let text = `Found ${count} products for "${args.query}"`;

                                    if (count > 0) {
                                        const productList = result.items.map(item => {
                                            let details = [];

                                            // Description (New: Critical for Context)
                                            if (item.description) {
                                                // Strip HTML tags and truncate
                                                const cleanDesc = item.description.replace(/<[^>]*>?/gm, '').trim();
                                                if (cleanDesc) {
                                                    details.push(cleanDesc.substring(0, 300) + (cleanDesc.length > 300 ? '...' : ''));
                                                }
                                            }

                                            // Price & Sale
                                            let priceStr = item.price ? `${item.price.value} ${item.price.currency}` : 'N/A';
                                            if (item.isOnSale) {
                                                priceStr += ` (On Sale! Reg: ${item.regularPrice})`;
                                            }
                                            details.push(`Price: ${priceStr}`);

                                            // Stock
                                            details.push(`Stock: ${item.availability}`);

                                            // Dimensions
                                            // Check if we have an object with dimensions, not an empty array
                                            if (item.dimensions && !Array.isArray(item.dimensions) && (item.dimensions.length || item.dimensions.width || item.dimensions.height)) {
                                                const d = item.dimensions;
                                                details.push(`Dims: ${d.length}x${d.width}x${d.height} ${d.unit || ''}`);
                                            }

                                            // Attributes
                                            if (item.attributes && Object.keys(item.attributes).length > 0) {
                                                const attrs = Object.entries(item.attributes)
                                                    .map(([k, v]) => `${k}: ${Array.isArray(v) ? v.join(', ') : v}`)
                                                    .join('; ');
                                                details.push(`Attrs: ${attrs}`);
                                            }

                                            return `- [ID: ${item.id}] **${item.name}**\n  ${details.join(' | ')}`;
                                        }).join('\n\n');
                                        text += `:\n${productList}`;
                                    }

                                    return {
                                        content: [{
                                            type: 'text',
                                            text: text
                                        }],
                                        structuredContent: result,
                                        isError: false
                                    };
                                } catch (error) {
                                    return {
                                        content: [{
                                            type: 'text',
                                            text: 'Error searching products: ' + error.message
                                        }],
                                        isError: true
                                    };
                                }
                            }
                        },
                        {
                            name: 'create_checkout',
                            description: 'Create a new checkout session with selected products. Returns a Cart Token (ID) and initial totals.',
                            inputSchema: {
                                type: 'object',
                                properties: {
                                    items: {
                                        type: 'array',
                                        description: 'Products to add to checkout',
                                        items: {
                                            type: 'object',
                                            properties: {
                                                id: { type: ['integer', 'string'], description: 'Product ID' },
                                                quantity: { type: 'integer', description: 'Quantity' }
                                            },
                                            required: ['id', 'quantity']
                                        }
                                    }
                                },
                                required: ['items']
                            },
                            execute: async function (args) {
                                try {
                                    const result = await callRestAPI('/checkout', 'POST', { items: args.items });
                                    const total = result.total ? `${result.total} ${result.currency}` : 'Pending';

                                    return {
                                        content: [{
                                            type: 'text',
                                            text: `Cart Created! ID: ${result.id}\nTotal: ${total}\n\nYou can now use 'update_checkout' to add shipping address or discounts, or 'complete_checkout' to pay.`
                                        }],
                                        structuredContent: result,
                                        isError: false
                                    };
                                } catch (error) {
                                    return {
                                        content: [{ type: 'text', text: 'Error creating checkout: ' + error.message }],
                                        isError: true
                                    };
                                }
                            }
                        },
                        {
                            name: 'update_checkout',
                            description: 'Update an existing checkout with shipping address, discounts, or new items.',
                            inputSchema: {
                                type: 'object',
                                properties: {
                                    id: { type: 'string', description: 'Cart Token/ID returned from create_checkout' },
                                    shipping_address: {
                                        type: 'object',
                                        description: 'Shipping address for tax/shipping calculation',
                                        properties: {
                                            first_name: { type: 'string' },
                                            last_name: { type: 'string' },
                                            address_line1: { type: 'string' },
                                            city: { type: 'string' },
                                            region: { type: 'string', description: 'State/Province' },
                                            country: { type: 'string', description: '2-letter ISO code (e.g. US, CA)' },
                                            postal_code: { type: 'string' }
                                        }
                                    },
                                    discounts: {
                                        type: 'object',
                                        properties: {
                                            codes: { type: 'array', items: { type: 'string' } }
                                        }
                                    }
                                },
                                required: ['id']
                            },
                            execute: async function (args) {
                                try {
                                    const result = await callRestAPI(`/checkout/${encodeURIComponent(args.id)}`, 'POST', args);
                                    
                                    let summary = `Cart Updated!\nTotal: ${result.total} ${result.currency}`;
                                    if (result.shipping_total > 0) summary += `\nShipping: ${result.shipping_total}`;
                                    if (result.tax_total > 0) summary += `\nTax: ${result.tax_total}`;
                                    if (result.discount_total > 0) summary += `\nDiscounts: -${result.discount_total}`;

                                    return {
                                        content: [{ type: 'text', text: summary }],
                                        structuredContent: result,
                                        isError: false
                                    };
                                } catch (error) {
                                    return {
                                        content: [{ type: 'text', text: 'Error updating checkout: ' + error.message }],
                                        isError: true
                                    };
                                }
                            }
                        },
                        {
                            name: 'complete_checkout',
                            description: 'Finalize the checkout and get a payment link.',
                            inputSchema: {
                                type: 'object',
                                properties: {
                                    id: { type: 'string', description: 'Cart Token/ID' }
                                },
                                required: ['id']
                            },
                            execute: async function (args) {
                                try {
                                    const result = await callRestAPI(`/checkout/${encodeURIComponent(args.id)}/complete`, 'POST', {});
                                    
                                    const paymentUrl = result.continue_url;
                                    
                                    // Show the link in chat first
                                    const response = {
                                        content: [{
                                            type: 'text',
                                            text: `✅ Order Created Successfully!\n\n🔗 Payment Link: ${paymentUrl}\n\n⏳ Redirecting you to checkout in 2 seconds...`
                                        }],
                                        structuredContent: result,
                                        isError: false
                                    };

                                    // Redirect after a short delay so user can see the link
                                    if (paymentUrl) {
                                        setTimeout(() => {
                                            console.log('[UCP Connect] Redirecting to payment:', paymentUrl);
                                            window.location.href = paymentUrl;
                                        }, 2000); // 2 second delay
                                    }

                                    return response;
                                } catch (error) {
                                    return {
                                        content: [{ type: 'text', text: 'Error completing checkout: ' + error.message }],
                                        isError: true
                                    };
                                }
                            }
                        },
                        {
                            name: 'get_discovery',
                            description: 'Get store capabilities and information. Returns details about what this store supports.',
                            inputSchema: {
                                type: 'object',
                                properties: {}
                            },
                            execute: async function (args) {
                                try {
                                    const result = await callRestAPI('/discovery', 'GET', null);
                                    const caps = Object.keys(result.capabilities || {}).join(', ');
                                    return {
                                        content: [{
                                            type: 'text',
                                            text: `Store: ${result.store_info?.name || 'Unknown'}\nLanguage: English\nProtocol: ${result.protocol || 'UCP'} (v${result.version || '0.1.0'})\nCurrency: ${result.store_info?.currency || 'N/A'}\nCapabilities: ${caps}`
                                        }],
                                        structuredContent: result,
                                        isError: false
                                    };
                                } catch (error) {
                                    return {
                                        content: [{
                                            type: 'text',
                                            text: 'Error fetching discovery info: ' + error.message
                                        }],
                                        isError: true
                                    };
                                }
                            }
                        },
                        {
                            name: 'get_available_discounts',
                            description: 'List active and public discount codes (coupons) for the store.',
                            inputSchema: {
                                type: 'object',
                                properties: {}
                            },
                            execute: async function (args) {
                                try {
                                    const result = await callRestAPI('/discounts', 'GET', null);
                                    
                                    if (!result.discounts || result.discounts.length === 0) {
                                        return {
                                            content: [{ type: 'text', text: 'No public discount codes are currently available.' }],
                                            isError: false
                                        };
                                    }

                                    const list = result.discounts.map(d => `- **${d.code}**: ${d.description} (Value: ${d.amount} ${d.type})`).join('\n');
                                    
                                    return {
                                        content: [{
                                            type: 'text',
                                            text: `Available Promotions:\n${list}`
                                        }],
                                        structuredContent: result,
                                        isError: false
                                    };
                                } catch (error) {
                                    return {
                                        content: [{
                                            type: 'text',
                                            text: 'Error fetching discounts: ' + error.message
                                        }],
                                        isError: true
                                    };
                                }
                            }
                        }
                    ];

                // Register tools on navigator.modelContext using registerTool() (native
                // browser extension API) or provideContext() (@mcp-b/global polyfill).
                // Returns true if registration succeeded.
                function registerUCPTools() {
                    var mc = window.navigator && window.navigator.modelContext;
                    if (!mc) return false;

                    try {
                        if (typeof mc.registerTool === 'function') {
                            ucpTools.forEach(function (tool) { mc.registerTool(tool); });
                            console.log('[UCP Connect] Registered ' + ucpTools.length + ' tools via registerTool()');
                        } else if (typeof mc.provideContext === 'function') {
                            mc.provideContext({ tools: ucpTools });
                            console.log('[UCP Connect] Registered ' + ucpTools.length + ' tools via provideContext()');
                        } else {
                            return false;
                        }
                    } catch (e) {
                        console.error('[UCP Connect] Registration error:', e);
                        return false;
                    }

                    window.dispatchEvent(new CustomEvent('ucp:tools-registered', {
                        detail: { source: 'ucp-connect-woocommerce', count: ucpTools.length }
                    }));
                    return true;
                }

                // Try immediately, then retry every 100ms for up to 5 seconds.
                if (!registerUCPTools()) {
                    var _ucpInterval = setInterval(function () {
                        if (registerUCPTools()) clearInterval(_ucpInterval);
                    }, 100);
                    setTimeout(function () { clearInterval(_ucpInterval); }, 5000);
                }
            })();
        </script>
        <?php
    }
}
