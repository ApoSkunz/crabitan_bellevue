-- ============================================================
-- Crabitan Bellevue — Seed mock data (CI + dev local)
-- Fichier unique remplaçant :
--   seed_wines_prod.sql
--   seed_news_prod.sql
--   seed_order_forms.sql
--   seed_pricing_rules_prod.php
--   seed_users_dev.php
--
-- Mot de passe de tous les comptes test : Dev123456789!
--
-- Import :
--   mysql -h 127.0.0.1 -u root -proot crabitan_bellevue < database/seed_mock_data.sql
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = '';

-- ============================================================
-- PURGE + reset AUTO_INCREMENT (ordre FK inverse)
-- TRUNCATE réinitialise l'auto-increment, DELETE ne le fait pas
-- ============================================================
TRUNCATE TABLE `device_confirm_tokens`;
TRUNCATE TABLE `trusted_devices`;
TRUNCATE TABLE `connections`;
TRUNCATE TABLE `password_reset`;
TRUNCATE TABLE `favorites`;
TRUNCATE TABLE `order_invoice_files`;
TRUNCATE TABLE `orders`;
TRUNCATE TABLE `order_address_snapshots`;
TRUNCATE TABLE `carts`;
TRUNCATE TABLE `addresses`;
TRUNCATE TABLE `account_individuals`;
TRUNCATE TABLE `account_companies`;
TRUNCATE TABLE `accounts`;
TRUNCATE TABLE `pricing_rules`;
TRUNCATE TABLE `wines`;
TRUNCATE TABLE `news`;
TRUNCATE TABLE `order_forms`;
TRUNCATE TABLE `newsletter_clicks`;
TRUNCATE TABLE `newsletter_opens`;
TRUNCATE TABLE `newsletter_links`;
TRUNCATE TABLE `newsletter_subscriptions`;
TRUNCATE TABLE `newsletter_attachments`;
TRUNCATE TABLE `newsletters`;
TRUNCATE TABLE `newsletter_unsubscribes`;
TRUNCATE TABLE `wine_nutrition`;
TRUNCATE TABLE `wine_ratings`;
TRUNCATE TABLE `order_rating_tokens`;
TRUNCATE TABLE `payment_intents`;
TRUNCATE TABLE `credit_card_token`;
TRUNCATE TABLE `audit_log`;
TRUNCATE TABLE `admin_logs`;
TRUNCATE TABLE `rgpd_requests`;
TRUNCATE TABLE `rgpd_export_logs`;
TRUNCATE TABLE `legal_notification_logs`;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- WINES (38 vins — données production)
-- ============================================================

INSERT INTO `wines`
    (`label_name`, `wine_color`, `format`, `vintage`, `price`, `production_quantity`, `available`,
     `certification_label`, `area`, `city`, `variety_of_vine`, `age_of_vineyard`,
     `oenological_comment_fr`, `oenological_comment_en`,
     `soil_fr`, `soil_en`,
     `pruning_fr`, `pruning_en`,
     `harvest_fr`, `harvest_en`,
     `vinification_fr`, `vinification_en`,
     `barrel_fermentation_fr`, `barrel_fermentation_en`,
     `award_fr`, `award_en`,
     `extra_comment_fr`, `extra_comment_en`,
     `image_path`, `slug`, `featured`)
VALUES

-- 1 Sainte-Croix-du-Mont 2010
('Sainte-Croix-du-Mont', 'sweet', 'bottle', 2010, 11.20, 40000, 0, 'AOC', 22.00,
 'Sainte-Croix-du-Mont', 'Sémillon 95%', 30,
 'Une belle robe jaune pâle. Des notes florales, de fruits blancs et un léger caractère miellé. Une attaque assez fraîche en bouche, avec une évolution ample et généreuse. Un bel équilibre entre liqueur et acidité, des retours floraux et un léger miel d\'acacia en bouche, une très bonne longueur et une persistance arômatique.', 'A beautiful pale yellow color. Floral notes, white fruits and a light honey character. A fairly fresh attack on the palate, with an ample and generous evolution. A nice balance between liquor and acidity, floral returns and a light acacia honey on the palate, very good length and aromatic persistence.',
 'Dominante argileuse', 'Dominant clay',
 'Guyot mixte', 'Mixed Guyot',
 'Manuelles (3 tries successives)', 'Manual (3 successive selections)',
 'Fermentation avec levures indigènes en cuves inox', 'Fermentation with indigenous yeasts in stainless steel tanks',
 '36 mois en cuves inox', '36 months in stainless steel tanks',
 'Médaille d\'Or au Concours International de Lyon 2017', 'Gold medal at the 2017 Lyon International Competition',
 '', '',
 'Wine_Sainte-Croix-du-Mont_2010_67ae7a94a14795068e5ed25b3b5e6a92.png', 'sainte-croix-du-mont-2010', 0),

-- 2 Sainte-Croix-du-Mont 2013
('Sainte-Croix-du-Mont', 'sweet', 'bottle', 2013, 9.70, 40000, 0, 'AOC', 22.00,
 'Sainte-Croix-du-Mont', 'Sémillon', 30,
 'Une robe brillante jaune or. Un nez complexe de fruits confits (oranges et mandarines), des nuances de vanille et de caramel avec une petite note de cire. En bouche, on retrouve l\'expression aromatique avec un bon équilibre.', 'A bright yellow dress or. A complex nose of candied fruits (oranges and tangerines), nuances of vanilla and caramel with a hint of wax. In the mouth, we find the aromatic expression with a good balance.',
 'Argilo-calcaire, argilo-graveleux et limono-argileux', 'Clayey-limestone, clayey-gravelly and silty-clayey',
 'Guyot mixte', 'Mixed Guyot',
 'Manuelles (3 tries successives)', 'Manual (3 successive sorting)',
 'Pressurage pneumatique, thermorégulation de la fermentation', 'Pneumatic pressing, thermoregulation of fermentation',
 '36 mois en cuves inox (20% d\'élevage en fût de chêne)', '36 months in stainless steel vats (20% aging in oak barrels)',
 'Médaille d\'Or au Concours de Bordeaux 2015', 'Gold Medal at the Concours de Bordeaux 2015',
 '', '',
 'Wine_Sainte-Croix-du-Mont_2013_75c3faa024e1d039a6338161316830d3.png', 'sainte-croix-du-mont-2013', 0),

-- 3 Premières Côtes de Bordeaux Blanc 2014
('Premières Côtes de Bordeaux Blanc', 'sweet', 'bottle', 2014, 8.00, 10000, 0, 'AOC', 3.00,
 'Gabarnac', 'Sémillon', 45,
 'Une robe brillante, jaune pâle. Le nez est ouvert avec des notes de fleurs blanches. La bouche est élégante avec un bel équilibre sucre-acidité; une finale fraîche et moelleuse sur des notes d\'agrumes légèrement confites.', 'A brilliant, pale yellow color. The nose is open with notes of white flowers. The palate is elegant with a nice balance between sugar and acidity, a fresh finish on slightly lemony citrus notes.',
 'Argileux-sablo-limoneux', 'Clayey-sandy-silty',
 'Guyot simple et mixte', 'Simple and mixed Guyot',
 'Manuelles (3 tries successives)', 'Manuelles par tries successives',
 'Pressurage pneumatique, thermorégulation', 'Pneumatic pressing, thermoregulation',
 '36 mois en cuves inox', '36 months in stainless steel tanks',
 '', '', '', '',
 'Wine_Premières Côtes de Bordeaux Blanc_2014_10238315a21860ff3f5c95b2efabf820.png', 'premieres-cotes-bordeaux-blanc-2014', 0),

-- 4 Sainte-Croix-du-Mont 2014 Cuvée Spéciale
('Sainte-Croix-du-Mont', 'sweet', 'bottle', 2014, 12.60, 7000, 0, 'AOC', 27.00,
 'Sainte-Croix-du-Mont', 'Sémillon 98%', 30,
 'Une robe jaune bouton d\'or. Un nez fruité sur des notes d\'abricot et d\'ananas. De la fraîcheur au nez comme en bouche. Un bon équilibre alcool-liqueur-acidité lui confèrent du gras et du volume de bouche. On retrouve aussi quelques notes vanillées en plus des notes de fruits frais.', 'A button yellow dress. A fruity nose with notes of apricot and pineapple. Freshness on the nose and on the palate. A good alcohol-liquor-acidity balance gives it fat and volume in the mouth. There are also some vanilla notes in addition to notes of fresh fruit.',
 'Dominante argileuse et multiples expositions', 'Dominant clay and multiple exposures',
 'Guyot simple et mixte', 'Simple and mixed Guyot',
 'Manuelles (3 tries successives)', 'Manual (3 successive tests)',
 'Thermorégulation', 'Thermoregulation',
 'Sélection des meilleurs lots de l\'année et passage 12 mois en fûts de chêne', 'Selection of the best lots of the year and passing 12 months in oak barrels',
 'Médaille d\'Or Lyon 2017', 'Gold medal Lyon 2017',
 'Cuvée Spéciale', 'Special Cuvée',
 'Wine_Sainte-Croix-du-Mont_2014_b77c86b19ee68ad74858bd5c2616c69b.png', 'sainte-croix-du-mont-2014-cuvee-speciale', 0),

-- 5 Sainte-Croix-du-Mont 2014
('Sainte-Croix-du-Mont', 'sweet', 'bottle', 2014, 8.70, 30000, 0, 'AOC', 27.00,
 'Sainte-Croix-du-Mont', 'Sémillon 98%', 30,
 'Une robe brillante, jaune or. Un nez de bonne puissance avec des notes de pain d\'épice et de miel. En bouche, une attaque assez vive, avec du volume et un bon équilibre en milieu de bouche, une finale délicate et assez longue.', 'A brilliant, golden yellow color. A nose of good power with hints of gingerbread and honey. On the palate, a fairly lively attack, with volume and a good balance in the mid-palate, a delicate and fairly long finish.',
 'Dominante argileuse et multiples expositions', 'Dominant Clayey and multiple exposures',
 'Guyot simple et mixte', 'Simple and mixed Guyot',
 'Manuelles (3 tries successives)', 'Manual (3 successive tests)',
 'Thermorégulation', 'Thermoregulation',
 '36 mois en cuves inox', '36 months in stainless steel tanks',
 '', '', '', '',
 'Wine_Sainte-Croix-du-Mont_2014_c621a1170fee481938baca6667964ed8.png', 'sainte-croix-du-mont-2014', 0),

-- 6 Côtes de Bordeaux Rouge 2014 Cuvée Spéciale
('Côtes de Bordeaux Rouge', 'red', 'bottle', 2014, 7.80, 26000, 0, 'AOC', 4.00,
 'Gabarnac', 'Merlot 66% - Cabernet Sauvignon 34%', 30,
 'Une belle robe profonde rouge foncée et brillante. Complexe au nez avec des arômes de fruits noirs mûrs (cassis), de vanille. Une attaque en bouche franche et équilibrée, de la fraîcheur, les tanins sont soyeux en milieu de bouche, des notes de raisins mûrs avec une finale harmonieuse.', 'A beautiful deep dark red and shiny color. Complex on the nose with aromas of ripe black fruits (blackcurrant), vanilla. A frank and balanced attack on the palate, freshness, the tannins are silky on the mid-palate, notes of ripe grapes with a harmonious finish.',
 'Argilo-calcaire, argilo-graveleux', 'Clayey-limestone, clayey-gravelly',
 'Guyot mixte', 'Mixed Guyot',
 'Mécaniques', 'Mechanicals',
 'Cuvaison de 3 semaines', '3 weeks vatting',
 '12 mois en fûts de chêne (25% de fûts neufs) après sélection de nos meilleures parcelles de l\'année', '12 months in oak barrels (25% new barrels) after selection of our best plots of the year',
 '', '', 'Cuvée Spéciale', 'Special Cuvée',
 'Wine_Côtes de Bordeaux Rouge_2014_f6c84dbdd93797346f602405e7c4c8c7.png', 'cotes-bordeaux-rouge-2014-cuvee-speciale', 0),

-- 7 Bordeaux Rouge 2016
('Bordeaux Rouge', 'red', 'bottle', 2016, 5.90, 36000, 0, 'AOC', 8.00,
 'Sainte-Croix-du-Mont et Gabarnac', 'Merlot 85% - Cabernet Sauvignon 15%', 20,
 'Une robe rouge sombre avec des reflets rubis. Un nez expressif sur des notes de fruits frais (cassis groseilles). Un bon équilibre en bouche, des tanins assez souples et une bonne tension lui confèrent de la longueur.', 'A dark red color with ruby reflections. An expressive nose with notes of fresh fruit (blackcurrant redcurrants). Good balance on the palate, fairly supple tannins and good tension give it length.',
 'Argilo-calcaire, argilo-graveleux', 'Clayey-limestone, clayey-gravelly',
 'Guyot mixte', 'Mixed Guyot',
 'Mécaniques', 'Mechanicals',
 'Cuvaison 15 jours', '15 days vatting',
 '24 mois en cuves inox', '24 months in stainless steel tanks',
 '', '', '', '',
 'Wine_Bordeaux Rouge_2016_421762234a1334f53b577f3a5527fc6a.png', 'bordeaux-rouge-2016', 0),

