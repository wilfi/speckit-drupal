#!/usr/bin/env php
<?php

/**
 * @file
 * Write .specify/drupal/ project context from MCP site-context bundle JSON.
 *
 * Usage: php write-site-context.php BUNDLE_JSON [PROJECT_ROOT]
 */

declare(strict_types=1);

$bundlePath = $argv[1] ?? '';
$projectRoot = $argv[2] ?? getenv('PROJECT_ROOT') ?: getcwd();

if ($bundlePath === '' || !is_file($bundlePath)) {
  fwrite(STDERR, "Usage: php write-site-context.php BUNDLE_JSON [PROJECT_ROOT]\n");
  exit(1);
}

$raw = file_get_contents($bundlePath);
if ($raw === FALSE) {
  fwrite(STDERR, "Cannot read bundle: $bundlePath\n");
  exit(1);
}

/** @var array<string, mixed> $bundle */
$bundle = json_decode($raw, TRUE);
if (!is_array($bundle)) {
  fwrite(STDERR, "Invalid JSON bundle\n");
  exit(1);
}

$outDir = rtrim($projectRoot, '/') . '/.specify/drupal';
$genDir = $outDir . '/generated';
if (!is_dir($genDir)) {
  mkdir($genDir, 0755, TRUE);
}

$timestamp = $bundle['generated_at'] ?? gmdate('Y-m-d\TH:i:s\Z');
$siteId = $bundle['site_id'] ?? 'default';

