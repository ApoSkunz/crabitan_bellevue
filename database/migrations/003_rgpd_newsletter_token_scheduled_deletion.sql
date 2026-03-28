-- Migration 003 — RGPD : token désabonnement newsletter + suppression programmée 30 jours
-- À exécuter une fois via : mysql -u root -p crabitan_bellevue < database/migrations/003_rgpd_newsletter_token_scheduled_deletion.sql

ALTER TABLE `accounts`
    ADD COLUMN `newsletter_unsubscribe_token` VARCHAR(64) DEFAULT NULL
        COMMENT 'Token désabonnement newsletter (RGPD Art. 21)'
        AFTER `newsletter`,
    ADD COLUMN `scheduled_deletion_at` DATETIME DEFAULT NULL
        COMMENT 'Date de purge effective des données personnelles (J+30 après deleted_at)'
        AFTER `deleted_at`;

-- Génère un token pour les comptes existants (sha2 = 64 chars hex)
UPDATE `accounts`
SET `newsletter_unsubscribe_token` = SHA2(CONCAT(id, email, RAND(), NOW()), 256)
WHERE `newsletter_unsubscribe_token` IS NULL;

CREATE INDEX `idx_accounts_unsub_token` ON `accounts` (`newsletter_unsubscribe_token`);
CREATE INDEX `idx_accounts_sched_del`   ON `accounts` (`scheduled_deletion_at`);
