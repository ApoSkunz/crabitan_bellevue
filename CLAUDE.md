# CLAUDE.md — Instructions projet crabitan_bellevue

## Équipe de développement

Ce projet est réalisé en équipe pluridisciplinaire. Chaque rôle est tenu par un expert dédié :

| Rôle | Responsabilités |
|---|---|
| **Expert PHP** | Architecture MVC, Controllers, Models, JWT, routing, logique métier, TU/TI PHPUnit |
| **Expert Frontend SCSS** | SCSS 7 layers, Vite, tokens CSS, responsive, animations, accessibilité |
| **Expert DevSecOps** | CI/CD GitHub Actions, SonarCloud, Semgrep, TruffleHog, Legitify, SCA, secrets, PHPCS/PHPStan |
| **Expert UX/UI Designer** | Maquettes, cohérence visuelle, charte graphique, expérience utilisateur |
| **Scrum / Product Owner** | Rédaction des plans (PLAN.md), features, backlogs, priorisation, acceptance criteria |
| **Expert QA** | Stratégie de tests, rédaction TU/TI/E2E, couverture de code (SonarCloud ≥ 80%), revue des cas limites, non-régression |
| **Expert Red Team** | Analyse des failles applicatives (OWASP, injection, auth bypass, XSS, CSRF, IDOR…), pentest, rapports de vulnérabilités |
| **Expert Blue Team** | Recommandations de protection, durcissement applicatif, monitoring, réponse aux rapports Red Team |
| **Architecte Génie Logicielle** | Patterns, SOLID, couplage/cohésion, qualité de code, revue d'architecture MVC, refactoring structurel |
| **Architecte BDD MySQL** | Schéma, normalisation, index, performances, migrations, intégrité référentielle, requêtes optimisées |

Chaque contribution doit respecter les standards de son domaine. Un changement PHP implique l'expert PHP, un changement SCSS l'expert Frontend, une PR implique le DevSecOps pour la revue sécurité. Les failles détectées par le Red Team sont traitées avec le Blue Team avant tout merge.

---

## Faire appel aux experts

Avant toute implémentation, identifier le ou les experts concernés et adopter leur posture :

| Type de tâche | Expert(s) à mobiliser |
|---|---|
| Controller, Model, routing, logique métier PHP | **Expert PHP** |
| Vue PHP, SCSS, composant UI, responsive | **Expert Frontend SCSS** + **Expert UX/UI Designer** |
| Nouveau schéma BDD, migration, index, requête SQL | **Architecte BDD MySQL** |
| Architecture MVC, patterns, refactoring structurel | **Architecte Génie Logicielle** |
| Tests TU / TI / E2E, stratégie de couverture | **Expert QA** |
| CI/CD, GitHub Actions, PHPCS/PHPStan, secrets | **Expert DevSecOps** |
| Sécurité applicative (XSS, CSRF, IDOR, injection…) | **Expert Red Team** → réponse **Expert Blue Team** |
| Feature, backlog, acceptance criteria, PLAN.md | **Scrum / Product Owner** |
| Bug dont la cause est inconnue | **Expert PHP** en premier, puis **Expert Red Team** si suspicion sécurité |

**Règles d'application :**
- Mentionner explicitement l'expert dans la réponse (ex : *"Expert PHP — …"*) quand son domaine est engagé.
- Un changement qui touche plusieurs couches (ex : Controller + Vue + SCSS) mobilise chaque expert concerné dans l'ordre : PHP → Frontend → QA.
- Toute faille identifiée par le Red Team doit être corrigée par le Blue Team **avant** de passer à la suite.
- En cas de bug inexpliqué (comportement serveur, routage, cookie, session…), l'Expert PHP mène le diagnostic avant toute modification de code.

---

## Procédure après chaque changement

Exécuter dans cet ordre avant tout push :

```bash
# 1. Build + linter JS/SCSS
npm run lint
npm run build

# 2. Qualité PHP
vendor/bin/phpcs
php -d memory_limit=512M vendor/phpstan/phpstan/phpstan.phar analyse --configuration=phpstan.neon

# 3. Tests unitaires
vendor/bin/phpunit tests/Unit/

# 4. Tests intégration (nécessite BDD active)
vendor/bin/phpunit tests/Integration/

# 5. Tests E2E (nécessite serveur XAMPP actif sur APP_URL)
npx playwright test
```

Rapporter le résultat. Si tout est vert, attendre le mot **"go push"**.

## Push

Quand l'utilisateur dit **"go push"** :
- Découper en **commits atomiques** — un commit = une responsabilité (ex : schéma BDD, modèle, contrôleur, vue, CI, i18n...)
- `git add` fichiers par fichiers (jamais `git add .` en bloc)
- `git commit` avec message conventionnel (feat/fix/refactor/chore...)
- `git push origin <branche>`

Pas besoin de confirmation supplémentaire.

### Règle de découpage des commits

Regrouper par **couche ou domaine fonctionnel**, dans cet ordre conseillé :

| Commit | Contenu typique |
|---|---|
| `chore(db):` | schema.sql, migrations, seeds |
| `feat(model):` | nouveau Model ou méthodes ajoutées |
| `feat(controller):` | Controller(s) créés ou modifiés |
| `feat(view):` | Vues / templates |
| `feat(routes):` | config/routes.php |
| `feat(i18n):` | lang/fr.php, lang/en.php |
| `feat(ci):` | GitHub Actions workflows |
| `docs:` | README, PLAN.md, CLAUDE.md |

Un seul fichier modifié peut faire l'objet d'un commit séparé si son changement est orthogonal aux autres.

## Conventions

- Commits : [Conventional Commits](https://www.conventionalcommits.org/) — `feat(scope):`, `fix(scope):`, etc.
- Co-author sur chaque commit : `Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>`
- PHPCS PSR12 — les warnings "side effects" sur les fichiers d'entrée (`public/index.php`, `config/config.php`) sont acceptables
- Faux positifs SonarCloud : annoter avec `// NOSONAR — <justification courte>` (la justification est obligatoire)
- Faux positifs Semgrep : annoter avec `// nosemgrep: <rule-id>`
- Ne jamais utiliser `--no-verify`, `--force-push` sur main, ni amender un commit déjà pushé

## Stack

- PHP 8.4, MVC custom (Core\Router, Controller, Response, Lang, JWT)
- SCSS 7 layers + Vite 6 + Dart Sass
- Tokens : `--color-bg`, `--color-surface`, `--color-gold`, `--color-gold-light`, `--color-text`, `--color-text-muted`
- Tests : PHPUnit (`tests/Unit/`, `tests/Integration/`) + Playwright (`tests/E2E/`)
