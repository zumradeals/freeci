# Rapport de vérification — lot 1 (socle et découverte des services)

Date : 2026-10-05. Environnement : PHP 8.3.6, PostgreSQL 16, Node 22, Chromium (Playwright 1.56), axe-core 4.13.0 (hors dépôt). **Niveaux de preuve** : A = automatisé ; B = examen visuel par l'auteur (pas un test utilisateur) ; C = non réalisé.

## A. Automatisé

| Contrôle | Résultat |
|---|---|
| `php artisan test` (PostgreSQL, base `freeci_test`) | **33 tests, 166 assertions, tous réussis** |
| Pint (style) | réussi |
| Installation propre : copie des fichiers suivis dans un dossier vierge, base vierge, `composer install`, `migrate --seed`, `npm ci`, `npm run build`, tests | réussie (voir « Installation propre » ci-dessous) |
| Navigateur, 360 px et 1440 px : recherche en direct, URL mise à jour, filtre de catégorie, « Tout effacer », pagination (page 2 = 3 cartes), dialogue « Bientôt » (ouverture, Échap), tiroir mobile, dépliants (repliés < 768 px, ouverts ≥ 768 px) | réussi |
| axe-core (WCAG 2.0/2.1/2.2 A-AA + bonnes pratiques) sur 7 pages × 2 largeurs | **0 violation** après correction de l'ordre des titres du catalogue |
| Erreurs console, défilement horizontal (35 captures : 7 pages × 5 largeurs) | aucune |

### Ce que couvrent les tests
- **Authentification** : inscription (mot de passe haché, e-mail normalisé, mots de passe faibles / non confirmés / doublon refusés) ; connexion et déconnexion ; **message d'échec unique** (compte connu ou non) ; **verrouillage après 5 échecs** même avec le bon mot de passe ; redirection des visiteurs et absence de redirection externe ; pages invité inaccessibles une fois connecté ; récupération sans révéler l'existence d'un compte ; **jeton de réinitialisation à usage unique** ; espace privé en `private, no-store`.
- **Visibilité** : brouillon, « en contrôle » et programmé = 404 et absents des listes ; suspendu / archivé = 410 sans donnée ; aucune adresse e-mail dans les pages publiques ; la fiche annonce que la commande n'est pas ouverte.
- **Recherche** : accents et casse, préfixe de titre, texte du résumé, services non publiés exclus, caractères spéciaux (`%`, `_`, `'`, injection SQL, guillemets) traités comme du texte, filtre de catégorie, tris (dont valeur invalide), pagination (12 par page, pages disjointes), état dans l'URL sans JavaScript, composant Livewire.
- **Données de démonstration** : reproductibles et idempotentes ; vendeurs non connectables ; mot de passe client issu de la configuration ; refus en production ; **aucun mot de passe dans le dépôt** (test sur le code du jeu de données).
- **Architecture** : l'interface (contrôleurs, Livewire) n'importe aucun modèle.

## B. Examen visuel (auteur)
Comparaison côte à côte V01.1 / application (dossier `comparaison/`) pour l'accueil, la fiche de service et l'espace client, à 360 et 1440 px. Écarts relevés **et corrigés** : vignettes des cartes réduites à 96 px sur ordinateur (règle non bornée héritée du prototype) ; ordre des titres du catalogue. Écarts **volontaires** : en-tête public sans messagerie ni « Publier une mission » actif (fonctions à venir, repérées « Bientôt ») ; fiche de service sans « Contacter / Favori / Signaler » ni bloc e-mail confirmé ; bouton « Demander cette prestation » marqué « Bientôt » ; services en exemple plus nombreux (15) et illustrations réutilisées entre services voisins.

## C. Non réalisé / limites
- Aucun appareil réel, un seul moteur (Chromium) ; pas de lecteur d'écran ; pas de vrai zoom navigateur ; pas de test utilisateur ; pas de mesure de performance ni de charge.
- **Courrier non envoyé** : pilote `log` ; le lien de récupération n'est lisible que dans les journaux locaux.
- Vérification d'adresse e-mail non implémentée (décision à prendre : lien à usage unique, `02` §8.2).
- Pas de vrai fournisseur d'envoi, pas de limitation d'inscriptions par adresse IP au-delà de `throttle`, pas de MFA.
- La recherche française ne gère pas toutes les formes de mots (ex. « logos » ne retrouve pas « logo ») ; le préfixe de titre compense en partie.
- Le catalogue n'a pas encore de filtres par prix, délai ou ville (prévus par `04` §2.4).
- Perf PostgreSQL non mesurée au volume cible (N06).
