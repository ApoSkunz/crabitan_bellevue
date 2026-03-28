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
-- PURGE (ordre FK inverse)
-- ============================================================
DELETE FROM `device_confirm_tokens`;
DELETE FROM `trusted_devices`;
DELETE FROM `connections`;
DELETE FROM `password_reset`;
DELETE FROM `favorites`;
DELETE FROM `orders`;
DELETE FROM `carts`;
DELETE FROM `addresses`;
DELETE FROM `account_individuals`;
DELETE FROM `account_companies`;
DELETE FROM `accounts`;
DELETE FROM `pricing_rules`;
DELETE FROM `wines`;
DELETE FROM `news`;
DELETE FROM `order_forms`;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- WINES (38 vins — données production)
-- ============================================================

INSERT INTO `wines`
    (`label_name`, `wine_color`, `format`, `vintage`, `price`, `quantity`, `available`,
     `certification_label`, `area`, `city`, `variety_of_vine`, `age_of_vineyard`,
     `oenological_comment`, `soil`, `pruning`, `harvest`, `vinification`,
     `barrel_fermentation`, `award`, `extra_comment`,
     `image_path`, `slug`)
VALUES

-- 1 Sainte-Croix-du-Mont 2010
('Sainte-Croix-du-Mont', 'sweet', 'bottle', 2010, 11.20, 40000, 0, 'AOC', 22.00,
 'Sainte-Croix-du-Mont', 'Sémillon 95%', 30,
 '{"fr":"Une belle robe jaune pâle. Des notes florales, de fruits blancs et un léger caractère miellé. Une attaque assez fraîche en bouche, avec une évolution ample et généreuse. Un bel équilibre entre liqueur et acidité, des retours floraux et un léger miel d\'acacia en bouche, une très bonne longueur et une persistance arômatique.","en":"A beautiful pale yellow color. Floral notes, white fruits and a light honey character. A fairly fresh attack on the palate, with an ample and generous evolution. A nice balance between liquor and acidity, floral returns and a light acacia honey on the palate, very good length and aromatic persistence."}',
 '{"fr":"Dominante argileuse","en":"Dominant clay"}',
 '{"fr":"Guyot mixte","en":"Mixed Guyot"}',
 '{"fr":"Manuelles (3 tries successives)","en":"Manual (3 successive selections)"}',
 '{"fr":"Fermentation avec levures indigènes en cuves inox","en":"Fermentation with indigenous yeasts in stainless steel tanks"}',
 '{"fr":"36 mois en cuves inox","en":"36 months in stainless steel tanks"}',
 '{"fr":"Médaille d\'Or au Concours International de Lyon 2017","en":"Gold medal at the 2017 Lyon International Competition"}',
 '{"fr":"","en":""}',
 'Wine_Sainte-Croix-du-Mont_2010_67ae7a94a14795068e5ed25b3b5e6a92.png', 'sainte-croix-du-mont-2010'),

-- 2 Sainte-Croix-du-Mont 2013
('Sainte-Croix-du-Mont', 'sweet', 'bottle', 2013, 9.70, 40000, 0, 'AOC', 22.00,
 'Sainte-Croix-du-Mont', 'Sémillon', 30,
 '{"fr":"Une robe brillante jaune or. Un nez complexe de fruits confits (oranges et mandarines), des nuances de vanille et de caramel avec une petite note de cire. En bouche, on retrouve l\'expression aromatique avec un bon équilibre.","en":"A bright yellow dress or. A complex nose of candied fruits (oranges and tangerines), nuances of vanilla and caramel with a hint of wax. In the mouth, we find the aromatic expression with a good balance."}',
 '{"fr":"Argilo-calcaire, argilo-graveleux et limono-argileux","en":"Clayey-limestone, clayey-gravelly and silty-clayey"}',
 '{"fr":"Guyot mixte","en":"Mixed Guyot"}',
 '{"fr":"Manuelles (3 tries successives)","en":"Manual (3 successive sorting)"}',
 '{"fr":"Pressurage pneumatique, thermorégulation de la fermentation","en":"Pneumatic pressing, thermoregulation of fermentation"}',
 '{"fr":"36 mois en cuves inox (20% d\'élevage en fût de chêne)","en":"36 months in stainless steel vats (20% aging in oak barrels)"}',
 '{"fr":"Médaille d\'Or au Concours de Bordeaux 2015","en":"Gold Medal at the Concours de Bordeaux 2015"}',
 '{"fr":"","en":""}',
 'Wine_Sainte-Croix-du-Mont_2013_75c3faa024e1d039a6338161316830d3.png', 'sainte-croix-du-mont-2013'),

-- 3 Premières Côtes de Bordeaux Blanc 2014
('Premières Côtes de Bordeaux Blanc', 'sweet', 'bottle', 2014, 8.00, 10000, 0, 'AOC', 3.00,
 'Gabarnac', 'Sémillon', 45,
 '{"fr":"Une robe brillante, jaune pâle. Le nez est ouvert avec des notes de fleurs blanches. La bouche est élégante avec un bel équilibre sucre-acidité; une finale fraîche et moelleuse sur des notes d\'agrumes légèrement confites.","en":"A brilliant, pale yellow color. The nose is open with notes of white flowers. The palate is elegant with a nice balance between sugar and acidity, a fresh finish on slightly lemony citrus notes."}',
 '{"fr":"Argileux-sablo-limoneux","en":"Clayey-sandy-silty"}',
 '{"fr":"Guyot simple et mixte","en":"Simple and mixed Guyot"}',
 '{"fr":"Manuelles (3 tries successives)","en":"Manuelles par tries successives"}',
 '{"fr":"Pressurage pneumatique, thermorégulation","en":"Pneumatic pressing, thermoregulation"}',
 '{"fr":"36 mois en cuves inox","en":"36 months in stainless steel tanks"}',
 '{"fr":"","en":""}', '{"fr":"","en":""}',
 'Wine_Premières Côtes de Bordeaux Blanc_2014_10238315a21860ff3f5c95b2efabf820.png', 'premieres-cotes-bordeaux-blanc-2014'),

-- 4 Sainte-Croix-du-Mont 2014 Cuvée Spéciale
('Sainte-Croix-du-Mont', 'sweet', 'bottle', 2014, 12.60, 7000, 0, 'AOC', 27.00,
 'Sainte-Croix-du-Mont', 'Sémillon 98%', 30,
 '{"fr":"Une robe jaune bouton d\'or. Un nez fruité sur des notes d\'abricot et d\'ananas. De la fraîcheur au nez comme en bouche. Un bon équilibre alcool-liqueur-acidité lui confèrent du gras et du volume de bouche. On retrouve aussi quelques notes vanillées en plus des notes de fruits frais.","en":"A button yellow dress. A fruity nose with notes of apricot and pineapple. Freshness on the nose and on the palate. A good alcohol-liquor-acidity balance gives it fat and volume in the mouth. There are also some vanilla notes in addition to notes of fresh fruit."}',
 '{"fr":"Dominante argileuse et multiples expositions","en":"Dominant clay and multiple exposures"}',
 '{"fr":"Guyot simple et mixte","en":"Simple and mixed Guyot"}',
 '{"fr":"Manuelles (3 tries successives)","en":"Manual (3 successive tests)"}',
 '{"fr":"Thermorégulation","en":"Thermoregulation"}',
 '{"fr":"Sélection des meilleurs lots de l\'année et passage 12 mois en fûts de chêne","en":"Selection of the best lots of the year and passing 12 months in oak barrels"}',
 '{"fr":"Médaille d\'Or Lyon 2017","en":"Gold medal Lyon 2017"}',
 '{"fr":"Cuvée Spéciale","en":"Special Cuvée"}',
 'Wine_Sainte-Croix-du-Mont_2014_b77c86b19ee68ad74858bd5c2616c69b.png', 'sainte-croix-du-mont-2014-cuvee-speciale'),

