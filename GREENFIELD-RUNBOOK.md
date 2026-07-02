# Greenfield Drupal + Figma Pixel-Perfect Runbook

Step-by-step guide for a **new Drupal project** using the Drupal Spec Kit extension: from
`specify init` to **pixel-perfect pages built from Figma**, with **minimal quality-gate re-runs**.

For MCP setup, gate rule tables, and day-to-day operations see
[RUNBOOK.md](./RUNBOOK.md). This document focuses on **shipping the extension**, **bootstrapping
a consumer repo**, and **the spec → clarify → plan → tasks → implement → verify loop**.

---

## Part 1 — What ships in the extension repo

Publish a standalone repo (e.g. `drupal-spec-kit/`) installed via:

```bash
specify extension add --dev /path/to/drupal-spec-kit --force
```

It lands at `.specify/extensions/drupal/` in consumer projects.

### Include in the extension repo

```
drupal-spec-kit/
├── extension.yml
├── README.md
├── RUNBOOK.md
├── GREENFIELD-RUNBOOK.md          # this file (copied to consumer on setup)
├── config-template.yml            # NOT drupal-config.yml
├── package.json
├── package-lock.json
├── .gitignore                     # node_modules/, drupal-config.yml
├── commands/                      # 15 slash-command definitions
├── templates/                     # scaffolds + figma-atomic-components-catalog.yml (generic)
├── scripts/bash/                  # gate + Figma + setup scripts
├── scripts/powershell/            # Windows parity
└── .specify-dev/agent-commands/cursor-agent/
    └── speckit-drupal-*/SKILL.md  # canonical Cursor skills (synced on setup)
```

### Exclude from the extension repo (consumer only)

| Path | Purpose |
|------|---------|
| `drupal-config.yml` | Per-project theme, Figma file_key, quality URLs — copy from `config-template.yml` |
| `node_modules/` | Playwright deps — installed by `ensure-figma-node-deps.sh` |
| `web/`, `config/sync/`, `.ddev/` | Drupal site |
| `specs/<feature>/` | Feature specs, Figma YAML, baselines |
| `.specify/drupal/` | Project content model |
| `.cursor/skills/` | Synced from extension on `/speckit-drupal-setup` |
| `.specify/templates/` | Installed copies from extension templates |

### Before tagging a release

1. `config-template.yml` only — add `drupal-config.yml` to extension `.gitignore`.
2. Keep `templates/figma-atomic-components-catalog.yml` generic (no project `file_key`).
3. Tag releases to match `extension.yml` `version`.

Project-specific atomic defaults belong in **`specs/<feature>/figma-regions.yml`** or a
consumer copy of the catalog under `specs/`, not in the published extension template.

---

## Part 2 — New greenfield project bootstrap

### Prerequisites

- Spec Kit CLI (`specify`)
- Cursor with Figma MCP (`https://mcp.figma.com/mcp`) for design-driven features
- Composer 2.x, PHP 8.3+, DDEV (recommended)
- Optional: `FIGMA_ACCESS_TOKEN` for automated Figma baseline export

### One-time setup

```bash
mkdir my-drupal-site && cd my-drupal-site
specify init
specify extension add --dev /path/to/drupal-spec-kit --force
```

In Cursor:

```text
/speckit-drupal-install
/speckit-drupal-setup
```

Setup copies Drupal spec/plan/tasks templates, syncs Cursor skills, scaffolds `.specify/drupal/`,
installs Playwright deps, and writes at project root:

- `README-SPECKIT-DRUPAL.md`
- `GREENFIELD-RUNBOOK.md` (this file)

Create project config:

```bash
cp .specify/extensions/drupal/config-template.yml \
   .specify/extensions/drupal/drupal-config.yml
# Edit: default_theme_name, figma.file_key, quality_rules.check_urls
```

Start Drupal:

```bash
ddev start
ddev drush site:install -y
```

Optional:

```text
/speckit-drupal-setup-mcp-tools
/generate-site-context
```

**At this point there is no `specs/` directory yet** — that is expected. Part 3 begins when you
run your first `/speckit-specify` for any Figma-driven feature (page, landing, section bundle, etc.).

