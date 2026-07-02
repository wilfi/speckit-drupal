---
description: "QR-FIGMA-002 for one or more sections; writes fix queue for agent loop"
---

# Verify Figma Section (QR-FIGMA-002)

Section-scoped screenshot diff. Faster than full `verify-quality.sh` during implement.

## Outputs

- `specs/<feature>/figma-baselines/reports/latest.json`
- `specs/<feature>/figma-fix-queue.md`

## Execution

```bash
.specify/extensions/drupal/scripts/bash/verify-figma-section.sh specs/<feature>
.specify/extensions/drupal/scripts/bash/verify-figma-section.sh specs/<feature> hero featured
```

## When to run

After each user story's `[TH]` theme tasks (before marking story complete).

On failure → run `/speckit-drupal-figma-fix-loop`.
