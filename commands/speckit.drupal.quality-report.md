---
description: "Generate stakeholder quality-results.md for the active Spec Kit feature"
---

# Generate Drupal Quality Report

Write **`specs/<feature>/quality-results.md`** for the active feature — pass / warn / fail by **P0 / P1 / P2** priority.

## Behavior

1. Resolve active feature from (in order): CLI arg, `SPECIFY_FEATURE_DIRECTORY`, `.specify/feature.json`
2. Run `verify-quality.sh` for that feature (all QR-* checks) when report is missing or stale
3. Write `quality-results.md` via `write-quality-report.php`
4. Print report path on success

**Post-verify mode**: When invoked immediately after `/speckit-drupal-verify-quality`, skip step 2 if `quality-results.md` already exists — read and summarize only.

## Execution

- **Bash**: `.specify/extensions/drupal/scripts/bash/generate-quality-report.sh [FEATURE_DIR]`
- **Skill**: `/speckit-drupal-quality-report`

## Output

`specs/<feature>/quality-results.md` includes:

- Gate status (passed / failed)
- P0 blockers, P1 warnings, P2 info
- Passed checks by category
- Detailed rule table and QA sign-off checklist

## When to Run

- **Mandatory** after `/speckit-drupal-verify-quality`
- Stakeholder or QA handoff
- After polish, without full finalize hook
- Manually: `/speckit-drupal-quality-report`

## Configuration

Uses the same config as `verify-quality.sh`:

```yaml
quality_rules:
  quality_report:
    enabled: true
    filename: quality-results.md
```

Disable report generation: `quality_report.enabled: false` in `drupal-config.yml`.