-- 8 Vin de Pays de l'Atlantique 2017 (Rosé)
('Vin de Pays de l\'Atlantique', 'rosé', 'bottle', 2017, 5.60, 5000, 0, 'AOC', 1.00,
 'Sainte-Croix-du-Mont', 'Merlot', 30,
 'Une robe brillante avec une teinte vive. Au nez, des notes de bonbons anglais. Assez rond en bouche avec une certaine longueur.', 'A shiny dress with a vivid tint. On the nose, notes of English sweets. Rather round in the mouth with a certain length.',
 'Limono-argileux', 'Silty clay',
 'Guyot mixte', 'Mixed Guyot',
 'Mécaniques', 'Mechanicals',
 'Pressurage direct', 'Direct pressing',
 '5 mois en cuves inox', '5 months in stainless steel tanks',
 '', '', 'Existe aussi en BIB (Bag-In-Box) de 5L.', 'Also available in 5L BIB (Bag-In-Box).',
 'Wine_Vin de Pays de l\'Atlantique_2017_e393a2abe0d1a1b4be051e1773aad3ba.png', 'vin-pays-atlantique-rose-2017', 0),

-- 9 Bordeaux Blanc 2018
('Bordeaux Blanc', 'white', 'bottle', 2018, 6.10, 12000, 0, 'AOC', 2.50,
 'Sainte-Croix-du-Mont', 'Sauvignon blanc & gris 100%', 30,
 'Une robe brillante, blanc/jaune. Un nez citronné et de fleurs blanches. Charnu, un bon volume en bouche avec de la fraîcheur, et une très légère amertume en finale.', 'A brilliant, white / yellow color. A lemony nose and white flowers. Fleshy, a good volume in the mouth with freshness, and a very slight bitterness on the finish.',
 'Argilo-calcaire, argilo-graveleux', 'Clayey-limestone, clayey-gravelly',
 'Guyot mixte', 'Mixed Guyot',
 'Mécaniques', 'Mechanicals',
 'Macération pelliculaire', 'Skin maceration',
 '3 mois en cuves inox', '3 months in stainless steel tanks',
 '', '', 'Existe aussi en BIB (Bag-In-Box) de 5L.', 'Also available in 5L BIB (Bag-In-Box).',
 'Wine_Bordeaux Blanc_2018_7f3ae9aa116216bfad1ec737c746dd20.png', 'bordeaux-blanc-2018', 0),

-- 10 Bordeaux Rouge 2017
('Bordeaux Rouge', 'red', 'bottle', 2017, 6.00, 36000, 0, 'AOC', 8.00,
 'Sainte-Croix-du-Mont et Gabarnac', 'Merlot 85% - Cabernet Sauvignon 15%', 20,
 'Une robe rouge sombre avec des reflets rubis. Un nez expressif sur des notes de fruits frais (cassis groseilles). Un bon équilibre en bouche, des tanins assez souples et une bonne tension lui confèrent de la longueur.', 'A dark red color with ruby reflections. An expressive nose with notes of fresh fruit (blackcurrant redcurrants). Good balance on the palate, fairly supple tannins and good tension give it length.',
 'Argilo-calcaire, argilo-graveleux', 'Clayey-limestone, clayey-gravelly',
 'Guyot mixte', 'Mixed Guyot',
 'Mécaniques', 'Mechanicals',
 'Cuvaison 15 jours', '15 days vatting',
 '24 mois en cuves Inox', '24 months in stainless steel tanks',
 '', '', '', '',
 'Wine_Bordeaux Rouge_2017_ae56077be992be9357190ca829658593.png', 'bordeaux-rouge-2017', 0),

-- 11 Sainte-Croix-du-Mont 2015 Cuvée Spéciale
('Sainte-Croix-du-Mont', 'sweet', 'bottle', 2015, 14.50, 7000, 0, 'AOC', 27.00,
 'Sainte-Croix-du-Mont', 'Sémillon 98%', 30,
 'Une robe jaune bouton d\'or. Un nez fruité sur des notes d\'abricot et d\'ananas. De la fraîcheur au nez comme en bouche. Un bon équilibre alcool-liqueur-acidité lui confèrent du gras et du volume de bouche. On retrouve aussi quelques notes vanillées en plus des notes de fruits frais.', 'A button yellow dress. A fruity nose with notes of apricot and pineapple. Freshness on the nose and on the palate. A good alcohol-liquor-acidity balance gives it fat and volume in the mouth.',
 'Dominante argileuse et multiples expositions', 'Dominant clay and multiple exposures',
 'Guyot simple et mixte', 'Simple and mixed Guyot',
 'Manuelles (3 tries successives)', 'Manual (3 successive sorting)',
 'Thermorégulation', 'Thermoregulation',
 'Sélection des meilleurs lots de l\'année et passage 12 mois en fûts de chêne', 'Selection of the best lots of the year and passing 12 months in oak barrels',
 'Médaille d\'Or Paris 2019', 'Gold Medal Paris 2019',
 'Cuvée Spéciale', 'Special Cuvée',
 'Wine_Sainte-Croix-du-Mont_2015_ecd4d5722452d9e74f9860b58071e626.png', 'sainte-croix-du-mont-2015-cuvee-speciale', 0),

-- 12 Vin de Pays de l'Atlantique 2019 (Rosé)
('Vin de Pays de l\'Atlantique', 'rosé', 'bottle', 2019, 5.80, 5000, 0, 'AOC', 1.00,
 'Sainte-Croix-du-Mont', 'Merlot', 30,
 'Une robe brillante avec une teinte vive. Au nez, des notes de bonbons anglais. Assez rond en bouche avec une certaine longueur.', 'A shiny dress with a vivid tint. On the nose, notes of English sweets. Rather round in the mouth with a certain length.',
 'Limono-argileux', 'Silty clay',
 'Guyot mixte', 'Mixed Guyot',
 'Mécaniques', 'Mechanicals',
 'Pressurage direct', 'Direct pressing',
 '5 mois en cuves inox', '5 months in stainless steel tanks',
 '', '', 'Existe aussi en BIB (Bag-In-Box) de 5L.', 'Also available in 5L BIB (Bag-In-Box).',
 'Wine_Vin de Pays de l\'Atlantique_2019_4a1ce290f10038fd000f17fd7ff13448.png', 'vin-pays-atlantique-rose-2019', 0),

-- 13 Côtes de Bordeaux Rouge 2015 Cuvée Spéciale
('Côtes de Bordeaux Rouge', 'red', 'bottle', 2015, 8.10, 26000, 0, 'AOC', 4.00,
 'Gabarnac', 'Merlot 66% - Cabernet Sauvignon 34%', 30,
 'Une belle robe profonde rouge foncée et brillante. Complexe au nez avec des arômes de fruits noirs mûrs (cassis), de vanille. Une attaque en bouche franche et équilibrée, de la fraîcheur, les tanins sont soyeux en milieu de bouche, des notes de raisins mûrs avec une finale harmonieuse.', 'A beautiful deep dark red and shiny color. Complex on the nose with aromas of ripe black fruits (blackcurrant), vanilla. A frank and balanced attack on the palate.',
 'Argilo-calcaire, argilo-graveleux', 'Clayey-limestone, clayey-gravelly',
 'Guyot mixte', 'Mixed Guyot',
 'Mécaniques', 'Mechanicals',
 'Cuvaison de 3 semaines', '3 weeks vatting',
 '12 mois en fûts de chêne (25% de fûts neufs) après sélection de nos meilleures parcelles de l\'année', '12 months in oak barrels (25% new barrels) after selection of our best plots of the year',
 '', '', 'Cuvée Spéciale', 'Special Cuvée',
 'Wine_Côtes de Bordeaux Rouge_2015_324831ce953b22fa81f2e5ea7efdcf32.png', 'cotes-bordeaux-rouge-2015-cuvee-speciale', 0),

-- 14 Bordeaux Blanc 2019
('Bordeaux Blanc', 'white', 'bottle', 2019, 6.20, 12000, 0, 'AOC', 2.50,
 'Sainte-Croix-du-Mont', 'Sauvignon blanc & gris 100%', 30,
 'Une robe brillante, blanc/jaune. Un nez citronné et de fleurs blanches. Charnu, un bon volume en bouche avec de la fraîcheur, et une très légère amertume en finale.', 'A brilliant, white / yellow color. A lemony nose and white flowers. Fleshy, a good volume in the mouth with freshness, and a very slight bitterness on the finish.',
 'Argilo-calcaire, argilo-graveleux', 'Clayey-limestone, clayey-gravelly',
 'Guyot mixte', 'Mixed Guyot',
 'Mécaniques', 'Mechanicals',
 'Macération pelliculaire', 'Skinn maceration',
 '3 mois en cuves inox', '3 months in stainless steel tanks',
 '', '', 'Existe aussi en BIB (Bag-In-Box) de 5L.', 'Also available in 5L BIB (Bag-In-Box).',
 'Wine_Bordeaux Blanc_2019_73b268b17fd35d7ad8b97533416c2a09.png', 'bordeaux-blanc-2019', 0),

-- 15 Premières Côtes de Bordeaux Blanc 2015
('Premières Côtes de Bordeaux Blanc', 'sweet', 'bottle', 2015, 7.40, 10000, 0, 'AOC', 4.00,
 'Gabarnac', 'Sémillon', 45,
 'Une robe brillante, jaune pâle. Le nez est ouvert avec des notes de fleurs blanches. La bouche est élégante avec un bel équilibre sucre-acidité; une finale fraîche sur des notes d\'agrumes légèrement citronnées.', 'A brilliant, pale yellow color. The nose is open with notes of white flowers. The palate is elegant with a nice balance between sugar and acidity; a fresh finish on slightly lemony citrus notes.',
 'Argileux-sablo-limoneux', 'Clayey-sandy-silty',
 'Guyot simple et mixte', 'Simple and mixed Guyot',
 'Manuelles (3 tries successives)', 'Manual (3 successive tests)',
 'Pressurage pneumatique, thermorégulation', 'Pneumatic pressing, thermoregulation',
 '36 mois en cuves inox', '36 months in stainless steel tanks',
 '', '', '', '',
 'Wine_Premières Côtes de Bordeaux Blanc_2015_90aeceecdc213ae9bb60d72b6c2fbe87.png', 'premieres-cotes-bordeaux-blanc-2015', 0),

-- 16 Sainte-Croix-du-Mont 2015
('Sainte-Croix-du-Mont', 'sweet', 'bottle', 2015, 11.50, 30000, 1, 'AOC', 27.00,
 'Sainte-Croix-du-Mont', 'Sémillon 98%', 30,
 'Une robe brillante, jaune or. Un nez de bonne puissance avec des notes de pain d\'épice et de miel. En bouche, une attaque assez vive, avec du volume et un bon équilibre en milieu de bouche, une finale délicate et assez longue.', 'A shiny, yellow or. A nose of good power with hints of gingerbread and honey. On the palate, a fairly lively attack, with volume and a good balance in the mid-palate, a delicate and fairly long finish.',
 'Dominante argileuse et multiples expositions', 'Dominant Clayey and multiple exposures',
 'Guyot simple et mixte', 'Simple and mixed Guyot',
 'Manuelles (3 tries successives)', 'Manual (3 successive sorting)',
 'Thermorégulation', 'Thermoregulation',
 '36 mois en cuves inox', '36 months in stainless steel tanks',
 'Médaille d\'Or Macon 2017', 'Gold Medal Macon 2017', '', '',
 'Wine_Sainte-Croix-du-Mont_2015_c34d68ee8a0915f54c3b35b37ebb2140.png', 'sainte-croix-du-mont-2015', 0),

-- 17 Côtes de Bordeaux Rouge 2016 Cuvée Spéciale
('Côtes de Bordeaux Rouge', 'red', 'bottle', 2016, 8.20, 13300, 0, 'AOC', 2.00,
 'Sainte-Croix-du-Mont', 'Merlot 74% Cabernet Sauvignon 26%', 25,
 'En cours', 'In progress',
 'Dominante argileuse', 'Dominante argileuse',
 'Guyot simple', 'Guyot mixte',
 'Manuelles par tries successives', 'Mécaniques',
 '16 jours de cuvaison', '16 jours de cuvaison',
 '12 mois en fûts de chêne', '12 mois en futs de chêne',
 '', '', 'Cuvée Spéciale', 'Cuvée Spéciale',
 'Wine_Côtes de Bordeaux Rouge_2016_2d47d13f14e5dea7b63b7023190ee408.png', 'cotes-bordeaux-rouge-2016-cuvee-speciale', 0),

-- 18 Bordeaux Blanc 2020
('Bordeaux Blanc', 'white', 'bottle', 2020, 6.20, 10000, 0, 'AOC', 3.00,
 'Sainte-Croix-du-Mont', '100% Sauvignon Blanc et Gris', 20,
 'Robe brillante assez pâle. Des notes délicates de fruits à chair blanche avec un retour frais légèrement citronné. Bouche souple, équilibrée et rafraichissante.', 'Brilliant, fairly pale colour. Delicate notes of white-fleshed fruits with a fresh, slightly lemony aftertaste. Supple, balanced and refreshing palate.',
 'Argilo calcaire et argilo graveleux', 'Argilo calcaire et argilo graveleux',
 'Guyot simple', 'Simple Guyot',
 'Mécaniques', 'Manuelles par tries successives',
 'Fermentation à 18°C après stabulation à froid', 'Fermentation at 18°C after cold stabilization',
 '6 mois en cuves inox', '6 mois en cuves inox',
 '', '', 'Existe également en Bib de 5 litres', 'Existe également en Bib de 5 litres',
 'Wine_Bordeaux Blanc_2020_a753befa1d63b7dbd6cb95be911ae6d0.png', 'bordeaux-blanc-2020', 0),

