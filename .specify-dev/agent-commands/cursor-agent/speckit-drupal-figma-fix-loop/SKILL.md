---
name: speckit-drupal-figma-fix-loop
description: Agent loop — fix theme/CSS from section diff report until QR-FIGMA-002 passes
compatibility: Requires Drupal Spec Kit extension, DDEV, figma-fix-queue.md
metadata:
  author: spec-project
  source: drupal:commands/speckit.drupal.figma-fix-loop.md
---

# Figma Fix Loop

**Goal:** Clear all failing sections in `figma-baselines/reports/latest.json`.

## Step 1 — Verify (or re-verify)

```bash
.specify/extensions/drupal/scripts/bash/verify-figma-section.sh specs/<feature>
```

## Step 2 — Triage

Read:

- `specs/<feature>/figma-fix-queue.md`
- `specs/<feature>/figma-baselines/reports/latest.json`
- `specs/<feature>/design-context.md`
- `specs/<feature>/figma-regions.yml`

## Step 3 — Fix each failing section

| Status | Action |
|--------|--------|
| `missing_baseline` | `try-export-figma-baselines.sh --when=after_theme_story specs/<feature>` |
| `fail` | Compare `figma-baselines/diffs/<slug>-diff.png`; fix Twig/CSS for selector in report |

Theme paths: `plan.md` Theme Strategy table + `web/themes/custom/<theme>/`.

After edits: `ddev drush cr`

## Step 4 — Re-verify failed slugs only

```bash
.specify/extensions/drupal/scripts/bash/verify-figma-section.sh specs/<feature> hero grid
```

## Step 5 — Loop

Repeat steps 3–4 until `failed_count` is 0.

## Step 6 — Full quality gate

```bash
.specify/extensions/drupal/scripts/bash/verify-quality.sh specs/<feature>
```

**Done when:** section verify + full verify exit 0.
