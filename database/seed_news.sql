-- ============================================================
-- Seed : ~100 actualités de test — Château Crabitan Bellevue
-- Table : news  (title + text_content en JSON bilingue fr/en)
-- Usage : mysql -u <user> -p <db_name> < database/seed_news.sql
-- ============================================================

INSERT INTO `news`
    (`title`, `text_content`, `image_path`, `link_path`, `slug`, `created_at`)
VALUES

-- 2020
(
    '{"fr":"Millésime 2020 : fraîcheur et élégance","en":"Vintage 2020: freshness and elegance"}',
    '{"fr":"Le millésime 2020 restera marqué par un printemps précoce et un été aux nuits fraîches, conditions idéales pour préserver l\'acidité naturelle des baies. Nos Sémillons révèlent une grande finesse aromatique, avec des notes d\'agrumes confits et de miel d\'acacia. Les rouges, dominés par le Merlot, offrent des tanins soyeux et une belle longueur en bouche.","en":"Vintage 2020 will be remembered for an early spring and summer with cool nights, ideal conditions for preserving natural acidity. Our Sémillons reveal great aromatic finesse, with notes of candied citrus and acacia honey. The reds, dominated by Merlot, offer silky tannins and a beautiful finish."}',
    '/assets/images/proprietaire.jpeg',
    NULL,
    'millesime-2020-fraicheur-elegance',
    '2020-11-10 09:00:00'
),
(
    '{"fr":"Ouverture de notre nouveau chai de barriques","en":"Opening of our new barrel cellar"}',
    '{"fr":"Après deux ans de travaux, notre nouveau chai de barriques ouvre ses portes. D\'une capacité de 200 barriques en chêne français de 225 litres, il permettra à nos vins rouges et blancs secs d\'être élevés dans des conditions optimales de température et d\'hygrométrie. Une étape majeure dans l\'histoire du domaine.","en":"After two years of construction, our new barrel cellar opens its doors. With a capacity of 200 French oak barrels of 225 litres, it will allow our red and dry white wines to be aged in optimal conditions of temperature and humidity. A major milestone in the estate\'s history."}',
    '/assets/images/proprietaire.jpeg',
    'https://www.google.com/maps?q=Chateau+Crabitan+Bellevue,+Sainte-Croix-du-Mont',
    'ouverture-nouveau-chai-barriques',
    '2020-03-15 10:00:00'
),
(
    '{"fr":"Médaille d\'or au Concours Général Agricole 2020","en":"Gold medal at the 2020 Concours Général Agricole"}',
    '{"fr":"Notre Sainte-Croix-du-Mont blanc liquoreux 2018 a décroché la médaille d\'or au Concours Général Agricole de Paris 2020, parmi plus de 15 000 vins en compétition. Une récompense qui confirme l\'excellence de notre terroir et le soin apporté par notre équipe à chaque étape de la vinification.","en":"Our 2018 Sainte-Croix-du-Mont sweet white has won the gold medal at the 2020 Concours Général Agricole in Paris, among more than 15,000 wines in competition. A reward confirming the excellence of our terroir and the care our team puts into every step of winemaking."}',
    NULL,
    'https://www.google.com/search?q=Concours+G%C3%A9n%C3%A9ral+Agricole+2020',
    'medaille-or-concours-general-agricole-2020',
    '2020-05-02 11:00:00'
),
(
    '{"fr":"Première certification HVE niveau 3","en":"First HVE level 3 certification"}',
    '{"fr":"Nous sommes fiers d\'annoncer l\'obtention de la certification Haute Valeur Environnementale (HVE) niveau 3, la plus haute distinction du label. Cette certification récompense notre engagement de longue date en faveur de la biodiversité, de la gestion raisonnée des intrants et de la préservation des ressources en eau sur l\'ensemble du domaine.","en":"We are proud to announce the achievement of the Haute Valeur Environnementale (HVE) level 3 certification, the highest distinction of the label. This certification rewards our long-standing commitment to biodiversity, reasoned input management and water resource preservation across the estate."}',
    '/assets/images/proprietaire.jpeg',
    NULL,
    'certification-hve-niveau-3',
    '2020-07-20 09:30:00'
),
(
    '{"fr":"Vendanges 2020 : le récit d\'une récolte réussie","en":"Harvest 2020: the story of a successful crop"}',
    '{"fr":"Soixante vendangeurs ont foulé nos vignes pendant trois semaines pour cette récolte 2020. La sélection des raisins atteints par le Botrytis cinerea s\'est faite en cinq tries successives, garantissant une concentration exceptionnelle des moûts. Le rendement, volontairement limité, promet des vins de grande garde.","en":"Sixty harvesters worked our vines for three weeks during this 2020 harvest. Selection of grapes affected by Botrytis cinerea was carried out over five successive passes, guaranteeing exceptional must concentration. The deliberately limited yield promises wines of great ageing potential."}',
    '/assets/images/proprietaire.jpeg',
    NULL,
    'vendanges-2020-recit-recolte',
    '2020-10-05 08:00:00'
),

-- 2021
(
    '{"fr":"Millésime 2021 : la résilience du terroir","en":"Vintage 2021: the resilience of the terroir"}',
    '{"fr":"Malgré les gelées de printemps qui ont frappé le vignoble bordelais en avril 2021, nos parcelles de Sainte-Croix-du-Mont ont été épargnées grâce à leur position en coteau dominant la Garonne. Le millésime 2021 s\'annonce comme un vin de belle expression florale, avec une acidité vive et rafraîchissante.","en":"Despite the spring frosts that struck the Bordeaux vineyard in April 2021, our Sainte-Croix-du-Mont plots were spared thanks to their hillside position overlooking the Garonne. Vintage 2021 promises to be a wine of beautiful floral expression, with a lively and refreshing acidity."}',
    NULL,
    NULL,
    'millesime-2021-resilience-terroir',
    '2021-11-08 09:00:00'
),
(
    '{"fr":"Portes ouvertes vendanges — septembre 2021","en":"Harvest open days — September 2021"}',
    '{"fr":"Pour la première fois, le Château Crabitan Bellevue ouvre ses vignes au public durant les vendanges. Les visiteurs pourront participer à une trie manuelle des raisins botrytisés, découvrir le pressoir pneumatique et déguster les moûts directement en cuve. Inscriptions ouvertes sur notre site.","en":"For the first time, Château Crabitan Bellevue opens its vineyards to the public during harvest. Visitors can participate in manual selection of botrytised grapes, discover the pneumatic press and taste the musts directly from the vat. Registrations open on our website."}',
    '/assets/images/proprietaire.jpeg',
    'https://www.google.com/maps?q=Chateau+Crabitan+Bellevue,+Sainte-Croix-du-Mont',
    'portes-ouvertes-vendanges-septembre-2021',
    '2021-09-01 10:00:00'
),
(
    '{"fr":"Référencement Guide Hachette des Vins 2021","en":"Listed in the 2021 Guide Hachette des Vins"}',
    '{"fr":"Notre cuvée principale Sainte-Croix-du-Mont 2019 fait son entrée dans le Guide Hachette des Vins 2021 avec une étoile, soulignant son caractère authentique et son excellent rapport qualité-plaisir. Une reconnaissance nationale qui nous encourage à poursuivre notre travail de précision au vignoble comme au chai.","en":"Our main Sainte-Croix-du-Mont 2019 cuvée enters the 2021 Guide Hachette des Vins with one star, highlighting its authentic character and excellent quality-to-pleasure ratio. A national recognition that encourages us to continue our precision work both in the vineyard and cellar."}',
    NULL,
    'https://www.google.com/search?q=Guide+Hachette+des+Vins+2021',
    'guide-hachette-vins-2021',
    '2021-04-12 11:00:00'
),
(
    '{"fr":"Nouveau partenariat avec trois restaurants étoilés","en":"New partnership with three Michelin-starred restaurants"}',
    '{"fr":"Le Château Crabitan Bellevue a le plaisir d\'annoncer des accords de référencement avec trois restaurants étoilés de la région Nouvelle-Aquitaine. Nos blancs liquoreux figureront désormais sur leurs cartes des vins, proposés en accord avec les desserts et foies gras de leurs chefs. Une vitrine d\'exception pour notre appellation.","en":"Château Crabitan Bellevue is pleased to announce listing agreements with three Michelin-starred restaurants in the Nouvelle-Aquitaine region. Our sweet whites will now feature on their wine lists, offered as pairings with their chefs\' desserts and foie gras. An exceptional showcase for our appellation."}',
    NULL,
    NULL,
    'partenariat-restaurants-etoiles-2021',
    '2021-02-18 14:00:00'
),
(
    '{"fr":"Lancement de la cuvée Prestige 2019","en":"Launch of the 2019 Prestige cuvée"}',
    '{"fr":"Issue d\'une sélection parcellaire de nos meilleures vignes de plus de quarante ans, la cuvée Prestige 2019 est notre vin d\'exception. Vinifiée en barriques de chêne français neuves et élevée dix-huit mois, elle développe une complexité aromatique remarquable : fruits exotiques, safran, miel de châtaignier et une minéralité caractéristique du calcaire à huîtres fossiles.","en":"Sourced from a plot selection of our best vines over forty years old, the 2019 Prestige cuvée is our flagship wine. Vinified in new French oak barrels and aged for eighteen months, it develops remarkable aromatic complexity: exotic fruits, saffron, chestnut honey and a minerality characteristic of fossilised oyster limestone."}',
    '/assets/images/proprietaire.jpeg',
    NULL,
    'lancement-cuvee-prestige-2019',
    '2021-06-01 09:00:00'
),
(
    '{"fr":"Participation au salon Bordeaux Vinexpo 2021","en":"Participation in Bordeaux Vinexpo 2021"}',
    '{"fr":"L\'équipe du Château Crabitan Bellevue sera présente au salon Vinexpo Bordeaux du 18 au 20 mai 2021, stand F42, hall 3. Venez déguster nos derniers millésimes et rencontrer notre maître de chai. C\'est l\'occasion de découvrir en avant-première notre nouvelle cuvée rosé issue de Cabernet Franc, attendue pour l\'automne.","en":"The Château Crabitan Bellevue team will be present at the Vinexpo Bordeaux fair from 18 to 20 May 2021, stand F42, hall 3. Come and taste our latest vintages and meet our cellar master. It\'s the opportunity to preview our new rosé cuvée from Cabernet Franc, expected in autumn."}',
    NULL,
    'https://www.google.com/search?q=Vinexpo+Bordeaux+2021',
    'salon-vinexpo-bordeaux-2021',
    '2021-05-10 08:00:00'
),