---

## Part 3 — Pixel-perfect page from Figma

After Part 2 you have Drupal, extension gates, and project config — but **no feature artifacts
yet**. There is no `specs/` folder until you run `/speckit-specify`.

Each feature lives under a numbered directory Spec Kit creates for you:

```text
specs/<NNN>-<short-name>/
```

Examples: `specs/001-homepage/`, `specs/002-about/`, `specs/003-recipe-detail/`. The prefix
(`001`, `002`, …) is assigned automatically; `<short-name>` comes from your feature description.
`.specify/feature.json` records the active feature so later slash commands resolve paths without
you typing the directory name.

**Edit sources of truth once** inside that feature folder; sync scripts propagate to downstream YAML.

### End-to-end workflow (Spec Kit + Drupal extension)

Run these slash commands **in order** for each new Figma-driven page. Gate scripts use the active
feature directory (from `.specify/feature.json` or `SPECIFY_FEATURE_DIRECTORY`).

| Step | Slash command | Purpose | Key outputs |
|------|---------------|---------|-------------|
| 1 | `/speckit-specify` | Create feature + write requirements | `specs/<NNN>-<name>/spec.md`, `.specify/feature.json` |
| 2 | `/speckit-clarify` | Resolve ambiguities before planning | Updated `spec.md` |
| 3 | `/speckit-drupal-figma-design` | Extract Figma layout + regions | `design-context.md`, `figma-regions.yml` |
| 4 | `/speckit-plan` | Technical plan + Drupal data model | `plan.md`, `data-model.md`, `research.md`, `contracts/` |
| 5 | `/speckit-drupal-verify-plan` | Validate plan + Figma artifacts | Exit 0 before tasks |
| 6 | *(agent)* Figma MCP `download_assets` | Record icon/image export URLs in manifest | `figma-asset-manifest.yml` `assets[]` |
| 7 | *(shell)* export theme assets + Figma baselines | Write PNGs/SVGs to theme; section reference PNGs for pixel diff | `web/themes/custom/<theme>/images/`; `figma-baselines/figma-source/` |
| 8 | `/speckit-tasks` | Dependency-ordered implementation tasks | `tasks.md` with `[TH]` / `[SB]` / `[FIGMA]` |
| 9 | `/speckit-implement` | Run all `tasks.md` work for **this feature** (theme, site building, sections) | Theme, config, content; gates between phases |
| 10 | `/speckit-drupal-verify-figma-section` | Scoped pixel gate per section | Pass per region slug |
| 11 | `/speckit-drupal-verify-quality` | Full gate at polish | All QR-* rules |
| 12 | `/speckit-drupal-finalize` | Cache rebuild + optional config export | Ready to ship |
| 13 | `/speckit-drupal-quality-report` | Stakeholder summary | `quality-results.md` |

Step 3 runs automatically when your `/speckit-specify` prompt includes a `figma.com` URL (extension
hook). You can also run it manually after clarify.

**After `/speckit-plan`**, the extension hook runs `setup-feature-artifacts` (syncs Figma checks,
quality rules, and atomic manifest). Re-run manually if you change `figma-regions.yml` later.

**Steps 6–7 before `/speckit-tasks`:** Step 6 is **not** a slash command — the agent
calls Figma MCP **`download_assets`** per node and updates `figma-asset-manifest.yml` with
`export_url` + `theme_path`. Step 7 runs shell scripts that **download those URLs to disk** (theme
icons/images) and export **section baseline PNGs** for QR-FIGMA-002. Skip neither step; YAML-only
manifest entries without step 7 produce gray squares in Twig and failing pixel gates.

| | Step 6 | Step 7 |
|---|--------|--------|
| **What** | Manifest + temporary Figma URLs in YAML | Files on disk in theme + `figma-baselines/` |
| **How** | Agent + Figma MCP `download_assets` | `export-figma-theme-assets.sh` + `export-figma-source-baselines.sh` |
| **Gates** | — | QR-ASSET-005; baselines required for QR-FIGMA-002 |

