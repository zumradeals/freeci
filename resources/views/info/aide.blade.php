<x-layouts.public :title="$title" :robots="$approved ? null : 'noindex, nofollow'">
<div class="container" style="padding-block:32px;max-width:760px">
  <h1 class="t-h1">{{ $title }}</h1>
  <x-fc.draft-banner :approved="$approved" />
  <div class="stack-sm" style="margin-top:16px">
    <details class="card" open><summary><strong>Je n’arrive pas à me connecter</strong></summary><p>Utilisez « Mot de passe oublié » sur la page de connexion. Un lien de réinitialisation n’est envoyé que si le courrier est configuré sur le service.</p></details>
    <details class="card"><summary><strong>Comment changer mon adresse e-mail, mon mot de passe ou fermer mon compte ?</strong></summary><p>Depuis votre espace, rubrique <strong>Compte</strong> : informations, mot de passe, sessions, export de vos données et demande de fermeture. Le changement d’adresse n’a lieu qu’après vérification de la nouvelle adresse.</p></details>
    <details class="card"><summary><strong>Où suivre ma commande ?</strong></summary><p>Dans votre espace, rubrique <strong>Commandes</strong> : chaque étape, l’accord figé et les actions attendues y figurent.</p></details>
    <details class="card"><summary><strong>Je suis en désaccord avec une livraison</strong></summary><p>Demandez d’abord une correction dans les limites de l’accord. Si le désaccord persiste, utilisez la demande de désaccord de la commande ; l’assistance ouvre alors un dossier.</p></details>
    <details class="card"><summary><strong>Comment signaler un contenu ?</strong></summary><p>Chaque profil, service, mission, message et avis public propose « Signaler ». Le signalé n’en est pas informé.</p></details>
    <details class="card"><summary><strong>Y a-t-il une garantie de paiement ?</strong></summary><p>Les garanties et modalités de paiement réel ne sont pas encore définies ; aucune garantie n’est annoncée à ce stade.</p></details>
    <p>Besoin d’une aide personnalisée ? Voir la page <a href="{{ route('info', 'contact') }}">Contact</a>.</p>
  </div>
</div></x-layouts.public>
