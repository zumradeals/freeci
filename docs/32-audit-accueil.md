# 32 — Audit de l'accueil et de la vitrine (étape 3)

**Statut : constats factuels + propositions à valider.** Rien de ce qui touche au contenu (textes, hiérarchie, destinations) n'est modifié sans votre décision. Captures de référence prises à 360 et 1440 px sur le jeu de démonstration local.

## 1. Ce qui fonctionne
- Un message principal clair (« Un freelance pour votre prochain projet »), une recherche proéminente avec quatre recherches fréquentes, et une entrée alternative « Publier une mission ».
- Un bloc « Le suivi d'une commande » (bureau) qui explique les quatre étapes sans jargon.
- Services récents, catégories et « Comment ça marche » dans un ordre logique ; bande « Vous êtes freelance ? » en fin de page.
- Aucun débordement horizontal, pas d'erreur JavaScript, structure accessible (parcours navigateur `docs/29`).

## 2. Défauts corrigés tout de suite (lot 29)
1. **Téléphone : le bouton favori (44 px) recouvrait la miniature** de chaque carte de service (il s'ancrait à la miniature de 96 px). Il est désormais au coin haut droit de la carte ; le titre lui laisse la place. Bureau inchangé.
2. **Pied de page : mention interne visible de tous** (« Pages d'information : brouillons tant qu'elles ne sont pas adoptées »). Retirée : l'état des textes se voit dans l'administration (Pages légales).

## 3. Constats à arbitrer (propositions, pas décisions)
| # | Constat | Proposition | Dépend de |
|---|---|---|---|
| A1 | Le hero (bureau) et la section « Comment ça marche » disent presque la même chose deux fois. Sur téléphone, le bloc de suivi n'apparaît pas. | Garder le bloc de suivi dans le hero et réduire la section du bas à un lien « Le détail », ou l'inverse. | Votre choix de message |
| A2 | Aucune preuve de confiance (garanties, chiffres, avis). Les seuls faits disponibles sont des règles du produit : prix et délai figés à l'accord, travail démarré après paiement confirmé, rien validé à votre place. | Une bande de 3 garanties fondée **uniquement** sur ces règles. Pas de chiffres ni de témoignages tant qu'ils n'existent pas. | Votre validation des formulations |
| A3 | Les services récents s'affichent par 6 (4 + 2 sur bureau : rangée incomplète). Les données de démonstration se répètent (mêmes visuels). | Afficher 4 ou 8 services ; retirer les données de démonstration (carte Exploitation) avant la présentation. | Retrait de la démo (vous, après sauvegarde) |
| A4 | Catégories : huit tuiles sans nombre de services ni description. | Afficher le nombre de services par catégorie quand il est ≥ 1, et masquer les catégories vides. | Votre accord |
| A5 | « Devenir freelance » n'apparaît que dans la bande du bas ; l'en-tête public ne l'offre pas. | Ajouter une entrée « Devenir freelance » (en-tête et menu mobile). | Votre accord sur le libellé |
| A6 | Le hero n'a qu'une action principale (recherche) ; « Publier une mission » est un simple lien souligné. | Donner à « Publier une mission » le statut de second bouton. | Votre choix de hiérarchie |
| A7 | Menus publics : Services, Missions, Freelances, Comment ça marche. Pied de page : trois colonnes. Pas de page « À propos » ni de lien d'assistance visible. | Décider si une page « À propos » est nécessaire à l'ouverture et si l'assistance doit figurer dans l'en-tête. | Vous |
| A8 | La page publique d'une mission reste sur l'ancienne structure (marges en ligne, hors système de page). | L'aligner sur la fiche d'un service. | Aucun (je peux le faire) |

## 4. Prochaine étape proposée
Vous répondez point par point (par exemple « A1 : bloc de suivi dans le hero, section du bas réduite ; A2 : oui, voici mes formulations ; A3 : 8 ; A4 : oui ; A5 : "Devenir freelance" ; A6 : oui ; A7 : à voir plus tard »). J'implémente alors tout en un lot, avec captures avant/après et tests, sans toucher aux paiements ni aux règles métier. A8 peut partir dès maintenant.
