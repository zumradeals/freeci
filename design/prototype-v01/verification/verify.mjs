// Vérification automatisée du prototype FreeCI V01 (outil de contrôle, hors prototype).
// Prérequis : Node 22, Playwright + Chromium installés globalement, et axe-core (variable AXE_PATH).
//   AXE_PATH=/chemin/axe-core/axe.min.js node verify.mjs [dossier-de-sortie]
// Ne modifie aucun fichier du prototype. Écrit un JSON de résultats.
import { createRequire } from 'module';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const require = createRequire(process.env.PW_ROOT || '/opt/node-tools/node_modules/');
const { chromium } = require('playwright');
const here = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(here, '..');
const outDir = process.argv[2] || here;
const axeSource = fs.readFileSync(process.env.AXE_PATH, 'utf8');

const PAGES = ['index', 'service', 'tableau-de-bord', 'tableau-de-bord-vide', 'commande', 'commande-paiement-en-verification'];
const WIDTHS = [360, 390, 768, 1024, 1440];
const ZOOM_WIDTHS = [360, 390, 768, 1024, 1440]; // viewport CSS = largeur / 2 (équivalent d'un zoom navigateur à 200 %)
const results = { generated: new Date().toISOString(), tool: 'playwright chromium', pages: {}, interactions: {}, links: {}, console: [] };

const browser = await chromium.launch();

