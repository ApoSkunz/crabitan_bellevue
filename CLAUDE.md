# CLAUDE.md — Instructions projet crabitan_bellevue

## Initialisation de session

Au début de chaque nouvelle session, lire systématiquement :

1. `CLAUDE.md` (ce fichier) — automatiquement chargé par Claude Code
2. `docs/SETUP-DEV.md` — environnement local, variables `.env`, commandes
3. `docs/TESTING-STRATEGY.md` — règles TU/TI/E2E, ratios cibles
4. `docs/CHARTE-GRAPHIQUE.md` — palette, typographie, tokens CSS, composants, SCSS 7 layers

> Ces lectures garantissent un contexte complet avant toute modification de code.

---

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
| **Scrum Master** | Animation des cérémonies, vélocité, gestion des impediments, coordination inter-équipes |
| **Product Owner** | Backlog, priorisation, rédaction des US et acceptance criteria, vision produit |
| **Expert Marketing** | Copywriting, ton de marque, emailings, newsletters, tunnels de conversion, SEO éditorial |
| **Expert QA** | Stratégie de tests, rédaction TU/TI/E2E, couverture SonarCloud ≥ 80%, non-régression |
| **Expert Red Team** | Analyse des failles applicatives (OWASP, injection, auth bypass, XSS, CSRF, IDOR…), pentest |
| **Expert Blue Team** | Durcissement applicatif, monitoring, réponse aux rapports Red Team |
| **Architecte Génie Logicielle** | Patterns, SOLID, couplage/cohésion, revue d'architecture MVC, refactoring structurel |
| **Architecte BDD MySQL** | Schéma, normalisation, index, performances, migrations, intégrité référentielle |
| **Expert RGPD** | Conformité RGPD/CNIL, bases légales des traitements, droits des personnes (Art. 15-22), durées de conservation, cookies ePrivacy, registre Art. 30, DPIA, DPA sous-traitants, notification violations 72h, privacy by design |
| **Expert Juridique** | Droit français et européen : Loi Evin (publicité alcool, mentions obligatoires), Code de la consommation (L111-1, L221-5/11/18, droit de rétractation 14 j), LCEN (mentions légales, hébergeur), Code de commerce (L441-9 facturation, L123-22 archivage 10 ans), RGAA/accessibilité, propriété intellectuelle |

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
| Conformité RGPD, consentement, droits des personnes, cookies | **Expert RGPD** |
| Mentions légales, CGV, droit de rétractation, Loi Evin, facturation | **Expert Juridique** |
| Données personnelles dans les traitements (newsletter, commandes, profil…) | **Expert RGPD** + **Expert Juridique** |
| Backlog, US, acceptance criteria, vision produit | **Product Owner** |
| Animation Scrum, vélocité, impediments | **Scrum Master** |
| Copywriting, emailings, newsletters, ton de marque | **Expert Marketing** |
| Bug dont la cause est inconnue | **Expert PHP** en premier, puis **Expert Red Team** si suspicion sécurité |

**Règles d'application :**
- Mentionner explicitement l'expert dans la réponse (ex : *"Expert PHP — …"*) quand son domaine est engagé.
- Un changement multi-couches (ex : Controller + Vue + SCSS) mobilise chaque expert dans l'ordre : PHP → Frontend → QA.
- Toute faille Red Team doit être corrigée par le Blue Team **avant** de continuer.
- Bug inexpliqué → l'Expert PHP mène le diagnostic avant toute modification de code.

---

## Standards de code obligatoires

Ces règles s'appliquent **à chaque nouvelle classe ou méthode**, sans exception.

### PHPDoc

Toute classe et toute méthode publique doit avoir un bloc PHPDoc :

```php
/**
 * Courte description de la classe.
 */
class MonController extends Controller
{
    /**
     * Courte description de l'action.
     *
     * @param int $id Identifiant de la ressource
     * @return Response
     */
    public function show(int $id): Response { … }
}
```

- `@param` et `@return` obligatoires sur les méthodes publiques
- `@throws` si la méthode peut lever une exception
- Les méthodes privées/protégées simples : PHPDoc recommandé, non bloquant

