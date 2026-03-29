<?php

declare(strict_types=1);

// ============================================================
// Routes Admin — déclarées EN PREMIER pour éviter le match
// de /{lang}/... sur les segments commençant par "admin"
// ============================================================
$router->get('/admin', 'Admin\DashboardController@index');
$router->get('/admin/vins', 'Admin\WineAdminController@index');
$router->get('/admin/vins/ajouter', 'Admin\WineAdminController@create');
$router->post('/admin/vins/ajouter', 'Admin\WineAdminController@store');
$router->get('/admin/vins/{id}/modifier', 'Admin\WineAdminController@edit');
$router->post('/admin/vins/{id}/modifier', 'Admin\WineAdminController@update');
$router->get('/admin/commandes', 'Admin\OrderAdminController@index');
$router->get('/admin/commandes/{id}', 'Admin\OrderAdminController@show');
$router->post('/admin/commandes/{id}/statut', 'Admin\OrderAdminController@updateStatus');
$router->post('/admin/commandes/{id}/facture', 'Admin\OrderAdminController@uploadInvoice');
$router->get('/admin/commandes/{id}/facture/telecharger', 'Admin\OrderAdminController@downloadInvoice');
$router->get('/admin/comptes', 'Admin\AccountAdminController@index');
$router->post('/admin/comptes/{id}/verifier', 'Admin\AccountAdminController@verify');
$router->get('/admin/tarifs', 'Admin\PricingAdminController@index');
$router->post('/admin/tarifs', 'Admin\PricingAdminController@update');
$router->get('/admin/actualites', 'Admin\NewsAdminController@index');
$router->get('/admin/actualites/ajouter', 'Admin\NewsAdminController@create');
$router->post('/admin/actualites/ajouter', 'Admin\NewsAdminController@store');
$router->get('/admin/actualites/{id}/modifier', 'Admin\NewsAdminController@edit');
$router->post('/admin/actualites/{id}/modifier', 'Admin\NewsAdminController@update');
$router->get('/admin/newsletter', 'Admin\NewsletterAdminController@index');
$router->get('/admin/newsletter/{id}', 'Admin\NewsletterAdminController@show');
$router->post('/admin/newsletter/envoyer', 'Admin\NewsletterAdminController@send');
$router->get('/admin/bons-de-commande', 'Admin\OrderFormAdminController@index');
$router->post('/admin/bons-de-commande/ajouter', 'Admin\OrderFormAdminController@upload');
$router->post('/admin/bons-de-commande/{id}/supprimer', 'Admin\OrderFormAdminController@delete');
$router->get('/admin/bons-de-commande/{id}/telecharger', 'Admin\OrderFormAdminController@download');
$router->get('/admin/statistiques', 'Admin\StatsAdminController@index');
$router->get('/admin/securite', 'Admin\ProfileAdminController@index');
$router->post('/admin/securite/mot-de-passe', 'Admin\ProfileAdminController@changePassword');
$router->post('/admin/securite/session/{id}/revoquer', 'Admin\ProfileAdminController@revokeSession');
$router->post('/admin/securite/sessions/revoquer-toutes', 'Admin\ProfileAdminController@revokeAllSessions');
$router->post('/admin/securite/appareils/retirer-confiance', 'Admin\ProfileAdminController@untrustDevice');
$router->post('/admin/securite/appareils/supprimer-toutes', 'Admin\ProfileAdminController@untrustAllDevices');
$router->post('/admin/securite/reinitialiser', 'Admin\ProfileAdminController@resetSecurity');

// ============================================================
// Routes publiques
// ============================================================

// Age gate
$router->get('/age-gate', 'AgeGateController@show');
$router->post('/age-gate', 'AgeGateController@confirm');

// Home
$router->get('/', 'HomeController@index'); // NOSONAR — 3 routes distinctes vers le même handler
$router->get('/fr', 'HomeController@index');
$router->get('/en', 'HomeController@index');

// Pages statiques
$router->get('/{lang}/le-chateau', 'PageController@chateau');
$router->get('/{lang}/savoir-faire', 'PageController@savoirFaire');
$router->get('/{lang}/contact', 'PageController@contact');
$router->post('/{lang}/contact', 'PageController@contactPost');
$router->get('/{lang}/mentions-legales', 'PageController@mentionsLegales');
$router->get('/{lang}/politique-de-confidentialite', 'PageController@politiqueConfidentialite');
$router->get('/{lang}/plan-du-site', 'PageController@planDuSite');
$router->get('/{lang}/support', 'PageController@support');
$router->get('/{lang}/jeux', 'PageController@jeux');
$router->get('/{lang}/webmaster', 'PageController@webmaster');

// Catalogue vins
$router->get('/{lang}/vins', 'WineController@index');
$router->get('/{lang}/vins/collection', 'WineController@collection');
$router->get('/{lang}/vins/{slug}/fiche-technique', 'WineController@technicalSheet');
$router->get('/{lang}/vins/{slug}', 'WineController@show');

