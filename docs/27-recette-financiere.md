# Recette financière en sandbox — remboursements, litiges, reversements

**But** : vérifier, avec le vrai bac à sable Genius Pay, ce que les tests automatiques ne peuvent pas prouver (ils utilisent des réponses simulées). **Aucun argent réel n'est en jeu. Ne passez pas en mode live pendant cette recette.**

**Durée** : 2 à 3 heures. **Ce qu'il faut noter à chaque étape** : réussi / échec / remarque, plus la référence de la commande ou de l'opération et une capture d'écran en cas d'écart. Le tableau final (§10) sert de compte rendu.

> Ce que couvrent déjà les tests automatiques (réponses simulées) : les calculs, la réservation des fonds, l'unicité des opérations, les droits d'accès, l'absence de renvoi automatique. Ce que cette recette vérifie en vrai : le comportement **réel** du bac à sable (statut après un remboursement, délai, référence renvoyée), vos écrans tels que vous les voyez, et la lisibilité du parcours.

---

## 0. Préparation (à faire une fois)

| # | Action | Résultat attendu |
|---|---|---|
| 0.1 | Sauvegarde : `sudo -u freeci -H /var/www/freeci/deploy/backup.sh avant-recette-financiere` | Dossier de sauvegarde créé, message « Sauvegarde terminée » |
| 0.2 | Administration → **Paramètres** → rubrique **Paiement Genius Pay** : mode **Sandbox**, « Ouvrir les nouveaux paiements » coché, « Autoriser le paiement réel » **décoché** | Les clés sandbox sont « définies » |
| 0.3 | Même rubrique : bouton **Tester la connexion (bac à sable)** | Message vert « Genius Pay (sandbox) a répondu : clés acceptées » |
| 0.4 | Administration → **État et préparation** | Ligne « Genius Pay — bac à sable » : *Remplie* ; tâches planifiées « À jour » (le cron tourne) |
| 0.5 | **Trois comptes distincts** : un **client** (C), un **freelance** (F, profil publié, un service publié acceptant les demandes), l'**administrateur** (A). A ne doit être ni C ni F : sur un dossier d'assistance, une partie prenante ne peut pas le traiter | — |
| 0.6 | Noter le taux de commission affiché dans Paramètres (10 % par défaut) | Les montants attendus ci-dessous supposent **10 %** ; sinon recalculer : commission = base × taux, arrondie au franc |

**Valeurs d'exemple** : service à **35 000 FCFA**. Commission 10 % = **3 500** ; part du freelance = **31 500**.
Remboursement partiel de 10 000 : base 25 000 → commission **2 500**, part du freelance **22 500**.

