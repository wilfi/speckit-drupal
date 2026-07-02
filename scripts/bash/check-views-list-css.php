#!/usr/bin/env php
<?php

/**
 * @file
 * QR-CSS-001–003: Views html_list layout and pager CSS checks.
 *
 * Usage: check-views-list-css.php [THEME_DIR] [HTML_BODY_FILE]
 * Env: PROJECT_ROOT, QUALITY_JSON (merged quality_rules JSON)
 */

declare(strict_types=1);

$projectRoot = getenv('PROJECT_ROOT') ?: getcwd();
$themeDir = $argv[1] ?? 'web/themes/custom';
$htmlFile = $argv[2] ?? '';
$themesPath = $projectRoot . '/' . ltrim($themeDir, '/');

$rules = json_decode(getenv('QUALITY_JSON') ?: '{}', true) ?: [];
$cssRules = $rules['css'] ?? [];
$enabled = $cssRules['enabled'] ?? true;

if ($enabled === false || $enabled === 'false' || $enabled === 0 || $enabled === '0') {
  exit(0);
}

$wrappers = $cssRules['views_list_wrappers'] ?? [];
$gridWrappers = $cssRules['views_list_grid_layout'] ?? $wrappers;
$pagerSelectors = $cssRules['pager_selectors'] ?? [];

$violations = [];
$logs = [];

function log_ok(string $msg): void {
  global $logs;
  $logs[] = "drupal: $msg";
}

function log_fail(string $msg): void {
  global $violations;
  $violations[] = $msg;
}

function strip_css_comments(string $css): string {
  return (string) preg_replace('/\/\*.*?\*\//s', '', $css);
}

/**
 * Extract rule blocks from CSS (handles one level of nesting in @media).
 */
function extract_rule_blocks(string $css): array {
  $blocks = [];
  $len = strlen($css);
  $i = 0;
  while ($i < $len) {
    if ($css[$i] === '@') {
      $start = $i;
      $brace = strpos($css, '{', $i);
      if ($brace === false) {
        break;
      }
      $depth = 0;
      for ($j = $brace; $j < $len; $j++) {
        if ($css[$j] === '{') {
          $depth++;
        }
        elseif ($css[$j] === '}') {
          $depth--;
          if ($depth === 0) {
            $blocks[] = ['at', substr($css, $start, $j - $start + 1)];
            $i = $j + 1;
            continue 2;
          }
        }
      }
      break;
    }
    $brace = strpos($css, '{', $i);
    if ($brace === false) {
      break;
    }
    $selectors = trim(substr($css, $i, $brace - $i));
    $depth = 0;
    for ($j = $brace; $j < $len; $j++) {
      if ($css[$j] === '{') {
        $depth++;
      }
      elseif ($css[$j] === '}') {
        $depth--;
        if ($depth === 0) {
          $declarations = substr($css, $brace + 1, $j - $brace - 1);
          $blocks[] = ['rule', $selectors, $declarations];
          $i = $j + 1;
          continue 2;
        }
      }
    }
    break;
  }
  return $blocks;
}

function flatten_css(string $css): string {
  $flat = '';
  foreach (extract_rule_blocks(strip_css_comments($css)) as $block) {
    if ($block[0] === 'at') {
      if (preg_match('/@media[^{]+\{(.+)\}$/s', $block[1], $m)) {
        $flat .= flatten_css($m[1]);
      }
    }
    else {
      $flat .= $block[1] . '{' . $block[2] . '}';
    }
  }
  return $flat;
}

function scan_theme_css(string $themesPath): string {
  $combined = '';
  if (!is_dir($themesPath)) {
    return $combined;
  }
  $iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($themesPath, FilesystemIterator::SKIP_DOTS)
  );
  foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'css') {
      $combined .= file_get_contents($file->getPathname()) . "\n";
    }
  }
  return $combined;
}