// ---------- mesures dans la page ----------
const measure = () => {
  const vw = document.documentElement.clientWidth;
  const out = {};
  out.docScrollOverflow = Math.max(document.documentElement.scrollWidth, document.body.scrollWidth) - vw;

  const visible = (el) => {
    if (el.closest('[hidden], dialog:not([open]), .sr-only, .skip-link, script, style, template')) return false;
    if (el.closest('.sr-only-m') && el.closest('.sr-only-m').getBoundingClientRect().width <= 1) return false; // masqué visuellement (≤ 767 px)
    const cd = el.closest('details:not([open])'); if (cd && !el.closest('summary')) return false;
    const cs = getComputedStyle(el);
    if (cs.display === 'none' || cs.visibility === 'hidden') return false;
    const r = el.getBoundingClientRect();
    return r.width > 0 && r.height > 0;
  };

  // 1. éléments dépassant du viewport
  const off = [];
  document.querySelectorAll('body *').forEach((el) => {
    if (!visible(el)) return;
    if (el.closest('.demo-pages nav')) return; // panneau ouvert à la demande, borné à la largeur
    const r = el.getBoundingClientRect();
    if (r.right > vw + 1 || r.left < -1) off.push(`${el.tagName.toLowerCase()}.${(el.className && el.className.baseVal === undefined ? el.className : '').toString().split(' ')[0]} (${Math.round(r.left)}→${Math.round(r.right)})`);
  });
  out.offViewport = off.slice(0, 20);

  // 2. texte tronqué : conteneurs masquant un dépassement (hors conteneurs décoratifs connus)
  const clipped = [];
  const okClip = '.hero, .svc, .gallery .main, .gallery .th, .table-wrap, .card-flush, .thumb, .site-footer';
  document.querySelectorAll('body *').forEach((el) => {
    if (!visible(el) || el.matches(okClip) || el.closest('.hero-grid-bg') || el.matches('.wm, .menu-btn span, .sr-only-m')) return; // .wm / libellé du menu : masqués visuellement à ≤ 300 px, nom accessible conservé
    const cs = getComputedStyle(el);
    const hid = ['hidden', 'clip', 'auto', 'scroll'].includes(cs.overflowX);
    if (hid && el.scrollWidth > el.clientWidth + 1 && el.clientWidth > 0) clipped.push(el.tagName.toLowerCase() + '.' + String(el.className).split(' ')[0]);
    if (cs.textOverflow === 'ellipsis') clipped.push('ellipsis:' + el.tagName.toLowerCase());
  });
  out.clipped = clipped.slice(0, 12);

  // 3. chevauchement de texte
  const items = [];
  const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
  while (walker.nextNode()) {
    const n = walker.currentNode;
    if (!n.textContent.trim()) continue;
    const el = n.parentElement;
    if (!el || !visible(el) || el.closest('.sticky-buy, .toast, svg, option')) continue;
    const rg = document.createRange(); rg.selectNodeContents(n);
    for (const rc of rg.getClientRects()) if (rc.width > 1 && rc.height > 1) items.push({ el, rc, t: n.textContent.trim().slice(0, 28) });
  }
  const overlaps = [];
  for (let i = 0; i < items.length; i++) for (let j = i + 1; j < items.length; j++) {
    const a = items[i], b = items[j];
    if (a.el === b.el || a.el.contains(b.el) || b.el.contains(a.el)) continue;
    const w = Math.min(a.rc.right, b.rc.right) - Math.max(a.rc.left, b.rc.left);
    const h = Math.min(a.rc.bottom, b.rc.bottom) - Math.max(a.rc.top, b.rc.top);
    if (w > 4 && h > 4) overlaps.push(`« ${a.t} » × « ${b.t} » (${Math.round(w)}×${Math.round(h)})`);
  }
  out.textOverlaps = overlaps.slice(0, 10);

  // 4. cibles tactiles
  const small = [];
  document.querySelectorAll('a[href], button, input:not([type=hidden]), select, textarea, summary, [role=tab]').forEach((el) => {
    if (!visible(el)) return;
    let t = el;
    if (el.matches('input[type=checkbox], input[type=radio]')) t = el.closest('label') || el;
    const r = t.getBoundingClientRect();
    // lien dans un texte courant : exemption WCAG 2.5.8 (inline)
    const stretch = el.classList.contains('stretch'); // lien étiré : la zone tactile est la carte entière
    const inline = stretch || el.tagName === 'A' && el.closest('p, li') && getComputedStyle(el).display === 'inline' && el.parentElement.textContent.trim().length > el.textContent.trim().length + 4;
    if ((r.width < 43.5 || r.height < 43.5) && !inline) small.push(`${el.tagName.toLowerCase()} « ${(el.getAttribute('aria-label') || el.textContent).trim().replace(/\s+/g, ' ').slice(0, 30)} » ${Math.round(r.width)}×${Math.round(r.height)}`);
  });
  out.smallTargets = small.slice(0, 12);

  // 5. barre fixe : marge basse compensée
  const bar = document.querySelector('.sticky-buy');
  if (bar && getComputedStyle(bar).display !== 'none') {
    out.stickyBar = { height: Math.round(bar.getBoundingClientRect().height), bodyPaddingBottom: parseFloat(getComputedStyle(document.body).paddingBottom) };
  }
  return out;
};

