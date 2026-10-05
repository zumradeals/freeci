# Lot 3 — paiement simulé et pièces jointes privées du brief

Statut : **projet de travail** livré pour recette ; rien ici n'est une décision adoptée tant que le dirigeant ne l'a pas validé.
Hors périmètre (volontairement) : remboursement, reversement, missions, litiges, prestataire de paiement réel.

## 1. Paiement simulé

### Cloisonnement démonstration / réel (installation publique)
Le simulateur n'agit que si **toutes** ces conditions sont réunies (`App\Modules\Finance\SandboxGate`) :
1. `FREECI_PAYMENT_SANDBOX=true` (**faux par défaut**) ;
2. la commande est une commande de démonstration (`orders.is_demo`) ;
3. client, freelance et service sont des éléments de démonstration ;
4. le compte client porte l'autorisation de recette (`users.sandbox_payments`, posée uniquement par la console, uniquement sur un compte `is_demo`).

Sinon : refus (409), aucune tentative créée. **Il n'existe ni bouton public ni paramètre d'URL qui confirme un paiement.**
Le client peut seulement *démarrer* une tentative. L'issue est fixée par l'opérateur en console (`freeci:sandbox:resolve`), qui envoie des
notifications **signées (HMAC, tolérance 300 s)** par la route webhook réelle ; la route répond 404 si le simulateur est désactivé ou si le secret est vide.
Toute notification « réussie » est **revérifiée côté serveur** auprès du contrat `PaymentProvider::verify` avant effet.

### Séparation commande / tentative / état financier
- `orders` : avancement de la commande. `payments` : tentatives (une seule ouverte et une seule confirmée par commande, index uniques partiels). `ledger_batches/lines` : écritures équilibrées, ajout seul. Montant : **toujours l'accord figé**, jamais la requête.
- États d'une tentative : `created → pending → (unknown) → confirmed | failed`, jamais de régression (un événement ancien ne défait pas un état plus avancé).
- Une confirmation tardive ou de montant différent crée un **cas de réconciliation** au lieu d'un effet silencieux.
- **Aucune nouvelle tentative tant qu'une tentative est ouverte ou incertaine** ; aucune après confirmation.

### Idempotence
Clé d'opération (`command_receipts`) pour les actions du client ; identifiant d'événement unique par prestataire (`payment_events`, compteur de doublons). Événement reçu N fois ou en désordre = un seul effet.

### Démarrage de la commande
Seul `StartOrderIfReady` démarre : paiement **confirmé côté serveur** ET brief complet (réponses renseignées, et ≥ 1 fichier contrôlé si l'**accord figé** l'exigeait). Il enregistre `started_at` et `due_at` **une seule fois** (immuables par déclencheur SQL). Paiement confirmé + brief incomplet → état « Brief à compléter » ; le démarrage a lieu à la complétion.
Compléter le brief ne modifie jamais l'accord : l'exigence de fichier est copiée dans l'accord à la demande (`order_agreements.brief_requires_files`).

### Échéance de paiement et commandes existantes
- L'échéance de paiement (`FREECI_PAYMENT_HOURS`) ne court **que** si le paiement est ouvert à l'acceptation (simulateur éligible). Sinon `payment_deadline_at` reste NULL et la commande **n'expire jamais** faute de paiement.
- **Commandes du lot 2 déjà acceptées** : aucune migration de données ; leur échéance reste NULL, elles ne sont pas expirées, ne peuvent pas être payées (le paiement n'est pas ouvert) et restent annulables par le client. Une commande réelle reste dans cet état tant qu'aucun vrai prestataire n'existe.

## 2. Pièces jointes privées du brief
- Dépôt : **client uniquement**, avant le démarrage. Extensions : PDF, PNG, JPG/JPEG, WebP, DWG. Taille : `FREECI_UPLOAD_MAX_MB` (10 Mo par défaut). Type **réel** vérifié (finfo + signature), double extension et extensions exécutables refusées, nom assaini.
- Stockage : disque privé `private_files` (hors `public/`), clé opaque, jamais exposée.
- Quarantaine → contrôle → propre. Le contrôle s'exécute après la réponse et est repris par `freeci:files:scan` (planifié toutes les 5 minutes (cron `schedule:run` déjà en place)). **Un fichier non contrôlé ou refusé n'est jamais téléchargeable**, même avec un lien fabriqué ; « scanner indisponible » ne rend jamais un fichier propre.
- Téléchargement : parties de la commande uniquement ; lien signé de 5 min lié à l'utilisateur, droits revérifiés ; réponse en pièce jointe, `application/octet-stream`, `nosniff`, `no-store`. Tiers : 404.
- Retrait : client, avant démarrage ; après démarrage le brief est figé.
- **Sans service de contrôle, le dépôt est désactivé** (`FREECI_FILE_SCANNER=none`, défaut) et l'interface le dit : le brief reste textuel. Le service de recette « brief avec fichier » n'est utilisable qu'avec ClamAV.

### Installer ClamAV (facultatif ; ne rien présumer de sa présence)
```
sudo apt-get install -y clamav-daemon
sudo systemctl enable --now clamav-freshclam clamav-daemon     # le démon met quelques minutes à charger les signatures
ls -l /var/run/clamav/clamd.ctl                                  # socket du démon
sudo usermod -aG clamav freeci                                   # si l'accès à la socket est refusé, puis rouvrir la session
```
Puis dans `.env` : `FREECI_FILE_SCANNER=clamav` (et `FREECI_CLAMSCAN_BINARY=/usr/bin/clamdscan` si différent), `php artisan config:cache`, et `php artisan freeci:files:check` (doit afficher « opérationnel »). Retour arrière : `FREECI_FILE_SCANNER=none`. Consommation mémoire de ClamAV : prévoir ≈ 1 Go.

## 3. Variables d'environnement
`FREECI_PAYMENT_SANDBOX` (false), `FREECI_SANDBOX_WEBHOOK_SECRET` (vide ; `openssl rand -hex 32`, seulement si simulateur actif), `FREECI_FILE_SCANNER` (none|clamav), `FREECI_CLAMSCAN_BINARY`, `FREECI_UPLOAD_MAX_MB`.

## 4. Recette (comptes de recette)
`deploy/recette.sh amorcer` → `deploy/recette.sh simulateur-on` (+ ClamAV pour le service à fichier) ; client : demande → freelance accepte → client « Aller au paiement simulé » → *Payer* (état « Vérification en cours ») ; opérateur :
`php artisan freeci:sandbox:resolve <SBX-…> succeeded --notify` (ou `failed`, `pending`, `--events=succeeded,succeeded,failed`) ; la commande démarre (ou attend le brief). Fin : `deploy/recette.sh simulateur-off`.

## 5. Limites connues
Pas de remboursement ni reversement ; pas d'expiration d'une tentative incertaine (traitée par l'opérateur) ; pas de notification courrier ; téléversement une pièce à la fois ; aucun prestataire réel.
