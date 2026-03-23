# Charte graphique — Crabitan Bellevue

Site e-commerce de vins — Refonte 2026.
Esthétique : luxe sobre, terroir bordelais, nuit profonde & or chaud.

---

## 1. Palette de couleurs

### Tokens CSS (6 tokens fondamentaux)

| Token | Rôle | Dark `#hex` | Light `#hex` |
|---|---|---|---|
| `--color-bg` | Fond principal | `#080808` | `#f5f0e8` |
| `--color-surface` | Cards, modals, drawers | `#111111` | `#ffffff` |
| `--color-gold` | Accent unique — bordures, CTA, highlights | `#c9a84c` | `#c9a84c` |
| `--color-gold-light` | Hover, emphasis, dégradé | `#e8c86a` | `#e8c86a` |
| `--color-text` | Texte principal | `#f5f0e8` | `#080808` |
| `--color-text-muted` | Labels, texte secondaire | `#8a8070` | `#5a5040` |

### Tokens dérivés (calculés, jamais de nouvelle couleur)

| Token | Valeur dark | Valeur light |
|---|---|---|
| `--color-border` | `rgba(245, 240, 232, 0.07)` | `rgba(8, 8, 8, 0.18)` |
| `--color-border-gold` | `rgba(201, 168, 76, 0.35)` | `rgba(201, 168, 76, 0.35)` |
| `--color-overlay` | `rgba(8, 8, 8, 0.85)` | `rgba(8, 8, 8, 0.50)` |

### Règle couleur

> **Jamais de nouvelle couleur.** Toute nuance dérive des 6 tokens via `rgba()`, `opacity` ou `mix()`. L'or `#c9a84c` est l'unique accent du site.

---

## 2. Typographie

### Familles de polices

| Rôle | Police | Fallback | Variable SCSS | Usage |
|---|---|---|---|---|
| Script | **CAC Champagne** | Brush Script MT, cursive | `$font-script` | Logo, citations ornementales |
| Serif | **Cinzel** (400 / 700) | Georgia, Times New Roman | `$font-serif` | Titres H1–H4, labels nav |
| Sans-serif | **Caviar Dreams** | system-ui, -apple-system | `$font-sans` | Corps, boutons, UI |

### Échelle typographique

| Classe | Police | Taille | Poids | Letter-spacing | Transform |
|---|---|---|---|---|---|
| `.section-title` | Cinzel | `clamp(1.5rem, 3vw, 2.25rem)` | 400 | `0.12em` | uppercase |
| `.quote` | Cinzel | `clamp(0.9rem, 1.5vw, 1.1rem)` | 400 italic | `0.06em` | — |
| `.btn` | Caviar Dreams | `1rem` | 400 | `0.1em` | uppercase |
| Corps | Caviar Dreams | `1rem` | 400 | — | — |

### Règle typographique

> Cinzel = prestige & structure. Caviar Dreams = lisibilité & modernité. CAC Champagne = touche artisanale réservée aux logos et ornements.

---

## 3. Composants

### Boutons

| Variante | Fond | Texte | Bordure | Hover fond | Hover texte |
|---|---|---|---|---|---|
| `.btn--gold` | transparent | `--color-gold` | `--color-gold` | `#c9a84c` | `#080808` |
| `.btn--white` *(dark)* | `#ffffff` | `--color-gold` | `#ffffff` | `#c9a84c` | `#080808` |
| `.btn--white` *(light)* | `--color-gold` | `#ffffff` | `--color-gold` | `--color-gold-light` | `#080808` |

- Border-radius : **0** (angles droits — sobriété)
- Padding : `0.6em 1.6em`
- Transition : `200ms ease` sur `background-color`, `color`, `border-color`, `box-shadow`

### Diviseur doré

```scss
.home-section__divider  // ligne horizontale fine couleur --color-gold
```

Utilisé entre titre et contenu dans toutes les sections. Centré avec `--center`, aligné à gauche par défaut.

### Focus visible (accessibilité)

```scss
outline: 2px solid var(--color-gold);
outline-offset: 3px;
border-radius: 2px;
```

Appliqué via le mixin `@include m.focus-ring` sur tous les éléments interactifs.

---

## 4. Espacements & Layout

### Breakpoints (mobile-first)

| Nom | Valeur |
|---|---|
| `sm` | 576px |
| `md` | 768px |
| `lg` | 992px |
| `xl` | 1200px |
| `xxl` | 1400px |

Usage : `@include bp('md') { ... }` via le mixin SCSS `bp($key)`.

### Conteneur

```scss
.container  // max-width défini globalement, padding horizontal responsive
```

---

## 5. Animations & Effets

| Keyframe | Effet | Usage |
|---|---|---|
| `fade-in` | `opacity 0→1` + `translateY(10px→0)` | Apparition de sections, cards |
| `gold-pulse` | `box-shadow` `12px→28px` doré | Age gate border, éléments mis en avant |
| `shimmer-text` | `opacity 1→0.75→1` | Texte doré scintillant |

### Transitions standard

| Variable | Valeur | Usage |
|---|---|---|
| `$transition-fast` | `120ms ease` | Hover états discrets |
| `$transition-base` | `200ms ease` | Boutons, liens, icônes |
| `$transition-slow` | `350ms ease` | Ouvertures de drawers, menus |

---

## 6. Thèmes (dark / light)

Le thème est piloté par `data-theme` sur `<html>`. Par défaut : **dark**.

```html
<html data-theme="dark">   <!-- nuit (défaut) -->
<html data-theme="light">  <!-- jour -->
```

Persisté en `localStorage` sous la clé `cb-theme`. Toggle via `#theme-toggle`.

Les 6 tokens inversent automatiquement leurs valeurs — aucune logique JS supplémentaire n'est nécessaire côté styles.

---

## 7. Architecture SCSS (7 layers)

```
resources/scss/
├── abstracts/       # Variables, mixins, animations (pas de CSS généré)
│   ├── _variables.scss
│   ├── _mixins.scss
│   └── _animations.scss
├── base/            # Reset, fonts, typographie globale
│   ├── _reset.scss
│   ├── _fonts.scss
│   └── _typography.scss
├── layout/          # Sections majeures (header, footer, page-hero, age-gate…)
├── components/      # Composants réutilisables (button, carousel, cookie-banner…)
├── pages/           # Styles spécifiques à une vue (wines, news, jeux, legal…)
├── themes/          # Overrides de thème si nécessaire
└── main.scss        # Point d'entrée — @use de tout, compilé par Vite
```

---

## 8. Règles à respecter

1. **Jamais de couleur hardcodée** hors `_variables.scss` — toujours `var(--color-*)`.
2. **Jamais de `!important`** — résoudre par spécificité.
3. **Mobile-first** — styles de base pour mobile, `@include bp()` pour agrandir.
4. **Accessibilité** — tout élément interactif doit avoir un focus visible (mixin `focus-ring`).
5. **Thème** — tout nouveau composant doit être compatible dark *et* light sans JS supplémentaire.
6. **Nouveaux composants** → fichier dédié dans `components/` ou `pages/`, jamais dans `main.scss` directement.
