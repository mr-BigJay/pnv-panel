import { chromium } from 'playwright';
import path from 'path';

const base = process.env.DEMO_BASE || 'http://127.0.0.1:8765';
const browser = await chromium.launch();
const page = await (await browser.newContext({ viewport: { width: 390, height: 844 } })).newPage();

async function viewerHidden() {
  return page.evaluate(function () {
    var el = document.getElementById('pnvSupportImageViewer');
    return !el || el.hidden;
  });
}

await page.goto(`${base}/tests/manual/support-image-viewer-demo.html`, { waitUntil: 'networkidle' });

const urlBefore = page.url();
await page.evaluate(function () {
  var c = document.getElementById('demoChat');
  var spacer = document.createElement('div');
  spacer.id = 'demoChatSpacer';
  spacer.style.height = '3000px';
  c.appendChild(spacer);
  c.scrollTop = 120;
});
await page.click('.msgImageLink');
await page.waitForFunction(function () {
  var el = document.getElementById('pnvSupportImageViewer');
  return el && !el.hidden;
});
const urlOpen = page.url();

await page.click('.pnv-image-viewer-close');
await page.waitForFunction(function () {
  var el = document.getElementById('pnvSupportImageViewer');
  return el && el.hidden;
});
await page.waitForTimeout(50);
const scrollAfterClose = await page.evaluate(function () {
  return document.getElementById('demoChat').scrollTop;
});

await page.click('#addPollMsg');
await page.locator('.msgImageLink').last().click();
await page.waitForFunction(function () {
  var el = document.getElementById('pnvSupportImageViewer');
  return el && !el.hidden;
});
await page.keyboard.press('Escape');
await page.waitForFunction(function () {
  var el = document.getElementById('pnvSupportImageViewer');
  return el && el.hidden;
});

await page.evaluate(function () {
  document.getElementById('demoChat').scrollTop = 80;
});
await page.click('.msgImageLink');
await page.waitForFunction(function () {
  var el = document.getElementById('pnvSupportImageViewer');
  return el && !el.hidden;
});
await page.goBack();
await page.waitForFunction(function () {
  var el = document.getElementById('pnvSupportImageViewer');
  return el && el.hidden;
});

const results = {
  urlUnchanged: urlBefore === urlOpen,
  scrollPreserved: scrollAfterClose === 120,
  dynamicAndEscape: true,
  historyBackClose: await viewerHidden()
};

console.log(JSON.stringify(results, null, 2));
await page.screenshot({
  path: path.join('/opt/cursor/artifacts', 'support-image-viewer-open-mobile.webp'),
  type: 'webp'
});

await browser.close();
if (!results.urlUnchanged || !results.scrollPreserved || !results.historyBackClose) {
  process.exit(1);
}
