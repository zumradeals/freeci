# Lot 9 — assistance, signalements et litiges

Statut : livré, à recetter. Les règles ci-dessous sont celles des contrats du dépôt (`docs/02` §4.4, §6 ; `docs/04` §8.4, §10.3). Aucune règle commerciale d'arbitrage n'est inventée : les décisions restent **humaines et motivées** ; les points non tranchés sont listés en fin de document.

## 1. Assistance et signalements (espace connecté)
- **Contacter le support** depuis « Assistance » ou depuis une commande (référence préremplie ; une commande d'autrui ou inexistante répond pareil). Idempotent (clé d'opération), limité à 10 dossiers / 24 h.
- **Signaler** un profil, un service, une mission ou un message (liens « Signaler… » sur les pages concernées), avec motif. La personne signalée n'est **jamais informée**. Un message signalé n'est transmis que sous forme de **copie de ce seul message** ; le reste de la conversation reste privé. Impossible de signaler son propre contenu ou un message d'une conversation dont on n'est pas participant ; un seul signalement vivant par contenu et par personne.
- **Suivi** : liste « Assistance », statut honnête (« Reçu : pas encore pris en charge », « Pris en charge… », « L'équipe attend votre réponse », « Décision rendue »), échanges, pièces jointes. Aucun délai de réponse n'est promis.
- **Trois canaux** : `requester` (demandeur ↔ équipe), `parties` (les deux parties d'un litige ↔ équipe), `internal` (notes de l'équipe, **jamais** visibles ni notifiées). Un message n'est jamais une décision, une validation ni une modification de l'accord.
- **Pièces** : même chaîne privée que le brief (type réel, quarantaine, contrôle, lien signé lié à l'utilisateur) ; téléchargeables seulement une fois contrôlées, selon le canal du message.
- Un compte **suspendu** ou un contact **bloqué** garde l'accès à ses dossiers actifs, peut y répondre et contacter le support.

## 2. Litiges de commande
- Ouverts par une **partie** depuis la commande (« Litige ou annulation »), avec motif (20 caractères au moins) et confirmation des conséquences.
  - **Litige** : commande payée dont le **reversement n'est pas exécuté** — états `in_progress`, `delivered`, `revision_requested`, `validated`, `closed`.
  - **Demande d'annulation après paiement** : états `awaiting_brief`, `in_progress`, `delivered`, `revision_requested` (examen contradictoire).
- Effets à l'ouverture (même verrou de commande) : la commande passe à **`disputed`** ; livrer, corriger, valider et proposer/accepter un report sont **suspendus** (la machine d'états les refuse) ; un **blocage interne des reversements** est enregistré (`payout_holds`) ; l'autre partie est notifiée ; les messages de la commande restent possibles. **Aucune validation n'a lieu par silence** (aucun besoin de suivi ni validation ne naît pendant un litige).
- Les deux parties voient le motif, les échanges partagés, les étapes et la décision ; elles ajoutent leurs réponses et preuves (pièces contrôlées).
- **Réclamation après versement** (`claim`) : seulement si le reversement a **déjà été exécuté** (contrat `PayoutExecution`, aucun reversement n'existe aujourd'hui : toujours faux). Aucun blocage, aucun changement d'état, **aucune promesse de gel ni de récupération** (texte affiché). Un litige est alors refusé.
- Conservé pour l'examen : accord figé, livraisons soumises, corrections, reports, historique, brief — tout est en ajout seul.

## 3. Traitement par le personnel
- **Habilitations** : administrateur (existant) ou **support** (nouveau, console : `freeci:staff:grant|revoke`). Le support n'a ni modération, ni gestion de comptes, ni audit ; son accueil est l'assistance. Accès : adresse vérifiée + double authentification + habilitation en vigueur, revérifiées à chaque requête et à chaque action.
- **Affectation** : « M'affecter » ou affectation par un administrateur à un membre éligible. **Le contenu d'un dossier (échanges, notes, pièces, extrait de commande) n'est accessible qu'à la personne affectée, après « Ouvrir le dossier » avec motif** (confirmation récente d'identité). L'accès **prend fin** à la libération, à la décision, à la clôture, à une réaffectation et à la révocation de l'habilitation. Sans cela, le personnel ne voit que des métadonnées.
- **Extrait de commande** limité à ce qui sert à trancher ; **la conversation de la commande n'est jamais ouverte**. Pièces de la commande consultables seulement pour un dossier de litige, d'annulation, de réclamation ou de suivi, affecté et ouvert ; livraisons soumises uniquement.
- **Conflits d'intérêts** : impossible de traiter (affecter, ouvrir, répondre, décider) un dossier dont on est demandeur, autre partie, partie de la commande ou auteur du contenu signalé ; contrainte SQL en plus pour l'auto-affectation.
- **Journalisation** (`admin_actions`, ajout seul) : affectation, libération, ouverture (motif), consultation du contenu, téléchargement de pièce, changement d'état, clôture, décision — refus compris. Historique du dossier en ajout seul.

## 4. Décisions (humaines, motivées, uniques)
Séparation stricte en **trois** éléments :
1. **La décision** (`support_decisions`, une seule par dossier : index unique + verrou + version attendue + clé d'opération) : poursuite de la prestation · résolution du désaccord (livraison jugée conforme) · annulation motivée · (réclamation) examinée. Motif de 20 à 2000 caractères, communiqué aux parties.
2. **Son effet sur la commande**, appliqué dans la même transaction et tracé dans l'historique de la commande : retour à l'état d'avant litige · `validated` puis `closed` (clôture commerciale, aucun reversement) · `cancelled` (motif « annulée après paiement »).
3. **La suite financière**, choisie **explicitement** : aucune · reversement à autoriser · remboursement à traiter · répartition à fixer (précision humaine obligatoire). **Elle n'est jamais exécutée** : affichée « **À traiter financièrement** » aux parties et dans l'onglet dédié du personnel. Une décision de remboursement ne vaut jamais remboursement effectué.
- **Blocage interne des reversements** : levé si la décision n'a aucune suite financière ou autorise le reversement ; **conservé** si un remboursement ou une répartition reste à traiter. Il est interne à FreeCI (aucun blocage chez un prestataire n'est affirmé). **Contrat pour le futur module financier** : `Support\PayoutHolds::isHeld($orderId)` doit être respecté par `Finance\InitiatePayout`, sous le même verrou de commande.
- **Besoins de suivi** (silence, désaccord signalé) : visibles du personnel avec leur **origine** ; l'ouverture d'un dossier à partir d'eux est une action **explicite, unique et journalisée**, qui ne modifie pas la commande et ne crée aucun litige (seules les parties ouvrent un litige). Un dossier de suivi est interne : le personnel ne contacte pas les parties depuis ce dossier à ce stade.

## 5. Notifications
Mécanisme du lot 7 : types essentiels `support_update` et `dispute_update` (réponse sur le dossier, litige ouvert, décision), titres génériques, **jamais le contenu** d'un message, d'une note ou d'une pièce. Les notes internes ne notifient personne.

## 6. Exploitation
- Migration `2026_10_15_000100_create_support_and_disputes` (tables `support_cases`, `support_messages`, `support_events`, `support_decisions`, `payout_holds` ; colonne nullable `file_assets.support_message_id` ; CHECK élargis : capacité `support`, motif de clôture `cancelled_after_payment`). Données existantes inchangées.
- Aucune nouvelle variable. Console : `freeci:staff:grant <courriel> [--expires=] [--reason=]` (compte existant), `freeci:staff:revoke <courriel>` (met fin aux affectations), `freeci:admin:list` (liste les deux capacités).
- Le contrôle des pièces suit la configuration existante (`FREECI_FILE_SCANNER`) : sans analyseur, le dépôt de pièces est désactivé.

## 7. Points à valider (décisions humaines — non tranchées par le code)
1. **Barème d'arbitrage** : aucune règle de répartition n'est calculée ; une répartition est saisie en texte par le personnel.
2. **Double approbation au-delà d'un seuil** (Q05, seuil non fixé) : non implémentée ; à introduire avec les opérations financières.
3. **Échéance et délai d'examen pendant un litige** : non neutralisés automatiquement ; après une poursuite, un délai d'examen dépassé peut enregistrer un besoin de suivi (jamais une validation).
4. **Résolution « correction accordée »** : non proposée (le compteur de corrections est contractuel) ; à arbitrer.
5. **Délais de traitement du support** : aucun délai promis ni mesuré.
6. **Contact des parties depuis un dossier de suivi** et **partage volontaire d'extraits de la conversation de commande** : non prévus ; à encadrer avec le support.
7. **Annulation après paiement depuis `awaiting_brief`** et l'exclusion de `awaiting_brief` pour un litige : lecture des contrats `docs/02` §4.4 ; à confirmer.

## 8. Limites
Aucune exécution financière, aucun avis, aucun Genius Pay. Pas de statistiques de traitement ni d'export. Le personnel « support » n'a pas de tableau de bord propre au-delà de la file d'assistance.
