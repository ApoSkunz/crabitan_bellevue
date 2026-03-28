-- Migration : ajout de price_type sur pricing_rules
-- À exécuter sur toute BDD créée avant ce changement.

ALTER TABLE `pricing_rules`
  ADD COLUMN `price_type` ENUM('fixed','per_bottle') NOT NULL DEFAULT 'fixed'
    COMMENT 'fixed = montant forfaitaire, per_bottle = tarif × quantité'
    AFTER `delivery_price`;

-- Mise à jour des règles existantes selon leur tranche :
-- tranches 1-23 bt (0€), 24-35 bt (15€ fixe), 36-47 bt (42€ fixe) → fixed
-- tranches 48+ bt (1.30/1.50/1.80 €/bt) → per_bottle
UPDATE `pricing_rules` SET `price_type` = 'per_bottle'
WHERE `format` = 'bottle' AND `min_quantity` >= 48;
