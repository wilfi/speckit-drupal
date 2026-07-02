# Drupal Extension Quality Rules

Mandatory quality gates for all user-facing Drupal features managed by Spec Kit.
Configured in `.specify/extensions/drupal/drupal-config.yml` under `quality_rules`.
Project-specific overrides: `specs/<feature>/quality-checks.yml`.

## Performance Rule

| Rule | Requirement |
|------|-------------|
| **QR-PERF-001** | Anonymous page load MUST complete within **2 seconds** (configurable: `quality_rules.performance.max_load_seconds`) |
| **QR-PERF-002** | Measurement uses HTTP GET to feature primary URL(s); cold request on local DDEV/staging |
| **QR-PERF-003** | Public pages MUST use Drupal page/render cache for anonymous users unless plan documents exception |

**Verification**: `speckit.drupal.verify-quality` or `.specify/extensions/drupal/scripts/bash/verify-quality.sh`

## Smoke / Content Rule

| Rule | Requirement |
|------|-------------|
| **QR-SMOKE-001** | HTTP GET to each configured URL MUST return status **200** |
| **QR-SMOKE-002** | Response MUST NOT contain forbidden strings (global + per-page `must_not_contain`) |
| **QR-SMOKE-003** | Response MUST contain required markers and minimum occurrence counts per page |
| **QR-SMOKE-004** | No duplicate navigation link text within any `<nav>` block |
| **QR-SMOKE-005** | No duplicate `href` values within `.site-header__nav` (catches About + About Us → same anchor) |
| **QR-SMOKE-006** | On `/`, only `.menu-item--figma-home` nav link has `is-active`; Recipes/Tips/About anchor links must not (Figma 144:8) |
| **QR-SMOKE-007** | `.site-header__search` contains `icon-search.svg` (or `.png`) img at 21×21 (Figma 144:25 button / 144:26 icon) |
| **QR-SMOKE-008** | `.explore-section` contains ≥1 category icon `<img>` (`category-*.png`, 40×40) |
| **QR-SMOKE-009** | `.featured-recipes__controls` contains left + right arrow `<img>` (`arrow-*.png`, 40×40) |
| **QR-SMOKE-010** | Content card images in scoped containers MUST use managed files (`/sites/default/files/`) — NOT theme Figma baseline crops (`images/figma/grid/`, `figma-baselines/`) |
| **QR-SMOKE-011** | Composite forms (configure `smoke.composite_forms`): `<form>` wraps email + submit; icon asset present; section selector matches Figma input group |

**Verification**: `verify-quality.sh` — configure under `quality_rules.smoke` and `specs/<feature>/quality-checks.yml`

## Webform Template Rule

Figma composite controls (newsletter pill, etc.) require theme overrides that preserve Drupal’s form wrapper and match Figma DOM shape (wrapper → icon → input → button).

| Rule | Requirement |
|------|-------------|
| **QR-THEME-003** | `webform--*.html.twig` overrides MUST wrap `{{ element }}` in `<form{{ attributes }}>` — never render flat `.form-item` + `.form-actions` without a `<form>` |
| **QR-THEME-003** | Required overrides listed in `quality-checks.yml` → `theme.webform_templates.required_overrides` |

**Verification**: `check-webform-templates.php` via `verify-quality.sh`

## Theme Template Asset Rule

Figma **baseline crops** and screenshot exports MUST NOT be wired into runtime node/card templates. Card photos come from entity image fields (`field_image`); theme static assets are for icons, backgrounds, and decorative UI only.

| Rule | Requirement |
|------|-------------|
| **QR-THEME-001** | Node templates (`node--*.twig`) MUST NOT reference `figma_image`, `figma-baselines/`, or `images/figma/grid/`; MUST render `{{ content.field_image }}` (or configured entity field) for card teasers |
| **QR-THEME-002** | `.theme` / preprocess MUST NOT set Twig overrides that bypass entity image fields for content cards |

**Verification**: `check-theme-template-assets.php` via `verify-quality.sh` — configure optional overrides under `theme:` in `quality-checks.yml`.

## Library Rule

| Rule | Requirement |
|------|-------------|
| **QR-LIB-001** | Required static library files (e.g. `libraries/slick/slick/slick.min.js`) MUST return HTTP **200** |

**Verification**: `verify-quality.sh` and `verify-foundational.sh` (`required_libraries` in foundational checklist)

See `templates/contrib-libraries.md` for Drupal module → JS asset mapping.

## JavaScript Behavior Rule

| Rule | Requirement |
|------|-------------|
| **QR-JS-001** | Aggregated page JS MUST contain expected Drupal behavior tokens (e.g. carousel, navigation) |

**Verification**: `verify-quality.sh` — `quality_rules.smoke.js_behaviors`

