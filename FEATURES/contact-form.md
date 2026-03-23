# Feature — Formulaire de contact

## Objectif

Permettre aux visiteurs d'envoyer un message au propriétaire du château via un formulaire en ligne, avec confirmation par email des deux côtés.

## Flux

1. Visiteur remplit le formulaire (civilité, prénom, nom, email, sujet, message, RGPD)
2. Soumission AJAX (pas de rechargement de page)
3. Validation serveur + CSRF
4. Envoi d'un email au propriétaire avec les détails du message
5. Envoi d'un email de confirmation au visiteur (FR ou EN selon la langue)
6. Affichage du retour (succès en vert, erreur en rouge)

## Sécurité

- **CSRF** : token généré en session (`bin2hex(random_bytes(32))`), comparé avec `hash_equals()`
- **Validation serveur** : tous les champs obligatoires vérifiés côté PHP (`PageController::contactPost`)
- **Validation client** : champs invalides marqués avec `is-invalid` + animation shake
- **Sanitisation** : `htmlspecialchars()` sur toutes les données avant insertion dans les corps d'email
- **Email** : `FILTER_VALIDATE_EMAIL` sur l'adresse email soumise

## Fichiers concernés

| Fichier | Rôle |
|---|---|
| `src/View/pages/contact.php` | Vue : formulaire HTML avec data-attributes i18n |
| `src/Controller/PageController.php` | `contact()` (GET) + `contactPost()` (POST) |
| `src/Service/MailService.php` | `sendContactToOwner()` + `sendContactConfirmation()` |
| `config/routes.php` | Route POST `/{lang}/contact` |
| `resources/js/main.js` | `initContactForm()` — AJAX, validation, shake, feedback |
| `resources/scss/layout/_pages.scss` | Styles formulaire, `is-invalid`, `@keyframes shake`, feedback |
| `lang/fr.php` + `lang/en.php` | Clés `contact.*` |

## Variables d'environnement requises

```dotenv
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USER=user@example.com
MAIL_PASS=secret
MAIL_FROM_NAME="Château Crabitan Bellevue"
CONTACT_OWNER_EMAIL=crabitan.bellevue@orange.fr
```

## UX / Accessibilité

- Champs invalides : bordure rouge + animation `shake` (350 ms) re-déclenchée à chaque tentative
- Scroll automatique vers la section formulaire après secousse
- Div feedback `role="alert"` + `aria-live="polite"` pour les lecteurs d'écran
- Spinner sur le bouton pendant l'appel AJAX
- Formulaire réinitialisé (`form.reset()`) après succès

## Tests à couvrir

- [ ] TU : `PageController::contactPost` — CSRF invalide (400), champs manquants (422), envoi OK (200)
- [ ] TI : mock `MailService`, vérifier les deux appels d'envoi
- [ ] E2E : soumettre formulaire incomplet → shake → soumettre complet → message succès