file_put_contents($genDir . '/site-context-bundle.json', json_encode($bundle, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

write_data_model($outDir . '/data-model.md', $bundle, $timestamp);
write_site_structure($outDir . '/site-structure.md', $bundle, $timestamp);
write_site_status($outDir . '/site-status.md', $bundle, $timestamp);
merge_sites_yml($outDir . '/sites.yml', $bundle, $siteId);
update_readme($outDir . '/README.md', $timestamp);

fwrite(STDOUT, "drupal: Site context written to .specify/drupal/\n");
fwrite(STDOUT, "drupal: Bundle archive: .specify/drupal/generated/site-context-bundle.json\n");

function mcp_data(array $bundle, string $key): array {
  $v = $bundle[$key] ?? [];
  if (!is_array($v)) {
    return [];
  }
  if (isset($v['data']) && is_array($v['data'])) {
    return $v['data'];
  }
  return $v;
}

function write_data_model(string $path, array $bundle, string $ts): void {
  $snapshot = $bundle['snapshot'] ?? [];
  $site = is_array($snapshot['site'] ?? NULL) ? $snapshot['site'] : [];
  $siteName = $site['name'] ?? 'Drupal site';
  $ct = mcp_data($bundle, 'content_types');
  $types = $ct['types'] ?? $snapshot['blueprint']['content_types']['items'] ?? [];
  $vocabs = mcp_data($bundle, 'vocabularies');
  $vocabItems = $vocabs['vocabularies'] ?? $snapshot['blueprint']['vocabularies']['items'] ?? [];
  $terms = $bundle['terms'] ?? [];
  $media = mcp_data($bundle, 'media_types');
  $mediaTypes = $media['media_types'] ?? $media['types'] ?? [];
  $blockTypes = $bundle['block_content_types']['names'] ?? [];
  $roles = mcp_data($bundle, 'roles');
  $roleItems = $roles['roles'] ?? $snapshot['blueprint']['roles']['items'] ?? [];
  $views = $snapshot['blueprint']['views']['items'] ?? [];
  $viewNames = $bundle['views_config']['names'] ?? [];

  $lines = [];
  $lines[] = '# Project data model — Drupal';
  $lines[] = '';
  $lines[] = '**Generated**: ' . $ts . ' via `generate-site-context` (Drupal MCP)';
  $lines[] = '**Site**: ' . $siteName;
  $lines[] = '';
  $lines[] = '> Auto-generated from live site. Review before commit; merge intentional hand-edits.';
  $lines[] = '';

  $lines[] = '## Content types (nodes)';
  $lines[] = '';
  foreach ($types as $type) {
    if (!is_array($type)) {
      continue;
    }
    $id = $type['id'] ?? 'unknown';
    $label = $type['label'] ?? $id;
    $lines[] = '### ' . $label . ' (`' . $id . '`)';
    $lines[] = '';
    if (!empty($type['description'])) {
      $lines[] = strip_tags((string) $type['description']);
      $lines[] = '';
    }
    $fields = $type['fields'] ?? [];
    if ($fields !== []) {
      $lines[] = '| Field | Machine name | Type | Required |';
      $lines[] = '|-------|--------------|------|----------|';
      foreach ($fields as $f) {
        if (!is_array($f)) {
          continue;
        }
        $lines[] = sprintf(
          '| %s | `%s` | %s | %s |',
          $f['label'] ?? $f['name'] ?? '',
          $f['name'] ?? '',
          $f['type'] ?? '',
          !empty($f['required']) ? 'Yes' : 'No'
        );
      }
      $lines[] = '';
    }
  }

  if ($blockTypes !== []) {
    $lines[] = '## Block content types (config)';
    $lines[] = '';
    foreach ($blockTypes as $name) {
      $machine = str_replace('block_content.type.', '', (string) $name);
      $lines[] = '- `' . $machine . '`';
    }
    $lines[] = '';
  }

  if ($mediaTypes !== []) {
    $lines[] = '## Media types';
    $lines[] = '';
    foreach ($mediaTypes as $mt) {
      if (is_array($mt)) {
        $lines[] = '- `' . ($mt['id'] ?? $mt['name'] ?? 'media') . '` — ' . ($mt['label'] ?? '');
      }
      else {
        $lines[] = '- `' . $mt . '`';
      }
    }
    $lines[] = '';
  }

  $lines[] = '## Taxonomy vocabularies';
  $lines[] = '';
  foreach ($vocabItems as $v) {
    if (!is_array($v)) {
      continue;
    }
    $vid = $v['id'] ?? '';
    $lines[] = '### ' . ($v['label'] ?? $vid) . ' (`' . $vid . '`)';
    if (isset($terms[$vid]) && is_array($terms[$vid])) {
      $termData = $terms[$vid]['data']['terms'] ?? $terms[$vid]['terms'] ?? $terms[$vid];
      if (is_array($termData)) {
        $lines[] = '';
        $lines[] = '| Term | ID |';
        $lines[] = '|------|-----|';
        foreach ($termData as $t) {
          if (!is_array($t)) {
            continue;
          }
          $lines[] = '| ' . ($t['name'] ?? $t['label'] ?? '') . ' | ' . ($t['tid'] ?? $t['id'] ?? '') . ' |';
        }
      }
    }
    $lines[] = '';
  }

  $lines[] = '## Views';
  $lines[] = '';
  if ($views !== []) {
    $lines[] = '| View | Label | Status | Base table |';
    $lines[] = '|------|-------|--------|------------|';
    foreach ($views as $view) {
      if (!is_array($view)) {
        continue;
      }
      $lines[] = sprintf(
        '| `%s` | %s | %s | %s |',
        $view['id'] ?? '',
        $view['label'] ?? '',
        $view['status'] ?? '',
        $view['base_table'] ?? ''
      );
    }
  }
  elseif ($viewNames !== []) {
    foreach ($viewNames as $n) {
      $lines[] = '- `' . str_replace('views.view.', '', (string) $n) . '`';
    }
  }
  $lines[] = '';

  $lines[] = '## Roles & permissions';
  $lines[] = '';
  foreach ($roleItems as $role) {
    if (!is_array($role)) {
      continue;
    }
    $lines[] = '### ' . ($role['label'] ?? $role['id'] ?? 'role');
    $lines[] = '';
    $perms = $role['permissions'] ?? [];
    if ($perms === [] && !empty($role['is_admin'])) {
      $lines[] = '_All permissions (administrator)_';
    }
    elseif ($perms !== []) {
      foreach ($perms as $p) {
        $lines[] = '- `' . $p . '`';
      }
    }
    else {
      $lines[] = '_(' . (int) ($role['permission_count'] ?? 0) . ' permissions — see generated bundle)_';
    }
    $lines[] = '';
  }

  file_put_contents($path, implode("\n", $lines) . "\n");
}

function write_site_structure(string $path, array $bundle, string $ts): void {
  $snapshot = $bundle['snapshot'] ?? [];
  $themes = $snapshot['blueprint']['themes'] ?? [];
  $defaultTheme = $themes['default'] ?? 'unknown';
  $regions = mcp_data($bundle, 'regions');
  $regionList = $regions['regions'] ?? [];
  $menus = mcp_data($bundle, 'menus');
  $menuItems = $menus['menus'] ?? $snapshot['blueprint']['menus']['items'] ?? [];
  $menuTrees = $bundle['menu_trees'] ?? [];

  $lines = [];
  $lines[] = '# Site structure — Drupal';
  $lines[] = '';
  $lines[] = '**Generated**: ' . $ts . ' via `generate-site-context` (Drupal MCP)';
  $lines[] = '**Default theme**: `' . $defaultTheme . '`';
  $lines[] = '';

  $lines[] = '## Theme regions & block placement';
  $lines[] = '';
  foreach ($regionList as $region) {
    if (!is_array($region)) {
      continue;
    }
    $rid = $region['id'] ?? '';
    $lines[] = '### ' . ($region['label'] ?? $rid) . ' (`' . $rid . '`)';
    $lines[] = '';
    $blocks = $region['blocks'] ?? [];
    if ($blocks === []) {
      $lines[] = '_No blocks_';
    }
    else {
      $lines[] = '| Block ID | Plugin | Weight | Enabled |';
      $lines[] = '|----------|--------|--------|---------|';
      foreach ($blocks as $b) {
        if (!is_array($b)) {
          continue;
        }
        $lines[] = sprintf(
          '| `%s` | `%s` | %s | %s |',
          $b['block_id'] ?? '',
          $b['plugin_id'] ?? '',
          $b['weight'] ?? '',
          !empty($b['status']) ? 'yes' : 'no'
        );
      }
    }
    $lines[] = '';
  }

  $lines[] = '## Menus';
  $lines[] = '';
  foreach ($menuItems as $menu) {
    if (!is_array($menu)) {
      continue;
    }
    $mid = $menu['id'] ?? '';
    $lines[] = '### ' . ($menu['label'] ?? $mid) . ' (`' . $mid . '`)';
    if (isset($menuTrees[$mid])) {
      $tree = $menuTrees[$mid]['data']['tree'] ?? $menuTrees[$mid]['tree'] ?? $menuTrees[$mid];
      $lines[] = '';
      $lines[] = '```text';
      $lines[] = format_menu_tree($tree, 0);
      $lines[] = '```';
    }
    $lines[] = '';
  }

  $lines[] = '## Enabled themes';
  $lines[] = '';
  foreach ($themes['enabled'] ?? [] as $t) {
    $lines[] = '- `' . $t . '`' . ($t === $defaultTheme ? ' **(default)**' : '');
  }
  $lines[] = '';

  file_put_contents($path, implode("\n", $lines) . "\n");
}

function format_menu_tree(mixed $tree, int $depth): string {
  if (!is_array($tree)) {
    return '';
  }
  $out = '';
  $pad = str_repeat('  ', $depth);
  foreach ($tree as $item) {
    if (!is_array($item)) {
      continue;
    }
    $title = $item['title'] ?? $item['label'] ?? '?';
    $url = $item['url'] ?? $item['uri'] ?? '';
    $out .= $pad . '- ' . $title . ($url !== '' ? ' → ' . $url : '') . "\n";
    if (!empty($item['children'])) {
      $out .= format_menu_tree($item['children'], $depth + 1);
    }
  }
  return $out;
}

function write_site_status(string $path, array $bundle, string $ts): void {
  $snapshot = $bundle['snapshot'] ?? [];
  $site = $snapshot['site'] ?? [];
  $status = mcp_data($bundle, 'site_status');
  $sys = mcp_data($bundle, 'system_status');
  $req = $snapshot['requirements'] ?? mcp_data($bundle, 'system_requirements');
  $drift = $snapshot['config_drift'] ?? mcp_data($bundle, 'config_status');
  $users = mcp_data($bundle, 'users');
  $cron = $status['cron'] ?? $snapshot['cron'] ?? [];

  $lines = [];
  $lines[] = '# Site status — Drupal';
  $lines[] = '';
  $lines[] = '**Generated**: ' . $ts . ' via `generate-site-context` (Drupal MCP)';
  $lines[] = '';

  $lines[] = '## Versions & environment';
  $lines[] = '';
  $lines[] = '| Item | Value |';
  $lines[] = '|------|-------|';
  $lines[] = '| Site name | ' . ($site['name'] ?? $status['site_name'] ?? '') . ' |';
  $lines[] = '| Drupal | ' . ($site['drupal_version'] ?? $status['drupal_version'] ?? '') . ' |';
  $lines[] = '| PHP | ' . ($site['php_version'] ?? $status['php_version'] ?? '') . ' |';
  $lines[] = '| Install profile | ' . ($site['install_profile'] ?? '') . ' |';
  $lines[] = '| Maintenance mode | ' . (!empty($site['maintenance_mode'] ?? $status['maintenance_mode']) ? 'Yes' : 'No') . ' |';
  $db = $status['database'] ?? $snapshot['database'] ?? [];
  if ($db !== []) {
    $lines[] = '| Database | ' . ($db['driver'] ?? '') . ' ' . ($db['version'] ?? '') . ' |';
  }
  $lines[] = '';

  $lines[] = '## Cron';
  $lines[] = '';
  if ($cron !== []) {
    $lines[] = '| Field | Value |';
    $lines[] = '|-------|-------|';
    foreach (['last_run', 'status', 'seconds_since_last_run'] as $k) {
      if (isset($cron[$k])) {
        $lines[] = '| ' . $k . ' | ' . $cron[$k] . ' |';
      }
    }
  }
  $lines[] = '';

  if ($req !== []) {
    $lines[] = '## System requirements';
    $lines[] = '';
    $summary = $req['summary'] ?? $req;
    if (is_array($summary)) {
      $lines[] = '| Check | Count |';
      $lines[] = '|-------|------:|';
      foreach (['ok', 'warning', 'error', 'info'] as $k) {
        if (isset($summary[$k])) {
          $lines[] = '| ' . $k . ' | ' . $summary[$k] . ' |';
        }
      }
    }
    $lines[] = '';
  }

  if ($drift !== []) {
    $lines[] = '## Config sync drift';
    $lines[] = '';
    $lines[] = '| Has changes | ' . (!empty($drift['has_changes']) ? 'Yes' : 'No') . ' |';
    $lines[] = '| Total changes | ' . (int) ($drift['total_changes'] ?? 0) . ' |';
    if (!empty($drift['sample']) && is_array($drift['sample'])) {
      $lines[] = '';
      $lines[] = 'Sample changes:';
      foreach ($drift['sample'] as $c) {
        if (is_array($c)) {
          $lines[] = '- `' . ($c['name'] ?? '') . '` (' . ($c['operation'] ?? '') . ')';
        }
      }
    }
    $lines[] = '';
  }

  if ($users !== []) {
    $lines[] = '## Users (summary)';
    $lines[] = '';
    $userList = $users['users'] ?? [];
    $lines[] = '| Total returned | ' . (int) ($users['total_users'] ?? count($userList)) . ' |';
    if ($userList !== []) {
      $lines[] = '';
      $lines[] = '| UID | Name | Roles | Status |';
      $lines[] = '|-----|------|-------|--------|';
      foreach (array_slice($userList, 0, 25) as $u) {
        if (!is_array($u)) {
          continue;
        }
        $roles = is_array($u['roles'] ?? NULL) ? implode(', ', $u['roles']) : ($u['roles'] ?? '');
        $lines[] = sprintf(
          '| %s | %s | %s | %s |',
          $u['uid'] ?? '',
          $u['name'] ?? $u['username'] ?? '',
          $roles,
          $u['status'] ?? ''
        );
      }
    }
    $lines[] = '';
  }

  file_put_contents($path, implode("\n", $lines) . "\n");
}

function merge_sites_yml(string $path, array $bundle, string $siteId): void {
  $snapshot = $bundle['snapshot'] ?? [];
  $site = $snapshot['site'] ?? [];
  $themes = $snapshot['blueprint']['themes'] ?? [];
  $baseUrl = $bundle['base_url'] ?? '';

  $existing = [];
  if (is_file($path) && function_exists('yaml_parse_file')) {
    $existing = yaml_parse_file($path) ?: [];
  }

  if ($existing === [] && is_file($path)) {
    $existing = parse_sites_yml_simple(file_get_contents($path) ?: '');
  }

  if (!isset($existing['sites'])) {
    $existing['sites'] = [];
  }
  if (!isset($existing['default_site']) || $existing['default_site'] === '') {
    $existing['default_site'] = $siteId;
  }

  $entry = $existing['sites'][$siteId] ?? [];
  $entry['label'] = $site['name'] ?? $entry['label'] ?? $siteId;
  $entry['drupal_site_dir'] = $entry['drupal_site_dir'] ?? 'default';
  $entry['theme'] = $themes['default'] ?? $entry['theme'] ?? '';
  if ($baseUrl !== '') {
    $entry['base_url'] = $baseUrl;
  }
  $entry['drupal_version'] = $site['drupal_version'] ?? $entry['drupal_version'] ?? '';
  $entry['site_uuid'] = $site['uuid'] ?? $entry['site_uuid'] ?? '';
  $entry['last_context_sync'] = $bundle['generated_at'] ?? gmdate('c');
  $existing['sites'][$siteId] = $entry;

  file_put_contents($path, render_sites_yml($existing));
}

function parse_sites_yml_simple(string $content): array {
  $data = ['default_site' => 'default', 'multisite_enabled' => FALSE, 'sites' => []];
  if (preg_match('/^default_site:\s*(\S+)/m', $content, $m)) {
    $data['default_site'] = trim($m[1]);
  }
  if (preg_match('/^multisite_enabled:\s*(true|false)/m', $content, $m)) {
    $data['multisite_enabled'] = $m[1] === 'true';
  }
  return $data;
}

function render_sites_yml(array $data): string {
  $lines = [];
  $lines[] = '# Drupal sites manifest — project-level (Spec Kit)';
  $lines[] = '# Updated by generate-site-context (Drupal MCP)';
  $lines[] = '';
  $lines[] = 'default_site: ' . ($data['default_site'] ?? 'default');
  $lines[] = '';
  $lines[] = 'multisite_enabled: ' . (!empty($data['multisite_enabled']) ? 'true' : 'false');
  $lines[] = '';
  $lines[] = 'sites:';
  foreach ($data['sites'] ?? [] as $id => $site) {
    if (!is_array($site)) {
      continue;
    }
    $lines[] = '  ' . $id . ':';
    foreach ($site as $k => $v) {
      if (is_array($v)) {
        $lines[] = '    ' . $k . ':';
        foreach ($v as $item) {
          $lines[] = '      - ' . yaml_scalar($item);
        }
      }
      else {
        $lines[] = '    ' . $k . ': ' . yaml_scalar($v);
      }
    }
    $lines[] = '';
  }
  return implode("\n", $lines) . "\n";
}

function yaml_scalar(mixed $v): string {
  if (is_bool($v)) {
    return $v ? 'true' : 'false';
  }
  if (is_numeric($v)) {
    return (string) $v;
  }
  $s = (string) $v;
  if ($s === '' || preg_match('/[:#\[\]{}|>&*!%@`]/', $s)) {
    return '"' . str_replace('"', '\\"', $s) . '"';
  }
  return $s;
}

function update_readme(string $path, string $ts): void {
  $extra = <<<'MD'

## Regenerate from live site

```bash
/generate-site-context
```

Uses Drupal MCP to refresh `data-model.md`, `site-structure.md`, `site-status.md`, and `sites.yml`.
Last sync: {{TS}}

MD;
  $extra = str_replace('{{TS}}', $ts, $extra);
  if (!is_file($path)) {
    file_put_contents($path, "# Drupal project context\n" . $extra);
    return;
  }
  $content = file_get_contents($path) ?: '';
  if (!str_contains($content, '## Regenerate from live site')) {
    file_put_contents($path, rtrim($content) . "\n" . $extra);
  }
  else {
    $content = preg_replace(
      '/Last sync: .*/',
      'Last sync: ' . $ts,
      $content
    ) ?? $content;
    file_put_contents($path, $content);
  }
}
