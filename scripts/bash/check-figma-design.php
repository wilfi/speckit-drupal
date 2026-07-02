#!/usr/bin/env php
<?php

/**
 * @file
 * QR-FIGMA-001: Figma design parity checks for themed pages.
 *
 * Usage: check-figma-design.php [HTML_FILE] [FEATURE_DIR]
 * Env: PROJECT_ROOT, QUALITY_JSON (optional — reads figma-design-checks.yml from feature dir)
 */

declare(strict_types=1);

$projectRoot = getenv('PROJECT_ROOT') ?: getcwd();
$htmlFile = $argv[1] ?? '';
$featureDir = $argv[2] ?? '';

require $projectRoot . '/vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;

function load_figma_config(string $projectRoot, string $featureDir): array {
  if ($featureDir !== '') {
    $path = str_starts_with($featureDir, '/')
      ? $featureDir . '/figma-design-checks.yml'
      : $projectRoot . '/' . ltrim($featureDir, '/') . '/figma-design-checks.yml';
    if (is_file($path)) {
      $parsed = Yaml::parseFile($path);
      return is_array($parsed['figma'] ?? null) ? $parsed['figma'] : [];
    }
  }
  $json = json_decode(getenv('QUALITY_JSON') ?: '{}', true) ?: [];
  return is_array($json['figma'] ?? null) ? $json['figma'] : [];
}

function scan_theme_css(string $themesPath): string {
  if (!is_dir($themesPath)) {
    return '';
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
  return $css;
}

$config = load_figma_config($projectRoot, $featureDir);
$enabled = $config['enabled'] ?? true;
if ($enabled === false || $enabled === 'false') {
  exit(0);
}

$violations = [];
$themeDir = $config['theme_dir'] ?? 'web/themes/custom/cooks_delight';
$themeCss = scan_theme_css($projectRoot . '/' . ltrim($themeDir, '/'));

if ($htmlFile === '' || !is_file($htmlFile)) {
  fwrite(STDERR, "drupal: QUALITY FAIL: QR-FIGMA-001: HTML file required\n");
  exit(1);
}

$html = file_get_contents($htmlFile);
$pages = $config['pages'] ?? [];

foreach ($pages as $page) {
  if (!is_array($page)) {
    continue;
  }
  $path = $page['path'] ?? '/';

  foreach ($page['must_contain'] ?? [] as $text) {
    if (!str_contains($html, $text)) {
      $violations[] = "QR-FIGMA-001: missing copy '$text' on $path";
    }
    else {
      echo "drupal: QR-FIGMA-001: OK — copy '$text' present on $path\n";
    }
  }

  foreach ($page['must_have_classes'] ?? [] as $class) {
    if (!preg_match('/\b' . preg_quote($class, '/') . '\b/', $html)) {
      $violations[] = "QR-FIGMA-001: missing class '$class' on $path";
    }
    else {
      echo "drupal: QR-FIGMA-001: OK — class '$class' present on $path\n";
    }
  }

  foreach ($page['css_selectors'] ?? [] as $selector) {
    $needle = trim($selector);
    if ($needle === '') {
      continue;
    }
  // Normalize: check class name appears in theme CSS if selector is a class rule.
    if (str_contains($themeCss, $needle) || preg_match('/' . preg_quote(ltrim($needle, '.'), '/') . '/', $themeCss)) {
      echo "drupal: QR-FIGMA-001: OK — CSS rule '$needle' found in theme\n";
    }
    else {
      $violations[] = "QR-FIGMA-001: theme CSS missing selector '$needle'";
    }
  }
}

foreach ($violations as $v) {
  fwrite(STDERR, "drupal: QUALITY FAIL: $v\n");
}

exit($violations === [] ? 0 : 1);