-- 5 Sainte-Croix-du-Mont 2014
('Sainte-Croix-du-Mont', 'sweet', 'bottle', 2014, 8.70, 30000, 0, 'AOC', 27.00,
 'Sainte-Croix-du-Mont', 'Sémillon 98%', 30,
 '{"fr":"Une robe brillante, jaune or. Un nez de bonne puissance avec des notes de pain d\'épice et de miel. En bouche, une attaque assez vive, avec du volume et un bon équilibre en milieu de bouche, une finale délicate et assez longue.","en":"A brilliant, golden yellow color. A nose of good power with hints of gingerbread and honey. On the palate, a fairly lively attack, with volume and a good balance in the mid-palate, a delicate and fairly long finish."}',
 '{"fr":"Dominante argileuse et multiples expositions","en":"Dominant Clayey and multiple exposures"}',
 '{"fr":"Guyot simple et mixte","en":"Simple and mixed Guyot"}',
 '{"fr":"Manuelles (3 tries successives)","en":"Manual (3 successive tests)"}',
 '{"fr":"Thermorégulation","en":"Thermoregulation"}',
 '{"fr":"36 mois en cuves inox","en":"36 months in stainless steel tanks"}',
 '{"fr":"","en":""}', '{"fr":"","en":""}',
 'Wine_Sainte-Croix-du-Mont_2014_c621a1170fee481938baca6667964ed8.png', 'sainte-croix-du-mont-2014'),

-- 6 Côtes de Bordeaux Rouge 2014 Cuvée Spéciale
('Côtes de Bordeaux Rouge', 'red', 'bottle', 2014, 7.80, 26000, 0, 'AOC', 4.00,
 'Gabarnac', 'Merlot 66% - Cabernet Sauvignon 34%', 30,
 '{"fr":"Une belle robe profonde rouge foncée et brillante. Complexe au nez avec des arômes de fruits noirs mûrs (cassis), de vanille. Une attaque en bouche franche et équilibrée, de la fraîcheur, les tanins sont soyeux en milieu de bouche, des notes de raisins mûrs avec une finale harmonieuse.","en":"A beautiful deep dark red and shiny color. Complex on the nose with aromas of ripe black fruits (blackcurrant), vanilla. A frank and balanced attack on the palate, freshness, the tannins are silky on the mid-palate, notes of ripe grapes with a harmonious finish."}',
 '{"fr":"Argilo-calcaire, argilo-graveleux","en":"Clayey-limestone, clayey-gravelly"}',
 '{"fr":"Guyot mixte","en":"Mixed Guyot"}',
 '{"fr":"Mécaniques","en":"Mechanicals"}',
 '{"fr":"Cuvaison de 3 semaines","en":"3 weeks vatting"}',
 '{"fr":"12 mois en fûts de chêne (25% de fûts neufs) après sélection de nos meilleures parcelles de l\'année","en":"12 months in oak barrels (25% new barrels) after selection of our best plots of the year"}',
 '{"fr":"","en":""}', '{"fr":"Cuvée Spéciale","en":"Special Cuvée"}',
 'Wine_Côtes de Bordeaux Rouge_2014_f6c84dbdd93797346f602405e7c4c8c7.png', 'cotes-bordeaux-rouge-2014-cuvee-speciale'),

-- 7 Bordeaux Rouge 2016
('Bordeaux Rouge', 'red', 'bottle', 2016, 5.90, 36000, 0, 'AOC', 8.00,
 'Sainte-Croix-du-Mont et Gabarnac', 'Merlot 85% - Cabernet Sauvignon 15%', 20,
 '{"fr":"Une robe rouge sombre avec des reflets rubis. Un nez expressif sur des notes de fruits frais (cassis groseilles). Un bon équilibre en bouche, des tanins assez souples et une bonne tension lui confèrent de la longueur.","en":"A dark red color with ruby reflections. An expressive nose with notes of fresh fruit (blackcurrant redcurrants). Good balance on the palate, fairly supple tannins and good tension give it length."}',
 '{"fr":"Argilo-calcaire, argilo-graveleux","en":"Clayey-limestone, clayey-gravelly"}',
 '{"fr":"Guyot mixte","en":"Mixed Guyot"}',
 '{"fr":"Mécaniques","en":"Mechanicals"}',
 '{"fr":"Cuvaison 15 jours","en":"15 days vatting"}',
 '{"fr":"24 mois en cuves inox","en":"24 months in stainless steel tanks"}',
 '{"fr":"","en":""}', '{"fr":"","en":""}',
 'Wine_Bordeaux Rouge_2016_421762234a1334f53b577f3a5527fc6a.png', 'bordeaux-rouge-2016'),

-- 8 Vin de Pays de l'Atlantique 2017 (Rosé)
('Vin de Pays de l\'Atlantique', 'rosé', 'bottle', 2017, 5.60, 5000, 0, 'AOC', 1.00,
 'Sainte-Croix-du-Mont', 'Merlot', 30,
 '{"fr":"Une robe brillante avec une teinte vive. Au nez, des notes de bonbons anglais. Assez rond en bouche avec une certaine longueur.","en":"A shiny dress with a vivid tint. On the nose, notes of English sweets. Rather round in the mouth with a certain length."}',
 '{"fr":"Limono-argileux","en":"Silty clay"}',
 '{"fr":"Guyot mixte","en":"Mixed Guyot"}',
 '{"fr":"Mécaniques","en":"Mechanicals"}',
 '{"fr":"Pressurage direct","en":"Direct pressing"}',
 '{"fr":"5 mois en cuves inox","en":"5 months in stainless steel tanks"}',
 '{"fr":"","en":""}', '{"fr":"Existe aussi en BIB (Bag-In-Box) de 5L.","en":"Also available in 5L BIB (Bag-In-Box)."}',
 'Wine_Vin de Pays de l\'Atlantique_2017_e393a2abe0d1a1b4be051e1773aad3ba.png', 'vin-pays-atlantique-rose-2017'),

-- 9 Bordeaux Blanc 2018
('Bordeaux Blanc', 'white', 'bottle', 2018, 6.10, 12000, 0, 'AOC', 2.50,
 'Sainte-Croix-du-Mont', 'Sauvignon blanc & gris 100%', 30,
 '{"fr":"Une robe brillante, blanc/jaune. Un nez citronné et de fleurs blanches. Charnu, un bon volume en bouche avec de la fraîcheur, et une très légère amertume en finale.","en":"A brilliant, white / yellow color. A lemony nose and white flowers. Fleshy, a good volume in the mouth with freshness, and a very slight bitterness on the finish."}',
 '{"fr":"Argilo-calcaire, argilo-graveleux","en":"Clayey-limestone, clayey-gravelly"}',
 '{"fr":"Guyot mixte","en":"Mixed Guyot"}',
 '{"fr":"Mécaniques","en":"Mechanicals"}',
 '{"fr":"Macération pelliculaire","en":"Skin maceration"}',
 '{"fr":"3 mois en cuves inox","en":"3 months in stainless steel tanks"}',
 '{"fr":"","en":""}', '{"fr":"Existe aussi en BIB (Bag-In-Box) de 5L.","en":"Also available in 5L BIB (Bag-In-Box)."}',
 'Wine_Bordeaux Blanc_2018_7f3ae9aa116216bfad1ec737c746dd20.png', 'bordeaux-blanc-2018'),

-- 10 Bordeaux Rouge 2017
('Bordeaux Rouge', 'red', 'bottle', 2017, 6.00, 36000, 0, 'AOC', 8.00,
 'Sainte-Croix-du-Mont et Gabarnac', 'Merlot 85% - Cabernet Sauvignon 15%', 20,
 '{"fr":"Une robe rouge sombre avec des reflets rubis. Un nez expressif sur des notes de fruits frais (cassis groseilles). Un bon équilibre en bouche, des tanins assez souples et une bonne tension lui confèrent de la longueur.","en":"A dark red color with ruby reflections. An expressive nose with notes of fresh fruit (blackcurrant redcurrants). Good balance on the palate, fairly supple tannins and good tension give it length."}',
 '{"fr":"Argilo-calcaire, argilo-graveleux","en":"Clayey-limestone, clayey-gravelly"}',
 '{"fr":"Guyot mixte","en":"Mixed Guyot"}',
 '{"fr":"Mécaniques","en":"Mechanicals"}',
 '{"fr":"Cuvaison 15 jours","en":"15 days vatting"}',
 '{"fr":"24 mois en cuves Inox","en":"24 months in stainless steel tanks"}',
 '{"fr":"","en":""}', '{"fr":"","en":""}',
 'Wine_Bordeaux Rouge_2017_ae56077be992be9357190ca829658593.png', 'bordeaux-rouge-2017'),

