---

description: "Drupal task list template for feature implementation"
---

# Tasks: [FEATURE NAME]

**Input**: Design documents from `/specs/[###-feature-name]/`

**Prerequisites**: plan.md (required), spec.md (required), research.md, data-model.md

**Tests**: Per Constitution Principle II, each user story MUST include PHPUnit tasks
(kernel or functional) unless exempted in spec.

**Quality gates** *(mandatory — do not remove)*:
- **Phase 2 gate**: `verify-foundational.sh` MUST pass before Phase 3 (user stories)
- **Figma features**: `figma-design-checks.yml` + `figma-baselines/` + populated **`figma-asset-manifest.yml`** MUST exist before `[FIGMA]` / `[TH]` story work
- **Per user story**: **QR-FIGMA-002** section screenshot gate after theme tasks — **do not mark story checkpoint `[X]` until `verify-figma-section.sh` exits 0**
- **Phase 9 (Polish)**: **`verify-quality.sh` MUST exit 0** (includes QR-FIGMA-002 full-page + sections) before marking implement complete or any Phase 9 task `[X]`
- **Polish phase**: **QR-PERF-001** (≤2s load) and **QR-A11Y-001** (WCAG2AA) via `verify-quality.sh`

Do not ask the user to add these rules separately.

**Organization**: Tasks grouped by user story for independent Drupal delivery.

## Format: `[ID] [WT?] [P?] [Story] Description`

- **[WT]**: Work type — who owns the task (see below)
- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: User story label (US1, US2, …)
- Paths use Drupal layout: `web/modules/custom/`, `config/sync/`, `web/themes/custom/`

### Work type tags

| Tag | Type | Owner | Typical signals |
|-----|------|-------|-----------------|
| **[SB]** | Site building | Drupal admin / site builder | `config/sync/`, content types, Views, Webform, block placement, menus, BEF |
| **[TH]** | Theme | Front-end / themer | `web/themes/custom/`, `.twig`, `.theme`, `css/`, `js/`, `libraries.yml` |
| **[CM]** | Custom module | Backend dev | `web/modules/custom/`, `src/Plugin`, `.services.yml`, routes |
| **[DO]** | DevOps / QA | Dev / CI | `composer`, `drush`, PHPUnit, PHPCS, `verify-*`, `ddev` |
| **[FIGMA]** | Figma parity | Theme + QA | `figma-design-checks.yml`, `figma-baselines/`, export scripts, QR-FIGMA gates |

Each phase / user story MUST include a **Work type** line (primary + secondary) and a
**Work breakdown** table. Tag every task with `[SB]`, `[TH]`, `[CM]`, or `[DO]`.
If `plan.md` says no custom module, omit `[CM]` unless tasks explicitly add module paths.

## Path Conventions

- **Custom module**: `web/modules/custom/[module]/`
- **Config**: `config/sync/` (exported YAML)
- **Tests**: `web/modules/custom/[module]/tests/` or `web/themes/custom/[theme]/tests/`
- **Theme**: `web/themes/custom/[theme]/`

---

## Phase 1: Drupal Setup (Shared)

**Purpose**: Module scaffold, contrib, theme tooling for this feature

**Work type**: Mixed — **Primary:** DevOps · **Secondary:** Custom module, Theme *(adjust per plan.md)*

| Type | Task IDs | Admin UI? |
|------|----------|-----------|
| DevOps | T001–T003 | No — composer, drush, scaffold |
| Custom module | T001 | No — module scaffold |
| Theme | T00X | No — theme scaffold if greenfield |

- [ ] T001 [CM] Create module scaffold `web/modules/custom/[module]/[module].info.yml`
- [ ] T002 [DO] [P] Add `composer.json` dev deps if needed (phpstan, phpcs)
- [ ] T003 [DO] [P] Enable module: `vendor/bin/drush -r web en [module] -y`

---

## Phase 2: Foundational (Blocking)

**Purpose**: Entities, fields, permissions, contrib modules — **blocks all user stories**

**Work type**: Site building — **Primary:** Site building · **Secondary:** DevOps, Theme

| Type | Task IDs | Admin UI? |
|------|----------|-----------|
| Site building | T004, T008 | Yes — content types, fields, permissions |
| DevOps | T005–T007, T009, T011 | No — composer, export/import, verify gate |
| Theme | T00X | No — default theme enable if applicable |