See `templates/figma-asset-export.md` for rules (use `download_assets`, not `get_design_context` URLs).

### How work types flow: spec → plan → tasks

Plan and tasks do not guess admin vs theme vs Figma — they read what you declare in **`spec.md`**
and what Figma artifacts produce. The Drupal extension installs templates on `/speckit-drupal-setup`
that structure this automatically.

| Work type | Tag in `tasks.md` | Who / where | Declared in `spec.md` | Detailed in `plan.md` | Becomes tasks in |
|-----------|-------------------|-------------|------------------------|------------------------|------------------|
| **Drupal admin UI** | `[SB]` | Site builder / Drupal MCP → `config/sync/` | **Drupal Content Model**, **Configuration vs Custom Code** table, **Design → Drupal Approach** (Views, blocks, Webform, menus) | **Config Strategy**, **data-model.md**, Ambiguities (MCP vs scripts) | Phase 2 Foundational + per-story `[SB]` (block placement, export) |
| **Theme** | `[TH]` | Themer → `web/themes/custom/<theme>/` | **Theme strategy** under UX & Design, user stories per section | **Theme Strategy** table (Figma frame → Twig/CSS path) | Phase 1 scaffold + per-story Twig/CSS/libraries |
| **Figma parity** | `[FIGMA]` | Agent + gates → `figma-*` YAML, baselines, verify scripts | Success criteria (e.g. ≤1% diff per section), **UX & Design (Figma)** | `figma-regions.yml`, synced `figma-design-checks.yml`, asset manifest | Phase 2.1 assets/baselines + per-story `verify-figma-section` + Phase 9 full quality |
| **Custom module** | `[CM]` | Backend → `web/modules/custom/` | Only when spec says custom code (not config-exportable) | Module boundaries in plan | Phase 1/2 if needed |
| **DevOps / QA** | `[DO]` | Drush, verify scripts, PHPUnit | NFR quality rules (QR-PERF, QR-A11Y) | Quality gates section | Phase 9 polish, gate tasks |

**Figma-specific inputs** (not work-type tags, but feed `[FIGMA]` tasks):

- `/speckit-drupal-figma-design` → `design-context.md`, `figma-regions.yml` (sections + `atomic_components[]`)
- After plan → sync → `figma-design-checks.yml`, `quality-checks.yml`
- Steps 6–7 → `figma-asset-manifest.yml`, theme `images/`, `figma-baselines/figma-source/`

**Pipeline:**

```text
/speckit-specify  →  spec.md (content model, config vs code, UX/Figma, theme strategy)
/speckit-plan     →  plan.md + data-model.md (config strategy, theme strategy, MCP decisions)
/speckit-tasks    →  tasks.md ([SB] [TH] [FIGMA] tags per tasks-template.md)
/speckit-implement → agent runs tagged tasks; MCP for [SB]; gates for [FIGMA]
```

Call out **site building vs theme vs Figma** in your specify prompt so `spec.md` splits work correctly
before plan and tasks run. See also `.specify/templates/spec-template.md` *(How to prompt)* section.

### Example prompts (homepage — adapt for any page)

Use these as templates; replace the Figma URL, page name, and acceptance criteria for your feature.

**1 — Specify** (creates `specs/` and the feature folder — **include SB / TH / Figma scope**):

```text
/speckit-specify Build a pixel-perfect marketing homepage for a recipe blog as the site front page (/).

Figma: https://www.figma.com/design/EXAMPLE/homepage?node-id=1-2
Match this frame at 1440px desktop. Success = visual parity within 1% per section vs Figma baselines.

Theme: greenfield — new custom theme `my_theme` under web/themes/custom/

Site building [SB] (config export to config/sync/):
- Recipe content type with image, body, prep time, category reference
- Category taxonomy
- Views: featured recipes carousel, paginated recipe grid
- Block placement for front page regions (hero, explore, featured, grid, about, newsletter)
- Webform: newsletter signup
- Main menu + footer links

Theme [TH]:
- Twig + CSS per Figma section (header, hero, explore, featured, grid, about, newsletter, footer)
- Design tokens from Figma (typography, colors, spacing)
- Icon assets referenced from theme images/

Figma [FIGMA]:
- All sections in figma-regions.yml with atomic_components for search icon, newsletter form, category icons
- QR-FIGMA-002 gate per section after theme work

Out of scope v1: user accounts, search results page, recipe detail page.
```

