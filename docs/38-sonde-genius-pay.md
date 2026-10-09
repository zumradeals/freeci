# 38 — Sonde du bac à sable Genius Pay (prompt pour Claude sur le VPS)

À copier-coller tel quel dans une session Claude Code lancée **sur le serveur** (dossier `/var/www/freeci`). Objectif : savoir, **avec des faits**, jusqu'où la recette financière (`docs/27`) peut être automatisée, et répondre aux points non établis du §8 de cette recette. Durée estimée : 30 à 60 minutes. Aucun argent réel n'est en jeu.

---

Tu travailles sur le VPS de FreeCI (https://freeci.dgafrique.com), dans `/var/www/freeci` (Laravel 13 / PHP 8.3 / PostgreSQL). Les commandes `artisan` se lancent avec `sudo -u freeci -H php8.3 artisan …`. Tu fais une **sonde** du bac à sable Genius Pay : tu observes, tu mesures, tu notes. Tu ne corriges rien dans le code.

## Règles absolues
1. **Aucun secret** dans ta réponse, tes journaux ou une commande affichée : jamais le contenu de `.env`, de clés, de secrets de webhook, de mots de passe. Les clés restent lues côté serveur par l'application (via `artisan`/`tinker`) ; ne les place jamais dans une ligne de commande ni dans un fichier.
2. **Bac à sable uniquement.** Vérifie d'abord `freeci:genius:status` : le mode doit être *sandbox* et la configuration sandbox *conforme*. Si le mode est *live*, ou si une clé `live` est présente dans l'environnement actif, **arrête-toi et préviens-moi**. N'active jamais le paiement réel ; ne modifie aucun réglage Genius Pay.
3. **Préserve les données.** Jamais de `migrate:fresh`, `db:wipe`, `TRUNCATE`, `DROP`, ni de suppression de lignes. Avant la première écriture : `sudo -u freeci -H /var/www/freeci/deploy/backup.sh avant-sonde-genius`.
4. **Ne déploie pas, ne modifie aucun fichier de l'application, ne commit pas, ne pousse pas.** Si une correction de code est nécessaire, décris-la (fichier, ligne, correctif proposé) dans ton rapport.
5. **Une seule commande d'écriture à la fois**, précédée d'une phrase : ce que tu fais, et comment l'annuler. Commence par des lectures.
6. **Périmètre des écritures** : uniquement les comptes de démonstration (`@demo.freeci.invalid`) et UNE commande de sonde de test. Ne touche à aucun compte ni commande réels. Si les comptes de démonstration n'existent pas, demande-moi de lancer `freeci:demo:recette` (les mots de passe générés ne doivent jamais être répétés dans ta réponse).
7. **Un seul paiement de sonde.** Pas de boucle, pas de répétition automatique d'un appel qui a échoué ou répondu de façon incertaine : note la réponse et arrête-toi sur ce point.

## Étape 0 — État initial (lecture seule)
1. `git -C /var/www/freeci rev-parse HEAD` et date du commit.
2. `freeci:genius:status` puis `freeci:genius:status --ping` (un appel réel `GET /account`, aucun paiement créé) : consigne le résultat, sans clé.
3. Administration → « État et préparation » (ou `freeci:finance:status`) : tâches planifiées à jour, file active, aucune opération « à vérifier ».
4. Sauvegarde (règle 3).

## Étape 1 — Créer la commande de sonde et le lien de paiement
1. Lis le code qui crée une demande, l'accepte et lance le paiement (`OrderRequestController`, `OrderController`, `PaymentController`, `app/Modules/Finance/Actions/InitiatePayment.php`) pour repérer les actions applicatives à utiliser.
2. Avec ces **actions applicatives** (par `artisan tinker`, en agissant comme le client puis le freelance de démonstration, sans mot de passe), crée une demande sur un service de démonstration, fais-la accepter, puis démarre le paiement jusqu'à l'obtention du **lien de checkout** (colonne `payments.checkout_url`). Si ce n'est pas possible proprement par `tinker`, **arrête-toi et demande-moi** de dérouler le parcours dans le navigateur jusqu'à « Continuer vers Genius Pay », puis reprends à partir de la ligne `payments` créée.
3. **Si aucun service de démonstration n'accepte de demande** (cas constaté : les services d'exemple ont `accepts_requests = false` et le service de recette peut être archivé ; `freeci:demo:recette` ne le republie pas, `firstOrCreate`) : après sauvegarde, et **seulement avec l'accord écrit du porteur**, passe `accepts_requests` à `true` sur UN service de démonstration déjà publié (noter l'ancienne valeur), crée la demande, puis **remets aussitôt `accepts_requests` à `false`** : ce champ n'est lu qu'à la création de la demande (`RequestService`), l'acceptation et le paiement n'en dépendent pas. Fenêtre d'ouverture : quelques minutes ; consigne les deux heures.
4. Consigne : référence de la commande et du paiement, environnement enregistré (attendu `sandbox`), état du paiement.

