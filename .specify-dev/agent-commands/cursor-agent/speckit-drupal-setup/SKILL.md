---
name: speckit-drupal-setup
description: 'Set up Drupal Spec Kit workflow: templates, Drush, config sync, DDEV'
compatibility: Requires spec-kit project structure with .specify/ directory
metadata:
  author: github-spec-kit
  source: drupal:commands/speckit.drupal.setup.md
---

# Setup Drupal Workflow

One-time setup after `speckit.drupal.install` to align Spec Kit with Drupal
development conventions.

## Behavior

1. Read `.specify/extensions/drupal/drupal-config.yml`
2. Verify Drupal is installed (`web/core` exists)
3. Install Drush via Composer (if enabled and missing)
4. Create `config/sync`, `web/modules/custom`, `web/themes/custom`
5. Configure `settings.php` config sync directory (if enabled)
6. Scaffold `.ddev/config.yaml` when DDEV is available (if enabled)
7. Install Drupal-specific `spec`, `plan`, and `tasks` templates into
   `.specify/templates/` (backs up existing templates to `.specify/templates/.backup-drupal/`).
   Templates include **QR-PERF-001** (≤2s load) and **QR-A11Y-001** (WCAG2AA) by default.
8. Scaffold **project Drupal context** to `.specify/drupal/` (`data-model.md`,
   `site-structure.md`, `sites.yml`) when missing — shared across all features.
9. Generate **`README-SPECKIT-DRUPAL.md`** at the project root from
   `.specify/templates/project-runbook-template.md` (full manual:
   `.specify/extensions/drupal/RUNBOOK.md`).

## Execution

- **Bash**: `.specify/extensions/drupal/scripts/bash/setup-workflow.sh`
- **PowerShell**: `.specify/extensions/drupal/scripts/powershell/setup-workflow.ps1`

## After Setup

Run Spec Kit commands as usual — they will use Drupal-aware templates:

```text
/speckit-specify → specs/[feature]/spec.md
/speckit-plan      → specs/[feature]/plan.md
/speckit-tasks     → specs/[feature]/tasks.md
```

Start local environment:

```bash
ddev start          # if DDEV scaffolded
ddev drush site:install -y
/speckit-drupal-setup-mcp-tools   # optional: Cursor MCP for [SB] site building
```