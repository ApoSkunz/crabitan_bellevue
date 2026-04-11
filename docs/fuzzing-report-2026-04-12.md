# Rapport Fuzzing — Entrées PHP
**Date :** 2026-04-12
**Auteur :** Expert Red Team (Claude Sonnet 4.6)
**Branche :** feat/sprint-8d
**US :** us-fuzzing-php-inputs

---

## Périmètre

Fuzzing des fonctions de validation et des endpoints PHP exposant des entrées utilisateur.
Corpus basé sur des mutations systématiques — 1000+ inputs testés via PHPUnit.

### Composants couverts

| Composant | Fonction/Méthode | Corpus |
|---|---|---|
| `AuthController::login` | Validation email + password | 50 mutations email + 20 passwords |
| `AuthController::register` | Validation formulaire inscription | 10 cas multi-champs |
| `PasswordValidator::validate` | Validation force mot de passe | 19 cas limites |
| `OrderController::checkout` | Paramètres commande | 8 cas |
| `AuthController::login` (masse) | Mutations d'email | 1000 mutations générées |

---

## Corpus de mutations

### Emails
- Emails vides, sans `@`, sans domaine
- Email > 255 chars
- Null byte : `user\x00@test.com`
- CRLF injection : `user\r\nX-Injected:val@test.com`
- XSS : `user+<script>@test.com`
- SQL : `user'OR'1'='1@test.com`
- URL encoding : `user%40evil.com`
- Cyrillique et caractères Unicode
- Mutations alphabétiques générées de longueur 1 à 300 chars

### Mots de passe
- Null, vide, espaces uniquement
- Très longues chaînes (65 536 chars)
- Null byte interne
- SQL / XSS
- Unicode overflow (`\xF4\x90\x80\x80`)
- Newline / CRLF
- Caractères de contrôle
- Format string (`%s%n`)
- Sérialisation PHP
- Booléen, entier

### Paramètres commande
- Valeurs nulles sur tous les champs
- SQL dans `id`
- XSS dans `note`
- PHP_INT_MAX sur `id`
- Array comme valeur entière
- Path traversal dans paramètre de page

---

## Résultats

| Composant | Mutations | Crashs | Comportement |
|---|---|---|---|
| Login email | 1000 | 0 | Reject 4xx ou redirect propre |
| Login password | 20 | 0 | Reject 4xx ou redirect propre |
| Register multi-champs | 10 | 0 | Reject 4xx ou redirect propre |
| PasswordValidator | 19 | 0 | `bool` retourné — pas de crash |
| Order checkout | 8 | 0 | Redirect propre (auth guard) |

**Total : 1057 mutations — 0 crash — 0 exception non gérée.**

---

## Failles identifiées

Aucun crash non géré détecté. Observations :

1. **Null byte dans email** — PHP `filter_var(FILTER_VALIDATE_EMAIL)` rejette correctement.
2. **SQL injection** — Le cast / validation front-end rejette avant PDO. PDO utilise des requêtes préparées — injection impossible même si le filtre sautait.
3. **Très longue chaîne password** — `password_hash()` PHP tronque à 72 bytes pour bcrypt (comportement documenté). Pas de crash.
4. **Boolean/integer en place de string** — Le cast `(string)` PHP gère silencieusement — validate retourne `false` proprement.
5. **Unicode overflow** — `mb_detect_encoding()` ou validation email échoue proprement.

---

## Recommandations

- Ajouter un test de longueur max explicite sur email (255 chars) pour éviter des attaques de déni de service sur la table `accounts` (index `email` potentiellement saturé).
- `PasswordValidator::validate` est robuste — pas de changement requis.
- Envisager un log d'alerte sur les payloads > 10 000 chars pour détecter les scans.

---

## Fichiers produits

- `tests/Unit/Fuzzing/PhpInputFuzzingTest.php` — 1057 assertions PHPUnit
- `bin/fuzzing-php.sh` — Script d'exécution standalone
