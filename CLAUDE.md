# CLAUDE.md — Instructions projet crabitan_bellevue

## Procédure après chaque changement

Exécuter dans cet ordre avant tout push :

```bash
# 1. Build + linter JS/SCSS
npm run lint
npm run build

# 2. Qualité PHP
vendor/bin/phpcs --standard=PSR12 src/ config/ public/index.php tests/
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
- `git add` / `git mv` / `git rm` les fichiers concernés
- `git commit` avec message conventionnel (feat/fix/refactor/chore...)
- `git push origin <branche>`

Pas besoin de confirmation supplémentaire.

## Conventions

- Commits : [Conventional Commits](https://www.conventionalcommits.org/) — `feat(scope):`, `fix(scope):`, etc.
- Co-author sur chaque commit : `Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>`
- PHPCS PSR12 — les warnings "side effects" sur les fichiers d'entrée (`public/index.php`, `config/config.php`) sont acceptables
- Faux positifs Semgrep : annoter avec `// nosemgrep: <rule-id>`
- Ne jamais utiliser `--no-verify`, `--force-push` sur main, ni amender un commit déjà pushé

## Stack

- PHP 8.4, MVC custom (Core\Router, Controller, Response, Lang, JWT)
- SCSS 7 layers + Vite 6 + Dart Sass
- Tokens : `--color-bg`, `--color-surface`, `--color-gold`, `--color-gold-light`, `--color-text`, `--color-text-muted`
- Tests : PHPUnit (`tests/Unit/`, `tests/Integration/`) + Playwright (`tests/E2E/`)
