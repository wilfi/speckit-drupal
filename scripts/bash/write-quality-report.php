<?php

/**
 * @file
 * Write stakeholder-facing quality-results.md for a feature.
 *
 * Usage: php write-quality-report.php FEATURE_DIR LOG_FILE [gate_status]
 */

declare(strict_types=1);

$projectRoot = getenv('PROJECT_ROOT') ?: getcwd();
$featureArg = $argv[1] ?? '';
$logFile = $argv[2] ?? '';
$gateStatus = $argv[3] ?? 'passed';

if ($featureArg === '' || $logFile === '') {
  fwrite(STDERR, "Usage: write-quality-report.php FEATURE_DIR LOG_FILE [gate_status]\n");
  exit(1);
}

$featureDir = str_starts_with($featureArg, '/')
  ? $featureArg
  : $projectRoot . '/' . ltrim($featureArg, '/');

if (!is_dir($featureDir)) {
  fwrite(STDERR, "Feature directory not found: $featureDir\n");
  exit(1);
}

$featureFlags = load_feature_quality_flags($featureDir);

$parseScript = __DIR__ . '/parse-quality-log.php';
$json = shell_exec('php ' . escapeshellarg($parseScript) . ' ' . escapeshellarg($logFile) . ' ' . escapeshellarg($gateStatus));
if ($json === NULL || $json === '') {
  fwrite(STDERR, "Failed to parse quality log\n");
  exit(1);
}

/** @var array<string, mixed> $data */
$data = json_decode($json, TRUE, 512, JSON_THROW_ON_ERROR);

$featureSlug = basename($featureDir);
$title = feature_title($featureDir) ?? $featureSlug;
$generated = gmdate('Y-m-d H:i:s') . ' UTC';
$gate = ($data['gate'] ?? 'failed') === 'passed';
$summary = $data['summary'] ?? [];
if (!empty($featureFlags['figma_source_of_truth']) || !empty($featureFlags['defer_a11y_to_figma'])) {
  $data['figma_source_of_truth'] = TRUE;
  $data['defer_a11y_to_figma'] = TRUE;
}

$warnP1 = 0;
$warnP2 = 0;
foreach ($data['entries'] ?? [] as $entry) {
  if (($entry['status'] ?? '') !== 'warn') {
    continue;
  }
  $p = $entry['priority'] ?? 'P2';
  if ($p === 'P1') {
    $warnP1++;
  }
  else {
    $warnP2++;
  }
}
$byPriority = ['P0' => [], 'P1' => [], 'P2' => []];
$byCategory = [];

foreach ($data['entries'] ?? [] as $entry) {
  $status = $entry['status'] ?? 'pass';
  if ($status === 'pass' || $status === 'info') {
    continue;
  }
  $priority = $entry['priority'] ?? 'P2';
  if ($status === 'fail') {
    $priority = 'P0';
  }
  if (!isset($byPriority[$priority])) {
    $byPriority[$priority] = [];
  }
  $byPriority[$priority][] = $entry;

  $cat = $entry['category'] ?? 'Other';
  $byCategory[$cat][] = $entry;
}

$passByCategory = [];
foreach ($data['entries'] ?? [] as $entry) {
  if (($entry['status'] ?? '') !== 'pass') {
    continue;
  }
  $cat = $entry['category'] ?? 'Other';
  $passByCategory[$cat] = ($passByCategory[$cat] ?? 0) + 1;
}

$out = [];
$out[] = '# Quality Check Results — ' . $title;
$out[] = '';
$out[] = '| Field | Value |';
$out[] = '|-------|-------|';
$out[] = '| **Feature** | `' . relative_feature_path($projectRoot, $featureDir) . '` |';
$out[] = '| **Generated** | ' . $generated . ' |';
$out[] = '| **Gate** | ' . ($gate ? '✅ **PASSED**' : '❌ **FAILED**') . ' |';
$out[] = '| **Figma source of truth** | ' . (!empty($data['figma_source_of_truth']) ? 'Yes (QR-FIGMA-000)' : 'No') . ' |';
$out[] = '| **Command** | `.specify/extensions/drupal/scripts/bash/verify-quality.sh` |';
$out[] = '';
$out[] = '## Summary';
$out[] = '';
$out[] = '| Status | Count | Priority | Stakeholder action |';
$out[] = '|--------|------:|----------|-------------------|';
$out[] = '| ✅ Passed | ' . (int) ($summary['pass'] ?? 0) . ' | P0 checks | None — release criteria met |';
$out[] = '| ⚠️ Warnings (P1) | ' . $warnP1 . ' | P1 | Review; gate may still pass when Figma takes precedence |';
$out[] = '| ⚠️ Warnings (P2) | ' . $warnP2 . ' | P2 | Environment / informational — awareness only |';
$out[] = '| ❌ Failed | ' . (int) ($summary['fail'] ?? 0) . ' | P0 | **Must fix** before release |';
$out[] = '';
$out[] = '### Priority legend';
$out[] = '';
$out[] = '| Priority | Meaning |';
$out[] = '|----------|---------|';
$out[] = '| **P0** | Blocker — fails the quality gate; must be fixed before handoff |';
$out[] = '| **P1** | Warning — review with design/QA; tolerated when QR-FIGMA-000 applies |';
$out[] = '| **P2** | Informational — environment or policy notes |';
$out[] = '';

