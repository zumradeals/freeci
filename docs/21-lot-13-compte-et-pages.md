# Lot 13 — gestion du compte et pages d'information

## Ce qui existait déjà (réutilisé)
Inscription, connexion, réinitialisation du mot de passe, vérification d'adresse par lien signé (`EmailVerification`), journal de sécurité, notifications, `freeci:notifications:retry`, suspension de compte (`AccountStanding`), assistance, `freeci:preflight`. Aucun espace « Compte » n'existait (lien « bientôt »).

## Compte (`/espace/compte`)
- **Nom** modifiable ; le nom public d'un freelance reste celui de son profil.
- **Mot de passe** : mot de passe actuel exigé ; toutes les autres sessions sont fermées.
- **Sessions** : liste (appareil approximatif, IP, dernière activité), fermeture d'une session ou de toutes les autres ; jamais celle d'un autre compte.
- **Adresse e-mail** : mot de passe exigé ; lien signé à usage unique (60 min) envoyé à la **nouvelle** adresse ; l'ancienne reste active jusqu'à confirmation et est prévenue (demande, puis remplacement). Sans courrier réel configuré, le changement est **refusé** (rien n'est « vérifié » sans lien reçu). Une adresse déjà utilisée n'est pas révélée.
- **Export** (JSON, mot de passe exigé, 5/jour, `no-store`, rien conservé) : compte, profil, services, missions, propositions, commandes et paiements du demandeur, **ses propres** messages, avis, favoris, dossiers d'assistance (ses messages, hors notes internes), notifications, préférences, sessions et événements de sécurité. **Exclus** : messages et identités des autres, secrets (mot de passe, MFA), jetons de session, destination de reversement (chiffrée).
- **Fermeture** : demande (mot de passe + case), délai de réflexion **provisoire** (`FREECI_CLOSURE_GRACE_DAYS`, 14), annulable ; pendant la demande, aucune nouvelle activité. `freeci:accounts:close` (horaire) n'exécute que si **aucune obligation** : commande non terminée, reversement/remboursement réel à finaliser, opération financière en cours, dossier d'assistance non clos, mission ouverte, proposition active, habilitation du personnel, suspension. Sinon la demande reste en attente et les obstacles sont affichés.
- **Exécution** : anonymisation **en place** (nom « Compte fermé », adresse `…@compte-ferme.invalid`, mot de passe aléatoire, MFA effacée) ; profil dépublié et vidé, services archivés, favoris, notifications, préférences, blocages, sessions supprimés ; destinations de reversement désactivées. **Conservés** (rattachés au compte anonyme) : commandes, paiements, écritures et opérations financières, messages échangés, avis, dossiers d'assistance, pièces de commande, journaux de sécurité, destinations (chiffrées).

## Règles approuvées / points à valider
| Point | Statut |
|---|---|
| Un seul administrateur gère les opérations financières | Approuvé (D39) |
| Délai de réflexion 14 j avant fermeture | **À valider** (provisoire) |
| Durées de conservation (commandes, messages, écritures financières, dossiers, journaux de sécurité, destinations) | **À valider** : aucune durée décidée ; rien n'est purgé automatiquement |
| Fermeture refusée si compte suspendu (ne pas effacer l'imputabilité) | Choix de prudence, **à valider** |
| Messages d'un compte fermé conservés tels quels côté contrepartie | **À valider** |
| Compte du personnel | Habilitation à révoquer d'abord |

## Pages d'information (`/informations/{page}`)
Fonctionnement, aide, contact, conditions, confidentialité, mentions légales. **Brouillons** (bandeau, `noindex`) tant que le slug n'est pas dans `FREECI_PAGES_APPROVED` (décision du porteur après relecture). Identité de l'exploitant, adresse, immatriculation, directeur de publication, hébergeur, contact : `FREECI_OPERATOR_*`, `FREECI_PUBLICATION_DIRECTOR`, `FREECI_HOST_NAME`, `FREECI_CONTACT_EMAIL` ; vides → « à renseigner ». Aucune garantie de paiement, aucun frais, aucune durée de conservation n'est annoncée.

### Informations manquantes
1. Identité complète de l'exploitant (nom, forme, adresse, immatriculation), directeur de la publication, hébergeur.
2. Adresse de contact publique (et éventuel téléphone).
3. Texte définitif des conditions (paiement réel, frais/commission, remboursements, reversements, garanties, droit applicable, litiges).
4. Confidentialité : responsable du traitement, durées de conservation, base légale, exigences locales.
5. Validation des paramètres provisoires (14 j avis, 14 j fermeture, commission 10 %).

## Limites
Pas de double authentification pour les comptes ordinaires ; fermeture irréversible ; export sans pièces jointes ; l'ancienne adresse n'est prévenue que si le courrier est configuré.
