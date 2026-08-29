# UCP MCP Proxy

A simple proxy connector that allows local AI agents (Claude Desktop, Cline, etc.) to communicate with UCP-enabled WordPress sites via the Model Context Protocol (MCP).

> **⚠️ EDUCATIONAL PURPOSES ONLY**  
> This package is a demonstration of the **Universal Commerce Protocol (UCP)** and **Model Context Protocol (MCP)** integration. It is intended for learning, testing, and development. **Do not use this in a mission-critical or high-security production environment.**

## 🚀 How to Use

This proxy is designed to be run **automatically** by your AI agent (like Claude Desktop or Cline) in the background. You do not need to manually run commands in your terminal to keep it open.

We use `npx` so the agent always fetches the latest version without you needing to manage installations.

### Setup Instructions

**1. Locate your Agent's Config File**
*   **Claude Desktop (Mac):** `~/Library/Application Support/Claude/claude_desktop_config.json`
*   **Claude Desktop (Windows):** `%APPDATA%\Claude\claude_desktop_config.json`
*   **Cline (VS Code):** `.cline/mcp.json` (inside your workspace)

**2. Add the Server Configuration**
Open the config file and add the following entry to the `"mcpServers"` object.  
*Make sure to replace the URL with your actual WordPress UCP endpoint.*

```json
{
  "mcpServers": {
    "my-woocommerce-store": {
      "command": "npx",
      "args": [
        "-y",
        "ucp-mcp-proxy",
        "https://your-wordpress-site.com/wp-json/ucp/v1/mcp"
      ]
    }
  }
}
```

**3. Restart Your Agent**
*   Fully quit and restart Claude Desktop.
*   Or reload your VS Code window.

Your agent will now boot up this proxy silently in the background and connect to your store!



### 2. Debugging

If you are having connection issues, you can append the `--debug` flag. The logs will be printed to your agent's stderr logs.

```json
      "args": [
        "-y",
        "ucp-mcp-proxy",
        "https://your-wordpress-site.com/wp-json/ucp/v1/mcp",
        "--debug"
      ]
```

## 📦 For Developers

To publish this package:

```bash
npm publish --access public
```