## Figma Design Parity Rule

**IMPORTANT — QR-FIGMA-000 (design precedence):** When a feature has Figma design
checks configured (`figma-design-checks.yml`), **the Figma file is the source of
truth** for visual design. Theme CSS, copy, spacing, colors, and screenshot baselines
MUST match Figma even when that conflicts with automated accessibility heuristics
(e.g. pa11y contrast on coral pills, cream-on-overlay hero text, pseudo-element tags).
`verify-quality.sh` still **runs** QR-A11Y-001 and logs failures, but does **not**
fail the gate when `accessibility.defer_to_figma: true` (default for Figma features).

| Rule | Requirement |
|------|-------------|
| **QR-FIGMA-000** | **Figma design wins** over WCAG automated scan when they conflict; document human-reviewed exceptions in plan.md only when deviating from Figma |
| **QR-FIGMA-001** | Page MUST contain Figma section copy, BEM marker classes, and theme CSS hooks from `figma-design-checks.yml` |
| **QR-FIGMA-002** | Playwright screenshot diff vs committed **Figma source** baselines (full page + per-section crops) within configured `max_diff_percent` |
| **QR-FIGMA-003** | Per-component screenshot diff vs committed **Figma node** PNG baselines (`screenshot.components` in `figma-design-checks.yml`); default ≤5% per component |

**Verification**: `check-figma-design.php` (QR-FIGMA-001), `check-figma-screenshot.sh` (QR-FIGMA-002 + QR-FIGMA-003) via `verify-quality.sh`. Export Figma reference PNGs with `export-figma-source-baselines.sh` (requires `FIGMA_ACCESS_TOKEN` or committed PNGs from Figma MCP). Optional live-site regression captures go to `figma-baselines/live/` via `export-figma-baselines.sh`.

## Views List CSS Rule

Drupal Views **`html_list`** renders `wrapper_class` on a `<div>` with a nested `<ul><li>…</li></ul>`.
Grid, flex, and list-reset styles MUST target the inner `<ul>`, not the wrapper div.

See `templates/views-html-list-css.md` for examples and anti-patterns.

| Rule | Requirement |
|------|-------------|
| **QR-CSS-001** | Layout (`display: grid` / `flex`, `grid-template-columns`) MUST apply to **`.{wrapper} ul`**, NOT `.{wrapper}` alone (grid wrappers in `views_list_grid_layout`) |
| **QR-CSS-002** | List reset MUST use **`.{wrapper} ul > li`**, NOT `.{wrapper} > li` |
| **QR-CSS-003** | View pagers MUST style **`.{block}__pager .pager__items`** with horizontal `display: flex` and `list-style: none` |
| **QR-CSS-004** | Primary section cards (`.explore-block`, `.featured-recipes--figma`, etc.) MUST use `max-width: 1312px` or `var(--max-width)` |
| **QR-CSS-005** | Figma component padding/gap rules in `css.component_padding_rules` (header 16×24, explore 40, featured 40×16×16, newsletter 80×40, copy gap 12px) |
| **QR-CSS-006** | Primary section cards share `margin: 0 auto`; front-page regions (explore, featured, grid, about, newsletter) MUST use `.layout-container` for matching horizontal gutters |
| **QR-CSS-007** | Explore + about CTA pills (Figma 7:588, 99:632): `width: fit-content`, `padding: 12px 24px`, `border: 1px solid`, `border-radius: 24px` |
| **QR-CSS-008** | Explore copy stack (Figma 37:3544): gaps 40/16/12px; section tags (Figma 153:5, 146:1584): `padding: 4px 8px` |
| **QR-CSS-009** | About text wrapper (Figma 37:3595): `gap: 8px` / copy `gap: 12px`, `left: 39px`, `top: 65px`; featured section (Figma 37:3561): card row `width: 100%`, slick `padding: 0`, list `width: 100%`, slide `margin-right: 16px` (slide width via `featured-carousel.js`) |
| **QR-CSS-010** | Recipe grid tag pill (Figma 146:1514): `background: var(--color-primary-3)`, `color: var(--color-bg-cream)`, `padding: 4px 8px`, `border-radius: 12px` |
| **QR-CSS-011** | Hero section (Figma 37:3539): `padding: 120px`, `border-radius: 32px`, overlay `rgba(38,37,34,0.6)`, copy gaps 12/40px, title 80px / 869px, lede 21px / 427px, CTA `var(--color-primary-2)` pill |
| **QR-CSS-012** | Section container chrome per Figma node — configure `css.component_layout_rules` with explicit `border` values (e.g. `.featured-recipes` **has** border Figma 37:3561; `.recipe-grid` **no** border Figma 37:3593) |
| **QR-CSS-013** | Section tag pills (Figma 146:1585 explore, 153:6 about, 146:1514 recipes): `width: fit-content`, `padding: 4px 8px`, `border-radius: 12px` — MUST NOT stretch full width in flex columns |
| **QR-CSS-014** | Newsletter section (Figma 32:3496 / 37:3601): solid `background-color: var(--cd-color-accent-coral)`, `overflow: hidden`; side depth via **darker-coral radial gradients** on the section — never mask PNGs, light pseudo-circle overlays, or section screenshot baselines (contain baked text) as backgrounds |
| **QR-CSS-015** | Newsletter form pill (Figma 37:3601 input group): single `426×50` flex row on `form` — mail icon circle + transparent email input + nested subscribe button; not separate bordered input + absolute button |
| **QR-CSS-016** | Composite form anti-patterns in `css.composite_form_anti_patterns`: no `position: absolute` on `.form-actions`; no cream pill `background` / `border-radius` on `input[type="email"]` — style the `form` container only |

