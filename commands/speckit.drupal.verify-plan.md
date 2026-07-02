---
description: "Verify Drupal implementation plan includes required architecture sections"
---

# Verify Drupal Plan

Post-plan checklist for Drupal features. Run after `/speckit-plan` completes.

## Behavior

1. Resolve the active feature `plan.md` (latest `specs/*/plan.md` or current branch)
2. Verify required Drupal sections are present and non-empty:
   - **Technical Context**
   - **Drupal Quality Rules** (QR-PERF-001, QR-A11Y-001, QR-FIGMA when Figma)
   - **Drupal Architecture**
   - **Config Strategy**
   - **Figma Design Parity** (when `design-context.md`, Figma URL, or Figma in spec)
3. **Figma features**: require `specs/<feature>/figma-design-checks.yml` and QR-FIGMA references in plan
4. Report missing sections with remediation guidance
5. Exit non-zero if critical sections are missing (for CI/script use)

**Prerequisite**: `/speckit-drupal-setup-feature-artifacts` runs automatically in the `after_plan` hook (priority 15) before this command.

## Execution

- **Bash**: `.specify/extensions/drupal/scripts/bash/verify-plan.sh [plan_path]`
- **PowerShell**: `.specify/extensions/drupal/scripts/powershell/verify-plan.ps1 [plan_path]`

## Agent Checklist

After running the script, confirm in the plan:

- [ ] Module boundaries are documented (single responsibility per module)
- [ ] Config vs custom code decisions are explicit
- [ ] Cache tags/contexts identified for dynamic content
- [ ] PHPUnit scope defined (Kernel vs Functional per story)
- [ ] Drush commands listed for export/import verification
- [ ] **Figma features**: `figma-design-checks.yml` copied from template; **Figma Design Parity** section filled; baselines export planned
