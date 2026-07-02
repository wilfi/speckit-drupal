# Design Context: [FEATURE NAME]

**Feature**: [###-feature-name] | **Date**: [DATE]

**Figma source**: [URL with node-id]

**Figma node**: [component/frame name and id]

**Theme strategy**: [brownfield | greenfield | hybrid]

**Active theme (brownfield)**: [e.g. Olivero + subtheme path]

## Design Summary

[1–3 sentences: what the user sees, primary actions, key content blocks]

## Layout & Regions

| Region (Figma) | Drupal region / placement | Notes |
|----------------|----------------------------|-------|
| [Hero] | [e.g. content region, block] | |

> **Automation**: `/speckit-drupal-figma-design` also writes `figma-regions.yml` with selectors,
> smoke markers, and baseline names. `setup-feature-artifacts` syncs into `figma-design-checks.yml`.

## Figma Quality Mapping *(optional YAML block — prefer figma-regions.yml)*

See `templates/figma-regions-template.yml` for the machine-readable schema synced at plan time.
| [Article list] | [e.g. View block] | |

## Typography & Color Tokens

| Figma style / variable | Token name | Drupal mapping |
|------------------------|------------|----------------|
| [Heading/H1] | [font-size, weight] | [theme CSS var or existing class] |
| [Primary color] | [#hex or variable] | [CSS custom property] |

## Components

| Figma component | Variants | Drupal implementation |
|-----------------|----------|----------------------|
| [Card/Article teaser] | [default, hover] | [View row style + Twig override path] |
| [Button/Primary] | | [theme button pattern or existing] |

## Responsive Breakpoints

| Breakpoint | Figma frame | Layout behavior |
|------------|-------------|-----------------|
| Mobile | [375px frame name] | [stack, hide sidebar, etc.] |
| Desktop | [1440px frame name] | |

## States

| State | Figma reference | Spec / acceptance note |
|-------|-----------------|------------------------|
| Default | | |
| Empty | | UX empty state |
| Loading | | |
| Error | | |

## Drupal Mapping (config vs code vs theme)

| Item | Approach | Path / config |
|------|----------|---------------|
| [Article listing] | View + block (config export) | `views.view.*` |
| [Teaser markup] | Twig override (theme) | `templates/node--article--teaser.html.twig` |
| [Hero copy] | Field on landing node | `field.hero_text` |
| [Custom logic] | Module (only if required) | `web/modules/custom/` |

## Code Connect (if available)

| Figma component | Connected code | Reuse in this feature? |
|-----------------|------------------|------------------------|
| | | |

## Open Questions

- [ ] [Missing mobile frame / empty state / token not in design system]

## References

- [Figma file](URL)
- [Spec](./spec.md)