-- 19 Bordeaux Rouge 2018
('Bordeaux Rouge', 'red', 'bottle', 2018, 6.20, 36000, 0, 'AOC', 8.00,
 'Sainte-Croix-du-Mont et Gabarnac', 'Merlot 75% - Cabernet Sauvignon 25%', 25,
 'Une robe rouge sombre aux reflets rubis. Un nez expressif avec des notes de fruits frais (cassis groseilles). Un bon équilibre en bouche, des tanins assez souples et une bonne tension lui donnent de la longueur.', 'A dark red color with ruby reflections. An expressive nose with notes of fresh fruit (blackcurrant redcurrants). Good balance on the palate, fairly supple tannins and good tension give it length.',
 'Argilo-calcaire, argilo-graveleux', 'Clayey-limestone, clayey-gravelly',
 'Guyot simple', 'Simple Guyot',
 'Manuelles par tries successives', 'Manuelles par tries successives',
 'Cuvaison 15 jours', '15 days vatting',
 '24 mois en cuve inox', '24 months in stainless steel tanks',
 '', '', '', '',
 'Wine_Bordeaux Rouge_2018_13b1fb44c6cea4d065747b287f62480a.png', 'bordeaux-rouge-2018', 0),

-- 20 Sainte-Croix-du-Mont 2021 (Rosé)
('Sainte-Croix-du-Mont', 'rosé', 'bottle', 2021, 6.00, 4800, 0, 'IGP', 0.90,
 'Sainte-Croix-du-Mont', 'Merlot', 35,
 'Robe brillante d\'un rose très pâle. Nez plein de fraîcheur développant des notes de groseilles. Bouche ronde et charnue se terminant sur la fraîcheur.', 'Brilliant, very pale pink colour. Nose full of freshness developing notes of currants. Round and fleshy palate ending on freshness.',
 'Limono argileux', 'Limono argileux',
 'Guyot simple', 'Guyot mixte',
 'Mécaniques', 'Manuelles par tries successives',
 'Pressurage direct après une courte macération', 'Pressurage direct après une courte macération',
 '3 mois en cuve', '3 mois en cuve',
 '', '', '', '',
 'Wine_Sainte-Croix-du-Mont_2021_19bd27113deeb9b43f7224dfaf14c045.png', 'sainte-croix-du-mont-rose-2021', 0),

-- 21 Sainte-Croix-du-Mont 2016
('Sainte-Croix-du-Mont', 'sweet', 'bottle', 2016, 11.50, 30000, 1, 'AOC', 22.00,
 'Sainte-Croix-du-Mont', 'Sémillon 95%', 35,
 'Belle robe brillante, bouton d\'Or. Le nez encore discret se développe à l\'aération avec des notes de fleurs d\'acacia et de fruits confits. Un bon équilibre en bouche où l\'on retrouve de l\'ananas, une finale chaleureuse et un retour arômatique intense.', 'Beautiful shiny color, buttercup. The still discreet nose develops with aeration with notes of acacia flowers and candied fruits. A good balance in the mouth where we find pineapple, a warm finish and an intense aromatic return.',
 'Dominante argileuse', 'Dominante argileuse',
 'Guyot simple', 'Guyot mixte',
 'Manuelles par tries successives', 'Manuelles par tries successives',
 'Thermorégulation', 'Thermorégulation',
 '36 mois en cuves', '36 mois en cuves',
 '', '', '', '',
 'Wine_Sainte-Croix-du-Mont_2016_5d20efcf2f2b79d2f95536eed2a13f4f.png', 'sainte-croix-du-mont-2016', 0),

-- 22 Bordeaux Rouge 2019
('Bordeaux Rouge', 'red', 'bottle', 2019, 6.80, 30000, 0, 'AOC', 7.00,
 'Sainte Croix du Mont et Gabarnac', 'Merlot 90%, Cabernet Sauvignon 10%', 25,
 'Robe grenat brillante avec quelques reflets briques. Nez de fruits au Kirch légèrement chocolatés. Bouche ronde aromatique, notes de fruits rouges comme la framboise. De la fraîcheur et une finale chaleureuse.', 'Brilliant garnet color with some brick reflections. Slightly chocolate Kirch fruit nose. Round aromatic mouth, notes of red fruits such as raspberry. Freshness and a warm finish.',
 'Dominante argileuse', 'Dominante argileuse',
 'Guyot simple', 'Guyot mixte',
 'Mécaniques', 'Manuelles par tries successives',
 '16 jours de cuvaison', '16 jours de cuvaison',
 '24 mois en cuves Inox', '24 mois en Cuves Inoxs',
 '', '', '', '',
 'Wine_Bordeaux Rouge_2019_8738262f0bbc95e17a83b2ab02c0786a.png', 'bordeaux-rouge-2019', 0),

-- 23 Bordeaux Blanc 2021
('Bordeaux Blanc', 'white', 'bottle', 2021, 6.80, 10000, 0, 'AOC', 3.00,
 'Sainte Croix du Mont', '60% Sauvignon Blanc et 40% Sauvignon Gris', 20,
 'Robe brillante assez pâle. Des notes florales et citronnées. Bouche fraîche, équilibrée et rafraichissante.', 'Bright, pale color. Floral and lemon notes. Supply, balanced and refreshing mouthfeel.',
 'Argilo calcaire et argilo graveleux', 'Argilo calcaire et argilo graveleux',
 'Guyot simple', 'Simple Guyot',
 'Mécaniques', 'Manuelles par tries successives',
 'Fermentation à 18°C après stabulation à froid', 'Fermentation at 18°C after cold stabilization',
 '6 mois en cuves inox', '6 months in stainless steel vats',
 '', '', '', '',
 'Wine_Bordeaux Blanc_2021_c029d830535f1e9e62763fd89f524c6e.png', 'bordeaux-blanc-2021', 0),

-- 24 Vin de Pays de l'Atlantique 2022 (Rosé)
('Vin de Pays de l\'Atlantique', 'rosé', 'bottle', 2022, 6.90, 5700, 0, 'IGP', 1.00,
 'Sainte Croix du Mont', 'Merlot 100%', 30,
 'Jolie robe rose de couleur vive. Des notes de fruits mûrs d\'été sont plaisantes au nez et accompagnent la belle rondeur en bouche.', 'Pretty bright pink color. Notes of ripe summer fruits are pleasant on the nose and accompany the lovely roundness on the palate.',
 'Dominante argilo limoneuse', 'Dominante argilo limoneuse',
 'Guyot mixte', 'Simple Guyot',
 'Mécaniques', 'Manuelles par tries successives',
 'Pressurage direct', 'Pressurage direct',
 '2 mois en cuves inox', '2 mois en cuves inox',
 '', '', '', '',
 'Wine_Vin de Pays de l\'Atlantique_2022_7db0baf4664663ab00d3502828a3f3bc.png', 'vin-pays-atlantique-rose-2022', 0),

-- 25 Côtes de Bordeaux Rouge 2017 Cuvée Spéciale
('Côtes de Bordeaux Rouge', 'red', 'bottle', 2017, 8.90, 13300, 0, 'AOC', 2.50,
 'Gabarnac', 'Merlot 95% Cabernet Sauvignon 5%', 30,
 'Robe rouge d\'intensité moyenne, le nez est ouvert avec des notes franches et délicates de fruits mûrs. Bouche ronde, élégante, très harmonieuse. Vin de plaisir immédiat.', 'Red color of medium intensity, the nose is open with frank and delicate notes of ripe fruit. Round, elegant, very harmonious mouth. Wine of immediate pleasure.',
 'Dominante Argilo calcaire', 'Dominante Argilo calcaire',
 'Guyot mixte', 'Simple Guyot',
 'Mécaniques', 'Manuelles par tries successives',
 '12 jours de cuvaison', '12 jours de cuvaison',
 '12 mois en barriques', '12 mois en barriques',
 'Médaille d\'Or Paris 2020', 'Médaille d\'Or Paris 2020', '', '',
 'Wine_Côtes de Bordeaux Rouge_2017_ea0fd676b0c276b2e4781c1d8027e5dd.png', 'cotes-bordeaux-rouge-2017-cuvee-speciale', 0),

-- 26 Premières Côtes de Bordeaux Blanc 2017
('Premières Côtes de Bordeaux Blanc', 'sweet', 'bottle', 2017, 8.70, 10000, 1, 'AOC', 3.00,
 'Gabarnac', 'Sémillon', 45,
 'Une robe brillante, jaune pâle. Le nez est ouvert avec des notes de fleurs blanches. La bouche est élégante avec un bel équilibre sucre-acidité; une finale fraîche et moelleuse sur des notes d\'agrumes légèrement confites.', 'A shiny, pale yellow robe. The nose is open with notes of white flowers. The palate is elegant with a nice sugar-acidity balance; a fresh and mellow finish on slightly candied citrus notes.',
 'Argileux-sablo-limoneux', 'Argileux-sablo-limoneux',
 'Guyot simple et mixte', 'Simple Guyot',
 'Manuelles (3 tries successives)', 'Manuelles par tries successives',
 'Pressurage pneumatique, thermorégulation', 'Pressurage pneumatique, thermorégulation',
 '36 mois en cuves inox', '36 mois en cuves inox',
 'Médaille d\'Or Bordeaux 2018', 'Médaille d\'Or Bordeaux 2018', '', '',
 'Wine_Premières Côtes de Bordeaux Blanc_2017_59cd069458da9e276c96389a3a1aebd2.png', 'premieres-cotes-bordeaux-blanc-2017', 0),

-- 27 Bordeaux Blanc 2022
('Bordeaux Blanc', 'white', 'bottle', 2022, 7.00, 9000, 0, 'AOC', 3.00,
 'Sainte Croix du Mont', 'Sauvignon blanc et gris', 30,
 'Robe brillante d\'une légère couleur jaune pâle. Au nez des notes florales et de fruits jaunes caractérisant une belle maturité du cépage. Du volume en bouche avec une attaque chaleureuse et une belle fraîcheur arômatique.', 'Robe brillante d\'une légère couleur jaune pâle. Au nez des notes florales et de fruits jaunes caractérisant une belle maturité du cépage.',
 'Dominante Argileuse', 'Dominante Argileuse',
 'Guyot mixte', 'Guyot mixte',
 'Mécaniques', 'Mécaniques',
 'Stabulation et fermentation à température contrôlée', 'Stabulation et fermentation a température contrôlée',
 '4 mois en cuves inox', '4 mois en cuves inox',
 'Médaille d\'Argent CGA Paris 2023', 'Médaille d\'Argent CGA Paris 2023',
 'À déguster avec des huîtres du bassin d\'Arcachon', 'A déguster avec des huitres du bassin d\'Arcahon',
 'Wine_Bordeaux Blanc_2022_61d86aed9dfec852b0185620024d47f4.png', 'bordeaux-blanc-2022', 0),

-- 28 Sainte-Croix-du-Mont 2016 Cuvée Spéciale
('Sainte-Croix-du-Mont', 'sweet', 'bottle', 2016, 15.20, 6000, 1, 'AOC', 22.00,
 'Sainte-Croix-du-Mont', 'Sémillon 95%', 35,
 'Belle robe Or.', 'Belle robe Or.',
 'Dominante Argilo Calcaire', 'Dominante Argilo Calcaire',
 'Guyot mixte', 'Guyot mixte',
 'Manuelles par tries successives', 'Manuelles par tries successives',
 'Traditionnelle', 'Traditionnelle',
 '12 mois en barriques et 3 ans en cuves', '12 mois en barriques et 3 ans en cuves',
 'Médaille d\'argent Bordeaux', 'Médaille d\'argent Bordeaux',
 'Cuvée Spéciale', 'Cuvée Spéciale',
 'Wine_Sainte-Croix-du-Mont_2016_528bb90fbcb9f726b165700436f190e8.png', 'sainte-croix-du-mont-2016-cuvee-speciale', 0),

-- 29 Côtes de Bordeaux Rouge 2018 Cuvée Spéciale
('Côtes de Bordeaux Rouge', 'red', 'bottle', 2018, 9.80, 13300, 0, 'AOC', 2.00,
 'Sainte-Croix-du-Mont et Gabarnac', 'Cabernet Sauvignon 95%, Merlot 5%', 20,
 'Belle robe sombre.', 'Belle robe sombre.',
 'Dominante Argilo Calcaire', 'Dominante Argilo Calcaire',
 'Guyot simple', 'Guyot simple',
 'Mécaniques', 'Mécaniques',
 'Traditionnelle', 'Traditionnelle',
 '12 mois en barriques', '12 mois en barriques',
 'Médaille d\'argent Bordeaux', 'Médaille d\'argent Bordeaux',
 'Cuvée Spéciale', 'Cuvée Spéciale',
 'Wine_Côtes de Bordeaux Rouge_2018_6074a0d584087023d5746ebc00d774b3.png', 'cotes-bordeaux-rouge-2018-cuvee-speciale', 0),

