# 40 — Ouverture officielle et passage au paiement réel

> **DEC-O01 (porteur, 2026-10-09)** : *« Je déclare l'ouverture et la levée du bac à sable. »* Motif : la solidité réelle ne se connaîtra qu'à l'usage avec les premiers inscrits.
> Ce document ne remplace pas cette décision : il fixe la **procédure**, l'**ordre** et le **retour arrière**. Il ne déclare aucune validation juridique ni fiscale, qui n'a pas été donnée.

## 1. Ce qui est établi / ce qui ne l'est pas

| Établi (recette du 2026-10-09, bac à sable) | Non établi, assumé par la décision d'ouverture |
|---|---|
| Parcours commande → paiement → litige → remboursement total → reversement : aucun échec, aucun double effet | Comportement de **vrais** paiements Mobile Money / cartes (forme réelle des webhooks, délais, reprises) |
| Remboursement total confirmé sur preuve du prestataire (lot 72) : à valider sur le vrai bac à sable après déploiement | Remboursement **partiel** chez le prestataire ; scénarios échec / attente / délai dépassé |
| Garde-fous (plafonds, doublons, litige ouvert, silence du client) | **Reversement au freelance : manuel** (aucune API de paiement sortant documentée ; question §8.4 au support Genius Pay) |
| | Textes : politique de confidentialité (photo, portfolio), conditions, mentions, **conseil fiscal (Q4)** |

## 2. Prérequis techniques (dans cet ordre)

1. Déployer le dernier commit de `main` (migrations comprises) ; `freeci:finance:reconcile` : `OP-2610-00001` confirmée, plus aucune opération « à vérifier ».
2. Compte marchand **live** Genius Pay : clés `pk_live_…` / `sk_live_…`, identifiant marchand, secret de webhook live (`whsec_…`) dans le `.env` serveur (jamais dans Git ni dans une conversation). URL du webhook : `https://freeci.dgafrique.com/webhooks/geniuspay`.
3. `freeci:genius:status --env=live` : configuration « conforme » ; `freeci:genius:status --env=live --ping` : clés acceptées.
4. **Purger les données de démonstration** (commandes de recette marquées test, comptes `@demo.freeci.invalid`, services d'exemple) : `freeci:demo:purge` — après sauvegarde. Les commandes de test restent séparées du réel, mais il ne faut pas ouvrir au public avec elles.
5. Sauvegarde : `deploy/backup.sh avant-ouverture-reelle`.
6. **Référencement (F-16)** : `APP_URL` = adresse publique en `https://` (ex. `https://freeci.net`) ; `FREECI_NOINDEX` reste activé tant que vous ne voulez pas être indexé ; à l'ouverture publique, Administration → Paramètres : décocher « Masquer le site aux moteurs de recherche » ; contrôler `/robots.txt` (ne contient plus `Disallow: /`) et `/sitemap.xml` ; soumettre `https://freeci.net/sitemap.xml` dans Google Search Console ; tester un lien partagé avec l'outil de débogage de partage de Facebook. La ligne « Référencement » de « État et préparation » résume l'état.

## 3. Bascule (par le porteur, avec confirmation de l'administration)

Administration → Paramètres → **Paiement Genius Pay** : mode **Live**, « Ouvrir les nouveaux paiements », « Autoriser le paiement réel » ; la phrase de confirmation `PAIEMENT REEL` est demandée, avec motif et identité récemment confirmée (tracé au journal).

Effet : seules les **nouvelles** commandes sont « réelles ». Les commandes de test existantes restent en test (environnement conservé par commande).

## 4. Première semaine : ouverture surveillée

- **Premiers paiements réels suivis un à un** : après chacun, vérifier dans l'administration → Finances que le paiement est « confirmé », que la commande passe « en cours », et dans le tableau de bord Genius Pay que le montant correspond.
- Jusqu'au premier remboursement et au premier reversement réussis, les traiter **vous-même**, un par un, sans automatisme élargi.
- Chaque jour : « État et préparation » (tâches planifiées, file, opérations « à vérifier », événements à revérifier), `freeci:finance:status`, journal d'audit.
- Les **reversements** restent manuels : prévenir clairement les freelances du délai de versement (texte à valider par vous avant diffusion).
- Toute anomalie financière (montant faux, double effet, accès non autorisé) = **arrêt immédiat des nouveaux paiements** (§5) puis analyse.

## 5. Retour arrière (réversible, sans perte de données)

1. Administration → Paramètres → Paiement : décocher « Ouvrir les nouveaux paiements » (ou repasser en mode Sandbox). Les paiements et opérations déjà créés restent traités ; aucune donnée n'est supprimée.
2. Ne jamais supprimer d'écriture : une erreur se corrige par écriture correctrice (déjà le fonctionnement du registre).
3. Code : `deploy/rollback.sh <commit précédent>` ; restauration de base : `deploy/restore-test.sh` d'abord sur une copie.

## 6. Reste à votre charge (non automatisable)

Écrans de la recette (B10, D3/D11, F5–F8), signature du §10 de `docs/27`, choix du fournisseur d'e-mails si ce n'est pas déjà fait (Q2), validation des textes juridiques, question §8.4 au support Genius Pay.
