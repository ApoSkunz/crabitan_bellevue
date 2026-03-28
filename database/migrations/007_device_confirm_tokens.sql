-- Migration 007 : table device_confirm_tokens
-- Stocke les tokens MFA de confirmation d'appareil.
-- Émis lors d'une connexion depuis un appareil inconnu et non de confiance.
-- Le JWT n'est émis qu'une fois le token validé via le lien email.

CREATE TABLE IF NOT EXISTS device_confirm_tokens (
    id           INT(11)      NOT NULL AUTO_INCREMENT,
    user_id      INT(11)      NOT NULL,
    device_token VARCHAR(64)  NOT NULL,
    device_name  VARCHAR(255) DEFAULT NULL,
    token        VARCHAR(64)  NOT NULL,
    redirect_url VARCHAR(500) DEFAULT NULL,
    lang         VARCHAR(5)   NOT NULL DEFAULT 'fr',
    expires_at   DATETIME     NOT NULL,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_dct_token (token),
    INDEX idx_dct_user (user_id),
    CONSTRAINT fk_dct_user FOREIGN KEY (user_id)
        REFERENCES accounts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
