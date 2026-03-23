# Plan d'action — Crabitan Bellevue

Refonte complète — site e-commerce de vins, PHP MVC custom, bilingue fr/en.

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

## Correspondance vues — ancienne → nouvelle

Légende : ✅ Validé par Alexandre — 🔄 Implémenté, non encore validé — ⬜ À créer

| Ancienne vue | Nouvelle vue | Statut |
|---|---|---|
| `Age-Verification.php` | `src/View/age-gate.php` | ✅ Validé |
| `Legal-Notice.php` | `src/View/pages/mentions-legales.php` | ✅ Validé |
| `Domain.php` _(Le Château)_ | `src/View/pages/chateau.php` | ✅ Validé |
| `Knowledge.php` _(Savoir-faire)_ | `src/View/pages/savoir-faire.php` | ✅ Validé |
| `Support.php` | `src/View/pages/support.php` | ✅ Validé |
| — _(nouvelle)_ | `src/View/pages/politique-confidentialite.php` | ✅ Validé |
| `Games.php` | `src/View/pages/jeux.php` | 🔄 Implémenté (images KO — à revalider) |
| `Home.php` | `src/View/home.php` | 🔄 Implémenté |
| `Contact.php` | `src/View/pages/contact.php` | 🔄 Implémenté |
| `News.php` | `src/View/news/index.php` + `news/show.php` | 🔄 Implémenté |
| `Wines.php` | `src/View/wines/index.php` + `wines/show.php` | 🔄 Implémenté |
| `Collection.php` | `src/View/wines/collection.php` | 🔄 Implémenté |
| `Sitemap.php` | `src/View/pages/plan-du-site.php` | 🔄 Implémenté |
| `Webmaster.php` | `src/View/pages/webmaster.php` | 🔄 Implémenté |
| — _(nouvelle)_ | `src/View/auth/login.php` | 🔄 Implémenté |
| `Register.php` | `src/View/auth/register.php` | 🔄 Implémenté |
| `Reset.php` | `src/View/auth/forgot-password.php` + `auth/reset-password.php` | 🔄 Implémenté |
| `Verify.php` | `src/View/auth/verify.php` | 🔄 Implémenté |
| `Bad-Request.php` | `src/View/errors/error.php` (400) | 🔄 Implémenté |
| `Unauthorized.php` | `src/View/errors/error.php` (401) | 🔄 Implémenté |
| `Access-Forbidden.php` | `src/View/errors/error.php` (403) | 🔄 Implémenté |
| `Not-Found.php` | `src/View/errors/error.php` (404) | 🔄 Implémenté |
| `Method-Not-Allowed.php` | `src/View/errors/error.php` (405) | 🔄 Implémenté |
| `Server-Error.php` | `src/View/errors/error.php` (500) | 🔄 Implémenté |
| `Cart.php` | _(à créer — feat/cart)_ | ⬜ À créer |
| `Confirm-Shop.php` _(confirmation commande)_ | _(à créer — feat/order)_ | ⬜ À créer |
| `Orders.php` | _(à créer — feat/account)_ | ⬜ À créer |
| `Dashboard.php` _(espace client)_ | _(à créer — feat/account)_ | ⬜ À créer |
| `Personal-Informations.php` | _(à créer — feat/account)_ | ⬜ À créer |
| `Billing-Address.php` | _(à créer — feat/account)_ | ⬜ À créer |
| `Delivery-Address.php` | _(à créer — feat/account)_ | ⬜ À créer |
| `Change-Password.php` | _(à créer — feat/account)_ | ⬜ À créer |
| `Newsletter.php` | _(à créer — feat/newsletter)_ | ⬜ À créer |
| `Terms-Of-Sales.php` _(CGV)_ | _(à créer — feat/order)_ | ⬜ À créer |
| `Admin-Dashboard.php` | _(à créer — feat/admin)_ | ⬜ À créer |
| `News-Management.php` | _(à créer — feat/admin)_ | ⬜ À créer |
| `Newsletter-Management.php` | _(à créer — feat/admin)_ | ⬜ À créer |
| `Order-Management.php` | _(à créer — feat/admin)_ | ⬜ À créer |
| `User-Management.php` | _(à créer — feat/admin)_ | ⬜ À créer |
| `Wine-Management.php` | _(à créer — feat/admin)_ | ⬜ À créer |

**6 validées — 18 implémentées à valider — 16 à créer**

---

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
- [x] Page Le Château — 7 sections historiques (photos famille, vendanges, Nicolas)
- [x] Page Savoir-Faire — contenu réel (vignoble, vinification, cave)
- [x] Page Mentions Légales — contenu légal complet (GFA Bernard Solane et Fils)
- [x] Page Support / FAQ — accordéon 11 questions (mdp, email, compte, adresses, commandes)
- [ ] Page Jeux — jeu mémoire MÉMO (14 paires bouteilles, timer 2 min, JS interactif) _(images KO — à revalider)_

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

### E2E — Bilan global (à faire en dernier)
- [ ] Bilan de couverture E2E une fois toutes les features implémentées
- [ ] Un spec par dossier feature : `auth.spec.js`, `wines.spec.js`, `cart.spec.js`, `order.spec.js`, `account.spec.js`, `admin.spec.js`, `jeux.spec.js`
- [ ] Chaque spec couvre le parcours nominal + 1 cas d'erreur critique minimum
- [ ] Objectif : new code JS coverage ≥ 80% sur SonarCloud
