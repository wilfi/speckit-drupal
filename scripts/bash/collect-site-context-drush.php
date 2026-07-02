#!/usr/bin/env php
<?php

/**
 * @file
 * Collect live Drupal site context into JSON bundle (Drush bootstrap).
 *
 * Usage: drush php:script collect-site-context-drush.php [OUTPUT_JSON]
 *   OUTPUT_JSON defaults to ../.specify/drupal/generated/site-context-bundle.json
 */

use Drupal\Core\Url;
use Drupal\user\Entity\User;

$projectRoot = dirname(DRUPAL_ROOT);
$outArg = $extra[0] ?? '';
$out = $outArg !== ''
  ? ($outArg[0] === '/' ? $outArg : $projectRoot . '/' . ltrim($outArg, '/'))
  : $projectRoot . '/.specify/drupal/generated/site-context-bundle.json';
$outDir = dirname($out);
if (!is_dir($outDir)) {
  mkdir($outDir, 0755, TRUE);
}

$siteId = 'default';
$sitesYml = $projectRoot . '/.specify/drupal/sites.yml';
if (is_file($sitesYml)) {
  $content = file_get_contents($sitesYml);
  if (preg_match('/^default_site:\s*(\S+)/m', $content, $m)) {
    $siteId = trim($m[1]);
  }
}

$baseUrl = '';
if (command_exists('ddev') && is_dir($projectRoot . '/.ddev')) {
  $baseUrl = trim((string) shell_exec('cd ' . escapeshellarg($projectRoot) . ' && ddev describe 2>/dev/null | grep -oE "https://[^ ]+\\.ddev\\.site" | head -1'));
}

/** @var \Drupal\Core\Config\ConfigFactoryInterface $configFactory */
$configFactory = \Drupal::configFactory();
$siteConfig = $configFactory->get('system.site');
$themeConfig = $configFactory->get('system.theme');

/** @var \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler */
$moduleHandler = \Drupal::service('module_handler');
/** @var \Drupal\Core\Extension\ThemeHandlerInterface $themeHandler */
$themeHandler = \Drupal::service('theme_handler');
/** @var \Drupal\Core\Entity\EntityFieldManagerInterface $efm */
$efm = \Drupal::service('entity_field.manager');
/** @var \Drupal\Core\Entity\EntityTypeManagerInterface $etm */
$etm = \Drupal::entityTypeManager();

$defaultTheme = $themeConfig->get('default') ?: 'stark';
$drupalVersion = \Drupal::VERSION;
$phpVersion = PHP_VERSION;

// Content types.
$types = [];
foreach ($etm->getStorage('node_type')->loadMultiple() as $type) {
  $id = $type->id();
  $defs = $efm->getFieldDefinitions('node', $id);
  $fields = [];
  foreach ($defs as $name => $def) {
    if ($def->isComputed() || in_array($name, ['nid', 'uuid', 'vid', 'langcode', 'type', 'revision_timestamp', 'revision_uid', 'revision_log', 'status', 'uid', 'created', 'changed', 'promote', 'sticky', 'default_langcode', 'revision_default', 'revision_translation_affected'], TRUE)) {
      continue;
    }
    $fields[] = [
      'name' => $name,
      'label' => $def->getLabel(),
      'type' => $def->getType(),
      'required' => $def->isRequired(),
    ];
  }
  $types[] = [
    'id' => $id,
    'label' => $type->label(),
    'description' => $type->getDescription(),
    'field_count' => count($fields),
    'fields' => $fields,
  ];
}

// Vocabularies + terms.
$vocabItems = [];
$termsByVocab = [];
foreach ($etm->getStorage('taxonomy_vocabulary')->loadMultiple() as $vocab) {
  $vid = $vocab->id();
  $vocabItems[] = [
    'id' => $vid,
    'label' => $vocab->label(),
    'description' => $vocab->getDescription(),
  ];
  $termRows = [];
  $terms = $etm->getStorage('taxonomy_term')->loadByProperties(['vid' => $vid]);
  foreach ($terms as $term) {
    $termRows[] = [
      'tid' => $term->id(),
      'name' => $term->label(),
    ];
  }
  $termsByVocab[$vid] = ['data' => ['terms' => $termRows]];
}

