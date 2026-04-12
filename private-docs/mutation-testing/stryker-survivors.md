# Stryker — Mutants survivants documentés

> Généré manuellement après analyse statique + exécution Stryker.
> Mettre à jour après chaque run `npm run mutation:js`.

## Périmètre analysé

| Module | Mutants tués | Mutants survivants | MSI |
|---|---|---|---|
| `resources/js/cart-utils.js` | à compléter | voir ci-dessous | à compléter |
| `resources/js/error-utils.js` | à compléter | voir ci-dessous | à compléter |

---

## Survivants identifiés (analyse statique pré-run)

### `resources/js/cart-utils.js`

#### `serializeCartCookie` — paramètre `path`

```js
export function serializeCartCookie(cart, path = '/', maxAge = CART_MAX_AGE) {
    const val = encodeURIComponent(JSON.stringify(cart));
    return `${CART_KEY}=${val}; path=${path}; max-age=${maxAge}; SameSite=Lax`;
}
```

**Mutant potentiel :** suppression du paramètre `path` dans la chaîne résultante.
**Raison survie :** le test vérifie `max-age` et `SameSite=Lax` mais pas la valeur de `path=` explicitement.
**Décision :** acceptable — `path=/` est un comportement par défaut standard, non critique fonctionnellement.

---

#### `getCartCount` — opérateur `||`

```js
return cart.reduce((sum, i) => sum + (i.qty || 1), 0);
```

**Mutant potentiel :** remplacement de `||` par `&&`.
**Raison survie :** le test couvre `qty: 0` et `qty: undefined`, ce mutant sera tué.
**Décision :** couvert.

---

### `resources/js/error-utils.js`

#### `buildRejectionPayload` — opérateur `instanceof`

```js
const message = reason instanceof Error
    ? reason.message
    : String(reason ?? 'Unhandled Promise Rejection');
```

**Mutant potentiel :** négation de `instanceof Error` (inverser la condition).
**Raison survie :** les tests couvrent Error + string + null → mutant tué.
**Décision :** couvert.

---

#### `shouldIgnore` — opérateur logique `&&` ligne 2

```js
if (source && !source.includes(hostname)) return true;
```

**Mutant potentiel :** supprimer le `&&` / remplacer par `||`.
**Raison survie :** le test couvre source présente + hors domaine + source dans domaine → tué.
**Décision :** couvert.

---

## Survivants réels (à compléter après run Stryker)

Après `npm run mutation:js`, compléter ce tableau :

| Fichier | Ligne | Mutant | Décision |
|---|---|---|---|
| — | — | — | — |

---

## Décisions acceptées (no-kill volontaires)

| Mutant | Justification |
|---|---|
| Suppression du `;` dans la chaîne cookie (`serializeCartCookie`) | Comportement navigateur tolérant, non testable unitairement sans DOM |
| Mutation du message par défaut `'Unhandled Promise Rejection'` | Chaîne littérale i18n non critique, test de valeur ajouterait fragilité |

---

## Historique des runs

| Date | MSI cart-utils | MSI error-utils | MSI global | Commit |
|---|---|---|---|---|
| 2026-04-12 | à compléter | à compléter | à compléter | — |
