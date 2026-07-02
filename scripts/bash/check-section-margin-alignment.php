#!/usr/bin/env php
<?php

/**
 * @file
 * QR-CSS-006: Section card margin alignment across front-page components.
 *
 * Ensures primary section cards share margin: 0 auto and matching layout-container
 * gutters on the front page (explore, featured, grid, about, newsletter).
 *
 * Usage: check-section-margin-alignment.php [THEME_DIR]
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

$marginSelectors = $cssRules['section_margin_selectors'] ?? [];
$layoutRegions = $cssRules['front_page_layout_regions'] ?? [];
$frontTemplate = $cssRules['front_page_template'] ?? 'web/themes/custom/cooks_delight/templates/page--front.html.twig';

if ($marginSelectors === [] && $layoutRegions === []) {
  fwrite(STDOUT, "drupal: QR-CSS-006: no section margin rules configured — skip\n");
  exit(0);
}

$violations = [];

if ($marginSelectors !== []) {
  if (!is_dir($themesPath)) {
    fwrite(STDERR, "drupal: QUALITY FAIL: QR-CSS-006: theme directory not found: $themesPath\n");
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

  foreach ($marginSelectors as $selector) {
    $selector = trim((string) $selector);
    if ($selector === '') {
      continue;
    }
    $escaped = preg_quote($selector, '/');
    $blockPattern = '/[^{]*' . $escaped . '[^{]*\{[^}]*margin\s*:\s*([^;}+]+)/s';
    if (!preg_match($blockPattern, $css, $match)) {
      $violations[] = "QR-CSS-006: missing margin on $selector";
      continue;
    }
    $margin = trim(preg_replace('/\s+/', ' ', $match[1]));
    if (!preg_match('/\b0\s+auto\b/i', $margin)) {
      $violations[] = "QR-CSS-006: $selector margin is '$margin' (expected '0 auto')";
      continue;
    }
    fwrite(STDOUT, "drupal: QR-CSS-006: OK — $selector has margin: 0 auto\n");
  }
}

if ($layoutRegions !== []) {
  $templatePath = str_starts_with($frontTemplate, '/')
    ? $frontTemplate
    : $projectRoot . '/' . ltrim($frontTemplate, '/');
  if (!is_file($templatePath)) {
    $violations[] = "QR-CSS-006: front page template not found: $templatePath";
  }
  else {
    $twig = file_get_contents($templatePath);
    foreach ($layoutRegions as $region) {
      $region = trim((string) $region);
      if ($region === '') {
        continue;
      }
      $pattern = '/region--' . preg_quote($region, '/') . '[^>]*>[\s\S]*?<div[^>]*class="[^"]*\blayout-container/s';
      if (preg_match($pattern, $twig)) {
        fwrite(STDOUT, "drupal: QR-CSS-006: OK — region--$region wrapped in layout-container\n");
        continue;
      }
      $violations[] = "QR-CSS-006: region--$region missing layout-container wrapper in front page template";
    }
  }
}

if ($violations !== []) {
  foreach ($violations as $violation) {
    fwrite(STDERR, "drupal: QUALITY FAIL: $violation\n");
  }
  exit(1);
}

exit(0);
