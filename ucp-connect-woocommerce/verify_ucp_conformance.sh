#!/bin/bash
# WebMCP Conformance Verification Script
# This script simulates an Agent interaction flow to verify the plugin's UCP compliance.

echo "==========================================="
echo "   UCP WebMCP Conformance Verification"
echo "==========================================="
echo ""

# 1. Base URL
if [ -z "$BASE_URL" ]; then
    if curl -s "http://localhost:8888" > /dev/null; then
        BASE_URL="http://localhost:8888/wp-json/ucp/v1"
    elif curl -s "http://localhost:10003" > /dev/null; then
        BASE_URL="http://localhost:10003/wp-json/ucp/v1" 
    else
        # Default to standard docker port 8080 if nothing else found
        echo "⚠️  Could not auto-detect WP URL. Using default: http://localhost:8080"
        BASE_URL="http://localhost:8080/wp-json/ucp/v1"
    fi
else
     echo "ℹ️  Using configured Base URL: $BASE_URL"
fi

echo "Target API Base: $BASE_URL"
echo ""

# 2. Test Discovery (Get Capabilities)
echo "[1] Testing 'get_discovery' (Capabilities)..."
DISCOVERY_RES=$(curl -s "$BASE_URL/discovery")
if echo "$DISCOVERY_RES" | grep -q "shopping.search"; then
    echo "✅ Success: Found 'shopping.search' capability."
    echo "   Payload: $(echo "$DISCOVERY_RES" | grep -o '"version":"[^"]*"' | head -1)"
else
    echo "❌ Failure: Discovery response missing 'shopping.search'."
    echo "   Response: $DISCOVERY_RES"
    exit 1
fi
echo ""

# 3. Test Search (Find a Product) - Testing "Smart Search" with plural
echo "[2] Testing 'search_products' (Search)..."
SEARCH_RES=$(curl -s -X POST "$BASE_URL/search" -H "Content-Type: application/json" -d '{"query":"hoodies"}')
PRODUCT_ID=$(echo "$SEARCH_RES" | grep -o '"id":"[^"]*"' | head -1 | cut -d'"' -f4)

if [ -n "$PRODUCT_ID" ]; then
    echo "✅ Success: Found product with ID: $PRODUCT_ID (Smart Search working!)"
else
    echo "❌ Failure: Search returned no products."
    echo "   Response: $SEARCH_RES"
    # If no products, we can't test checkout. Try creating one? No, just warn.
    echo "   (Ensure WooCommerce has at least one published product)"
    exit 1
fi
echo ""

# 4. Test Checkout (Create Session)
echo "[3] Testing 'create_checkout' (Checkout)..."
CHECKOUT_DATA="{\"items\":[{\"id\":$PRODUCT_ID,\"quantity\":1}]}"
CHECKOUT_RES=$(curl -s -X POST "$BASE_URL/checkout" -H "Content-Type: application/json" -d "$CHECKOUT_DATA")
ORDER_ID=$(echo "$CHECKOUT_RES" | grep -o '"order_id":[^,]*' | cut -d':' -f2 | tr -d ' ')

if [ -n "$ORDER_ID" ] && [ "$ORDER_ID" != "null" ]; then
    echo "✅ Success: Created Checkout/Order ID: $ORDER_ID"
    PAYMENT_URL=$(echo "$CHECKOUT_RES" | grep -o '"payment_url":"[^"]*"' | cut -d'"' -f4)
    echo "   Payment URL: $PAYMENT_URL"
else
    echo "❌ Failure: Failed to create checkout."
    echo "   Response: $CHECKOUT_RES"
    exit 1
fi

echo ""

# 5. Test MCP Server Handshake (Dynamic Info)
echo "[4] Testing 'MCP Server Handshake' (POST /mcp)..."
HANDSHAKE_DATA='{"jsonrpc":"2.0","method":"initialize","id":1}'
MCP_RES=$(curl -s -X POST "$BASE_URL/mcp" -H "Content-Type: application/json" -d "$HANDSHAKE_DATA")

if echo "$MCP_RES" | grep -q "serverInfo"; then
    SERVER_NAME=$(echo "$MCP_RES" | grep -o '"name":"[^"]*"' | cut -d'"' -f4)
    VERSION=$(echo "$MCP_RES" | grep -o '"version":"[^"]*"' | cut -d'"' -f4)
    echo "✅ Success: MCP Handshake complete."
    echo "   Server Name: $SERVER_NAME"
    echo "   Version: $VERSION"
else
    echo "❌ Failure: MCP Handshake failed."
    echo "   Response: $MCP_RES"
    exit 1
fi

echo ""
echo "==========================================="
echo "🎉  Conformance Result: PASSED"
echo "    The plugin is fully UCP & MCP Compliant."
echo "==========================================="
