#!/usr/bin/env php
<?php

/**
 * @file
 * QR-ASSET-001/002: Verify images and static assets resolve within page/component scopes.
 *
 * Usage: check-page-assets.php HTML_FILE BASE_URL [FEATURE_DIR]
 * Env: PROJECT_ROOT, QUALITY_JSON (merged quality_rules JSON)
 */

declare(strict_types=1);

use Symfony\Component\CssSelector\CssSelectorConverter;
use Symfony\Component\Yaml\Yaml;

$projectRoot = getenv('PROJECT_ROOT') ?: getcwd();
$htmlFile = $argv[1] ?? '';
$baseUrl = rtrim($argv[2] ?? '', '/');
$featureDir = $argv[3] ?? '';

require $projectRoot . '/vendor/autoload.php';

function load_yaml_file(string $path): array {
  if (!is_file($path)) {
    return [];
  }
  $parsed = Yaml::parseFile($path);
  return is_array($parsed) ? $parsed : [];
}

function load_assets_config(): array {
  $rules = json_decode(getenv('QUALITY_JSON') ?: '{}', true) ?: [];
  return is_array($rules['assets'] ?? null) ? $rules['assets'] : [];
}

function load_figma_config(string $projectRoot, string $featureDir): array {
  if ($featureDir === '') {
    return [];
  }
  $featurePath = str_starts_with($featureDir, '/')
    ? $featureDir
    : $projectRoot . '/' . ltrim($featureDir, '/');
  $path = $featurePath . '/figma-design-checks.yml';
  if (!is_file($path)) {
    return [];
  }
  $parsed = load_yaml_file($path);
  return is_array($parsed['figma'] ?? null) ? $parsed['figma'] : [];
}

function figma_scopes(array $figma): array {
  $scopes = [];
  $screenshot = $figma['screenshot'] ?? [];
  foreach ($screenshot['sections'] ?? [] as $section) {
    if (!is_array($section)) {
      continue;
    }
    $selector = trim((string) ($section['selector'] ?? ''));
    if ($selector === '') {
      continue;
    }
    $scopes[] = [
      'name' => 'section-' . ($section['name'] ?? 'unknown'),
      'selector' => $selector,
    ];
  }
  $components = $screenshot['components']['items'] ?? [];
  foreach ($components as $component) {
    if (!is_array($component)) {
      continue;
    }
    $selector = trim((string) ($component['selector'] ?? ''));
    if ($selector === '') {
      continue;
    }
    $scopes[] = [
      'name' => 'component-' . ($component['name'] ?? 'unknown'),
      'selector' => $selector,
    ];
  }
  return $scopes;
}

function merge_scopes(array $base, array $extra): array {
  $merged = $base;
  $seen = [];
  foreach ($base as $scope) {
    $key = ($scope['name'] ?? '') . '|' . ($scope['selector'] ?? '');
    $seen[$key] = true;
  }
  foreach ($extra as $scope) {
    $key = ($scope['name'] ?? '') . '|' . ($scope['selector'] ?? '');
    if (isset($seen[$key])) {
      continue;
    }
    $merged[] = $scope;
    $seen[$key] = true;
  }
  return $merged;
}

