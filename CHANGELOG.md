# Changelog

All notable changes to Crabitan Bellevue are documented here.

# [0.18.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.17.0...v0.18.0) (2026-04-02)


### Bug Fixes

* **mail:** bouton révocation — fermeture attribut style manquante (HTML cassé, block invisible) ([20ec5c6](https://github.com/ApoSkunz/crabitan_bellevue/commit/20ec5c60341e27d714a1344a2597f762f5fc30d8))
* **mail:** bouton révocation dans email confirmation + retrait nom dans notification (DCP) + emailSimpleLayout lang param ([8c5d0aa](https://github.com/ApoSkunz/crabitan_bellevue/commit/8c5d0aa77b71a68c089a884fa5ba6956c79a987b))
* **security:** confirmation changement email → ancienne adresse (anti account-takeover) ([cf75f55](https://github.com/ApoSkunz/crabitan_bellevue/commit/cf75f5512e0c8e7db226704e31fccb1677c251ae))
* **test:** corriger 4 TI en échec en CI ([c27ce67](https://github.com/ApoSkunz/crabitan_bellevue/commit/c27ce674ae4372dacedca4e6a3b51d910fd52739))
* **view:** bouton changement email — btn--secondary inexistant → btn--gold ([c6f9823](https://github.com/ApoSkunz/crabitan_bellevue/commit/c6f9823e0050601ebc2ccb746ba287889423c737))
* **view:** email_change_confirm — layout auth-card, btn--ghost, état révocation ([2668eb4](https://github.com/ApoSkunz/crabitan_bellevue/commit/2668eb4baf2c4705e8eecb7565402d9759893efa))
* **view:** profile — ancre #email-change pour scroll auto sur erreur formulaire ([1e051d7](https://github.com/ApoSkunz/crabitan_bellevue/commit/1e051d7fce676780e3b7d45cd5fe145cd2fd2bc1)), closes [#email-change](https://github.com/ApoSkunz/crabitan_bellevue/issues/email-change)
* **view:** suppression hint mailto obsolète sur email — formulaire double opt-in visible ([2238caa](https://github.com/ApoSkunz/crabitan_bellevue/commit/2238caa151e9fca93c66a038dc5fe87cafba1de6))


### Features

* **account:** changement d'email avec double confirmation (us-changement-email) ([b6d6db5](https://github.com/ApoSkunz/crabitan_bellevue/commit/b6d6db53434175b6bcb2f8801d8a0e851099656d))
* **controller+routes:** revokeEmailChange — révocation demande email depuis lien email (sans auth) ([8779aeb](https://github.com/ApoSkunz/crabitan_bellevue/commit/8779aebf75ee703dd9d78d562badb63b6c2d4ff9))
* **controller:** AccountController — action cancelEmailChange (révocation demande en attente) ([e31a071](https://github.com/ApoSkunz/crabitan_bellevue/commit/e31a0713a035950f0318360152833d41bcdadf2d))
* **controller:** AccountController — double opt-in newsletter depuis profil (0→1 = email, 1→0 = direct) ([e0200be](https://github.com/ApoSkunz/crabitan_bellevue/commit/e0200befa244e8565476e09f2ca94525d1d8ba74))
* **controller:** AuthController — activer newsletter_optin_pending à la vérification email ([69fdc17](https://github.com/ApoSkunz/crabitan_bellevue/commit/69fdc17d2d0199bc09579105ea17cf4a59167f89))
* **controller:** NewsletterController — confirmSubscription + subscribe (double opt-in) ([82b6bf4](https://github.com/ApoSkunz/crabitan_bellevue/commit/82b6bf4ec461115bcf07c95faa839ccf88587293))
* **i18n:** clés email_change_pending_* + email_change_cancelled (fr + en) ([ae12553](https://github.com/ApoSkunz/crabitan_bellevue/commit/ae1255325256e64b6198f9e23cb1ef208c2ba1aa))
* **i18n:** clés email_change_revoked_title/body (fr + en) ([3b95e96](https://github.com/ApoSkunz/crabitan_bellevue/commit/3b95e964211a1193e2151cfd53b9bc94220f254f))
* **model:** AccountModel — ajout clearEmailChangeToken() pour révocation demande email ([5b9b1ab](https://github.com/ApoSkunz/crabitan_bellevue/commit/5b9b1ab83321973bef66219bd47dc3e40ec83d93))
* **model:** AccountModel — double opt-in newsletter (optin_pending, confirm token, activation) ([10c6c3c](https://github.com/ApoSkunz/crabitan_bellevue/commit/10c6c3c2a1793a5136f23f18c476e9afdcd31420))
* **routes:** ajout POST /{lang}/mon-compte/email/annuler — révocation changement email ([994c7c1](https://github.com/ApoSkunz/crabitan_bellevue/commit/994c7c16c1eadef719336f91377947fc6c6e5516))
* **service:** AccountService — revokeUrl passé à sendEmailChangeConfirmation + retrait displayName de notification ([b66879e](https://github.com/ApoSkunz/crabitan_bellevue/commit/b66879ebc9cc644dfa330bf75ffd89c650cf37b1))
* **view:** newsletter/confirm — page confirmation abonnement double opt-in ([1a3b220](https://github.com/ApoSkunz/crabitan_bellevue/commit/1a3b22005bea5d78a5bca0523979998cd4710fba))
* **view:** profile — bloc demande email en attente + bouton annulation ([ecd2115](https://github.com/ApoSkunz/crabitan_bellevue/commit/ecd2115a8a6ec3dc5dbc2c01ca9472f6d20ad590))

# [0.17.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.16.0...v0.17.0) (2026-04-02)


### Bug Fixes

* **i18n:** account.export_intro — précise ZIP avec JSON et PDF (fr + en) ([6daa601](https://github.com/ApoSkunz/crabitan_bellevue/commit/6daa601b1980f5db8c8075e8d30084faf14ae048))
* **i18n:** correction formulation Loi Évin EN — 'To be consumed in moderation.' ([a339389](https://github.com/ApoSkunz/crabitan_bellevue/commit/a3393894637ee8f6917ac95d2a89ecc63b367939))
* **i18n:** suppression doublon account.export_intro dans fr.php ([1fde8bc](https://github.com/ApoSkunz/crabitan_bellevue/commit/1fde8bcd3bebe85df009a2030dc4088c05ace643))
* **mail+test:** buildNewsletterHtml — propagation $lang pour mention Loi Évin EN ([e8ce6a4](https://github.com/ApoSkunz/crabitan_bellevue/commit/e8ce6a4d0e9ac38bcd7557952030501a69ec9781))
* **mail:** mention Loi Évin dans la langue du destinataire uniquement ([612018a](https://github.com/ApoSkunz/crabitan_bellevue/commit/612018ae20c5d12c204d18822332ae85b92570da))


### Features

* **mail:** mention Loi Évin bilingue dans le footer partagé de tous les emails ([e279970](https://github.com/ApoSkunz/crabitan_bellevue/commit/e27997067d082700f4cac4392dcd2c1d1e37d15a)), closes [#6b5e4a](https://github.com/ApoSkunz/crabitan_bellevue/issues/6b5e4a)

# [0.16.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.15.0...v0.16.0) (2026-04-01)


### Bug Fixes

* **ci:** déclencher CI et Security sur toutes les branches feat/fix/refactor/chore ([861dd5e](https://github.com/ApoSkunz/crabitan_bellevue/commit/861dd5eb1a9b259514a59e106c60e3fdd88f2db2))
* **ci:** optimise CI — dédup push/PR + scorecard main only + PHPStan + Semgrep ([150c43d](https://github.com/ApoSkunz/crabitan_bellevue/commit/150c43da96fbc391b8bc75be7b6df3625eef26f4))
* **ci:** push trigger sur main uniquement — supprime les doubles runs push+PR ([ff9a9a2](https://github.com/ApoSkunz/crabitan_bellevue/commit/ff9a9a2678e2505f4218392a9a3a7075bf4a85d0))
* **cs:** PHPCBF — PSR12 multi-line function call signatures (AccountModelTokenTtlTest) ([b180c2a](https://github.com/ApoSkunz/crabitan_bellevue/commit/b180c2ae2e367b0d1b73c3099a6a246bc15227e6))
* **rate-limiter:** isApcuAvailable() utilise apcu_enabled() pour détecter le CLI ([5094ef2](https://github.com/ApoSkunz/crabitan_bellevue/commit/5094ef267fad970040b76f0b25f8a88f0b4f7520))
* **test:** corrige l'erreur CI ALTER TABLE IF NOT EXISTS (MySQL 8) et les 6 failures session ([6bef2ae](https://github.com/ApoSkunz/crabitan_bellevue/commit/6bef2ae8aa326b690fb6c7830345597717b45188))
* **test:** corrige la perte des PDF storage/order_forms lors du test mkdir ([9c538ac](https://github.com/ApoSkunz/crabitan_bellevue/commit/9c538ac99edfd3509afed546e29fb9951895f512))
* **tests:** aligner TI et E2E sur les nouvelles clés flash register_success/forgot_success ([51b8c95](https://github.com/ApoSkunz/crabitan_bellevue/commit/51b8c950741e15fd238f6e611240281ba7140ba5))


### Features

* **auth:** anti-énumération inscription + alerte lockout à la 5ème tentative ([58aec72](https://github.com/ApoSkunz/crabitan_bellevue/commit/58aec72bffbe9f12db01aa41beb2ce322430aa4a))
* **controller:** AccountController + ProfileAdminController — E6 + R4/BT4 ([6238896](https://github.com/ApoSkunz/crabitan_bellevue/commit/6238896bb3aa59718a6739856d93f7cdb4647fe9))
* **controller:** AuthController — sécurisation complète auth (E1/E2/E3/R1/R2/R5/BT2/E4) ([6131e54](https://github.com/ApoSkunz/crabitan_bellevue/commit/6131e54a49ef927189b41d053df33ca9a861c2ed))
* **front:** B1 — validation temps réel concordance mots de passe ([24dc55e](https://github.com/ApoSkunz/crabitan_bellevue/commit/24dc55e5d1bd5168b3cdf5a861587c3b34229364))
* **front:** ouverture modal forgot sur succès + nettoyage cookie banner ([5ff026d](https://github.com/ApoSkunz/crabitan_bellevue/commit/5ff026dedf87aeebc90b1b1177ec92bfda1f97d3))
* **i18n:** nouvelles clés auth — rate limiting, ANSSI granulaire, MDP identique ([7bbe760](https://github.com/ApoSkunz/crabitan_bellevue/commit/7bbe760704337a53b23c3d2cbdd600d739a1d43f))
* **mail:** sendEmailAlreadyExists et sendAccountLocked (fr/en) ([35e06f6](https://github.com/ApoSkunz/crabitan_bellevue/commit/35e06f64890366714332bbe3072cd935e630fd5f))
* **model:** AccountModel — TTL 24h token vérification email (R6/BT6) ([9efee00](https://github.com/ApoSkunz/crabitan_bellevue/commit/9efee00d3184070fd04259923a1a34f3540e51e2))
* **service:** RateLimiterService (R1/R2/BT2) + PasswordValidator::getErrors() (E2/E3) ([2ef9317](https://github.com/ApoSkunz/crabitan_bellevue/commit/2ef93173cab3762e5a4713c6b98505e8af60115e))
* **service:** sendPasswordChangedAlert + sendNewDeviceAlert — emails sécurité (R4/BT4) ([bab22b9](https://github.com/ApoSkunz/crabitan_bellevue/commit/bab22b9165bb2844d0427d18eb268ce3c54bc676))
* **view:** affichage messages succès inscription/forgot dans les modals ([573dbc9](https://github.com/ApoSkunz/crabitan_bellevue/commit/573dbc9f09bfc0adfd45a9cecc401fe297ce120d))
* **view:** header.php — E4 reset success modal + B1 data-mismatch-label ([7feab4e](https://github.com/ApoSkunz/crabitan_bellevue/commit/7feab4ea2d42a4412587713d81de693ae6b81e94)), closes [#reg-password-confirm](https://github.com/ApoSkunz/crabitan_bellevue/issues/reg-password-confirm)

# [0.15.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.14.0...v0.15.0) (2026-03-29)


### Bug Fixes

* **admin:** politique MDP ANSSI sur changement de mot de passe admin ([7803a1c](https://github.com/ApoSkunz/crabitan_bellevue/commit/7803a1c1fa7546fdd3f1a0b73296b1e6c1dbb005))
* **charts:** enregistrer LineController et BarController dans Chart.js ([d6651a6](https://github.com/ApoSkunz/crabitan_bellevue/commit/d6651a6cb0da26c03a8d24a5eaa3e4f7ca89ae1e))
* **ci:** audit Red/Blue — corriger YAML malformé persist-credentials ([ce9bf10](https://github.com/ApoSkunz/crabitan_bellevue/commit/ce9bf10411619bedd52eeb6783d3a9566d42d0af))
* **ci:** audit Red/Blue — ré-intégrer Exakat version épinglée + corriger YAML ([f807d07](https://github.com/ApoSkunz/crabitan_bellevue/commit/f807d07cfd9c3f91ac713300f7747d4db7e7f620))
* **ci:** durcissement workflows — permissions, pinning, timeouts, Scorecard, SLSA L2 ([45592f4](https://github.com/ApoSkunz/crabitan_bellevue/commit/45592f41d968ab2758e7cf0f55cb63a8131b8529))
* **ci:** Exakat fallback URL + supprimer Legitify (upload-artifact@v3 deprecated) ([228b85b](https://github.com/ApoSkunz/crabitan_bellevue/commit/228b85b20ed16a85e9ac61e790473a7dffdf6062))
* **ci:** Exakat graceful skip si indisponible + Legitify CLI binary (bypass action v3) ([23f0e3e](https://github.com/ApoSkunz/crabitan_bellevue/commit/23f0e3e6bdf79ba3f4868687f40b490c48eebe75))
* **ci:** Legitify --github-token + dépréciation \$context stream wrappers PHP 8.2 ([a2dcc50](https://github.com/ApoSkunz/crabitan_bellevue/commit/a2dcc507514e607e7ed224f0d414814371984679)), closes [--#token](https://github.com/--/issues/token)
* **ci:** nosemgrep SHA SonarCloud + npm audit critical uniquement ([ade8c68](https://github.com/ApoSkunz/crabitan_bellevue/commit/ade8c68ebe84ec581b3a88e2f184a1cf72e1d8d3))
* **ci:** rétablir Legitify non-bloquant + npm audit omit=dev ([5a2a443](https://github.com/ApoSkunz/crabitan_bellevue/commit/5a2a4437034d05c595860b69a2a58d8edb916eb6))
* **controller:** newsletter vin — filtre comptes société + gestion slug dupliqué ([80ff1a6](https://github.com/ApoSkunz/crabitan_bellevue/commit/80ff1a6b0cecb56ab86b62a496168a53b79f6b2a))
* **controller:** NOSONAR inline sur error_log (S4792) et http URL (S5332) ([be27f18](https://github.com/ApoSkunz/crabitan_bellevue/commit/be27f18235378effd16d05c797aad8ed4cf0adb4))
* **controller:** SonarCloud — nested ternary, moveUploadedFile seam, string constants ([42a4536](https://github.com/ApoSkunz/crabitan_bellevue/commit/42a453681056a1c75a8670ae8324b74b64d8f310))
* **e2e:** age-gate — intercept soumission formulaire, secouer cookie banner si pas de consentement ([1c5bb6f](https://github.com/ApoSkunz/crabitan_bellevue/commit/1c5bb6fc4ac8cafec63394866a7e8516fcad161c)), closes [#age-gate-form](https://github.com/ApoSkunz/crabitan_bellevue/issues/age-gate-form)
* **mail:** newsletter vin — appellation, cuvée spéciale, récompense, filtre société ([09166f3](https://github.com/ApoSkunz/crabitan_bellevue/commit/09166f3866d96ae14f94a2905527057efdcb55d8))
* **service:** SonarCloud — variables inutilisées, constante BTN_STYLE, resolveAwardText, regex \d ([999e240](https://github.com/ApoSkunz/crabitan_bellevue/commit/999e240d88edadc4373f9406283b135086809f40))
* **test:** TI newsletter — aligner subject avec préfixe branding du controller ([eaa964c](https://github.com/ApoSkunz/crabitan_bellevue/commit/eaa964c368314bccd15b28ff7b7b48b009e74463))
* **upload:** corriger translittération accents noms fichiers images (strtr) ([ebb8d0a](https://github.com/ApoSkunz/crabitan_bellevue/commit/ebb8d0a2eceec2d391686d478c7b48c56db2ae6b))
* **upload:** translittérer les accents dans les noms de fichiers images ([b1f26e6](https://github.com/ApoSkunz/crabitan_bellevue/commit/b1f26e648d74b04fd45b3bd5110087555ed0cf70))
* **view:** admin — chargement main.js, erreur slug dupliqué, lien Sécurité header ([c9c860b](https://github.com/ApoSkunz/crabitan_bellevue/commit/c9c860b6ce61cd56e6ad37b973e2b90e31cc2a39))


### Features

* **admin-nav:** ajouter lien Statistiques CA dans account-panel__nav ([8ccf894](https://github.com/ApoSkunz/crabitan_bellevue/commit/8ccf894f86a445723fafdb08ea8735251cf16ecd))
* **admin:** email transactionnel au client sur changement de statut commande ([d450da8](https://github.com/ApoSkunz/crabitan_bellevue/commit/d450da899edbb30231ad7a394c3e70c53c0149f7))
* **auth:** case 'Se souvenir de moi' — JWT et cookie 30 jours ([d3ec08a](https://github.com/ApoSkunz/crabitan_bellevue/commit/d3ec08a76c3da252be39dbb6cc4b1f1f1f664ab1))
* **auth:** politique mot de passe ANSSI MDP 2021 ([7cfd321](https://github.com/ApoSkunz/crabitan_bellevue/commit/7cfd321d5e8baf397b093df450cdcbc7450b63ef))
* **mail:** newsletter nouveau vin — objet branding, salutation Cher(e), intro château ([699b16f](https://github.com/ApoSkunz/crabitan_bellevue/commit/699b16f41b92a71fca5669672b5d64590e8be9c7))
* **mail:** templates email statut commande et newsletter nouveau vin ([679ed6d](https://github.com/ApoSkunz/crabitan_bellevue/commit/679ed6dae768815cd459a814e134ec952baca6d6))
* **model:** OrderModel::getById() inclut la langue du compte client ([a7e86cc](https://github.com/ApoSkunz/crabitan_bellevue/commit/a7e86cc45a22b8f8fcffdfaa61a4e3f00a4753cc))
* **newsletter:** audit marketing — objet, salutation prénom, champ titre ([576bc02](https://github.com/ApoSkunz/crabitan_bellevue/commit/576bc02b7111d58bfb9eb5f5c2db85ca7a3add9e))
* **ux:** cookie banner non bloquant sur la page age-gate ([31ef49b](https://github.com/ApoSkunz/crabitan_bellevue/commit/31ef49b2e92a2accdf7171ce5033edcb1c744c8a))

# [0.14.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.13.0...v0.14.0) (2026-03-29)


### Bug Fixes

* **admin/news:** retirer astérisque obligatoire sur le champ image en édition ([24e8487](https://github.com/ApoSkunz/crabitan_bellevue/commit/24e848791884b17d8701a791bde6a5e3266befce))
* **controller:** image actualité non obligatoire à la création ([d8c0c5f](https://github.com/ApoSkunz/crabitan_bellevue/commit/d8c0c5f722c779ad8c0c5ba2d711d14e5df71fcc))
* **controller:** prix vin minimum 3 € (validation parseWineForm) ([cd2a58a](https://github.com/ApoSkunz/crabitan_bellevue/commit/cd2a58a2ffd811e317cca9a2114f1b3a8099b1c2))
* **middleware:** masquer les routes admin avec 404 au lieu de 403 ([5f01dee](https://github.com/ApoSkunz/crabitan_bellevue/commit/5f01dee8f5e083a9917a78a65483f68ee8442549))
* **model:** ajouter link_path aux SELECT de NewsModel ([da7c7c7](https://github.com/ApoSkunz/crabitan_bellevue/commit/da7c7c75965f55522cc2537c49c7d60db010cf4f))
* **sonar:** corriger 4 code smells restants (S1192, S1448×2, S1142) ([5dbdccc](https://github.com/ApoSkunz/crabitan_bellevue/commit/5dbdccc54c415edd11569d0a9753fa4f4041bf7b))
* **ti:** absorber exception mail dans cancelOrder + aligner tests NewsAdmin ([44c3212](https://github.com/ApoSkunz/crabitan_bellevue/commit/44c3212bbdde2c7a7fda9db23a0725f0923e4eb6))
* **ti:** résoudre les 5 tests skipped (fileinfo, SMTP, table vide) ([eb012ae](https://github.com/ApoSkunz/crabitan_bellevue/commit/eb012ae12699469e50bc3ec50a58426ceb5ffbc2))
* **view:** ajouter refund_refused aux statuts affichés (compte + admin) ([3d79ee0](https://github.com/ApoSkunz/crabitan_bellevue/commit/3d79ee09e7cab2074948b3b293a929a3522c30d7))
* **view:** image actualité non obligatoire — label, validation JS et message supprimés ([ac46fd2](https://github.com/ApoSkunz/crabitan_bellevue/commit/ac46fd2bfb18b9de8496ec55915d748d87cb07f8))
* **view:** réduire la taille du bouton "En savoir plus" (btn--sm) ([2fa19ff](https://github.com/ApoSkunz/crabitan_bellevue/commit/2fa19ff697dd6c69b65a9b58921fc3f0b6daaa41))
* **view:** timeline return_requested/refunded + message support uniquement sur retour actif ([a336069](https://github.com/ApoSkunz/crabitan_bellevue/commit/a3360697d7e92bfc96cfa7c6e2604c473674461d))


### Features

* **account:** rétractation — fiche retour PDF, emails, date livraison, vue ([559921b](https://github.com/ApoSkunz/crabitan_bellevue/commit/559921b3385747b425b342791abb3ca459d2314d))
* **admin/orders:** verrouillage statut annulé, pop-in confirmation, double opt-in remboursé ([d9679bf](https://github.com/ApoSkunz/crabitan_bellevue/commit/d9679bf04f04bf5cec816d07805c97daec9a8669)), closes [#1a1208](https://github.com/ApoSkunz/crabitan_bellevue/issues/1a1208)
* **controller:** annulation commande — fenêtre rétractation + branche return ([511416e](https://github.com/ApoSkunz/crabitan_bellevue/commit/511416e2e634adbfa69991e23cd17699f32cb6c1))
* **controller:** newsletter — persister campagne + show() historique ([7b250b2](https://github.com/ApoSkunz/crabitan_bellevue/commit/7b250b23a6719f8251d16083eefd7f022a9fb547))
* **i18n:** clés rétractation commande (fr + en) ([47c34ba](https://github.com/ApoSkunz/crabitan_bellevue/commit/47c34ba3567e7502085792cc9fdf091da8b21425))
* **i18n:** clés rétractation, date livraison et retour en cours (fr+en) ([461eeb3](https://github.com/ApoSkunz/crabitan_bellevue/commit/461eeb3fdd423d85bbbf24c929b9ede3b790ace6))
* **model:** ajouter refund_refused aux statuts valides de OrderModel ([2e423af](https://github.com/ApoSkunz/crabitan_bellevue/commit/2e423af59fdd45081b065e77de5dbc87803c0c15))
* **model:** annulation commande — CANCEL_WINDOW_DAYS + requestReturnForUser ([c60ed28](https://github.com/ApoSkunz/crabitan_bellevue/commit/c60ed2896282a63ea2fcb1d7b6b10cae28d72612))
* **model:** NewsletterModel — create, updateStats, getAll, count, findCampaignById, saveAttachment ([cfe39bb](https://github.com/ApoSkunz/crabitan_bellevue/commit/cfe39bbf9a3ef309efe177007984836362877c6b))
* **newsletter:** filtre 10/25/50 sur l'historique des campagnes ([8c4caae](https://github.com/ApoSkunz/crabitan_bellevue/commit/8c4caae8c1b7cb9531691effdafbb45c3a480938))
* **routes:** ajouter route GET fiche-retour commande client ([e72c8ef](https://github.com/ApoSkunz/crabitan_bellevue/commit/e72c8effe3e8f0f0d0bbdaeb8cc232e9752f1579))
* **routes:** GET /admin/newsletter/{id} — détail campagne newsletter ([0b86c66](https://github.com/ApoSkunz/crabitan_bellevue/commit/0b86c66dde4638107893cfdf5b937d200828d043))
* **service:** MailService — emails retour rétractation propriétaire et client ([d33079a](https://github.com/ApoSkunz/crabitan_bellevue/commit/d33079aad3f61f88200b57a9ba03ce84580b48b4))
* **service:** TranslationService DeepL Free — remplace MyMemory dupliqué ([eb3ee47](https://github.com/ApoSkunz/crabitan_bellevue/commit/eb3ee47e60dfedbc64af8bc04e8d4cfa224b55c7))
* **view:** lien "En savoir plus" target=_blank sur la page article ([0d0e86d](https://github.com/ApoSkunz/crabitan_bellevue/commit/0d0e86dc2eaf4e3421819cb7a65bf5bfdf995067))
* **view:** newsletter — section historique dans index + vue show détail campagne ([e62f390](https://github.com/ApoSkunz/crabitan_bellevue/commit/e62f3909b935b6ff7e2928c1574a811450200bd0))
* **view:** order_detail — bouton rétractation avec date limite ([646148d](https://github.com/ApoSkunz/crabitan_bellevue/commit/646148db3068c3d5e32bba704787c6f5541d0a7b))

# [0.13.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.12.1...v0.13.0) (2026-03-29)


### Bug Fixes

* **auth:** bloquer /mon-compte pour admin/super_admin (404) et reset mdp via form public ([a9677dc](https://github.com/ApoSkunz/crabitan_bellevue/commit/a9677dc425391cfbc563f597cfc2b17b8fbcd042))
* **ci:** corriger crash sandbox Puppeteer dans Lighthouse CI ([5778b81](https://github.com/ApoSkunz/crabitan_bellevue/commit/5778b816ef067ffefd45bb2717b0aa13db188469))
* **ci:** désactiver Lighthouse (standby) — conflit ESM/Puppeteer en investigation ([bfff7a5](https://github.com/ApoSkunz/crabitan_bellevue/commit/bfff7a503c26459e838839b46a722fbeae8f2a75))
* **ci:** install Chrome avant Lighthouse CI via browser-actions/setup-chrome ([52d4b81](https://github.com/ApoSkunz/crabitan_bellevue/commit/52d4b81033d493478e4c6832d0cafc1e0fb4e829))
* **ci:** MAIL_USER vide en TI + puppeteerScript → .cjs ([17fc242](https://github.com/ApoSkunz/crabitan_bellevue/commit/17fc242c056aaac94c594f6c9460c361bb6c24df))
* **ci:** renommer lighthouse-auth.js → .cjs (ESM conflict) ([46ef8d7](https://github.com/ApoSkunz/crabitan_bellevue/commit/46ef8d7e495f4ade1420fd8dbe2c1c793ee5553a))
* **config:** éviter les warnings PHP si .env est absent (TU sans fichier .env) ([80b5a47](https://github.com/ApoSkunz/crabitan_bellevue/commit/80b5a479a8f22391f3cbef2e8adb04d5e2b9e4de))
* **controller:** passer ownerEmail à la vue profil pour mailto changement email ([b382fae](https://github.com/ApoSkunz/crabitan_bellevue/commit/b382fae25bc1d9adf1fca420439ccb19fcd4f481))
* **i18n:** formulations RGPD — suppression vs anonymisation, factures, lien reactivate ([3831c10](https://github.com/ApoSkunz/crabitan_bellevue/commit/3831c104c296276db4bd0e2bf03d63f9424864e4))
* **js:** badge panier affiche 0 quand le panier est vide ([0b7dea8](https://github.com/ApoSkunz/crabitan_bellevue/commit/0b7dea8afe97a85b8a18187237b17ad706b8c291))
* **mail:** adresse From valide en CI via MAIL_FROM env var ([d20f3de](https://github.com/ApoSkunz/crabitan_bellevue/commit/d20f3de3f03af5489776ce9a371163a8e7068712))
* **mail:** fallback APP_URL sur constante si absent de \$_ENV ([92adedc](https://github.com/ApoSkunz/crabitan_bellevue/commit/92adedc62ff895791c05e7bbef23f6f7d8339a95))
* **model:** sessions token, méthodes commandes/adresses/compte ([53042b6](https://github.com/ApoSkunz/crabitan_bellevue/commit/53042b6e0552be306647577d8c53ccaa113de208))
* **phpcs:** extraire $validStatuses pour respecter la limite de 150 caractères ([327a7ba](https://github.com/ApoSkunz/crabitan_bellevue/commit/327a7ba3556fd581a5cd2a90b82d2b81f1444843))
* **rgpd:** civility nullable pour anonymisation des comptes supprimés ([b4d07ee](https://github.com/ApoSkunz/crabitan_bellevue/commit/b4d07ee604dabe0acae12aa5873666a885ec500b))
* **routes:** POST désabonnement newsletter + age-gate chemin public ([d827eab](https://github.com/ApoSkunz/crabitan_bellevue/commit/d827eabeda44572c379a3c25a749ddfa5ac866ff))
* **scss,view:** sidebar admin scroll indépendant, panel public raccourcis admin complets ([6d92b85](https://github.com/ApoSkunz/crabitan_bellevue/commit/6d92b858d69682c0dcc7f797a8679a06a8f90452))
* **sonar:** accessibilité — supprimer role=list redondant sur <ul>, associer label Email au champ ([a50bd0a](https://github.com/ApoSkunz/crabitan_bellevue/commit/a50bd0a87356b5dc73eb9cbfee9bc1a1b6e20b06))
* **sonar:** correction des code smells restants PR [#33](https://github.com/ApoSkunz/crabitan_bellevue/issues/33) ([e8edcc5](https://github.com/ApoSkunz/crabitan_bellevue/commit/e8edcc5c0635566d08835c9d70a0a70d2c4c30d0))
* **sonar:** déplacer NOSONAR inline sur les déclarations de classe/méthode ([7577f96](https://github.com/ApoSkunz/crabitan_bellevue/commit/7577f968bb91e53e3685101a0b943d82f116a78f))
* **sonar:** déplacer NOSONAR S1142 sur la déclaration de méthode ([f5c5d15](https://github.com/ApoSkunz/crabitan_bellevue/commit/f5c5d15cc60639fbe6934bf5659d8f8770dcf6d5))
* **sonar:** éliminer duplication ' selected' et corriger autocomplete ([888d6fb](https://github.com/ApoSkunz/crabitan_bellevue/commit/888d6fb5752f12e6f94f69da3d840214c53df7fb))
* **sonar:** extraire ternaires imbriqués MailService + SRI Chart.js ([81523e4](https://github.com/ApoSkunz/crabitan_bellevue/commit/81523e4142750f562dcebc8d7ca6ce2b2a2df7d2))
* **sonar:** NOSONAR sur faux positifs — duplications i18n et hotspot SRI chart.js ([0510794](https://github.com/ApoSkunz/crabitan_bellevue/commit/0510794a1c251878d04c8a62592867b034e09eed))
* **sonar:** supprimer les variables locales non utilisées ($gold, $dark, $name, $safeDate) et extraire le ternaire imbriqué ([2c1dac2](https://github.com/ApoSkunz/crabitan_bellevue/commit/2c1dac23af59cfe9c353389620c81d660e2d3451))
* **tests:** insérer la connexion active en base pour que AuthMiddleware valide le JWT ([883de7c](https://github.com/ApoSkunz/crabitan_bellevue/commit/883de7c3d845c21b260ab73fbde928c9b595f298))
* **tests:** valeurs ENUM invalides et PHPCS brace finale ([92685b3](https://github.com/ApoSkunz/crabitan_bellevue/commit/92685b3dd23eeefae1afa2fcb65c1d69239a98ce))
* **view:** guard function_exists sur ordersUrl + retirer URL 404 LH ([1e0d7b4](https://github.com/ApoSkunz/crabitan_bellevue/commit/1e0d7b4c72bb03a119ad41a979c20356e00394d7))
* **view:** panier — badge compteur header, pop-in succès, cuvée spéciale ([e081f4b](https://github.com/ApoSkunz/crabitan_bellevue/commit/e081f4b41f7c3efaf7e5167839369678b66f7a01))


### Features

* **account:** masquer commandes/adresses dans le header pour les comptes société ([058c121](https://github.com/ApoSkunz/crabitan_bellevue/commit/058c121c4430b59327bda365207f30b411f4fcda))
* **account:** restreindre commandes et adresses aux comptes particuliers ([7e66bd7](https://github.com/ApoSkunz/crabitan_bellevue/commit/7e66bd7661f2832effdd7909fb51cb35a515a831))
* **admin:** bundler chart.js via Vite, supprimer CDN ([6a4d731](https://github.com/ApoSkunz/crabitan_bellevue/commit/6a4d7318ab3d01658d6422ca5f0a8cd658b11b04)), closes [#chart-data](https://github.com/ApoSkunz/crabitan_bellevue/issues/chart-data)
* **auth:** MFA appareil — JWT différé jusqu'à confirmation email, première connexion auto-trust ([ef28582](https://github.com/ApoSkunz/crabitan_bellevue/commit/ef28582ded41276f6dac6a454f22a41cbfc8a444))
* **controller:** AccountController sécurité + Api\MfaController polling MFA ([38dcc39](https://github.com/ApoSkunz/crabitan_bellevue/commit/38dcc39df1f0d294b3ba033dba4d0c343abf28b2))
* **controller:** AccountController, FavoriteApiController, WineController ([f2ae773](https://github.com/ApoSkunz/crabitan_bellevue/commit/f2ae77388b692bb11684bafb6c9e5d4ce1e764fd))
* **controller:** admin — ProfileAdminController sécurité + StatsAdminController CA ([7d090d2](https://github.com/ApoSkunz/crabitan_bellevue/commit/7d090d2041cca9840819c356fa8746a7837e2d74))
* **controller:** adresses — CSRF, soft-lock, validation zip/phone internationale ([1930f42](https://github.com/ApoSkunz/crabitan_bellevue/commit/1930f429c9d8d92631ec467878a53f4b83196d52))
* **controller:** détail commande, annulation, CRUD adresses, profil, export, suppression compte ([300ea53](https://github.com/ApoSkunz/crabitan_bellevue/commit/300ea539ed3753ec9d4fdf1de21491da516217a7))
* **controller:** filtre statut commandes, shipping_discount depuis colonne orders ([cae6a78](https://github.com/ApoSkunz/crabitan_bellevue/commit/cae6a78f22af3af0901e4ef823096c64ea031ef7))
* **controller:** PDF newsletter admin, désabonnement confirmation, purge RGPD ([c79261a](https://github.com/ApoSkunz/crabitan_bellevue/commit/c79261a524671ce969f0534f0262219d2cdf395a))
* **controller:** ProfileAdminController — changement mot de passe admin ([bb2ab1b](https://github.com/ApoSkunz/crabitan_bellevue/commit/bb2ab1b921d196dbd9e876ab32458d1f23dd3cbe))
* **core:** CookieHelper — set/clear cookie JWT centralisé ([e50cbee](https://github.com/ApoSkunz/crabitan_bellevue/commit/e50cbee67228f580bd79f6306507c3e8fe60ac84))
* **export:** ajout appareils de confiance et sessions actives dans l'export RGPD ([8edce7d](https://github.com/ApoSkunz/crabitan_bellevue/commit/8edce7d2cce29563a7d210e56700a8f9889922bd))
* **i18n:** adresses — hint téléphone international, fix spacing opérateur => (phpcbf) ([2600bc7](https://github.com/ApoSkunz/crabitan_bellevue/commit/2600bc738012303cc8faf6d6df1ffd7ff9890a91))
* **i18n:** clés compte, statuts commande, favoris, quantité production ([b04957b](https://github.com/ApoSkunz/crabitan_bellevue/commit/b04957bc13e74d46c20fa964557cc9e2d5acd7fc))
* **i18n:** clés MFA, sécurité compte, appareils de confiance, reset, stats ([82fbf23](https://github.com/ApoSkunz/crabitan_bellevue/commit/82fbf239155bc6f4d6109be45912668105af5ab6))
* **i18n:** clés panel.profile/security/export, email mailto, FAQ support q12-q13 ([cae6352](https://github.com/ApoSkunz/crabitan_bellevue/commit/cae6352dca6c81fad8bc170963361c4ceb709ce6))
* **i18n:** clés profil, commandes, adresses, export RGPD, sécurité ([7ad827f](https://github.com/ApoSkunz/crabitan_bellevue/commit/7ad827ff3cb3e561b913550fdedd21d944c3ee6c))
* **i18n:** clés RGPD, sécurité, export, désabonnement newsletter ([7594e8f](https://github.com/ApoSkunz/crabitan_bellevue/commit/7594e8f003c64f6946bffabdc0e215318e317309))
* **i18n:** statut return_requested, download_invoice_detail, filtre statut commandes ([2a55d3f](https://github.com/ApoSkunz/crabitan_bellevue/commit/2a55d3fe68bf8b4022bb9e46206035eb8ca98d23))
* **js:** favoris suppression, modal suppression compte, validation forms admin ([dc43868](https://github.com/ApoSkunz/crabitan_bellevue/commit/dc4386810d76dbfed57aa496466e6291e5f7922b))
* **js:** œil password, confirm forms, toggle adresse, suppression DOM favoris ([c24fa60](https://github.com/ApoSkunz/crabitan_bellevue/commit/c24fa60e86f0d964a2ddcce9e7a39ac59ce31a1d))
* **js:** toggle favoris AJAX, cœur liké/brisé, compteur, pop-in panier ([0660d84](https://github.com/ApoSkunz/crabitan_bellevue/commit/0660d84f8b56d7732c2d88f856c0162866e9d346))
* **middleware:** AuthMiddleware vérifie la session en BDD + sessionChecker injectable pour TU ([28ea959](https://github.com/ApoSkunz/crabitan_bellevue/commit/28ea959b1b17a777d23aa1251839168dd820318a))
* **model:** AccountModel purge RGPD, newsletter subscribers, FavoriteModel alias SQL ([b745922](https://github.com/ApoSkunz/crabitan_bellevue/commit/b7459229154c6ba54dbe181a053b885217ce7bae))
* **model:** FavoriteModel, AddressModel, extensions ConnectionModel/OrderModel/WineModel ([915e3df](https://github.com/ApoSkunz/crabitan_bellevue/commit/915e3df9430c3e5103be11ecfade7529c8af12d3))
* **model:** PricingRuleModel + OrderModel filtre statut, annulation pending only, return_requested ([60bde07](https://github.com/ApoSkunz/crabitan_bellevue/commit/60bde07339f2f93233d555f52247c7b330bc6fa9))
* **model:** TrustedDeviceModel, DeviceConfirmTokenModel + extensions AccountModel/ConnectionModel/OrderModel ([18a261a](https://github.com/ApoSkunz/crabitan_bellevue/commit/18a261a88dce73f56cd754dcdf92eaf0e42c37f2))
* **routes:** MFA, sécurité admin, stats CA, appareils, réinitialisation ([ab13233](https://github.com/ApoSkunz/crabitan_bellevue/commit/ab1323338930676b8b7e2984e7b01baa4f1fc7a1))
* **routes:** nouvelles routes espace client ([9ec1047](https://github.com/ApoSkunz/crabitan_bellevue/commit/9ec10470c94344df66504c6563314edf5eb254e4))
* **routes:** routes espace compte + API favoris ([1bc264b](https://github.com/ApoSkunz/crabitan_bellevue/commit/1bc264b6cc5b1f0e393bf821a2d5ac79b4c1864a))
* **scss+js:** spinner MFA animation, modal reset sécurité, polling MFA ([7d0a08c](https://github.com/ApoSkunz/crabitan_bellevue/commit/7d0a08c2e24b7413ac61c30faa918402420d10a9))
* **scss:** adresses — suppression .form-phone-wrap/.form-phone-prefix, soft-lock styles ([b3808e5](https://github.com/ApoSkunz/crabitan_bellevue/commit/b3808e5d60f8e785295080c59ae1e9f4231fca8d))
* **scss:** btn--primary/danger/sm, layout espace compte ([f24625a](https://github.com/ApoSkunz/crabitan_bellevue/commit/f24625a6986c2c57ea49373888f3408f64e6d5f5))
* **scss:** formulaires espace compte, nav sticky dynamique via CSS variable ([921456b](https://github.com/ApoSkunz/crabitan_bellevue/commit/921456b93b90f8c313f577f9fee55192e5db4db5))
* **scss:** layout compte, modal panier succès, cartes vins favoris ([ea8c50c](https://github.com/ApoSkunz/crabitan_bellevue/commit/ea8c50c9906838c5fb9badeedb8172158d87f614))
* **scss:** timeline sans icônes, styles remise transport et notice contact ([e13fd87](https://github.com/ApoSkunz/crabitan_bellevue/commit/e13fd87c66cb3a3fa0c6044fe9d95b0d96598c0b))
* **service:** MailService — alerte nouvel appareil, lien révocation MFA, objet «Sécurité —» ([e05edc7](https://github.com/ApoSkunz/crabitan_bellevue/commit/e05edc7dc8b78482a9589c0c14e2941076238d02))
* **service:** MailService pièce jointe newsletter + lien Se désinscrire ([1e25940](https://github.com/ApoSkunz/crabitan_bellevue/commit/1e25940e3b9258d062457fa3f1be5730057c3905))
* **view:** account security SVG œil, favoris, export RGPD, header icônes ([3153077](https://github.com/ApoSkunz/crabitan_bellevue/commit/315307762355f71eea894cbf796f849773eadd77))
* **view:** admin — sécurité (sessions, appareils, reset modal), stats CA (Chart.js), nav ([734a5dd](https://github.com/ApoSkunz/crabitan_bellevue/commit/734a5dd1534c182dd0edb88ebffe2c8da560c63b))
* **view:** admin — statut return_requested dans labels commandes et dashboard ([7dad3a2](https://github.com/ApoSkunz/crabitan_bellevue/commit/7dad3a27118ab546642b93ff77b606a25161c9f6))
* **view:** adresses — champs réorganisés, BAN datalist, téléphone international, soft-lock ([46b23a5](https://github.com/ApoSkunz/crabitan_bellevue/commit/46b23a544b3c906ba92149b20cb6c232472dcb78))
* **view:** commandes — filtre statut, facture nouvel onglet, timeline, remise transport, retour/annulation ([67d864f](https://github.com/ApoSkunz/crabitan_bellevue/commit/67d864f5d03b7cadd021fadc6bc959af4f4bee9c))
* **view:** désabonnement confirmation, admin newsletter PDF/validation, news validation, orders RGPD ([bbaf9be](https://github.com/ApoSkunz/crabitan_bellevue/commit/bbaf9bed0e7cf9e2ea8abdd85b727e815bf17a4a))
* **view:** espace compte — dashboard, commandes, adresses, favoris, sécurité, export ([b30a569](https://github.com/ApoSkunz/crabitan_bellevue/commit/b30a569c787f369e150649845ad844ea17208292))
* **view:** espace compte — MFA (new_device, device_confirmed, mfa_cancelled), sécurité, nav icônes ([4b64178](https://github.com/ApoSkunz/crabitan_bellevue/commit/4b64178a45d8d9e28a524f923cda980a37aa9155)), closes [#mfa-denied](https://github.com/ApoSkunz/crabitan_bellevue/issues/mfa-denied)
* **view:** FAQ support q12-q13 (sessions, export RGPD) + chemins mis à jour ([1039276](https://github.com/ApoSkunz/crabitan_bellevue/commit/1039276c9212e1f67632610aafe39ccfdb5f7022))
* **view:** fiche vin + cartes — favori, specs réordonnées, quantité production ([bd56326](https://github.com/ApoSkunz/crabitan_bellevue/commit/bd563263fa901da0826aaf770ad4d2f0aa90ddc6))
* **view:** nav compte sticky JS dynamique, profil avec mailto email change ([5004c3f](https://github.com/ApoSkunz/crabitan_bellevue/commit/5004c3fc9f8d406612322604ab82aaec7788604d))
* **view:** page profil admin — changement mdp avec toggle œil, sidebar raccourcis complets ([ca01a38](https://github.com/ApoSkunz/crabitan_bellevue/commit/ca01a38fa903b0209ad6301b589e65e19760f99c))
* **view:** raccourcis panel header complets (admin + client) ([0571b84](https://github.com/ApoSkunz/crabitan_bellevue/commit/0571b84d502a7875634605d4e6b756c5ddc483a3))
* **view:** vues espace compte (commandes, adresses, sécurité, profil, export, favoris) ([7863c60](https://github.com/ApoSkunz/crabitan_bellevue/commit/7863c6080a3c96d26b83e45719a762e84d6679eb))

## [0.12.1](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.12.0...v0.12.1) (2026-03-26)


### Bug Fixes

* **ci:** CodeQL — ajout permissions security-events:write + actions:read ([175e4ec](https://github.com/ApoSkunz/crabitan_bellevue/commit/175e4ec09678b01991a39651526707b41491ebc8))

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
