#!/usr/bin/env php
<?php

/**
 * @file
 * QR-CSS-005/007/008/009/010/011/012/013/014: Figma component padding/gap/layout parity in theme CSS.
 *
 * Usage: check-component-padding-css.php [THEME_DIR]
 * Env: PROJECT_ROOT, QUALITY_JSON (merged quality_rules JSON)
 */

declare(strict_types=1);

$projectRoot = getenv('PROJECT_ROOT') ?: getcwd();
$themeDir = $argv[1] ?? 'web/themes/custom';
$themesPath = $projectRoot . '/' . ltrim($themeDir, '/');

$rules = json_decode(getenv('QUALITY_JSON') ?: '{}', true) ?: [];
$cssRules = $rules['css'] ?? [];
$enabled = $cssRules['enabled'] ?? true;

if ($enabled === false || $enabled === 'false' || $enabled === 0 || $enabled === '0') {
  exit(0);
}

$paddingRules = $cssRules['component_padding_rules'] ?? [];
$buttonRules = $cssRules['component_button_rules'] ?? [];
$allRules = [];
foreach ($paddingRules as $rule) {
  if (is_array($rule)) {
    $allRules[] = array_merge($rule, ['_prefix' => 'QR-CSS-005']);
  }
}
foreach ($buttonRules as $rule) {
  if (is_array($rule)) {
    $allRules[] = array_merge($rule, ['_prefix' => 'QR-CSS-007']);
  }
}
foreach ($cssRules['component_layout_rules'] ?? [] as $rule) {
  if (is_array($rule)) {
    $prefix = (string) ($rule['_prefix'] ?? 'QR-CSS-008');
    $allRules[] = array_merge($rule, ['_prefix' => $prefix]);
  }
}
foreach ($cssRules['component_spacing_rules'] ?? [] as $rule) {
  if (is_array($rule)) {
    $allRules[] = array_merge($rule, ['_prefix' => 'QR-CSS-009']);
  }
}

if ($allRules === []) {
  fwrite(STDOUT, "drupal: QR-CSS-005/007/012/013/014: no component style rules configured — skip\n");
  exit(0);
}

if (!is_dir($themesPath)) {
  fwrite(STDERR, "drupal: QUALITY FAIL: QR-CSS-005: theme directory not found: $themesPath\n");
  exit(1);
}

$css = '';
$iterator = new RecursiveIteratorIterator(
  new RecursiveDirectoryIterator($themesPath, FilesystemIterator::SKIP_DOTS)
);
foreach ($iterator as $file) {
  if ($file->isFile() && $file->getExtension() === 'css') {
    $css .= file_get_contents($file->getPathname()) . "\n";
  }
}

$css = (string) preg_replace('/\/\*.*?\*\//s', '', $css);
$violations = [];

foreach ($allRules as $rule) {
  if (!is_array($rule)) {
    continue;
  }
  $prefix = (string) ($rule['_prefix'] ?? 'QR-CSS-005');
  $selector = trim((string) ($rule['selector'] ?? ''));
  $property = trim((string) ($rule['property'] ?? ''));
  $value = trim((string) ($rule['value'] ?? ''));
  $pattern = trim((string) ($rule['pattern'] ?? ''));

  if ($selector === '' || $property === '') {
    continue;
  }

  $escaped = preg_quote($selector, '/');
  $propPattern = preg_quote($property, '/');
  // Allow grouped selectors; match exact property names (not border-width when checking width).
  $blockPattern = '/[^{]*' . $escaped . '[^{]*\{([^}]*)\}/s';
  $found = '';
  if (preg_match_all($blockPattern, $css, $blocks, PREG_SET_ORDER)) {
    foreach ($blocks as $block) {
      $declPattern = '/(?:^|;)\s*' . $propPattern . '\s*:\s*([^;]+)/';
      if (preg_match($declPattern, $block[1], $decl)) {
        $found = trim(preg_replace('/\s+/', ' ', $decl[1]));
        break;
      }
    }
  }

  if ($found === '') {
    $violations[] = "$prefix: missing $property on $selector";
    continue;
  }
  if ($pattern !== '') {
    if (!preg_match('/' . $pattern . '/i', $found)) {
      $violations[] = "$prefix: $selector $property is '$found' (expected pattern: $pattern)";
    }
    else {
      fwrite(STDOUT, "drupal: $prefix: OK — $selector $property matches $pattern\n");
    }
    continue;
  }

  if ($value !== '' && strcasecmp($found, $value) !== 0) {
    $violations[] = "$prefix: $selector $property is '$found' (expected '$value')";
    continue;
  }

  fwrite(STDOUT, "drupal: $prefix: OK — $selector has $property: $found\n");
}

if ($violations !== []) {
  foreach ($violations as $violation) {
    fwrite(STDERR, "drupal: QUALITY FAIL: $violation\n");
  }
  exit(1);
}

exit(0);
