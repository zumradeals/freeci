# FreeCI — prototype statique V01

> **Prototype de conception, non destiné à la mise en ligne.** Il sert à **voir et évaluer l'apparence et les interactions locales** de FreeCI sur téléphone et ordinateur. Ce **n'est pas** le socle applicatif Laravel : aucun backend, aucun compte, aucune base de données, aucun paiement, aucune règle métier exécutable.
> Toutes les données (noms, prix, dates, références, fichiers) sont **fictives**. Une barre « Prototype V01 » est présente sur chaque écran.

Statut des choix : ce qui figure ici est une **proposition visuelle à examiner** (voir `docs/03-direction-visuelle.md`, D22). Seules les décisions du porteur (D01–D22, `docs/01-cadrage.md`) sont validées.

## Ouvrir le prototype

Aucune installation, aucun serveur : **ouvrir `index.html` dans un navigateur** (double-clic). Le prototype a été contrôlé en ouverture directe de fichier (`file://`).

Option avec un serveur local statique (équivalent) :

```bash
python3 -m http.server 8080 --directory design/prototype-v01
# puis http://localhost:8080/
```

La barre du haut (« **Écrans** ») liste tous les écrans ; sur mobile, ouvrir « Menu » donne accès au tiroir de navigation.

## Écrans

| Écran | Fichier | Rôle |
|---|---|---|
| 1. Accueil | `index.html` | Identité, promesse, recherche, catégories, prestations **en exemple**, deux parcours, suivi |
| 2. Détail d'un service | `service.html` | Périmètre, livrables, prix, délai, corrections, vendeur ; action « Demander cette prestation » (fenêtre de brief) |
| 3. Tableau de bord client | `tableau-de-bord.html` | Tâches et échéances d'abord, commandes ensuite, compteurs en dernier |
| 4. Commande livrée (client) | `commande.html` | Livraison à examiner, accord, brief, finances **distinctes**, historique |
| Variante : état vide | `tableau-de-bord-vide.html` | Nouveau compte : orientation vers une action utile |
| Variante : paiement en vérification | `commande-paiement-en-verification.html` | Même commande à l'étape du paiement ; **pas de bouton « Payer »** |

Seuls ces six écrans existent. Les liens vers d'autres écrans affichent un message « Simulation : cet écran n'est pas inclus dans le prototype V01 ». La commande ne présente que 5 des 7 onglets prévus (Livraisons, Accord, Brief, Finances, Historique) ; Messages et Avis viendront ensuite.

## Scénario de démonstration (identique d'un écran à l'autre)

| Élément | Valeur fictive |
|---|---|
| Client | **Fanta Bamba** (compte client fictif) |
| Freelance | **Kader Soro**, dessinateur DAO, Abidjan (profil fictif) |
| Prestation | *Convertir vos plans PDF en fichiers AutoCAD (DWG)* — **35 000 FCFA**, 5 jours, 2 corrections |
| Référence | **DEMO-26018** (paiement simulé : SIM-PAY-0007421) |
| Instant simulé | Tableau de bord et commande livrée : **jeudi 8 octobre 2026, 14:00** (heure d'Abidjan). Variante paiement : **vendredi 2 octobre 2026, 10:02** |
| Historique | 1 oct. demande (09:12) et acceptation (15:40) → 2 oct. paiement confirmé (10:05), départ (10:06), échéance 7 oct. → 5 oct. **report de 2 jours accepté**, échéance 9 oct. → 6 oct. livraison v1 → 7 oct. correction 1/2 → 8 oct. livraison v2 (**examen avant le 15 oct. 11:15**) |
| Autres éléments (tableau de bord) | DEMO-26021 (logo, 45 000 FCFA, à payer avant le 9 oct. 09:00) ; DEMO-26009 (fiches produit, 60 000 FCFA, en cours) ; mission DEMO-M-0412 (3 propositions) |

Aucun faux avis, aucun faux volume d'activité, aucun badge non justifié : les fiches de services portent « Aucun avis pour l'instant » et « Exemple ».

## Ce qui est simulé

- **Aucun bouton n'effectue une opération réelle.** Les actions affichent un message « **Simulation** » (bandeau en bas d'écran) ou, dans les fenêtres de confirmation, un état « Simulation : … n'a pas été modifié(e) ». L'état affiché ne change jamais (vérifié : après « Valider la livraison », la commande reste « Livrée »).
- Fichiers : les téléchargements sont simulés ; les illustrations sont des SVG dessinés pour la démonstration (aucune photographie, aucune personne).
- Paiement : référence `SIM-PAY-…`, balisé « Simulé » ; aucun prestataire, aucun moyen de paiement réel.
- Interactions réellement locales (JavaScript de présentation, ~130 lignes, sans bibliothèque) : tiroir de navigation, panneau « Écrans », onglets, fenêtres modales, galerie d'images, messages de simulation.

## Contenu du dossier

```text
design/prototype-v01/
├── index.html · service.html · tableau-de-bord.html · tableau-de-bord-vide.html
├── commande.html · commande-paiement-en-verification.html
├── assets/css/freeci.css          Jetons et composants (aucune dépendance)
├── assets/js/prototype.js         JavaScript de présentation
├── assets/img/*.svg               Illustrations de démonstration + monogramme
├── assets/fonts/                  Inter variable (latin, 48 Ko) + licence SIL OFL 1.1
├── captures/                      24 captures réelles (voir ci-dessous)
└── verification/                  Script de contrôle, résultats bruts, rapport
```

