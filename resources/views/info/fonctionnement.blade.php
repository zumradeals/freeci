<x-layouts.public :title="$title" :robots="$approved ? null : 'noindex, nofollow'">
<div class="container" style="padding-block:32px;max-width:760px">
  <h1 class="t-h1">{{ $title }}</h1>
  <x-fc.draft-banner :approved="$approved" />
  <div class="stack-sm" style="margin-top:16px">
    <p>FreeCI met en relation des clients et des freelances indépendants en Côte d’Ivoire. Chaque freelance reste indépendant : FreeCI est un espace de mise en relation et de suivi, pas l’employeur des freelances.</p>
    <h2 class="t-h2">Le parcours d’une commande</h2>
    <ol>
      <li><strong>Choisir.</strong> Parcourez les services (prix et délai annoncés), les freelances ou les missions publiées.</li>
      <li><strong>Demander.</strong> Le client envoie une demande ; le freelance l’accepte ou la refuse (avec son motif). Les conditions acceptées sont <strong>figées dans l’accord</strong> de la commande.</li>
      <li><strong>Payer.</strong> La commande démarre lorsque le paiement est confirmé côté serveur et que le brief est complet.</li>
      <li><strong>Livrer.</strong> Le freelance dépose sa livraison ; le client peut la valider ou demander une correction dans les limites de l’accord.</li>
      <li><strong>Clôturer.</strong> Après validation explicite, la commande est clôturée et le client peut laisser un avis.</li>
    </ol>
    <h2 class="t-h2">Messagerie, assistance et litiges</h2>
    <p>Les échanges se font dans la messagerie privée de la plateforme. En cas de difficulté, l’assistance (depuis votre espace) peut ouvrir un dossier ; un litige sur une commande payée est traité par des personnes habilitées, selon les faits au dossier.</p>
    <h2 class="t-h2">Avis</h2>
    <p>Seules les commandes réelles, validées puis clôturées, donnent lieu à un avis public, après un délai de publication. Les avis ne sont jamais modifiés par l’administration ; ils peuvent être masqués avec un motif en cas de non-respect des règles.</p>
    <h2 class="t-h2">Paiement</h2>
    <p>@unless(\App\Integrations\Payments\PaymentMode::isLive())Le service est actuellement en <strong>mode test</strong> : aucun argent réel n’est utilisé. @endunless Les modalités de paiement réel, les frais éventuels et les garanties offertes ne sont <strong>pas encore arrêtés</strong> et seront publiés avant l’ouverture aux paiements réels.</p>
  </div>
</div></x-layouts.public>
