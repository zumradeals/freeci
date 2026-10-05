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
})();
