---
description: "Scaffold per-feature quality-checks, figma-design-checks, and foundational Figma gate config"
---

# Setup Feature Artifacts (Drupal)

**Automated post-plan scaffolding** for Drupal features. Runs from the `after_plan`
hook (before `verify-plan`) when Figma or quality YAML is missing.

## What it creates / updates

| File | Action |
|------|--------|
| `quality-checks.yml` | Create from template; for Figma features seed `assets.enabled`, **`smoke.icon_markers` (QR-SMOKE-008/009)**, **`css.component_padding_rules` (QR-CSS-005)** |
| `figma-design-checks.yml` | Create from template; seed `file_key` / `node_id`; **sync selectors from `figma-regions.yml`** |
| `figma-asset-manifest.yml` | Create from template; seed theme name; **auto-sync `atomic_components[]`** from regions + catalog |
| `foundational-checklist.yml` | Add `figma.enabled: true` + `required_baselines` |
| `figma-baselines/` | Create directory (PNG export is separate) |

## What it does **not** do

- Does not populate `figma-asset-manifest.yml` **`assets[]`** export URLs (agent uses Figma MCP **`download_assets`** — see `figma-asset-export.md`)
- **Does** auto-sync `atomic_components[]` from `figma-regions.yml` + catalog via `sync-figma-atomic-manifest.php`
- Does not overwrite existing `figma-design-checks.yml` unless `--force` (sync merges sections from regions)
- Baseline PNG export uses `try-export-figma-baselines.sh` (skipped until DDEV + HTTP 200)

## Execution

- **Bash**: `.specify/extensions/drupal/scripts/bash/setup-feature-artifacts.sh [FEATURE_DIR]`
- **PowerShell**: `.specify/extensions/drupal/scripts/powershell/setup-feature-artifacts.ps1 [FEATURE_DIR]`

Options:

- `--force` — replace `figma-design-checks.yml` from template
- `--export-baselines` — also capture PNGs (requires DDEV + themed site)

## When it runs

| Trigger | Command |
|---------|---------|
| `/speckit-plan` | `after_plan` hook → `/speckit-drupal-setup-feature-artifacts` (automatic when hooks enabled) |
| Manual | `/speckit-drupal-setup-feature-artifacts specs/<feature>` |
| CI / script | `setup-feature-artifacts.sh specs/<feature>` |

## Agent checklist

After `/speckit-plan` on a Figma feature:

- [ ] Hook created `figma-design-checks.yml` — review selectors vs `design-context.md`
- [ ] **`figma-asset-manifest.yml` populated** with every theme icon/image (`download_assets`)
- [ ] Run **`export-figma-theme-assets.sh specs/<feature>`** after manifest is filled
- [ ] `foundational-checklist.yml` has `figma.enabled: true`
- [ ] When theme renders: `export-figma-source-baselines.sh specs/<feature>`
- [ ] Then: `/speckit-drupal-verify-plan`
