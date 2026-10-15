/**
 * Auth pages viewport smoke test.
 *
 * Env:
 *   AUTH_TEST_SCREENSHOT_DIR — output directory (default: <repo>/tmp/auth-screenshots)
 *   AUTH_TEST_PORT — PHP server port when not using AUTH_TEST_BASE_URL (default: 8766)
 *   AUTH_TEST_BASE_URL — e.g. http://127.0.0.1:8766 (skip built-in server if set)
 *
 * Run: npx playwright install chromium && node tests/manual/auth-viewports.mjs
 */
import { chromium } from 'playwright';
import { spawn } from 'child_process';
import { mkdirSync } from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(__dirname, '../..');
const OUT =
  process.env.AUTH_TEST_SCREENSHOT_DIR ||
  path.join(ROOT, 'tmp', 'auth-screenshots');
const widths = [320, 375, 430, 768, 1366, 1920];
const PORT = parseInt(process.env.AUTH_TEST_PORT || '8766', 10);
const BASE = (process.env.AUTH_TEST_BASE_URL || `http://127.0.0.1:${PORT}`).replace(
  /\/$/,
  '',
);

mkdirSync(OUT, { recursive: true });

let server = null;

if (!process.env.AUTH_TEST_BASE_URL) {
  server = spawn('php', ['-S', `127.0.0.1:${PORT}`, '-t', ROOT], {
    cwd: ROOT,
    stdio: 'ignore',
  });
  await new Promise((r) => setTimeout(r, 500));
}

const browser = await chromium.launch();
let failed = 0;

async function checkNoHorizontalScroll(page, label) {
  const overflow = await page.evaluate(() => {
    const el = document.documentElement;
    return el.scrollWidth > el.clientWidth + 1;
  });
  if (overflow) {
    console.error(`FAIL horizontal scroll: ${label}`);
    failed++;
  } else {
    console.log(`OK  no horizontal scroll: ${label}`);
  }
}

/** CDP scale smoke (not equivalent to browser zoom — kept separately). */
async function setCdpPageScaleFactor(page, scaleFactor) {
  const cdp = await page.context().newCDPSession(page);
  await cdp.send('Emulation.setPageScaleFactor', { pageScaleFactor: scaleFactor });
}

/** Chromium layout reflow similar to browser zoom via CSS zoom (not CDP). */
async function applyCssBrowserZoom(page, percent) {
  await page.evaluate((pct) => {
    document.documentElement.style.zoom = String(pct);
  }, percent);
}

const context = await browser.newContext({ locale: 'fa-IR' });
const page = await context.newPage();

for (const w of widths) {
  await page.setViewportSize({ width: w, height: 800 });
  await page.goto(`${BASE}/index.php`, { waitUntil: 'domcontentloaded' });
  await checkNoHorizontalScroll(page, `login ${w}px`);
  await page.screenshot({ path: path.join(OUT, `login-${w}.png`), fullPage: true });

  await page.goto(`${BASE}/register.php`, { waitUntil: 'domcontentloaded' });
  await checkNoHorizontalScroll(page, `register ${w}px`);
  await page.screenshot({ path: path.join(OUT, `register-${w}.png`), fullPage: true });
}

await page.setViewportSize({ width: 375, height: 800 });
await page.goto(`${BASE}/index.php`);
await page.locator('.auth-captcha-refresh').first().click();
await page.waitForLoadState('domcontentloaded');
console.log('OK  login captcha refresh navigates');

await page.goto(`${BASE}/register.php?ref=TESTREF`);
if ((await page.locator('#referrer').count()) === 0) console.log('OK  referrer hidden with ref param');
else {
  console.error('FAIL referrer visible with ref');
  failed++;
}
const href = await page.locator('.auth-captcha-refresh').first().getAttribute('href');
if (href && href.includes('ref=TESTREF') && href.includes('refreshcaptcha=1')) {
  console.log('OK  register refresh link keeps ref');
} else {
  console.error('FAIL register refresh link:', href);
  failed++;
}
await page.locator('.auth-captcha-refresh').first().click();
await page.waitForURL(/ref=TESTREF/);
if (page.url().includes('ref=TESTREF') && !page.url().includes('refreshcaptcha')) {
  console.log('OK  register captcha refresh preserves ref in URL');
} else {
  console.error('FAIL register redirect lost ref:', page.url());
  failed++;
}

await page.goto(`${BASE}/register.php`);
await page.locator('#password').fill('secret12');
const typeBefore = await page.locator('#password').getAttribute('type');
await page.locator('.auth-eye').click();
const typeAfter = await page.locator('#password').getAttribute('type');
if (typeBefore === 'password' && typeAfter === 'text') console.log('OK  password toggle');
else {
  console.error('FAIL password toggle');
  failed++;
}

await page.goto(`${BASE}/index.php`);
const loginDesc = await page.locator('#login-captcha-desc').textContent();
if (loginDesc && loginDesc.includes('کد امنیتی نمایشی')) console.log('OK  captcha accessible description');
else {
  console.error('FAIL captcha a11y desc');
  failed++;
}