-- 11 Sainte-Croix-du-Mont 2015 Cuvée Spéciale
('Sainte-Croix-du-Mont', 'sweet', 'bottle', 2015, 14.50, 7000, 0, 'AOC', 27.00,
 'Sainte-Croix-du-Mont', 'Sémillon 98%', 30,
 '{"fr":"Une robe jaune bouton d\'or. Un nez fruité sur des notes d\'abricot et d\'ananas. De la fraîcheur au nez comme en bouche. Un bon équilibre alcool-liqueur-acidité lui confèrent du gras et du volume de bouche. On retrouve aussi quelques notes vanillées en plus des notes de fruits frais.","en":"A button yellow dress. A fruity nose with notes of apricot and pineapple. Freshness on the nose and on the palate. A good alcohol-liquor-acidity balance gives it fat and volume in the mouth."}',
 '{"fr":"Dominante argileuse et multiples expositions","en":"Dominant clay and multiple exposures"}',
 '{"fr":"Guyot simple et mixte","en":"Simple and mixed Guyot"}',
 '{"fr":"Manuelles (3 tries successives)","en":"Manual (3 successive sorting)"}',
 '{"fr":"Thermorégulation","en":"Thermoregulation"}',
 '{"fr":"Sélection des meilleurs lots de l\'année et passage 12 mois en fûts de chêne","en":"Selection of the best lots of the year and passing 12 months in oak barrels"}',
 '{"fr":"Médaille d\'Or Paris 2019","en":"Gold Medal Paris 2019"}',
 '{"fr":"Cuvée Spéciale","en":"Special Cuvée"}',
 'Wine_Sainte-Croix-du-Mont_2015_ecd4d5722452d9e74f9860b58071e626.png', 'sainte-croix-du-mont-2015-cuvee-speciale'),

-- 12 Vin de Pays de l'Atlantique 2019 (Rosé)
('Vin de Pays de l\'Atlantique', 'rosé', 'bottle', 2019, 5.80, 5000, 0, 'AOC', 1.00,
 'Sainte-Croix-du-Mont', 'Merlot', 30,
 '{"fr":"Une robe brillante avec une teinte vive. Au nez, des notes de bonbons anglais. Assez rond en bouche avec une certaine longueur.","en":"A shiny dress with a vivid tint. On the nose, notes of English sweets. Rather round in the mouth with a certain length."}',
 '{"fr":"Limono-argileux","en":"Silty clay"}',
 '{"fr":"Guyot mixte","en":"Mixed Guyot"}',
 '{"fr":"Mécaniques","en":"Mechanicals"}',
 '{"fr":"Pressurage direct","en":"Direct pressing"}',
 '{"fr":"5 mois en cuves inox","en":"5 months in stainless steel tanks"}',
 '{"fr":"","en":""}', '{"fr":"Existe aussi en BIB (Bag-In-Box) de 5L.","en":"Also available in 5L BIB (Bag-In-Box)."}',
 'Wine_Vin de Pays de l\'Atlantique_2019_4a1ce290f10038fd000f17fd7ff13448.png', 'vin-pays-atlantique-rose-2019'),

-- 13 Côtes de Bordeaux Rouge 2015 Cuvée Spéciale
('Côtes de Bordeaux Rouge', 'red', 'bottle', 2015, 8.10, 26000, 0, 'AOC', 4.00,
 'Gabarnac', 'Merlot 66% - Cabernet Sauvignon 34%', 30,
 '{"fr":"Une belle robe profonde rouge foncée et brillante. Complexe au nez avec des arômes de fruits noirs mûrs (cassis), de vanille. Une attaque en bouche franche et équilibrée, de la fraîcheur, les tanins sont soyeux en milieu de bouche, des notes de raisins mûrs avec une finale harmonieuse.","en":"A beautiful deep dark red and shiny color. Complex on the nose with aromas of ripe black fruits (blackcurrant), vanilla. A frank and balanced attack on the palate."}',
 '{"fr":"Argilo-calcaire, argilo-graveleux","en":"Clayey-limestone, clayey-gravelly"}',
 '{"fr":"Guyot mixte","en":"Mixed Guyot"}',
 '{"fr":"Mécaniques","en":"Mechanicals"}',
 '{"fr":"Cuvaison de 3 semaines","en":"3 weeks vatting"}',
 '{"fr":"12 mois en fûts de chêne (25% de fûts neufs) après sélection de nos meilleures parcelles de l\'année","en":"12 months in oak barrels (25% new barrels) after selection of our best plots of the year"}',
 '{"fr":"","en":""}', '{"fr":"Cuvée Spéciale","en":"Special Cuvée"}',
 'Wine_Côtes de Bordeaux Rouge_2015_324831ce953b22fa81f2e5ea7efdcf32.png', 'cotes-bordeaux-rouge-2015-cuvee-speciale'),

-- 14 Bordeaux Blanc 2019
('Bordeaux Blanc', 'white', 'bottle', 2019, 6.20, 12000, 0, 'AOC', 2.50,
 'Sainte-Croix-du-Mont', 'Sauvignon blanc & gris 100%', 30,
 '{"fr":"Une robe brillante, blanc/jaune. Un nez citronné et de fleurs blanches. Charnu, un bon volume en bouche avec de la fraîcheur, et une très légère amertume en finale.","en":"A brilliant, white / yellow color. A lemony nose and white flowers. Fleshy, a good volume in the mouth with freshness, and a very slight bitterness on the finish."}',
 '{"fr":"Argilo-calcaire, argilo-graveleux","en":"Clayey-limestone, clayey-gravelly"}',
 '{"fr":"Guyot mixte","en":"Mixed Guyot"}',
 '{"fr":"Mécaniques","en":"Mechanicals"}',
 '{"fr":"Macération pelliculaire","en":"Skinn maceration"}',
 '{"fr":"3 mois en cuves inox","en":"3 months in stainless steel tanks"}',
 '{"fr":"","en":""}', '{"fr":"Existe aussi en BIB (Bag-In-Box) de 5L.","en":"Also available in 5L BIB (Bag-In-Box)."}',
 'Wine_Bordeaux Blanc_2019_73b268b17fd35d7ad8b97533416c2a09.png', 'bordeaux-blanc-2019'),

-- 15 Premières Côtes de Bordeaux Blanc 2015
('Premières Côtes de Bordeaux Blanc', 'sweet', 'bottle', 2015, 7.40, 10000, 0, 'AOC', 4.00,
 'Gabarnac', 'Sémillon', 45,
 '{"fr":"Une robe brillante, jaune pâle. Le nez est ouvert avec des notes de fleurs blanches. La bouche est élégante avec un bel équilibre sucre-acidité; une finale fraîche sur des notes d\'agrumes légèrement citronnées.","en":"A brilliant, pale yellow color. The nose is open with notes of white flowers. The palate is elegant with a nice balance between sugar and acidity; a fresh finish on slightly lemony citrus notes."}',
 '{"fr":"Argileux-sablo-limoneux","en":"Clayey-sandy-silty"}',
 '{"fr":"Guyot simple et mixte","en":"Simple and mixed Guyot"}',
 '{"fr":"Manuelles (3 tries successives)","en":"Manual (3 successive tests)"}',
 '{"fr":"Pressurage pneumatique, thermorégulation","en":"Pneumatic pressing, thermoregulation"}',
 '{"fr":"36 mois en cuves inox","en":"36 months in stainless steel tanks"}',
 '{"fr":"","en":""}', '{"fr":"","en":""}',
 'Wine_Premières Côtes de Bordeaux Blanc_2015_90aeceecdc213ae9bb60d72b6c2fbe87.png', 'premieres-cotes-bordeaux-blanc-2015'),