-- 30 Bordeaux Rouge 2020
('Bordeaux Rouge', 'red', 'bottle', 2020, 7.70, 35000, 1, 'AOC', 10.00,
 'Sainte-Croix-du-Mont et Gabarnac', 'Merlot 90%, Cabernet Sauvignon 10%', 20,
 'Belle robe rouge sombre de bonne intensité. Nez expressif sur des notes de fruits mûrs et d\'épices. Bouche ronde, élégance avec de la puissance et une bonne longueur. Les tanins présents laissent augurer un beau potentiel de garde.', 'Belle robe rouge sombre.',
 'Dominante Argilo Calcaire', 'Dominante Argilo Calcaire',
 'Guyot mixte', 'Guyot mixte',
 'Mécaniques', 'Mécaniques',
 'Traditionnelle', 'Traditionnelle',
 '36 mois en cuves et barriques', '36 mois en cuves',
 'Médaille d\'Or CGA Paris 2022', 'Médaille d\'Or CGA Paris 2022', '', '',
 'Wine_Bordeaux Rouge_2020_3afb48525a1d22de6a93bb132ce35957.png', 'bordeaux-rouge-2020', 0),

-- 31 Sainte-Croix-du-Mont 2017
('Sainte-Croix-du-Mont', 'sweet', 'bottle', 2017, 11.00, 35000, 1, 'AOC', 20.00,
 'Sainte Croix du Mont', 'Sémillon 95%, 4% Sauvignon, 1% Muscadelle', 30,
 'Belle robe dorée, brillante. Nez puissant de fruits confits (orange, abricot) et de miel. Bouche onctueuse, riche et avec une pointe d\'amertume amenant de la fraîcheur. Finale longue et digeste.', 'Belle robe dorée, brillante. Nez de fruits frais.',
 'Dominante Argileuse', 'Dominante Argileuse',
 'Guyot mixte', 'Guyot mixte',
 'Manuelles par tries successives', 'Manuelles par tries successives',
 'Traditionnelle, levures indigènes et thermorégulation', 'Traditionnelle',
 '63 mois en cuves et barriques', '36 mois en cuves',
 'Médaille d\'Or Bordeaux — 2 * Guide Hachette', 'Médaille d\'Or Bordeaux', '', '',
 'Wine_Sainte-Croix-du-Mont_2017_5b2273c2a72f87f0d3856c032e88eddd.png', 'sainte-croix-du-mont-2017', 0),

-- 32 Vin de Pays de l'Atlantique 2023 (Rosé)
('Vin de Pays de l\'Atlantique', 'rosé', 'bottle', 2023, 7.50, 5000, 0, 'IGP', 1.00,
 'Sainte Croix du Mont', 'Merlot 95%, Cabernet Sauvignon 5%', 30,
 'Belle robe rose clair, nez parfumé de fruits rouges type groseille, bouche ronde et enrobée avec un soupçon de sucrosité. Fraîcheur conservée en finale.', 'Belle robe rose clair, nez parfumé de fruits rouges type groseille, bouche ronde et enrobée avec un soupçon de sucrosité.',
 'Dominante Argileuse', 'Dominante Argileuse',
 'Guyot mixte', 'Guyot mixte',
 'Mécaniques', 'Mécaniques',
 'Pressurage direct', 'Pressurage direct',
 '5 mois en cuves inox', '5 mois en cuves inox',
 '', '', '', '',
 'Wine_Sainte-Croix-du-Mont_2023_bcd739729f70fdbf1730a5d05a1d9d70.png', 'vin-pays-atlantique-rose-2023', 0),

-- 33 Bordeaux Blanc 2023
('Bordeaux Blanc', 'white', 'bottle', 2023, 7.50, 9000, 0, 'AOC', 3.00,
 'Sainte Croix du Mont', 'Sauvignon blanc et gris', 20,
 'Robe claire et brillante. Des notes fraîches, variétales et fruitées. Une bonne bouche où l\'on retrouve la fraîcheur avec une légère pointe citronnée.', 'Robe claire et brillante. Des notes fraîches, variétales et fruitées.',
 'Dominante Argileuse', 'Dominante Argileuse',
 'Guyot mixte', 'Guyot mixte',
 'Mécaniques', 'Mécaniques',
 'Stabulation et fermentation à température contrôlée', 'Stabulation et fermentation a température contrôlée',
 '5 mois en cuves inox', '5 mois en cuves inox',
 '     ', '     ', '', '',
 'Wine_Bordeaux Blanc_2023_a199f1c1a6d03a211385ff1f31d227f8.png', 'bordeaux-blanc-2023', 0),

-- 34 Côtes de Bordeaux Rouge 2019 Cuvée Spéciale
('Côtes de Bordeaux Rouge', 'red', 'bottle', 2019, 10.00, 13300, 0, 'AOC', 2.00,
 'Sainte-Croix-du-Mont et Gabarnac', 'Cabernet Sauvignon 100%', 20,
 'Jolie robe sombre.', 'Jolie robe sombre.',
 'Dominante Argileuse', 'Dominante Argileuse',
 'Guyot mixte', 'Guyot mixte',
 'Mécaniques', 'Mécaniques',
 '15-18 jours de macération', '1518 jours de macération',
 '12 mois en barrique et 24 mois en cuve', '12 mois en barrique et 24 mois en cuve',
 'Médaille d\'Or Bordeaux', 'Médaille d\'Or Bordeaux', '', '',
 'Wine_Côtes de Bordeaux Rouge_2019_c370e0c109a2d12c2b3457682e981913.png', 'cotes-bordeaux-rouge-2019-cuvee-speciale', 0),

-- 35 Bordeaux Blanc 2024
('Bordeaux Blanc', 'white', 'bottle', 2024, 7.70, 10000, 1, 'AOC', 2.00,
 'Sainte-Croix-du-Mont', 'Sauvignon blanc 65% Sauvignon gris 35%', 25,
 'Robe claire et brillante, nez fruité qui se développe à l\'aération, bouche fraîche et fruitée.', 'Robe claire et brillante, nez fruité qui se développe à l\'aération, bouche fraîche et fruité',
 'Dominante Argilo calcaire', 'Dominante Argilo calcaire',
 'Guyot mixte', 'Guyot mixte',
 'Mécaniques', 'Mécaniques',
 'Pressurage direct puis stabulation au froid 3 jours', 'pressurage direct puis stabulation au froid 3 jours15',
 '5 mois en cuves inox', '5 mois en cuves inox',
 'Médaille d\'Or Bordeaux', 'Médaille d\'Or Bordeaux', '', '',
 'Wine_Bordeaux Blanc_2024_5954f86fb10716610ae55c99d1b69454.png', 'bordeaux-blanc-2024', 0),

-- 36 Sainte-Croix-du-Mont 2018
('Sainte-Croix-du-Mont', 'sweet', 'bottle', 2018, 10.80, 30000, 1, 'AOC', 22.00,
 'Sainte Croix du Mont', 'Sémillon', 35,
 'Belle robe dorée, brillante, bouquet complexe avec des notes d\'oranges confites et de miel. Ces mêmes notes se retrouvent au palais qui est très expressif, ample et d\'une belle fraîcheur.', 'Belle robe dorée, brillante, bouquet complexe avec des notes d\'oranges confites et de miel.',
 'Dominante Argilo calcaire', 'Dominante Argilo calcaire',
 'Guyot mixte', 'Guyot mixte',
 'Manuelles par tries successives', 'Manuelles par tries successives',
 'Pressurage pneumatique, thermorégulation', 'Pressurage pneumatique, thermorégulation',
 '72 mois en cuves et barriques', '72 mois en cuves et barriques',
 'Médaille d\'Argent Lyon — 2 * Guide Hachette', 'Médaille d\'Argent Lyon et 2 *Guide Hachette', '', '',
 'Wine_Sainte-Croix-du-Mont_2018_658de46ecb6c802896343d2de714b701.png', 'sainte-croix-du-mont-2018', 0),

-- 37 Vin de Pays de l'Atlantique 2024 (Rosé)
('Vin de Pays de l\'Atlantique', 'rosé', 'bottle', 2024, 7.50, 4000, 1, 'IGP', 0.60,
 'Sainte Croix du Mont', 'Merlot', 30,
 'Belle robe rose pâle.', 'Belle robe rose pâle.',
 'Argilo limoneux', 'Argilo limoneux',
 'Guyot mixte', 'Guyot mixte',
 'Mécaniques', 'Mécaniques',
 'Fermentation à 18°C', 'Fermentation à 18°C',
 '3 mois en cuves', '3 mois en cuves',
 '', '', '', '',
 'Wine_Vin de Pays de l\'Atlantique_2024_810517101934c7ebf2e0475c4b89f4a1.png', 'vin-pays-atlantique-rose-2024', 0),

-- 38 Côtes de Bordeaux Rouge 2020 Cuvée Spéciale
('Côtes de Bordeaux Rouge', 'red', 'bottle', 2020, 10.00, 13400, 1, 'AOC', 2.50,
 'Gabarnac', 'Merlot 100%', 30,
 'Belle robe intense rouge sombre. Au nez de jolies notes de fruits mûrs. Palais chaleureux, ample, équilibré avec une belle fraîcheur laissant présager un beau potentiel de garde.', 'Belle robe intense rouge sombre. Aux nez de jolies notes de fruits mûrs. Palais chaleureux, ample, équilibré.',
 'Dominante argilo calcaire', 'Dominante argilo calcaire',
 'Guyot mixte', 'Guyot mixte',
 'Mécaniques', 'Mécaniques',
 'Traditionnelle en cuves inox thermorégulées', 'Traditionnelle en cuves inox thermorégulées',
 '12 mois en fûts de chêne', '12 mois en fûts de chêne',
 'Médaille d\'Or Bordeaux 2023 et coup de cœur Guide Hachette', 'Médaille d\'Or Bordeaux 2023 et coup de coeur guide hachette',
 'Cuvée spéciale', 'Cuvée spéciale',
 'Wine_Côtes de Bordeaux Rouge_2020_6a71fd0c7991438d6d191568b920fa14.png', 'cotes-bordeaux-rouge-2020-cuvee-speciale', 0);\n\nUPDATE `wines` SET `is_cuvee_speciale` = 1 WHERE `slug` IN (
    'sainte-croix-du-mont-2014-cuvee-speciale',
    'cotes-bordeaux-rouge-2014-cuvee-speciale',
    'sainte-croix-du-mont-2015-cuvee-speciale',
    'cotes-bordeaux-rouge-2015-cuvee-speciale',
    'cotes-bordeaux-rouge-2016-cuvee-speciale',
    'cotes-bordeaux-rouge-2017-cuvee-speciale',
    'sainte-croix-du-mont-2016-cuvee-speciale',
    'cotes-bordeaux-rouge-2018-cuvee-speciale',
    'cotes-bordeaux-rouge-2019-cuvee-speciale',
    'cotes-bordeaux-rouge-2020-cuvee-speciale'
);

ALTER TABLE `wines` AUTO_INCREMENT = 39;

-- Initialiser le stock disponible = quantité produite (données mock)
UPDATE `wines` SET `stock_quantity` = `production_quantity`;

-- ============================================================
-- WINE NUTRITION (valeurs nutritionnelles — mock représentatif)
-- wine_id correspond aux positions INSERT (1=SCM2010, 7=BxRouge2016, 9=BxBlanc2018, 8=Rosé2017)
-- Inclut des cas : valeurs complètes, valeurs "<", garde, degré alcool, ingrédients
-- ============================================================

-- Alcool + garde : dans la table wines (caractéristiques du vin, pas nutritionnelles)
UPDATE `wines` SET `alcohol_degree` = '12,5', `aging_potential` = '10 à 20 ans' WHERE `slug` = 'sainte-croix-du-mont-2010';
UPDATE `wines` SET `alcohol_degree` = '13',   `aging_potential` = '5 à 8 ans'   WHERE `slug` = 'bordeaux-rouge-2016';
UPDATE `wines` SET `alcohol_degree` = '12',   `aging_potential` = '2 à 5 ans'   WHERE `slug` = 'bordeaux-blanc-2018';
UPDATE `wines` SET `alcohol_degree` = '11,5', `aging_potential` = '1 à 3 ans'   WHERE `slug` = 'vin-pays-atlantique-rose-2017';

-- Sainte-Croix-du-Mont 2010 : liquoreux, sucres élevés, garde longue
INSERT INTO `wine_nutrition` (`wine_id`, `energy_kj`, `fat_g`, `saturated_fat_g`, `carbohydrate_g`, `sugars_g`, `protein_g`, `salt_g`, `ingredients`)
SELECT `id`, 477, '0', '0', '16,5', '16,5', '< 0,2', '< 0,2', '{"fr":"Raisins, Anhydride sulfureux","en":"Grapes, Sulphur dioxide"}'
FROM `wines` WHERE `slug` = 'sainte-croix-du-mont-2010';

-- Bordeaux Rouge 2016 : rouge sec, valeurs typiques
INSERT INTO `wine_nutrition` (`wine_id`, `energy_kj`, `fat_g`, `saturated_fat_g`, `carbohydrate_g`, `sugars_g`, `protein_g`, `salt_g`, `ingredients`)
SELECT `id`, 336, '0', '0', '0,03', '0,03', '< 0,2', '< 0,2', '{"fr":"Raisins, Sulfites","en":"Grapes, Sulphites"}'
FROM `wines` WHERE `slug` = 'bordeaux-rouge-2016';

