<?php

namespace Database\Seeders;

use App\Modules\Accounts\Models\User;
use App\Modules\Catalog\Enums\ServiceStatus;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\FreelanceProfile;
use App\Modules\Catalog\Models\Service;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Jeu de données FICTIF et reproductible : catégories, profils et services de démonstration.
 * Aucun mot de passe n'est écrit ici : les vendeurs de démonstration ne peuvent pas se connecter
 * (mot de passe aléatoire jeté) ; le compte client de démonstration reçoit un mot de passe fourni par
 * l'environnement ou, à défaut, généré et affiché une seule fois dans la console.
 */
class DemoCatalogSeeder extends Seeder
{
    private const DOMAIN = 'demo.freeci.invalid';

    public function run(): void
    {
        if (app()->isProduction() && ! config('freeci.allow_demo_seed')) {
            $this->command?->error('Données de démonstration refusées en production (FREECI_ALLOW_DEMO_SEED=true pour forcer).');

            return;
        }

        $categories = $this->categories();
        $profiles = $this->profiles();

        foreach ($this->services() as $i => $s) {
            $payload = [
                'category_id' => $categories[$s['cat']]->id,
                'freelance_profile_id' => $profiles[$s['by']]->id,
                'title' => $s['title'],
                'summary' => $s['summary'],
                'scope' => $s['scope'],
                'price_xof' => $s['price'],
                'delivery_days' => $s['days'],
                'revisions_included' => $s['rev'],
                'deliverables' => $s['deliver'],
                'exclusions' => $s['excl'],
                'client_inputs' => $s['inputs'],
                'images' => [$s['img']],
                'status' => $s['status'] ?? ServiceStatus::Published->value,
                'published_at' => now()->startOfDay()->subDays(2 + $i * 2),
                'is_demo' => true,
            ];
            if (isset($s['gallery'])) {
                $payload['images'] = $s['gallery'];
            }
            Service::updateOrCreate(['slug' => $s['slug']], $payload);
        }

        $this->demoClient();
    }

    /** @return array<string, Category> */
    private function categories(): array
    {
        $rows = [
            'btp' => ['BTP et Architecture', 'building'],
            'ingenierie' => ['Ingénierie et Industrie', 'cog'],
            'dev' => ['Développement et Informatique', 'code'],
            'design' => ['Design et Graphisme', 'pen'],
            'video' => ['Photo et Vidéo', 'camera'],
            'marketing' => ['Marketing et Communication', 'megaphone'],
            'redaction' => ['Rédaction et Traduction', 'text'],
            'formation' => ['Formation et Accompagnement', 'cap'],
        ];
        $out = [];
        $pos = 1;
        foreach ($rows as $key => [$name, $icon]) {
            $out[$key] = Category::updateOrCreate(['slug' => Str::slug($name)], ['name' => $name, 'icon' => $icon, 'position' => $pos++]);
        }

        return $out;
    }

    /** @return array<string, FreelanceProfile> */
    private function profiles(): array
    {
        $rows = [
            'kader' => ['Kader Soro', 'Dessinateur DAO', 'Abidjan'],
            'mariam' => ['Mariam Touré', 'Designer', 'Abidjan'],
            'yann' => ['Yann Gnagne', 'Développeur web', 'Bouaké'],
            'salimata' => ['Salimata Cissé', 'Traductrice', 'Abidjan'],
            'ibrahim' => ['Ibrahim Konaté', 'Monteur', 'Yamoussoukro'],
            'awa' => ['Awa Diallo', 'Community manager', 'Abidjan'],
            'joel' => ['Joël Ahoussou', 'Ingénieur méthodes', 'San-Pédro'],
            'nadege' => ['Nadège Kouamé', 'Formatrice bureautique', 'Abidjan'],
            'serge' => ['Serge Yapi', 'Architecte d’intérieur', 'Abidjan'],
        ];
        $out = [];
        foreach ($rows as $key => [$name, $headline, $city]) {
            $user = User::firstOrNew(['email' => $key.'@'.self::DOMAIN]);
            if (! $user->exists) {
                // Mot de passe aléatoire jeté : ce compte vendeur n'est pas utilisable pour se connecter.
                $user->forceFill(['name' => $name, 'password' => Hash::make(Str::random(64)), 'is_demo' => true])->save();
            }
            $out[$key] = FreelanceProfile::updateOrCreate(['user_id' => $user->id], [
                'display_name' => $name, 'headline' => $headline, 'city' => $city, 'is_demo' => true,
            ]);
        }

        return $out;
    }