### Tests (TU / TI)

**Objectif coverage : ≥ 80 % (quality gate SonarCloud)**

| Règle | Obligation |
|---|---|
| Toute classe avec logique (`if`, validation, transformation) | → TU dans `tests/Unit/` |
| Tout nouveau `Model` | → TI CRUD complet dans `tests/Integration/` |
| Tout nouveau `Controller` | → TI par action (nominal + cas d'erreur) |
| Toute feature front | → spec E2E Playwright (nominal + 1 erreur critique) |

Les tests **ne se reportent pas** — ils sont écrits dans le même commit que le code qu'ils couvrent.

---

## Workflow

### 1. Méthode TDD — Red → Green → Refactor

Toute nouvelle logique métier (service, model, controller) est développée en **TDD strict** :

| Phase | Action |
|---|---|
| 🔴 **Red** | Écrire le(s) test(s) qui décrivent le comportement attendu — ils échouent (classe/méthode inexistante) |
| 🟢 **Green** | Implémenter le minimum de code pour faire passer les tests — sans sur-ingénierie |
| 🔵 **Refactor** | Nettoyer le code (nommage, extraction, PHPDoc) sans casser les tests |

**Règles d'application :**
- Un cycle Red/Green/Refactor par comportement unitaire (pas par fichier entier)
- Les tests TDD remplacent les TU/TI de l'étape 2 — ils sont écrits **avant** le code de production
- Exception acceptée : vues PHP, SCSS, i18n — pas de TDD, tests E2E suffisent
- Si la logique est triviale (getter pur, constante), un test post-hoc est acceptable

### 2. Ordre des tâches par feature

Pour chaque feature ou correction, respecter cet ordre **sans exception** :

| Étape | Contenu |
|---|---|
| **1. TDD Red** | Écrire les TU/TI décrivant le comportement attendu (tests échouent) |
| **2. TDD Green** | Implémenter le code (Controller, Model, Service…) + PHPDoc pour faire passer les tests |
| **3. TDD Refactor** | Nettoyer — PHPCS/PHPStan doivent être verts |
| **4. Vue / i18n / SCSS** | Vues PHP, clés de traduction, styles (pas de TDD — E2E couvre) |
| **5. BACKLOG** | Créer l'US si non tracée · Mettre à jour la colonne 🤖 dans `us-*.md` et `README.md` · **obligatoire avant le commit** |
| **6. E2E** | Écrire et passer la spec Playwright (nominal + 1 erreur critique) |
| **7. Commit** | `git add` fichier par fichier · commit(s) atomiques (push local uniquement) |

> **E2E différable** : si XAMPP n'est pas actif, l'étape E2E peut être reportée à la session suivante. Le BACKLOG (étape 5) et le commit (étape 7) ne sont pas différables. Indiquer `🔄 En cours` dans la colonne 🎭 si la spec E2E n'est pas encore écrite.

### 2. Vérifications avant push

**C'est Claude qui exécute ces commandes**, pas l'utilisateur. Après chaque implémentation, lancer dans cet ordre sans attendre d'instruction :

```bash
# Build + linter JS/SCSS
npm run lint
npm run build

# Qualité PHP (en parallèle)
vendor/bin/phpcs
php -d memory_limit=512M vendor/phpstan/phpstan/phpstan.phar analyse --configuration=phpstan.neon

# Tests unitaires
vendor/bin/phpunit tests/Unit/

# Tests intégration (nécessite BDD active)
vendor/bin/phpunit tests/Integration/

# Tests E2E (nécessite serveur XAMPP actif sur APP_URL)
npx playwright test
```

Rapporter ✅ ou les erreurs complètes. Si tout est vert, attendre le mot **"go push"**.

### 3. Parallélisation des actions

**Lancer un maximum d'actions en parallèle** dans chaque message pour accélérer le développement :

| Actions parallélisables | Exemples |
|---|---|
| Lectures indépendantes | Plusieurs `Read` / `Grep` / `Glob` dans le même message |
| Linters PHP | `phpcs` + `phpstan` lancés simultanément |
| Créations de fichiers indépendants | Plusieurs `Write` en parallèle (ex. TU + TI d'une même feature) |
| Recherches codebase | Plusieurs `Grep` sur des cibles différentes |

Ne séquencer que ce qui dépend du résultat d'une étape précédente.

### 4. Branches

**Règle principale : une branche par US.**

Le nom de branche est dérivé du nom du fichier `us-{sujet}.md` :

| Préfixe | Usage | Exemple |
|---|---|---|
| `feat/` | Nouvelle fonctionnalité (US fonctionnelle) | `feat/us-declaration-majorite` |
| `fix/` | Correction de bug | `fix/us-connexion-csrf` |
| `refactor/` | Refactoring sans changement de comportement | `refactor/us-password-service` |
| `chore/` | Maintenance, CI, dépendances, config | `chore/us-composer-npm-audit-ci` |
| `docs/` | Documentation uniquement | `docs/us-mentions-legales-lcen` |

**Règles d'application :**
- Claude crée systématiquement une nouvelle branche avant toute implémentation d'US : `git checkout -b feat/us-{sujet}`
- Jamais de travail directement sur `main`
- Une branche = une US = une PR (sauf US triviales type `docs/` ou `chore/` qui peuvent être regroupées)
- Le nom de branche correspond exactement au slug du fichier `us-*.md` associé

### 5. Commits

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

### 6. Annotations qualité

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

> Le PDF intègre automatiquement (dans l'ordre) :
> 1. Page de couverture
> 2. **Tableau de bord** — avancement global + détail par EPIC (critères IA Claude / PO / E2E / Recette)
> 3. Contenu complet du backlog (EPICs → Features/Enablers → US)
> 4. **Plan de priorisation** (`PRIORISATION.md`) — sprints ordonnés par urgence légale · risque sécurité · valeur métier

### Structure

```
BACKLOG/
  README.md                        ← synthèse macro tous EPICs
  PRIORISATION.md                  ← plan de priorisation par sprints (mis à jour à chaque audit)
  AUDIT_BACKLOG_[date].md          ← rapport d'audit multi-experts (archivé, non regénéré)
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

### Mise à jour de `PRIORISATION.md`

**Quand mettre à jour :** après chaque audit multi-experts ou à chaque sprint si des US changent de priorité.

**Qui met à jour :** Claude (IA) — analyse croisée Expert Juridique · Expert RGPD · Expert Red Team · DevSecOps.

**Règle :** les US passées à `✅ Fait` dans la colonne 🤖 doivent être retirées de `PRIORISATION.md`. Les nouvelles US Must identifiées lors d'un audit doivent y être ajoutées immédiatement.

### Colonnes de suivi d'avancement

Chaque README contient un tableau avec 4 colonnes :

| Colonne | Responsable | Signification |
|---|---|---|
| 🤖 Claude | Claude IA | Implémenté et fonctionnel selon l'auto-évaluation |
| ✅ PO | Alexandre | Recette métier validée par le PO |
| 🎭 E2E | Expert QA | Tests Playwright couvrant nominal + erreur |
| 🚀 Recette Release | DevSecOps | Mergé sur main, déployable en production |

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

### Audits finaux

Les 4 audits suivants sont à réaliser **en dernier**, après que tous les EPICs fonctionnels sont `✅ Claude`. Ils s'exécutent dans cet ordre :

| Audit | Enabler | Expert | Rapport produit |
|---|---|---|---|
| BDD | `audit-bdd/` | Architecte BDD MySQL | `docs/audit-bdd-[date].md` |
| Génie Logiciel | `audit-genie-logiciel/` | Architecte Génie Logicielle | `docs/audit-genie-logiciel-[date].md` |
| QA | `audit-qa/` | Expert QA | `docs/audit-qa-[date].md` |
| Cybersécurité | `audit-cyber/` | Expert Red Team → Blue Team | `docs/audit-cyber-[date].md` |

L'audit cyber reste le tout dernier — il valide l'ensemble avant déploiement.
