# Lot 16 — paramètres administrables et textes légaux éditables

## Principe
L'administration est le poste de contrôle de la plateforme : les règles métier ne se règlent plus dans le code ni dans le `.env`. Le code et le `.env` ne fournissent que les **valeurs par défaut** ; une valeur saisie en administration les **superpose** (lue au démarrage de chaque requête, une seule lecture mise en cache, invalidée à chaque écriture). Sans saisie, le comportement est exactement celui d'avant.

## Paramètres (`/admin/parametres`)
| Groupe | Paramètres | Statut |
|---|---|---|
| Commission | taux (0–30 %) | provisoire / approuvé ; confirmation explicite ; ne s'applique qu'aux nouvelles commandes (taux figé dans chaque accord) |
| Délais | réponse du freelance, paiement, examen d'une livraison, report maximal, sélection d'une mission, publication des avis, réflexion avant fermeture | provisoire / approuvé |
| Prix des services | minimum, maximum | provisoire / approuvé |
| Exploitant | nom, adresse, immatriculation, directeur de la publication, hébergeur, contact | sans statut |
| Site | masquage aux moteurs de recherche, notifications par courriel | sans statut |

Règles : valeurs bornées ; motif obligatoire (10 caractères) ; une valeur modifiée redevient *provisoire* tant que « Je valide ces valeurs » n'est pas coché ; approbation sans changement possible ; historique en ajout seul (`app_setting_changes`, ancienne/nouvelle valeur, auteur, motif) ; journal d'audit ; confirmation récente d'identité ; **un seul administrateur suffit** (aucune seconde approbation). L'étiquette de politique de commission dans les nouveaux accords passe à « approuvee » seulement si le taux est approuvé.

**Hors administration, volontairement** : mot de passe SMTP, clés et secrets Genius Pay, `APP_KEY`, mode de paiement et autorisation du live (décision séparée). Un secret dans une page web exposerait les clés de paiement en cas de compromission de cette page.

## Pages légales (`/admin/pages`)
Six pages : fonctionnement, aide, contact, conditions, confidentialité, mentions légales. États : texte de départ → brouillon de l'administrateur (jamais public) → **adopté** (publication motivée, version numérotée, historique `legal_page_history` en ajout seul) ; retrait possible (le texte et l'historique sont conservés). Seul un texte adopté perd le bandeau « brouillon » (`FREECI_PAGES_APPROVED` supprimée). Format simple (paragraphes, titres, listes, gras/italique, liens ; HTML neutralisé) ; jetons `{exploitant}` `{adresse}` `{immatriculation}` `{directeur}` `{hebergeur}` `{contact}` remplacés par les paramètres (« à renseigner » si vides). Les textes de départ restent des projets de travail : aucun n'est présenté comme approuvé.

## Préparation à l'ouverture
Les lignes « Paramètres commerciaux », « Pages légales » et « Identité de l'exploitant » lisent désormais ces écrans et proposent « Compléter ».

## Migration
`2026_10_22_000100_create_app_settings_and_legal_pages` : nouvelles tables seulement.

## Limites
Les bornes techniques du catalogue (images, longueurs de champs), les limites de fichiers et le plafond de missions restent dans le code ; à exposer si le besoin apparaît. Les processus `queue:work` déjà lancés relisent les paramètres au redémarrage (`queue:restart` par `update.sh`).

## Lot 17 — extension : secrets, limites techniques et autres réglages
Décision du porteur : l'espace d'administration est le poste de contrôle global ; l'accès est protégé par double authentification (application d'authentification). Le registre couvre désormais **107 paramètres en 11 rubriques** : commission, délais, prix, **paiement Genius Pay** (mode, ouverture des paiements, autorisation du réel, adresse API, hôtes, tolérance, clés et secrets sandbox/live, comptes marchands), **courrier SMTP**, fichiers (antivirus, tailles), limites des services et profils, limites des missions et propositions, avis/messagerie/assistance, sécurité des accès (durées et tentatives), exploitant, site et sauvegardes.

**Secrets** (mot de passe SMTP, clés publiques/secrètes et secrets de webhook) : chiffrés avec `APP_KEY` au repos, **en écriture seule** (jamais réaffichés : le champ montre « défini »), jamais présents dans l'historique, le journal d'audit ni les pages ; champ vide = conserver ; case « retirer » = retour à la valeur du serveur. Une valeur illisible (clé de chiffrement changée) retombe sur la valeur du serveur.

**Paiement réel** : le mode reste « sandbox » par défaut. Passer en « live », ou donner l'autorisation du réel, exige : confirmation financière cochée + saisie de la phrase `PAIEMENT REEL` + confirmation récente d'identité. Revenir au test n'exige pas la phrase. Chaque commande et chaque paiement gardent leur environnement.

**Vérifier sans risque** : « Courriel de test » (envoyé uniquement à l'adresse de l'administrateur connecté) et « Tester la connexion » Genius Pay (lecture du compte marchand ; aucun paiement créé ; aucune clé affichée).

**Ne peuvent pas se régler ici** (nécessaires avant la lecture de la base ou à l'amorçage) : `APP_KEY` (la clé qui chiffre les secrets), identifiants de la base de données, `APP_URL`, `APP_ENV`/`APP_DEBUG`, réglages des cookies de session, HSTS. Ils restent dans le `.env` du serveur.
