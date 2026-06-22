---
name: bitrix24-partners-cli
description: |
  Use when scraping Bitrix24 partner directory pages or importing partner data from CSV
  into the database. Covers partners:scrape (full scrape, update by specific IDs, resume
  from interruption) and bitrix24:partners:import (full-sync, partial-sync, dry-run).
  Helps agents choose the correct command, options, zone, and sync mode, and handle
  ban detection, state files, and output paths.
---

# Bitrix24 Partners CLI

CLI for scraping Bitrix24 partner directories and importing partner data into PostgreSQL.

## Command Entry Point

All commands run through Symfony Console inside Docker:

```bash
docker compose run --rm php-cli php bin/console <command>
```

When working inside the repository without Docker, use directly:

```bash
php bin/console <command>
```

If the command surface may have changed, inspect it first:

```bash
docker compose run --rm php-cli php bin/console list
```

## Environment

These commands require a running Docker environment with PostgreSQL:

```bash
docker compose up -d
```

Database connection is resolved from `.env` variables (`DATABASE_HOST`, `DATABASE_NAME`, `DATABASE_USER`, `DATABASE_PASSWORD`).

## Scrape: Full Partner Directory

Scrapes the Bitrix24 partner directory and saves results as a CSV file.

```bash
docker compose run --rm php-cli php bin/console partners:scrape --zone=ru --output-dir=var/scraper
```

Options:

| Option | Default | Description |
|--------|---------|-------------|
| `--zone` | `ru` | Bitrix24 zone: `ru` (Russia) or `kz` (Kazakhstan) |
| `--output-dir` | — *(required)* | Directory for CSV output and state files |
| `--request-delay` | `2` | Delay between HTTP requests in seconds. Increase if ban detected |
| `--insecure` | off | Disable SSL verification. Dev only, never use in production |
| `--resume` | off | Resume scraping from the last saved state (`state.json`) |

Output: `<output-dir>/partners-YYYYMMDD-HHMMSS.csv`

The command first determines the total number of pages, then scrapes each page with a progress bar.

## Scrape: Update Specific Partners

Scrapes only the given partner IDs and appends/updates them in the existing CSV.

```bash
docker compose run --rm php-cli php bin/console partners:scrape --partner-ids=16592200,22521876 --zone=ru --output-dir=var/scraper
```

`--partner-ids` accepts a comma-separated list of numeric partner IDs. When this option is provided, the command runs in update mode instead of a full scrape.

## Scrape: Resume After Interruption

If scraping was interrupted (ban, network error, manual stop), resume from the last checkpoint:

```bash
docker compose run --rm php-cli php bin/console partners:scrape --zone=ru --output-dir=var/scraper --resume
```

The resume state is stored in `<output-dir>/state.json`. If the state file does not exist, the command will fail with an error — run without `--resume` to start fresh.

## Import: Load CSV into Database

Imports partner data from a CSV file into PostgreSQL via Doctrine ORM.

```bash
docker compose run --rm php-cli php bin/console bitrix24:partners:import <file>
```

Arguments and options:

| Argument/Option | Default | Description |
|----------------|---------|-------------|
| `file` (required) | — | Path to CSV file. If the path contains no `/`, it is resolved relative to `var/scraper/` |
| `--sync-mode` | `full` | `full`: CSV represents the complete dataset — partners not in CSV are soft-deleted. `partial`: CSV is a patch — only add/update, never delete |
| `--dry-run` | off | Show planned actions without applying changes |

Output example:

```
Created: 5 | Updated: 12 | Skipped: 80 | Soft-deleted: 3 | Errors: 0
```

In dry-run mode, each planned action is listed with partner number, title, and details.

## Typical Workflow

The scrape → import pipeline for updating the partner database:

**Step 1 — Scrape partners:**

```bash
docker compose run --rm php-cli php bin/console partners:scrape --zone=ru --output-dir=var/scraper
```

Note the output CSV filename from the command output (e.g., `partners-20260527-094238.csv`).

**Step 2 — Preview import (optional but recommended):**

```bash
docker compose run --rm php-cli php bin/console bitrix24:partners:import partners-20260527-094238.csv --dry-run
```

**Step 3 — Apply import:**

```bash
docker compose run --rm php-cli php bin/console bitrix24:partners:import partners-20260527-094238.csv
```

For partial updates of specific partners:

```bash
docker compose run --rm php-cli php bin/console partners:scrape --partner-ids=12345,67890 --zone=ru --output-dir=var/scraper
docker compose run --rm php-cli php bin/console bitrix24:partners:import partners-YYYYMMDD-HHMMSS.csv --sync-mode=partial
```

## Error Handling

**Ban detected during scrape:**

The scraper detects when access is blocked (empty pages, rate limiting). When this happens:

1. The command saves progress to `state.json`
2. It reports: `Парсинг прерван... Возможно, доступ заблокирован`
3. Increase `--request-delay` (e.g., from 2 to 5 seconds)
4. Wait a few minutes, then resume with `--resume`

**State file not found (with --resume):**

Resume requires a valid `state.json` in the output directory. If missing, run without `--resume` to start a fresh scrape.

**File not found during import:**

If the file path contains no `/`, it is resolved as `var/scraper/<file>`. Use a full path or a filename relative to `var/scraper/`.

**Invalid zone:**

Only `ru` and `kz` are valid zones. Any other value will fail with an error listing the allowed values.

**Invalid sync-mode:**

Only `full` and `partial` are valid sync modes.

## Safety

- Never use `--insecure` in production environments
- Always run `--dry-run` import before the real import when working with unfamiliar data
- The `full` sync mode soft-deletes partners not present in the CSV — use `partial` mode if the CSV is not a complete dataset
- CSV files contain scraped partner data including contact information — treat them as sensitive