-- Bordeaux Blanc 2018 : blanc sec
INSERT INTO `wine_nutrition` (`wine_id`, `energy_kj`, `fat_g`, `saturated_fat_g`, `carbohydrate_g`, `sugars_g`, `protein_g`, `salt_g`, `ingredients`)
SELECT `id`, 310, '0', '0', '0,5', '0,5', '< 0,2', '< 0,2', '{"fr":"Raisins, Agent stabilisant : Carboxyméthylcellulose, Sulfites","en":"Grapes, Stabilising agent: Carboxymethylcellulose, Sulphites"}'
FROM `wines` WHERE `slug` = 'bordeaux-blanc-2018';

-- Rosé 2017 : sans ingrédients (ENABLE_EXTRA_INFO désactivé)
INSERT INTO `wine_nutrition` (`wine_id`, `energy_kj`, `fat_g`, `saturated_fat_g`, `carbohydrate_g`, `sugars_g`, `protein_g`, `salt_g`)
SELECT `id`, 295, '0', '0', '1,2', '1,2', '< 0,2', '< 0,2'
FROM `wines` WHERE `slug` = 'vin-pays-atlantique-rose-2017';

-- ============================================================
-- PRICING RULES (tarifs livraison — données production)
-- ============================================================

INSERT INTO `pricing_rules`
    (`format`, `min_quantity`, `max_quantity`, `delivery_price`, `price_type`, `withdrawal_price`, `label`, `active`)
VALUES
('bottle',   1,   23, 0.00, 'fixed',      2.20, '{"fr":"Moins de 2 caisses — pas de remise livraison","en":"Less than 2 cases — no delivery discount"}',     1),
('bottle',  24,   35,15.00, 'fixed',      2.20, '{"fr":"2 caisses — 15 € de remise livraison","en":"2 cases — €15 delivery discount"}',                       1),
('bottle',  36,   47,42.00, 'fixed',      2.20, '{"fr":"3 caisses — 42 € de remise livraison","en":"3 cases — €42 delivery discount"}',                       1),
('bottle',  48,   71, 1.30, 'per_bottle', 2.20, '{"fr":"4 à 5 caisses — 1,30 € / bouteille","en":"4 to 5 cases — €1.30 / bottle"}',                          1),
('bottle',  72,  131, 1.50, 'per_bottle', 2.20, '{"fr":"6 à 10 caisses — 1,50 € / bouteille","en":"6 to 10 cases — €1.50 / bottle"}',                        1),
('bottle', 132,  311, 1.80, 'per_bottle', 2.20, '{"fr":"11 à 25 caisses — 1,80 € / bouteille","en":"11 to 25 cases — €1.80 / bottle"}',                      1),
('bottle', 312, NULL, 1.80, 'per_bottle', 2.20, '{"fr":"Plus de 25 caisses — 1,80 € / bouteille","en":"More than 25 cases — €1.80 / bottle"}',                1);

-- ============================================================
-- NEWS (14 actualités — données production)
-- ============================================================

INSERT INTO `news` (`title`, `text_content`, `image_path`, `link_path`, `slug`, `created_at`) VALUES
('{"fr":"Refonte du site internet","en":"Website redesign"}',
 '{"fr":"Ce nouveau site a été conçu pour vous offrir une meilleure ergonomie (version mobile, tablette, mode jour et mode nuit). Il offre également un meilleur aperçu sur nos produits et notre domaine. Il s\'agit d\'une version Bêta, si vous rencontrez le moindre souci, merci de contacter : service-info@crabitanbellevue.fr. Bonne visite. L\'équipe du Château Crabitan Bellevue.","en":"This new site has been designed to offer you better ergonomics (mobile version, tablet, day mode and night mode). It also offers better insight into our products and our field. This is a Beta version, if you have any concerns, please contact: service-info@crabitanbellevue.fr. Good visit. The Château Crabitan Bellevue team."}',
 NULL, NULL, 'refonte-site-internet-2020', '2020-08-24 12:00:00'),

('{"fr":"Période COVID","en":"COVID period"}',
 '{"fr":"Nous vous informons que la propriété reste ouverte et nous pouvons vous recevoir tout le mois de décembre dans le respect des règles sanitaires. Nous pouvons également préparer vos commandes de vin sous la forme du click and collect.","en":"We inform you that the property remains open and we can receive you all the month of December in compliance with sanitary rules. We can also prepare your wine orders in the form of click and collect."}',
 NULL, NULL, 'periode-covid-2020', '2020-12-06 12:00:00'),

('{"fr":"Bibs 2020 sec et rosé","en":"Bibs 2020 white and rosé"}',
 '{"fr":"Les bibs de 5 litres en Sauvignon blanc sec et Rosé 2020 sont maintenant disponibles à la Propriété. Nous sommes toujours ouverts jusqu\'à 18h (couvre feu oblige). Au plaisir de vous recevoir.","en":"The 5 liter bibs in dry Sauvignon Blanc and Rosé 2020 are now available at the Property. We are always open until 6 p.m. (curfew obliges). We look forward to welcoming you."}',
 NULL, NULL, 'bibs-sec-rose-2020', '2021-02-07 12:00:00'),

('{"fr":"Mise à jour du site","en":"Site update"}',
 '{"fr":"Chers clients, nous vous informons d\'une mise à jour sur l\'ensemble du site afin de palier aux différents problèmes techniques, une meilleure couverture linguistique pour l\'anglais. Nous vous remercions de la confiance que vous nous accordez. Nous restons ouverts en click & collect, à très vite. L\'équipe du Château Crabitan Bellevue","en":"Dear customers, we inform you of an update on the whole site in order to overcome the various technical problems, better language coverage for English. Thank you for placing your trust in us. We remain open in click & collect, see you soon. The Château Crabitan Bellevue team"}',
 'News_84d04bdc9f8a16daedfc829e2f06a495.png', NULL, 'mise-a-jour-site-avril-2021', '2021-04-21 00:04:20'),

('{"fr":"Commandes en ligne","en":"Online orders"}',
 '{"fr":"Le paiement sécurisé par carte bancaire est activé pour vos commandes sur notre site. Pour rappel, vous devez vous inscrire afin d\'accéder à la partie e-commerce de notre site (accessible directement sur la page \'Les vins\').","en":"Secure payment by credit card is activated for your orders on our site. As a reminder, you must register to access the e-commerce part of our website (accessible directly on the \'Wines\' page)."}',
 'News_72ef7dc7c6a04afa3fdfbaae1afd19c5.png', NULL, 'commandes-en-ligne-2021', '2021-10-18 20:25:28'),

('{"fr":"Magnum pour les fêtes","en":"Magnum pour les fêtes"}',
 '{"fr":"Le Sainte-Croix-du-Mont 2016 Cuvée Spéciale est disponible à la propriété en magnum.","en":"Le Sainte-Croix-du-Mont 2016 Cuvée Spéciale est disponible à la propriété en magnum."}',
 'News_08967a606cb0a718fed64708cd2dfd90.jpg', NULL, 'magnum-fetes-2021', '2021-12-25 14:10:21'),

('{"fr":"Bibs rosé et sec dernière récolte","en":"Bibs rosé et sec dernière récolte"}',
 '{"fr":"Vous pouvez dès à présent retrouver le millésime 2021 en blanc sec et rosé disponible en BIBS de 5 litres à la propriété, tout comme notre Bordeaux Rouge 2018.","en":"Vous pouvez dès à présent retrouver le millésime 2021 en blanc sec et rosé disponible en BIBS de 5 litres à la propriété, tout comme notre Bordeaux Rouge 2018."}',
 'News_51e1467136f224aa6e9d4d6507a4cbf3.png', NULL, 'bibs-rose-sec-2022', '2022-01-29 13:52:41'),

('{"fr":"Portes ouvertes Sainte Croix du Mont","en":"Portes ouvertes Sainte Croix du Mont"}',
 '{"fr":"A l\'occasion des portes ouvertes les 19 et 20 novembre prochain, vous aurez l\'occasion de déguster à la propriété un millésime de 40 ans AOC Sainte Croix du Mont 1982.","en":"A l\'occasion des portes ouvertes les 19 et 20 novembre prochain, vous aurez l\'occasion de déguster à la propriété un millésime de 40 ans AOC Sainte Croix du Mont 1982."}',
 NULL, NULL, 'portes-ouvertes-scm-novembre-2022', '2022-11-11 11:07:46'),

('{"fr":"Mise à jour du site","en":"Mise à jour du site"}',
 '{"fr":"Bonjour, dans le cadre d\'une montée de version technique de notre site, vous avez été susceptibles de rencontrer des problèmes techniques le 07/03/23. Ces problèmes sont dorénavant résorbés. Néanmoins, si vous rencontrez un nouveau problème technique, merci de nous le faire parvenir à crabitan.bellevue@orange.fr pour une résolution dans les 48h. L\'équipe technique","en":"Bonjour, dans le cadre d\'une montée de version technique de notre site, vous avez été susceptibles de rencontrer des problèmes techniques le 07/03/23. Ces problèmes sont dorénavant résorbés."}',
 NULL, NULL, 'mise-a-jour-technique-mars-2023', '2023-03-08 06:54:52'),

('{"fr":"Portes Ouvertes les 18 et 19 novembre 2023","en":"Portes Ouvertes les 18 et 19 novembre 2023"}',
 '{"fr":"Nos chais seront ouverts et à cette occasion nous vous ferons découvrir une association huîtres du bassin et Crabitan Bellevue blanc Doux. Un vieux millésime également à redéguster : le 2003.","en":"Nos chais seront ouverts et à cette occasion nous vous ferons découvrir une association huîtres du bassin et Crabitan Bellevue blanc Doux."}',
 'News_37713404fe3e34e6384add3ce72d503f.jpg', NULL, 'portes-ouvertes-18-19-novembre-2023', '2023-11-13 17:36:10'),

('{"fr":"Paiement CB","en":"Paiement CB"}',
 '{"fr":"Le paiement par carte bancaire est actuellement indisponible. Veuillez nous en excuser. Les paiements par chèque ou virement bancaire sont eux possibles. L\'équipe du Château Crabitan Bellevue.","en":"Le paiement par carte bancaire est actuellement indisponible. Veuillez nous en excuser. Les paiements par chèque ou virement bancaire sont eux possibles."}',
 NULL, NULL, 'paiement-cb-indisponible-2023', '2023-12-14 14:57:36'),

('{"fr":"Voeux 2024","en":"Voeux 2024"}',
 '{"fr":"Chers amateurs de vin, l\'équipe du Château Crabitan Bellevue vous adresse ses vœux les plus chaleureux pour la nouvelle année 2024. Nous sommes également ravis de vous annoncer le retour du paiement par carte bancaire. Santé et bonheur, l\'équipe du Château Crabitan Bellevue","en":"Dear wine lovers, the Château Crabitan Bellevue team sends you its warmest wishes for the new year 2024. We are also pleased to announce the return of payment by credit card. Health and happiness, the Château Crabitan Bellevue team"}',
 'News_142ac6d92c0c325c5a1508fe34ed3796.jpg', NULL, 'voeux-2024', '2024-01-16 07:13:58'),

('{"fr":"Mise à jour technique du site","en":"Technical update of the site"}',
 '{"fr":"Chers utilisateurs, nous tenons à vous informer qu\'une mise à jour majeure de notre infrastructure a été réalisée avec succès. Cette mise à jour vise à améliorer votre expérience utilisateur et à garantir la cybersécurité du site.","en":"Dear users, we would like to inform you that a major update of our infrastructure has been successfully completed. This update aims to improve your user experience and to guarantee the cybersecurity of the site."}',
 NULL, NULL, 'mise-a-jour-technique-septembre-2024', '2024-09-08 19:43:00'),

('{"fr":"Portes Ouvertes 2025","en":"Portes Ouvertes 2025"}',
 '{"fr":"Venez nous rendre visite dans nos chais les 15 et 16 octobre prochains. À cette occasion, vous pourrez déguster un vieux millésime (1995) de Sainte Croix du Mont, sentir ses parfums complexes d\'évolution et apprécier son potentiel tout au long des années.","en":"Venez nous rendre viste dans nos chais les 15 et 16 octobre prochains. À cette occasion vous pourrez déguster un vieux millésime (1995) de Sainte Croix du Mont."}',
 'News_c57afc261edbf7a4adde00d6c4ebe603.jpg', NULL, 'portes-ouvertes-2025', '2025-11-10 08:03:04');

ALTER TABLE `news` AUTO_INCREMENT = 15;

-- ============================================================
-- ORDER FORMS (bons de commande historiques)
-- ============================================================

INSERT INTO `order_forms` (`year`, `label`, `filename`, `uploaded_at`) VALUES
(2020, NULL,  '2020_prices.pdf',    '2020-01-01 00:00:00'),
(2021, NULL,  '2021_prices.pdf',    '2021-01-01 00:00:00'),
(2021, 'V2',  '2021_prices_V2.pdf', '2021-06-01 00:00:00'),
(2022, NULL,  '2022_prices.pdf',    '2022-01-01 00:00:00'),
(2023, NULL,  '2023_prices.pdf',    '2023-01-01 00:00:00'),
(2024, NULL,  '2024_prices.pdf',    '2024-01-01 00:00:00'),
(2025, NULL,  '2025_prices.pdf',    '2025-01-01 00:00:00'),
(2026, NULL,  '2026_prices.pdf',    '2026-01-01 00:00:00');

-- ============================================================
-- ACCOUNTS — mot de passe : Dev123456789! (bcrypt cost 10)
-- id 1 = super_admin | 2 = admin | 3–7 = customers
-- ============================================================

