# AGENTS

## Local Skills

This repository contains local workflow skills in `.claude/skills/`.

Before starting issue-driven, maintainer, or repository workflow tasks, agents must:

- check whether `.claude/skills/**/SKILL.md` exists
- load and follow the relevant local skill before relying on generic built-in skills
- treat repository-local skills as higher priority than generic platform skills when both apply

Default local maintainer skill for this repository:

- `.claude/skills/bitrix24-php-lib-maintainer/SKILL.md`

If a local skill defines a stricter workflow than `AGENTS.md`, follow the local skill.

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
