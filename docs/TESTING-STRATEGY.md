# Stratégie de tests — Crabitan Bellevue

> Les règles d'obligation (quand écrire, ratios cibles, objectif ≥ 80 %) sont dans `CLAUDE.md` § *Standards de code obligatoires*.
> Ce fichier documente les détails techniques : exclusions, conventions de nommage, outillage.

---

## Exclusions du coverage

Ces chemins sont exclus du calcul de couverture (SonarCloud + PHPUnit) :

- `src/View/**` — templates PHP
- `lang/**` — fichiers de traduction
- `database/**` — migrations SQL
- `config/**` — constantes de configuration
- `vendor/**`, `node_modules/**`

---

## TU — Conventions

**Convention de nommage des méthodes :** `test_{méthode}_{condition}_{résultatAttendu}`

```php
public function test_validate_emailVide_retourneFalse(): void { … }
public function test_calculate_remiseNulle_retournePrixBase(): void { … }
```

---

## TI — Outillage

- Isolation par **transaction rollback** via `IntegrationTestCase` (rollback après chaque test, BDD propre)
- Ne jamais mocker la BDD — les TI doivent frapper la vraie base de test (`crabitan_bellevue_test`)
- Credentials CI : `DB_HOST=127.0.0.1`, `DB_USER=root`, `DB_PASS=root`

---

## E2E — Outillage

- Navigateur : **Chromium** uniquement en CI (`--project=chromium`)
- Coverage JS : Istanbul/nyc → `coverage/lcov.info` (uploadé comme artifact CI)
- 1 feature = 1 fichier spec dans `tests/E2E/`
- E2E = parcours nominaux. Les cas d'erreur détaillés restent dans TU/TI
- Specs liées aux features BACKLOG : `EPIC-qualite-infrastructure/ENABLERS/e2e-coverage/`
