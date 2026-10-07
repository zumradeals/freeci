# Registre des limites et points ouverts

**Objet** : un seul endroit pour savoir ce qui n'est pas encore fait, pas encore décidé ou pas encore vérifié, lot par lot, afin de prioriser les améliorations de l'application et de l'expérience utilisateur.
**Dernière revue** : 7 octobre 2026, à partir des documents de chaque lot (`docs/06` à `docs/27`) et du code. **Non revérifié sur votre serveur** : les lignes « À vérifier sur le serveur » demandent un contrôle de votre côté.
**Règle d'entretien** : chaque nouveau lot met à jour ce registre (statut et date). Une ligne n'est jamais supprimée : elle passe à « Levé » avec le lot qui l'a résolue.

## Lecture

| Priorité | Sens |
|---|---|
| **P1** | Bloquant pour ouvrir le paiement réel |
| **P2** | Important avant l'ouverture au public |
| **P3** | Amélioration d'expérience ou de confort |

| Statut | Sens |
|---|---|
| **Ouvert** | À traiter (développement ou vérification) |
| **À valider** | Décision qui vous revient (je ne la tranche pas) |
| **À vérifier serveur** | Se contrôle sur le VPS, pas depuis le dépôt |
| **Décidé** | Tranché par vous, tracé dans le cadrage |
| **Levé (lot N)** | Résolu |

---

## 1. Paiement et finances (lots 3, 10, 10.1, 11 — recette `docs/27`)

| ID | Limite | Prio | Statut | Qui | Prochaine action |
|---|---|---|---|---|---|
| F1 | Aucun échange réel avec Genius Pay prouvé par les tests (réponses simulées) | P1 | Ouvert | Vous + moi | Recette `docs/27` |
| F2 | Comportement réel du remboursement en bac à sable : statut, référence, délai, second remboursement, partiel | P1 | Ouvert | Vous | Recette B7 à B9, C |
| F3 | Reversement : aucune API documentée, exécution **manuelle** | P1 | Ouvert | Vous | Interroger le support Genius Pay |
| F4 | Mode live jamais exercé | P1 | Ouvert | Vous | Après la recette, test à très faible montant (décision séparée) |
| F5 | Commission de 10 % non approuvée | P1 | À valider | Vous | Page Paramètres → Commission |
| F6 | Répartition des frais du prestataire non décidée (information seulement) | P1 | À valider | Vous | Décision commerciale |
| F7 | Barème d'arbitrage : aucune règle de répartition calculée, saisie en texte par le personnel | P2 | À valider | Vous | Définir une grille si souhaité |
| F8 | Devise non fournie par l'API : vérifiée si présente, sinon XOF envoyé | P2 | Ouvert | Moi | Constat pendant la recette |
| F9 | Anciennes commandes (« legacy ») sans taux figé : non reversables, non reclassées | P3 | Ouvert | Moi | À traiter seulement si elles existent sur le serveur |
| F10 | Expiration d'un lien de paiement : pas de statut documenté, la tentative est signalée, jamais annulée seule | P3 | Décidé | — | Observer en recette |
| F11 | Double approbation au-delà d'un seuil | — | Décidé (D39) | — | Abandonnée : un seul administrateur |
| F12 | Aucun remboursement ni reversement avant le lot 11 | — | Levé (lot 11) | — | — |

## 2. Assistance et litiges (lot 9)

| ID | Limite | Prio | Statut | Qui | Prochaine action |
|---|---|---|---|---|---|
| L1 | Échéance et délai d'examen non neutralisés pendant un litige (après poursuite, un délai dépassé peut enregistrer un besoin de suivi, jamais une validation) | P2 | À valider | Vous | Dire si le délai doit être suspendu |
| L2 | Annulation depuis « en attente de brief » et exclusion du litige à cet état : lecture des contrats à confirmer | P2 | À valider | Vous | Confirmer la règle |
| L3 | Aucun délai de traitement promis ni mesuré | P2 | À valider | Vous | Fixer un engagement (ou non) |
| L4 | Résolution « correction accordée » non proposée | P3 | À valider | Vous | Arbitrer |
| L5 | Contact des parties depuis un besoin de suivi ; partage volontaire d'extraits de conversation : non prévus | P3 | À valider | Vous | Encadrer avec le support |
| L6 | Pas de statistiques, d'export ni de tableau de bord propre pour le personnel « support » | P3 | Ouvert | Moi | Selon le volume réel |
| L7 | Aucun avis, aucune exécution financière dans le lot 9 | — | Levé (lots 11, 12) | — | — |

## 3. Avis, favoris, découverte (lot 12)

| ID | Limite | Prio | Statut | Qui | Prochaine action |
|---|---|---|---|---|---|
| R1 | Avis client → freelance uniquement | — | Décidé (D42) | — | Option future : avis privé du freelance sur le client |
| R2 | Délai de publication des avis (14 jours) provisoire | P2 | À valider | Vous | Paramètres → Délais |
| R3 | Aucun avis public avant l'existence de commandes réelles | P3 | Ouvert | — | Se résout avec le live |
| R4 | Recherche sur PostgreSQL, sans moteur externe | — | Décidé | — | À réexaminer si le volume l'exige |