INSERT INTO `accounts`
    (`id`, `email`, `password`, `account_type`, `role`, `lang`, `newsletter`,
     `email_verified_at`, `has_connected`, `created_at`)
VALUES
-- Mot de passe de test unifié : Dev123456789! — données mock uniquement, jamais en production
-- has_connected=0 sur tous les comptes sauf id 3 (TU/TI/E2E — appareil de confiance préenregistré)
-- Super admin
(1, 'superadmin@dev.local',       '$2y$10$zvmI7JOSrZn00L4Hdg6LCOcPBuoWhDyiXN7KT0jCHtqJ9ec/JDr0a', 'individual', 'super_admin', 'fr', 0, '2026-01-01 08:00:00', 0, '2026-01-01 08:00:00'), -- nosemgrep: generic.secrets.security.detected-bcrypt-hash.detected-bcrypt-hash
-- Admin
(2, 'admin@dev.local',            '$2y$10$zvmI7JOSrZn00L4Hdg6LCOcPBuoWhDyiXN7KT0jCHtqJ9ec/JDr0a', 'individual', 'admin',       'fr', 0, '2026-01-01 08:00:00', 0, '2026-01-01 08:00:00'), -- nosemgrep: generic.secrets.security.detected-bcrypt-hash.detected-bcrypt-hash
-- Client vérifié avec commandes — has_connected=1 + appareil de confiance pour TU/TI/E2E
(3, 'client.verifie@dev.local',   '$2y$10$zvmI7JOSrZn00L4Hdg6LCOcPBuoWhDyiXN7KT0jCHtqJ9ec/JDr0a', 'individual', 'customer',    'fr', 1, '2026-01-10 10:00:00', 1, '2026-01-10 10:00:00'), -- nosemgrep: generic.secrets.security.detected-bcrypt-hash.detected-bcrypt-hash
-- Client non vérifié (email non confirmé)
(4, 'client.nouveau@dev.local',   '$2y$10$zvmI7JOSrZn00L4Hdg6LCOcPBuoWhDyiXN7KT0jCHtqJ9ec/JDr0a', 'individual', 'customer',    'fr', 0, NULL,                 0, '2026-02-01 09:00:00'), -- nosemgrep: generic.secrets.security.detected-bcrypt-hash.detected-bcrypt-hash
-- Client société
(5, 'societe@dev.local',          '$2y$10$zvmI7JOSrZn00L4Hdg6LCOcPBuoWhDyiXN7KT0jCHtqJ9ec/JDr0a', 'company',    'customer',    'fr', 1, '2026-01-15 14:00:00', 0, '2026-01-15 14:00:00'), -- nosemgrep: generic.secrets.security.detected-bcrypt-hash.detected-bcrypt-hash
-- Client EN
(6, 'customer.en@dev.local',      '$2y$10$zvmI7JOSrZn00L4Hdg6LCOcPBuoWhDyiXN7KT0jCHtqJ9ec/JDr0a', 'individual', 'customer',    'en', 1, '2026-02-10 11:00:00', 0, '2026-02-10 11:00:00'), -- nosemgrep: generic.secrets.security.detected-bcrypt-hash.detected-bcrypt-hash
-- Client soft-deleted (en attente de suppression)
(7, 'client.supprime@dev.local',  '$2y$10$zvmI7JOSrZn00L4Hdg6LCOcPBuoWhDyiXN7KT0jCHtqJ9ec/JDr0a', 'individual', 'customer',    'fr', 0, '2026-01-20 08:00:00', 0, '2026-01-20 08:00:00'), -- nosemgrep: generic.secrets.security.detected-bcrypt-hash.detected-bcrypt-hash
-- Compte RGPD purgé (ancienne commande > 10 ans, compte anonymisé après effacement Art. 17)
-- L'email @purged.invalid est la convention de pseudonymisation du compte
(8, 'a1b2c3d4e5f6@purged.invalid', '$2y$10$zvmI7JOSrZn00L4Hdg6LCOcPBuoWhDyiXN7KT0jCHtqJ9ec/JDr0a', 'individual', 'customer', 'fr', 0, NULL, 0, '2014-01-01 00:00:00'), -- nosemgrep: generic.secrets.security.detected-bcrypt-hash.detected-bcrypt-hash
-- ── Comptes RGPD inactivité (cron test) ───────────────────────────────────────
-- id 9 : inactif 37 mois, aucune connexion, aucun avertissement → cible phase 1 (purge_inactive_accounts.php)
(9, 'rgpd.inactif.warn@dev.local',   '$2y$10$zvmI7JOSrZn00L4Hdg6LCOcPBuoWhDyiXN7KT0jCHtqJ9ec/JDr0a', 'individual', 'customer', 'fr', 0, '2023-01-01 08:00:00', 0, '2023-01-01 08:00:00'), -- nosemgrep: generic.secrets.security.detected-bcrypt-hash.detected-bcrypt-hash
-- id 10 : averti il y a 31 jours, aucune reconnexion → cible phase 2 (soft-delete)
(10, 'rgpd.inactif.delete@dev.local', '$2y$10$zvmI7JOSrZn00L4Hdg6LCOcPBuoWhDyiXN7KT0jCHtqJ9ec/JDr0a', 'individual', 'customer', 'fr', 0, '2023-01-01 08:00:00', 0, '2023-01-01 08:00:00'), -- nosemgrep: generic.secrets.security.detected-bcrypt-hash.detected-bcrypt-hash
-- id 11 : inactif 37 mois MAIS commande pending en cours → protégé (ni avertissement ni suppression)
(11, 'rgpd.inactif.protege@dev.local','$2y$10$zvmI7JOSrZn00L4Hdg6LCOcPBuoWhDyiXN7KT0jCHtqJ9ec/JDr0a', 'individual', 'customer', 'fr', 0, '2023-01-01 08:00:00', 0, '2023-01-01 08:00:00'), -- nosemgrep: generic.secrets.security.detected-bcrypt-hash.detected-bcrypt-hash
-- id 12 : abonné newsletter sans commande → segment 'sans-commande'
(12, 'newsletter.noorder@dev.local',  '$2y$10$zvmI7JOSrZn00L4Hdg6LCOcPBuoWhDyiXN7KT0jCHtqJ9ec/JDr0a', 'individual', 'customer', 'fr', 1, '2026-01-20 08:00:00', 0, '2026-01-20 08:00:00'), -- nosemgrep: generic.secrets.security.detected-bcrypt-hash.detected-bcrypt-hash
-- id 13 : abonné newsletter avec commande ancienne (> 6 mois) → segment 'inactifs'
(13, 'newsletter.inactif@dev.local',  '$2y$10$zvmI7JOSrZn00L4Hdg6LCOcPBuoWhDyiXN7KT0jCHtqJ9ec/JDr0a', 'individual', 'customer', 'fr', 1, '2025-01-10 08:00:00', 0, '2025-01-10 08:00:00'); -- nosemgrep: generic.secrets.security.detected-bcrypt-hash.detected-bcrypt-hash

-- Tokens de désabonnement newsletter (déterministes pour les tests)
UPDATE `accounts`
SET `newsletter_unsubscribe_token` = SHA2(CONCAT('dev-unsub-token-', `id`), 256)
WHERE `newsletter_unsubscribe_token` IS NULL;

-- Déclaration de majorité (Sprint 5 — Art. L3342-1 CSP)
-- id 1 (super_admin), id 2 (admin), id 3 (client principal) : déclarés → pas de modal intempestif
-- id 5 (societe), id 6 (customer EN) : NULL → permettent de tester le flux modal en dev
-- id 4 (non vérifié), id 7 (supprimé), id 8 (purgé) : NULL intentionnel
-- majority_declared_ip chiffrée AES-256-GCM (clé dev : DB_ENCRYPT_KEY=change-this-to-a-long-random-secret-32-chars-min)
-- Valeurs pré-calculées via php -r "echo EncryptionService::fromEnv()->encrypt('127.0.0.1');"
UPDATE `accounts` SET `majority_declared_at` = `created_at`, `majority_declared_ip` = 'x5vjr67kGPu683uNYPPCefltiOpaaWMWc4uDOrwTNtMK9M5CFQ==' WHERE `id` = 1;
UPDATE `accounts` SET `majority_declared_at` = `created_at`, `majority_declared_ip` = 'G7qW1b7gGYFTmCNa3XlmoaLSiBPzwrMfdalZ3litfIVROefa9g==' WHERE `id` = 2;
UPDATE `accounts` SET `majority_declared_at` = `created_at`, `majority_declared_ip` = 'gw+z/b5nAqSUndB7tRa/Jn9eESrs7icSVN/vPA4baot3S4lEjTtA5w==' WHERE `id` = 3;

-- Soft delete du compte 7 (scheduled_deletion_at toujours dans le futur pour les tests de purge)
UPDATE `accounts`
SET `deleted_at`            = DATE_SUB(NOW(), INTERVAL 5 DAY),
    `scheduled_deletion_at` = DATE_ADD(NOW(), INTERVAL 25 DAY),
    `reactivation_token`    = 'a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2'
WHERE `id` = 7;

-- Antidater created_at pour simuler l'inactivité (37 mois en arrière)
UPDATE `accounts` SET `created_at` = DATE_SUB(NOW(), INTERVAL 37 MONTH) WHERE `id` IN (9, 10, 11);
-- Marquer id 10 comme averti il y a 31 jours
UPDATE `accounts` SET `inactivity_warning_sent_at` = DATE_SUB(NOW(), INTERVAL 31 DAY) WHERE `id` = 10;

ALTER TABLE `accounts` AUTO_INCREMENT = 14;

-- ============================================================
-- PROFILS INDIVIDUELS
-- ============================================================

INSERT INTO `account_individuals` (`account_id`, `lastname`, `firstname`, `civility`) VALUES
(1, 'Admin',    'Super',    'M'),
(2, 'Test',     'Admin',    'M'),
(3, 'Durand',   'Sophie',   'F'),
(4, 'Lefebvre', 'Marc',     'M'),
(6, 'Smith',    'James',    'M'),
(7, 'Martin',   'Claire',   'F'),
-- Compte purgé — données personnelles effacées (RGPD Art. 17)
(8, 'XXXXX',    'XXXXX',    'M'),
-- Comptes cron RGPD
(9,  'Inactif',  'Warn',   'M'),
(10, 'Inactif',  'Delete', 'F'),
(11, 'Inactif',  'Protege','M'),
-- Comptes segmentation newsletter
(12, 'NoOrder',  'Newsletter', 'M'),
(13, 'Inactif',  'Newsletter', 'F');

-- ============================================================
-- PROFILS SOCIÉTÉS
-- ============================================================

INSERT INTO `account_companies` (`account_id`, `company_name`, `siret`) VALUES
(5, 'Les Caves du Sud SARL', '12345678900012');

-- ============================================================
-- ADRESSES
-- ============================================================

INSERT INTO `addresses`
    (`id`, `user_id`, `type`, `firstname`, `lastname`, `civility`, `street`, `city`, `zip_code`, `country`, `phone`, `saved`)
VALUES
-- Sophie (id 3) — facturation + livraison sauvegardées
(1, 3, 'billing',  'Sophie', 'Durand',   'F', '12 rue des Vignes',    'Bordeaux',  '33000', 'France', '0612345678', 1),
(2, 3, 'delivery', 'Sophie', 'Durand',   'F', '12 rue des Vignes',    'Bordeaux',  '33000', 'France', '0612345678', 1),
(3, 3, 'delivery', 'Sophie', 'Durand',   'F', '5 allée des Platanes', 'Mérignac',  '33700', 'France', '0612345678', 1),
-- Les Caves du Sud (id 5)
(4, 5, 'billing',  'Pierre', 'Moreau',   'M', '8 avenue du Commerce', 'Toulouse',  '31000', 'France', '0556789012', 1),
(5, 5, 'delivery', 'Pierre', 'Moreau',   'M', '8 avenue du Commerce', 'Toulouse',  '31000', 'France', '0556789012', 1),
-- James Smith (id 6)
(6, 6, 'billing',  'James',  'Smith',    'M', '10 Baker Street',      'London',    'W1U 3BW', 'United Kingdom', '+441234567890', 1),
-- Admin (id 2) — pour test admin
(7, 2, 'billing',  'Admin',  'Test',     'M', '1 rue de la Mairie',   'Paris',     '75001', 'France', '0100000000', 1),
-- Claire Martin (id 7, soft-deleted) — adresse préservée pour commande livrée → cible purge_addresses.php
(8, 7, 'delivery', 'Claire', 'Martin',   'F', '3 rue des Lilas',      'Lyon',      '69002', 'France', '0478000010', 0),
-- Client protégé (id 11) — adresse pour commande pending
(9, 11, 'billing',  'Test', 'Protege', 'M', '7 rue des Tests', 'Paris', '75001', 'France', '0600000011', 0);

UPDATE `addresses` SET `preserved_for_order` = 1 WHERE `id` = 8;

ALTER TABLE `addresses` AUTO_INCREMENT = 10;

-- ============================================================
-- ORDER ADDRESS SNAPSHOTS (instantanés des adresses de commande)
-- ============================================================

INSERT INTO `order_address_snapshots`
    (`id`, `source_address_id`, `civility`, `firstname`, `lastname`, `street`, `city`, `zip`, `country`, `phone`)
