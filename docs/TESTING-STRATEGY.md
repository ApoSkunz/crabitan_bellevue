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

**Règles :**
- Tout `if/else`, toute validation, toute transformation → TU.
- 1 classe source = au moins 1 fichier de test.
- Nommer les méthodes : `test_{méthode}_{condition}_{résultatAttendu}`.

---

## TI — Tests d'Intégration (PHPUnit + BDD réelle)

**Périmètre :** flows complets avec vraie BDD MySQL, isolation par transaction rollback (`IntegrationTestCase`).
**Quand écrire :** dès qu'une classe lit ou écrit en base de données.

**Règles :**
- Tout nouveau `Model` → TI CRUD complet.
- Tout nouveau `Controller` → TI pour chaque action (nominal + cas d'erreur).
- Ne jamais mocker la BDD — les TI doivent frapper la vraie base de test.

---

## E2E — Tests bout en bout (Playwright, Chromium)

**Périmètre :** parcours utilisateur dans le navigateur, serveur PHP réel + BDD.
**Contribution coverage :** JS uniquement (via Istanbul/nyc → `coverage/lcov.info`).
**Quand écrire :** à chaque feature front-end — 1 spec = parcours nominal + 1 cas d'erreur critique minimum.

**Règles :**
- E2E = parcours nominaux uniquement. Les cas d'erreur détaillés restent dans TU/TI.
- 1 feature = 1 fichier spec dédié dans `tests/E2E/`.
- Les specs E2E sont liées aux features du BACKLOG — voir `EPIC-qualite-infrastructure/ENABLERS/e2e-coverage/`.

---

## Ratios cibles

| Type | Ratio cible |
|---|---|
| TU | 1 classe métier = 1 fichier test minimum |
| TI | 1 Model = TI CRUD · 1 Controller = TI par action |
| E2E | 1 feature front = 1 spec (nominal + 1 erreur critique) |
| Coverage global | ≥ 80 % (SonarCloud quality gate) |
