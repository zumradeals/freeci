# Lot 12 — avis, favoris et découverte

## Avis
- Éligibilité : commande **réelle** (`environment = live`), validée explicitement par le client (événement `validated`), puis clôturée commercialement. Une clôture décidée par le support n'ouvre pas d'avis.
- Un avis par commande : note 1–5 + commentaire (10–1500 caractères), du client vers le freelance. Auteur affiché de façon anonyme. Aucun avis créé par un administrateur.
- Publication à `max(maintenant, clôture + délai)`. Délai **provisoire** : `FREECI_REVIEW_PUBLICATION_DAYS` (14 par défaut), figé au dépôt. Commande `freeci:reviews:publish` (horaire) : notifie le freelance.
- Réponse publique unique du freelance (≤ 1000 caractères), non modifiable.
- Note et commentaire immuables (déclencheur PostgreSQL) ; seul `hidden_at` change.
- Bac à sable : l'avis peut être déposé en test, il reste hors de toute page publique et de tout calcul de réputation.

## Réputation et modération
- Moyenne et nombre calculés à la lecture sur les seuls avis réellement publiés (aucun cache).
- Service : avis dont il est l'origine. Profil : tous les avis, avec ventilation service / mission. Aucun rattachement artificiel à un service.
- Signalement d'un avis ou d'une réponse via l'assistance (contenu public uniquement).
- Administration `/admin/avis` : masquer / rétablir avec motif, historique append-only, MFA + confirmation récente. Une note négative n'est pas un motif. Les agrégats se recalculent sans effacer l'historique.

## Favoris
Service ou freelance, privés, sans doublon, page « Favoris ». Un contenu retiré ou suspendu s'affiche « indisponible » sans donnée privée.

## Découverte
- Services : recherche, catégorie, prix, délai, compétence, tris explicites (récents, prix, « mieux notés » = moyenne des avis publiés puis nombre), pagination stable, filtres dans l'URL.
- Freelances : annuaire filtré (compétence, catégorie, tri). Missions : filtres catégorie, budget, délai ; pas de filtre par compétence (donnée non structurée).
- Aucun badge de confiance, « recommandé » ou indicateur de popularité.

## Limites
Avis client → freelance uniquement (réciprocité F36 non traitée). Délai de 14 jours non validé. Pas de moteur de recherche externe. Aucune commande réelle n'existe encore : aucun avis public avant l'activation du mode live.