VALUES
-- snapshot 1 : adresse id=1 (Sophie facturation)
(1, 1, 'F', 'Sophie', 'Durand', '12 rue des Vignes', 'Bordeaux', '33000', 'France', '0612345678'),
-- snapshot 2 : adresse id=2 (Sophie livraison)
(2, 2, 'F', 'Sophie', 'Durand', '12 rue des Vignes', 'Bordeaux', '33000', 'France', '0612345678'),
-- snapshot 3 : adresse id=4 (Pierre Moreau facturation)
(3, 4, 'M', 'Pierre', 'Moreau', '8 avenue du Commerce', 'Toulouse', '31000', 'France', '0556789012'),
-- snapshot 4 : adresse id=5 (Pierre Moreau livraison)
(4, 5, 'M', 'Pierre', 'Moreau', '8 avenue du Commerce', 'Toulouse', '31000', 'France', '0556789012'),
-- snapshot 5 : adresse id=7 (Admin facturation)
(5, 7, 'M', 'Admin', 'Test', '1 rue de la Mairie', 'Paris', '75001', 'France', '0100000000'),
-- snapshots 6-8 : commandes archivées (> 10 ans) — données personnelles non pseudonymisées
(6, NULL, 'M', 'Henri', 'Archivé', '3 allée des Chênes', 'Lyon', '69001', 'France', '0478000001'),
(7, NULL, 'F', 'Lucie', 'Ancienne', '17 rue du Port', 'Nantes', '44000', 'France', '0240000002'),
-- snapshot 8 : déjà pseudonymisé (simule cron déjà passé)
(8, NULL, 'M', 'XXXXX', 'XXXXX', 'XXXXX', 'Marseille', '13001', 'France', ''),
-- snapshot 9 : adresse id=8 (Claire Martin id 7, commande livrée — preserved_for_order=1)
(9, 8, 'F', 'Claire', 'Martin', '3 rue des Lilas', 'Lyon', '69002', 'France', '0478000010'),
-- snapshot 10 : adresse id=9 (Test Protege id 11, commande pending)
(10, 9, 'M', 'Test', 'Protege', '7 rue des Tests', 'Paris', '75001', 'France', '0600000011');\n\nALTER TABLE `order_address_snapshots` AUTO_INCREMENT = 11;

UPDATE `order_address_snapshots` SET `pseudonymized_at` = '2015-04-05 02:00:00' WHERE `id` = 8;

-- ============================================================
-- ORDERS (toutes statuts couverts pour les tests)
-- ============================================================

INSERT INTO `orders`
    (`id`, `user_id`, `order_reference`, `content`, `price`, `payment_method`,
     `shipping_discount`, `id_billing_snapshot`, `id_delivery_snapshot`, `status`, `ordered_at`, `delivered_at`)
VALUES
-- paid — Sophie (référence pour tests commandes)
(1, 3, 'CBV-2026-000001',
 '[{"wine_id":16,"label_name":"Sainte-Croix-du-Mont","vintage":2015,"wine_color":"sweet","format":"bottle","qty":12,"price":11.50,"subtotal":138.00}]',
 138.00, 'card', 0.00, 1, 2, 'paid', '2026-01-15 10:30:00', NULL),

-- processing
(2, 3, 'CBV-2026-000002',
 '[{"wine_id":30,"label_name":"Bordeaux Rouge","vintage":2020,"wine_color":"red","format":"bottle","qty":6,"price":7.70,"subtotal":46.20}]',
 46.20, 'card', 0.00, 1, 2, 'processing', '2026-02-01 14:00:00', NULL),

-- shipped
(3, 3, 'CBV-2026-000003',
 '[{"wine_id":35,"label_name":"Bordeaux Blanc","vintage":2024,"wine_color":"white","format":"bottle","qty":12,"price":7.70,"subtotal":92.40},{"wine_id":21,"label_name":"Sainte-Croix-du-Mont","vintage":2016,"wine_color":"sweet","format":"bottle","qty":6,"price":11.50,"subtotal":69.00}]',
 161.40, 'card', 15.00, 1, 2, 'shipped', '2026-02-10 09:00:00', NULL),

-- delivered dans la fenêtre (< 14j, pour tester le retour) : delivered_at = aujourd'hui - 5 jours
(4, 3, 'CBV-2026-000004',
 '[{"wine_id":7,"label_name":"Bordeaux Rouge","vintage":2016,"wine_color":"red","format":"bottle","qty":24,"price":5.90,"subtotal":141.60}]',
 141.60, 'card', 15.00, 1, 2, 'delivered', '2026-01-05 11:00:00', DATE_SUB(NOW(), INTERVAL 5 DAY)),

-- cancelled
(5, 3, 'CBV-2026-000005',
 '[{"wine_id":9,"label_name":"Bordeaux Blanc","vintage":2018,"wine_color":"white","format":"bottle","qty":3,"price":6.10,"subtotal":18.30}]',
 18.30, 'card', 0.00, 1, NULL, 'cancelled', '2026-01-20 16:00:00', NULL),

-- refunded
(6, 3, 'CBV-2026-000006',
 '[{"wine_id":10,"label_name":"Bordeaux Rouge","vintage":2017,"wine_color":"red","format":"bottle","qty":6,"price":6.00,"subtotal":36.00}]',
 36.00, 'card', 0.00, 1, 2, 'refunded', '2026-01-25 13:00:00', '2026-02-01 10:00:00'),

-- return_requested (delivered_at > 14j pour être hors fenêtre)
(7, 3, 'CBV-2026-000007',
 '[{"wine_id":31,"label_name":"Sainte-Croix-du-Mont","vintage":2017,"wine_color":"sweet","format":"bottle","qty":6,"price":11.00,"subtotal":66.00}]',
 66.00, 'card', 0.00, 1, 2, 'return_requested', '2026-02-01 08:00:00', '2026-02-10 10:00:00'),

-- pending (non payée — initiation tunnel)
(8, 3, 'CBV-2026-000008',
 '[{"wine_id":16,"label_name":"Sainte-Croix-du-Mont","vintage":2015,"wine_color":"sweet","format":"bottle","qty":12,"price":11.50,"subtotal":138.00}]',
 138.00, 'card', 0.00, 1, 2, 'pending', '2026-03-01 17:00:00', NULL),

-- société (id 5) — delivered hors fenêtre (> 15j)
(9, 5, 'CBV-2026-000009',
 '[{"wine_id":34,"label_name":"Côtes de Bordeaux Rouge","vintage":2019,"wine_color":"red","format":"bottle","qty":48,"price":10.00,"subtotal":480.00}]',
 480.00, 'virement', 62.40, 3, 4, 'delivered', '2026-01-20 10:00:00', '2026-02-01 10:00:00'),

-- admin (id 2) — paid (pour tester vue admin)
(10, 2, 'CBV-2026-000010',
 '[{"wine_id":36,"label_name":"Sainte-Croix-du-Mont","vintage":2018,"wine_color":"sweet","format":"bottle","qty":12,"price":10.80,"subtotal":129.60}]',
 129.60, 'card', 15.00, 5, NULL, 'paid', '2026-03-10 09:00:00', NULL),

-- ── Commandes archivées (ordered_at > 10 ans) ─────────────────────────────
-- user_id = 8 = compte RGPD purgé (email @purged.invalid — "Compte anonymisé" dans la vue)
-- delivered il y a 11 ans — snapshot 6 non pseudonymisé (cron à déclencher)
(11, 8, 'CBV-2015-000011',
 '[{"wine_id":1,"label_name":"Sainte-Croix-du-Mont","vintage":2009,"wine_color":"sweet","format":"bottle","qty":6,"price":10.00,"subtotal":60.00}]',
 60.00, 'virement', 0.00, 6, 6, 'delivered', '2015-01-15 10:00:00', '2015-02-01 10:00:00'),

-- delivered il y a 12 ans — snapshot 7 non pseudonymisé
(12, 8, 'CBV-2014-000012',
 '[{"wine_id":2,"label_name":"Bordeaux Rouge","vintage":2010,"wine_color":"red","format":"bottle","qty":12,"price":7.00,"subtotal":84.00}]',
 84.00, 'cheque', 0.00, 7, 7, 'delivered', '2014-06-10 14:00:00', '2014-06-25 10:00:00'),

-- refunded il y a 11 ans — snapshot 8 déjà pseudonymisé (simule cron passé)
(13, 8, 'CBV-2015-000013',
 '[{"wine_id":3,"label_name":"Bordeaux Blanc","vintage":2011,"wine_color":"white","format":"bottle","qty":6,"price":6.50,"subtotal":39.00}]',
 39.00, 'card', 0.00, 8, 8, 'refunded', '2015-03-20 09:00:00', NULL),

-- ── Commandes cron RGPD ───────────────────────────────────────────────────────
-- Claire Martin (id 7, soft-deleted) — delivered il y a 2 ans → adresse id=8 purge_addresses.php cible
(14, 7, 'CBV-2024-000014',
 '[{"wine_id":16,"label_name":"Sainte-Croix-du-Mont","vintage":2020,"wine_color":"sweet","format":"bottle","qty":6,"price":11.50,"subtotal":69.00}]',
 69.00, 'card', 0.00, 9, 9, 'delivered', DATE_SUB(NOW(), INTERVAL 2 YEAR), DATE_SUB(NOW(), INTERVAL 23 MONTH)),

-- Client protégé (id 11) — commande pending → protège du cron inactivité
(15, 11, 'CBV-2026-000015',
 '[{"wine_id":30,"label_name":"Bordeaux Rouge","vintage":2022,"wine_color":"red","format":"bottle","qty":6,"price":7.70,"subtotal":46.20}]',
 46.20, 'card', 0.00, 10, 10, 'pending', DATE_SUB(NOW(), INTERVAL 1 DAY), NULL),

-- Newsletter inactif (id 13) — commande delivered il y a 8 mois → segment 'inactifs'
(16, 13, 'CBV-2025-000016',
 '[{"wine_id":1,"label_name":"Sainte-Croix-du-Mont","vintage":2019,"wine_color":"sweet","format":"bottle","qty":6,"price":10.00,"subtotal":60.00}]',
 60.00, 'card', 0.00, 1, 1, 'delivered', DATE_SUB(NOW(), INTERVAL 8 MONTH), DATE_SUB(NOW(), INTERVAL 7 MONTH)),

-- ── Commandes bloquées (test alerte cron) ─────────────────────────────────
-- pending + chèque — chèque jamais reçu (20 jours sans mouvement)
(17, 5, 'CBV-2026-000017',
 '[{"wine_id":16,"label_name":"Sainte-Croix-du-Mont","vintage":2020,"wine_color":"sweet","format":"bottle","qty":24,"price":11.50,"subtotal":276.00}]',
 276.00, 'cheque', 0.00, 3, 4, 'pending', DATE_SUB(NOW(), INTERVAL 22 DAY), NULL),

-- paid + virement — virement reçu mais préparation bloquée (15 jours)
(18, 3, 'CBV-2026-000018',
 '[{"wine_id":30,"label_name":"Bordeaux Rouge","vintage":2021,"wine_color":"red","format":"bottle","qty":12,"price":7.70,"subtotal":92.40},{"wine_id":35,"label_name":"Bordeaux Blanc","vintage":2024,"wine_color":"white","format":"bottle","qty":6,"price":7.70,"subtotal":46.20}]',
 138.60, 'virement', 15.00, 1, 2, 'paid', DATE_SUB(NOW(), INTERVAL 18 DAY), NULL),

-- processing + virement — en préparation depuis 12 jours (colis non expédié)
(19, 5, 'CBV-2026-000019',
 '[{"wine_id":21,"label_name":"Sainte-Croix-du-Mont","vintage":2018,"wine_color":"sweet","format":"bottle","qty":6,"price":11.00,"subtotal":66.00}]',
 66.00, 'virement', 0.00, 3, 4, 'processing', DATE_SUB(NOW(), INTERVAL 15 DAY), NULL),

-- shipped — colis bloqué en transit depuis 14 jours
(20, 3, 'CBV-2026-000020',
 '[{"wine_id":7,"label_name":"Bordeaux Rouge","vintage":2016,"wine_color":"red","format":"bottle","qty":6,"price":5.90,"subtotal":35.40}]',
 35.40, 'card', 0.00, 1, 2, 'shipped', DATE_SUB(NOW(), INTERVAL 16 DAY), NULL),

-- return_requested — retour demandé non traité depuis 9 jours
(21, 5, 'CBV-2026-000021',
 '[{"wine_id":9,"label_name":"Bordeaux Blanc","vintage":2018,"wine_color":"white","format":"bottle","qty":3,"price":6.10,"subtotal":18.30}]',
 18.30, 'card', 0.00, 3, 4, 'return_requested', DATE_SUB(NOW(), INTERVAL 30 DAY), DATE_SUB(NOW(), INTERVAL 31 DAY)),

-- refund_refused — remboursement refusé en litige depuis 11 jours
(22, 3, 'CBV-2026-000022',
 '[{"wine_id":10,"label_name":"Bordeaux Rouge","vintage":2017,"wine_color":"red","format":"bottle","qty":6,"price":6.00,"subtotal":36.00}]',
 36.00, 'card', 0.00, 1, 2, 'refund_refused', DATE_SUB(NOW(), INTERVAL 13 DAY), DATE_SUB(NOW(), INTERVAL 40 DAY)),

