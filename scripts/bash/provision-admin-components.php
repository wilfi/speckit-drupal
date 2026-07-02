<?php

/**
 * @file
 * Idempotent post-implement admin provisioning from drupal-admin-checklist.yml.
 *
 * Usage: drush php:script provision-admin-components.php [FEATURE_DIR]
 */

use Drupal\Core\File\FileSystemInterface;
use Drupal\file\Entity\File;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\node\Entity\Node;
use Symfony\Component\Yaml\Yaml;

$feature_dir = $extra[0] ?? getenv('FEATURE_DIR') ?: '';
if ($feature_dir === '' && file_exists(DRUPAL_ROOT . '/../.specify/feature.json')) {
  $feature_json = json_decode(file_get_contents(DRUPAL_ROOT . '/../.specify/feature.json'), TRUE);
  $feature_dir = $feature_json['feature_directory'] ?? '';
}

$checklist_path = $feature_dir
  ? DRUPAL_ROOT . '/../' . trim($feature_dir, '/') . '/drupal-admin-checklist.yml'
  : '';

if ($checklist_path === '' || !file_exists($checklist_path)) {
  echo "No drupal-admin-checklist.yml found (feature: " . ($feature_dir ?: 'none') . "). Skipping.\n";
  return;
}

$config = Yaml::parseFile($checklist_path);
$provision = $config['admin_provision'] ?? [];
if (empty($provision['enabled'])) {
  echo "Admin provisioning disabled in checklist.\n";
  return;
}

$created = 0;
$skipped = 0;
$warnings = 0;
$errors = [];

/**
 * Return TRUE when a menu link with the same URI exists in the menu.
 */
function spec_provision_menu_link_exists($storage, string $menu_name, string $title, string $uri): bool {
  foreach ($storage->loadByProperties(['menu_name' => $menu_name]) as $link) {
    if (($link->get('link')->uri ?? '') === $uri) {
      return TRUE;
    }
  }
  return FALSE;
}

/**
 * Ensure a menu link exists.
 */
function spec_provision_ensure_menu_link($storage, string $menu_name, string $title, string $uri, int $weight = 0): string {
  if (spec_provision_menu_link_exists($storage, $menu_name, $title, $uri)) {
    return 'skipped';
  }
  MenuLinkContent::create([
    'title' => $title,
    'link' => ['uri' => $uri],
    'menu_name' => $menu_name,
    'weight' => $weight,
    'expanded' => FALSE,
  ])->save();
  return 'created';
}

/**
 * Find basic page by title.
 */
function spec_provision_find_page(string $title): ?Node {
  $nodes = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
    'type' => 'page',
    'title' => $title,
  ]);
  return $nodes ? reset($nodes) : NULL;
}

/**
 * Copy theme image to public files.
 */
function spec_provision_theme_file(string $theme, string $relative_path, string $dest_name): ?File {
  $source = DRUPAL_ROOT . '/themes/custom/' . $theme . '/' . ltrim($relative_path, '/');
  if (!file_exists($source)) {
    return NULL;
  }
  $data = file_get_contents($source);
  $directory = 'public://provision';
  \Drupal::service('file_system')->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);
  $uri = \Drupal::service('file_system')->saveData($data, $directory . '/' . $dest_name, FileSystemInterface::EXISTS_REPLACE);
  if (!$uri) {
    return NULL;
  }
  $file = File::create(['uri' => $uri, 'status' => 1]);
  $file->save();
  return $file;
}

// Menu links.
$menu_storage = \Drupal::entityTypeManager()->getStorage('menu_link_content');
foreach ($provision['menu_links'] ?? [] as $link) {
  $menu = $link['menu'] ?? 'main';
  $title = $link['title'] ?? '';
  $uri = $link['uri'] ?? '';
  $weight = (int) ($link['weight'] ?? 0);
  if ($title === '' || $uri === '') {
    $warnings++;
    continue;
  }
  $result = spec_provision_ensure_menu_link($menu_storage, $menu, $title, $uri, $weight);
  echo ($result === 'created' ? 'Created' : 'Skipped') . " menu link: $title ($menu)\n";
  $result === 'created' ? $created++ : $skipped++;
}

