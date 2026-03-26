# Changelog

All notable changes to Crabitan Bellevue are documented here.

# [0.12.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.11.0...v0.12.0) (2026-03-26)


### Bug Fixes

* **core:** index.php — catch \Throwable → page 500 branded pour toute exception non gérée ([1ad8d04](https://github.com/ApoSkunz/crabitan_bellevue/commit/1ad8d047bca52a29de44a2ffe79a7fe6d14d43f9))


### Features

* **controller+view:** vins admin — appellation restreinte AOC/IGP/STG/AOP ([4e672f4](https://github.com/ApoSkunz/crabitan_bellevue/commit/4e672f4646355d703d3f850464b39fddaec4c816))
* **controller:** contact — passe $message à sendContactConfirmation pour le récapitulatif client ([610ea63](https://github.com/ApoSkunz/crabitan_bellevue/commit/610ea63ed93064915ffbd4ffdea2698241be5ee6))
* **controller:** GameScoreController API POST/GET + routes + PageController WR ([7003d89](https://github.com/ApoSkunz/crabitan_bellevue/commit/7003d89d5a4f82db623f15659011acf8cec36c24))
* **controller:** jeux — wrVendangeExpress + ALLOWED_GAMES vendangeexpress + 9 paires mémo ([fdd154b](https://github.com/ApoSkunz/crabitan_bellevue/commit/fdd154b28f550728979cf73c961816d84f6e0430))
* **controller:** WeatherController — proxy /api/meteo vers WeatherAPI.com (clé serveur) ([946c726](https://github.com/ApoSkunz/crabitan_bellevue/commit/946c726572b76ad3bb0f062cd7f49670ab71f154))
* **game:** Labour Chrono + Tonneau Catapulte + Vendange Express + La Cave aux Secrets ([a92b598](https://github.com/ApoSkunz/crabitan_bellevue/commit/a92b5982de2e94dcc82cb93a9a5e523744656624))
* **game:** lazy-import Labour Chrono, Tonneau Catapulte, Vendange Express dans main.js ([03fc5bd](https://github.com/ApoSkunz/crabitan_bellevue/commit/03fc5bd6e38f29e14ebdf4b57123401049326442))
* **game:** vendangeuse — pause/play/rejouer + accélération réduite + fix keyboard scope ([431ccb9](https://github.com/ApoSkunz/crabitan_bellevue/commit/431ccb93c0af43faed4177823d71775d8ea7d42a))
* **game:** world record mémo + jeu Trial tracteur canvas 2D ([e88e9be](https://github.com/ApoSkunz/crabitan_bellevue/commit/e88e9bee2118ea9ffa3940eba47af427cec8ae17))
* **i18n:** contact — clé contact.error_rgpd FR/EN ([123b7b6](https://github.com/ApoSkunz/crabitan_bellevue/commit/123b7b6a5d82bb97267c69a2f1dccbdbf59682cb))
* **i18n:** jeux — Labour Chrono, Tonneau Catapulte, Vendange Express, La Cave aux Secrets (mémo renommé) ([aef293d](https://github.com/ApoSkunz/crabitan_bellevue/commit/aef293de9443000091b67fbb3eacc0acc5ee8b26))
* **i18n:** jeux tracteur_title/desc FR+EN ([5b8ea0c](https://github.com/ApoSkunz/crabitan_bellevue/commit/5b8ea0ce0219c9de52c57dbc1deb50bf6a4eaa56))
* **i18n:** news — clé news.nav_label FR/EN ([8040dc7](https://github.com/ApoSkunz/crabitan_bellevue/commit/8040dc7aa6865efee30b34ce13306b36e75590b4))
* **jeux:** add runner game La Vigneronne — canvas 2D T-Rex style ([a5ccc7c](https://github.com/ApoSkunz/crabitan_bellevue/commit/a5ccc7c6ae342f9b0259ccde45721a7912d7a548))
* **jeux:** bouton Démarrer/Rejouer + fix shuffle + espacement + images ([2e93921](https://github.com/ApoSkunz/crabitan_bellevue/commit/2e93921074f5db11f46bfaddfa06f76fe8b14e74))
* **js:** contact — erreur RGPD inline + secousse + succès 3 s + pas de banner global si seul champ manquant ([ccd3e39](https://github.com/ApoSkunz/crabitan_bellevue/commit/ccd3e39916b519a64e4b7a6df4b81dd96534f64a))
* **js:** widget météo — migration Open-Meteo → proxy /api/meteo, suppression tables WMO ([5450218](https://github.com/ApoSkunz/crabitan_bellevue/commit/545021810b9aad9263b7d83d54ae49be94ba9fd0))
* **model,controller:** news — pagination 9/page + getPrev/getNext pour nav article ([04ee523](https://github.com/ApoSkunz/crabitan_bellevue/commit/04ee5230b2bf442317cd33a2167d8b8c7157934c))
* **model:** add WineModel::getRandomForMemo() — 14 vins aléatoires avec image ([baebea9](https://github.com/ApoSkunz/crabitan_bellevue/commit/baebea9045f6f3dbd915a0b8cc24d1e84be07e7c))
* **model:** GameScoreModel — getBestScore + updateIfBetter (upsert) ([a5605f4](https://github.com/ApoSkunz/crabitan_bellevue/commit/a5605f4b875b47f081fae8b7b5937c3c8ddb9a53))
* **routes:** ajout GET /api/meteo → WeatherController@current ([9f2f4ab](https://github.com/ApoSkunz/crabitan_bellevue/commit/9f2f4ab065afa43afb48ce02c544e4c84711896c))
* **scss:** btn-social — gap + taille logo + inversion Apple logo dark mode ([0fd9227](https://github.com/ApoSkunz/crabitan_bellevue/commit/0fd92274c8f462004b1298f4cd1ed96044a8a13b))
* **scss:** contact — style message erreur inline RGPD + shake sur checkbox ([66f717a](https://github.com/ApoSkunz/crabitan_bellevue/commit/66f717afb76111b2de7e495ad52d03b36ee80604))
* **scss:** jeux — Labour Chrono, Tonneau Catapulte, Vendange Express canvas + animations mémo is-wrong/is-matched-flash + grille 9 paires 3×6 ([15b4e01](https://github.com/ApoSkunz/crabitan_bellevue/commit/15b4e0139d434e12b0283c82d355c8cd035c5554))
* **scss:** news — .news-list padding, .news-article layout image+texte, nav prev/next, date--lg ([cf4de5d](https://github.com/ApoSkunz/crabitan_bellevue/commit/cf4de5db799932c63ecb92a572344238a7f19dc9))
* **service:** MailService — email owner HTML branded + Reply-To + sujet "Contact site : {label}" + recap client + pièce jointe bon de commande ([329b3df](https://github.com/ApoSkunz/crabitan_bellevue/commit/329b3df2654184c3e304f92fbbba2583ccc98f4a))
* **view,scss,js:** météo — attribution WeatherAPI.com sous le widget, cliquable, taille lisible ([e287143](https://github.com/ApoSkunz/crabitan_bellevue/commit/e287143d6acb86a51c14b9d5bd21e69b67b8fca4))
* **view,scss:** météo — attribution Open-Meteo CC BY 4.0 + style lien discret carousel ([e33576b](https://github.com/ApoSkunz/crabitan_bellevue/commit/e33576b0a88d6391d57a2e15c000931af4b6aef8))
* **view:** contact — message d'erreur inline sous la case RGPD ([31b5b54](https://github.com/ApoSkunz/crabitan_bellevue/commit/31b5b54ef44e2fdbd916d8558f3be188cea47a90))
* **view:** jeux — section hill-climb tracteur + data-world-record ([e4b2521](https://github.com/ApoSkunz/crabitan_bellevue/commit/e4b25211cf77f12f47f59191ea9731d7ec1b93f5))
* **view:** jeux — sections Labour Chrono, Tonneau Catapulte, Vendange Express + suppression hill-climb ([9590f2f](https://github.com/ApoSkunz/crabitan_bellevue/commit/9590f2f4598e94a92ed70c6f3f35cb50bd2fb94f))
* **view:** modals — icônes SVG œil/œil-barré + logos Google/Apple sur boutons sociaux ([e4b795b](https://github.com/ApoSkunz/crabitan_bellevue/commit/e4b795bee5f0a8eb78cb08184fcbd5d79c5602d5))
* **view:** news — pagination liste + vue détail image/texte côte à côte + nav prev/next ([d434e26](https://github.com/ApoSkunz/crabitan_bellevue/commit/d434e26ee2b95cba47c97d32985064ca508a05db))

# [0.11.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.10.0...v0.11.0) (2026-03-25)


### Bug Fixes

* **admin:** champs EN readonly + badges CI + fix TI login redirect ([118117b](https://github.com/ApoSkunz/crabitan_bellevue/commit/118117b5d08fbcc0d45f30739a24113a1bc10b57))
* **admin:** retours UX round 4 — dashboard CA, filtres, tarifs, slugs, newsletter ([ab502b3](https://github.com/ApoSkunz/crabitan_bellevue/commit/ab502b36adf5ad95c06532bc9e2a558077ece902))
* **admin:** retours UX round 5 — per_page, checkboxes, tarifs, slug, modal newsletter, favicon ([0529d57](https://github.com/ApoSkunz/crabitan_bellevue/commit/0529d5759337075799bda7d8ee7955fedef588d1))
* **admin:** UX rounds 6-7 — per_page, checkboxes, slug BDD, login redirect, newsletter image ([bd9c43f](https://github.com/ApoSkunz/crabitan_bellevue/commit/bd9c43f003741763be9311137a7226a550336abe))
* **auth:** admin reste sur la page courante après connexion ([70b45f5](https://github.com/ApoSkunz/crabitan_bellevue/commit/70b45f5cecffcf82c3970f09b56ef28eada92df4))
* **ci:** CodeQL — retrait PHP (non supporté), revert à javascript uniquement ([10ccba6](https://github.com/ApoSkunz/crabitan_bellevue/commit/10ccba64aa4640b59a5f4e224d4ef61820cd9d81))
* **model:** OrderFormModel.getLatest() — id DESC comme tiebreaker ([5dc7247](https://github.com/ApoSkunz/crabitan_bellevue/commit/5dc7247468994045b67bcc288270ea8f7a10f325))
* **panel:** label Administration centré et doré + masquage panier admins ([f02971b](https://github.com/ApoSkunz/crabitan_bellevue/commit/f02971b2c67810b0adbf2f4eb9ac1a27c8051c3d))
* **phpcs:** indentation ligne 61 dashboard.php ([dda3496](https://github.com/ApoSkunz/crabitan_bellevue/commit/dda34960a8a057718a45c6be814425ab05c4ad75))
* **sast:** nosemgrep md5 dans WineController — usage filename uniquement ([42d2a76](https://github.com/ApoSkunz/crabitan_bellevue/commit/42d2a7643ec042f3ae3ae3ae1196fc2d759a3ac1))
* **sca:** override tmp@^0.2.4 — corrige GHSA-52f5-9888-hmc6 (symlink write) ([bd6ec2b](https://github.com/ApoSkunz/crabitan_bellevue/commit/bd6ec2bb34e2607a0b027a0179b34156c017df77))
* **security:** chmod 0750 sur mkdir storage/ — invoices et order_forms ([545de77](https://github.com/ApoSkunz/crabitan_bellevue/commit/545de778968ce53637ffa46b8174c5c1a11cd527))
* **sonar:** controllers admin — constants S1192, \$_params S1172, NOSONAR S1142/S3776 ([fb5f3f5](https://github.com/ApoSkunz/crabitan_bellevue/commit/fb5f3f53755bd77838b40e197dc0b0285c107922))
* **sonar:** models/service/cart — NOSONAR S107/S4144, buildWhereClause S1192 ([c404600](https://github.com/ApoSkunz/crabitan_bellevue/commit/c40460030c791d918c779a70bf646c37f1a818f7))
* **sonar:** NOSONAR S1172/S1142/S3776 sur lignes déclarées (15 × \$_params + 3 méthodes) ([d488ac3](https://github.com/ApoSkunz/crabitan_bellevue/commit/d488ac31acb7b2a7968f13029aa000f453acecf3))
* **sonar:** S3973 — accolades sur guards function_exists dans vues admin ([96b54b4](https://github.com/ApoSkunz/crabitan_bellevue/commit/96b54b4cd05164f8beeb669232cdb98b0a40fb08))
* **sonar:** vues admin — duplicate id S7930, is-error S1192, accessibilité S6851/S6853/S7927 ([3003efb](https://github.com/ApoSkunz/crabitan_bellevue/commit/3003efb4e015bff2ee025fc840f7d5e0ffff137b))
* **view:** \$isAdmin par défaut dans vues vins publiques ([a142f6a](https://github.com/ApoSkunz/crabitan_bellevue/commit/a142f6a90e088fae89d10601090ae207d44cba0c))
* **view:** guards function_exists sur fonctions inline des vues admin (redéclaration PHPUnit) ([5aadbf6](https://github.com/ApoSkunz/crabitan_bellevue/commit/5aadbf69944bcc48a8097c0ba251f4d39b50662c))


### Features

* **admin:** back-office complet + masquage panier pour admins ([366c1c1](https://github.com/ApoSkunz/crabitan_bellevue/commit/366c1c1e8026d1bb23d2705a502ff92ba71ccdb0))
* **admin:** commandes — per_page, filtre paiement, facture PDF sécurisée ([934cece](https://github.com/ApoSkunz/crabitan_bellevue/commit/934cece603dd247121ccb2c15add1e2a27433945))
* **admin:** favicon CB doré, CA annuel dashboard, suppression delivery_tracking ([7311f9d](https://github.com/ApoSkunz/crabitan_bellevue/commit/7311f9db8b1947f7c4225bcefabe939195a1f127)), closes [#c9a84c](https://github.com/ApoSkunz/crabitan_bellevue/issues/c9a84c)
* **admin:** news/newsletter CRUD + traduction backend + fix 500 super_admin + seeds enrichis ([1bf03e3](https://github.com/ApoSkunz/crabitan_bellevue/commit/1bf03e353f9297b91769b39949cffa6c9de67aac))
* **auth:** rester sur la page après connexion + spinner + toast succès ([a913879](https://github.com/ApoSkunz/crabitan_bellevue/commit/a9138793dc399e2aef538227584fe3ce7c561a29))
* **ci:** E2E badge auto + Semgrep exhaustif + CodeQL PHP ([da28f24](https://github.com/ApoSkunz/crabitan_bellevue/commit/da28f241783bb3e7b4a2dc2e8fff89cb84d7a00b))
* **controller:** bons de commande — admin CRUD + download public + contact ([c4d3a7b](https://github.com/ApoSkunz/crabitan_bellevue/commit/c4d3a7bb0f3ee0e3096b7c249410e7ed413a257a))
* **i18n:** clés contact.order_form_* fr/en pour section bon de commande ([168bf23](https://github.com/ApoSkunz/crabitan_bellevue/commit/168bf2350581542630db6c60e2f49dac6d29b8d8))
* **model:** OrderFormModel — CRUD + pagination bons de commande ([7c9bd99](https://github.com/ApoSkunz/crabitan_bellevue/commit/7c9bd99cbe5cde8a0181fad664002cb50398960c))
* **routes:** bons de commande — routes admin + download public ([ebe6d9f](https://github.com/ApoSkunz/crabitan_bellevue/commit/ebe6d9fd099a76abc96c1d5968d112644ac2315b))
* **view:** bons de commande — vue admin + nav + section contact ([65830ed](https://github.com/ApoSkunz/crabitan_bellevue/commit/65830ed9362a7045c2a0f8a45671d93223f5198f))

# [0.10.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.9.0...v0.10.0) (2026-03-25)


### Bug Fixes

* **security:** mot de passe minimum 12 caractères + PLAN.md docs mis à jour ([a3e61b9](https://github.com/ApoSkunz/crabitan_bellevue/commit/a3e61b9cfade113719b1cfd66017ff3aa8c9bc42))


### Features

* **auth:** modal inscription + suppression vue /inscription ([50f96bf](https://github.com/ApoSkunz/crabitan_bellevue/commit/50f96bf0bd48698d5e6198ca45f7dc78fb52747b))
* **auth:** modal mot de passe oublié + vérification email + reset modal + MailHog ([26f68cd](https://github.com/ApoSkunz/crabitan_bellevue/commit/26f68cd78d43a4403a8b4cbe5d0d143b1158ee81))
* **ui:** widget météo carousel + nav bar or en light theme ([400ebd5](https://github.com/ApoSkunz/crabitan_bellevue/commit/400ebd53c0b49f87c5b3e14028d49c2e62c75c52)), closes [#c9a84c](https://github.com/ApoSkunz/crabitan_bellevue/issues/c9a84c) [#2a2218](https://github.com/ApoSkunz/crabitan_bellevue/issues/2a2218)

# [0.9.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.8.1...v0.9.0) (2026-03-25)


### Bug Fixes

* **auth:** supprimer toutes les redirections vers /connexion (GET supprimé) ([8ab1dfa](https://github.com/ApoSkunz/crabitan_bellevue/commit/8ab1dfa342dd02bac2177b70645b0235c560ffcc))


### Features

* **auth:** login modal dans le header + suppression vue /connexion ([d11d7e3](https://github.com/ApoSkunz/crabitan_bellevue/commit/d11d7e3bc3cd6a0b94884711965ec48bf6320ad2))

## [0.8.1](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.8.0...v0.8.1) (2026-03-25)


### Bug Fixes

* **ci:** align DB name crabitan_bellevue + fix seed/transaction TI failures ([532ab07](https://github.com/ApoSkunz/crabitan_bellevue/commit/532ab070d41c9230281387bb2907201e25c2795a))
* **seed:** remove ALTER TABLE IF NOT EXISTS incompatible MySQL 8.0 ([44e7ff3](https://github.com/ApoSkunz/crabitan_bellevue/commit/44e7ff3330e7b0982722fe290378faab424b0b0c))

# [0.8.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.7.0...v0.8.0) (2026-03-25)


### Bug Fixes

* **cart:** cookie storage + badge count + remove offline toast ([dff73bf](https://github.com/ApoSkunz/crabitan_bellevue/commit/dff73bfe386f100bdf618eccc8b6e67199f7dcfe))
* **cart:** shorten toast duration + update login prompt message ([7bd68f2](https://github.com/ApoSkunz/crabitan_bellevue/commit/7bd68f286ee7012ddaee4d02ba896393a17cdece))
* **ci:** add -fL to curl for exakat.phar download (follow redirects + fail on HTTP error) ([cdf3788](https://github.com/ApoSkunz/crabitan_bellevue/commit/cdf37882870bed0cdddfcd5694f5caf01f07b113))
* **ci:** add is_cuvee_speciale to schema + remove technical_form_path + cart stub ([a572c1e](https://github.com/ApoSkunz/crabitan_bellevue/commit/a572c1eedb3cc786d7423c2f9569ec44735b42cb))
* **ci:** debug Exakat output path + use relative -R . + upload projects/ ([e90d3a4](https://github.com/ApoSkunz/crabitan_bellevue/commit/e90d3a48d039fa41e952d95583a6e6eb207c7cbe))
* **ci:** expose Exakat output + locate real write path ([ca3a36f](https://github.com/ApoSkunz/crabitan_bellevue/commit/ca3a36fb50a18645be46103e0f2993ca428411b1))
* **ci:** fix Exakat empty report — upload project dir instead of stdout redirect ([df60297](https://github.com/ApoSkunz/crabitan_bellevue/commit/df6029791189a98c805b9d8d6374e6dc3bbe3e6d))
* **ci:** replace broken Exakat Docker setup with phar approach + update CodeQL to v3 ([a9eec73](https://github.com/ApoSkunz/crabitan_bellevue/commit/a9eec73baeae32a274c6ccfdba3ef4668f40feba))
* **e2e:** align sitemap test with actual .sitemap-card selector ([e788216](https://github.com/ApoSkunz/crabitan_bellevue/commit/e788216021f810a82cdb9ee2c0c2b33892dc0e2e))
* **e2e:** correct baseURL fallback + fix cart/contact tests ([a4797e9](https://github.com/ApoSkunz/crabitan_bellevue/commit/a4797e96b2faa18d6a0f766444c5078055fe4d98)), closes [#contact-feedback](https://github.com/ApoSkunz/crabitan_bellevue/issues/contact-feedback)
* php exakat ([94e493d](https://github.com/ApoSkunz/crabitan_bellevue/commit/94e493d274b38e8690f9dfe479563e04d556af1b))
* php exakat ([1af8d4a](https://github.com/ApoSkunz/crabitan_bellevue/commit/1af8d4afc75b9ef5425ecc4be89af0051abd6a0c))
* **sonar:** reduce cognitive complexity + fix all main branch issues ([6692b0f](https://github.com/ApoSkunz/crabitan_bellevue/commit/6692b0f2ffe410ed8b3079809996edce7734caec))
* **ux:** cookie consent → cookie 13 mois + fix Sonar duplicate id ([9e3fc56](https://github.com/ApoSkunz/crabitan_bellevue/commit/9e3fc564d282157ffbebab2ea0ebaf028c0b9d06))
* **webmaster:** replace profile photo with better-cropped portrait ([0298d38](https://github.com/ApoSkunz/crabitan_bellevue/commit/0298d3884204fbe28f78a9f4d84c674f58558759))


### Features

* add codeql audit ([acc1db8](https://github.com/ApoSkunz/crabitan_bellevue/commit/acc1db837e4e9e4af635bc7342e78228b87106a0))
* **contact:** formulaire AJAX complet + CSRF + PHPMailer + UX shake/scroll ([0d083f9](https://github.com/ApoSkunz/crabitan_bellevue/commit/0d083f94c8adbb1f24759f34b4fdb73b7d3d2fd7))
* **pages:** add Contact, Plan du site & Webmaster pages with full content ([3e72491](https://github.com/ApoSkunz/crabitan_bellevue/commit/3e724917d415ed4d11147fa96cc59e4229df4e47))
* remove PHP from language codeql, add exakat sast audit ([6505d72](https://github.com/ApoSkunz/crabitan_bellevue/commit/6505d72d53588826b7bfe3bb39ba21477815ac93))
* **sitemap+assets:** reseed wines/news from prod, rework plan-du-site layout ([5477cfa](https://github.com/ApoSkunz/crabitan_bellevue/commit/5477cfac9f52c2d5a68ea5db40d682de1f6ac64c))
* **ux:** animation d'arrivée post age-gate (intro overlay) ([eab0287](https://github.com/ApoSkunz/crabitan_bellevue/commit/eab0287b30988bca9e35c265aef07bf15691860f))
* **wines:** add is_cuvee_speciale boolean + show on boutique/collection/detail ([a62181e](https://github.com/ApoSkunz/crabitan_bellevue/commit/a62181e743e8933bed0f3358c62ca89d4699ef11))

# [0.7.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.6.1...v0.7.0) (2026-03-23)


### Bug Fixes

* **ci:** seed wines for E2E tests + skip unimplemented contact form tests ([12bac86](https://github.com/ApoSkunz/crabitan_bellevue/commit/12bac86ae8d7c372a1361ed6d8c9083601dcf58a))
* **e2e:** check localStorage instead of #header-cart-count for guest cart ([2b7082e](https://github.com/ApoSkunz/crabitan_bellevue/commit/2b7082e89ad5b899b716691a6c9761654b80d41c)), closes [#header-cart-count](https://github.com/ApoSkunz/crabitan_bellevue/issues/header-cart-count) [#header-cart-count](https://github.com/ApoSkunz/crabitan_bellevue/issues/header-cart-count)
* **e2e:** correct cart test selectors and fix PHPCS header in error.php ([dc9c4c3](https://github.com/ApoSkunz/crabitan_bellevue/commit/dc9c4c31fd281a3ee1285757199ec9aa6e8a8712)), closes [#cart-count](https://github.com/ApoSkunz/crabitan_bellevue/issues/cart-count) [#header-cart-count](https://github.com/ApoSkunz/crabitan_bellevue/issues/header-cart-count)
* **security:** neutralize XSS risk in carbon-badge.js (SAST react-unsanitized-method) ([cb72747](https://github.com/ApoSkunz/crabitan_bellevue/commit/cb72747d8ea4acd7667c8ad7000f61c3764575dd))
* **seed:** remove orphan award_image column from seed_wines.sql ([0cf4dcd](https://github.com/ApoSkunz/crabitan_bellevue/commit/0cf4dcd0b1e9321aa796314066377ca277e5947e))
* **sonar:** resolve 2 code smells + add missing coverage ([3f01878](https://github.com/ApoSkunz/crabitan_bellevue/commit/3f01878cdbbf13a7e576970d84df5be8265c6bbe))
* **sonar:** resolve nested ternary in Response + wrap li in ul in jeux ([0261e43](https://github.com/ApoSkunz/crabitan_bellevue/commit/0261e43f980f202136b3ea5f944ebd93788bbef0))
* **tests:** suppress PHPUnit notice on mock without expectations ([e49bcb8](https://github.com/ApoSkunz/crabitan_bellevue/commit/e49bcb8e8a63b43c8fa57c8aba5022e336d64168))


### Features

* **age-gate:** add FR/EN lang switcher + update slogan + add design charter ([5014a16](https://github.com/ApoSkunz/crabitan_bellevue/commit/5014a169a553372f58161c132b09f35a8224967d))
* **legal:** add politique de confidentialité page with full RGPD notice ([1d08ba7](https://github.com/ApoSkunz/crabitan_bellevue/commit/1d08ba71add9b1a01671560279f5f9ee1d0fc6b3))
* **pages:** add full content for Château, Savoir-faire, Mentions légales, Support FAQ, Jeux mémo ([7b26e63](https://github.com/ApoSkunz/crabitan_bellevue/commit/7b26e63d408f66ff369446962210c8f347cfefea))
* **ux:** light theme default, age-gate redesign, bare legal mode, E2E business tests ([1fd6fd3](https://github.com/ApoSkunz/crabitan_bellevue/commit/1fd6fd3fadaa0456f5a432d20ad59624ead48ec6)), closes [#theme-toggle](https://github.com/ApoSkunz/crabitan_bellevue/issues/theme-toggle)

## [0.6.1](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.6.0...v0.6.1) (2026-03-22)


### Bug Fixes

* **ui:** PDF inline + footer/home light-dark theme fixes, remove award_path ([cbfe373](https://github.com/ApoSkunz/crabitan_bellevue/commit/cbfe3730395b54706e39980f8ea2fdd2b8f5886c))

# [0.6.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.5.0...v0.6.0) (2026-03-22)


### Bug Fixes

* **catalogue:** use \$activeColor/\$activeSort in pagination buildUrl closure ([be58206](https://github.com/ApoSkunz/crabitan_bellevue/commit/be58206917235e3617c064f86d089c7501c79af6))
* **html:** move NOSONAR comments outside opening HTML tags ([5f87c05](https://github.com/ApoSkunz/crabitan_bellevue/commit/5f87c05003040ad5a83424d3500c73805e1b2a0b))
* **seed:** add TRUNCATE before INSERT to prevent duplicate key on re-import ([de7e5c7](https://github.com/ApoSkunz/crabitan_bellevue/commit/de7e5c7fe2b47d82ceb201e127ca5af013ecf50c))
* **seed:** remove trailing stock_alert_threshold value on last row ([26088e0](https://github.com/ApoSkunz/crabitan_bellevue/commit/26088e0cb7778b32bf86e88733c2493aa26bb263))
* **semgrep:** nosemgrep annotation on cart login redirect ([001566c](https://github.com/ApoSkunz/crabitan_bellevue/commit/001566c90ef9ff3da51df10db582fc36fde029bb))
* **sonar:** cover PDF helpers via reflection, fix cpd.exclusions, clean lang ([b38d653](https://github.com/ApoSkunz/crabitan_bellevue/commit/b38d6535133a968d0cf619c1a9c1133cc0e870cd))
* **sonar:** resolve all remaining code smells, add cpd.exclusions and new tests ([743aeb9](https://github.com/ApoSkunz/crabitan_bellevue/commit/743aeb9be8c7fc3c6516d427b9e62e508c82a97f))
* **sonar:** resolve quality gate failures and increase test coverage ([eabdd54](https://github.com/ApoSkunz/crabitan_bellevue/commit/eabdd54fe00060a05484d7a92ce4f2659b5d1191))
* **tests:** prevent TU failure without DB in testTechnicalSheetAborts404 ([5b1b2b1](https://github.com/ApoSkunz/crabitan_bellevue/commit/5b1b2b1e7277cbc3d73c324903920d32914b070b))
* **ux:** filter layout column, toast centered, collection anchor scroll-margin ([339eb0f](https://github.com/ApoSkunz/crabitan_bellevue/commit/339eb0f82a58614c5631a043a7e2172e892ac056))
* **view:** restore broken $cls assignment in collection.php ([1431ec2](https://github.com/ApoSkunz/crabitan_bellevue/commit/1431ec2f2118cb6faa0122f1995a40ce0b709283))


### Features

* **analytics:** add GA snippet in head, skipped on local environments ([99dd2ef](https://github.com/ApoSkunz/crabitan_bellevue/commit/99dd2ef4e54ae3cdcc519239b6cd8bf7477f1294))
* **catalogue:** add full wine catalogue — model, views, filters, SCSS, tests + fix lang switcher ([27bd51d](https://github.com/ApoSkunz/crabitan_bellevue/commit/27bd51da841af621e3989c605e97ad1b62482563))
* **catalogue:** improve shop — cart btn, pagination, likes, PDF sheet, seed fixes ([d323a83](https://github.com/ApoSkunz/crabitan_bellevue/commit/d323a83c0a91d7fab84b5b073244a231def0c5bb))
* **catalogue:** panier header, filtre layout, TTC note, per_page, disponible/épuisé, TCPDF alpha fix ([bcf630d](https://github.com/ApoSkunz/crabitan_bellevue/commit/bcf630da6dd89c1a93def8a388f46f1ce0789031))
* **collection:** smart anchor nav + cart toast + filter polish ([ea1a34e](https://github.com/ApoSkunz/crabitan_bellevue/commit/ea1a34e61290a03fa9c7cca165ab705478d1c3b5))
* **errors:** branded error pages + maintenance page ([6413791](https://github.com/ApoSkunz/crabitan_bellevue/commit/6413791b8a8541304a46f692ab44c2c31d5bea1a))
* **ux:** polish filters, cart redirect, collection nav and TTC note ([3ebeba3](https://github.com/ApoSkunz/crabitan_bellevue/commit/3ebeba370769999d492cb0308ed9d9d37796e48a))
* **wines:** UX improvements — header redesign, cart modal, collection filters & pagination ([18be2d0](https://github.com/ApoSkunz/crabitan_bellevue/commit/18be2d029d617eed11f62d3364cf7e46f91aef38))
* **wines:** UX polish — cart badge, modal total, collection nav & spacing ([7639570](https://github.com/ApoSkunz/crabitan_bellevue/commit/7639570c4dab5abb86e55576ffebb5db7d7ebe5c))

# [0.5.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.4.1...v0.5.0) (2026-03-22)


### Bug Fixes

* **ci:** correct TruffleHog base/head for push and PR events ([f4ffacd](https://github.com/ApoSkunz/crabitan_bellevue/commit/f4ffacd400eba481f227c4a81a4986bde4cbeec8))
* **ci:** passer LHCI_GITHUB_APP_TOKEN pour que Lighthouse poste le status check sur GitHub ([b03af13](https://github.com/ApoSkunz/crabitan_bellevue/commit/b03af138908e2cbb25a6125674c2cf0f209ace67))
* **ci:** remove push trigger from e2e.yml, fix server setup for manual runs ([de1bc26](https://github.com/ApoSkunz/crabitan_bellevue/commit/de1bc26d5c10645f01c6c8f1d574da73d7c4fc84))
* **footer:** center nav on full width with HVE|nav|CB grid layout, remove up2pay logo ([f1dd648](https://github.com/ApoSkunz/crabitan_bellevue/commit/f1dd6489456ad55040b779e6de66ee671ef22114))
* **home:** carousel timing, section bg alternance, footer refonte ([99aead7](https://github.com/ApoSkunz/crabitan_bellevue/commit/99aead71430ad9214772dcc45a23b9d9afb3fee2))
* **nosonar:** add justification to all bare NOSONAR comments ([dfa5705](https://github.com/ApoSkunz/crabitan_bellevue/commit/dfa5705690bf818344146c70181c4ec2f111ca94))
* **quality:** résoudre 63 issues SonarCloud, ajouter tests coverage et Lighthouse CI ([064e44d](https://github.com/ApoSkunz/crabitan_bellevue/commit/064e44de414bec2e088f3a199d87fb2ded7345d6))
* **views:** replace require with require_once in all new page views ([1b3ddfc](https://github.com/ApoSkunz/crabitan_bellevue/commit/1b3ddfc18417aebb487cf383ab942a2c6f1cf59c))
* **views:** resolve SonarCloud code smells in header and home ([0a5837d](https://github.com/ApoSkunz/crabitan_bellevue/commit/0a5837d6aba524a618e3f2061e9e2e4e5ed594e6))


### Features

* **homepage:** design revision round 2 — header/nav/carousel/home/footer ([b0b001a](https://github.com/ApoSkunz/crabitan_bellevue/commit/b0b001ade7ac3f25420053a9f2fb4f6e6af7fe79))
* **pages:** add all homepage-linked pages with controllers, views and tests ([28a6c21](https://github.com/ApoSkunz/crabitan_bellevue/commit/28a6c218226ecf39efefae491d2ccb51aed357fb))
* **ui:** light theme support + header/nav/carousel polish ([790990d](https://github.com/ApoSkunz/crabitan_bellevue/commit/790990d2f3f86a33d303117c79a941af0765725b)), closes [#ffffff](https://github.com/ApoSkunz/crabitan_bellevue/issues/ffffff) [#c9a84c](https://github.com/ApoSkunz/crabitan_bellevue/issues/c9a84c) [#080808](https://github.com/ApoSkunz/crabitan_bellevue/issues/080808)

## [0.4.1](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.4.0...v0.4.1) (2026-03-21)


### Bug Fixes

* **tests:** remove require_once from ControllerTest and ModelTest ([82cada4](https://github.com/ApoSkunz/crabitan_bellevue/commit/82cada4eb7f0b5f7cc2a734b4490708f0a106c8f))

# [0.4.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.3.0...v0.4.0) (2026-03-21)


### Bug Fixes

* **ci:** secure router.php path traversal + fix JS coverage pipeline ([7cac92a](https://github.com/ApoSkunz/crabitan_bellevue/commit/7cac92a60c124c7eba5dbf027c32830f7b4807f4))
* **ci:** suppress setTimeout false positive semgrep finding ([9ff408c](https://github.com/ApoSkunz/crabitan_bellevue/commit/9ff408c4cd79792666ca3fcb6286302124b6c2da))
* **core:** replace exit with HttpException for testability ([e635a19](https://github.com/ApoSkunz/crabitan_bellevue/commit/e635a19a6f9da89e913e357c7a7bb81cda82f7c3))
* **layout:** remove hyphen in brand name in header ([876aef2](https://github.com/ApoSkunz/crabitan_bellevue/commit/876aef238c4918003fe2a969db824ceef5e74817))
* **layout:** rename logo asset and remove hyphen in brand name ([a1af4f6](https://github.com/ApoSkunz/crabitan_bellevue/commit/a1af4f6c5b344bd8ac49e23213cc2392bcef155e))
* **security:** open redirect + semgrep assets + JS/PHP coverage pipeline ([9624cf0](https://github.com/ApoSkunz/crabitan_bellevue/commit/9624cf07711d5fe81fdc9de4a8744ff9d122c8bf))


### Features

* **layout:** add SCSS/Vite pipeline, age gate, responsive header and footer ([bbe3dd6](https://github.com/ApoSkunz/crabitan_bellevue/commit/bbe3dd68b1a9be1004a025c35c2f3013eedca938))
* **layout:** age gate — design, comportements, responsive et tests ([1ddaef3](https://github.com/ApoSkunz/crabitan_bellevue/commit/1ddaef3e7f18724eef4716457e9794c7e4c305ea))

# [0.3.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.2.0...v0.3.0) (2026-03-21)


### Bug Fixes

* **quality:** fix PHPCS violations in test files ([4cc68f2](https://github.com/ApoSkunz/crabitan_bellevue/commit/4cc68f238665f729a307b9c37636f808e2f3d441))


### Features

* **tests:** add unit tests for Core layer ([54c6239](https://github.com/ApoSkunz/crabitan_bellevue/commit/54c6239ed456fa0628e21802daa20985e607a56a))

# [0.2.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.1.1...v0.2.0) (2026-03-21)


### Bug Fixes

* **ci:** use legitify@main, no stable v1 tag exists ([94af994](https://github.com/ApoSkunz/crabitan_bellevue/commit/94af9946d69d763941de62e6476bef0bed472352))
* **quality:** fix PHPCS violations and SonarCloud sources ([831ff9d](https://github.com/ApoSkunz/crabitan_bellevue/commit/831ff9dc81728a25c1d6d1f7a26cd229933146a2))
* **quality:** fix PHPStan config — bootstrap, memory, dynamic constants, exclude views ([c78a371](https://github.com/ApoSkunz/crabitan_bellevue/commit/c78a37138fe7de22545759b70af8721e4b56458f))


### Features

* **ci:** add SAST/SCA, fix PR triggers on all branches ([eafe88c](https://github.com/ApoSkunz/crabitan_bellevue/commit/eafe88c3e45bc91979fa03403c617ed8b6ee1c72))
* **ci:** add SonarCloud, README badges, JS coverage via Playwright ([7fc58bf](https://github.com/ApoSkunz/crabitan_bellevue/commit/7fc58bf24d1597cd4d01629db796916a90191a05))
* **ci:** setup CI/CD pipeline, quality tools and test structure ([aac3e4d](https://github.com/ApoSkunz/crabitan_bellevue/commit/aac3e4d3c78de5e1eba1b6355aaf28b63efe1037))

## [0.1.1](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.1.0...v0.1.1) (2026-03-21)


### Bug Fixes

* **ci:** use SEMANTIC_RELEASE_TOKEN PAT to bypass branch protection ([eae14af](https://github.com/ApoSkunz/crabitan_bellevue/commit/eae14af1ae63143905c643c198abd11e5200c9f0))


### Reverts

* **ci:** restore GITHUB_TOKEN now that branch protection bypass is configured ([2168b8a](https://github.com/ApoSkunz/crabitan_bellevue/commit/2168b8adbc8839ad2ef8d765a1256a528416b77d))

# [0.1.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.0.0...v0.1.0) (2026-03-21)


### Bug Fixes

* **ci:** upgrade Node.js 20 → 22 in release workflow ([e8b2b8d](https://github.com/ApoSkunz/crabitan_bellevue/commit/e8b2b8da322341e5490aa26cc506177366b4fcdf))
* **ci:** use fine-grained PAT to bypass branch protection on release ([dc0ba72](https://github.com/ApoSkunz/crabitan_bellevue/commit/dc0ba7266628ff9267339db2ea51b40ff91708d6))


### Features

* **core:** add Core layer - Router, Controller, Model, JWT, Lang, i18n ([21d7907](https://github.com/ApoSkunz/crabitan_bellevue/commit/21d79073ba50b30e782da81d5ccc6c8ee7f39f40))
* **release:** setup semantic-release with GitHub Actions and project plan ([198b560](https://github.com/ApoSkunz/crabitan_bellevue/commit/198b5601bc68b5d157f6a776c1cdd03ddc5cdf7c))
* **release:** setup semantic-release with GitHub Actions and project plan ([#3](https://github.com/ApoSkunz/crabitan_bellevue/issues/3)) ([973e2eb](https://github.com/ApoSkunz/crabitan_bellevue/commit/973e2eba999a424a520ad86c41204e7626aab13a))