    private function demoClient(): void
    {
        $email = 'client@'.self::DOMAIN;
        if (User::where('email', $email)->exists()) {
            $this->command?->info("Compte client de démonstration déjà présent : {$email} (mot de passe inchangé).");

            return;
        }
        $password = config('freeci.demo_client_password') ?: Str::password(16, symbols: false);
        User::forceCreate([
            'name' => 'Fanta Bamba (démo)', 'email' => $email, 'password' => Hash::make($password),
            'email_verified_at' => now(), 'is_demo' => true,
        ]);
        $this->command?->warn("Compte client de DÉMONSTRATION créé : {$email}");
        if (! config('freeci.demo_client_password')) {
            $this->command?->warn("Mot de passe généré (affiché une seule fois) : {$password}");
        }
    }

    private function img(string $file, string $alt, ?string $card = null): array
    {
        return ['src' => '/img/demo/'.$file, 'card' => '/img/demo/'.($card ?? $file), 'alt' => $alt, 'caption' => $alt];
    }

    /** @return list<array<string, mixed>> */
    private function services(): array
    {
        $dwgGallery = [
            ['src' => '/img/demo/plan-dwg-wide.svg', 'card' => '/img/demo/plan-dwg.svg', 'alt' => 'Plan d’étage converti en fichier DWG avec calques structurés', 'caption' => 'Après : fichier DWG aux calques structurés.'],
            ['src' => '/img/demo/plan-pdf-wide.svg', 'card' => '/img/demo/plan-dwg.svg', 'alt' => 'Plan d’étage reçu au format PDF', 'caption' => 'Avant : plan reçu au format PDF.'],
            ['src' => '/img/demo/plan-calques-wide.svg', 'card' => '/img/demo/plan-dwg.svg', 'alt' => 'Calques structurés : murs, cotations, mobilier', 'caption' => 'Calques structurés : murs, cotation, mobilier.'],
        ];
        $plan = $this->img('plan-dwg-wide.svg', 'Exemple : plan d’étage', 'plan-dwg.svg');
        $ident = $this->img('svc-identite.svg', 'Exemple : papeterie, carte de visite et palette d’une identité visuelle');
        $site = $this->img('svc-site.svg', 'Exemple : maquette d’un site vitrine sur ordinateur et téléphone');
        $trad = $this->img('svc-redaction.svg', 'Exemple : document traduit du français vers l’anglais');
        $video = $this->img('svc-video.svg', 'Exemple : montage vidéo, aperçu et pistes de montage');
        $social = $this->img('svc-social.svg', 'Exemple : calendrier éditorial et publications pour les réseaux sociaux');

        $s = fn (string $slug, string $cat, string $by, string $title, string $summary, string $scope, int $price, int $days, int $rev, array $img, array $deliver, array $excl, array $inputs, array $extra = []) => array_merge(compact('slug', 'cat', 'by', 'title', 'summary', 'scope', 'price', 'days', 'rev', 'img', 'deliver', 'excl', 'inputs'), $extra);

        return [
            $s('convertir-plans-pdf-en-dwg', 'btp', 'kader', 'Convertir vos plans PDF en fichiers AutoCAD (DWG)',
                'Vos plans PDF redessinés en fichiers DWG propres, calques structurés, prêts à modifier.',
                'Jusqu’à 12 plans (A4 à A1) sur 3 niveaux au maximum, 2 corrections comprises. Au-delà, une proposition sur mesure est établie avant tout paiement.',
                35000, 5, 2, $plan,
                ['Un fichier DWG par plan, à l’échelle indiquée.', 'Des calques structurés : murs, cotations, textes, mobilier.', 'Un PDF de contrôle pour comparer avec votre plan d’origine.', 'Format AutoCAD 2018, ou antérieur sur demande.'],
                ['Le dimensionnement ou la vérification technique des ouvrages.', 'La création de plans à partir de croquis à main levée.', 'Les demandes au-delà de 12 plans ou 3 niveaux.'],
                ['Vos plans au format PDF (vectoriel de préférence, sinon scan net à 300 dpi).', 'Le nombre de plans et de niveaux concernés.', 'La version d’AutoCAD de destination.', 'Vos conventions de calques, si vous en avez.'],
                ['gallery' => $dwgGallery]),
            $s('plan-amenagement-interieur-2d', 'btp', 'serge', 'Plan d’aménagement intérieur en 2D pour un appartement',
                'Un plan coté de l’aménagement de votre logement, avec deux variantes de disposition.',
                'Un logement jusqu’à 120 m², deux variantes de disposition, une reprise de chaque variante.',
                60000, 7, 1, $plan,
                ['Un plan 2D coté au format PDF.', 'Deux variantes de disposition.', 'La liste du mobilier représenté.'],
                ['Les plans d’exécution pour les corps de métier.', 'Le suivi de chantier.'],
                ['Un plan ou un relevé de mesures du logement.', 'Vos contraintes (budget indicatif, mobilier à conserver).']),
            $s('logo-mini-charte-graphique', 'design', 'mariam', 'Créer votre logo et une mini charte graphique',
                'Un logo décliné en versions couleur et monochrome, avec les couleurs et les polices à utiliser.',
                'Trois pistes de logo, une piste retenue et affinée, 3 corrections comprises, mini charte de deux pages.',
                45000, 7, 3, $ident,
                ['Le logo en PNG, SVG et PDF.', 'Des versions couleur, noir et blanc.', 'Une mini charte de deux pages : couleurs, polices, usages.'],
                ['Les supports imprimés (cartes, affiches).', 'Le dépôt de marque.'],
                ['Le nom de l’entreprise et son activité.', 'Des exemples de logos que vous aimez ou rejetez.', 'Les couleurs imposées, le cas échéant.']),
            $s('affiche-evenement-a3', 'design', 'mariam', 'Affiche d’événement au format A3, prête à imprimer',
                'Une affiche claire et lisible pour votre événement, livrée prête pour l’imprimeur.',
                'Une affiche recto A3, deux corrections, fichier PDF haute définition et version pour les réseaux sociaux.',
                18000, 3, 2, $ident,
                ['Un PDF haute définition prêt à imprimer.', 'Une version carrée pour les réseaux sociaux.'],
                ['L’impression.', 'La rédaction du texte de l’événement.'],
                ['Le texte exact à afficher.', 'Le logo des organisateurs, en bonne qualité.']),
            $s('site-vitrine-5-pages', 'dev', 'yann', 'Site vitrine de 5 pages, prêt à publier',
                'Un site simple et rapide pour présenter votre activité, consultable sur téléphone.',
                'Cinq pages (accueil, services, à propos, galerie, contact), formulaire de contact, 2 corrections comprises. Hébergement et nom de domaine non inclus.',
                120000, 14, 2, $site,
                ['Le site complet, prêt à être mis en ligne.', 'Une courte notice pour modifier les textes.'],
                ['L’hébergement et le nom de domaine.', 'La rédaction des textes et la prise de photos.', 'Une boutique en ligne.'],
                ['Vos textes et images, ou un accord pour les rédiger séparément.', 'Votre logo et vos couleurs.', 'Les coordonnées à afficher.']),
            $s('correction-site-wordpress', 'dev', 'yann', 'Corriger et accélérer un site WordPress existant',
                'Diagnostic et corrections des lenteurs et erreurs courantes d’un site WordPress.',
                'Un site, jusqu’à 10 heures de travail, un rapport avant/après.',
                50000, 5, 1, $site,
                ['Un rapport de diagnostic.', 'Les corrections appliquées et listées.'],
                ['La refonte du design.', 'La récupération d’un site piraté.'],
                ['Un accès administrateur temporaire (jamais par message public).', 'La description des problèmes constatés.']),
            $s('traduction-fr-en-10-pages', 'redaction', 'salimata', 'Traduire un document du français vers l’anglais (10 pages)',
                'Une traduction soignée de votre document, mise en page conservée quand c’est possible.',
                'Jusqu’à 10 pages (environ 3 000 mots), 1 relecture comprise. Documents techniques ou juridiques sur devis.',
                25000, 3, 1, $trad,
                ['Le document traduit au format Word ou PDF.', 'Un glossaire des termes spécifiques, si utile.'],
                ['La traduction certifiée ou assermentée.', 'Les documents de plus de 10 pages.'],
                ['Le document source modifiable (Word) si possible.', 'Le public visé et le ton souhaité.']),
            $s('redaction-fiches-produit', 'redaction', 'salimata', 'Rédiger 10 fiches produit pour votre boutique en ligne',
                'Dix fiches produit claires, avec descriptions courtes et longues, prêtes à publier.',
                'Dix produits, un ton à convenir, 2 corrections comprises.',
                30000, 4, 2, $trad,
                ['Dix fiches au format texte ou tableur.', 'Un titre et une description courte par produit.'],
                ['Les photographies.', 'Le référencement avancé.'],
                ['La liste des produits avec leurs caractéristiques.', 'Le ton souhaité et le public visé.']),
            $s('montage-video-presentation-2-minutes', 'video', 'ibrahim', 'Monter une vidéo de présentation de 2 minutes',
                'Un montage dynamique de vos images, avec musique libre de droits et sous-titres.',
                'Jusqu’à 30 minutes de rushes, une vidéo de 2 minutes, 2 corrections comprises.',
                60000, 6, 2, $video,
                ['La vidéo au format MP4 (1080p).', 'Un fichier de sous-titres.'],
                ['Le tournage.', 'La voix off.'],
                ['Les images et sons bruts.', 'Le message principal et le public visé.']),
            $s('retouche-photos-produit-20', 'video', 'ibrahim', 'Retoucher 20 photos de produits',
                'Fonds nettoyés, couleurs équilibrées, 20 photos prêtes pour votre boutique.',
                'Vingt photos, formats web, 1 correction comprise.',
                20000, 3, 1, $video,
                ['Vingt images retouchées en JPG.', 'Une version à fond blanc.'],
                ['La prise de vue.', 'Les montages complexes.'],
                ['Les photos d’origine en bonne définition.']),
            $s('calendrier-publications-reseaux-sociaux', 'marketing', 'awa', 'Calendrier de publications pour un mois (12 visuels)',
                'Un mois de publications planifiées, avec textes et visuels adaptés à vos réseaux.',
                'Douze visuels, un calendrier éditorial, 2 corrections comprises. La publication sur vos comptes n’est pas incluse.',
                55000, 10, 2, $social,
                ['Un calendrier éditorial d’un mois.', 'Douze visuels aux bons formats.', 'Les textes d’accompagnement.'],
                ['La publication sur vos comptes.', 'La publicité payante.'],
                ['Votre activité et votre public.', 'Votre logo et vos couleurs.', 'Les dates importantes du mois.']),
            $s('audit-presence-en-ligne', 'marketing', 'awa', 'Audit de votre présence en ligne avec recommandations',
                'Un état des lieux de vos pages et un plan d’actions priorisé.',
                'Jusqu’à 3 pages ou comptes, un rapport de 8 à 12 pages, un entretien de restitution de 30 minutes.',
                40000, 6, 1, $social,
                ['Un rapport PDF avec constats et priorités.', 'Un entretien de restitution.'],
                ['La mise en œuvre des recommandations.'],
                ['Les adresses de vos pages ou comptes.', 'Vos objectifs.']),
            $s('optimisation-planning-atelier', 'ingenierie', 'joel', 'Optimiser le planning d’un petit atelier de production',
                'Un planning hebdomadaire réaliste à partir de vos commandes et de vos machines.',
                'Un atelier jusqu’à 10 postes, un tableur de planification, 1 session de prise en main.',
                85000, 8, 1, $plan,
                ['Un tableur de planification prêt à l’emploi.', 'Une note de deux pages sur les hypothèses.'],
                ['La modification des machines.', 'Le suivi quotidien.'],
                ['La liste des commandes et des temps de fabrication.', 'Les horaires de l’atelier.']),
            $s('modele-tableur-suivi-stock', 'ingenierie', 'joel', 'Modèle de tableur pour suivre votre stock',
                'Un tableur simple : entrées, sorties, seuils d’alerte, valeur du stock.',
                'Un modèle pour jusqu’à 200 références, 1 correction.',
                22000, 3, 1, $plan,
                ['Le tableur au format Excel.', 'Une page d’explications.'],
                ['La saisie de vos données.'],
                ['La liste de vos références.']),
            $s('formation-excel-debutants-4h', 'formation', 'nadege', 'Formation Excel pour débutants (4 heures en visioconférence)',
                'Quatre heures pour utiliser un tableur au quotidien : saisie, formules simples, tableaux.',
                'Une personne ou un petit groupe de 3 personnes, deux séances de 2 heures, support fourni.',
                30000, 7, 0, $trad,
                ['Deux séances de 2 heures.', 'Un support PDF et des exercices.'],
                ['Les logiciels : vous devez disposer d’Excel ou d’un équivalent.'],
                ['Votre niveau actuel et vos objectifs.', 'Une connexion Internet stable.']),
            $s('ancien-service-retire', 'design', 'mariam', 'Exemple de service retiré',
                'Un service qui n’est plus proposé.', 'Sans objet.', 10000, 3, 1, $ident, ['—'], ['—'], ['—'],
                ['status' => ServiceStatus::Archived->value]),
        ];
    }
}
