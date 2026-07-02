# Feature Specification: [FEATURE NAME]

**Feature Branch**: `[###-feature-name]`

**Created**: [DATE]

**Status**: Draft

**Input**: User description: "$ARGUMENTS"

> **Drupal extension quality gates** *(mandatory — do not remove)*: Every
> user-facing feature automatically includes **QR-PERF-001** (primary URL loads
> ≤ 2s) and **QR-A11Y-001** (WCAG2AA automated scan, zero errors). Preserve
> all sections below marked *(mandatory)*; do not ask the user to supply these
> rules separately.

## How to prompt `/speckit-specify` *(template reference — omit from output `spec.md`)*

> **Agents**: Do **not** write this section into the feature `spec.md`. It is
> author guidance only. Populate `**Input**` and the sections below from the
> user's message and `design-context.md`.

**Prerequisites for Figma**: Figma MCP in Cursor (`https://mcp.figma.com/mcp`).
A `figma.com` URL in the prompt triggers optional hook `/speckit-drupal-figma-design`
→ `design-context.md` before this spec is written.

### Minimum prompt (Figma hook auto-runs)

```text
/speckit-specify Build a [page/feature] for [site name].

Figma: https://www.figma.com/design/FILE_KEY/Design-Name?node-id=XX-YY
```

Use a **frame or component URL** with `node-id=` so Figma MCP fetches the correct node.

### Recommended prompt (best results)

```text
/speckit-specify Build [what] as [primary URL, e.g. site front page /].

Figma: https://www.figma.com/design/FILE_KEY/Design-Name?node-id=XX-YY

Theme: [greenfield | brownfield | hybrid] — [new theme machine name | extend existing theme]

Scope:
- [content types, fields, taxonomy]
- [Views, blocks, menus, Webform]
- [theme vs config export split]
- [breakpoints / layout notes from Figma]

Constraints:
- [language, search, integrations]
- [explicit out of scope for v1]
```

**Greenfield example** — new custom theme:

```text
/speckit-specify Build the recipe blog landing page as the site front page (/).

Figma: https://www.figma.com/design/FILE_KEY/Cooking-Blog?node-id=7-360

Theme: greenfield — new custom theme `my_theme` under web/themes/custom/

Scope: Recipe content type, Views (featured carousel + paginated grid), block-composed
front page, Webform newsletter, config export for Views/blocks/menus.
```

**Brownfield example** — extend existing theme:

```text
/speckit-specify Add a marketing landing page with hero and latest articles.

Figma: https://www.figma.com/design/FILE_KEY/Marketing?node-id=42-128

Theme: brownfield — extend existing theme `olivero` (Twig/CSS overrides only)
```

### Two-step alternative (more control)

```text
/speckit-drupal-figma-design https://www.figma.com/design/FILE_KEY/Name?node-id=XX-YY
Theme: greenfield — new theme my_theme
```

Then:

```text
/speckit-specify Build the landing page from design-context.md. [Scope and constraints…]
```

### What happens automatically

1. `figma.com` in the prompt → optional `/speckit-drupal-figma-design` → `design-context.md`
2. `/speckit-specify` merges design context into **UX & Design (Figma)** below
3. Continue: `/speckit-plan` → `/speckit-tasks` → `/speckit-implement`

Read project baseline before specifying: `.specify/drupal/data-model.md`, `site-structure.md`, `sites.yml`.

---

Before writing this spec, read the **project-level** Drupal context (if present):

| Document | Path |
|----------|------|
| Content model (baseline) | `.specify/drupal/data-model.md` |
| Site structure | `.specify/drupal/site-structure.md` |
| Sites / multisite | `.specify/drupal/sites.yml` |

**Target site(s)**: [site key from `sites.yml`, e.g. `spec_project`]

In this spec, document only **what changes** for this feature — new bundles, fields,
pages, or routes. Do not copy the full project data model here.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - [Brief Title] (Priority: P1)

[Describe this user journey in plain language]

**Why this priority**: [Explain the value and why it has this priority level]

**Independent Test**: [Describe how this can be tested independently]

**Acceptance Scenarios**:

1. **Given** [initial state], **When** [action], **Then** [expected outcome]
2. **Given** [initial state], **When** [action], **Then** [expected outcome]
3. **Given** an anonymous visitor on the primary URL, **When** the page is requested,
   **Then** it loads within **2 seconds** (**QR-PERF-001**).
4. **Given** the primary URL is rendered, **When** an automated WCAG2AA scan runs,
   **Then** it reports **zero errors** (**QR-A11Y-001**).

---

