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

## Tests And Linters

Agents working in this repository must run linters and tests only through `Makefile` entrypoints.

Do not call tool binaries directly when an equivalent `make` target exists.

Use:

- `make lint-all` for the full linter pass
- `make test-unit` for the unit test suite
- `make test-functional` for the functional test suite
- `make lint-cs-fixer-fix` and `make lint-rector-fix` only when an autofix pass is needed