## 4. Compte et données personnelles (lot 13)

| ID | Limite | Prio | Statut | Qui | Prochaine action |
|---|---|---|---|---|---|
| C1 | Durées de conservation (commandes, messages, écritures, dossiers, journaux, destinations) non décidées ; rien n'est purgé automatiquement | P1 | À valider | Vous + conseil juridique | Fixer et publier dans la confidentialité |
| C2 | Délai de réflexion avant fermeture (14 jours) provisoire | P2 | À valider | Vous | Paramètres → Délais |
| C3 | Fermeture refusée si le compte est suspendu ; messages d'un compte fermé conservés | P2 | À valider | Vous | Confirmer ces choix de prudence |
| C4 | Pas de double authentification pour les comptes ordinaires | P2 | À valider | Vous | Décider (obligatoire, facultative ou non) |
| C5 | Export sans pièces jointes | P3 | Ouvert | Moi | Selon les demandes reçues |
| C6 | Fermeture irréversible | — | Décidé | — | — |
| C7 | L'ancienne adresse n'est prévenue que si le courrier est configuré | P2 | À vérifier serveur | Vous | Se règle avec E1 |

## 5. Textes légaux et exploitant (lots 13, 16)

| ID | Limite | Prio | Statut | Qui | Prochaine action |
|---|---|---|---|---|---|
| J1 | Identité de l'exploitant, adresse, immatriculation, hébergeur, contact non renseignés | P1 | Ouvert | Vous | Paramètres → Exploitant |
| J2 | Conditions, confidentialité, mentions légales : projets de départ, aucun texte adopté | P1 | Ouvert | Vous + conseil juridique | Pages légales → relire, éditer, publier |
| J3 | Modalités de paiement réel, frais, garanties non annoncés | P1 | À valider | Vous | À rédiger dans les conditions |
| J4 | Pages d'information éditables en administration | — | Levé (lot 16) | — | — |

## 6. Exploitation et sécurité du serveur (lots 8, 13, 16, 17)

