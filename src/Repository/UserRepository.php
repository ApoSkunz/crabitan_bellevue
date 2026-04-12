<?php

declare(strict_types=1);

namespace Repository;

/**
 * Contrat d'accès aux données des comptes utilisateurs.
 *
 * Regroupe les opérations de lecture et d'écriture les plus utilisées par les controllers.
 * L'implémentation concrète (ex. MysqlUserRepository) délègue à AccountModel ou
 * exécute directement les requêtes SQL.
 */
interface UserRepository // NOSONAR — php:S1448 — contrat complet des opérations AccountModel
{
    // ----------------------------------------------------------------
    // Lookup — lecture
    // ----------------------------------------------------------------

    /**
     * Trouve un compte actif par son identifiant.
     *
     * @param int $id Identifiant du compte
     * @return array<string, mixed>|false Compte avec profil joint, ou false
     */
    public function findById(int $id): array|false;

    /**
     * Trouve un compte actif par son adresse email.
     *
     * @param string $email Adresse email
     * @return array<string, mixed>|false Compte avec profil joint, ou false
     */
    public function findByEmail(string $email): array|false;

    /**
     * Trouve un compte en cours de soft-delete par son adresse email.
     *
     * @param string $email Adresse email
     * @return array<string, mixed>|false Compte soft-deleted, ou false
     */
    public function findDeletedByEmail(string $email): array|false;

    /**
     * Trouve un compte actif par son identifiant Google OAuth.
     *
     * @param string $googleId Identifiant Google (sub)
     * @return array<string, mixed>|false
     */
    public function findByGoogleId(string $googleId): array|false;

    /**
     * Trouve un compte soft-deleted par son identifiant Google OAuth.
     *
     * @param string $googleId Identifiant Google (sub)
     * @return array<string, mixed>|false
     */
    public function findDeletedByGoogleId(string $googleId): array|false;

    /**
     * Trouve un compte actif par son identifiant Apple Sign-In.
     *
     * @param string $appleId Identifiant Apple (sub)
     * @return array<string, mixed>|false
     */
    public function findByAppleId(string $appleId): array|false;

    /**
     * Trouve un compte par son token de vérification email (non expiré).
     *
     * @param string $token Token brut (64 hex chars)
     * @return array<string, mixed>|false
     */
    public function findByVerificationToken(string $token): array|false;

    /**
     * Trouve un compte par son token de confirmation de sécurité (haché SHA-256, non expiré).
     *
     * @param string $hash Hash SHA-256 du token brut
     * @return array<string, mixed>|false
     */
    public function findBySecurityConfirmToken(string $hash): array|false;

    /**
     * Trouve un compte par son token de changement d'email (haché SHA-256).
     *
     * @param string $hashedToken Hash SHA-256 du token brut
     * @return array<string, mixed>|false Compte + champs email_change_*, ou false
     */
    public function findByEmailChangeToken(string $hashedToken): array|false;

    /**
     * Trouve un compte en attente de suppression par son token de réactivation.
     *
     * @param string $token Token de réactivation brut
     * @return array<string, mixed>|false
     */
    public function findByReactivationToken(string $token): array|false;

    /**
     * Trouve un compte actif par son token de désinscription newsletter.
     *
     * @param string $token Token de désinscription (32 hex chars)
     * @return array<string, mixed>|false
     */
    public function findByUnsubscribeToken(string $token): array|false;

    /**
     * Vérifie si un compte a déclaré sa majorité.
     *
     * @param int $id Identifiant du compte
     * @return bool
     */
    public function hasDeclaredMajority(int $id): bool;

    // ----------------------------------------------------------------
    // Écriture — création
    // ----------------------------------------------------------------

