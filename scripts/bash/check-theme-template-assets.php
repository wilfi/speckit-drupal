#!/usr/bin/env php
<?php

/**
 * @file
 * QR-THEME-001: Forbid Figma baseline crops and figma_image overrides in theme templates.
 *
 * Usage: check-theme-template-assets.php [THEME_DIR]
 * Env: PROJECT_ROOT, QUALITY_JSON (merged quality_rules JSON)
 */

declare(strict_types=1);

$projectRoot = getenv('PROJECT_ROOT') ?: getcwd();
$themeBase = $argv[1] ?? 'web/themes/custom';

$rules = json_decode(getenv('QUALITY_JSON') ?: '{}', true) ?: [];
$themeRules = $rules['theme'] ?? [];
$enabled = $themeRules['enabled'] ?? true;

if ($enabled === false || $enabled === 'false' || $enabled === 0 || $enabled === '0') {
  exit(0);
}

$cssThemeDir = $rules['css']['theme_dir'] ?? $themeBase;
$themeName = trim((string) ($themeRules['theme_name'] ?? ''));
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
      if (is_dir($candidate) && (is_file("$candidate/$entry.info.yml") || is_file("$candidate/$entry.theme"))) {
        $themeRoots[] = $candidate;
      }
    }
  }
}

$forbiddenPatterns = $themeRules['forbidden_patterns'] ?? [
  'figma-baselines',
  'images/figma/grid',
  'figma/grid/',
  'newsletter-bg.png',
];

$nodeForbiddenTokens = $themeRules['node_template_forbidden'] ?? [
  'figma_image',
  'images/figma/grid',
  'figma-baselines',
];

$requiredFieldRenders = $themeRules['require_field_render'] ?? [
  [
    'template_glob' => 'node--*-teaser.html.twig',
    'field' => 'content.field_image',
  ],
  [
    'template_glob' => 'node--*-featured.html.twig',
    'field' => 'content.field_image',
  ],
];

$violations = [];

/**
 * @param list<string> $patterns
 */
function line_matches_any(string $line, array $patterns): ?string {
  foreach ($patterns as $pattern) {
    if ($pattern !== '' && str_contains($line, $pattern)) {
      return $pattern;
    }
  }
  return NULL;
}

/**
 * Simple glob: node--*-teaser.html.twig
 */
function template_matches_glob(string $filename, string $glob): bool {
  $regex = '/^' . str_replace('\*', '.*', preg_quote($glob, '/')) . '$/';
  return (bool) preg_match($regex, $filename);
}

foreach ($themeRoots as $themeRoot) {
  if (!is_dir($themeRoot)) {
    $violations[] = "theme directory not found: $themeRoot";
    continue;
  }

  $iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($themeRoot, FilesystemIterator::SKIP_DOTS)
  );

  /** @var SplFileInfo $file */
  foreach ($iterator as $file) {
    if (!$file->isFile()) {
      continue;
    }
    $basename = $file->getFilename();
    $ext = $file->getExtension();
    if (!in_array($ext, ['twig', 'theme', 'php'], TRUE)) {
      continue;
    }

    $relative = str_replace($themeRoot . '/', '', $file->getPathname());
    $contents = (string) file_get_contents($file->getPathname());
    $lines = preg_split('/\R/', $contents) ?: [];

    foreach ($lines as $num => $line) {
      $match = line_matches_any($line, $forbiddenPatterns);
      if ($match !== NULL) {
        $violations[] = "$relative:" . ($num + 1) . " references forbidden baseline path '$match'";
      }
    }

    if ($ext === 'theme' || ($ext === 'php' && str_ends_with($basename, '.theme'))) {
      if (preg_match('/figma_image|figma_recipe_image/i', $contents)) {
        $violations[] = "$relative sets forbidden figma_image override (QR-THEME-002)";
      }
    }

    if ($ext === 'twig' && str_starts_with($basename, 'node--')) {
      foreach ($nodeForbiddenTokens as $token) {
        if ($token !== '' && str_contains($contents, $token)) {
          $violations[] = "$relative uses forbidden token '$token' in node template";
        }
      }

      foreach ($requiredFieldRenders as $rule) {
        if (!is_array($rule)) {
          continue;
        }
        $glob = (string) ($rule['template_glob'] ?? '');
        $field = (string) ($rule['field'] ?? '');
        if ($glob === '' || $field === '') {
          continue;
        }
        if (!template_matches_glob($basename, $glob)) {
          continue;
        }
        if (!str_contains($contents, $field)) {
          $violations[] = "$relative must render $field (not theme Figma crop overrides)";
        }
        if (preg_match('/<img[^>]+src=["\'][^"\']*images\/figma\//i', $contents)) {
          $violations[] = "$relative must not hardcode <img src=\"…/images/figma/…\"> for entity content";
        }
      }
    }
  }
}

if ($themeRoots === []) {
  fwrite(STDERR, "drupal: QUALITY FAIL: QR-THEME-001: no theme directories found under $themesPath\n");
  exit(1);
}

if ($violations !== []) {
  foreach ($violations as $violation) {
    fwrite(STDERR, "drupal: QUALITY FAIL: QR-THEME-001: $violation\n");
  }
  exit(1);
}

fwrite(STDOUT, 'drupal: QR-THEME-001: OK — theme templates avoid Figma baseline crop overrides' . PHP_EOL);
exit(0);
