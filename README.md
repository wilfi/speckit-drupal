# Drupal Spec Kit Extension

Drupal CMS integration for Spec Kit — installation, workflow templates, config sync,
DDEV scaffolding, **quality rules (2s load + WCAG a11y)**, and hooks.

**Runbook:** [RUNBOOK.md](./RUNBOOK.md) — full operational manual. **Greenfield + pixel-perfect homepage:**
[GREENFIELD-RUNBOOK.md](./GREENFIELD-RUNBOOK.md) — shipped with the extension and copied to the
project root on `/speckit-drupal-setup`. Each project also gets `README-SPECKIT-DRUPAL.md` at the
repo root when you run setup.

## Commands

| Command | Slash (Cursor) | Description |
|---------|----------------|-------------|
| `speckit.drupal.install` | `/speckit-drupal-install` | Install Drupal via Composer |
| `speckit.drupal.setup` | `/speckit-drupal-setup` | Templates, Drush, config sync, DDEV |
| `speckit.drupal.setup-mcp-tools` | `/speckit-drupal-setup-mcp-tools` | Install mcp_tools + Cursor MCP (local site building) |
| `speckit.drupal.verify-plan` | `/speckit-drupal-verify-plan` | Validate Drupal plan sections |
| `speckit.drupal.verify-foundational` | `/speckit-drupal-verify-foundational` | Phase 2 gate — contrib, config export, active entities |
| `speckit.drupal.verify-quality` | `/speckit-drupal-verify-quality` | Enforce 2s load + WCAG2AA scan |
| `speckit.drupal.finalize` | `/speckit-drupal-finalize` | Cache rebuild, config export, quality verify |
| `speckit.drupal.figma-design` | `/speckit-drupal-figma-design` | Figma MCP → `design-context.md` for Drupal spec |
| `speckit.drupal.setup-feature-artifacts` | `/speckit-drupal-setup-feature-artifacts` | Auto-scaffold + sync Figma YAML after plan |
| `speckit.drupal.try-export-figma-baselines` | `/speckit-drupal-try-export-figma-baselines` | Conditional baseline PNG export |
| `speckit.drupal.verify-figma-section` | `/speckit-drupal-verify-figma-section` | Section QR-FIGMA-002 + fix queue |
| `speckit.drupal.figma-fix-loop` | `/speckit-drupal-figma-fix-loop` | Agent loop until sections pass |

## Automation pipeline (Figma features)

```text
/speckit-specify     → design-context.md + figma-regions.yml
/speckit-plan        → setup-feature-artifacts → sync-figma-checks-from-design
                     → populate figma-asset-manifest.yml (download_assets)
                     → export-figma-theme-assets.sh
/speckit-implement   → try-export (before_implement)
                     → verify-figma-section per story (hard gate — no [X] until pass)
                     → figma-fix-loop on fail
/speckit-drupal-finalize → verify-quality MUST exit 0 (QR-FIGMA-002 hard gate)
```

**Theme assets**: `templates/figma-asset-export.md` — **`download_assets` only**, never `get_design_context` URLs for PNGs.

**Config** (`drupal-config.yml` → `figma`): `export_when`, `require_baselines_at_polish`, `require_asset_manifest_at_polish`, `region_selector_pattern`.

**Automatic**: regions sync, smoke merge, conditional baseline export, sectional verify reports, fix-loop skill.

**Agent-only**: Twig/CSS edits inside fix-loop (cannot be fully scripted).

## Figma → Drupal Spec

When a feature includes Figma UX:

1. Connect **Figma MCP** in Cursor (`https://mcp.figma.com/mcp`)
2. Run `/speckit-drupal-figma-design` with your Figma component URL (or include URL in `/speckit-specify`)
3. Agent writes `design-context.md` (tokens, layout, Drupal mapping)
4. `/speckit-specify` merges into `spec.md` (UX & Design section)
5. `/speckit-plan` adds Theme Strategy (brownfield/greenfield Twig paths)

See `templates/figma-design-rules.md` and skill `.cursor/skills/speckit-drupal-figma-design/`.

## Quality Rules (mandatory)

Defined in `templates/drupal-quality-rules.md` and `drupal-config.yml`:

| Rule | Requirement |
|------|-------------|
| **QR-PERF-001** | Primary URL loads in ≤ **2 seconds** |
| **QR-A11Y-001** | **WCAG2AA** automated scan — **zero errors** (pa11y) |

Templates (spec/plan/tasks) embed these rules. **Phase 2** runs `verify-foundational.sh`; polish phase runs `verify-quality.sh`.

## Foundational Gate (Phase 2)

After site building and config export, **before user stories**:

1. Fill `specs/<feature>/foundational-checklist.yml` from `foundational-checklist-template.yml`
2. `ddev drush config:import -y && ddev drush cr`
3. `.specify/extensions/drupal/scripts/bash/verify-foundational.sh` — must exit 0

`/speckit-implement` stops at this gate if verification fails.

## MCP Tools (optional — AI site building)

Connect Cursor to your **local** Drupal site for **`[SB]`** tasks (content types,
Views, Webform, blocks) via [drupal/mcp_tools](https://www.drupal.org/project/mcp_tools).

```bash
/speckit-drupal-setup-mcp-tools
```

1. Configures `.cursor/mcp.json` (server name default: `drupal`)
2. Enables site-building submodules from `drupal-config.yml`
3. See `templates/mcp-tools-workflow.md` for MCP vs config-first + export workflow

**Not a replacement for** `config/sync/`, verify-foundational, or theme code — always
`ddev drush config:export -y` after MCP changes.

## Recommended Workflow

See **[GREENFIELD-RUNBOOK.md](./GREENFIELD-RUNBOOK.md)** for the full pixel-perfect homepage checklist and extension repo ship manifest.

```text
1. /speckit-drupal-install
2. /speckit-drupal-setup              # copies GREENFIELD-RUNBOOK.md to project root
3. cp config-template.yml → drupal-config.yml (customize)
4. ddev start && ddev drush site:install -y
5. /speckit-drupal-setup-mcp-tools          # optional: Cursor ↔ Drupal MCP
6. /speckit-specify → /speckit-plan → /speckit-drupal-verify-plan
7. /speckit-tasks → /speckit-implement (Phase 2: MCP for [SB], then config export + gate)
8. /speckit-drupal-finalize    # includes quality verify when enabled
```

## Configuration

```yaml
quality_rules:
  enabled: true
  use_ddev: true
  performance:
    max_load_seconds: 2
    check_urls:
      - /
  accessibility:
    standard: WCAG2AA
    max_errors: 0
  post_implement:
    run_verify: true
```

## Hooks

| Event | Command | Default |
|-------|---------|---------|
| `after_plan` | `speckit.drupal.verify-plan` | Optional |
| `after_implement` | `speckit.drupal.finalize` | Optional (runs quality verify) |

## Development

```bash
specify extension add --dev extension-src/drupal --force
```

**Publishing a standalone extension repo:** ship only the framework (see **Part 1** in
[GREENFIELD-RUNBOOK.md](./GREENFIELD-RUNBOOK.md)). Exclude `drupal-config.yml`, `node_modules/`,
and all consumer site content (`web/`, `specs/`, `.cursor/skills/`).
