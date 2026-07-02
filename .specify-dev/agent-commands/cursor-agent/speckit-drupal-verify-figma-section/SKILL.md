---
name: speckit-drupal-verify-figma-section
description: QR-FIGMA-002 for one or more sections; writes fix queue for agent loop
compatibility: Requires spec-kit project structure with .specify/ directory
metadata:
  author: spec-project
  source: drupal:commands/speckit.drupal.verify-figma-section.md
---

# Verify Figma Section

```bash
.specify/extensions/drupal/scripts/bash/verify-figma-section.sh specs/<feature> [section...]
```

On failure, run `/speckit-drupal-figma-fix-loop`.
