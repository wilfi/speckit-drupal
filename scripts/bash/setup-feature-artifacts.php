<?php

/**
 * Scaffold / merge per-feature quality + Figma artifacts for Drupal Spec Kit.
 *
 * Usage: php setup-feature-artifacts.php [--feature=DIR] [--force] [--export-baselines]
 *
 * Env: PROJECT_ROOT (default: cwd)
 */

declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;

$projectRoot = getenv('PROJECT_ROOT') ?: getcwd();
$extDir = $projectRoot . '/.specify/extensions/drupal';
$templatesDir = $extDir . '/templates';

require $projectRoot . '/vendor/autoload.php';

$options = getopt('', ['feature:', 'force', 'export-baselines']);
$force = array_key_exists('force', $options);
$exportBaselines = array_key_exists('export-baselines', $options);
$featureArg = $options['feature'] ?? '';

function log_msg(string $msg): void {
  fwrite(STDOUT, "drupal: $msg\n");
}

function fail_msg(string $msg): never {
  fwrite(STDERR, "drupal: FAIL: $msg\n");
  exit(1);
}

function resolve_feature_dir(string $projectRoot, string $arg): ?string {
  if ($arg !== '' && is_dir("$projectRoot/$arg")) {
    return rtrim($arg, '/');
  }
  $featureJson = "$projectRoot/.specify/feature.json";
  if (is_file($featureJson)) {
    $data = json_decode((string) file_get_contents($featureJson), true);
    if (is_array($data) && !empty($data['feature_directory'])) {
      $dir = $data['feature_directory'];
      if (is_dir("$projectRoot/$dir")) {
        return rtrim($dir, '/');
      }
    }
  }
  $plans = glob("$projectRoot/specs/*/plan.md") ?: [];
  if ($plans === []) {
    return null;
  }
  usort($plans, static fn(string $a, string $b): int => filemtime($b) <=> filemtime($a));
  return dirname(str_replace($projectRoot . '/', '', $plans[0]));
}

function read_text(string $path): string {
  return is_file($path) ? (string) file_get_contents($path) : '';
}

function is_figma_feature(string $featureDir): bool {
  global $projectRoot;
  $base = "$projectRoot/$featureDir";
  if (is_file("$base/design-context.md")) {
    return true;
  }
  foreach (['spec.md', 'plan.md'] as $file) {
    $text = read_text("$base/$file");
    if ($text !== '' && preg_match('/figma\.com|UX & Design \(Figma\)|QR-FIGMA|Figma Design Parity/i', $text)) {
      return true;
    }
  }
  return false;
}

/**
 * @return array{file_key: string, node_id: string, theme: string}
 */
function parse_design_context(string $path): array {
  $out = ['file_key' => '', 'node_id' => '', 'theme' => ''];
  if (!is_file($path)) {
    return $out;
  }
  $text = read_text($path);
  if (preg_match('#figma\.com/design/([A-Za-z0-9]+)#', $text, $m)) {
    $out['file_key'] = $m[1];
  }
  if (preg_match('#node-id=(\d+)-(\d+)#', $text, $m)) {
    $out['node_id'] = $m[1] . ':' . $m[2];
  }
  elseif (preg_match('/Figma node[^`]*`([^`]+)`/', $text, $m)) {
    $out['node_id'] = trim($m[1]);
  }
  if (preg_match('/\*\*Active theme[^*]*\*\*:\s*`([^`]+)`/', $text, $m)) {
    $out['theme'] = trim($m[1]);
  }
  elseif (preg_match('/custom theme `([^`]+)`/', $text, $m)) {
    $out['theme'] = trim($m[1]);
  }
  return $out;
}

