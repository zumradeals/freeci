# Rapport de vérification — lot 2 (demande de prestation et commande)

Date : 2026-10-05. PHP 8.3.6, PostgreSQL 16, Chromium (Playwright), axe-core 4.13. A = automatisé ; B = examen visuel par l'auteur ; C = non réalisé.

## A. Automatisé
- **86 tests, 723 assertions, tous réussis** (PostgreSQL). Nouveaux : `OrderRequestFlowTest` (15), `OrderAccessTest` (9), `OrderSpacesTest` (8).
- **Parcours complet à deux comptes** (visiteur → connexion → demande → dossier → réception côté freelance → acceptation → « en attente de paiement »), y compris avec les comptes créés par `freeci:demo:recette`.
- **Accès interdit** : tiers, administrateur, mauvaise partie, visiteur ; dossier interdit **identique** à dossier inexistant ; listes bornées ; champs falsifiés ignorés.
- **Transitions invalides** : accepter deux fois, accepter après refus ou expiration, retirer après acceptation, annuler avant acceptation, version périmée ; la machine d'états n'a aucune transition vers le travail sans paiement.
- **Doubles soumissions** : même clé = un seul effet ; même clé, contenu différent = 409 ; deuxième demande en attente = 409.
- **Conservation des conditions** : modification du service après la demande sans effet sur l'accord ; service modifié avant l'envoi = 409 avec conditions à jour ; accord et historique en ajout seul (déclencheurs).
- **Expiration** : après 48 h ; une commande acceptée n'expire pas tant que le paiement est fermé ; avec paiement ouvert (configuration de test), l'échéance de 24 h s'applique.
- **Mise à jour d'une base existante** : migration rejouée sur une base avec données (16 services, 10 comptes) : comptes et rôles intacts, services d'exemple passés en « demandes fermées », aucune donnée de démonstration ajoutée.
- Navigateur, 5 largeurs (360, 390, 768, 1024, 1440) : aucun défilement horizontal, aucune erreur console ; onglets du dossier ; axe-core : **0 violation** sur le service, le formulaire, le tableau de bord et le dossier (client et freelance) à 360 et 1440 px.

## B. Examen visuel
Captures dans `captures/` (360 et 1440 px pour toutes les pages ; 390, 768, 1024 pour les écrans principaux). Les écrans réutilisent les composants V01.1 (carte d'action, onglets, cartes de commande, fil d'étapes, historique). Écarts volontaires : l'onglet « Livraisons » n'existe pas (hors lot) ; les actions passent par une page de confirmation (objet, conséquences, boutons) plutôt qu'un dialogue, pour fonctionner sans JavaScript.

## C. Non réalisé / limites
Appareils réels, autres navigateurs, lecteurs d'écran, tests utilisateurs, charge. Pas de notification par courrier. Brief textuel (pas de fichiers). Pas de paiement, messagerie, report, livraison, litige, avis, mission. Gestion des services par le freelance : lecture seule.
