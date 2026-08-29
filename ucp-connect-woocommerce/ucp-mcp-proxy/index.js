#!/usr/bin/env node

/**
 * UCP MCP Proxy
 * Connects a local MCP client (checking stdio) to a remote UCP endpoint.
 * 
 * Usage: ucp-mcp-proxy <url> [--debug]
 */

const http = require('http');
const https = require('https');
const url = require('url');

// Parse Arguments
const args = process.argv.slice(2);
const targetUrlStr = args[0];
const isDebug = args.includes('--debug');

if (!targetUrlStr || targetUrlStr.startsWith('-')) {
    console.error("Usage: ucp-mcp-proxy <url> [--debug]");
    process.exit(1);
}

// Validation
let targetUrl;
try {
    targetUrl = new url.URL(targetUrlStr);
} catch (e) {
    console.error(`Invalid URL: ${targetUrlStr}`);
    process.exit(1);
}

// Logger (Writes to stderr to avoid breaking invalid JSON on stdout)
function debug(msg) {
    if (isDebug) {
        console.error(`[DEBUG] ${msg}`);
    }
}

debug(`Starting proxy for: ${targetUrl.href}`);

// Process Stdio
process.stdin.setEncoding('utf8');

let buffer = '';
let messageQueue = Promise.resolve();

process.stdin.on('data', (chunk) => {
    buffer += chunk;

    const lines = buffer.split('\n');
    buffer = lines.pop(); // Keep incomplete chunk

    for (const line of lines) {
        if (line.trim()) {
            messageQueue = messageQueue.then(() => handleMessage(line));
        }
    }
});

async function handleMessage(messageStr) {
    debug(`Received: ${messageStr}`);
    try {
        let message;
        try {
            message = JSON.parse(messageStr);
        } catch (e) {
            debug(`JSON Parse Error: ${e.message}`);
            return;
        }

        // Call remote
        const responseData = await postToRemote(message);

        // JSON-RPC 2.0: Do not reply to Notifications (no id)
        if (message.id === undefined || message.id === null) {
            debug("notification (no id), suppressing response");
            return;
        }

        const responseStr = JSON.stringify(responseData);
        debug(`Sending: ${responseStr}`);

        // Write to stdout (The MCP Transport)
        process.stdout.write(responseStr + "\n");

    } catch (err) {
        debug(`Handler Error: ${err.message}`);
    }
}

function postToRemote(jsonRpcPayload) {
    return new Promise((resolve) => {
        const isHttps = targetUrl.protocol === 'https:';
        const lib = isHttps ? https : http;

        const requestBody = JSON.stringify(jsonRpcPayload);

        const options = {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Content-Length': Buffer.byteLength(requestBody),
                'User-Agent': 'UCP-MCP-Proxy/1.0'
            }
        };

        const req = lib.request(targetUrl, options, (res) => {
            let data = '';

            res.on('data', (chunk) => {
                data += chunk;
            });

            res.on('end', () => {
                try {
                    const json = JSON.parse(data);
                    resolve(json);
                } catch (e) {
                    debug(`Non-JSON Response: ${data.substring(0, 100)}...`);
                    resolve({
                        jsonrpc: "2.0",
                        error: { code: -32000, message: "Invalid response from server" },
                        id: jsonRpcPayload.id || null
                    });
                }
            });
        });

        req.on('error', (e) => {
            debug(`Network Error: ${e.message}`);
            resolve({
                jsonrpc: "2.0",
                error: { code: -32000, message: "Network error: " + e.message },
                id: jsonRpcPayload.id || null
            });
        });

        req.write(requestBody);
        req.end();
    });
}
