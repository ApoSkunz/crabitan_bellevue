# Feature — Plan du site

## Objectif

Page de navigation globale présentant toutes les sections du site sous forme de cartes cliquables, avec une section dédiée aux vins présentant des bouteilles aléatoires par couleur.

## Structure de la page

```
/fr/plan-du-site
├── Cartes principales (Château, Savoir-faire, Boutique, Collection, Actualités, Contact)
├── Section vins (lignes colorées avec bouteilles aléatoires)
│   ├── Liquoreux (sweet)   — survol : #c4912a
│   ├── Rouges (red)        — survol : #8b1a2a
│   ├── Blancs (white)      — survol : #b8963e
│   └── Rosés (rosé)        — survol : #c97a8a
└── Autres pages (Jeux, Support, Mentions légales, Politique confidentialité, Webmaster)
```

## Images aléatoires

Le contrôleur injecte des images aléatoires par couleur via `WineModel` :

```php
$wineImages = [
    'sweet'      => $model->getRandomByColor('sweet'),
    'red'        => $model->getRandomByColor('red'),
    'white'      => $model->getRandomByColor('white'),
    'rosé'       => $model->getRandomByColor('rosé'),
    'collection' => $model->getRandom(),
];
```

Chemin des images : `/assets/images/wines/{image_path}`

## Fichiers concernés

| Fichier | Rôle |
|---|---|
| `src/View/pages/plan-du-site.php` | Vue complète |
| `src/Controller/PageController.php` | `planDuSite()` — injection des images aléatoires |
| `src/Model/WineModel.php` | `getRandomByColor()`, `getRandom()` |
| `resources/scss/layout/_pages.scss` | `.sitemap-*` — cartes, wine-rows, modificateurs couleur |

## Design SCSS

### Cartes
- `.sitemap-card` — carte avec hover scale
- `.sitemap-card--soft-zoom` — zoom discret (scale 1.02) sur l'image au hover
- `.sitemap-card--fit` — `object-fit: contain` pour les images de bouteilles (pas de recadrage)

### Lignes vins
```scss
.sitemap-wines        // margin: 0 25%
.sitemap-wine-row     // flex: 0 0 22%, min-height: 180px, padding: 1rem 2rem
.sitemap-wine-row--sweet   // hover color: #c4912a
.sitemap-wine-row--red     // hover color: #8b1a2a
.sitemap-wine-row--white   // hover color: #b8963e
.sitemap-wine-row--rose    // hover color: #c97a8a
.sitemap-wine-row--collection
```

## Tests à couvrir

- [ ] TU : `PageController::planDuSite()` — images injectées pour chaque couleur
- [ ] E2E : vérifier que les images bouteilles s'affichent (src non vide)
- [ ] E2E : vérifier les liens de navigation vers les sections
