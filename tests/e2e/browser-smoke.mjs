// Parcours navigateur (Playwright) : pages publiques, inscription (validation), connexion, espace client, menu mobile.
// Usage : voir tests/e2e/run.sh. Variables : BASE_URL, E2E_EMAIL, E2E_PASSWORD, PLAYWRIGHT_MODULE.
import { pathToFileURL } from 'node:url';

const pw = await import(pathToFileURL(process.env.PLAYWRIGHT_MODULE ?? '/opt/node-tools/node_modules/playwright/index.mjs').href);
const BASE = process.env.BASE_URL ?? 'http://127.0.0.1:8099';
const EMAIL = process.env.E2E_EMAIL, PASSWORD = process.env.E2E_PASSWORD;
const failures = [];
const check = (ok, msg) => { console.log(`${ok ? 'OK ' : 'KO '} ${msg}`); if (!ok) failures.push(msg); };

const browser = await pw.chromium.launch({ executablePath: process.env.CHROMIUM ?? '/opt/pw-browsers/chromium' });
const sizes = [[360, 800], [768, 1024], [1440, 900]];

async function open(page, path) {
  const errs = [];
  const onErr = (e) => errs.push(String(e.message ?? e));
  const onMsg = (m) => { if (m.type() === 'error') errs.push(m.text()); };
  page.on('pageerror', onErr); page.on('console', onMsg);
  const res = await page.goto(BASE + path, { waitUntil: 'networkidle' });
  page.off('pageerror', onErr); page.off('console', onMsg);
  return { status: res?.status() ?? 0, errs };
}
const overflow = (page) => page.evaluate(() => document.documentElement.scrollWidth - innerWidth);

// 1. Pages publiques : statut, erreurs JS, débordement horizontal aux trois largeurs.
for (const [w, h] of sizes) {
  const page = await browser.newPage({ viewport: { width: w, height: h } });
  for (const path of ['/', '/services', '/missions', '/inscription', '/connexion', '/informations/conditions']) {
    const { status, errs } = await open(page, path);
    check(status === 200, `${w}px ${path} répond 200 (${status})`);
    check(errs.length === 0, `${w}px ${path} sans erreur JS/console${errs.length ? ' : ' + errs[0] : ''}`);
    check((await overflow(page)) <= 0, `${w}px ${path} sans débordement horizontal`);
  }
  await page.close();
}

const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });

// 2. Catalogue → fiche d'un service.
await open(page, '/services');
const first = page.locator('a[href*="/services/"]').first();
if (await first.count()) {
  await first.click(); await page.waitForLoadState('networkidle');
  check((await page.locator('h1').count()) === 1, 'la fiche d’un service a un seul titre h1');
} else { check(false, 'le catalogue affiche au moins un service'); }

// 3. Inscription : un mot de passe trop faible est refusé côté serveur, sans quitter la page (les champs requis sont remplis pour passer la validation du navigateur).
await open(page, '/inscription');
for (const el of await page.locator('main form input:not([type=hidden]):not([type=checkbox]):not([type=submit])').all()) {
  const type = (await el.getAttribute('type')) ?? 'text';
  await el.fill(type === 'email' ? 'e2e-inscription@example.test' : type === 'password' ? 'abc' : 'Test Utilisateur');
}
for (const box of await page.locator('main form input[type=checkbox][required]').all()) await box.check();
await page.locator('main form button[type=submit]').first().click(); await page.waitForLoadState('networkidle');
check(page.url().includes('/inscription'), 'l’inscription avec un mot de passe faible reste sur la page');
check((await page.locator('.field-error, [role=alert]').count()) > 0, 'l’inscription faible signale une erreur');

// 4. Clavier : le premier arrêt de tabulation est un lien d'évitement.
await open(page, '/');
await page.keyboard.press('Tab');
const firstFocus = await page.evaluate(() => document.activeElement?.textContent?.trim().toLowerCase() ?? '');
check(/contenu|passer|aller/.test(firstFocus), `premier arrêt clavier = lien d’évitement (« ${firstFocus.slice(0, 40)} »)`);

// 5. Connexion et espace client.
if (EMAIL && PASSWORD) {
  await open(page, '/connexion');
  await page.fill('input[name=email]', EMAIL); await page.fill('input[name=password]', PASSWORD);
  await page.locator('main form button[type=submit]').first().click(); await page.waitForLoadState('networkidle');
  check(!page.url().includes('/connexion'), `connexion réussie (${page.url().replace(BASE, '')})`);
  for (const path of ['/espace', '/espace/commandes', '/espace/messages', '/espace/notifications', '/espace/compte', '/espace/assistance']) {
    const { status, errs } = await open(page, path);
    check(status === 200, `${path} répond 200 (${status})`);
    check(errs.length === 0, `${path} sans erreur JS/console${errs.length ? ' : ' + errs[0] : ''}`);
  }
  // 6. Menu mobile de l'espace.
  await page.setViewportSize({ width: 360, height: 800 });
  await open(page, '/espace');
  const menu = page.getByRole('button', { name: /menu/i }).first();
  check(await menu.count() > 0, 'le bouton Menu existe sur mobile');
  if (await menu.count()) {
    await menu.click();
    const link = page.getByRole('link', { name: /commandes/i }).first();
    check(await link.isVisible(), 'le menu mobile affiche la navigation de l’espace');
    await page.keyboard.press('Escape');
  }
  check((await overflow(page)) <= 0, '/espace sans débordement horizontal sur mobile');
} else { console.log('-- connexion ignorée (E2E_EMAIL / E2E_PASSWORD absents)'); }

await browser.close();
console.log(failures.length ? `\n${failures.length} échec(s)` : '\nTous les contrôles passent');
process.exit(failures.length ? 1 : 0);