// Views from config names.
$viewItems = [];
$viewNames = [];
foreach ($configFactory->listAll('views.view.') as $name) {
  $viewNames[] = $name;
  $machine = str_replace('views.view.', '', $name);
  $viewConfig = $configFactory->get($name);
  $viewItems[] = [
    'id' => $machine,
    'label' => $viewConfig->get('label') ?: $machine,
    'status' => $viewConfig->get('status') ? 'enabled' : 'disabled',
    'base_table' => $viewConfig->get('base_table') ?: '',
  ];
}

// Block content types.
$blockTypeNames = array_values($configFactory->listAll('block_content.type.'));

// Roles.
$roleItems = [];
foreach ($etm->getStorage('user_role')->loadMultiple() as $role) {
  $perms = $role->isAdmin() ? [] : $role->getPermissions();
  $roleItems[] = [
    'id' => $role->id(),
    'label' => $role->label(),
    'is_admin' => $role->isAdmin(),
    'permission_count' => $role->isAdmin() ? 0 : count($perms),
    'permissions' => array_values($perms),
  ];
}

// Menus.
$menuItems = [];
$menuTrees = [];
$menuStorage = $etm->getStorage('menu');
$treeParams = \Drupal::menuTree()->getCurrentRouteMenuTreeParameters('main');
foreach ($menuStorage->loadMultiple() as $menu) {
  $mid = $menu->id();
  $menuItems[] = ['id' => $mid, 'label' => $menu->label()];
  $tree = build_menu_tree($mid);
  if ($tree !== []) {
    $menuTrees[$mid] = ['data' => ['tree' => $tree]];
  }
}

// Regions + blocks for default theme.
$regionList = [];
if ($themeHandler->themeExists($defaultTheme)) {
  /** @var \Drupal\Core\Extension\ThemeExtensionList $themeList */
  $themeList = \Drupal::service('extension.list.theme');
  $extension = $themeList->get($defaultTheme);
  $regionLabels = $extension->info['regions'] ?? [];
  $blockStorage = $etm->getStorage('block');
  $blocks = $blockStorage->loadByProperties(['theme' => $defaultTheme]);
  $byRegion = [];
  foreach ($blocks as $block) {
    $rid = $block->getRegion();
    $byRegion[$rid][] = [
      'block_id' => $block->id(),
      'plugin_id' => $block->getPluginId(),
      'weight' => $block->getWeight(),
      'status' => $block->status(),
    ];
  }
  foreach ($regionLabels as $rid => $label) {
    $regionList[] = [
      'id' => $rid,
      'label' => $label,
      'blocks' => $byRegion[$rid] ?? [],
      'block_count' => count($byRegion[$rid] ?? []),
    ];
  }
}

// Media types.
$mediaTypes = [];
if ($moduleHandler->moduleExists('media')) {
  foreach ($etm->getStorage('media_type')->loadMultiple() as $mt) {
    $mediaTypes[] = ['id' => $mt->id(), 'label' => $mt->label()];
  }
}

// Users (summary, no emails required).
$userRows = [];
$userQuery = $etm->getStorage('user')->getQuery()->accessCheck(FALSE)->range(0, 25);
foreach ($userQuery->execute() as $uid) {
  $user = User::load($uid);
  if (!$user) {
    continue;
  }
  $userRows[] = [
    'uid' => $user->id(),
    'name' => $user->getAccountName(),
    'roles' => $user->getRoles(),
    'status' => $user->isActive() ? 'active' : 'blocked',
  ];
}

// Config drift.
$configChanges = [];
$sync = $configFactory->get('core.extension');
$hasChanges = FALSE;
$totalChanges = 0;
if (\Drupal::moduleHandler()->moduleExists('config')) {
  try {
    $syncStorage = \Drupal::service('config.storage.sync');
    $activeStorage = \Drupal::service('config.storage');
    $changelist = \Drupal::service('config.manager')->getChangelist();
    foreach (['create', 'update', 'delete', 'rename'] as $op) {
      $totalChanges += count($changelist[$op] ?? []);
      foreach (array_slice($changelist[$op] ?? [], 0, 10) as $item) {
        $configChanges[] = ['name' => is_array($item) ? ($item['name'] ?? '') : $item, 'operation' => $op];
      }
    }
    $hasChanges = $totalChanges > 0;
  }
  catch (\Throwable $e) {
    // Ignore if sync unavailable.
  }
}

$cron = \Drupal::state()->get('system.cron_last');
$cronStatus = $cron && (time() - $cron) < 86400 ? 'healthy' : 'stale';