// Basic pages.
foreach ($provision['nodes'] ?? [] as $node_cfg) {
  $type = $node_cfg['type'] ?? 'page';
  $title = $node_cfg['title'] ?? '';
  $path = $node_cfg['path'] ?? '';
  $body = $node_cfg['body'] ?? '';
  if ($title === '') {
    $warnings++;
    continue;
  }
  $node = spec_provision_find_page($title);
  if (!$node) {
    $node = Node::create([
      'type' => $type,
      'title' => $title,
      'status' => 1,
      'body' => [
        'value' => $body,
        'format' => 'basic_html',
      ],
    ]);
    $node->save();
    echo "Created node: $title\n";
    $created++;
  }
  else {
    echo "Skipped node (exists): $title\n";
    $skipped++;
  }
  if ($path !== '') {
    $alias_manager = \Drupal::service('path_alias.manager');
    $system_path = '/node/' . $node->id();
    $existing = $alias_manager->getAliasByPath($system_path);
    if ($existing === $system_path || $existing !== '/' . $path) {
      \Drupal::entityTypeManager()->getStorage('path_alias')->create([
        'path' => $system_path,
        'alias' => '/' . ltrim($path, '/'),
        'langcode' => $node->language()->getId(),
      ])->save();
      echo "Set path alias: /$path for $title\n";
    }
  }
}

// Recipe images from theme figma directory.
$recipe_cfg = $provision['recipe_images'] ?? NULL;
if (!empty($recipe_cfg['source_dir'])) {
  $theme = $recipe_cfg['theme'] ?? 'cooks_delight';
  $source_dir = $recipe_cfg['source_dir'];
  $glob_path = DRUPAL_ROOT . '/themes/custom/' . $theme . '/' . trim($source_dir, '/') . '/recipe-*.{png,jpg,jpeg}';
  $files_on_disk = array_merge(
    glob(str_replace('{png,jpg,jpeg}', 'png', $glob_path)) ?: [],
    glob(str_replace('{png,jpg,jpeg}', 'jpg', $glob_path)) ?: [],
    glob(str_replace('{png,jpg,jpeg}', 'jpeg', $glob_path)) ?: [],
  );
  sort($files_on_disk);
  $file_entities = [];
  foreach ($files_on_disk as $idx => $disk_path) {
    $basename = basename($disk_path);
    $relative = $source_dir . '/' . $basename;
    $entity = spec_provision_theme_file($theme, $relative, 'recipe-provision-' . ($idx + 1) . '-' . $basename);
    if ($entity) {
      $file_entities[] = $entity;
    }
  }
  if (empty($file_entities)) {
    echo "Warning: no recipe images found in theme $source_dir\n";
    $warnings++;
  }
  else {
    $nids = \Drupal::entityTypeManager()->getStorage('node')->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'recipe')
      ->sort('nid')
      ->execute();
    $idx = 0;
    foreach (\Drupal::entityTypeManager()->getStorage('node')->loadMultiple($nids) as $recipe_node) {
      $file = $file_entities[$idx % count($file_entities)];
      $recipe_node->set('field_image', ['target_id' => $file->id(), 'alt' => $recipe_node->label()]);
      $recipe_node->save();
      $idx++;
    }
    echo "Attached recipe images to " . count($nids) . " recipes.\n";
    $created += count($nids);
  }
}

// Webform checks.
foreach ($provision['webforms'] ?? [] as $webform_cfg) {
  $id = $webform_cfg['id'] ?? '';
  if ($id === '') {
    continue;
  }
  $webform = \Drupal::entityTypeManager()->getStorage('webform')->load($id);
  if (!$webform) {
    $msg = "Webform '$id' not found";
    if (!empty($webform_cfg['required'])) {
      $errors[] = $msg;
      echo "ERROR: $msg\n";
    }
    else {
      echo "Warning: $msg\n";
      $warnings++;
    }
  }
  else {
    echo "Webform OK: $id\n";
  }
}

// Verify hints (informational — MCP skill performs live checks).
foreach ($provision['verify'] ?? [] as $check) {
  $tool = $check['tool'] ?? '';
  if ($tool === 'mcp_tools_get_menu_tree') {
    $menu = $check['menu'] ?? 'main';
    $expect = $check['expect_titles'] ?? [];
    $links = $menu_storage->loadByProperties(['menu_name' => $menu]);
    $titles = array_map(fn($l) => $l->getTitle(), $links);
    foreach ($expect as $expected_title) {
      if (!in_array($expected_title, $titles, TRUE)) {
        $errors[] = "Menu '$menu' missing link: $expected_title";
        echo "FAIL verify: menu '$menu' missing '$expected_title'\n";
      }
      else {
        echo "OK verify: menu '$menu' has '$expected_title'\n";
      }
    }
  }
}

echo "\nProvision summary: created=$created skipped=$skipped warnings=$warnings errors=" . count($errors) . "\n";

if (!empty($errors)) {
  throw new \RuntimeException('Admin provisioning failed: ' . implode('; ', $errors));
}