/** @return array<string, mixed> */
function figma_default_quality_checks(): array {
  return [
    'smoke' => [
      'icon_markers' => [
        [
          'id' => 'QR-SMOKE-008',
          'path' => '/',
          'container_class' => 'explore-section',
          'img_pattern' => 'category-[^"]+\\.png',
          'min_count' => 1,
          'width' => 40,
          'height' => 40,
        ],
        [
          'id' => 'QR-SMOKE-009',
          'path' => '/',
          'container_class' => 'featured-recipes__controls',
          'img_pattern' => 'arrow-(left|right)\\.png',
          'min_count' => 2,
          'width' => 40,
          'height' => 40,
        ],
      ],
      'content_image_scopes' => [
        [
          'id' => 'QR-SMOKE-010',
          'path' => '/',
          'parent_class' => 'recipe-grid',
          'container_class' => 'recipe-card__image',
          'img_src_must_match' => '/sites/default/files/',
          'img_src_must_not_match' => [
            'images/figma/grid',
            'figma-baselines',
          ],
          'min_count' => 1,
        ],
        [
          'id' => 'QR-SMOKE-010',
          'path' => '/',
          'parent_class' => 'featured-recipes',
          'container_class' => 'recipe-card__image',
          'img_src_must_match' => '/sites/default/files/',
          'img_src_must_not_match' => [
            'images/figma/grid',
            'figma-baselines',
          ],
          'min_count' => 1,
        ],
      ],
      'composite_forms' => [
        [
          'id' => 'QR-SMOKE-011',
          'path' => '/',
          'section_selector' => '.newsletter-section',
          'form_selector' => '.newsletter-section form',
          'requires_email' => TRUE,
          'requires_submit' => TRUE,
          'icon_asset_pattern' => 'icon-mail\\.(svg|png)',
          'icon_via' => 'any',
        ],
      ],
    ],
    'theme' => [
      'enabled' => TRUE,
      'webform_templates' => [
        'enabled' => TRUE,
        'required_overrides' => [
          'webform--newsletter-signup.html.twig',
        ],
      ],
    ],
    'css' => [
      'component_padding_rules' => [
        [
          'selector' => '.newsletter-section',
          'property' => 'min-height',
          'pattern' => '486px',
        ],
        [
          'selector' => '.newsletter-section__inner',
          'property' => 'padding',
          'pattern' => '64px\s+40px',
        ],
        [
          'selector' => '.newsletter-section__copy',
          'property' => 'gap',
          'value' => '12px',
        ],
        [
          'selector' => '.site-footer__bar',
          'property' => 'display',
          'value' => 'flex',
        ],
        [
          'selector' => '.site-footer__bar',
          'property' => 'justify-content',
          'value' => 'space-between',
        ],
      ],
      'component_layout_rules' => [
        [
          '_prefix' => 'QR-CSS-012',
          'selector' => '.recipe-grid',
          'property' => 'border',
          'value' => 'none',
        ],
        [
          '_prefix' => 'QR-CSS-012',
          'selector' => '.featured-recipes',
          'property' => 'border',
          'pattern' => '1px\s+solid',
        ],
        [
          '_prefix' => 'QR-CSS-013',
          'selector' => '.tag',
          'property' => 'width',
          'value' => 'fit-content',
        ],
        [
          '_prefix' => 'QR-CSS-013',
          'selector' => '.tag',
          'property' => 'padding',
          'pattern' => '4px\s+8px',
        ],
        [
          '_prefix' => 'QR-CSS-013',
          'selector' => '.tag',
          'property' => 'border-radius',
          'value' => '12px',
        ],
        [
          '_prefix' => 'QR-CSS-014',
          'selector' => '.newsletter-section',
          'property' => 'background-color',
          'pattern' => 'accent-coral|#ee6352',
        ],
        [
          '_prefix' => 'QR-CSS-014',
          'selector' => '.newsletter-section',
          'property' => 'overflow',
          'value' => 'hidden',
        ],
        [
          '_prefix' => 'QR-CSS-014',
          'selector' => '.newsletter-section',
          'property' => 'background-image',
          'pattern' => 'radial-gradient',
        ],
        [
          '_prefix' => 'QR-CSS-015',
          'selector' => '.newsletter-section form',
          'property' => 'display',
          'value' => 'flex',
        ],
        [
          '_prefix' => 'QR-CSS-015',
          'selector' => '.newsletter-section form',
          'property' => 'height',
          'pattern' => '50px',
        ],
        [
          '_prefix' => 'QR-CSS-015',
          'selector' => '.newsletter-section input[type="email"]',
          'property' => 'background',
          'pattern' => 'transparent',
        ],
      ],
      'composite_form_anti_patterns' => [
        [
          '_prefix' => 'QR-CSS-016',
          'selector' => '.newsletter-section .form-actions',
          'property' => 'position',
          'forbidden_pattern' => 'absolute',
          'message' => 'subscribe button must be flex sibling inside form pill',
        ],
        [
          '_prefix' => 'QR-CSS-016',
          'selector' => '.newsletter-section input[type="email"]',
          'property' => 'background',
          'forbidden_pattern' => 'surface|fffbf2|#fffbf2|var\\(--cd-color-surface\\)',
          'message' => 'email input must be transparent; pill background on form container',
        ],
        [
          '_prefix' => 'QR-CSS-016',
          'selector' => '.newsletter-section input[type="email"]',
          'property' => 'border-radius',
          'forbidden_pattern' => '24px|var\\(--cd-radius-button\\)',
          'message' => 'email input must not be its own pill',
        ],
      ],
    ],
  ];
}

