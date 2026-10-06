<x-layouts.public :title="$title" :robots="$approved ? null : 'noindex, nofollow'">
<div class="container" style="padding-block:32px;max-width:760px">
  <h1 class="t-h1">{{ $title }}</h1>
  <x-fc.draft-banner :approved="$approved" />
  <div class="stack-sm" style="margin-top:16px">
    <p>Ce projet décrit les règles de fonctionnement déjà appliquées par la plateforme. Il n’a pas encore été relu ni adopté sous forme de conditions définitives : <strong>il ne vaut pas contrat</strong> tant que cette mention figure.</p>
    <h2 class="t-h2">1. Objet</h2><p>FreeCI permet à des clients et à des freelances indépendants de se mettre en relation, de convenir d’un accord, de suivre une prestation et d’en consulter la situation financière.</p>
    <h2 class="t-h2">2. Comptes</h2><p>Un compte est personnel. Vous êtes responsable de la confidentialité de votre mot de passe. Un compte peut être suspendu en cas de manquement ; la suspension empêche de démarrer de nouvelles activités, sans interrompre les commandes en cours.</p>
    <h2 class="t-h2">3. Accord et commande</h2><p>Les conditions acceptées (prix, délai, livrables, corrections incluses) sont figées dans l’accord de la commande. Les délais de réponse et de paiement y figurent.</p>
    <h2 class="t-h2">4. Paiement, frais et garanties</h2><p><em>À rédiger par l’exploitant : modalités de paiement réel, frais et commission, remboursements, reversements, garanties.</em> Rien n’est promis à ce stade.</p>
    <h2 class="t-h2">5. Contenus et avis</h2><p>Les contenus publiés doivent respecter la loi et les règles de la plateforme. Un contenu peut être signalé puis masqué par une personne habilitée, avec un motif conservé.</p>
    <h2 class="t-h2">6. Fermeture du compte</h2><p>Vous pouvez demander la fermeture de votre compte ; elle n’est exécutée qu’en l’absence d’obligations en cours, et les éléments qui doivent être conservés le restent sous forme anonymisée.</p>
    <h2 class="t-h2">7. Droit applicable et litiges</h2><p><em>À rédiger par l’exploitant.</em></p>
  </div>
</div></x-layouts.public>
