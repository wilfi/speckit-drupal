#!/usr/bin/env php
<?php

/**
 * @file
 * QR-ASSET-005: figma-asset-manifest.yml populated and theme files exist.
 *
 * Usage: check-figma-asset-manifest.php FEATURE_DIR [THEME_DIR]
 */

declare(strict_types=1);

$projectRoot = getenv('PROJECT_ROOT') ?: getcwd();
$featureDir = $argv[1] ?? '';
$themeDir = $argv[2] ?? 'web/themes/custom';

if ($featureDir === '') {
  fwrite(STDERR, "drupal: QUALITY FAIL: QR-ASSET-005: feature dir required\n");
  exit(1);
}

require $projectRoot . '/vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;

$manifestPath = "$projectRoot/$featureDir/figma-asset-manifest.yml";
if (!is_file($manifestPath)) {
  fwrite(STDERR, "drupal: QUALITY FAIL: QR-ASSET-005: missing $manifestPath\n");
  exit(1);
}

$manifest = Yaml::parseFile($manifestPath) ?: [];
$theme = trim((string) ($manifest['theme'] ?? ''));
$assets = $manifest['assets'] ?? null;

if ($theme === '') {
  fwrite(STDERR, "drupal: QUALITY FAIL: QR-ASSET-005: manifest theme is empty\n");
  exit(1);
}

if (!is_array($assets) || $assets === []) {
  fwrite(STDERR, "drupal: QUALITY FAIL: QR-ASSET-005: manifest assets[] is empty — populate during /speckit-plan\n");
  exit(1);
}

$themeRoot = rtrim("$projectRoot/$themeDir/$theme", '/');
$pngSig = "\x89PNG\r\n\x1a\n";
$violations = [];

foreach ($assets as $asset) {
  if (!is_array($asset)) {
    continue;
  }
  if (($asset['asset_role'] ?? '') === 'reference_only') {
    continue;
  }
  $path = trim((string) ($asset['theme_path'] ?? ''));
  if ($path === '') {
    $violations[] = 'entry missing theme_path';
    continue;
  }
  $full = "$themeRoot/$path";
  if (!is_file($full)) {
    $violations[] = "missing theme file $path (run export-figma-theme-assets.sh)";
    continue;
  }
  $head = (string) file_get_contents($full, FALSE, NULL, 0, 512);
  $isPng = str_starts_with($head, $pngSig);
  $isSvg = stripos($head, '<svg') !== FALSE;
  if (!$isPng && !$isSvg) {
    $violations[] = "$path is not a valid PNG or SVG (use download_assets, not get_design_context)";
  }
}

if ($violations !== []) {
  foreach ($violations as $v) {
    fwrite(STDERR, "drupal: QUALITY FAIL: QR-ASSET-005: $v\n");
  }
  exit(1);
}

fwrite(STDOUT, 'drupal: QR-ASSET-005: OK — ' . count($assets) . " manifest assets present in theme\n");
exit(0);
