---
description: "Verify Drupal extension quality rules — performance, smoke, libraries, JS, and accessibility"
---

# Verify Drupal Quality Rules

Enforce mandatory quality rules from the Drupal extension.

## Rules (see `templates/drupal-quality-rules.md`)

| Rule | Requirement |
|------|-------------|
| **QR-PERF-001** | Page load ≤ 2 seconds (configurable) |
| **QR-SMOKE-001–010** | HTTP 200, content markers, nav checks, header search icon (007), explore category icons (008), featured arrows (009), content card image sources (010) |
| **QR-THEME-001–002** | No Figma baseline crops or `figma_image` overrides in node templates / theme preprocess |
| **QR-LIB-001** | Required library files return HTTP 200 |
| **QR-JS-001** | Expected Drupal behaviors present in page JS |
| **QR-CSS-001–006** | Views `html_list` CSS on nested `ul`; pager flex layout; section max-width; Figma padding; margin alignment |
| **QR-FIGMA-000** | **Figma design is source of truth** — overrides automated a11y when they conflict (`accessibility.defer_to_figma: true`) |
| **QR-FIGMA-001** | Figma copy, marker classes, theme CSS hooks |
| **QR-FIGMA-002** | Screenshot diff vs committed baselines (Playwright + pixelmatch) |
| **QR-FIGMA-003** | Per-component diff vs Figma node PNG baselines (≤5% default) |
| **QR-ASSET-001–005** | Assets reachable per scope; format integrity; **manifest populated** (`figma-asset-manifest.yml`) |
| **QR-A11Y-001** | WCAG 2.1 AA automated scan — informational; fails gate only when `defer_to_figma: false` |

## Behavior

1. Read `quality_rules` from `.specify/extensions/drupal/drupal-config.yml`
2. Merge `specs/<feature>/quality-checks.yml` when feature dir passed
3. Resolve check URL(s) from config or CLI arguments
4. Run smoke, CSS layout, performance, library, JS, and pa11y checks
5. Write **`specs/<feature>/quality-results.md`** stakeholder QA report (pass / warn / fail by P0–P2 priority)
6. Exit non-zero on failure (CI and post-implement hooks)
7. **Agent follow-up (mandatory)**: apply **`speckit-drupal-quality-report`** — read the report and present stakeholder summary (gate, P0 / P1 / P2). Do not end the verify-quality response without this step.

## Execution

- **Bash**: `.specify/extensions/drupal/scripts/bash/verify-quality.sh [FEATURE_DIR] [path ...]`
- **PowerShell**: `.specify/extensions/drupal/scripts/powershell/verify-quality.ps1`

## Configuration

```yaml
quality_rules:
  enabled: true
  use_ddev: true
  performance:
    max_load_seconds: 2
    check_urls:
      - /
  smoke:
    enabled: true
    forbidden_strings:
      - "Hello World"
    libraries:
      - /libraries/slick/slick/slick.min.js
    js_behaviors:
      - myThemeBehavior
    pages:
      - path: /
        must_contain: [hero-block]
        must_not_contain: ["Hello World"]
        min_occurrences:
          recipe-card: 6
  accessibility:
    standard: WCAG2AA
    max_errors: 0
    defer_to_figma: true   # QR-FIGMA-000: do not fail gate on pa11y when Figma wins
  figma:
    source_of_truth: true
  quality_report:
    enabled: true
    filename: quality-results.md
```

Project overrides: `specs/<feature>/quality-checks.yml` (copy from `quality-checks-template.yml`).

## Stakeholder QA report

After each run with a feature directory, the script writes **`specs/<feature>/quality-results.md`**:

| Section | Purpose |
|---------|---------|
| **Summary** | Counts of passed / warnings / failures |
| **Stakeholder review** | **P0** blockers, **P1** warnings (e.g. deferred a11y under QR-FIGMA-000), **P2** info |
| **Passed by category** | Performance, Design, Smoke, CSS, etc. |
| **Detailed results** | Full rule-by-rule table for QA |
| **QA checklist** | Sign-off checklist for reviewers |

The post-implement **finalize** hook runs verify-quality and logs the report path.

## Agent: mandatory post-step

After `/speckit-drupal-verify-quality`, **always** run **`speckit-drupal-quality-report`**:

1. Confirm `quality-results.md` exists (written by verify-quality.sh)
2. Read and summarize for the user — gate status, P0 blockers, P1 warnings, P2 info, file path

## When to Run

- After seed/setup scripts (Phase 2.5)
- After `/speckit-implement` (finalize hook)
- In CI before merge
- Manually: `/speckit-drupal-verify-quality`
