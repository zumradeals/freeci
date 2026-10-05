# 09 — Lot 2 : demande de prestation et commande (sans paiement)

> Statut : **réalisé**. Parcours client → freelance utilisable sur le site déployé, **sans paiement réel**. Complète `04` §3, §6, §7, §8 et `02` §4.4, §6. Tout ce qui n'est pas listé en §1 est **à venir**.

## 1. Livré

| Contrat (docs/02 §6) | Réalisation |
|---|---|
| `Orders\RequestService` | Page « Décrire votre besoin » (formulaire serveur, sans JavaScript) ; une réponse par « élément à fournir » du service + précisions ; case de conditions (version et date enregistrées) ; commande « en attente de réponse », **accord figé**, brief **textuel**, historique ; idempotence par clé d'opération |
| `Orders\AcceptServiceRequest` | Freelance de la commande seulement, dans le délai de réponse (48 h) ; commande « en attente de paiement » |
| `Orders\DeclineServiceRequest` | Refus avec **motif obligatoire** (10 à 1 000 caractères), visible des deux parties |
| `Orders\WithdrawServiceRequest` / `Orders\CancelBeforePayment` | Le client retire sa demande (avant réponse) ou annule la commande acceptée (avant paiement) |
| Expiration | Une demande sans réponse à 48 h devient « expirée » : appliquée avant toute action, à l'affichage des listes de l'utilisateur et par `freeci:orders:expire` (tâche planifiée facultative) |
| `Orders\GetOrderDossier`, `ListOrders` | Dossier commun **borné aux deux parties** (Accord, Brief, Finances, Historique) ; inexistant et interdit répondent **exactement pareil** (404) |
| Tableaux de bord | Client et freelance : vraies données, actions classées par **échéance réelle** (la plus proche d'abord ; sans échéance ensuite), états vides honnêtes |
| Espace freelance minimal | `/freelance` (vue d'ensemble, demandes et commandes, services en **lecture seule**, profil) ; activation par un profil de trois champs (nom affiché, activité, ville) |
| Recette | `php artisan freeci:demo:recette` (volontaire) : un client, un freelance et un service de démonstration |

## 2. Règles garanties (et testées)

- **Accord figé** : copie, à l'instant de la demande, du titre, du prix, du délai, des corrections, du périmètre, des livrables, des exclusions, des éléments à fournir, des délais de réponse et de paiement, de la version des conditions. Table en **ajout seul** (déclencheur PostgreSQL). Une modification ultérieure du service n'a aucun effet ; un service modifié entre la consultation et l'envoi donne un **409** avec les conditions à jour (version du service = `row_version`, incrément atomique).
- **Aucun démarrage sans paiement** : la machine d'états n'a **aucune transition** vers « en attente du brief » ou « en cours » (elles appartiennent à `Finance\ConfirmPayment`, non implémenté). Aucune route ni colonne de paiement ; aucune échéance de réalisation ni **de paiement** ne court (`payments_open = false`, constante de configuration, non modifiable par variable d'environnement) : une commande acceptée reste « en attente de paiement » sans expirer.
- **États séparés** : la table `orders` ne contient aucune donnée de paiement ; l'onglet Finances affiche Paiement (non ouvert), Remboursement et Reversement (sans objet) séparément.
- **Autorisations côté serveur** : seul le freelance accepte ou refuse ; seul le client retire ou annule ; tout autre utilisateur, **administrateur compris**, reçoit 404. Listes bornées par relation. Champs falsifiés (état, parties, prix) ignorés.
- **Commander son propre service** : refusé (403) ; interdit aussi par contrainte de base (`client_id <> freelancer_id`).
- **Doubles soumissions** : même clé d'opération = un seul effet (reçu écrit dans la transaction) ; même clé avec un contenu différent = 409 ; une seule demande en attente par client et service (index unique partiel) ; version attendue périmée = 409 ; bouton verrouillé côté navigateur.
- **Historique** : ajout seul (déclencheur) ; auteur, date, état précédent → suivant, motif.
- **Services d'exemple du catalogue** : demandes **fermées** (leurs vendeurs ne sont pas connectables) ; seuls les services de recette ou réels acceptent des demandes.

## 3. Brief : textuel pour ce lot (décision)

Le brief est **textuel** : réponses aux éléments à fournir + précisions. **Aucun fichier** n'est accepté, parce que la chaîne de protection (stockage privé, contrôle de type et de taille, quarantaine et contrôle de sécurité avant téléchargement, liens signés) n'est pas encore construite. Le complément de brief après envoi viendra avec le lot suivant.

## 4. Décisions et limites

| Réf. | Décision / limite |
|---|---|
| D35 | Brief textuel dans ce lot ; fichiers avec la chaîne de protection complète dans un lot ultérieur |
| D36 | Délais : réponse 48 h, paiement 24 h (valeurs de `docs/04` §3.2), **figés dans l'accord** ; le délai de paiement ne court qu'à l'ouverture du paiement |
| D37 | Une demande acceptée ne peut **pas** démarrer dans ce lot : c'est voulu |
| D38 | L'attribution du rôle administrateur n'ouvre aucun accès aux commandes : l'administration métier reste à construire avec ses exigences (MFA, vérification d'adresse, courrier réel, accès support journalisé) |
| Limite | Pas de messagerie, pas d'avis, pas de report, pas de livraison, pas de litige, pas de missions : lots ultérieurs |
| Limite | Pas de notification (courrier non configuré) : chaque partie constate l'état dans son tableau de bord |
| Limite | Services : lecture seule pour le freelance ; création et publication de services dans un lot ultérieur (les services de la recette sont créés par la console) |
| Limite | Comparaison avant/après des conditions modifiées non affichée (seules les conditions à jour le sont) |

## 5. Recette avec deux comptes

1. `php artisan freeci:demo:recette` (en production : `FREECI_ALLOW_DEMO_SEED=true` le temps de l'opération, puis `false`). Les deux mots de passe sont **affichés une seule fois** ; les comptes existants ne sont jamais modifiés.
2. **Client** (`recette.client@demo.freeci.invalid`) : service « Mise en plan 2D… (service de recette) » → *Demander cette prestation* → remplir → *Envoyer* → consulter le dossier (état « En attente de réponse »).
3. **Freelance** (`recette.freelance@demo.freeci.invalid`) : vue d'ensemble → *Répondre à la demande* → lire le brief → *Accepter* (ou *Refuser* avec motif).
4. Client : la commande est « En attente de paiement » ; le dossier indique que le paiement n'est pas ouvert, qu'aucune échéance de réalisation ne court ; *Annuler la commande* est possible.
