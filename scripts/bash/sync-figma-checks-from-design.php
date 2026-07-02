<?php

/**
 * Sync figma-design-checks.yml + quality-checks.yml from figma-regions.yml (or design-context.md).
 *
 * Usage: php sync-figma-checks-from-design.php --feature=specs/<name> [--force]
 */

declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;

$projectRoot = getenv('PROJECT_ROOT') ?: getcwd();
$extDir = $projectRoot . '/.specify/extensions/drupal';
$templatesDir = $extDir . '/templates';

require $projectRoot . '/vendor/autoload.php';

$options = getopt('', ['feature:', 'force']);
$force = array_key_exists('force', $options);
$featureArg = $options['feature'] ?? '';

function log_sync(string $msg): void {
  fwrite(STDOUT, "drupal: $msg\n");
}

function load_drupal_config(string $projectRoot): array {
  $path = "$projectRoot/.specify/extensions/drupal/drupal-config.yml";
  if (!is_file($path)) {
    return [];
  }
  return Yaml::parseFile($path) ?: [];
}

function slugify(string $text): string {
  $text = strtolower(trim($text));
  $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? $text;
  return trim($text, '-') ?: 'section';
}

function default_selector(string $slug, array $figmaCfg): string {
  $pattern = $figmaCfg['region_selector_pattern'] ?? '.region--{slug}';
  return str_replace('{slug}', $slug, $pattern);
}

function baseline_name(string $slug, array $figmaCfg): string {
  $suffix = $figmaCfg['section_baseline_suffix'] ?? '-1440.png';
  return $slug . $suffix;
}

/**
 * Parse Layout & Regions table from design-context.md.
 *
 * @return array{regions: list<array<string, mixed>>, pages: list<array<string, mixed>>}
 */
function parse_regions_from_design_context(string $path, array $figmaCfg): array {
  if (!is_file($path)) {
    return ['regions' => [], 'pages' => []];
  }
  $text = (string) file_get_contents($path);
  $regions = [];
  if (!preg_match('/## Layout & Regions\s*\n([\s\S]*?)(?:\n## |\z)/', $text, $section)) {
    return ['regions' => [], 'pages' => []];
  }
  $body = $section[1];
  foreach (explode("\n", $body) as $line) {
    if (!preg_match('/^\|\s*(.+?)\s*\|\s*(.+?)\s*\|/', $line, $m)) {
      continue;
    }
    $label = trim($m[1]);
    $drupal = trim($m[2]);
    if ($label === 'Region (Figma)' || str_starts_with($label, '---') || $label === 'Region') {
      continue;
    }
    $figmaNode = '';
    if (preg_match('/\(`([^`]+)`\)/', $label, $nm)) {
      $figmaNode = $nm[1];
    }
    $figmaLabel = preg_replace('/\s*\(`[^`]+`\)\s*/', '', $label) ?? $label;
    $drupalRegion = preg_replace('/[`]/', '', $drupal);
    $drupalRegion = trim(explode('/', $drupalRegion)[0]);
    $slug = slugify($drupalRegion ?: $figmaLabel);
    $regions[] = [
      'slug' => $slug,
      'figma_node_id' => $figmaNode,
      'figma_label' => trim($figmaLabel),
      'drupal_region' => trim($drupalRegion),
      'selector' => default_selector($slug, $figmaCfg),
      'baseline' => baseline_name($slug, $figmaCfg),
      'max_diff_percent' => 1,
    ];
  }
  return [
    'regions' => $regions,
    'pages' => [['path' => '/', 'must_contain' => [], 'smoke_markers' => []]],
  ];
}

