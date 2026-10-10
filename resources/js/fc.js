/* FreeCI — JavaScript de présentation uniquement (tiroir, dialogues, galerie, dépliants, barre d'achat).
   Aucune règle métier : toute action passe par un formulaire serveur. Alpine.js et Livewire sont fournis par Livewire. */
(function () {
  "use strict";

  document.addEventListener("click", function (e) {
    var open = e.target.closest("[data-open]");
    if (open) {
      e.preventDefault();
      var d = document.getElementById(open.getAttribute("data-open"));
      if (d && d.showModal) {
        d.__opener = open;
        var res = d.querySelector(".dlg-result"), form = d.querySelector(".dlg-form");
        if (res) res.hidden = true;
        if (form) form.hidden = false;
        d.showModal();
      }
    }
    var close = e.target.closest("[data-close]");
    if (close) {
      var dlg = close.closest("dialog");
      if (dlg) dlg.close();
    }
    // clic sur le fond d'un dialogue = fermeture
    if (e.target.tagName === "DIALOG") e.target.close();
  });

  document.querySelectorAll("dialog").forEach(function (d) {
    d.addEventListener("close", function () {
      if (d.__opener && d.__opener.focus) d.__opener.focus();
    });
  });

  // Onglets (motif ARIA : flèches, Début, Fin ; lien profond par #hash)
  document.querySelectorAll("[role=tablist]").forEach(function (list) {
    var tabs = Array.prototype.slice.call(list.querySelectorAll("[role=tab]"));
    function select(tab, focus) {
      tabs.forEach(function (t) {
        var on = t === tab;
        t.setAttribute("aria-selected", on ? "true" : "false");
        t.tabIndex = on ? 0 : -1;
        var p = document.getElementById(t.getAttribute("aria-controls"));
        if (p) p.hidden = !on;
      });
      if (focus) tab.focus();
    }
    tabs.forEach(function (t, i) {
      t.addEventListener("click", function () {
        select(t, false);
        if (history.replaceState) history.replaceState(null, "", "#" + t.getAttribute("aria-controls").replace("panel-", ""));
      });
      t.addEventListener("keydown", function (e) {
        var k = e.key, n = null;
        if (k === "ArrowRight" || k === "ArrowDown") n = tabs[(i + 1) % tabs.length];
        else if (k === "ArrowLeft" || k === "ArrowUp") n = tabs[(i - 1 + tabs.length) % tabs.length];
        else if (k === "Home") n = tabs[0];
        else if (k === "End") n = tabs[tabs.length - 1];
        if (n) { e.preventDefault(); select(n, true); }
      });
    });
    function fromHash() {
      var wanted = location.hash && document.getElementById("panel-" + location.hash.slice(1));
      if (wanted) {
        var t = tabs.filter(function (x) { return x.getAttribute("aria-controls") === wanted.id; })[0];
        if (t) select(t, false);
      }
    }
    fromHash();
    window.addEventListener("hashchange", fromHash);
    window.addEventListener("fc:tab", function (e) {
      var t = tabs.filter(function (x) { return x.getAttribute("aria-controls") === "panel-" + e.detail; })[0];
      if (t) select(t, false);
    });
  });

  // Galerie du détail de service
  document.querySelectorAll("[data-gallery]").forEach(function (g) {
    var main = g.querySelector(".stage img"), cap = g.querySelector("figcaption");
    g.querySelectorAll(".th").forEach(function (b) {
      b.addEventListener("click", function () {
        g.querySelectorAll(".th").forEach(function (x) { x.setAttribute("aria-pressed", "false"); });
        b.setAttribute("aria-pressed", "true");
        main.src = b.getAttribute("data-src");
        main.alt = b.getAttribute("data-alt");
        var full = g.querySelector("[data-gallery-full]");
        if (full) full.href = b.getAttribute("data-src");
        if (cap) cap.textContent = b.getAttribute("data-cap");
      });
    });
  });

  // Dépliants : ouverts par défaut sur écran large, repliés sur téléphone (l'utilisateur garde la main ensuite)
  var wide = window.matchMedia("(min-width: 768px)");
  function foldDefaults() {
    document.querySelectorAll("details[data-open-desktop]").forEach(function (d) { d.open = wide.matches; });
  }
  foldDefaults();
  if (wide.addEventListener) wide.addEventListener("change", foldDefaults);

  // « Consulter les fichiers » : ouvre la rubrique, fait défiler jusqu'à la cible et y place le focus
  document.addEventListener("click", function (e) {
    var g = e.target.closest("[data-goto]");
    if (!g) return;
    e.preventDefault();
    var tab = g.getAttribute("data-goto-tab");
    if (tab) window.dispatchEvent(new CustomEvent("fc:tab", { detail: tab }));
    var target = document.getElementById(g.getAttribute("data-goto"));
    if (!target) return;
    var reduce = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    target.scrollIntoView({ behavior: reduce ? "auto" : "smooth", block: "start" });
    target.focus({ preventScroll: true });
  });

  // Barre d'achat fixe : visible seulement quand le bouton principal du résumé n'est plus à l'écran
  var bar = document.querySelector(".sticky-buy"), cta = document.getElementById("buy-cta");
  if (bar && cta && "IntersectionObserver" in window) {
    var io = new IntersectionObserver(function (en) {
      var inView = en[0].isIntersecting;
      var above = en[0].boundingClientRect.top < 0;
      bar.classList.toggle("is-off", inView || !above);
    });
    bar.classList.add("is-off");
    io.observe(cta);
  }

  // Double soumission : un formulaire marqué data-once ne part qu'une fois (le serveur la refuse aussi : clé d'opération).
  document.addEventListener("submit", function (e) {
    var f = e.target.closest("form[data-once]");
    if (!f) return;
    if (f.dataset.sent === "1") { e.preventDefault(); return; }
    f.dataset.sent = "1";
    // Désactivation APRÈS la construction des données du formulaire : un bouton désactivé n'enverrait pas son name/value
    // (ex. intent=submit) et le formulaire se comporterait comme le bouton « enregistrer ».
    setTimeout(function () {
      f.querySelectorAll("button[type=submit]").forEach(function (b) {
        b.setAttribute("aria-busy", "true");
        b.disabled = true;
        if (b.dataset.onceLabel) b.textContent = b.dataset.onceLabel;
      });
    }, 0);
  });
  window.addEventListener("pageshow", function (e) {
    if (!e.persisted) return;
    document.querySelectorAll("form[data-once]").forEach(function (f) { f.dataset.sent = "0"; f.querySelectorAll("button[type=submit]").forEach(function (b) { b.disabled = false; b.removeAttribute("aria-busy"); }); });
  });
})();

