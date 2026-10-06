# Contrôle visuel — lot 8

Captures locales (compte d'administrateur de démonstration, jamais présent en production) : défi de double authentification, tableau de bord, modération, utilisateurs, journal d'audit, événements de sécurité, « Ma sécurité », à 360 px et 1440 px (`captures/`).
Constat : aucun débordement horizontal (`scrollWidth <= innerWidth`) sur les 12 écrans mesurés ; tableaux empilés en cartes en mobile (composant `table.list` existant) ; navigation latérale (bureau) et menu (mobile) alignés sur l'identité FreeCI. Aucune refonte du site public.
