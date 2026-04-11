# Infection PHP — Mutants survivants documentés

> Modules ciblés : `src/Model/OrderModel.php`, `src/Service/PasswordValidator.php`, `src/Service/EncryptionService.php`
> Run de référence : sprint 8d — 2026-04-12
> MSI cible : ≥ 60 %

---

## Méthodologie

Infection génère des mutants en altérant le code source (opérateurs, conditions, constantes, retours).
Un mutant **survit** si aucun test ne le détecte (le test passe alors qu'il devrait échouer).
Un mutant est **tué** si au moins un test échoue à cause de la mutation.

---

## Mutants survivants attendus et justifications

### 1. `OrderModel::getRevenue()` — mutant sur `(float)`

**Mutation** : suppression du cast `(float)` → retour de la valeur brute string de BDD.
**Statut** : survivant probable — les TU mockent `fetchOne` avec des valeurs déjà typées `float`.
**Justification** : cast défensif contre les valeurs string MySQL (`'0.00'`). Comportement correct en production. Le TI d'intégration vérifie le type via BDD réelle.
**Action** : non bloquant — couvert par les TI. Ajout d'une assertion `assertIsFloat` dans `OrderModelTest` recommandé si le MSI global descend sous 60 %.

---

### 2. `OrderModel::countForUser()` / `countActiveOrdersForUser()` — mutant sur `?? 0`

**Mutation** : remplacement de `0` par `1` dans `$row['total'] ?? 0`.
**Statut** : survivant probable — les tests mockent `fetchOne` avec `['total' => 0]` explicitement.
**Justification** : chemin `$row === null` non testé en TU (BDD mockée). Couvert en TI.
**Action** : non bloquant. Le comportement défensif est correct.

---

### 3. `OrderModel::getAddressIdsWithActiveOrders()` / `hasActiveOrderForAddress()` — stubs

**Mutation** : toute mutation sur `return []` ou `return false`.
**Statut** : survivants intentionnels — ces méthodes sont des stubs documentés (NOSONAR php:S1172).
**Justification** : méthodes maintenues pour compatibilité interface après migration address-snapshot. Elles n'ont pas de logique à tester.
**Action** : exclure ces méthodes du périmètre Infection si le MSI est impacté. Ajout possible d'un `@infection-ignore-all` en annotation.

---

### 4. `PasswordValidator::isStrong()` — mutant sur `=== []`

**Mutation** : remplacement de `=== []` par `!== []` (inversion).
**Statut** : tué — `testIsStrongReturnsTrueWhenGetErrorsIsEmpty` détecte la mutation.
**Note** : documenté ici car il était susceptible de survivre avant les tests de cohérence `isStrong ↔ getErrors`.

---

### 5. `EncryptionService::decrypt()` — mutant sur longueur minimale

**Mutation** : remplacement de `self::IV_LENGTH + self::TAG_LENGTH` par une valeur inférieure.
**Statut** : tué — `testDecryptTooShortDataReturnsNull` vérifie explicitement ce cas limite.

---

### 6. `OrderModel::requestReturnForUser()` — mutant sur `CANCEL_WINDOW_DAYS`

**Mutation** : remplacement de `14` par `13` ou `15` dans le calcul du deadline.
**Statut** : tué — `testRequestReturnReturnsFalseWhenWindowExpired` (J-15) et `testRequestReturnReturnsTrueOnLastDay` (J+14 - 1min) couvrent les deux bornes.

---

### 7. `OrderModel::cancelForUser()` — mutant sur `in_array` strict

**Mutation** : suppression du troisième argument `true` (strict) dans `in_array`.
**Statut** : survivant probable — les tests utilisent uniquement des strings, le comportement est identique en mode non-strict pour ce cas.
**Justification** : le strict mode est une bonne pratique défensive. Pas de cas concret exploitable avec des strings pures.
**Action** : non bloquant.

---

## Résumé des actions correctives

| Mutant | Module | Statut | Action |
|---|---|---|---|
| Cast `(float)` sur retour BDD | `OrderModel::getRevenue` | Survivant attendu | Couvert en TI |
| `?? 0` → `?? 1` | `OrderModel::countForUser` | Survivant attendu | Couvert en TI |
| Stubs retournant `[]`/`false` | `OrderModel` (2 méthodes) | Intentionnel | Annotation `@infection-ignore-all` si besoin |
| `in_array` strict | `OrderModel::cancelForUser` | Survivant mineur | Non bloquant |

---

## Commande de reproduction

```bash
vendor/bin/infection --min-msi=60 --threads=4 --show-mutations
# Rapport HTML : build/infection/infection-report.html
```