function check_anti_patterns(string $css, string $sourceLabel): void {
  $flat = flatten_css($css);
  foreach (extract_rule_blocks($flat) as $block) {
    if ($block[0] !== 'rule') {
      continue;
    }
    [, $selectors, $declarations] = $block;
    $has_layout = (bool) preg_match('/display\s*:\s*(grid|flex)\b/i', $declarations);
    $has_grid_cols = (bool) preg_match('/grid-template-columns\s*:/i', $declarations);

    foreach (explode(',', $selectors) as $selector) {
      $selector = trim($selector);
      if ($selector === '') {
        continue;
      }

      // QR-CSS-002: direct child li on wrapper (missing ul).
      if (preg_match('/\.([a-zA-Z0-9_-]+__items)\s*>\s*li\b/', $selector, $m)) {
        log_fail("QR-CSS-002: {$sourceLabel} — use .{$m[1]} ul > li, not .{$m[1]} > li");
      }

      // QR-CSS-001: grid/flex on wrapper div only (exact .block__items selector).
      if (preg_match('/^\.([a-zA-Z0-9_-]+__items)$/', $selector, $m)
        && ($has_layout || $has_grid_cols)) {
        log_fail("QR-CSS-001: {$sourceLabel} — apply layout on .{$m[1]} ul, not .{$m[1]}");
      }
    }
  }
}

function selector_in_css(string $css, string $needle): bool {
  return str_contains($css, $needle);
}

function wrapper_has_list_layout(string $css, string $wrapper): bool {
  $flat = flatten_css($css);
  $pattern = preg_quote($wrapper, '/') . '\s+ul';
  foreach (extract_rule_blocks($flat) as $block) {
    if ($block[0] !== 'rule') {
      continue;
    }
    [, $selectors, $declarations] = $block;
    if (!preg_match("/{$pattern}/", $selectors)) {
      continue;
    }
    if (preg_match('/display\s*:\s*(grid|flex)\b/i', $declarations)
      || preg_match('/grid-template-columns\s*:/i', $declarations)) {
      return true;
    }
  }
  return false;
}

function pager_has_flex_layout(string $css, string $pagerSelector): bool {
  $flat = flatten_css($css);
  $escaped = preg_quote($pagerSelector, '/');
  $escaped = str_replace('\ ', '\s+', $escaped);
  foreach (extract_rule_blocks($flat) as $block) {
    if ($block[0] !== 'rule') {
      continue;
    }
    [, $selectors, $declarations] = $block;
    if (!preg_match("/{$escaped}/", preg_replace('/\s+/', ' ', $selectors))) {
      continue;
    }
    if (preg_match('/display\s*:\s*flex\b/i', $declarations)) {
      return true;
    }
  }
  return false;
}

function check_html_structure(string $html, array $wrappers): void {
  foreach ($wrappers as $wrapper) {
    if (!str_contains($html, $wrapper)) {
      continue;
    }
    $class = preg_quote($wrapper, '/');
    if (!preg_match('/class="[^"]*\b' . $class . '\b[^"]*"[^>]*>\s*<ul\b/is', $html)) {
      log_fail("QR-CSS-001: page markup — .{$wrapper} must wrap a <ul> (Views html_list contract)");
      continue;
    }
    log_ok("QR-CSS-001: OK — .{$wrapper} > ul structure present in page HTML");
  }
}

$themeCss = scan_theme_css($themesPath);
if ($themeCss === '') {
  log_ok('QR-CSS: no custom theme CSS found — skipping theme scan');
}
else {
  check_anti_patterns($themeCss, 'theme CSS');
  log_ok('QR-CSS-001/002: theme CSS anti-pattern scan complete');
}

foreach ($gridWrappers as $wrapper) {
  if (wrapper_has_list_layout($themeCss, $wrapper)) {
    log_ok("QR-CSS-001: OK — .{$wrapper} ul layout rule found in theme CSS");
  }
  else {
    log_fail("QR-CSS-001: theme CSS missing grid/flex on .{$wrapper} ul");
  }
}

foreach ($pagerSelectors as $pagerSelector) {
  if (pager_has_flex_layout($themeCss, $pagerSelector)) {
    log_ok("QR-CSS-003: OK — {$pagerSelector} flex layout found in theme CSS");
  }
  else {
    log_fail("QR-CSS-003: theme CSS missing display:flex on {$pagerSelector}");
  }
}

if ($htmlFile !== '' && is_file($htmlFile)) {
  check_html_structure(file_get_contents($htmlFile), $wrappers);
}

foreach ($logs as $line) {
  echo $line, PHP_EOL;
}
foreach ($violations as $line) {
  fwrite(STDERR, "drupal: QUALITY FAIL: {$line}\n");
}

exit($violations === [] ? 0 : 1);
