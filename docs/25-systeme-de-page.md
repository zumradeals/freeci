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

## Suite (lot 18) — finitions
- Pages de création et de formulaire alignées sur l'anatomie de référence : **Créer un service**, **Publier une mission**, **Contacter le support**, **Préférences de notification** (en-tête avec phrase d'usage, carte à titre interne, aide à droite reprenant les textes existants). Les tuiles « kpi » de l'administration prennent le rendu des tuiles de chiffres (carte blanche, tuiles sur fond de page). Pastilles de rubriques lisibles sur fond clair (Paramètres). Menu d'administration : groupe « Support » (évite deux repères portant le même nom).
- **Contrôle d'accessibilité automatisé** (axe-core, règles WCAG 2.0/2.1 A et AA + bonnes pratiques) sur 49 pages publiques, client, freelance et administration à 1 440 px : 2 anomalies trouvées (contraste des pastilles de rubriques, repères de navigation en double), corrigées ; **0 anomalie** au second passage. Aucun débordement horizontal à 360 et 768 px.
- **Limites** : un contrôle automatisé ne couvre qu'une partie des critères (≈ 30 à 40 %) : le parcours au clavier, les lecteurs d'écran, les agrandissements du texte et les vrais téléphones restent à essayer à la main. Le détail d'une commande, la messagerie et le profil freelance gardent leur structure.
