# Views HTML List CSS Rules

Mandatory layout conventions for Drupal Views **`html_list`** style and similar
list-based components. Enforced by **QR-CSS-001–003** via `verify-quality.sh`.

## Markup contract

Views `html_list` with `wrapper_class: my-block__items` renders:

```html
<div class="my-block__items">
  <ul>
    <li>…card or row content…</li>
  </ul>
</div>
```

The wrapper `<div>` has **one child** (`<ul>`). List items are **`ul > li`**, not
direct children of the wrapper.

## CSS rules (all new components MUST follow)

| Rule | DO | DON'T |
|------|-----|--------|
| **QR-CSS-001** | Apply `display: grid` / `flex` on **`.my-block__items ul`** | Apply grid/flex on **`.my-block__items`** alone |
| **QR-CSS-002** | Reset list styles on **`.my-block__items ul > li`** | Use **`.my-block__items > li`** |
| **QR-CSS-003** | Style pagers: **`.my-block__pager .pager__items`** with `display: flex`, `list-style: none` | Leave `.pager__items` as default vertical bullets |

### Correct example

```css
.my-block__items ul {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: var(--space-md);
  list-style: none;
  padding: 0;
  margin: 0;
}

.my-block__items ul > li {
  list-style: none;
  margin: 0;
  padding: 0;
}

.my-block__pager .pager__items {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: var(--space-xs);
  list-style: none;
  padding: 0;
  margin: 0;
}
```

### Incorrect example (causes stacked cards + bullets)

```css
/* WRONG — grid on wrapper div; only one <ul> child, cards never align */
.my-block__items {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
}

/* WRONG — no <ul> in selector; list bullets remain */
.my-block__items > li {
  list-style: none;
}
```

## When to apply

- Any View using `style: html_list` with a BEM `wrapper_class`
- Custom Twig that wraps `{{ rows }}` from Views (carousel, grid, card lists)
- JavaScript carousels (Slick, etc.) — target **`.block__carousel ul`**, not the outer wrapper

## Views config

```yaml
style:
  type: html_list
  options:
    wrapper_class: my-block__items
```

Document `wrapper_class` values in `quality-checks.yml` under `css.views_list_wrappers`
(grid layouts also list under `css.views_list_grid_layout`).

## References

- `templates/drupal-quality-rules.md` — QR-CSS-001–003
- `templates/quality-checks-template.yml` — per-feature wrapper classes
- Example: `recipe-grid__items`, `featured-recipes__items` in cooks_delight theme
