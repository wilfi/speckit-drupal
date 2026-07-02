---
name: speckit-implement
description: Drupal extension overlay — Figma parity gates for /speckit-implement
compatibility: Requires Drupal Spec Kit extension and spec-kit project structure
metadata:
  author: spec-project
  source: drupal:templates/speckit-implement-drupal-gates.md
---

# Drupal Extension — `/speckit-implement` Figma Gates

**Merge with** the base `speckit-implement` skill. When the Drupal extension is
active and the feature is Figma-backed, enforce these gates in addition to the
base implement workflow.

## Detect Figma feature

Treat as Figma-backed when **any** of:

- `specs/<feature>/design-context.md` exists
- `spec.md` or `plan.md` contains a `figma.com` URL or **UX & Design (Figma)** section
- `foundational-checklist.yml` has `figma.enabled: true`

## Phase 2.1 — before user-story theme work

Do **not** start `[TH]` tasks until:

- [ ] `figma-design-checks.yml` exists (from `figma-design-checks-template.yml`)
- [ ] `quality-checks.yml` exists with `assets.enabled: true`, `smoke.icon_markers`, `css.component_padding_rules`
- [ ] **`figma-asset-manifest.yml` populated** (every theme icon/image; Figma MCP **`download_assets`**) — see `templates/figma-asset-export.md`
- [ ] **`export-figma-theme-assets.sh specs/<feature>`** run successfully (**QR-ASSET-005**)
- [ ] `figma-baselines/figma-source/` contains reference PNGs
- [ ] `verify-plan.sh specs/<feature>/plan.md` exits 0

## Per user story — QR-FIGMA hard gate

After each user story's `[TH]` theme tasks:

1. Re-export theme assets if manifest changed: `export-figma-theme-assets.sh specs/<feature>`
2. Run `.specify/extensions/drupal/scripts/bash/verify-figma-section.sh specs/<feature> <story-section-slugs>`
3. **Section** screenshot diffs MUST pass (**QR-FIGMA-002**)
4. Fix Twig/CSS/assets until diff ≤ configured `max_diff_percent`
5. **Do not mark the story `[FIGMA]` task or story checkpoint `[X]` until step 2 exits 0**

Tag these tasks `[FIGMA]` in `tasks.md`.

## Phase 9 completion — mandatory hard gate

**Before** marking **any Phase 9 task** or `/speckit-implement` complete:

### When Figma-backed feature

1. Confirm `figma-design-checks.yml` and populated `figma-asset-manifest.yml` exist — **STOP** if missing
2. Run:

   ```bash
   .specify/extensions/drupal/scripts/bash/verify-quality.sh specs/<feature>
   ```

3. **MUST exit 0** — includes **QR-FIGMA-002**, **QR-ASSET-005**, **QR-SMOKE-008/009**, **QR-CSS-005**
4. If verify fails: fix theme/CSS/assets, re-run — **do not mark Phase 9 or polish tasks `[X]`**

### Marking tasks complete

- **Never** mark a `[FIGMA]` gate task `[X]` unless the corresponding verify command exited 0 in this session
- **Never** mark Phase 9 complete while `verify-quality.sh` fails

### Completion checklist (append to base "Done When")

- [ ] All tasks in `tasks.md` completed and marked `[X]`
- [ ] **Figma features**: `verify-quality.sh specs/<feature>` exited 0 (QR-FIGMA-002 + assets + smoke icons)
- [ ] Extension hooks dispatched per base implement skill

## Agent behavior on failure

| Failure | Action |
|---------|--------|
| Missing `figma-design-checks.yml` | Re-run `/speckit-plan` or `setup-feature-artifacts.sh` |
| Empty `figma-asset-manifest.yml` | Populate via Figma MCP **`download_assets`**; see `figma-asset-export.md` |
| SVG saved as `.png` (QR-ASSET-004) | Re-export with `download_assets` + `export-figma-theme-assets.sh` |
| Missing baselines | `export-figma-source-baselines.sh specs/<feature>` |
| QR-FIGMA-002 diff too high | `/speckit-drupal-figma-fix-loop` |
| QR-ASSET-001 / QR-ASSET-005 | Fix paths; use `theme_assets` in Twig; run export script |
| QR-SMOKE-008/009 | Fix icon `<img>` markup in explore/featured templates |
