---
name: bitrix24-php-lib-maintainer
description: |
  Use this skill whenever working with GitHub issues or maintainer workflows in the mesilov/bitrix24-php-lib repository:
  reading or creating issues, planning implementation from an issue, choosing the correct base branch,
  updating CHANGELOG.md, or preparing a pull request that follows the repository template and quality gate.
  Use the bitrix24-dev MCP server when issue context depends on official Bitrix24 documentation.
user-invocable: true
allowed-tools: Bash, mcp__github__issue_read, mcp__github__issue_write, mcp__github__search_issues, mcp__github__pull_request_read, mcp__github__create_pull_request, mcp__github__list_commits, mcp__bitrix24_dev__bitrix_search, mcp__bitrix24_dev__bitrix_method_details, mcp__bitrix24_dev__bitrix_article_details, mcp__bitrix24_dev__bitrix_event_details, mcp__bitrix24_dev__bitrix_app_development_doc_details
---

# bitrix24-php-lib Maintainer

Repository: **mesilov/bitrix24-php-lib** (`owner: mesilov`, `repo: bitrix24-php-lib`)

---

## On skill invocation: verify project MCP and repo workflow

Before doing issue-driven work, confirm the repository-level setup:

1. `.mcp.json` exists in the repository root.
2. `.mcp.json` contains the `bitrix24-dev` server:

```json
{
  "mcpServers": {
    "bitrix24-dev": {
      "type": "http",
      "url": "https://mcp-dev.bitrix24.tech/mcp"
    }
  }
}
```

3. The MCP server is available in the current client.
4. If `.mcp.json` changed after the last pull, restart the client before continuing.

Availability check rule:

- do not treat an empty generic MCP resource listing as proof that `bitrix24-dev` is unavailable
- confirm availability with a lightweight live tool call such as `mcp__bitrix24_dev__bitrix_search`

Project rule: run linters and tests only through `Makefile` entrypoints. Do not call tool binaries directly when an equivalent `make` target exists.

Primary quality commands:

```bash
make lint-all
make test-unit
make test-functional
```

Autofix commands are allowed only when an autofix pass is explicitly needed:

```bash
make lint-cs-fixer-fix
make lint-rector-fix
```

---

## Working with an existing issue

When given an issue number, load it first via GitHub tools and read:

- title
- body
- labels
- linked discussion or acceptance criteria

Do not start implementation from a short user summary if the issue already contains the authoritative scope.

If the issue affects Bitrix24 install flow, auth flow, lifecycle events, or SDK-backed contracts, expand context from official Bitrix24 documentation through the `bitrix24-dev` MCP server before planning the change.

If the issue changes Composer dependency constraints or package versions:

1. inspect the current `composer.json` metadata first, especially:
   - `minimum-stability`
   - existing exact or ranged constraints
   - nearby locked packages that may restrict the upgrade path
2. verify installability through `Makefile` entrypoints before finalizing the implementation:

```bash
make composer "show <package>"
make composer "update <package> --dry-run"
```

3. if the issue asks for a version range that is not currently installable in this repository, do not copy the requested constraint blindly
4. choose the narrowest installable constraint that satisfies the actual goal
5. document the deviation explicitly in:
   - the `.tasks/<issue-number>/...-plan.md`
   - `CHANGELOG.md` when release-facing behavior changes
   - the pull request description

Useful MCP tools:

| Tool | When to use |
|---|---|
| `mcp__bitrix24_dev__bitrix_search` | when the exact method, event, or article name is unknown |
| `mcp__bitrix24_dev__bitrix_method_details` | when the change depends on a specific REST method |
| `mcp__bitrix24_dev__bitrix_event_details` | when the issue is about `ONAPPINSTALL`, lifecycle hooks, or event semantics |
| `mcp__bitrix24_dev__bitrix_article_details` | when you need overview or concept documentation |
| `mcp__bitrix24_dev__bitrix_app_development_doc_details` | when the issue touches app-development rules or platform behavior |

Record the relevant findings in the implementation plan so the plan is grounded in current documentation, not memory.

---

## GitHub CLI fallback

When GitHub MCP tools are unavailable or return auth errors, use `gh` via `Bash`:

```bash
gh search issues "<query>" --repo mesilov/bitrix24-php-lib --state open
gh label list --repo mesilov/bitrix24-php-lib
gh issue view <number> --repo mesilov/bitrix24-php-lib
gh issue create --repo mesilov/bitrix24-php-lib --title "..." --body "..."
gh pr create --repo mesilov/bitrix24-php-lib --title "..." --body "..." --base <branch>
```

Use the CLI only as a fallback, not as the first choice when MCP tools work.

---

## Creating a new issue

Before creating a new issue:

1. Search for duplicates in `mesilov/bitrix24-php-lib`.
2. Verify the exact label names in the repository before applying them.
3. Write the issue so that another maintainer can implement it without guesswork.

Recommended issue body structure:

```markdown
## Problem

<What is broken, missing, or unclear?>

## Proposed solution

<What should be changed and where?>

## Acceptance criteria

- [ ] <criterion 1>
- [ ] <criterion 2>
- [ ] <criterion 3>
```

Title rules:

- Feature: `Add <feature description>`
- Bug fix: `Fix <what is broken>`
- Refactoring: `Refactor <what and why>`
- Documentation: `Document <topic>`

Keep the title concrete. Avoid vague titles such as `Fix tests` or `Improve code`.

---

## Project conventions for implementation work

### Source layout

Main bounded contexts in this repository:

- `ApplicationInstallations`
- `ApplicationSettings`
- `Bitrix24Accounts`
- `ContactPersons`
- `Journal`

Typical code layout:

