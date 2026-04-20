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
$router->post('/admin/vins/{id}/featured', 'Admin\WineAdminController@setFeatured');
$router->post('/admin/vins/{id}/disponibilite', 'Admin\WineAdminController@toggleAvailable');
$router->get('/admin/commandes', 'Admin\OrderAdminController@index');
$router->get('/admin/commandes/{id}', 'Admin\OrderAdminController@show');
$router->post('/admin/commandes/{id}/statut', 'Admin\OrderAdminController@updateStatus');
$router->post('/admin/commandes/{id}/facture', 'Admin\OrderAdminController@uploadInvoice');
$router->get('/admin/commandes/{id}/facture/telecharger', 'Admin\OrderAdminController@downloadInvoice');
$router->get('/admin/commandes/{id}/facture/historique/{fileId}', 'Admin\OrderAdminController@downloadInvoiceFile');
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
$router->get('/admin/newsletter/segment-count', 'Admin\NewsletterAdminController@segmentCount');
$router->get('/admin/newsletter/{id}', 'Admin\NewsletterAdminController@show');
$router->post('/admin/newsletter/envoyer', 'Admin\NewsletterAdminController@send');
$router->get('/admin/bons-de-commande', 'Admin\OrderFormAdminController@index');
$router->post('/admin/bons-de-commande/ajouter', 'Admin\OrderFormAdminController@upload');
$router->post('/admin/bons-de-commande/{id}/supprimer', 'Admin\OrderFormAdminController@delete');
$router->get('/admin/bons-de-commande/{id}/telecharger', 'Admin\OrderFormAdminController@download');
$router->get('/admin/statistiques', 'Admin\StatsAdminController@index');
$router->get('/admin/marketing', 'Admin\MarketingAdminController@index');
$router->get('/admin/factures', 'Admin\InvoiceAdminController@index');
$router->get('/admin/factures/export', 'Admin\InvoiceAdminController@export');
$router->get('/admin/dpo', 'Admin\DpoAdminController@index');
$router->get('/admin/dpo/registre-traitements', 'Admin\DpoAdminController@downloadRegistre');
$router->get('/admin/dpo/sous-traitants', 'Admin\DpoAdminController@downloadSousTraitants');
$router->get('/admin/dpo/procedure-violation', 'Admin\DpoAdminController@downloadProcedure');
$router->get('/admin/dpo/matrice-habilitations', 'Admin\DpoAdminController@downloadMatrice');
$router->get('/admin/dpo/dpia', 'Admin\DpoAdminController@downloadDpia');
$router->get('/admin/dpo/pages-legales', 'Admin\LegalDocumentAdminController@index');
$router->get('/admin/dpo/pages-legales/{slug}/modifier', 'Admin\LegalDocumentAdminController@edit');
$router->post('/admin/dpo/pages-legales/{slug}/modifier', 'Admin\LegalDocumentAdminController@store');
$router->get('/admin/dpo/pages-legales/{slug}/confirmer-notification', 'Admin\LegalDocumentAdminController@confirmNotify');
$router->post('/admin/dpo/pages-legales/{slug}/confirmer-notification', 'Admin\LegalDocumentAdminController@sendNotifications');
$router->get('/admin/securite', 'Admin\ProfileAdminController@index');
$router->post('/admin/securite/mot-de-passe', 'Admin\ProfileAdminController@changePassword');
$router->post('/admin/securite/session/{id}/revoquer', 'Admin\ProfileAdminController@revokeSession');
$router->post('/admin/securite/sessions/revoquer-toutes', 'Admin\ProfileAdminController@revokeAllSessions');
$router->post('/admin/securite/appareils/retirer-confiance', 'Admin\ProfileAdminController@untrustDevice');
$router->post('/admin/securite/appareils/supprimer-toutes', 'Admin\ProfileAdminController@untrustAllDevices');
$router->post('/admin/securite/reinitialiser', 'Admin\ProfileAdminController@resetSecurity');
$router->get('/admin/logs', 'Admin\AuditLogAdminController@index');
$router->get('/admin/logs/export', 'Admin\AuditLogAdminController@export');
$router->get('/admin/admins', 'Admin\AdminGestionAdminsController@index');
$router->post('/admin/admins/{id}/bloquer', 'Admin\AdminGestionAdminsController@block');
$router->post('/admin/admins/{id}/debloquer', 'Admin\AdminGestionAdminsController@unblock');
$router->get('/admin/rgpd/demandes', 'Admin\RgpdAdminController@index');
$router->get('/admin/rgpd/demandes/ajouter', 'Admin\RgpdAdminController@create');
$router->post('/admin/rgpd/demandes/ajouter', 'Admin\RgpdAdminController@store');
$router->post('/admin/rgpd/demandes/{id}/statut', 'Admin\RgpdAdminController@updateStatus');

$router->get('/admin/rgpd/demandes/{id}/export-portabilite', 'Admin\RgpdAdminController@exportPortabilite');

