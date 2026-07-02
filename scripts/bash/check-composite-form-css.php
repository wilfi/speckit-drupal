#!/usr/bin/env php
<?php

/**
 * @file
 * QR-CSS-015 / QR-CSS-016: Composite form CSS — container-first, no CMS primitive styling.
 *
 * Usage: check-composite-form-css.php [THEME_DIR]
 * Env: PROJECT_ROOT, QUALITY_JSON
 */

declare(strict_types=1);

$projectRoot = getenv('PROJECT_ROOT') ?: getcwd();
$themeBase = $argv[1] ?? 'web/themes/custom';

$rules = json_decode(getenv('QUALITY_JSON') ?: '{}', true) ?: [];
$cssRules = $rules['css'] ?? [];
$enabled = $cssRules['enabled'] ?? true;

if ($enabled === false || $enabled === 'false' || $enabled === 0 || $enabled === '0') {
  exit(0);
}

$antiPatterns = $cssRules['composite_form_anti_patterns'] ?? [];
$layoutRules = $cssRules['component_layout_rules'] ?? [];

if ($antiPatterns === [] && $layoutRules === []) {
  exit(0);
}

$cssThemeDir = $cssRules['theme_dir'] ?? $themeBase;
$themeName = trim((string) ($rules['theme']['theme_name'] ?? ''));
$themesPath = $projectRoot . '/' . ltrim($cssThemeDir, '/');

if ($themeName !== '') {
  $themeRoots = ["$themesPath/$themeName"];
}
else {
  $themeRoots = [];
  if (is_dir($themesPath)) {
    foreach (scandir($themesPath) ?: [] as $entry) {
      if ($entry === '.' || $entry === '..') {
        continue;
      }
      $candidate = "$themesPath/$entry";
      if (is_dir($candidate)) {
        $themeRoots[] = $candidate;
      }
    }
  }
}

$css = '';
foreach ($themeRoots as $root) {
  $files = glob("$root/css/**/*.css") ?: [];
  foreach ($files as $file) {
    $css .= (string) file_get_contents($file) . "\n";
  }
}

$violations = [];

foreach ($antiPatterns as $rule) {
  if (!is_array($rule)) {
    continue;
  }
  $prefix = trim((string) ($rule['_prefix'] ?? 'QR-CSS-016'));
  $selector = trim((string) ($rule['selector'] ?? ''));
  $property = trim((string) ($rule['property'] ?? ''));
  $forbidden = trim((string) ($rule['forbidden_pattern'] ?? ''));
  $message = trim((string) ($rule['message'] ?? ''));

  if ($selector === '' || $property === '' || $forbidden === '') {
    continue;
  }

  $escaped = preg_quote($selector, '/');
  $propPattern = preg_quote($property, '/');
  $blockPattern = '/[^{]*' . $escaped . '[^{]*\{([^}]*)\}/s';
  if (!preg_match_all($blockPattern, $css, $blocks, PREG_SET_ORDER)) {
    continue;
  }

  foreach ($blocks as $block) {
    $declPattern = '/(?:^|;)\s*' . $propPattern . '\s*:\s*([^;]+)/';
    if (preg_match($declPattern, $block[1], $decl)) {
      $found = trim(preg_replace('/\s+/', ' ', $decl[1]));
      if (preg_match('/' . $forbidden . '/i', $found)) {
        $violations[] = "$prefix: $selector $property is '$found' — " . ($message ?: "forbidden pattern $forbidden");
      }
      else {
        fwrite(STDOUT, "drupal: $prefix: OK — $selector $property avoids forbidden pattern\n");
      }
    }
  }
}

// Positive layout rules with _prefix QR-CSS-015 already run in check-component-padding-css.php;
// re-run subset here only when explicitly tagged composite_form in rule.
foreach ($layoutRules as $rule) {
  if (!is_array($rule) || empty($rule['composite_form'])) {
    continue;
  }
  $prefix = trim((string) ($rule['_prefix'] ?? 'QR-CSS-015'));
  $selector = trim((string) ($rule['selector'] ?? ''));
  $property = trim((string) ($rule['property'] ?? ''));
  $value = trim((string) ($rule['value'] ?? ''));
  $pattern = trim((string) ($rule['pattern'] ?? ''));

  if ($selector === '' || $property === '') {
    continue;
  }

  $escaped = preg_quote($selector, '/');
  $propPattern = preg_quote($property, '/');
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
    $violations[] = "$prefix: missing $property on $selector (composite form container)";
    continue;
  }
  if ($pattern !== '') {
    if (!preg_match('/' . $pattern . '/i', $found)) {
      $violations[] = "$prefix: $selector $property is '$found' (expected pattern: $pattern)";
    }
    else {
      fwrite(STDOUT, "drupal: $prefix: OK — $selector $property matches $pattern\n");
    }
  }
  elseif ($value !== '' && strcasecmp($found, $value) !== 0) {
    $violations[] = "$prefix: $selector $property is '$found' (expected '$value')";
  }
}

if ($violations !== []) {
  foreach ($violations as $v) {
    fwrite(STDERR, "drupal: QUALITY FAIL: $v\n");
  }
  exit(1);
}

fwrite(STDOUT, "drupal: QR-CSS-015/016: OK — composite form CSS checks passed\n");
exit(0);