- [ ] T004 [SB] Define entity/field config (site builder UI or `config/sync/` export) per `data-model.md`
- [ ] T004b [SB] Configure **Manage form display** for each new bundle — all custom fields visible on default form (**QR-CONFIG-001**); re-export config
- [ ] T005 [DO] [P] Install and enable contrib modules from `plan.md` (`composer require` + `drush en`)
- [ ] T006 [SB] [P] Export foundational config to `config/sync/` (`drush config:export -y`)
- [ ] T007 [DO] Import config to active site: `drush config:import -y && drush cr`
- [ ] T008 [SB] Configure permissions in `user.role.*.yml` and re-export if changed
- [ ] T009 [DO] Create/update `foundational-checklist.yml` from `foundational-checklist-template.yml`
- [ ] T010 [DO] Kernel test bootstrap in `tests/src/Kernel/` (optional but recommended)

### MANDATORY GATE — do not start Phase 3 until this passes

- [ ] T011 [DO] **FOUNDATIONAL GATE**: `.specify/extensions/drupal/scripts/bash/verify-foundational.sh` — **MUST exit 0** (`/speckit-implement` stops if this fails)

**Checkpoint**: Foundation ready — user stories proceed **only after T011 passes**

---

## Phase 2.1: Figma sync, assets & baselines *(mandatory when Figma URL or design-context.md)*

**Purpose**: Sync selectors, populate theme asset manifest, export baselines when site ready.

**Work type**: Figma parity — **Primary:** Figma · **Secondary:** DevOps

- [ ] T01X [FIGMA] Ensure `figma-regions.yml` exists (from `/speckit-drupal-figma-design` or auto-generated at plan)
- [ ] T01X [DO] **ARTIFACT GATE**: `/speckit-drupal-setup-feature-artifacts` — syncs `figma-design-checks.yml` + quality-checks *(automatic after plan)*
- [ ] T01X [FIGMA] **ASSET MANIFEST**: Populate `figma-asset-manifest.yml` with every icon/image (Figma MCP **`download_assets`**, NOT `get_design_context` URLs) — see `templates/figma-asset-export.md`
- [ ] T01X [DO] **EXPORT THEME ASSETS**: `export-figma-theme-assets.sh specs/<feature>` — writes PNGs to `web/themes/custom/<theme>/` (**QR-ASSET-005**)
- [ ] T01X [DO] **TRY EXPORT**: `try-export-figma-baselines.sh --when=after_seed specs/<feature>` *(skip OK if site not ready)*
- [ ] T01X [DO] After first theme render: `try-export-figma-baselines.sh --when=after_theme_story specs/<feature>`

**Checkpoint**: Manifest populated + theme assets exported + baselines exported or skip logged — proceed to `[TH]` tasks

---

## Phase 2.5: Seed data & smoke verify *(when feature has setup/seed scripts)*

**Purpose**: Populate demo content and catch broken pages before user story polish

- [ ] T01X [DO] Run idempotent `scripts/seed-*.php` / `scripts/setup-*-site.php` (see `setup-site.php.template`)
- [ ] T01X [DO] Copy `quality-checks-template.yml` → `specs/<feature>/quality-checks.yml` and fill markers
- [ ] T01X [DO] **SMOKE GATE**: `verify-quality.sh specs/<feature>` — QR-SMOKE, QR-LIB, QR-JS, QR-CSS (**MUST exit 0**)

**Checkpoint**: Primary URL renders expected sections with seed content

### Optional: MCP Tools for `[SB]` tasks

If `/speckit-drupal-setup-mcp-tools` was run, use the **`drupal`** MCP server in Cursor
for site-building tasks above instead of manual admin UI — then export config before the gate.
See `.specify/extensions/drupal/templates/mcp-tools-workflow.md`.

```bash
ddev drush config:export -y
.specify/extensions/drupal/scripts/bash/verify-foundational.sh
```

**Do not use MCP for** `[TH]` theme code or `[DO]` verify scripts.

---

## Phase 3: User Story 1 (Priority: P1) 🎯 MVP

**Goal**: [Brief description]

**Independent Test**: [Drush/UI verification steps]

**Work type**: Mixed — **Primary:** [Site building | Theme | Custom module] · **Secondary:** [list types with tasks]

| Type | Task IDs | Admin UI? |
|------|----------|-----------|
| Site building | T0XX | Yes — Views, blocks, … |
| Theme | T0XX | No |
| Custom module | T0XX | No |
| DevOps | T0XX | No — tests, export |

