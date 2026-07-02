<?php

/**
 * @file
 * Sync atomic_components from figma-regions.yml + catalog → figma-asset-manifest.yml.
 *
 * Also appends atomic screenshot sections to figma-design-checks.yml when configured.
 *
 * Usage: php sync-figma-atomic-manifest.php --feature=specs/<name> [--force]
 * Env: PROJECT_ROOT
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

function log_atomic(string $msg): void {
  fwrite(STDOUT, "drupal: $msg\n");
}

function load_yaml_file(string $path): array {
  if (!is_file($path)) {
    return [];
  }
  $parsed = Yaml::parseFile($path);
  return is_array($parsed) ? $parsed : [];
}

function dump_yaml_file(string $path, array $data): void {
  file_put_contents($path, Yaml::dump($data, 8, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK));
}

function region_by_slug(array $regions, string $slug): ?array {
  foreach ($regions as $region) {
    if (!is_array($region)) {
      continue;
    }
    if (($region['slug'] ?? '') === $slug) {
      return $region;
    }
  }
  return null;
}

/**
 * @param list<array<string, mixed>> $entries
 * @return list<array<string, mixed>>
 */
function resolve_atomic_entries(array $entries, array $regions): array {
  $resolved = [];
  foreach ($entries as $entry) {
    if (!is_array($entry)) {
      continue;
    }
    $slug = trim((string) ($entry['region_slug'] ?? ''));
    if ($slug !== '') {
      $region = region_by_slug($regions, $slug);
      if ($region !== null) {
        if (empty($entry['parent_frame_id'])) {
          $entry['parent_frame_id'] = $region['figma_node_id'] ?? '';
        }
        if (empty($entry['parent_frame_name'])) {
          $entry['parent_frame_name'] = $region['figma_label'] ?? $slug;
        }
      }
    }
    unset($entry['region_slug']);
    if (!empty($entry['children']) && is_array($entry['children'])) {
      $entry['children'] = resolve_atomic_entries($entry['children'], $regions);
    }
    $resolved[] = $entry;
  }
  return $resolved;
}

/**
 * @param list<array<string, mixed>> $entries
 * @return array<string, array<string, mixed>>
 */
function index_atomic_by_id(array $entries): array {
  $indexed = [];
  foreach ($entries as $entry) {
    if (!is_array($entry)) {
      continue;
    }
    $id = trim((string) ($entry['id'] ?? ''));
    if ($id === '') {
      continue;
    }
    $indexed[$id] = $entry;
    foreach ($entry['children'] ?? [] as $child) {
      if (!is_array($child)) {
        continue;
      }
      $childId = trim((string) ($child['id'] ?? ''));
      if ($childId !== '') {
        $indexed[$childId] = $child;
      }
    }
  }
  return $indexed;
}

/**
 * @param list<array<string, mixed>> $base
 * @param list<array<string, mixed>> $overlay
 * @return list<array<string, mixed>>
 */
function merge_atomic_lists(array $base, array $overlay): array {
  $indexed = index_atomic_by_id($base);
  foreach ($overlay as $entry) {
    if (!is_array($entry)) {
      continue;
    }
    $id = trim((string) ($entry['id'] ?? ''));
    if ($id === '') {
      continue;
    }
    if (isset($indexed[$id])) {
      $indexed[$id] = array_replace_recursive($indexed[$id], $entry);
    }
    else {
      $indexed[$id] = $entry;
    }
  }
  // Preserve top-level order: base ids first, then new overlay roots.
  $topLevel = [];
  $seen = [];
  foreach ($base as $entry) {
    $id = trim((string) ($entry['id'] ?? ''));
    if ($id !== '' && isset($indexed[$id])) {
      $topLevel[] = $indexed[$id];
      $seen[$id] = TRUE;
    }
  }
  foreach ($overlay as $entry) {
    $id = trim((string) ($entry['id'] ?? ''));
    if ($id !== '' && empty($seen[$id])) {
      $topLevel[] = $indexed[$id];
      $seen[$id] = TRUE;
    }
  }
  return $topLevel;
}

/**
 * Strip manifest-only fields; keep screenshot for design-checks pass separately.
 *
 * @param list<array<string, mixed>> $entries
 * @return list<array<string, mixed>>
 */
function manifest_atomic_entries(array $entries): array {
  $out = [];
  foreach ($entries as $entry) {
    if (!is_array($entry)) {
      continue;
    }
    $clean = $entry;
    unset($clean['screenshot'], $clean['region_slug']);
    if (!empty($clean['children']) && is_array($clean['children'])) {
      $clean['children'] = manifest_atomic_entries($clean['children']);
    }
    $out[] = $clean;
  }
  return $out;
}

