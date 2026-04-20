<?php

declare(strict_types=1);

namespace Core;

/**
 * Canal SMTP à utiliser pour un envoi email.
 *
 * Chaque canal correspond à un compte SMTP IONOS dédié avec son propre quota.
 * Les variables d'environnement suivent le pattern MAIL_{CHANNEL}_{KEY}.
 * Fallback sur MAIL_{KEY} si les variables de canal ne sont pas définies.
 */
enum MailChannel
{
    /** Commandes, factures, welcome, post-achat, panier abandonné — noreply@ */
    case TRANSACTIONNEL;

    /** MFA, reset, activation, suppression, verrouillage compte — security@ */
    case SECURITY;

    /** Newsletter queue, confirmation abonnement — newsletter@ */
    case NEWSLETTER;

    /** Formulaire de contact — contact@ */
    case CONTACT;
}
