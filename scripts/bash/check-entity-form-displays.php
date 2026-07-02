<?php

/**
 * @file
 * QR-CONFIG-001 — bundle fields must be visible on default entity form displays.
 *
 * Usage: check-entity-form-displays.php [CONFIG_SYNC_DIR]
 */

declare(strict_types=1);

$projectRoot = getenv('PROJECT_ROOT') ?: dirname(__DIR__, 5);
require $projectRoot . '/vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;

$syncDir = $argv[1] ?? $projectRoot . '/config/sync';
if (!is_dir($syncDir)) {
  fwrite(STDERR, "QR-CONFIG-001: config sync dir not found: $syncDir\n");
  exit(1);
}

$failures = [];

foreach (glob("$syncDir/core.entity_form_display.node.*.default.yml") ?: [] as $formDisplayFile) {
  $bundle = preg_replace('/^.*\.node\.(.+)\.default\.yml$/', '$1', basename($formDisplayFile));
  if ($bundle === basename($formDisplayFile)) {
    continue;
  }

  $display = Yaml::parseFile($formDisplayFile);
  $hidden = array_keys($display['hidden'] ?? []);
  $content = array_keys($display['content'] ?? []);

  foreach (glob("$syncDir/field.field.node.$bundle.field_*.yml") ?: [] as $fieldFile) {
    $fieldName = preg_replace('/^.*\.node\.' . preg_quote($bundle, '/') . '\.(field_[^.]+)\.yml$/', '$1', basename($fieldFile));
    if (!str_starts_with($fieldName, 'field_')) {
      continue;
    }
    if (in_array($fieldName, $hidden, TRUE)) {
      $failures[] = "$bundle: $fieldName is hidden on form display but defined on bundle";
    }
    elseif (!in_array($fieldName, $content, TRUE)) {
      $failures[] = "$bundle: $fieldName missing from form display content region";
    }
  }

  if (is_file("$syncDir/field.field.node.$bundle.body.yml")) {
    if (in_array('body', $hidden, TRUE)) {
      $failures[] = "$bundle: body is hidden on form display";
    }
    elseif (!in_array('body', $content, TRUE)) {
      $failures[] = "$bundle: body missing from form display content region";
    }
  }
}

if ($failures !== []) {
  foreach ($failures as $message) {
    fwrite(STDERR, "QR-CONFIG-001: $message\n");
  }
  exit(1);
}

fwrite(STDOUT, "QR-CONFIG-001: OK — bundle fields visible on default form displays\n");
exit(0);