$snapshot = [
  'site' => [
    'name' => $siteConfig->get('name'),
    'uuid' => $siteConfig->get('uuid'),
    'drupal_version' => $drupalVersion,
    'php_version' => $phpVersion,
    'install_profile' => \Drupal::installProfile(),
    'maintenance_mode' => (bool) $configFactory->get('system.maintenance')->get('enabled'),
  ],
  'database' => [
    'driver' => \Drupal::database()->driver(),
    'version' => \Drupal::database()->version(),
  ],
  'cron' => [
    'last_run' => $cron ? date('Y-m-d H:i:s', $cron) : '',
    'last_run_timestamp' => $cron ?: 0,
    'seconds_since_last_run' => $cron ? time() - $cron : 0,
    'status' => $cronStatus,
  ],
  'blueprint' => [
    'content_types' => ['total' => count($types), 'items' => $types],
    'vocabularies' => ['total' => count($vocabItems), 'items' => $vocabItems],
    'roles' => ['total' => count($roleItems), 'items' => $roleItems],
    'views' => ['total' => count($viewItems), 'items' => $viewItems],
    'menus' => ['total' => count($menuItems), 'items' => $menuItems],
    'themes' => [
      'default' => $defaultTheme,
      'admin' => $themeConfig->get('admin') ?: 'claro',
      'enabled' => array_keys($themeHandler->listInfo()),
      'total_enabled' => count($themeHandler->listInfo()),
    ],
  ],
  'config_drift' => [
    'has_changes' => $hasChanges,
    'total_changes' => $totalChanges,
    'sample' => $configChanges,
    'sync_directory_exists' => is_dir($projectRoot . '/config/sync'),
  ],
];

$bundle = [
  'generated_at' => gmdate('Y-m-d\TH:i:s\Z'),
  'collector' => 'drush',
  'site_id' => $siteId,
  'base_url' => $baseUrl,
  'snapshot' => $snapshot,
  'content_types' => ['data' => ['types' => $types, 'total_types' => count($types)]],
  'vocabularies' => ['data' => ['vocabularies' => $vocabItems]],
  'terms' => $termsByVocab,
  'views_config' => ['data' => ['names' => $viewNames, 'total' => count($viewNames)]],
  'block_content_types' => ['names' => $blockTypeNames],
  'roles' => ['data' => ['roles' => $roleItems, 'total_roles' => count($roleItems)]],
  'menus' => ['data' => ['menus' => $menuItems]],
  'menu_trees' => $menuTrees,
  'regions' => ['data' => ['theme' => $defaultTheme, 'regions' => $regionList, 'count' => count($regionList)]],
  'media_types' => ['data' => ['media_types' => $mediaTypes]],
  'users' => ['data' => ['users' => $userRows, 'total_users' => count($userRows)]],
  'site_status' => [
    'data' => [
      'drupal_version' => $drupalVersion,
      'php_version' => $phpVersion,
      'site_name' => $siteConfig->get('name'),
      'maintenance_mode' => (bool) $configFactory->get('system.maintenance')->get('enabled'),
      'cron' => $snapshot['cron'],
      'database' => $snapshot['database'],
    ],
  ],
];

file_put_contents($out, json_encode($bundle, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
echo "drupal: Collected site context → $out\n";

/**
 * Build a simple menu tree for bundle output.
 */
function build_menu_tree(string $menuId): array {
  $tree = [];
  try {
    $parameters = \Drupal::menuTree()->getCurrentRouteMenuTreeParameters($menuId);
    $parameters->setMaxDepth(3);
    $treeData = \Drupal::menuTree()->load($menuId, $parameters);
    $manipulators = [
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
    ];
    $treeData = \Drupal::menuTree()->transform($treeData, $manipulators);
    $tree = flatten_menu_tree($treeData);
  }
  catch (\Throwable $e) {
    return [];
  }
  return $tree;
}

function flatten_menu_tree(array $elements): array {
  $out = [];
  foreach ($elements as $element) {
    if (empty($element['link'])) {
      continue;
    }
    $link = $element['link'];
    $url = '';
    try {
      $url = $link->getUrlObject()->toString();
    }
    catch (\Throwable $e) {
      $url = $link->getUri();
    }
    $item = [
      'title' => $link->getTitle(),
      'url' => $url,
    ];
    if (!empty($element['below'])) {
      $item['children'] = flatten_menu_tree($element['below']);
    }
    $out[] = $item;
  }
  return $out;
}

function command_exists(string $cmd): bool {
  $path = trim((string) shell_exec('command -v ' . escapeshellarg($cmd) . ' 2>/dev/null'));
  return $path !== '';
}
