# Lot 4 — livraison, corrections, report d'échéance, validation

Statut : **projet de travail** livré ; les choix marqués « retenu » sont des solutions cohérentes avec les contrats existants, à valider par le dirigeant.
Hors périmètre : paiement réel, remboursement, **reversement**, litige, avis, missions, notification par courrier.

## 1. Cycle
`en cours → livrée → (correction demandée → livrée)* → validée → clôturée`. Toutes les transitions sont serveur (rôle, état, version attendue, clé d'opération, verrou de ligne).

## 2. Livraison versionnée
- Le freelance prépare un **brouillon privé** (message + fichiers) : rien n'apparaît au client ni dans l'historique partagé.
- **Soumission explicite** (page de confirmation) : la version (v1, v2…) est attribuée, la livraison devient **immuable** (déclencheur PostgreSQL : ni modification ni suppression), ses fichiers ne peuvent plus être retirés. Une nouvelle version **ne remplace rien** : toutes restent consultables (versions précédentes repliées, étiquette « Remplacée par la vN »).
- **Fichiers** : même chaîne que le brief (disque privé, clé opaque, type réel, quarantaine, contrôle, lien signé de 5 min). Soumission **impossible** tant qu'un fichier est en cours de contrôle, qu'un fichier refusé n'est pas retiré, ou qu'un fichier obligatoire manque ; la page liste chaque blocage en clair.
- **Fichiers obligatoires** (retenu) : propriété figée dans l'accord (`order_agreements.delivery_requires_files`, copiée du service à la demande ; *faux* pour tous les services et accords existants). Si elle est vraie et que le contrôle de sécurité n'est pas installé, la livraison est **bloquée** et le dit (installer ClamAV : docs/10). Sinon une livraison par message seul est possible.
- Le client ne télécharge qu'après soumission, que des fichiers contrôlés ; le freelance accède à son brouillon ; tiers et administrateur : 404.

## 3. Examen et corrections
- Le client voit d'abord la livraison (carte d'action → onglet « Livraisons »), puis choisit : **demander une correction** (motif de 15 à 2000 caractères) ou **valider** (page de confirmation + case à cocher).
- Compteur « incluses / utilisées / restantes » : les incluses viennent de l'**accord figé** (une modification du service n'a aucun effet).
- **Aucune double consommation** : même clé = rejouée ; autre clé sur la même version = 409 ; la base impose en plus une seule demande par version et un numéro unique par commande.
- La correction vise la **dernière** version ; la nouvelle livraison enregistre à quelle demande elle répond (affiché dans le dossier et l'historique).
- Corrections épuisées (retenu) : le bouton est désactivé et le dit ; seule la validation reste possible. Un changement de périmètre passe par une nouvelle commande.

## 4. Report d'échéance
- Le freelance **propose** une date (même heure que l'échéance actuelle ; postérieure à l'actuelle, au plus 30 jours après : `FREECI_EXTENSION_MAX_DAYS`) avec un motif ; une proposition en attente à la fois ; elle peut être retirée, et elle est retirée automatiquement si une livraison est soumise.
- Le client **accepte ou refuse** (message facultatif). **Seule l'acceptation** modifie `orders.due_at` ; la base l'impose (déclencheur : un report accepté et enregistré, ancienne et nouvelle valeurs concordantes). L'ancienne échéance (`previous_due_at`), la décision, l'auteur et la date sont conservés ; une décision rendue ne se modifie plus.
- Aucune remise à zéro : ni livraison, ni correction ne touchent à l'échéance. Le retard n'est pas un état : badge « Échéance dépassée » calculé.
- Sans réponse du client, l'échéance reste inchangée (pas d'acceptation tacite).

## 5. Validation et clôture
Validation explicite, dernière version uniquement → événements « validée » puis « clôturée » (clôture **commerciale**, motif `validated`, version validée enregistrée). **Aucune écriture de registre, aucun paiement, aucun reversement** : le dossier l'affiche (onglet Finances : « Non déclenché »).

## 6. Silence du client
Délai d'examen indicatif : 7 jours (`FREECI_REVIEW_DAYS`). À son expiration la commande **reste « livrée » et ouverte** ; rien n'est validé, clôturé ni libéré. Un **besoin de suivi** est enregistré une seule fois (`order_follow_ups`) avec une ligne d'historique qui précise qu'**aucun support n'a été contacté automatiquement** (aucun mécanisme n'existe). Constaté à l'ouverture du dossier, des tableaux de bord, et par `freeci:orders:expire` (planifié). Le client peut encore valider ou demander une correction.

## 7. Données et existant
Migration `2026_10_10_000100_create_deliveries_and_corrections` : tables `deliveries`, `correction_requests`, `extension_requests`, `order_follow_ups` ; colonnes `file_assets.delivery_id`, `orders.validated_delivery_id/validated_at`, `*.delivery_requires_files` (faux pour l'existant) ; motif de clôture `validated` ; déclencheur d'échéance remplacé (un report accepté seulement). Aucune donnée existante n'est modifiée. Les commandes déjà « en cours » (recette du lot 3) peuvent être livrées sans autre opération.

## 8. Variables et prérequis
`FREECI_DELIVERY_MAX_MB` (10), `FREECI_REVIEW_DAYS` (7), `FREECI_EXTENSION_MAX_DAYS` (30) : facultatives. Pour dépasser 10 Mo par fichier : relever aussi `client_max_body_size` (nginx, 12m aujourd'hui) et `upload_max_filesize` / `post_max_size` (PHP-FPM). ClamAV n'est requis que pour livrer des **fichiers** (docs/10).

## 9. Limites
Pas de messagerie, litige, avis, reversement, notification ; un fichier par envoi ; pas de comparaison entre versions ; l'« accès d'abord aux éléments » est assuré par l'ordre de l'écran et l'exigence d'une livraison examinable, non par un compteur de consultation.