Les pages sont du HTML statique ; l'en-tête, le pied de page et le tiroir sont répétés dans chaque fichier (prototype, pas de modèle partagé).

## Captures réelles (Chromium, pleine page)

Téléphone **360 px** (rendu ×2) et ordinateur **1440 px** pour les six écrans ; **390, 768 et 1024 px** pour les quatre écrans principaux.

| Écran | 360 | 390 | 768 | 1024 | 1440 |
|---|---|---|---|---|---|
| Accueil | [png](captures/v01-accueil-360.png) | [png](captures/v01-accueil-390.png) | [png](captures/v01-accueil-768.png) | [png](captures/v01-accueil-1024.png) | [png](captures/v01-accueil-1440.png) |
| Détail d'un service | [png](captures/v01-service-360.png) | [png](captures/v01-service-390.png) | [png](captures/v01-service-768.png) | [png](captures/v01-service-1024.png) | [png](captures/v01-service-1440.png) |
| Tableau de bord client | [png](captures/v01-tableau-de-bord-360.png) | [png](captures/v01-tableau-de-bord-390.png) | [png](captures/v01-tableau-de-bord-768.png) | [png](captures/v01-tableau-de-bord-1024.png) | [png](captures/v01-tableau-de-bord-1440.png) |
| Commande livrée | [png](captures/v01-commande-livree-360.png) | [png](captures/v01-commande-livree-390.png) | [png](captures/v01-commande-livree-768.png) | [png](captures/v01-commande-livree-1024.png) | [png](captures/v01-commande-livree-1440.png) |
| Tableau de bord — état vide | [png](captures/v01-tableau-de-bord-vide-360.png) | — | — | — | [png](captures/v01-tableau-de-bord-vide-1440.png) |
| Commande — paiement en vérification | [png](captures/v01-commande-paiement-en-verification-360.png) | — | — | — | [png](captures/v01-commande-paiement-en-verification-1440.png) |

## Vérifications

Le détail, avec ce qui a été **effectivement testé** et ce qui **reste seulement prévu**, est dans [`verification/RAPPORT-VERIFICATION-V01.md`](verification/RAPPORT-VERIFICATION-V01.md). Résumé : 60 combinaisons page × largeur contrôlées automatiquement (30 en affichage normal, 30 en zoom 200 % **émulé**) sans constat final ; clavier, tiroir, onglets, fenêtres et contrastes contrôlés ; **aucun test sur appareil réel ni lecteur d'écran**.

## Ajustements par rapport aux documents (justifiés au rendu, D22)

| Ajustement | Document d'origine | Justification |
|---|---|---|
| Orange **plus vif** pour le point décoratif (`#F26B1D`), `accent-300` conservé sur fond marine | `03` §3.2 | Le point pâle (`#FFB48A`) manquait de présence sur fond clair à la taille du logo ; l'orange vif n'est jamais porteur de texte ni de statut |
| **Trame de points** très discrète dans le bandeau d'accueil (≈ 9 % de blanc) | `03` §2.4 (« marine uni, jamais de motif ») | Évoque le papier millimétré des plans ; casse l'aplat sans nuire à la lisibilité (contrastes mesurés : titre 14,0:1, texte 10,4:1, surtitre 8,1:1). **À garder ou retirer selon votre avis** |
| Carte « **Suivi d'une commande** » dans le bandeau, à la place de profils fictifs | `04` §2 | Montre l'utilité du produit sans fabriquer de personnes, d'avis ni de volumes |
| Galerie en **3:2** (et non 16:9) | `03` §2.5 | Les plans sont rendus sans rognage ; le 16:9 coupait les cotations |
| **Une seule action pleine** par tableau de bord (la tâche la plus urgente) ; « Publier une mission » et les autres tâches en boutons secondaires | `04` §0.1, §6 | Respecte « une action primaire par vue » ; contrepartie : l'exemple principal (livraison) apparaît en 3ᵉ position car classé par échéance |
| Catégories **empilées** (icône au-dessus) sur téléphone | `04` §2 | Les libellés longs se coupaient mal à côté de l'icône à 360 px |
| **Fil d'Ariane réduit à un lien de retour** sur téléphone | `03` §6.1 | Le fil complet passait sur 3 lignes |
| Barre d'achat fixe **seulement** sur le détail de service | `03` §6.1 | Aucune barre d'onglets fixe ailleurs : rien ne masque le contenu |
| Hauteur de cible : **44 px partout**, y compris les liens de pied de page | `03` §5.2 | Exigence D10 |
| Inter **embarquée en fichier** (latin, variable) | `03` §4 | Rendu typographique fiable hors ligne ; **fichier de ressource, pas une dépendance** ; licence SIL OFL 1.1 jointe |

## Choix visuels nécessitant votre avis

1. **Identité** : monogramme « F• » + « Free**CI** » avec « CI » en orange foncé — reconnaissable ou trop discret ?
2. **Trame de points** du bandeau d'accueil : garder, atténuer ou supprimer ?
3. **Carte « Suivi d'une commande »** dans le bandeau d'accueil plutôt que des profils de freelances.
4. **Hiérarchie du tableau de bord** : tâche la plus urgente en bouton plein, les autres en secondaire ; ordre par échéance.
5. **Onglets de la commande** en pastilles (actif = marine plein) plutôt que soulignés.
6. **Ton et densité** : titres 700 à interlettrage serré, libellés explicites (« Examiner la livraison », « Demander une correction (1 sur 2 restante) »), blocs « Ce qui se passera ».