// News
$router->get('/{lang}/actualites', 'NewsController@index');
$router->get('/{lang}/actualites/{slug}', 'NewsController@show');

// Auth
$router->post('/{lang}/connexion', 'AuthController@login');
$router->post('/{lang}/inscription', 'AuthController@register');
$router->get('/{lang}/deconnexion', 'AuthController@logout');
$router->get('/{lang}/verification/{token}', 'AuthController@verifyEmail');
$router->get('/{lang}/mot-de-passe-oublie', 'AuthController@forgotForm');
$router->post('/{lang}/mot-de-passe-oublie', 'AuthController@forgot');
$router->get('/{lang}/reinitialisation/{token}', 'AuthController@resetForm');
$router->post('/{lang}/reinitialisation/{token}', 'AuthController@reset');

// Panier
$router->get('/{lang}/panier', 'CartController@index');
$router->post('/{lang}/panier/ajouter', 'CartController@add');
$router->post('/{lang}/panier/modifier', 'CartController@update');
$router->post('/{lang}/panier/supprimer', 'CartController@remove');

// Commande
$router->get('/{lang}/commande', 'OrderController@checkout');
$router->post('/{lang}/commande/paiement', 'OrderController@payment');
$router->get('/{lang}/commande/confirmation', 'OrderController@confirmation');

// Espace client
$router->get('/{lang}/mon-compte', 'AccountController@index');
$router->get('/{lang}/mon-compte/profil', 'AccountController@profile');
$router->post('/{lang}/mon-compte/profil', 'AccountController@updateProfile');
$router->get('/{lang}/mon-compte/commandes', 'AccountController@orders');
$router->get('/{lang}/mon-compte/commandes/{id}', 'AccountController@orderDetail');
$router->post('/{lang}/mon-compte/commandes/{id}/annuler', 'AccountController@cancelOrder');
$router->get('/{lang}/mon-compte/commandes/{id}/facture', 'InvoiceController@download');
$router->get('/{lang}/mon-compte/adresses', 'AccountController@addresses');
$router->post('/{lang}/mon-compte/adresses/ajouter', 'AccountController@addAddress');
$router->get('/{lang}/mon-compte/adresses/{id}/modifier', 'AccountController@editAddress');
$router->post('/{lang}/mon-compte/adresses/{id}/modifier', 'AccountController@updateAddress');
$router->post('/{lang}/mon-compte/adresses/{id}/supprimer', 'AccountController@deleteAddress');
$router->get('/{lang}/mon-compte/favoris', 'AccountController@favorites');
$router->get('/{lang}/mon-compte/securite', 'AccountController@security');
$router->post('/{lang}/mon-compte/securite/mot-de-passe', 'AccountController@changePassword');
$router->post('/{lang}/mon-compte/securite/session/{id}/revoquer', 'AccountController@revokeSession');
$router->post('/{lang}/mon-compte/securite/supprimer-compte', 'AccountController@deleteAccount');
$router->post('/{lang}/mon-compte/securite/sessions/revoquer-toutes', 'AccountController@revokeAllUserSessions');
$router->get('/{lang}/mon-compte/nouvel-appareil', 'AccountController@newDevice');
$router->get('/{lang}/mon-compte/appareil/confirmer', 'AccountController@confirmDevice');
$router->get('/{lang}/mon-compte/appareil/annuler',   'AccountController@cancelMfa');
$router->post('/{lang}/mon-compte/securite/appareils/retirer-confiance', 'AccountController@untrustDevice');
$router->post('/{lang}/mon-compte/securite/reinitialiser', 'AccountController@resetSecurity');
$router->post('/{lang}/mon-compte/securite/appareils/supprimer-toutes', 'AccountController@untrustAllDevices');
$router->get('/{lang}/compte/reactiver', 'AccountController@reactivateAccount');
$router->get('/{lang}/mon-compte/export', 'AccountController@exportPage');
$router->get('/{lang}/mon-compte/export/telecharger', 'AccountController@exportData');
$router->get('/{lang}/newsletter/desabonnement', 'AccountController@unsubscribePage');
$router->post('/{lang}/newsletter/desabonnement', 'AccountController@unsubscribe');

// ============================================================
// Routes API (AJAX)
// ============================================================
$router->post('/api/cart/add', 'Api\CartApiController@add');
$router->post('/api/cart/update', 'Api\CartApiController@update');
$router->post('/api/cart/remove', 'Api\CartApiController@remove');
$router->post('/api/favorites/toggle', 'Api\FavoriteApiController@toggle');
$router->get('/api/mfa/poll',          'Api\MfaController@poll');
$router->post('/api/jeux/score',       'GameScoreController@save');
$router->get('/api/jeux/score',        'GameScoreController@get');
$router->get('/api/meteo',             'WeatherController@current');

// Bons de commande (téléchargement public, servi via PHP)
$router->get('/bons-de-commande/{id}/telecharger', 'OrderFormController@download');