## Étape 2 — Observer la page de paiement du bac à sable (lecture seule, sans payer)
Avec `curl -sIL` puis `curl -sL` sur le lien de checkout (hôte déjà autorisé par l'application) :
1. Code HTTP, redirections, `Content-Type`, taille, serveur, présence de Cloudflare ou d'une protection anti-robot (en-têtes, scripts, mots « captcha », « challenge », « turnstile », « recaptcha »).
2. La page est-elle un **HTML simple** (formulaires, boutons) ou une **application JavaScript** qui n'affiche rien sans exécuter le script ? Liste les champs de formulaire, les boutons, les mentions « sandbox », « test », « simuler ».
3. Cherche dans la documentation publique de Genius Pay (liens cités dans `docs/17-lot-10-genius-pay-sandbox.md`) ce qui décrit la **simulation de succès / échec / attente** en bac à sable : numéros de test, code de validation, page « simulateur », option dans le tableau de bord. Cite le passage exact et l'adresse de la page.
4. **Conclusion A / B / C** (à justifier) :
   - **A** : un paiement sandbox peut être réglé par de simples requêtes HTTP (pas de JavaScript indispensable, pas de protection) ;
   - **B** : réglable par un navigateur automatisé (JavaScript, mais sans captcha ni code envoyé sur un téléphone) ;
   - **C** : intervention humaine nécessaire (captcha, code reçu par SMS ou application, validation sur un appareil).
   Si l'information manque, dis **« inconnu »** et ce qu'il faudrait pour trancher : ne devine pas.

## Étape 3 — Régler le paiement de sonde (seulement si A ou B, ou si je le fais pour toi)
1. Si la conclusion est **A** : règle le paiement avec les moyens décrits par la documentation, une seule fois.
   Si **B** ou **C** : **arrête-toi et demande-moi** de régler ce paiement dans le navigateur (je te dirai quand c'est fait), en notant précisément ce que j'ai dû faire (pages, champs, numéro de test utilisé).
2. Observe la confirmation : `payment_events` (événement reçu ? signature valide ?), état du paiement dans FreeCI, délai entre le règlement et la confirmation **sans** intervention. Si l'état n'avance pas, lance **une seule fois** `freeci:payments:reconcile` et consigne la différence.
3. Vérifie la commande : « confirmé côté serveur », passage à l'étape du brief, aucun démarrage automatique du travail.

## Étape 4 — Comportement du remboursement par API (points §8.1 à §8.3 de la recette)
Sur **ce seul paiement de sonde confirmé**, avec l'adaptateur de l'application (`GeniusPayProvider` de l'environnement `sandbox`, appelé par `tinker`, **jamais** par `curl` avec une clé) :
1. Demande **un** remboursement total (montant = montant payé). Consigne **exactement** la réponse : statut, référence de remboursement éventuelle, durée de l'appel.
2. Observe l'état du paiement chez le prestataire (`verify`) à plusieurs reprises sur quelques minutes : quel statut apparaît, au bout de combien de temps ? Un événement `payment.refunded` arrive-t-il sur le webhook ?
3. Demande un **second** remboursement du même paiement et consigne la réponse (la documentation annonce un appel idempotent : est-ce vrai ?). C'est le **seul** rejeu autorisé de toute la sonde.
4. Note l'effet de bord attendu : le remboursement a eu lieu **hors du circuit FreeCI** (ni opération ni écriture comptable de FreeCI). Il est possible qu'un dossier de rapprochement apparaisse : **ne le résous pas** ; décris-le, je le traiterai moi-même dans l'administration.

## Étape 5 — Ce que tu ne peux pas établir par toi-même
Dresse la liste (sans la résoudre) de ce qui reste **manuel ou inconnu** : actions dans le tableau de bord Genius Pay, existence d'un paiement sortant (reversement) à confirmer avec leur support (point §8.4), remboursement partiel en bac à sable, tout comportement que la documentation ne décrit pas.

## Ce que tu me rends
Un rapport en français, en quatre parties, **sans secret** :
1. **Constats** — pour chaque étape : ce que tu as fait, la preuve (extrait de sortie, en-tête, durée), et le résultat.
2. **Conclusion d'automatisation** — A, B, C ou « inconnu », avec le raisonnement, et ce que cela implique pour un script de recette.
3. **Changements appliqués** — la liste exacte de ce que tu as écrit (sauvegarde, commande et paiement de sonde, remboursement) et comment l'annuler ou le repérer ensuite.
4. **À décider ou à corriger côté développeur** — tout écart, ambiguïté de libellé, comportement inattendu, avec fichier et ligne quand tu les connais.

Termine en rappelant : paiement toujours en bac à sable, aucun mode live activé.