/**
 * @param list<array<string, mixed>> $entries
 * @return list<array<string, mixed>>
 */
function collect_screenshot_sections(array $entries, array $regions): array {
  $sections = [];
  foreach ($entries as $entry) {
    if (!is_array($entry)) {
      continue;
    }
    $shot = $entry['screenshot'] ?? null;
    if (is_array($shot) && !empty($shot['selector'])) {
      $parentId = trim((string) ($entry['parent_frame_id'] ?? ''));
      if ($parentId === '' && !empty($entry['region_slug'])) {
        $region = region_by_slug($regions, (string) $entry['region_slug']);
        $parentId = trim((string) ($region['figma_node_id'] ?? ''));
      }
      $sections[] = [
        'name' => $shot['name'] ?? ($entry['id'] ?? 'atomic-component'),
        'selector' => $shot['selector'],
        'baseline' => $shot['baseline'] ?? (($shot['name'] ?? 'component') . '-1440.png'),
        'figma_node_id' => $shot['figma_node_id'] ?? $parentId,
        'parent_frame_id' => $shot['parent_frame_id'] ?? $parentId,
        'max_diff_percent' => $shot['max_diff_percent'] ?? 1,
        'optional_baseline' => !empty($shot['optional_baseline']),
      ];
    }
    if (!empty($entry['children']) && is_array($entry['children'])) {
      $sections = array_merge($sections, collect_screenshot_sections($entry['children'], $regions));
    }
  }
  return $sections;
}

/**
 * Walk atomic tree and link icon assets in manifest assets[].
 *
 * @param list<array<string, mixed>> $entries
 */
function sync_assets_from_atomic(array &$manifest, array $entries): void {
  $assets = $manifest['assets'] ?? [];
  if (!is_array($assets)) {
    $assets = [];
  }
  $byPath = [];
  $byNode = [];
  foreach ($assets as $i => $asset) {
    if (!is_array($asset)) {
      continue;
    }
    $path = trim((string) ($asset['theme_path'] ?? ''));
    $node = trim((string) ($asset['figma_node_id'] ?? ''));
    if ($path !== '') {
      $byPath[$path] = $i;
    }
    if ($node !== '') {
      $byNode[$node] = $i;
    }
  }

  $walk = static function (array $entry) use (&$walk, &$assets, &$byPath, &$byNode): void {
    $id = trim((string) ($entry['id'] ?? ''));
    $kind = trim((string) ($entry['kind'] ?? 'icon'));
    $themePath = trim((string) ($entry['theme_path'] ?? ''));
    $nodeId = trim((string) ($entry['figma_node_id'] ?? ''));
    $display = $entry['display'] ?? [];

    if ($kind === 'icon' && $themePath !== '' && $nodeId !== '') {
      $assetRow = [
        'figma_node_id' => $nodeId,
        'figma_name' => $id !== '' ? $id : basename($themePath, '.' . pathinfo($themePath, PATHINFO_EXTENSION)),
        'theme_path' => $themePath,
        'atomic_component_id' => $id,
      ];
      if (!empty($display['width']) && !empty($display['height'])) {
        $assetRow['display'] = [
          'width' => (int) $display['width'],
          'height' => (int) $display['height'],
        ];
      }
      if (isset($byNode[$nodeId])) {
        $assets[$byNode[$nodeId]] = array_replace($assets[$byNode[$nodeId]], $assetRow);
      }
      elseif (isset($byPath[$themePath])) {
        $assets[$byPath[$themePath]] = array_replace($assets[$byPath[$themePath]], $assetRow);
      }
      else {
        $assets[] = $assetRow;
      }
    }

    foreach ($entry['children'] ?? [] as $child) {
      if (is_array($child)) {
        $walk($child);
      }
    }
  };

  foreach ($entries as $entry) {
    if (is_array($entry)) {
      $walk($entry);
    }
  }

  $manifest['assets'] = array_values($assets);
}

/**
 * @param list<array<string, mixed>> $catalogDefaults
 * @param list<array<string, mixed>> $regions
 * @return list<array<string, mixed>>
 */
function catalog_entries_for_regions(array $catalogDefaults, array $regions): array {
  $entries = [];
  $slugs = [];
  foreach ($regions as $region) {
    if (is_array($region) && !empty($region['slug'])) {
      $slugs[(string) $region['slug']] = TRUE;
    }
  }
  foreach ($catalogDefaults as $slug => $items) {
    if (empty($slugs[$slug]) || !is_array($items)) {
      continue;
    }
    foreach ($items as $item) {
      if (is_array($item)) {
        $entries[] = $item;
      }
    }
  }
  return $entries;
}