function scopes_for_path(string $path, array $assets, array $figma): array {
  $scopes = [];
  $checkFullPage = $assets['check_full_page'] ?? true;
  if ($checkFullPage !== false && $checkFullPage !== 'false' && $checkFullPage !== 0 && $checkFullPage !== '0') {
    $scopes[] = ['name' => 'page', 'selector' => ''];
  }

  $useFigmaScopes = $assets['use_figma_scopes'] ?? false;
  if ($useFigmaScopes !== false && $useFigmaScopes !== 'false' && $useFigmaScopes !== 0 && $useFigmaScopes !== '0') {
    $scopes = merge_scopes($scopes, figma_scopes($figma));
  }

  foreach ($assets['pages'] ?? [] as $page) {
    if (!is_array($page) || ($page['path'] ?? '/') !== $path) {
      continue;
    }
    foreach ($page['scopes'] ?? [] as $scope) {
      if (!is_array($scope)) {
        continue;
      }
      $scopes[] = [
        'name' => $scope['name'] ?? 'scope',
        'selector' => $scope['selector'] ?? '',
        'required' => $scope['required'] ?? [],
      ];
    }
    foreach ($page['required'] ?? [] as $url) {
      $scopes[] = [
        'name' => 'required',
        'selector' => '',
        'required' => [$url],
      ];
    }
  }

  foreach ($assets['scopes'] ?? [] as $scope) {
    if (!is_array($scope)) {
      continue;
    }
    $scopes[] = [
      'name' => $scope['name'] ?? 'scope',
      'selector' => $scope['selector'] ?? '',
      'required' => $scope['required'] ?? [],
    ];
  }

  return $scopes;
}

function parse_srcset(string $srcset): array {
  $urls = [];
  foreach (explode(',', $srcset) as $part) {
    $part = trim($part);
    if ($part === '') {
      continue;
    }
    $pieces = preg_split('/\s+/', $part);
    $url = trim($pieces[0] ?? '');
    if ($url !== '') {
      $urls[] = $url;
    }
  }
  return $urls;
}

function urls_from_style(string $style): array {
  $urls = [];
  if (preg_match_all('/url\(\s*([\'"]?)([^\'")]+)\1\s*\)/i', $style, $matches)) {
    foreach ($matches[2] as $url) {
      $url = trim($url);
      if ($url !== '') {
        $urls[] = $url;
      }
    }
  }
  return $urls;
}

function is_skippable_url(string $url): bool {
  $url = trim($url);
  if ($url === '' || $url === '#') {
    return true;
  }
  return (bool) preg_match('#^(data:|blob:|mailto:|javascript:|tel:)#i', $url);
}

function resolve_asset_url(string $url, string $baseUrl): string {
  $url = trim($url);
  if ($url === '') {
    return '';
  }
  if (preg_match('#^https?://#i', $url)) {
    return $url;
  }
  if (str_starts_with($url, '//')) {
    $scheme = parse_url($baseUrl, PHP_URL_SCHEME) ?: 'https';
    return $scheme . ':' . $url;
  }
  if (str_starts_with($url, '/')) {
    $parts = parse_url($baseUrl);
    $scheme = $parts['scheme'] ?? 'https';
    $host = $parts['host'] ?? 'localhost';
    $port = isset($parts['port']) ? ':' . $parts['port'] : '';
    return $scheme . '://' . $host . $port . $url;
  }
  $base = preg_replace('#/[^/]*$#', '/', $baseUrl);
  return rtrim($base, '/') . '/' . ltrim($url, '/');
}

/**
 * @return array{urls: string[], missing_src: string[]}
 */