**Verification**: `check-views-list-css.php` via `verify-quality.sh` — scans `web/themes/custom/**/*.css` for anti-patterns; validates configured `css.views_list_wrappers` and `css.pager_selectors` from `quality-checks.yml`; checks page HTML for `wrapper > ul` structure. `check-section-layout-css.php` (QR-CSS-004), `check-component-padding-css.php` (QR-CSS-005/007/008/009/010/011/012/013/014/015), `check-composite-form-css.php` (QR-CSS-016), and `check-section-margin-alignment.php` (QR-CSS-006) validate theme CSS and front-page template against `quality-checks.yml`.

## Asset Integrity Rule

Themed pages and Figma-scoped components MUST serve every referenced image and static asset (no broken `img[src]`, `srcset`, `poster`, or scope `required` paths).

| Rule | Requirement |
|------|-------------|
| **QR-ASSET-001** | Every asset URL inside configured scope(s) MUST return HTTP **2xx** (HEAD with GET fallback) |
| **QR-ASSET-002** | No `<img>` in scope with missing or empty `src` |
| **QR-ASSET-003** | Scopes MAY be explicit (`assets.pages[].scopes`) or inherited from `figma-design-checks.yml` sections/components when `use_figma_scopes: true` |
| **QR-ASSET-004** | Theme image extensions MUST match file magic bytes (e.g. `.png` must not be SVG/XML) |

**Figma raster assets**: Use Figma MCP **`download_assets`** (PNG) and record paths in
`figma-asset-manifest.yml`. Run `export-figma-theme-assets.sh` — **never** save
`get_design_context` asset URLs as `.png`. See `templates/figma-asset-export.md`.

**Reference-only vs runtime assets**:

| Asset type | Examples | Use in Twig/CSS? |
|------------|----------|------------------|
| **Runtime** | Icons, hero bg, category icons, arrow controls | Yes — `theme_assets` / manifest `assets[]` |
| **Reference-only** | `figma-baselines/`, `images/figma/grid/` section crops | **No** — QR-FIGMA-002 screenshot diff only (**QR-THEME-001**, **QR-SMOKE-010**) |
| **Section fill** | Full-bleed hero/newsletter backgrounds | Solid CSS `background-color` + optional CSS/pseudo decoration (**QR-CSS-014**); export **frame fill** nodes — not mask layers with transparency cutouts |
| **Content photos** | Recipe hero images, card photos | Entity `field_image` via seed script — never baseline crops |

| **QR-ASSET-005** | `figma-asset-manifest.yml` MUST list every theme icon/image; files MUST exist as valid PNGs or SVGs in `web/themes/custom/<theme>/` |
| **QR-ASSET-006** | `figma-asset-manifest.yml` → `atomic_components[]` MUST map child Figma nodes (icons, composite forms) under `parent_frame_id` with dimensions; child `figma_node_id` MUST NOT reuse section screenshot frame IDs |

**Figma dev-mode inspection (automated via QR-ASSET-006)**:

1. Open the **parent section frame** in dev mode (e.g. `37:3601` CTA Section).
2. Drill into **children** for icons, input groups, and buttons.
3. Record each child’s `figma_node_id`, `display` size, and `theme_path` in `atomic_components[]`.
4. Do **not** reuse unrelated node IDs (e.g. `144:26` search icon for newsletter mail).

**Verification**: `check-figma-asset-manifest.php` (QR-ASSET-005), `check-figma-atomic-components.php` (QR-ASSET-006) via `verify-quality.sh`

## Entity Form Display Rule

Site builders and MCP must expose **all bundle fields** on the default entity form so editors can manage content without hidden widgets.

