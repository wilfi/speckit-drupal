# Project data model — Drupal

**Project**: [PROJECT NAME]  
**Site ID**: `[default_site_key]` (see [sites.yml](./sites.yml))  
**Maintained**: Update when any feature adds or changes entities; feature `data-model.md` files document deltas only.

## Content types & bundles

### Node: [Bundle machine name] (`[bundle]`)

| Field | Machine name | Type | Cardinality | Required | Notes |
|-------|--------------|------|-------------|----------|-------|
| Title | `title` | string | 1 | Yes | |

### Taxonomy: [Vocabulary] (`[vocabulary_machine_name]`)

| Term | Machine name | Notes |
|------|--------------|-------|
| [Label] | `[term_machine_name]` | |

### Block content: [Type] (`[block_type]`)

| Field | Machine name | Type | Required |
|-------|--------------|------|----------|
| | | | |

### Webform: [id] (`[webform_id]`)

| Element | Key | Type | Required |
|---------|-----|------|----------|

## Views

### `[view_machine_name]`

| Property | Value |
|----------|-------|
| Base table | |
| Filter | |
| Display | |

## Roles & permissions

| Role | Key permissions | Notes |
|------|-----------------|-------|
| anonymous | | |
| content_editor | | |

## Feature deltas

When a feature changes this model, add a row:

| Feature | Change | Date |
|---------|--------|------|
| specs/NNN-feature | Initial model | |
