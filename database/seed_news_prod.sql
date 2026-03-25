-- ============================================================
-- Crabitan Bellevue — Seed production — actualités (14 news)
-- Données exportées depuis la base prod le 23/03/2026
-- ============================================================
-- Import : mysql -u root crabitan_bellevue < database/seed_news_prod.sql
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DELETE FROM `news`;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- NEWS (14 actualités)
-- ============================================================

INSERT INTO `news`
    (`title`, `text_content`, `image_path`, `link_path`, `slug`, `created_at`)
VALUES

('{"fr":"Refonte du site internet","en":"Website redesign"}',
 '{"fr":"Ce nouveau site a été conçu pour vous offrir une meilleure ergonomie (version mobile, tablette, mode jour et mode nuit). Il offre également un meilleur aperçu sur nos produits et notre domaine. Il s\'agit d\'une version Bêta, si vous rencontrez le moindre souci, merci de contacter : service-info@crabitanbellevue.fr. Bonne visite. L\'équipe du Château Crabitan Bellevue. PS : Nous sommes au courant des problèmes techniques rencontrés, le déploiement de la version 2.10 du site arrive très bientôt. Merci pour votre patience.","en":"This new site has been designed to offer you better ergonomics (mobile version, tablet, day mode and night mode). It also offers better insight into our products and our field. This is a Beta version, if you have any concerns, please contact: service-info@crabitanbellevue.fr. Good visit. The Château Crabitan Bellevue team."}',
 NULL, NULL, 'refonte-site-internet-2020', '2020-08-24 12:00:00'),

('{"fr":"Période COVID","en":"COVID period"}',
 '{"fr":"Nous vous informons que la propriété reste ouverte et nous pouvons vous recevoir tout le mois de décembre dans le respect des règles sanitaires. Nous pouvons également préparer vos commandes de vin sous la forme du click and collect. Dans ce cas là, prévenez nous la veille, de votre horaire de retrait; par mail de préférence.","en":"We inform you that the property remains open and we can receive you all the month of December in compliance with sanitary rules. We can also prepare your wine orders in the form of click and collect. In this case, let us know the day before, your withdrawal schedule; preferably by email."}',
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
 '{"fr":"Vous pouvez dès à présent retrouver le millésime 2021 en blanc sec et rosé disponible en BIBS de 5 litres à la propriété, tout comme notre Bordeaux Rouge 2018. N\'hésitez pas à venir nous rendre visite.","en":"Vous pouvez dès à présent retrouver le millésime 2021 en blanc sec et rosé disponible en BIBS de 5 litres à la propriété, tout comme notre Bordeaux Rouge 2018."}',
 'News_51e1467136f224aa6e9d4d6507a4cbf3.png', NULL, 'bibs-rose-sec-2022', '2022-01-29 13:52:41'),

('{"fr":"Portes ouvertes Sainte Croix du Mont","en":"Portes ouvertes Sainte Croix du Mont"}',
 '{"fr":"A l\'occasion des portes ouvertes les 19 et 20 novembre prochain, vous aurez l\'occasion de déguster à la propriété un millésime de 40 ans AOC Sainte Croix du Mont 1982. Venez nombreux découvrir sa robe ambrée et ses saveurs délicates.","en":"A l\'occasion des portes ouvertes les 19 et 20 novembre prochain, vous aurez l\'occasion de déguster à la propriété un millésime de 40 ans AOC Sainte Croix du Mont 1982."}',
 NULL, NULL, 'portes-ouvertes-scm-novembre-2022', '2022-11-11 11:07:46'),

('{"fr":"Mise à jour du site","en":"Mise à jour du site"}',
 '{"fr":"Bonjour, dans le cadre d\'une montée de version technique de notre site, vous avez été susceptibles de rencontrer des problèmes techniques le 07/03/23. Ces problèmes sont dorénavant résorbés. Néanmoins, si vous rencontrez un nouveau problème technique, merci de nous le faire parvenir à crabitan.bellevue@orange.fr pour une résolution dans les 48h. L\'équipe technique","en":"Bonjour, dans le cadre d\'une montée de version technique de notre site, vous avez été susceptibles de rencontrer des problèmes techniques le 07/03/23. Ces problèmes sont dorénavant résorbés."}',
 NULL, NULL, 'mise-a-jour-technique-mars-2023', '2023-03-08 06:54:52'),