/* Menus de filtres (Budget, Délai, Autres catégories) : un seul ouvert à la fois ; se ferment au clic à l'extérieur et avec Échap. */
(function () {
  var open = function () { return document.querySelectorAll("details.sd-pop[open]"); };
  document.addEventListener("click", function (e) {
    open().forEach(function (d) { if (!d.contains(e.target)) d.removeAttribute("open"); });
  });
  document.addEventListener("keydown", function (e) {
    if (e.key !== "Escape") return;
    open().forEach(function (d) { d.removeAttribute("open"); var s = d.querySelector("summary"); if (s) s.focus(); });
  });
  document.addEventListener("toggle", function (e) {
    var t = e.target;
    if (!t.matches || !t.matches("details.sd-pop") || !t.open) return;
    open().forEach(function (d) { if (d !== t) d.removeAttribute("open"); });
  }, true);
  /* Choisir une tranche prédéfinie referme le menu. */
  document.addEventListener("click", function (e) {
    var a = e.target.closest && e.target.closest("details.sd-pop .sd-pop-panel a");
    if (a) { var d = a.closest("details"); setTimeout(function () { d.removeAttribute("open"); }, 0); }
  });
})();

/* Formulaires de catalogue sans Livewire : changer le tri recharge la liste. */
document.addEventListener("change", function (e) {
  var f = e.target.closest && e.target.closest("form[data-autosubmit]");
  if (f && e.target.matches("select")) f.submit();
});

/* Conversation : on ouvre sur le dernier message. */
document.querySelectorAll("[data-thread]").forEach(function (t) { t.scrollTop = t.scrollHeight; });

