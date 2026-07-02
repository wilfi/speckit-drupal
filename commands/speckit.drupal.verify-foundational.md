---
description: "Verify Phase 2 foundational work — contrib modules, config export, active entities"
---

# Verify Drupal Foundational Gate

**Mandatory checkpoint after Phase 2** (before user stories). Blocks `/speckit-implement`
from proceeding when site building or config export is incomplete.

## Behavior

1. Resolve active feature directory (`.specify/feature.json` or argument)
2. Load `foundational-checklist.yml` in the feature directory
3. Verify:
   - `config/sync/` exists
   - Listed **config entity files** are exported
   - Listed **contrib modules** are enabled (`drush pm:list`)
   - Listed **node types / vocabularies** exist in **active** Drupal config (`drush cim` done)
   - Optional: default **theme** name and scaffold
   - Optional: `require_config_import` — no "Only in sync dir" for listed entities
4. Exit **non-zero** on failure (CI and implement gate)

## Prerequisites

During `/speckit-plan`, copy and fill:

`specs/<feature>/foundational-checklist.yml`

from `templates/foundational-checklist-template.yml` using `data-model.md` and `plan.md`.

## Execution

- **Bash**: `.specify/extensions/drupal/scripts/bash/verify-foundational.sh [feature_dir]`
- **PowerShell**: `.specify/extensions/drupal/scripts/powershell/verify-foundational.ps1 [feature_dir]`

## Agent Checklist

After Phase 2 tasks in `tasks.md`:

- [ ] `foundational-checklist.yml` exists and matches plan/data-model
- [ ] `ddev drush config:import -y && ddev drush cr` run after exporting Phase 2 config
- [ ] `verify-foundational.sh` exits 0
- [ ] **Do not start Phase 3** until the gate passes

## Typical failures

| Message | Fix |
|---------|-----|
| Missing `foundational-checklist.yml` | Create from template during plan |
| Missing `config/sync/node.type.*.yml` | Export config after content type UI work |
| Module not enabled | `ddev composer require` + `ddev drush en` |
| Node type not in active config | `ddev drush config:import -y && ddev drush cr` |
| Only in sync dir | Import config before verify |
