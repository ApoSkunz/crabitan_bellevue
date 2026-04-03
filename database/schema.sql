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
DROP TABLE IF EXISTS `orders`;
DROP TABLE IF EXISTS `carts`;
DROP TABLE IF EXISTS `pricing_rules`;
DROP TABLE IF EXISTS `wines`;
DROP TABLE IF EXISTS `addresses`;
DROP TABLE IF EXISTS `account_companies`;
DROP TABLE IF EXISTS `account_individuals`;
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
  `email_change_new_email`       VARCHAR(255)   DEFAULT NULL COMMENT 'Nouvelle adresse en attente de confirmation',
  `email_change_expires_at`      DATETIME       DEFAULT NULL COMMENT 'TTL 24h du token de changement email',
  `email_change_used_at`         DATETIME       DEFAULT NULL COMMENT 'Horodatage usage unique',
  `google_id`                    VARCHAR(255)   DEFAULT NULL COMMENT 'Google OAuth ID',
  `apple_id`                     VARCHAR(255)   DEFAULT NULL COMMENT 'Apple Sign In ID',
  `deleted_at`                   DATETIME       DEFAULT NULL COMMENT 'Soft delete',
  `scheduled_deletion_at`        DATETIME       DEFAULT NULL COMMENT 'Date de purge effective (J+30 après deleted_at)',
  `reactivation_token`           VARCHAR(64)    DEFAULT NULL COMMENT 'Token annulation suppression compte (valide 30 jours)',
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
  INDEX `idx_accounts_sched_del`     (`scheduled_deletion_at`),
  INDEX `idx_accounts_reactiv_token`        (`reactivation_token`),
  INDEX `idx_accounts_email_change_token`   (`email_change_token`),
  INDEX `idx_accounts_nl_confirm_token`     (`newsletter_confirm_token`)
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
  `created_at`   DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME       DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_addresses_user` FOREIGN KEY (`user_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE,
  INDEX `idx_addresses_user_type` (`user_id`, `type`)
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
  `quantity`             INT            NOT NULL DEFAULT 0,
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
  `image_path`           VARCHAR(255)   NOT NULL,
  `slug`                 VARCHAR(255)   NOT NULL COMMENT 'URL SEO ex: bordeaux-chateau-x-2019',
  `created_at`           DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`           DATETIME       DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_wines_slug` (`slug`),
  INDEX `idx_wines_color_available` (`wine_color`, `available`),
  INDEX `idx_wines_format` (`format`),
  INDEX `idx_wines_vintage` (`vintage`),
  INDEX `idx_wines_price` (`price`)
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
  `content`          JSON           NOT NULL COMMENT 'Articles du panier [{wine_id, qty, name, image}] — sans prix',
  `price`            DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
  `withdrawal_price` DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
  `delivery_price`   DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
  `total_quantity`   INT            NOT NULL DEFAULT 0,
  `created_at`       DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME       DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_carts_user` FOREIGN KEY (`user_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE,
  UNIQUE KEY `uq_carts_user` (`user_id`) COMMENT 'Un seul panier actif par utilisateur'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table : orders
-- Commandes passées
-- ============================================================
CREATE TABLE `orders` (
  `id`                  INT            NOT NULL AUTO_INCREMENT,
  `user_id`             INT            NOT NULL,
  `order_reference`     VARCHAR(50)    NOT NULL,
  `content`             JSON           NOT NULL COMMENT 'Snapshot du panier au moment de la commande',
  `price`               DECIMAL(10,2)  NOT NULL,
  `payment_method`      VARCHAR(50)    NOT NULL,
  `shipping_discount`   DECIMAL(10,2)  NOT NULL DEFAULT 0.00 COMMENT 'Remise livraison snapshot au moment de la commande',
  `id_billing_address`  INT            NOT NULL,
  `id_delivery_address` INT            DEFAULT NULL,
  `status`              ENUM('pending','paid','processing','shipped','delivered','cancelled','refunded','return_requested','refund_refused') NOT NULL DEFAULT 'pending',
  `path_invoice`        VARCHAR(255)   DEFAULT NULL,
  `ordered_at`          DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `delivered_at`        DATETIME       DEFAULT NULL COMMENT 'Date de livraison confirmée ; base du délai de rétractation (15 j)',
  `updated_at`          DATETIME       DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_orders_reference` (`order_reference`),
  CONSTRAINT `fk_orders_user`             FOREIGN KEY (`user_id`)             REFERENCES `accounts`  (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_orders_billing_address`  FOREIGN KEY (`id_billing_address`)  REFERENCES `addresses` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_orders_delivery_address` FOREIGN KEY (`id_delivery_address`) REFERENCES `addresses` (`id`) ON DELETE RESTRICT,
  INDEX `idx_orders_user_status` (`user_id`, `status`),
  INDEX `idx_orders_ordered_at` (`ordered_at`)
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
  UNIQUE KEY `uq_favorites_user_wine` (`user_id`, `wine_id`)
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
  `body`         TEXT           NOT NULL,
  `image_url`    VARCHAR(500)   DEFAULT NULL COMMENT 'URL publique de l\'image d\'en-tête optionnelle',
  `sent_count`   INT            NOT NULL DEFAULT 0,
  `failed_count` INT            NOT NULL DEFAULT 0,
  `sent_at`      DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
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
  `user_id`    INT UNSIGNED    NOT NULL,
  `event_type` VARCHAR(64)     NOT NULL COMMENT 'email_changed, email_change_request, …',
  `ip`         VARCHAR(45)     NOT NULL DEFAULT '' COMMENT 'IPv4 ou IPv6',
  `meta`       JSON            DEFAULT NULL COMMENT 'Données complémentaires (ancienne adresse, etc.)',
  `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_audit_log_user_id`    (`user_id`),
  INDEX `idx_audit_log_event_type` (`event_type`),
  INDEX `idx_audit_log_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Log d\'audit RGPD — Art. 30 RGPD';

COMMIT;
