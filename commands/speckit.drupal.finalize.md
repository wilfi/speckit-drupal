---
description: "Finalize Drupal implementation: cache rebuild and optional config export"
---

# Finalize Drupal Implementation

Post-implement hook for Drupal projects. Run after `/speckit-implement` completes.

## Behavior

1. Read `.specify/extensions/drupal/drupal-config.yml` `post_implement` settings
2. If Drush is available:
   - Run `drush cr` (cache rebuild) when `cache_rebuild: true`
   - Run `drush config:export -y` when `config_export: true`
3. Run **quality rules** verification when `quality_rules.post_implement.run_verify: true`:
   - **QR-PERF-001**: page load ≤ 2 seconds
   - **QR-A11Y-001**: pa11y WCAG2AA (warnings only when `defer_to_figma: true`)
   - Writes **`specs/<feature>/quality-results.md`** when `quality_report.enabled: true`
4. Report summary and remind to commit `config/sync/` when config changed

## Execution

- **Bash**: `.specify/extensions/drupal/scripts/bash/finalize-implement.sh`
- **PowerShell**: `.specify/extensions/drupal/scripts/powershell/finalize-implement.ps1`

## Configuration

In `drupal-config.yml`:

```yaml
post_implement:
  cache_rebuild: true
  config_export: false
  drush_root: web

quality_rules:
  enabled: true
  performance:
    max_load_seconds: 2
  accessibility:
    standard: WCAG2AA
    max_errors: 0
  post_implement:
    run_verify: true
    write_quality_report: true

quality_rules:
  quality_report:
    enabled: true
```

## Agent Reminder

After finalize, verify:

- [ ] `config/sync/` committed if configuration changed
- [ ] PHPUnit passing for affected modules
- [ ] PHPCS clean on `web/modules/custom/`
- [ ] **QR-PERF-001**: load ≤ 2s on primary URL
- [ ] **QR-A11Y-001**: pa11y WCAG2AA zero errors
