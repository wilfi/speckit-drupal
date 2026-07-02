---
description: "Extract Figma design context and map it to Drupal spec inputs (theme, layout, config vs code)"
---

# Figma Design → Drupal Spec Context

Run **before or during** `/speckit-specify` when the feature includes Figma UX designs.

## Prerequisites

- Figma MCP server connected in Cursor (`https://mcp.figma.com/mcp` recommended)
- Skill: `speckit-drupal-figma-design`

## Behavior

1. Read `figma` settings from `.specify/extensions/drupal/drupal-config.yml`
2. Parse Figma URL from `$ARGUMENTS` or active feature input
3. Use **Figma MCP** to fetch frame/component context (agent-driven)
4. Write `specs/[feature]/design-context.md` from `templates/design-context-template.md`
5. Output summary for `/speckit-specify` to merge into `spec.md`

## User Input

```text
$ARGUMENTS
```

Example:

```text
https://www.figma.com/design/FILE_KEY/Site?node-id=123-456
Theme: brownfield — extend Olivero subtheme
```

## Theme Strategy

| Value | Meaning |
|-------|---------|
| `brownfield` | Twig/CSS overrides on existing theme (default) |
| `greenfield` | New custom theme under `web/themes/custom/` |
| `hybrid` | Code Connect + selective overrides |

## Execution

Agent skill: `.cursor/skills/speckit-drupal-figma-design/SKILL.md`

Optional script (creates empty scaffold if MCP skipped):

```bash
.specify/extensions/drupal/scripts/bash/figma-design-context.sh [feature-dir] [figma-url]
```

## Hook

Register as `before_specify` when Figma URL detected (optional, priority 15).
