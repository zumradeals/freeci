# Lot 10 — Genius Pay (bac à sable uniquement)

Statut : livré, à recetter. Décision de fond : **D37** (`docs/01`) — Genius Pay est le prestataire retenu ; **aucune qualification pour la production** n'est prononcée ici. Le **mode réel n'est ni intégré ni activable** : son activation exigera votre autorisation explicite.

## 1. Ce que dit — et ne dit pas — la documentation fournie (`API_Documentation.md`)
| Sujet | Documentation | Conséquence dans le code |
|---|---|---|
| URL de base | `http://geniuspay.ci/api/v1/merchant` (corps du document) mais `https://…` dans l'exemple d'idempotence | **HTTPS exigé** : toute URL `http://` est refusée (aucune clé ne circule en clair), redirections HTTP non suivies. **L'URL HTTPS n'a pas pu être vérifiée depuis l'environnement de développement** (sortie réseau bloquée) : à contrôler sur le VPS **avant** d'y mettre des clés (§6). |
| Sandbox / production | Même URL ; l'environnement est porté par le préfixe des clés (`pk_sandbox_/sk_sandbox_`, `pk_live_/sk_live_`) | Seules des clés `*_sandbox_*` sont acceptées ; des clés `live` sont **refusées** (aucun appel émis). |
| Création de paiement | `POST /payments` ; `amount` XOF (min. 200), `external_reference` = référence métier **et clé d'idempotence** ; en-tête `Idempotency-Key` ; réponse `reference`, `checkout_url`, `environment`, `expires_at` (24 h) | Référence de tentative **stable** (`GP-…`) = `external_reference` = `Idempotency-Key` ; rejouée après un délai dépassé. |
| Lecture | `GET /payments/{reference}` : `status` (`pending/processing/completed/failed/cancelled/refunded`), `amount`… **sans** devise, `external_reference`, métadonnées ni environnement | La devise envoyée (XOF) est celle de la tentative ; **si** le prestataire en renvoie une, elle doit concorder. Le rattachement s'appuie sur la **référence attribuée** et sur l'`external_reference` renvoyé **à la création**. |
| Webhook | Signature `HMAC-SHA256(timestamp + "." + corps brut)`, en-têtes `X-Webhook-Signature/Timestamp/Event/Environment` ; secret `whsec_…` par webhook, affiché **une fois** ; événements `payment.initiated/success/failed/cancelled/refunded` ; payload `data.transaction`, `data.merchant.id`, `data.environment` | Signature vérifiée sur le **corps brut** ; environnement corps/en-tête concordants ; compte marchand comparé à `GET /account`. |
| Reprises | Backoff 1 min → 5 min → 15 min → 1 h → 4 h → 24 h ; **non précisé** : le corps/l'horodatage sont-ils re-signés ? | Fenêtre de fraîcheur **25 h** (`GENIUSPAY_WEBHOOK_TOLERANCE_SECONDS`) ; horodatage futur refusé. Les rejeux dans la fenêtre sont **sans effet** (événement dédoublonné, effet idempotent, succès toujours revérifié par l'API). |
| Identifiant d'événement | **Aucun** dans le payload | Clé dérivée : événement + référence de transaction + horodatage du corps ; empreinte du corps conservée. |
| Création d'un webhook | Routes `/webhooks` (CRUD, `test`, `regenerate-secret`) listées ; **champs de la requête non documentés** ; `retry_count` (3 par défaut) mentionné | Configuration **depuis le tableau de bord** (rien n'est inventé). |
| Simulation de succès / échec / attente en sandbox | « Les transactions sont simulées » ; **aucune procédure** (numéros de test, endpoint, page) n'est décrite ; seul le remboursement sandbox est mentionné | **Non inventée.** Procédure d'observation au §6 : ouvrir le checkout sandbox et noter ce qu'il propose. |
| Séquestre, reversement | **Rien** (seulement paiement, remboursement, solde) | **Non supposés.** Aucun reversement ; aucun remboursement exécuté (l'endpoint n'est jamais appelé). |