-- delivered J+22 — multi-vins (Sophie id=3) — cible email post-achat J+21 (fenêtre 504h–528h) + test page notation
(23, 3, 'CBV-2026-000023',
 '[{"wine_id":16,"label_name":"Sainte-Croix-du-Mont","vintage":2015,"wine_color":"sweet","format":"bottle","qty":12,"price":11.50,"subtotal":138.00},{"wine_id":30,"label_name":"Bordeaux Rouge","vintage":2020,"wine_color":"red","format":"bottle","qty":6,"price":7.70,"subtotal":46.20},{"wine_id":35,"label_name":"Bordeaux Blanc","vintage":2024,"wine_color":"white","format":"bottle","qty":6,"price":7.70,"subtotal":46.20}]',
 230.40, 'card', 15.00, 1, 2, 'delivered', DATE_SUB(NOW(), INTERVAL 23 DAY), DATE_SUB(NOW(), INTERVAL 22 DAY));

ALTER TABLE `orders` AUTO_INCREMENT = 24;

-- Forcer updated_at anciens sur les commandes bloquées de test
UPDATE `orders` SET `updated_at` = DATE_SUB(NOW(), INTERVAL 20 DAY) WHERE `id` = 17;
UPDATE `orders` SET `updated_at` = DATE_SUB(NOW(), INTERVAL 15 DAY) WHERE `id` = 18;
UPDATE `orders` SET `updated_at` = DATE_SUB(NOW(), INTERVAL 12 DAY) WHERE `id` = 19;
UPDATE `orders` SET `updated_at` = DATE_SUB(NOW(), INTERVAL 14 DAY) WHERE `id` = 20;
UPDATE `orders` SET `updated_at` = DATE_SUB(NOW(), INTERVAL 9  DAY) WHERE `id` = 21;
UPDATE `orders` SET `updated_at` = DATE_SUB(NOW(), INTERVAL 11 DAY) WHERE `id` = 22;

-- ============================================================
-- CARTS (paniers actifs)
-- ============================================================

INSERT INTO `carts` (`user_id`, `content`, `total_quantity`) VALUES
-- Sophie — panier avec 2 articles
(3, '[{"wine_id":21,"qty":6},{"wine_id":35,"qty":6}]', 12),
-- James — panier 1 article
(6, '[{"wine_id":30,"qty":12}]', 12);

-- ============================================================
-- FAVORITES
-- ============================================================

INSERT INTO `favorites` (`user_id`, `wine_id`) VALUES
(3, 16), (3, 21), (3, 28), (3, 31),
(5, 34), (5, 29),
(6, 30), (6, 35);

-- ============================================================
-- CONNECTIONS (sessions JWT)
-- ============================================================

INSERT INTO `connections`
    (`user_id`, `token`, `device_token`, `ip_address`, `device_name`, `auth_method`, `status`, `last_used_at`, `created_at`, `expired_at`)
VALUES
-- Sophie — active (expired_at toujours dans le futur)
(3, 'mock-jwt-sophie-active-001', 'device-uuid-sophie-macbook', '82.65.10.1', 'Chrome · macOS', 'password', 'active', NOW(), DATE_SUB(NOW(), INTERVAL 1 HOUR), DATE_ADD(NOW(), INTERVAL 30 DAY)),
-- Sophie — expirée (historique) — expired_at futur pour ne pas être purgée lors des tests
(3, 'mock-jwt-sophie-expired-001', 'device-uuid-sophie-macbook', '82.65.10.1', 'Chrome · macOS', 'password', 'expired', DATE_SUB(NOW(), INTERVAL 60 DAY), DATE_SUB(NOW(), INTERVAL 90 DAY), DATE_ADD(NOW(), INTERVAL 30 DAY)),
-- Admin — active
(2, 'mock-jwt-admin-active-001', 'device-uuid-admin-chrome', '10.0.0.1', 'Firefox · Windows', 'password', 'active', NOW(), DATE_SUB(NOW(), INTERVAL 1 HOUR), DATE_ADD(NOW(), INTERVAL 30 DAY)),
-- Société — expirée (status 'expired' au lieu de 'revoked' pour éviter la purge automatique lors des tests)
(5, 'mock-jwt-societe-revoked-001', 'device-uuid-societe-ipad', '81.33.20.5', 'Safari · iOS', 'password', 'expired', DATE_SUB(NOW(), INTERVAL 30 DAY), DATE_SUB(NOW(), INTERVAL 35 DAY), DATE_ADD(NOW(), INTERVAL 30 DAY));

-- ============================================================
-- TRUSTED DEVICES
-- ============================================================

INSERT INTO `trusted_devices` (`user_id`, `device_token`, `device_name`, `confirmed_at`, `last_seen`, `expires_at`) VALUES
(3, 'device-uuid-sophie-macbook',  'Chrome · macOS',    '2026-01-10 10:05:00', '2026-03-28 08:00:00', DATE_ADD('2026-03-28 08:00:00', INTERVAL 90 DAY)),
(3, 'device-uuid-sophie-iphone',   'Safari · iOS',      '2026-02-01 11:00:00', '2026-03-20 14:00:00', DATE_ADD('2026-03-20 14:00:00', INTERVAL 90 DAY)),
(2, 'device-uuid-admin-chrome',    'Firefox · Windows', '2026-01-01 08:05:00', '2026-03-28 07:30:00', DATE_ADD('2026-03-28 07:30:00', INTERVAL 90 DAY)),
(5, 'device-uuid-societe-ipad',    'Safari · iOS',      '2026-01-15 14:05:00', '2026-03-01 10:00:00', DATE_ADD('2026-03-01 10:00:00', INTERVAL 90 DAY));

-- ============================================================
-- PASSWORD RESET TOKENS (quelques-uns pour les tests)
-- ============================================================

-- ============================================================
-- NEWSLETTERS (12 campagnes pour tester la pagination admin)
-- ============================================================

INSERT INTO `newsletters` (`subject`, `body`, `image_url`, `sent_count`, `failed_count`, `sent_at`, `segment_label`) VALUES
('Ouverture des inscriptions — millésime 2024', '<p>Découvrez les premiers vins du millésime 2024, prometteur après un été exceptionnel.</p>', NULL, 142, 2, '2026-03-01 10:00:00', NULL),
('Printemps au château — visite & dégustation', '<p>À l\'occasion du printemps, venez nous rendre visite et découvrir nos nouvelles cuvées.</p>', NULL, 138, 1, '2026-02-15 09:30:00', 'particuliers'),
('Bon de commande 2025 disponible', '<p>Notre bon de commande 2025 est disponible. Téléchargez-le et retournez-le avant le 31 mars.</p>', NULL, 155, 0, '2026-02-01 11:00:00', 'commandes'),
('Saint-Valentin — offrez du Crabitan Bellevue', '<p>Pour la Saint-Valentin, offrez une sélection de nos meilleurs vins liquoreux.</p>', NULL, 130, 3, '2026-01-28 08:00:00', NULL),
('Bilan 2025 & perspectives 2026', '<p>Retour sur une excellente année 2025 et les projets que nous préparons pour 2026.</p>', NULL, 160, 0, '2026-01-10 10:00:00', NULL),
('Vœux 2026 — merci de votre fidélité', '<p>Toute l\'équipe du Château Crabitan Bellevue vous souhaite une excellente année 2026.</p>', NULL, 175, 1, '2026-01-01 08:00:00', NULL),
('Fêtes de fin d\'année — dernières commandes', '<p>Dernière chance pour recevoir votre commande avant Noël. Passez commande avant le 18 décembre.</p>', NULL, 148, 2, '2025-12-12 09:00:00', NULL),
('Harmonie mets & vins pour les fêtes', '<p>Nos conseils pour sublimer votre table de fête avec les vins du Château Crabitan Bellevue.</p>', NULL, 122, 0, '2025-12-01 10:30:00', NULL),
('Millésime 2023 — disponible en boutique', '<p>Les vins du millésime 2023 sont désormais disponibles à la commande en ligne.</p>', NULL, 134, 1, '2025-11-15 11:00:00', NULL),
('Vendanges 2025 — les premières impressions', '<p>Les vendanges 2025 sont terminées. Découvrez les premières impressions de notre maître de chai.</p>', NULL, 118, 0, '2025-10-10 09:00:00', NULL),
('Automne au domaine — photos & coulisses', '<p>Plongez dans l\'atmosphère automnale du Château Crabitan Bellevue avec notre reportage photo.</p>', NULL, 109, 2, '2025-09-20 10:00:00', NULL),
('Rentrée des amateurs de vin', '<p>La rentrée est là ! Découvrez notre sélection de vins pour l\'automne et retrouvez-nous sur le site.</p>', NULL, 97, 0, '2025-09-01 09:00:00', NULL);

-- ============================================================
-- PASSWORD RESET TOKENS (quelques-uns pour les tests)
-- ============================================================

INSERT INTO `password_reset` (`user_id`, `token`, `requested_at`, `expires_at`) VALUES
-- Token valide (expires dans le futur)
(3, 'valid-reset-token-sophie-0001', '2026-03-28 06:00:00', '2026-03-28 07:00:00'),
-- Token expiré (pour tester le cas d'erreur)
(4, 'expired-reset-token-marc-0001', '2026-03-01 10:00:00', '2026-03-01 11:00:00');

-- ============================================================
-- NEWSLETTER SUBSCRIPTIONS (flux visiteurs — double opt-in)
-- Comptes avec newsletter=1 sont dans accounts (ids 3 et 5)
-- ============================================================
INSERT INTO `newsletter_subscriptions`
    (`email`, `newsletter_token_hash`, `newsletter_token_expires_at`,
     `newsletter_confirmed`, `newsletter_consent_date`, `newsletter_consent_ip`,
     `consent_ip`, `lang`, `attempts_24h`, `last_attempt_at`, `created_at`)
VALUES
-- Visiteur confirmé (newsletter active)
('visiteur.confirme@dev.local', NULL, NULL,
 1, '2026-02-15 10:00:00', '127.0.0.1',
 '127.0.0.1', 'fr', 1, '2026-02-15 09:58:00', '2026-02-15 09:58:00'),
-- Visiteur en attente de confirmation (token encore valide)
('visiteur.pending@dev.local',
 'a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2',
 '2099-12-31 23:59:59',
 0, NULL, NULL,
 '127.0.0.1', 'fr', 1, '2026-03-01 08:00:00', '2026-03-01 08:00:00'),
-- Visiteur EN confirmé
('visitor.confirmed@dev.local', NULL, NULL,
 1, '2026-03-10 14:00:00', '127.0.0.1',
 '127.0.0.1', 'en', 1, '2026-03-10 13:58:00', '2026-03-10 13:58:00');

-- ============================================================
-- NEWSLETTER TRACKING (liens, ouvertures, clics — campagne id=1)
-- ============================================================

INSERT INTO `newsletter_links` (`id`, `newsletter_id`, `url`) VALUES
(1, 1, 'https://crabitanbellevue.fr/fr/vins'),
(2, 1, 'https://crabitanbellevue.fr/fr/actualites');

ALTER TABLE `newsletter_links` AUTO_INCREMENT = 3;

INSERT INTO `newsletter_opens` (`newsletter_id`, `subscriber_id`, `opened_at`) VALUES
(1, 3, '2026-03-01 11:30:00'),
(1, 5, '2026-03-01 14:00:00'),
(1, 12, '2026-03-02 08:45:00');

ALTER TABLE `newsletter_opens` AUTO_INCREMENT = 4;

INSERT INTO `newsletter_clicks` (`newsletter_id`, `link_id`, `subscriber_id`, `clicked_at`) VALUES
(1, 1, 3, '2026-03-01 11:32:00'),
(1, 2, 5, '2026-03-01 14:05:00');

ALTER TABLE `newsletter_clicks` AUTO_INCREMENT = 3;

-- legal_documents : données initiales incluses dans schema.sql (INSERT au schéma)

-- ============================================================
-- wine_ratings : notes fictives pour tester le tri par notation
-- Vins notés par plusieurs comptes (min 2 pour être publiques)
-- wine_id 30 (Bordeaux Rouge 2020) : 5+4+5 = avg 4.67
-- wine_id 35 (Bordeaux Blanc 2024) : 4+3 = avg 3.5
-- wine_id 16 (Sainte-Croix-du-Mont 2015) : 5+5+4 = avg 4.67
-- wine_id 21 (Sainte-Croix-du-Mont 2016) : 3+3 = avg 3.0
-- ============================================================
INSERT INTO `wine_ratings` (`account_id`, `wine_id`, `order_id`, `rating`, `token_created_at`, `created_at`) VALUES
(3, 30, 2, 5, NULL, '2026-02-10 12:00:00'),
(5, 30, 2, 4, NULL, '2026-02-11 09:00:00'),
(6, 30, 2, 5, NULL, '2026-02-12 14:00:00'),
(3, 35, 3, 4, NULL, '2026-02-15 10:00:00'),
(5, 35, 3, 3, NULL, '2026-02-16 11:00:00'),
(3, 16, 1, 5, NULL, '2026-02-20 09:00:00'),
(5, 16, 1, 5, NULL, '2026-02-21 10:00:00'),
(6, 16, 1, 4, NULL, '2026-02-22 11:00:00'),
(3, 21, 3, 3, NULL, '2026-02-25 09:00:00'),
(5, 21, 3, 3, NULL, '2026-02-26 10:00:00');
