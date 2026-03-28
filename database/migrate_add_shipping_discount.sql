-- Migration : ajout de shipping_discount sur la table orders
-- Snapshot de la remise livraison au moment de la commande (les tarifs peuvent évoluer).
-- À exécuter sur toute BDD créée avant ce changement.

ALTER TABLE `orders`
  ADD COLUMN `shipping_discount` DECIMAL(10,2) NOT NULL DEFAULT 0.00
    COMMENT 'Remise livraison snapshot au moment de la commande'
    AFTER `payment_method`;