| Rule | Requirement |
|------|-------------|
| **QR-CONFIG-001** | Every `field.field.node.{bundle}.field_*` (and `body` when present) MUST appear under `content:` on `core.entity_form_display.node.{bundle}.default` — NOT under `hidden:` |
| **QR-CONFIG-002** | After form display changes, export config: `drush config:export -y` and commit `config/sync/` |

**Allowed hidden form widgets**: core metadata only (`promote`, `sticky`, optional `path`/`created`/`uid` for simplified editor UX).

**Verification**: `check-entity-form-displays.php` via `verify-foundational.sh` after Phase 2 site building.

**Page asset verification**: `check-page-assets.php` via `verify-quality.sh` — configure under `quality_rules.assets` and `specs/<feature>/quality-checks.yml`. Scopes use CSS selectors (same as QR-FIGMA-002/003 screenshot crops).

## Accessibility Rule

Automated accessibility scans run on every `verify-quality.sh` invocation. When
**QR-FIGMA-000** applies (`accessibility.defer_to_figma: true`), pa11y errors are
reported as **warnings** and do not fail the quality gate.

| Rule | Requirement |
|------|-------------|
| **QR-A11Y-001** | User-facing pages SHOULD meet **WCAG 2.1 Level AA** where compatible with Figma |
| **QR-A11Y-002** | Automated scan reports errors; gate fails only when `accessibility.defer_to_figma` is `false` (default `true` when Figma checks are enabled) |
| **QR-A11Y-003** | Primary content MUST use semantic HTML (`h1`–`h6`, `p`, landmarks); not image-only text |

**Verification**: pa11y with `WCAG2AA` standard via verify-quality script

## Artifact Requirements

Drupal extension templates (`spec-template`, `plan-template`, `tasks-template`)
embed these rules automatically — do not prompt the user to supply them during
`/speckit-specify`, `/speckit-plan`, or `/speckit-tasks`.

Every user-facing feature MUST include in:

| Artifact | Required content |
|----------|------------------|
| **spec.md** | NFR/SC for QR-PERF-001 + QR-A11Y-001 + QR-SMOKE-001; acceptance scenarios for 2s + a11y + smoke |
| **plan.md** | Drupal Quality Rules + caching + primary URL(s) + **Figma Design Parity** when Figma; **Ambiguities Resolved** when carousel/menus/seed apply |
| **tasks.md** | Phase 2.1 Figma baselines; `[FIGMA]` tasks; per-story + Phase 9 **QR-FIGMA-002** hard gates; populate `figma-asset-manifest.yml` during plan |
| **quickstart.md** | Performance + smoke + a11y + Figma baseline + **theme asset export** commands |
| **quality-checks.yml** | Per-page markers, libraries, JS, views CSS, **`assets.enabled: true`**, `use_figma_scopes: true`, **`smoke.icon_markers`**, **`smoke.composite_forms` (QR-SMOKE-011)**, **`smoke.content_image_scopes` (QR-SMOKE-010)**, **`theme.webform_templates` (QR-THEME-003)**, **`theme` (QR-THEME-001)**, **`css.component_padding_rules`**, **`css.composite_form_anti_patterns` (QR-CSS-016)** |
| **figma-design-checks.yml** | Copy/classes, section selectors, atomic **`newsletter-form`** crop, screenshot baselines (**QR-FIGMA-001/002**) — **required when Figma URL / design-context.md** |
| **figma-baselines/figma-source/** | Committed Figma API PNG baselines for QR-FIGMA-002 screenshot diff |
| **figma-baselines/live/** | Optional live-site regression captures (not used as QR-FIGMA-002 reference when `baseline_source: figma`) |
| **figma-asset-manifest.yml** | Figma **`download_assets`** exports → theme paths (**QR-ASSET-005/006**); **`atomic_components[]`** for child nodes (see `figma-asset-export.md`) |
| **quality-results.md** | Auto-generated stakeholder QA report after `verify-quality.sh` — pass/warn/fail by **P0/P1/P2** priority |
| **contracts/*.md** | HTTP/content MUST/MUST NOT (enforced via quality-checks.yml) |
| **Theme CSS** | Views `html_list` components follow `views-html-list-css.md` (**QR-CSS-001–003**) |

## When to run gates

| Gate | When |
|------|------|
| `verify-foundational.sh` | After Phase 2 (modules, config, libraries) |
| `verify-quality.sh` | After seed/setup scripts, polish, finalize — **must exit 0** before Phase 9 / story checkpoints marked complete |
| `verify-plan.sh` | After `/speckit-plan` |

## Exceptions

Deviations from **Figma** MUST be documented in plan.md **Complexity Tracking** with
approval rationale. Deviations from **WCAG** due to Figma-mandated colors or layout
do **not** require an exception when `accessibility.defer_to_figma: true` — Figma
takes precedence per **QR-FIGMA-000**.