```text
src/<BoundedContext>/
    Docs/
    Entity/
    Infrastructure/
    Services/
    UseCase/
tests/Unit/<BoundedContext>/
tests/Functional/<BoundedContext>/
tests/Helpers/
```

Follow the existing bounded-context structure. Do not introduce a new folder pattern when the current context already has an established one.

### Branch naming

Use:

```text
feature/<issue-number>-<short-slug>
bugfix/<issue-number>-<short-slug>
```

Examples from the repository:

- `feature/99-bump-dependencies`
- `bugfix/90-fix-app-install`

### Base branch

Default rule:

- branch from `dev` for regular issue work
- use `main` only for release or hotfix work when that target is explicit

If the correct base branch is unclear from the issue context, ask before branching. Do not guess.

### Commit messages

Use an imperative subject line that says what changed:

```text
Fix install flow for tokenless UI installs
Add functional coverage for duplicate ONAPPINSTALL handling
Update README with MCP and Makefile workflow
```

When the change is tied to a specific issue, include the issue reference if it improves traceability.
Do not use Conventional Commits unless the repository starts using them explicitly.

---

## Task folder and implementation plan

This repository already uses `.tasks/<issue-number>/` for implementation planning.

Before writing production code for an issue:

1. Create or reuse `.tasks/<issue-number>/`.
2. Create a plan file named:

```text
.tasks/<issue-number>/<short-slug>-plan.md
```

Examples already in the repository:

- `.tasks/90/install-handler-status-fix-plan.md`
- `.tasks/93/table-names-normalization-plan.md`

Recommended plan structure:

```markdown
## Summary

<What problem is being solved and why?>

## Scope

<What is in scope and what is explicitly out of scope?>

## Target contract

<Expected behavior after the change>

## Implementation changes

1. <file / module / rule to change>
2. <tests to update>
3. <docs or changelog to update>

## Test cases and scenarios

1. <unit scenarios>
2. <functional scenarios>

## Assumptions and defaults

<Any decisions that must stay explicit>
```

Plan rules:

- use exact file paths when possible
- name the concrete classes, handlers, commands, repositories, or docs involved
- include verification steps before coding starts
- present the plan to the user and wait for explicit approval before production edits

---

## Start-of-work protocol for implementing an issue

When the user asks to implement an issue, follow this order:

### Step 1 — Load the issue

Read the full issue first.

### Step 2 — Expand external context when needed

If the issue depends on Bitrix24 platform behavior, contracts, events, or official docs, fetch the relevant documentation through `bitrix24-dev`.

### Step 3 — Choose the base branch

Use `dev` by default for normal development work. Use `main` only for explicit hotfix or release work.

### Step 4 — Create the working branch

Use `feature/<issue-number>-<slug>` or `bugfix/<issue-number>-<slug>`.

### Step 5 — Create the task plan

Write `.tasks/<issue-number>/<slug>-plan.md` before production changes.

### Step 6 — Review the plan

Check the plan for:

- unambiguity
- internal consistency
- full coverage of tests and docs
- installability of any dependency/version changes under the current Composer metadata

### Step 7 — Get approval

Show the plan and wait for explicit approval before editing production code.

### Step 8 — Implement and verify

Work from the approved plan, updating it if scope changes.

---

## Testing and quality gate

For code changes, run checks in this order:

```bash
make lint-all
make test-unit
make test-functional
```

Rules:

- Use only `make` targets, never direct binary calls.
- If `make lint-all` fails, fix the root cause before moving to tests.
- If `make test-unit` fails, fix that before running `make test-functional`.
- Do not skip failing tests or comment them out to get green output.
- For docs-only or metadata-only changes, tests may be skipped, but the final report must say that explicitly.

When a formatter or automated refactor is needed, use:

```bash
make lint-cs-fixer-fix
make lint-rector-fix
```

Then rerun the full quality gate.

---

## CHANGELOG.md rules

The repository does not use an `Unreleased` section by default. Do not invent one.

Before changing `CHANGELOG.md`:

1. Read the current top section.
2. Preserve the existing style and heading structure.
3. Add release-note entries only when the change is user-visible, release-relevant, or explicitly requested.

Typical cases that should update `CHANGELOG.md`:

- new features
- deprecations or BC changes
- externally visible bug fixes
- developer workflow changes that affect contributors

When issue references exist, prefer linking them in the same style already used in the file.

---

## Pull request workflow

After the quality gate is green:

### Step 1 — Re-read the PR template from disk

Always read:

```bash
cat .github/PULL_REQUEST_TEMPLATE.md
```

Do not use a memorized PR body.

### Step 2 — Fill the template exactly

Respect the existing table fields:

- `Bug fix?`
- `New feature?`
- `Deprecations?`
- `Issues`
- `License`

When the PR closes an issue, use the repository convention from the template:

```text
Fix #<issue-number>
```

### Step 3 — Summarize verification honestly

List which commands were run and whether they passed:

- `make lint-all`
- `make test-unit`
- `make test-functional`

If some checks were intentionally skipped, state exactly why.

If the final implementation intentionally differs from the literal issue text, state that explicitly in the PR body with the technical reason. Example cases:

- requested package version is not yet tagged stable
- requested constraint conflicts with `minimum-stability`
- requested change needs a narrower installable range because of a related locked package

### Step 4 — Create the PR against the correct base branch

Use the same base branch that was chosen at branch creation time.

### Step 5 — Return the PR URL

After creation, return the PR URL to the user.

---

## Maintainer mindset for this repository

- All work is organized through issues.
- Think and discuss first, then write code.
- Prefer bounded-context consistency over one-off shortcuts.
- Reuse existing builders, helpers, and repository patterns in tests.
- When documentation or workflow rules already exist in `README.md`, `AGENTS.md`, or `.tasks/`, align with them instead of inventing a parallel process.