function load_catalog(string $templatesDir, string $fileKey): array {
  $path = "$templatesDir/figma-atomic-components-catalog.yml";
  if (!is_file($path)) {
    return [];
  }
  $catalog = Yaml::parseFile($path) ?: [];
  $catalogKey = trim((string) ($catalog['file_key'] ?? ''));
  if ($catalogKey !== '' && $fileKey !== '' && $catalogKey !== $fileKey) {
    return [];
  }
  return is_array($catalog['defaults_by_region_slug'] ?? null)
    ? $catalog['defaults_by_region_slug']
    : [];
}

function merge_design_check_sections(string $checksPath, array $atomicSections, bool $force): bool {
  if ($atomicSections === [] || !is_file($checksPath)) {
    return FALSE;
  }
  $checks = Yaml::parseFile($checksPath) ?: [];
  $sections = $checks['figma']['screenshot']['sections'] ?? [];
  if (!is_array($sections)) {
    $sections = [];
  }
  $byName = [];
  foreach ($sections as $section) {
    if (is_array($section) && !empty($section['name'])) {
      $byName[(string) $section['name']] = $section;
    }
  }
  $changed = FALSE;
  foreach ($atomicSections as $section) {
    $name = (string) ($section['name'] ?? '');
    if ($name === '') {
      continue;
    }
    if (!isset($byName[$name]) || $force) {
      $byName[$name] = $section;
      $changed = TRUE;
    }
  }
  if (!$changed) {
    return FALSE;
  }
  $checks['figma']['screenshot']['sections'] = array_values($byName);
  dump_yaml_file($checksPath, $checks);
  return TRUE;
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

$regionsPath = "$featurePath/figma-regions.yml";
$manifestPath = "$featurePath/figma-asset-manifest.yml";
$checksPath = "$featurePath/figma-design-checks.yml";

$regionsManifest = load_yaml_file($regionsPath);
$regions = $regionsManifest['regions'] ?? [];
if ($regions === []) {
  log_atomic('No figma-regions.yml regions — skip atomic manifest sync');
  exit(0);
}

$fileKey = '';
$checks = load_yaml_file($checksPath);
$fileKey = trim((string) ($checks['figma']['file_key'] ?? ''));

$catalogDefaults = load_catalog($templatesDir, $fileKey);
$catalogEntries = catalog_entries_for_regions($catalogDefaults, $regions);
$featureEntries = $regionsManifest['atomic_components'] ?? [];
if (!is_array($featureEntries)) {
  $featureEntries = [];
}
$featureAtomicWasEmpty = $featureEntries === [];

$mergedRaw = merge_atomic_lists(
  resolve_atomic_entries($catalogEntries, $regions),
  resolve_atomic_entries($featureEntries, $regions)
);

if ($mergedRaw === []) {
  log_atomic('No atomic components matched — add atomic_components[] to figma-regions.yml or catalog');
  exit(0);
}

$manifest = load_yaml_file($manifestPath);
if ($manifest === [] && is_file("$templatesDir/figma-asset-manifest-template.yml")) {
  $manifest = load_yaml_file("$templatesDir/figma-asset-manifest-template.yml");
}

if (empty($manifest['theme'])) {
  $designContext = load_yaml_file("$featurePath/design-context.md");
  unset($designContext);
  $dcText = is_file("$featurePath/design-context.md") ? (string) file_get_contents("$featurePath/design-context.md") : '';
  if (preg_match('/\*\*Active theme[^*]*\*\*:\s*`([^`]+)`/', $dcText, $m)) {
    $manifest['theme'] = trim($m[1]);
  }
}

$existing = $manifest['atomic_components'] ?? [];
$manifest['atomic_components'] = manifest_atomic_entries(
  merge_atomic_lists(is_array($existing) ? $existing : [], $mergedRaw)
);

sync_assets_from_atomic($manifest, $manifest['atomic_components']);

dump_yaml_file($manifestPath, $manifest);
log_atomic('Synced figma-asset-manifest.yml atomic_components (' . count($manifest['atomic_components']) . ' root entries)');

if ($featureAtomicWasEmpty && $catalogEntries !== []) {
  $regionsManifest['atomic_components'] = $catalogEntries;
  dump_yaml_file($regionsPath, $regionsManifest);
  log_atomic('Materialized atomic_components into figma-regions.yml from catalog defaults');
}

$atomicSections = collect_screenshot_sections($mergedRaw, $regions);
if (merge_design_check_sections($checksPath, $atomicSections, $force)) {
  log_atomic('Synced atomic screenshot sections into figma-design-checks.yml');
}

exit(0);