**2 — Clarify** (recommended before plan; answer the agent’s questions in chat):

```text
/speckit-clarify
```

**3 — Figma design** (skip if already ran from specify hook; use to refresh after spec edits):

```text
/speckit-drupal-figma-design
```

**4 — Plan**:

```text
/speckit-plan
```

**5 — Verify plan**:

```text
/speckit-drupal-verify-plan
```

**6 — Figma assets (agent, step 6)** — no slash command; paste into chat after step 5:

```text
Using Figma MCP download_assets, export every icon and decorative image needed for this feature.
Read figma-regions.yml and figma-asset-manifest.yml. For each asset use the child figma_node_id
(not the section frame), defaultFormat png (svg for simple icons if appropriate), defaultScale 2
for UI icons. Update figma-asset-manifest.yml assets[] with export_url, theme_path, and display
dimensions. Do not use get_design_context asset URLs. See templates/figma-asset-export.md.
```

**7 — Export to disk (shell, step 7)** — run in terminal before tasks:

```bash
FEATURE="$(jq -r '.feature_directory' .specify/feature.json)"
.specify/extensions/drupal/scripts/bash/export-figma-theme-assets.sh "$FEATURE"
.specify/extensions/drupal/scripts/bash/export-figma-source-baselines.sh "$FEATURE"
```

**8 — Tasks**:

```text
/speckit-tasks
```

**9 — Implement** — runs all feature tasks (theme, site building, sections, gates):

```text
/speckit-implement
```

**10 — Fix Figma drift** (after a scoped section gate fails):

```text
/speckit-drupal-figma-fix-loop
```

### What exists after each step (first feature)

```text
After /speckit-specify:
  specs/001-<short-name>/spec.md
  .specify/feature.json

After /speckit-drupal-figma-design:
  specs/001-<short-name>/design-context.md
  specs/001-<short-name>/figma-regions.yml

After /speckit-plan:
  specs/001-<short-name>/plan.md
  specs/001-<short-name>/data-model.md
  specs/001-<short-name>/figma-design-checks.yml   # synced — do not hand-edit
  specs/001-<short-name>/quality-checks.yml        # synced — do not hand-edit

After steps 6–7:
  specs/001-<short-name>/figma-asset-manifest.yml  # assets[] with export_url
  web/themes/custom/<theme>/images/...             # icons/PNGs on disk
  specs/001-<short-name>/figma-baselines/figma-source/*.png

After /speckit-tasks:
  specs/001-<short-name>/tasks.md
```

Replace `001-<short-name>` with your actual directory from `.specify/feature.json`.

### Source-of-truth chain

```text
design-context.md  ← Figma MCP during specify / figma-design
figma-regions.yml  ← regions + atomic_components[] (review and edit here)
        │
        ├─ setup-feature-artifacts (after plan)
        │     ├─ sync-figma-checks-from-design.php → figma-design-checks.yml, quality-checks.yml
        │     └─ sync-figma-atomic-manifest.php   → figma-asset-manifest.yml
        │
        └─ export-figma-theme-assets.sh → web/themes/custom/<theme>/images/
```

**Do not** hand-edit selectors in `figma-design-checks.yml` when `figma-regions.yml` exists.

### Design intake details (workflow steps 1–8)

Steps 1–8 above are **design intake** — spec, Figma, plan, assets, and `tasks.md`. No theme
implementation yet.

**`figma-regions.yml` checklist** (review after step 3):

- [ ] One `regions[]` row per design section in your Figma frame
- [ ] `selector` matches the BEM class you will implement (e.g. `.site-header`)
- [ ] `atomic_components[]` lists **child** Figma nodes for icons, buttons, composite forms
- [ ] Optional: project catalog copy under `$FEATURE/figma-atomic-catalog.yml`