-- 16 Sainte-Croix-du-Mont 2015
('Sainte-Croix-du-Mont', 'sweet', 'bottle', 2015, 11.50, 30000, 1, 'AOC', 27.00,
 'Sainte-Croix-du-Mont', 'Sémillon 98%', 30,
 '{"fr":"Une robe brillante, jaune or. Un nez de bonne puissance avec des notes de pain d\'épice et de miel. En bouche, une attaque assez vive, avec du volume et un bon équilibre en milieu de bouche, une finale délicate et assez longue.","en":"A shiny, yellow or. A nose of good power with hints of gingerbread and honey. On the palate, a fairly lively attack, with volume and a good balance in the mid-palate, a delicate and fairly long finish."}',
 '{"fr":"Dominante argileuse et multiples expositions","en":"Dominant Clayey and multiple exposures"}',
 '{"fr":"Guyot simple et mixte","en":"Simple and mixed Guyot"}',
 '{"fr":"Manuelles (3 tries successives)","en":"Manual (3 successive sorting)"}',
 '{"fr":"Thermorégulation","en":"Thermoregulation"}',
 '{"fr":"36 mois en cuves inox","en":"36 months in stainless steel tanks"}',
 '{"fr":"Médaille d\'Or Macon 2017","en":"Gold Medal Macon 2017"}', '{"fr":"","en":""}',
 'Wine_Sainte-Croix-du-Mont_2015_c34d68ee8a0915f54c3b35b37ebb2140.png', 'sainte-croix-du-mont-2015'),

-- 17 Côtes de Bordeaux Rouge 2016 Cuvée Spéciale
('Côtes de Bordeaux Rouge', 'red', 'bottle', 2016, 8.20, 13300, 0, 'AOC', 2.00,
 'Sainte-Croix-du-Mont', 'Merlot 74% Cabernet Sauvignon 26%', 25,
 '{"fr":"En cours","en":"In progress"}',
 '{"fr":"Dominante argileuse","en":"Dominante argileuse"}',
 '{"fr":"Guyot simple","en":"Guyot mixte"}',
 '{"fr":"Manuelles par tries successives","en":"Mécaniques"}',
 '{"fr":"16 jours de cuvaison","en":"16 jours de cuvaison"}',
 '{"fr":"12 mois en fûts de chêne","en":"12 mois en futs de chêne"}',
 '{"fr":"","en":""}', '{"fr":"Cuvée Spéciale","en":"Cuvée Spéciale"}',
 'Wine_Côtes de Bordeaux Rouge_2016_2d47d13f14e5dea7b63b7023190ee408.png', 'cotes-bordeaux-rouge-2016-cuvee-speciale'),

-- 18 Bordeaux Blanc 2020
('Bordeaux Blanc', 'white', 'bottle', 2020, 6.20, 10000, 0, 'AOC', 3.00,
 'Sainte-Croix-du-Mont', '100% Sauvignon Blanc et Gris', 20,
 '{"fr":"Robe brillante assez pâle. Des notes délicates de fruits à chair blanche avec un retour frais légèrement citronné. Bouche souple, équilibrée et rafraichissante.","en":"Brilliant, fairly pale colour. Delicate notes of white-fleshed fruits with a fresh, slightly lemony aftertaste. Supple, balanced and refreshing palate."}',
 '{"fr":"Argilo calcaire et argilo graveleux","en":"Argilo calcaire et argilo graveleux"}',
 '{"fr":"Guyot simple","en":"Simple Guyot"}',
 '{"fr":"Mécaniques","en":"Manuelles par tries successives"}',
 '{"fr":"Fermentation à 18°C après stabulation à froid","en":"Fermentation at 18°C after cold stabilization"}',
 '{"fr":"6 mois en cuves inox","en":"6 mois en cuves inox"}',
 '{"fr":"","en":""}', '{"fr":"Existe également en Bib de 5 litres","en":"Existe également en Bib de 5 litres"}',
 'Wine_Bordeaux Blanc_2020_a753befa1d63b7dbd6cb95be911ae6d0.png', 'bordeaux-blanc-2020'),

-- 19 Bordeaux Rouge 2018
('Bordeaux Rouge', 'red', 'bottle', 2018, 6.20, 36000, 0, 'AOC', 8.00,
 'Sainte-Croix-du-Mont et Gabarnac', 'Merlot 75% - Cabernet Sauvignon 25%', 25,
 '{"fr":"Une robe rouge sombre aux reflets rubis. Un nez expressif avec des notes de fruits frais (cassis groseilles). Un bon équilibre en bouche, des tanins assez souples et une bonne tension lui donnent de la longueur.","en":"A dark red color with ruby reflections. An expressive nose with notes of fresh fruit (blackcurrant redcurrants). Good balance on the palate, fairly supple tannins and good tension give it length."}',
 '{"fr":"Argilo-calcaire, argilo-graveleux","en":"Clayey-limestone, clayey-gravelly"}',
 '{"fr":"Guyot simple","en":"Simple Guyot"}',
 '{"fr":"Manuelles par tries successives","en":"Manuelles par tries successives"}',
 '{"fr":"Cuvaison 15 jours","en":"15 days vatting"}',
 '{"fr":"24 mois en cuve inox","en":"24 months in stainless steel tanks"}',
 '{"fr":"","en":""}', '{"fr":"","en":""}',
 'Wine_Bordeaux Rouge_2018_13b1fb44c6cea4d065747b287f62480a.png', 'bordeaux-rouge-2018'),

-- 20 Sainte-Croix-du-Mont 2021 (Rosé)
('Sainte-Croix-du-Mont', 'rosé', 'bottle', 2021, 6.00, 4800, 0, 'IGP', 0.90,
 'Sainte-Croix-du-Mont', 'Merlot', 35,
 '{"fr":"Robe brillante d\'un rose très pâle. Nez plein de fraîcheur développant des notes de groseilles. Bouche ronde et charnue se terminant sur la fraîcheur.","en":"Brilliant, very pale pink colour. Nose full of freshness developing notes of currants. Round and fleshy palate ending on freshness."}',
 '{"fr":"Limono argileux","en":"Limono argileux"}',
 '{"fr":"Guyot simple","en":"Guyot mixte"}',
 '{"fr":"Mécaniques","en":"Manuelles par tries successives"}',
 '{"fr":"Pressurage direct après une courte macération","en":"Pressurage direct après une courte macération"}',
 '{"fr":"3 mois en cuve","en":"3 mois en cuve"}',
 '{"fr":"","en":""}', '{"fr":"","en":""}',
 'Wine_Sainte-Croix-du-Mont_2021_19bd27113deeb9b43f7224dfaf14c045.png', 'sainte-croix-du-mont-rose-2021'),

-- 21 Sainte-Croix-du-Mont 2016
('Sainte-Croix-du-Mont', 'sweet', 'bottle', 2016, 11.50, 30000, 1, 'AOC', 22.00,
 'Sainte-Croix-du-Mont', 'Sémillon 95%', 35,
 '{"fr":"Belle robe brillante, bouton d\'Or. Le nez encore discret se développe à l\'aération avec des notes de fleurs d\'acacia et de fruits confits. Un bon équilibre en bouche où l\'on retrouve de l\'ananas, une finale chaleureuse et un retour arômatique intense.","en":"Beautiful shiny color, buttercup. The still discreet nose develops with aeration with notes of acacia flowers and candied fruits. A good balance in the mouth where we find pineapple, a warm finish and an intense aromatic return."}',
 '{"fr":"Dominante argileuse","en":"Dominante argileuse"}',
 '{"fr":"Guyot simple","en":"Guyot mixte"}',
 '{"fr":"Manuelles par tries successives","en":"Manuelles par tries successives"}',
 '{"fr":"Thermorégulation","en":"Thermorégulation"}',
 '{"fr":"36 mois en cuves","en":"36 mois en cuves"}',
 '{"fr":"","en":""}', '{"fr":"","en":""}',
 'Wine_Sainte-Croix-du-Mont_2016_5d20efcf2f2b79d2f95536eed2a13f4f.png', 'sainte-croix-du-mont-2016'),

-- 22 Bordeaux Rouge 2019
('Bordeaux Rouge', 'red', 'bottle', 2019, 6.80, 30000, 0, 'AOC', 7.00,
 'Sainte Croix du Mont et Gabarnac', 'Merlot 90%, Cabernet Sauvignon 10%', 25,
 '{"fr":"Robe grenat brillante avec quelques reflets briques. Nez de fruits au Kirch légèrement chocolatés. Bouche ronde aromatique, notes de fruits rouges comme la framboise. De la fraîcheur et une finale chaleureuse.","en":"Brilliant garnet color with some brick reflections. Slightly chocolate Kirch fruit nose. Round aromatic mouth, notes of red fruits such as raspberry. Freshness and a warm finish."}',
 '{"fr":"Dominante argileuse","en":"Dominante argileuse"}',
 '{"fr":"Guyot simple","en":"Guyot mixte"}',
 '{"fr":"Mécaniques","en":"Manuelles par tries successives"}',
 '{"fr":"16 jours de cuvaison","en":"16 jours de cuvaison"}',
 '{"fr":"24 mois en cuves Inox","en":"24 mois en Cuves Inoxs"}',
 '{"fr":"","en":""}', '{"fr":"","en":""}',
 'Wine_Bordeaux Rouge_2019_8738262f0bbc95e17a83b2ab02c0786a.png', 'bordeaux-rouge-2019'),

