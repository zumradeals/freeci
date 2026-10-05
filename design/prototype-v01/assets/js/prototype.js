/* FreeCI — prototype V01 : JavaScript de présentation uniquement.
   Aucune requête réseau, aucune donnée enregistrée, aucune règle métier.
   Rôle : tiroir, onglets, modales, galerie, et messages « Simulation ». */
(function () {
  "use strict";

  var toast = document.getElementById("toast");
  var toastTimer = null;

  function say(message) {
    if (!toast) return;
    toast.querySelector(".msg").textContent = message;
    toast.hidden = false;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () { toast.hidden = true; }, 7000);
  }

  // Interactions simulées : aucun bouton n'accomplit d'opération réelle.
  document.addEventListener("click", function (e) {
    var sim = e.target.closest("[data-sim]");
    if (sim) {
      e.preventDefault();
      say(sim.getAttribute("data-sim") || "Action simulée : rien n'a été envoyé ni enregistré.");
    }
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
    var confirm = e.target.closest("[data-confirm]");
    if (confirm) {
      e.preventDefault();
      var dl = confirm.closest("dialog");
      dl.querySelector(".dlg-form").hidden = true;
      var r = dl.querySelector(".dlg-result");
      r.hidden = false;
      var h = r.querySelector("[tabindex='-1']");
      if (h) h.focus();
    }
    // clic sur le fond d'un dialogue = fermeture
    if (e.target.tagName === "DIALOG") e.target.close();
  });

  document.querySelectorAll("dialog").forEach(function (d) {
    d.addEventListener("close", function () {
      if (d.__opener && d.__opener.focus) d.__opener.focus();
    });
  });

  document.addEventListener("submit", function (e) {
    var f = e.target.closest("form[data-sim-form]");
    if (f) {
      e.preventDefault();
      say(f.getAttribute("data-sim-form"));
    }
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
})();