-- 2022
(
    '{"fr":"Millésime 2022 : chaleur exceptionnelle, vins concentrés","en":"Vintage 2022: exceptional heat, concentrated wines"}',
    '{"fr":"L\'été caniculaire de 2022 a imposé une vigilance de chaque instant dans nos vignes. Grâce à une gestion rigoureuse du feuillage et des vendanges nocturnes pour préserver les arômes, nous avons obtenu des vins d\'une concentration remarquable. Les rouges 2022 s\'annoncent comme de très grandes gardes, riches en polyphénols.","en":"The scorching summer of 2022 required constant vigilance in our vineyards. Thanks to rigorous canopy management and night harvesting to preserve aromas, we achieved wines of remarkable concentration. The 2022 reds are set to be outstanding keepers, rich in polyphenols."}',
    '/assets/images/proprietaire.jpeg',
    NULL,
    'millesime-2022-chaleur-vins-concentres',
    '2022-11-15 09:00:00'
),
(
    '{"fr":"Journées Portes Ouvertes — mai 2022","en":"Open Days — May 2022"}',
    '{"fr":"Les 21 et 22 mai 2022, le Château Crabitan Bellevue vous accueille pour ses Journées Portes Ouvertes du printemps. Visite guidée du vignoble et du chai, dégustation de cinq millésimes en présence du vigneron, vente directe à tarif préférentiel. Entrée libre, parking disponible sur place.","en":"On 21 and 22 May 2022, Château Crabitan Bellevue welcomes you for its Spring Open Days. Guided tour of the vineyard and cellar, tasting of five vintages in the presence of the winemaker, direct sales at preferential rates. Free entry, parking available on site."}',
    '/assets/images/proprietaire.jpeg',
    'https://www.google.com/maps?q=Chateau+Crabitan+Bellevue,+Sainte-Croix-du-Mont',
    'journees-portes-ouvertes-mai-2022',
    '2022-05-05 10:00:00'
),
(
    '{"fr":"Médaille d\'argent — Decanter World Wine Awards 2022","en":"Silver medal — Decanter World Wine Awards 2022"}',
    '{"fr":"Notre cuvée Sainte-Croix-du-Mont 2020 a été récompensée d\'une médaille d\'argent aux Decanter World Wine Awards 2022, l\'un des concours de vins les plus prestigieux au monde avec plus de 18 000 vins évalués. Une reconnaissance internationale qui ouvre de nouvelles perspectives à l\'export, notamment vers le marché britannique.","en":"Our 2020 Sainte-Croix-du-Mont cuvée was awarded a silver medal at the Decanter World Wine Awards 2022, one of the world\'s most prestigious wine competitions with over 18,000 wines evaluated. An international recognition opening new export prospects, particularly towards the British market."}',
    NULL,
    'https://www.google.com/search?q=Decanter+World+Wine+Awards+2022',
    'medaille-argent-decanter-2022',
    '2022-07-04 11:00:00'
),
(
    '{"fr":"Installation d\'un système d\'irrigation de précision","en":"Installation of a precision irrigation system"}',
    '{"fr":"Dans le cadre de notre adaptation au changement climatique, nous avons investi dans un système d\'irrigation de précision par goutte-à-goutte sur nos parcelles les plus exposées. Ce dispositif, couplé à des sondes tensiométriques enterrées, nous permet d\'apporter exactement la quantité d\'eau nécessaire aux vignes sans gaspillage, en accord avec notre engagement HVE.","en":"As part of our climate adaptation strategy, we have invested in a precision drip irrigation system on our most exposed plots. This system, coupled with buried tensiometric probes, allows us to provide exactly the amount of water the vines need without waste, in line with our HVE commitment."}',
    NULL,
    NULL,
    'irrigation-precision-adaptation-climatique',
    '2022-04-20 09:00:00'
),
(
    '{"fr":"Notre rosé 2022 rejoint la gamme","en":"Our 2022 rosé joins the range"}',
    '{"fr":"Issu d\'un assemblage de Cabernet Franc et de Merlot vinifiés en saignée, notre premier rosé de domaine fait son entrée dans la gamme. Avec sa robe saumonée lumineuse et ses arômes de fraise des bois et de pêche blanche, il s\'inscrit parfaitement dans l\'esprit des vins de Bordeaux rosé, frais et gourmands.","en":"Made from a blend of Cabernet Franc and Merlot vinified by saignée, our first estate rosé joins the range. With its bright salmon hue and aromas of wild strawberry and white peach, it perfectly embodies the spirit of Bordeaux rosé wines, fresh and indulgent."}',
    '/assets/images/proprietaire.jpeg',
    NULL,
    'rose-2022-nouvelle-cuvee',
    '2022-08-30 10:00:00'
),
(
    '{"fr":"Fête de la Saint-Vincent — janvier 2022","en":"Saint-Vincent festival — January 2022"}',
    '{"fr":"Comme chaque année, le domaine a participé à la Fête de la Saint-Vincent, fête patronale des vignerons, en compagnie de nos voisins de l\'appellation. Une procession dans les vignes, une messe en plein air et un repas convivial entre producteurs ont marqué cette journée de tradition et de partage.","en":"As every year, the estate participated in the Saint-Vincent festival, the patron feast of winemakers, alongside our appellation neighbours. A procession through the vineyards, an open-air mass and a convivial meal among producers marked this day of tradition and sharing."}',
    NULL,
    NULL,
    'fete-saint-vincent-janvier-2022',
    '2022-01-22 09:00:00'
),
(
    '{"fr":"Exportation : premiers envois vers le Japon","en":"Export: first shipments to Japan"}',
    '{"fr":"Après plusieurs mois de négociations avec un importateur de Tokyo spécialisé dans les vins doux naturels et liquoreux, nos premiers cartons de Sainte-Croix-du-Mont ont traversé l\'océan. Le marché japonais, très amateur de vins sucrés de qualité, représente une opportunité formidable pour faire rayonner notre appellation à l\'international.","en":"After several months of negotiations with a Tokyo importer specialising in natural sweet and dessert wines, our first cases of Sainte-Croix-du-Mont have crossed the ocean. The Japanese market, very fond of quality sweet wines, represents a wonderful opportunity to showcase our appellation internationally."}',
    NULL,
    'https://www.google.com/search?q=export+vins+bordeaux+japon',
    'exportation-premiers-envois-japon',
    '2022-09-12 14:00:00'
),
(
    '{"fr":"Travaux de rénovation de la cave historique","en":"Renovation works on the historic cellar"}',
    '{"fr":"Notre cave du XIXe siècle, creusée dans le calcaire à huîtres fossiles, entre en phase de rénovation. Les voûtes ont été consolidées, le système d\'aération modernisé et l\'éclairage LED basse consommation installé. Cette cave demeurera le lieu de vieillissement de nos vins de garde, avec une température naturellement stable de 13 °C.","en":"Our 19th-century cellar, carved into fossilised oyster limestone, is entering a renovation phase. The vaults have been consolidated, the ventilation system modernised and low-consumption LED lighting installed. This cellar will remain the ageing place for our fine wines, with a naturally stable temperature of 13°C."}',
    '/assets/images/proprietaire.jpeg',
    NULL,
    'renovation-cave-historique-2022',
    '2022-02-28 10:00:00'
),
(
    '{"fr":"Recrutement : un nouveau chef de culture","en":"Recruitment: a new vineyard manager"}',
    '{"fr":"Le domaine accueille Pierre-Antoine Marty au poste de chef de culture. Fort de dix ans d\'expérience dans des domaines de la Gironde et du Lot-et-Garonne, Pierre-Antoine apportera son expertise en viticulture raisonnée et en gestion des sols vivants. Son arrivée renforce notre équipe dans la perspective d\'une conversion vers l\'agriculture biologique.","en":"The estate welcomes Pierre-Antoine Marty as vineyard manager. With ten years of experience on estates in the Gironde and Lot-et-Garonne, Pierre-Antoine brings his expertise in sustainable viticulture and living soil management. His arrival strengthens our team with a view to conversion to organic farming."}',
    NULL,
    NULL,
    'recrutement-nouveau-chef-de-culture',
    '2022-03-01 09:00:00'
),

