import { chromium } from 'playwright';
import { mkdir } from 'fs/promises';
import path from 'path';

const base = 'http://127.0.0.1:9876';
const outDir = '/opt/cursor/artifacts/screenshots';

function assert(cond, msg) {
  if (!cond) throw new Error(msg);
}

function parseRgb(color) {
  const m = color.match(/rgba?\((\d+),\s*(\d+),\s*(\d+)/);
  if (!m) return null;
  return { r: +m[1], g: +m[2], b: +m[3] };
}

function isDarkBody(bg) {
  const rgb = parseRgb(bg);
  if (!rgb) return false;
  const lum = 0.2126 * rgb.r + 0.7152 * rgb.g + 0.0722 * rgb.b;
  return lum < 80;
}

await mkdir(outDir, { recursive: true });

const browser = await chromium.launch();
const context = await browser.newContext({ locale: 'fa-IR' });
const page = await context.newPage();

const failedRequests = [];
page.on('response', (res) => {
  const url = res.url();
  if (url.includes('coupon_ui.css') && res.status() !== 200) {
    failedRequests.push(`${res.status()} ${url}`);
  }
});
page.on('requestfailed', (req) => {
  const url = req.url();
  if (url.includes('.css')) {
    failedRequests.push(`failed ${url}`);
  }
});

const cssWait = page.waitForResponse(
  (res) => res.url().includes('coupon_ui.css') && res.status() === 200,
  { timeout: 15000 },
);
await page.goto(`${base}/tests/manual/coupon-preview-bootstrap.php`, { waitUntil: 'networkidle' });
await page.waitForURL('**/coupon.php');
const cssResponse = await cssWait;
assert(cssResponse.ok(), `coupon_ui.css must be HTTP 200, got ${cssResponse.status()}`);

const cssDirect = await page.request.get(`${base}/coupon_ui.css?v=5`);
assert(cssDirect.status() === 200, `direct coupon_ui.css?v=5 expected 200, got ${cssDirect.status()}`);

const styles = await page.evaluate(() => {
  const bodyBg = getComputedStyle(document.body).backgroundColor;
  const icon = document.querySelector('.couponFieldHead .couponIcon');
  const hero = document.querySelector('.couponHero');
  return {
    bodyBg,
    iconWidth: icon ? getComputedStyle(icon).width : null,
    heroDisplay: hero ? getComputedStyle(hero).display : null,
    scrollWidth: document.documentElement.scrollWidth,
    clientWidth: document.documentElement.clientWidth,
  };
});

assert(failedRequests.length === 0, `CSS network errors: ${failedRequests.join('; ')}`);
assert(isDarkBody(styles.bodyBg), `body background should be dark, got ${styles.bodyBg}`);
assert(styles.iconWidth === '16px', `.couponIcon width expected 16px, got ${styles.iconWidth}`);
assert(styles.heroDisplay === 'flex', `.couponHero display expected flex, got ${styles.heroDisplay}`);
assert(styles.scrollWidth <= styles.clientWidth, `horizontal overflow ${styles.scrollWidth} > ${styles.clientWidth}`);

await page.setViewportSize({ width: 390, height: 900 });
await page.waitForTimeout(300);
await page.screenshot({
  path: path.join(outDir, 'coupon-referral-club-mobile-390.png'),
  fullPage: true,
});

await page.setViewportSize({ width: 1280, height: 900 });
await page.waitForTimeout(300);
await page.screenshot({
  path: path.join(outDir, 'coupon-referral-club-desktop.png'),
  fullPage: true,
});

await page.setViewportSize({ width: 390, height: 900 });
await page.click('#copyCodeBtn');
await page.waitForSelector('#couponToast.is-show');
const codeToast = await page.textContent('#couponToast');
assert(codeToast.includes('کپی'), `copy code toast: ${codeToast}`);

await page.click('#copyLinkBtn');
await page.waitForSelector('#couponToast.is-show');

const tierSource = await page.evaluate(() => {
  return document.documentElement.innerHTML.includes('tierDefs') ? 'hardcoded' : 'ok';
});
assert(tierSource === 'ok', 'page must not contain hardcoded tierDefs');

await browser.close();
console.log('OK: CSS loaded, computed styles, copy, overflow, screenshots saved to', outDir);
