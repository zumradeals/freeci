# Lot 5 — profil freelance et gestion des services

Statut : **projet de travail**. Les bornes numériques (`config/freeci.php`, clé `catalog`) sont des **paramètres provisoires** (docs/04 §4.2), non des règles commerciales approuvées. Hors périmètre : missions, messagerie, avis, paiements réels, administration web de modération (prérequis de sécurité à traiter dans un lot dédié).

## 1. Profil freelance
- **Public** (page `/freelances/{adresse}`, une fois publié) : nom affiché, activité, ville, présentation (50 à 1500 caractères), compétences (1 à 10), services **effectivement publiés**. **Privé** : adresse e-mail et compte, jamais affichés (la page d'édition les sépare visuellement).
- **Publication** par le propriétaire quand le profil est complet (activité, présentation, ville, ≥ 1 compétence). C'est une visibilité, pas une vérification. Adresse publique générée une fois (stable). Les profils qui portaient déjà un service publié sont restés publics (migration).
- **Aucun badge, aucune vérification, aucun rôle ni habilitation ne s'attribue** : l'enregistrement du profil est une liste blanche de champs ; tout autre champ envoyé est ignoré (testé : `verified`, `badge`, `role`, `capability`, `published_at`, `slug`, `user_id`…). Pas de coordonnées privées dans les textes publics (détection e-mail / téléphone).

## 2. Services versionnés
- `services` = **version publiée** (ce que voit le public). Les modifications vivent dans `service_versions` (brouillon → en contrôle → publiée / à corriger → remplacée). Une version soumise, publiée ou remplacée est **immuable** (déclencheur PostgreSQL) ; une seule version « ouverte » et une seule « publiée » par service.
- Modifier un service publié = **nouvelle version** (copie) ; la version publiée reste en ligne jusqu'à l'approbation. **Aucun accord de commande n'est modifié** (instantanés en ajout seul) : testé avec une commande en cours après changement de prix, retrait puis suspension.
- Contenu : titre, catégorie, résumé, description du périmètre, prix FCFA, délai, corrections incluses, livrables, exclusions, éléments à fournir (= questions du brief), **mode de livraison** (au moins un fichier — défaut — ou message seul, figé dans chaque accord), brief avec fichier exigé ou non, images.
- Brouillon : contenu incomplet admis (jamais mal formé). Soumission : tout est vérifié et listé en clair sur la page de confirmation ; profil publié exigé. Sauvegarde protégée contre les écrans périmés (`revision_no`).
- **Retrait du catalogue** par le propriétaire (`archived`) : plus proposé ni commandable (410), commandes et demandes en cours inchangées ; il peut le remettre en ligne. Un service **suspendu par la modération** ne se remet pas en ligne soi-même.
- Adresse publique du service fixée à la première publication (`brouillon-…` avant), puis stable.
- Services existants : une version initiale est créée à la migration (et à la volée si besoin) ; **aucun contenu n'est modifié**. Le défaut « au moins un fichier » s'applique aux services existants, sauf le service de recette « mise en plan » (message seul) ; les accords existants ne changent pas.

## 3. Médias
Images JPG/PNG/WebP, 5 Mo, ≥ 400 px de large, ≤ 16 millions de pixels, 6 par service. Chaîne : taille → extension → type réel (finfo) → image décodable → dimensions → **réencodage complet en WebP** (grande taille + vignette ; l'original n'est jamais conservé) → disque **privé** (`private_files`, hors `public/`). Accès uniquement par `/medias/{id}/{large|card}` : public si l'image figure dans la **version publiée** d'un service en ligne, sinon propriétaire seulement, sinon 404. Texte alternatif obligatoire. `freeci:media:prune` (quotidien) supprime les images qu'aucune version ne référence. Prérequis : extension PHP **GD avec WebP** (`php8.3-gd`) ; sans elle le dépôt d'images est désactivé et signalé (le service reste publiable sans image) ; contrôle ajouté à `freeci:preflight`.

## 4. Modération provisoire (console, sans interface web)
Chaque commande exige `--by=<courriel d'un administrateur>` (habilitation en vigueur, revérifiée par l'action), refuse la modération de son propre service, demande confirmation (ou `--yes`) et écrit l'historique (acteur, « modération (console) », motif).
- `freeci:moderation:queue [--show=<version>]` : file d'attente / détail.
- `freeci:moderation:approve <version> --by=…` : la version devient publiée.
- `freeci:moderation:refuse <version> --by=… --reason="…"` : motif obligatoire (10 à 1000 caractères), visible du freelance ; la version publiée éventuelle reste en ligne.
- `freeci:moderation:suspend <service> --by=… --reason="…"` / `freeci:moderation:reinstate <service> --by=…`.

## 5. Limites
Pas d'interface web de modération ni de notification du freelance (il voit l'état et le motif dans « Mes services » et son tableau de bord) ; pas de portfolio ; pas de comparaison entre versions ; compétences en liste libre (pas de référentiel) ; pas de suppression de service ; ajouter/retirer une image recharge la page.