-- 2023
(
    '{"fr":"Millésime 2023 : retour à l\'équilibre","en":"Vintage 2023: return to balance"}',
    '{"fr":"Après les excès de chaleur de 2022, le millésime 2023 renoue avec l\'équilibre classique des vins de Bordeaux. Les pluies de fin d\'été ont permis aux baies de parfaire leur maturité phénolique tout en conservant une acidité fraîche. Nos blancs liquoreux 2023 seront marqués par une grande délicatesse aromatique.","en":"After the heat excesses of 2022, vintage 2023 returns to the classic balance of Bordeaux wines. End-of-summer rains allowed the berries to perfect their phenolic maturity while retaining a fresh acidity. Our 2023 sweet whites will be marked by great aromatic delicacy."}',
    '/assets/images/proprietaire.jpeg',
    NULL,
    'millesime-2023-retour-equilibre',
    '2023-11-20 09:00:00'
),
(
    '{"fr":"Salon des Vins de Loire — nos vins à l\'honneur","en":"Salon des Vins de Loire — our wines in the spotlight"}',
    '{"fr":"Invités par les organisateurs du Salon des Vins de Loire à présenter les liquoreux de la rive droite bordelaise, nous avons réalisé une masterclass autour de quatre millésimes de notre Sainte-Croix-du-Mont devant cent professionnels. Une occasion rare de confronter notre terroir à d\'autres grandes appellations de vins moelleux.","en":"Invited by the organisers of the Salon des Vins de Loire to present the sweet wines of the Bordeaux right bank, we hosted a masterclass around four vintages of our Sainte-Croix-du-Mont before one hundred professionals. A rare occasion to compare our terroir with other great sweet wine appellications."}',
    NULL,
    'https://www.google.com/search?q=Salon+des+Vins+de+Loire+2023',
    'salon-vins-de-loire-masterclass-2023',
    '2023-02-05 11:00:00'
),
(
    '{"fr":"Obtention du label Terra Vitis","en":"Obtaining the Terra Vitis label"}',
    '{"fr":"Après trois ans de démarche, le Château Crabitan Bellevue obtient le label Terra Vitis, certification de viticulture durable reconnue au niveau européen. Ce label garantit à nos clients une viticulture respectueuse de l\'environnement, de la santé des viticulteurs et du tissu économique local, en complément de notre certification HVE niveau 3.","en":"After three years of process, Château Crabitan Bellevue obtains the Terra Vitis label, a European-recognised sustainable viticulture certification. This label guarantees our customers environmentally friendly viticulture, respecting the health of winegrowers and the local economic fabric, complementing our HVE level 3 certification."}',
    NULL,
    NULL,
    'label-terra-vitis-certification',
    '2023-04-14 10:00:00'
),
(
    '{"fr":"Nouveau site internet en ligne","en":"New website goes live"}',
    '{"fr":"Nous sommes heureux de vous présenter notre nouveau site internet, entièrement repensé pour offrir une expérience optimale sur tous les appareils. Vous y retrouverez l\'ensemble de notre gamme, notre histoire, notre terroir, et très bientôt une boutique en ligne pour commander directement vos vins préférés depuis chez vous.","en":"We are pleased to present our new website, completely redesigned to offer an optimal experience on all devices. You will find our full range, our history, our terroir, and very soon an online shop to order your favourite wines directly from home."}',
    '/assets/images/proprietaire.jpeg',
    'https://www.google.com/search?q=Chateau+Crabitan+Bellevue',
    'nouveau-site-internet-2023',
    '2023-06-01 09:00:00'
),
(
    '{"fr":"Visite d\'un groupe d\'œnotouristes canadiens","en":"Visit from a group of Canadian wine tourists"}',
    '{"fr":"Une vingtaine d\'amateurs de vins venus de Montréal et Vancouver ont séjourné une journée au domaine dans le cadre d\'un circuit œnotouristique organisé par un opérateur spécialisé. Dégustation verticale des millésimes 2015 à 2022, visite des vignes à cheval et dîner au chai : une immersion totale dans l\'univers de Crabitan Bellevue.","en":"Around twenty wine lovers from Montreal and Vancouver spent a day at the estate as part of a wine tourism circuit organised by a specialist operator. Vertical tasting of vintages 2015 to 2022, vineyard visit on horseback and dinner in the cellar: a total immersion in the world of Crabitan Bellevue."}',
    '/assets/images/proprietaire.jpeg',
    NULL,
    'visite-oenotouristes-canadiens-2023',
    '2023-07-22 14:00:00'
),
(
    '{"fr":"Partenariat avec l\'école hôtelière de Bordeaux","en":"Partnership with the Bordeaux hotel school"}',
    '{"fr":"Nous avons signé une convention de partenariat avec l\'école hôtelière de Bordeaux pour accueillir chaque année deux stagiaires en sommellerie. Ces étudiants participeront activement aux dégustations, à l\'organisation des visites et à la formation de notre personnel de vente. Un investissement dans les talents de demain.","en":"We have signed a partnership agreement with the Bordeaux hotel school to welcome two sommellerie interns each year. These students will actively participate in tastings, organisation of visits and training of our sales staff. An investment in tomorrow\'s talent."}',
    NULL,
    NULL,
    'partenariat-ecole-hoteliere-bordeaux',
    '2023-09-01 10:00:00'
),
(
    '{"fr":"Nos vins dans la presse spécialisée","en":"Our wines in the specialist press"}',
    '{"fr":"Le magazine Terre de Vins consacre un article de quatre pages au renouveau de l\'appellation Sainte-Croix-du-Mont, avec le Château Crabitan Bellevue en figure de proue. Le journaliste Julien Allard souligne la cohérence de notre gamme et l\'identité forte de notre terroir calcaire. L\'article est disponible en kiosque et sur notre site.","en":"The magazine Terre de Vins devotes a four-page article to the revival of the Sainte-Croix-du-Mont appellation, with Château Crabitan Bellevue as its figurehead. Journalist Julien Allard highlights the coherence of our range and the strong identity of our limestone terroir. The article is available at newsstands and on our website."}',
    NULL,
    'https://www.google.com/search?q=Terre+de+Vins+Sainte-Croix-du-Mont',
    'presse-terre-de-vins-article-2023',
    '2023-10-03 11:00:00'
),
(
    '{"fr":"Vendanges 2023 : quatre-vingt vendangeurs au domaine","en":"Harvest 2023: eighty harvesters at the estate"}',
    '{"fr":"C\'est avec une équipe de quatre-vingt vendangeurs, dont beaucoup sont fidèles depuis plusieurs années, que nous avons conduit les vendanges 2023. Le travail manuel de sélection des raisins botrytisés a nécessité six tries sur trois semaines. Une expérience humaine intense, reflet de notre engagement pour la qualité avant tout.","en":"It is with a team of eighty harvesters, many of whom have been loyal for several years, that we carried out the 2023 harvest. The manual selection work on botrytised grapes required six passes over three weeks. An intense human experience, reflecting our commitment to quality above all."}',
    '/assets/images/proprietaire.jpeg',
    NULL,
    'vendanges-2023-quatre-vingt-vendangeurs',
    '2023-10-25 08:00:00'
),
(
    '{"fr":"Inauguration de la salle de dégustation rénovée","en":"Inauguration of the renovated tasting room"}',
    '{"fr":"La salle de dégustation du château vient d\'être entièrement rénovée. Avec ses grandes baies vitrées surplombant le vignoble et la Garonne, son mobilier en chêne massif et son bar de dégustation sur mesure, elle offre un cadre exceptionnel pour accueillir nos visiteurs dans les meilleures conditions. Réservation en ligne disponible.","en":"The château\'s tasting room has just been completely renovated. With its large bay windows overlooking the vineyard and the Garonne, its solid oak furniture and bespoke tasting bar, it offers an exceptional setting to welcome our visitors in the best conditions. Online booking available."}',
    '/assets/images/proprietaire.jpeg',
    'https://www.google.com/maps?q=Chateau+Crabitan+Bellevue,+Sainte-Croix-du-Mont',
    'inauguration-salle-degustation-renovee',
    '2023-05-15 10:00:00'
),
(
    '{"fr":"Certification bio en cours — première étape franchie","en":"Organic certification underway — first step achieved"}',
    '{"fr":"Nous avons officiellement entamé notre conversion vers l\'agriculture biologique. La première année de conversion est validée : suppression totale des herbicides chimiques, introduction de couverts végétaux entre les rangs et traitement à base de cuivre et soufre uniquement. Rendez-vous en 2026 pour le premier millésime certifié AB.","en":"We have officially begun our conversion to organic farming. The first year of conversion has been validated: total elimination of chemical herbicides, introduction of cover crops between rows and treatment based solely on copper and sulphur. See you in 2026 for the first AB-certified vintage."}',
    NULL,
    NULL,
    'conversion-bio-premiere-etape',
    '2023-12-01 09:00:00'
),

-- 2024
(
    '{"fr":"Millésime 2024 : une vendange d\'exception","en":"Vintage 2024: an exceptional harvest"}',
    '{"fr":"Les conditions climatiques de l\'été 2024 ont offert au vignoble de Crabitan Bellevue un millésime remarquable. Alternance de chaleur sèche et de nuits fraîches, le Botrytis cinerea s\'est développé avec une régularité rare sur nos parcelles de Sémillon, promettant des blancs liquoreux d\'une grande concentration aromatique.","en":"The summer 2024 climate offered the Crabitan Bellevue vineyard a remarkable vintage. Alternating dry heat and cool nights, Botrytis cinerea developed with rare consistency across our Sémillon plots, promising sweet whites of exceptional aromatic concentration."}',
    '/assets/images/proprietaire.jpeg',
    NULL,
    'millesime-2024-vendange-exception',
    '2024-10-15 09:00:00'
),
(
    '{"fr":"Médaille d\'or — Challenge International du Vin 2024","en":"Gold medal — International Wine Challenge 2024"}',
    '{"fr":"Notre Sainte-Croix-du-Mont 2022 a remporté la médaille d\'or au Challenge International du Vin de Bordeaux 2024. Parmi les 5 400 vins présentés par 1 200 producteurs du monde entier, notre cuvée principale s\'est distinguée par son équilibre remarquable entre douceur, acidité et complexité aromatique.","en":"Our 2022 Sainte-Croix-du-Mont won the gold medal at the 2024 Bordeaux International Wine Challenge. Among 5,400 wines presented by 1,200 producers from around the world, our main cuvée stood out for its remarkable balance between sweetness, acidity and aromatic complexity."}',
    NULL,
    'https://www.google.com/search?q=Challenge+International+du+Vin+Bordeaux+2024',
    'medaille-or-challenge-international-vin-2024',
    '2024-04-28 11:00:00'
),
(
    '{"fr":"Dîner de prestige au château — collection verticale","en":"Prestige dinner at the château — vertical collection"}',
    '{"fr":"Le 15 juin 2024, le Château Crabitan Bellevue a organisé un dîner de prestige pour vingt collectionneurs et amateurs éclairés, autour d\'une verticale exceptionnelle de la cuvée Prestige sur les millésimes 2010, 2013, 2016, 2018 et 2019. Un voyage dans le temps au cœur du calcaire fossile de Sainte-Croix-du-Mont.","en":"On 15 June 2024, Château Crabitan Bellevue organised a prestige dinner for twenty collectors and discerning enthusiasts, around an exceptional vertical tasting of the Prestige cuvée across vintages 2010, 2013, 2016, 2018 and 2019. A journey through time at the heart of the fossil limestone of Sainte-Croix-du-Mont."}',
    '/assets/images/proprietaire.jpeg',
    NULL,
    'diner-prestige-verticale-collection-2024',
    '2024-06-16 10:00:00'
),
(
    '{"fr":"Portes ouvertes printemps 2024","en":"Spring open days 2024"}',
    '{"fr":"Les 18 et 19 mai 2024, venez nous rendre visite lors de nos journées portes ouvertes de printemps. Au programme : visite guidée du vignoble et du chai, dégustation de six vins dont deux inédits, marché de producteurs locaux et initiation à l\'art de la dégustation pour les enfants. Entrée libre, parking gratuit.","en":"On 18 and 19 May 2024, come and visit us during our spring open days. On the programme: guided tour of the vineyard and cellar, tasting of six wines including two new releases, local producers\' market and wine tasting initiation for children. Free entry, free parking."}',
    '/assets/images/proprietaire.jpeg',
    'https://www.google.com/maps?q=Chateau+Crabitan+Bellevue,+Sainte-Croix-du-Mont',
    'portes-ouvertes-printemps-2024',
    '2024-05-01 10:00:00'
),
(
    '{"fr":"Installation de ruches au domaine","en":"Installation of beehives at the estate"}',
    '{"fr":"Dans le cadre de notre démarche agroécologique, huit ruches ont été installées en lisière de nos parcelles. Les abeilles joueront un rôle essentiel dans la pollinisation des couverts végétaux et la biodiversité des haies. Le miel produit sera proposé à la vente directe lors de vos visites au domaine.","en":"As part of our agroecological approach, eight beehives have been installed at the edge of our plots. The bees will play an essential role in pollinating cover crops and hedgerow biodiversity. The honey produced will be available for direct purchase during your estate visits."}',
    NULL,
    NULL,
    'installation-ruches-domaine-2024',
    '2024-03-20 09:00:00'
),
(
    '{"fr":"Nos vins à Hong Kong — salon ProWine Asia","en":"Our wines in Hong Kong — ProWine Asia fair"}',
    '{"fr":"Pour la première fois, le Château Crabitan Bellevue participait au salon ProWine Asia à Hong Kong en mars 2024. Les liquoreux de Sainte-Croix-du-Mont ont suscité un vif intérêt auprès des acheteurs asiatiques, confirmant l\'attrait croissant de cette région pour les grands vins doux français. Des accords d\'importation sont en cours de finalisation avec deux distributeurs.","en":"For the first time, Château Crabitan Bellevue participated in the ProWine Asia fair in Hong Kong in March 2024. The Sainte-Croix-du-Mont sweet wines attracted keen interest from Asian buyers, confirming the growing attraction of this region for great French sweet wines. Import agreements are being finalised with two distributors."}',
    NULL,
    'https://www.google.com/search?q=ProWine+Asia+Hong+Kong+2024',
    'prowine-asia-hong-kong-2024',
    '2024-03-10 11:00:00'
),
(
    '{"fr":"Lancement de la cuvée Blanc Sec 2023","en":"Launch of the 2023 Dry White cuvée"}',
    '{"fr":"Après le succès de notre rosé 2022, nous enrichissons notre gamme avec un Bordeaux blanc sec 2023 issu de Sauvignon Blanc et Sémillon. Vinifié à basse température pour préserver la fraîcheur aromatique, il développe des notes de buis, de pamplemousse et de fleurs blanches. Idéal en apéritif ou en accompagnement de poissons et fruits de mer.","en":"Following the success of our 2022 rosé, we enrich our range with a 2023 dry white Bordeaux from Sauvignon Blanc and Sémillon. Vinified at low temperature to preserve aromatic freshness, it develops notes of boxwood, grapefruit and white flowers. Ideal as an aperitif or with fish and seafood."}',
    '/assets/images/proprietaire.jpeg',
    NULL,
    'cuvee-blanc-sec-2023-lancement',
    '2024-02-15 10:00:00'
),
(
    '{"fr":"Foire aux Vins de Bordeaux — automne 2024","en":"Bordeaux Wine Fair — autumn 2024"}',
    '{"fr":"Retrouvez le Château Crabitan Bellevue à la Foire aux Vins de Bordeaux, du 4 au 8 octobre 2024, place des Quinconces. Nous proposerons une sélection de nos millésimes à des tarifs exclusifs réservés aux visiteurs du salon, ainsi qu\'une animation dégustation chaque après-midi à 15 h 30 autour des vins liquoreux de Sainte-Croix-du-Mont.","en":"Find Château Crabitan Bellevue at the Bordeaux Wine Fair, from 4 to 8 October 2024, place des Quinconces. We will offer a selection of our vintages at exclusive rates for fair visitors, as well as a tasting event each afternoon at 3:30 pm around the sweet wines of Sainte-Croix-du-Mont."}',
    NULL,
    'https://www.google.com/search?q=Foire+aux+Vins+de+Bordeaux+2024',
    'foire-aux-vins-bordeaux-automne-2024',
    '2024-09-20 10:00:00'
),
(
    '{"fr":"Rénovation du gîte du domaine","en":"Renovation of the estate gîte"}',
    '{"fr":"Le gîte du château, situé dans l\'ancienne maison du régisseur, a été entièrement rénové. Pouvant accueillir jusqu\'à huit personnes, il dispose désormais d\'une cuisine moderne, d\'une terrasse avec vue sur les vignes et d\'un accès privatif à la salle de dégustation. Réservation disponible à la semaine d\'avril à octobre.","en":"The château\'s gîte, located in the former estate manager\'s house, has been fully renovated. Accommodating up to eight people, it now has a modern kitchen, a terrace with vineyard views and private access to the tasting room. Weekly bookings available from April to October."}',
    '/assets/images/proprietaire.jpeg',
    'https://www.google.com/maps?q=Chateau+Crabitan+Bellevue,+Sainte-Croix-du-Mont',
    'renovation-gite-domaine-2024',
    '2024-01-15 10:00:00'
),
(
    '{"fr":"Tournage d\'un documentaire sur l\'appellation","en":"Filming of a documentary on the appellation"}',
    '{"fr":"Une équipe de France Télévisions a séjourné trois jours au domaine pour le tournage d\'un documentaire consacré à l\'appellation Sainte-Croix-du-Mont, diffusé sur France 3 Nouvelle-Aquitaine en décembre 2024. La vie du vignoble, les vendanges et la passion de la famille Solane sont au cœur de ce portrait de 52 minutes.","en":"A France Télévisions team spent three days at the estate filming a documentary dedicated to the Sainte-Croix-du-Mont appellation, broadcast on France 3 Nouvelle-Aquitaine in December 2024. The life of the vineyard, the harvest and the passion of the Solane family are at the heart of this 52-minute portrait."}',
    '/assets/images/proprietaire.jpeg',
    'https://www.google.com/search?q=Sainte-Croix-du-Mont+documentaire+France+3',
    'documentaire-france-3-appellation-2024',
    '2024-08-05 11:00:00'
),