/**
 * Merge Figma default quality checks when missing from feature quality-checks.yml.
 */
function merge_figma_quality_defaults(array $quality): array {
  $defaults = figma_default_quality_checks();
  $changed = false;

  if (empty($quality['smoke']['icon_markers'])) {
    $quality['smoke'] = array_merge($quality['smoke'] ?? [], [
      'icon_markers' => $defaults['smoke']['icon_markers'],
    ]);
    $changed = true;
    log_msg('Updated quality-checks.yml: smoke.icon_markers (QR-SMOKE-008/009)');
  }

  if (empty($quality['smoke']['content_image_scopes'])) {
    $quality['smoke'] = array_merge($quality['smoke'] ?? [], [
      'content_image_scopes' => $defaults['smoke']['content_image_scopes'],
    ]);
    $changed = true;
    log_msg('Updated quality-checks.yml: smoke.content_image_scopes (QR-SMOKE-010)');
  }

  if (empty($quality['smoke']['composite_forms'])) {
    $quality['smoke'] = array_merge($quality['smoke'] ?? [], [
      'composite_forms' => $defaults['smoke']['composite_forms'],
    ]);
    $changed = true;
    log_msg('Updated quality-checks.yml: smoke.composite_forms (QR-SMOKE-011)');
  }

  if (empty($quality['theme']['webform_templates'])) {
    $quality['theme'] = array_merge($quality['theme'] ?? $defaults['theme'], [
      'webform_templates' => $defaults['theme']['webform_templates'],
    ]);
    $changed = true;
    log_msg('Updated quality-checks.yml: theme.webform_templates (QR-THEME-003)');
  }

  if (empty($quality['theme'])) {
    $quality['theme'] = $defaults['theme'];
    $changed = true;
    log_msg('Updated quality-checks.yml: theme (QR-THEME-001)');
  }

  if (empty($quality['css']['component_padding_rules'])) {
    $quality['css'] = array_merge($quality['css'] ?? [], [
      'component_padding_rules' => $defaults['css']['component_padding_rules'],
    ]);
    $changed = true;
    log_msg('Updated quality-checks.yml: css.component_padding_rules (newsletter/footer QR-CSS-005)');
  }

  if (empty($quality['css']['component_layout_rules'])) {
    $quality['css'] = array_merge($quality['css'] ?? [], [
      'component_layout_rules' => $defaults['css']['component_layout_rules'],
    ]);
    $changed = true;
    log_msg('Updated quality-checks.yml: css.component_layout_rules (QR-CSS-012 section borders)');
  }

  if (empty($quality['css']['composite_form_anti_patterns'])) {
    $quality['css'] = array_merge($quality['css'] ?? [], [
      'composite_form_anti_patterns' => $defaults['css']['composite_form_anti_patterns'],
    ]);
    $changed = true;
    log_msg('Updated quality-checks.yml: css.composite_form_anti_patterns (QR-CSS-016)');
  }

  if (($quality['assets']['enabled'] ?? null) !== true) {
    $quality['assets'] = array_merge($quality['assets'] ?? [], [
      'enabled' => true,
      'use_figma_scopes' => true,
      'validate_formats' => true,
    ]);
    $changed = true;
    log_msg('Updated quality-checks.yml: assets.enabled=true, use_figma_scopes=true');
  }

  return [$quality, $changed];
}

function copy_template_if_missing(string $src, string $dest, bool $force): bool {
  if (is_file($dest) && !$force) {
    return false;
  }
  if (!is_file($src)) {
    fail_msg("Missing template: $src");
  }
  copy($src, $dest);
  return true;
}

function dump_yaml(string $path, array $data): void {
  file_put_contents($path, Yaml::dump($data, 6, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK));
}

