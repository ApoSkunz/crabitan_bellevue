# Stratégie de tests — Crabitan Bellevue

**Objectif :** 80 % de coverage global sur le code source PHP et JS.
**Quality Gate SonarCloud :** new code ≥ 80 % (configuré dans l'interface SonarCloud).

---

## Exclusions du coverage

- `src/View/**` — templates PHP
- `lang/**` — fichiers de traduction
- `database/**` — migrations SQL
- `config/**` — constantes de configuration
- `vendor/**`, `node_modules/**`

---

## TU — Tests Unitaires (PHPUnit, sans BDD)

**Périmètre :** logique métier isolée, mocks pour les dépendances externes.
**Quand écrire :** dès qu'une classe a de la logique interne (validations, transformations, conditions).

| Classe | Statut | Tests |
|---|---|---|
| `Core/Router` | ✅ | 9 |
| `Core/Jwt` | ✅ | 7 |
| `Core/Lang` | ✅ | 7 |
| `Core/Request` | ✅ | 12 |
| `Core/Response` | ✅ | 7 |
| `Core/Controller` | ✅ | 4 |
| `Core/Model` | ✅ | 5 |
| `Core/Exception/HttpException` | ✅ via Response | — |
| `Middleware/AuthMiddleware` | ✅ | 3 |
| `Middleware/AdminMiddleware` | ✅ | 4 |
| `Middleware/GuestMiddleware` | ✅ | 3 |
| `Controller/AgeGateController` | ✅ | 7 |
| `Controller/AuthController` (validation) | ✅ | 7 |
| `helpers.php` | ✅ | 4 |
| `Service/MailService` | ❌ À faire | corps d'email (string logic, sans SMTP) |
| Futurs Controllers / Services | ❌ À faire | au fur et à mesure des features |

**Règle :** tout `if/else`, toute validation, toute transformation → TU.

---

## TI — Tests d'Intégration (PHPUnit + BDD réelle)

**Périmètre :** flows complets avec vraie BDD MySQL, isolation par transaction rollback (`IntegrationTestCase`).
**Quand écrire :** dès qu'une classe lit ou écrit en base de données.

| Classe | Statut | Tests |
|---|---|---|
| `Core/Database` | ✅ | 6 |
| `Model/AccountModel` | ✅ | 12 |
| `Model/ConnectionModel` | ✅ | 4 |
| `Model/PasswordResetModel` | ✅ | 5 |
| `Controller/AuthController` (POST actions) | ✅ | 16 |
| `Controller/AuthController` (GET forms) | ❌ À faire | loginForm, registerForm, forgotForm, resetForm |
| Futurs Models (Wine, Cart, Order…) | ❌ À faire | TI CRUD complet à chaque feature |
| Futurs Controllers | ❌ À faire | TI flow nominal + cas d'erreur à chaque PR |

**Règle :** tout nouveau `Model` → TI CRUD complet. Tout nouveau `Controller` → TI pour chaque action.

---

## E2E — Tests bout en bout (Playwright, Chromium)

**Périmètre :** parcours utilisateur dans le navigateur, serveur PHP réel + BDD.
**Contribution coverage :** JS uniquement (via Istanbul/nyc → `coverage/lcov.info`).
**Quand écrire :** à chaque feature front-end — 1 spec = parcours nominal + 1 cas d'erreur critique minimum.

| Flux | Statut | Fichier |
|---|---|---|
| Age Gate | ✅ 11 tests | `tests/E2E/age-gate.spec.js` |
| Auth : register → verify email | ❌ À faire | `tests/E2E/auth.spec.js` |
| Auth : login → logout | ❌ À faire | `tests/E2E/auth.spec.js` |
| Auth : forgot → reset password | ❌ À faire | `tests/E2E/auth.spec.js` |
| Catalogue : liste, fiche produit | ❌ À faire | avec `feat/catalogue` |
| Panier : add / update / remove | ❌ À faire | avec `feat/cart` |
| Checkout | ❌ À faire | avec `feat/order` |
| Espace client | ❌ À faire | avec `feat/account` |
| Admin | ❌ À faire | avec `feat/admin` |

**Règle :** E2E = parcours nominal uniquement. Les cas d'erreur détaillés restent dans TU/TI.

---

## Prochaines actions pour atteindre 80 %

1. **TI** — `AuthController` GET forms : `loginForm`, `registerForm`, `forgotForm`, `resetForm`
2. **TU** — `MailService` : `verificationBodyFr/En`, `resetBodyFr/En` (logique string pure)
3. **E2E** — `auth.spec.js` : register / login / logout / forgot / reset (~10 tests)

---

## Convention PR

- **Avant chaque merge :** `npm run lint` → `npm run build` → PHPCS → PHPStan → TU → TI → E2E → tout vert
- **Quality Gate SonarCloud :** new code coverage ≥ 80 % — bloque le merge si non respecté
- **Ratio cible :** 1 classe source = au moins 1 fichier de test (TU ou TI selon le cas)
