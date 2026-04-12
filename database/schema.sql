-- ============================================================
-- Crabitan Bellevue - Schéma BDD v3
-- Charset  : utf8mb4 (support accents, emojis, caractères spéciaux)
-- Engine   : InnoDB (support FK, transactions)
-- ============================================================

CREATE DATABASE IF NOT EXISTS `crabitan_bellevue`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `crabitan_bellevue`;

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- DROP (ordre FK inverse pour éviter les contraintes)
-- ============================================================
DROP TABLE IF EXISTS `newsletter_subscriptions`;
DROP TABLE IF EXISTS `admin_logs`;
DROP TABLE IF EXISTS `audit_log`;
DROP TABLE IF EXISTS `newsletter_subscriptions`;
DROP TABLE IF EXISTS `newsletter_attachments`;
DROP TABLE IF EXISTS `newsletters`;
DROP TABLE IF EXISTS `game_scores`;
DROP TABLE IF EXISTS `order_forms`;
DROP TABLE IF EXISTS `password_reset`;
DROP TABLE IF EXISTS `news`;
DROP TABLE IF EXISTS `favorites`;
DROP TABLE IF EXISTS `credit_card_token`;
DROP TABLE IF EXISTS `device_confirm_tokens`;
DROP TABLE IF EXISTS `trusted_devices`;
DROP TABLE IF EXISTS `connections`;
DROP TABLE IF EXISTS `order_invoice_files`;
DROP TABLE IF EXISTS `payment_intents`;
DROP TABLE IF EXISTS `orders`;
DROP TABLE IF EXISTS `order_address_snapshots`;
DROP TABLE IF EXISTS `carts`;
DROP TABLE IF EXISTS `pricing_rules`;
DROP TABLE IF EXISTS `wine_nutrition`;
DROP TABLE IF EXISTS `wines`;
DROP TABLE IF EXISTS `addresses`;
DROP TABLE IF EXISTS `account_companies`;
DROP TABLE IF EXISTS `account_individuals`;
DROP TABLE IF EXISTS `legal_notification_logs`;
DROP TABLE IF EXISTS `legal_documents`;
DROP TABLE IF EXISTS `accounts`;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- Table : accounts (centrale)
-- Credentials + infos communes à tous les types de comptes.
-- Le profil détaillé est dans account_individuals ou account_companies.
-- password NULL : auth sociale uniquement (Google / Apple).
-- ============================================================
CREATE TABLE `accounts` (
  `id`                           INT            NOT NULL AUTO_INCREMENT,
  `email`                        VARCHAR(255)   NOT NULL,
  `password`                     VARCHAR(255)   DEFAULT NULL COMMENT 'NULL si auth sociale uniquement',
  `account_type`                 ENUM('individual','company') NOT NULL DEFAULT 'individual',
  `role`                         ENUM('super_admin','admin','customer') NOT NULL DEFAULT 'customer',
  `lang`                         ENUM('fr','en')              NOT NULL DEFAULT 'fr',
  `newsletter`                   TINYINT(1)     NOT NULL DEFAULT 0,
  `newsletter_unsubscribe_token` VARCHAR(64)    DEFAULT NULL COMMENT 'Token désabonnement newsletter (RGPD Art. 21)',
  `newsletter_optin_pending`     TINYINT(1)     NOT NULL DEFAULT 0 COMMENT 'Consentement newsletter coché à l\'inscription — activé lors de la vérification email',
  `newsletter_confirm_token`     VARCHAR(64)    DEFAULT NULL COMMENT 'Token double opt-in newsletter (déclenchement depuis profil ou formulaire public)',
  `newsletter_confirm_expires_at` DATETIME      DEFAULT NULL COMMENT 'TTL 48h du token de confirmation newsletter',
  `email_verification_token`          VARCHAR(255)   DEFAULT NULL,
  `email_verification_token_expires_at` DATETIME  DEFAULT NULL COMMENT 'TTL 24h — expiration du token de vérification email',
  `email_verified_at`            DATETIME       DEFAULT NULL,
  `email_change_token`           VARCHAR(64)    DEFAULT NULL COMMENT 'SHA-256 du token de changement email',
  `email_change_new_email`       TEXT           DEFAULT NULL COMMENT 'Nouvelle adresse en attente de confirmation — chiffrée AES-256-GCM (RGPD Art. 32)',
  `email_change_expires_at`      DATETIME       DEFAULT NULL COMMENT 'TTL 24h du token de changement email',
  `email_change_used_at`         DATETIME       DEFAULT NULL COMMENT 'Horodatage usage unique',
  `google_id`                    VARCHAR(255)   DEFAULT NULL COMMENT 'Google OAuth ID',
  `apple_id`                     VARCHAR(255)   DEFAULT NULL COMMENT 'Apple Sign In ID',
  `majority_declared_at`         DATETIME       DEFAULT NULL COMMENT 'Horodatage déclaration majorité (Art. L3342-1 CSP)',
  `majority_declared_ip`         TEXT           DEFAULT NULL COMMENT 'IP lors de la déclaration majorité (preuve RGPD Art. 7) — chiffrée AES-256-GCM (RGPD Art. 32)',
  `deleted_at`                   DATETIME       DEFAULT NULL COMMENT 'Soft delete',
  `scheduled_deletion_at`        DATETIME       DEFAULT NULL COMMENT 'Date de purge effective (J+30 après deleted_at)',
  `reactivation_token`           VARCHAR(64)    DEFAULT NULL COMMENT 'Token annulation suppression compte',
  `reactivation_token_expires_at` DATETIME      DEFAULT NULL COMMENT 'TTL 30 jours — enforcement du délai au niveau BDD (audit BDD 2026-04-12)',
  `encryption_version`           TINYINT        NOT NULL DEFAULT 1 COMMENT 'Version de la clé AES-256-GCM — permet la rotation de clé (audit BDD 2026-04-12)',
  `inactivity_warning_sent_at`   DATETIME       DEFAULT NULL COMMENT 'Horodatage envoi avertissement inactivité RGPD (Art. 5 §1e)',
  `deletion_reminder_7d_sent_at` DATETIME       DEFAULT NULL COMMENT 'Horodatage envoi rappel J-7 avant anonymisation',
  `deletion_reminder_1d_sent_at` DATETIME       DEFAULT NULL COMMENT 'Horodatage envoi rappel J-1 avant anonymisation',
  `pending_password_hash`             VARCHAR(255)   DEFAULT NULL COMMENT 'Nouveau MDP en attente de confirmation par email (TTL 1h)',
  `pending_password_expires_at`       DATETIME       DEFAULT NULL COMMENT 'Expiration du MDP en attente',
  `pending_password_session_token`    VARCHAR(512)   DEFAULT NULL COMMENT 'Token de session à l\'origine de la demande de changement MDP',
  `security_confirm_token_hash`       VARCHAR(64)    DEFAULT NULL COMMENT 'SHA-256 du token de confirmation action sécurité (TTL 1h)',
  `security_confirm_token_expires_at` DATETIME       DEFAULT NULL COMMENT 'Expiration du token de confirmation action sécurité',
  `is_blocked`                   TINYINT(1)     NOT NULL DEFAULT 0 COMMENT 'Compte bloqué par un super_admin (accès révoqué)',
  `created_at`                   DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`                   DATETIME       DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `has_connected`                TINYINT(1)     NOT NULL DEFAULT 0 COMMENT 'Vrai dès la première connexion réussie',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_accounts_email`     (`email`),
  UNIQUE KEY `uq_accounts_google_id` (`google_id`),
  UNIQUE KEY `uq_accounts_apple_id`  (`apple_id`),
  INDEX `idx_accounts_role`          (`role`),
  INDEX `idx_accounts_deleted`       (`deleted_at`),
  INDEX `idx_accounts_unsub_token`   (`newsletter_unsubscribe_token`),
  INDEX `idx_accounts_sched_del`         (`scheduled_deletion_at`),
  INDEX `idx_accounts_inactivity_warning` (`inactivity_warning_sent_at`),
  INDEX `idx_accounts_reactiv_token`        (`reactivation_token`),
  INDEX `idx_accounts_email_change_token`   (`email_change_token`),
  INDEX `idx_accounts_nl_confirm_token`     (`newsletter_confirm_token`),
  INDEX `idx_accounts_sec_confirm_token`    (`security_confirm_token_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table : account_individuals
-- Profil particulier (1:1 avec accounts)
-- ============================================================
CREATE TABLE `account_individuals` (
  `id`         INT                   NOT NULL AUTO_INCREMENT,
  `account_id` INT                   NOT NULL,
  `lastname`   VARCHAR(100)          NOT NULL,
  `firstname`  VARCHAR(100)          NOT NULL,
  `civility`   ENUM('M','F','other') NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_account_individuals_account` (`account_id`),
  CONSTRAINT `fk_account_individuals_account`
    FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table : account_companies
-- Profil société (1:1 avec accounts)
-- SIRET facultatif — utile pour facturation pro (restaurateurs, cavistes…)
-- ============================================================
CREATE TABLE `account_companies` (
  `id`           INT          NOT NULL AUTO_INCREMENT,
  `account_id`   INT          NOT NULL,
  `company_name` VARCHAR(255) NOT NULL,
  `siret`        VARCHAR(14)  DEFAULT NULL COMMENT 'SIRET facultatif — facturation pro',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_account_companies_account` (`account_id`),
  CONSTRAINT `fk_account_companies_account`
    FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table : addresses
-- Adresses de facturation ET de livraison (mutualisées)
-- civility : civilité de la personne sur l'étiquette d'envoi
-- ============================================================
CREATE TABLE `addresses` (
  `id`           INT            NOT NULL AUTO_INCREMENT,
  `user_id`      INT            NOT NULL,
  `type`         ENUM('billing','delivery') NOT NULL,
  `firstname`    VARCHAR(100)   NOT NULL,
  `lastname`     VARCHAR(100)   NOT NULL,
  `civility`     ENUM('M','F','other') NULL DEFAULT NULL,
  `street`       VARCHAR(255)   NOT NULL,
  `city`         VARCHAR(100)   NOT NULL,
  `zip_code`     VARCHAR(10)    NOT NULL,
  `country`      VARCHAR(100)   NOT NULL,
  `phone`        VARCHAR(20)    NOT NULL,
  `saved`        TINYINT(1)     NOT NULL DEFAULT 0,
  `deleted_at`          DATETIME    DEFAULT NULL COMMENT 'Soft-delete : adresse utilisée dans une commande — non supprimée physiquement',
  `preserved_for_order` TINYINT(1)  NOT NULL DEFAULT 0 COMMENT 'Préservée pour commande active — purgée par bin/purge_addresses.php',
  `created_at`          DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          DATETIME    DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_addresses_user` FOREIGN KEY (`user_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE,
  INDEX `idx_addresses_user_type`    (`user_id`, `type`),
  INDEX `idx_addresses_deleted`      (`deleted_at`),
  INDEX `idx_addresses_preserved`    (`preserved_for_order`),
  INDEX `idx_addresses_user_deleted` (`user_id`, `deleted_at`) COMMENT 'WHERE user_id = ? AND deleted_at IS NULL — audit BDD 2026-04-12'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table : wines
-- Catalogue des vins
-- Champs JSON bilingues : {"fr": "...", "en": "..."}
-- format : 'bottle' (défaut) | 'bib' (bag-in-box, à venir)
-- wine_color : red | white | rosé | champagne | sparkling | sweet (liquoreux)
-- Typo corrigée : prunning → pruning
-- ============================================================
CREATE TABLE `wines` (
  `id`                   INT            NOT NULL AUTO_INCREMENT,
  `label_name`           VARCHAR(255)   NOT NULL,
  `wine_color`           ENUM('red','white','rosé','champagne','sparkling','sweet') NOT NULL,
  `format`               ENUM('bottle','bib') NOT NULL DEFAULT 'bottle',
  `vintage`              INT            NOT NULL,
  `price`                DECIMAL(10,2)  NOT NULL,
  `production_quantity`  INT            NOT NULL DEFAULT 0 COMMENT 'Nombre de bouteilles produites (millésime)',
  `stock_quantity`       INT            NOT NULL DEFAULT 0 COMMENT 'Stock disponible à la vente (décrémenté à chaque commande confirmée)',
  `available`            TINYINT(1)     NOT NULL DEFAULT 1,
  `certification_label`  ENUM('AOC','IGP','STG','AOP') NOT NULL DEFAULT 'AOC',
  `area`                 DECIMAL(10,2)  NOT NULL COMMENT 'Superficie en hectares',
  `city`                 VARCHAR(255)   NOT NULL,
  `variety_of_vine`      VARCHAR(255)   NOT NULL,
  `age_of_vineyard`      INT            NOT NULL,
  `oenological_comment`  JSON           NOT NULL COMMENT '{"fr":"...","en":"..."}',
  `soil`                 JSON           NOT NULL COMMENT '{"fr":"...","en":"..."}',
  `pruning`              JSON           NOT NULL COMMENT '{"fr":"...","en":"..."}',
  `harvest`              JSON           NOT NULL COMMENT '{"fr":"...","en":"..."}',
  `vinification`         JSON           NOT NULL COMMENT '{"fr":"...","en":"..."}',
  `barrel_fermentation`  JSON           NOT NULL COMMENT '{"fr":"...","en":"..."}',
  `award`                JSON           NOT NULL COMMENT '{"fr":"...","en":"..."}',
  `extra_comment`        JSON           NOT NULL COMMENT '{"fr":"...","en":"..."}',
  `is_cuvee_speciale`    TINYINT(1)     NOT NULL DEFAULT 0,
  `featured`             TINYINT(1)     NOT NULL DEFAULT 0 COMMENT 'Vin du moment affiché sur la homepage (un seul à la fois)',
  `alcohol_degree`       VARCHAR(20)    DEFAULT NULL COMMENT 'ex: 12 ou 12,5 (% vol.)',
  `aging_potential`      VARCHAR(100)   DEFAULT NULL COMMENT 'ex: 5 à 10 ans',
  `image_path`           VARCHAR(255)   NOT NULL,
  `slug`                 VARCHAR(255)   NOT NULL COMMENT 'URL SEO ex: bordeaux-chateau-x-2019',
  `low_stock_notified_at` DATETIME      DEFAULT NULL COMMENT 'Horodatage dernier envoi email alerte stock bas — évite la double notification',
  `created_at`           DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`           DATETIME       DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_wines_slug` (`slug`),
  INDEX `idx_wines_color_available` (`wine_color`, `available`),
  INDEX `idx_wines_format` (`format`),
  INDEX `idx_wines_vintage` (`vintage`),
  INDEX `idx_wines_price` (`price`),
  CONSTRAINT `chk_wine_stock_nonneg` CHECK (`stock_quantity` >= 0),
  CONSTRAINT `chk_wine_price_pos`    CHECK (`price` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table : wine_nutrition
-- Valeurs nutritionnelles + informations complémentaires (1:1 wines)
-- Champs VARCHAR(20) pour supporter les valeurs "<", "traces", etc.
-- ingredients stocké en JSON {"fr":"...","en":"..."} — affiché si ENABLE_EXTRA_INFO=true (règl. UE 2019/787)
-- ============================================================
CREATE TABLE `wine_nutrition` (
  `id`               INT           NOT NULL AUTO_INCREMENT,
  `wine_id`          INT           NOT NULL,
  `energy_kj`        INT           DEFAULT NULL COMMENT 'ex: 336 (kcal calculé automatiquement ÷ 4,184)',
  `fat_g`            VARCHAR(20)   DEFAULT NULL,
  `saturated_fat_g`  VARCHAR(20)   DEFAULT NULL,
  `carbohydrate_g`   VARCHAR(20)   DEFAULT NULL,
  `sugars_g`         VARCHAR(20)   DEFAULT NULL,
  `protein_g`        VARCHAR(20)   DEFAULT NULL,
  `salt_g`           VARCHAR(20)   DEFAULT NULL,
  `ingredients`      TEXT          DEFAULT NULL COMMENT 'Liste ingrédients — affiché si ENABLE_EXTRA_INFO=true',
  `updated_at`       DATETIME      DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_wine_nutrition_wine` (`wine_id`),
  CONSTRAINT `fk_wine_nutrition_wine` FOREIGN KEY (`wine_id`) REFERENCES `wines` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table : pricing_rules
-- Tarifs de livraison dynamiques par format et tranche de quantité
-- Remplace le hardcoding actuel
-- Exemple : 1-5 bouteilles = 8.90€ | 6-11 = 5.90€ | 12+ = 0€ (franco)
-- max_quantity NULL = pas de plafond (tranche ouverte)
-- ============================================================
CREATE TABLE `pricing_rules` (
  `id`               INT           NOT NULL AUTO_INCREMENT,
  `format`           ENUM('bottle','bib') NOT NULL,
  `min_quantity`     INT           NOT NULL,
  `max_quantity`     INT           DEFAULT NULL COMMENT 'NULL = illimité (franco de port)',
  `delivery_price`   DECIMAL(10,2) NOT NULL,
  `price_type`       ENUM('fixed','per_bottle') NOT NULL DEFAULT 'fixed' COMMENT 'fixed = montant forfaitaire, per_bottle = tarif × quantité',
  `withdrawal_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Prix retrait en cave',
  `label`            JSON          NOT NULL COMMENT '{"fr":"...","en":"..."}',
  `active`           TINYINT(1)    NOT NULL DEFAULT 1,
  `created_at`       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME      DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_pricing_format_qty` (`format`, `min_quantity`, `active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table : carts
-- Paniers actifs (un par utilisateur)
-- ============================================================
CREATE TABLE `carts` (
  `id`               INT            NOT NULL AUTO_INCREMENT,
  `user_id`          INT            NOT NULL,
  `content`          JSON           NOT NULL COMMENT 'Articles du panier [{wine_id, qty}] — détails récupérés à la demande',
  `total_quantity`   INT            NOT NULL DEFAULT 0,
  `created_at`        DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        DATETIME       DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `reminder_sent_at`  DATETIME       DEFAULT NULL COMMENT 'Date d\'envoi de la relance email panier abandonné (unique)',
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_carts_user` FOREIGN KEY (`user_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE,
  UNIQUE KEY `uq_carts_user` (`user_id`) COMMENT 'Un seul panier actif par utilisateur'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table : orders
-- Commandes passées
-- ============================================================
-- ============================================================
-- Table : order_address_snapshots
-- Snapshot immuable d'une adresse au moment de la commande.
-- Aucun lien vers accounts ou addresses — dissociation RGPD complète.
-- Permet la suppression d'un compte sans altérer l'historique des commandes (L123-22 C.com.).
-- ============================================================
CREATE TABLE `order_address_snapshots` (
  `id`                INT           NOT NULL AUTO_INCREMENT,
  `source_address_id` INT           NULL DEFAULT NULL COMMENT 'ID origine dans addresses — NULL si adresse manuelle ou compte supprimé',
  `civility`          ENUM('M','F','other') NULL DEFAULT NULL,
  `firstname`         VARCHAR(100)  NOT NULL DEFAULT '',
  `lastname`          VARCHAR(100)  NOT NULL DEFAULT '',
  `street`            VARCHAR(255)  NOT NULL DEFAULT '',
  `city`              VARCHAR(100)  NOT NULL DEFAULT '',
  `zip`               VARCHAR(20)   NOT NULL DEFAULT '',
  `country`           VARCHAR(100)  NOT NULL DEFAULT '',
  `phone`             VARCHAR(30)   NOT NULL DEFAULT '',
  `created_at`        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `pseudonymized_at`  DATETIME      NULL DEFAULT NULL COMMENT 'Date de pseudonymisation RGPD (J+3650 depuis ordered_at)',
  PRIMARY KEY (`id`),
  INDEX `idx_oas_source_address` (`source_address_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `orders` (
  `id`                   INT            NOT NULL AUTO_INCREMENT,
  `user_id`              INT            NOT NULL,
  `order_reference`      VARCHAR(50)    NOT NULL,
  `content`              JSON           NOT NULL COMMENT 'Snapshot du panier au moment de la commande',
  `price`                DECIMAL(10,2)  NOT NULL,
  `payment_method`       VARCHAR(50)    NOT NULL,
  `shipping_discount`    DECIMAL(10,2)  NOT NULL DEFAULT 0.00 COMMENT 'Remise livraison snapshot au moment de la commande',
  `id_billing_snapshot`  INT            NOT NULL COMMENT 'Snapshot adresse facturation — dissocié du compte',
  `id_delivery_snapshot` INT            DEFAULT NULL COMMENT 'Snapshot adresse livraison — dissocié du compte',
  `status`               ENUM('pending','paid','processing','shipped','delivered','cancelled','refunded','return_requested','refund_refused','refund_pending') NOT NULL DEFAULT 'pending',
  `cgv_version`          VARCHAR(20)    NOT NULL DEFAULT '1.0' COMMENT 'Version des CGV acceptées au moment de la commande',
  `ca_numappel`          VARCHAR(11)    DEFAULT NULL COMMENT 'NUMAPPEL CA e-Transactions (lettre T PBX_RETOUR) — requis pour remboursement GAE',
  `ca_numtrans`          VARCHAR(11)    DEFAULT NULL COMMENT 'NUMTRANS CA e-Transactions (lettre S PBX_RETOUR) — requis pour remboursement GAE',
  `invoice_number`       VARCHAR(30)    DEFAULT NULL COMMENT 'Numéro de facture WEB-INVOICE-YYYY-XXXX',
  `path_invoice`         VARCHAR(255)   DEFAULT NULL,
  `ordered_at`           DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `delivered_at`         DATETIME       DEFAULT NULL COMMENT 'Date de livraison confirmée ; base du délai de rétractation (14 j — art. L221-18)',
  `rating_email_sent_at` DATETIME       DEFAULT NULL COMMENT 'Date d\'envoi de l\'email de notation J+7 post-livraison',
  `updated_at`           DATETIME       DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_orders_reference` (`order_reference`),
  CONSTRAINT `fk_orders_user`             FOREIGN KEY (`user_id`)              REFERENCES `accounts`               (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_orders_billing_snapshot` FOREIGN KEY (`id_billing_snapshot`)  REFERENCES `order_address_snapshots` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_orders_delivery_snapshot` FOREIGN KEY (`id_delivery_snapshot`) REFERENCES `order_address_snapshots` (`id`) ON DELETE RESTRICT,
  INDEX `idx_orders_user_status` (`user_id`, `status`),
  INDEX `idx_orders_ordered_at` (`ordered_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table : order_invoice_files
-- Historique de toutes les factures liées à une commande.
-- Conservation obligatoire 10 ans (Art. L441-9 C.com.) — jamais de DELETE.
-- `source` = 'auto' pour les PDF générés automatiquement, 'upload' pour les PDF uploadés par l'admin.
-- ============================================================
CREATE TABLE `order_invoice_files` (
  `id`             INT           NOT NULL AUTO_INCREMENT,
  `order_id`       INT           NOT NULL,
  `path`           VARCHAR(512)  NOT NULL COMMENT 'Chemin relatif depuis ROOT_PATH (ex: storage/invoices/xxx.pdf)',
  `invoice_number` VARCHAR(100)  DEFAULT NULL COMMENT 'Numéro de facture si connu (WEB-INVOICE-YYYY-XXXX)',
  `source`         ENUM('auto','upload') NOT NULL DEFAULT 'upload' COMMENT 'auto = généré automatiquement lors du changement de statut, upload = téléversé par admin',
  `uploaded_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_order_invoice_files_order_id` (`order_id`),
  CONSTRAINT `fk_order_invoice_files_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table : payment_intents
-- Snapshot du panier/adresses stocké côté serveur avant envoi vers CA.
-- Nécessaire car l'IPN CA (server-to-server) n'a pas accès à la session PHP
-- du navigateur client. TTL 1 heure — purgé après création de commande ou expiration.
-- ============================================================
CREATE TABLE `payment_intents` (
  `reference`  VARCHAR(50)  NOT NULL,
  `user_id`    INT          NOT NULL COMMENT 'FK accounts.id — ON DELETE RESTRICT : pas d\'orpheline lors d\'IPN race condition',
  `snapshot`   JSON         NOT NULL COMMENT 'Snapshot complet : items, total, adresses, cgv_version, lang, newsletter',
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Défaut = maintenant : entrée purgée immédiatement si INSERT oublie ce champ',
  PRIMARY KEY (`reference`),
  CONSTRAINT `fk_payment_intents_user` FOREIGN KEY (`user_id`) REFERENCES `accounts` (`id`) ON DELETE RESTRICT,
  KEY `idx_payment_intents_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table : connections
-- Sessions JWT actives + historique appareil
-- device_token : UUID cookie longue durée (90j) — identifiant appareil,
--   plus fiable que l'IP (4G/CGNAT = IP instable).
-- ip_address : audit/affichage uniquement, pas pour la détection.
-- La notion de confiance est portée par trusted_devices (table séparée).
-- ============================================================
CREATE TABLE `connections` (
  `id`           INT            NOT NULL AUTO_INCREMENT,
  `user_id`      INT            NOT NULL,
  `token`        VARCHAR(255)   NOT NULL,
  `device_token` VARCHAR(64)    DEFAULT NULL COMMENT 'UUID cookie 90j — identifiant appareil',
  `ip_address`   VARCHAR(45)    DEFAULT NULL COMMENT 'IPv4 ou IPv6 — audit uniquement',
  `user_agent`   TEXT           DEFAULT NULL,
  `device_name`  VARCHAR(255)   DEFAULT NULL COMMENT 'Ex : Chrome · Windows',
  `auth_method`  ENUM('password','google','apple') NOT NULL DEFAULT 'password',
  `status`       ENUM('active','expired','revoked') NOT NULL DEFAULT 'active',
  `last_used_at` DATETIME       DEFAULT NULL,
  `created_at`   DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expired_at`   DATETIME       NOT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_connections_user` FOREIGN KEY (`user_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE,
  INDEX `idx_connections_token`        (`token`),
  INDEX `idx_connections_device_token` (`device_token`),
  INDEX `idx_connections_user_status`  (`user_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table : trusted_devices
-- Appareils explicitement confirmés par l'utilisateur.
-- Séparé de connections : la confiance survit aux révocations de sessions.
-- confirmed_at : date du premier trust (email ou auto première connexion).
-- last_seen    : mis à jour à chaque connexion depuis cet appareil.
-- ============================================================
CREATE TABLE `trusted_devices` (
  `id`           INT(11)      NOT NULL AUTO_INCREMENT,
  `user_id`      INT(11)      NOT NULL,
  `device_token` VARCHAR(64)  NOT NULL,
  `device_name`  VARCHAR(255) DEFAULT NULL,
  `confirmed_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_seen`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_device` (`user_id`, `device_token`),
  INDEX `idx_td_user`         (`user_id`),
  CONSTRAINT `fk_td_user` FOREIGN KEY (`user_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table : device_confirm_tokens
-- Tokens MFA émis lors d'une connexion depuis un appareil inconnu et non de confiance.
-- Le JWT n'est émis qu'après validation du lien email. Expiration : 15 minutes.
-- ============================================================
CREATE TABLE `device_confirm_tokens` (
  `id`           INT(11)      NOT NULL AUTO_INCREMENT,
  `user_id`      INT(11)      NOT NULL,
  `device_token` VARCHAR(64)  NOT NULL,
  `device_name`  VARCHAR(255) DEFAULT NULL,
  `token`        VARCHAR(64)  NOT NULL,
  `redirect_url` VARCHAR(500) DEFAULT NULL,
  `lang`         VARCHAR(5)   NOT NULL DEFAULT 'fr',
  `expires_at`   DATETIME     NOT NULL,
  `confirmed_at` DATETIME     NULL     DEFAULT NULL COMMENT 'Rempli quand le lien email a été cliqué',
  `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_dct_token` (`token`),
  INDEX `idx_dct_user` (`user_id`),
  CONSTRAINT `fk_dct_user` FOREIGN KEY (`user_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table : credit_card_token
-- Tokens temporaires paiement API Crédit Agricole
-- ============================================================
CREATE TABLE `credit_card_token` (
  `id`         INT            NOT NULL AUTO_INCREMENT,
  `user_id`    INT            NOT NULL,
  `cart_id`    INT            NOT NULL,
  `token`      VARCHAR(255)   NOT NULL,
  `jwt`        TEXT           NOT NULL,
  `created_at` DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` DATETIME       NOT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_cc_token_user` FOREIGN KEY (`user_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cc_token_cart` FOREIGN KEY (`cart_id`) REFERENCES `carts`    (`id`) ON DELETE CASCADE,
  INDEX `idx_cc_token_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table : favorites
-- Vins mis en favoris par les utilisateurs
-- ============================================================
CREATE TABLE `favorites` (
  `id`         INT      NOT NULL AUTO_INCREMENT,
  `user_id`    INT      NOT NULL,
  `wine_id`    INT      NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_favorites_user` FOREIGN KEY (`user_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_favorites_wine` FOREIGN KEY (`wine_id`) REFERENCES `wines`    (`id`) ON DELETE CASCADE,
  UNIQUE KEY `uq_favorites_user_wine` (`user_id`, `wine_id`),
  INDEX `idx_favorites_wine_id` (`wine_id`) COMMENT 'Couvre la sous-requête COUNT likes dans WineModel.getAll()'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table : news
-- Articles / actualités du site
-- Champs JSON bilingues : {"fr": "...", "en": "..."}
-- ============================================================
CREATE TABLE `news` (
  `id`           INT      NOT NULL AUTO_INCREMENT,
  `title`        JSON     NOT NULL COMMENT '{"fr":"...","en":"..."}',
  `text_content` JSON     NOT NULL COMMENT '{"fr":"...","en":"..."}',
  `image_path`   VARCHAR(255) DEFAULT NULL,
  `link_path`    VARCHAR(255) DEFAULT NULL,
  `slug`         VARCHAR(255) NOT NULL COMMENT 'URL SEO ex: degustation-printemps-2025',
  `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_news_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table : password_reset
-- Tokens de réinitialisation de mot de passe
-- (renommé depuis "reset" pour plus de clarté)
-- ============================================================
CREATE TABLE `password_reset` (
  `id`           INT          NOT NULL AUTO_INCREMENT,
  `user_id`      INT          NOT NULL,
  `token`        VARCHAR(255) NOT NULL,
  `requested_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at`   DATETIME     NOT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_password_reset_user` FOREIGN KEY (`user_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE,
  INDEX `idx_password_reset_token` (`token`),
  INDEX `idx_password_reset_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table : order_forms
-- Bons de commande PDF (tarifs annuels, plusieurs versions par an)
-- Servis via PHP — stockés dans storage/order_forms/ (hors public/)
-- ============================================================
CREATE TABLE `order_forms` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `year`        SMALLINT UNSIGNED NOT NULL COMMENT 'Millésime tarifaire ex: 2026',
  `label`       VARCHAR(100)  DEFAULT NULL COMMENT 'Version optionnelle ex: V2, Mise à jour printemps',
  `filename`    VARCHAR(255)  NOT NULL,
  `uploaded_at` DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_order_forms_year` (`year`),
  INDEX `idx_order_forms_uploaded` (`uploaded_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table : game_scores
-- Meilleur score mondial par jeu (sans pseudo — public)
-- ============================================================
CREATE TABLE `game_scores` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `game`        VARCHAR(50)   NOT NULL COMMENT 'Identifiant du jeu ex: vendangeuse',
  `score`       INT UNSIGNED  NOT NULL DEFAULT 0,
  `achieved_at` DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_game` (`game`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `game_scores` (`game`, `score`) VALUES
    ('vendangeuse', 0),
    ('memo', 0),
    ('tracteur', 0);

-- ============================================================
-- Table : newsletters
-- Historique des campagnes envoyées
-- ============================================================
CREATE TABLE `newsletters` (
  `id`           INT            NOT NULL AUTO_INCREMENT,
  `subject`      VARCHAR(255)   NOT NULL,
  `title`        VARCHAR(255)   DEFAULT NULL COMMENT 'Titre optionnel affiché dans la newsletter',
  `body`         TEXT           NOT NULL,
  `image_url`    VARCHAR(500)   DEFAULT NULL COMMENT 'URL publique de l\'image d\'en-tête optionnelle',
  `sent_count`   INT            NOT NULL DEFAULT 0,
  `failed_count` INT            NOT NULL DEFAULT 0,
  `sent_at`      DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `segment_label` VARCHAR(100)  DEFAULT NULL COMMENT 'Segment ciblé lors de l\'envoi (NULL = tous)',
  PRIMARY KEY (`id`),
  INDEX `idx_newsletters_sent_at` (`sent_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table : newsletter_attachments
-- Pièces jointes PDF liées à une campagne (conservées après envoi)
-- ============================================================
CREATE TABLE `newsletter_attachments` (
  `id`            INT            NOT NULL AUTO_INCREMENT,
  `newsletter_id` INT            NOT NULL,
  `original_name` VARCHAR(255)   NOT NULL COMMENT 'Nom original du fichier uploadé',
  `stored_path`   VARCHAR(500)   NOT NULL COMMENT 'Chemin relatif dans storage/newsletters/attachments/',
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_nl_attachment_newsletter`
    FOREIGN KEY (`newsletter_id`) REFERENCES `newsletters` (`id`) ON DELETE CASCADE,
  INDEX `idx_nl_attachment_newsletter` (`newsletter_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- Table : newsletter_links
-- Liens trackés par campagne (redirect click tracking)
-- ============================================================
DROP TABLE IF EXISTS `newsletter_clicks`;
DROP TABLE IF EXISTS `newsletter_links`;
CREATE TABLE `newsletter_links` (
  `id`            INT            NOT NULL AUTO_INCREMENT,
  `newsletter_id` INT            NOT NULL,
  `url`           VARCHAR(2000)  NOT NULL COMMENT 'URL cible originale',
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_nl_link_newsletter`
    FOREIGN KEY (`newsletter_id`) REFERENCES `newsletters` (`id`) ON DELETE CASCADE,
  INDEX `idx_nl_link_newsletter` (`newsletter_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table : newsletter_opens
-- Ouvertures trackées par campagne et abonné (pixel 1×1)
-- Rétention RGPD : 13 mois maximum
-- ============================================================
DROP TABLE IF EXISTS `newsletter_opens`;
CREATE TABLE `newsletter_opens` (
  `id`            INT            NOT NULL AUTO_INCREMENT,
  `newsletter_id` INT            NOT NULL,
  `subscriber_id` INT            NULL COMMENT 'NULL si token invalide',
  `opened_at`     DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_open` (`newsletter_id`, `subscriber_id`),
  INDEX `idx_opened_at` (`opened_at`),
  CONSTRAINT `fk_nl_open_newsletter`
    FOREIGN KEY (`newsletter_id`) REFERENCES `newsletters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table : newsletter_clicks
-- Clics trackés par lien, campagne et abonné
-- Rétention RGPD : 13 mois maximum
-- ============================================================
CREATE TABLE `newsletter_clicks` (
  `id`            INT            NOT NULL AUTO_INCREMENT,
  `newsletter_id` INT            NOT NULL,
  `link_id`       INT            NOT NULL,
  `subscriber_id` INT            NULL COMMENT 'NULL si token invalide',
  `clicked_at`    DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_clicked_at` (`clicked_at`),
  INDEX `idx_nl_click_newsletter` (`newsletter_id`),
  CONSTRAINT `fk_nl_click_newsletter`
    FOREIGN KEY (`newsletter_id`) REFERENCES `newsletters` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_nl_click_link`
    FOREIGN KEY (`link_id`) REFERENCES `newsletter_links` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table : newsletter_unsubscribes
-- Désinscriptions tracées par campagne (abonnés avec compte uniquement)
-- ============================================================
DROP TABLE IF EXISTS `newsletter_unsubscribes`;
CREATE TABLE `newsletter_unsubscribes` (
  `id`              INT      NOT NULL AUTO_INCREMENT,
  `newsletter_id`   INT      NOT NULL,
  `account_id`      INT      NOT NULL,
  `unsubscribed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_nl_unsub` (`newsletter_id`, `account_id`),
  CONSTRAINT `fk_nl_unsub_newsletter` FOREIGN KEY (`newsletter_id`) REFERENCES `newsletters` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_nl_unsub_account`    FOREIGN KEY (`account_id`)    REFERENCES `accounts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table : newsletter_subscriptions
-- Abonnements newsletter visiteurs non-inscrits (double opt-in RGPD Art. 7)
-- Les abonnés avec un compte utilisent accounts.newsletter à la place.
-- ============================================================
DROP TABLE IF EXISTS `newsletter_subscriptions`;
CREATE TABLE `newsletter_subscriptions` (
  `id`                          INT            NOT NULL AUTO_INCREMENT,
  `email`                       VARCHAR(255)   NOT NULL,
  `newsletter_token_hash`       VARCHAR(64)    DEFAULT NULL COMMENT 'SHA-256 du token brut (jamais stocké en clair)',
  `newsletter_token_expires_at` DATETIME       DEFAULT NULL COMMENT 'TTL 48h',
  `newsletter_confirmed`        TINYINT(1)     NOT NULL DEFAULT 0,
  `newsletter_consent_date`     DATETIME       DEFAULT NULL COMMENT 'Horodatage de la confirmation (preuve RGPD)',
  `newsletter_consent_ip`       VARCHAR(45)    DEFAULT NULL COMMENT 'IP lors du clic de confirmation',
  `consent_ip`                  VARCHAR(45)    DEFAULT NULL COMMENT 'IP lors de la soumission du formulaire',
  `lang`                        ENUM('fr','en') NOT NULL DEFAULT 'fr',
  `attempts_24h`                INT            NOT NULL DEFAULT 0 COMMENT 'Compteur de renvois confirmation / 24h',
  `last_attempt_at`             DATETIME       DEFAULT NULL COMMENT 'Date du dernier envoi',
  `created_at`                  DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_newsletter_subscriptions_email` (`email`),
  INDEX `idx_newsletter_subscriptions_token_hash` (`newsletter_token_hash`),
  INDEX `idx_newsletter_subscriptions_confirmed` (`newsletter_confirmed`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- ============================================================
-- Table : audit_log
-- Log d'audit RGPD (Art. 30) — changements email, suppressions, etc.
-- ============================================================
CREATE TABLE `audit_log` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT             NOT NULL COMMENT 'FK accounts.id — ON DELETE RESTRICT pour accountability RGPD Art. 5.2',
  `event_type` VARCHAR(64)     NOT NULL COMMENT 'email_changed, email_change_request, …',
  `ip`         VARCHAR(45)     NOT NULL DEFAULT '' COMMENT 'IPv4 ou IPv6',
  `meta`       JSON            DEFAULT NULL COMMENT 'Données complémentaires (ancienne adresse, etc.)',
  `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_audit_log_user` FOREIGN KEY (`user_id`) REFERENCES `accounts` (`id`) ON DELETE RESTRICT,
  INDEX `idx_audit_log_user_id`    (`user_id`),
  INDEX `idx_audit_log_event_type` (`event_type`),
  INDEX `idx_audit_log_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Log d\'audit RGPD — Art. 30 RGPD';

-- ============================================================
-- Table : admin_logs
-- Journal d'audit des actions admin (RGPD Art. 5.2 accountability).
-- Rétention légale recommandée : 3 ans.
-- Journal immuable — pas de DELETE depuis l'interface.
-- ============================================================
CREATE TABLE `admin_logs` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `admin_id`    INT             NOT NULL COMMENT 'FK accounts — identifiant de l\'admin ayant réalisé l\'action',
  `action`      VARCHAR(100)    NOT NULL COMMENT 'Identifiant d\'action ex: order.status_updated',
  `target_type` VARCHAR(50)     NOT NULL COMMENT 'Type de cible ex: order, wine, account',
  `target_id`   INT             DEFAULT NULL COMMENT 'ID de la cible NULL si non applicable',
  `details`     JSON            DEFAULT NULL COMMENT 'Contexte supplémentaire (ancien/nouveau statut, etc.)',
  `ip_address`  VARCHAR(45)     NOT NULL DEFAULT '' COMMENT 'IPv4 ou IPv6 de l\'admin',
  `created_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_admin_logs_admin` FOREIGN KEY (`admin_id`) REFERENCES `accounts` (`id`) ON DELETE RESTRICT,
  INDEX `idx_admin_logs_admin_id`      (`admin_id`),
  INDEX `idx_admin_logs_action`        (`action`),
  INDEX `idx_admin_logs_created_at`    (`created_at`),
  INDEX `idx_admin_logs_admin_created` (`admin_id`, `created_at`) COMMENT 'WHERE admin_id = ? ORDER BY created_at — audit BDD 2026-04-12'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Journal audit admin — rétention 3 ans (Art. 5.2 RGPD accountability)';

-- ============================================================
-- Table : rgpd_requests
-- Demandes d'exercice de droits RGPD (Art. 15-21 RGPD).
-- Délai légal de traitement : 1 mois (Art. 12 RGPD).
-- Alerte back-office à J+25 (J-5 du délai légal).
-- ============================================================
DROP TABLE IF EXISTS `rgpd_requests`;
CREATE TABLE `rgpd_requests` (
  `id`               INT            NOT NULL AUTO_INCREMENT,
  `type`             ENUM('acces','rectification','effacement','portabilite','opposition') NOT NULL
                       COMMENT 'Art. 15 = accès, 16 = rectification, 17 = effacement, 20 = portabilité, 21 = opposition',
  `requester_email`  VARCHAR(255)   NOT NULL COMMENT 'Email du demandeur',
  `requester_name`   VARCHAR(255)   NOT NULL COMMENT 'Nom complet du demandeur',
  `status`           ENUM('nouvelle','en_cours','traitee','refusee') NOT NULL DEFAULT 'nouvelle',
  `received_at`      DATETIME       NOT NULL                         COMMENT 'Date de réception de la demande (J0 du délai légal)',
  `treated_at`       DATETIME       DEFAULT NULL                     COMMENT 'Date de clôture du traitement',
  `treated_by`       INT            DEFAULT NULL                     COMMENT 'FK accounts.id de l\'admin traitant',
  `response_note`    TEXT           DEFAULT NULL                     COMMENT 'Note interne de traitement',
  `created_at`       TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_rgpd_requests_treated_by`
    FOREIGN KEY (`treated_by`) REFERENCES `accounts` (`id`) ON DELETE SET NULL,
  INDEX `idx_rgpd_requests_status`      (`status`),
  INDEX `idx_rgpd_requests_type`        (`type`),
  INDEX `idx_rgpd_requests_received_at` (`received_at`),
  INDEX `idx_rgpd_requests_email`       (`requester_email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Demandes RGPD Art. 15-21 — délai légal 1 mois (Art. 12)';

-- ============================================================
-- Table : rgpd_export_logs
-- Journal des exports Art. 20 déclenchés par les utilisateurs.
-- Append-only — aucune suppression manuelle autorisée.
-- Rétention 3 ans (accountability Art. 5.2 RGPD).
-- ============================================================
DROP TABLE IF EXISTS `rgpd_export_logs`;
CREATE TABLE `rgpd_export_logs` (
  `id`           INT           NOT NULL AUTO_INCREMENT,
  `user_id`      INT           DEFAULT NULL              COMMENT 'FK accounts.id — nullable si compte supprimé',
  `email`        VARCHAR(255)  NOT NULL                  COMMENT 'Email dénormalisé (consultation post-suppression)',
  `ip`           VARCHAR(45)   DEFAULT NULL              COMMENT 'IP de la requête (IPv4/IPv6)',
  `filename`     VARCHAR(255)  NOT NULL                  COMMENT 'Nom du fichier généré',
  `requested_at` DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at`   DATETIME      NOT NULL                  COMMENT 'Date de purge automatique (rétention 3 ans)',
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_rgpd_export_logs_user`
    FOREIGN KEY (`user_id`) REFERENCES `accounts` (`id`) ON DELETE SET NULL,
  INDEX `idx_rgpd_export_logs_user_id`      (`user_id`),
  INDEX `idx_rgpd_export_logs_requested_at` (`requested_at`),
  INDEX `idx_rgpd_export_logs_expires_at`   (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Journal exports RGPD Art. 20 — accountability Art. 5.2 — rétention 3 ans';

-- ============================================================
-- Table : legal_documents
-- Versioning BDD des documents légaux (remplace config/legal.php)
-- ============================================================
DROP TABLE IF EXISTS `legal_documents`;
CREATE TABLE `legal_documents` (
  `id`                    TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug`                  VARCHAR(64)      NOT NULL UNIQUE,
  `label`                 VARCHAR(128)     NOT NULL,
  `version`               VARCHAR(10)      NOT NULL DEFAULT '1.0',
  `updated_at`            DATE             NOT NULL,
  `requires_notification` TINYINT(1)       NOT NULL DEFAULT 0
    COMMENT '1 = email aux utilisateurs requis lors d\'une MàJ substantielle',
  `last_notified_at`      DATETIME         NULL     DEFAULT NULL,
  `notified_count`        INT UNSIGNED     NOT NULL DEFAULT 0,
  `updated_by`            INT              NULL     DEFAULT NULL
    COMMENT 'FK accounts.id — admin ayant effectué la dernière MàJ',
  `created_at`            DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_legal_documents_admin`
    FOREIGN KEY (`updated_by`) REFERENCES `accounts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Versions et dates des documents légaux — source de vérité BDD';

INSERT INTO `legal_documents` (`slug`, `label`, `version`, `updated_at`, `requires_notification`) VALUES
('cgv',                    'Conditions Générales de Vente',              '1.0', '2026-04-04', 1),
('politique_confidentialite', 'Politique de confidentialité',            '1.0', '2026-04-07', 1),
('mentions_legales',       'Mentions légales',                           '1.0', '2026-04-07', 0),
('matrice_habilitations',  'Matrice des habilitations',                  '1.0', '2026-04-07', 0),
('registre_traitements',   'Registre des activités de traitement',       '1.0', '2026-04-07', 0),
('sous_traitants',         'Liste des sous-traitants',                   '1.0', '2026-04-07', 0),
('procedure_violation',    'Procédure violation de données',             '1.0', '2026-04-07', 0),
('dpia',                   'DPIA — Analyse d\'impact',                   '1.0', '2026-04-07', 0);

-- ============================================================
-- Table : legal_notification_logs
-- Preuve d'envoi RGPD Art. 5.2 — garantit l'idempotence
-- ============================================================
CREATE TABLE `legal_notification_logs` (
  `id`                INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `legal_document_id` TINYINT UNSIGNED NOT NULL,
  `version`           VARCHAR(10)      NOT NULL,
  `account_id`        INT              NOT NULL,
  `sent_at`           DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_legal_notif_once` (`legal_document_id`, `version`, `account_id`),
  CONSTRAINT `fk_legal_notif_doc`
    FOREIGN KEY (`legal_document_id`) REFERENCES `legal_documents` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_legal_notif_account`
    FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Historique des notifications légales envoyées — preuve RGPD Art. 5.2';

-- ============================================================
-- Table : wine_ratings
-- Notation /5 des vins par les clients ayant commandé.
-- Contrainte UNIQUE (account_id, wine_id) : une note par client par vin.
-- token : lien email usage unique pour noter directement depuis l'email.
-- ============================================================
CREATE TABLE IF NOT EXISTS `wine_ratings` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `account_id` INT NOT NULL,
  `wine_id` INT NOT NULL,
  `order_id` INT NOT NULL,
  `rating` TINYINT UNSIGNED NOT NULL,
  `token` VARCHAR(64) NULL DEFAULT NULL,
  `token_used_at` DATETIME NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_account_wine` (`account_id`, `wine_id`),
  UNIQUE KEY `uq_token` (`token`),
  KEY `idx_wine_id` (`wine_id`),
  CONSTRAINT `fk_wr_account` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_wr_wine` FOREIGN KEY (`wine_id`) REFERENCES `wines` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_wr_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_rating_range` CHECK (`rating` BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table : order_rating_tokens
-- Token unique par commande pour la page de notation centralisée.
-- Permet d'afficher tous les vins d'une commande sur une seule page.
-- ============================================================
CREATE TABLE IF NOT EXISTS `order_rating_tokens` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `order_id` INT NOT NULL,
  `account_id` INT NOT NULL,
  `token` VARCHAR(64) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_order_token` (`token`),
  UNIQUE KEY `uq_order_account` (`order_id`, `account_id`),
  CONSTRAINT `fk_ort_order` FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ort_account` FOREIGN KEY (`account_id`) REFERENCES `accounts`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;
