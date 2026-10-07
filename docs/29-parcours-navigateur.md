# 29 — Parcours automatisé dans un navigateur

**Statut : outil de contrôle, sans effet sur l'application.** Il ne déploie rien et n'utilise jamais la base de développement ni celle de production.

## Ce qu'il vérifie
- Pages publiques (accueil, services, missions, inscription, connexion, conditions) à 360, 768 et 1440 px : réponse 200, aucune erreur JavaScript ou de console, aucun débordement horizontal.
- Catalogue → fiche d'un service (un seul titre principal).
- Inscription avec un mot de passe trop faible : refus affiché, sans quitter la page.
- Clavier : le premier arrêt de tabulation est le lien « Aller au contenu ».
- Connexion d'un compte client, puis Espace, Commandes, Messages, Notifications, Compte, Assistance (200, sans erreur JavaScript).
- Menu mobile de l'espace (ouverture, navigation visible) et absence de débordement sur téléphone.

## Lancer
```
tests/e2e/run.sh
```
Le script recrée la base jetable `freeci_e2e` (jeu de démonstration local), remplace le mot de passe du compte client de démonstration par un secret aléatoire jetable, démarre le serveur local sur le port 8099 puis exécute `tests/e2e/browser-smoke.mjs`. Prérequis : PostgreSQL local, Chromium et Playwright (variables `CHROMIUM` et `PLAYWRIGHT_MODULE` si les chemins par défaut diffèrent). Code de sortie non nul au moindre échec.

## Limites connues
- Pas de parcours d'achat complet (commande, paiement sandbox, livraison) : il reste couvert par les tests PHP, pas par le navigateur.
- Un seul navigateur (Chromium) ; pas de test sur de vrais téléphones ni avec un lecteur d'écran (voir Q2 du registre).
- Pas d'intégration automatique à une chaîne de validation continue : à lancer à la demande.
