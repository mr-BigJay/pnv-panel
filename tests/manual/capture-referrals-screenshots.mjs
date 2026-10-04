import { chromium } from 'playwright';
import { mkdir } from 'fs/promises';
import path from 'path';

const base = process.env.DEMO_BASE || 'http://127.0.0.1:8765';
const outDir = process.env.ARTIFACT_DIR || '/opt/cursor/artifacts';

await mkdir(outDir, { recursive: true });

const browser = await chromium.launch();
const context = await browser.newContext({
  locale: 'fa-IR',
});
const page = await context.newPage();

await page.goto(`${base}/bigjay_controller/index.php`, { waitUntil: 'networkidle' });
await page.fill('input[name="username"]', 'BigJay');
await page.fill('input[name="password"]', '603240@BigJayX');
await page.click('button[type="submit"]');
await page.waitForTimeout(800);

await page.goto(`${base}/bigjay_controller/referrals.php`, { waitUntil: 'networkidle' });
await page.waitForTimeout(1200);

await page.setViewportSize({ width: 1280, height: 900 });
await page.screenshot({ path: path.join(outDir, 'referrals-kpi-desktop.webp'), type: 'webp' });

await page.setViewportSize({ width: 390, height: 844 });
await page.screenshot({ path: path.join(outDir, 'referrals-kpi-mobile.webp'), type: 'webp' });

const firstLink = page.locator('a.refLink').first();
if (await firstLink.count()) {
  await firstLink.click();
  await page.waitForTimeout(600);
  await page.screenshot({ path: path.join(outDir, 'referrals-detail-mobile.webp'), type: 'webp' });
}

await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
await page.waitForTimeout(400);
await page.screenshot({ path: path.join(outDir, 'referrals-settings-mobile.webp'), type: 'webp' });

await browser.close();
console.log('referrals screenshots saved');
