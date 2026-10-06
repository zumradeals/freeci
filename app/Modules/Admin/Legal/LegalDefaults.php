<?php

namespace App\Modules\Admin\Legal;

/**
 * Textes de départ des pages d'information (projets de travail rédigés à partir des règles réellement appliquées par la plateforme).
 * Ils ne sont JAMAIS présentés comme adoptés : une page n'est « adoptée » que lorsque l'administrateur publie sa propre version.
 * Jetons remplacés à l'affichage : {exploitant} {adresse} {immatriculation} {directeur} {hebergeur} {contact} (« à renseigner » si vide).
 */
final class LegalDefaults
{
    public const PAGES = [
        'fonctionnement' => 'Comment fonctionne FreeCI',
        'aide' => 'Aide',
        'contact' => 'Contact',
        'conditions' => 'Conditions d’utilisation',
        'confidentialite' => 'Confidentialité',
        'mentions-legales' => 'Mentions légales',
    ];

    public static function body(string $slug): string
    {
        return match ($slug) {
            'fonctionnement' => <<<'MD'
FreeCI met en relation des clients et des freelances indépendants en Côte d’Ivoire. Chaque freelance reste indépendant : FreeCI est un espace de mise en relation et de suivi, pas l’employeur des freelances.

## Le parcours d’une commande

1. **Choisir.** Parcourez les services (prix et délai annoncés), les freelances ou les missions publiées.
2. **Demander.** Le client envoie une demande ; le freelance l’accepte ou la refuse (avec son motif). Les conditions acceptées sont figées dans l’accord de la commande.
3. **Payer.** La commande démarre lorsque le paiement est confirmé côté serveur et que le brief est complet.
4. **Livrer.** Le freelance dépose sa livraison ; le client peut la valider ou demander une correction dans les limites de l’accord.
5. **Clôturer.** Après validation explicite, la commande est clôturée et le client peut laisser un avis.

## Messagerie, assistance et litiges

Les échanges se font dans la messagerie privée de la plateforme. En cas de difficulté, l’assistance (depuis votre espace) peut ouvrir un dossier ; un litige sur une commande payée est traité par des personnes habilitées, selon les faits au dossier.

## Avis

Seules les commandes réelles, validées puis clôturées, donnent lieu à un avis public, après un délai de publication. Les avis ne sont jamais modifiés par l’administration ; ils peuvent être masqués avec un motif en cas de non-respect des règles.

## Paiement

Les modalités de paiement réel, les frais éventuels et les garanties offertes ne sont pas encore arrêtés et seront publiés avant l’ouverture aux paiements réels.
MD,
            'aide' => <<<'MD'
## Je n’arrive pas à me connecter

Utilisez « Mot de passe oublié » sur la page de connexion. Un lien de réinitialisation n’est envoyé que si le courrier est configuré sur le service.

## Comment changer mon adresse e-mail, mon mot de passe ou fermer mon compte ?

Depuis votre espace, rubrique **Mon compte** : informations, mot de passe, sessions, export de vos données et demande de fermeture. Le changement d’adresse n’a lieu qu’après vérification de la nouvelle adresse.

## Où suivre ma commande ?

Dans votre espace, rubrique **Commandes** : chaque étape, l’accord figé et les actions attendues y figurent.

## Je suis en désaccord avec une livraison

Demandez d’abord une correction dans les limites de l’accord. Si le désaccord persiste, utilisez la demande de désaccord de la commande ; l’assistance ouvre alors un dossier.

## Comment signaler un contenu ?

Chaque profil, service, mission, message et avis public propose « Signaler ». Le signalé n’en est pas informé.

## Y a-t-il une garantie de paiement ?

Les garanties et modalités de paiement réel ne sont pas encore définies ; aucune garantie n’est annoncée à ce stade.
MD,
            'contact' => <<<'MD'
## Assistance

L’assistance se fait depuis votre espace connecté, avec suivi de dossier et référence.

## Autres moyens de contact

Adresse de contact : {contact}

Aucun numéro de téléphone ni adresse postale n’est publié tant que l’exploitant ne les a pas fournis.
MD,
            'conditions' => <<<'MD'
Ce texte décrit les règles de fonctionnement appliquées par la plateforme. Tant qu’il n’est pas adopté par l’exploitant, il ne vaut pas contrat.

## 1. Objet

FreeCI permet à des clients et à des freelances indépendants de se mettre en relation, de convenir d’un accord, de suivre une prestation et d’en consulter la situation financière.

## 2. Comptes

Un compte est personnel. Vous êtes responsable de la confidentialité de votre mot de passe. Un compte peut être suspendu en cas de manquement ; la suspension empêche de démarrer de nouvelles activités, sans interrompre les commandes en cours.

## 3. Accord et commande

Les conditions acceptées (prix, délai, livrables, corrections incluses) sont figées dans l’accord de la commande. Les délais de réponse et de paiement y figurent.

## 4. Paiement, frais et garanties

*À rédiger par l’exploitant : modalités de paiement réel, frais et commission, remboursements, reversements, garanties.*

## 5. Contenus et avis

Les contenus publiés doivent respecter la loi et les règles de la plateforme. Un contenu peut être signalé puis masqué par une personne habilitée, avec un motif conservé.

## 6. Fermeture du compte

Vous pouvez demander la fermeture de votre compte ; elle n’est exécutée qu’en l’absence d’obligations en cours, et les éléments qui doivent être conservés le restent sous forme anonymisée.

## 7. Droit applicable et litiges

*À rédiger par l’exploitant.*
MD,
            'confidentialite' => <<<'MD'
Ce texte décrit les données traitées par la plateforme telle qu’elle fonctionne aujourd’hui. Tant qu’il n’est pas adopté par l’exploitant, il ne constitue pas la politique définitive.

## Données traitées

- Compte : nom, adresse e-mail, mot de passe (stocké sous forme chiffrée irréversible).
- Profil freelance, services, missions et propositions que vous publiez.
- Commandes, messages, fichiers de brief et de livraison, avis, favoris, notifications.
- Paiements et opérations financières liés à vos commandes ; destination de reversement du freelance (chiffrée).
- Journaux de sécurité (événements de connexion, adresse IP).

## Qui y accède

Vos messages, brief et livraisons sont réservés aux parties de la commande. L’administration n’a pas d’accès implicite aux commandes : un accès pour traiter un dossier d’assistance est motivé et journalisé. Vos favoris sont privés.

## Vos droits

Depuis **Mon compte** : modifier vos informations, exporter vos données, demander la fermeture de votre compte (anonymisation, sauf éléments à conserver).

## Durées de conservation

*À arrêter par l’exploitant.* Aucune durée n’est annoncée à ce stade pour les commandes, messages, écritures financières, dossiers d’assistance et journaux de sécurité.

## Responsable du traitement et contact

{exploitant} — {contact}
MD,
            'mentions-legales' => <<<'MD'
**Éditeur du service :** {exploitant}

**Adresse :** {adresse}

**Immatriculation :** {immatriculation}

**Directeur de la publication :** {directeur}

**Hébergeur :** {hebergeur}

**Contact :** {contact}
MD,
            default => '',
        };
    }
}
