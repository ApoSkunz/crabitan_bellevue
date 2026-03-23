# Feature — Badge Cuvée Spéciale

## Objectif

Identifier visuellement les vins élevés en fût de chêne (« Cuvée Spéciale ») sur toutes les vues du catalogue : boutique, collection et fiche détail.

## Modèle de données

Colonne ajoutée à la table `wines` :

```sql
ALTER TABLE wines ADD COLUMN IF NOT EXISTS is_cuvee_speciale TINYINT(1) NOT NULL DEFAULT 0;
```

- `0` = vin standard
- `1` = Cuvée Spéciale (élevé en fût de chêne)

Le champ `extra_comment` (JSON) est conservé pour un usage futur mais n'est plus utilisé pour cette indication.

## Mise à jour BDD production

```sql
-- Marquer les cuvées spéciales
UPDATE wines SET is_cuvee_speciale = 1 WHERE slug IN (
    'chateau-crabitan-bellevue-blanc-sec-2021',
    ...
);

-- Purger extra_comment si nécessaire
UPDATE wines SET extra_comment = '{}' WHERE is_cuvee_speciale = 1;
```

## Fichiers concernés

| Fichier | Rôle |
|---|---|
| `src/Model/WineModel.php` | `getAll()`, `getAllByColor()`, `getBySlug()` — champ ajouté au SELECT |
| `src/View/wines/index.php` | Badge sous le nom du vin (vue boutique) |
| `src/View/wines/collection.php` | Badge sous le nom du vin (vue collection) |
| `src/View/wines/show.php` | Badge au-dessus de la médaille (vue fiche détail) |
| `resources/scss/layout/_wines.scss` | `.wine-card__extra` — 0.75 rem italique, `.wine-detail .wine-card__extra` — 0.9 rem |
| `lang/fr.php` | `'wine.cuvee_speciale' => 'Cuvée Spéciale'` |
| `lang/en.php` | `'wine.cuvee_speciale' => 'Special Cuvée'` |
| `database/seed_prod_import.sql` | ALTER TABLE + UPDATE pour la prod |

## Rendu

```php
<?php if (!empty($wine['is_cuvee_speciale'])) : ?>
    <p class="wine-card__extra"><?= htmlspecialchars(__('wine.cuvee_speciale')) ?></p>
<?php endif; ?>
```

## Tailles typographiques

| Vue | Taille |
|---|---|
| Boutique / Collection (carte) | `0.75rem`, italique, `color-text-muted` |
| Fiche détail | `0.9rem` (override `.wine-detail .wine-card__extra`) |

## Tests à couvrir

- [ ] TU : `WineModel::getAll()` retourne `is_cuvee_speciale` dans le résultat
- [ ] TU : `WineModel::getBySlug()` retourne `is_cuvee_speciale`
- [ ] E2E : vérifier que le badge apparaît sur la carte d'un vin marqué
