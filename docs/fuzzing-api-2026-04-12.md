# Rapport Fuzzing — Routes API HTTP
**Date :** 2026-04-12
**Auteur :** Expert Red Team (Claude Sonnet 4.6)
**Branche :** feat/sprint-8d
**US :** us-fuzzing-api-routes

---

## Périmètre

Routes POST/PUT/DELETE testées par instanciation directe des controllers avec superglobales malformées.
Pas de serveur actif requis — PHPUnit simule les requêtes.

### Routes couvertes

| Route | Méthode | Controller | Cas testés |
|---|---|---|---|
| `/api/cart/add` | POST | `CartApiController::add` | 12 payloads + CSRF forgé/absent + header manquant |
| `/api/cart/count` | GET | `CartApiController::count` | JWT absent, malformé, expiré, admin bloqué |
| `/api/cart/remove` | POST | `CartApiController::remove` | 5 payloads (null, SQL, path traversal, overflow) |
| `/age-gate` | POST | `AgeGateController::confirm` | 9 payloads (SQL, XSS, null byte, unicode, overflow) |
| `/admin` | GET | `DashboardController::index` | sans auth → 4xx attendu |
| `/admin/vins` | GET | `WineAdminController::index` | JWT malformé → 4xx attendu |
| `/admin/vins/{id}/disponibilite` | POST | `WineAdminController::toggleAvailable` | CSRF forgé → 403 attendu |
| `/admin/vins/{id}/featured` | POST | `WineAdminController::setFeatured` | CSRF forgé → 403 attendu |

---

## Corpus de payloads

Payloads systématiques couvrant les catégories OWASP :

- **Valeurs nulles** : `null`, `0`, `false`, `''`
- **Types incorrects** : float, bool, array comme entier, string comme int
- **SQL Injection** : `' OR '1'='1`, `1; DROP TABLE wines--`, `' UNION SELECT *`
- **XSS** : `<script>alert(1)</script>`, `<img src=x onerror=alert(1)>`
- **CSRF forgé** : token présent mais différent du session token
- **JWT malformé** : `not.a.valid.jwt.token`, JWT with alg:none, JWT expiré
- **Overflow** : `PHP_INT_MAX`, `str_repeat('A', 65536)`
- **Null byte** : `"value\x00injected"`
- **Newline injection** : `"value\r\nX-Injected: header"`
- **Path traversal** : `../../../etc/passwd`
- **PHP serialization** : `O:8:"stdClass":0:{}`
- **Format string** : `%s%s%s%s%n%n`

---

## Résultats

| Catégorie | Cas testés | Crashs 500 | Rejets 4xx | Status |
|---|---|---|---|---|
| CartApiController payloads | 12 | 0 | 12 | ✅ |
| CSRF forgé/absent | 2 | 0 | 2 (403) | ✅ |
| JWT absent/malformé/expiré | 3 | 0 | 3 (401) | ✅ |
| Admin JWT bloqué | 1 | 0 | 1 (403) | ✅ |
| CartRemove fuzz | 5 | 0 | 5 (401/422) | ✅ |
| AgeGate fuzz | 9 | 0 | 9 (4xx/redirect) | ✅ |
| Admin sans auth | 2 | 0 | 2 (401/403) | ✅ |
| Admin CSRF forgé | 2 | 0 | 2 (403) | ✅ |
| Headers manquants | 2 | 0 | 2 (401/403) | ✅ |

**Total : 38 cas — 0 crash non géré — 0 réponse 500 non intentionnelle.**

---

## Failles identifiées

Aucune faille critique détectée. Les guards suivants sont opérationnels :

- Vérification CSRF via `hash_equals()` (résistant timing-attack)
- Vérification JWT via `Jwt::decode()` avec signature HMAC-SHA256
- Validation des types d'entrées via cast `(int)` avant utilisation
- Retours `HttpException` structurés pour tous les cas d'erreur

---

## Recommandations

- Ajouter un rate limiting sur `/api/cart/add` (actuellement protégé JWT mais pas rate limité)
- Considérer un canary token dans les erreurs 422 pour détecter les scans automatisés
- Les erreurs de CSRF retournent 403 JSON — cohérent avec l'API REST

---

## Fichiers produits

- `tests/Unit/Fuzzing/ApiRoutesFuzzingTest.php` — 38 assertions PHPUnit
