# Drupal Spec Kit Extension — Runbook

Operational guide for using the **Drupal Spec Kit extension** with Spec Kit and Cursor.

| Document | Audience |
|----------|----------|
| **[GREENFIELD-RUNBOOK.md](./GREENFIELD-RUNBOOK.md)** | New projects: extension ship manifest, bootstrap, pixel-perfect homepage flow |
| **This file (RUNBOOK.md)** | MCP setup, gate rules, day-to-day operations |
| **`README-SPECKIT-DRUPAL.md`** (project root) | Generated per project by `/speckit-drupal-setup` |
| **`GREENFIELD-RUNBOOK.md`** (project root) | Copy of extension greenfield guide (updated on setup) |

---

## What this extension does

The extension is **not** your Drupal site. It is the **delivery framework** around Spec Kit:

- Installs and scaffolds Drupal (`web/`, `config/sync/`, DDEV)
- Provides Drupal-aware **spec / plan / tasks** templates
- Runs **quality gates** (performance, accessibility, smoke tests)
- Connects **Cursor MCP** to your local site for AI site building
- Maintains **project context** in `.specify/drupal/` (content model, site structure)

Your actual site code lives in `web/`, `config/sync/`, and `specs/<feature>/`.

---

## Prerequisites

### Required

| Tool | Version / notes | Purpose |
|------|-----------------|--------|
| [Spec Kit](https://github.com/github/spec-kit) | `specify` CLI | Feature workflow (specify → plan → tasks → implement) |
| **Cursor** | Latest | AI agent, slash commands, MCP client |
| **Composer** | 2.x | Drupal install, Drush, contrib modules |
| **PHP** | **8.3+** | Drupal 11 requirement |
| **DDEV** | Latest (recommended) | Local Drupal stack — Drush, database, web server |
| **Git** | Any recent | Version control for code and `config/sync/` |

Verify locally:

```bash
specify --version
composer --version
php -v          # must be ≥ 8.3
ddev version    # if using DDEV
```

### Recommended — MCP integrations

Two MCP servers extend this workflow. Both are **optional** but strongly recommended:

| MCP server | Package / URL | Used for |
|------------|---------------|----------|
| **Figma MCP** | [https://mcp.figma.com/mcp](https://mcp.figma.com/mcp) | Design → `design-context.md` during `/speckit-specify` and Figma quality baselines |
| **Drupal MCP Tools** | [drupal/mcp_tools](https://www.drupal.org/project/mcp_tools) | AI site building — content types, Views, blocks, menus during `/speckit-implement` `[SB]` tasks |

Neither replaces git or `config/sync/`. They accelerate discovery and admin UI work; exported config and theme code remain the source of truth.

---

### Set up Figma MCP (Cursor)

Use when features include Figma designs (hook: `/speckit-drupal-figma-design` on Figma URLs in `/speckit-specify`).

**1. Add the Figma MCP server in Cursor**

1. Open **Cursor Settings → MCP** (or **Features → MCP**).
2. Click **Add MCP server** (or edit `.cursor/mcp.json` in the project).
3. Add the official Figma server:

```json
{
  "mcpServers": {
    "figma": {
      "url": "https://mcp.figma.com/mcp"
    }
  }
}
```

4. Save and **authenticate** when Cursor prompts (Figma account OAuth).
5. **Reload MCP** in Cursor so tools such as `get_design_context` appear.

**2. Confirm it works**

- In Cursor chat, confirm the **figma** server shows as connected (green / tools listed).
- Or run `/speckit-drupal-figma-design` with a Figma frame URL:

```text
/speckit-drupal-figma-design https://www.figma.com/design/FILE_KEY/Name?node-id=1-2
```

Expected output: `specs/<feature>/design-context.md`.

**3. Extension config (optional)**

Edit `.specify/extensions/drupal/drupal-config.yml` → `figma:` section (`theme_strategy`, `design_context_file`, etc.).

**Notes**

- Figma MCP is **global to your Cursor user** (OAuth); the project only needs the server entry in `.cursor/mcp.json` if you commit MCP config per repo.
- Without Figma MCP you can still paste design notes manually; automated extraction and QR-FIGMA baselines need the server.

---

### Set up Drupal MCP Tools (per project)

Use for **`[SB]`** site-building tasks during `/speckit-implement` and for `/generate-site-context`.

**Prerequisites:** Drupal installed (`web/core`), DDEV running, site installed (`ddev drush site:install`).

**1. Run the extension setup command**

In Cursor:

```text
/speckit-drupal-setup-mcp-tools
```

Or from the project root:

```bash
.specify/extensions/drupal/scripts/bash/setup-mcp-tools.sh
```

This will:

- `composer require drupal/mcp_tools` (and `drupal/tool`)
- Enable submodules from `drupal-config.yml` (structure, views, blocks, menus, webform, …)
- Merge a **`drupal`** entry into `.cursor/mcp.json` (STDIO via `ddev drush mcp-tools:serve`)

Example `.cursor/mcp.json` entry (paths vary per machine):

```json
{
  "mcpServers": {
    "drupal": {
      "command": "ddev",
      "args": [
        "drush",
        "mcp-tools:serve",
        "--quiet",
        "--uid=1",
        "--scope=read,write"
      ],
      "cwd": "/absolute/path/to/your-project"
    }
  }
}
```

Use `--no-cursor-config` to install modules only without touching `.cursor/mcp.json`.

**2. Choose a Drupal admin preset**

1. Ensure DDEV is up: `ddev start`
2. Open **`/admin/config/services/mcp-tools`** in the browser (`ddev launch` + path).
3. Select **Development** preset for local work (read + write).
4. Use **Staging** (config-only) or **Production** (read-only) on non-local environments — never enable write scope on production.

**3. Reload MCP in Cursor**

- Cursor **Settings → MCP** → reload servers.
- Confirm server name **`drupal`** lists tools (e.g. `mcp_tools_list_content_types`, `mcp_tools_get_vocabularies`).

**4. Verify**

```bash
ddev start   # MCP serve needs a running site
.specify/extensions/drupal/scripts/bash/verify-mcp-tools.sh
```

**5. After every MCP site-building session**

```bash
ddev drush config:export -y
```

Config in `config/sync/` is what you commit — not live database state alone.

**Troubleshooting**

| Issue | Fix |
|-------|-----|
| No `drupal` tools in Cursor | Reload MCP; check `ddev start`; re-run setup script |
| `mcp-tools:serve` fails | `ddev drush en mcp_tools_stdio -y && ddev drush cr` |
| `mcp_tools_webform` missing | `composer require drupal/webform` then re-run setup |
| MCP hangs on slow sites | Prefer Drush for `/generate-site-context` (see runbook below) |

Full workflow: `.specify/extensions/drupal/templates/mcp-tools-workflow.md`

**Security:** Drupal MCP write scope is for **local DDEV only**. Do not point production sites at Cursor with `read,write`.

---

## Where to install the extension

**Install per project, not globally in Cursor.**

Spec Kit extensions live in:

```text
your-project/.specify/extensions/drupal/
```

Cursor skills for slash commands live in:

```text
your-project/.cursor/skills/
```

Hooks are registered in:

```text
your-project/.specify/extensions.yml
```

This keeps each Drupal repo self-contained: same extension version, same hooks, same MCP config for that site.

### Unpublished / local extension (not in Spec Kit community registry)

From a Spec Kit project root, point at your extension source:

```bash
# Clone or copy extension-src/drupal into a path you control, then:
specify extension add --dev /path/to/extension-src/drupal --force
```

`--dev` installs from a local directory. `--force` replaces an existing install.

**Alternative:** copy `extension-src/drupal` manually to `.specify/extensions/drupal/` and register `drupal` in `.specify/extensions.yml` (see an existing project for hook examples).

After install, sync agent skills if your workflow does not do it automatically:

```bash
# Skills ship under .specify/extensions/drupal/.specify-dev/agent-commands/cursor-agent/
# Copy or symlink them into .cursor/skills/ (see setup-mcp-tools / extension docs).
```

You do **not** install this as a Cursor IDE plugin. Cursor reads project-local `.cursor/skills/` and `.cursor/mcp.json`.

---

## One-time project setup (DDEV)

Run once per repository when adopting Spec Kit + this extension. The path depends on whether you are starting **greenfield** (new site) or joining a **brownfield** (existing Drupal repo).

### Greenfield vs brownfield

| | **Greenfield** | **Brownfield** |
|---|----------------|----------------|
| **Starting point** | Empty or Spec Kit-only repo | Existing Drupal codebase (`web/`, `composer.json`, often `config/sync/`) |
| **Drupal install** | Run `/speckit-drupal-install` | **Skip** — Drupal is already present |
| **Fresh database** | `ddev drush site:install -y` | **Skip** — use existing DB; import config instead |
| **Site context** | `/generate-site-context` after install | **`/generate-site-context` first** — captures real content types, Views, theme |
| **Theme strategy** | Often **greenfield** — new theme under `web/themes/custom/` | Usually **brownfield** — extend active theme / subtheme |
| **Config sync** | Export as you build features | Confirm `config_sync_directory` in `settings.php`; `drush cim` before features |
| **DDEV** | Scaffolded by `/speckit-drupal-setup` | Use existing `.ddev/` or run setup (skips if config exists) |

Set default theme strategy in `.specify/extensions/drupal/drupal-config.yml` → `figma.theme_strategy` (`greenfield` | `brownfield` | `hybrid`). Per-feature overrides go in `design-context.md` and `plan.md`.

---

### Greenfield — new Spec Kit + Drupal project

Use when there is no `web/core` yet.

#### Step 0 — Create a Spec Kit project

```bash
mkdir my-site && cd my-site
specify init
```

#### Step 1 — Add the Drupal extension

```bash
specify extension add --dev /path/to/extension-src/drupal --force
```

Verify: `.specify/extensions/drupal/extension.yml` exists.

#### Step 2 — Install Drupal

In Cursor: `/speckit-drupal-install`

Or:

```bash
.specify/extensions/drupal/scripts/bash/install-drupal.sh
```

Creates `web/`, `composer.json`, etc. Preserves `.specify/`, `.cursor/`, `specs/`, `.git/`.

#### Step 3 — Set up the Drupal workflow

In Cursor: `/speckit-drupal-setup`

Or:

```bash
.specify/extensions/drupal/scripts/bash/setup-workflow.sh
```

This:

- Installs Drush (if missing)
- Creates `config/sync/`, `web/modules/custom/`, `web/themes/custom/`
- Sets `config_sync_directory` in `settings.php`
- Scaffolds `.ddev/config.yaml` (when DDEV is on PATH)
- Installs Drupal spec/plan/tasks templates
- Scaffolds `.specify/drupal/` (empty templates)
- **Generates `README-SPECKIT-DRUPAL.md`** at the project root

#### Step 4 — Start DDEV and install the site

```bash
ddev start
ddev composer install    # if not already done
ddev drush site:install -y
ddev launch              # open site in browser
```

#### Step 5 — Set up MCP (recommended)

See **Prerequisites → Set up Figma MCP** and **Set up Drupal MCP Tools** above.

Quick checklist:

1. **Figma MCP** — add `https://mcp.figma.com/mcp` in Cursor Settings → MCP; authenticate; reload.
2. **Drupal MCP** — `/speckit-drupal-setup-mcp-tools` (after site install); Development preset at `/admin/config/services/mcp-tools`; reload MCP; run `verify-mcp-tools.sh`.

#### Step 6 — Capture live site context

After the site exists (even a vanilla install):

In Cursor: `/generate-site-context`

Or:

```bash
.specify/extensions/drupal/scripts/bash/generate-site-context.sh
```

Refreshes `.specify/drupal/data-model.md`, `site-structure.md`, `sites.yml`.

#### Step 7 — Cursor rule (recommended)

Add a project rule (e.g. `.cursor/rules/specify-rules.mdc`) so agents always read:

- `.specify/drupal/data-model.md`
- `.specify/drupal/site-structure.md`
- `.specify/drupal/sites.yml`
- Active feature `specs/<feature>/plan.md`

#### Step 8 — First feature

```text
/speckit-specify "…" → /speckit-plan → /speckit-tasks → /speckit-implement
```

Greenfield features often scaffold a new custom theme in Phase 1 (see `tasks-template.md`).

---

### Brownfield — existing Drupal repository

Use when the repo already has `web/core`, a database, and usually committed configuration.

#### Step 0 — Open the existing repo

```bash
git clone <your-drupal-repo> && cd <your-drupal-repo>
```

Do **not** run `/speckit-drupal-install` — it refuses to run when `web/core` already exists.

#### Step 1 — Add Spec Kit (if missing)

If there is no `.specify/` directory yet:

```bash
specify init
```

Commit or stash local changes before merging Spec Kit scaffolding into an active repo.

#### Step 2 — Add the Drupal extension

```bash
specify extension add --dev /path/to/extension-src/drupal --force
```

Register hooks in `.specify/extensions.yml` if `specify extension add` did not merge them (compare with a reference project).

#### Step 3 — Set up the Drupal workflow

In Cursor: `/speckit-drupal-setup`

Or:

```bash
.specify/extensions/drupal/scripts/bash/setup-workflow.sh
```

Safe on brownfield repos:

- Creates missing `config/sync/`, custom module/theme dirs
- Appends `config_sync_directory` to `settings.php` only if not already set
- Skips `.ddev/config.yaml` if it already exists
- Backs up and installs Drupal templates into `.specify/templates/`
- Scaffolds `.specify/drupal/` **only for missing files** (does not overwrite existing context)
- Generates `README-SPECKIT-DRUPAL.md`

#### Step 4 — Align local environment

```bash
ddev start                    # or use your existing stack
ddev composer install
```

**Import configuration** (do not run `site:install` on an existing site):

```bash
ddev drush config:import -y
ddev drush cr
```

If config drift blocks import, resolve in Drush or export from a known-good environment first.

Optional — export current active config into the repo baseline:

```bash
ddev drush config:export -y
git status config/sync/
```

#### Step 5 — Capture live site context (do this early)

Brownfield agents need the **real** site map, not empty templates:

In Cursor: `/generate-site-context`

Or:

```bash
.specify/extensions/drupal/scripts/bash/generate-site-context.sh
```

Review and commit `.specify/drupal/data-model.md`, `site-structure.md`, `sites.yml`.

Update `sites.yml` with the correct site label, default theme, and Drupal version.

#### Step 6 — Configure extension for brownfield

Edit `.specify/extensions/drupal/drupal-config.yml`:

```yaml
figma:
  theme_strategy: brownfield
  base_theme: olivero          # or your active theme machine name
  subtheme_dir: web/themes/custom

quality_rules:
  performance:
    check_urls:
      - /                      # adjust to your primary URL(s)
```

#### Step 7 — Set up MCP (recommended)

Same as greenfield **Step 5** — after DDEV is running and the site responds:

```text
/speckit-drupal-setup-mcp-tools
```

Then Development preset, reload MCP, `verify-mcp-tools.sh`.

MCP accelerates `[SB]` tasks; brownfield sites often need fewer new content types and more Views/block placement changes — still export after MCP:

```bash
ddev drush config:export -y
```

#### Step 8 — Cursor rule (recommended)

Same as greenfield **Step 7** — point agents at `.specify/drupal/*` and the active feature plan.

#### Step 9 — First feature on brownfield

When specifying and planning:

- Document **deltas** in feature `data-model.md` (what changes vs project `data-model.md`)
- Set **Theme strategy: brownfield** in `plan.md` — Twig overrides on existing theme paths
- Phase 2 foundational gate still applies for new contrib modules, fields, or Views
- Run `verify-foundational.sh` after config export before user-story phases

```text
/generate-site-context   # refresh after foundational work
/speckit-specify "…" → /speckit-plan → /speckit-tasks → /speckit-implement
```

---

## Per-feature workflow

Repeat for every feature (branch or `specs/###-feature-name/`).

```text
┌────────────────────────────────────────────────────────────┐
│  /speckit-specify  →  spec.md                              │
│  /speckit-plan     →  plan.md                              │
│  /speckit-tasks    →  tasks.md                             │
│  /speckit-implement → code + config + tests                │
│  hooks: provision-site, finalize, quality verify           │
│  /generate-site-context → refresh .specify/drupal/         │
└────────────────────────────────────────────────────────────┘
```

### A — Specify

```text
/speckit-specify "Build a recipe blog landing page"
```

- If input contains a Figma URL, optional hook runs `/speckit-drupal-figma-design` → `design-context.md`
- Output: `specs/<feature>/spec.md` (includes QR-PERF-001, QR-A11Y-001)

Optional: `/speckit-clarify`, `/speckit-checklist`

### B — Plan

```text
/speckit-plan
```

Output: `plan.md` — docroot, contrib modules, theme strategy, config sync path.

Optional hook: `/speckit-drupal-verify-plan`

Optional: `/speckit-analyze` (consistency across spec, plan, tasks)

### C — Tasks

```text
/speckit-tasks
```

Output: `tasks.md` in phases:

| Phase | Purpose | Gate |
|-------|---------|------|
| **1** | Scaffold (composer, theme/module shell) | — |
| **2** | Foundational site building (entities, Views, contrib, export) | **`verify-foundational.sh` must pass** |
| **2.5** | Seed content + smoke checks | `verify-quality.sh` (smoke/CSS/JS) |
| **3+** | User stories (theme, config, tests) | — |
| **Polish** | Performance + accessibility | **`verify-quality.sh` (QR-PERF, QR-A11Y)** |

### D — Implement

```text
/speckit-implement
```

Task work types:

| Tag | Owner | Examples |
|-----|-------|----------|
| **[SB]** | Site builder | Content types, Views, Webform, blocks, menus → `config/sync/` |
| **[TH]** | Themer | Twig, CSS, JS → `web/themes/custom/` |
| **[CM]** | Backend dev | Custom modules → `web/modules/custom/` |
| **[DO]** | DevOps / QA | Composer, Drush, PHPUnit, verify scripts |

**MCP rule:** use Drupal MCP for `[SB]` tasks when configured, then always:

```bash
ddev drush config:export -y
```

**Never** use MCP for theme code or verify scripts.

**Phase 2 gate** (blocking):

```bash
.specify/extensions/drupal/scripts/bash/verify-foundational.sh
```

`/speckit-implement` stops if this fails.

### E — Finish (hooks)

After implement, extension hooks run:

1. **`/speckit-drupal-provision-site`** — from `drupal-admin-checklist.yml` (menus, demo nodes, images)
2. **`/speckit-drupal-finalize`** — cache rebuild, optional config export, quality verify

Stakeholder report:

```text
/speckit-drupal-quality-report
```

### F — Refresh project context

```text
/generate-site-context
```

Run after foundational work so the next feature starts from accurate `.specify/drupal/` files.

---

## Command reference

| Slash command | Purpose |
|---------------|---------|
| `/speckit-drupal-install` | Composer create-project Drupal into repo |
| `/speckit-drupal-setup` | Workflow scaffold + root runbook |
| `/speckit-drupal-setup-mcp-tools` | MCP Tools + `.cursor/mcp.json` |
| `/speckit-specify` | Feature spec |
| `/speckit-drupal-figma-design` | Figma → `design-context.md` |
| `/speckit-plan` | Implementation plan |
| `/speckit-drupal-verify-plan` | Validate plan sections |
| `/speckit-tasks` | Task breakdown |
| `/speckit-implement` | Execute tasks |
| `/speckit-drupal-verify-foundational` | Phase 2 gate |
| `/speckit-drupal-verify-quality` | Perf, a11y, smoke, CSS, Figma |
| `/speckit-drupal-quality-report` | `quality-results.md` for stakeholders |
| `/speckit-drupal-provision-site` | Admin checklist provisioning |
| `/speckit-drupal-finalize` | Cache rebuild + export + verify |
| `/generate-site-context` | Refresh `.specify/drupal/` |

Shell scripts (from repo root):

```bash
.specify/extensions/drupal/scripts/bash/verify-foundational.sh
.specify/extensions/drupal/scripts/bash/verify-quality.sh specs/<feature>
.specify/extensions/drupal/scripts/bash/generate-site-context.sh
```

---

## Quality rules (default)

| Rule | Requirement |
|------|-------------|
| **QR-PERF-001** | Primary URL loads in ≤ **2 seconds** |
| **QR-A11Y-001** | **WCAG2AA** (pa11y) — **zero errors** |
| **QR-SMOKE-*** | HTTP 200, content markers (per `quality-checks.yml`) |
| **QR-CSS / QR-FIGMA** | Layout and design baseline checks when configured |

Configure URLs and thresholds in `.specify/extensions/drupal/drupal-config.yml`.

---

## What stays in the project vs the extension

| Extension (reusable) | Project (per site) |
|----------------------|-------------------|
| Templates, verify scripts, hooks | `web/themes/custom/`, `web/modules/custom/` |
| `drupal-config.yml` defaults | `config/sync/` |
| MCP setup scripts | `specs/<feature>/` |
| Runbook template | `.specify/drupal/` (live site map) |
| | Seed scripts, feature checklists |

---

## Troubleshooting

| Problem | Check |
|---------|--------|
| `verify-foundational.sh` fails | `ddev drush config:import -y && ddev drush cr`; review `foundational-checklist.yml` |
| MCP tools missing | Re-run `/speckit-drupal-setup-mcp-tools`; run `verify-mcp-tools.sh` |
| Quality perf fails | Enable caches; check image sizes; reduce carousel weight |
| Quality a11y fails | Fix contrast, labels, heading order; see `quality-results.md` |
| Stale agent context | `/generate-site-context`; update `.cursor/rules` active feature path |
| Extension changes not applied | `specify extension add --dev … --force` from updated `extension-src/drupal` |

---

## Extension development

When editing the extension source:

```bash
specify extension add --dev extension-src/drupal --force
```

Bump `version` in `extension.yml` when releasing changes.
