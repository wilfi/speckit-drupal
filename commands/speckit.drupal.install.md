---
description: "Install the latest Drupal recommended project in the current directory"
---

# Install Drupal

Install the latest stable Drupal (recommended project template) into the current
Spec Kit workspace using Composer.

## Behavior

1. Read configuration from `.specify/extensions/drupal/drupal-config.yml`
2. Verify `composer` and `php` are available and meet the minimum PHP version
3. Detect if Drupal is already installed (abort with guidance if so)
4. Create a temporary directory and run `composer create-project` for the
   configured template (default: `drupal/recommended-project`)
5. Merge Drupal files into the project root, preserving Spec Kit directories
   (`.specify`, `.cursor`, `specs`, `.git`)
6. Report installed Drupal version and suggested next steps

## Execution

Run the install script from the project root:

- **Bash**: `.specify/extensions/drupal/scripts/bash/install-drupal.sh`
- **PowerShell**: `.specify/extensions/drupal/scripts/powershell/install-drupal.ps1`

## After Install

Drupal's web root is `web/`. Typical next steps:

```bash
# Browser install (no extra tools)
# Point your web server at web/ and open /core/install.php

# Or install Drush first, then use CLI:
composer require drush/drush
vendor/bin/drush -r web site:install --yes
vendor/bin/drush -r web user:login
```

Or use a local stack (DDEV, Lando, etc.) pointed at the `web/` directory.

## Safety

- Does **not** delete existing `.specify`, `.cursor`, `specs`, or `.git` content
- Refuses to run if `web/core` or `composer.json` already contains Drupal
- Requires network access for Composer package download
