#!/usr/bin/env node
/**
 * Export Figma theme assets from figma-asset-manifest.yml (PNG via MCP export URLs).
 *
 * Usage: export-figma-theme-assets.mjs <FEATURE_DIR> [PROJECT_ROOT]
 *
 * Re-export URLs expire after ~7 days — refresh via Figma MCP download_assets.
 */

import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const projectRoot = process.argv[3] || process.env.PROJECT_ROOT || path.resolve(__dirname, '../../../..');
const featureDir = process.argv[2] || '';
const manifestPath = path.join(projectRoot, featureDir, 'figma-asset-manifest.yml');
const PNG_SIG = Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]);

function parseYaml(text) {
  const lines = text.split('\n');
  const assets = [];
  let theme = '';
  let current = null;
  for (const line of lines) {
    if (/^theme:\s*(.+)/.test(line)) {
      theme = line.replace(/^theme:\s*/, '').replace(/^["']|["']$/g, '').trim();
      continue;
    }
    const nodeMatch = line.match(/^\s*-\s*figma_node_id:\s*['"]?([^'"\s]+)/);
    if (nodeMatch) {
      if (current) assets.push(current);
      current = { figma_node_id: nodeMatch[1] };
      continue;
    }
    if (!current) continue;
    const kv = line.match(/^\s*(\w+):\s*(.+)/);
    if (kv) {
      current[kv[1]] = kv[2].replace(/^["']|["']$/g, '').trim();
    }
  }
  if (current) assets.push(current);
  return { theme, assets };
}

async function loadManifest() {
  if (!fs.existsSync(manifestPath)) {
    throw new Error(`Missing manifest: ${manifestPath}`);
  }
  const text = fs.readFileSync(manifestPath, 'utf8');
  try {
    const yaml = await import('yaml');
    return yaml.parse(text) || {};
  } catch {
    return parseYaml(text);
  }
}

async function download(url) {
  const res = await fetch(url);
  if (!res.ok) {
    throw new Error(`HTTP ${res.status}`);
  }
  return Buffer.from(await res.arrayBuffer());
}

if (!featureDir) {
  console.error('Usage: export-figma-theme-assets.mjs <FEATURE_DIR> [PROJECT_ROOT]');
  process.exit(1);
}

const manifest = await loadManifest();
const theme = manifest.theme || '';
const assets = Array.isArray(manifest.assets) ? manifest.assets : [];
if (!theme) {
  console.error('figma-asset-manifest.yml: theme is required');
  process.exit(1);
}
if (assets.length === 0) {
  console.error('figma-asset-manifest.yml: assets[] is empty — populate during /speckit-plan (see figma-asset-export.md)');
  process.exit(1);
}

const themeRoot = path.join(projectRoot, 'web/themes/custom', theme);
let failed = 0;

for (const asset of assets) {
  const dest = path.join(themeRoot, asset.theme_path || '');
  if (!asset.theme_path || !asset.export_url) {
    console.warn(`Skip incomplete entry: ${asset.figma_node_id || 'unknown'}`);
    failed++;
    continue;
  }
  fs.mkdirSync(path.dirname(dest), { recursive: true });
  try {
    const data = await download(asset.export_url);
    if (!data.slice(0, 8).equals(PNG_SIG)) {
      throw new Error('not a PNG — use Figma download_assets, not get_design_context SVG URLs');
    }
    fs.writeFileSync(dest, data);
    console.log(`OK ${asset.theme_path} (${data.length} bytes)`);
  } catch (err) {
    console.error(`FAIL ${asset.theme_path}: ${err.message}`);
    failed++;
  }
}

if (failed > 0) process.exit(1);
console.log('Figma theme assets exported.');
