<?php

/**
 * @file
 * Parse verify-quality.sh log output into structured check results.
 *
 * Usage: php parse-quality-log.php LOG_FILE [gate_status]
 * Output: JSON on stdout.
 */

declare(strict_types=1);

require __DIR__ . '/quality-rule-meta.php';

$logFile = $argv[1] ?? '';
$gateStatus = $argv[2] ?? 'passed';

if ($logFile === '' || !is_file($logFile)) {
  fwrite(STDERR, "parse-quality-log.php: missing log file\n");
  exit(1);
}

$lines = file($logFile, FILE_IGNORE_NEW_LINES) ?: [];
$entries = [];
$gatePassed = $gateStatus !== 'failed';
$figmaPrecedence = FALSE;
$deferA11y = FALSE;

foreach ($lines as $line) {
  $line = trim($line);
  if ($line === '') {
    continue;
  }

  if (str_contains($line, 'QR-FIGMA-000: Figma design is source of truth')) {
    $figmaPrecedence = TRUE;
    $deferA11y = TRUE;
    $entries[] = [
      'rule' => 'QR-FIGMA-000',
      'status' => 'info',
      'message' => 'Figma design is source of truth — automated a11y failures are warnings only',
      'priority' => 'P1',
      'category' => 'Design',
      'name' => 'Figma design precedence',
    ];
    continue;
  }

  if (preg_match('/^drupal: QUALITY FAIL: (.+)$/', $line, $m)) {
    $gatePassed = FALSE;
    $rule = extract_rule_id($m[1]) ?? 'QR-GATE';
    $meta = quality_rule_lookup($rule);
    $entries[] = entry($rule, 'fail', $m[1], $meta['priority'], $meta['category'], $meta['name']);
    continue;
  }

  if (preg_match('/^drupal: WARN: (.+)$/', $line, $m)) {
    $rule = extract_rule_id($m[1]) ?? 'ENV';
    $meta = quality_rule_lookup($rule);
    $priority = 'P2';
    if ($rule === 'QR-A11Y-001' || str_contains($m[1], 'QR-FIGMA-000') || str_contains($m[1], 'tolerated under QR-FIGMA-000')) {
      $priority = 'P1';
    }
    $entries[] = entry($rule, 'warn', $m[1], $priority, $meta['category'], $meta['name']);
    continue;
  }

  if (preg_match('/^drupal: (QR-[A-Z]+-\d+(?:–\d+)?):.*\bOK\b/i', $line, $m)
    || preg_match('/^drupal: (QR-[A-Z]+-\d+(?:–\d+)?):.*→ HTTP 200/i', $line, $m)
    || preg_match('/^drupal: (QR-[A-Z]+-\d+(?:–\d+)?):.*complete/i', $line, $m)) {
    $rule = quality_rule_normalize_id($m[1]);
    $meta = quality_rule_lookup($rule);
    $entries[] = entry($rule, 'pass', strip_prefix($line), $meta['priority'], $meta['category'], $meta['name']);
    continue;
  }

  if (preg_match('/^drupal: (QR-[A-Z]+-\d+(?:–\d+)?):/', $line, $m)) {
    $rule = quality_rule_normalize_id($m[1]);
    $meta = quality_rule_lookup($rule);
    if (preg_match('/exceeded|missing|not found|failed|returned HTTP [^2]/i', $line)) {
      $gatePassed = FALSE;
      $entries[] = entry($rule, 'fail', strip_prefix($line), 'P0', $meta['category'], $meta['name']);
    }
    elseif (preg_match('/→ HTTP/i', $line)) {
      $entries[] = entry($rule, 'pass', strip_prefix($line), $meta['priority'], $meta['category'], $meta['name']);
    }
    else {
      $entries[] = entry($rule, 'pass', strip_prefix($line), $meta['priority'], $meta['category'], $meta['name']);
    }
  }
}

if (str_contains(implode("\n", $lines), 'Quality verification passed.')) {
  $gatePassed = TRUE;
}

$summary = summarize($entries);

echo json_encode([
  'gate' => $gatePassed ? 'passed' : 'failed',
  'figma_source_of_truth' => $figmaPrecedence,
  'defer_a11y_to_figma' => $deferA11y,
  'summary' => $summary,
  'entries' => $entries,
], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);

function extract_rule_id(string $text): ?string {
  if (preg_match('/(QR-[A-Z]+-\d+(?:–\d+)?)/', $text, $m)) {
    return quality_rule_normalize_id($m[1]);
  }
  return NULL;
}

function strip_prefix(string $line): string {
  return preg_replace('/^drupal:\s*/', '', $line) ?? $line;
}

/**
 * @return array<string, mixed>
 */
function entry(string $rule, string $status, string $message, string $priority, string $category, string $name): array {
  return [
    'rule' => $rule,
    'status' => $status,
    'message' => $message,
    'priority' => $priority,
    'category' => $category,
    'name' => $name,
  ];
}

/**
 * @param array<int, array<string, mixed>> $entries
 * @return array<string, int>
 */
function summarize(array $entries): array {
  $counts = ['pass' => 0, 'warn' => 0, 'fail' => 0, 'info' => 0];
  foreach ($entries as $e) {
    $status = $e['status'] ?? 'pass';
    if (!isset($counts[$status])) {
      $counts[$status] = 0;
    }
    $counts[$status]++;
  }
  return $counts;
}
