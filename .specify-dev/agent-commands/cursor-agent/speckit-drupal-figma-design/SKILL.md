---
name: speckit-drupal-figma-design
description: Extract Figma design context and map it to Drupal spec inputs (theme,
  layout, config vs code)
compatibility: Requires spec-kit project structure with .specify/ directory
metadata:
  author: github-spec-kit
  source: drupal:commands/speckit.drupal.figma-design.md
---

# Figma Design → Drupal Spec Context

Run **before or during** `/speckit-specify` when the feature includes Figma UX designs.

## Prerequisites

- Figma MCP server connected in Cursor (`https://mcp.figma.com/mcp` recommended)

## Behavior

1. Read `figma` settings from `.specify/extensions/drupal/drupal-config.yml`
2. Parse Figma URL from `$ARGUMENTS` or active feature input
3. Use **Figma MCP** to fetch frame/component context (agent-driven)
4. Write **`specs/<feature>/design-context.md`** from design-context template
5. Write **`specs/<feature>/figma-regions.yml`** from `figma-regions-template.yml` *(mandatory)*:
   - One `regions[]` entry per Layout & Regions row
   - `slug`, `figma_node_id`, `drupal_region`, `selector`, `smoke_must_contain`, `baseline`, `user_stories`
   - Use `region_selector_pattern` from config when selector unknown (default `.region--{slug}`)
6. Output summary for `/speckit-specify` to merge into `spec.md`

## Theme raster assets *(during `/speckit-plan`)*

For every icon, badge, logo, or decorative PNG referenced in Twig/CSS:

| Step | Tool | Notes |
|------|------|-------|
| Export PNG | Figma MCP **`download_assets`** (`defaultFormat: png`, `defaultScale: 2` for UI icons) | Real PNG bytes |
| **Do not use** | `get_design_context` asset URLs | SVG/XML — breaks `<img>` and QR-ASSET-004 |
| Record | `specs/<feature>/figma-asset-manifest.yml` | `figma_node_id`, `theme_path`, `export_url` |
| Atomic controls | `specs/<feature>/figma-regions.yml` → `atomic_components[]` | Auto-synced to manifest by `sync-figma-atomic-manifest.php` (catalog defaults by region slug) |
| Write to theme | `export-figma-theme-assets.sh specs/<feature>` | Validates PNG magic bytes |

See **`templates/figma-asset-export.md`** for full workflow.

## figma-regions.yml (required)

`/speckit-plan` → `setup-feature-artifacts` → `sync-figma-checks-from-design.php` merges this into
`figma-design-checks.yml` automatically. **Do not** hand-edit selectors in figma-design-checks if
regions file is complete.

If MCP cannot infer selectors, populate from theme `.info.yml` regions or plan Theme Strategy.

## Fallback

When MCP is unavailable, `sync-figma-checks-from-design.php` parses the **Layout & Regions** table
from `design-context.md` and generates `figma-regions.yml` on `/speckit-plan`.

## Execution

Optional scaffold:

```bash
.specify/extensions/drupal/scripts/bash/figma-design-context.sh [feature-dir] [figma-url]
```

## Hook

`before_specify` when input contains `figma.com`.