**Pour chaque commande de la recette**, créez-la avec le même service afin de comparer. Chemin court d'une commande payée :
1. C : fiche du service → **Demander cette prestation** → envoyer.
2. F : **Demandes et commandes** → accepter.
3. C : sur la commande, **Paiement** → payer sur la page Genius Pay *sandbox* → revenir ; si l'état n'avance pas, bouton **Actualiser l'état**. Attendu : paiement « confirmé côté serveur ».
4. C : compléter le **brief** (et ses fichiers si le service l'exige) → la commande passe **En cours**.

---

## 1. Scénario A — désaccord, la prestation continue (aucun argent ne bouge)

| # | Qui | Où / action | Résultat attendu |
|---|---|---|---|
| A1 | F | Commande en cours → déposer une livraison (**Livraison** → soumettre) | La commande passe « Livrée » |
| A2 | C | Commande → **Litige ou annulation** → *Litige* → motif (20 caractères) → cocher « Je comprends ces conséquences » → **Ouvrir le dossier** | Dossier créé avec une référence ; la commande passe **« en litige »** ; livraison/corrections/validation suspendues |
| A3 | A | **Dossiers** → ouvrir le dossier → **M'affecter ce dossier** → **Ouvrir le dossier (accès journalisé)** | Les échanges des deux parties sont lisibles ; l'accès est journalisé |
| A4 | A | **Décision** : *Poursuite de la prestation* ; suite financière : *Aucune suite* ; motif ; cocher ; **Rendre la décision** | Décision enregistrée ; la commande **retourne à l'état d'avant** ; le blocage interne des reversements est **levé** |
| A5 | C, F | Voir la commande | Les deux voient la décision et son motif ; la commande est de nouveau actionnable |
| A6 | A | **Opérations** (Finances) | **Aucune** opération créée : une décision ne rembourse ni ne verse rien |

## 2. Scénario B — annulation après paiement, remboursement **total** par API

| # | Qui | Où / action | Résultat attendu |
|---|---|---|---|
| B1 | — | Nouvelle commande payée (chemin court §0), jusqu'à « En cours » | — |
| B2 | C | **Litige ou annulation** → *Demande d'annulation après paiement* → motif → confirmer | Dossier « annulation » ; commande « en litige » |
| B3 | A | Affecter et ouvrir le dossier ; **Décision** : *Annulation motivée de la commande* ; suite financière : **Remboursement** ; précision : « remboursement total » ; motif ; confirmer | Commande **annulée** ; la suite financière est « **à traiter** » : **rien n'est encore remboursé** |
| B4 | A | **Opérations** → section « Décisions du support à traiter financièrement » → **Préparer le remboursement** (montant vide = total) | Opération créée, état « à confirmer » ; **les fonds sont réservés** (35 000) |
| B5 | A | Ouvrir l'opération (lien de sa référence) : relire le récapitulatif (montant 35 000, bénéficiaire = C, environnement **TEST**) | Récapitulatif cohérent |
| B6 | A | **1. Confirmer le récapitulatif** : cocher « Je confirme… » → **Confirmer** | État « approuvée » ; « Confirmée par A » ; **aucun second approbateur demandé** |
| B7 | A | **2. Exécuter par API Genius Pay (remboursement total)** (une seule fois) | **À NOTER avec précision** : résultat obtenu (voir B8) |
| B8 | A | Observer l'état de l'opération | Trois issues acceptables, **à consigner** : (a) **Confirmée** avec une référence de remboursement du prestataire ; (b) **« À vérifier »** (réponse ou statut incertain : *aucun renvoi automatique*) ; (c) **Échec** explicite. Dans tous les cas **aucun deuxième envoi** n'a lieu tout seul |
| B9 | A | Si (b) : dans le **tableau de bord Genius Pay (sandbox)**, constater si le paiement est « remboursé » ; puis sur la page de l'opération : **Rapprochement manuel** → *Confirmer le remboursement constaté* (référence, justificatif, **retaper le montant**, case) — ou *Constater l'absence de remboursement* | État final « confirmée » (ou réservation libérée si absence constatée) |
| B10 | C | **Paiements** (espace client) et onglet « Finances » de la commande | Payé 35 000 ; remboursement affiché **« confirmé » seulement s'il l'est vraiment** (libellés prudents) ; commande de test signalée |
| B11 | A | **Opérations** → « Totaux » | Montants dans la colonne **Test**, **jamais** dans « Réel (live) » |

> **Important à relever** : l'ordre exact des messages de Genius Pay, la durée avant que le statut « remboursé » apparaisse, et la présence d'une référence de remboursement. Ce sont les points que la documentation ne garantit pas.

## 3. Scénario C — remboursement **partiel** (enregistré à la main)

| # | Qui | Où / action | Résultat attendu |
|---|---|---|---|
| C1 | — | Nouvelle commande payée, en cours ; C ouvre un **litige** | Dossier créé |
| C2 | A | **Décision** : *Poursuite de la prestation* ou *Annulation* selon le cas ; suite : **Répartition** ; précision obligatoire : « remboursement de 10 000 FCFA au client » | Décision enregistrée ; suite « à traiter » |
| C3 | A | **Opérations** → **Préparer le remboursement** avec montant **10 000** | Opération partielle ; 10 000 réservés ; plafond respecté |
| C4 | A | Tenter un montant **supérieur aux fonds disponibles** (ex. 40 000) | **Refusé** avec explication (plafond) |
| C5 | A | Confirmer l'opération (même parcours que B6) | « approuvée » |
| C6 | A | Sur la page : le texte indique que le partiel **n'est pas exécuté par API** | Pas de bouton « Exécuter par API » |
| C7 | A | **Effectuer le remboursement dans le tableau de bord Genius Pay (sandbox)** si l'option existe pour 10 000, sinon simuler le constat | — |
| C8 | A | **2. Enregistrer une exécution manuelle déjà effectuée** : référence externe, justificatif, **retaper 10 000**, case → **Enregistrer comme effectué** | État « confirmée » ; **référence externe unique** (la réutiliser sur une autre opération doit être **refusée**) |
| C9 | A | **Opérations** : le reversement futur de cette commande | Base recalculée : 35 000 − 10 000 = 25 000 → commission 2 500, part freelance 22 500 |

## 4. Scénario D — reversement au freelance (parcours nominal)

| # | Qui | Où / action | Résultat attendu |
|---|---|---|---|
| D1 | — | Nouvelle commande payée, livrée par F | — |
| D2 | C | Sur la livraison : **Valider la livraison** (confirmer) | Commande **clôturée, validée** |
| D3 | F | **Revenus** | Montant « **Disponible** » 31 500 (en **Montants de test**, pas dans « Revenus réels ») ; message « Coordonnées à compléter » |
| D4 | F | **Où recevoir mes versements ?** : moyen *Mobile Money*, titulaire, numéro (6 caractères minimum) → **Enregistrer mes coordonnées** | « Coordonnées en attente de vérification » ; **le numéro n'est jamais réaffiché** |
| D5 | A | **Opérations** → « Destinations de reversement à vérifier » → **Marquer comme vérifiée** (note 10 caractères) | La destination passe « vérifiée » ; un freelance **ne peut pas** vérifier la sienne |
| D6 | A | « Reversements : commandes validées sans reversement » → **Préparer le reversement (réserve les fonds)** | Opération « reversement » : base 35 000, commission 3 500, **montant 31 500** ; fonds réservés |
| D7 | A | Page de l'opération : décomposition | « taux figé dans l'accord » ; la mention « proposition provisoire non approuvée » tant que la commission n'est pas **approuvée** dans Paramètres |
| D8 | A | **Confirmer** le récapitulatif | « approuvée » |
| D9 | A | Page de l'opération | Texte : exécution du reversement par Genius Pay **indisponible** ; **pas** de bouton API |
| D10 | A | Effectuer (ou simuler) le transfert hors FreeCI, puis **Enregistrer comme effectué** : **référence externe unique**, justificatif, **retaper 31 500**, case | État « confirmée » ; la commission **acquise** n'augmente qu'ici |
| D11 | F | **Revenus** | « Versé » 31 500 (en test) ; « Disponible » repasse à 0 |
| D12 | A | Retenter un **second reversement** sur la même commande | **Refusé** (fonds déjà engagés) |

## 5. Scénario E — litige qui valide la livraison, puis reversement

| # | Qui | Où / action | Résultat attendu |
|---|---|---|---|
| E1 | — | Commande payée, livrée ; C demande une correction puis ouvre un **litige** (désaccord) | Commande « en litige » |
| E2 | A | **Décision** : *Résolution du désaccord : livraison jugée conforme → commande clôturée* ; suite : **Reversement à autoriser** | Commande **clôturée, validée** ; blocage interne **levé** |
| E3 | F, A | Reprendre D3 → D11 | Le reversement n'est possible qu'**après** cette décision motivée ; **le silence du client n'ouvre jamais un reversement** (à vérifier : une commande livrée non validée n'apparaît pas dans « commandes validées sans reversement ») |

