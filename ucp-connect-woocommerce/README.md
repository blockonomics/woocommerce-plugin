> [!WARNING]
> **For Learning Purposes Only**
> This plugin was "vibe coded" as an experiment to explore Agentic Commerce concepts. It is **NOT** production-ready and should be used for educational or testing purposes only.

# UCP Connect for WooCommerce: Agentic Commerce Endpoint

![License](https://img.shields.io/badge/License-GPLv2-blue.svg)
![Version](https://img.shields.io/badge/Version-2.0.1-green.svg)
![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-blue.svg)
![WooCommerce](https://img.shields.io/badge/WooCommerce-Active-violet.svg)

**Turn your WooCommerce store into an AI-ready commerce powerhouse.**

UCP Connect for WooCommerce bridges the gap between traditional e-commerce and the new wave of **Agentic AI**. It exposes your inventory capabilities via the **Universal Commerce Protocol (UCP)** and **Model Context Protocol (MCP)**, allowing AI agents—from browser-based assistants to powerful desktop models like Claude—to seamlessly discover, search, and purchase products from your catalog.

---

## 🚀 Why This Matters

The way users shop is evolving. Instead of browsing categories, they are asking agents: *"Find me a red hoodie and some matching sneakers."*

Most stores are invisible to these agents. **UCP Connect** makes your store visible, interpretable, and actionable.

### Key Capabilities
- **🔎 Unified "Smart" Search**: Intelligent search logic that understands natural language. It handles singular/plural variations (finding "hoodie" when asked for "hoodies") and understands categories, ensuring agents never hit a dead end.
- **🧠 Rich Product Context**: Exposes key comparison data like Dimensions, Attributes (color, care instructions), and Live Stock Status directly to the AI, enabling "Smart Comparisons" (e.g., *"Which plant fits on my 10cm shelf?"*).
- **📜 List All Products**: Simply pass an empty query to `search_products("")` to retrieve the entire product catalog (paginated).
- **🛒 Stateless Cart Architecture**: Uses secure **Cart Tokens** to manage shopping sessions without cookies or database clutter. Agents can add items, apply coupons (`update_checkout`), and then generate a secure payment link (`complete_checkout`).
- **🤖 Dual-Protocol Support**:
    - **WebMCP**: For browser-based agents (Chrome Extensions, Web Chatbots).
    - **Native MCP Server**: For server-side agents (Claude Desktop, VS Code, Custom Clients).

---

## 📦 Installation

1.  Download the latest release zip.
2.  Upload the `ucp-connect-woocommerce` folder to your `/wp-content/plugins/` directory.
3.  Activate the plugin through the 'Plugins' menu in WordPress.
4.  **That's it.** Your store is now live on the Universal Commerce Protocol network.

---

## 🛠️ Usage & Integration

### For Browser Agents (WebMCP)
*Target: Chrome Extensions, Web Chatbots*

The plugin automatically injects the standard `@mcp-b/global` polyfill. No configuration is required. Agents visiting your site will automatically detect the following tools:
- `search_products(query)`
- `create_checkout(items)`
- `get_discovery()`

### For Desktop Agents (Claude Desktop, etc.)
*Target: Claude, IDEs, Custom RAG pipelines*

You can connect powerful desktop agents directly to your store's native MCP endpoint.

**Configuring Claude Desktop:**

To connect your desktop agent, you need to use our [official proxy package on NPM](https://www.npmjs.com/package/ucp-mcp-proxy).

Add this to your `claude_desktop_config.json`:

```json
{
  "mcpServers": {
    "my-store": {
        "command": "npx",
        "args": [
            "-y", 
            "ucp-mcp-proxy", 
            "https://your-site.com/wp-json/ucp/v1/mcp"
        ]
    }
  }
}
```

**Why a Proxy?**
Desktop agents (like Claude) typically communicate over **stdio** (standard input/output), while WordPress provides an **HTTP** endpoint. The `ucp-mcp-proxy` bridge connects these two worlds seamlessly.

_(Note: Ensure your site is publicly accessible via HTTPS.)_

---

## 📡 API Reference

For developers building custom connectors, the plugin exposes standard REST endpoints compatible with the UCP spec.

| Endpoint | Method | Description |
| :--- | :--- | :--- |
| `/wp-json/ucp/v1/discovery` | `GET` | Returns store capabilities and protocol version. |
| `/wp-json/ucp/v1/search` | `POST` | Semantic-aware product search. |
| `/wp-json/ucp/v1/checkout` | `POST` | **Create Cart**: Returns a secure `cart_token`. |
| `/wp-json/ucp/v1/checkout/{token}` | `POST` | **Update Cart**: Add items or apply coupons to an active session. |
| `/wp-json/ucp/v1/checkout/{token}/complete` | `POST` | **Finalize Order**: Converts the stateless cart into a WooCommerce Order and returns a payment link. |

---

## 🧪 Testing Your Endpoint

You can verify your store's agent readiness using a simple prompt with any UCP-compatible agent:

> **"Use `get_discovery` to check what this store sells, then find a product related to 'shoes' and create a checkout link for it."**

If the agent returns a valid payment URL, your integration is 100% functional.

---

## 🔒 Privacy & Compliance

This plugin is built with privacy by design:
- **Public Data Only**: It only exposes public catalog data (products, prices).
- **Secure Handling**: No customer PII is stored by the plugin. Checkout happens on your secure WooCommerce payment pages.
- **Transparent**: It loads the standard `@mcp-b/global` library from `unpkg.com` to facilitate browser agent communication.

---

## 🤝 Contributing

We welcome contributions! This project is open source and compliant with WordPress.org standards.
1. Fork the repo.
2. Create a feature branch.
3. Submit a Pull Request.

**License**: GPLv2 or later.
