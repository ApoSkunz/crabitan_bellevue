-- Migration 008 : colonne confirmed_at sur device_confirm_tokens
-- Permet à la page interstitiel de détecter par polling la validation du lien email
-- sans avoir à re-saisir le mot de passe. Le JWT est émis par l'endpoint de polling.

ALTER TABLE device_confirm_tokens
    ADD COLUMN confirmed_at DATETIME NULL DEFAULT NULL
        COMMENT 'Rempli quand le lien email a été cliqué'
        AFTER expires_at;
