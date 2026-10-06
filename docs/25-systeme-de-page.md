# Système de page issu de « Mes revenus » (lot 15)

La page `/freelance/revenus` sert de **référence**. Son anatomie est devenue un jeu de composants partagés (CSS `freeci.css`, section « Lot 15 ») ; ses classes `earnings-*` sont remplacées par les classes génériques, sans changement de contenu ni de calcul.

| Élément | Classes | Usage |
|---|---|---|
| En-tête | `.page-head` (rubrique, titre, phrase d'usage `.lead`) | toutes les pages des espaces et de l'administration |
| Carte à titre interne | `.card.panel` + `.card-head` (titre à gauche, précision `.meta-r` ou lien à droite) | toute section ; plus de titres hors carte |
| Tuiles de chiffres | `.metrics` / `.metric` (+ `.metric-featured`, `.metrics-dashed` pour le test) ; tuile-lien `a.metric` | synthèses : revenus, paiements, tableaux de bord, administration |
| Principal + latéral | `.split` (2/3 – 1/3 dès 1 200 px) | tableaux de bord, formulaire + guide |
| Formulaire | `.form-grid` (2 colonnes), `.span-all` | formulaires de carte |
| Lignes séparées | `.record`, `.record-head`, `.record-amounts` ; les listes `.tasks` et `.order-list` rendues dans une carte prennent le même style | commandes, dossiers, notifications, détail par commande |
| Aperçu test | `.metrics-dashed` + étiquette « Aucun argent réel » | séparation réel / test |

## Pages alignées
Revenus (référence, migrée vers les classes génériques) ; tableaux de bord client et freelance (nouveau compte : cartes de démarrage ; compte actif : synthèse, à faire, commandes récentes + raccourcis) ; Paiements du client (synthèse réelle / test + détail par commande) ; Commandes, Notifications, Assistance (listes dans une carte à titre) ; tableau de bord et Exploitation de l'administration (tuiles, cartes). Toutes les pages avec un en-tête reçoivent une phrase d'usage.

## Limites
Les pages de formulaire à tâche unique (service, mission, litige…), la messagerie, le détail d'une commande et les listes d'administration (modération, utilisateurs, assistance) gardent leur structure : elles reprennent l'en-tête et les espacements communs mais pas encore les cartes à titre interne. À migrer au fil des prochains lots en utilisant les classes ci-dessus. Vérification visuelle locale à 360, 768, 1 024, 1 440 et 1 920 px : aucun débordement horizontal.
