# Plan d'action — Crabitan Bellevue

Refonte complète de crabitanbellevue.fr — site e-commerce de vins, PHP MVC custom, bilingue fr/en.

## Stack technique

| Domaine              | Choix                                              |
|----------------------|----------------------------------------------------|
| Backend              | PHP vanilla (MVC maison)                           |
| Base de données      | MySQL InnoDB, utf8mb4                              |
| Auth sessions        | JWT (table connections)                            |
| Auth sociale         | Google OAuth + Apple Sign In (gratuit)             |
| Paiement             | Crédit Agricole API                                |
| Emails               | PHPMailer                                          |
| PDF                  | TCPDF                                              |
| Tests unitaires / TI | PHPUnit                                            |
| Tests E2E            | Playwright                                         |
| Linter               | PHPStan + PHP_CodeSniffer                          |
| CI/CD                | GitHub Actions + semantic-release                  |
| Dépendances auto     | Renovate (en dernier)                              |

## Branches & statut

| Branch                   | Statut        | Contenu                                                                          |
|--------------------------|---------------|----------------------------------------------------------------------------------|
| `feat/core`              | ✅ Mergé      | Router, Controller, Model, JWT, Lang, i18n, BDD schema v3                        |
| `feat/auth`              | ✅ Mergé      | AuthController, JWT sessions, vérif email, reset password                        |
| `feat/release`           | ✅ Mergé      | semantic-release, GitHub Actions release.yml                                     |
| `feat/homepage`          | ✅ Mergé      | Layout, age gate, carousel, sections, header/footer, cookie RGPD                 |
| `feat/pages`             | ✅ Mergé      | NewsController, WineController, PageController + toutes les vues                 |
| `feat/catalogue`         | ✅ Mergé      | Catalogue vins complet : filtres, collection, fiche, SCSS, seed ~40 vins         |
| `fix/pdf-png-alpha`      | ✅ En cours   | PDF inline, fond crème, logo/image vin (rendu encore cassé — à reprendre après pages) |
| `feat/pages-content`     | 🔄 En cours   | Contenu réel : Château, Savoir-faire, Mentions légales, Support FAQ, Jeux mémo   |
| `feat/cart`              | ⬜ À faire    | Panier CRUD + API AJAX + calcul livraison (pricing_rules)                        |
| `feat/order`             | ⬜ À faire    | Checkout, paiement CA API, facture TCPDF, email confirmation                     |
| `feat/account`           | ⬜ À faire    | Espace client : commandes, adresses, favoris (→ alimente tri "likes")            |
| `feat/auth-social`       | ⬜ À faire    | Google OAuth + Apple Sign In                                                     |
| `feat/admin`             | ⬜ À faire    | Back-office : vins, commandes, comptes, tarifs                                   |
| `feat/contact-form`      | ⬜ À faire    | Formulaire contact fonctionnel (PHPMailer)                                       |
| `feat/i18n`              | ⬜ À faire    | Fichiers de langue fr/en complets                                                |
| `feat/email`             | ⬜ À faire    | Templates emails transactionnels                                                 |
| `feat/seo`               | ⬜ À faire    | Slugs, balises meta bilingues, sitemap.xml, Open Graph                           |
| `feat/newsletter`        | ⬜ À faire    | Opt-in, envois                                                                   |
| `feat/renovate`          | ⬜ En dernier | Renovate pour les dépendances automatiques                                       |
| `chore/git-lfs`          | ⬜ En dernier | Migrer assets lourds (vidéos MP4 > 50 MB) vers Git LFS                          |

## Backlog détaillé

### feat/pages-content (en cours)
- [x] Retirer champagne/pétillant du filtre UI (déjà absent — ENUM BDD conservé)
- [ ] Page Le Château — 7 sections historiques (photos famille, vendanges, Nicolas)
- [ ] Page Savoir-Faire — contenu réel (vignoble, vinification, cave)
- [ ] Page Mentions Légales — contenu légal complet (GFA Bernard Solane et Fils)
- [ ] Page Support / FAQ — accordéon 11 questions (mdp, email, compte, adresses, commandes)
- [ ] Page Jeux — jeu mémoire MÉMO (14 paires bouteilles, timer 2 min, JS interactif)

### Catalogue (suite)
- [ ] Pagination / lazy loading sur `/vins` et `/vins/collection`
- [ ] Bouton "Ajouter au panier" (non connecté → redirect /connexion, connecté → panier)
- [ ] Rattacher images réelles aux vins du seed
- [ ] Retravailler seed : distinguer HVE (certification) de l'appellation AOC
- [ ] Affichage compteur likes (fictif jusqu'à feat/account)
- [ ] Fiche technique PDF — **à revoir après toutes les pages** (implémenté mais rendu cassé)

### Transversal
- [ ] Vidéo `.webm` en complément `.mp4` pour la homepage
- [ ] Plan du site dynamique (`/fr/plan-du-site`)
