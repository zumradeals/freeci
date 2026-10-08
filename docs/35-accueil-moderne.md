# Accueil moderne — 8 octobre 2026

Implémentation de la maquette visuelle approuvée par le porteur : hero marine illustré, recherche, raccourcis, repères de commande, catégories compactes, services, deux entrées et parcours en quatre étapes.

Les données du catalogue et les composants de service restent ceux de l'application. Aucun service, prix, avis ou compteur fictif n'est ajouté. Recherche, menus, destinations des boutons, annonce et textes configurables restent branchés sur les routes et réglages existants. Les valeurs déjà personnalisées en administration priment sur les nouveaux textes par défaut ; elles ne sont pas écrasées.

L'illustration décorative du hero a été générée pour FreeCI, puis exportée en WebP 640 et 1200 pixels (environ 42 et 117 Ko). Titres, recherche et étapes sont du HTML, pas du texte dans l'image. Les styles supplémentaires sont dans `resources/css/home.css`. Aucun changement au paiement, aux commandes ou aux données. Aucune migration ni variable supplémentaire.

## Vérifications réalisées

- Compilation Vite et `git diff --check` réussis.
- Toutes les vues Blade compilées avec PHP WASM ; accueil rendu par Laravel avec des projections de catalogue de vérification, sans base de données.
- Chromium : rendu de cet HTML à 360, 390, 768, 1024 et 1440 pixels, sans dépassement horizontal ni image manquante.
- Captures à 360 et 1440 pixels examinées ; axe-core : aucune violation à ces deux largeurs.
- Assertions existantes de titre par défaut mises à jour.

Limites : pas de suite PostgreSQL exécutée dans cet environnement, pas de navigation avec serveur Laravel actif, ni de vérification Safari ou appareil réel. Les données utilisées pour le rendu sont temporaires et ne sont ni versionnées ni injectées dans le site. Le script Livewire était neutralisé dans cet aperçu statique ; ses échanges serveur ne sont donc pas validés ici.

## Déploiement

Utiliser `deploy/update.sh` avec le SHA publié. Le script reconstruit les ressources et les caches. Le paiement reste dans le mode déjà configuré sur le VPS. Si un ancien titre personnalisé apparaît encore, le modifier dans les réglages d'accueil de l'administration.