// ============================================================
// Cron (IONOS HTTP GET — protégé par token secret)
// ============================================================
$router->get('/cron/{job}', 'CronController@run');

// ============================================================
// Routes publiques
// ============================================================

// Rating (email token + espace client)
$router->get('/rating/commande/{token}/{note}', 'RatingController@rateViaEmail');
$router->get('/rating/order/{orderToken}', 'RatingController@rateOrder');
$router->post('/api/rating/commande', 'RatingController@rateViaTokenApi');
$router->post('/{lang}/rating/vin/{wineId}', 'RatingController@rateViaAccount');

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
$router->get('/{lang}/conditions-generales-de-vente', 'PageController@conditionsGeneralesVente');
$router->get('/{lang}/plan-du-site', 'PageController@planDuSite');
$router->get('/sitemap.xml', 'PageController@sitemapXml');
$router->get('/{lang}/support', 'PageController@support');
$router->get('/{lang}/jeux', 'PageController@jeux');
$router->get('/{lang}/credits-medias', 'PageController@creditsMedias');
$router->get('/{lang}/galerie', 'PageController@galerie');
$router->get('/{lang}/accessibilite', 'PageController@accessibilite');
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
$router->get('/{lang}/auth/google', 'GoogleOAuthController@authorize');
$router->get('/{lang}/auth/google/callback', 'GoogleOAuthController@callback');
$router->get('/{lang}/auth/google/type-compte', 'GoogleOAuthController@accountTypePending');
$router->post('/{lang}/auth/google/type-compte', 'GoogleOAuthController@accountTypeSubmit');
$router->get('/{lang}/auth/google/link', 'GoogleOAuthController@linkConfirm');
$router->get('/{lang}/auth/google/link/verify', 'GoogleOAuthController@linkVerifyByToken');
$router->post('/{lang}/auth/google/link', 'GoogleOAuthController@linkConfirmPost');

$router->post('/{lang}/connexion', 'AuthController@login');
$router->post('/{lang}/inscription', 'AuthController@register');
$router->post('/{lang}/renvoyer-activation', 'AuthController@resendActivation');
$router->get('/{lang}/deconnexion', 'AuthController@logout');   // Retourne 405 — protection CSRF passive
$router->post('/{lang}/deconnexion', 'AuthController@logout');
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

// Paiement CA — retours navigateur
$router->get('/{lang}/commande/paiement-ca/ok', 'OrderController@paymentReturnOk');
$router->get('/{lang}/commande/paiement-ca/annule', 'OrderController@paymentReturnCancel');
$router->get('/{lang}/commande/paiement-ca/refuse', 'OrderController@paymentReturnRefuse');

// IPN CA — server-to-server (sans authentification)
$router->post('/payment/ipn', 'IpnController@handle');

// Espace client — Profil & tableau de bord
$router->get('/{lang}/mon-compte', 'Account\AccountProfileController@index');
$router->get('/{lang}/mon-compte/profil', 'Account\AccountProfileController@profile');
$router->post('/{lang}/mon-compte/profil', 'Account\AccountProfileController@updateProfile');
$router->post('/{lang}/mon-compte/preference-langue', 'Account\AccountProfileController@updateLangPreference');
$router->post('/{lang}/mon-compte/profil/changer-email', 'Account\AccountProfileController@requestEmailChange');
$router->post('/{lang}/mon-compte/email/annuler', 'Account\AccountProfileController@cancelEmailChange');
$router->get('/{lang}/mon-compte/email/confirmer', 'Account\AccountProfileController@confirmEmailChange');
$router->get('/{lang}/mon-compte/email/revoquer', 'Account\AccountProfileController@revokeEmailChange');

// Espace client — Commandes
$router->get('/{lang}/mon-compte/commandes', 'Account\AccountOrderController@orders');
$router->get('/{lang}/mon-compte/commandes/{id}', 'Account\AccountOrderController@orderDetail');
$router->post('/{lang}/mon-compte/commandes/{id}/annuler', 'Account\AccountOrderController@cancelOrder');
$router->post('/{lang}/mon-compte/commandes/{id}/remboursement', 'Account\AccountOrderController@requestRefund');
$router->get('/{lang}/mon-compte/factures', 'InvoiceController@list');
$router->get('/{lang}/mon-compte/commandes/{id}/facture', 'InvoiceController@download');
$router->get('/{lang}/mon-compte/commandes/{id}/fiche-retour', 'Account\AccountOrderController@returnSlip');

// Espace client — Adresses
$router->get('/{lang}/mon-compte/adresses', 'Account\AccountAddressController@addresses');
$router->post('/{lang}/mon-compte/adresses/ajouter', 'Account\AccountAddressController@addAddress');
$router->get('/{lang}/mon-compte/adresses/{id}/modifier', 'Account\AccountAddressController@editAddress');
$router->post('/{lang}/mon-compte/adresses/{id}/modifier', 'Account\AccountAddressController@updateAddress');
$router->post('/{lang}/mon-compte/adresses/{id}/supprimer', 'Account\AccountAddressController@deleteAddress');

