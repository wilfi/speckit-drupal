---
description: "Post-implement admin provisioning from drupal-admin-checklist.yml"
---

# Provision Drupal Site Components

Idempotent post-implement hook that ensures admin-managed entities exist per the feature checklist.

## Behavior

1. Resolve `FEATURE_DIR` from argument, `FEATURE_DIR` env, or `.specify/feature.json`
2. Read `specs/<feature>/drupal-admin-checklist.yml` when `admin_provision.enabled: true`
3. Run `provision-admin-components.php` via Drush:
   - Menu links (title|uri idempotent pattern)
   - Basic pages with path aliases
   - Recipe images from theme `images/figma/`
   - Webform existence checks
   - Verify menu titles from checklist
4. In Cursor, the agent skill may use Drupal MCP write tools first, then always reconcile with the PHP script

## Execution

```bash
ddev drush php:script .specify/extensions/drupal/scripts/bash/provision-admin-components.php specs/<feature>
```

Or via finalize hook when `post_implement.provision_admin: true`.

## Configuration

`drupal-config.yml`:

```yaml
post_implement:
  provision_admin: true
```

Per-feature `drupal-admin-checklist.yml` — see `templates/drupal-admin-checklist-template.yml`.
