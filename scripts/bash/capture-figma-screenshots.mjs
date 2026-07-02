#!/usr/bin/env node
/**
 * Capture page, section, and component screenshots via Playwright.
 * Usage: node capture-figma-screenshots.mjs <configJsonPath> <outputDir> <baseUrl> [mode]
 * mode: all | components (default all)
 */
import fs from 'node:fs';
import path from 'node:path';
import { chromium } from 'playwright';

const [configPath, outputDir, baseUrl, modeArg] = process.argv.slice(2);
const mode = modeArg || 'all';
const config = JSON.parse(fs.readFileSync(configPath, 'utf8'));
const screenshot = config.screenshot || {};
const viewport = screenshot.viewport || { width: 1440, height: 900 };
const fullPage = screenshot.full_page !== false;
const components = screenshot.components || {};
const stabilize = components.stabilize || {};

fs.mkdirSync(outputDir, { recursive: true });

const browser = await chromium.launch();
const page = await browser.newPage({
  viewport: { width: viewport.width, height: viewport.height },
});

async function stabilizePage() {
  await page.evaluate(() => {
    document.documentElement.classList.add('figma-screenshot-mode');
  });
}

async function preparePage(url) {
  await page.goto(url, { waitUntil: 'networkidle', timeout: 60000 });
  await page.evaluate(async () => {
    await document.fonts.ready;
    window.scrollTo(0, document.body.scrollHeight);
    await new Promise((r) => setTimeout(r, 400));
    window.scrollTo(0, 0);
  });
  await page.waitForTimeout(500);
}

if (mode === 'all' || mode === 'pages') {
  for (const pageCfg of screenshot.pages || []) {
    const url = new URL(pageCfg.path || '/', baseUrl).toString();
    await preparePage(url);
    const out = path.join(outputDir, pageCfg.baseline || 'page.png');
    await page.screenshot({ path: out, fullPage });
    console.log(`captured: ${out}`);
  }
}

if (mode === 'all' || mode === 'sections') {
  for (const section of screenshot.sections || []) {
    const pagePath = section.page || '/';
    const url = new URL(pagePath, baseUrl).toString();
    await preparePage(url);
    await stabilizePage();
    const locator = page.locator(section.selector).first();
    await locator.waitFor({ state: 'visible', timeout: 15000 });
    const out = path.join(outputDir, section.baseline || `${section.name}.png`);
    await locator.screenshot({ path: out });
    console.log(`captured section ${section.name}: ${out}`);
  }
}

if ((mode === 'all' || mode === 'components') && components.enabled !== false) {
  const items = components.items || [];
  const pagePath = stabilize.menu_active_path || '/';
  const url = new URL(pagePath, baseUrl).toString();
  await preparePage(url);
  await stabilizePage();

  for (const item of items) {
    const locator = page.locator(item.selector).first();
    await locator.waitFor({ state: 'visible', timeout: 15000 });
    const out = path.join(outputDir, item.baseline || `${item.name}.png`);
    await locator.screenshot({ path: out });
    console.log(`captured component ${item.name}: ${out}`);
  }
}

await browser.close();
