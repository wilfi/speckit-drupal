---
name: speckit-drupal-setup-feature-artifacts
description: Scaffold per-feature quality-checks, figma-design-checks, and foundational
  Figma config after /speckit-plan
compatibility: Requires spec-kit project structure with .specify/ directory
metadata:
  author: spec-project
  source: drupal:commands/speckit.drupal.setup-feature-artifacts.md
---

# Setup Drupal Feature Artifacts

Automated scaffolding for `specs/<feature>/` quality and Figma parity files.
**Runs automatically** after `/speckit-plan` via the Drupal extension `after_plan` hook.

## Behavior

1. Resolve active feature directory (argument, `.specify/feature.json`, or latest `plan.md`)
2. Detect Figma feature (`design-context.md`, Figma URL in spec/plan)
3. Create or merge:
   - `quality-checks.yml` — ensure `assets.enabled: true` when Figma
   - `figma-design-checks.yml` — template + `file_key`/`node_id` from design context
   - `figma-asset-manifest.yml`
   - `foundational-checklist.yml` → `figma.enabled: true`, baseline list
   - `figma-baselines/` directory
4. Exit 0; log created/updated paths

## Execution

```bash
.specify/extensions/drupal/scripts/bash/setup-feature-artifacts.sh [FEATURE_DIR]
# Optional after theme work:
.specify/extensions/drupal/scripts/bash/setup-feature-artifacts.sh specs/<feature> --export-baselines
```

## Agent instructions

When executing this hook after `/speckit-plan`:

1. Run the script (do not hand-copy templates unless script fails)
2. Report which files were created vs updated
3. Remind user to customize `figma-design-checks.yml` selectors from `design-context.md`
4. Baseline PNG export requires a running site — defer to implement Phase 2.1 or `--export-baselines`

## Related

- `/speckit-drupal-verify-plan` — runs next in hook chain; requires `figma-design-checks.yml` for Figma features
- `export-figma-baselines.sh` — captures PNGs when site is ready
