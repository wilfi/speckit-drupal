---
description: "Install drupal/mcp_tools and connect Cursor MCP for AI site building"
---

# Setup Drupal MCP Tools

One-time (per project) setup for **[drupal/mcp_tools](https://www.drupal.org/project/mcp_tools)** —
exposes Drupal admin capabilities to Cursor via MCP (STDIO through Drush).

**Local development only.** Do not enable write scopes on production.

## Behavior

1. Read `mcp_tools` section from `.specify/extensions/drupal/drupal-config.yml`
2. `composer require drupal/mcp_tools` (constraint from config)
3. Enable `mcp_tools_stdio` + configured submodules (structure, views, blocks, webform, …)
4. Merge Cursor MCP entry into `.cursor/mcp.json` (server name default: `drupal`)
5. Print next steps (admin preset, config export, verify-foundational)

## Prerequisites

- Drupal installed (`web/core`)
- Drush available (`vendor/bin/drush` or `ddev drush`)
- DDEV recommended (`use_ddev: true` in config)

## Execution

- **Bash**: `.specify/extensions/drupal/scripts/bash/setup-mcp-tools.sh`
- **PowerShell**: `.specify/extensions/drupal/scripts/powershell/setup-mcp-tools.ps1`
- **Slash**: `/speckit-drupal-setup-mcp-tools`

Options:

- `--no-cursor-config` — install modules only, skip `.cursor/mcp.json`

## After Setup

1. Open `/admin/config/services/mcp-tools` → choose **Development** preset (local)
2. Reload MCP in Cursor
3. During `/speckit-implement`, use MCP for **`[SB]`** site-building tasks
4. Always export config after MCP changes:

   ```bash
   ddev drush config:export -y
   .specify/extensions/drupal/scripts/bash/verify-foundational.sh
   ```

See `templates/mcp-tools-workflow.md` and `templates/mcp-tooling-guide.md` for MCP vs
config-first guidance and approved fallbacks when read tools are missing.

Verify exposed tools:

```bash
.specify/extensions/drupal/scripts/bash/verify-mcp-tools.sh
```

## Configuration

Edit `.specify/extensions/drupal/drupal-config.yml`:

```yaml
mcp_tools:
  enabled: true
  composer_constraint: "^1.0@beta"
  scope: read,write
  uid: 1
  cursor_server_name: drupal
  cursor_config: .cursor/mcp.json
  submodules:
    - mcp_tools
    - mcp_tools_stdio
    - mcp_tools_structure
    # … see config-template.yml
```