const placeholders = await page.evaluate(() =>
  Array.from(document.querySelectorAll('.auth-form-stack input')).some((el) => el.placeholder),
);
if (!placeholders) console.log('OK  no duplicate placeholders in login form');
else {
  console.error('FAIL login still has placeholders');
  failed++;
}

await page.goto(`${BASE}/register.php`);
const regPlaceholders = await page.evaluate(() =>
  Array.from(document.querySelectorAll('.auth-form-stack input')).some((el) => el.placeholder),
);
if (!regPlaceholders) console.log('OK  no duplicate placeholders in register form');
else {
  console.error('FAIL register still has placeholders');
  failed++;
}

await page.locator('#username').click();
await page.waitForTimeout(200);
const focusHintState = await page.evaluate(() => {
  const hint = document.getElementById('username-hint');
  const btn = document.querySelector('.auth-hint-trigger[aria-controls="username-hint"]');
  if (!hint || !btn) return null;
  const style = getComputedStyle(hint);
  const visible = parseFloat(style.opacity) > 0.5 || style.maxHeight !== '0px';
  return { visible, expanded: btn.getAttribute('aria-expanded') };
});
if (focusHintState?.visible && focusHintState.expanded === 'true') {
  console.log('OK  username hint + aria-expanded on focus');
} else {
  console.error('FAIL username hint/aria on focus', focusHintState);
  failed++;
}

await page.locator('#mobile').click();
await page.waitForTimeout(350);
const blurHintState = await page.evaluate(() => {
  const btn = document.querySelector('.auth-hint-trigger[aria-controls="username-hint"]');
  const hint = document.getElementById('username-hint');
  const style = hint ? getComputedStyle(hint) : null;
  const visible = style && (parseFloat(style.opacity) > 0.5 || style.maxHeight !== '0px');
  return { visible: !!visible, expanded: btn ? btn.getAttribute('aria-expanded') : null };
});
if (!blurHintState.visible && blurHintState.expanded === 'false') {
  console.log('OK  username hint + aria-expanded after blur (unpinned)');
} else {
  console.error('FAIL username hint/aria after blur', blurHintState);
  failed++;
}

const infoBtn = page.locator('.auth-hint-trigger[aria-controls="username-hint"]');
const hitBox = await infoBtn.evaluate((el) => {
  const rect = el.getBoundingClientRect();
  return { w: rect.width, h: rect.height };
});
if (hitBox.w >= 44 && hitBox.h >= 44) console.log('OK  info button 44px touch target');
else {
  console.error('FAIL info touch target', hitBox);
  failed++;
}

await infoBtn.click();
await page.waitForTimeout(150);
const pinnedState = await page.evaluate(() => {
  const field = document.getElementById('username')?.closest('.auth-field');
  const btn = document.querySelector('.auth-hint-trigger[aria-controls="username-hint"]');
  return {
    pinned: field?.classList.contains('is-hint-pinned'),
    expanded: btn?.getAttribute('aria-expanded'),
  };
});
if (pinnedState.pinned && pinnedState.expanded === 'true') {
  console.log('OK  info click pins hint + aria-expanded true');
} else {
  console.error('FAIL info pin state', pinnedState);
  failed++;
}

await infoBtn.click();
await page.waitForTimeout(150);
const unpinnedState = await page.evaluate(() => {
  const field = document.getElementById('username')?.closest('.auth-field');
  const btn = document.querySelector('.auth-hint-trigger[aria-controls="username-hint"]');
  return {
    pinned: field?.classList.contains('is-hint-pinned'),
    expanded: btn?.getAttribute('aria-expanded'),
  };
});
if (!unpinnedState.pinned && unpinnedState.expanded === 'false') {
  console.log('OK  info click unpins + aria-expanded false when unfocused');
} else {
  console.error('FAIL info unpin state', unpinnedState);
  failed++;
}

await context.close();

const zoomContext = await browser.newContext({ locale: 'fa-IR' });
const zoomPage = await zoomContext.newPage();
await zoomPage.setViewportSize({ width: 375, height: 700 });
await zoomPage.goto(`${BASE}/register.php`, { waitUntil: 'domcontentloaded' });
await setCdpPageScaleFactor(zoomPage, 2);
await checkNoHorizontalScroll(zoomPage, 'register 375px @CDP setPageScaleFactor(2) smoke');
await zoomPage.screenshot({ path: path.join(OUT, 'register-375-zoom200-cdp.png'), fullPage: true });

const reflowPage = await zoomContext.newPage();
await reflowPage.setViewportSize({ width: 375, height: 700 });
await reflowPage.goto(`${BASE}/register.php`, { waitUntil: 'domcontentloaded' });
await applyCssBrowserZoom(reflowPage, '200%');
await checkNoHorizontalScroll(reflowPage, 'register 375px @200% CSS browser-like zoom reflow');
await reflowPage.screenshot({
  path: path.join(OUT, 'register-375-zoom200-browser-reflow.png'),
  fullPage: true,
});
await zoomContext.close();

await browser.close();
if (server) server.kill();

console.log(`Screenshots: ${OUT}`);

if (failed > 0) process.exit(1);
console.log('All auth viewport checks passed');