## 2. Architecture
- **Trois environnements distincts**, conservés pour chaque tentative (`payments.provider` + `payments.environment`) : `simulator` (simulateur interne), `sandbox` (Genius Pay), `live` (non activable). Les tentatives existantes restent `simulator` (valeur par défaut de la colonne) : **aucune réinterprétation**. Le simulateur interne continue de fonctionner pour ses tentatives même si Genius Pay est actif (chaque tentative est vérifiée par SON prestataire).
- **Garde-fous en base** : `environment='live'` ⇔ non simulé ; `sandbox`/`simulator` ⇒ `is_simulated = true` ; `sandbox` interne ⇒ `simulator`. Un paiement de bac à sable n'est donc **jamais** de l'argent réel ; le registre le marque simulé.
- **Sélection** : `FREECI_PAYMENT_PROVIDER` = `simulator` (défaut) ou `geniuspay_sandbox`. Genius Pay (bac à sable) exige **aussi** `FREECI_PAYMENT_SANDBOX=true` et la porte existante : commande, parties et service/mission de démonstration, compte client autorisé (`freeci:sandbox:authorize`). Une commande réelle ne passe jamais.
- **Création** (`InitiatePayment`) : montant lu **uniquement** dans l'accord figé (XOF) — aucun champ de la requête (montant, devise, moyen) n'est pris en compte ; commande « en attente de paiement » ; une seule tentative ouverte par commande (index existant) ; référence de tentative enregistrée **avant** l'appel ; appel hors transaction ; redirection vers le **checkout hébergé** (hôte contrôlé : `geniuspay.ci` par défaut, `GENIUSPAY_CHECKOUT_HOSTS`).
- **Incertitude** : délai dépassé / 5xx / réponse illisible ⇒ la tentative reste « créée » (ouverte) : **aucune nouvelle tentative**. Le rapprochement rejoue la **même** demande (même référence, même clé). Un **refus définitif** (401/403/400/422…) ou une réponse incohérente (environnement ≠ sandbox, montant ≠ accord, référence déjà rattachée) clôt la tentative et signale le cas.
- **Retour navigateur** (`/commandes/{réf}/paiement/retour`) : informatif ; ses paramètres sont **ignorés** ; il déclenche seulement une revérification serveur (limitée).

