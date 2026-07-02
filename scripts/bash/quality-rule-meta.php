<?php

/**
 * @file
 * Metadata for Drupal quality rules (stakeholder report).
 */

declare(strict_types=1);

/**
 * Rule metadata: category, default priority, stakeholder label.
 *
 * @return array<string, array{category: string, priority: string, name: string}>
 */
function quality_rule_meta(): array {
  return [
    'QR-FIGMA-000' => ['category' => 'Design', 'priority' => 'P1', 'name' => 'Figma design precedence'],
    'QR-FIGMA-001' => ['category' => 'Design', 'priority' => 'P0', 'name' => 'Figma copy, classes, and CSS hooks'],
    'QR-FIGMA-002' => ['category' => 'Design', 'priority' => 'P0', 'name' => 'Live-site screenshot diff (sections)'],
    'QR-FIGMA-003' => ['category' => 'Design', 'priority' => 'P0', 'name' => 'Figma component pixel diff'],
    'QR-PERF-001' => ['category' => 'Performance', 'priority' => 'P0', 'name' => 'Page load time budget'],
    'QR-PERF-002' => ['category' => 'Performance', 'priority' => 'P2', 'name' => 'Performance measurement method'],
    'QR-PERF-003' => ['category' => 'Performance', 'priority' => 'P2', 'name' => 'Anonymous page cache'],
    'QR-SMOKE-001' => ['category' => 'Smoke / Content', 'priority' => 'P0', 'name' => 'HTTP 200 on primary URLs'],
    'QR-SMOKE-002' => ['category' => 'Smoke / Content', 'priority' => 'P0', 'name' => 'Forbidden strings absent'],
    'QR-SMOKE-003' => ['category' => 'Smoke / Content', 'priority' => 'P0', 'name' => 'Required content markers'],
    'QR-SMOKE-004' => ['category' => 'Smoke / Content', 'priority' => 'P0', 'name' => 'No duplicate nav link text'],
    'QR-SMOKE-005' => ['category' => 'Smoke / Content', 'priority' => 'P0', 'name' => 'No duplicate nav hrefs'],
    'QR-SMOKE-006' => ['category' => 'Smoke / Content', 'priority' => 'P0', 'name' => 'Figma nav active state'],
    'QR-SMOKE-007' => ['category' => 'Smoke / Content', 'priority' => 'P0', 'name' => 'Header search icon markup'],
    'QR-SMOKE-008' => ['category' => 'Smoke / Content', 'priority' => 'P0', 'name' => 'Explore category icon markup'],
    'QR-SMOKE-009' => ['category' => 'Smoke / Content', 'priority' => 'P0', 'name' => 'Featured carousel arrow icons'],
    'QR-SMOKE-010' => ['category' => 'Smoke / Content', 'priority' => 'P0', 'name' => 'Content card images use managed files'],
    'QR-SMOKE-011' => ['category' => 'Smoke / Content', 'priority' => 'P0', 'name' => 'Composite form DOM structure'],
    'QR-THEME-001' => ['category' => 'Theme / Templates', 'priority' => 'P0', 'name' => 'No Figma baseline crops in node templates'],
    'QR-THEME-002' => ['category' => 'Theme / Templates', 'priority' => 'P0', 'name' => 'No preprocess figma_image overrides for cards'],
    'QR-THEME-003' => ['category' => 'Theme / Templates', 'priority' => 'P0', 'name' => 'Webform overrides preserve form wrapper'],
    'QR-LIB-001' => ['category' => 'Libraries', 'priority' => 'P0', 'name' => 'Required JS libraries reachable'],
    'QR-JS-001' => ['category' => 'JavaScript', 'priority' => 'P0', 'name' => 'Drupal behaviors present in page JS'],
    'QR-CSS-001' => ['category' => 'CSS / Layout', 'priority' => 'P0', 'name' => 'Views list grid on inner ul'],
    'QR-CSS-002' => ['category' => 'CSS / Layout', 'priority' => 'P0', 'name' => 'Views list reset selectors'],
    'QR-CSS-003' => ['category' => 'CSS / Layout', 'priority' => 'P0', 'name' => 'View pager flex layout'],
    'QR-CSS-004' => ['category' => 'CSS / Layout', 'priority' => 'P0', 'name' => 'Section max-width parity'],
    'QR-CSS-005' => ['category' => 'CSS / Layout', 'priority' => 'P0', 'name' => 'Figma component padding and gap'],
    'QR-CSS-006' => ['category' => 'CSS / Layout', 'priority' => 'P0', 'name' => 'Section margin alignment'],
    'QR-CSS-007' => ['category' => 'CSS / Layout', 'priority' => 'P0', 'name' => 'CTA pill button sizing'],
    'QR-CSS-008' => ['category' => 'CSS / Layout', 'priority' => 'P0', 'name' => 'Explore copy stack and section tags'],
    'QR-CSS-009' => ['category' => 'CSS / Layout', 'priority' => 'P0', 'name' => 'About and featured spacing'],
    'QR-CSS-010' => ['category' => 'CSS / Layout', 'priority' => 'P0', 'name' => 'Recipes tag pill colors'],
    'QR-CSS-011' => ['category' => 'CSS / Layout', 'priority' => 'P0', 'name' => 'Hero overlay and typography'],
    'QR-CSS-012' => ['category' => 'CSS / Layout', 'priority' => 'P0', 'name' => 'Section container border per Figma'],
    'QR-CSS-013' => ['category' => 'CSS / Layout', 'priority' => 'P0', 'name' => 'Section tag pill fit-content width'],
    'QR-CSS-014' => ['category' => 'CSS / Layout', 'priority' => 'P0', 'name' => 'Newsletter solid fill and overflow'],
    'QR-CSS-015' => ['category' => 'CSS / Layout', 'priority' => 'P0', 'name' => 'Newsletter form composite pill layout'],
    'QR-CSS-016' => ['category' => 'CSS / Layout', 'priority' => 'P0', 'name' => 'No CMS primitive styling on composite forms'],
    'QR-ASSET-001' => ['category' => 'Assets', 'priority' => 'P0', 'name' => 'Asset URLs return 2xx'],
    'QR-ASSET-002' => ['category' => 'Assets', 'priority' => 'P0', 'name' => 'No empty image src'],
    'QR-ASSET-003' => ['category' => 'Assets', 'priority' => 'P2', 'name' => 'Figma asset scopes'],
    'QR-ASSET-004' => ['category' => 'Assets', 'priority' => 'P0', 'name' => 'Image format integrity'],
    'QR-ASSET-005' => ['category' => 'Assets', 'priority' => 'P0', 'name' => 'Figma asset manifest populated'],
    'QR-ASSET-006' => ['category' => 'Assets', 'priority' => 'P0', 'name' => 'Atomic Figma components mapped'],
    'QR-A11Y-001' => ['category' => 'Accessibility', 'priority' => 'P1', 'name' => 'WCAG 2.1 AA automated scan (pa11y)'],
    'QR-A11Y-002' => ['category' => 'Accessibility', 'priority' => 'P1', 'name' => 'A11y gate deferral policy'],
    'QR-A11Y-003' => ['category' => 'Accessibility', 'priority' => 'P2', 'name' => 'Semantic HTML landmarks'],
  ];
}

/**
 * Expand compound rule ids (e.g. QR-CSS-001–003) to the first rule in range.
 */
function quality_rule_normalize_id(string $rule): string {
  if (preg_match('/^(QR-[A-Z]+)-(\d+)/', $rule, $m)) {
    return $m[1] . '-' . $m[2];
  }
  return $rule;
}

/**
 * @return array{category: string, priority: string, name: string}
 */
function quality_rule_lookup(string $rule): array {
  $meta = quality_rule_meta();
  $id = quality_rule_normalize_id($rule);
  if (isset($meta[$id])) {
    return $meta[$id];
  }
  if (preg_match('/^(QR-[A-Z]+)-\d+/', $id, $m)) {
    return [
      'category' => 'Quality',
      'priority' => 'P0',
      'name' => $id,
    ];
  }
  return [
    'category' => 'Other',
    'priority' => 'P2',
    'name' => $rule,
  ];
}
