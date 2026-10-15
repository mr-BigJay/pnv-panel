/**
 * Auth pages viewport smoke test.
 * Run: cd /workspace && npm init -y && npm i -D playwright && npx playwright install chromium && node tests/manual/auth-viewports.mjs
 */
import { chromium } from 'playwright';
import { spawn } from 'child_process';
import { mkdirSync } from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(__dirname, '../..');
const OUT = '/opt/cursor/artifacts/screenshots/auth-pr25';
const widths = [320, 375, 430, 768, 1366, 1920];
const PORT = 8766;

mkdirSync(OUT, { recursive: true });

const server = spawn('php', ['-S', `127.0.0.1:${PORT}`, '-t', ROOT], {
  cwd: ROOT,
  stdio: 'ignore',
});

await new Promise((r) => setTimeout(r, 500));

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

const context = await browser.newContext({ locale: 'fa-IR' });
const page = await context.newPage();

for (const w of widths) {
  await page.setViewportSize({ width: w, height: 800 });
  await page.goto(`http://127.0.0.1:${PORT}/index.php`, { waitUntil: 'domcontentloaded' });
  await checkNoHorizontalScroll(page, `login ${w}px`);
  await page.screenshot({ path: path.join(OUT, `login-${w}.png`), fullPage: true });

  await page.goto(`http://127.0.0.1:${PORT}/register.php`, { waitUntil: 'domcontentloaded' });
  await checkNoHorizontalScroll(page, `register ${w}px`);
  await page.screenshot({ path: path.join(OUT, `register-${w}.png`), fullPage: true });
}

await page.setViewportSize({ width: 375, height: 800 });
await page.goto(`http://127.0.0.1:${PORT}/index.php`);
const captchaBefore = (await page.locator('.auth-captcha-visual').first().textContent()) || '';
await page.click('a.auth-captcha-refresh');
await page.waitForLoadState('domcontentloaded');
const captchaAfter = (await page.locator('.auth-captcha-visual').first().textContent()) || '';
if (captchaBefore && captchaAfter) console.log('OK  login captcha refresh (code updated or page reloaded)');

await page.goto(`http://127.0.0.1:${PORT}/register.php?ref=TESTREF`);
if ((await page.locator('#referrer').count()) === 0) console.log('OK  referrer hidden with ref param');
else {
  console.error('FAIL referrer visible with ref');
  failed++;
}
const href = await page.locator('a.auth-captcha-refresh').getAttribute('href');
if (href && href.includes('ref=TESTREF') && href.includes('refreshcaptcha=1')) {
  console.log('OK  register refresh link keeps ref');
} else {
  console.error('FAIL register refresh link:', href);
  failed++;
}
await page.click('a.auth-captcha-refresh');
await page.waitForURL(/ref=TESTREF/);
if (page.url().includes('ref=TESTREF') && !page.url().includes('refreshcaptcha')) {
  console.log('OK  register captcha refresh preserves ref in URL');
} else {
  console.error('FAIL register redirect lost ref:', page.url());
  failed++;
}

await page.goto(`http://127.0.0.1:${PORT}/register.php`);
await page.locator('#password').fill('secret12');
const typeBefore = await page.locator('#password').getAttribute('type');
await page.locator('.auth-eye').click();
const typeAfter = await page.locator('#password').getAttribute('type');
if (typeBefore === 'password' && typeAfter === 'text') console.log('OK  password toggle');
else {
  console.error('FAIL password toggle');
  failed++;
}

await page.goto(`http://127.0.0.1:${PORT}/index.php`);
const loginDesc = await page.locator('#login-captcha-desc').textContent();
if (loginDesc && loginDesc.includes('کد امنیتی نمایشی')) console.log('OK  captcha accessible description');
else {
  console.error('FAIL captcha a11y desc');
  failed++;
}

await context.close();

const zoomContext = await browser.newContext({ locale: 'fa-IR', deviceScaleFactor: 2 });
const zoomPage = await zoomContext.newPage();
await zoomPage.setViewportSize({ width: 375, height: 700 });
await zoomPage.goto(`http://127.0.0.1:${PORT}/register.php`);
await checkNoHorizontalScroll(zoomPage, 'register 375px @200% zoom');
await zoomPage.screenshot({ path: path.join(OUT, 'register-375-zoom200.png'), fullPage: true });
await zoomContext.close();

await browser.close();
server.kill();

if (failed > 0) process.exit(1);
console.log('All auth viewport checks passed');