// Espace client — Préférences & favoris
$router->get('/{lang}/mon-compte/favoris', 'Account\AccountPreferenceController@favorites');

// Espace client — Sécurité
$router->get('/{lang}/mon-compte/securite', 'Account\AccountSecurityController@security');
$router->post('/{lang}/mon-compte/securite/mot-de-passe', 'Account\AccountSecurityController@changePassword');
$router->post('/{lang}/mon-compte/securite/session/{id}/revoquer', 'Account\AccountSecurityController@revokeSession');
$router->post('/{lang}/mon-compte/securite/supprimer-compte', 'Account\AccountSecurityController@deleteAccount');
$router->post('/{lang}/mon-compte/securite/sessions/revoquer-toutes', 'Account\AccountSecurityController@revokeAllUserSessions');
$router->get('/{lang}/mon-compte/nouvel-appareil', 'Account\AccountSecurityController@newDevice');
$router->get('/{lang}/mon-compte/appareil/confirmer', 'Account\AccountSecurityController@confirmDevice');
$router->get('/{lang}/mon-compte/appareil/annuler', 'Account\AccountSecurityController@cancelMfa');
$router->post('/{lang}/mon-compte/securite/appareils/retirer-confiance', 'Account\AccountSecurityController@untrustDevice');
$router->post('/{lang}/mon-compte/securite/reinitialiser', 'Account\AccountSecurityController@resetSecurity');
$router->post('/{lang}/mon-compte/securite/appareils/supprimer-toutes', 'Account\AccountSecurityController@untrustAllDevices');
$router->get('/{lang}/compte/reactiver', 'Account\AccountSecurityController@reactivateAccount');
$router->get('/{lang}/session-expiree', 'AuthController@sessionExpired');
$router->get('/{lang}/securite/confirmer', 'Account\AccountSecurityController@confirmSecurityAction');
$router->get('/{lang}/securite/ce-netait-pas-moi', 'Account\AccountSecurityController@denySecurityAction');

// Espace client — Export RGPD
$router->get('/{lang}/mon-compte/export', 'Account\AccountExportController@exportPage');
$router->post('/{lang}/mon-compte/export/envoyer', 'Account\AccountExportController@exportSend');

// Newsletter — désabonnement
$router->get('/{lang}/newsletter/desabonnement', 'Account\AccountPreferenceController@unsubscribePage');
$router->post('/{lang}/newsletter/desabonnement', 'Account\AccountPreferenceController@unsubscribe');

// Newsletter — double opt-in (RGPD Art. 7)
$router->get('/{lang}/newsletter/confirmation', 'NewsletterController@confirmSubscription');
$router->post('/{lang}/newsletter/inscription', 'NewsletterController@subscribe');

// Newsletter — abonnement depuis email de bienvenue (one-click)
$router->get('/fr/newsletter/inscription-bienvenue', 'NewsletterController@subscribeFromWelcome');
$router->get('/en/newsletter/welcome-subscribe', 'NewsletterController@subscribeFromWelcome');

// Déclaration de majorité (Art. L3342-1 CSP)
$router->post('/{lang}/declaration-majorite', 'MajorityController@declare');

// ============================================================
// Routes API (AJAX)
// ============================================================
$router->get('/api/cart/count', 'Api\CartApiController@count');
$router->get('/api/cart/details', 'Api\CartApiController@details');
$router->post('/api/cart/add', 'Api\CartApiController@add');
$router->post('/api/cart/update', 'Api\CartApiController@update');
$router->post('/api/cart/remove', 'Api\CartApiController@remove');
$router->post('/api/favorites/toggle', 'Api\FavoriteApiController@toggle');
$router->get('/api/mfa/poll', 'Api\MfaController@poll');
$router->get('/api/google-link-status', 'Api\GoogleLinkStatusController@status');
$router->get('/api/session/ping', 'Api\SessionApiController@ping');
$router->post('/api/jeux/score', 'GameScoreController@save');
$router->get('/api/jeux/score', 'GameScoreController@get');
$router->get('/api/meteo', 'WeatherController@current');
$router->post('/api/log/client-error', 'Api\LogApiController@clientError');

// Tracking newsletter (pixel ouverture + redirect clic)
$router->get('/track/open/{id}/{token}', 'TrackingController@open');
$router->get('/track/click/{id}/{linkId}/{token}', 'TrackingController@click');

// Bons de commande (téléchargement public, servi via PHP)
$router->get('/bons-de-commande/{id}/telecharger', 'OrderFormController@download');

// Security.txt (RFC 9116) — généré dynamiquement depuis les variables d'environnement
$router->get('/.well-known/security.txt', 'PageController@securityTxt');
