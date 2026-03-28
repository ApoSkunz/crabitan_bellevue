-- Migration 006 : colonne has_connected sur accounts
-- Indique si l'utilisateur s'est déjà connecté au moins une fois.
-- Utilisé pour le premier auto-trust d'appareil indépendamment des sessions.

ALTER TABLE accounts
    ADD COLUMN has_connected TINYINT(1) NOT NULL DEFAULT 0
        COMMENT 'Vrai dès la première connexion réussie'
        AFTER updated_at;

-- Rétro-compatibilité : marquer comme connectés les comptes ayant des sessions
UPDATE accounts
SET has_connected = 1
WHERE id IN (SELECT DISTINCT user_id FROM connections);
