# Lot 7 — messagerie privée et notifications

Statut : livré, à recetter. Aucune décision nouvelle n'est prise ici ; les choix ci-dessous sont des **propositions de travail** tant qu'ils ne sont pas validés.

## Messagerie
- Une conversation relie exactement deux parties (client, freelance) et un contexte : **service**, **proposition** (mission) ou **commande**. Une seule conversation par couple et par contexte (index uniques partiels).
- À la demande de prestation ou à la sélection d'une proposition, le fil existant est **rattaché à la commande** (`order_id`) : l'historique est conservé, les fils des autres candidats ne sont ni touchés ni exposés.
- Accès réservé aux **participants** : aucun accès administrateur implicite ; la conversation d'autrui répond 404.
- Messages en ajout seul (déclencheur PostgreSQL), idempotents (`client_key`), paginés, avec non-lus par partie.
- Pièces jointes : même chaîne que le brief (type réel, quarantaine, contrôle, téléchargement par lien signé lié à l'utilisateur). Un fichier de message n'est jamais un fichier de brief ni de livraison (contraintes CHECK).
- Un message ou une pièce jointe **n'est jamais** une livraison, une modification d'accord, une acceptation de report ni une validation : ces actes passent uniquement par la commande.
- Protections : limite de débit (30 messages / 10 min et `throttle:30,1`), double soumission neutralisée, corps limité à 4 000 caractères.
- Blocage de contact : suspend les échanges non indispensables dans les deux sens, sans effacer l'historique ; les échanges liés à une commande **active** (ni annulée, ni expirée, ni clôturée) restent permis.

## Notifications
- Centre dans l'application, compteur de non-lus, marquage lu. Chaque ligne d'historique métier (commande, service, mission) produit **une** notification (`dedupe_key`, unique par utilisateur) : un événement rejoué n'en crée pas de seconde.
- Les messages non lus d'une même conversation sont **regroupés** en une notification, sans reprise du texte.
- Les liens passent par une route nommée ; l'écran cible **revérifie les droits** (lien falsifié : 404).
- Types **essentiels** (non désactivables) et **facultatifs** (application : actif par défaut ; courriel : désactivé par défaut), réglables dans les préférences.

## Courriels
- File `database`, tâche `SendNotificationEmail` : 5 essais, délais 1, 5, 15 et 60 min ; échec définitif → état `failed` (code générique, sans secret).
- États : `none`, `unavailable` (courrier non configuré : jamais présenté comme envoyé), `pending`, `sent` (= accepté par le serveur SMTP configuré, rien de plus), `failed`.
- Le courriel ne contient **ni message privé ni pièce jointe** : il invite à consulter l'espace connecté.
- Sans SMTP, les notifications dans l'application fonctionnent normalement.

## Exploitation
- Variables : `FREECI_NOTIFICATION_EMAILS` (`true` par défaut ; `false` coupe les courriels), `FREECI_QUEUE_VIA_SCHEDULER` (`false` ; `true` vide la file depuis le planificateur, pour un hébergement sans travailleur permanent).
- Travailleur : `deploy/freeci-queue.service.example` (unité systemd `queue:work`). `update.sh` et `rollback.sh` exécutent `queue:restart`.
- Planificateur : le cron `schedule:run` existant exécute `freeci:notifications:retry --stale` toutes les 10 minutes.
- Commandes : `freeci:notifications:status` (courrier configuré, file, échecs), `freeci:notifications:retry [--failed] [--stale]`. `freeci:preflight` signale une file non traitée.

## Limites assumées
- Pas de temps réel : actualisation légère par interrogation (30 s) via Livewire.
- Pas de litiges, d'administration métier ni de Genius Pay dans ce lot.
- Pas de SMS ni de notifications push.