/** @return list<string> */
function collect_required_baselines(array $figmaChecks): array {
  $baselines = [];
  $shot = $figmaChecks['figma']['screenshot'] ?? [];
  foreach ($shot['pages'] ?? [] as $page) {
    if (!empty($page['baseline'])) {
      $baselines[] = $page['baseline'];
    }
  }
  foreach ($shot['sections'] ?? [] as $section) {
    if (!empty($section['baseline'])) {
      $baselines[] = $section['baseline'];
    }
  }
  return array_values(array_unique($baselines));
}

$featureDir = resolve_feature_dir($projectRoot, $featureArg);
if ($featureDir === null) {
  fail_msg('No feature directory found. Pass --feature=specs/<name> or run /speckit-plan first.');
}

$featurePath = "$projectRoot/$featureDir";
$figmaFeature = is_figma_feature($featureDir);
$designContext = "$featurePath/design-context.md";
$parsed = parse_design_context($designContext);

log_msg("Feature artifacts: $featureDir" . ($figmaFeature ? ' (Figma)' : ''));

// --- quality-checks.yml ---
$qualityDest = "$featurePath/quality-checks.yml";
$qualityTemplate = "$templatesDir/quality-checks-template.yml";
if (!is_file($qualityDest)) {
  copy_template_if_missing($qualityTemplate, $qualityDest, true);
  log_msg("Created quality-checks.yml from template");
  if ($figmaFeature) {
    $quality = Yaml::parseFile($qualityDest) ?: [];
    [$quality, ] = merge_figma_quality_defaults($quality);
    dump_yaml($qualityDest, $quality);
  }
}
else {
  $quality = Yaml::parseFile($qualityDest) ?: [];
  $changed = false;
  if ($figmaFeature) {
    [$quality, $figmaQualityChanged] = merge_figma_quality_defaults($quality);
    $changed = $changed || $figmaQualityChanged;
  }
  if ($changed || ($force && is_file($qualityDest))) {
    if ($changed) {
      dump_yaml($qualityDest, $quality);
    }
  }
}

// --- Non-Figma: done ---
if (!$figmaFeature) {
  log_msg('Not a Figma feature — skipped figma-design-checks and baselines');
  exit(0);
}

// --- figma-design-checks.yml ---
$figmaDest = "$featurePath/figma-design-checks.yml";
$figmaTemplate = "$templatesDir/figma-design-checks-template.yml";
$createdFigma = copy_template_if_missing($figmaTemplate, $figmaDest, $force);
if ($createdFigma) {
  log_msg('Created figma-design-checks.yml from template');
}
$figmaChecks = Yaml::parseFile($figmaDest) ?: [];
$figmaChanged = false;
if ($parsed['file_key'] !== '' && empty($figmaChecks['figma']['file_key'])) {
  $figmaChecks['figma']['file_key'] = $parsed['file_key'];
  $figmaChanged = true;
}
if ($parsed['node_id'] !== '' && empty($figmaChecks['figma']['node_id'])) {
  $figmaChecks['figma']['node_id'] = $parsed['node_id'];
  $figmaChanged = true;
}
if ($figmaChanged || $createdFigma) {
  dump_yaml($figmaDest, $figmaChecks);
  if ($figmaChanged) {
    log_msg("Seeded figma file_key/node_id from design-context.md");
  }
}

// --- figma-asset-manifest.yml ---
$manifestDest = "$featurePath/figma-asset-manifest.yml";
$manifestTemplate = "$templatesDir/figma-asset-manifest-template.yml";
if (copy_template_if_missing($manifestTemplate, $manifestDest, $force)) {
  if ($parsed['theme'] !== '') {
    $manifest = Yaml::parseFile($manifestDest) ?: [];
    if (empty($manifest['theme'])) {
      $manifest['theme'] = $parsed['theme'];
      dump_yaml($manifestDest, $manifest);
    }
  }
  log_msg('Created figma-asset-manifest.yml from template');
}

// --- foundational-checklist.yml ---
$foundDest = "$featurePath/foundational-checklist.yml";
$foundTemplate = "$templatesDir/foundational-checklist-template.yml";
if (!is_file($foundDest)) {
  copy_template_if_missing($foundTemplate, $foundDest, true);
  log_msg('Created foundational-checklist.yml from template (fill contrib/modules from plan)');
}
$foundational = Yaml::parseFile($foundDest) ?: [];
$requiredBaselines = collect_required_baselines($figmaChecks);
$figmaBlock = [
  'enabled' => true,
  'baselines_dir' => $figmaChecks['figma']['screenshot']['baselines_dir'] ?? 'figma-baselines',
  'required_baselines' => $requiredBaselines,
  'asset_manifest' => 'figma-asset-manifest.yml',
];
if (($foundational['figma'] ?? []) !== $figmaBlock) {
  $foundational['figma'] = $figmaBlock;
  dump_yaml($foundDest, $foundational);
  log_msg('Updated foundational-checklist.yml: figma.enabled=true + required_baselines');
}

