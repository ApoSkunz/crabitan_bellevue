ALTER TABLE `accounts`
    ADD COLUMN `reactivation_token` VARCHAR(64) DEFAULT NULL
        COMMENT 'Token annulation suppression compte (valide 30 jours)'
        AFTER `scheduled_deletion_at`;
CREATE INDEX `idx_accounts_reactiv_token` ON `accounts` (`reactivation_token`);