('{"fr":"Portes Ouvertes les 18 et 19 novembre 2023","en":"Portes Ouvertes les 18 et 19 novembre 2023"}',
 '{"fr":"Nos chais seront ouverts et à cette occasion nous vous ferons découvrir une association huîtres du bassin et Crabitan Bellevue blanc Doux. Un vieux millésime également à redéguster : le 2003. Venez tenter de gagner un Louis d\'or en visitant 3 châteaux de l\'appellation. Plus de renseignement sur saintecroixdumont.com. PS : Huîtres du producteur à nous réserver par Email avant vendredi 17/11/2023","en":"Nos chais seront ouverts et à cette occasion nous vous ferons découvrir une association huîtres du bassin et Crabitan Bellevue blanc Doux."}',
 'News_37713404fe3e34e6384add3ce72d503f.jpg', NULL, 'portes-ouvertes-18-19-novembre-2023', '2023-11-13 17:36:10'),

('{"fr":"Paiement CB","en":"Paiement CB"}',
 '{"fr":"Le paiement par carte bancaire est actuellement indisponible. Veuillez nous en excuser. Les paiements par chèque ou virement bancaire sont eux possibles. L\'équipe du Château Crabitan Bellevue.","en":"Le paiement par carte bancaire est actuellement indisponible. Veuillez nous en excuser. Les paiements par chèque ou virement bancaire sont eux possibles."}',
 NULL, NULL, 'paiement-cb-indisponible-2023', '2023-12-14 14:57:36'),

('{"fr":"Voeux 2024","en":"Voeux 2024"}',
 '{"fr":"Chers amateurs de vin, l\'équipe du Château Crabitan Bellevue vous adresse ses vœux les plus chaleureux pour la nouvelle année 2024. Que cette année vous apporte joie, prospérité et des moments délicieux partagés autour de nos vins d\'exception. Nous sommes reconnaissants pour votre fidélité continue. Nous sommes également ravis de vous annoncer le retour du paiement par carte bancaire. Santé et bonheur, l\'équipe du Château Crabitan Bellevue","en":"Dear wine lovers, the Château Crabitan Bellevue team sends you its warmest wishes for the new year 2024. We are also pleased to announce the return of payment by credit card. Health and happiness, the Château Crabitan Bellevue team"}',
 'News_142ac6d92c0c325c5a1508fe34ed3796.jpg', NULL, 'voeux-2024', '2024-01-16 07:13:58'),

('{"fr":"Mise à jour technique du site","en":"Technical update of the site"}',
 '{"fr":"Chers utilisateurs, nous tenons à vous informer qu\'une mise à jour majeure de notre infrastructure a été réalisée avec succès. Cette mise à jour vise à améliorer votre expérience utilisateur et à garantir la cybersécurité du site. Si vous rencontrez un problème technique, n\'hésitez pas à nous contacter. Notre équipe est prête à intervenir rapidement. Château Crabitan Bellevue","en":"Dear users, we would like to inform you that a major update of our infrastructure has been successfully completed. This update aims to improve your user experience and to guarantee the cybersecurity of the site. Château Crabitan Bellevue"}',
 NULL, NULL, 'mise-a-jour-technique-septembre-2024', '2024-09-08 19:43:00'),

('{"fr":"Portes Ouvertes 2025","en":"Portes Ouvertes 2025"}',
 '{"fr":"Venez nous rendre visite dans nos chais les 15 et 16 octobre prochains. À cette occasion, vous pourrez déguster un vieux millésime (1995) de Sainte Croix du Mont, sentir ses parfums complexes d\'évolution et apprécier son potentiel tout au long des années. À cette occasion et en allant visiter 3 chais un jeu concours vous permettra de gagner 3 balades en pinasse sur le Bassin d\'Arcachon.","en":"Venez nous rendre viste dans nos chais les 15 et 16 octobre prochains. À cette occasion vous pourrez déguster un vieux millésime (1995) de Sainte Croix du Mont."}',
 'News_c57afc261edbf7a4adde00d6c4ebe603.jpg', NULL, 'portes-ouvertes-2025', '2025-11-10 08:03:04');

-- Réinitialise l'AUTO_INCREMENT
ALTER TABLE `news` AUTO_INCREMENT = 15;