let axeBusy = false;
const runAxe = async (page) => {
  axeBusy = true;
  await page.evaluate(axeSource);
  const r = await page.evaluate(async () => {
    const res = await axe.run(document, { runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa', 'best-practice'] } });
    return { violations: res.violations.map((v) => ({ id: v.id, impact: v.impact, nodes: v.nodes.length, help: v.help, sample: v.nodes.slice(0, 2).map((n) => n.target.join(' ')) })), passes: res.passes.length, incomplete: res.incomplete.map((v) => ({ id: v.id, nodes: v.nodes.length })) };
  });
  axeBusy = false;
  return r;
};

for (const mode of ['normal', 'zoom200']) {
  for (const w of WIDTHS) {
    const vwCss = mode === 'zoom200' ? Math.round(w / 2) : w;
    const ctx = await browser.newContext({ viewport: { width: vwCss, height: mode === 'zoom200' ? 450 : 900 }, deviceScaleFactor: mode === 'zoom200' ? 2 : 1, locale: 'fr-FR', timezoneId: 'Africa/Abidjan' });
    const page = await ctx.newPage();
    page.on('pageerror', (e) => results.console.push({ page: 'pageerror', msg: String(e) }));
    page.on('console', (m) => { if (!axeBusy && m.type() === 'error') results.console.push({ page: m.location().url, msg: m.text() }); });
    page.on('requestfailed', (r) => { if (!axeBusy) results.console.push({ page: r.url(), msg: 'requestfailed ' + (r.failure() || {}).errorText }); });
    for (const n of PAGES) {
      await page.goto(`file://${root}/${n}.html`);
      await page.evaluate(() => document.fonts.ready);
      const m = await page.evaluate(measure);
      const key = `${n}@${mode === 'zoom200' ? 'zoom200-' : ''}${w}`;
      results.pages[key] = m;
      if (mode === 'normal') {
        results.pages[key].fontsLoaded = await page.evaluate(() => document.fonts.check('16px Inter') && [...document.fonts].some((f) => f.family.includes('Inter') && f.status === 'loaded'));
        if (w === 360 || w === 1440) results.pages[key].axe = await runAxe(page);
      }
    }
    await ctx.close();
  }
}

// ---------- interactions clavier / composants ----------
const I = results.interactions;
async function ctxFor(w, extra = {}) { return browser.newContext({ viewport: { width: w, height: 900 }, locale: 'fr-FR', ...extra }); }

// Parcours au clavier + visibilité du focus
for (const w of [360, 1440]) {
  const ctx = await ctxFor(w); const page = await ctx.newPage();
  for (const n of PAGES) {
    await page.goto(`file://${root}/${n}.html`);
    const stops = [];
    const bad = [];
    for (let i = 0; i < 160; i++) {
      await page.keyboard.press('Tab');
      const info = await page.evaluate(() => {
        const el = document.activeElement; if (!el || el === document.body) return null;
        const cs = getComputedStyle(el); const r = el.getBoundingClientRect();
        const bar = document.querySelector('.sticky-buy'); const br = bar && getComputedStyle(bar).display !== 'none' ? bar.getBoundingClientRect() : null;
        return { tag: el.tagName.toLowerCase(), name: (el.getAttribute('aria-label') || el.textContent || el.id || '').trim().replace(/\s+/g, ' ').slice(0, 36), outline: cs.outlineStyle, ow: parseFloat(cs.outlineWidth), shadow: cs.boxShadow !== 'none', inView: r.bottom > 0 && r.top < innerHeight && r.right > 0 && r.left < innerWidth, hiddenByBar: !!(br && !el.closest('.sticky-buy') && r.bottom > br.top && r.top < br.bottom && r.width > 0), w: Math.round(r.width), h: Math.round(r.height) };
      });
      if (!info) break;
      if (stops.length && stops[0].name === info.name && stops.length > 3 && info.tag === stops[0].tag) break;
      stops.push(info);
      if (!(info.outline !== 'none' && info.ow >= 2) && !info.shadow) bad.push(`${info.tag} « ${info.name} » sans indicateur de focus`);
      if (!info.inView) bad.push(`${info.tag} « ${info.name} » hors écran au focus`);
      if (info.hiddenByBar) bad.push(`${info.tag} « ${info.name} » masqué par la barre fixe`);
    }
    I[`focus:${n}@${w}`] = { stops: stops.length, problems: [...new Set(bad)].slice(0, 8) };
  }
  await ctx.close();
}

// Tiroir (mobile)
{
  const ctx = await ctxFor(360); const page = await ctx.newPage();
  await page.goto(`file://${root}/index.html`);
  await page.focus('.menu-btn'); await page.keyboard.press('Enter');
  const open = await page.evaluate(() => document.getElementById('drawer').open);
  const inside = await page.evaluate(() => document.getElementById('drawer').contains(document.activeElement));
  await page.keyboard.press('Escape');
  const closed = await page.evaluate(() => !document.getElementById('drawer').open);
  const back = await page.evaluate(() => document.activeElement && document.activeElement.classList.contains('menu-btn'));
  I['tiroir@360'] = { ouvertAuClavier: open, focusDansLeTiroir: inside, echapFerme: closed, focusRetourAuBouton: back };
  // Panneau « Écrans du prototype »
  await page.focus('.demo-pages summary'); await page.keyboard.press('Enter');
  const o2 = await page.evaluate(() => document.querySelector('.demo-pages').open);
  const fit = await page.evaluate(() => { const r = document.querySelector('.demo-pages nav').getBoundingClientRect(); return r.left >= 0 && r.right <= document.documentElement.clientWidth; });
  I['ecrans-du-prototype@360'] = { ouvertAuClavier: o2, panneauDansLeViewport: fit };
  await ctx.close();
}

// Onglets + dialogues (commande)
{
  const ctx = await ctxFor(1440); const page = await ctx.newPage();
  await page.goto(`file://${root}/commande.html`);
  await page.focus('#tab-livraisons');
  await page.keyboard.press('ArrowRight');
  const sel1 = await page.evaluate(() => [document.querySelector('[role=tab][aria-selected=true]').id, !document.getElementById('panel-accord').hidden, document.getElementById('panel-livraisons').hidden]);
  await page.keyboard.press('End');
  const sel2 = await page.evaluate(() => document.querySelector('[role=tab][aria-selected=true]').id);
  await page.keyboard.press('Home');
  const sel3 = await page.evaluate(() => document.querySelector('[role=tab][aria-selected=true]').id);
  I['onglets@1440'] = { fleche_droite: sel1, fin: sel2, debut: sel3 };
  // lien profond (#finances) puis changement de hash dans la même page
  await page.goto(`file://${root}/commande.html#finances`);
  await page.reload();
  I['onglets-lien-profond'] = await page.evaluate(() => document.querySelector('[role=tab][aria-selected=true]').id);
  await page.evaluate(() => { location.hash = '#historique'; });
  I['onglets-hashchange'] = await page.evaluate(() => document.querySelector('[role=tab][aria-selected=true]').id);
  // dialogue valider
  await page.goto(`file://${root}/commande.html`);
  await page.focus('[data-open=dlg-validate]'); await page.keyboard.press('Enter');
  const d1 = await page.evaluate(() => { const d = document.getElementById('dlg-validate'); return { ouvert: d.open, focusDedans: d.contains(document.activeElement) }; });
  await page.keyboard.press('Escape');
  const d2 = await page.evaluate(() => ({ ferme: !document.getElementById('dlg-validate').open, retour: document.activeElement.getAttribute('data-open') }));
  // confirmation => résultat « Simulation »
  await page.click('[data-open=dlg-validate]');
  await page.click('#dlg-validate [data-confirm]');
  const d3 = await page.evaluate(() => ({ resultatVisible: !document.querySelector('#dlg-validate .dlg-result').hidden, texte: document.querySelector('#dlg-validate .dlg-result h3').textContent, focusSurTitre: document.activeElement.tagName === 'H3' }));
  I['dialogue-valider@1440'] = { ouverture: d1, fermeture: d2, resultat: d3 };
  // aucune modification réelle de l'état affiché
  await page.keyboard.press('Escape');
  I['etat-inchange-apres-validation'] = await page.evaluate(() => document.querySelector('.order-head .badge').textContent.trim());
  await ctx.close();
}
// Dialogue de demande (service) à 360 : plein écran, pied de page visible
{
  const ctx = await ctxFor(360); const page = await ctx.newPage();
  await page.goto(`file://${root}/service.html`);
  await page.click('#buy-cta');
  const r = await page.evaluate(() => { const d = document.getElementById('request'); const b = d.getBoundingClientRect(); const f = d.querySelector('.dlg-form .modal-foot').getBoundingClientRect(); return { ouvert: d.open, largeur: Math.round(b.width), hauteur: Math.round(b.height), viewportH: innerHeight, pieDansViewport: f.bottom <= innerHeight + 1 && f.top >= 0, scrollCorps: getComputedStyle(document.body).overflow }; });
  I['dialogue-demande@360'] = r;
  await ctx.close();
}
// Galerie
{
  const ctx = await ctxFor(1440); const page = await ctx.newPage();
  await page.goto(`file://${root}/service.html`);
  await page.focus('.gallery .th:nth-child(2)'); await page.keyboard.press('Enter');
  I['galerie'] = await page.evaluate(() => ({ src: document.querySelector('.gallery .stage img').getAttribute('src'), legende: document.querySelector('.gallery figcaption').textContent, pressed: document.querySelector('.gallery .th:nth-child(2)').getAttribute('aria-pressed') }));
  await ctx.close();
}
// Simulation : un clic sur un bouton « réel » ne doit afficher qu'un message de simulation
{
  const ctx = await ctxFor(1440); const page = await ctx.newPage();
  await page.goto(`file://${root}/tableau-de-bord.html`);
  const before = page.url();
  await page.click('a:has-text("Payer 45")');
  I['simulation-bouton-payer'] = await page.evaluate((b) => ({ memeUrl: location.href === b, toastVisible: !document.getElementById('toast').hidden, toast: document.getElementById('toast').textContent.trim().slice(0, 90) }), before);
  await ctx.close();
}
// Réduction des mouvements
{
  const ctx = await ctxFor(1440, { reducedMotion: 'reduce' }); const page = await ctx.newPage();
  await page.goto(`file://${root}/index.html`);
  I['reduced-motion'] = await page.evaluate(() => getComputedStyle(document.querySelector('.btn')).transitionDuration);
  await ctx.close();
}

// ---------- V01.1 : vérifications ciblées de la révision ----------
{
  const J = results.interactions;
  // 1. Examen de livraison : l'action principale mène aux fichiers, validation non favorisée, aucune obligation de télécharger
  for (const w of [360, 1440]) {
    const ctx = await ctxFor(w); const page = await ctx.newPage();
    await page.goto(`file://${root}/commande.html#finances`); await page.reload();
    const base = await page.evaluate(() => {
      const card = document.querySelector('.action-card');
      const primary = [...card.querySelectorAll('.btn-primary')].map((b) => b.textContent.trim());
      const dec = [...document.querySelectorAll('.choice .btn')].map((b) => ({ t: b.textContent.trim(), c: b.className }));
      const reqDl = /obligatoire|doit télécharger|avant de valider/i.test(document.querySelector('.decision-wrap').textContent);
      return { primaireCarteAction: primary, valideDansCarteAction: /Valider la livraison/.test(card.textContent), choix: dec.map((d) => d.t), memeStyleDesChoix: dec.length === 2 && dec[0].c === dec[1].c, aucuneObligationDeTelechargement: !reqDl };
    });
    await page.click('[data-goto]');
    await page.waitForTimeout(700);
    const after = await page.evaluate(() => { const t = document.getElementById('livraison-v2'); const r = t.getBoundingClientRect(); return { ongletLivraisons: document.querySelector('[role=tab][aria-selected=true]').id, focusSurLivraison: document.activeElement === t, cibleDansLEcran: r.top >= -2 && r.top < innerHeight }; });
    const sec = await page.evaluate(() => ({ nbControles: document.querySelectorAll('.file-line .sec').length, noteDistincte: /Contrôle de sécurité ≠ qualité du travail/.test(document.getElementById('panel-livraisons').textContent), v1Replie: !document.querySelector('details.prev').open }));
    J[`examen-livraison@${w}`] = { ...base, ...after, ...sec };
    await ctx.close();
  }
  // 2. Dépliants : repliés sur téléphone, ouverts sur ordinateur
  for (const w of [360, 1440]) {
    const ctx = await ctxFor(w); const page = await ctx.newPage();
    const res = {};
    for (const n of ['index', 'service', 'commande']) {
      await page.goto(`file://${root}/${n}.html`);
      res[n] = await page.evaluate(() => [...document.querySelectorAll('details.fold')].map((d) => d.open ? 'ouvert' : 'replié').join(','));
    }
    J[`depliants@${w}`] = res; await ctx.close();
  }
  // 3. Barre d'achat : cachée tant que le bouton principal est visible
  {
    const ctx = await ctxFor(360); const page = await ctx.newPage();
    await page.goto(`file://${root}/service.html`); await page.waitForTimeout(300);
    const top = await page.evaluate(() => getComputedStyle(document.querySelector('.sticky-buy')).display);
    await page.evaluate(() => window.scrollTo(0, 1500)); await page.waitForTimeout(300);
    const mid = await page.evaluate(() => getComputedStyle(document.querySelector('.sticky-buy')).display);
    await page.evaluate(() => window.scrollTo(0, 0)); await page.waitForTimeout(300);
    const back = await page.evaluate(() => getComputedStyle(document.querySelector('.sticky-buy')).display);
    J['barre-achat@360'] = { enHaut: top, apresDefilement: mid, retourEnHaut: back };
    // Prix, délai, corrections, livrables dans le premier écran + bouton principal visible sans défilement
    const first = await page.evaluate(() => { const q = (sel) => { const e = document.querySelector(sel); const r = e && e.getBoundingClientRect(); return r ? Math.round(r.bottom) : null; }; return { hauteurEcran: innerHeight, bas_prix: q('.buy-summary .price-lg'), bas_faits: q('.buy-summary .facts-row'), bas_bouton: q('#buy-cta') }; });
    J['service-premier-ecran@360'] = first;
    await ctx.close();
  }
  // 4. Tableau de bord : ordre des sections et distinction des échéances
  for (const w of [360, 1440]) {
    const ctx = await ctxFor(w); const page = await ctx.newPage();
    await page.goto(`file://${root}/tableau-de-bord.html`);
    const r = await page.evaluate(() => {
      const y = (id) => document.getElementById(id).getBoundingClientRect().top + scrollY;
      const x = (id) => document.getElementById(id).getBoundingClientRect().left;
      const dom = ['h-todo', 'h-orders', 'h-missions', 'h-stats'].map((id) => [...document.querySelectorAll('h2')].indexOf(document.getElementById(id)));
      const tasks = [...document.querySelectorAll('.task')].map((t) => ({ titre: t.querySelector('.t').textContent, echeance: t.querySelector('.due').textContent.replace(/\s+/g, ' ').trim().slice(0, 70), info: t.querySelector('.due').classList.contains('due-info') }));
      return { ordreDansLeDocument: dom.every((v, i) => i === 0 || v > dom[i - 1]), yActions: Math.round(y('h-todo')), yCommandes: Math.round(y('h-orders')), yAutres: Math.round(y('h-missions')), yChiffres: Math.round(y('h-stats')), xAutres: Math.round(x('h-missions')), xCommandes: Math.round(x('h-orders')), tasks, boutonsPleins: document.querySelectorAll('.task .btn-primary').length, enTeteSansPublier: !document.querySelector('.site-header .header-cta'), enTeteSansNavPrincipale: !document.querySelector('.site-header .main-nav'), lienCatalogue: !!document.querySelector('.site-header .cat-link'), hauteurPied: Math.round(document.querySelector('footer').getBoundingClientRect().height) };
    });
    J[`tableau-de-bord@${w}`] = r; await ctx.close();
  }
  // 5. Confiance : e-mail discret, pas de pastille « vérifié » ; démonstration non répétée
  {
    const ctx = await ctxFor(1440); const page = await ctx.newPage();
    await page.goto(`file://${root}/service.html`);
    J['confiance-service'] = await page.evaluate(() => ({ pastillesEmail: [...document.querySelectorAll('.badge')].filter((b) => /e-mail|vérifi/i.test(b.textContent)).length, noteEmail: document.querySelector('.note-email').textContent.replace(/\s+/g, ' ').trim(), marquesDemo: document.querySelectorAll('.tag-demo').length }));
    await page.goto(`file://${root}/index.html`);
    J['accueil'] = await page.evaluate(() => ({ cartesPrestations: document.querySelectorAll('.svc').length, imagesChargees: [...document.querySelectorAll('.svc img')].every((i) => i.complete && i.naturalWidth > 0), avisOuNotes: document.querySelectorAll('[class*=rating], [class*=review], [class*=star]').length, marquesDemo: document.querySelectorAll('.tag-demo').length }));
    await ctx.close();
  }
  // 6. Onglets à 360 px : cinq rubriques visibles, équilibrées, onglet actif évident
  {
    const ctx = await ctxFor(360); const page = await ctx.newPage();
    await page.goto(`file://${root}/commande.html`);
    J['onglets-360'] = await page.evaluate(() => { const t = [...document.querySelectorAll('.tab')].map((b) => { const r = b.getBoundingClientRect(); return { n: b.textContent.trim().replace(/\d+$/, ''), l: Math.round(r.left), r: Math.round(r.right), top: Math.round(r.top), h: Math.round(r.height), actif: b.getAttribute('aria-selected') === 'true', ombre: getComputedStyle(b).boxShadow !== 'none', fond: getComputedStyle(b).backgroundColor }; }); return { onglets: t, tousDansLEcran: t.every((x) => x.l >= 0 && x.r <= innerWidth), lignes: [...new Set(t.map((x) => x.top))].length }; });
    await ctx.close();
  }
}

// ---------- liens internes ----------
for (const n of PAGES) {
  const html = fs.readFileSync(`${root}/${n}.html`, 'utf8');
  const hrefs = [...html.matchAll(/(?:href|src)="([^"#][^"]*)"/g)].map((m) => m[1]).filter((h) => !/^(https?:|data:|mailto:)/.test(h));
  const missing = [...new Set(hrefs)].filter((h) => !fs.existsSync(path.join(root, h.split('#')[0])));
  const anchors = [...html.matchAll(/href="([^"]*)#([^"]+)"/g)].map((m) => ({ file: m[1] || (n + '.html'), id: m[2] }));
  const badAnchors = anchors.filter((a) => { const f = path.join(root, a.file || (n + '.html')); if (!fs.existsSync(f)) return true; return !new RegExp(`id="${a.id}"`).test(fs.readFileSync(f, 'utf8')); }).map((a) => `${a.file}#${a.id}`);
  results.links[n] = { missingFiles: missing, badAnchors: [...new Set(badAnchors)] };
}

await browser.close();
fs.writeFileSync(path.join(outDir, 'resultats-v01.json'), JSON.stringify(results, null, 1));
// synthèse
let issues = 0;
for (const [k, v] of Object.entries(results.pages)) {
  const p = [];
  if (v.docScrollOverflow > 0) p.push(`défilement horizontal +${v.docScrollOverflow}px`);
  if (v.offViewport.length) p.push('hors viewport: ' + v.offViewport.slice(0, 3).join('; '));
  if (v.clipped.length) p.push('tronqué: ' + v.clipped.join(', '));
  if (v.textOverlaps.length) p.push('chevauchement: ' + v.textOverlaps.slice(0, 2).join(' | '));
  if (v.smallTargets.length) p.push('cibles <44: ' + v.smallTargets.slice(0, 3).join(' | '));
  if (p.length) { issues++; console.log(k, '→', p.join(' || ')); }
}
console.log('combinaisons avec constats:', issues, '/', Object.keys(results.pages).length);
console.log('erreurs console/réseau:', results.console.length);