// --- figma-baselines/ directory ---
$baselinesDirName = $figmaBlock['baselines_dir'];
$baselinesPath = "$featurePath/$baselinesDirName";
if (!is_dir($baselinesPath)) {
  mkdir($baselinesPath, 0755, true);
  file_put_contents("$baselinesPath/.gitkeep", '');
  log_msg("Created $baselinesDirName/");
}
require "$extDir/scripts/bash/figma-baseline-utils.php";
$figmaForPaths = $figmaChecks['figma'] ?? [];
foreach ([figma_reference_baselines_subpath($figmaForPaths), figma_live_baselines_subpath($figmaForPaths)] as $sub) {
  $subPath = "$featurePath/$sub";
  if (!is_dir($subPath)) {
    mkdir($subPath, 0755, true);
    file_put_contents("$subPath/.gitkeep", '');
    log_msg("Created $sub/");
  }
}

// --- Sync selectors from figma-regions / design-context ---
$syncScript = "$extDir/scripts/bash/sync-figma-checks-from-design.php";
if (is_file($syncScript)) {
  passthru('php ' . escapeshellarg($syncScript) . ' --feature=' . escapeshellarg($featureDir) . ($force ? ' --force' : ''), $syncCode);
  if ($syncCode !== 0) {
    fail_msg('sync-figma-checks-from-design.php failed');
  }
  $figmaChecks = Yaml::parseFile($figmaDest) ?: [];
  $requiredBaselines = collect_required_baselines($figmaChecks);
  $figmaBlock['required_baselines'] = $requiredBaselines;
  $foundational['figma'] = $figmaBlock;
  dump_yaml($foundDest, $foundational);
}

// --- Atomic components → figma-asset-manifest.yml (QR-ASSET-006) ---
$atomicSyncScript = "$extDir/scripts/bash/sync-figma-atomic-manifest.php";
if (is_file($atomicSyncScript)) {
  passthru('php ' . escapeshellarg($atomicSyncScript) . ' --feature=' . escapeshellarg($featureDir) . ($force ? ' --force' : ''), $atomicCode);
  if ($atomicCode !== 0) {
    fail_msg('sync-figma-atomic-manifest.php failed');
  }
}

// --- Figma source baselines (QR-FIGMA-002 reference) ---
$exportFigmaSource = "$extDir/scripts/bash/export-figma-source-baselines.sh";
if (is_file($exportFigmaSource)) {
  log_msg('Exporting Figma source baselines (FIGMA_ACCESS_TOKEN or committed PNGs)...');
  passthru('bash ' . escapeshellarg($exportFigmaSource) . ' ' . escapeshellarg($featureDir), $sourceCode);
  if ($sourceCode !== 0) {
    log_msg('WARN: Figma source export incomplete — set FIGMA_ACCESS_TOKEN or add PNGs to figma-baselines/figma-source/');
  }
}

// --- Optional live baseline export ---
if ($exportBaselines) {
  $tryExport = "$extDir/scripts/bash/try-export-figma-baselines.sh";
  if (!is_file($tryExport)) {
    fail_msg("try-export script missing: $tryExport");
  }
  log_msg('Exporting Figma baselines (requires DDEV + themed site)...');
  passthru('bash ' . escapeshellarg($tryExport) . ' --when=after_theme_story --require ' . escapeshellarg($featureDir), $code);
  if ($code !== 0) {
    fail_msg('try-export-figma-baselines.sh failed');
  }
}

log_msg("Feature artifacts ready: $featureDir");
log_msg('Populate figma-asset-manifest.yml assets[] during plan (download_assets) — atomic_components[] auto-sync from figma-regions.yml + catalog via sync-figma-atomic-manifest.php');
log_msg('Run export-figma-theme-assets.sh + export-figma-source-baselines.sh; verify-figma-section.sh per story; figma-fix-loop for failures');
