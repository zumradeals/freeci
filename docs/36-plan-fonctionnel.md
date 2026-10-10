# 36 — Plan fonctionnel (fonctionnalités à ajouter)

> **Statut : projet de travail.** Ce document n'est ni adopté ni un engagement de livraison. Seules les lignes marquées **DEC** sont des décisions du porteur ; tout le reste est une **proposition (PROP)** ou une **question (Q)** qui attend sa validation.
> Il ne déclare rien « fait » : l'état de l'existant (§3) est celui constaté dans le code à la date du document.

Étiquettes : **DEC** décision du porteur · **PROP** proposition · **Q** question ouverte.

---

## 1. Décisions prises

| Réf. | Décision | Source |
|---|---|---|
| DEC-F01 | **Photo de profil** : publication **immédiate**, **signalement** possible par tout utilisateur connecté, et un bouton **« Retirer la photo »** pour l'administrateur, avec **motif** obligatoire et **trace dans le journal d'audit**. | Échange du porteur |
| DEC-F03 | **Q1 : la photo de profil est facultative aussi pour les clients** (mêmes règles ; visible seulement des personnes en commande avec eux et de l'équipe tant qu'ils n'ont pas de profil freelance publié). | Échange du porteur |
| DEC-F04 | **Q7 : une photo retirée par l'administration est conservée 30 jours, ou tant qu'un dossier d'assistance est ouvert sur le profil, puis effacée.** | Échange du porteur |
| DEC-F02 | **Création et suppression de comptes, services ou autres contenus par l'administrateur : abandonnées pour le moment.** Aucun développement, aucune maquette. Le sujet pourra être rouvert par le porteur. | Échange du porteur |

---

## 2. Principes directeurs (hérités des décisions existantes)

1. **L'administration modère, elle ne rédige pas** : elle masque, suspend, retire avec motif ; elle ne réécrit ni n'invente le contenu d'autrui (même principe que pour les avis).
2. **Sandbox tant que non décidé** : aucun paiement réel sans décision explicite du porteur et validation juridique.
3. **Moindre pouvoir et traçabilité** : toute action sensible exige un motif, une confirmation récente d'identité quand elle est administrative, et laisse une trace au journal d'audit.
4. **Pas de promesse commerciale non adoptée** : aucun texte de garantie, de délai ou de gratuité n'est publié sans validation du porteur.
5. **Données personnelles** : tout nouveau type de donnée est couvert par l'export, la fermeture de compte et la politique de confidentialité **avant** mise en ligne.
6. **Chaque lot** suit le cycle déjà établi : maquette proposée → approbation explicite → implémentation avec tests → captures 360 / 768 / 1024 / 1440 px → entrée dans `docs/LIVRAISONS.md` → déploiement par le porteur.

---

## 3. Constat sur l'existant (vérifié dans le code)

- **Aucune photo de profil** : l'avatar est un rond à initiales partout ; le profil freelance porte nom affiché, titre, ville et présentation. Les services ont une couverture, traitée par un module d'image qui contrôle et ré-encode.
- **Signalement d'un profil** : déjà prévu dans les cibles de signalement (profil, service, mission, message, avis, réponse).
- **Fermeture de compte** : existe côté utilisateur (délai de réflexion, obstacles bloquants, anonymisation).
- **Export des données personnelles** : existe.
- **E-mails** : la configuration d'envoi est détectée, mais le fichier d'exemple est en mode « journal » ; l'état réel du serveur est **à vérifier** avant toute ouverture.
- **Paiement** : intégration Genius Pay en **bac à sable** ; reversements enregistrés mais non automatisés.
- **Double authentification** : constatée pour l'administration et le support ; **non constatée** pour les autres utilisateurs.
- **Documents PDF** : aucun reçu ni facture générés pour les utilisateurs.
- **Notifications** : dans l'application, et par e-mail quand l'envoi est configuré ; aucun canal SMS ou WhatsApp.

---

## 4. Chantiers proposés, par vagues

### Vague 0 — Prérequis d'une vraie ouverture

| Réf. | Chantier | Dépendances | Points à cadrer |
|---|---|---|---|
| F-01 | **Photo de profil** (spécification détaillée au §5) — **livrée au lot 67 (maquette approuvée)** | Module d'image existant | **Politique de confidentialité à valider par le porteur avant l'ouverture publique** |
| F-02 | **E-mails opérationnels** : configuration serveur, confirmation de compte, notifications, test de bout en bout — **guide et commande d'essai livrés (`docs/37-courrier.md`, `freeci:mail:test`) ; reste le choix du fournisseur (Q2) et la configuration du serveur par le porteur** | Choix d'un fournisseur d'envoi (Q2) | Aucune donnée sensible dans les e-mails (déjà la règle) |
| F-03 | **Paiement réel et versements Mobile Money** (Orange, MTN, Moov, Wave) | Compte et clés fournisseur ; texte juridique ; décision du porteur | Séparation test / réel conservée ; reversement jamais confirmé sans preuve |
| F-04 | **Reçus et factures PDF** (client et freelance), numérotation, mentions légales | F-03 pour les montants réels ; mentions à fournir | Conformité fiscale à confirmer (Q4) |
| F-05 | **Notifications WhatsApp / SMS** (invitation à consulter l'application, sans contenu) | Choix du fournisseur et du coût (Q3) | Consentement et désinscription |
| F-06 | **Vérification d'identité légère** (téléphone, ou pièce avec badge « vérifié ») | Cadrage juridique (Q5) | Conservation des pièces : le moins possible |

### Vague 1 — Vendre mieux

| Réf. | Chantier | Description |
|---|---|---|
| F-07 | **Portfolio** — **livré au lot 69 (maquette approuvée ; 8 réalisations au plus, aucun lien, agrandissement dans la page, plus récente d'abord, signalement avec le profil)** | Exemples de réalisations sur le profil (images contrôlées, titre, courte description) ; mêmes règles de signalement et de retrait que la photo |
| F-08 | **Offres à niveaux et options payantes** — **livré au lot 76 (maquette approuvée, 5 points approuvés)** | Basique / standard / premium et options sur un service ; l'accord de commande fige le choix (comme aujourd'hui pour prix, délai, périmètre) |
| F-09 | **Offre personnalisée** — **livrée au lot 75 (maquette approuvée, 5 points approuvés)** | Depuis la messagerie : le freelance propose prix, délai, périmètre ; le client accepte ; création d'une commande normale |
| F-10 | **Disponibilité et temps de réponse** — **livré au lot 74 (maquette approuvée, 5 points approuvés)** | Mode « indisponible » du freelance (ses services restent visibles mais non commandables) ; temps de réponse **calculé**, jamais déclaré |
| F-11 | **Alertes de recherche et missions recommandées** — **livré au lot 77 (maquette approuvée, 12 points approuvés avec F-12)** | Alertes par catégorie ; recommandations fondées uniquement sur la catégorie et le budget |
| F-12 | **Inviter un freelance à une mission** — **livré au lot 77** | Le client invite ; le freelance décide de proposer ou non |
| F-13 | **Jalons de paiement pour les missions** — **livré au lot 78 en sandbox (cadrage docs/41 validé, maquette approuvée, 9 points)** | Découpage d'une mission en étapes payées séparément ; **dépend de F-03** et d'un cadrage financier (Q6) |

### Vague 2 — Croissance

F-14 parrainage et codes promotionnels · F-15 version anglaise · F-16 référencement (plan du site, aperçus de partage, données structurées) · F-17 statistiques d'administration et exports CSV — **livrée au lot 79 (maquette approuvée, 12 points approuvés)** · F-18 double authentification facultative pour tous — **livrée au lot 73 (maquette approuvée, 5 points approuvés)**.

### Reporté

F-X1 création / suppression de comptes et de contenus par l'administrateur (**DEC-F02**).

---

## 5. Spécification proposée — F-01 Photo de profil

**Objectif.** Donner un visage aux profils (confiance), sans alourdir la modération.

**Périmètre (PROP).**
- Dépôt par le **freelance** depuis son profil ; **facultatif pour le client** si le porteur le valide (Q1).
- Formats JPG, PNG, WebP ; poids maximal à fixer (proposition : 5 Mo avant traitement) ; recadrage **carré** ; deux tailles dérivées (petite pour listes et messages, grande pour le profil).
- Traitement par le module d'image existant : contrôle du fichier, **ré-encodage**, suppression des métadonnées (position GPS comprise).
- **Publication immédiate** (DEC-F01) ; remplacement et suppression possibles à tout moment par la personne ; repli sur les **initiales** quand il n'y a pas de photo.

**Où elle s'affiche (PROP).** Profil public, cartes de services et de freelances, messagerie, commandes, propositions, avatar de l'en-tête.

**Signalement (DEC-F01).** Le profil est déjà une cible de signalement ; le bouton de signalement existant couvre la photo, avec un motif dédié « Photo de profil ». Le dossier arrive dans l'assistance comme les autres signalements. La personne signalée n'est pas informée.

**Retrait par l'administrateur (DEC-F01).**
- Action **« Retirer la photo »** sur la fiche du compte et depuis un dossier de signalement.
- **Motif obligatoire** (10 à 1000 caractères), **confirmation récente d'identité**, **trace dans le journal d'audit** (qui, quand, quel compte, quel motif).
- Effet : la photo cesse d'être servie (retour aux initiales) ; l'utilisateur en est informé avec le motif ; le fichier est conservé le temps nécessaire à un éventuel litige, puis effacé (durée à fixer : Q7).
- Après un retrait, l'utilisateur peut déposer **une autre** photo ; en cas de récidive, la suspension du compte reste l'outil existant. Un blocage automatique du dépôt n'est pas prévu (Q8).

**Données personnelles.** La photo est une donnée personnelle : incluse dans l'**export**, **supprimée à la fermeture du compte**, mentionnée dans la **politique de confidentialité** (texte à valider par le porteur).

**Critères de validation.** Dépôt, remplacement, suppression par l'utilisateur ; refus d'un fichier non image ou trop lourd ; métadonnées retirées ; affichage et repli sur les initiales aux cinq largeurs ; signalement d'un profil avec photo ; retrait par l'administrateur avec motif, trace au journal et notification ; photo retirée absente des pages publiques, du cache et de l'export public ; fermeture de compte qui efface le fichier ; aucune régression sur la suite de tests ; captures 360 / 768 / 1024 / 1440 px.

**Hors périmètre.** Retouche d'image, galerie multiple (voir F-07), reconnaissance de visage.

---

## 6. Ordre et dépendances (PROP)

1. **F-01** : indépendant, livrable en premier ; ne dépend que de textes à valider.
2. **F-02** en parallèle : purement technique et serveur.
3. **F-03 → F-04 → F-13** : chaîne financière, à ne démarrer qu'avec la décision du porteur et la validation juridique.
4. **F-05, F-06** : après choix des fournisseurs et cadrage juridique.
5. **Vague 1** : F-07 suit F-01 (mêmes règles d'image) ; F-08 et F-09 touchent l'accord de commande et demandent un soin particulier pour ne rien réécrire des commandes existantes ; F-10 à F-12 sont indépendants.
6. **Vague 2** : selon l'usage observé après ouverture.

Un chantier ne commence qu'après : sa **maquette approuvée** si l'interface change, la **validation des textes** concernés, et la définition de ses **critères de validation**.

---

## 7. Questions ouvertes pour le porteur

| Réf. | Question | Recommandation |
|---|---|---|
| Q1 | Photo de profil facultative aussi pour les clients ? | **Tranché : oui (DEC-F03)** |
| Q2 | Fournisseur d'envoi d'e-mails ? | **Tranché par le porteur : SMTP du serveur o2switch, dès que le domaine freeci.net est acheté et opérationnel** |
| Q3 | WhatsApp ou SMS comme premier canal mobile ? | WhatsApp s'il est accessible via fournisseur agréé ; sinon SMS |
| Q4 | Mentions et numérotation exigées pour les reçus et factures ? | À faire confirmer par un conseil fiscal avant F-04 |
| Q5 | Niveau de vérification d'identité souhaité et conservation des pièces ? | Téléphone d'abord ; pièce d'identité plus tard, si nécessaire |
| Q6 | Règles de découpage et de paiement par jalons ? | À cadrer avec les règles financières existantes avant toute maquette |
| Q7 | Durée de conservation d'une photo retirée ? | **Tranché : 30 jours ou dossier ouvert (DEC-F04)** |
| Q8 | Blocage du dépôt de photo après retrait ? | Non : s'appuyer sur la suspension du compte |

---

## 8. Suite

À la validation de ce plan, ou de ses parties : (1) maquette de **F-01** à présenter ; (2) implémentation après approbation ; (3) entrée au registre des lots (`docs/LIVRAISONS.md`). Les autres chantiers sont planifiés un à un, sur décision du porteur.