-- 23 Bordeaux Blanc 2021
('Bordeaux Blanc', 'white', 'bottle', 2021, 6.80, 10000, 0, 'AOC', 3.00,
 'Sainte Croix du Mont', '60% Sauvignon Blanc et 40% Sauvignon Gris', 20,
 '{"fr":"Robe brillante assez pâle. Des notes florales et citronnées. Bouche fraîche, équilibrée et rafraichissante.","en":"Bright, pale color. Floral and lemon notes. Supply, balanced and refreshing mouthfeel."}',
 '{"fr":"Argilo calcaire et argilo graveleux","en":"Argilo calcaire et argilo graveleux"}',
 '{"fr":"Guyot simple","en":"Simple Guyot"}',
 '{"fr":"Mécaniques","en":"Manuelles par tries successives"}',
 '{"fr":"Fermentation à 18°C après stabulation à froid","en":"Fermentation at 18°C after cold stabilization"}',
 '{"fr":"6 mois en cuves inox","en":"6 months in stainless steel vats"}',
 '{"fr":"","en":""}', '{"fr":"","en":""}',
 'Wine_Bordeaux Blanc_2021_c029d830535f1e9e62763fd89f524c6e.png', 'bordeaux-blanc-2021'),

-- 24 Vin de Pays de l'Atlantique 2022 (Rosé)
('Vin de Pays de l\'Atlantique', 'rosé', 'bottle', 2022, 6.90, 5700, 0, 'IGP', 1.00,
 'Sainte Croix du Mont', 'Merlot 100%', 30,
 '{"fr":"Jolie robe rose de couleur vive. Des notes de fruits mûrs d\'été sont plaisantes au nez et accompagnent la belle rondeur en bouche.","en":"Pretty bright pink color. Notes of ripe summer fruits are pleasant on the nose and accompany the lovely roundness on the palate."}',
 '{"fr":"Dominante argilo limoneuse","en":"Dominante argilo limoneuse"}',
 '{"fr":"Guyot mixte","en":"Simple Guyot"}',
 '{"fr":"Mécaniques","en":"Manuelles par tries successives"}',
 '{"fr":"Pressurage direct","en":"Pressurage direct"}',
 '{"fr":"2 mois en cuves inox","en":"2 mois en cuves inox"}',
 '{"fr":"","en":""}', '{"fr":"","en":""}',
 'Wine_Vin de Pays de l\'Atlantique_2022_7db0baf4664663ab00d3502828a3f3bc.png', 'vin-pays-atlantique-rose-2022'),

-- 25 Côtes de Bordeaux Rouge 2017 Cuvée Spéciale
('Côtes de Bordeaux Rouge', 'red', 'bottle', 2017, 8.90, 13300, 0, 'AOC', 2.50,
 'Gabarnac', 'Merlot 95% Cabernet Sauvignon 5%', 30,
 '{"fr":"Robe rouge d\'intensité moyenne, le nez est ouvert avec des notes franches et délicates de fruits mûrs. Bouche ronde, élégante, très harmonieuse. Vin de plaisir immédiat.","en":"Red color of medium intensity, the nose is open with frank and delicate notes of ripe fruit. Round, elegant, very harmonious mouth. Wine of immediate pleasure."}',
 '{"fr":"Dominante Argilo calcaire","en":"Dominante Argilo calcaire"}',
 '{"fr":"Guyot mixte","en":"Simple Guyot"}',
 '{"fr":"Mécaniques","en":"Manuelles par tries successives"}',
 '{"fr":"12 jours de cuvaison","en":"12 jours de cuvaison"}',
 '{"fr":"12 mois en barriques","en":"12 mois en barriques"}',
 '{"fr":"Médaille d\'Or Paris 2020","en":"Médaille d\'Or Paris 2020"}', '{"fr":"","en":""}',
 'Wine_Côtes de Bordeaux Rouge_2017_ea0fd676b0c276b2e4781c1d8027e5dd.png', 'cotes-bordeaux-rouge-2017-cuvee-speciale'),

-- 26 Premières Côtes de Bordeaux Blanc 2017
('Premières Côtes de Bordeaux Blanc', 'sweet', 'bottle', 2017, 8.70, 10000, 1, 'AOC', 3.00,
 'Gabarnac', 'Sémillon', 45,
 '{"fr":"Une robe brillante, jaune pâle. Le nez est ouvert avec des notes de fleurs blanches. La bouche est élégante avec un bel équilibre sucre-acidité; une finale fraîche et moelleuse sur des notes d\'agrumes légèrement confites.","en":"A shiny, pale yellow robe. The nose is open with notes of white flowers. The palate is elegant with a nice sugar-acidity balance; a fresh and mellow finish on slightly candied citrus notes."}',
 '{"fr":"Argileux-sablo-limoneux","en":"Argileux-sablo-limoneux"}',
 '{"fr":"Guyot simple et mixte","en":"Simple Guyot"}',
 '{"fr":"Manuelles (3 tries successives)","en":"Manuelles par tries successives"}',
 '{"fr":"Pressurage pneumatique, thermorégulation","en":"Pressurage pneumatique, thermorégulation"}',
 '{"fr":"36 mois en cuves inox","en":"36 mois en cuves inox"}',
 '{"fr":"Médaille d\'Or Bordeaux 2018","en":"Médaille d\'Or Bordeaux 2018"}', '{"fr":"","en":""}',
 'Wine_Premières Côtes de Bordeaux Blanc_2017_59cd069458da9e276c96389a3a1aebd2.png', 'premieres-cotes-bordeaux-blanc-2017'),

-- 27 Bordeaux Blanc 2022
('Bordeaux Blanc', 'white', 'bottle', 2022, 7.00, 9000, 0, 'AOC', 3.00,
 'Sainte Croix du Mont', 'Sauvignon blanc et gris', 30,
 '{"fr":"Robe brillante d\'une légère couleur jaune pâle. Au nez des notes florales et de fruits jaunes caractérisant une belle maturité du cépage. Du volume en bouche avec une attaque chaleureuse et une belle fraîcheur arômatique.","en":"Robe brillante d\'une légère couleur jaune pâle. Au nez des notes florales et de fruits jaunes caractérisant une belle maturité du cépage."}',
 '{"fr":"Dominante Argileuse","en":"Dominante Argileuse"}',
 '{"fr":"Guyot mixte","en":"Guyot mixte"}',
 '{"fr":"Mécaniques","en":"Mécaniques"}',
 '{"fr":"Stabulation et fermentation à température contrôlée","en":"Stabulation et fermentation a température contrôlée"}',
 '{"fr":"4 mois en cuves inox","en":"4 mois en cuves inox"}',
 '{"fr":"Médaille d\'Argent CGA Paris 2023","en":"Médaille d\'Argent CGA Paris 2023"}',
 '{"fr":"À déguster avec des huîtres du bassin d\'Arcachon","en":"A déguster avec des huitres du bassin d\'Arcahon"}',
 'Wine_Bordeaux Blanc_2022_61d86aed9dfec852b0185620024d47f4.png', 'bordeaux-blanc-2022'),

-- 28 Sainte-Croix-du-Mont 2016 Cuvée Spéciale
('Sainte-Croix-du-Mont', 'sweet', 'bottle', 2016, 15.20, 6000, 1, 'AOC', 22.00,
 'Sainte-Croix-du-Mont', 'Sémillon 95%', 35,
 '{"fr":"Belle robe Or.","en":"Belle robe Or."}',
 '{"fr":"Dominante Argilo Calcaire","en":"Dominante Argilo Calcaire"}',
 '{"fr":"Guyot mixte","en":"Guyot mixte"}',
 '{"fr":"Manuelles par tries successives","en":"Manuelles par tries successives"}',
 '{"fr":"Traditionnelle","en":"Traditionnelle"}',
 '{"fr":"12 mois en barriques et 3 ans en cuves","en":"12 mois en barriques et 3 ans en cuves"}',
 '{"fr":"Médaille d\'argent Bordeaux","en":"Médaille d\'argent Bordeaux"}',
 '{"fr":"Cuvée Spéciale","en":"Cuvée Spéciale"}',
 'Wine_Sainte-Croix-du-Mont_2016_528bb90fbcb9f726b165700436f190e8.png', 'sainte-croix-du-mont-2016-cuvee-speciale'),