-- 2025
(
    '{"fr":"Ouverture de la boutique en ligne — printemps 2025","en":"Online shop opening — spring 2025"}',
    '{"fr":"Nous avons le plaisir de vous annoncer l\'ouverture de notre boutique en ligne. Commandez directement depuis le domaine nos vins de l\'appellation Sainte-Croix-du-Mont, nos Bordeaux rouges et nos blancs secs. Livraison sécurisée en France métropolitaine et en Europe. Retrouvez également notre collection prestige en édition limitée.","en":"We are pleased to announce the opening of our online shop. Order directly from the estate our Sainte-Croix-du-Mont appellation wines, our red Bordeaux and dry whites. Secure delivery across mainland France and Europe. Our limited-edition prestige collection is also available."}',
    '/assets/images/proprietaire.jpeg',
    NULL,
    'ouverture-boutique-en-ligne-2025',
    '2025-03-01 10:00:00'
),
(
    '{"fr":"Médaille de platine — Millésime Bio 2025","en":"Platinum medal — Millésime Bio 2025"}',
    '{"fr":"Notre Sainte-Croix-du-Mont 2022, premier millésime élaboré en conversion biologique, remporte la médaille de platine au salon Millésime Bio 2025 de Montpellier. Une reconnaissance exceptionnelle qui valide notre démarche de transition agroécologique et nous encourage à poursuivre nos efforts vers la certification AB complète en 2026.","en":"Our 2022 Sainte-Croix-du-Mont, the first vintage made during organic conversion, wins the platinum medal at the Millésime Bio 2025 fair in Montpellier. An exceptional recognition validating our agroecological transition approach and encouraging us to continue our efforts towards full AB certification in 2026."}',
    NULL,
    'https://www.google.com/search?q=Millesime+Bio+Montpellier+2025',
    'medaille-platine-millesime-bio-2025',
    '2025-01-28 11:00:00'
),
(
    '{"fr":"Journées Portes Ouvertes — juin 2025","en":"Open Days — June 2025"}',
    '{"fr":"Le Château Crabitan Bellevue vous invite à ses Journées Portes Ouvertes les 14 et 15 juin 2025. Venez découvrir notre chai, parcourir les vignes avec notre maître de chai et déguster en avant-première les derniers millésimes. Entrée libre, inscription conseillée. Une occasion unique de rencontrer la famille Solane sur son domaine d\'exception.","en":"Château Crabitan Bellevue invites you to its Open Days on 14 and 15 June 2025. Come and discover our cellar, walk the vineyards with our cellar master and taste the latest vintages in preview. Free entry, registration recommended. A unique opportunity to meet the Solane family on their exceptional estate."}',
    '/assets/images/proprietaire.jpeg',
    'https://www.google.com/maps?q=Chateau+Crabitan+Bellevue,+Sainte-Croix-du-Mont',
    'journees-portes-ouvertes-juin-2025',
    '2025-06-01 08:00:00'
),
(
    '{"fr":"Nouveau partenariat avec Relais & Châteaux","en":"New partnership with Relais & Châteaux"}',
    '{"fr":"Nos vins intègrent désormais la sélection officielle de huit propriétés Relais & Châteaux de la région Sud-Ouest. Nos blancs liquoreux de Sainte-Croix-du-Mont y seront proposés en accord mets et vins avec les menus gastronomiques de chefs étoilés. Une mise en lumière exceptionnelle pour notre domaine auprès d\'une clientèle internationale exigeante.","en":"Our wines are now part of the official selection of eight Relais & Châteaux properties in the South-West region. Our Sainte-Croix-du-Mont sweet whites will be offered as food and wine pairings with the gastronomic menus of Michelin-starred chefs. Exceptional exposure for our estate among a demanding international clientele."}',
    NULL,
    'https://www.google.com/search?q=Relais+%26+Chateaux+vins+Bordeaux',
    'partenariat-relais-chateaux-2025',
    '2025-02-10 10:00:00'
),
(
    '{"fr":"Lancement du programme de visites œnotouristiques","en":"Launch of the wine tourism visit programme"}',
    '{"fr":"Dès le 1er avril 2025, le domaine propose un programme structuré de visites œnotouristiques : visite classique (1 h, dégustation de 3 vins), visite prestige (2 h, dégustation de 6 millésimes dont la cuvée Prestige), et expérience vendanges en septembre-octobre. Réservation en ligne ou par téléphone, groupes sur devis.","en":"From 1 April 2025, the estate offers a structured wine tourism visit programme: classic visit (1 hour, tasting of 3 wines), prestige visit (2 hours, tasting of 6 vintages including the Prestige cuvée), and harvest experience in September-October. Online or telephone booking, groups on request."}',
    '/assets/images/proprietaire.jpeg',
    'https://www.google.com/maps?q=Chateau+Crabitan+Bellevue,+Sainte-Croix-du-Mont',
    'programme-oenotourisme-2025',
    '2025-03-20 09:00:00'
),
(
    '{"fr":"Notre cuvée Prestige 2021 en avant-première","en":"Our 2021 Prestige cuvée in preview"}',
    '{"fr":"Après vingt-quatre mois de barrique et douze mois de bouteille, la cuvée Prestige 2021 est prête à être révélée. Lors d\'une soirée privée organisée le 28 février 2025, une centaine d\'ambassadeurs du domaine ont pu découvrir ce vin d\'exception avant sa commercialisation officielle prévue pour avril 2025. Les premières impressions sont enthousiastes.","en":"After twenty-four months in barrel and twelve months in bottle, the 2021 Prestige cuvée is ready to be unveiled. During a private evening held on 28 February 2025, one hundred estate ambassadors were able to discover this exceptional wine before its official release scheduled for April 2025. First impressions are enthusiastic."}',
    '/assets/images/proprietaire.jpeg',
    NULL,
    'cuvee-prestige-2021-avant-premiere',
    '2025-03-01 08:00:00'
),
(
    '{"fr":"Accord de distribution aux États-Unis","en":"Distribution agreement in the United States"}',
    '{"fr":"Un accord de distribution exclusif vient d\'être signé avec l\'importateur new-yorkais Fine Wines of Bordeaux LLC, spécialisé dans les vins liquoreux et de dessert. Nos vins seront distribués dans les États de New York, Californie, Floride et Illinois. Les premières livraisons sont prévues pour l\'automne 2025.","en":"An exclusive distribution agreement has just been signed with New York importer Fine Wines of Bordeaux LLC, specialising in sweet and dessert wines. Our wines will be distributed in the states of New York, California, Florida and Illinois. First deliveries are planned for autumn 2025."}',
    NULL,
    'https://www.google.com/search?q=import+vins+bordeaux+etats-unis',
    'distribution-etats-unis-accord-2025',
    '2025-02-25 11:00:00'
),
(
    '{"fr":"Reprise des apéros au chai — tous les vendredis","en":"Cellar aperitifs resume — every Friday"}',
    '{"fr":"De mai à octobre 2025, retrouvez-nous chaque vendredi soir pour nos apéros au chai, devenus un rendez-vous incontournable de l\'été en Sainte-Croix-du-Mont. Dégustation de trois vins du domaine, plateau de fromages et charcuteries locales, vue panoramique sur la Garonne. Sur réservation, 25 € par personne, 18 h 30 à 21 h 00.","en":"From May to October 2025, join us every Friday evening for our cellar aperitifs, which have become a must-attend summer event in Sainte-Croix-du-Mont. Tasting of three estate wines, local cheese and charcuterie board, panoramic view over the Garonne. By reservation, €25 per person, 6:30 pm to 9:00 pm."}',
    '/assets/images/proprietaire.jpeg',
    'https://www.google.com/maps?q=Chateau+Crabitan+Bellevue,+Sainte-Croix-du-Mont',
    'aperos-au-chai-vendredis-2025',
    '2025-04-28 10:00:00'
),
(
    '{"fr":"Printemps des Vins de Blaye et Bourg 2025","en":"Printemps des Vins de Blaye et Bourg 2025"}',
    '{"fr":"Le domaine participera en tant qu\'invité d\'honneur au Printemps des Vins de Blaye et Bourg 2025, manifestation qui réunit chaque année plusieurs centaines de vignerons de la rive droite bordelaise. Nous y présenterons l\'ensemble de notre gamme et animerons une conférence sur l\'appellation Sainte-Croix-du-Mont et son potentiel de vieillissement.","en":"The estate will participate as guest of honour at the Printemps des Vins de Blaye et Bourg 2025, an event that brings together several hundred right-bank Bordeaux winemakers each year. We will present our full range and host a conference on the Sainte-Croix-du-Mont appellation and its ageing potential."}',
    NULL,
    'https://www.google.com/search?q=Printemps+des+Vins+de+Blaye+Bourg+2025',
    'printemps-vins-blaye-bourg-2025',
    '2025-05-12 09:00:00'
),
(
    '{"fr":"Interview du propriétaire dans Vitisphère","en":"Owner interview in Vitisphère"}',
    '{"fr":"Bernard Solane, propriétaire du Château Crabitan Bellevue, répond aux questions de la rédaction de Vitisphère dans une interview en ligne consacrée à l\'avenir des appellations de vins doux en Gironde. Il y aborde la conversion bio, le développement à l\'export, l\'œnotourisme et la transmission familiale du domaine à la troisième génération.","en":"Bernard Solane, owner of Château Crabitan Bellevue, answers questions from the Vitisphère editorial team in an online interview devoted to the future of sweet wine appellations in the Gironde. He discusses organic conversion, export development, wine tourism and the family handover of the estate to the third generation."}',
    '/assets/images/proprietaire.jpeg',
    'https://www.google.com/search?q=Vitisph%C3%A8re+Crabitan+Bellevue',
    'interview-proprietaire-vitisphere-2025',
    '2025-01-15 11:00:00'
),

