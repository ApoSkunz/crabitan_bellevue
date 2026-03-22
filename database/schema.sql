-- ============================================================
-- Crabitan Bellevue - Schéma BDD v3
-- Charset  : utf8mb4 (support accents, emojis, caractères spéciaux)
-- Engine   : InnoDB (support FK, transactions)
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";
SET NAMES utf8mb4;

-- ============================================================
-- Table : accounts
-- Utilisateurs (clients, admins, super_admin)
-- gender 'society' : compte entreprise (affichage raison sociale,
--   visuels distincts). Peut évoluer vers un rôle dédié en v4.
-- company_name : rempli uniquement si gender = 'society'
-- ============================================================
CREATE TABLE `accounts` (
  `id`                       INT            NOT NULL AUTO_INCREMENT,
  `lastname`                 VARCHAR(100)   NOT NULL,
  `firstname`                VARCHAR(100)   NOT NULL,
  `company_name`             VARCHAR(255)   DEFAULT NULL COMMENT 'Raison sociale, rempli si gender = society',
  `email`                    VARCHAR(255)   NOT NULL,
  `password`                 VARCHAR(255)   NOT NULL,
  `role`                     ENUM('super_admin','admin','customer') NOT NULL DEFAULT 'customer',
  `gender`                   ENUM('M','F','other','society')        NOT NULL,
  `lang`                     ENUM('fr','en')                        NOT NULL DEFAULT 'fr',
  `newsletter`               TINYINT(1)     NOT NULL DEFAULT 0,
  `email_verification_token` VARCHAR(255)   DEFAULT NULL,
  `email_verified_at`        DATETIME       DEFAULT NULL,
  `google_id`                VARCHAR(255)   DEFAULT NULL COMMENT 'Google OAuth ID',
  `deleted_at`               DATETIME       DEFAULT NULL COMMENT 'Soft delete',
  `created_at`               DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`               DATETIME       DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_accounts_email` (`email`),
  UNIQUE KEY `uq_accounts_google_id` (`google_id`),
  INDEX `idx_accounts_role` (`role`),
  INDEX `idx_accounts_deleted` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table : addresses
-- Adresses de facturation ET de livraison (mutualisées)
-- gender 'society' aligné sur accounts pour les comptes entreprise
-- ============================================================
CREATE TABLE `addresses` (
  `id`           INT            NOT NULL AUTO_INCREMENT,
  `user_id`      INT            NOT NULL,
  `type`         ENUM('billing','delivery') NOT NULL,
  `firstname`    VARCHAR(100)   NOT NULL,
  `lastname`     VARCHAR(100)   NOT NULL,
  `gender`       ENUM('M','F','other','society') NOT NULL,
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
  `certification_label`  VARCHAR(255)   DEFAULT NULL,
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
  `award_path`           VARCHAR(255)   DEFAULT NULL,
  `extra_comment`        JSON           NOT NULL COMMENT '{"fr":"...","en":"..."}',
  `technical_form_path`  VARCHAR(255)   DEFAULT NULL,
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
  `content`          JSON           NOT NULL COMMENT 'Snapshot des articles [{wine_id, qty, price, format, ...}]',
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
  `id_billing_address`  INT            NOT NULL,
  `id_delivery_address` INT            DEFAULT NULL,
  `status`              ENUM('pending','paid','processing','shipped','delivered','cancelled','refunded') NOT NULL DEFAULT 'pending',
  `delivery_tracking`   VARCHAR(255)   DEFAULT NULL,
  `path_invoice`        VARCHAR(255)   DEFAULT NULL,
  `ordered_at`          DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
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
-- Sessions JWT actives
-- ============================================================
CREATE TABLE `connections` (
  `id`             INT            NOT NULL AUTO_INCREMENT,
  `user_id`        INT            NOT NULL,
  `token`          VARCHAR(255)   NOT NULL,
  `client_machine` VARCHAR(255)   NOT NULL,
  `status`         ENUM('active','expired','revoked') NOT NULL DEFAULT 'active',
  `created_at`     DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expired_at`     DATETIME       NOT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_connections_user` FOREIGN KEY (`user_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE,
  INDEX `idx_connections_token` (`token`),
  INDEX `idx_connections_user_status` (`user_id`, `status`)
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

COMMIT;
