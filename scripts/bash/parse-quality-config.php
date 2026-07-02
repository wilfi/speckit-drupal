#!/usr/bin/env php
<?php

/**
 * @file
 * Merge extension + feature quality config for verify-quality.sh.
 *
 * Usage: php parse-quality-config.php [FEATURE_DIR]
 * Output: JSON quality_rules object on stdout.
 */

declare(strict_types=1);

$projectRoot = getenv('PROJECT_ROOT') ?: getcwd();
$featureArg = $argv[1] ?? '';

require $projectRoot . '/vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;

function load_yaml(string $path): array {
  if (!is_file($path)) {
    return [];
  }
  $parsed = Yaml::parseFile($path);
  return is_array($parsed) ? $parsed : [];
}

function merge_list(array $base, array $extra): array {
  return array_values(array_unique(array_merge($base, $extra)));
}

function merge_smoke(array $base, array $extra): array {
  $merged = $base;
  if (isset($extra['forbidden_strings'])) {
    $merged['forbidden_strings'] = merge_list(
      $merged['forbidden_strings'] ?? [],
      $extra['forbidden_strings']
    );
  }
  if (isset($extra['libraries'])) {
    $merged['libraries'] = merge_list(
      $merged['libraries'] ?? [],
      $extra['libraries']
    );
  }
  if (isset($extra['js_behaviors'])) {
    $merged['js_behaviors'] = merge_list(
      $merged['js_behaviors'] ?? [],
      $extra['js_behaviors']
    );
  }
  if (isset($extra['icon_markers']) && is_array($extra['icon_markers'])) {
    $merged['icon_markers'] = array_merge(
      $merged['icon_markers'] ?? [],
      $extra['icon_markers']
    );
  }
  if (isset($extra['content_image_scopes']) && is_array($extra['content_image_scopes'])) {
    $merged['content_image_scopes'] = array_merge(
      $merged['content_image_scopes'] ?? [],
      $extra['content_image_scopes']
    );
  }
  if (isset($extra['composite_forms']) && is_array($extra['composite_forms'])) {
    $merged['composite_forms'] = array_merge(
      $merged['composite_forms'] ?? [],
      $extra['composite_forms']
    );
  }
  if (isset($extra['pages']) && is_array($extra['pages'])) {
    $pages = $merged['pages'] ?? [];
    foreach ($extra['pages'] as $page) {
      if (!is_array($page) || empty($page['path'])) {
        continue;
      }
      $found = FALSE;
      foreach ($pages as &$existing) {
        if (($existing['path'] ?? '') === $page['path']) {
          $existing['must_contain'] = merge_list(
            $existing['must_contain'] ?? [],
            $page['must_contain'] ?? []
          );
          $existing['must_not_contain'] = merge_list(
            $existing['must_not_contain'] ?? [],
            $page['must_not_contain'] ?? []
          );
          $existing['min_occurrences'] = array_merge(
            $existing['min_occurrences'] ?? [],
            $page['min_occurrences'] ?? []
          );
          $found = TRUE;
          break;
        }
      }
      unset($existing);
      if (!$found) {
        $pages[] = $page;
      }
    }
    $merged['pages'] = $pages;
  }
  return $merged;
}

function merge_assets(array $base, array $extra): array {
  $merged = $base;
  foreach (['enabled', 'check_full_page', 'use_figma_scopes'] as $key) {
    if (array_key_exists($key, $extra)) {
      $merged[$key] = $extra[$key];
    }
  }
  if (isset($extra['scopes']) && is_array($extra['scopes'])) {
    $merged['scopes'] = merge_list($merged['scopes'] ?? [], $extra['scopes']);
  }
  if (isset($extra['pages']) && is_array($extra['pages'])) {
    $pages = $merged['pages'] ?? [];
    foreach ($extra['pages'] as $page) {
      if (!is_array($page) || empty($page['path'])) {
        continue;
      }
      $found = FALSE;
      foreach ($pages as &$existing) {
        if (($existing['path'] ?? '') === $page['path']) {
          if (isset($page['required'])) {
            $existing['required'] = merge_list(
              $existing['required'] ?? [],
              is_array($page['required']) ? $page['required'] : []
            );
          }
          if (isset($page['scopes']) && is_array($page['scopes'])) {
            $existing['scopes'] = array_merge($existing['scopes'] ?? [], $page['scopes']);
          }
          $found = TRUE;
          break;
        }
      }
      unset($existing);
      if (!$found) {
        $pages[] = $page;
      }
    }
    $merged['pages'] = $pages;
  }
  return $merged;
}

$configPath = $projectRoot . '/.specify/extensions/drupal/drupal-config.yml';
$rules = load_yaml($configPath)['quality_rules'] ?? [];

