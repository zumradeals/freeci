/* FreeCI — présentation uniquement : une indisponibilité passagère du serveur (502, 503, 504 : mise à jour, maintenance, surcharge)
   ne doit pas ouvrir la fenêtre d'erreur technique de Livewire. Un message discret s'affiche ; les compteurs réessaient seuls. */
(function () {
  "use strict";
  var notice = null;
  function show() {
    if (!notice) {
      notice = document.createElement("div");
      notice.setAttribute("role", "status");
      notice.className = "notice tone-warning";
      notice.style.cssText = "position:fixed;left:16px;right:16px;bottom:16px;max-width:32rem;margin:0 auto;z-index:50";
      notice.textContent = "Connexion momentanément indisponible. Nouvelle tentative automatique ; si cela dure, rechargez la page dans une minute.";
      document.body.appendChild(notice);
    }
    notice.hidden = false;
  }
  document.addEventListener("livewire:init", function () {
    window.Livewire.hook("request", function (ctx) {
      ctx.fail(function (r) {
        if (r.status === 502 || r.status === 503 || r.status === 504) { r.preventDefault(); show(); }
      });
      ctx.succeed(function () { if (notice) notice.hidden = true; });
    });
  });
})();
