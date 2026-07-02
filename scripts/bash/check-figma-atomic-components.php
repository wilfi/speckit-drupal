#!/usr/bin/env php
<?php

/**
 * @file
 * QR-ASSET-006: Atomic Figma components mapped in figma-asset-manifest.yml.
 *
 * Validates child-node entries (icons, composite forms) under parent section frames.
 * Dev-mode workflow: parent_frame_id → drill children → record figma_node_id + dimensions.
 *
 * Usage: check-figma-atomic-components.php FEATURE_DIR [THEME_DIR]
 */

declare(strict_types=1);

$projectRoot = getenv('PROJECT_ROOT') ?: getcwd();
$featureDir = $argv[1] ?? '';
$themeDir = $argv[2] ?? 'web/themes/custom';

if ($featureDir === '') {
  fwrite(STDERR, "drupal: QUALITY FAIL: QR-ASSET-006: feature dir required\n");
  exit(1);
}

require $projectRoot . '/vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;

$manifestPath = "$projectRoot/$featureDir/figma-asset-manifest.yml";
if (!is_file($manifestPath)) {
  fwrite(STDERR, "drupal: QUALITY FAIL: QR-ASSET-006: missing $manifestPath\n");
  exit(1);
}

$manifest = Yaml::parseFile($manifestPath) ?: [];
$theme = trim((string) ($manifest['theme'] ?? ''));
$atomic = $manifest['atomic_components'] ?? null;

if ($theme === '') {
  fwrite(STDERR, "drupal: QUALITY FAIL: QR-ASSET-006: manifest theme is empty\n");
  exit(1);
}

if (!is_array($atomic) || $atomic === []) {
  fwrite(STDERR, "drupal: QUALITY FAIL: QR-ASSET-006: atomic_components[] is empty — map child Figma nodes (icons, composite forms) under section frames\n");
  exit(1);
}

$themeRoot = rtrim("$projectRoot/$themeDir/$theme", '/');
$pngSig = "\x89PNG\r\n\x1a\n";
$svgNeedle = '<svg';
$violations = [];
$seenNodeIds = [];
$sectionNodeIds = [];
$exportPendingPaths = [];
foreach ($manifest['assets'] ?? [] as $assetRow) {
  if (!is_array($assetRow)) {
    continue;
  }
  $tp = trim((string) ($assetRow['theme_path'] ?? ''));
  if ($tp !== '' && trim((string) ($assetRow['export_url'] ?? '')) !== '') {
    $exportPendingPaths[$tp] = TRUE;
  }
}

// Collect section-level node IDs from figma-design-checks.yml for cross-reference.
$designChecks = "$projectRoot/$featureDir/figma-design-checks.yml";
if (is_file($designChecks)) {
  $figma = Yaml::parseFile($designChecks)['figma'] ?? [];
  foreach ($figma['screenshot']['sections'] ?? [] as $section) {
    $nid = trim((string) ($section['figma_node_id'] ?? ''));
    if ($nid !== '') {
      $sectionNodeIds[$nid] = (string) ($section['name'] ?? 'section');
    }
  }
}

$validateAssetFile = static function (string $path, string $full) use (&$violations, $pngSig, $svgNeedle): void {
  if (!is_file($full)) {
    $violations[] = "missing theme file $path";
    return;
  }
  $head = (string) file_get_contents($full, FALSE, NULL, 0, 512);
  if (str_starts_with($head, $pngSig)) {
    return;
  }
  if (stripos($head, $svgNeedle) !== FALSE) {
    return;
  }
  $violations[] = "$path is not a valid PNG or SVG";
};

$walk = static function (array $entry, ?string $parentFrameId = NULL) use (
  &$walk,
  &$violations,
  &$seenNodeIds,
  $sectionNodeIds,
  $themeRoot,
  $exportPendingPaths,
  $validateAssetFile
): void {
  $id = trim((string) ($entry['id'] ?? ''));
  if ($id === '') {
    $violations[] = 'atomic component missing id';
    return;
  }

  $frameId = trim((string) ($entry['parent_frame_id'] ?? $parentFrameId ?? ''));
  $nodeId = trim((string) ($entry['figma_node_id'] ?? ''));
  $kind = trim((string) ($entry['kind'] ?? 'icon'));
  $themePath = trim((string) ($entry['theme_path'] ?? ''));
  $display = $entry['display'] ?? [];
  $pending = !empty($entry['node_pending']);

  if ($frameId === '' && $kind !== 'icon') {
    $violations[] = "$id: parent_frame_id required (Figma dev-mode: open section frame first)";
  }

  if ($nodeId !== '') {
    if (isset($seenNodeIds[$nodeId])) {
      $violations[] = "$id: duplicate figma_node_id $nodeId (already used by {$seenNodeIds[$nodeId]})";
    }
    else {
      $seenNodeIds[$nodeId] = $id;
    }

    // Child icon node must not reuse a section screenshot frame ID.
    if ($kind === 'icon' && isset($sectionNodeIds[$nodeId])) {
      $violations[] = "$id: figma_node_id $nodeId is section frame '{$sectionNodeIds[$nodeId]}' — use the child icon node, not the parent section";
    }

    if ($frameId !== '' && $nodeId === $frameId && $kind === 'icon') {
      $violations[] = "$id: figma_node_id must be a child node, not the parent frame $frameId";
    }
  }
  elseif (!$pending && $kind === 'icon') {
    $violations[] = "$id: figma_node_id required for kind=icon (or set node_pending: true until exported)";
  }

  if ($kind === 'icon' && $themePath !== '') {
    $full = "$themeRoot/$themePath";
    if (!is_file($full)) {
      if ($pending || isset($exportPendingPaths[$themePath])) {
        fwrite(STDOUT, "drupal: QR-ASSET-006: OK — $id icon pending export ($themePath)\n");
      }
      else {
        $violations[] = "$id: missing theme file $themePath";
      }
    }
    else {
      $validateAssetFile($themePath, $full);
      $w = (int) ($display['width'] ?? 0);
      $h = (int) ($display['height'] ?? 0);
      if ($w <= 0 || $h <= 0) {
        $violations[] = "$id: display width/height required for icons";
      }
      else {
        fwrite(STDOUT, "drupal: QR-ASSET-006: OK — $id icon {$w}×{$h} → $themePath\n");
      }
    }
  }

  if ($kind === 'composite_form') {
    $dims = $entry['dimensions'] ?? [];
    $w = (int) ($dims['width'] ?? 0);
    $h = (int) ($dims['height'] ?? 0);
    if ($w <= 0 || $h <= 0) {
      $violations[] = "$id: dimensions width/height required for composite_form";
    }
    $css = trim((string) ($entry['css_selector'] ?? ''));
    if ($css === '') {
      $violations[] = "$id: css_selector required for composite_form";
    }
    else {
      fwrite(STDOUT, "drupal: QR-ASSET-006: OK — $id composite form {$w}×{$h} → $css\n");
    }
  }

  foreach ($entry['children'] ?? [] as $child) {
    if (!is_array($child)) {
      continue;
    }
    $walk($child, $frameId !== '' ? $frameId : $parentFrameId);
  }
};

foreach ($atomic as $entry) {
  if (!is_array($entry)) {
    continue;
  }
  $walk($entry);
}

if ($violations !== []) {
  foreach ($violations as $v) {
    fwrite(STDERR, "drupal: QUALITY FAIL: QR-ASSET-006: $v\n");
  }
  exit(1);
}

fwrite(STDOUT, 'drupal: QR-ASSET-006: OK — ' . count($atomic) . " atomic component(s) validated\n");
exit(0);
