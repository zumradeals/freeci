# 37 — Courrier (F-02) : mise en service et vérification

> **Statut : guide de travail.** Le choix du fournisseur d'envoi (Q2 du plan fonctionnel) reste **à décider par le porteur** ; ce guide n'en impose aucun.

## 1. Ce que le code fait déjà

- Les courriels existants : confirmation et changement d'adresse, mot de passe oublié, avis de compte, notifications (invitation à consulter l'application, **sans contenu**).
- `MailStatus::configured()` : tant que `MAIL_MAILER` vaut `log` ou `array`, **rien n'est envoyé** et le site ne le présente jamais comme envoyé (l'écran « Mon compte » et l'état d'exploitation l'indiquent).
- Les notifications par courriel partent par la file de tâches, avec reprises ; l'état d'envoi est visible dans l'administration (« État et préparation »).

## 2. Ce qu'il faut décider et fournir (porteur)

1. **Fournisseur SMTP** (ou service d'envoi transactionnel) : adresse du serveur, port, chiffrement, identifiant, mot de passe. Aucun secret ne doit être écrit dans une conversation ni dans le dépôt.
2. **Adresse d'expédition** : par exemple `no-reply@` suivi du domaine du site ; une adresse de réponse humaine (`contact@`) est conseillée pour les questions.
3. **Domaine d'envoi** : enregistrements DNS **SPF**, **DKIM** et **DMARC** fournis par le prestataire, sans quoi les messages arrivent en courrier indésirable.

## 3. Procédure sur le serveur (à exécuter par le porteur)

1. Modifier `/var/www/freeci/.env` (propriétaire `freeci`) :
   ```
   MAIL_MAILER=smtp
   MAIL_HOST=<serveur fourni>
   MAIL_PORT=<port fourni>
   MAIL_SCHEME=<smtps si port 465, sinon laisser vide pour STARTTLS>
   MAIL_USERNAME=<identifiant>
   MAIL_PASSWORD=<mot de passe>
   MAIL_FROM_ADDRESS="no-reply@<domaine>"
   MAIL_FROM_NAME="FreeCI"
   ```
2. Recharger la configuration : `sudo -u freeci -H php /var/www/freeci/artisan config:clear` (puis le cache de configuration habituel du déploiement).
3. **Essai d'envoi** : `sudo -u freeci -H php /var/www/freeci/artisan freeci:mail:test <votre-adresse>` — la commande refuse les pilotes sans envoi réel, indique le pilote et l'expéditeur, et dit si le serveur de courrier a **accepté** le message.
4. Vérifier la **réception** (boîte principale, puis courriers indésirables) et l'en-tête du message (SPF/DKIM « pass »).
5. Vérifier la file : les notifications par courriel passent par la file de tâches ; l'écran « État et préparation » doit montrer un travailleur de file actif et aucun envoi bloqué.

## 4. Critères de validation

- Essai d'envoi reçu en boîte principale.
- Parcours de bout en bout : inscription → courriel de confirmation reçu → lien valide → connexion ; « mot de passe oublié » → courriel reçu → nouveau mot de passe.
- Une notification facultative activée envoie une invitation **sans contenu** ; désactivée, rien n'est envoyé.
- Aucun message ne contient de mot de passe, de code de sécurité ni de contenu de message privé.

## 5. Retour arrière

Remettre `MAIL_MAILER=log` puis `config:clear` : le site continue de fonctionner, les courriels ne partent plus et l'état « non configuré » est de nouveau affiché.
