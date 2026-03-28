# CLAUDE.md — Instructions projet crabitan_bellevue

## Stack technique

- **Backend** : PHP 8.4, MVC custom (`Core\Router`, `Controller`, `Response`, `Lang`, `JWT`)
- **Frontend** : SCSS 7 layers + Vite 6 + Dart Sass
- **Tokens CSS** : `--color-bg`, `--color-surface`, `--color-gold`, `--color-gold-light`, `--color-text`, `--color-text-muted`
- **Tests** : PHPUnit (`tests/Unit/`, `tests/Integration/`) + Playwright (`tests/E2E/`)
- **BDD** : MySQL 8, schéma et migrations en local uniquement (`database/` gitignored)
- **Serveur local** : XAMPP sur `http://crabitan.local/`

---

## Équipe de développement

Ce projet est réalisé en équipe pluridisciplinaire. Chaque rôle est tenu par un expert dédié :

| Rôle | Responsabilités |
|---|---|
| **Expert PHP** | Architecture MVC, Controllers, Models, JWT, routing, logique métier, TU/TI PHPUnit |
| **Expert Frontend SCSS** | SCSS 7 layers, Vite, tokens CSS, responsive, animations, accessibilité |
| **Expert DevSecOps** | CI/CD GitHub Actions, SonarCloud, Semgrep, TruffleHog, Legitify, SCA, secrets, PHPCS/PHPStan |
| **Expert UX/UI Designer** | Maquettes, cohérence visuelle, charte graphique, expérience utilisateur |
| **Scrum / Product Owner** | Backlog, priorisation, rédaction des US et acceptance criteria |
| **Expert QA** | Stratégie de tests, rédaction TU/TI/E2E, couverture SonarCloud ≥ 80%, non-régression |
| **Expert Red Team** | Analyse des failles applicatives (OWASP, injection, auth bypass, XSS, CSRF, IDOR…), pentest |
| **Expert Blue Team** | Durcissement applicatif, monitoring, réponse aux rapports Red Team |
| **Architecte Génie Logicielle** | Patterns, SOLID, couplage/cohésion, revue d'architecture MVC, refactoring structurel |
| **Architecte BDD MySQL** | Schéma, normalisation, index, performances, migrations, intégrité référentielle |

Chaque contribution doit respecter les standards de son domaine. Les failles détectées par le Red Team sont traitées avec le Blue Team **avant tout merge**.

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
| Backlog, US, acceptance criteria, priorisation | **Scrum / Product Owner** |
| Bug dont la cause est inconnue | **Expert PHP** en premier, puis **Expert Red Team** si suspicion sécurité |

**Règles d'application :**
- Mentionner explicitement l'expert dans la réponse (ex : *"Expert PHP — …"*) quand son domaine est engagé.
- Un changement multi-couches (ex : Controller + Vue + SCSS) mobilise chaque expert dans l'ordre : PHP → Frontend → QA.
- Toute faille Red Team doit être corrigée par le Blue Team **avant** de continuer.
- Bug inexpliqué → l'Expert PHP mène le diagnostic avant toute modification de code.

---

## Workflow

### 1. Vérifications avant push

Exécuter dans cet ordre :

```bash
# Build + linter JS/SCSS
npm run lint
npm run build

# Qualité PHP
vendor/bin/phpcs
php -d memory_limit=512M vendor/phpstan/phpstan/phpstan.phar analyse --configuration=phpstan.neon

# Tests unitaires
vendor/bin/phpunit tests/Unit/

# Tests intégration (nécessite BDD active)
vendor/bin/phpunit tests/Integration/

# Tests E2E (nécessite serveur XAMPP actif sur APP_URL)
npx playwright test
```

Rapporter le résultat. Si tout est vert, attendre le mot **"go push"**.

### 2. Branches

Convention de nommage :

| Préfixe | Usage |
|---|---|
| `feat/` | Nouvelle fonctionnalité |
| `fix/` | Correction de bug |
| `refactor/` | Refactoring sans changement de comportement |
| `chore/` | Maintenance, CI, dépendances, config |
| `docs/` | Documentation uniquement |

Une branche = un sujet. Ne jamais travailler directement sur `main`.

### 3. Commits