| ID | Limite | Prio | Statut | Qui | Prochaine action |
|---|---|---|---|---|---|
| E1 | Courrier : Gmail limité (~500 envois/jour), risque de spam ; un domaine d'envoi avec SPF/DKIM sera nécessaire | P2 | Ouvert | Vous | Choisir un domaine et un service d'envoi |
| E2 | Antivirus ClamAV : signalé installé par vous | P2 | À vérifier serveur | Vous | État et préparation → ligne « Contrôle des fichiers » |
| E3 | Copie de sauvegarde hors du VPS non activée | P2 | Ouvert | Vous | `deploy/local.env`, `BACKUP_OFFSITE_*` |
| E4 | Test de restauration jamais exercé sur le serveur (exercé en local seulement) | P2 | À vérifier serveur | Vous | `deploy/restore-test.sh` |
| E5 | Copie de `APP_KEY` hors serveur | P1 | À vérifier serveur | Vous | Gestionnaire de mots de passe |
| E6 | Cron `schedule:run` et worker de file : à confirmer | P2 | À vérifier serveur | Vous | État et préparation → tâches planifiées |
| E7 | Rotation des journaux (logrotate) non installée | P3 | À vérifier serveur | Vous | `deploy/logrotate-freeci.example` |
| E8 | 504 intermittent non diagnostiqué | P2 | Ouvert | Vous + moi | Journal des requêtes lentes de PHP-FPM |
| E9 | Données de démonstration encore présentes sur le serveur | P2 | À vérifier serveur | Vous | Carte « Données de démonstration » |
| E10 | Courriels de vérification d'adresse envoyés de façon synchrone (pas de file) | P3 | Ouvert | Moi | Selon le volume |
| E11 | Les processus de file déjà lancés relisent les paramètres au redémarrage | P3 | Décidé | — | `update.sh` les relance |
| E12 | Audit de sécurité externe (test d'intrusion) jamais réalisé | P2 | Ouvert | Vous | Avant l'ouverture au paiement réel |
| E13 | Valeurs de double authentification (8 h, 10 min) provisoires | — | Levé (lot 17) | — | Réglables en Paramètres |

## 7. Produit et expérience utilisateur (lots 2 à 7, 12)

| ID | Limite | Prio | Statut | Qui | Prochaine action |
|---|---|---|---|---|---|
| P1 | Messagerie sans temps réel (actualisation toutes les 30 s) | P3 | Ouvert | Moi | Évaluer selon l'usage |
| P2 | Pas de SMS ni de notifications push | P3 | Ouvert | Moi | Selon l'usage ; le courrier passe d'abord |
| P3 | Pas de portfolio pour le freelance | P3 | Ouvert | Moi | Forte valeur pour la confiance des clients |
| P4 | Pas de comparaison entre versions (service, livraison, conditions d'une demande) | P3 | Ouvert | Moi | — |
| P5 | Compétences en liste libre, sans référentiel | P3 | Ouvert | Moi | Améliorerait la recherche |
| P6 | Pas de suppression de service (archivage seulement) | P3 | Décidé | — | Conforme à la conservation de l'historique |
| P7 | Pas de pièces jointes sur une mission (vérifié dans le code : aucun lien entre missions et fichiers) | P3 | Ouvert | Moi | À ajouter si les clients en ont besoin |
| P8 | Budget de mission : valeur indicative unique, pas de fourchette | P3 | Ouvert | Moi | — |
| P9 | Dépôt de fichiers : un fichier à la fois (vérifié dans le code : pas de sélection multiple) | P3 | Ouvert | Moi | Envisager un dépôt multiple |
| P10 | Pas de code QR pour la double authentification (saisie manuelle de la clé) | P3 | Ouvert | Moi | Simplifierait l'activation |
| P11 | Services publiés sans portefeuille de preuves (avis, réalisations) tant qu'il n'y a pas de commande réelle | P3 | Ouvert | — | Se construit avec l'usage |
| P12 | Pas de messagerie, d'avis, de litiges, de reports, de modération web, de missions (lots 2 à 6) | — | Levé (lots 4 à 12) | — | — |

## 8. Interface et qualité (lots 14, 15, 18)

| ID | Limite | Prio | Statut | Qui | Prochaine action |
|---|---|---|---|---|---|
| Q1 | Structure des pages alignée sur la page « Mes revenus » (détail de commande, édition de service et de mission inclus) | P3 | Levé (lot 24) | — | — |
| Q2 | Accessibilité : contrôle automatique (0 anomalie) ; clavier, lecteur d'écran, agrandissement du texte et vrais téléphones non essayés | P2 | Ouvert | Vous + moi | Essai manuel guidé |
| Q3 | Parcours navigateur automatisé en place (`tests/e2e/run.sh`, voir `docs/29`) ; manquent l'achat complet dans le navigateur, d'autres navigateurs et une exécution automatique à chaque modification | P3 | Partiel | Moi | Étendre si besoin |
| Q4 | Pas de test de charge ni de mesure de performance | P2 | Ouvert | Moi | Après diagnostic du 504 |
| Q5 | Textes d'aide et de fonctionnement : projets de départ, à relire par vous | P3 | Ouvert | Vous | Pages légales |
| Q6 | Montée de version des dépendances : relecture des notes avant toute mise à jour | — | Décidé | — | — |

---

## 9. Pistes d'amélioration de l'expérience (à prioriser avec vous)

Ces idées viennent des lignes ci-dessus. **Ce sont des propositions, pas des décisions.**

1. **Confiance des clients** : portfolio du freelance (P3), puis avis réels (R3). C'est ce qui rassure le plus avant une première commande.
2. **Premiers pas** : activation de la double authentification plus simple (P10), parcours d'accueil du freelance jusqu'à son premier service publié.
3. **Échanges** : messagerie plus réactive (P1) et notifications utiles par courriel quand le domaine d'envoi sera prêt (E1).
4. **Recherche** : compétences normalisées (P5) pour de meilleurs filtres.
5. **Réassurance financière** : textes clairs sur les garanties et les délais de versement (J3, F6) ; c'est ce que les utilisateurs liront en premier.
6. **Mesure** : avant de construire davantage, décider quels retours recueillir (par exemple une question de satisfaction après une commande clôturée, ou un lien « donner mon avis sur FreeCI » dans l'assistance). Aucune mesure d'usage n'est en place aujourd'hui.

## 10. Ordre de traitement conseillé

1. **Vos décisions** (statut « À valider », P1 puis P2) : commission, frais, conservation, textes légaux, double authentification des comptes ordinaires.
2. **Recette financière** (F1 à F4).
3. **Vérifications du serveur** (E2 à E9) : un contrôle de quelques minutes chacun.
4. **Mon travail** (statut « Ouvert », Qui = Moi) : Q2, Q3, Q4, puis Q1 et les améliorations P3 selon vos priorités.

## Revue de consolidation — 7 octobre 2026

- Totaux de revenus et de paiements limités à 100 commandes : corrigés dans le code (historique complet, détail paginé), tests de régression ajoutés mais pas exécutés sur PostgreSQL dans cet environnement. Voir `docs/31-consolidation-moteur.md`.
- Disponibilité ClamAV : sonde d’analyse réelle et timeouts traités comme indisponibilité ; contrôle VPS restant nécessaire.
- E8 (504) reste **ouvert**. Aucune cause certaine identifiée.
- Performance : la lecture par lots borne la mémoire ; les requêtes par commande restent à mesurer et à regrouper avant une forte montée en charge.
- Les suites correction/litige/remboursement restent à réexécuter dans un environnement PostgreSQL isolé ; le point 2 de la feuille de route n’est pas déclaré terminé.