-- 29 Côtes de Bordeaux Rouge 2018 Cuvée Spéciale
('Côtes de Bordeaux Rouge', 'red', 'bottle', 2018, 9.80, 13300, 0, 'AOC', 2.00,
 'Sainte-Croix-du-Mont et Gabarnac', 'Cabernet Sauvignon 95%, Merlot 5%', 20,
 '{"fr":"Belle robe sombre.","en":"Belle robe sombre."}',
 '{"fr":"Dominante Argilo Calcaire","en":"Dominante Argilo Calcaire"}',
 '{"fr":"Guyot simple","en":"Guyot simple"}',
 '{"fr":"Mécaniques","en":"Mécaniques"}',
 '{"fr":"Traditionnelle","en":"Traditionnelle"}',
 '{"fr":"12 mois en barriques","en":"12 mois en barriques"}',
 '{"fr":"Médaille d\'argent Bordeaux","en":"Médaille d\'argent Bordeaux"}',
 '{"fr":"Cuvée Spéciale","en":"Cuvée Spéciale"}',
 'Wine_Côtes de Bordeaux Rouge_2018_6074a0d584087023d5746ebc00d774b3.png', 'cotes-bordeaux-rouge-2018-cuvee-speciale'),

-- 30 Bordeaux Rouge 2020
('Bordeaux Rouge', 'red', 'bottle', 2020, 7.70, 35000, 1, 'AOC', 10.00,
 'Sainte-Croix-du-Mont et Gabarnac', 'Merlot 90%, Cabernet Sauvignon 10%', 20,
 '{"fr":"Belle robe rouge sombre de bonne intensité. Nez expressif sur des notes de fruits mûrs et d\'épices. Bouche ronde, élégance avec de la puissance et une bonne longueur. Les tanins présents laissent augurer un beau potentiel de garde.","en":"Belle robe rouge sombre."}',
 '{"fr":"Dominante Argilo Calcaire","en":"Dominante Argilo Calcaire"}',
 '{"fr":"Guyot mixte","en":"Guyot mixte"}',
 '{"fr":"Mécaniques","en":"Mécaniques"}',
 '{"fr":"Traditionnelle","en":"Traditionnelle"}',
 '{"fr":"36 mois en cuves et barriques","en":"36 mois en cuves"}',
 '{"fr":"Médaille d\'Or CGA Paris 2022","en":"Médaille d\'Or CGA Paris 2022"}', '{"fr":"","en":""}',
 'Wine_Bordeaux Rouge_2020_3afb48525a1d22de6a93bb132ce35957.png', 'bordeaux-rouge-2020'),

-- 31 Sainte-Croix-du-Mont 2017
('Sainte-Croix-du-Mont', 'sweet', 'bottle', 2017, 11.00, 35000, 1, 'AOC', 20.00,
 'Sainte Croix du Mont', 'Sémillon 95%, 4% Sauvignon, 1% Muscadelle', 30,
 '{"fr":"Belle robe dorée, brillante. Nez puissant de fruits confits (orange, abricot) et de miel. Bouche onctueuse, riche et avec une pointe d\'amertume amenant de la fraîcheur. Finale longue et digeste.","en":"Belle robe dorée, brillante. Nez de fruits frais."}',
 '{"fr":"Dominante Argileuse","en":"Dominante Argileuse"}',
 '{"fr":"Guyot mixte","en":"Guyot mixte"}',
 '{"fr":"Manuelles par tries successives","en":"Manuelles par tries successives"}',
 '{"fr":"Traditionnelle, levures indigènes et thermorégulation","en":"Traditionnelle"}',
 '{"fr":"63 mois en cuves et barriques","en":"36 mois en cuves"}',
 '{"fr":"Médaille d\'Or Bordeaux — 2 * Guide Hachette","en":"Médaille d\'Or Bordeaux"}', '{"fr":"","en":""}',
 'Wine_Sainte-Croix-du-Mont_2017_5b2273c2a72f87f0d3856c032e88eddd.png', 'sainte-croix-du-mont-2017'),

-- 32 Vin de Pays de l'Atlantique 2023 (Rosé)
('Vin de Pays de l\'Atlantique', 'rosé', 'bottle', 2023, 7.50, 5000, 0, 'IGP', 1.00,
 'Sainte Croix du Mont', 'Merlot 95%, Cabernet Sauvignon 5%', 30,
 '{"fr":"Belle robe rose clair, nez parfumé de fruits rouges type groseille, bouche ronde et enrobée avec un soupçon de sucrosité. Fraîcheur conservée en finale.","en":"Belle robe rose clair, nez parfumé de fruits rouges type groseille, bouche ronde et enrobée avec un soupçon de sucrosité."}',
 '{"fr":"Dominante Argileuse","en":"Dominante Argileuse"}',
 '{"fr":"Guyot mixte","en":"Guyot mixte"}',
 '{"fr":"Mécaniques","en":"Mécaniques"}',
 '{"fr":"Pressurage direct","en":"Pressurage direct"}',
 '{"fr":"5 mois en cuves inox","en":"5 mois en cuves inox"}',
 '{"fr":"","en":""}', '{"fr":"","en":""}',
 'Wine_Sainte-Croix-du-Mont_2023_bcd739729f70fdbf1730a5d05a1d9d70.png', 'vin-pays-atlantique-rose-2023'),

-- 33 Bordeaux Blanc 2023
('Bordeaux Blanc', 'white', 'bottle', 2023, 7.50, 9000, 0, 'AOC', 3.00,
 'Sainte Croix du Mont', 'Sauvignon blanc et gris', 20,
 '{"fr":"Robe claire et brillante. Des notes fraîches, variétales et fruitées. Une bonne bouche où l\'on retrouve la fraîcheur avec une légère pointe citronnée.","en":"Robe claire et brillante. Des notes fraîches, variétales et fruitées."}',
 '{"fr":"Dominante Argileuse","en":"Dominante Argileuse"}',
 '{"fr":"Guyot mixte","en":"Guyot mixte"}',
 '{"fr":"Mécaniques","en":"Mécaniques"}',
 '{"fr":"Stabulation et fermentation à température contrôlée","en":"Stabulation et fermentation a température contrôlée"}',
 '{"fr":"5 mois en cuves inox","en":"5 mois en cuves inox"}',
 '{"fr":"     ","en":"     "}', '{"fr":"","en":""}',
 'Wine_Bordeaux Blanc_2023_a199f1c1a6d03a211385ff1f31d227f8.png', 'bordeaux-blanc-2023'),

-- 34 Côtes de Bordeaux Rouge 2019 Cuvée Spéciale
('Côtes de Bordeaux Rouge', 'red', 'bottle', 2019, 10.00, 13300, 0, 'AOC', 2.00,
 'Sainte-Croix-du-Mont et Gabarnac', 'Cabernet Sauvignon 100%', 20,
 '{"fr":"Jolie robe sombre.","en":"Jolie robe sombre."}',
 '{"fr":"Dominante Argileuse","en":"Dominante Argileuse"}',
 '{"fr":"Guyot mixte","en":"Guyot mixte"}',
 '{"fr":"Mécaniques","en":"Mécaniques"}',
 '{"fr":"15-18 jours de macération","en":"1518 jours de macération"}',
 '{"fr":"12 mois en barrique et 24 mois en cuve","en":"12 mois en barrique et 24 mois en cuve"}',
 '{"fr":"Médaille d\'Or Bordeaux","en":"Médaille d\'Or Bordeaux"}', '{"fr":"","en":""}',
 'Wine_Côtes de Bordeaux Rouge_2019_c370e0c109a2d12c2b3457682e981913.png', 'cotes-bordeaux-rouge-2019-cuvee-speciale'),

