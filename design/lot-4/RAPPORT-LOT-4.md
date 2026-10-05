# Vérification — lot 4 (livraison, corrections, report, validation)

Date : 2026-10-05. PHP 8.3, PostgreSQL 16, Chromium (Playwright), axe-core 4.13.

- **133 tests réussis** (dont 18 nouveaux, `DeliveryCycleTest`) : cycle complet ; autorisations (client, freelance, tiers, administrateur) ; brouillon privé ; fichiers obligatoires, quarantaine, fichier infecté, scanner absent ; limite de corrections et double soumission ; version périmée ; validation explicite et unique ; immutabilité en base (livraisons, corrections, reports, fichiers livrés, échéance) ; report accepté/refusé/retiré ; silence du client ; tableaux de bord.
- **Parcours réel dans le navigateur** (deux comptes, 360 et 1440 px) : demande → acceptation → paiement simulé (page du lot 3 examinée) → report → brouillon → livraison v1 → correction → v2 → validation → clôture. Aucun défilement horizontal, aucune erreur console, **0 violation axe-core** sur les écrans capturés (paiement, dossiers, brouillon, confirmations, tableaux de bord).
- Un fichier de livraison contrôlé a été **inséré directement** pour la capture (ClamAV non installé dans cet environnement) ; la chaîne de contrôle est couverte par les tests avec un analyseur de test.
- Non réalisé : appareils réels, lecteurs d'écran, autres navigateurs, charge, test de concurrence multi-processus (verrous de ligne et contraintes d'unicité vérifiés par tests séquentiels et par la base).