## 3. Confirmation et rapprochement
1. `POST /webhooks/geniuspay` : signature (corps brut) + fraîcheur + environnement. Rejet ⇒ `401` sans trace durable. Valide ⇒ **enregistré durablement** (dédoublonné) puis traité **de façon asynchrone** (`ProcessPaymentEvent`) ; réponse 2xx immédiate.
2. Traitement : tentative retrouvée par la référence du prestataire (ou, si la réponse de création n'a pas été enregistrée, par notre référence de tentative portée en métadonnée, puis **rejeu idempotent** de la création pour lier la référence) ; **compte marchand** comparé à `GET /account` ; **un succès n'est jamais cru sur parole** : `GET /payments/{ref}` doit confirmer statut `completed`, **montant = accord**, devise (si fournie), référence, rattachement et environnement.
3. Information manquante, incohérente ou prestataire injoignable ⇒ **« à vérifier »** (`needs_review` ou dossier de rapprochement) : **aucun démarrage**. Les incohérences définitives (montant, environnement, compte marchand, référence) créent un dossier ; l'indisponibilité est reprise automatiquement.
4. `freeci:payments:reconcile` (toutes les 5 min) : reprend les événements non traités ou à revérifier, **interroge** les tentatives ouvertes (notification manquante), signale celles restées ouvertes plus d'une heure après l'expiration du lien. Rien n'est annulé, démarré ni confirmé sans revérification.
5. Démarrage : `ConfirmPayment` → `StartOrderIfReady` existants (paiement confirmé **et** brief complet, une seule fois, un seul lot de registre).
6. **Succès tardif** (après échec, commande annulée ou expirée) : enregistré, dossier de rapprochement **« à traiter »**, visible des administrateurs (`/admin/paiements`, compteur du tableau de bord) ; **aucun travail n'est relancé**. `payment.refunded` : enregistré et signalé, jamais exécuté ni déduit.
7. Un administrateur peut marquer un dossier « examiné » (qui, quand, pourquoi, journalisé) : **aucune opération financière** n'en découle.

## 4. Séparation du sandbox
Bandeau permanent « Bac à sable Genius Pay — aucun argent réel », libellés « Bac à sable » partout (page de paiement, historique de commande, administration), case de compréhension obligatoire, registre marqué simulé. Aucun montant de bac à sable n'est présenté comme encaissé réellement.

## 5. Secrets
Clés et secret du webhook : `.env` du serveur uniquement (hors Git), jamais envoyés au navigateur, jamais stockés ni journalisés (les payloads conservés excluent les données client ; les erreurs de transport ne reprennent pas les messages du prestataire). Les exemples HTTP et l'exemple JavaScript exposant le secret de la documentation **n'ont pas été reproduits**. `freeci:genius:status` n'affiche aucune clé.

## 6. Procédure sur le VPS (clés à saisir par vous, jamais dans une conversation)
1. **Vérifier l'URL HTTPS sans clé** : `curl -sS -i https://geniuspay.ci/api/v1/merchant/account | head -n 15` → attendu : réponse JSON `401 MISSING_API_KEY`. Et `curl -sS -o /dev/null -w "%{http_code} %{redirect_url}\n" http://geniuspay.ci/api/v1/merchant/account` pour voir si le HTTP redirige vers HTTPS. Si HTTPS ne répond pas, **ne mettez aucune clé** et contactez Genius Pay.
2. Tableau de bord Genius Pay (bac à sable) : relever la clé publique `pk_sandbox_…` et la clé secrète `sk_sandbox_…`.
3. Créer le **webhook** (tableau de bord) : URL `https://freeci.dgafrique.com/webhooks/geniuspay` ; événements `payment.success`, `payment.failed`, `payment.cancelled` (recommandés : `payment.initiated`, `payment.refunded`) ; relever le secret `whsec_…` (affiché une seule fois). Les champs exacts de création par API ne sont pas documentés.
4. `.env` (`sudo -u freeci -H nano /var/www/freeci/.env`) : `FREECI_PAYMENT_SANDBOX=true`, `FREECI_PAYMENT_PROVIDER=geniuspay_sandbox`, `GENIUSPAY_API_KEY=`, `GENIUSPAY_API_SECRET=`, `GENIUSPAY_WEBHOOK_SECRET=`. **Supprimer les doublons de clés** éventuels du fichier.
5. `php artisan config:cache`, `php artisan queue:restart` (un `queue:work` actif — ou `FREECI_QUEUE_VIA_SCHEDULER=true` — est nécessaire au traitement des événements).
6. `php artisan freeci:genius:status` (tout « OUI ») puis `php artisan freeci:genius:status --ping` (**premier échange réel** : `GET /account`).
7. Comptes de recette : `php artisan freeci:demo:recette` si besoin, puis `php artisan freeci:sandbox:authorize <courriel du client de démonstration>`.
8. Parcours : client de démonstration → demande d'un service de démonstration → freelance de démonstration accepte → page de paiement → « Continuer vers Genius Pay » → **noter ce que le checkout sandbox propose pour simuler succès / échec / attente** (non documenté) → revenir sur FreeCI. Contrôler : page de paiement (« vérification en cours » puis « confirmé »), historique de la commande, `freeci:genius:status` (événements reçus / à revérifier), `/admin/paiements`.
9. Bouton « tester » du webhook (tableau de bord) : l'événement de test doit être **accepté puis ignoré** (référence inconnue), sans effet sur une commande.
10. Rapprochement manuel : `php artisan freeci:payments:reconcile`.

## 7. Limites et points à valider
- **Aucun échange réel avec Genius Pay n'a eu lieu** : les 17 tests du lot utilisent des réponses simulées localement (`Http::fake`) ; ils prouvent la logique de FreeCI, pas le comportement du prestataire.
- La devise n'est pas fournie par l'API documentée : vérifiée **si** elle apparaît, sinon fondée sur la devise envoyée (XOF) — à confirmer lors de la recette sandbox.
- L'expiration d'un lien (24 h) n'a pas de statut documenté : une tentative restée ouverte est **signalée** (jamais déclarée expirée ni annulée automatiquement).
- Pas de remboursement exécuté, pas de reversement, pas de séquestre supposé, pas de mode réel, pas d'avis.