/* Pages d'accès : afficher ou masquer le mot de passe, barre de force (indicative ; le serveur reste seul juge). */
(function () {
  document.querySelectorAll("[data-reveal]").forEach(function (btn) {
    var input = document.getElementById(btn.dataset.reveal);
    if (!input) return;
    btn.hidden = false;
    btn.addEventListener("click", function () {
      var show = input.type === "password";
      input.type = show ? "text" : "password";
      btn.setAttribute("aria-pressed", show ? "true" : "false");
      btn.firstChild.nodeValue = show ? "Masquer" : "Afficher";
    });
  });
  document.querySelectorAll("[data-strength-for]").forEach(function (meter) {
    var input = document.getElementById(meter.dataset.strengthFor);
    if (!input) return;
    meter.hidden = false;
    var bars = meter.querySelectorAll("i");
    var score = function (v) {
      var s = 0;
      if (v.length >= 10) s += 1;
      if (/[A-Za-z]/.test(v) && /\d/.test(v)) s += 1;
      if (v.length >= 14) s += 1;
      if (/[^A-Za-z0-9]/.test(v) && v.length >= 10) s += 1;
      return s;
    };
    var paint = function () { var n = score(input.value); bars.forEach(function (b, i) { b.classList.toggle("on", i < n); }); };
    input.addEventListener("input", paint);
    paint();
  });
})();

/* Formulaire de proposition : résumé de l'offre en direct (indicatif, le serveur valide). */
(function () {
  var form = document.querySelector(".pr-form");
  if (!form) return;
  var $ = function (n) { return form.elements.namedItem(n); };
  var set = function (k, v) { var el = form.querySelector("[data-pr-" + k + "]"); if (el) el.textContent = v; };
  var plural = function (n, one, many) { return n + " " + (n > 1 ? many : one); };
  var refresh = function () {
    var price = Number(String($("price_xof").value).replace(/\D/g, "")) || 0;
    var days = Number($("delivery_days").value) || 0;
    var rev = Number($("revisions_included").value) || 0;
    var val = Number($("validity_days").value) || 0;
    set("price", price > 0 ? new Intl.NumberFormat("fr-FR").format(price) + " FCFA" : "à renseigner");
    set("days", days > 0 ? plural(days, "jour", "jours") : "à renseigner");
    set("rev", plural(rev, "correction", "corrections"));
    set("valid", val > 0 ? plural(val, "jour", "jours") : "à renseigner");
  };
  form.addEventListener("input", refresh);
  refresh();
})();

/* Paiement par jalons (proposition) : lignes supplémentaires masquées tant qu'elles sont vides, somme et délai total en direct (indicatif : le serveur valide). */
(function () {
  var box = document.querySelector("[data-ms-form]");
  if (!box) return;
  var toggle = box.querySelector("[data-ms-toggle]");
  var rows = [].slice.call(box.querySelectorAll("[data-ms-row]"));
  var form = box.closest("form");
  var num = function (v) { return Number(String(v).replace(/\D/g, "")) || 0; };
  var fmt = function (n) { return new Intl.NumberFormat("fr-FR").format(n); };
  var empty = function (r) { return [].every.call(r.querySelectorAll("input,textarea"), function (i) { return i.value.trim() === ""; }); };
  var sync = function () {
    var on = toggle.checked;
    box.querySelector("[data-ms-rows]").hidden = !on;
    box.querySelector("[data-ms-add]").hidden = !on;
    var days = form.elements.namedItem("delivery_days");
    if (days) { days.readOnly = on; var f = days.closest(".field"); if (f) f.style.opacity = on ? ".6" : ""; }
    var sum = 0, total = 0;
    rows.forEach(function (r) { sum += num(r.querySelector("[data-ms-price]").value); total += num(r.querySelector("[data-ms-days]").value); });
    box.querySelector("[data-ms-sum]").textContent = fmt(sum) + " FCFA";
    box.querySelector("[data-ms-total-days]").textContent = total + (total > 1 ? " jours" : " jour");
    if (on && days) days.value = total || "";
    var price = num(form.elements.namedItem("price_xof").value), chk = box.querySelector("[data-ms-check]");
    chk.textContent = !on ? "—" : (price > 0 && sum === price ? "✓ La somme des jalons égale le prix ferme" : "La somme doit égaler le prix ferme (" + fmt(price) + " FCFA)");
    chk.style.color = on && price > 0 && sum === price ? "#1a7f4b" : "";
  };
  rows.forEach(function (r) { if (r.hasAttribute("data-ms-extra") && empty(r)) r.hidden = true; });
  box.querySelector("[data-ms-add]").addEventListener("click", function () {
    var next = rows.filter(function (r) { return r.hidden; })[0];
    if (next) { next.hidden = false; var i = next.querySelector("input"); if (i) i.focus(); }
  });
  box.addEventListener("click", function (e) {
    if (!e.target.closest("[data-ms-remove]")) return;
    var r = e.target.closest("[data-ms-row]");
    r.querySelectorAll("input,textarea").forEach(function (i) { i.value = ""; });
    r.hidden = true; sync();
  });
  form.addEventListener("input", sync);
  toggle.addEventListener("change", sync);
  sync();
})();