$out[] = '## Stakeholder review';
$out[] = '';
$out[] = stakeholder_section('P0 — Blockers (must fix before release)', $byPriority['P0'], 'fail');
$out[] = stakeholder_section('P1 — Warnings (review; gate may still pass)', $byPriority['P1'], 'warn');
$out[] = stakeholder_section('P2 — Informational', $byPriority['P2'], 'info');

$out[] = '## Passed checks by category';
$out[] = '';
if ($passByCategory === []) {
  $out[] = '_No passed checks recorded._';
}
else {
  ksort($passByCategory);
  $out[] = '| Category | Passed checks |';
  $out[] = '|----------|--------------:|';
  foreach ($passByCategory as $cat => $count) {
    $out[] = '| ' . $cat . ' | ' . $count . ' |';
  }
}
$out[] = '';

$out[] = '## Detailed results';
$out[] = '';
$out[] = detail_table($data['entries'] ?? []);

$out[] = '## QA checklist';
$out[] = '';
$out[] = '- [ ] Confirm **P0** items are empty (or resolved) before stakeholder sign-off';
$out[] = '- [ ] Review **P1** warnings — document accepted Figma vs a11y trade-offs if any';
$out[] = '- [ ] Spot-check primary URL(s) in browser at desktop and mobile widths';
$out[] = '- [ ] Compare live UI to Figma file when visual parity is in scope';
$out[] = '';

$reportPath = $featureDir . '/quality-results.md';
file_put_contents($reportPath, implode("\n", $out) . "\n");

fwrite(STDOUT, "drupal: QA report written: " . relative_feature_path($projectRoot, $reportPath) . "\n");

/**
 * @param array<int, array<string, mixed>> $items
 */
function stakeholder_section(string $heading, array $items, string $defaultStatus): string {
  $lines = ['### ' . $heading, ''];
  if ($items === []) {
    $lines[] = '_None._';
    $lines[] = '';
    return implode("\n", $lines);
  }
  foreach ($items as $item) {
    $rule = $item['rule'] ?? 'QR';
    $name = $item['name'] ?? $rule;
    $message = $item['message'] ?? '';
    $icon = ($item['status'] ?? $defaultStatus) === 'fail' ? '❌' : '⚠️';
    $lines[] = '- ' . $icon . ' **' . $rule . '** — ' . $name . ': ' . $message;
  }
  $lines[] = '';
  return implode("\n", $lines);
}

/**
 * @param array<int, array<string, mixed>> $entries
 */
function detail_table(array $entries): string {
  if ($entries === []) {
    return '_No checks recorded._';
  }
  $lines = [
    '| Rule | Category | Status | Priority | Detail |',
    '|------|----------|--------|----------|--------|',
  ];
  foreach ($entries as $entry) {
    $status = $entry['status'] ?? 'pass';
    $icon = match ($status) {
      'pass' => '✅ pass',
      'warn' => '⚠️ warn',
      'fail' => '❌ fail',
      'info' => 'ℹ️ info',
      default => $status,
    };
    $detail = str_replace('|', '\\|', $entry['message'] ?? '');
    if (strlen($detail) > 120) {
      $detail = substr($detail, 0, 117) . '...';
    }
    $lines[] = sprintf(
      '| %s | %s | %s | %s | %s |',
      $entry['rule'] ?? '',
      $entry['category'] ?? '',
      $icon,
      $entry['priority'] ?? '',
      $detail
    );
  }
  return implode("\n", $lines);
}

function feature_title(string $featureDir): ?string {
  $spec = $featureDir . '/spec.md';
  if (!is_file($spec)) {
    return NULL;
  }
  $content = file_get_contents($spec);
  if ($content === FALSE) {
    return NULL;
  }
  if (preg_match('/^#\s+Feature Specification:\s*(.+)$/m', $content, $m)) {
    return trim($m[1]);
  }
  if (preg_match('/^#\s+(.+)$/m', $content, $m)) {
    return trim($m[1]);
  }
  return NULL;
}

function relative_feature_path(string $root, string $path): string {
  $root = rtrim($root, '/') . '/';
  if (str_starts_with($path, $root)) {
    return substr($path, strlen($root));
  }
  return $path;
}

/**
 * @return array{figma_source_of_truth: bool, defer_a11y_to_figma: bool}
 */
function load_feature_quality_flags(string $featureDir): array {
  $flags = [
    'figma_source_of_truth' => FALSE,
    'defer_a11y_to_figma' => FALSE,
  ];
  $checks = $featureDir . '/quality-checks.yml';
  if (!is_file($checks)) {
    return $flags;
  }
  $content = file_get_contents($checks);
  if ($content === FALSE) {
    return $flags;
  }
  if (preg_match('/^\s*source_of_truth:\s*true\s*$/m', $content)) {
    $flags['figma_source_of_truth'] = TRUE;
  }
  if (preg_match('/^\s*defer_to_figma:\s*true\s*$/m', $content)) {
    $flags['defer_a11y_to_figma'] = TRUE;
  }
  return $flags;
}