## 6. Scénario F — garde-fous (à tenter volontairement)

| # | Action | Résultat attendu |
|---|---|---|
| F1 | A prépare un remboursement, puis clique **Refuser (libère la réservation)** avec un motif | État « refusée » ; **fonds libérés** (visibles de nouveau dans le plafond) ; une écriture correctrice, rien n'est effacé |
| F2 | A prépare une opération puis **Annuler l'opération** | Même résultat (annulée, fonds libérés) |
| F3 | Pendant un **litige ouvert**, tenter de préparer un reversement de cette commande | **Refusé** : litige ou blocage interne |
| F4 | Tenter de préparer un second remboursement alors qu'un est déjà ouvert pour le même paiement | **Refusé** (une seule opération ouverte par paiement) |
| F5 | Cliquer deux fois rapidement sur **Confirmer** ou **Exécuter** | Un seul effet (pas de double envoi) |
| F6 | Sans confirmation d'identité récente, tenter une action d'écriture | Redirection vers « Confirmer votre identité » |
| F7 | C ou F ouvre `/admin/finances` | Page **introuvable** (aucun accès) |
| F8 | A consulte la commande de C et F sans dossier ouvert | Aucun accès implicite aux échanges privés |
| F9 | Journal : **Journal d'audit** | Chaque action (préparer, confirmer, exécuter, refuser, enregistrer, refus compris) y figure avec auteur, cible, motif |

