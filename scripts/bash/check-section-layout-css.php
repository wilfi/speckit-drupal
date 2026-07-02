#!/usr/bin/env php
<?php

/**
 * @file
 * QR-CSS-004: Section card max-width parity across themed components.
 *
 * Usage: check-section-layout-css.php [THEME_DIR]
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

$selectors = $cssRules['section_max_width_selectors'] ?? [];
if ($selectors === []) {
  fwrite(STDOUT, "drupal: QR-CSS-004: no section_max_width_selectors configured — skip\n");
  exit(0);
}

if (!is_dir($themesPath)) {
  fwrite(STDERR, "drupal: QUALITY FAIL: QR-CSS-004: theme directory not found: $themesPath\n");
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

foreach ($selectors as $selector) {
  $selector = trim((string) $selector);
  if ($selector === '') {
    continue;
  }
  $escaped = preg_quote($selector, '/');
  $pattern = '/' . $escaped . '\s*\{[^}]*max-width\s*:\s*(1312px|var\(--max-width\))/s';
  if (preg_match($pattern, $css)) {
    fwrite(STDOUT, "drupal: QR-CSS-004: OK — $selector has max-width 1312px or --max-width\n");
    continue;
  }
  $violations[] = "QR-CSS-004: missing max-width: 1312px (or var(--max-width)) on $selector";
}

if ($violations !== []) {
  foreach ($violations as $violation) {
    fwrite(STDERR, "drupal: QUALITY FAIL: $violation\n");
  }
  exit(1);
}

exit(0);
