---
name: speckit-drupal-try-export-figma-baselines
description: Export figma-baselines when DDEV site is ready (conditional, config-driven)
compatibility: Requires spec-kit project structure with .specify/ directory
metadata:
  author: spec-project
  source: drupal:commands/speckit.drupal.try-export-figma-baselines.md
---

# Try Export Figma Baselines

Run when the site may be ready; skips gracefully when not.

```bash
.specify/extensions/drupal/scripts/bash/try-export-figma-baselines.sh --when=after_theme_story specs/<feature>
```

Read `drupal-config.yml` → `figma.export_when` and `require_baselines_at_polish`.
