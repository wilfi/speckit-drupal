#!/usr/bin/env php
<?php

/**
 * @file
 * QR-THEME-003: Webform Twig overrides preserve form wrapper and Figma DOM shape.
 *
 * Usage: check-webform-templates.php [THEME_DIR]
 * Env: PROJECT_ROOT, QUALITY_JSON
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

$webformRules = $themeRules['webform_templates'] ?? [];
if ($webformRules === [] || ($webformRules['enabled'] ?? true) === false) {
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

$requiredPatterns = $webformRules['require_form_wrapper_patterns'] ?? [
  '<form{{ attributes',
  '<form {{ attributes',
];
$forbiddenPatterns = $webformRules['forbidden_patterns'] ?? [
  '/\{\{\s*element\s*\}\}/' => 'Render {{ element }} only — wrap in <form{{ attributes }}> per Figma composite',
];
$requiredFiles = $webformRules['required_overrides'] ?? [];

$violations = [];

foreach ($themeRoots as $themeRoot) {
  $webformDir = "$themeRoot/templates/webform";
  if (!is_dir($webformDir)) {
    continue;
  }

  $files = glob("$webformDir/webform--*.html.twig") ?: [];
  foreach ($files as $file) {
    $basename = basename($file);
    $content = (string) file_get_contents($file);
    $rel = str_replace($projectRoot . '/', '', $file);

    $hasForm = FALSE;
    foreach ($requiredPatterns as $pattern) {
      if (str_contains($content, $pattern)) {
        $hasForm = TRUE;
        break;
      }
    }
    if (!$hasForm && preg_match('/\{\{\s*element\s*\}\}/', $content)) {
      $violations[] = "$rel: missing <form{{ attributes }}> wrapper around {{ element }}";
    }

    foreach ($forbiddenPatterns as $pattern => $message) {
      if (@preg_match($pattern, $content) === 1) {
        // Allowed when form wrapper is also present.
        if (!$hasForm) {
          $violations[] = "$rel: $message";
        }
      }
    }

    if ($hasForm) {
      fwrite(STDOUT, "drupal: QR-THEME-003: OK — $rel preserves form wrapper\n");
    }
  }

  foreach ($requiredFiles as $required) {
    $path = "$webformDir/$required";
    if (!is_file($path)) {
      $violations[] = "missing required webform override templates/webform/$required";
      continue;
    }
    $content = (string) file_get_contents($path);
    $hasForm = FALSE;
    foreach ($requiredPatterns as $pattern) {
      if (str_contains($content, $pattern)) {
        $hasForm = TRUE;
        break;
      }
    }
    if (!$hasForm) {
      $violations[] = "templates/webform/$required: missing <form{{ attributes }}> wrapper";
    }
  }
}

if ($violations !== []) {
  foreach ($violations as $v) {
    fwrite(STDERR, "drupal: QUALITY FAIL: QR-THEME-003: $v\n");
  }
  exit(1);
}

fwrite(STDOUT, "drupal: QR-THEME-003: OK — webform template structure checks passed\n");
exit(0);
