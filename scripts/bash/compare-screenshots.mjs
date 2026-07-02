#!/usr/bin/env node
/**
 * Compare two PNGs with pixelmatch. Writes diff PNG on failure.
 * Scales actual to baseline size when within 5% dimension delta.
 * Usage: node compare-screenshots.mjs <baseline> <actual> <diffOut> <maxDiffPercent> [threshold]
 */
import fs from 'node:fs';
import { PNG } from 'pngjs';
import pixelmatch from 'pixelmatch';

const [baselinePath, actualPath, diffPath, maxDiffPercentArg, thresholdArg] = process.argv.slice(2);
const maxDiffPercent = parseFloat(maxDiffPercentArg || '15', 10);
const threshold = parseFloat(thresholdArg || '0.15', 10);

function readPng(filePath) {
  return PNG.sync.read(fs.readFileSync(filePath));
}

function crop(png, w, h) {
  if (png.width === w && png.height === h) {
    return png;
  }
  const out = new PNG({ width: w, height: h });
  for (let y = 0; y < h; y++) {
    for (let x = 0; x < w; x++) {
      const i = (w * y + x) << 2;
      const j = (png.width * y + x) << 2;
      out.data[i] = png.data[j];
      out.data[i + 1] = png.data[j + 1];
      out.data[i + 2] = png.data[j + 2];
      out.data[i + 3] = png.data[j + 3];
    }
  }
  return out;
}

/** Nearest-neighbor scale to target dimensions. */
function scaleTo(png, targetW, targetH) {
  if (png.width === targetW && png.height === targetH) {
    return png;
  }
  const out = new PNG({ width: targetW, height: targetH });
  const xRatio = png.width / targetW;
  const yRatio = png.height / targetH;
  for (let y = 0; y < targetH; y++) {
    for (let x = 0; x < targetW; x++) {
      const sx = Math.min(png.width - 1, Math.floor(x * xRatio));
      const sy = Math.min(png.height - 1, Math.floor(y * yRatio));
      const si = (png.width * sy + sx) << 2;
      const di = (targetW * y + x) << 2;
      out.data[di] = png.data[si];
      out.data[di + 1] = png.data[si + 1];
      out.data[di + 2] = png.data[si + 2];
      out.data[di + 3] = png.data[si + 3];
    }
  }
  return out;
}

function withinPercent(a, b, pct) {
  if (a === 0 || b === 0) {
    return a === b;
  }
  return Math.abs(a - b) / Math.max(a, b) <= pct / 100;
}

const img1 = readPng(baselinePath);
let img2 = readPng(actualPath);

const wClose = withinPercent(img1.width, img2.width, 5);
const hClose = withinPercent(img1.height, img2.height, 5);

if (wClose && hClose && (img1.width !== img2.width || img1.height !== img2.height)) {
  img2 = scaleTo(img2, img1.width, img1.height);
}

const width = Math.min(img1.width, img2.width);
const height = Math.min(img1.height, img2.height);

const a = crop(img1, width, height);
const b = crop(img2, width, height);
const diff = new PNG({ width, height });

const diffPixels = pixelmatch(a.data, b.data, diff.data, width, height, {
  threshold,
  includeAA: true,
});

const totalPixels = width * height;
const diffPercent = (diffPixels / totalPixels) * 100;

if (diffPercent > maxDiffPercent) {
  fs.mkdirSync(pathDir(diffPath), { recursive: true });
  fs.writeFileSync(diffPath, PNG.sync.write(diff));
  console.error(`FAIL: ${diffPercent.toFixed(2)}% diff (max ${maxDiffPercent}%) — wrote ${diffPath}`);
  process.exit(1);
}

console.log(`OK: ${diffPercent.toFixed(2)}% diff (max ${maxDiffPercent}%)`);
process.exit(0);

function pathDir(p) {
  return p.replace(/\/[^/]+$/, '');
}
