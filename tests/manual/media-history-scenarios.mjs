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

async function injectPickerFiles(count) {
  await page.evaluate(
    async ({ b64, count }) => {
      const bin = atob(b64);
      const arr = new Uint8Array(bin.length);
      for (let i = 0; i < bin.length; i++) arr[i] = bin.charCodeAt(i);
      const picker = document.querySelector('[data-pnv-gallery-picker]');
      const dt = new DataTransfer();
      for (let n = 0; n < count; n++) {
        const blob = new Blob([arr], { type: 'image/jpeg' });
        dt.items.add(new File([blob], 'img-' + n + '.jpg', { type: 'image/jpeg' }));
      }
      picker.files = dt.files;
      picker.dispatchEvent(new Event('change', { bubbles: true }));
    },
    { b64: imgB64, count }
  );
}

async function flowSingleToPreview() {
  await page.goto(`${base}/tests/manual/support-attach-demo.html`, { waitUntil: 'networkidle' });
  await page.click('#openGallery');
  await page.waitForTimeout(250);
  await injectPickerFiles(1);
  await page.waitForSelector('#pnvMediaEditor:not([hidden])');
  results.singleSkipGallery = (await vis('pnvMediaGallery')) ? 'fail' : 'ok';
  await page.click('#pnvMediaEditApply');
  await page.waitForSelector('#pnvMediaPreview:not([hidden])');
}

// Single-image history: edit → preview; back edit → chat
await flowSingleToPreview();
await page.goBack();
results.A1 = (await vis('pnvMediaEditor')) ? 'edit' : 'fail';
await page.goBack();
results.A2 =
  !(await vis('pnvMediaGallery')) && !(await vis('pnvMediaEditor')) ? 'chat' : 'fail';

// Multi-image: gallery grid
await page.goto(`${base}/tests/manual/support-attach-demo.html`, { waitUntil: 'networkidle' });
await page.click('#openGallery');
await page.waitForTimeout(250);
await injectPickerFiles(3);
await page.waitForSelector('#pnvMediaGallery:not([hidden])');
results.multiGallery = (await page.locator('.pnv-media-gallery-cell').count()) === 3 ? 'ok' : 'fail';
await page.click('.pnv-media-gallery-cell');
await page.waitForSelector('#pnvMediaEditor:not([hidden])');
await page.goBack();
results.multiEditBack = (await vis('pnvMediaGallery')) ? 'gallery' : 'fail';

// B send (single stack depth 2)
await flowSingleToPreview();
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

// C cancel from edit (single)
await page.goto(`${base}/tests/manual/support-attach-demo.html`, { waitUntil: 'networkidle' });
await page.click('#openGallery');
await page.waitForTimeout(250);
await injectPickerFiles(1);
await page.waitForSelector('#pnvMediaEditor:not([hidden])');
await page.click('#pnvMediaEditCancel');
await page.waitForTimeout(200);
results.C = !(await vis('pnvMediaEditor')) ? 'ok' : 'fail';

// D preview edit (multi flow for gallery back)
await page.goto(`${base}/tests/manual/support-attach-demo.html`, { waitUntil: 'networkidle' });
await page.click('#openGallery');
await page.waitForTimeout(250);
await injectPickerFiles(2);
await page.waitForSelector('#pnvMediaGallery:not([hidden])');
await page.click('.pnv-media-gallery-cell');
await page.waitForSelector('#pnvMediaEditor:not([hidden])');
await page.click('#pnvMediaEditApply');
await page.waitForSelector('#pnvMediaPreview:not([hidden])');
await page.click('#pnvMediaPreviewEdit');
await page.waitForTimeout(120);
results.D1 = (await vis('pnvMediaEditor')) ? 'edit' : 'fail';
await page.goBack();
results.D2 = (await vis('pnvMediaGallery')) ? 'gallery' : 'fail';

// Picker cancel (no overlay stuck)
await page.goto(`${base}/tests/manual/support-attach-demo.html`, { waitUntil: 'networkidle' });
await page.click('#openGallery');
await page.waitForTimeout(500);
await page.evaluate(() => {
  document.querySelector('[data-pnv-gallery-picker]').dispatchEvent(new Event('cancel', { bubbles: true }));
});
await page.waitForTimeout(150);
results.pickerCancel =
  !(await vis('pnvMediaGallery')) && !(await vis('pnvMediaEditor')) ? 'ok' : 'fail';

console.log(
  JSON.stringify(
    {
      results,
      historyStacks: {
        single: ['edit', 'preview'],
        multi: ['gallery', 'edit', 'preview']
      }
    },
    null,
    2
  )
);

const expect = {
  singleSkipGallery: 'ok',
  A1: 'edit',
  A2: 'chat',
  multiGallery: 'ok',
  multiEditBack: 'gallery',
  B_afterSend: 'chat',
  C: 'ok',
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
