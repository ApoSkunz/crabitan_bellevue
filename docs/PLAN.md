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
| `Contact.php` | `src/View/pages/contact.php` | 🚧 Tests techniques restants |
| `Wines.php` | `src/View/wines/index.php` + `wines/show.php` | ✅ Validé |
| `Collection.php` | `src/View/wines/collection.php` | ✅ Validé |
| `Sitemap.php` | `src/View/pages/plan-du-site.php` | ✅ Validé |
| `Games.php` | `src/View/pages/jeux.php` | 🔄 🚧 (images KO — à revalider) |
| `Home.php` | `src/View/home.php` | ✅ Validé |
| `News.php` | `src/View/news/index.php` + `news/show.php` | 🔄 Implémenté |
| `Webmaster.php` | `src/View/pages/webmaster.php` | ✅ Validé |
| — _(nouvelle)_ | Modal connexion dans le header | ✅ Validé |
| `Register.php` | Modal inscription dans le header | ✅ Validé |
| — _(nouvelle)_ | Modal mot de passe oublié (panel dans modal connexion) | ✅ Validé |
| `Reset.php` | Modal réinitialisation mot de passe (`?modal=reset`) | ✅ Validé |
| `Verify.php` | `src/View/auth/verify.php` | ✅ Validé |
| `Bad-Request.php` | `src/View/errors/error.php` (400) | 🔄 Implémenté |
| `Unauthorized.php` | `src/View/errors/error.php` (401) | 🔄 Implémenté |
| `Access-Forbidden.php` | `src/View/errors/error.php` (403) | 🔄 Implémenté |
| `Not-Found.php` | `src/View/errors/error.php` (404) | 🔄 Implémenté |
| `Method-Not-Allowed.php` | `src/View/errors/error.php` (405) | 🔄 Implémenté |
| `Server-Error.php` | `src/View/errors/error.php` (500) | ✅ Validé |
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
| `Admin-Dashboard.php` | `src/View/admin/dashboard.php` — KPIs, CA 30j, commandes récentes | 🔄 Implémenté |
| `News-Management.php` | `src/View/admin/news/` — CRUD articles bilingues, slug régénéré depuis le titre FR | 🔄 Implémenté |
| `Newsletter-Management.php` | `src/View/admin/newsletter/index.php` — liste abonnés, envoi via modal, image d'en-tête optionnelle | 🔄 Implémenté |
| `Order-Management.php` | `src/View/admin/orders/` — liste + détail commandes | 🔄 Implémenté |
| `User-Management.php` | `src/View/admin/accounts/index.php` — filtre rôle/type/recherche | 🔄 Implémenté |
| `Wine-Management.php` | `src/View/admin/wines/` — CRUD vins, upload image, traduction auto EN | 🔄 Implémenté |

**17 validées — 1 en tests techniques — 1 à revalider — 13 implémentées — 10 à créer**

