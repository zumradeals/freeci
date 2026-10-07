# 33 — Inventaire de l'administration (étape 4)

**Principe retenu (vos consignes) :** l'administration est la salle de contrôle du projet ; rien de ce qui change au quotidien ne doit exiger une modification du code. Les secrets restent côté serveur, protégés (chiffrés, en écriture seule). L'accès est protégé par la double authentification et la reconfirmation d'identité pour les actes sensibles.

## 1. Ce qui est modifiable aujourd'hui dans l'administration
| Domaine | Où | Notes |
|---|---|---|
| Commission FreeCI | Paramètres › Commission | Statut provisoire/approuvé, motif, historique. |
| Délais (réponse, paiement, examen, report, sélection de mission, avis, fermeture de compte) | Paramètres › Délais | Idem. |
| Prix minimum et maximum d'un service | Paramètres › Prix | |
| Paiement (mode sandbox/live, ouverture, clés Genius Pay, hôtes autorisés) | Paramètres › Paiement | Passage au réel protégé par une phrase et des confirmations ; clés chiffrées. |
| Courrier (SMTP) | Paramètres › Courrier | Mot de passe chiffré ; courriel de test. |
| Fichiers (antivirus, tailles, nombres) | Paramètres › Fichiers | |
| Limites du catalogue, des missions, des échanges, de la sécurité | Paramètres (quatre groupes) | |
| Identité et contact de l'exploitant | Paramètres › Exploitant | Alimente mentions légales, confidentialité, contact. |
| **Textes de l'accueil et du pied de page** (accroche, titre, sous-titre, exemple de recherche, recherches fréquentes, phrase avant « Publier une mission », bande freelance, phrase du pied de page) | Paramètres › Accueil et vitrine | **Nouveau (lot 31).** Champ vide = texte de départ. |
| Visibilité aux moteurs de recherche, notifications par courriel, sauvegardes | Paramètres › Site et sauvegardes | |
| Pages légales et d'information (fonctionnement, aide, contact, conditions, confidentialité, mentions légales) | Pages légales | Brouillon, aperçu, publication comme texte adopté, retrait, historique. |
| **Catégories** (création, nom, icône, ordre, archivage, suppression si inutilisée) | Catégories | **Nouveau (lot 31).** |
| Modération des services, missions, avis ; utilisateurs ; assistance ; opérations financières ; rapprochements | Menus correspondants | Déjà en place. |
| Retrait des données de démonstration | Exploitation | Fait de votre côté. |

## 2. Ce qui reste volontairement dans le code ou la console
| Élément | Pourquoi | Piste |
|---|---|---|
| Habilitations administrateur et assistance (octroi, révocation) | Sécurité : accorder le pouvoir suprême ne doit pas passer par l'interface web. | Console uniquement ; à documenter dans le guide d'exploitation. |
| Secrets de serveur (clé d'application, base de données) | Jamais dans l'interface. | `.env` sur le VPS. |
| Structure des menus, destinations des boutons, ordre des sections de l'accueil | Choix de produit stables ; un bouton mal réglé casserait un parcours. | À rouvrir si vous voulez des bandeaux éditables. |
| Étapes du suivi de commande (hero), « Comment ça marche » téléphone, états vides | Textes produit alignés sur les règles métier. | Rester dans le code, relus avec vous. |
| Gabarits de courriels de notification | Lien avec les règles métier. | Éditables plus tard si besoin réel. |
| Sujets du formulaire d'assistance, catégories de signalement et de modération des avis | Alignés sur les procédures internes. | Idem. |
| Compétences (liste libre côté freelance) | Pas de référentiel (P5 du registre). | Après usage réel. |
| Pays, devise, nom du service et logo | Identité du produit. | Hors périmètre. |

## 3. Constat important découvert pendant l'inventaire
**Les catégories n'existaient que dans le jeu de démonstration** : aucune migration ne les crée et aucune page d'administration ne les gérait. Sur un serveur neuf, personne n'aurait pu créer un service ni une mission. Le retrait des données de démonstration **ne supprime pas les catégories** (elles ne sont pas marquées « démonstration ») : vérifiez la page **Catégories** après la mise à jour ; si elle est vide, créez-en au moins une avant l'ouverture. Les services et missions existants gardent leur catégorie, même archivée.

## 4. À valider ou à décider (rien n'est supposé)
1. Liste initiale des catégories (noms, ordre, icônes) : à vous de la valider ; les huit actuelles viennent du jeu de démonstration.
2. Souhaitez-vous des bandeaux d'accueil éditables (annonce, mise en avant d'une catégorie) ? Non fait.
3. Souhaitez-vous rendre les gabarits de courriels éditables avant l'ouverture ?

## 5. Décisions du porteur et réalisation (lot 32)
| Décision | Réalisation |
|---|---|
| Liste initiale des catégories : validée (les huit actuelles) | Posée par la migration en **production seulement** et **seulement si la table est vide** ; ensuite gérée dans Catégories. Rien n'est écrasé. |
| Bandeaux d'accueil éditables : oui | Bandeau d'annonce (Paramètres › Accueil et vitrine : activation, texte, destination choisie dans une liste de pages du site, texte du bouton) ; catégorie « à la une » (une seule à la fois, depuis Catégories). |
| Gabarits de courriels éditables : oui | Paramètres › Courriels : début de l'objet, formule d'ouverture, signature, phrases des courriels de confirmation d'adresse, de notification et d'avis de sécurité. Champ vide = texte de départ. Les liens, la durée de validité du lien (60 minutes) et l'absence de message privé dans les courriels restent fixés par l'application. |
| « Les deux points de l'inventaire dans l'espace admin » | Équipe d'assistance : page dédiée (accorder / retirer l'habilitation « support », motif obligatoire, identité reconfirmée, journalisé). **L'habilitation d'administrateur reste hors interface** (voir ci-dessous). Structure des menus : à préciser. |

### Pourquoi l'habilitation d'administrateur ne passe pas par l'interface
Votre décision D39 : un seul administrateur gère les opérations financières. Si l'interface pouvait créer un administrateur, un compte administrateur compromis pourrait créer ses propres complices. Accorder ce pouvoir se fait donc en console, à la demande du porteur. Si vous voulez malgré tout l'avoir dans l'interface, il faudra une règle explicite (par exemple double validation) : à décider par vous.

## 6. Maquette de la page d'accueil
`docs/maquettes/accueil-moderne.html` (aussi publiée comme page privée). Proposition, rien n'est branché : à valider avant toute mise en œuvre.
