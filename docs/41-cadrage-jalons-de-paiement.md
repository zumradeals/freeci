# F-13 — Cadrage des jalons de paiement (Q6)

> **Statut : projet de travail, non adopté.** Ce document cadre les règles financières avant toute maquette (docs/36, Q6). Aucune règle ci-dessous n'est une décision de GAMAD tant que le porteur ne l'a pas validée. Aucun code n'est écrit.

## 1. Point de départ : ce que le moteur fait aujourd'hui

- **Une commande = un accord figé = un paiement confirmé = une série de livraisons = un reversement.** Le prix, la commission (10 %, proposition non validée) et le délai sont copiés dans l'accord au moment de la sélection.
- Une mission n'a **qu'une commande vivante** (index `order_mission_live_uq`) ; elle passe « réservée » à la sélection puis « attribuée » au paiement confirmé.
- Remboursements, litiges, retenues de reversement, rapprochements et exports financiers sont tous attachés à **la commande**.
- Les paiements réels ne sont pas encore ouverts (sandbox seulement). Le circuit des fonds (séquestre, Q03) n'est pas tranché.

## 2. Deux façons de bâtir les jalons

| | **A. Un jalon = une commande ordinaire, enchaînée** (recommandée) | **B. Plusieurs jalons dans une seule commande** |
|---|---|---|
| Principe | La proposition retenue contient un plan de N jalons. La **première commande** est créée à la sélection ; la suivante n'est créée qu'à la validation de la précédente. Chaque jalon a son accord, son paiement, ses livraisons, sa commission, son reversement. | Une commande unique porte N paiements, N livraisons, N reversements. |
| Moteur financier | **Inchangé** : aucune règle de paiement, de remboursement, de litige ou de reversement n'est réécrite. | Réécriture du paiement, de la livraison, du litige et du reversement. |
| Risque | Faible : on ajoute un lien « groupe de jalons » et on adapte l'état de la mission. | Élevé : touche l'argent de toutes les commandes existantes. |
| Remboursement / litige | Par jalon, comme aujourd'hui. Un litige sur un jalon ne bloque pas les jalons déjà validés. | À redéfinir (quelle part est contestée ?). |

## 3. Règles proposées (avec A)

**R1 — Qui définit le plan.** Le freelance, **dans sa proposition** (versionnée comme aujourd'hui). Le client le voit à la comparaison et le retient avec la proposition : il ne peut pas le modifier, il peut demander une révision. Après sélection, **le plan est figé**.

**R2 — Bornes (provisoires, configurables).** 2 à 5 jalons ; chaque jalon ≥ 10 000 FCFA ; la somme des jalons = le prix total de la proposition (au franc près, vérifié par le serveur) ; chaque jalon a un titre (3 à 80 caractères), un périmètre (au moins 30 caractères) et un délai propre (1 à 180 jours).

**R3 — Financement séquentiel.** Le client ne paie que **le jalon en cours**. Le jalon suivant n'est ouvert qu'après la **validation** du précédent. Aucune avance sur les jalons futurs : cela évite d'avoir à trancher le séquestre (Q03) pour ce chantier.

**R4 — Commission.** Même taux que l'accord de la mission, **calculé et arrondi jalon par jalon** (la somme des parts peut différer de quelques francs d'un calcul global : c'est affiché et assumé).

**R5 — Fin de mission.** La mission devient « attribuée » au premier paiement confirmé (comme aujourd'hui), et « terminée » à la validation du dernier jalon. Un **avis unique** est déposé à la fin du dernier jalon pour l'ensemble.

**R6 — Arrêt en cours de route.** Les jalons non encore ouverts ne sont jamais dus. Le client peut **arrêter le plan** après un jalon validé (les jalons restants sont annulés sans frais ni paiement) ; le freelance peut le proposer par message. Un désaccord relève du support, jalon par jalon.

**R7 — Ce qui ne change pas.** Délais de paiement (24 h), délais d'examen (sans libération automatique), corrections par jalon, double lecture serveur des montants, mode sandbox/réel fixé à la création de chaque commande.

## 4. Points à ne pas inventer (décision du porteur)

1. Architecture A ou B (recommandation : **A**).
2. Financement séquentiel (R3) ou avance de tous les jalons (suppose Q03, séquestre).
3. Bornes de R2 (2 à 5 jalons, 10 000 FCFA minimum).
4. Qui peut arrêter le plan (R6) et à quelles conditions.
5. Départ du chantier : **F-03 (paiement réel) n'est pas disponible** ; F-13 peut être construit et testé en **sandbox** seulement, et ne sera exploitable qu'à l'ouverture des paiements réels.

## 5. Effets sur le code (indicatif, avec A)

Nouvelle table de jalons liée à la version de proposition (immuable une fois retenue) ; lien `milestone_group` / rang sur la commande ; ajustement de l'index « une commande vivante par mission » en « une commande vivante par jalon en cours » ; transitions de l'état de la mission ; ajout au formulaire de proposition, à la page de comparaison, à la page commande et à l'espace revenus ; tests des invariants financiers (somme, séquence, annulation). Aucune modification des tables `payments`, `financial_operations`, remboursements ni reversements.

## 6. Étapes après décision

1. Le porteur répond aux points du §4.
2. Maquette (proposition avec jalons, comparaison, suivi côté client et freelance) puis approbation.
3. Implémentation en sandbox, tests, mise à jour de docs/19 et docs/36.