-- 35 Bordeaux Blanc 2024
('Bordeaux Blanc', 'white', 'bottle', 2024, 7.70, 10000, 1, 'AOC', 2.00,
 'Sainte-Croix-du-Mont', 'Sauvignon blanc 65% Sauvignon gris 35%', 25,
 '{"fr":"Robe claire et brillante, nez fruité qui se développe à l\'aération, bouche fraîche et fruitée.","en":"Robe claire et brillante, nez fruité qui se développe à l\'aération, bouche fraîche et fruité"}',
 '{"fr":"Dominante Argilo calcaire","en":"Dominante Argilo calcaire"}',
 '{"fr":"Guyot mixte","en":"Guyot mixte"}',
 '{"fr":"Mécaniques","en":"Mécaniques"}',
 '{"fr":"Pressurage direct puis stabulation au froid 3 jours","en":"pressurage direct puis stabulation au froid 3 jours15"}',
 '{"fr":"5 mois en cuves inox","en":"5 mois en cuves inox"}',
 '{"fr":"Médaille d\'Or Bordeaux","en":"Médaille d\'Or Bordeaux"}', '{"fr":"","en":""}',
 'Wine_Bordeaux Blanc_2024_5954f86fb10716610ae55c99d1b69454.png', 'bordeaux-blanc-2024'),

-- 36 Sainte-Croix-du-Mont 2018
('Sainte-Croix-du-Mont', 'sweet', 'bottle', 2018, 10.80, 30000, 1, 'AOC', 22.00,
 'Sainte Croix du Mont', 'Sémillon', 35,
 '{"fr":"Belle robe dorée, brillante, bouquet complexe avec des notes d\'oranges confites et de miel. Ces mêmes notes se retrouvent au palais qui est très expressif, ample et d\'une belle fraîcheur.","en":"Belle robe dorée, brillante, bouquet complexe avec des notes d\'oranges confites et de miel."}',
 '{"fr":"Dominante Argilo calcaire","en":"Dominante Argilo calcaire"}',
 '{"fr":"Guyot mixte","en":"Guyot mixte"}',
 '{"fr":"Manuelles par tries successives","en":"Manuelles par tries successives"}',
 '{"fr":"Pressurage pneumatique, thermorégulation","en":"Pressurage pneumatique, thermorégulation"}',
 '{"fr":"72 mois en cuves et barriques","en":"72 mois en cuves et barriques"}',
 '{"fr":"Médaille d\'Argent Lyon — 2 * Guide Hachette","en":"Médaille d\'Argent Lyon et 2 *Guide Hachette"}', '{"fr":"","en":""}',
 'Wine_Sainte-Croix-du-Mont_2018_658de46ecb6c802896343d2de714b701.png', 'sainte-croix-du-mont-2018'),

-- 37 Vin de Pays de l'Atlantique 2024 (Rosé)
('Vin de Pays de l\'Atlantique', 'rosé', 'bottle', 2024, 7.50, 4000, 1, 'IGP', 0.60,
 'Sainte Croix du Mont', 'Merlot', 30,
 '{"fr":"Belle robe rose pâle.","en":"Belle robe rose pâle."}',
 '{"fr":"Argilo limoneux","en":"Argilo limoneux"}',
 '{"fr":"Guyot mixte","en":"Guyot mixte"}',
 '{"fr":"Mécaniques","en":"Mécaniques"}',
 '{"fr":"Fermentation à 18°C","en":"Fermentation à 18°C"}',
 '{"fr":"3 mois en cuves","en":"3 mois en cuves"}',
 '{"fr":"","en":""}', '{"fr":"","en":""}',
 'Wine_Vin de Pays de l\'Atlantique_2024_810517101934c7ebf2e0475c4b89f4a1.png', 'vin-pays-atlantique-rose-2024'),

-- 38 Côtes de Bordeaux Rouge 2020 Cuvée Spéciale
('Côtes de Bordeaux Rouge', 'red', 'bottle', 2020, 10.00, 13400, 1, 'AOC', 2.50,
 'Gabarnac', 'Merlot 100%', 30,
 '{"fr":"Belle robe intense rouge sombre. Au nez de jolies notes de fruits mûrs. Palais chaleureux, ample, équilibré avec une belle fraîcheur laissant présager un beau potentiel de garde.","en":"Belle robe intense rouge sombre. Aux nez de jolies notes de fruits mûrs. Palais chaleureux, ample, équilibré."}',
 '{"fr":"Dominante argilo calcaire","en":"Dominante argilo calcaire"}',
 '{"fr":"Guyot mixte","en":"Guyot mixte"}',
 '{"fr":"Mécaniques","en":"Mécaniques"}',
 '{"fr":"Traditionnelle en cuves inox thermorégulées","en":"Traditionnelle en cuves inox thermorégulées"}',
 '{"fr":"12 mois en fûts de chêne","en":"12 mois en fûts de chêne"}',
 '{"fr":"Médaille d\'Or Bordeaux 2023 et coup de cœur Guide Hachette","en":"Médaille d\'Or Bordeaux 2023 et coup de coeur guide hachette"}',
 '{"fr":"Cuvée spéciale","en":"Cuvée spéciale"}',
 'Wine_Côtes de Bordeaux Rouge_2020_6a71fd0c7991438d6d191568b920fa14.png', 'cotes-bordeaux-rouge-2020-cuvee-speciale');

