---
description: "Export figma-baselines when DDEV site is ready (conditional, config-driven)"
---

# Try Export Figma Baselines

Conditional wrapper around `export-figma-baselines.sh`. **Exits 0 when skipped**
(site not ready) unless `--require` is passed.

## Config (drupal-config.yml → figma)

| Key | Default | Meaning |
|-----|---------|---------|
| `auto_export_baselines` | `true` | Master switch |
| `export_when` | `after_theme_story` | When hooks/scripts attempt export |
| `require_baselines_at_polish` | `true` | Finalize hook uses `--require` |

`export_when` values: `plan` | `after_seed` | `after_theme_story` | `polish_only` | `manual`

## Execution

```bash
.specify/extensions/drupal/scripts/bash/try-export-figma-baselines.sh [FEATURE_DIR]
.specify/extensions/drupal/scripts/bash/try-export-figma-baselines.sh --when=after_theme_story specs/<feature>
.specify/extensions/drupal/scripts/bash/try-export-figma-baselines.sh --when=polish_only --require specs/<feature>
```

## Hooks

- `before_implement` — tries `--when=after_seed` (skip if not ready)
- `finalize-implement.sh` — tries `--when=polish_only --require` before verify-quality

## Agent

Run after seed/setup scripts or when completing a user-story theme checkpoint:

```bash
try-export-figma-baselines.sh --when=after_theme_story specs/<feature>
```
