# Procédure d'installation — Environnement de développement

Crabitan Bellevue — PHP 8.4 · MySQL · Vite · PHPUnit · Playwright

---

## Prérequis

| Outil | Version minimale | Téléchargement |
|---|---|---|
| XAMPP | 8.4+ | https://www.apachefriends.org |
| Node.js | 20 LTS | https://nodejs.org |
| Composer | 2.x | https://getcomposer.org |
| Git | 2.x | https://git-scm.com |
| VSCode | Dernière | https://code.visualstudio.com |

---

## 1. VSCode — Extension Claude Code

1. Ouvrir VSCode → Extensions (`Ctrl+Shift+X`)
2. Rechercher **Claude Code** (éditeur : Anthropic)
3. Installer et redémarrer VSCode
4. Se connecter avec le compte Anthropic depuis la palette de commandes (`Ctrl+Shift+P` → `Claude Code: Sign In`)
5. Ouvrir le terminal intégré → lancer `claude` pour démarrer une session

> Le plugin permet d'interagir avec Claude directement dans l'éditeur, avec accès aux fichiers et au terminal.

---

## 2. XAMPP — Serveur web + MySQL

### Installation

1. Télécharger XAMPP 8.4 pour Windows
2. Installer dans `C:\xampp`
3. Ouvrir le panneau de contrôle XAMPP
4. Démarrer **Apache** et **MySQL**

### Configuration PHP

Éditer `C:\xampp\php\php.ini` :

```ini
; Activer les extensions nécessaires
extension=pdo_mysql
extension=mysqli
extension=openssl
extension=mbstring
extension=intl

; Mémoire
memory_limit = 512M
upload_max_filesize = 20M
post_max_size = 20M
```

Redémarrer Apache après modification.

### Base de données

1. Ouvrir phpMyAdmin : http://localhost/phpmyadmin
2. Créer la base : `crabitan_bellevue` (utf8mb4_unicode_ci)
3. Importer le schéma :
   ```bash
   mysql -u root crabitan_bellevue < database/schema.sql
   ```
4. Importer les données de seed :
   ```bash
   mysql -u root crabitan_bellevue < database/seed_prod_import.sql
   ```

### Virtual host (optionnel)

Éditer `C:\xampp\apache\conf\extra\httpd-vhosts.conf` :

```apache
<VirtualHost *:80>
    DocumentRoot "C:/xampp/htdocs/crabitan_bellevue/public"
    ServerName crabitan.local
    <Directory "C:/xampp/htdocs/crabitan_bellevue/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Ajouter dans `C:\Windows\System32\drivers\etc\hosts` :
```
127.0.0.1  crabitan.local
```

---

## 3. Serveur SMTP local — MailHog

MailHog intercepte tous les emails envoyés en dev sans les transmettre réellement.

### Installation

**Option A — Chocolatey (recommandé sous Windows)**
```bash
choco install mailhog
```

**Option B — Docker**
```bash
docker run -d -p 1025:1025 -p 8025:8025 mailhog/mailhog
```

**Option C — Binaire direct**
Télécharger `MailHog_windows_amd64.exe` depuis https://github.com/mailhog/MailHog/releases
Renommer en `mailhog.exe` et placer dans `C:\tools\`

### Démarrage

```bash
mailhog
# Interface web : http://localhost:8025
# SMTP : localhost:1025
```

### Configuration `.env` pour dev

```dotenv
MAIL_HOST=localhost
MAIL_PORT=1025
MAIL_USER=
MAIL_PASS=
MAIL_FROM_NAME="Château Crabitan Bellevue"
CONTACT_OWNER_EMAIL=test@localhost
```

> Tous les emails (inscription, reset, contact) apparaissent dans l'interface MailHog sur http://localhost:8025

---

## 4. Clone et installation du projet

```bash
# Cloner le dépôt
git clone https://github.com/<org>/crabitan_bellevue.git
cd crabitan_bellevue

# Dépendances PHP
composer install

# Dépendances JS
npm install

# Copier et configurer l'environnement
cp .env.example .env
# Éditer .env avec vos valeurs (DB, SMTP, JWT_SECRET, APP_URL...)
```

### Variables `.env` essentielles

```dotenv
APP_URL=http://crabitan.local
APP_NAME="Château Crabitan Bellevue"
APP_ENV=development

DB_HOST=localhost
DB_NAME=crabitan_bellevue
DB_USER=root
DB_PASS=

JWT_SECRET=votre_secret_jwt_32_caracteres_minimum

MAIL_HOST=localhost
MAIL_PORT=1025
MAIL_USER=
MAIL_PASS=
MAIL_FROM_NAME="Château Crabitan Bellevue"
CONTACT_OWNER_EMAIL=test@localhost
```

---

## 5. Build des assets

```bash
# Développement (watch)
npm run dev

# Production
npm run build
```

---

## 6. Lancer les tests

```bash
# Lint JS/SCSS
npm run lint

# Qualité PHP
vendor/bin/phpcs --standard=PSR12 src/ config/ public/index.php tests/
php -d memory_limit=512M vendor/phpstan/phpstan/phpstan.phar analyse --configuration=phpstan.neon

# Tests unitaires
vendor/bin/phpunit tests/Unit/

# Tests d'intégration (BDD active requise)
vendor/bin/phpunit tests/Integration/

# Tests E2E (XAMPP + APP_URL actifs)
npx playwright test
```

---

## 7. Vérification finale

| URL | Attendu |
|---|---|
| http://crabitan.local/fr | Page d'accueil (age gate) |
| http://crabitan.local/fr/vins | Catalogue des vins |
| http://crabitan.local/fr/contact | Formulaire de contact |
| http://localhost:8025 | Interface MailHog |
| http://localhost/phpmyadmin | Base de données |