function load_regions_manifest(string $featurePath, string $templatesDir, array $figmaCfg, bool $force): array {
  $regionsFile = "$featurePath/figma-regions.yml";
  $designContext = "$featurePath/design-context.md";

  if (is_file($regionsFile)) {
    $data = Yaml::parseFile($regionsFile) ?: [];
    if (!empty($data['regions'])) {
      return $data;
    }
  }

  $inferred = parse_regions_from_design_context($designContext, $figmaCfg);
  if ($inferred['regions'] !== []) {
    if (!is_file($regionsFile) || $force) {
      $out = [
        'viewport' => ['width' => 1440, 'height' => 900],
        'pages' => $inferred['pages'],
        'components' => [],
        'regions' => $inferred['regions'],
      ];
      file_put_contents($regionsFile, Yaml::dump($out, 6, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK));
      log_sync('Generated figma-regions.yml from design-context.md Layout table');
      return $out;
    }
    return Yaml::parseFile($regionsFile) ?: $inferred;
  }

  if (!is_file($regionsFile)) {
    $tpl = "$templatesDir/figma-regions-template.yml";
    if (is_file($tpl)) {
      copy($tpl, $regionsFile);
      log_sync('Created figma-regions.yml from template (fill regions or re-run figma-design)');
    }
  }
  return Yaml::parseFile($regionsFile) ?: [];
}

function merge_checks(array $figmaChecks, array $regionsManifest, array $figmaCfg): array {
  $regions = $regionsManifest['regions'] ?? [];
  $sections = [];
  $mustContain = [];
  $mustHaveClasses = [];

  $shotDefaults = [
    'baseline_source' => $figmaCfg['baseline_source'] ?? 'figma',
    'figma_source_subdir' => $figmaCfg['figma_source_subdir'] ?? 'figma-source',
    'live_subdir' => $figmaCfg['live_subdir'] ?? 'live',
    'figma_export_scale' => $figmaCfg['figma_export_scale'] ?? 1,
  ];
  $figmaChecks['figma']['screenshot'] = array_merge(
    $shotDefaults,
    $figmaChecks['figma']['screenshot'] ?? []
  );

  foreach ($regions as $region) {
    $slug = $region['slug'] ?? slugify($region['drupal_region'] ?? $region['figma_label'] ?? 'section');
    $selector = $region['selector'] ?? default_selector($slug, $figmaCfg);
    $baseline = $region['baseline'] ?? baseline_name($slug, $figmaCfg);
    $sections[] = [
      'name' => $slug,
      'selector' => $selector,
      'baseline' => $baseline,
      'figma_node_id' => $region['figma_node_id'] ?? '',
      'max_diff_percent' => $region['max_diff_percent'] ?? 1,
    ];
    foreach ($region['smoke_must_contain'] ?? [] as $marker) {
      $mustContain[] = $marker;
    }
    foreach ($region['must_have_classes'] ?? [] as $cls) {
      $mustHaveClasses[] = $cls;
    }
  }

  if ($sections !== []) {
    $figmaChecks['figma']['screenshot']['sections'] = $sections;
  }

  $pages = $regionsManifest['pages'] ?? [];
  if ($pages !== []) {
    $pageCfg = $pages[0];
    $path = $pageCfg['path'] ?? '/';
    $pageMust = array_values(array_unique(array_merge(
      $pageCfg['must_contain'] ?? [],
      $mustContain,
      ...array_map(static fn(array $r): array => $r['smoke_must_contain'] ?? [], $regions)
    )));
    $pageClasses = array_values(array_unique(array_merge(
      $pageCfg['must_have_classes'] ?? [],
      $mustHaveClasses
    )));

    $figmaChecks['figma']['pages'] = [[
      'path' => $path,
      'must_contain' => $pageMust,
      'must_have_classes' => $pageClasses,
      'css_selectors' => array_values(array_unique(array_map(
        static fn(array $s): string => $s['selector'],
        $sections
      ))),
    ]];

    if (empty($figmaChecks['figma']['screenshot']['pages'])) {
      $figmaChecks['figma']['screenshot']['pages'] = [[
        'path' => $path,
        'baseline' => $pageCfg['baseline'] ?? 'front-full-1440.png',
        'figma_node_id' => $pageCfg['figma_node_id'] ?? ($figmaChecks['figma']['node_id'] ?? ''),
      ]];
    }
    else {
      $pagesShot = $figmaChecks['figma']['screenshot']['pages'];
      if (!empty($pagesShot[0]) && empty($pagesShot[0]['figma_node_id'])) {
        $pagesShot[0]['figma_node_id'] = $pageCfg['figma_node_id']
          ?? ($figmaChecks['figma']['node_id'] ?? '');
        $figmaChecks['figma']['screenshot']['pages'] = $pagesShot;
      }
    }
  }

  $viewport = $regionsManifest['viewport'] ?? [];
  if ($viewport !== []) {
    $figmaChecks['figma']['screenshot']['viewport'] = array_merge(
      $figmaChecks['figma']['screenshot']['viewport'] ?? ['width' => 1440, 'height' => 900],
      $viewport
    );
  }

  $components = $regionsManifest['components'] ?? [];
  if ($components !== []) {
    $items = [];
    foreach ($components as $comp) {
      $items[] = [
        'name' => $comp['slug'] ?? $comp['name'] ?? 'component',
        'figma_node_id' => $comp['figma_node_id'] ?? '',
        'selector' => $comp['selector'] ?? '',
        'baseline' => $comp['baseline'] ?? '',
        'max_diff_percent' => $comp['max_diff_percent'] ?? 1,
      ];
    }
    $figmaChecks['figma']['screenshot']['components']['enabled'] = true;
    $figmaChecks['figma']['screenshot']['components']['items'] = $items;
  }

  return $figmaChecks;
}

