# 39 — Résultats de la sonde du bac à sable Genius Pay (2026-10-09)

Source : rapport de Claude sur le VPS (prompt `docs/38`), commit déployé `43ecdc3`. Un seul paiement de sonde (commande de test FC-2610-00004, 18 000 XOF simulés), un seul rejeu (second remboursement). Aucun mode live, aucun secret affiché.

## Constats établis

| Sujet | Constat |
|---|---|
| Règlement d'un paiement sandbox | **Conclusion A** : deux requêtes HTTP suffisent. `GET` du lien de checkout (cookie de session + jeton CSRF de la balise `csrf-token`), puis `POST {checkout}/sandbox/process` avec `{"scenario":"success","gateway":"orange_money"}`. Aucun captcha, aucun SMS, aucune balise `<form>`. **Mécanisme hors documentation** : trouvé en lisant la page ; il peut changer sans préavis. Scénarios proposés par la page : `success`, `failure`, `timeout`, `pending` ; passerelles : orange_money, mtn_momo, moov_money, wave, visa, mastercard. Seul `success` avec `orange_money` a été essayé. |
| Confirmation | Le **webhook** `payment.success` est arrivé mais a été **refusé (401)** ; le **rapprochement planifié** a confirmé le paiement environ 20 s après le règlement. |
| Remboursement total par API | Réponse en ~0,5 s ; **statut `refunded` effectif en moins de 10 s**, stable ; **second appel idempotent** (même résultat, même statut). Le prestataire a envoyé `payment.refunded` (refusé 401 par FreeCI). |
| Référence de remboursement | **Absente** de la réponse (`refund_reference_missing`) : FreeCI classe donc le résultat « incertain » alors que le remboursement a eu lieu. |
| Effet côté FreeCI | Rien : le remboursement fait hors circuit FreeCI ne crée ni opération ni écriture ; le paiement reste « confirmé » (aucun dossier de rapprochement au moment de la sonde). |
| Démarrage du travail | La commande est passée **en cours** dès paiement confirmé et brief complet (`StartOrderIfReady`) : comportement voulu, à connaître pour la recette. |

## Défauts corrigés (lot 70)

1. **Webhooks du simulateur refusés** : le corps réel est **plat** (`data.reference`, `data.status`… sans `data.transaction`), alors que le code n'acceptait que la forme documentée. Les deux formes sont maintenant acceptées. Un corps **authentifié mais inexploitable** répond désormais **400** (`invalid_body`), plus **401** (réservé aux signatures fausses).
2. **Référence de remboursement** : le comportement prudent est conservé (pas de « confirmé » sans preuve de rattachement). Les **noms des champs** renvoyés (jamais les valeurs) sont journalisés (`geniuspay.refund_reference_missing`) pour savoir si la référence existe sous un autre nom.
3. **Fuseau de session PostgreSQL** : la sonde a constaté une session en Europe/Berlin alors que l'application est en UTC. Laravel écrit des dates sans décalage horaire ; PostgreSQL les interprétait alors en heure de Berlin (instants stockés décalés de 2 h). La connexion impose maintenant `timezone = UTC` (`DB_TIMEZONE`, défaut UTC). **Les lignes déjà écrites gardent leur décalage** : sans conséquence tant que l'ouverture n'a pas eu lieu (données de test) ; à purger avec les données de démonstration.

## Restent inconnus (à ne pas supposer)

- Corps brut des réponses de remboursement ; forme des webhooks des **paiements réels** (plate comme le simulateur, ou documentée) ; reprise des webhooks refusés par le prestataire.
- Comportement des scénarios `failure`, `timeout`, `pending`, et des passerelles par carte.
- Remboursement **partiel** en bac à sable ; existence d'un **paiement sortant** (reversement) : question à poser au support Genius Pay (§8.4 de la recette).
- Redirection `https://geniuspay.ci/docs` → `http://geniuspay.ci:8000/docs/` (côté prestataire).

## Recette automatisée du 2026-10-09 (`freeci:recette:finance`, commit `1e8dc22`)

Bac à sable, six scénarios A à F, six paiements réglés par le simulateur et confirmés sans intervention. Tous les contrôles sont RÉUSSIS, sauf un point « à voir » : le remboursement total par API (B) est resté « à vérifier » faute de référence de remboursement (opération `OP-2610-00001`). Décision du porteur : confirmation automatique sur preuve du prestataire (lot 72). Restent manuels : écrans (B10, D3/D11, F5–F8), remboursement partiel chez le prestataire (C7), question du paiement sortant (§8.4), signature du §10.
