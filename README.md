# FreeCI

Projet en phase de compréhension et de conception.

## Gouvernance

- Distinguer vision, conception et implémentation.
- Documenter les exigences, hypothèses et décisions avec leur source et leur statut.
- Conserver les décisions validées et contrats explicites dans Git.
- Le **lot 1** (socle et découverte des services) est implémenté ; tout le reste est annoncé « Bientôt » dans l'application. Voir `docs/06-lot-1-socle-et-decouverte.md`.
- Aucun choix technologique ni paramètre commercial ne devient validé par sa seule présence dans un document de travail.

Les documents du client doivent être examinés avant de figer la conception. Leur publication dans ce dépôt public doit être décidée séparément.

## Conception

- Dossier documentaire : [`docs/`](docs/).
- Prototype visuel statique V01 (données simulées, sans backend) : [`design/prototype-v01/`](design/prototype-v01/README.md) — ouvrir `index.html` dans un navigateur.

## Application (lot 1)

Laravel 13 · PHP ≥ 8.3 · PostgreSQL ≥ 13 (testé en 16) · Blade + Livewire 4 (Alpine inclus) · Tailwind 4 · Vite 8. Dépendances verrouillées (`composer.lock`, `package-lock.json`). **Démonstration : données fictives, aucun paiement ni commande réels.**

### Lancer en local

```bash
# 1. Base PostgreSQL (adapter nom et mot de passe ; ne rien committer)
sudo -u postgres psql -c "CREATE ROLE freeci LOGIN PASSWORD 'CHOISISSEZ-UN-MOT-DE-PASSE' CREATEDB;"
sudo -u postgres createdb -O freeci freeci
sudo -u postgres createdb -O freeci freeci_test      # base des tests

# 2. Installation
composer install
npm ci && npm run build
cp .env.example .env && php artisan key:generate
#    puis renseigner DB_PASSWORD dans .env

# 3. Schéma et données fictives
php artisan migrate --seed
#    Le mot de passe du compte client de démonstration est généré et affiché UNE fois.
#    Pour le fixer : FREECI_DEMO_CLIENT_PASSWORD='...' php artisan db:seed --force

# 4. Servir
php artisan serve        # http://127.0.0.1:8000
```

Compte client de démonstration : `client@demo.freeci.invalid` (mot de passe : voir l'étape 3). Les messages (lien de récupération du mot de passe) sont écrits dans `storage/logs/laravel.log` (`MAIL_MAILER=log`).

### Tests

```bash
php artisan test        # PostgreSQL, base freeci_test
./vendor/bin/pint --test
```
