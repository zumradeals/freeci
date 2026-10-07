# Consolidation du moteur — première tranche

## Corrections

- Les synthèses freelance et client ne s’arrêtent plus aux 100 dernières commandes. L’historique complet est lu par lots de 100 ; seules 20 lignes de détail sont conservées pour la page demandée. Tri déterministe par date puis identifiant. Les calculs financiers existants restent la source des montants.
- Les totaux ne dépendent pas de la page affichée. Côté client, les anciennes commandes `legacy` ne sont plus agrégées avec les paiements réels. Elles restent visibles et identifiées dans le détail.
- Le contrôle de disponibilité de ClamAV analyse un contenu inoffensif au lieu de lire seulement sa version. Les exceptions de processus, dont les timeouts, deviennent un état « indisponible » ; aucun fichier n’est déclaré propre sur erreur. Le résultat de disponibilité reste en cache 60 secondes.
- `freeci:files:check` retourne un code d’échec si le scanner est indisponible ou si l’écriture privée échoue. Le diagnostic précise que les limites PHP CLI peuvent différer de celles de PHP-FPM.

Aucune migration, variable, écriture financière, modification de l’accord ou activation de paiement réel. Le sandbox reste inchangé. Aucun déploiement VPS effectué par l’agent.

## Vérification réalisée

- Syntaxe des sept fichiers PHP concernés vérifiée avec PHP WASM.
- Compilation des vues Blade et build Vite réussis ; diff sans erreur d’espacement.
- Tests ajoutés : historique de 101 commandes, invariance des totaux entre pages 1 et 6, séparation test/réel, absence de recouvrement entre pages, isolation d’un tiers ; fausse disponibilité d’un scanner dont seule la version répond ; échec de la commande de diagnostic si scanner absent.
- **Exécution confirmée ensuite** sur PostgreSQL local, avec la suite complète : 300 tests sur 300 passent, dont les trois tests ajoutés (101 commandes, disponibilité ClamAV, échec de `freeci:files:check`). Mise en forme Pint corrigée sur `FinanceOperationsTest`.

## Parcours métier : état de la revue

`DeliveryCycleTest` couvre le cycle livraison/correction/validation, les corrections épuisées, la double soumission et les autorisations. `SupportTest` couvre les dossiers de support. `FinanceOperationsTest` couvre les plafonds, les doubles engagements de fonds, les remboursements ambigus, les reprises sans renvoi automatique et l’administrateur unique. Ces suites existaient ; elles ont été relues, pas réexécutées pour cette livraison.

À exécuter dans un environnement de test PostgreSQL isolé, jamais avec la base du VPS en production : `php artisan test --filter='FinanceOperationsTest|ScannerReadinessTest|DeliveryCycleTest|SupportTest|OrderFilesTest|OrderRequestFlowTest|PaymentModesTest|GeniusPayTest'`. Aucune preuve nouvelle d’un remboursement chez Genius Pay n’est revendiquée.

## 504 : diagnostic ouvert

Preuve reçue du VPS : le 6 octobre à 23:55:24 +0200, nginx a expiré en attendant l’en-tête de réponse PHP-FPM pour POST `/livewire-57e30ddc/update`, depuis `/freelance/revenus`. Les compteurs se rafraîchissent toutes les 30 secondes. Le code effectue aussi des analyses de fichiers après réponse dans les workers web : piste à examiner, pas une cause démontrée du 504.

Prochaine preuve : `/var/log/php8.3-fpm-freeci-slow.log` lors d’un incident, avec l’heure correspondante. Ne pas augmenter les délais ni masquer les erreurs pour conclure à une résolution.

## Limite de performance explicite

La lecture par lots borne la mémoire mais pas le nombre de requêtes : les calculs existants interrogent les fonds et l’éligibilité de chaque commande. Mesurer puis regrouper ces lectures pour les gros historiques, avec comparaison des résultats aux calculs de référence. Cette tranche corrige les totaux tronqués ; elle ne prétend pas résoudre la montée en charge ni garantir un instantané transactionnel entre plusieurs requêtes concurrentes.

## Contrôle après déploiement

- Vérifier les pages Paiements et Revenus et leurs montants sur les comptes existants.
- Exécuter `php8.3 artisan freeci:files:check` depuis le dossier du projet sous l’utilisateur freeci ; vérifier le scanner réel.
- Conserver le suivi du slowlog. Les validations financières réelles et l’ouverture du live restent hors de ce changement.

## 503 observé sur l'accueil (7 octobre)

La fenêtre affichait « 503 | Service Unavailable » : c'est la page minimale de Laravel, pas celle de nginx. Aucun `abort(503)` n'existe dans le code ; l'hypothèse la plus probable est le **mode maintenance** activé par `deploy/update.sh` (`artisan down` … `artisan up`) pendant une mise à jour, alors qu'une page restée ouverte interrogeait le serveur (compteurs toutes les 30 s). Cause non confirmée : à vérifier sur le VPS (`ls storage/framework/down` absent = site en ligne ; l'heure de la mise à jour dans le journal de déploiement).
Si une mise à jour échoue, le site reste volontairement en maintenance (voir `docs/07`) : un 503 durable doit être lu ainsi.
Corrigé côté présentation (lot 28) : page 503 en français ; une indisponibilité passagère (502/503/504) d'une requête Livewire n'ouvre plus la fenêtre technique, un message discret s'affiche et les compteurs réessaient. Cela ne remplace pas le diagnostic du 504 du 6 octobre (cause distincte : expiration nginx).
