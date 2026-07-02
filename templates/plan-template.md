# Implementation Plan: [FEATURE]

**Branch**: `[###-feature-name]` | **Date**: [DATE] | **Spec**: [link]

**Input**: Feature specification from `/specs/[###-feature-name]/spec.md`

**Note**: Drupal plan template from the `drupal` Spec Kit extension.

> **Drupal extension quality gates** *(mandatory — do not remove)*: This plan
> automatically includes **QR-PERF-001** (≤2s load), **QR-A11Y-001**
> (WCAG2AA zero pa11y errors), and — when a Figma URL or `design-context.md`
> exists — **QR-FIGMA-001/002** (copy/class hooks + screenshot diff).
> Preserve the **Drupal Quality Rules** and **Figma Design Parity** sections;
> do not ask the user to supply these rules separately.

## Summary

[Primary requirement + technical approach]

## Technical Context

**Drupal Version**: [e.g. 11.3.x]

**Docroot**: `web/`

**Custom Code Paths**:
- Modules: `web/modules/custom/[module_name]/`
- Theme: `web/themes/custom/[theme_name]/` (if applicable)

**Config Sync**: `config/sync/` (committed to VCS)

**Tooling**: [Drush, DDEV, PHPUnit, PHPCS, PHPStan]

**Primary Dependencies**: [contrib modules with versions]

**Storage**: [MariaDB/MySQL/SQLite — DDEV default]

**Testing**: PHPUnit Kernel/Functional per user story; polish phase runs
`.specify/extensions/drupal/scripts/bash/verify-quality.sh` (**QR-PERF-001**,
**QR-A11Y-001**)

**Performance Goals**: Primary URL(s) load ≤ **2 seconds** for anonymous users
(**QR-PERF-001**); Drupal page/render cache for public pages

**Accessibility Goals**: WCAG 2.1 Level AA; automated pa11y **WCAG2AA** scan
with **zero errors** (**QR-A11Y-001**)

**Constraints**: [memory, CDN, multilingual, offline admin]

**Scale/Scope**: [content volume, concurrent editors, traffic]

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Principle | Gate | Status |
|-----------|------|--------|
| I. Code Quality | PHPCS clean; services/plugins over procedural hooks where possible | ⬜ |
| II. Testing Standards | Kernel/functional tests per user story; CI runs PHPUnit | ⬜ |
| III. UX Consistency | Theme + admin patterns; error/empty states in spec | ⬜ |
| IV. Performance | Cache tags/contexts; **QR-PERF-001** ≤2s load on primary URL | ⬜ |
| V. Accessibility | **QR-A11Y-001** automated WCAG2AA scan in tasks/polish | ⬜ |

Violations requiring exceptions MUST be recorded in Complexity Tracking below.

## Drupal Quality Rules *(mandatory — do not remove)*

Extension rules from `.specify/extensions/drupal/templates/drupal-quality-rules.md`:

| Rule | Target | Verification |
|------|--------|--------------|
| QR-PERF-001 | Load ≤ 2s (configurable) | `verify-quality.sh` / `/speckit-drupal-verify-quality` |
| QR-SMOKE-001–010 | HTTP 200, content markers, nav checks, icon markup, content card image sources | `verify-quality.sh` + `quality-checks.yml` |
| QR-THEME-001–002 | No Figma baseline crops in node templates / preprocess | `check-theme-template-assets.php` |
| QR-LIB-001 | Required JS library files reachable | `verify-quality.sh` + `verify-foundational.sh` |
| QR-JS-001 | Expected Drupal behaviors in page JS | `verify-quality.sh` |
| QR-CSS-001–003 | Views `html_list` grid on `{wrapper} ul`; pager flex | `verify-quality.sh` + `views-html-list-css.md` |
| QR-FIGMA-000–003 | Figma source of truth; copy/classes; screenshot diff; component diff | `figma-design-checks.yml` + `verify-quality.sh` |
| QR-ASSET-001–004 | Images/static assets reachable per scope | `quality-checks.yml` (`assets.enabled: true`) |
| QR-CONFIG-001–002 | All bundle fields on default form display; export after changes | `verify-foundational.sh` + `config/sync/` |
| QR-A11Y-001 | WCAG2AA; informational when `defer_to_figma: true` | Same |

**Primary URL(s)**: [e.g. `/` — document paths checked by verify-quality]

**Config reference**: `quality_rules.performance.check_urls` in
`.specify/extensions/drupal/drupal-config.yml` (default `/`)

Copy during `/speckit-plan` (automatic via `/speckit-drupal-setup-feature-artifacts` hook):

- `templates/quality-checks-template.yml` → `specs/<feature>/quality-checks.yml`
- `templates/figma-design-checks-template.yml` → `specs/<feature>/figma-design-checks.yml` **when Figma URL or `design-context.md` present** (`verify-plan.sh` enforces this)

## Figma Design Parity *(mandatory when Figma URL or design-context.md present)*

When the feature includes a Figma frame URL or `design-context.md`, the plan MUST
document Figma parity artifacts and baseline workflow. `verify-plan.sh` fails if
`figma-design-checks.yml` is missing.

| Artifact | Purpose |
|----------|---------|
| `design-context.md` | Structured Figma → Drupal mapping (from `/speckit-drupal-figma-design`) |
| `figma-design-checks.yml` | Copy markers, CSS selectors, screenshot baselines (**QR-FIGMA-001/002**) |
| `figma-baselines/` | Committed PNG baselines for full-page and section diffs |
| `figma-asset-manifest.yml` | Figma **`download_assets`** PNG exports → theme paths (**QR-ASSET-005**; see `templates/figma-asset-export.md`) |
| `quality-checks.yml` | Smoke/CSS/assets; `assets.enabled: true`, `use_figma_scopes: true`, `smoke.icon_markers`, `css.component_padding_rules` |

