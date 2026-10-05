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

async function viewerOpen() {
  return page.evaluate(function () {
    var el = document.getElementById('pnvSupportImageViewer');
    return el && !el.hidden;
  });
}

async function waitViewerOpen() {
  await page.waitForFunction(function () {
    var el = document.getElementById('pnvSupportImageViewer');
    return el && !el.hidden;
  });
}

async function waitViewerClosed() {
  await page.waitForFunction(function () {
    var el = document.getElementById('pnvSupportImageViewer');
    return el && el.hidden;
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
await waitViewerOpen();
const urlOpen = page.url();

await page.click('.pnv-image-viewer-image');
await page.waitForTimeout(80);
const imageClickKeepsOpen = await viewerOpen();

await page.evaluate(function () {
  var stage = document.getElementById('pnvImageViewerStage');
  var rect = stage.getBoundingClientRect();
  stage.dispatchEvent(
    new MouseEvent('click', {
      bubbles: true,
      cancelable: true,
      clientX: rect.left + 8,
      clientY: rect.top + 8
    })
  );
});
await waitViewerClosed();
await page.waitForTimeout(50);
const scrollAfterStageClose = await page.evaluate(function () {
  return document.getElementById('demoChat').scrollTop;
});
const stageOutsideClose = await viewerHidden();

await page.click('.msgImageLink');
await waitViewerOpen();
await page.keyboard.press('Escape');
await waitViewerClosed();
const escapeClose = await viewerHidden();

await page.click('.msgImageLink');
await waitViewerOpen();
await page.goBack();
await waitViewerClosed();
const historyBackClose = await viewerHidden();

await page.click('#addPollMsg');
await page.locator('.msgImageLink').last().click();
await waitViewerOpen();
await page.keyboard.press('Escape');
await waitViewerClosed();

const results = {
  urlUnchanged: urlBefore === urlOpen,
  imageClickKeepsOpen: imageClickKeepsOpen,
  stageOutsideClose: stageOutsideClose,
  scrollPreservedAfterStageClose: scrollAfterStageClose === 120,
  escapeClose: escapeClose,
  historyBackClose: historyBackClose,
  dynamicAndEscape: true
};

console.log(JSON.stringify(results, null, 2));

await browser.close();

const expect = {
  urlUnchanged: true,
  imageClickKeepsOpen: true,
  stageOutsideClose: true,
  scrollPreservedAfterStageClose: true,
  escapeClose: true,
  historyBackClose: true
};

const failed = Object.keys(expect).filter(function (k) {
  return results[k] !== expect[k];
});
if (failed.length) {
  console.error('FAILED', failed);
  process.exit(1);
}
