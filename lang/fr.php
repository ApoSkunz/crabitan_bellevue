<?php

return [
    // Age gate
    'age_gate.quote'         => 'L\'art et la passion du vin, pour sublimer votre table',
    'age_gate.intro'         => 'Pour visiter ce site, vous devez être en âge légal de consommer de l\'alcool dans votre pays de résidence.',
    'age_gate.legal'         => 'Je suis en âge légal de consommer de l\'alcool dans mon pays de résidence',
    'age_gate.not_legal'     => 'Je ne suis pas en âge légal de consommer de l\'alcool dans mon pays de résidence',
    'age_gate.remember'      => 'Se souvenir de moi',
    'age_gate.enter'         => 'Entrer',
    'age_gate.info_label'    => 'Informations légales',
    'age_gate.choice_legend' => 'Vérification de l\'âge',
    'age_gate.legal_more'    => 'En savoir plus.',
    'age_gate.error'         => 'Vous devez être en âge légal pour accéder à ce site. Vous allez être redirigé dans 3 secondes.',

    // Navigation
    'nav.home'        => 'Accueil',
    'nav.contact'     => 'Contact',
    'nav.wines'       => 'Boutique',
    'nav.collection'  => 'Collection des vins',
    'nav.news'        => 'Actualités',
    'nav.savoir_faire' => 'Savoir-Faire',
    'nav.chateau'     => 'Le Château',
    'nav.cart'        => 'Panier',
    'nav.account'     => 'Mon compte',
    'nav.login'       => 'Connexion',
    'nav.logout'      => 'Déconnexion',
    'nav.register'    => 'Inscription',

    // Auth
    'auth.login'             => 'Connexion',
    'auth.register'          => 'Créer un compte',
    'auth.email'             => 'Adresse email',
    'auth.password'          => 'Mot de passe',
    'auth.forgot_password'   => 'Mot de passe oublié ?',
    'auth.reset_password'    => 'Réinitialiser le mot de passe',
    'auth.verify_email'      => 'Vérifiez votre email',
    'auth.invalid_credentials' => 'Email ou mot de passe incorrect',
    'auth.account_inactive'  => 'Veuillez vérifier votre email pour activer votre compte',
    'auth.email_taken'       => 'Cette adresse email est déjà utilisée',
    'auth.register_success'  => 'Compte créé ! Consultez votre email pour activer votre compte.',
    'auth.verify_success'    => 'Votre compte est activé. Vous pouvez vous connecter.',
    'auth.verify_invalid'    => 'Lien de vérification invalide ou expiré.',
    'auth.verify_contact'    => 'Contactez-nous si vous avez besoin d\'aide.',
    'auth.already_verified'  => 'Votre compte est déjà activé.',
    'auth.reset_email_sent'  => 'Si un compte existe avec cet email, vous recevrez un lien de réinitialisation.',
    'auth.reset_invalid'     => 'Lien de réinitialisation invalide ou expiré.',
    'auth.password_invalid'  => 'Mot de passe trop court ou les mots de passe ne correspondent pas.',
    'auth.password_updated'  => 'Mot de passe mis à jour. Vous pouvez vous connecter.',

    // Vins — Catalogue
    'wine.add_to_cart'  => 'Ajouter au panier',
    'wine.out_of_stock' => 'Épuisé',
    'wine.available'    => 'Disponible',
    'wine.favorites'      => 'Favoris',
    'wine.like_login'     => 'Connectez-vous pour aimer ce vin.',
    'wine.vintage'      => 'Millésime',
    'wine.price'        => 'Prix',
    'wine.in_stock'     => 'en stock',
    'wine.read_more'    => 'En savoir plus', // NOSONAR php:S1192 — même libellé CTA utilisé intentionnellement sur des clés de traduction distinctes
    'wine.empty'        => 'Aucun vin disponible pour le moment.',
    'wine.per_page'     => 'Par page :',
    'wine.ttc_note'     => 'Prix TTC, livraison comprise en France métropolitaine. Remises appliquées par multiples de 12 bouteilles.',

    // Vins — Couleurs
    'wine.color.all'        => 'Tous les vins',
    'wine.color.red'        => 'Rouges',
    'wine.color.white'      => 'Blancs secs',
    'wine.color.rosé'       => 'Rosés',
    'wine.color.sweet'      => 'Blancs doux',

    // Vins — Filtres
    'wine.filter_label'  => 'Filtrer les vins',
    'wine.filter_show'   => 'Afficher :',
    'wine.filter_sort'   => 'Résultats triés selon :',
    'wine.filter_apply'  => 'Appliquer le filtre',
    'wine.view_collection' => 'Vue par collection',

    // Vins — Tri
    'wine.sort.default'      => 'Crabitan Bellevue présente',
    'wine.sort.price_asc'    => 'Prix croissant',
    'wine.sort.price_desc'   => 'Prix décroissant',
    'wine.sort.vintage_asc'  => 'Millésime croissant',
    'wine.sort.vintage_desc' => 'Millésime décroissant',
    'wine.sort.likes_desc'   => 'Les plus aimés',

    // Vins — Fiche produit
    'wine.zoom'          => 'Agrandir la photo',
    'wine.tasting'       => 'Dégustation',
    'wine.technical'     => 'Fiche technique',
    'wine.appellation'   => 'Appellation',
    'wine.variety'       => 'Encépagement',
    'wine.area'          => 'Superficie',
    'wine.age'           => 'Âge des vignes',
    'wine.years'         => 'ans',
    'wine.soil'          => 'Terroir',
    'wine.pruning'       => 'Taille',
    'wine.harvest'       => 'Vendanges',
    'wine.vinification'  => 'Vinification',
    'wine.aging'         => 'Élevage',
    'wine.certification' => 'Certification',
    'wine.download_sheet' => 'Télécharger la fiche technique complète',

    // Vins — Collection
    'wine.collection_nav'  => 'Navigation par type de vin',
    'wine.filter_avail'    => 'Disponibilité :',
    'wine.avail.all'       => 'Tous',
    'wine.avail.available' => 'Disponible',
    'wine.avail.out'       => 'Épuisé',

    // Vins — Accords mets & vins
    'wine.pairing_title' => 'Accords mets & vins',
    'wine.pairing.sweet' => 'Idéal à l\'apéritif, avec du foie gras, du roquefort ou des desserts aux fruits jaunes. Se déguste frais (8-10 °C).',
    'wine.pairing.white' => 'Parfait avec les poissons, fruits de mer, volailles en sauce et fromages de chèvre frais. Servir à 10-12 °C.',
    'wine.pairing.red'   => 'S\'accorde avec les viandes rouges grillées, le gibier, les plats mijotés et les fromages affinés. Servir à 16-18 °C.',
    'wine.pairing.rosé'  => 'Excellent avec les salades composées, charcuteries, grillades estivales et cuisines méditerranéennes. Servir frais à 10-12 °C.',

    // Panier
    'cart.title'         => 'Votre panier',
    'cart.empty'         => 'Votre panier est vide',
    'cart.total'         => 'Total',
    'cart.checkout'      => 'Passer commande',
    'cart.remove'        => 'Supprimer',
    'cart.qty'           => 'Quantité',
    'cart.login_required'  => 'Connectez-vous pour finaliser votre commande.',
    'cart.added_offline'   => 'Ajouté au panier. Connectez-vous pour passer commande.',
    'cart.added'           => 'Ajouté au panier !',

    // Commande
    'order.confirm'   => 'Confirmer la commande',
    'order.success'   => 'Commande confirmée !',
    'order.reference' => 'Référence commande',

    // Erreurs
    'error.404'    => 'Page introuvable',
    'error.500'    => 'Erreur serveur',
    'error.403'    => 'Accès refusé',
    'error.csrf'   => 'Requête invalide, veuillez réessayer.',

    // Formulaires
    'form.lastname'         => 'Nom',
    'form.firstname'        => 'Prénom',
    'form.gender'           => 'Civilité',
    'form.gender.m'         => 'M.',
    'form.gender.f'         => 'Mme',
    'form.gender.other'     => 'Autre',
    'form.gender.society'   => 'Société',
    'form.company'          => 'Raison sociale',
    'form.password_confirm' => 'Confirmer le mot de passe',
    'form.newsletter'       => 'Je souhaite recevoir les actualités et offres du domaine',

    // Validation
    'validation.required'      => 'Ce champ est requis.',
    'validation.email'         => 'Adresse email invalide.',
    'validation.password_min'  => 'Le mot de passe doit contenir au moins 8 caractères.',
    'validation.password_match' => 'Les mots de passe ne correspondent pas.',

    // Cookie banner
    'cookie.banner_label' => 'Bandeau cookies',
    'cookie.text'         => 'Ce site utilise Google Analytics pour mesurer son audience. Acceptez-vous l\'utilisation de ce cookie de suivi ?',
    'cookie.learn_more'   => 'En savoir plus',
    'cookie.accept'       => 'Accepter',
    'cookie.refuse'       => 'Refuser',
    'cookie.required'     => '⚠ Veuillez accepter ou refuser les cookies avant de continuer.',

    // Footer
    'footer.legal_notice'    => 'Mentions légales',
    'footer.privacy_policy'  => 'Politique de confidentialité',
    'footer.sitemap'         => 'Plan du site',
    'footer.carbon'          => 'Site Carbone',
    'footer.alcohol_warning' => "L'abus d'alcool est dangereux pour la santé. À consommer avec modération.",
    'footer.made_by'         => 'Réalisé par',
    'footer.webmaster'       => 'Alexandre Solane',

    // Général
    'btn.submit'   => 'Envoyer',
    'btn.save'     => 'Enregistrer',
    'btn.cancel'   => 'Annuler',
    'btn.back'     => 'Retour',

    // Homepage — Carousel
    'home.carousel_alt'   => 'Vignoble du Château Crabitan Bellevue',
    'home.carousel_title' => 'Château Crabitan Bellevue',
    'home.carousel_sub'   => 'Vins de Bordeaux — Sainte-Croix-du-Mont',

    'home.img_wines_alt'   => 'Gamme des vins du Château Crabitan Bellevue',
    'home.img_harvest_alt' => 'Vendanges à cheval — Château Crabitan Bellevue',
    'home.img_cellar_alt'  => 'Chai à barriques — Château Crabitan Bellevue',

    // Homepage — Section Millésime / Nos vins
    'home.wines_tag'   => 'Millésime',
    'home.wines_title' => 'Nos Vins',
    'home.wines_text'  => 'Juchés sur les falaises calcaires dominant la Garonne, nos vignes expriment toute'
        . ' la noblesse de l\'appellation Sainte-Croix-du-Mont. Ce terroir d\'exception, façonné par des calcaires'
        . ' à huîtres fossiles et la douceur des brumes matinales, réunit les conditions idéales au développement'
        . ' du Botrytis cinerea. Aux côtés de ces blancs liquoreux,'
        . ' le Château Crabitan Bellevue élabore une gamme de vins rouges et blancs secs issus des grands cépages'
        . ' bordelais — Merlot, Cabernet Sauvignon et Sauvignon Blanc.',
    'home.wines_cta'   => 'Découvrir nos vins',

    // Homepage — Section Notre Histoire
    'home.history_tag'   => 'Domaine',
    'home.history_title' => 'Notre Histoire',
    'home.history_text'  => 'Depuis plusieurs générations, la famille Solane cultive ses vignes sur les coteaux'
        . ' argilo-calcaires de Sainte-Croix-du-Mont. Une terre d\'exception qui donne naissance'
        . ' à des vins d\'une grande finesse.',
    'home.history_cta'   => 'En savoir plus',

    // Homepage — Section Savoir-Faire
    'home.savoir_tag'   => 'Le Métier',
    'home.savoir_title' => 'Notre Savoir-Faire',
    'home.savoir_text'  => 'De la vigne au chai, chaque étape est maîtrisée avec rigueur et passion.'
        . ' Un élevage soigné en fûts de chêne pour des vins complexes, équilibrés et élégants.',
    'home.savoir_cta'   => 'En savoir plus',

    // Homepage — Section Vidéo
    'home.video_tag'   => 'Le domaine',
    'home.video_title' => 'Le Château en vidéo',

    // Homepage — Section Actualités
    'home.news_tag'   => 'À la une',
    'home.news_title' => 'Actualités',
    'home.news_cta'   => 'Toutes les actualités',

    // Homepage — Section Localisation
    'home.location_title'   => 'Où sommes-nous ?',
    'home.location_address' => 'Crabitan, 33410 Sainte-Croix-du-Mont',
    'home.location_contact' => 'Comment nous joindre ?',
    'home.location_phone'   => '05 56 62 01 53',
    'home.location_cta'     => 'Contactez-nous',

    // News
    'news.read_more' => 'Lire la suite',
    'news.back'      => 'Retour aux actualités',
    'news.empty'     => 'Aucune actualité disponible pour le moment.',

    // Contact
    'contact.tag' => 'Nous joindre',

    // Mentions légales
    'legal.editor_title'  => 'Éditeur du site',
    'legal.hosting_title' => 'Hébergement',
    'legal.ai_mention'    => 'Ce site a été développé avec l\'assistance de Claude, un modèle d\'intelligence artificielle édité par Anthropic.',
    'legal.hosting_info'  => 'Ce site est hébergé par IONOS (1&1 Internet SARL) — 7, place de la Gare, 57200 Sarreguemines — France. Téléphone : +33 (0)9 70 80 89 11. Contact : email@1and1.fr.',
    'legal.data_title'    => 'Données personnelles',
    'legal.data_info'     => 'Conformément au RGPD, vous disposez d\'un droit d\'accès, de rectification et de suppression de vos données. Pour exercer ce droit, contactez-nous par téléphone ou courrier.',

    // Le Château
    'chateau.origins_tag'    => 'Les Origines',
    'chateau.origins_title'  => 'Une famille de tonneliers',
    'chateau.origins_text'   => 'Tout d\'abord petits artisans tonneliers, la famille s\'est installée à Crabitan sur les hauteurs de Sainte-Croix-du-Mont en 1870. La salle de dégustation actuelle est un témoignage de l\'ancienne cuisine des aïeux de la famille. Le vignoble s\'est développé progressivement au fil des générations par l\'achat, la prise en fermage (location) et la plantation de parcelles de vigne.',
    'chateau.year1956_tag'   => '1956',
    'chateau.year1956_title' => 'La Grande Gelée',
    'chateau.year1956_text'  => 'En février 1956, la grande gelée ravagea les vignobles bordelais. À Crabitan comme partout en Gironde, les pieds de vigne furent décimés. La famille Solane s\'attela à replanter les parcelles, amorçant un renouveau patient et déterminé du domaine.',
    'chateau.year1975_tag'   => '1975',
    'chateau.year1975_title' => 'Château Crabitan Bellevue',
    'chateau.year1975_text'  => 'Les vins du clos de Crabitan se renommèrent Château Crabitan Bellevue et se firent connaître avec Bernard et Eliane lors de salons et foires expositions en mettant en valeur leur bon rapport qualité/prix.',
    'chateau.year1994_tag'   => '1994',
    'chateau.year1994_title' => 'GFA Bernard Solane et Fils',
    'chateau.year1994_text'  => 'Entre temps le tracteur avait remplacé les bœufs et le cheval pour le travail de la vigne, ce qui dans ces coteaux argileux difficiles rendit les tâches quotidiennes moins ardues. Nicolas, gérant actuel du domaine, a rejoint ses parents en 1994 avec la création du GFA Bernard Solane et Fils.',
    'chateau.today_tag'      => 'Aujourd\'hui',
    'chateau.today_title'    => 'Nicolas & Corinne',
    'chateau.img_origins_alt' => 'La famille Solane — fin XIXe siècle',
    'chateau.img_1956_alt'   => 'Vendanges 1956 — Château Crabitan Bellevue',
    'chateau.img_1975_alt'   => 'Vendanges années 1970 — Château Crabitan Bellevue',
    'chateau.img_1994_alt'   => 'Le chai — Château Crabitan Bellevue',
    'chateau.img_today_alt'  => 'Nicolas et Corinne Solane — Château Crabitan Bellevue',
    'chateau.today_text'     => 'Il s\'efforce chaque jour, avec le concours de Corinne à l\'administratif et de leur équipe au chai et au vignoble, d\'amener le raisin à la meilleure maturité afin de rendre les vins de Crabitan Bellevue toujours plaisants à déguster pour les visiteurs venant au domaine ou ceux qui les découvrent dans des régions plus lointaines. Depuis 2020, le domaine propose également la vente en ligne avec livraison directe en France, pour que chacun puisse recevoir ses bouteilles à domicile.',

    // Savoir-Faire
    'savoir.vignoble_tag'   => 'Le Vignoble',
    'savoir.vignoble_title' => 'Un terroir d\'exception',
    'savoir.vignoble_text'  => 'Situés sur les hauteurs de Sainte-Croix-du-Mont, nos vignes bénéficient d\'une exposition idéale et d\'un sol argilo-calcaire. Ces coteaux façonnés par les siècles confèrent aux raisins une richesse aromatique et une concentration incomparables, fondement de tous nos vins.',
    'savoir.vinif_tag'      => 'La Vinification',
    'savoir.vinif_title'    => 'L\'art de la vinification',
    'savoir.vinif_text'     => 'La vendange est récoltée à parfaite maturité, vinifiée avec soin pour préserver l\'expression unique de chaque parcelle. Chaque vin est élaboré en respectant les traditions tout en intégrant les meilleures pratiques contemporaines, alliant précision technique et sensibilité au produit.',
    'savoir.elevage_tag'    => 'L\'Élevage',
    'savoir.elevage_title'  => 'La patience récompensée',
    'savoir.img_vignoble_alt' => 'Le vignoble en été — Château Crabitan Bellevue',
    'savoir.img_vinif_alt'   => 'Récolte des raisins — Château Crabitan Bellevue',
    'savoir.img_elevage_alt' => 'Chai à barriques — Château Crabitan Bellevue',
    'savoir.elevage_text'   => 'Nos liquoreux sont élevés avec patience pour développer leur complexité et leur longueur en bouche. Les vins rouges bénéficient d\'un élevage adapté à chaque millésime, garantissant équilibre et élégance. Chaque bouteille est le fruit d\'un travail minutieux, de la vigne jusqu\'à la mise en bouteille.',

    // Support / FAQ
    'support.title'           => 'Support',
    'support.faq_title'       => 'Foire aux questions',
    'support.q1'              => 'J\'ai oublié mon mot de passe',
    'support.a1'              => 'Vous pouvez réinitialiser votre mot de passe en cliquant sur « Mot de passe oublié » accessible depuis la page Connexion.',
    'support.q2'              => 'Je n\'ai pas reçu le mail de validation d\'inscription',
    'support.a2'              => 'Vous pouvez demander le renvoi d\'un mail de validation (valable 48h) depuis la page Connexion en cliquant sur « Je n\'ai pas reçu le mail de validation ».',
    'support.q3'              => 'Je souhaite modifier mon mot de passe',
    'support.a3'              => 'Vous pouvez modifier votre mot de passe depuis l\'espace « Mes infos perso » › « Modifier mon mot de passe ».',
    'support.q4'              => 'Je souhaite me désinscrire de la newsletter',
    'support.a4'              => 'Vous pouvez vous désinscrire de notre newsletter depuis l\'espace « Newsletter » ou depuis « Mon compte » › en cliquant ici.',
    'support.q5'              => 'Je souhaite modifier mes coordonnées',
    'support.a5'              => 'Vous pouvez modifier l\'ensemble de vos coordonnées depuis l\'espace « Mes infos perso » › « Modifier mes coordonnées ».',
    'support.q6'              => 'Je souhaite supprimer mon compte',
    'support.a6'              => 'Vous pouvez supprimer votre compte depuis « Mes infos perso » accessible depuis l\'espace « Mon compte ».',
    'support.q7'              => 'Je souhaite ajouter une adresse de livraison',
    'support.a7'              => 'Vous pouvez ajouter une adresse de livraison depuis l\'espace « Mes adresses » › « Mon compte ».',
    'support.q8'              => 'Je souhaite supprimer une adresse de livraison',
    'support.a8'              => 'Vous pouvez supprimer une adresse de livraison depuis l\'espace « Mes adresses » › « Mon compte ».',
    'support.q9'              => 'Je souhaite ajouter une adresse de facturation',
    'support.a9'              => 'Vous pouvez ajouter une adresse de facturation depuis l\'espace « Mes adresses » › « Mon compte ».',
    'support.q10'             => 'Je souhaite supprimer une adresse de facturation',
    'support.a10'             => 'Vous pouvez supprimer une adresse de facturation depuis l\'espace « Mes adresses » › « Mon compte ».',
    'support.q11'             => 'Je souhaite voir mes commandes',
    'support.a11'             => 'Vous pouvez consulter l\'ensemble de vos commandes depuis l\'espace « Mes commandes » › « Mon compte ».',

    // Jeux
    'jeux.title'       => 'Jeux',
    'jeux.memo_title'  => 'Mémo',
    'jeux.memo_desc'   => 'Vous devez retrouver les 14 paires de bouteilles de vin avant la fin du temps imparti.',
    'jeux.hours'       => 'heures',
    'jeux.minutes'     => 'minutes',
    'jeux.seconds'     => 'secondes',
    'jeux.start'       => 'Démarrer',
    'jeux.restart'     => 'Rejouer',
    'jeux.win'         => 'Félicitations ! Vous avez trouvé toutes les paires.',
    'jeux.lose'        => 'Temps écoulé ! Réessayez.',
    'jeux.pairs_found' => 'paires trouvées',

    // Webmaster
    'webmaster.bio' => 'Site conçu et développé par Alexandre Solane, développeur web full-stack.',

    // Account panel (drawer)
    'panel.title'     => 'Mon espace',
    'panel.account'   => 'Mon compte',
    'panel.orders'    => 'Mes commandes',
    'panel.addresses' => 'Mes adresses',
    'panel.favorites' => 'Mes favoris',
    'panel.logout'    => 'Déconnexion',
];
