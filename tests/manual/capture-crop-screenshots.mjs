import { chromium } from 'playwright';
import { mkdir, readFile } from 'fs/promises';
import path from 'path';

const base = process.env.DEMO_BASE || 'http://127.0.0.1:8765';
const outDir = process.env.ARTIFACT_DIR || '/opt/cursor/artifacts';
const portraitB64 = (await readFile(path.join(process.cwd(), 'tests/fixtures/support-test-image.jpg'))).toString('base64');

await mkdir(outDir, { recursive: true });
const browser = await chromium.launch();
const page = await (await browser.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2 })).newPage();

async function openEditWithOneFile(b64) {
  await page.goto(`${base}/tests/manual/support-attach-demo.html`, { waitUntil: 'networkidle' });
  await page.click('#openGallery');
  await page.waitForTimeout(200);
  await page.evaluate(async (b) => {
    const bin = atob(b);
    const arr = new Uint8Array(bin.length);
    for (let i = 0; i < bin.length; i++) arr[i] = bin.charCodeAt(i);
    const blob = new Blob([arr], { type: 'image/jpeg' });
    const file = new File([blob], 'portrait.jpg', { type: 'image/jpeg' });
    const picker = document.querySelector('[data-pnv-gallery-picker]');
    const dt = new DataTransfer();
    dt.items.add(file);
    picker.files = dt.files;
    picker.dispatchEvent(new Event('change', { bubbles: true }));
  }, b64);
  await page.waitForSelector('#pnvMediaEditor:not([hidden])');
}

await openEditWithOneFile(portraitB64);
await page.waitForTimeout(400);
await page.screenshot({ path: path.join(outDir, 'support-crop-portrait-default.webp'), type: 'webp' });

await page.evaluate(() => {
  const canvas = document.getElementById('pnvMediaCropCanvas');
  const rect = canvas.getBoundingClientRect();
  const start = { x: rect.left + rect.width * 0.85, y: rect.top + rect.height * 0.5 };
  const end = { x: rect.left + rect.width * 0.95, y: rect.top + rect.height * 0.5 };
  canvas.dispatchEvent(new MouseEvent('mousedown', { clientX: start.x, clientY: start.y, bubbles: true }));
  canvas.dispatchEvent(new MouseEvent('mousemove', { clientX: end.x, clientY: end.y, bubbles: true }));
  canvas.dispatchEvent(new MouseEvent('mouseup', { clientX: end.x, clientY: end.y, bubbles: true }));
});
await page.waitForTimeout(200);
await page.screenshot({ path: path.join(outDir, 'support-crop-wide-freeform.webp'), type: 'webp' });

console.log('saved crop screenshots');
await browser.close();