-- Entrées supplémentaires pour compléter la centaine
(
    '{"fr":"Plantation de cépages résistants — expérimentation","en":"Planting of resistant grape varieties — experiment"}',
    '{"fr":"Dans le cadre du plan d\'adaptation au changement climatique, nous expérimentons la plantation de nouvelles variétés résistantes aux maladies (PIWI) sur une parcelle de deux hectares. Floréal, Voltis et Muscaris sont testés aux côtés de nos cépages traditionnels. Les premiers résultats seront évalués lors de la vendange 2026.","en":"As part of the climate adaptation plan, we are experimenting with the planting of new disease-resistant varieties (PIWI) on a two-hectare plot. Floréal, Voltis and Muscaris are being tested alongside our traditional varieties. First results will be evaluated during the 2026 harvest."}',
    NULL,
    NULL,
    'plantation-cepages-resistants-piwi',
    '2023-03-10 09:00:00'
),
(
    '{"fr":"Nuit des étoiles au domaine — août 2023","en":"Night of the Stars at the estate — August 2023"}',
    '{"fr":"Le 12 août 2023, à l\'occasion des étoiles filantes des Perséides, le domaine a organisé une soirée d\'observation astronomique dans ses vignes. Une centaine de participants ont profité d\'un ciel dégagé exceptionnel pour observer les étoiles, accompagnés d\'un verre de notre cuvée Prestige. Un événement qui sera reconduit chaque été.","en":"On 12 August 2023, on the occasion of the Perseid shooting stars, the estate organised a stargazing evening in its vineyards. One hundred participants enjoyed an exceptionally clear sky to observe the stars, accompanied by a glass of our Prestige cuvée. An event to be held every summer."}',
    '/assets/images/proprietaire.jpeg',
    NULL,
    'nuit-etoiles-domaine-aout-2023',
    '2023-08-13 09:00:00'
),
(
    '{"fr":"Nos vins référencés chez Lavinia Paris","en":"Our wines listed at Lavinia Paris"}',
    '{"fr":"La cave spécialisée Lavinia, référence incontournable sur la place de Paris, intègre trois de nos vins à sa sélection permanente : notre Sainte-Croix-du-Mont 2021, notre cuvée Prestige 2019 et notre Bordeaux rouge 2020. Un référencement parisien qui renforce notre visibilité auprès des amateurs de vins du monde entier.","en":"The specialist wine shop Lavinia, an unmissable reference in Paris, adds three of our wines to its permanent selection: our 2021 Sainte-Croix-du-Mont, our 2019 Prestige cuvée and our 2020 red Bordeaux. A Parisian listing strengthening our visibility among wine lovers from around the world."}',
    NULL,
    'https://www.google.com/search?q=Lavinia+Paris+vins+bordeaux',
    'vins-references-lavinia-paris',
    '2022-11-05 10:00:00'
),
(
    '{"fr":"Atelier accord mets et vins — automne 2022","en":"Food and wine pairing workshop — autumn 2022"}',
    '{"fr":"En partenariat avec un chef cuisinier local, le domaine propose un atelier mensuel d\'accord mets et vins chaque dernier samedi du mois, d\'octobre à décembre. Les participants découvrent comment marier nos blancs liquoreux avec des préparations sucrées-salées, des fromages affinés et des desserts de saison. Places limitées à douze personnes.","en":"In partnership with a local chef, the estate offers a monthly food and wine pairing workshop every last Saturday of the month, from October to December. Participants discover how to pair our sweet whites with sweet-savoury preparations, aged cheeses and seasonal desserts. Places limited to twelve people."}',
    '/assets/images/proprietaire.jpeg',
    NULL,
    'atelier-accord-mets-vins-automne-2022',
    '2022-10-01 10:00:00'
),
(
    '{"fr":"Présentation au Club des Sommeliers de Bordeaux","en":"Presentation to the Bordeaux Sommeliers Club"}',
    '{"fr":"À l\'invitation du Club des Sommeliers de Bordeaux, nous avons présenté une verticale de six millésimes de notre cuvée Prestige devant une vingtaine de professionnels. L\'occasion de partager notre philosophie de vinification et d\'échanger sur les tendances de consommation des vins liquoreux en France et à l\'international.","en":"At the invitation of the Bordeaux Sommeliers Club, we presented a vertical of six vintages of our Prestige cuvée to twenty professionals. The occasion to share our winemaking philosophy and discuss trends in sweet wine consumption in France and internationally."}',
    NULL,
    NULL,
    'club-sommeliers-bordeaux-presentation',
    '2021-11-15 14:00:00'
),
(
    '{"fr":"Renovation de la façade du château","en":"Renovation of the château façade"}',
    '{"fr":"La façade du château, datant du début du XXe siècle, a fait l\'objet d\'une rénovation complète en 2020. Les pierres de taille ont été rejointoyées, les volets en bois restaurés à l\'identique et la toiture d\'ardoise remplacée. Le château retrouve tout son cachet d\'antan tout en répondant aux normes d\'isolation thermique actuelles.","en":"The château\'s façade, dating from the early 20th century, underwent a complete renovation in 2020. The cut stones have been repointed, the wooden shutters restored identically and the slate roof replaced. The château regains all its former charm while meeting current thermal insulation standards."}',
    '/assets/images/proprietaire.jpeg',
    NULL,
    'renovation-facade-chateau-2020',
    '2020-12-10 10:00:00'
),
(
    '{"fr":"Participation au Wine Paris & Vinexpo Paris 2023","en":"Participation in Wine Paris & Vinexpo Paris 2023"}',
    '{"fr":"Pour la première fois, le Château Crabitan Bellevue participait au salon Wine Paris & Vinexpo Paris au Parc des Expositions de la Porte de Versailles. Deux jours de rencontres intenses avec des importateurs, distributeurs et journalistes venus du monde entier. Plusieurs accords commerciaux ont été initiés lors de cet événement.","en":"For the first time, Château Crabitan Bellevue participated in the Wine Paris & Vinexpo Paris fair at the Parc des Expositions de la Porte de Versailles. Two days of intense meetings with importers, distributors and journalists from around the world. Several commercial agreements were initiated during this event."}',
    NULL,
    'https://www.google.com/search?q=Wine+Paris+Vinexpo+Paris+2023',
    'wine-paris-vinexpo-2023',
    '2023-02-15 10:00:00'
),
(
    '{"fr":"Notre domaine sur les réseaux sociaux","en":"Our estate on social media"}',
    '{"fr":"Le Château Crabitan Bellevue est désormais présent sur Instagram, Facebook et LinkedIn. Suivez-nous pour découvrir les coulisses du domaine, les dernières actualités des millésimes et des invitations exclusives à nos événements. Notre compte Instagram compte déjà plus de 2 500 abonnés passionnés de vins de Bordeaux.","en":"Château Crabitan Bellevue is now present on Instagram, Facebook and LinkedIn. Follow us to discover behind-the-scenes at the estate, the latest vintage news and exclusive invitations to our events. Our Instagram account already has more than 2,500 followers passionate about Bordeaux wines."}',
    '/assets/images/proprietaire.jpeg',
    NULL,
    'reseaux-sociaux-domaine-2021',
    '2021-01-20 10:00:00'
),
(
    '{"fr":"Fin de vendanges 2022 — bilan positif","en":"End of harvest 2022 — positive assessment"}',
    '{"fr":"Les vendanges 2022 se sont achevées le 3 octobre après cinq semaines d\'une récolte intense. Malgré la sécheresse estivale, la qualité des moûts est au rendez-vous grâce à une gestion rigoureuse de l\'enherbement et des vendanges en vert réalisées en juillet. Les premiers assemblages confirment un millésime d\'exception, particulièrement pour les rouges.","en":"Harvest 2022 ended on 3 October after five weeks of an intense harvest. Despite the summer drought, the quality of the musts is there thanks to rigorous cover crop management and green harvesting carried out in July. First blends confirm an exceptional vintage, particularly for the reds."}',
    NULL,
    NULL,
    'fin-vendanges-2022-bilan',
    '2022-10-05 08:00:00'
),
(
    '{"fr":"Concours des Grands Vins de Bordeaux 2021 — deux médailles","en":"Concours des Grands Vins de Bordeaux 2021 — two medals"}',
    '{"fr":"Le Château Crabitan Bellevue repart du Concours des Grands Vins de Bordeaux avec deux récompenses : une médaille d\'argent pour notre Sainte-Croix-du-Mont 2019 et une médaille de bronze pour notre Bordeaux rouge 2018. Un palmarès qui témoigne de la constance de notre travail sur l\'ensemble de la gamme.","en":"Château Crabitan Bellevue returns from the Concours des Grands Vins de Bordeaux with two awards: a silver medal for our 2019 Sainte-Croix-du-Mont and a bronze medal for our 2018 red Bordeaux. A record reflecting the consistency of our work across the entire range."}',
    NULL,
    'https://www.google.com/search?q=Concours+Grands+Vins+de+Bordeaux+2021',
    'concours-grands-vins-bordeaux-2021-medailles',
    '2021-07-05 11:00:00'
),
(
    '{"fr":"Inauguration du sentier pédestre dans les vignes","en":"Inauguration of the walking trail through the vineyards"}',
    '{"fr":"Un sentier pédestre balisé de 3,5 km a été aménagé à travers les parcelles du domaine, offrant aux visiteurs une immersion dans le vignoble de Sainte-Croix-du-Mont. Des panneaux explicatifs jalonnent le parcours, présentant les cépages, les pratiques culturales et l\'histoire géologique du calcaire à huîtres fossiles. Libre d\'accès de 9 h à 18 h.","en":"A 3.5 km marked walking trail has been developed through the estate\'s plots, offering visitors an immersion in the Sainte-Croix-du-Mont vineyard. Explanatory panels mark the route, presenting the grape varieties, cultivation practices and geological history of the fossilised oyster limestone. Free access from 9am to 6pm."}',
    '/assets/images/proprietaire.jpeg',
    'https://www.google.com/maps?q=Chateau+Crabitan+Bellevue,+Sainte-Croix-du-Mont',
    'sentier-pedestre-vignes-inauguration',
    '2022-06-15 10:00:00'
),
(
    '{"fr":"Participation au Bordeaux Wine Festival 2022","en":"Participation in the Bordeaux Wine Festival 2022"}',
    '{"fr":"Le Château Crabitan Bellevue était présent au Bordeaux Wine Festival (Fête le Vin) sur les quais de la Garonne du 23 au 26 juin 2022. Quatre jours de rencontres avec des milliers d\'amateurs de vins du monde entier, l\'occasion de faire découvrir nos liquoreux à un public novice et de tisser des liens avec des professionnels internationaux.","en":"Château Crabitan Bellevue was present at the Bordeaux Wine Festival (Fête le Vin) on the Garonne quays from 23 to 26 June 2022. Four days of encounters with thousands of wine lovers from around the world, the opportunity to introduce our sweet wines to a novice audience and forge links with international professionals."}',
    NULL,
    'https://www.google.com/search?q=Bordeaux+Wine+Festival+2022+Fete+le+Vin',
    'bordeaux-wine-festival-2022',
    '2022-06-28 10:00:00'
),
(
    '{"fr":"Départ à la retraite de notre maître de chai","en":"Retirement of our cellar master"}',
    '{"fr":"Après trente-deux ans au service du Château Crabitan Bellevue, Michel Dupeyrat prend une retraite bien méritée. Arrivé en 1992, il a façonné l\'identité gustative de nos vins avec un talent et une rigueur exemplaires. Son successeur, Théodore Lassus, formé à ses côtés depuis cinq ans, prend la relève avec les mêmes valeurs d\'excellence.","en":"After thirty-two years serving Château Crabitan Bellevue, Michel Dupeyrat is taking a well-deserved retirement. Having joined in 1992, he shaped the taste identity of our wines with exemplary talent and rigour. His successor, Théodore Lassus, trained alongside him for five years, takes over with the same values of excellence."}',
    '/assets/images/proprietaire.jpeg',
    NULL,
    'depart-retraite-maitre-de-chai',
    '2024-01-31 14:00:00'
),
(
    '{"fr":"Nouvelle cuvée : Bordeaux Supérieur rouge 2022","en":"New cuvée: Bordeaux Supérieur red 2022"}',
    '{"fr":"Fort du succès de notre Bordeaux rouge, nous lançons une nouvelle cuvée Bordeaux Supérieur 2022, issue de notre sélection de vieilles vignes de Merlot et de Cabernet Sauvignon de plus de trente ans. Élevé douze mois en barriques de chêne français, ce vin offre une complexité accrue et un potentiel de garde de dix à quinze ans.","en":"Building on the success of our red Bordeaux, we launch a new 2022 Bordeaux Supérieur cuvée, from our selection of old Merlot and Cabernet Sauvignon vines over thirty years old. Aged twelve months in French oak barrels, this wine offers increased complexity and an ageing potential of ten to fifteen years."}',
    '/assets/images/proprietaire.jpeg',
    NULL,
    'bordeaux-superieur-rouge-2022-nouvelle-cuvee',
    '2024-07-10 10:00:00'
),
(
    '{"fr":"Fête de la Musique au domaine — juin 2024","en":"Music Festival at the estate — June 2024"}',
    '{"fr":"Pour la Fête de la Musique 2024, le domaine a organisé une soirée jazz et vins dans les vignes. Deux groupes locaux se sont produits sous les étoiles, devant une centaine de spectateurs venus apprécier la musique et déguster notre gamme complète. Gratuit et ouvert à tous, un moment de convivialité à l\'image de notre domaine.","en":"For the 2024 Fête de la Musique, the estate organised a jazz and wine evening in the vineyards. Two local groups performed under the stars, in front of one hundred spectators who came to enjoy the music and taste our full range. Free and open to all, a moment of conviviality in the image of our estate."}',
    '/assets/images/proprietaire.jpeg',
    NULL,
    'fete-musique-domaine-juin-2024',
    '2024-06-22 09:00:00'
),
(
    '{"fr":"Intégration dans le circuit œnotouristique de la Gironde","en":"Integration into the Gironde wine tourism circuit"}',
    '{"fr":"Le Château Crabitan Bellevue rejoint officiellement le réseau Vignobles & Découvertes de la Gironde, label national qui garantit la qualité de l\'accueil et des prestations œnotouristiques. Cette reconnaissance nous permettra de bénéficier d\'une meilleure visibilité auprès des offices de tourisme régionaux et nationaux.","en":"Château Crabitan Bellevue officially joins the Gironde Vignobles & Découvertes network, a national label guaranteeing the quality of welcome and wine tourism services. This recognition will give us better visibility with regional and national tourist offices."}',
    NULL,
    'https://www.google.com/search?q=Vignobles+Decouvertes+Gironde',
    'vignobles-decouvertes-gironde-integration',
    '2023-11-01 10:00:00'
),
(
    '{"fr":"Tri des vieilles vignes : sélection massale","en":"Old vine selection: mass selection"}',
    '{"fr":"Pour préserver le patrimoine génétique de nos vieilles vignes de Sémillon âgées de soixante-dix ans, nous avons entrepris une sélection massale. Les meilleurs pieds sont identifiés, prélevés et multipliés en pépinière viticole certifiée. Ces nouvelles boutures perpétueront l\'identité unique de notre terroir pour les générations futures.","en":"To preserve the genetic heritage of our seventy-year-old Sémillon old vines, we have undertaken a mass selection. The best plants are identified, harvested and multiplied in a certified vine nursery. These new cuttings will perpetuate the unique identity of our terroir for future generations."}',
    NULL,
    NULL,
    'selection-massale-vieilles-vignes-semillon',
    '2021-03-15 09:00:00'
),
(
    '{"fr":"Repas de vendanges — tradition et convivialité","en":"Harvest meal — tradition and conviviality"}',
    '{"fr":"Chaque année, à la fin des vendanges, le domaine organise le traditionnel repas des vendangeurs. Cette année, cent trente personnes étaient réunies autour d\'une grande tablée dressée dans le chai. Canards, confits, fromages du Périgord et bien sûr les vins du domaine ont rythmé cette soirée de partage et de célébration.","en":"Every year, at the end of harvest, the estate organises the traditional harvesters\' meal. This year, one hundred and thirty people gathered around a large table set up in the cellar. Ducks, confits, Périgord cheeses and of course the estate\'s wines punctuated this evening of sharing and celebration."}',
    '/assets/images/proprietaire.jpeg',
    NULL,
    'repas-vendanges-tradition-convivialite',
    '2023-10-20 14:00:00'
),
(
    '{"fr":"Notre domaine labellisé Vignoble & Patrimoine","en":"Our estate awarded the Vignoble & Patrimoine label"}',
    '{"fr":"Après examen de notre dossier par un jury de spécialistes, le Château Crabitan Bellevue reçoit le label Vignoble & Patrimoine, qui distingue les domaines alliant excellence viticole, préservation du patrimoine architectural et accueil de qualité. Notre cave du XIXe siècle et notre façade restaurée ont particulièrement été saluées.","en":"After examination of our application by a panel of specialists, Château Crabitan Bellevue receives the Vignoble & Patrimoine label, which distinguishes estates combining viticultural excellence, architectural heritage preservation and quality hospitality. Our 19th-century cellar and restored façade were particularly praised."}',
    '/assets/images/proprietaire.jpeg',
    NULL,
    'label-vignoble-patrimoine',
    '2024-11-05 10:00:00'
),
(
    '{"fr":"Atelier initiation à la dégustation — printemps 2025","en":"Wine tasting initiation workshop — spring 2025"}',
    '{"fr":"Chaque samedi matin d\'avril à juin 2025, le domaine propose un atelier d\'initiation à la dégustation de deux heures, animé par notre sommelier. Les participants apprennent les bases de l\'analyse sensorielle, découvrent les différents styles de vins du domaine et repartent avec un guide personnel de dégustation. Tarif : 35 € par personne.","en":"Every Saturday morning from April to June 2025, the estate offers a two-hour wine tasting initiation workshop, led by our sommelier. Participants learn the basics of sensory analysis, discover the different wine styles from the estate and leave with a personal tasting guide. Price: €35 per person."}',
    '/assets/images/proprietaire.jpeg',
    'https://www.google.com/maps?q=Chateau+Crabitan+Bellevue,+Sainte-Croix-du-Mont',
    'atelier-initiation-degustation-2025',
    '2025-03-15 10:00:00'
),

