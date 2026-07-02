# Spec Kit + Drupal — Project Runbook

> **Auto-generated** by `/speckit-drupal-setup` on **{{GENERATED_AT}}** for project **{{PROJECT_NAME}}**.
> Re-running setup overwrites this file. For the canonical extension manual see
> `.specify/extensions/drupal/RUNBOOK.md`.

This repo uses the **Drupal Spec Kit extension** to build features with Spec Kit, Cursor,
DDEV, and optional AI site building (MCP).

---

## Prerequisites

### Required

| Tool | Notes |
|------|--------|
| [Spec Kit](https://github.com/github/spec-kit) | `specify` CLI |
| **Cursor** | Slash commands + MCP client |
| **Composer** + **PHP 8.3+** | Drupal 11 |
| **DDEV** | Local stack (recommended) |
| **Git** | Code + `{{CONFIG_SYNC_DIR}}/` |

### Recommended — MCP

| Server | Setup | Used for |
|--------|-------|----------|
| **Figma MCP** | Cursor → add `https://mcp.figma.com/mcp` | `/speckit-drupal-figma-design`, design baselines |
| **Drupal MCP Tools** | `/speckit-drupal-setup-mcp-tools` | `[SB]` site building, `/generate-site-context` |

#### Figma MCP — setup

1. **Cursor Settings → MCP** → add server (or append to `.cursor/mcp.json`):

```json
{
  "mcpServers": {
    "figma": {
      "url": "https://mcp.figma.com/mcp"
    }
  }
}
```

2. Authenticate with Figma when prompted.
3. Reload MCP in Cursor.
4. Test: `/speckit-drupal-figma-design <figma-frame-url>` → writes `design-context.md`.

Config: `.specify/extensions/drupal/drupal-config.yml` → `figma:`

#### Drupal MCP Tools — setup

**After** `ddev start` and `ddev drush site:install`:

```text
/speckit-drupal-setup-mcp-tools
```

Or:

```bash
.specify/extensions/drupal/scripts/bash/setup-mcp-tools.sh
```

Then:

1. Open `/admin/config/services/mcp-tools` → **Development** preset (local only).
2. Reload MCP in Cursor — server **`drupal`** should list tools.
3. Verify:

```bash
ddev start
.specify/extensions/drupal/scripts/bash/verify-mcp-tools.sh
```

After MCP site-building work, always export config:

```bash
ddev drush config:export -y
```

Details: `.specify/extensions/drupal/templates/mcp-tools-workflow.md` and
`.specify/extensions/drupal/RUNBOOK.md` (full Prerequisites section).

---

## One-time setup — greenfield vs brownfield

| | **Greenfield** (new site) | **Brownfield** (existing repo) |
|---|---------------------------|--------------------------------|
| Drupal | Run `/speckit-drupal-install` | **Skip** — `web/core` already exists |
| Database | `ddev drush site:install -y` | **Skip** — use existing DB; `ddev drush config:import -y` |
| Site context | `/generate-site-context` after install | **`/generate-site-context` early** — real content model + theme |
| Theme | Usually new custom theme | Extend existing theme (`brownfield` in `drupal-config.yml`) |

Full steps: `.specify/extensions/drupal/RUNBOOK.md` → **One-time project setup**.

---

## Quick start — greenfield (new DDEV project)

No `web/core` yet. Starting from an empty or Spec Kit-only repo:

```bash
# 1. New Spec Kit project
mkdir my-site && cd my-site
specify init

# 2. Add your unpublished Drupal extension
specify extension add --dev /path/to/extension-src/drupal --force

# 3. Install Drupal into the repo
#    Cursor: /speckit-drupal-install
#    Or:
.specify/extensions/drupal/scripts/bash/install-drupal.sh

# 4. Scaffold workflow (Drush, config/sync, DDEV, templates, runbook)
#    Cursor: /speckit-drupal-setup
#    Or:
.specify/extensions/drupal/scripts/bash/setup-workflow.sh

# 5. DDEV + fresh site
ddev start
ddev composer install
ddev drush site:install -y
ddev launch

# 6. Optional — AI site building via MCP
#    Cursor: /speckit-drupal-setup-mcp-tools
#    Then: /admin/config/services/mcp-tools → Development preset
#    Then: .specify/extensions/drupal/scripts/bash/verify-mcp-tools.sh
#    (Figma MCP: add https://mcp.figma.com/mcp in Cursor Settings → MCP — see Prerequisites)

# 7. Capture site structure for agents
#    Cursor: /generate-site-context
#    Or: .specify/extensions/drupal/scripts/bash/generate-site-context.sh

# 8. First feature
#    /speckit-specify "…" → /speckit-plan → /speckit-tasks → /speckit-implement
```

Set `figma.theme_strategy: greenfield` in `drupal-config.yml` when building a new custom theme.

---

## Quick start — brownfield (existing Drupal repo)

Repo already has `web/`, `composer.json`, and usually `{{CONFIG_SYNC_DIR}}/`.
**Do not** run `/speckit-drupal-install` or `ddev drush site:install`.

```bash
# 1. Open existing Drupal repo
git clone <your-drupal-repo> && cd <your-drupal-repo>

# 2. Add Spec Kit if missing (no .specify/ directory yet)
specify init

# 3. Add your unpublished Drupal extension
specify extension add --dev /path/to/extension-src/drupal --force

# 4. Scaffold workflow (Drush, config/sync, DDEV, templates, runbook)
#    Cursor: /speckit-drupal-setup
#    Or:
.specify/extensions/drupal/scripts/bash/setup-workflow.sh

# 5. DDEV + existing site — import config, do NOT site:install
ddev start
ddev composer install
ddev drush config:import -y && ddev drush cr
ddev launch

# 6. Capture REAL site context (critical for brownfield — do this early)
#    Cursor: /generate-site-context
#    Or: .specify/extensions/drupal/scripts/bash/generate-site-context.sh
#    Review and commit .specify/drupal/data-model.md and site-structure.md

# 7. Set brownfield defaults in .specify/extensions/drupal/drupal-config.yml:
#    figma.theme_strategy: brownfield
#    figma.base_theme: <your-active-theme>   # e.g. olivero

# 8. Optional — AI site building via MCP
#    Cursor: /speckit-drupal-setup-mcp-tools
#    Then: /admin/config/services/mcp-tools → Development preset
#    Then: .specify/extensions/drupal/scripts/bash/verify-mcp-tools.sh

# 9. First feature — document deltas vs project data-model.md
#    /speckit-specify "…" → /speckit-plan (brownfield theme strategy) → /speckit-tasks → /speckit-implement
```

After MCP or admin changes: `ddev drush config:export -y`

---

## Daily commands

| Step | Cursor command | Output |
|------|----------------|--------|
| Describe feature | `/speckit-specify "…"` | `specs/<feature>/spec.md` |
| Plan build | `/speckit-plan` | `plan.md` |
| Break down work | `/speckit-tasks` | `tasks.md` |
| Build it | `/speckit-implement` | code, config, tests |
| Refresh site map | `/generate-site-context` | `.specify/drupal/*.md` |

Optional: `/speckit-clarify`, `/speckit-analyze`, `/speckit-drupal-verify-plan`

**Figma + specify prompt examples**: `.specify/templates/spec-template.md` → *How to prompt `/speckit-specify`* (or extension copy under `.specify/extensions/drupal/templates/`).

---

## Project layout

```text
web/                          Drupal docroot
{{CONFIG_SYNC_DIR}}/            Exported configuration (commit to git)
.specify/drupal/              Live site context (content types, Views, menus)
.specify/extensions/drupal/   Extension (templates, scripts, hooks)
specs/<feature>/              Per-feature spec, plan, tasks, quality checks
.cursor/skills/               Agent slash-command skills
.cursor/mcp.json              Drupal MCP (after setup-mcp-tools)
```

---

## Per-feature workflow

### 1. Specify

```text
/speckit-specify "Your feature description"
```

Add a Figma URL in the prompt to trigger design context extraction.

### 2. Plan

```text
/speckit-plan
```

Documents theme strategy, contrib modules, and config approach.

### 3. Tasks

```text
/speckit-tasks
```

Tasks use work-type tags:

| Tag | Meaning |
|-----|---------|
| **[SB]** | Site building — content types, Views, blocks → export to `{{CONFIG_SYNC_DIR}}/` |
| **[TH]** | Theme — Twig, CSS, JS in `web/themes/custom/` |
| **[CM]** | Custom module — `web/modules/custom/` |
| **[DO]** | DevOps / QA — Composer, Drush, tests, verify scripts |

### 4. Implement

```text
/speckit-implement
```

**Phase 2 gate (blocking)** — must pass before user stories:

```bash
ddev drush config:import -y && ddev drush cr
.specify/extensions/drupal/scripts/bash/verify-foundational.sh
```

**MCP for [SB] tasks:** use Drupal MCP in Cursor, then always:

```bash
ddev drush config:export -y
```

**Polish gate:**

```bash
.specify/extensions/drupal/scripts/bash/verify-quality.sh specs/<feature>
```

### 5. Finish

Hooks after implement:

- `/speckit-drupal-provision-site` — demo content from `drupal-admin-checklist.yml`
- `/speckit-drupal-finalize` — cache rebuild, quality verify
- `/speckit-drupal-quality-report` — stakeholder summary

### 6. Update project context

```text
/generate-site-context
```

---

## Agents should read

Before writing specs or code, read:

- `.specify/drupal/data-model.md` — content model
- `.specify/drupal/site-structure.md` — themes, menus, blocks
- `.specify/drupal/sites.yml` — site identity
- `specs/<active-feature>/plan.md` — current feature architecture

---

## Quality rules (mandatory for user-facing features)

| Rule | Target |
|------|--------|
| **QR-PERF-001** | Primary URL ≤ **2s** load |
| **QR-A11Y-001** | **WCAG2AA** — zero pa11y errors |

Per-feature markers: `specs/<feature>/quality-checks.yml`

Configure defaults: `.specify/extensions/drupal/drupal-config.yml`

---

## DDEV cheat sheet

```bash
ddev start
ddev stop
ddev drush cr
ddev drush config:export -y
ddev drush config:import -y
ddev drush uli                    # one-time login link
ddev launch
```

---

## Extension install (unpublished)

This extension is **per-project**, not a global Cursor plugin:

```bash
specify extension add --dev /path/to/extension-src/drupal --force
```

Full manual: `.specify/extensions/drupal/RUNBOOK.md`

---

## Troubleshooting

| Issue | Action |
|-------|--------|
| Foundational gate fails | Fix checklist in `specs/<feature>/foundational-checklist.yml`; re-import config |
| MCP not working | `/speckit-drupal-setup-mcp-tools`; `verify-mcp-tools.sh` |
| Agent lacks site context | `/generate-site-context` |
| Quality verify fails | See `specs/<feature>/quality-results.md` after `/speckit-drupal-quality-report` |
