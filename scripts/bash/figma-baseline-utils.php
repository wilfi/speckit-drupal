<?php

/**
 * @file
 * Shared helpers for Figma baseline source (figma vs live) path resolution.
 */

declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;

/**
 * @return array<string, mixed>
 */
function figma_load_checks(string $checksFile): array {
  if (!is_file($checksFile)) {
    return [];
  }
  $parsed = Yaml::parseFile($checksFile);
  return is_array($parsed['figma'] ?? null) ? $parsed['figma'] : [];
}

/**
 * @param array<string, mixed> $figma
 */
function figma_baseline_source(array $figma): string {
  $source = $figma['screenshot']['baseline_source'] ?? 'figma';
  if (!is_string($source) || $source === '') {
    return 'figma';
  }
  return strtolower($source);
}

/**
 * Reference PNG directory for QR-FIGMA-002 compare (relative to feature dir).
 *
 * @param array<string, mixed> $figma
 */
function figma_reference_baselines_subpath(array $figma): string {
  $base = trim((string) ($figma['screenshot']['baselines_dir'] ?? 'figma-baselines'), '/');
  $source = figma_baseline_source($figma);
  if ($source === 'live') {
    $sub = trim((string) ($figma['screenshot']['live_subdir'] ?? 'live'), '/');
    return $base . '/' . $sub;
  }
  $sub = trim((string) ($figma['screenshot']['figma_source_subdir'] ?? 'figma-source'), '/');
  return $base . '/' . $sub;
}

/**
 * Live-site regression capture directory (relative to feature dir).
 *
 * @param array<string, mixed> $figma
 */
function figma_live_baselines_subpath(array $figma): string {
  $base = trim((string) ($figma['screenshot']['baselines_dir'] ?? 'figma-baselines'), '/');
  $sub = trim((string) ($figma['screenshot']['live_subdir'] ?? 'live'), '/');
  return $base . '/' . $sub;
}

/**
 * Build Figma Images API export payload from figma-design-checks + figma-regions.
 *
 * @param array<string, mixed> $figma
 * @param array<string, mixed> $regionsManifest
 * @return array{file_key: string, out_dir: string, scale: float, items: list<array<string, mixed>>}
 */
function figma_build_source_export_payload(
  array $figma,
  string $outDirAbs,
  array $regionsManifest = [],
): array {
  $items = [];
  $seen = [];

  $add = static function (string $nodeId, string $baseline, string $name = '') use (&$items, &$seen): void {
    $nodeId = trim($nodeId);
    $baseline = trim($baseline);
    if ($nodeId === '' || $baseline === '') {
      return;
    }
    $key = $nodeId . '|' . $baseline;
    if (isset($seen[$key])) {
      return;
    }
    $seen[$key] = true;
    $items[] = [
      'name' => $name !== '' ? $name : pathinfo($baseline, PATHINFO_FILENAME),
      'figma_node_id' => $nodeId,
      'baseline' => $baseline,
    ];
  };

  $pageNode = (string) ($figma['node_id'] ?? '');
  foreach ($figma['screenshot']['pages'] ?? [] as $page) {
    if (!is_array($page)) {
      continue;
    }
    $add($page['figma_node_id'] ?? $pageNode, (string) ($page['baseline'] ?? ''), 'full-page');
  }

  foreach ($figma['screenshot']['sections'] ?? [] as $section) {
    if (!is_array($section)) {
      continue;
    }
    $add(
      (string) ($section['figma_node_id'] ?? ''),
      (string) ($section['baseline'] ?? ''),
      (string) ($section['name'] ?? 'section'),
    );
  }

  foreach ($regionsManifest['regions'] ?? [] as $region) {
    if (!is_array($region)) {
      continue;
    }
    $add(
      (string) ($region['figma_node_id'] ?? ''),
      (string) ($region['baseline'] ?? ''),
      (string) ($region['slug'] ?? 'region'),
    );
  }

  foreach ($figma['screenshot']['components']['items'] ?? [] as $comp) {
    if (!is_array($comp)) {
      continue;
    }
    $add(
      (string) ($comp['figma_node_id'] ?? ''),
      (string) ($comp['baseline'] ?? ''),
      (string) ($comp['name'] ?? 'component'),
    );
  }

  $scale = $figma['screenshot']['figma_export_scale'] ?? 1;
  if (!is_numeric($scale)) {
    $scale = 1;
  }

  return [
    'file_key' => (string) ($figma['file_key'] ?? ''),
    'out_dir' => $outDirAbs,
    'scale' => (float) $scale,
    'items' => $items,
  ];
}
