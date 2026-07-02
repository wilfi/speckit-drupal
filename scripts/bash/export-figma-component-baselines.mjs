#!/usr/bin/env node
/**
 * Export Figma node PNGs via Figma Images API.
 * Usage: node export-figma-component-baselines.mjs <configJsonPath>
 */
import fs from 'node:fs';
import path from 'node:path';
import https from 'node:https';
import http from 'node:http';

const configPath = process.argv[2];
const { file_key: fileKey, out_dir: outDir, items = [], scale: exportScale = 2 } = JSON.parse(
  fs.readFileSync(configPath, 'utf8'),
);
const figmaScale = Number(exportScale) > 0 ? Number(exportScale) : 1;

if (!fileKey) {
  console.error('file_key missing in figma-design-checks.yml');
  process.exit(1);
}

fs.mkdirSync(outDir, { recursive: true });

const token = process.env.FIGMA_ACCESS_TOKEN || '';

function fetchJson(url, headers = {}) {
  return new Promise((resolve, reject) => {
    const lib = url.startsWith('https') ? https : http;
    lib
      .get(url, { headers }, (res) => {
        let data = '';
        res.on('data', (c) => (data += c));
        res.on('end', () => {
          try {
            resolve(JSON.parse(data));
          } catch (e) {
            reject(new Error(`Invalid JSON from ${url}: ${data.slice(0, 200)}`));
          }
        });
      })
      .on('error', reject);
  });
}

function downloadFile(url, dest) {
  return new Promise((resolve, reject) => {
    const lib = url.startsWith('https') ? https : http;
    lib
      .get(url, (res) => {
        if (res.statusCode && res.statusCode >= 300 && res.statusCode < 400 && res.headers.location) {
          downloadFile(res.headers.location, dest).then(resolve).catch(reject);
          return;
        }
        const chunks = [];
        res.on('data', (c) => chunks.push(c));
        res.on('end', () => {
          fs.writeFileSync(dest, Buffer.concat(chunks));
          resolve(dest);
        });
      })
      .on('error', reject);
  });
}

async function exportViaApi(nodeId, baseline) {
  if (!token) {
    throw new Error('FIGMA_ACCESS_TOKEN not set');
  }
  const id = encodeURIComponent(nodeId);
  const apiUrl = `https://api.figma.com/v1/images/${fileKey}?ids=${id}&format=png&scale=${figmaScale}`;
  const result = await fetchJson(apiUrl, { 'X-Figma-Token': token });
  if (result.err) {
    throw new Error(result.err);
  }
  const imageUrl = result.images?.[nodeId];
  if (!imageUrl) {
    throw new Error(`No image URL for node ${nodeId}`);
  }
  const dest = path.join(outDir, baseline);
  await downloadFile(imageUrl, dest);
  console.log(`exported (api): ${dest}`);
}

async function exportViaMcpFallback(nodeId, baseline) {
  // Node script cannot call MCP; check for manual curl instructions in README.
  const dest = path.join(outDir, baseline);
  if (fs.existsSync(dest)) {
    console.log(`skipped (exists): ${dest}`);
    return;
  }
  throw new Error(
    `Missing baseline ${baseline} for node ${nodeId}. Set FIGMA_ACCESS_TOKEN or download via Figma MCP download_assets and save to ${dest}`,
  );
}

for (const item of items) {
  const nodeId = item.figma_node_id;
  const baseline = item.baseline;
  if (!nodeId || !baseline) {
    continue;
  }
  try {
    if (token) {
      await exportViaApi(nodeId, baseline);
    } else {
      await exportViaMcpFallback(nodeId, baseline);
    }
  } catch (err) {
    if (token) {
      throw err;
    }
    await exportViaMcpFallback(nodeId, baseline);
  }
}
