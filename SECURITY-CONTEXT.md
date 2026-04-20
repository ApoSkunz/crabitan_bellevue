# SECURITY-CONTEXT.md — Château Crabitan Bellevue

Contexte de sécurité destiné à l'audit Red Team.  
Dernière mise à jour : 2026-04-21.

---

## 1. Stack technique

| Couche | Technologie |
|---|---|
| Backend | PHP 8.4 — MVC custom (pas de framework) |
| Frontend | SCSS 7 layers + Vite 6 + Dart Sass |
| Base de données | MySQL 8 — PDO `EMULATE_PREPARES=false` |
| Serveur | Apache (XAMPP local) / IONOS mutualisé en prod |
| Auth | JWT maison (`src/Core/Jwt.php`) + cookies HttpOnly |
| OAuth | Google OAuth 2.0 (`src/Service/GoogleOAuthService.php`) |
| Paiement | CA e-Transactions (IPN RSA SHA-1, pas HMAC) |
| Envois email | PHPMailer — 4 canaux SMTP IONOS dédiés |
| Chiffrement données | AES-256-GCM (`src/Service/EncryptionService.php`) |
| Rate limiting | `src/Service/RateLimiterService.php` (APCu) |

---

## 2. Points d'entrée (entry points)

### 2.1 Point d'entrée unique
```
public/index.php
```
Toutes les requêtes passent par ce fichier. Le routeur MVC (`src/Core/Router.php`) dispatche vers les contrôleurs.

### 2.2 Routes sensibles

| Méthode | Route | Contrôleur | Auth requise |
|---|---|---|---|
| POST | `/fr/connexion` | `AuthController@login` | Aucune |
| POST | `/fr/inscription` | `AuthController@register` | Aucune |
| POST | `/fr/mot-de-passe-oublie` | `AuthController@forgotPassword` | Aucune |
| GET/POST | `/fr/reinitialisation/{token}` | `AuthController@resetPassword` | Token URL |
| GET | `/auth/google/callback` | `GoogleOAuthController@callback` | OAuth state |
| POST | `/payment/ipn` | `IpnController@handle` | Signature RSA CA |
| GET | `/cron/{job}` | `CronController@run` | Token interne `?token=` |
| POST | `/api/cart/add` | `CartApiController@add` | Session |
| POST | `/api/favorites/toggle` | `FavoriteApiController@toggle` | JWT cookie |
| GET | `/api/mfa/poll` | `MfaController@poll` | Session |
| POST | `/api/jeux/score` | `GameScoreController@save` | Optionnelle |
| POST | `/api/rating/commande` | `RatingController@rateViaTokenApi` | Token URL |
| GET/POST | `/admin/*` | `Admin\*Controller` | JWT + rôle admin/super_admin |
| GET/POST | `/fr/mon-compte/*` | `Account\*Controller` | JWT cookie customer |
| POST | `/fr/commande/passer` | `OrderController@place` | JWT customer + CSRF |

### 2.3 Endpoints sans authentification formelle
- `/payment/ipn` — server-to-server CA, vérifié par signature RSA mais pas d'auth HTTP
- `/cron/{job}` — protégé par token `?token=` en GET param (secret dans `.env`)
- `/api/rating/commande` — token dans l'URL (one-time use)
- `/fr/fiche-technique/{slug}` — PDF public, lié à la Loi Evin

---

## 3. Authentification & sessions

### 3.1 JWT maison
- Implémentation : `src/Core/Jwt.php`
- Stocké dans cookie `auth_token` : `HttpOnly=true`, `SameSite=Lax`, `Secure` conditionnel
- Payload : `{ id, role, email, exp }`
- Algorithme : HMAC-SHA256 (secret dans `$_ENV['JWT_SECRET']`)
- TTL : session login court + refresh token device

### 3.2 Trusted devices (MFA)
- Cookie `device_token` : `HttpOnly=true`, `SameSite=Strict`, TTL 90 jours
- Table `trusted_devices` en BDD
- MFA par code envoyé par email (TOTP-like, pas TOTP standard)
- Google OAuth → MFA si device non reconnu (Option A)

### 3.3 Guards dans les contrôleurs
- `$this->requireAdmin()` — vérifie JWT + rôle admin ou super_admin
- `$this->requireSuperAdmin()` — vérifie JWT + rôle super_admin uniquement
- `$this->requireCustomer()` — vérifie JWT + rôle customer
- Implémentés dans `src/Core/Controller.php` et `src/Controller/Admin/AdminController.php`

### 3.4 CSRF
- Token stocké en `$_SESSION['csrf']`
- `$this->verifyCsrf()` dans chaque action POST sensible
- **Attention** : méthode dupliquée dans ~21 contrôleurs (pas centralisée en `Core\Controller`)

---

## 4. Base de données

- PDO avec `EMULATE_PREPARES=false` → prepared statements natifs sur tous les modèles
- Toutes les requêtes passent par `src/Core/Database.php::query()` via `fetchOne/fetchAll/execute`
- **Exception connue** : quelques requêtes avec LIMIT/OFFSET utilisaient `(int)` cast direct (corrigé)
- Données sensibles (adresses, téléphones) chiffrées en AES-256-GCM en BDD (`EncryptionService`)