function extract_assets_in_scope(string $html, string $selector): array {
  $urls = [];
  $missingSrc = [];

  $dom = new DOMDocument();
  $previous = libxml_use_internal_errors(true);
  $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
  libxml_clear_errors();
  libxml_use_internal_errors($previous);

  $xpath = new DOMXPath($dom);

  $contextNodes = [];
  if ($selector === '') {
    $contextNodes[] = $dom->documentElement;
  }
  else {
    if (!class_exists(CssSelectorConverter::class)) {
      return [
        'urls' => [],
        'missing_src' => [
          'CssSelectorConverter unavailable — run: composer require symfony/css-selector',
        ],
      ];
    }
    $converter = new CssSelectorConverter();
    $xpathExpr = $converter->toXPath($selector);
    $matches = $xpath->query($xpathExpr);
    if ($matches === false || $matches->length === 0) {
      return ['urls' => [], 'missing_src' => ["selector not found: $selector"]];
    }
    foreach ($matches as $node) {
      $contextNodes[] = $node;
    }
  }

  foreach ($contextNodes as $context) {
    $nodes = $xpath->query('.//*', $context);
    if ($nodes === false) {
      continue;
    }
    foreach ($nodes as $node) {
      if (!$node instanceof DOMElement) {
        continue;
      }
      $tag = strtolower($node->tagName);

      if ($tag === 'img') {
        if (!$node->hasAttribute('src') || trim($node->getAttribute('src')) === '') {
          $missingSrc[] = 'img without src';
        }
        else {
          $urls[] = $node->getAttribute('src');
        }
        if ($node->hasAttribute('srcset')) {
          $urls = array_merge($urls, parse_srcset($node->getAttribute('srcset')));
        }
      }

      if ($tag === 'source') {
        if ($node->hasAttribute('src')) {
          $urls[] = $node->getAttribute('src');
        }
        if ($node->hasAttribute('srcset')) {
          $urls = array_merge($urls, parse_srcset($node->getAttribute('srcset')));
        }
      }

      if ($tag === 'video' && $node->hasAttribute('poster')) {
        $urls[] = $node->getAttribute('poster');
      }

      if ($tag === 'link') {
        $rel = strtolower($node->getAttribute('rel'));
        if (preg_match('/\b(icon|preload|stylesheet|apple-touch-icon)\b/', $rel) && $node->hasAttribute('href')) {
          $urls[] = $node->getAttribute('href');
        }
      }

      if ($node->hasAttribute('style')) {
        $urls = array_merge($urls, urls_from_style($node->getAttribute('style')));
      }
    }
  }

  return [
    'urls' => array_values(array_unique($urls)),
    'missing_src' => $missingSrc,
  ];
}

function verify_asset_url(string $url): array {
  $ch = curl_init($url);
  if ($ch === false) {
    return ['ok' => false, 'code' => 0];
  }
  curl_setopt_array($ch, [
    CURLOPT_NOBODY => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_USERAGENT => 'SpecKit-QR-ASSET-001',
  ]);
  curl_exec($ch);
  $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  if ($code === 405 || $code === 501) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_CONNECTTIMEOUT => 5,
      CURLOPT_TIMEOUT => 15,
      CURLOPT_USERAGENT => 'SpecKit-QR-ASSET-001',
    ]);
    curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
  }

  return ['ok' => $code >= 200 && $code < 400, 'code' => $code];
}

function fetch_asset_snippet(string $url, int $maxBytes = 512): string {
  $ch = curl_init($url);
  if ($ch === false) {
    return '';
  }
  curl_setopt_array($ch, [
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_RANGE => '0-' . max(0, $maxBytes - 1),
    CURLOPT_USERAGENT => 'SpecKit-QR-ASSET-004',
  ]);
  $body = curl_exec($ch);
  curl_close($ch);
  return is_string($body) ? $body : '';
}

/**
 * @return array{ok: bool, message: string}
 */
function validate_asset_format(string $url): array {
  $path = parse_url($url, PHP_URL_PATH) ?: '';
  $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
  if (!in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'], true)) {
    return ['ok' => true, 'message' => 'skip'];
  }

  $snippet = fetch_asset_snippet($url);
  if ($snippet === '') {
    return ['ok' => false, 'message' => 'could not read asset bytes'];
  }

  $trimmed = ltrim($snippet);
  $isXml = str_starts_with($trimmed, '<?xml') || str_starts_with($trimmed, '<svg');
  $isPng = str_starts_with($snippet, "\x89PNG\r\n\x1a\n");
  $isJpeg = str_starts_with($snippet, "\xFF\xD8\xFF");
  $isGif = str_starts_with($snippet, 'GIF87a') || str_starts_with($snippet, 'GIF89a');
  $isWebp = strlen($snippet) >= 12 && substr($snippet, 0, 4) === 'RIFF' && substr($snippet, 8, 4) === 'WEBP';

  if ($ext === 'png' && !$isPng) {
    $detail = $isXml ? 'SVG/XML content' : 'not a PNG signature';
    return ['ok' => false, 'message' => ".$ext file has $detail"];
  }
  if (in_array($ext, ['jpg', 'jpeg'], true) && !$isJpeg) {
    return ['ok' => false, 'message' => ".$ext file is not a JPEG signature"];
  }
  if ($ext === 'gif' && !$isGif) {
    return ['ok' => false, 'message' => '.gif file is not a GIF signature'];
  }
  if ($ext === 'webp' && !$isWebp) {
    return ['ok' => false, 'message' => '.webp file is not a WEBP signature'];
  }
  if ($ext === 'svg' && !$isXml) {
    return ['ok' => false, 'message' => '.svg file is not SVG/XML content'];
  }

  return ['ok' => true, 'message' => 'valid'];
}

