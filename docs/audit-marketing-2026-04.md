# Audit Marketing — Château Crabitan Bellevue
**Date :** avril 2026
**Expert :** Expert Marketing

---

## Méthodologie

Audit réalisé depuis le code source réel (MailService.php, NewsletterAdminController.php, WineController.php, vues show.php / home.php / cart/index.php / order/checkout.php, StatsAdminController.php, lang/fr.php, partials/head.php). Aucune hypothèse — les constats sont ancrés dans ce qui est effectivement en production.

---

## 1. Emails transactionnels — Note : 7/10

### Ce qui existe

`MailService.php` couvre un éventail transactionnel complet :
- Activation de compte (`sendEmailVerification`) — objet FR/EN
- Reset mot de passe (`sendPasswordReset`) — FR/EN
- Compte Google-only (`sendGoogleAccountInfo`) — FR/EN
- Contact propriétaire + accusé de réception client (`sendContactToOwner` / `sendContactConfirmation`)
- Suppression de compte RGPD + token de réactivation (`sendAccountDeletionConfirmation`) — bouton doré CTA
- Rappels avant suppression à J-7 et J-1 (`sendDeletionReminder`) — urgence visuelle
- Changement de mot de passe — double confirmation "C'était moi / Ce n'était pas moi" (`sendPasswordChangeConfirmation`)
- Nouvel appareil inconnu avec CTA confirmer/révoquer (`sendNewDeviceAlert`)
- Newsletter abonnés avec désabonnement tokenisé (`sendNewsletter`, `buildNewsletterHtml`)
- Nouveau vin disponible avec photo, tableau appellation/millésime, CTA "Découvrir" (`sendNewWineNewsletter`)
- Confirmation de commande client avec tableau produits, mode de paiement, coordonnées bancaires si virement/chèque, note de rétractation 14j (`sendOrderConfirmationToClient`)
- Notification propriétaire nouvelle commande (`sendOrderConfirmationToOwner`)
- Suivi de statut commande : processing / shipped / delivered / cancelled / refunded (`sendOrderStatusEmail`)

### Points forts