Quand l'utilisateur dit **"go push"** :
- Découper en **commits atomiques** — un commit = une responsabilité
- `git add` fichier par fichier (jamais `git add .` en bloc)
- Message au format [Conventional Commits](https://www.conventionalcommits.org/) : `feat(scope):`, `fix(scope):`, etc.
- Co-author sur chaque commit : `Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>`
- `git push origin <branche>`

Pas besoin de confirmation supplémentaire.

**Ordre conseillé des commits par couche :**

| Commit | Contenu typique |
|---|---|
| `chore(db):` | schema.sql, migrations, seeds |
| `feat(model):` | nouveau Model ou méthodes ajoutées |
| `feat(controller):` | Controller(s) créés ou modifiés |
| `feat(view):` | Vues / templates |
| `feat(routes):` | config/routes.php |
| `feat(i18n):` | lang/fr.php, lang/en.php |
| `feat(ci):` | GitHub Actions workflows |
| `docs:` | README, CLAUDE.md |

Un seul fichier modifié peut faire l'objet d'un commit séparé si son changement est orthogonal aux autres.

### 4. Annotations qualité

- **PHPCS PSR12** — warnings "side effects" sur `public/index.php` et `config/config.php` acceptables
- **Faux positifs SonarCloud** : `// NOSONAR — <justification courte>` (justification obligatoire)
- **Faux positifs Semgrep** : `// nosemgrep: <rule-id>`

---

## Règles absolues

Ces règles ne souffrent **aucune exception** :

| Interdit | Raison |
|---|---|
| `--no-verify` | Contourne les hooks de qualité |
| `git push --force` sur `main` | Écrase l'historique partagé |
| Amender un commit déjà pushé | Réécrit l'historique distant |
| `git add .` en bloc | Risque d'inclure `.env`, fichiers sensibles ou binaires |
| Merger sans revue Red/Blue Team si faille détectée | Introduit une vulnérabilité en production |
| Commiter `.env` ou toute valeur de secret | Exposition définitive des credentials |

---

## Backlog

Le backlog est géré localement dans `BACKLOG/` (gitignored). Le PDF complet est générable via `php BACKLOG/scripts/generate_backlog_pdf.php`.

### Structure

```
BACKLOG/
  README.md                        ← synthèse macro tous EPICs
  scripts/                         ← outils locaux (generate_backlog_pdf.php…)
  EPIC-{nom}/
    README.md                      ← objectif, périmètre, suivi 4 colonnes par feature
    FEATURES/{feature}/
      README.md                    ← routes, branche git, suivi 4 colonnes par US
      us-{sujet}.md                ← User Story : rôle / action / bénéfice + critères d'acceptation
    ENABLERS/{enabler}/
      README.md                    ← contexte technique, suivi 4 colonnes par US
      us-{sujet}.md                ← tâche technique découpée
```

### Colonnes de suivi d'avancement

Chaque README contient un tableau avec 4 colonnes :

| Colonne | Responsable | Signification |
|---|---|---|
| 🤖 Claude | Claude IA | Implémenté et fonctionnel selon l'auto-évaluation |
| ✅ PO | Alexandre | Recette métier validée par le PO |
| 🎭 E2E | Expert QA | Tests Playwright couvrant nominal + erreur |
| 🚀 Livré | DevSecOps | Mergé sur main, déployable en production |

États : `✅ Fait` · `🔄 En cours` · `⬜ À faire` · `❌ Bloqué`

### Mise à jour du statut (Claude)

**Claude met à jour la colonne 🤖 en temps réel**, dès qu'une US est implémentée :
- US terminée → `✅ Fait` dans `us-{sujet}.md` ET dans le tableau du `README.md` de la feature/enabler
- Feature complète → mettre à jour le `README.md` de l'EPIC
- US en cours → `🔄 En cours`
- US bloquée → `❌ Bloqué` + note explicative

Ne jamais laisser un statut obsolète.

### Rôle Scrum/PO

- Rédige ou met à jour les fichiers `BACKLOG/` pour toute nouvelle feature, US ou enabler
- Met à jour le `README.md` de l'EPIC et de la feature concernés après chaque livraison
- Ne pas modifier manuellement `BACKLOG/README.md` — le regénérer si les totaux changent

**Si Alexandre exprime un besoin non tracé**, le Scrum/PO doit immédiatement :
1. Identifier l'EPIC concerné (ou en créer un)
2. Créer le `us-{sujet}.md` avec rôle / action / bénéfice + critères d'acceptation
3. Mettre à jour les README de la feature/enabler et de l'EPIC
4. Signaler à Alexandre que l'US a été ajoutée avant de continuer

Ne jamais ignorer un besoin en supposant qu'il sera tracé plus tard.

### Audit cyber

L'enabler `BACKLOG/EPIC-qualite-infrastructure/ENABLERS/audit-cyber/` est à réaliser **en dernier**, après que tous les autres EPICs sont `✅ Claude`. Claude IA conduit l'audit (Expert Red Team) et produit le rapport dans `docs/audit-cyber-[date].md`.