    /**
     * Crée un nouveau compte client avec token de vérification email.
     *
     * @param string $accountType     Type de compte ('individual' ou 'company')
     * @param string $email           Adresse email
     * @param string $hashedPassword  Mot de passe hashé (bcrypt)
     * @param string $lang            Langue préférée ('fr' ou 'en')
     * @param int    $newsletter      Consentement newsletter (0 ou 1)
     * @param string $verificationToken Token de vérification (64 hex chars)
     * @param string $civility        Civilité — particulier uniquement
     * @param string $lastname        Nom de famille — particulier uniquement
     * @param string $firstname       Prénom — particulier uniquement
     * @param string $companyName     Raison sociale — entreprise uniquement
     * @return string Identifiant du compte créé
     * @throws \Throwable En cas d'erreur BDD (rollback automatique)
     */
    public function create( // NOSONAR — php:S107 — 10 params nécessaires pour le formulaire d'inscription
        string $accountType,
        string $email,
        string $hashedPassword,
        string $lang,
        int $newsletter,
        string $verificationToken,
        string $civility,
        string $lastname,
        string $firstname,
        string $companyName
    ): string;

    /**
     * Crée un compte depuis Google OAuth (email pré-vérifié, sans mot de passe).
     *
     * @param string $email     Adresse email Google
     * @param string $googleId  Identifiant Google (sub)
     * @param string $lang      Langue de l'interface ('fr' ou 'en')
     * @param string $firstname Prénom fourni par Google
     * @param string $lastname  Nom de famille fourni par Google
     * @return int Identifiant du compte créé
     * @throws \Throwable En cas d'erreur BDD
     */
    public function createFromGoogle(
        string $email,
        string $googleId,
        string $lang,
        string $firstname,
        string $lastname
    ): int;

    // ----------------------------------------------------------------
    // Écriture — mise à jour profil
    // ----------------------------------------------------------------

    /**
     * Met à jour le profil d'un compte particulier.
     *
     * @param int    $id        Identifiant du compte
     * @param string $civility  Civilité ('M', 'F', 'other')
     * @param string $firstname Prénom
     * @param string $lastname  Nom de famille
     * @return void
     */
    public function updateIndividualProfile(int $id, string $civility, string $firstname, string $lastname): void;

    /**
     * Met à jour le profil d'un compte entreprise.
     *
     * @param int         $id          Identifiant du compte
     * @param string      $companyName Raison sociale
     * @param string|null $siret       Numéro SIRET (optionnel)
     * @return void
     */
    public function updateCompanyProfile(int $id, string $companyName, ?string $siret): void;

    /**
     * Met à jour le mot de passe haché d'un compte.
     *
     * @param int    $id             Identifiant du compte
     * @param string $hashedPassword Nouveau mot de passe haché (bcrypt)
     * @return void
     */
    public function updatePassword(int $id, string $hashedPassword): void;

    /**
     * Met à jour la langue préférée d'un compte.
     *
     * @param int    $id   Identifiant du compte
     * @param string $lang Code langue ('fr' ou 'en')
     * @return void
     */
    public function updateLang(int $id, string $lang): void;

    /**
     * Met à jour le consentement newsletter d'un compte.
     *
     * @param int  $id        Identifiant du compte
     * @param bool $subscribe true pour s'abonner, false pour se désabonner
     * @return void
     */
    public function updateNewsletter(int $id, bool $subscribe): void;

    /**
     * Rattache un google_id à un compte existant.
     *
     * @param int    $accountId Identifiant du compte
     * @param string $googleId  Identifiant Google (sub)
     * @return void
     */
    public function linkGoogleId(int $accountId, string $googleId): void;

    /**
     * Marque l'email d'un compte comme vérifié et invalide le token de vérification.
     *
     * @param int $id Identifiant du compte
     * @return void
     */
    public function verifyEmail(int $id): void;

    /**
     * Régénère le token de vérification email d'un compte non vérifié.
     *
     * @param int    $id    Identifiant du compte
     * @param string $token Nouveau token brut (64 hex chars)
     * @return void
     */
    public function refreshVerificationToken(int $id, string $token): void;

    /**
     * Marque un compte comme ayant effectué sa première connexion.
     *
     * @param int $id Identifiant du compte
     * @return void
     */
    public function markAsConnected(int $id): void;