/* Réalisations : agrandissement de l'image dans une boîte de dialogue native (Échap ferme, le focus revient sur la carte). Sans JavaScript, le lien ouvre l'image. */
(function () {
  "use strict";
  var dlg = document.getElementById("po-dlg");
  if (!dlg || typeof dlg.showModal !== "function") return;
  var img = dlg.querySelector("img");
  var cap = dlg.querySelector("[data-po-cap]");
  var last = null;
  document.addEventListener("click", function (e) {
    var a = e.target.closest("a[data-po-open]");
    if (a) {
      e.preventDefault();
      last = a;
      img.src = a.getAttribute("href");
      img.alt = a.getAttribute("data-po-title") || "";
      cap.textContent = a.getAttribute("data-po-title") || "";
      dlg.showModal();
      return;
    }
    if (e.target === dlg || e.target.closest("[data-po-close]")) dlg.close();
  });
  dlg.addEventListener("close", function () { img.removeAttribute("src"); if (last) last.focus(); });
})();

/* Formules et options (F-08) : total affiché pendant la sélection. AMÉLIORATION seulement : le serveur recalcule tout à l'envoi et sans JavaScript la page reste utilisable. */
document.querySelectorAll("[data-tier-form]").forEach(function (f) {
  var rows = f.querySelector("[data-tier-rows]");
  if (!rows) return;
  function fcfa(n) { return String(n).replace(/\B(?=(\d{3})+(?!\d))/g, " ") + " FCFA"; }
  function row(label, value, cls) {
    var d = document.createElement("div"); d.className = "r" + (cls ? " " + cls : "");
    var a = document.createElement("span"); a.textContent = label;
    var b = document.createElement("span"); b.textContent = value;
    d.appendChild(a); d.appendChild(b); return d;
  }
  function update() {
    var tier = f.querySelector('input[name="formule"]:checked');
    var needTier = !!f.querySelector('input[name="formule"]');
    var base = tier ? +tier.dataset.price : (needTier ? null : +f.dataset.basePrice);
    var days = tier ? +tier.dataset.days : (needTier ? null : +f.dataset.baseDays);
    var rev = tier ? +tier.dataset.rev : +f.dataset.baseRev;
    f.querySelectorAll(".tr-card").forEach(function (c) { var r = c.querySelector("input"); c.classList.toggle("sel", !!(r && r.checked)); });
    f.querySelectorAll(".tr-opt").forEach(function (c) { var r = c.querySelector("input"); c.classList.toggle("on", !!(r && r.checked)); });
    rows.textContent = "";
    if (base === null) { var p = document.createElement("p"); p.className = "muted small"; p.style.margin = "0"; p.textContent = "Choisissez une formule : le total s’affiche ici."; rows.appendChild(p); return; }
    var total = base, d = days;
    rows.appendChild(row(tier ? "Formule " + tier.dataset.name : "Prix du service", fcfa(base)));
    f.querySelectorAll('input[name="options[]"]:checked').forEach(function (o) {
      total += +o.dataset.price; d += +o.dataset.days;
      rows.appendChild(row(o.dataset.label, "+ " + fcfa(+o.dataset.price)));
    });
    d = Math.max(1, d);
    rows.appendChild(row("Total", fcfa(total), "tot"));
    rows.appendChild(row("Délai total", d + (d > 1 ? " jours" : " jour")));
    rows.appendChild(row("Corrections incluses", String(rev)));
  }
  f.addEventListener("change", update); update();
});

/* Éditeur de service : en mode « formules », les champs prix/délai/retouches d'offre unique sont masqués (le serveur dérive ces valeurs des formules). */
document.querySelectorAll('input[name="pricing_mode"]').forEach(function (r) {
  r.addEventListener("change", function () {
    var tiers = document.querySelector('input[name="pricing_mode"][value="tiers"]').checked;
    document.querySelectorAll("[data-single-fields]").forEach(function (e) { e.hidden = tiers; });
    document.querySelectorAll("[data-tier-fields]").forEach(function (e) { e.hidden = !tiers; });
  });
});
