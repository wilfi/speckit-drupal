---
description: "Generate .specify/drupal/ project context from live Drupal site via MCP"
---

# Generate Site Context

Capture the live Drupal site (content model, structure, status) via **Drupal MCP**
and scaffold `.specify/drupal/`.

## Skill

Use the **`generate-site-context`** skill (`.cursor/skills/generate-site-context/SKILL.md`).

Slash command: **`/generate-site-context`**

## Outputs

| File | Description |
|------|-------------|
| `data-model.md` | Content types, fields, vocabularies, views, roles |
| `site-structure.md` | Theme regions, block placement, menus |
| `site-status.md` | Drupal/PHP versions, cron, requirements, config drift |
| `sites.yml` | Updated site metadata (multisite-aware) |
| `generated/site-context-bundle.json` | Raw MCP archive |

## Execution (after MCP collection)

```bash
.specify/extensions/drupal/scripts/bash/generate-site-context.sh
```

## Prerequisites

- Drupal MCP enabled: `/speckit-drupal-setup-mcp-tools`
- `verify-mcp-tools.sh` passes