> ⚠️ `/fr/mon-compte` renvoie une 500 attendue — la route n'existe pas encore (`feat/account` ⬜).

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
| `refactor/schema-accounts` | 🔄 En cours | Scission accounts → accounts + account_individuals + account_companies, refonte connections (device_token, apple_id) |
| `feat/pages-content`     | 🔄 En cours  | Contenu réel, contact form, cuvée spéciale, plan du site, UX/SCSS               |
| `feat/contact-form`      | ✅ Fait       | Formulaire contact AJAX + CSRF + PHPMailer (inclus dans feat/pages-content)      |
| `chore/smtp-local`       | ✅ Fait       | MailHog installé (C:/tools/mailhog.exe), .env configuré, templates HTML/CSS branded |
| `fix/jeux-memo`          | ⬜ À faire    | Revoir jeu mémoire : images bouteilles KO, logique JS à revalider                |
| `fix/wine-pdf`           | ⬜ À faire    | Revoir fiche technique PDF (TCPDF) — rendu cassé, fond crème, logo, image vin    |
| `feat/lazy-load`         | ⏸ Plus tard  | Pagination / lazy loading sur `/vins`, `/vins/collection`, `/actualites`         |
| `feat/webm`              | ⬜ À faire    | Encoder vidéo homepage en `.webm` en complément du `.mp4`                        |
| `feat/sitemap-dynamic`   | ⬜ À faire    | Plan du site dynamique généré depuis les routes (`/fr/plan-du-site`)             |
| `feat/cart`              | ⬜ À faire    | Panier CRUD + API AJAX + calcul livraison (pricing_rules)                        |
| `feat/order`             | ⬜ À faire    | Checkout, paiement CA API, facture TCPDF, email confirmation                     |
| `feat/account`           | ⬜ À faire    | Espace client : commandes, adresses, favoris (→ alimente tri "likes")            |
| `feat/auth-modal`        | ✅ Mergé      | Modal connexion header, CSRF, toggle password, social placeholders, suppression GET /connexion |
| `feat/auth-register-modal` | 🔄 En cours | Modal inscription, modal forgot password, modal reset, verify.php, MailHog, templates emails HTML, widget météo carousel, nav bar light theme |
| `feat/auth-social`       | ⬜ Plus tard  | Google OAuth (en premier) + Apple Sign In (après, nécessite Apple Developer)     |
| `feat/admin`             | 🔄 En cours  | Back-office : dashboard, vins CRUD, commandes, comptes, tarifs, actualités, newsletter — UX round 7 en cours (image newsletter cadrée dans corps) |
| `feat/i18n`              | ⬜ À faire    | Fichiers de langue fr/en complets                                                |
| `feat/email`             | ✅ Fait       | Templates HTML/CSS brandés (vérif compte, reset, contact) — inclus dans feat/auth-register-modal |
| `feat/seo`               | ⬜ À faire    | Slugs, balises meta bilingues, sitemap.xml, Open Graph                           |
| `chore/exakat-ci`        | ⬜ Après SEO  | Intégrer Exakat SAST PHP dans la CI (phar ou Docker) après stabilisation du code |
| `feat/newsletter`        | ⬜ À faire    | Opt-in, envois                                                                   |
| `feat/smtps`             | ✅ Fait       | PHPMailer + MailHog dev + SMTP prod via .env — inclus dans chore/smtp-local      |
| `chore/doc-technique`    | ⬜ À faire    | Ajouter PHPDoc sur les méthodes sans commentaire (Controllers, Models, Services)  |
| `chore/e2e-coverage`     | ⬜ En dernier | Bilan E2E global : un spec par feature, coverage ≥ 80% SonarCloud                |
| `feat/renovate`          | ⬜ En dernier | Renovate pour les dépendances automatiques                                       |
| `chore/git-lfs`          | ⬜ En dernier | Migrer assets lourds (vidéos MP4 > 50 MB) vers Git LFS                          |

---

## Backlog détaillé

### chore/smtp-local

> Nécessite de monter un serveur SMTP local pour valider les envois d'email (inscription, reset, contact) sans passer par un vrai SMTP de production.

- [ ] Installer MailHog (ou Mailtrap CLI) : `choco install mailhog` ou via Docker `mailhog/mailhog`
- [ ] Configurer `.env` local : `MAIL_HOST=localhost`, `MAIL_PORT=1025`, `MAIL_USER=`, `MAIL_PASS=`
- [ ] Documenter dans `docs/setup-dev.md` (section SMTP)
- [ ] Tester les 4 flux : vérification compte, reset mot de passe, contact propriétaire, confirmation client
- [ ] Ajouter un test d'intégration PHPUnit qui mocke `MailService` ou vérifie l'envoi via MailHog API

### chore/doc-technique

- [ ] Passer en revue `src/Controller/` — ajouter PHPDoc sur les méthodes publiques non documentées
- [ ] Passer en revue `src/Model/` — documenter les paramètres et types de retour
- [ ] Passer en revue `src/Service/` — documenter `MailService`, `PdfService` etc.
- [ ] Passer en revue `src/Core/` — documenter Router, Controller base, Response
- [ ] Vérifier que PHPStan niveau 8 passe toujours après ajout des annotations

### fix/jeux-memo
- [ ] Intégrer les images réelles des bouteilles (14 paires)
- [ ] Revalider la logique JS (timer 2 min, retournement cartes, détection match)
- [ ] TU/E2E jeux

### fix/wine-pdf
- [ ] Revoir rendu TCPDF : fond crème, logo, image vin, police dejavusans, footer doré
- [ ] PDF inline (nouvel onglet)
- [ ] TU helper PDF

### responsive-complet
- [ ] Audit toutes les pages sur mobile (320px) et tablette (768px)
- [ ] Refonte responsive modals connexion + inscription (touch, scroll interne)
- [ ] Vues client et admin adaptées mobile
- [ ] Breakpoints SCSS harmonisés dans `_breakpoints.scss`
- [ ] Tests Playwright viewport mobile

### Apple Sign In
- [ ] Nécessite un compte Apple Developer + domaine vérifié HTTPS
- [ ] À traiter après Google OAuth2 validé en production
- [ ] Route `/auth/apple/callback`, vérification JWT Apple, liaison AccountModel

### chore/e2e-coverage
- [ ] Un spec par dossier feature : `auth.spec.js`, `wines.spec.js`, `cart.spec.js`, `order.spec.js`, `account.spec.js`, `admin.spec.js`, `jeux.spec.js`
- [ ] Chaque spec couvre le parcours nominal + 1 cas d'erreur critique minimum
- [ ] Objectif : new code JS coverage ≥ 80% sur SonarCloud
