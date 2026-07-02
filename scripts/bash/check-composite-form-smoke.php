#!/usr/bin/env php
<?php

/**
 * @file
 * QR-SMOKE-011: Live HTML composite form structure (email + submit + icon).
 *
 * Usage: check-composite-form-smoke.php BODY_FILE URL
 * Env: PROJECT_ROOT, QUALITY_JSON
 */

declare(strict_types=1);

$bodyFile = $argv[1] ?? '';
$url = $argv[2] ?? '/';

if ($bodyFile === '' || !is_file($bodyFile)) {
  fwrite(STDERR, "drupal: QUALITY FAIL: QR-SMOKE-011: HTML body file required\n");
  exit(1);
}

$rules = json_decode(getenv('QUALITY_JSON') ?: '{}', true) ?: [];
$forms = $rules['smoke']['composite_forms'] ?? [];

if ($forms === []) {
  exit(0);
}

$html = (string) file_get_contents($bodyFile);
$violations = [];

foreach ($forms as $form) {
  if (!is_array($form)) {
    continue;
  }
  $id = (string) ($form['id'] ?? 'QR-SMOKE-011');
  $path = (string) ($form['path'] ?? '/');
  if ($path !== '/' && !str_contains($url, rtrim($path, '/'))) {
    continue;
  }

  $section = trim((string) ($form['section_selector'] ?? ''));
  $formSel = trim((string) ($form['form_selector'] ?? ''));
  if ($section === '' || $formSel === '') {
    $violations[] = "$id: section_selector and form_selector required";
    continue;
  }

  // Extract section HTML (class-based heuristic).
  $sectionClass = preg_replace('/^\./', '', $section);
  if (!preg_match('/class="[^"]*\b' . preg_quote($sectionClass, '/') . '\b[^"]*"/i', $html)) {
    $violations[] = "$id: section $section not found on $url";
    continue;
  }

  if (!preg_match('/<' . 'form\b[^>]*class="[^"]*"[^>]*>/i', $html)) {
    $violations[] = "$id: no <form> element found on $url — webform override must preserve form wrapper (QR-THEME-003)";
    continue;
  }

  if (!empty($form['requires_email']) && !preg_match('/type="email"/i', $html)) {
    $violations[] = "$id: email input missing on $url";
    continue;
  }

  if (!empty($form['requires_submit'])) {
    $hasSubmit = preg_match('/type="submit"/i', $html) || preg_match('/class="[^"]*form-submit[^"]*"/i', $html);
    if (!$hasSubmit) {
      $violations[] = "$id: submit control missing on $url";
      continue;
    }
  }

  $iconPattern = trim((string) ($form['icon_asset_pattern'] ?? ''));
  if ($iconPattern !== '') {
    $via = (string) ($form['icon_via'] ?? 'html_img');
    $iconSelector = trim((string) ($form['icon_selector'] ?? ''));
    $found = FALSE;
    if ($via === 'html_img' || $via === 'any') {
      $found = (bool) preg_match('/' . $iconPattern . '/i', $html);
      if (!$found && $iconSelector !== '') {
        $found = (bool) preg_match(
          '/class="[^"]*' . preg_quote($iconSelector, '/') . '[^"]*"[^>]*>[\s\S]*?' . $iconPattern . '/i',
          $html
        );
      }
    }
    if (!$found && ($via === 'css_pseudo' || $via === 'any')) {
      // Theme CSS is not in HTML — check linked stylesheets for pseudo-element icon reference.
      $projectRoot = getenv('PROJECT_ROOT') ?: getcwd();
      $themeName = trim((string) ($rules['theme']['theme_name'] ?? 'cooks_delight'));
      $cssDir = $projectRoot . '/web/themes/custom/' . $themeName . '/css';
      if (is_dir($cssDir)) {
        foreach (glob("$cssDir/**/*.css") ?: [] as $cssFile) {
          $css = (string) file_get_contents($cssFile);
          if (preg_match('/' . preg_quote($sectionClass, '/') . '[^{]*form::before[^{]*\{[^}]*' . $iconPattern . '/is', $css)) {
            $found = TRUE;
            break;
          }
        }
      }
    }
    if (!$found) {
      $violations[] = "$id: icon asset matching /$iconPattern/ not found in HTML or $sectionClass form::before CSS";
      continue;
    }
  }

  // Form must wrap email field (not sibling-only flat structure outside form).
  if (!preg_match('/<' . 'form\b[^>]*>[\s\S]*?type="email"[\s\S]*?<' . '\/form>/i', $html)) {
    $violations[] = "$id: email input must be inside <form> on $url";
    continue;
  }

  fwrite(STDOUT, "drupal: $id: OK — composite form structure on $url ($section)\n");
}

if ($violations !== []) {
  foreach ($violations as $v) {
    fwrite(STDERR, "drupal: QUALITY FAIL: $v\n");
  }
  exit(1);
}

exit(0);