if ($featureArg !== '') {
  $featureDir = str_starts_with($featureArg, '/')
    ? $featureArg
    : $projectRoot . '/' . ltrim($featureArg, '/');
  $featureChecks = $featureDir . '/quality-checks.yml';
  if (is_file($featureChecks)) {
    $featureRules = load_yaml($featureChecks);
    if (isset($featureRules['performance']['check_urls'])) {
      $rules['performance']['check_urls'] = merge_list(
        $rules['performance']['check_urls'] ?? ['/'],
        $featureRules['performance']['check_urls']
      );
    }
    if (isset($featureRules['smoke'])) {
      $rules['smoke'] = merge_smoke($rules['smoke'] ?? [], $featureRules['smoke']);
    }
    if (isset($featureRules['css']) && is_array($featureRules['css'])) {
      $baseCss = $rules['css'] ?? [];
      $extraCss = $featureRules['css'];
      if (isset($extraCss['views_list_wrappers'])) {
        $baseCss['views_list_wrappers'] = merge_list(
          $baseCss['views_list_wrappers'] ?? [],
          $extraCss['views_list_wrappers']
        );
      }
      if (isset($extraCss['views_list_grid_layout'])) {
        $baseCss['views_list_grid_layout'] = merge_list(
          $baseCss['views_list_grid_layout'] ?? [],
          $extraCss['views_list_grid_layout']
        );
      }
      if (isset($extraCss['pager_selectors'])) {
        $baseCss['pager_selectors'] = merge_list(
          $baseCss['pager_selectors'] ?? [],
          $extraCss['pager_selectors']
        );
      }
      if (isset($extraCss['section_max_width_selectors'])) {
        $baseCss['section_max_width_selectors'] = merge_list(
          $baseCss['section_max_width_selectors'] ?? [],
          $extraCss['section_max_width_selectors']
        );
      }
      if (isset($extraCss['component_padding_rules']) && is_array($extraCss['component_padding_rules'])) {
        $baseCss['component_padding_rules'] = array_merge(
          $baseCss['component_padding_rules'] ?? [],
          $extraCss['component_padding_rules']
        );
      }
      if (isset($extraCss['section_margin_selectors'])) {
        $baseCss['section_margin_selectors'] = merge_list(
          $baseCss['section_margin_selectors'] ?? [],
          $extraCss['section_margin_selectors']
        );
      }
      if (isset($extraCss['front_page_layout_regions'])) {
        $baseCss['front_page_layout_regions'] = merge_list(
          $baseCss['front_page_layout_regions'] ?? [],
          $extraCss['front_page_layout_regions']
        );
      }
      if (isset($extraCss['component_button_rules']) && is_array($extraCss['component_button_rules'])) {
        $baseCss['component_button_rules'] = array_merge(
          $baseCss['component_button_rules'] ?? [],
          $extraCss['component_button_rules']
        );
      }
      if (isset($extraCss['component_layout_rules']) && is_array($extraCss['component_layout_rules'])) {
        $baseCss['component_layout_rules'] = array_merge(
          $baseCss['component_layout_rules'] ?? [],
          $extraCss['component_layout_rules']
        );
      }
      if (isset($extraCss['composite_form_anti_patterns']) && is_array($extraCss['composite_form_anti_patterns'])) {
        $baseCss['composite_form_anti_patterns'] = array_merge(
          $baseCss['composite_form_anti_patterns'] ?? [],
          $extraCss['composite_form_anti_patterns']
        );
      }
      if (isset($extraCss['component_spacing_rules']) && is_array($extraCss['component_spacing_rules'])) {
        $baseCss['component_spacing_rules'] = array_merge(
          $baseCss['component_spacing_rules'] ?? [],
          $extraCss['component_spacing_rules']
        );
      }
      if (isset($extraCss['front_page_template'])) {
        $baseCss['front_page_template'] = $extraCss['front_page_template'];
      }
      foreach (['enabled', 'theme_dir'] as $key) {
        if (array_key_exists($key, $extraCss)) {
          $baseCss[$key] = $extraCss[$key];
        }
      }
      $rules['css'] = $baseCss;
    }
    if (isset($featureRules['assets']) && is_array($featureRules['assets'])) {
      $rules['assets'] = merge_assets($rules['assets'] ?? [], $featureRules['assets']);
    }
    if (isset($featureRules['accessibility']) && is_array($featureRules['accessibility'])) {
      $rules['accessibility'] = array_merge($rules['accessibility'] ?? [], $featureRules['accessibility']);
    }
    if (isset($featureRules['figma']) && is_array($featureRules['figma'])) {
      $rules['figma'] = array_merge($rules['figma'] ?? [], $featureRules['figma']);
    }
    if (isset($featureRules['theme']) && is_array($featureRules['theme'])) {
      $rules['theme'] = array_merge($rules['theme'] ?? [], $featureRules['theme']);
    }
  }
}

$drupalCfg = load_yaml($configPath);
if (!empty($drupalCfg['figma']) && is_array($drupalCfg['figma'])) {
  $rules['figma'] = array_merge($drupalCfg['figma'], $rules['figma'] ?? []);
}

echo json_encode($rules, JSON_THROW_ON_ERROR);