$config = load_assets_config();
$enabled = $config['enabled'] ?? true;
if ($enabled === false || $enabled === 'false' || $enabled === 0 || $enabled === '0') {
  exit(0);
}

if ($htmlFile === '' || !is_file($htmlFile)) {
  fwrite(STDERR, "drupal: QUALITY FAIL: QR-ASSET-001: HTML file required\n");
  exit(1);
}

if ($baseUrl === '') {
  fwrite(STDERR, "drupal: QUALITY FAIL: QR-ASSET-001: base URL required\n");
  exit(1);
}

$html = file_get_contents($htmlFile);
$path = parse_url($baseUrl, PHP_URL_PATH) ?: '/';
$figma = load_figma_config($projectRoot, $featureDir);
$scopes = scopes_for_path($path, $config, $figma);

if ($scopes === []) {
  fwrite(STDOUT, "drupal: QR-ASSET-001: no asset scopes configured — skip\n");
  exit(0);
}

$violations = [];
$checked = [];
$formatChecked = [];
$validateFormats = $config['validate_formats'] ?? true;
if ($validateFormats === 'false' || $validateFormats === 0 || $validateFormats === '0') {
  $validateFormats = false;
}

foreach ($scopes as $scope) {
  $name = (string) ($scope['name'] ?? 'scope');
  $selector = (string) ($scope['selector'] ?? '');
  $required = $scope['required'] ?? [];
  if (!is_array($required)) {
    $required = [];
  }

  $extracted = extract_assets_in_scope($html, $selector);
  foreach ($extracted['missing_src'] as $issue) {
    if (str_starts_with($issue, 'selector not found')) {
      $violations[] = "QR-ASSET-001: scope '$name' — $issue";
      continue 2;
    }
    $violations[] = "QR-ASSET-002: scope '$name' — $issue";
  }

  $urls = array_merge($extracted['urls'], $required);
  $urls = array_values(array_unique(array_filter($urls, static fn(string $u): bool => !is_skippable_url($u))));

  foreach ($urls as $rawUrl) {
    $resolved = resolve_asset_url($rawUrl, $baseUrl);
    if ($resolved === '' || is_skippable_url($resolved)) {
      continue;
    }
    $cacheKey = $name . '|' . $resolved;
    if (isset($checked[$cacheKey])) {
      continue;
    }
    $checked[$cacheKey] = true;

    $result = verify_asset_url($resolved);
    if ($result['ok']) {
      fwrite(STDOUT, "drupal: QR-ASSET-001: OK — scope '$name' asset $resolved → HTTP {$result['code']}\n");
      if ($validateFormats && !isset($formatChecked[$resolved])) {
        $formatChecked[$resolved] = true;
        $format = validate_asset_format($resolved);
        if ($format['ok']) {
          fwrite(STDOUT, "drupal: QR-ASSET-004: OK — $resolved format {$format['message']}\n");
        }
        else {
          $violations[] = "QR-ASSET-004: $resolved — {$format['message']}";
        }
      }
      continue;
    }
    $violations[] = "QR-ASSET-001: scope '$name' missing/unreachable asset $resolved (HTTP {$result['code']})";
  }
}

if ($violations !== []) {
  foreach ($violations as $violation) {
    fwrite(STDERR, "drupal: QUALITY FAIL: $violation\n");
  }
  exit(1);
}

exit(0);