    /**
     * Enregistre la déclaration de majorité d'un compte.
     *
     * @param int    $id Identifiant du compte
     * @param string $ip Adresse IP du déclarant
     * @return void
     */
    public function declareMajority(int $id, string $ip): void;

    // ----------------------------------------------------------------
    // Écriture — suppression / réactivation
    // ----------------------------------------------------------------

    /**
     * Planifie la suppression d'un compte (soft-delete sur 30 jours).
     *
     * @param int $id Identifiant du compte
     * @return int Nombre de lignes modifiées
     */
    public function delete(int $id): int;

    /**
     * Réactive un compte en annulant la suppression programmée.
     *
     * @param int $id Identifiant du compte
     * @return int Nombre de lignes modifiées
     */
    public function reactivate(int $id): int;

    /**
     * Révoque toutes les sessions actives d'un compte.
     *
     * @param int $id Identifiant du compte
     * @return void
     */
    public function revokeAllSessions(int $id): void;

    // ----------------------------------------------------------------
    // Écriture — tokens de sécurité
    // ----------------------------------------------------------------

    /**
     * Enregistre un token de confirmation de sécurité (haché SHA-256, TTL 1h).
     *
     * @param int    $userId    Identifiant du compte
     * @param string $hash      Hash SHA-256 du token brut
     * @param string $expiresAt Date d'expiration au format 'Y-m-d H:i:s'
     * @return void
     */
    public function saveSecurityConfirmToken(int $userId, string $hash, string $expiresAt): void;

    /**
     * Invalide le token de confirmation de sécurité après usage.
     *
     * @param int $userId Identifiant du compte
     * @return void
     */
    public function consumeSecurityConfirmToken(int $userId): void;

    /**
     * Stocke un mot de passe en attente de confirmation (TTL 1h).
     *
     * @param int    $userId       Identifiant du compte
     * @param string $hash         Nouveau mot de passe haché (bcrypt)
     * @param string $expiresAt    Date d'expiration au format 'Y-m-d H:i:s'
     * @param string $sessionToken Token JWT de la session ayant initié la demande
     * @return void
     */
    public function savePendingPassword(int $userId, string $hash, string $expiresAt, string $sessionToken): void;

    /**
     * Applique le mot de passe en attente et efface le pending (opération atomique).
     *
     * @param int $userId Identifiant du compte
     * @return int Nombre de lignes modifiées (1 si appliqué, 0 si aucun pending)
     */
    public function applyPendingPassword(int $userId): int;

    /**
     * Efface le mot de passe en attente sans l'appliquer.
     *
     * @param int $userId Identifiant du compte
     * @return void
     */
    public function clearPendingPassword(int $userId): void;

    /**
     * Enregistre la demande de changement d'email avec TTL et nouvelle adresse.
     *
     * @param int         $userId      Identifiant du compte
     * @param string|null $hashedToken Hash SHA-256 du token brut, ou null si email déjà pris
     * @param string      $newEmail    Nouvelle adresse email en attente de confirmation
     * @param string      $expiresAt   Date d'expiration au format 'Y-m-d H:i:s'
     * @return void
     */
    public function saveEmailChangeToken(int $userId, ?string $hashedToken, string $newEmail, string $expiresAt): void;

    /**
     * Applique le changement d'email effectif et marque le token comme utilisé.
     *
     * @param int    $userId   Identifiant du compte
     * @param string $newEmail Nouvelle adresse email définitive
     * @return void
     */
    public function applyEmailChange(int $userId, string $newEmail): void;

    /**
     * Annule une demande de changement d'email en cours.
     *
     * @param int $userId Identifiant du compte
     * @return void
     */
    public function clearEmailChangeToken(int $userId): void;

    /**
     * Enregistre un événement dans le log d'audit RGPD.
     *
     * @param int                  $userId    Identifiant du compte concerné
     * @param string               $eventType Type d'événement (ex. 'email_changed')
     * @param string               $ip        Adresse IP de l'acteur
     * @param array<string, mixed> $meta      Données complémentaires
     * @return void
     */
    public function logAuditEvent(int $userId, string $eventType, string $ip, array $meta = []): void;
}