## 7. Scénario G — signalements automatiques (observation)

| # | Observation | Attendu |
|---|---|---|
| G1 | **Rapprochements** (Paiements à vérifier) après les scénarios | Aucun dossier ouvert, sauf cas anormal à décrire |
| G2 | **État et préparation** | Tâches « À jour » ; « Paiements et rapprochements » : 0 opération « à vérifier » (sinon, noter pourquoi) |
| G3 | En console (facultatif) : `sudo -u freeci -H php8.3 /var/www/freeci/artisan freeci:finance:status` | Totaux cohérents avec les écrans ; **aucune** opération en mode réel |

## 8. Points de la documentation encore non établis (à noter pendant la recette)

1. Après un remboursement par API en sandbox : quel **statut** et quelle **référence de remboursement** le prestataire renvoie-t-il, et au bout de combien de temps ?
2. Que répond le prestataire à un **second** remboursement du même paiement ?
3. Un remboursement **partiel** est-il possible dans le tableau de bord sandbox, et dans quel état le paiement reste-t-il ?
4. Existe-t-il, dans le tableau de bord ou la documentation du prestataire, une fonction de **paiement sortant** (reversement) ? Sinon, le reversement reste **manuel** : à confirmer avec le support Genius Pay avant l'ouverture.

## 9. Critères de décision

- **Conforme** : tous les résultats attendus obtenus ; aucune opération n'a bougé de l'argent sans votre confirmation ; aucun double effet.
- **À corriger** : tout écart d'état, de montant ou de libellé ; tout écran qui laisse croire qu'un remboursement ou un reversement est fait alors qu'il ne l'est pas ; toute perte de l'historique.
- **Bloquant pour l'ouverture au paiement réel** : un double remboursement ou reversement possible, un montant faux, un accès non autorisé, une opération « confirmée » sans preuve.

Envoyez-moi le tableau complété et les captures des écarts : je corrige dans l'ordre de gravité.

## 10. Compte rendu (à compléter)

| Étape | Réussi / Échec | Référence (commande, dossier, opération) | Remarque / capture |
|---|---|---|---|
| 0.1 → 0.6 | | | |
| A1 → A6 | | | |
| B1 → B11 | | | |
| C1 → C9 | | | |
| D1 → D12 | | | |
| E1 → E3 | | | |
| F1 → F9 | | | |
| G1 → G3 | | | |
| Points §8 (1 à 4) | | | |

Signé : ______________________  Date : ____________