**Theme asset export** (during plan + implement):

```bash
# 1. Populate specs/<feature>/figma-asset-manifest.yml via Figma MCP download_assets
# 2. Export PNGs into web/themes/custom/<theme>/
.specify/extensions/drupal/scripts/bash/export-figma-theme-assets.sh specs/<feature>
```

**Screenshot baselines** (Phase 2, before theme polish):

```bash
.specify/extensions/drupal/scripts/bash/export-figma-baselines.sh specs/<feature>
# Optional per-component baselines:
.specify/extensions/drupal/scripts/bash/export-figma-component-baselines.sh specs/<feature>
```

**Screenshot thresholds** (defaults in template — tighten for pixel-perfect delivery):

| Scope | Default `max_diff_percent` |
|-------|---------------------------|
| Full page | 2% |
| Section crops | 1% |
| Components (QR-FIGMA-003) | 1% |

Document section selectors and baseline filenames in `figma-design-checks.yml`
(`screenshot.pages`, `screenshot.sections`, `screenshot.components.items`).

**Theme Strategy** must list each Figma frame → Twig/CSS path (see section below).

## Ambiguities Resolved *(mandatory when carousel, menus, or seed scripts apply)*

Document explicit decisions — `verify-plan.sh` fails if this section is missing
when plan mentions carousel, slick, menu, or seed/setup scripts.

| Topic | Decision | Rationale |
|-------|----------|-----------|
| Carousel | [slick_views / theme JS / none] | [why] |
| Slick JS asset | [composer npm-asset / manual / N/A] | See `contrib-libraries.md` |
| Front page content | [blocks only / node body / Layout Builder] | [which regions render what] |
| Menu strategy | [core front page link only / custom links] | Avoid duplicate Home |
| Seed data | [script path, idempotency, min content counts] | [when to run] |
| MCP vs scripts | [which `[SB]` tasks use MCP vs PHP scripts] | See `mcp-tooling-guide.md` |

## Project Drupal Context *(read first)*

| Document | Path |
|----------|------|
| Project content model | `.specify/drupal/data-model.md` |
| Site structure | `.specify/drupal/site-structure.md` |
| Sites / multisite | `.specify/drupal/sites.yml` |

**Target site(s)**: [key from `sites.yml`]  
Feature [data-model.md](./data-model.md) documents **deltas** only.

## Drupal Architecture *(mandatory)*

### Module Boundaries

| Module | Responsibility |
|--------|----------------|
| `[module_name]` | [Single clear purpose] |

### Plugins & Services

- **Plugins**: [blocks, fields, views style, migrate source, etc.]
- **Services**: [injectable services in `module.services.yml`]
- **Routes**: [`[module].routing.yml` endpoints]
- **Forms**: [Form API classes, AJAX needs]

### Data Model

Link project baseline `.specify/drupal/data-model.md`. Document feature deltas in
`specs/<feature>/data-model.md` and summary bullets here:

- **Entities/bundles**: [from spec — changes only]
- **Fields**: [storage types, formatters, widgets]
- **Migrations**: [if importing legacy data — YAML in `migrations/`]

### Caching Strategy

- **Render cache**: [cache tags per entity type]
- **Dynamic page**: [contexts: user, permissions]
- **Views**: [query caching, tag invalidation]
- **QR-PERF-001**: [how caching keeps primary URL under 2s]

## Config Strategy *(mandatory)*

| Config item | Export to `config/sync` | Notes |
|-------------|-------------------------|-------|
| [Content type] | Yes/No | [UUID dependencies] |
| [View] | Yes | [Display modes] |
| [Role permissions] | Yes | [Split env?] |

**Workflow**:
1. Build in UI or scripted config for local dev
2. Export: `vendor/bin/drush -r web config:export -y`
3. Import on deploy: `vendor/bin/drush -r web config:import -y`
4. Never edit synced config UUIDs manually

## Project Structure

### Documentation (this feature)

```text
.specify/drupal/           # Project-level (all features)
├── data-model.md
├── site-structure.md
└── sites.yml

specs/[###-feature]/
├── plan.md
├── research.md
├── data-model.md          # Feature deltas only
├── quickstart.md
├── design-context.md      # When Figma URL provided
├── quality-checks.yml
├── figma-design-checks.yml   # Required when Figma / design-context.md
├── figma-baselines/          # PNG baselines (QR-FIGMA-002)
├── figma-asset-manifest.yml    # Figma exports → theme paths
├── foundational-checklist.yml
└── tasks.md
```

### Drupal Code (repository root)

```text
web/modules/custom/[module_name]/
├── [module_name].info.yml
├── [module_name].module
├── [module_name].services.yml
├── src/
│   ├── Plugin/
│   ├── Form/
│   └── Controller/
├── config/install/          # optional default config
└── tests/src/
    ├── Kernel/
    └── Functional/

config/sync/                 # exported configuration
web/themes/custom/           # if theming required
```

**Structure Decision**: [Document chosen module split and why]

## Theme Strategy *(when Figma or UX design provided)*

**Strategy**: [brownfield | greenfield | hybrid]

**Base theme**: [e.g. Olivero / existing custom theme]

**Custom theme path**: `web/themes/custom/[theme_name]/`

| Figma component / frame | Twig / asset | Notes |
|-------------------------|--------------|-------|
| [Article teaser] | `templates/node--article--teaser.html.twig` | From design-context |
| [Page layout] | `templates/page--front.html.twig` | Optional |

**Libraries**: `[theme].libraries.yml` — CSS from Figma tokens (no inline styles in Twig)

**Code Connect**: [reuse existing mappings or N/A]

## Complexity Tracking

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| [e.g. custom entity] | [need] | [why node+fields insufficient] |
