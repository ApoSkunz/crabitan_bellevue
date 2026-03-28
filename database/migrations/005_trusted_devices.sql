-- Migration 005 : table dédiée aux appareils de confiance
-- Sépare la notion de "session active" (connections) de "appareil reconnu" (trusted_devices)

CREATE TABLE IF NOT EXISTS trusted_devices (
    id           INT(11)       NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id      INT(11)       NOT NULL,
    device_token VARCHAR(64)   NOT NULL,
    device_name  VARCHAR(255)  DEFAULT NULL,
    confirmed_at DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_seen    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY   uq_user_device (user_id, device_token),
    INDEX        idx_td_user   (user_id),
    CONSTRAINT   fk_td_user    FOREIGN KEY (user_id) REFERENCES accounts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Suppression de la colonne is_trusted devenue inutile dans connections
ALTER TABLE connections DROP COLUMN IF EXISTS is_trusted;
