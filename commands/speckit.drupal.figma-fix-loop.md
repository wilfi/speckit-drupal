---
description: "Agent loop: fix theme/CSS from section diff report until QR-FIGMA-002 passes"
---

# Figma Fix Loop

Automated **verify → diagnose → fix → re-verify** workflow for Figma-backed features.

## Prerequisites

- `verify-figma-section.sh` has been run and failed (or run as step 1)
- `figma-fix-queue.md` and `figma-baselines/reports/latest.json` exist
- DDEV site running with theme changes applied

## Agent workflow (mandatory)

1. Read `specs/<feature>/figma-fix-queue.md` and `figma-baselines/reports/latest.json`
2. For each section with `status: fail` or `missing_baseline`:
   - **missing_baseline** → run `try-export-figma-baselines.sh --when=after_theme_story specs/<feature>`
   - **fail** → open diff PNG under `figma-baselines/diffs/`
   - Read `design-context.md` + `figma-regions.yml` for that section
   - Edit theme Twig/CSS (paths from `plan.md` Theme Strategy) — prefer Twig-first, no field formatter wrappers
   - `ddev drush cr`
3. Re-run:

   ```bash
   .specify/extensions/drupal/scripts/bash/verify-figma-section.sh specs/<feature> <failed-slug>...
   ```

4. Repeat until `failed_count: 0` in `latest.json`
5. Run full gate when all sections pass:

   ```bash
   .specify/extensions/drupal/scripts/bash/verify-quality.sh specs/<feature>
   ```

## Do not

- Mark `[FIGMA]` or user-story checkpoint tasks `[X]` until section verify passes
- Loosen `max_diff_percent` without documenting in plan Complexity Tracking
- Skip baseline export and expect QR-FIGMA-002 to pass

## Skill

`.cursor/skills/speckit-drupal-figma-fix-loop/SKILL.md`