-- Entrées 71-100 pour compléter la centaine
(
    '{"fr":"Mise en place de l\'agriculture de précision","en":"Implementation of precision farming"}',
    '{"fr":"Le domaine investit dans des outils d\'agriculture de précision : capteurs de température et d\'humidité dans le sol, cartographie parcellaire par drone et logiciel de modulation de dose d\'intrants. Ces technologies nous permettent d\'intervenir au bon endroit, au bon moment, avec la bonne quantité, réduisant notre impact environnemental.","en":"The estate is investing in precision farming tools: soil temperature and humidity sensors, drone-based plot mapping and variable-rate input software. These technologies allow us to intervene in the right place, at the right time, with the right quantity, reducing our environmental footprint."}',
    NULL,
    NULL,
    'agriculture-precision-nouveaux-outils',
    '2020-08-20 09:00:00'
),
(
    '{"fr":"Rénovation du pressoir pneumatique","en":"Pneumatic press renovation"}',
    '{"fr":"Pièce maîtresse de notre vinification, notre pressoir pneumatique Bucher de trois tonnes a été entièrement révisé avant les vendanges 2021. Sa membrane, son programme de pressurage et son système de lavage automatique ont été mis à niveau. Un investissement indispensable pour garantir l\'extraction la plus douce et la plus qualitative de nos moûts.","en":"The key piece of our winemaking, our three-tonne Bucher pneumatic press has been completely overhauled ahead of the 2021 harvest. Its membrane, pressing programme and automatic washing system have been upgraded. An essential investment to guarantee the gentlest and highest-quality extraction of our musts."}',
    '/assets/images/proprietaire.jpeg',
    NULL,
    'renovation-pressoir-pneumatique-2021',
    '2021-07-01 09:00:00'
),
(
    '{"fr":"La faune sauvage dans nos vignes","en":"Wildlife in our vineyards"}',
    '{"fr":"Dans le cadre de notre démarche agroécologique, nous avons réalisé un inventaire de la faune présente sur le domaine. Résultat : dix-neuf espèces d\'oiseaux nicheurs, trois espèces de chauves-souris et une colonie de lézards des murailles ont été répertoriés. Ces indicateurs de biodiversité témoignent de la santé de notre écosystème viticole.","en":"As part of our agroecological approach, we carried out a wildlife inventory on the estate. Result: nineteen nesting bird species, three bat species and a colony of wall lizards were recorded. These biodiversity indicators reflect the health of our viticultural ecosystem."}',
    NULL,
    NULL,
    'faune-sauvage-vignes-inventaire',
    '2021-04-22 09:00:00'
),
(
    '{"fr":"Nos vins dans la bistronomie parisienne","en":"Our wines in Paris bistronomy"}',
    '{"fr":"Après un partenariat noué lors du salon Wine Paris, nos vins figurent désormais sur les cartes de quatre établissements bistronomiques parisiens. Nos Bordeaux rouges 2019 et 2020, accessibles et gourmands, séduisent une clientèle urbaine à la recherche d\'authenticité. Un ancrage parisien qui complète notre présence dans la grande restauration.","en":"Following a partnership forged at the Wine Paris fair, our wines now feature on the menus of four Parisian bistronomy establishments. Our 2019 and 2020 red Bordeaux, accessible and pleasurable, appeal to an urban clientele seeking authenticity. A Parisian presence complementing our fine dining footprint."}',
    NULL,
    NULL,
    'vins-bistronomie-parisienne',
    '2022-12-05 11:00:00'
),
(
    '{"fr":"Partenariat avec l\'Office de Tourisme de la Gironde","en":"Partnership with the Gironde Tourist Office"}',
    '{"fr":"Un accord de partenariat avec l\'Office de Tourisme de la Gironde nous permet désormais d\'être mis en avant dans les circuits touristiques officiels de la région. Notre domaine figurera dans les brochures et le site internet de l\'office, ainsi que dans les recommandations faites aux visiteurs à l\'accueil du Château de La Brède et de l\'Entre-Deux-Mers.","en":"A partnership agreement with the Gironde Tourist Office now allows us to be featured in the region\'s official tourist circuits. Our estate will appear in the office\'s brochures and website, as well as in recommendations made to visitors at the reception of the Château de La Brède and the Entre-Deux-Mers."}',
    NULL,
    'https://www.google.com/search?q=Office+de+Tourisme+Gironde',
    'partenariat-office-tourisme-gironde',
    '2022-09-05 10:00:00'
),
(
    '{"fr":"Dégustation chez des étudiants en œnologie de Bordeaux","en":"Tasting session with Bordeaux oenology students"}',
    '{"fr":"Nous avons accueilli une promotion de quarante étudiants de l\'Institut des Sciences de la Vigne et du Vin de Bordeaux pour une journée de découverte du terroir de Sainte-Croix-du-Mont. Analyse sensorielle des millésimes 2018 à 2022, visite géologique des falaises calcaires et atelier d\'assemblage : une journée dense et passionnante pour la génération de demain.","en":"We welcomed a class of forty students from the Bordeaux Institute of Vine and Wine Sciences for a day discovering the Sainte-Croix-du-Mont terroir. Sensory analysis of vintages 2018 to 2022, geological tour of the limestone cliffs and blending workshop: a packed and fascinating day for the next generation."}',
    '/assets/images/proprietaire.jpeg',
    NULL,
    'degustation-etudiants-oenologie-bordeaux',
    '2022-11-22 09:00:00'
),
(
    '{"fr":"Hivernage du vignoble 2022-2023","en":"Vineyard winter preparation 2022-2023"}',
    '{"fr":"L\'hiver est la saison des grandes décisions au vignoble. Cette année, nous avons planté deux hectares de nouvelles vignes en remplacement d\'une vieille parcelle arrachée, effectué le recépage de cent soixante pieds affaiblis et procédé à la taille Guyot simple sur l\'ensemble du domaine. Des journées courtes mais essentielles pour la qualité future de nos vins.","en":"Winter is the season of major decisions in the vineyard. This year, we planted two hectares of new vines to replace an old plot that was pulled out, carried out stump grafting on one hundred and sixty weakened plants and completed single Guyot pruning across the entire estate. Short but essential days for the future quality of our wines."}',
    NULL,
    NULL,
    'hivernage-vignoble-2022-2023',
    '2023-01-15 09:00:00'
),
(
    '{"fr":"Sortie de la cuvée Collection 2022","en":"Release of the 2022 Collection cuvée"}',
    '{"fr":"Intermédiaire entre notre cuvée principale et notre Prestige, la nouvelle cuvée Collection 2022 est issue d\'une sélection de parcelles sur sol argilo-calcaire profond. Élevée neuf mois en barriques de un vin, elle offre une belle expression du Sémillon botrytisé avec des notes de mangue, de safran et d\'écorce d\'orange confite. En vente directe au domaine dès aujourd\'hui.","en":"Sitting between our main cuvée and our Prestige, the new 2022 Collection cuvée is sourced from a selection of plots on deep clay-limestone soil. Aged nine months in one-wine barrels, it offers a beautiful expression of botrytised Sémillon with notes of mango, saffron and candied orange peel. Available for direct sale from the estate today."}',
    '/assets/images/proprietaire.jpeg',
    NULL,
    'cuvee-collection-2022-sortie',
    '2023-05-20 10:00:00'
),
(
    '{"fr":"Atelier vendanges en famille — septembre 2023","en":"Family harvest workshop — September 2023"}',
    '{"fr":"Pour la deuxième année consécutive, le domaine a organisé son atelier vendanges en famille. Quarante familles avec enfants ont participé à une matinée de trie des raisins dans les vignes, suivie d\'un déjeuner champêtre et d\'une initiation à la fabrication du jus de raisin. Une expérience mémorable pour les petits comme pour les grands.","en":"For the second consecutive year, the estate organised its family harvest workshop. Forty families with children participated in a morning of grape selection in the vineyards, followed by a countryside lunch and an initiation into grape juice making. A memorable experience for young and old alike."}',
    '/assets/images/proprietaire.jpeg',
    'https://www.google.com/maps?q=Chateau+Crabitan+Bellevue,+Sainte-Croix-du-Mont',
    'atelier-vendanges-famille-2023',
    '2023-09-15 08:00:00'
),
(
    '{"fr":"Résultats de notre dégustation à l\'aveugle interne","en":"Results of our internal blind tasting"}',
    '{"fr":"Chaque année, notre équipe technique organise une dégustation à l\'aveugle de nos vins face à ceux de nos concurrents directs sur l\'appellation. Cette année, notre Sainte-Croix-du-Mont 2021 est arrivé en tête des préférences parmi douze vins dégustés. Un résultat encourageant qui confirme la progression qualitative de nos assemblages.","en":"Every year, our technical team organises a blind tasting of our wines against those of our direct competitors on the appellation. This year, our 2021 Sainte-Croix-du-Mont came top of preferences among twelve wines tasted. An encouraging result confirming the qualitative progress of our blends."}',
    NULL,
    NULL,
    'degustation-aveugle-interne-2023',
    '2023-07-10 14:00:00'
),
(
    '{"fr":"Semaine de la Gastronomie au Château Crabitan","en":"Gastronomy Week at Château Crabitan"}',
    '{"fr":"Du 16 au 22 octobre 2023, le domaine a organisé sa première Semaine de la Gastronomie. Chaque soir, un chef invité différent a conçu un menu en cinq actes autour des vins du château. Six cents couverts servis, cinq chefs mobilisés et une couverture médiatique régionale remarquable pour cette manifestation appelée à devenir annuelle.","en":"From 16 to 22 October 2023, the estate organised its first Gastronomy Week. Each evening, a different guest chef created a five-act menu around the château\'s wines. Six hundred covers served, five chefs mobilised and remarkable regional media coverage for this event destined to become annual."}',
    '/assets/images/proprietaire.jpeg',
    NULL,
    'semaine-gastronomie-chateau-2023',
    '2023-10-16 09:00:00'
),
(
    '{"fr":"Expérimentation de l\'agroforesterie au vignoble","en":"Agroforestry experimentation in the vineyard"}',
    '{"fr":"En partenariat avec l\'INRAE de Bordeaux, nous expérimentons l\'introduction d\'arbres fruitiers et d\'oliviers en bordure de parcelles viticoles. L\'objectif : étudier l\'impact de l\'ombrage partiel sur la précocité du Botrytis, la régulation thermique du microclimat et la biodiversité fonctionnelle. Les résultats seront publiés à l\'issue d\'un suivi de trois ans.","en":"In partnership with INRAE Bordeaux, we are experimenting with introducing fruit trees and olive trees on the edges of vineyard plots. The aim: to study the impact of partial shading on Botrytis earliness, thermal regulation of the microclimate and functional biodiversity. Results will be published after a three-year monitoring period."}',
    NULL,
    NULL,
    'experimentation-agroforesterie-vignoble',
    '2023-04-05 09:00:00'
),
(
    '{"fr":"Premier Bordeaux Clairet du domaine","en":"Estate\'s first Bordeaux Clairet"}',
    '{"fr":"Répondant à la demande de nos clients les plus fidèles, nous produisons pour la première fois un Bordeaux Clairet 2024, issu d\'une macération courte de notre Cabernet Franc. Entre le rosé et le rouge léger, ce vin à la robe grenat translucide révèle des arômes de fruits rouges frais et d\'épices douces. Production limitée à 3 000 bouteilles.","en":"Responding to the demands of our most loyal customers, we are producing for the first time a 2024 Bordeaux Clairet, from a short maceration of our Cabernet Franc. Between a rosé and a light red, this wine with its translucent garnet colour reveals aromas of fresh red fruits and gentle spices. Limited production of 3,000 bottles."}',
    '/assets/images/proprietaire.jpeg',
    NULL,
    'premier-bordeaux-clairet-domaine-2024',
    '2024-09-10 10:00:00'
),
(
    '{"fr":"Présence au SITEVI Montpellier 2024","en":"Presence at SITEVI Montpellier 2024"}',
    '{"fr":"Le Château Crabitan Bellevue était représenté au salon SITEVI de Montpellier, salon international des équipements et techniques pour la vigne, le vin et les fruits. L\'occasion pour notre équipe technique de découvrir les dernières innovations en matière de machinerie viticole, d\'œnologie et de gestion durable du vignoble.","en":"Château Crabitan Bellevue was represented at the SITEVI fair in Montpellier, the international trade show for vineyard, wine and fruit equipment and techniques. The opportunity for our technical team to discover the latest innovations in viticultural machinery, oenology and sustainable vineyard management."}',
    NULL,
    'https://www.google.com/search?q=SITEVI+Montpellier+2024',
    'sitevi-montpellier-2024',
    '2024-11-27 10:00:00'
),
(
    '{"fr":"Partenariat export avec un caviste allemand","en":"Export partnership with a German wine merchant"}',
    '{"fr":"Un accord de distribution exclusive a été signé avec la maison Weinkeller Rhein & Main de Francfort, spécialisée dans les vins doux et liquoreux d\'Europe. Nos Sainte-Croix-du-Mont seront proposés dans leurs douze boutiques en Hesse et Rhénanie, ainsi que sur leur plateforme e-commerce. Première livraison prévue pour les fêtes de fin d\'année 2024.","en":"An exclusive distribution agreement has been signed with Weinkeller Rhein & Main of Frankfurt, specialising in sweet and dessert wines from Europe. Our Sainte-Croix-du-Mont wines will be offered in their twelve shops in Hesse and Rhineland, as well as on their e-commerce platform. First delivery planned for the 2024 end-of-year festivities."}',
    NULL,
    NULL,
    'partenariat-export-caviste-allemand',
    '2024-10-20 11:00:00'
),
(
    '{"fr":"Nouveau packaging pour nos bouteilles de prestige","en":"New packaging for our prestige bottles"}',
    '{"fr":"En collaboration avec un studio de design bordelais, nous avons repensé l\'habillage de nos bouteilles Prestige et Collection. L\'étiquette revisitée intègre une gravure du château, un papier texturé ivoire et une dorure à chaud en accord avec la charte graphique du domaine. Le nouveau packaging sera déployé à partir du millésime 2022.","en":"In collaboration with a Bordeaux design studio, we have redesigned the packaging of our Prestige and Collection bottles. The redesigned label integrates an engraving of the château, an ivory textured paper and hot foil gilding in keeping with the estate\'s visual identity. The new packaging will be rolled out from the 2022 vintage."}',
    '/assets/images/proprietaire.jpeg',
    NULL,
    'nouveau-packaging-bouteilles-prestige',
    '2024-02-28 10:00:00'
),
(
    '{"fr":"Trophée du meilleur rapport qualité-prix 2024","en":"Best value-for-money trophy 2024"}',
    '{"fr":"La revue Bettane+Desseauve attribue à notre Sainte-Croix-du-Mont 2022 le Trophée du Meilleur Rapport Qualité-Prix dans la catégorie vins doux. Un prix décerné parmi plusieurs centaines de références qui confirme notre engagement à produire des vins d\'excellence sans pour autant dépasser un tarif accessible aux amateurs.","en":"The Bettane+Desseauve magazine awards our 2022 Sainte-Croix-du-Mont the Best Value-for-Money Trophy in the dessert wine category. A prize awarded among several hundred references that confirms our commitment to producing wines of excellence without exceeding a price accessible to enthusiasts."}',
    NULL,
    'https://www.google.com/search?q=Bettane+Desseauve+trophee+vins+2024',
    'trophee-meilleur-rapport-qualite-prix-2024',
    '2024-11-18 11:00:00'
),
(
    '{"fr":"Stage de formation : accueil et œnotourisme","en":"Training workshop: hospitality and wine tourism"}',
    '{"fr":"L\'ensemble du personnel d\'accueil du domaine a suivi un stage de deux jours dispensé par un formateur spécialisé en œnotourisme. Au programme : techniques de présentation des vins, gestion des groupes internationaux, accueil des personnes à mobilité réduite et communication digitale pour les vignobles. Un investissement humain pour élever la qualité de notre accueil.","en":"All estate reception staff attended a two-day training course led by a specialist wine tourism trainer. The programme included: wine presentation techniques, managing international groups, welcoming people with reduced mobility and digital communication for vineyards. A human investment to raise the quality of our hospitality."}',
    NULL,
    NULL,
    'stage-formation-accueil-oenotourisme',
    '2024-03-12 09:00:00'
),
(
    '{"fr":"Notre cuvée Prestige mentionnée dans Wine Spectator","en":"Our Prestige cuvée mentioned in Wine Spectator"}',
    '{"fr":"La cuvée Prestige 2019 du Château Crabitan Bellevue a obtenu la note de 91 points dans le magazine américain Wine Spectator, avec une mention spéciale pour ses arômes de miel et sa persistance exceptionnelle en bouche. Une reconnaissance outre-Atlantique qui ouvre des perspectives nouvelles sur le marché américain des vins liquoreux.","en":"Château Crabitan Bellevue\'s 2019 Prestige cuvée received a score of 91 points in the American magazine Wine Spectator, with a special mention for its honey aromas and exceptional length on the palate. Transatlantic recognition opening new prospects on the American dessert wine market."}',
    NULL,
    'https://www.google.com/search?q=Wine+Spectator+Sainte-Croix-du-Mont',
    'cuvee-prestige-wine-spectator-mention',
    '2024-07-22 11:00:00'
),
(
    '{"fr":"Programme d\'adoption de vignes 2025","en":"Vine adoption programme 2025"}',
    '{"fr":"Dès le printemps 2025, le Château Crabitan Bellevue lance son programme d\'adoption de vignes. Pour 150 € par an, adoptez votre propre pied de vigne et recevez une bouteille de la production issue de votre parcelle, votre certificat d\'adoption nominatif et une invitation à la journée des adoptants lors des vendanges. Vingt places disponibles pour cette première édition.","en":"From spring 2025, Château Crabitan Bellevue launches its vine adoption programme. For €150 per year, adopt your own vine and receive a bottle of the production from your plot, your personalised adoption certificate and an invitation to the adopters\' day during harvest. Twenty places available for this first edition."}',
    '/assets/images/proprietaire.jpeg',
    'https://www.google.com/maps?q=Chateau+Crabitan+Bellevue,+Sainte-Croix-du-Mont',
    'programme-adoption-vignes-2025',
    '2025-02-01 10:00:00'
),
(
    '{"fr":"Participation à ProWein Düsseldorf 2025","en":"Participation in ProWein Düsseldorf 2025"}',
    '{"fr":"Pour la première fois, le Château Crabitan Bellevue participait au salon ProWein de Düsseldorf, le plus grand salon mondial des vins et spiritueux professionnels. Trois jours de rencontres intenses avec des importateurs d\'Europe du Nord, d\'Asie et d\'Amérique du Nord. Plusieurs accords de distribution sont en cours de signature à la suite de cet événement.","en":"For the first time, Château Crabitan Bellevue participated in ProWein Düsseldorf, the world\'s largest professional wines and spirits trade fair. Three days of intense meetings with importers from Northern Europe, Asia and North America. Several distribution agreements are being signed following this event."}',
    NULL,
    'https://www.google.com/search?q=ProWein+Dusseldorf+2025',
    'prowein-dusseldorf-2025',
    '2025-03-17 10:00:00'
),
(
    '{"fr":"Lancement du coffret cadeau collector 2025","en":"Launch of the 2025 collector gift box"}',
    '{"fr":"Pour les fêtes 2025, le domaine propose son premier coffret cadeau collector : deux bouteilles de cuvée Prestige 2021 et 2022 accompagnées d\'un livre de photographies du vignoble et d\'une invitation pour deux personnes à une visite privée du chai. Production limitée à 200 exemplaires numérotés, disponible uniquement sur commande directe.","en":"For the 2025 festive season, the estate offers its first collector gift box: two bottles of Prestige cuvée 2021 and 2022 accompanied by a photobook of the vineyard and an invitation for two to a private cellar tour. Limited production of 200 numbered copies, available by direct order only."}',
    '/assets/images/proprietaire.jpeg',
    NULL,
    'coffret-cadeau-collector-2025',
    '2025-01-20 10:00:00'
),
(
    '{"fr":"Dégustation verticale — 10 millésimes exceptionnels","en":"Vertical tasting — 10 exceptional vintages"}',
    '{"fr":"À l\'occasion du trentième anniversaire du domaine, nous avons organisé une dégustation verticale historique : dix millésimes de notre cuvée Prestige, de 1995 à 2022, présentés dans l\'ordre chronologique devant un public de cent cinquante collectionneurs et journalistes. Un voyage dans le temps qui a mis en lumière la remarquable constance de notre terroir.","en":"On the occasion of the estate\'s thirtieth anniversary, we organised a historic vertical tasting: ten vintages of our Prestige cuvée, from 1995 to 2022, presented in chronological order before an audience of one hundred and fifty collectors and journalists. A journey through time that highlighted the remarkable consistency of our terroir."}',
    '/assets/images/proprietaire.jpeg',
    NULL,
    'degustation-verticale-10-millesimes-anniversaire',
    '2025-04-05 10:00:00'
),
(
    '{"fr":"La troisième génération au cœur du domaine","en":"The third generation at the heart of the estate"}',
    '{"fr":"Alexandre et Marie Solane, petits-enfants du fondateur, rejoignent officiellement l\'équipe du domaine en 2025. Alexandre prend en charge le développement commercial et l\'export, tandis que Marie se consacre à la communication et à l\'œnotourisme. Une relève assurée, portée par la même passion et le même attachement au terroir de Sainte-Croix-du-Mont.","en":"Alexandre and Marie Solane, grandchildren of the founder, officially join the estate team in 2025. Alexandre takes charge of commercial development and export, while Marie focuses on communications and wine tourism. A secure succession, driven by the same passion and attachment to the Sainte-Croix-du-Mont terroir."}',
    '/assets/images/proprietaire.jpeg',
    NULL,
    'troisieme-generation-domaine-2025',
    '2025-01-05 10:00:00'
),
(
    '{"fr":"Inauguration de la cave à vins naturels","en":"Inauguration of the natural wine cellar"}',
    '{"fr":"En marge de notre gamme principale, nous inaugurons un espace dédié à la conservation des vins naturels : une cave à température régulée proposant nos millésimes en magnum et en jéroboam, ainsi que quelques flacons de grandes années (2001, 2005, 2009, 2011) issus des réserves familiales. Accessible sur rendez-vous lors des visites prestige.","en":"Alongside our main range, we are inaugurating a dedicated natural wine conservation space: a temperature-regulated cellar offering our vintages in magnum and jeroboam, as well as some bottles from great years (2001, 2005, 2009, 2011) from family reserves. Accessible by appointment during prestige visits."}',
    '/assets/images/proprietaire.jpeg',
    'https://www.google.com/maps?q=Chateau+Crabitan+Bellevue,+Sainte-Croix-du-Mont',
    'cave-vins-naturels-inauguration-2025',
    '2025-02-20 10:00:00'
),
(
    '{"fr":"Portrait photographique du vignoble en automne","en":"Photographic portrait of the vineyard in autumn"}',
    '{"fr":"Le photographe giroudin Mathieu Castaing a réalisé un reportage photographique de nos vignes en pleine couleur automnale. Ces images, qui ornent désormais notre site internet et notre salle de dégustation, capturent avec une sensibilité rare la beauté des rangs de vigne en octobre, avec la Garonne en arrière-plan. Un travail artistique que nous sommes fiers de partager.","en":"Gironde-based photographer Mathieu Castaing carried out a photographic report of our vineyards in full autumn colour. These images, which now adorn our website and tasting room, capture with rare sensitivity the beauty of the vine rows in October, with the Garonne in the background. Artistic work we are proud to share."}',
    '/assets/images/proprietaire.jpeg',
    NULL,
    'reportage-photo-vignoble-automne',
    '2020-11-01 10:00:00'
),
(
    '{"fr":"Récompense Coup de Cœur du Guide Vert Michelin","en":"Coup de Cœur award in the Green Michelin Guide"}',
    '{"fr":"Le Château Crabitan Bellevue figure dans la nouvelle édition du Guide Vert Michelin Aquitaine avec la mention Coup de Cœur pour son accueil œnotouristique. Le guide souligne la qualité de nos visites guidées, la beauté du site et la passion communicative de notre équipe. Une récompense qui rejaillit positivement sur toute l\'appellation.","en":"Château Crabitan Bellevue features in the new edition of the Michelin Green Guide Aquitaine with a Coup de Cœur mention for its wine tourism hospitality. The guide highlights the quality of our guided tours, the beauty of the site and the infectious passion of our team. An award that reflects positively on the entire appellation."}',
    NULL,
    'https://www.google.com/search?q=Guide+Vert+Michelin+Aquitaine',
    'coup-de-coeur-guide-vert-michelin',
    '2022-04-05 10:00:00'
),
(
    '{"fr":"Atelier pétanque et vins du Midi","en":"Pétanque and afternoon wines workshop"}',
    '{"fr":"Chaque dimanche après-midi de juillet et août 2023, le domaine proposait un atelier convivial : une partie de pétanque sur l\'esplanade du château suivie d\'une dégustation de nos vins rouges et rosés frais. Un format décontracté pour initier un large public à nos vins dans un esprit de partage. Succès immédiat avec une liste d\'attente dès la deuxième session.","en":"Every Sunday afternoon in July and August 2023, the estate offered a convivial workshop: a game of pétanque on the château\'s esplanade followed by a tasting of our chilled red and rosé wines. A relaxed format to introduce a wide audience to our wines in a spirit of sharing. Immediate success with a waiting list from the second session."}',
    '/assets/images/proprietaire.jpeg',
    'https://www.google.com/maps?q=Chateau+Crabitan+Bellevue,+Sainte-Croix-du-Mont',
    'atelier-petanque-vins-ete-2023',
    '2023-06-25 10:00:00'
),
(
    '{"fr":"Test de la vendange mécanique nocturne","en":"Nocturnal mechanical harvest trial"}',
    '{"fr":"Dans le cadre de notre adaptation au changement climatique, nous avons testé pour la première fois la vendange mécanique de nuit sur une parcelle de Merlot. La récolte entre 2 h et 6 h du matin permet de conserver une température de la baie inférieure à 18 °C, préservant ainsi les arômes et limitant les oxydations. Les résultats seront comparés à ceux de la vendange manuelle diurne.","en":"As part of our climate adaptation strategy, we trialled nocturnal mechanical harvesting for the first time on a Merlot plot. Harvesting between 2am and 6am allows berry temperature to be kept below 18°C, thus preserving aromas and limiting oxidation. Results will be compared to those of daytime manual harvesting."}',
    NULL,
    NULL,
    'test-vendange-mecanique-nocturne-2024',
    '2024-09-25 08:00:00'
);
