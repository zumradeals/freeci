# Lot 8 — administration sécurisée

Statut : livré, à recetter. Les choix ci-dessous sont des **propositions de travail** tant qu'ils ne sont pas validés par le dirigeant.

## 1. Accès : conditions cumulatives, revérifiées côté serveur à chaque requête
1. **Habilitation administrateur en vigueur** (`staff_grants`, datée, révocable, expirable) — sinon l'espace n'existe pas (404) et la tentative est journalisée.
2. **Adresse e-mail vérifiée** par un lien signé de 60 minutes envoyé par courriel (ou, faute de SMTP, attestation console explicite — voir §5).
3. **Double authentification TOTP activée** (application d'authentification) ; 8 codes de récupération à usage unique, affichés une seule fois, conservés sous forme d'empreinte HMAC.
4. **Défi franchi dans la session en cours** (validité 8 h, liée au compte) ; refait à chaque nouvelle connexion.
5. **Confirmation récente d'identité** (mot de passe, 10 min) pour les actes sensibles : suspension/remise en ligne d'un contenu, suspension/réactivation d'un compte, régénération des codes.
- Tant que (2) ou (3) manque, **rien ne s'ouvre** : l'administrateur est dirigé vers la page d'activation (`/admin/activation`). Aucune fonction n'est activée silencieusement.
- Chaque action d'administration (`AdminAudit::run`) revérifie habilitation + adresse vérifiée + double authentification, indépendamment de la session web.
- Limites : 5 codes MFA refusés → blocage de 15 min (même un bon code est refusé), 5 mots de passe de reconfirmation refusés / 10 min, connexion limitée (existant) ; un code TOTP accepté ne peut pas être rejoué ; toute tentative est journalisée (`security_events`).
- Aucun droit administratif n'est attribuable depuis l'inscription ou un formulaire : le modèle `User` n'accepte que `name`, `email`, `password` ; l'habilitation ne s'accorde que par la console.

## 2. Modération (web)
- Files « services à modérer » / « missions à modérer », fiche de contrôle avec **version soumise comparée à la version publique** (lignes modifiées surlignées), historique.
- Approuver, refuser avec motif (10–1000 car.), suspendre, remettre en ligne : **mêmes actions métier que la console** (`ServiceModeration`, `MissionModeration`, avec `via('modération (web)')`) ; aucune règle dupliquée. Missions : ajout de `suspend`/`reinstate` (statut `suspended`, réservé aux missions ouvertes ; remise en ligne refusée si la date limite est dépassée).
- L'auteur est notifié (notifications existantes) ; **nul ne modère son propre contenu** (action + interface).
- Une suspension ne touche pas aux commandes en cours.

## 3. Utilisateurs
- Recherche (nom, e-mail), filtres (état, rôle), pagination ; fiche : identité du compte, rôles, état, volumes (commandes, services, missions), historique des suspensions. Jamais de mot de passe, de secret, de message, de brief ni de fichier.
- **Suspension / réactivation motivées et historisées** (`account_restrictions`, ajout seul), notification de l'intéressé (le motif lui est communiqué).
- Effet d'une suspension — **nouvelles activités restreintes** : nouvelle demande, acceptation d'une nouvelle demande, nouvelle mission ou réouverture, nouvelle proposition, soumission d'un service/mission au contrôle, sélection d'une proposition (client suspendu ou candidat suspendu), nouvelle conversation ; services et missions du compte suspendu masqués du catalogue public. **Dossiers actifs inchangés** : paiement, brief, livraison, corrections, report, validation, messages d'une commande active ; refuser une demande reste possible.
- Pas de suppression de compte dans ce lot. Un administrateur en vigueur ne peut pas être suspendu (retirer d'abord l'habilitation par la console), ni se suspendre lui-même. L'attribution des habilitations reste à la console.

## 4. Tableau de bord et audit
- Compteurs réels : contenus à modérer, utilisateurs, comptes suspendus, alertes de sécurité (24 h) ; **besoins de suivi enregistrés** présentés comme de simples enregistrements (aucun litige pris en charge).
- Journal des actions administratives filtrable (auteur, action, résultat, cible, dates), paginé, en ajout seul (déclencheur PostgreSQL) ; actions refusées incluses avec la règle opposée. Journal des événements de sécurité (sans mot de passe, secret ni code ; l'adresse IP est conservée à des fins d'investigation).
- **Aucun accès général** aux conversations, briefs ou fichiers privés : ces écrans n'existent pas pour l'administrateur (404 sur le dossier de commande et la conversation). Leur consultation encadrée dans un dossier de support est prévue au lot suivant.

## 5. Activation de votre compte administrateur existant
Votre habilitation et votre mot de passe sont **inchangés**. Sur le serveur :
1. `sudo -u freeci -H php /var/www/freeci/artisan freeci:admin:status <votre-courriel>` — état des prérequis.
2. **Si le SMTP est configuré** : connectez-vous, ouvrez `/admin`, cliquez « Envoyer le lien de vérification », ouvrez le lien reçu (connecté, même navigateur).
   **Sinon** (SMTP absent) : `sudo -u freeci -H php /var/www/freeci/artisan freeci:admin:verify-email <votre-courriel> --attest` — attestation explicite, journalisée comme « console ». Un courriel écrit dans les journaux n'est jamais une adresse vérifiée.
3. Dans `/admin/activation` : « Commencer », saisissez la clé dans votre application d'authentification (saisie manuelle, type « basé sur l'heure »), confirmez avec le code à 6 chiffres, puis **conservez les 8 codes de récupération** (affichés une seule fois).
4. Aux connexions suivantes : code de l'application (ou un code de récupération) à chaque session.

## 6. Récupération
- Application perdue, codes disponibles : saisir un code de récupération (usage unique) ; en régénérer depuis « Ma sécurité ».
- Application **et** codes perdus : depuis le serveur, `php artisan freeci:admin:mfa-reset <courriel>` — journalisé (`mfa_reset`), supprime le secret et les codes, **ferme toutes les sessions du compte** ; l'administration reste fermée jusqu'à réactivation (§5, étape 3).
- Habilitation : `freeci:admin:grant` / `freeci:admin:revoke` (journalisés dans `security_events`) ; `freeci:admin:list` et `freeci:admin:status` sans secret.

## 7. Configuration du courrier (SMTP) — à faire pour vérifier les adresses par lien
Dans `/var/www/freeci/.env` (valeurs fournies par votre prestataire ; **ne les transmettez pas dans une conversation**) :
```
MAIL_MAILER=smtp
MAIL_HOST=...
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS="no-reply@votre-domaine"
```
puis `sudo -u freeci -H php artisan config:cache` (ou la prochaine mise à jour). Avec un pilote `log`/`array`, l'envoi est déclaré **indisponible** (aucun faux « envoyé »). « Envoyé » = accepté par le serveur SMTP, pas remis en boîte.

## Limites
- Pas de code QR : saisie manuelle de la clé (évite une dépendance externe).
- La session MFA dure 8 h (`freeci.admin.mfa_session_minutes`) ; reconfirmation 10 min (`reauth_minutes`) : valeurs provisoires.
- Courriels de vérification envoyés de façon synchrone (limités à 3 / 10 min) ; pas de file.
- Les non-administrateurs ne sont pas soumis à la double authentification (non demandé).