[Add more user stories as needed — include QR-PERF-001 and QR-A11Y-001 acceptance
scenarios for each user-facing story]

### Edge Cases

- What happens when [boundary condition]?
- How does system handle [error scenario]?
- Cache invalidation when [content changes]?
- Permission denied for [role]?
- Primary URL exceeds 2s load budget — what is the caching/render optimization plan?
- Accessibility regression — which semantic markup or contrast fixes are required?

## Drupal Content Model *(mandatory for content features)*

**Project baseline**: `.specify/drupal/data-model.md`  
Document **deltas only** below (new or changed entities for this feature).

### Content Types & Entities

- **[Entity/Bundle]**: [Purpose, key fields, relationships]
- **Fields**: [field_name: type, cardinality, required, translatable?]
- **Display modes**: [teaser, full — view modes for themed output]
- **Form display**: [default form MUST expose every bundle field under Manage form display — **QR-CONFIG-001**; export `core.entity_form_display.*` to `config/sync/`]

### Taxonomies & References

- **[Vocabulary]**: [Terms, hierarchy, usage]
- **Entity references**: [Source → target, cardinality]

### Configuration vs Custom Code

| Item | Approach | Rationale |
|------|----------|-----------|
| [e.g. View listing] | Config export / UI | [Why] |
| [e.g. Custom validation] | Custom module | [Why] |

## Permissions & Roles *(mandatory)*

- **Roles affected**: [anonymous, authenticated, editor, admin, custom]
- **Permissions**: [specific permissions to grant/revoke]
- **Access hooks**: [entity access, route requirements if custom]

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST [specific capability]
- **FR-002**: System MUST [specific capability]
- **FR-003**: Editors MUST be able to [content workflow action]

### Non-Functional Requirements (Drupal) *(mandatory — do not remove)*

- **NFR-001**: Primary user-facing URL MUST load within **2 seconds** (**QR-PERF-001**; `quality_rules.performance.max_load_seconds`)
- **NFR-002**: All forms MUST include CSRF protection (Drupal default)
- **NFR-003**: Public pages MUST use cache tags/contexts correctly
- **NFR-004**: User-facing UI MUST pass automated **WCAG2AA** scan with zero errors (**QR-A11Y-001**)

### Drupal Extension Quality Rules *(mandatory — do not remove)*

Per `.specify/extensions/drupal/templates/drupal-quality-rules.md`:

| Rule | Requirement | Verification |
|------|-------------|--------------|
| **QR-PERF-001** | Primary URL(s) load ≤ 2s | `verify-quality.sh` / `/speckit-drupal-verify-quality` |
| **QR-A11Y-001** | pa11y WCAG2AA — zero errors | Same |

### Contrib Modules *(if applicable)*

- **[module_name]**: [Purpose, version constraint, config dependencies]

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: [User outcome]
- **SC-002**: Primary URL loads within **2 seconds** (**QR-PERF-001**)
- **SC-003**: Automated accessibility scan reports **zero errors** (**QR-A11Y-001**)
- **SC-004**: [Scale/business metric]

### UX & Accessibility Requirements *(mandatory — do not remove)*

- **UX-001**: Error, empty, and loading states defined per user journey
- **UX-002**: Admin and theme patterns align with project design system
- **UX-003**: WCAG 2.1 Level AA for all user-facing flows (verified by QR-A11Y-001)

## UX & Design (Figma) *(when Figma URL provided — fill from user prompt + `design-context.md`)*

**Design source**: [Figma URL + node-id from user prompt]

**Design context**: [link to `./design-context.md` — from `/speckit-drupal-figma-design` hook or manual run]

**Theme strategy**: [brownfield — extend `[theme]` | greenfield — new custom theme | hybrid]

### Visual Requirements

- **UX-004**: Layout MUST match Figma frame **[frame name]** at breakpoints documented in design-context
- **UX-005**: Typography and colors MUST use tokens mapped in design-context (or document deviations)
- **UX-006**: Component states (default, hover, empty, loading) MUST match Figma variants where specified

### Design → Drupal Approach *(summary — detail in design-context.md)*

| Figma element | Drupal approach |
|---------------|-----------------|
| [e.g. Article card] | [View row + Twig override] |
| [e.g. Hero] | [Fields + block placement] |

## Assumptions

- [Drupal version, e.g. "Drupal 11.x on recommended-project layout"]
- [Environment, e.g. "DDEV local; config sync in config/sync"]
- [Existing modules/services reused]
- [Primary URL(s) for QR-PERF-001 / QR-A11Y-001, default `/`]