**Shell commands** for steps 6–7 and plan sync (set `FEATURE` from `.specify/feature.json`):

```bash
FEATURE="$(jq -r '.feature_directory' .specify/feature.json)"

# After plan (automatic via hook); re-run if figma-regions.yml changed:
.specify/extensions/drupal/scripts/bash/setup-feature-artifacts.sh "$FEATURE"

# Step 7 — theme icons/images from manifest export_url:
.specify/extensions/drupal/scripts/bash/export-figma-theme-assets.sh "$FEATURE"

# Step 7 — section reference PNGs for pixel diff (FIGMA_ACCESS_TOKEN or commit PNGs manually):
.specify/extensions/drupal/scripts/bash/export-figma-source-baselines.sh "$FEATURE"

# Re-sync atomic manifest if figma-regions.yml changed after plan:
php .specify/extensions/drupal/scripts/bash/sync-figma-atomic-manifest.php \
  --feature="$FEATURE"
```

---

### Build & verify (`/speckit-implement`)

Step 9 runs **everything in `tasks.md` for the active feature** — not a one-time project setup.
Each new Figma page gets its own feature folder and its own implement pass.

```text
/speckit-implement
```

The agent works through `tasks.md` in order. Typical phases (exact names come from your tasks file):

| Tasks phase | What happens | Gate (agent runs) |
|-------------|--------------|-------------------|
| Theme scaffold | Regions, libraries, base CSS from `design-context.md` | — |
| Site building `[SB]` | Content types, Views, blocks, menus, Webforms (often via Drupal MCP) | `/speckit-drupal-verify-foundational` |
| User stories `[TH]` / `[SB]` | Twig + CSS per section; place blocks/Views | `/speckit-drupal-verify-figma-section` per region slug |
| Polish tasks | Seed content, media, smoke markers | (full gate in step 11) |

**You do not run Drush manually** for the normal loop — the agent handles `config:export`,
`config:import`, and `cache rebuild` during implement and finalize when config or theme changes.

If a scoped section gate fails:

```text
/speckit-drupal-figma-fix-loop
```

Then re-run `/speckit-drupal-verify-figma-section` for the failed slug(s) only.

Reserve **`/speckit-drupal-verify-quality`** (step 11) until all sections are done — it runs every
section plus performance, accessibility, and asset checks.

---

### Polish (workflow steps 11–13)

After implement completes and section gates pass:

| Step | Command | Purpose |
|------|---------|---------|
| 11 | `/speckit-drupal-verify-quality` | Full quality gate for this feature |
| 12 | `/speckit-drupal-finalize` | Cache rebuild, optional config export |
| 13 | `/speckit-drupal-quality-report` | Stakeholder `quality-results.md` |

---

## Part 4 — Success criteria and re-run budget

| Goal | How the extension enforces it |
|------|--------------------------------|
| Pixel parity | QR-FIGMA-002: Playwright diff vs `figma-baselines/figma-source/` per section |
| Atomic controls | QR-ASSET-006, QR-SMOKE-011, `atomic_components[]` in regions |
| Icons render (not gray squares) | Figma MCP **`download_assets`** only; `<img src="...svg">` in Twig; QR-ASSET-004/005 |
| Composite forms | QR-THEME-003 (`<form{{ attributes }}>`), QR-CSS-015/016, QR-SMOKE-011 |
| No config drift | Edit `figma-regions.yml` only → re-run `setup-feature-artifacts` |
| Cheap gates first | `verify-plan` → `verify-foundational` → `verify-figma-section` → `verify-quality` last |

**Target re-run budget (new Figma page):**

- 0–1 full `verify-quality.sh` failures at polish
- 1–2 scoped `verify-figma-section.sh` re-runs per story maximum

### Rules to minimize re-runs

1. Finish steps 6–7 before `/speckit-tasks` so assets and baselines exist on disk.
2. During `/speckit-implement`, pass **foundational gate** before section theme stories; use **section-scoped** verify during stories.
3. Reserve **`/speckit-drupal-verify-quality`** for polish (step 11).
4. Populate **`figma-asset-manifest.yml` once** during design intake; re-export theme assets only when the manifest changes.
5. Do not use `--force` on `setup-feature-artifacts` unless regions or sync structure changed.
6. Map **child** Figma node IDs for icons — never reuse section frame IDs for mail/search icons.

