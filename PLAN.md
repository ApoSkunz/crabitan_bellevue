# Plan d'action — Crabitan Bellevue

Refonte complète de crabitanbellevue.fr — site e-commerce de vins, PHP MVC custom, bilingue fr/en.

## Stack technique

| Domaine              | Choix                                              |
|----------------------|----------------------------------------------------|
| Backend              | PHP vanilla (MVC maison)                           |
| Base de données      | MySQL InnoDB, utf8mb4                              |
| Auth sessions        | JWT (table connections)                            |
| Auth sociale         | Google OAuth + Apple Sign In (gratuit)             |
| Paiement             | Crédit Agricole API + PayPal + Apple Pay/Braintree |
| Emails               | PHPMailer                                          |
| PDF                  | TCPDF                                              |
| Tests unitaires / TI | PHPUnit                                            |
| Tests E2E            | Playwright                                         |
| Linter               | PHPStan + PHP_CodeSniffer                          |
| CI/CD                | GitHub Actions + semantic-release                  |
| Dépendances auto     | Renovate (en dernier)                              |

## Branches & statut

| Branch                 | Statut      | Contenu                                                        |
|------------------------|-------------|----------------------------------------------------------------|
| `feat/core`            | Mergé       | Router, Controller, Model, JWT, Lang, i18n, BDD schema v3      |
| `feat/auth`            | Mergé       | AuthController, JWT sessions, vérif email, reset password      |
| `feat/release`         | En cours    | semantic-release, GitHub Actions release.yml                   |
| `feat/ci`              | A faire     | Pipeline GitHub Actions : PHPUnit (TU+TI) + Playwright (E2E)  |
| `feat/quality`         | A faire     | PHPStan + PHP_CodeSniffer                                      |
| `feat/tests-unit`      | A faire     | TU PHPUnit : Core layer                                        |
| `feat/tests-integration` | A faire   | TI PHPUnit : Controllers + BDD réelle                          |
| `feat/tests-e2e`       | A faire     | E2E Playwright : parcours clés                                 |
| `feat/auth-social`     | A faire     | Google OAuth + Apple Sign In                                   |
| `feat/layout`          | A faire     | Layout principal, assets CSS/JS/fonts, responsive              |
| `feat/home`            | A faire     | Page d'accueil fr/en                                           |
| `feat/catalogue`       | A faire     | Vins : liste, collection, fiche produit, filtres               |
| `feat/cart`            | A faire     | Panier CRUD + API AJAX + calcul livraison                      |
| `feat/order`           | A faire     | Checkout, paiement multi-PSP, facture TCPDF, email             |
| `feat/account`         | A faire     | Espace client : commandes, adresses, favoris                   |
| `feat/news`            | A faire     | Actualités : liste, article                                    |
| `feat/admin`           | A faire     | Back-office : vins, commandes, comptes, tarifs                 |
| `feat/i18n`            | A faire     | Fichiers de langue fr/en complets                              |
| `feat/email`           | A faire     | Templates emails transactionnels                               |
| `feat/seo`             | A faire     | Slugs, balises meta, sitemap.xml                               |
| `feat/newsletter`      | A faire     | Opt-in, envois                                                 |
| `feat/renovate`        | En dernier  | Renovate pour les dépendances automatiques                     |
