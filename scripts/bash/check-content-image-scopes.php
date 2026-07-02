#!/usr/bin/env php
<?php

/**
 * @file
 * QR-SMOKE-010: Card/content images must use managed files, not theme baseline crops.
 *
 * Usage: check-content-image-scopes.php HTML_FILE URL
 * Env: QUALITY_JSON (merged quality_rules JSON)
 */

declare(strict_types=1);

$htmlFile = $argv[1] ?? '';
$url = $argv[2] ?? '/';

if ($htmlFile === '' || !is_file($htmlFile)) {
  fwrite(STDERR, "drupal: QUALITY FAIL: QR-SMOKE-010: HTML body file required\n");
  exit(1);
}

$rules = json_decode(getenv('QUALITY_JSON') ?: '{}', true) ?: [];
$scopes = $rules['smoke']['content_image_scopes'] ?? [];

if (!is_array($scopes) || $scopes === []) {
  exit(0);
}

$path = parse_url($url, PHP_URL_PATH) ?: '/';
$body = (string) file_get_contents($htmlFile);

/**
 * Extract inner HTML of first element whose class list contains $className.
 */
function extract_class_block(string $html, string $className): ?string {
  $quoted = preg_quote($className, '/');
  if (!preg_match('/<([a-z][a-z0-9]*)[^>]*class="[^"]*\b' . $quoted . '\b[^"]*"[^>]*>/i', $html, $open, PREG_OFFSET_CAPTURE)) {
    return NULL;
  }
  $tag = strtolower($open[1][0]);
  $start = $open[0][1];
  $pos = $start + strlen($open[0][0]);
  $depth = 1;
  $len = strlen($html);
  while ($pos < $len && $depth > 0) {
    if (preg_match('/<\/?' . preg_quote($tag, '/') . '\b[^>]*>/i', $html, $token, PREG_OFFSET_CAPTURE, $pos)) {
      $match = $token[0][0];
      $pos = $token[0][1] + strlen($match);
      if (str_starts_with($match, '</')) {
        $depth--;
      }
      else {
        $depth++;
      }
      continue;
    }
    break;
  }
  if ($depth !== 0) {
    return NULL;
  }
  return substr($html, $start, $pos - $start);
}

/**
 * @return list<string>
 */
function extract_class_blocks(string $html, string $className): array {
  $blocks = [];
  $remaining = $html;
  while ($remaining !== '') {
    $block = extract_class_block($remaining, $className);
    if ($block === NULL) {
      break;
    }
    $blocks[] = $block;
    $offset = strpos($remaining, $block);
    if ($offset === FALSE) {
      break;
    }
    $remaining = substr($remaining, $offset + strlen($block));
  }
  return $blocks;
}

foreach ($scopes as $scope) {
  if (!is_array($scope)) {
    continue;
  }
  $id = (string) ($scope['id'] ?? 'QR-SMOKE-010');
  $scopePath = (string) ($scope['path'] ?? '/');
  if ($scopePath !== $path) {
    continue;
  }

  $parentClass = trim((string) ($scope['parent_class'] ?? ''));
  $containerClass = trim((string) ($scope['container_class'] ?? ''));
  if ($containerClass === '') {
    continue;
  }

  $searchHtml = $body;
  if ($parentClass !== '') {
    $parents = extract_class_blocks($body, $parentClass);
    if ($parents === []) {
      fwrite(STDERR, "$id: parent .$parentClass not found on $url\n");
      exit(1);
    }
    $searchHtml = implode("\n", $parents);
  }

  $containers = extract_class_blocks($searchHtml, $containerClass);
  if ($containers === []) {
    fwrite(STDERR, "$id: container .$containerClass not found on $url\n");
    exit(1);
  }

  $mustMatch = $scope['img_src_must_match'] ?? '/sites/default/files/';
  $mustNotMatch = $scope['img_src_must_not_match'] ?? [
    'images/figma/grid',
    'figma-baselines',
  ];
  if (!is_array($mustNotMatch)) {
    $mustNotMatch = [$mustNotMatch];
  }
  $minImages = (int) ($scope['min_count'] ?? 1);

  $imgCount = 0;
  foreach ($containers as $container) {
    if (!preg_match_all('/<img[^>]+src="([^"]+)"[^>]*>/i', $container, $imgs)) {
      continue;
    }
    foreach ($imgs[1] as $src) {
      $src = html_entity_decode(trim($src));
      if ($src === '') {
        fwrite(STDERR, "$id: empty img src in .$containerClass on $url\n");
        exit(1);
      }
      $imgCount++;
      if (is_string($mustMatch) && $mustMatch !== '' && !preg_match('#' . $mustMatch . '#i', $src)) {
        fwrite(STDERR, "$id: img src must match /$mustMatch/ but got '$src' in .$containerClass on $url\n");
        exit(1);
      }
      foreach ($mustNotMatch as $forbidden) {
        $forbidden = (string) $forbidden;
        if ($forbidden === '') {
          continue;
        }
        $regex = str_starts_with($forbidden, '/') ? $forbidden : '#' . preg_quote($forbidden, '#') . '#i';
        if (preg_match($regex, $src)) {
          fwrite(STDERR, "$id: img src must not match forbidden pattern '$forbidden' but got '$src' in .$containerClass on $url\n");
          exit(1);
        }
      }
    }
  }

  if ($imgCount < $minImages) {
    fwrite(STDERR, "$id: expected >= $minImages content img(s) in .$containerClass on $url (found $imgCount)\n");
    exit(1);
  }

  fwrite(STDOUT, "drupal: $id: OK — content images in .$containerClass use managed files on $url\n");
}

exit(0);
