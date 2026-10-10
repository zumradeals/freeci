# F-14 — Cadrage du parrainage et des codes promotionnels

> **Statut : cadrage validé par le porteur sur les 4 points du §4 (niveau A + B ; parrain et filleul récompensés ; 3 commandes à 0 % chacun, 10 filleuls qualifiés au plus par parrain ; codes promotionnels de campagne inclus, même mécanisme).** Les autres règles (§3) et celles de la maquette restent des propositions tant qu'elles ne sont pas approuvées avec la maquette. Aucun code n'est écrit.

## 1. Point de départ : ce que le moteur financier fait aujourd'hui

- Le **client paie le prix de l'accord**, sans remise ni frais ajoutés. Ce prix, le délai et le **taux de commission** (aujourd'hui 10 %, proposition non validée) sont **figés dans l'accord** à la création de la commande (`order_agreements.commission_bp`).
- Le taux est lu à **trois endroits seulement** : demande de service, sélection d'une proposition, offre personnalisée. Les jalons (F-13) reprennent l'accord de la sélection.
- Commission, remboursements, reversements, rapprochements et exports (F-17) partent tous de ce taux figé et du montant payé. **Aucune notion de remise n'existe.**
- Il n'y a ni parrainage ni code promotionnel dans le code.

## 2. Trois niveaux possibles, du moins au plus risqué

| Niveau | Contenu | Effet sur l'argent | Risque |
|---|---|---|---|
| **A. Parrainage sans récompense financière** | Chaque compte a un lien/code de parrainage ; on voit ses filleuls (inscrits, actifs, premiers travaux validés) ; remerciement symbolique (mention, compteur) | Aucun | Très faible |
| **B. Commission offerte ou réduite** | Une récompense = FreeCI **renonce à tout ou partie de SA commission** sur N commandes d'un freelance. Le client paie le même prix ; le freelance reçoit plus ; seule la **part de FreeCI** baisse | Le taux figé de l'accord vaut 0 % (ou moins de 10 %) : **aucun changement du paiement, du remboursement ni du reversement**, ils lisent déjà ce taux | Faible : un seul point central de décision du taux, remplaçant les trois lectures actuelles |
| **C. Remise sur le prix payé par le client** | Un code réduit le montant que le client paie | Le montant payé ≠ prix de l'accord : à redéfinir pour le paiement, les remboursements, la base de commission, le reversement, la facture, la réconciliation Genius Pay | **Élevé**, et inutile tant que B suffit : à écarter pour ce lot |

Niveau D (crédit, portefeuille, retrait de bonus) : exclu — il fait de FreeCI un détenteur de valeur monétaire (questions réglementaires), et rien dans le plan ne le demande.

## 3. Recommandation : A + B

**Pourquoi.** La récompense est entièrement supportée par la part de FreeCI, donc elle ne peut jamais priver un freelance de ce qu'on lui a promis, et elle réutilise tel quel un mécanisme déjà figé et audité. Elle évite la remise client (C) et ses effets sur tous les flux financiers.

**Règles proposées (à valider) :**

- **R1 — Lien et code.** Chaque compte a un code de parrainage stable (non devinable, 8 caractères), partageable par lien d'inscription `…/inscription?parrain=CODE`. Le code est mémorisé jusqu'à l'inscription (cookie de 30 jours), puis rattaché au nouveau compte **une seule fois, à l'inscription, jamais modifiable ensuite**.
- **R2 — Qualification.** Une récompense n'est déclenchée que par un **fait réel** : la première commande **réelle** (environnement `live`) du filleul, **payée, livrée et validée par le client**. Les commandes de test et sandbox ne comptent jamais.
- **R3 — Récompense (proposition).** Au moment de la qualification : le **filleul freelance** obtient 0 % de commission sur ses 3 commandes réelles suivantes, et le **parrain** obtient 0 % de commission sur 3 de ses commandes réelles futures (une récompense par filleul qualifié, plafonnée). Valeurs provisoires et configurables.
- **R4 — Application.** La commission offerte s'applique à la **création d'une commande** (le taux 0 % est figé dans l'accord, comme aujourd'hui) ; les commandes déjà créées ne changent jamais. Un compteur « commandes offertes restantes » est décrémenté au moment où la commande est **payée** (pas avant), et rendu si la commande expire ou est annulée avant paiement.
- **R5 — Anti-abus.** Auto-parrainage impossible (même compte) ; pas de parrainage entre deux comptes partageant la même adresse e-mail vérifiée ; un compte ne peut être parrainé qu'une fois ; **plafond de récompenses par parrain** (ex. 10 filleuls qualifiés) ; récompense suspendue si le compte du parrain ou du filleul est suspendu ; filleul et parrain ne peuvent pas être client et freelance d'une même commande qualifiante ; **toute attribution est journalisée** et révocable par un administrateur avec motif.
- **R6 — Codes promotionnels (campagnes).** Même mécanisme B, mais créé par un administrateur : code libre (ex. `LANCEMENT`), commission offerte pour N commandes d'un freelance qui le saisit à l'activation de son espace freelance, dates de début/fin, nombre maximal d'utilisations, un seul code promotionnel par compte. Cumul avec un parrainage : **non** (le plus favorable au freelance s'applique, jamais les deux).
- **R7 — Transparence.** Le freelance voit sur chaque accord le taux appliqué et la raison (« parrainage », « code LANCEMENT ») ; le client ne voit aucun changement ; l'administration voit tous les attributs, les décomptes et les révocations ; statistiques F-17 : commissions réellement perçues et commissions offertes, séparées.
- **R8 — Réversibilité.** Désactiver le parrainage ou un code est immédiat pour l'avenir ; les accords déjà figés ne changent jamais.

## 4. Points à ne pas inventer (décision du porteur)

1. Périmètre : A seul, **A + B (recommandé)**, ou A + B + C.
2. Qui est récompensé : filleul, parrain, ou les deux.
3. Ampleur : nombre de commandes à commission offerte (proposition : 3 chacun) et plafond de filleuls par parrain.
4. Codes promotionnels de campagne (R6) : oui ou non dans ce lot.
5. Dépendance : **F-03 (paiement réel) n'est pas disponible** ; le chantier sera construit et testé en **sandbox** seulement (les récompenses ne se déclenchent que sur des commandes réelles, donc rien ne sera attribué avant l'ouverture).

## 5. Effets sur le code (indicatif, avec A + B)

Tables : `referral_codes`, `referrals` (filleul unique), `commission_grants` (parrainage ou campagne, solde de commandes offertes, journal) et `promo_campaigns` ; un **point central** `CommissionTerms::forNewOrder` remplaçant les trois lectures du taux ; un déclencheur à la validation d'une commande réelle ; pages « Parrainage » (compte), « Campagnes » (administration), accord enrichi ; tests des invariants (auto-parrainage, sandbox exclu, une récompense par filleul, solde jamais négatif, taux figé inchangé après coup). Aucune modification des tables de paiement, de remboursement ni de reversement.

## 6. Étapes après décision

1. Le porteur répond aux points du §4.
2. Maquette (page de parrainage, accord avec taux, administration des campagnes), puis approbation.
3. Implémentation en sandbox, tests, mise à jour de docs/36 et docs/19.
