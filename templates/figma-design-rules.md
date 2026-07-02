# Figma → Drupal Design Rules

Guidance for translating Figma UX into Spec Kit artifacts. Used by
`speckit.drupal.figma-design` and the `speckit-drupal-figma-design` skill.

## Artifact Flow

```text
Figma URL in /speckit-specify
        ↓
/speckit-drupal-figma-design  (Figma MCP reads component)
        ↓
design-context.md             (structured design + Drupal mapping)
        ↓
spec.md                       (UX & Design section, config vs code)
        ↓
plan.md                       (Theme Strategy, Twig paths, config export, figma-design-checks.yml)
        ↓
figma-design-checks.yml       (copy/classes, screenshot baselines — required at plan time)
figma-baselines/              (PNG baselines from export-figma-baselines.sh)
        ↓
tasks.md                      (admin UI, Twig, theme build, [FIGMA] gates, export, QA)
```

## Brownfield vs Greenfield

| | Brownfield | Greenfield |
|--|------------|------------|
| **Use when** | Site has Olivero/custom theme; match existing DS | New brand, no reusable theme |
| **Theme path** | `web/themes/custom/[existing_subtheme]/` | `web/themes/custom/[new_theme]/` |
| **Spec says** | Override templates, extend CSS | New theme scaffold, regions, libraries |
| **Risk** | Fighting base theme constraints | More upfront setup |

## What Goes in spec.md vs design-context.md

| spec.md | design-context.md |
|---------|-------------------|
| User-visible requirements | Pixel/token-level detail from Figma |
| UX acceptance scenarios | Component variant matrix |
| High-level config vs code table | Full Drupal mapping table |
| Theme strategy one-liner | Breakpoints, regions, token table |

## Mandatory Spec Sections (when Figma provided)

- **UX & Design (Figma)** — link to design-context, strategy, primary frames
- **Configuration vs Custom Code** — include theme/Twig rows
- **UX & Accessibility** — contrast/semantics from design (QR-A11Y-001)

## Plan Sections (Phase 1)

- **Theme Strategy** — brownfield/greenfield, base theme, Twig override list
- **Figma Design Parity** — `figma-design-checks.yml`, `figma-baselines/`, export commands, thresholds
- **Config Strategy** — Views/blocks/fields from design regions
- **Project Structure** — theme template paths + Figma artifact paths

## Do Not

- Paste full Figma JSON into spec.md
- Commit Figma API tokens to the repo
- Skip `design-context.md` when a Figma URL was provided
- Implement Twig in specify phase (plan/tasks/implement only)