### Tests for User Story 1 (REQUIRED)

- [ ] T012 [DO] [P] [US1] Kernel test `[TestClass].php` for [behavior]
- [ ] T013 [DO] [P] [US1] Functional test `[TestClass].php` for [journey] (if UI)

### Implementation for User Story 1

- [ ] T014 [CM] [P] [US1] Plugin/service in `src/` *(omit if theme-only v1)*
- [ ] T015 [CM] [US1] Form or controller in `src/`
- [ ] T016 [CM] [US1] Route in `[module].routing.yml`
- [ ] T017 [TH] [US1] Twig template or theme hook (if needed)
- [ ] T018 [SB] [US1] Export config changes to `config/sync/`
- [ ] T019 [DO] [US1] Verify cache tags on rendered output
- [ ] T01X [FIGMA] **QR-FIGMA GATE**: `verify-figma-section.sh specs/<feature> <US1-section-slugs>` — on fail run `/speckit-drupal-figma-fix-loop` until pass; **do not mark checkpoint `[X]` until exit 0**

**Checkpoint**: US1 independently testable via PHPUnit + manual QA + **QR-FIGMA-002 passed** for US1 sections

---

## Phase 4: User Story 2 (Priority: P2)

**Work type**: [Primary + secondary — same pattern as Phase 3]

[Same pattern as Phase 3 — Work type line, breakdown table, tagged tasks]

---

## Phase N: Polish, Quality Gates & Drupal Ops *(mandatory)*

**Purpose**: Code quality, config export, and extension quality rules

**Work type**: DevOps — **Primary:** DevOps · **Secondary:** Theme *(if asset polish)*

| Type | Task IDs | Admin UI? |
|------|----------|-----------|
| DevOps | TXXX | No — PHPCS, PHPUnit, verify-quality, export |
| Theme | TXXX | No — image/assets polish |

- [ ] TXXX [DO] Run PHPCS: `vendor/bin/phpcs web/modules/custom/[module]`
- [ ] TXXX [DO] Run PHPUnit: `vendor/bin/phpunit -c web/core/phpunit.xml.dist …/tests`
- [ ] TXXX [DO] `vendor/bin/drush -r web cr`
- [ ] TXXX [DO] `vendor/bin/drush -r web config:export -y` and commit `config/sync/`
- [ ] TXXX [FIGMA] **QR-FIGMA-002 HARD GATE**: `verify-quality.sh specs/<feature>` MUST exit 0 (full-page + all sections). **`/speckit-implement` MUST NOT mark this task or Phase 9 `[X]` until pass** — on fail run `/speckit-drupal-figma-fix-loop`
- [ ] TXXX [DO] **QR-PERF-001 + QR-SMOKE (007–009) + QR-LIB + QR-JS + QR-CSS-005 + QR-ASSET (001–005) + QR-A11Y-001**: same `verify-quality.sh` run
- [ ] TXXX [DO] Update `quickstart.md` with Drush, verify-quality, figma baseline + **export-figma-theme-assets.sh** commands

---

## Dependencies & Execution Order

- Phase 1 → Phase 2 blocks all user stories
- **Phase 2 → T011 verify-foundational.sh MUST pass before any Phase 3+ work**
- **Figma features: Phase 2.1 (manifest + baselines + figma-design-checks.yml) MUST complete before `[TH]` user-story tasks**
- User stories proceed P1 → P2 → P3 (or parallel if staffed); each story ends with **QR-FIGMA-002 hard gate** when Figma-backed — **no `[X]` on story FIGMA tasks until verify exits 0**
- Tests written and FAIL before implementation
- Config export after each story that changes configuration
- Final phase: cache rebuild + config export + **verify-quality.sh** (mandatory)
- **Figma features: Phase 9 incomplete until `verify-quality.sh` exits 0 (QR-FIGMA-002)** — `/speckit-implement` MUST NOT mark Phase 9 `[X]` otherwise

---

## Task Summary

| Phase | Story | Work type (primary) | Task IDs | Count |
|-------|-------|---------------------|----------|-------|
| 1 Setup | — | DevOps / Mixed | T001–T00X | |
| 2 Foundational | — | Site building | T004–T011 | |
| 3 US1 | US1 | Mixed | T0XX–T0XX | |
| N Polish | — | DevOps | TXXX | |

**Format validation**: All tasks use `- [ ] [TaskID] [WT?] [P?] [Story?] Description` ✅