---

## Part 5 — Troubleshooting

### Gray square instead of icon

**Cause:** Corrupt PNG from `get_design_context` URL saved as `.png`, or missing asset file.

**Fix:**

1. Re-export via Figma MCP **`download_assets`** (`defaultFormat: png`, `defaultScale: 2` for UI icons).
2. For SVG icons, commit hand-traced or exported `.svg` under `images/icons/`.
3. Reference with `<img src="{{ theme_assets }}/images/icons/....">` in Twig — avoid CSS-only `::before` backgrounds for critical icons.
4. Run `export-figma-theme-assets.sh` and `verify-quality.sh` (QR-ASSET-005).

### Newsletter / webform missing `<form>` wrapper

**Cause:** Custom `webform--*.html.twig` renders `{{ element }}` without `<form{{ attributes }}>`.

**Fix:** Wrap element in form tag (QR-THEME-003). Re-run `check-webform-templates.php`.

### Section gate passes but atomic control wrong

**Cause:** Section screenshot (`content-below-1440.png`) too coarse; atomic drift hidden.

**Fix:** Add `atomic_components[]` + optional `screenshot` with tight `selector` (e.g. `.newsletter-section__form`) and `optional_baseline: true` until child-node PNG exported.

### QR-FIGMA-002 fails after CSS fix

1. Read `$FEATURE/figma-fix-queue.md` and `figma-baselines/reports/latest.json`.
2. Open diff PNG under `figma-baselines/figma-source/diffs/`.
3. Fix padding/typography via `quality-checks.yml` `component_padding_rules` or theme CSS.
4. Re-run only failed slugs: `verify-figma-section.sh "$FEATURE" <slug>`.

### `atomic_components` empty in `figma-regions.yml`

Sync materializes from **`templates/figma-atomic-components-catalog.yml`** when catalog has matching region slugs and `file_key`. For greenfield projects, **define `atomic_components[]` explicitly** in `figma-regions.yml` during figma-design (recommended).

### Config import fails after MCP site building

Re-run `/speckit-implement` or `/speckit-drupal-finalize` so the agent re-exports/imports config.
The foundational gate catches missing entity displays if active config is out of sync.

---

## Quick reference — gate scripts

Set `FEATURE` from `.specify/feature.json` before running shell gates.

| Script / command | When | Cost |
|--------|------|------|
| `/speckit-drupal-verify-plan` | After `/speckit-plan` | Low |
| `setup-feature-artifacts.sh "$FEATURE"` | After plan (auto) | Medium |
| `/speckit-drupal-verify-foundational` | After Phase 2 site building | Medium |
| `/speckit-drupal-verify-figma-section` or `verify-figma-section.sh "$FEATURE" [slugs…]` | After each story `[TH]` | Medium |
| `/speckit-drupal-verify-quality` or `verify-quality.sh "$FEATURE"` | Phase D / finalize | High |
| `sync-figma-atomic-manifest.php --feature="$FEATURE"` | After regions/catalog edit | Low |

Full rule IDs: `templates/drupal-quality-rules.md`.

---

## Cursor slash commands (summary)

**Spec Kit core**

```text
/speckit-specify
/speckit-clarify
/speckit-plan
/speckit-tasks
/speckit-implement
```

**Drupal extension**

```text
/speckit-drupal-install
/speckit-drupal-setup
/speckit-drupal-setup-mcp-tools
/speckit-drupal-figma-design
/speckit-drupal-verify-plan
/speckit-drupal-verify-foundational
/speckit-drupal-verify-figma-section
/speckit-drupal-figma-fix-loop
/speckit-drupal-verify-quality
/speckit-drupal-finalize
/speckit-drupal-quality-report
```

---

*Extension version: see `extension.yml`. Generated copies of this file in consumer projects are updated when you re-run `/speckit-drupal-setup`.*
