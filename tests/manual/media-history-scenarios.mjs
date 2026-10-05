import { chromium } from 'playwright';
import { readFile } from 'fs/promises';
import path from 'path';

const base = process.env.DEMO_BASE || 'http://127.0.0.1:8765';
const imgB64 = (await readFile(path.join(process.cwd(), 'tests/fixtures/support-test-image.jpg'))).toString('base64');

const browser = await chromium.launch();
const page = await (await browser.newContext({ viewport: { width: 390, height: 844 } })).newPage();

const results = {};

async function vis(id) {
  return page.locator(`#${id}:not([hidden])`).isVisible();
}

async function injectGalleryFile() {
  await page.evaluate(async (b64) => {
    const bin = atob(b64);
    const arr = new Uint8Array(bin.length);
    for (let i = 0; i < bin.length; i++) arr[i] = bin.charCodeAt(i);
    const blob = new Blob([arr], { type: 'image/jpeg' });
    const file = new File([blob], 'support-test-image.jpg', { type: 'image/jpeg' });
    const picker = document.querySelector('[data-pnv-gallery-picker]');
    const dt = new DataTransfer();
    dt.items.add(file);
    picker.files = dt.files;
    picker.dispatchEvent(new Event('change', { bubbles: true }));
  }, imgB64);
}

async function flowToPreview() {
  await page.goto(`${base}/tests/manual/support-attach-demo.html`, { waitUntil: 'networkidle' });
  await page.click('#openGallery');
  await page.waitForSelector('#pnvMediaGallery:not([hidden])');
  await injectGalleryFile();
  await page.click('.pnv-media-gallery-cell');
  await page.waitForSelector('#pnvMediaEditor:not([hidden])');
  await page.click('#pnvMediaEditApply');
  await page.waitForSelector('#pnvMediaPreview:not([hidden])');
}

// A
await flowToPreview();
await page.goBack();
results.A1 = (await vis('pnvMediaEditor')) ? 'edit' : 'fail';
await page.goBack();
results.A2 = (await vis('pnvMediaGallery')) ? 'gallery' : 'fail';
await page.goBack();
results.A3 =
  !(await vis('pnvMediaGallery')) && !(await vis('pnvMediaEditor')) && !(await vis('pnvMediaPreview'))
    ? 'chat'
    : 'fail';

// B
await flowToPreview();
await page.evaluate(() => {
  const realFetch = window.fetch.bind(window);
  window.fetch = (url, opts) => {
    if (opts && opts.headers && opts.headers['X-Support-Ajax']) {
      return Promise.resolve({ ok: true, json: () => Promise.resolve({ ok: true }) });
    }
    return realFetch(url, opts);
  };
  document.getElementById('pnvMediaSend').click();
});
await page.waitForTimeout(250);
results.B_afterSend =
  (await vis('pnvMediaPreview')) || (await vis('pnvMediaEditor')) || (await vis('pnvMediaGallery'))
    ? 'fail'
    : 'chat';
await page.goBack();
results.B_back =
  (await vis('pnvMediaPreview')) || (await vis('pnvMediaEditor')) || (await vis('pnvMediaGallery'))
    ? 'stale'
    : 'ok';

// C
await page.goto(`${base}/tests/manual/support-attach-demo.html`, { waitUntil: 'networkidle' });
await page.click('#openGallery');
await injectGalleryFile();
await page.click('.pnv-media-gallery-cell');
await page.waitForSelector('#pnvMediaEditor:not([hidden])');
await page.click('#pnvMediaEditCancel');
await page.waitForTimeout(200);
results.C = !(await vis('pnvMediaEditor')) && !(await vis('pnvMediaGallery')) ? 'ok' : 'fail';
await page.goBack();
results.C_back =
  (await vis('pnvMediaGallery')) || (await vis('pnvMediaEditor')) ? 'stale' : 'ok';

// D
await flowToPreview();
await page.click('#pnvMediaPreviewEdit');
await page.waitForTimeout(120);
results.D1 = (await vis('pnvMediaEditor')) ? 'edit' : 'fail';
await page.goBack();
results.D2 = (await vis('pnvMediaGallery')) ? 'gallery' : 'fail';

// Picker cancel
await page.goto(`${base}/tests/manual/support-attach-demo.html`, { waitUntil: 'networkidle' });
await page.click('#openGallery');
await page.waitForSelector('#pnvMediaGallery:not([hidden])');
await page.waitForTimeout(500);
await page.evaluate(() => {
  const picker = document.querySelector('[data-pnv-gallery-picker]');
  picker.dispatchEvent(new Event('cancel', { bubbles: true }));
});
await page.waitForTimeout(150);
results.pickerCancel = !(await vis('pnvMediaGallery')) ? 'ok' : 'fail';

const depthInfo = await page.evaluate(() => {
  return window.mediaDemo && window.mediaDemo._test
    ? null
    : null;
});

console.log(JSON.stringify({ results, pushStatesPerFullFlow: 3 }, null, 2));

const expect = {
  A1: 'edit',
  A2: 'gallery',
  A3: 'chat',
  B_afterSend: 'chat',
  B_back: 'ok',
  C: 'ok',
  C_back: 'ok',
  D1: 'edit',
  D2: 'gallery',
  pickerCancel: 'ok'
};

const failed = Object.keys(expect).filter((k) => results[k] !== expect[k]);
await browser.close();
if (failed.length) {
  console.error('FAILED keys:', failed);
  process.exit(1);
}
