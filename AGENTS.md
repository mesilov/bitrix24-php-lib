# AGENTS

## MCP Servers

This repository includes project-level MCP server configuration in `.mcp.json`.

Developers and agents working with this project must verify the MCP configuration before starting work.

Configured servers:

- `bitrix24-dev` - HTTP MCP server at `https://mcp-dev.bitrix24.tech/mcp`

Checks before work starts:

- ensure `.mcp.json` is present and contains the expected server list
- restart the client after pulling changes to `.mcp.json`
- verify that the configured MCP servers are available in the current client
