# MCP Tools Workflow (Spec Kit + Drupal)

Use **[drupal/mcp_tools](https://www.drupal.org/project/mcp_tools)** to accelerate **`[SB]`**
site-building tasks during `/speckit-implement`. Theme **`[TH]`** and DevOps **`[DO]`**
tasks remain in the repo (Twig, CSS, Drush scripts, PHPUnit, quality gates).

## Setup (once per project)

```bash
/speckit-drupal-setup-mcp-tools
```

Then:

1. `/admin/config/services/mcp-tools` → **Development** preset (local only)
2. Reload MCP in Cursor (Settings → MCP)
3. Confirm server **`drupal`** appears with tools from enabled submodules

## When to use MCP vs config-first

| Work type | Use MCP Tools? | Why |
|-----------|----------------|-----|
| **[SB]** Site building | **Yes** (optional accelerator) | Content types, fields, taxonomy, Views, Webform, block placement |
| **[TH]** Theme | **No** | Twig/CSS/JS belong in git under `web/themes/custom/` |
| **[CM]** Custom module | **No** | Code in `web/modules/custom/` |
| **[DO]** DevOps / QA | **No** | PHPUnit, PHPCS, `verify-foundational.sh`, `verify-quality.sh` |

### Phase 2 (Foundational)

Good MCP candidates:

- Create content types and fields per `data-model.md`
- Taxonomy vocabularies and default terms
- Custom block types
- Role permissions (verify in UI or export)
- Enable default theme

After MCP work **always**:

```bash
ddev drush config:export -y
ddev drush config:import -y && ddev drush cr   # if team imports before verify
.specify/extensions/drupal/scripts/bash/verify-foundational.sh
```

**T019 / T011 FOUNDATIONAL GATE** must pass before Phase 3.

### User story phases (US1–US5)

| MCP helps with | Examples |
|----------------|----------|
| Views | Featured carousel, recipe grid, exposed filters |
| Blocks | Hero, explore, about, Webform placement |
| Menus | Main/footer menu structure |
| Webform | Newsletter form config |

| Keep in implement (no MCP) | Examples |
|----------------------------|----------|
| Theme templates | `page--front.html.twig`, `node--recipe--card.html.twig` |
| Theme PNG icons | `figma-asset-manifest.yml` + **`download_assets`** + `export-figma-theme-assets.sh` — **not** MCP `get_design_context` URLs |
| CSS/JS | Breakpoints, hamburger, design tokens |
| Tests | Functional tests in theme `tests/` |

## Ideal loop per story

```text
1. Read tasks.md [SB] items + data-model.md / contracts/
2. Ask Cursor (with drupal MCP): "Create … per specs/…/data-model.md"
3. ddev drush config:export -y
4. Continue [TH] / [DO] tasks in repo
5. Run story PHPUnit + mark tasks [X]
```

## Security

- **Local DDEV only** for write scope (`read,write`)
- **Staging**: config-only mode or limited scopes
- **Production**: read-only or disable MCP write entirely
- MCP changes live in the **database** until exported — treat export + git commit as the source of truth

## Troubleshooting

| Issue | Fix |
|-------|-----|
| No MCP tools in Cursor | Reload MCP; check `.cursor/mcp.json`; run setup again |
| `drush mcp-tools:serve` fails | `ddev drush en mcp_tools_stdio -y`; use `--uid=1` |
| verify-foundational fails | Export config; `ddev drush cim -y && ddev drush cr` |
| webform MCP missing | Install `drupal/webform`; re-run `/speckit-drupal-setup-mcp-tools` to enable `mcp_tools_webform` |

## Related

- Setup command: `/speckit-drupal-setup-mcp-tools`
- Config: `.specify/extensions/drupal/drupal-config.yml` → `mcp_tools:`
- Work types in tasks: `[SB]` `[TH]` `[DO]` in `tasks-template.md`