UPDATE `wines` SET `is_cuvee_speciale` = 1 WHERE `slug` IN (
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
-- Super admin
(1, 'superadmin@dev.local',       '$2y$10$9fzGIQEmemTZoFq2ZBlkC.x4ZS4TkpYIkT8/8qPS7cvBikLSzegDa', 'individual', 'super_admin', 'fr', 0, '2026-01-01 08:00:00', 1, '2026-01-01 08:00:00'),
-- Admin
(2, 'admin@dev.local',            '$2y$10$9fzGIQEmemTZoFq2ZBlkC.x4ZS4TkpYIkT8/8qPS7cvBikLSzegDa', 'individual', 'admin',       'fr', 0, '2026-01-01 08:00:00', 1, '2026-01-01 08:00:00'),
-- Client vérifié avec commandes
(3, 'client.verifie@dev.local',   '$2y$10$9fzGIQEmemTZoFq2ZBlkC.x4ZS4TkpYIkT8/8qPS7cvBikLSzegDa', 'individual', 'customer',    'fr', 1, '2026-01-10 10:00:00', 1, '2026-01-10 10:00:00'),
-- Client non vérifié (email non confirmé)
(4, 'client.nouveau@dev.local',   '$2y$10$9fzGIQEmemTZoFq2ZBlkC.x4ZS4TkpYIkT8/8qPS7cvBikLSzegDa', 'individual', 'customer',    'fr', 0, NULL,                 0, '2026-02-01 09:00:00'),
-- Client société
(5, 'societe@dev.local',          '$2y$10$9fzGIQEmemTZoFq2ZBlkC.x4ZS4TkpYIkT8/8qPS7cvBikLSzegDa', 'company',    'customer',    'fr', 1, '2026-01-15 14:00:00', 1, '2026-01-15 14:00:00'),
-- Client EN
(6, 'customer.en@dev.local',      '$2y$10$9fzGIQEmemTZoFq2ZBlkC.x4ZS4TkpYIkT8/8qPS7cvBikLSzegDa', 'individual', 'customer',    'en', 0, '2026-02-10 11:00:00', 1, '2026-02-10 11:00:00'),
-- Client soft-deleted (en attente de suppression)
(7, 'client.supprime@dev.local',  '$2y$10$9fzGIQEmemTZoFq2ZBlkC.x4ZS4TkpYIkT8/8qPS7cvBikLSzegDa', 'individual', 'customer',    'fr', 0, '2026-01-20 08:00:00', 1, '2026-01-20 08:00:00');

-- Soft delete du compte 7
UPDATE `accounts`
SET `deleted_at` = '2026-03-01 10:00:00',
    `scheduled_deletion_at` = '2026-03-31 10:00:00'
WHERE `id` = 7;

ALTER TABLE `accounts` AUTO_INCREMENT = 8;

-- ============================================================
-- PROFILS INDIVIDUELS
-- ============================================================

INSERT INTO `account_individuals` (`account_id`, `lastname`, `firstname`, `civility`) VALUES
(1, 'Admin',    'Super',    'M'),
(2, 'Test',     'Admin',    'M'),
(3, 'Durand',   'Sophie',   'F'),
(4, 'Lefebvre', 'Marc',     'M'),
(6, 'Smith',    'James',    'M'),
(7, 'Martin',   'Claire',   'F');

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
(7, 2, 'billing',  'Admin',  'Test',     'M', '1 rue de la Mairie',   'Paris',     '75001', 'France', '0100000000', 1);

ALTER TABLE `addresses` AUTO_INCREMENT = 8;

-- ============================================================
-- ORDERS (toutes statuts couverts pour les tests)
-- ============================================================

INSERT INTO `orders`
    (`id`, `user_id`, `order_reference`, `content`, `price`, `payment_method`,
     `shipping_discount`, `id_billing_address`, `id_delivery_address`, `status`, `ordered_at`)
VALUES
-- paid — Sophie (référence pour tests commandes)
(1, 3, 'CBV-2026-000001',
 '[{"wine_id":16,"label_name":"Sainte-Croix-du-Mont","vintage":2015,"wine_color":"sweet","format":"bottle","qty":12,"unit_price":11.50,"subtotal":138.00}]',
 138.00, 'card', 0.00, 1, 2, 'paid', '2026-01-15 10:30:00'),

-- processing
(2, 3, 'CBV-2026-000002',
 '[{"wine_id":30,"label_name":"Bordeaux Rouge","vintage":2020,"wine_color":"red","format":"bottle","qty":6,"unit_price":7.70,"subtotal":46.20}]',
 46.20, 'card', 0.00, 1, 2, 'processing', '2026-02-01 14:00:00'),

-- shipped
(3, 3, 'CBV-2026-000003',
 '[{"wine_id":35,"label_name":"Bordeaux Blanc","vintage":2024,"wine_color":"white","format":"bottle","qty":12,"unit_price":7.70,"subtotal":92.40},{"wine_id":21,"label_name":"Sainte-Croix-du-Mont","vintage":2016,"wine_color":"sweet","format":"bottle","qty":6,"unit_price":11.50,"subtotal":69.00}]',
 161.40, 'card', 15.00, 1, 2, 'shipped', '2026-02-10 09:00:00'),

-- delivered
(4, 3, 'CBV-2026-000004',
 '[{"wine_id":7,"label_name":"Bordeaux Rouge","vintage":2016,"wine_color":"red","format":"bottle","qty":24,"unit_price":5.90,"subtotal":141.60}]',
 141.60, 'card', 15.00, 1, 2, 'delivered', '2026-01-05 11:00:00'),

-- cancelled
(5, 3, 'CBV-2026-000005',
 '[{"wine_id":9,"label_name":"Bordeaux Blanc","vintage":2018,"wine_color":"white","format":"bottle","qty":3,"unit_price":6.10,"subtotal":18.30}]',
 18.30, 'card', 0.00, 1, NULL, 'cancelled', '2026-01-20 16:00:00'),

-- refunded
(6, 3, 'CBV-2026-000006',
 '[{"wine_id":10,"label_name":"Bordeaux Rouge","vintage":2017,"wine_color":"red","format":"bottle","qty":6,"unit_price":6.00,"subtotal":36.00}]',
 36.00, 'card', 0.00, 1, 2, 'refunded', '2026-01-25 13:00:00'),

-- return_requested
(7, 3, 'CBV-2026-000007',
 '[{"wine_id":31,"label_name":"Sainte-Croix-du-Mont","vintage":2017,"wine_color":"sweet","format":"bottle","qty":6,"unit_price":11.00,"subtotal":66.00}]',
 66.00, 'card', 0.00, 1, 2, 'return_requested', '2026-02-15 08:00:00'),

-- pending (non payée — initiation tunnel)
(8, 3, 'CBV-2026-000008',
 '[{"wine_id":16,"label_name":"Sainte-Croix-du-Mont","vintage":2015,"wine_color":"sweet","format":"bottle","qty":12,"unit_price":11.50,"subtotal":138.00}]',
 138.00, 'card', 0.00, 1, 2, 'pending', '2026-03-01 17:00:00'),

-- société (id 5) — delivered
(9, 5, 'CBV-2026-000009',
 '[{"wine_id":34,"label_name":"Côtes de Bordeaux Rouge","vintage":2019,"wine_color":"red","format":"bottle","qty":48,"unit_price":10.00,"subtotal":480.00}]',
 480.00, 'transfer', 62.40, 4, 5, 'delivered', '2026-02-20 10:00:00'),

-- admin (id 2) — paid (pour tester vue admin)
(10, 2, 'CBV-2026-000010',
 '[{"wine_id":36,"label_name":"Sainte-Croix-du-Mont","vintage":2018,"wine_color":"sweet","format":"bottle","qty":12,"unit_price":10.80,"subtotal":129.60}]',
 129.60, 'card', 15.00, 7, NULL, 'paid', '2026-03-10 09:00:00');

ALTER TABLE `orders` AUTO_INCREMENT = 11;

-- ============================================================
-- CARTS (paniers actifs)
-- ============================================================

INSERT INTO `carts` (`user_id`, `content`, `price`, `withdrawal_price`, `delivery_price`, `total_quantity`) VALUES
-- Sophie — panier avec 2 articles
(3, '[{"wine_id":21,"label_name":"Sainte-Croix-du-Mont","vintage":2016,"wine_color":"sweet","format":"bottle","qty":6,"unit_price":11.50,"subtotal":69.00},{"wine_id":35,"label_name":"Bordeaux Blanc","vintage":2024,"wine_color":"white","format":"bottle","qty":6,"unit_price":7.70,"subtotal":46.20}]',
 115.20, 26.40, 0.00, 12),
-- James — panier 1 article
(6, '[{"wine_id":30,"label_name":"Bordeaux Rouge","vintage":2020,"wine_color":"red","format":"bottle","qty":12,"unit_price":7.70,"subtotal":92.40}]',
 92.40, 26.40, 15.00, 12);

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
-- Sophie — active
(3, 'mock-jwt-sophie-active-001', 'device-uuid-sophie-macbook', '82.65.10.1', 'Chrome · macOS', 'password', 'active', '2026-03-28 08:00:00', '2026-03-28 07:00:00', '2026-04-27 07:00:00'),
-- Sophie — expirée (historique)
(3, 'mock-jwt-sophie-expired-001', 'device-uuid-sophie-macbook', '82.65.10.1', 'Chrome · macOS', 'password', 'expired', '2026-02-01 18:00:00', '2026-01-01 09:00:00', '2026-02-01 09:00:00'),
-- Admin — active
(2, 'mock-jwt-admin-active-001', 'device-uuid-admin-chrome', '10.0.0.1', 'Firefox · Windows', 'password', 'active', '2026-03-28 07:30:00', '2026-03-28 07:00:00', '2026-04-27 07:00:00'),
-- Société — révoquée
(5, 'mock-jwt-societe-revoked-001', 'device-uuid-societe-ipad', '81.33.20.5', 'Safari · iOS', 'password', 'revoked', '2026-03-01 10:00:00', '2026-03-01 09:00:00', '2026-04-01 09:00:00');

-- ============================================================
-- TRUSTED DEVICES
-- ============================================================

INSERT INTO `trusted_devices` (`user_id`, `device_token`, `device_name`, `confirmed_at`, `last_seen`) VALUES
(3, 'device-uuid-sophie-macbook',  'Chrome · macOS',    '2026-01-10 10:05:00', '2026-03-28 08:00:00'),
(3, 'device-uuid-sophie-iphone',   'Safari · iOS',      '2026-02-01 11:00:00', '2026-03-20 14:00:00'),
(2, 'device-uuid-admin-chrome',    'Firefox · Windows', '2026-01-01 08:05:00', '2026-03-28 07:30:00'),
(5, 'device-uuid-societe-ipad',    'Safari · iOS',      '2026-01-15 14:05:00', '2026-03-01 10:00:00');

-- ============================================================
-- PASSWORD RESET TOKENS (quelques-uns pour les tests)
-- ============================================================

INSERT INTO `password_reset` (`user_id`, `token`, `requested_at`, `expires_at`) VALUES
-- Token valide (expires dans le futur)
(3, 'valid-reset-token-sophie-0001', '2026-03-28 06:00:00', '2026-03-28 07:00:00'),
-- Token expiré (pour tester le cas d'erreur)
(4, 'expired-reset-token-marc-0001', '2026-03-01 10:00:00', '2026-03-01 11:00:00');