function merge_quality_smoke(string $qualityPath, array $regionsManifest): void {
  if (!is_file($qualityPath)) {
    return;
  }
  $quality = Yaml::parseFile($qualityPath) ?: [];
  $pages = $regionsManifest['pages'] ?? [];
  $regions = $regionsManifest['regions'] ?? [];
  $markers = [];
  foreach ($pages as $p) {
    foreach ($p['smoke_markers'] ?? $p['must_contain'] ?? [] as $m) {
      $markers[] = $m;
    }
  }
  foreach ($regions as $r) {
    foreach ($r['smoke_must_contain'] ?? [] as $m) {
      $markers[] = $m;
    }
  }
  $markers = array_values(array_unique(array_filter($markers)));
  if ($markers === []) {
    return;
  }
  $path = $pages[0]['path'] ?? '/';
  $smokePages = $quality['smoke']['pages'] ?? [];
  $found = false;
  foreach ($smokePages as &$sp) {
    if (($sp['path'] ?? '/') === $path) {
      $existing = $sp['must_contain'] ?? [];
      $sp['must_contain'] = array_values(array_unique(array_merge($existing, $markers)));
      $found = true;
    }
  }
  unset($sp);
  if (!$found) {
    $smokePages[] = ['path' => $path, 'must_contain' => $markers];
  }
  $quality['smoke']['pages'] = $smokePages;
  file_put_contents($qualityPath, Yaml::dump($quality, 6, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK));
}

// --- main ---
if ($featureArg === '') {
  fwrite(STDERR, "drupal: FAIL: pass --feature=specs/<name>\n");
  exit(1);
}

$featurePath = "$projectRoot/$featureArg";
if (!is_dir($featurePath)) {
  fwrite(STDERR, "drupal: FAIL: feature dir not found: $featureArg\n");
  exit(1);
}

$drupalCfg = load_drupal_config($projectRoot);
$figmaCfg = $drupalCfg['figma'] ?? [];

$regionsManifest = load_regions_manifest($featurePath, $templatesDir, $figmaCfg, $force);
$checksPath = "$featurePath/figma-design-checks.yml";
if (!is_file($checksPath)) {
  fwrite(STDERR, "drupal: FAIL: missing figma-design-checks.yml — run setup-feature-artifacts first\n");
  exit(1);
}

$figmaChecks = Yaml::parseFile($checksPath) ?: [];
$merged = merge_checks($figmaChecks, $regionsManifest, $figmaCfg);
file_put_contents($checksPath, Yaml::dump($merged, 6, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK));
log_sync('Synced figma-design-checks.yml from figma-regions / design-context');

merge_quality_smoke("$featurePath/quality-checks.yml", $regionsManifest);
log_sync('Merged smoke markers into quality-checks.yml');

exit(0);
