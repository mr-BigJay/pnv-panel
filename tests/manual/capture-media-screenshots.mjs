import { chromium } from 'playwright';
import { mkdir, readFile } from 'fs/promises';
import path from 'path';

const base = process.env.DEMO_BASE || 'http://127.0.0.1:8765';
const outDir = process.env.ARTIFACT_DIR || '/opt/cursor/artifacts';
const fixturePath = path.join(process.cwd(), 'tests/fixtures/support-test-image.jpg');

await mkdir(outDir, { recursive: true });
const imgB64 = (await readFile(fixturePath)).toString('base64');

const browser = await chromium.launch();
const context = await browser.newContext({
  viewport: { width: 390, height: 844 },
  deviceScaleFactor: 2,
  locale: 'fa-IR'
});
const page = await context.newPage();
await page.goto(`${base}/tests/manual/support-attach-demo.html`, { waitUntil: 'networkidle' });

async function injectGalleryFile() {
  await page.evaluate(async (b64) => {
    const bin = atob(b64);
    const arr = new Uint8Array(bin.length);
    for (let i = 0; i < bin.length; i++) arr[i] = bin.charCodeAt(i);
    const blob = new Blob([arr], { type: 'image/jpeg' });
    const file = new File([blob], 'support-test-image.jpg', { type: 'image/jpeg' });
    const form = document.getElementById('demoForm');
    const picker = form.querySelector('[data-pnv-gallery-picker]');
    if (!picker) return;
    const dt = new DataTransfer();
    dt.items.add(file);
    picker.files = dt.files;
    picker.dispatchEvent(new Event('change', { bubbles: true }));
  }, imgB64);
}

await page.click('#openGallery');
await page.waitForSelector('#pnvMediaGallery:not([hidden])', { timeout: 8000 });
await injectGalleryFile();
await page.waitForSelector('.pnv-media-gallery-cell', { timeout: 5000 });
await page.waitForTimeout(250);
await page.screenshot({
  path: path.join(outDir, 'support-media-gallery-mobile.webp'),
  type: 'webp'
});
console.log('saved support-media-gallery-mobile.webp');

await page.click('.pnv-media-gallery-cell');
await page.waitForSelector('#pnvMediaEditor:not([hidden])', { timeout: 5000 });
await page.waitForTimeout(600);
await page.screenshot({
  path: path.join(outDir, 'support-media-edit-mobile.webp'),
  type: 'webp'
});
console.log('saved support-media-edit-mobile.webp');

await page.click('#pnvMediaEditApply');
await page.waitForSelector('#pnvMediaPreview:not([hidden])', { timeout: 5000 });
await page.waitForTimeout(400);
await page.screenshot({
  path: path.join(outDir, 'support-media-preview-mobile.webp'),
  type: 'webp'
});
console.log('saved support-media-preview-mobile.webp');

const cropSample = await page.evaluate(async () => {
  const img = document.getElementById('pnvMediaPreviewImg');
  if (!img || !img.complete) return null;
  const canvas = document.createElement('canvas');
  canvas.width = Math.min(240, img.naturalWidth || 240);
  canvas.height = Math.min(240, img.naturalHeight || 240);
  canvas.getContext('2d').drawImage(img, 0, 0, canvas.width, canvas.height);
  return canvas.toDataURL('image/jpeg', 0.92);
});

if (cropSample) {
  const raw = cropSample.replace(/^data:image\/\w+;base64,/, '');
  const { writeFile } = await import('fs/promises');
  await writeFile(path.join(outDir, 'support-media-crop-sample.jpg'), Buffer.from(raw, 'base64'));
  console.log('saved support-media-crop-sample.jpg');
}

await browser.close();
