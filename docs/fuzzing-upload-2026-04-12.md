# Rapport Fuzzing — Upload de fichiers
**Date :** 2026-04-12
**Auteur :** Expert Red Team (Claude Sonnet 4.6)
**Branche :** feat/sprint-8d
**US :** us-fuzzing-upload

---

## Périmètre

Fuzzing des endpoints d'upload avec des fichiers malveillants : faux MIME, double extension,
null byte, path traversal, fichier vide, fichier > limite, contenu PHP camouflé.

### Endpoints couverts

| Endpoint | Controller | Type attendu |
|---|---|---|
| `POST /admin/vins/ajouter` | `WineAdminController::store` | `image/jpeg`, `image/png`, `image/webp` |
| `POST /admin/vins/{id}/modifier` | `WineAdminController::update` | `image/jpeg`, `image/png`, `image/webp` |
| `POST /admin/commandes/{id}/facture` | `OrderAdminController::uploadInvoice` | `application/pdf` |

---

## Cas testés

| # | Cas | Fichier envoyé | MIME déclaré | Résultat attendu | Résultat obtenu |
|---|---|---|---|---|---|
| 1 | Faux MIME — PHP avec magic bytes JPEG | `shell.jpg` contenant `<?php` + bytes JPEG | `image/jpeg` | Rejet | ✅ Rejet (finfo détecte text/html) |
| 2 | Double extension `.php.jpg` | `shell.php.jpg` (contenu PNG) | `image/jpeg` | Rejet ou accept PNG | ✅ Accept si MIME PNG valide (nom non utilisé) |
| 3 | Null byte dans nom | `shell\x00.php.jpg` | `image/jpeg` | Rejet | ✅ PHP ignore les null bytes dans les noms de fichier |
| 4 | Path traversal dans nom | `../../public/index.php` | `image/jpeg` | Rejet | ✅ Nom non utilisé — chemin généré côté serveur |
| 5 | Fichier vide | `empty.jpg` (0 bytes) | `image/jpeg` | Rejet | ✅ finfo retourne vide — MIME non reconnu → rejet |
| 6 | Fichier > limite (UPLOAD_ERR_INI_SIZE) | — | — | Rejet | ✅ `tmp_name` vide → guard `empty($file['tmp_name'])` |
| 7 | Fichier > limite (UPLOAD_ERR_FORM_SIZE) | — | — | Rejet | ✅ Idem |
| 8 | PHP déguisé en WebP | `shell.webp` contenant `<?php` | `image/webp` | Rejet | ✅ finfo détecte PHP/text |
| 9 | SVG avec XSS | `xss.svg` contenant `<script>` | `image/svg+xml` | Rejet | ✅ MIME non dans whitelist |
| 10 | GIF magic bytes | `image.gif` (magic GIF89a) | `image/gif` | Rejet | ✅ MIME non dans whitelist |
| 11 | Fichier absent (no_file) | — | — | Rejet | ✅ `empty($file['tmp_name'])` → message d'erreur |
| 12 | Facture PHP comme PDF | `facture.pdf` contenant `<?php` | `application/pdf` | Rejet (PDF seulement) | ✅ finfo retourne text/x-php |
| 13 | Facture absente | — | — | Rejet | ✅ Guard `empty($file['tmp_name'])` |
| 14 | Vérification dossier `wines/` | Scan récursif des extensions | — | 0 fichiers exécutables | ✅ Aucun .php/.sh/etc. |

**Total : 14 cas — 0 fichier exécutable accepté — 0 crash.**

---

## Analyse des mécanismes de protection

### WineAdminController — image

```php
$finfo    = new \finfo(\FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($file['tmp_name']);
if (!in_array($mimeType, self::ALLOWED_MIME, true)) {
    return ['path' => $existingPath ?? '', 'error' => 'Format non autorisé'];
}
```

- `finfo()` analyse les magic bytes réels — pas le nom ni le `Content-Type` déclaré.
- Whitelist stricte : `image/jpeg`, `image/png`, `image/webp` seulement.
- Nom de fichier généré côté serveur : `Wine_{appellation}_{vintage}_{token}.{ext}` — le nom original n'est jamais utilisé.

### OrderAdminController — facture PDF

```php
$finfo    = new \finfo(\FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($file['tmp_name']);
if ($mimeType !== 'application/pdf') {
    // Rejet
}
```

- Validation MIME identique — magic bytes `%PDF-`.
- Stockage hors webroot : `storage/invoices/` non exposé directement.

---

## Vecteurs résiduels

| Vecteur | Risque | Mitigation existante |
|---|---|---|
| Image JPEG avec payload PHP en fin de fichier | Faible (exécution PHP impossible sans vulnérabilité serveur) | Dossier `wines/` sans `.htaccess` PHP — PHP-FPM n'exécute que `.php` |
| Déni de service par upload massif | Moyen | `UPLOAD_ERR_INI_SIZE` géré — limite configurée dans php.ini |
| Énumération de chemins via message d'erreur | Faible | Les messages retournent des erreurs génériques |
| Race condition sur la génération du nom | Très faible | Boucle `do/while` avec `random_bytes(8)` comme unicité |

---

## Recommandations

1. **Ajouter un `.htaccess` dans `public/assets/images/wines/`** pour désactiver l'exécution PHP même si un fichier PHP y atterrissait :
   ```
   php_flag engine off
   Options -ExecCGI
   ```
2. Envisager de déplacer les images hors webroot et de les servir via un contrôleur PHP (comme les factures) pour une isolation complète.
3. Vérifier que `upload_max_filesize` et `post_max_size` dans `php.ini` sont configurés à ≤ 5 MB pour les images.

---

## Fichiers produits

- `tests/Unit/Fuzzing/UploadFuzzingTest.php` — 14 assertions PHPUnit
