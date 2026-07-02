# Drupal project context

Project-level Drupal documentation shared across **all** Spec Kit features.
Agents and `/speckit-specify`, `/speckit-plan`, and `/speckit-implement` should
read these files before writing feature specs.

| File | Purpose |
|------|---------|
| [data-model.md](./data-model.md) | Content types, fields, vocabularies, Views, Webforms, permissions |
| [site-structure.md](./site-structure.md) | Themes, regions, menus, routes, block layout patterns |
| [sites.yml](./sites.yml) | Site names, hostnames, multisite mapping, default site for this repo |

## How features use this

| Artifact | What to document |
|----------|------------------|
| **Feature `spec.md`** | User-facing requirements; link project context; document **deltas** only |
| **Feature `data-model.md`** | **Changes** to the project model for this feature — not a full copy |
| **Feature `plan.md`** | Link project `site-structure.md` + `data-model.md`; feature-specific architecture |

When a feature **introduces** new entities, update **project** `data-model.md`, then
record the delta in the feature `data-model.md`.

## Multisite

Edit `sites.yml` when adding sites. Each feature spec should name target site(s)
using `sites.yml` keys (e.g. `default`, `spec_project`).

## Scaffold

```bash
.specify/extensions/drupal/scripts/bash/scaffold-project-context.sh
```

Created automatically by `/speckit-drupal-setup` when missing.
