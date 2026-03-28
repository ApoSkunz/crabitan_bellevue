-- Migration : ajout du statut 'return_requested' sur la table orders
-- À exécuter sur toute BDD créée avant ce changement (schema.sql déjà mis à jour).

ALTER TABLE `orders`
  MODIFY COLUMN `status`
    ENUM('pending','paid','processing','shipped','delivered','cancelled','refunded','return_requested')
    NOT NULL DEFAULT 'pending';