- Design cohérent : layout blanc + gradient doré (#e8c86a → #c9a84c), police Georgia/serif, branding "Château Crabitan Bellevue"
- Footer newsletter complet : liens Politique de confidentialité, Mentions légales, Support, CGV, désabonnement
- Bilingue FR/EN natif sur la quasi-totalité des emails
- Conformité Loi Evin dans les PDFs (footer) et bonne gestion RGPD dans les emails de suppression
- Objets bien rédigés, personnalisation avec prénom

### Lacunes

- **Aucun email de relance panier abandonné** — la commande ne se finalise qu'avec paiement, pas de relance
- **Aucun email post-livraison** (demande d'avis, suggestion de réachat, upsell millésime similaire) — le `sendOrderStatusEmail` à statut `delivered` ne propose pas de CTA vers la boutique
- **L'objet newsletter** est préfixé "Château Crabitan Bellevue — " mais le sujet lui-même est libre : risque de manque de cohérence éditoriale entre campagnes
- **Aucune séquence de bienvenue** : l'activation de compte déclenche un seul email (vérification), pas de mail de bienvenue chaleureux post-activation avec CTA vers la boutique
- **sendNewWineNewsletter** : salutation "Cher(e) {toName}" — le "e" entre parenthèses est vieillissant, à moderniser (`Bonjour {prénom},`)
- Pas de tracking d'ouverture ni de clic (normal pour une gestion SMTP custom, mais limitant pour l'analyse)

---

## 2. Newsletter — Note : 6/10

### Ce qui existe

`NewsletterAdminController` gère :
- Liste paginée des abonnés (25/page)
- Envoi de campagne : objet, titre optionnel, corps texte, image uploadée (jpg/png/webp), pièce jointe PDF
- Historique des campagnes avec compteurs envoyés/échecs
- Détail d'une campagne archivée

Le contenu HTML est construit dans `buildNewsletterHtml` : en-tête logo + titre, salutation personnalisée, titre optionnel h2, corps `nl2br`, image, pied de page désabonnement + liens légaux.

### Points forts

- Désabonnement tokenisé dans chaque email — RGPD conforme
- Pièce jointe PDF (tarif, fiche technique) supportée avec copie permanente en `storage/`
- Image newsletter (upload côté admin) pour visuels
- Branding cohérent avec le reste du site

### Lacunes

- **Interface admin basique** : champ `body` = textarea libre, pas de WYSIWYG ni de prévisualisation du rendu final avant envoi — risque d'erreurs de mise en page
- **Pas de segmentation** : envoi à tous les abonnés en bloc, sans filtre (par langue, par ancienneté, par historique d'achat)
- **Pas de test d'envoi** (envoi à une adresse de test avant la campagne) — risque d'erreur en production
- **Pas de programmation d'envoi** — tout est synchrone, bloquant si la liste dépasse quelques centaines d'abonnés
- **Pas de statistiques d'ouverture** (taux d'ouverture, clics) — seuls les compteurs envoyés/échecs sont tracés
- **Fréquence et calendrier éditorial** non structurés — pas de cadre planning (il n'existe pas dans le back-office)
- La langue est celle de l'admin au moment de l'envoi, pas celle de l'abonné — risque d'envoyer en FR à des abonnés EN

---

## 3. Pages produits vins — Note : 7.5/10

### Ce qui existe (`WineController::show` + `wines/show.php`)

- Nom + millésime en H1, couleur en badge
- Prix TTC affiché avec note "livraison comprise en France métropolitaine, remises multiples de 12"
- Disponibilité (disponible/épuisé)
- Bouton "Ajouter au panier" avec `data-wine-id`, `data-wine-name`, `data-wine-price`, `data-wine-image`
- Bouton favori avec état liked/unliked, aria-label, gestion non connecté
- Note de dégustation (oenological_comment JSON bilingue)
- Accords mets & vins par couleur (4 types : sweet, white, red, rosé) depuis lang/fr.php — textes pertinents et gastronomiques
- Tableau technique complet : certification, appellation, encépagement, âge des vignes, terroir, vendanges, vinification, élevage, superficie, taille, quantité produite
- Récompenses avec trophée emoji
- Commentaire extra (HTML libre, utilisé pour lien Andreas Larsson)
- Téléchargement fiche technique PDF (TCPDF, bilingue, rate-limited à 10 req/IP/vin/heure)
- Zoom image (overlay JS)

### Points forts

- Fiche très complète sur le plan technique/oenologique
- PDF fiche technique professionnel, idéal pour la B2B et les grossistes
- Accords mets & vins bien rédigés et utiles à la décision d'achat
- Bilingue natif FR/EN

### Lacunes

- **Pas de balises Open Graph spécifiques par vin** dans `head.php` (cf. section SEO ci-dessous)
- **Pas de prix structuré** (schema.org Product/Offer) — aucun JSON-LD visible
- **Pas de cross-sell** : "vous aimerez aussi", produits similaires (même couleur, même gamme de prix)
- **Pas de notation/avis clients** — champ `award` = récompenses professionnelles uniquement, pas d'avis consommateurs
- **Quantité de production** affichée (nombre de bouteilles) — peut être supprimée pour les petites cuvées, sentiment de pénurie non exploité
- **CTA unique** : seul "ajouter au panier" — pas de "ajouter à la liste de cadeaux" ni de "partager"

---

## 4. Homepage — Note : 7/10

### Ce qui existe (`home.php`)

- Hero carousel 5 slides (été, automne, récolte, allée, hiver) avec widget météo locale (WeatherAPI)
- H1 "Château Crabitan Bellevue", sous-titre "Vins de Bordeaux — Sainte-Croix-du-Mont"
- CTA principal "Découvrir nos vins" → `/vins`
- Section "Nos Vins" avec visuel + texte + CTA
- Section "Notre Histoire" (fond sombre) avec visuel vendanges + texte + CTA → `/le-chateau`
- Section "Notre Savoir-Faire" avec visuel chai barriques + CTA → `/savoir-faire`
- Section vidéo propriétaire (MP4 local, poster image)
- Section actualités (3 dernières news en cards)
- Section localisation : Google Maps embed + adresse + téléphone + CTA contact

### Points forts

- Storytelling structuré : vins → histoire → savoir-faire → vidéo → news → localisation
- Vidéo propriétaire = fort atout confiance pour un château haut de gamme
- Widget météo : différenciateur original, crée un lien émotionnel avec le vignoble
- Design visuellement cohérent avec la charte (section claire/sombre alternées)

### Lacunes

- **H1 non visible** (dans le carousel, stylisé en titre décoratif) — bonne pratique SEO mais l'UX est discutable : le visiteur lit d'abord l'eyebrow "Sainte-Croix-du-Mont" puis le H1 = bon
- **Textes marketing génériques** dans lang/fr.php : `home.wines_text` mentionne "juchés sur les falaises calcaires" — potentiellement plus évocateur à relire
- **Section actualités vide** : si aucune news, la section affiche un paragraphe vide `<!-- Aucune actualité -->` — aucun fallback visible pour l'internaute (section disparaît silencieusement)
- **Pas de section "mise en avant" d'un vin du moment** (best-seller, coup de cœur, cuvée spéciale) — opportunité manquée
- **Pas de CTA newsletter** sur la homepage — aucun mécanisme de capture email en dehors de l'inscription compte
- **Vidéo propriétaire** : pas de sous-titres (NOSONAR justifié "musique d'ambiance") — mais si le propriétaire parle un jour à la caméra, ce sera bloquant RGAA

---

## 5. SEO éditorial — Note : 4/10

### Ce qui existe (`partials/head.php`)

```php
<title><?= $pageTitle ?><?= isset($pageTitle) ? ' — ' : '' ?><?= APP_NAME ?></title>
<meta name="robots" content="index, follow">
<link rel="icon" href="/assets/images/logo/favicon.svg">
<link rel="stylesheet" href="/assets/css/main.css">
```

**Ce qui n'existe pas :**
- Aucune `<meta name="description">`
- Aucune balise Open Graph (`og:title`, `og:description`, `og:image`, `og:url`, `og:type`)
- Aucune balise Twitter Card
- Aucun JSON-LD (schema.org : Product, Organization, LocalBusiness, BreadcrumbList)
- Pas de `<link rel="canonical">`
- Pas de `<meta name="author">`

### Constats spécifiques

- **Homepage** : `$pageTitle = null` → title = "Château Crabitan Bellevue" seul. Correct mais pas optimisé pour les requêtes longue traîne ("vin blanc liquoreux Sainte-Croix-du-Mont")
- **Fiche vin** : title = `"Crabitan Blanc 2022 — Château Crabitan Bellevue"` — format correct, mais sans meta description ni OG image → partage sur réseaux sociaux sans aperçu visuel
- **Catalogue vins** (`/vins`) : title probablement "Nos Vins — Château Crabitan Bellevue" (non vérifié en vue index mais pattern identique) — correct
- **Aucune sitemap.xml** tracée dans les routes connues
- **Aucun fichier robots.txt** tracé dans les routes connues

### Impact

Très faible indexation des fiches vins, partage social sans aperçu image = taux de clic médiocre sur les réseaux. Pour un château haut de gamme, l'absence d'OG image est particulièrement pénalisante.

---

## 6. Tunnel de conversion — Note : 6.5/10

### Ce qui existe

**Fiche vin → Panier** (`wines/show.php` → `cart/index.php`) :
- CTA "Ajouter au panier" présent sur la fiche, actif uniquement si disponible et non admin
- Panier côté serveur pour les connectés, cookie `cb-cart` pour les invités
- Contrainte multiple de 12 bouteilles (1 caisse minimum) avec notice + shake animation si invalide
- Remises livraison par paliers de quantité avec barre de progression temps réel
- Notice > 600 bouteilles avec mailto direct
- Vin aléatoire suggéré si panier vide (cross-sell basique)
- Redirection invité avec CTA "Se connecter"

**Panier → Checkout** (`order/checkout.php`) :
- Formulaire adresse facturation + livraison (avec carnet d'adresses pré-rempli)
- Support B2B : message de contact direct (email) au lieu du tunnel standard
- 3 modes de paiement : carte, virement, chèque
- Checkbox newsletter au checkout (`$isNewsletterSubscribed`)
- Token anti-replay (`$submitToken`) en plus du CSRF
- Délai de livraison 7-15 jours affiché

### Points forts

- Contrainte 12 bouteilles bien gérée (UX proactive, pas de blocage silencieux)
- Remises livraison avec visualisation paliers = levier de panier moyen
- Support B2B avec redirection intelligente
- Checkbox newsletter au checkout = capture email opportuniste

### Lacunes

- **Invité non connecté ne peut pas commander** : le tunnel se bloque avant la page checkout, avec un seul CTA "se connecter". Pas de checkout invité. Friction maximale pour les nouveaux visiteurs
- **Pas d'indicateur d'étapes** (step 1/3 : panier → 2/3 : livraison → 3/3 : paiement) — le visiteur ne sait pas où il en est
- **Pas de mini-panier** dans le header (badge quantité uniquement, pas d'aperçu survol)
- **Pas de récapitulatif panier latéral persistant** pendant le checkout
- **Pas de réassurance visuelle** au checkout : sécurité SSL, politique de retour, délai livraison — absent ou non mis en valeur
- **Pas d'upsell/cross-sell** dans le panier (ex. "Complétez votre caisse avec ce rouge primé")
- **Le tunnel n'est accessible qu'aux connectés** : cela est cohérent avec le modèle B2C/B2B du château mais bloque l'acquisition de nouveaux acheteurs

---

## 7. Statistiques marketing back-office — Note : 5/10

### Ce qui existe (`StatsAdminController` + vue `admin/stats/index.php`)

- KPIs : Chiffre d'affaires, Nombre de commandes, Panier moyen
- Filtres période : 30 jours, 3 mois, 12 mois, "depuis toujours", + filtres par année
- Graphique évolution CA (Chart.js via `admin-charts.js`) — mode barres ou courbe
- Granularité automatique : daily (30j), monthly (3m/12m), yearly (all/année)

### Points forts

- Filtres temporels bien pensés pour le suivi opérationnel
- Graphique interactif, mode barres/courbe — usage simple
- Requêtes BDD efficaces (`getStatsForPeriod`, `getChartData`)

### Lacunes Marketing critiques

- **Aucune statistique sur les abonnés newsletter** dans l'espace stats CA — seul le module `/admin/newsletter` liste les abonnés, sans courbe d'évolution ni taux de churn
- **Aucun taux de conversion** visible (nb visites → nb commandes) — nécessiterait une intégration analytics externe
- **Aucun classement des vins best-sellers** — pas de tableau "top 5 vins par CA" ou "top 5 par volume bouteilles"
- **Aucune analyse de panier** : quels vins sont souvent achetés ensemble, quelle est la taille de panier la plus fréquente
- **Aucune statistique géographique** : d'où viennent les clients (pays/région), utile pour cibler les campagnes
- **Aucune métrique newsletter** : taux d'envoi, d'échec, nombre de campagnes envoyées par mois — seul le compteur brut par campagne est tracé dans l'historique
- **Pas d'export CSV/Excel** des données de CA ou des abonnés newsletter pour reporting externe

---

## Synthèse des notes

| Section | Note |
|---|---|
| Emails transactionnels | 7/10 |
| Newsletter | 6/10 |
| Pages produits vins | 7.5/10 |
| Homepage | 7/10 |
| SEO éditorial | 4/10 |
| Tunnel de conversion | 6.5/10 |
| Statistiques marketing back-office | 5/10 |
| **Moyenne** | **6.1/10** |

---

## Liste priorisée des 10 actions marketing les plus impactantes

| # | Action | Effort | Impact (1-5) | Justification |
|---|---|---|---|---|
| 1 | **Balises meta description + Open Graph sur toutes les pages** | S | 5 | Zéro OG actuellement. Partage RS et SEO immédiatement impactés. `head.php` à enrichir avec `$metaDescription`, `$ogImage`. | 
| 2 | **Email de bienvenue post-activation + CTA boutique** | S | 4 | Le `sendEmailVerification` ne déclenche aucun mail post-activation. Ajouter `sendWelcome()` après validation du token email. Levier d'engagement fort. |
| 3 | **Capture newsletter sur la homepage** | S | 4 | Aucun formulaire newsletter public actuellement. Un bloc "Restez informé" avec champ email en footer ou section dédiée peut multiplier les abonnés. |
| 4 | **JSON-LD schema.org sur les fiches vins (Product + BreadcrumbList)** | M | 4 | Rich snippets Google : prix, disponibilité, fil d'Ariane dans les SERP. Impact SEO direct sur les fiches produit. |
| 5 | **Section "Vin du moment" sur la homepage** | M | 4 | Mettre en avant un vin best-seller, une cuvée spéciale ou un nouveau millésime. Cross-sell éditorial sans développement complexe. |
| 6 | **Email post-livraison avec CTA boutique + suggestion de réachat** | M | 4 | `sendOrderStatusEmail` au statut `delivered` envoie une notification, mais aucun CTA vers la boutique ni suggestion. Enrichir ce template. |
| 7 | **Segmentation newsletter par langue** | M | 3 | Actuellement la langue de l'envoi est celle du texte admin. Les abonnés EN reçoivent du FR. Utiliser le champ `lang` du compte abonné. |
| 8 | **Indicateur d'étapes dans le tunnel checkout** | M | 3 | Pas de stepper (1/3 → 2/3 → 3/3). Réduit l'anxiété du checkout et le taux d'abandon. Développement frontend pur. |
| 9 | **Widget statistiques newsletter dans le dashboard stats** | M | 3 | Courbe d'évolution abonnés, taux d'envoi/échec par campagne. Données déjà en BDD (table `newsletters` + `accounts`). Enrichir `StatsAdminController`. |
| 10 | **Bloc "Produits similaires" sur la fiche vin** | L | 3 | Cross-sell "Vous aimerez aussi" : même couleur ou même gamme de prix. Nécessite une méthode `WineModel::getSimilar()` + intégration dans `WineController::show`. |

---

*Audit réalisé sur code source réel — branche `feat/us-analyse-marketing` — avril 2026.*