---

## 5. Upload de fichiers

### Zones d'upload (admin uniquement)
| Contrôleur | Type | Validation |
|---|---|---|
| `NewsAdminController` | Images articles | `finfo` MIME + whitelist extensions |
| `NewsletterAdminController` | PDF + images | `finfo` MIME strict |
| `OrderAdminController` | Documents commande | `finfo` MIME + `move_uploaded_file` |
| `WineAdminController` | Images vins | Via `ImageVariantService` |

Tous les uploads nécessitent `requireSuperAdmin()` ou `requireAdmin()`.

Destination : `public/assets/images/` (accessible publiquement).

---

## 6. Headers de sécurité

Appliqués par `src/Middleware/SecurityHeaderMiddleware.php` (appelé en premier dans `index.php`) :

```
Content-Security-Policy:
  default-src 'self'
  script-src 'self' 'nonce-{random}'
  style-src 'self' 'unsafe-inline'
  img-src 'self' data: https:
  font-src 'self'
  connect-src 'self' https://api.websitecarbon.com
  form-action 'self' https://tpeweb.e-transactions.fr ...
  frame-src https://www.google.com
  frame-ancestors 'none'
  base-uri 'self'
  object-src 'none'
```

`X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`, `HSTS` → dans `.htaccess`.

---

## 7. Paiement (CA e-Transactions)

- Flux : client → formulaire POST → TPE CA → IPN server-to-server → `/payment/ipn`
- Vérification signature IPN : RSA SHA-1 avec clé publique CA
- Idempotence : table `payment_intents` — double IPN détecté et ignoré
- Remboursement : API GAE (appel serveur CA), authentification par clés dans `.env`
- Fichiers clés : `src/Controller/IpnController.php`, `src/Service/PaymentService.php`

---

## 8. Chiffrement

| Donnée | Méthode |
|---|---|
| Adresses (rue, ville, téléphone) | AES-256-GCM (`EncryptionService`) |
| Mots de passe | `password_hash` Bcrypt |
| Tokens (reset, device, MFA) | `bin2hex(random_bytes(32))` |
| JWT secret | HMAC-SHA256 depuis `$_ENV['JWT_SECRET']` |
| Clé chiffrement BDD | `$_ENV['DB_ENCRYPT_KEY']` |

---

## 9. Age gate (Art. L3342-1 CSP)

- Cookie `age_verified=1` : `SameSite=Lax`, `HttpOnly=false` (lu côté JS pour tracking)
- Vérification côté serveur dans `index.php` avant dispatch
- Contournement légitime : crawlers SEO (validation DNS inverse), liens emails internes, admin, assets
- Bypass DNS inverse pour Googlebot en prod (reverse DNS IONOS KO → UA trust)

---

## 10. Zones non protégées / surfaces d'attaque connues

| Zone | Risque | Statut |
|---|---|---|
| `/cron/{job}?token=` | Token en clair dans l'URL (logs Apache) | Connu |
| `style-src 'unsafe-inline'` | CSP partielle (inline styles acceptés) | Connu |
| IPN RSA SHA-1 | SHA-1 déprécié (imposé par CA) | Connu — pas de choix |
| CSRF dupliqué ~21 controllers | Pas de protection centralisée | Connu, refacto prévu |
| `secure: false` sur `cb-cart` cookie | Cart count visible en HTTP local | Connu, non critique |
| Upload vers `public/assets/` | Fichiers servis publiquement après upload | Connu |

---

## 11. Audits précédents

| Version | Date | Score | Périmètre |
|---|---|---|---|
| v1 | 2026-02 | — | Audit initial |
| v2 | 2026-03 | — | Post-refactoring auth |
| v3 | 2026-04 | 7.2/10 | Pre-prod — 0 CRITIQUE, 5 P1 bloquants résolus |

P1 résolus en v3 : `unserialize` supprimé, session fixation corrigée, session options durcies, TTL trusted_devices, `/api/log` protégé.

Rapports complets : `private-docs/audits/Cyber/audit-cyber.md` (gitignored).

---

## 12. Arborescence critique

```
public/index.php          — entry point, age gate, middleware sécurité
src/Core/
  Jwt.php                 — HMAC-SHA256, génération/validation tokens
  Database.php            — PDO singleton, EMULATE_PREPARES=false
  Controller.php          — guards requireAdmin/requireCustomer/verifyCsrf
  Router.php              — dispatch routes
src/Middleware/
  SecurityHeaderMiddleware.php  — CSP nonce, headers sécurité
src/Service/
  EncryptionService.php   — AES-256-GCM
  RateLimiterService.php  — APCu rate limiting
  LoginService.php        — logique connexion, MFA, trusted devices
  PaymentService.php      — CA e-Transactions, remboursement GAE
src/Controller/
  AuthController.php      — login, register, forgot/reset password, MFA
  IpnController.php       — IPN CA, vérification RSA
  GoogleOAuthController.php — OAuth 2.0 Google
  CronController.php      — jobs cron HTTP, auth token GET param
  Api/                    — endpoints JSON (cart, favorites, MFA poll, session)
  Admin/                  — backoffice, tous super_admin/admin gated
  Account/                — espace client authentifié
config/
  routes.php              — toutes les routes de l'application
```
