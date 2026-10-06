<x-layouts.info :title="$title" :slug="$slug" :approved="$approved">
  <div class="prose">
    <p>Ce projet décrit les données traitées par la plateforme telle qu’elle fonctionne aujourd’hui. Il n’a pas été adopté comme politique définitive.</p>
    <h2 class="t-h2">Données traitées</h2>
    <ul><li>Compte : nom, adresse e-mail, mot de passe (stocké sous forme chiffrée irréversible).</li><li>Profil freelance, services, missions et propositions que vous publiez.</li><li>Commandes, messages, fichiers de brief et de livraison, avis, favoris, notifications.</li><li>Paiements et opérations financières liés à vos commandes ; destination de reversement du freelance (chiffrée).</li><li>Journaux de sécurité (événements de connexion, adresse IP).</li></ul>
    <h2 class="t-h2">Qui y accède</h2><p>Vos messages, brief et livraisons sont réservés aux parties de la commande. L’administration n’a pas d’accès implicite aux commandes : un accès pour traiter un dossier d’assistance est motivé et journalisé. Vos favoris sont privés.</p>
    <h2 class="t-h2">Vos droits</h2><p>Depuis <strong>Compte</strong> : modifier vos informations, exporter vos données, demander la fermeture de votre compte (anonymisation, sauf éléments à conserver).</p>
    <h2 class="t-h2">Durées de conservation</h2><p><em>À arrêter par l’exploitant.</em> Aucune durée n’est annoncée à ce stade pour les commandes, messages, écritures financières, dossiers d’assistance et journaux de sécurité.</p>
    <h2 class="t-h2">Responsable du traitement et contact</h2><p>@if($legal['operator_name']){{ $legal['operator_name'] }}@else<em>à renseigner</em>@endif — @if($legal['contact_email']){{ $legal['contact_email'] }}@else<em>contact à renseigner</em>@endif</p>
  </div>
</x-layouts.info>
