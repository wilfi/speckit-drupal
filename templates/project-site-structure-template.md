# Site structure — [PROJECT NAME]

**Site ID**: `[default_site_key]` (see [sites.yml](./sites.yml))  
**Default theme**: `[theme_machine_name]` (`web/themes/custom/[theme]/`)  
**Config sync**: `config/sync/`  
**Front page**: `/`

## Theme regions

| Order | Region | Source | Notes |
|------:|--------|--------|-------|
| 1 | Header | `region--header` | |
| 2 | Content | `region--content` | |

## Menus

| Menu | Machine name | Notes |
|------|--------------|-------|
| Main | `main` | |

## Key routes

| Path | Delivery |
|------|----------|
| `/` | Front page |

## Block placement pattern

- [How blocks / Views are placed and visibility rules]

## Multisite notes

See [sites.yml](./sites.yml) for hostnames and `drupal_site_dir` per site.
