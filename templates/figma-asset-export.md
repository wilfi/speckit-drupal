# Figma Theme Asset Export (QR-ASSET-004 / QR-ASSET-005)

**Mandatory for Figma features** with icons, badges, or decorative PNGs in the theme.

## Rules

| Do | Don't |
|----|-------|
| Figma MCP **`download_assets`** per node → real PNG | **`get_design_context`** asset URLs (SVG/XML, often mis-saved as `.png`) |
| Record every export in **`figma-asset-manifest.yml`** during `/speckit-plan` | Ad-hoc downloads into `images/` without manifest entries |
| Run **`export-figma-theme-assets.sh`** after updating manifest | Commit SVG bytes with a `.png` extension |
| Use **`theme_assets`** in Twig (see theme preprocess) | Relative `{{ directory }}/images/...` without leading `/` |

## Workflow

### 1. During `/speckit-plan` (agent + designer)

For each Figma icon/image used in Twig or CSS `url()`:

1. Note **`figma_node_id`** from dev mode or MCP metadata.
2. Call Figma MCP **`download_assets`** with `defaultFormat: png` (and `defaultScale: 2` for crisp UI icons).
3. Add a row to **`specs/<feature>/figma-asset-manifest.yml`**:

```yaml
- figma_node_id: '7:477'
  figma_name: explore-icon-breakfast
  theme_path: images/icons/category-breakfast.png
  display: { width: 40, height: 40 }   # optional — resize after download
  export_url: https://www.figma.com/api/mcp/asset/…   # from download_assets; refresh every ~7 days
```

4. Map content images (hero, recipes) separately — attach via `scripts/attach-figma-images.php` or manifest `attach_to` when scripted. **Never** use `figma-baselines/` or `images/figma/grid/` section crops in Twig — those are reference-only (**QR-THEME-001**, **QR-SMOKE-010**).

5. **Section backgrounds** (newsletter, hero): use CSS `background-color` for the frame fill. Decorative side depth = **darker** radial gradients positioned off left/right edges (Figma 32:3496). Do **not** export inner mask layers (`32:3497`) or section screenshot baselines as `<img>` — baselines include baked text (**QR-CSS-014**). Export a **background-only** layer if a bitmap is required.

### 2. Export to theme (implement / polish)

```bash
.specify/extensions/drupal/scripts/bash/export-figma-theme-assets.sh specs/<feature>
```

Writes PNGs under `web/themes/custom/<theme>/` and validates PNG magic bytes.

### 3. Verify

- **QR-ASSET-004** — format integrity (via `verify-quality.sh`)
- **QR-ASSET-005** — manifest `assets[]` populated + files on disk (via `verify-quality.sh` / `verify-foundational.sh`)
- **QR-ASSET-006** — manifest `atomic_components[]` maps child Figma nodes under parent frames (via `check-figma-atomic-components.php`)
- **QR-SMOKE-008/009** — explore category + featured arrow `<img>` markup (via `quality-checks.yml` `smoke.icon_markers`)
- **QR-SMOKE-010** — card images in scoped containers use `/sites/default/files/` not theme baseline crops
- **QR-SMOKE-011** — composite form DOM: `<form>` wraps email + submit + icon (via `smoke.composite_forms`)
- **QR-THEME-001** — node templates render `content.field_image`, no `figma_image` / `images/figma/grid/` references
- **QR-THEME-003** — webform overrides preserve `<form{{ attributes }}>` wrapper
- **QR-CSS-015/016** — composite form container-first CSS (via `component_layout_rules` + `composite_form_anti_patterns`)

## Atomic components (QR-ASSET-006)

Section screenshot gates (QR-FIGMA-002) miss sub-component drift. Map **child nodes** in `atomic_components[]`:

```yaml
atomic_components:
  - id: newsletter-input-pill
    parent_frame_id: "37:3601"
    css_selector: .newsletter-section form
    kind: composite_form
    dimensions: { width: 426, height: 50 }
    children:
      - id: newsletter-mail-icon
        figma_node_id: "<child-node-from-dev-mode>"
        theme_path: images/icons/icon-mail.svg
        display: { width: 21, height: 21 }
        kind: icon
```

Add a tighter screenshot crop in `figma-design-checks.yml` → `screenshot.sections` with `selector: .newsletter-section__form` and `optional_baseline: true` until the Figma child baseline is exported.

## Automated atomic manifest sync

During **`setup-feature-artifacts`** and **`verify-quality.sh`** (when `figma.auto_sync_atomic_manifest: true`):

```bash
php .specify/extensions/drupal/scripts/bash/sync-figma-atomic-manifest.php --feature=specs/<feature>
```

1. Reads **`figma-regions.yml`** `atomic_components[]` (feature-specific overrides)
2. Merges **`figma-atomic-components-catalog.yml`** defaults for matching region slugs + `file_key`
3. Writes **`figma-asset-manifest.yml`** `atomic_components[]` and links icon rows in `assets[]`
4. Appends atomic **`screenshot`** sections to **`figma-design-checks.yml`** (e.g. `newsletter-form`)

## Refreshing expired URLs

`export_url` values from Figma MCP expire (~7 days). Re-run **`download_assets`** for each node and update the manifest, then re-run the export script.
