# Changelog

All notable changes to Crabitan Bellevue are documented here.

## [1.27.2](https://github.com/ApoSkunz/crabitan_bellevue/compare/v1.27.1...v1.27.2) (2026-04-25)


### Bug Fixes

* **ci:** dast-full — upload explicite rapport ZAP HTML+JSON après chaque scan ([8771d11](https://github.com/ApoSkunz/crabitan_bellevue/commit/8771d1144b72ab5c1aea96de10f11aedbb51ceb6))

## [1.27.1](https://github.com/ApoSkunz/crabitan_bellevue/compare/v1.27.0...v1.27.1) (2026-04-25)


### Bug Fixes

* **test:** ErrorLoggerTest — purge logs dans setUp() pour ordre aléatoire Infection ([c594847](https://github.com/ApoSkunz/crabitan_bellevue/commit/c5948478195750c18246649f17ac251dd9b773ee))

# [1.27.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v1.26.0...v1.27.0) (2026-04-25)


### Bug Fixes

* **ci:** mutation.yml — coverage-xml path + junit pour Infection ([e11c1c2](https://github.com/ApoSkunz/crabitan_bellevue/commit/e11c1c23f68f5cefc14e91e547bd3008e160c1ad))
* **test:** ErrorLoggerTest::testInfoLogsWithInfoLevel — purge log avant appel ([751ee05](https://github.com/ApoSkunz/crabitan_bellevue/commit/751ee0579fbf784a393c381c23b15e8231fac832))


### Features

* **admin:** cron monitoring — modal log complet, CSP nonce, responsive ([5ea9b58](https://github.com/ApoSkunz/crabitan_bellevue/commit/5ea9b582a1a37c855c431866f3fcf3d6515f6e42))
* **scss:** cron-log-cell — empilement vertical excerpt/erreur/bouton sur mobile ([cb3b887](https://github.com/ApoSkunz/crabitan_bellevue/commit/cb3b8879d0a3865df7c81b9be3b110430607ec19))


### Reverts

* **controller:** restaurer doExit() — suppression non autorisée ([d16c63e](https://github.com/ApoSkunz/crabitan_bellevue/commit/d16c63e54427d3b14f97ac1d765d2afe389df4c4))

# [1.26.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v1.25.2...v1.26.0) (2026-04-24)


### Bug Fixes

* **a11y:** add aria-label on header cart button — button-name LHCI P1 fix (all pages) ([90b8094](https://github.com/ApoSkunz/crabitan_bellevue/commit/90b80940819fa3e436ed394a34cb5b4822b120c3))
* **cs:** PHPCS PSR12 — split long line in AccountPreferenceController::unsubscribePage ([7b6caf0](https://github.com/ApoSkunz/crabitan_bellevue/commit/7b6caf0288888057431a1897d2037669e32879f1))
* **geo:** allow AI crawlers (GPTBot, Google-Extended, anthropic-ai) — GEO visibility ([69e6d65](https://github.com/ApoSkunz/crabitan_bellevue/commit/69e6d65735d845a1b213e967084f184a0acb045b))
* **geo:** llms.txt — retirer is_for_sale (colonne inexistante) + bypass age-gate ([0a34adc](https://github.com/ApoSkunz/crabitan_bellevue/commit/0a34adcc602b917fbdc246438d786034707f8fce))
* **legal:** directeur de publication → Nicolas Solane (gérant GFA — LCEN Art. 6 III) ([0181b6f](https://github.com/ApoSkunz/crabitan_bellevue/commit/0181b6f8eab323359472da15449276a9f5b13dcf))
* **sonar:** S3923 checkout identical branches + S112 MailRateLimitException ([177a7d2](https://github.com/ApoSkunz/crabitan_bellevue/commit/177a7d29c41491f38bd2a63fda38ca548f730fba))
* **tests:** DB singleton mock before model construction — 74 CI tests (PDOException) ([c744cac](https://github.com/ApoSkunz/crabitan_bellevue/commit/c744cace6de2af13e86ecc394791174db4b56d59))
* **test:** testRateLimitReturnsPending429 — time() + 5 pour éviter le timing race sur second boundary ([bf1cbb7](https://github.com/ApoSkunz/crabitan_bellevue/commit/bf1cbb7449d25dc12dac52180c41a584403184a4))


### Features

* **ci:** DAST — split customer/admin en 2 jobs parallèles (timeout 120 min chacun) ([301e7ed](https://github.com/ApoSkunz/crabitan_bellevue/commit/301e7ed3fec822b90c0220ac546472bd354192e8))
* **geo:** FAQPage JSON-LD on /support + Article schema on /le-chateau and /savoir-faire ([47c9d42](https://github.com/ApoSkunz/crabitan_bellevue/commit/47c9d4264bcca34268381aa5190cd803e593c053))
* **geo:** LocalBusiness fusionné Winery home — telephone, geo, hasMap (GEO 9.5/10) ([ad21421](https://github.com/ApoSkunz/crabitan_bellevue/commit/ad2142114aa0354e03fb7631c91a96da67e43f72))
* **geo:** Twitter cards, llms.txt dynamic route, AI crawler blocking ([87dc3db](https://github.com/ApoSkunz/crabitan_bellevue/commit/87dc3db948a02d3e2c4985b94702595e1f68198d))
* **seo:** noindex controllers — panier, checkout, confirmation, rating, newsletter, désabonnement ([48197f0](https://github.com/ApoSkunz/crabitan_bellevue/commit/48197f0dad6de9de1e15d9daed34c547c8e4fc56))
* **seo:** noindex toutes les vues mon-compte — 17 fichiers ([bb23dc2](https://github.com/ApoSkunz/crabitan_bellevue/commit/bb23dc228916e37e191a1ebe87ef39a7282740ec))

## [1.25.2](https://github.com/ApoSkunz/crabitan_bellevue/compare/v1.25.1...v1.25.2) (2026-04-24)


### Bug Fixes

* **ci:** mutation.yml — PHPUnit génère coverage, Infection le réutilise ([c2b30c7](https://github.com/ApoSkunz/crabitan_bellevue/commit/c2b30c71256c5937353e7e599bd0ba5f823781b9))
* **ci:** mutation.yml — supprime trigger pull_request, main uniquement ([ebdf817](https://github.com/ApoSkunz/crabitan_bellevue/commit/ebdf81702350025588e07e3ba5cdcf48a95131ed))
* **cs:** PHPCS — brace vide + ligne trop longue dans tests coverage ([40684b5](https://github.com/ApoSkunz/crabitan_bellevue/commit/40684b5a91d3a6d20ce2ac9295704e982eca0aa9))
* **test:** DpoAdminControllerTest — namespace file_exists override au lieu de ROOT_PATH ([23468c5](https://github.com/ApoSkunz/crabitan_bellevue/commit/23468c51985cdbd30989f2c9c7dabb4a18a14a9b))
* **test:** ErrorLoggerTest — rename au lieu de rm pour testWriteCreatesLogDirWhenMissing ([2972c2a](https://github.com/ApoSkunz/crabitan_bellevue/commit/2972c2ad4b89776bdc7ebf164af479743ee644d8))

## [1.25.1](https://github.com/ApoSkunz/crabitan_bellevue/compare/v1.25.0...v1.25.1) (2026-04-24)


### Bug Fixes

* **ci:** ZAP v0.13.0 — retirer context_file_name/ajax_spider/url-list invalides ([e3a0eec](https://github.com/ApoSkunz/crabitan_bellevue/commit/e3a0eecdb3c7b361d2129002bee3dcad858124df))
* **test:** aligner sendOrderConfirmationToClient sur signature 10 params ([c4db157](https://github.com/ApoSkunz/crabitan_bellevue/commit/c4db1571e126f7d5067283db1fab51693a8dd296))

# [1.25.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v1.24.0...v1.25.0) (2026-04-24)


### Bug Fixes

* **env:** DB_ENCRYPT_KEY sans fallback getenv + CA_PUBKEY_PATH fallback config/ca-pubkey.pem ([584265e](https://github.com/ApoSkunz/crabitan_bellevue/commit/584265eca0085b1172a67dc60b8795a9934e05c3))


### Features

* **env:** fallbacks prod — mail, emails métier, APP_URL/NAME, CA_SANDBOX_MODE ([ed8a532](https://github.com/ApoSkunz/crabitan_bellevue/commit/ed8a5327f395860a782d6403f1b93832f176f7ee))

# [1.24.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v1.23.0...v1.24.0) (2026-04-24)


### Bug Fixes

* **admin:** nav Configuration visible admin + super_admin, retrait doublon footer ([9464af3](https://github.com/ApoSkunz/crabitan_bellevue/commit/9464af333bb62b6deae032cc4a60b981779c8f45))
* **admin:** verify() requiert super_admin (était requireAdmin) ([6e50e80](https://github.com/ApoSkunz/crabitan_bellevue/commit/6e50e80a01e5695470b8c69dfd9d7d5e3d2e35ba))
* **auth:** aligne fallback JWT_EXPIRY à 7200s (2h) dans tous les fichiers ([61e1e3c](https://github.com/ApoSkunz/crabitan_bellevue/commit/61e1e3cedf9eec29ea3320c2edb10db7f0fe170a))
* **auth:** JWT sliding refresh — régénérer token + cookie à chaque refresh standard ([d4ad85a](https://github.com/ApoSkunz/crabitan_bellevue/commit/d4ad85af944376029dfe474cc607c0fdce08e8c1))
* **cs:** PHPCS PSR12 — un argument par ligne dans l'anonymous class makeController ([ae047fc](https://github.com/ApoSkunz/crabitan_bellevue/commit/ae047fcb6a53b042d93b696f24ec4f01a3fe4388))
* **feature-flags:** blocage 1h après 3 échecs OTP + fix flash admin_flash + CSP style ([c9cebdb](https://github.com/ApoSkunz/crabitan_bellevue/commit/c9cebdb23241ff11916602ddfcfc4228a35ec454))
* **feature-flags:** CSRF token name + data-admin-confirm sur le form ([0073dc0](https://github.com/ApoSkunz/crabitan_bellevue/commit/0073dc0cc4edccdbd73863aaf5c0c639db033880))
* **feature-flags:** OTP modal — bloquer à 0 tentatives + flash visible + message annulation ([76eb44a](https://github.com/ApoSkunz/crabitan_bellevue/commit/76eb44af40fbc677af5eb690adc019ff63927fbd))
* **scss:** contrast WCAG AA sur bouton et input disabled (css:S7924) ([3e452bd](https://github.com/ApoSkunz/crabitan_bellevue/commit/3e452bd5b604383810df54cc5dba6f1ccc6c51c9)), closes [#e5e7eb](https://github.com/ApoSkunz/crabitan_bellevue/issues/e5e7eb) [#ffffff](https://github.com/ApoSkunz/crabitan_bellevue/issues/ffffff) [#9ca3af](https://github.com/ApoSkunz/crabitan_bellevue/issues/9ca3af) [#6b7280](https://github.com/ApoSkunz/crabitan_bellevue/issues/6b7280)
* **scss:** input disabled color [#6](https://github.com/ApoSkunz/crabitan_bellevue/issues/6)b7280→[#4](https://github.com/ApoSkunz/crabitan_bellevue/issues/4)b5563 (ratio 4.3→6.4:1, WCAG AA) ([e7b3eda](https://github.com/ApoSkunz/crabitan_bellevue/commit/e7b3eda6f3dc88aec8ea0461a120a44f26449d7f)), closes [#4b5563](https://github.com/ApoSkunz/crabitan_bellevue/issues/4b5563) [#6b7280](https://github.com/ApoSkunz/crabitan_bellevue/issues/6b7280) [#f3f4f6](https://github.com/ApoSkunz/crabitan_bellevue/issues/f3f4f6) [#4b5563](https://github.com/ApoSkunz/crabitan_bellevue/issues/4b5563)
* **scss:** revert couleurs disabled — faux positifs S7924 (WCAG exempte disabled) ([58bbad8](https://github.com/ApoSkunz/crabitan_bellevue/commit/58bbad8d5f6f789d8029ad4f15e580662de9ad8b)), closes [9ca3af/#e5e7eb](https://github.com/ApoSkunz/crabitan_bellevue/issues/e5e7eb) [#9ca3af](https://github.com/ApoSkunz/crabitan_bellevue/issues/9ca3af)
* **sonar:** corrige 8 issues PR[#215](https://github.com/ApoSkunz/crabitan_bellevue/issues/215) (bug, smells, contraste, a11y) ([0637f52](https://github.com/ApoSkunz/crabitan_bellevue/commit/0637f5219f2317256a563e500f44e976f38e3a16)), closes [e5e7eb/#9ca3af](https://github.com/ApoSkunz/crabitan_bellevue/issues/9ca3af) [fff/#6b7280](https://github.com/ApoSkunz/crabitan_bellevue/issues/6b7280) [#9ca3af](https://github.com/ApoSkunz/crabitan_bellevue/issues/9ca3af) [#4b5563](https://github.com/ApoSkunz/crabitan_bellevue/issues/4b5563)


### Features

* **admin:** feature-flags — batch save + toggle switches + catégories UI ([069c032](https://github.com/ApoSkunz/crabitan_bellevue/commit/069c0320762d447e5316a5994048b16638492e16))
* **admin:** feature-flags — section Configuration sidebar + alignement vue ([f4d0815](https://github.com/ApoSkunz/crabitan_bellevue/commit/f4d08159bb03c7416a590676ad07c470777e1fa2))
* **admin:** feature-flags lecture-seule pour admin, modification super_admin uniquement ([db0cec8](https://github.com/ApoSkunz/crabitan_bellevue/commit/db0cec8be2cab0b3d5350ce361923dd6ae1061f6))
* **admin:** feature-flags UI — toggle aligné, responsive, bouton disabled, CSP nonce ([4a09da7](https://github.com/ApoSkunz/crabitan_bellevue/commit/4a09da7fd48ac49842e63d4ee626c95f84bb69d9))
* **admin:** vérification email compte — accessible à admin + super_admin ([ce642d0](https://github.com/ApoSkunz/crabitan_bellevue/commit/ce642d006be97853cad883d84f824d9eeadfef9c))
* **controller:** FeatureFlagsAdminController — gestion flags OTP email ([d70967f](https://github.com/ApoSkunz/crabitan_bellevue/commit/d70967f9321528067c9f741b50a17819df06b808))
* **core:** FeatureFlags — cache statique BDD, seams de test ([25716a8](https://github.com/ApoSkunz/crabitan_bellevue/commit/25716a8f6173d9ca413a6b980053d30d414066d2))
* **cron:** purge_admin_otps — nettoyage horaire des OTP admin expirés ([5f2fcb0](https://github.com/ApoSkunz/crabitan_bellevue/commit/5f2fcb0ae428ec0395d3a567e419e713d60827fd))
* **cron:** purge-logs + purge-admin-otps HTTP + rotation 90j cohérente ([d8020a1](https://github.com/ApoSkunz/crabitan_bellevue/commit/d8020a18ca974504824898b4fdaba968aadc0235))
* **cron:** purge-logs 30j — bin CLI + HTTP + CronRunner::rotate aligné ([93e31d3](https://github.com/ApoSkunz/crabitan_bellevue/commit/93e31d3ce1310a8e8d849e08086278c14e57c631))
* **feature-flags:** flash dans modal OTP + tentatives restantes + animation soumission ([b79f39a](https://github.com/ApoSkunz/crabitan_bellevue/commit/b79f39afa8cfc336e9584612990189be9acf01d0))
* **feature-flags:** OTP email tirets par chiffre + modal admin avec compte à rebours ([661d3ef](https://github.com/ApoSkunz/crabitan_bellevue/commit/661d3ef1ee227f5c4a0f67de3d57150f5c75a02d))
* **feature-flags:** OTP modal — 6 inputs individuels avec tiret par chiffre ([6ab6b32](https://github.com/ApoSkunz/crabitan_bellevue/commit/6ab6b324b72328b119f9ff4e849fb027ea5b4c53))
* **mail:** sendFeatureFlagOtp — OTP modification feature flag ([ea9d4e7](https://github.com/ApoSkunz/crabitan_bellevue/commit/ea9d4e71f14bb12057aacd1159ed3870c5f50bfb))
* **routes:** /admin/feature-flags + nav admin feature_flags ([ceceb11](https://github.com/ApoSkunz/crabitan_bellevue/commit/ceceb115f8fd456702ca60bb16047010d0c99c60))
* **security:** OTP feature-flags — blocage 1h immédiat au 3e échec + email alerte ([a1def1b](https://github.com/ApoSkunz/crabitan_bellevue/commit/a1def1bbb43d66e4984ab9be0739b75c867b0deb))
* **security:** table admin_otps — OTP feature flags stocké en BDD ([28fe699](https://github.com/ApoSkunz/crabitan_bellevue/commit/28fe699213977f5ba22d3a1ec703b4479b188d01))
* **ui:** feature-flags — UX OTP complète + menu front + sidebar scroll fix ([c2236ce](https://github.com/ApoSkunz/crabitan_bellevue/commit/c2236ce6ad1b32ea7d66b7c4877f5b71f9511f1a))
* **view:** admin/feature-flags/index.php — liste flags + formulaire OTP ([6e42774](https://github.com/ApoSkunz/crabitan_bellevue/commit/6e42774613c92379f0d6f3a9c7ddde1158e5539a))

# [1.23.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v1.22.1...v1.23.0) (2026-04-24)


### Bug Fixes

* **age-gate:** ajout /fiche-technique/qr en whitelist (loader QR) ([e376773](https://github.com/ApoSkunz/crabitan_bellevue/commit/e3767737688c75d0a1e3ba9d4dc56b67e9b570a6))
* **db:** null coalesce sur DB_USER + DB_PASS pour éviter E_WARNING local ([b8e80e0](https://github.com/ApoSkunz/crabitan_bellevue/commit/b8e80e07eeb7fbeacfcb7cdbde4f48c2580d7070))
* **i18n:** wording contact.error_rate_limit — "trop de demandes sur la dernière heure" ([b9cc4bb](https://github.com/ApoSkunz/crabitan_bellevue/commit/b9cc4bb8351262152063eb4dd8345aa517ae8a59))
* **orders:** label "Remise Click & Collect" aussi mail owner + PDF + admin show ([f1e03f5](https://github.com/ApoSkunz/crabitan_bellevue/commit/f1e03f5c370e5d29606deef9d4732c7e5c6e7c36))
* **seam+sonar:** newMailService() dans paymentReturnOk + S3358 buildAddressBlock titleMode ([1124931](https://github.com/ApoSkunz/crabitan_bellevue/commit/11249319d506ef6083b50abb0bada8a1958eda02))
* **tests:** corriger testStartSessionIsOverridableSeam — appel $this->startSession() direct ([9227743](https://github.com/ApoSkunz/crabitan_bellevue/commit/92277430cb700dbbeb8c94ff5dcbcd8d4ec22e50))
* **tests:** isoler session_write_close dans RateLimiterServiceTest — fix testRateLimitReturnsPending429 ([b2131df](https://github.com/ApoSkunz/crabitan_bellevue/commit/b2131df7a34509f5e800871ffc070691d551f7ed))
* **tests:** migrer AccountModelTest TI vers AccountAdminFilters DTO ([516e657](https://github.com/ApoSkunz/crabitan_bellevue/commit/516e657ff180c88210f857877322d65d79c56ec1))
* **tests:** résoudre 3 erreurs CI + 1 failure + risky + warnings ([9d4e262](https://github.com/ApoSkunz/crabitan_bellevue/commit/9d4e262fc4747c310f35c3ca5774af6f1558607b))
* **tests:** résout failure + warning + 4 skipped ([34560fa](https://github.com/ApoSkunz/crabitan_bellevue/commit/34560fa0918f73af12fd62d3e564875df1445e97))
* **tests:** retire dernier skipped — doExit() throw HttpException(0) pour testabilité ([9670306](https://github.com/ApoSkunz/crabitan_bellevue/commit/96703061c39efb9cfdffddef7a30bcb50a87032e))
* **tests:** signature log() compatible avec EmailRateLogModel::log() (+ $type param) + login rate limit 10 ([99c29e9](https://github.com/ApoSkunz/crabitan_bellevue/commit/99c29e9a06fce23c3b9be016078b0cc284ab1af5))
* **ux:** aligner Réinitialiser factures + label pickup admin + adresses cancelled + QR progression + responsive ([26d4b20](https://github.com/ApoSkunz/crabitan_bellevue/commit/26d4b206c8573faf61f58e274ebd3044005c7e27))
* **ux:** effet hover Vérifier (vert) + Réinit. mdp (orange) + retrait border adresses ([b7529fc](https://github.com/ApoSkunz/crabitan_bellevue/commit/b7529fc5e1ac013923e06a3b6f936c3e664f440e)), closes [#15803d](https://github.com/ApoSkunz/crabitan_bellevue/issues/15803d) [#92400e](https://github.com/ApoSkunz/crabitan_bellevue/issues/92400e)
* **ux:** feedback recette post-prod-1 batch 1 ([8b9ce3d](https://github.com/ApoSkunz/crabitan_bellevue/commit/8b9ce3d70b33e2439830781b68117f41d8e199ec))
* **ux:** masquer adresses + bloc pickup château sur commande annulée + adresse château si pickup ([e736c26](https://github.com/ApoSkunz/crabitan_bellevue/commit/e736c26fd21613df8793943b2d07f2d4c818dfcc))
* **ux:** menu admin sections + QR loader % réel + timeline pickup + adresse château + pricing ([8f3644b](https://github.com/ApoSkunz/crabitan_bellevue/commit/8f3644bdbcedbc55af99e0c93439dcc990a13d7a))
* **ux:** menu sections split + colonne mode orders + QR loader CSP nonce + delivery_method queries ([8023d3b](https://github.com/ApoSkunz/crabitan_bellevue/commit/8023d3b98330a6f68b78d9e52a5c881ff09bc114))
* **ux:** variante admin-btn--dark (retour outline transparent) + factures recherche + QR blob fallback + label remise C&C ([dace47d](https://github.com/ApoSkunz/crabitan_bellevue/commit/dace47d7238d900592dce459b56f77190921fea0))


### Features

* **admin:** afficher + éditer remise Click & Collect (€/btl) depuis vue tarifs ([07cbb2a](https://github.com/ApoSkunz/crabitan_bellevue/commit/07cbb2a20cb0cd28bdfff1fdc070cc2472ad074e))
* **admin:** comptes — filtre has_ordered, tri order_count, split colonnes Vérif/Réinit ([400cd3e](https://github.com/ApoSkunz/crabitan_bellevue/commit/400cd3e52e455a71f417ccd275699e3250a5fea6))
* **admin:** monitoring crons + mode livraison orders + menu admin ([28ecc73](https://github.com/ApoSkunz/crabitan_bellevue/commit/28ecc73cac090d56f64a1cc641ba95c64be90430))
* **admin:** monitoring emails — pagination 25/50/100 + colonne type ([3879751](https://github.com/ApoSkunz/crabitan_bellevue/commit/3879751d59c04c243eca1ca623270034c932e382))
* **admin:** monitoring emails — table email_rate_log + onglet super_admin + détection rate limit IONOS ([65a91c1](https://github.com/ApoSkunz/crabitan_bellevue/commit/65a91c15bc9bc6443449b1740ea3b0777dd1cbdc))
* **admin:** remise Click & Collect configurable BDD (singleton) + retire env var ([6f31f0e](https://github.com/ApoSkunz/crabitan_bellevue/commit/6f31f0e83ef3916de9b5331486e997fdeae68e9d))
* **admin:** types emails i18n + quota 500/h + activité comptes + logs apps + workflow C&C ([f6f49da](https://github.com/ApoSkunz/crabitan_bellevue/commit/f6f49da0602ed845aa2e463f341303e25b633798))
* **boutique:** onglet dépliant remises livraison — wines + collection ([635d3f9](https://github.com/ApoSkunz/crabitan_bellevue/commit/635d3f9be55d91f35bc3bd05585fff0282ea45be))
* **click-collect:** feature flag CLICK_COLLECT_ENABLED + statuts ready_for_pickup/collected + checkout + badge ([3a18e81](https://github.com/ApoSkunz/crabitan_bellevue/commit/3a18e8172a47a36dacd39ecc32e53f2349c4b963))
* **contact:** champ téléphone facultatif + fix mock sendContactToOwner signature ([0bf42c0](https://github.com/ApoSkunz/crabitan_bellevue/commit/0bf42c0d3bac2ee6521c9aa9e14afb0766373983))
* **mail:** récap adresses livraison/facturation + mode dans mails commande client + owner ([b89b809](https://github.com/ApoSkunz/crabitan_bellevue/commit/b89b8091f0c65073ace5be04f3da0a716bbb63f6))
* **seo:** sitemap-products.xml — vins disponibles à la vente ([3562c01](https://github.com/ApoSkunz/crabitan_bellevue/commit/3562c01da847aa77a3456774bd9d0dada28d0c23))
* **ux:** spinner chargement QR code admin — modale wines ([a305fe8](https://github.com/ApoSkunz/crabitan_bellevue/commit/a305fe8340004326dd14cb376145fac77aec3212))


### Reverts

* Revert "feat(admin): monitoring emails — table email_rate_log + onglet super_admin + détection rate limit IONOS" ([f5ef866](https://github.com/ApoSkunz/crabitan_bellevue/commit/f5ef8664d32c2f2ee40f2e7b9b92acb56995159a))

## [1.22.1](https://github.com/ApoSkunz/crabitan_bellevue/compare/v1.22.0...v1.22.1) (2026-04-22)


### Bug Fixes

* **contact:** catch Throwable pour rate limit 429 sans casser fallback 500 ([fd96627](https://github.com/ApoSkunz/crabitan_bellevue/commit/fd9662708d97276247b8570c880126b31a89085a))
* **phpstan:** retirer return inatteignables après json() (never return type) ([199f6ab](https://github.com/ApoSkunz/crabitan_bellevue/commit/199f6ab58f9055d459483ae58ed7031ad9971791))

# [1.22.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v1.21.4...v1.22.0) (2026-04-22)


### Features

* **admin:** comptes — filtre has_ordered, tri order_count, split colonnes Vérif/Réinit ([9cb77a5](https://github.com/ApoSkunz/crabitan_bellevue/commit/9cb77a5bc4a5ce5cbefd87df25d70ea801e62f8c))
* **admin:** monitoring emails — table email_rate_log + onglet super_admin + détection rate limit IONOS ([2d1a5b4](https://github.com/ApoSkunz/crabitan_bellevue/commit/2d1a5b4f000ed9fe579c7fecdfa758afa511dfa7))
* **boutique:** onglet dépliant remises livraison — wines + collection ([f119731](https://github.com/ApoSkunz/crabitan_bellevue/commit/f11973170fbd48379f78fdfc133c1acdae426927))
* **click-collect:** feature flag CLICK_COLLECT_ENABLED + statuts ready_for_pickup/collected + checkout + badge ([b49c2ad](https://github.com/ApoSkunz/crabitan_bellevue/commit/b49c2adb92d9eb22325d3b41cb8b889e70b76a81))
* **contact:** champ téléphone facultatif + fix mock sendContactToOwner signature ([db8699f](https://github.com/ApoSkunz/crabitan_bellevue/commit/db8699f79e873384147396b2de470afed294c803))
* **ux:** spinner chargement QR code admin — modale wines ([f29ffa2](https://github.com/ApoSkunz/crabitan_bellevue/commit/f29ffa254d3791fa1e2e7518547436619f753373))

## [1.21.4](https://github.com/ApoSkunz/crabitan_bellevue/compare/v1.21.3...v1.21.4) (2026-04-22)

## [1.21.3](https://github.com/ApoSkunz/crabitan_bellevue/compare/v1.21.2...v1.21.3) (2026-04-22)


### Bug Fixes

* **tests:** corriger 3 regressions CI chore/coverage-99-v2 ([ebc8599](https://github.com/ApoSkunz/crabitan_bellevue/commit/ebc8599f672dd8c7609d4bdffcb8a70313643c76))

## [1.21.2](https://github.com/ApoSkunz/crabitan_bellevue/compare/v1.21.1...v1.21.2) (2026-04-22)


### Bug Fixes

* **exit-intent:** délai 15 min avant affichage sur boutique et collection ([11df63f](https://github.com/ApoSkunz/crabitan_bellevue/commit/11df63f6b85892d68382b15d36c9126a1fa54739))
* **exit-intent:** supprimer pop-in achat si client avec commande(s) ([6e07873](https://github.com/ApoSkunz/crabitan_bellevue/commit/6e078732a0b93e54e59844853ac94248df815df3))
* **sonar:** S1066 footer.php — fusionner if imbriqués exit-intent ([3f0177b](https://github.com/ApoSkunz/crabitan_bellevue/commit/3f0177b5427ac66c84b45aa94d52c7ee46f2e0b5))

## [1.21.1](https://github.com/ApoSkunz/crabitan_bellevue/compare/v1.21.0...v1.21.1) (2026-04-22)


### Bug Fixes

* **security:** restreindre /bons-de-commande/{id}/telecharger à admin (IDOR) ([9e51fce](https://github.com/ApoSkunz/crabitan_bellevue/commit/9e51fce54408dc6048067898608b35b3b017f9f9))
* **seo:** whitelister sitemaps et robots.txt dans isPublicPath (age-gate bypass) ([25af07c](https://github.com/ApoSkunz/crabitan_bellevue/commit/25af07c637efdea0e3ddca17d47b983130cec944))
* **tests:** extraire requireAdminAccess() seam pour débloquer OrderFormControllerTest ([67cdb7d](https://github.com/ApoSkunz/crabitan_bellevue/commit/67cdb7dde83e0344407c76e504dcc9891e03cd65))

# [1.21.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v1.20.0...v1.21.0) (2026-04-21)


### Bug Fixes

* **cv:** patch middleware ~10 applications (pas 16) ([f3c2e14](https://github.com/ApoSkunz/crabitan_bellevue/commit/f3c2e14057ca76884ff40da00731f16dbdc53460))
* **cv:** PowerShell → Développement, MySQL → Middlewares & BDD ([226d2a7](https://github.com/ApoSkunz/crabitan_bellevue/commit/226d2a759c0a9ae8c0bfb2a427b1d0715e6f1952))
* **security:** VULN-001/002 — validation same-origin JS + sanitizeRedirect MajorityController ([013874d](https://github.com/ApoSkunz/crabitan_bellevue/commit/013874d645c3a4c92b43ebb685e6a85200d93f96))
* **sonar:** APP_BASE_URL constant + CronController unused var ([4f859e8](https://github.com/ApoSkunz/crabitan_bellevue/commit/4f859e849423cfd01d9ecd6e714e13d4a039b651))
* **tests:** ajouter APP_BASE_URL dans bootstrap TI — constant manquante ([d91237e](https://github.com/ApoSkunz/crabitan_bellevue/commit/d91237eb4e30b2f4c4972ebc3c52415565a89b1c))


### Features

* **cv:** ajustements CV Alexandre Solane — formation + Capgemini + EDF ([c52776b](https://github.com/ApoSkunz/crabitan_bellevue/commit/c52776bc0f0f4827dce3626f1a05a56cc5a1efb0))
* **cv:** clarté EDF (tenure global + 2 sous-rôles) + métriques CI/CD ([7dc73ba](https://github.com/ApoSkunz/crabitan_bellevue/commit/7dc73ba2a3761903a0b36b003e4c1871a684de5e))
* **cv:** edf_sub_task8 — patch middleware/hotfix < 1h sur 16 applications ([583b713](https://github.com/ApoSkunz/crabitan_bellevue/commit/583b71347666d75fd6ebead3e861d7fb30619f12))
* **cv:** intro rewriting + signature binaire + compétences ([e38ae9f](https://github.com/ApoSkunz/crabitan_bellevue/commit/e38ae9f0850d15a1e7dd932dc110ba0586acf05a))
* **cv:** résultats EDF au niveau company (global nov. 2023 → présent) ([6a80f89](https://github.com/ApoSkunz/crabitan_bellevue/commit/6a80f89d8f4d29362bca6e8bae6f5f5d8a76ace6))
* **cv:** section Langues + section Projets personnels + retrait TOEIC/Chinois Télécom ([ed9b189](https://github.com/ApoSkunz/crabitan_bellevue/commit/ed9b18976847e3120cc096a000a953132099eff1))
* **cv:** TOEIC+Chinois même ligne, compétences Terraform/Docker/PowerShell/DAST/MAST/Oracle ([2b760d4](https://github.com/ApoSkunz/crabitan_bellevue/commit/2b760d45f6fdbbe2119249b10100dd5187be1a0a))
* **seo:** canonical + hreflang + OG + JSON-LD NewsArticle + AggregateRating sur 17 vues ([d05cf44](https://github.com/ApoSkunz/crabitan_bellevue/commit/d05cf4482cb4afd0f0f0a9a703634f5d8f0e8b99))

# [1.20.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v1.19.0...v1.20.0) (2026-04-21)


### Bug Fixes

* **cron:** S1192 constantes APP_URL_FALLBACK/LOG_OK/LOG_ORDER_REF + retrait \$unsubUrl inutilisé + ordered_at ([16a60de](https://github.com/ApoSkunz/crabitan_bellevue/commit/16a60de03c2d3819d3c6b0bb8344fd263283c678))


### Features

* **cron:** enregistrer send-newsletter-welcome-sequence, send-reconquest, send-review-request dans CronController ([917229a](https://github.com/ApoSkunz/crabitan_bellevue/commit/917229a13a9644bd38eda8066bc5be23a0c6309d))
* **ux:** exit-intent — cookie 7 jours par type (newsletter / commerce) ([ee1a9a4](https://github.com/ApoSkunz/crabitan_bellevue/commit/ee1a9a44dbd7749c19629276f1b590deb83bc3b5))

# [1.19.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v1.18.0...v1.19.0) (2026-04-21)


### Bug Fixes

* **i18n:** age-gate slogan + sous-slogan + home CTAs FR + nav.wines EN déjà correct ([a74433f](https://github.com/ApoSkunz/crabitan_bellevue/commit/a74433fe100ba3d125e247f913b8c1e8423b2e83))
* **phpcs:** HomeController — split view() args array (ligne 151 chars) ([798571c](https://github.com/ApoSkunz/crabitan_bellevue/commit/798571c9168aba94f3b0bacb8412545a9b8461ad))
* **phpcs:** MailService — 0 warnings ligne longue (38 lignes splittées) ([f66827b](https://github.com/ApoSkunz/crabitan_bellevue/commit/f66827b46abd3c0014d871c9d432a671b522ad85))
* **phpcs:** TU controllers — MultipleClasses → fichiers séparés + lignes longues RatingModelTest ([1aa32f3](https://github.com/ApoSkunz/crabitan_bellevue/commit/1aa32f3651d46c85f66842db24e9120ab3aa66df))
* **sonar:** S107 NOSONAR same-line × 7, S3358 nested ternary, S1142 NOSONAR × 2, S6853 label for ([1211498](https://github.com/ApoSkunz/crabitan_bellevue/commit/12114988b69095be3c743aca7f0389323c172ef7))
* **sonar:** Web:S7930 exit-intent duplicate id → querySelector class ([b1ab52b](https://github.com/ApoSkunz/crabitan_bellevue/commit/b1ab52b329c1e639ba842bb3fd44d417ef8b30fb))
* **ux:** age-gate slogan une ligne (max-width 820→1100px) + exit-intent skip si déjà abonné newsletter ([b2b9679](https://github.com/ApoSkunz/crabitan_bellevue/commit/b2b9679767742d398607e178fce08324ad05079a))
* **ux:** retours recette — sous-slogan doré, retrait header-contact, savoir_cta, opt-in wine supprimé, exit-intent prefill email connecté ([706f44a](https://github.com/ApoSkunz/crabitan_bellevue/commit/706f44af4968bdf1cd63c33ec4fd0f642a1695d5))


### Features

* **account:** Mes notes + Mon avis — modification depuis l'espace client ([be15c21](https://github.com/ApoSkunz/crabitan_bellevue/commit/be15c21daf1e9282d64d8c1911271d2244d93481))
* **account:** vue mes-notes mosaïque + badge cuvée spéciale + flash review ([c426e65](https://github.com/ApoSkunz/crabitan_bellevue/commit/c426e6582478ab54354f6fb296d5be41402d9063))
* **controller:** ReviewController — show/submit/success + routes /{lang}/mon-avis ([ba278a4](https://github.com/ApoSkunz/crabitan_bellevue/commit/ba278a4351ef0b2913e5885090c2f3dfa67dcfa6))
* **cron:** guards anti-bruit J+21 + J+28 ([c117516](https://github.com/ApoSkunz/crabitan_bellevue/commit/c11751652b338641ebff4425078cfe9c4d93a921))
* **home:** inject ReviewModel::getRandom(3,4) — section témoignages pilotée par BDD ([054dc2b](https://github.com/ApoSkunz/crabitan_bellevue/commit/054dc2b78636ccba8b9f4d74518761774019aa6a))
* **i18n:** clés exit_intent.newsletter, cart.nudge, home.testimonials — FR/EN ([9d72107](https://github.com/ApoSkunz/crabitan_bellevue/commit/9d721076a461001929fa0d56a8271cce894d2e85))
* **i18n:** clés ratings_not_yet, testimonials_reviews, location_placeholder ([17cc25f](https://github.com/ApoSkunz/crabitan_bellevue/commit/17cc25fd7c02ce8ce46cb402666447fc978546c3))
* **i18n:** clés review.* — formulaire avis, messages FR/EN ([7c2ee17](https://github.com/ApoSkunz/crabitan_bellevue/commit/7c2ee1798c01d143aec3d296c13363fd8e4db99c))
* **js:** initExtraNewsletterForms — handler AJAX pour exit-intent et wine opt-in forms ([f6644ca](https://github.com/ApoSkunz/crabitan_bellevue/commit/f6644ca1e3640934a9c0bc68803c05b77814d9a5))
* **mail:** CTA 'Laisser un avis' dans sendPostPurchase J+21 — lien /{lang}/mon-avis?token= ([a9e6ad4](https://github.com/ApoSkunz/crabitan_bellevue/commit/a9e6ad47d6ae0d271f7485df4382a03f83ec72a1))
* **mail:** split post-achat J+21/J+28 — notation vins vs avis global séparés ([7d5d64e](https://github.com/ApoSkunz/crabitan_bellevue/commit/7d5d64ed863fbd00682e3b9aca3dae263592411e))
* **model:** ReviewModel — findByAccountId, create, getRandom(count, minRating) ([3b4fbf6](https://github.com/ApoSkunz/crabitan_bellevue/commit/3b4fbf68bc288066a88b0238763d005a0cb58147))
* **ratings:** getFirstOrderId, allWinesRated, getStats — guards cron + moyenne homepage ([2f9bfff](https://github.com/ApoSkunz/crabitan_bellevue/commit/2f9bfffe58364663ca7b574b88dfe03f911d2bc0))
* **rgpd:** customer_reviews inclus dans l'export RGPD buildForAccount() ([331eaac](https://github.com/ApoSkunz/crabitan_bellevue/commit/331eaac17b173e28bc6e3fdb5c9873ed4473df73))
* **scss:** account-ratings-grid mosaïque + home-testimonials__avg ([b383f4a](https://github.com/ApoSkunz/crabitan_bellevue/commit/b383f4a62530acc251956db166df72966ea87ac5))
* **scss:** age-gate__subquote + header-nav font-size clamp pour 7 items ([659c9b5](https://github.com/ApoSkunz/crabitan_bellevue/commit/659c9b562a72413b7bbbd8a7d945c24ef7e42d86))
* **scss:** review-form-page — star rating interactif + styles formulaire avis ([75075e6](https://github.com/ApoSkunz/crabitan_bellevue/commit/75075e68dff4535c8b70636cde32499621fa75d0))
* **scss:** testimonials + wine opt-in + cart nudge + exit-intent newsletter form styles ([a4db713](https://github.com/ApoSkunz/crabitan_bellevue/commit/a4db713b5c9ad3a76cff4826f3a68f56549fbfe7))
* **view:** age-gate subquote + nav desktop actualites/contact ([9275dc5](https://github.com/ApoSkunz/crabitan_bellevue/commit/9275dc5e3308d2117a359198f056c4fd0a669880))
* **view:** exit-intent newsletter form + témoignages home + cart nudge + wine opt-in ([a83b47d](https://github.com/ApoSkunz/crabitan_bellevue/commit/a83b47d349ebe41483ade180c6753ff728455904))
* **view:** formulaire avis client — star rating + commentaire + lieu + pages success/already-done ([92c1d9e](https://github.com/ApoSkunz/crabitan_bellevue/commit/92c1d9ebfe3697d289871cd60c79df75adc43baf))

# [1.18.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v1.17.0...v1.18.0) (2026-04-21)


### Bug Fixes

* **db:** reseed adresses mock avec clé DB_ENCRYPT_KEY courante ([ffa1e3d](https://github.com/ApoSkunz/crabitan_bellevue/commit/ffa1e3d3beb30ece1c2c2e4f503302691509fa07))
* **mail:** constants SALUTATION_FR/EN + PATH_CATALOG — Sonar S1192 ([a72d150](https://github.com/ApoSkunz/crabitan_bellevue/commit/a72d150ead39989964dc618d0a47c0b4b4ebaff9))
* **mail:** split ligne preheader emailWrap > 150 chars (PHPCS) ([0429aec](https://github.com/ApoSkunz/crabitan_bellevue/commit/0429aecb12caee4cb985066cb5bb3b2de20483d2))
* **scss:** checkout sticky CTA visible + wine-nav vintage/cuvee styles ([53751f2](https://github.com/ApoSkunz/crabitan_bellevue/commit/53751f237b256d54d8a1177b71b163f1106b9016))
* **view:** checkout TTC price sticky CTA + confirmation PHPCS fixes ([8d0d697](https://github.com/ApoSkunz/crabitan_bellevue/commit/8d0d697441a02b5b7d96e8109c38fbd0e9364564))


### Features

* **cron:** séquence bienvenue newsletter J+7/J+30 + reconquête + ciblage favoris ([60223aa](https://github.com/ApoSkunz/crabitan_bellevue/commit/60223aa83367940cb9932a207e2b311b7136be66))
* **i18n:** clés FR+EN marketing/UX — réassurance, upsell, breadcrumb, exit-intent ([2c8a86c](https://github.com/ApoSkunz/crabitan_bellevue/commit/2c8a86cb3703e4ba9aaa92d215d2c3f2b50e3908))
* **i18n:** exit_intent variant keys FR/EN — show/boutique/collection/cart ([5bd5cbd](https://github.com/ApoSkunz/crabitan_bellevue/commit/5bd5cbd72fb034fcd40887c4e2cf54dc38ac577b))
* **mail:** séquence newsletter J+7/J+30, reconquête, cross-sell, preheader ([0652096](https://github.com/ApoSkunz/crabitan_bellevue/commit/0652096665d2cc444f8a046f51e03d9be0ff24e0))
* **model/ctrl:** getAdjacentSlugs + ctx-aware prev/next navigation ([f9bb4e4](https://github.com/ApoSkunz/crabitan_bellevue/commit/f9bb4e45025b9c589c7d6561db3c017de17cbff7))
* **model:** getPendingSegmented — ciblage newsletter par couleur (favorites) ([01f6240](https://github.com/ApoSkunz/crabitan_bellevue/commit/01f62403b20ecb9e44296bb4fd4010c1f127b271))
* **view:** exit-intent overlay partial — 4 variants global via footer ([98307d4](https://github.com/ApoSkunz/crabitan_bellevue/commit/98307d4d605d11fde09e37be98b78ab127297253))
* **view:** marketing/UX batch — breadcrumb, réassurance, upsell, exit-intent, civility ([c44c840](https://github.com/ApoSkunz/crabitan_bellevue/commit/c44c8404bae9d5e6dcb6d1a10845bed1db094a0d)), closes [#6b5f50](https://github.com/ApoSkunz/crabitan_bellevue/issues/6b5f50)

# [1.17.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v1.16.0...v1.17.0) (2026-04-21)


### Bug Fixes

* **auth:** sliding age-gate refresh pour tous les utilisateurs authentifiés ([1915ee2](https://github.com/ApoSkunz/crabitan_bellevue/commit/1915ee2838126b8b31428e31f7ea41caee965eb0))
* **model:** updateLastLoginAt — reset inactivity_warning_sent_at à NULL ([c455e39](https://github.com/ApoSkunz/crabitan_bellevue/commit/c455e39f4e9cabfa3ba30a5ce4e3c16bfcddc2ba))


### Features

* **auth:** sliding refresh cookie age-gate sur JWT_REFRESH_THRESHOLD ([8522443](https://github.com/ApoSkunz/crabitan_bellevue/commit/8522443c9dbe8cadee2f24106eb7319f303fa28b))

# [1.16.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v1.15.0...v1.16.0) (2026-04-21)


### Bug Fixes

* **mail:** expliciter applySmtpConfig sur 11 fonctions sans canal défini ([5407127](https://github.com/ApoSkunz/crabitan_bellevue/commit/5407127e200ef260103c2b029fff8de8be78242b))
* **mail:** wording resetAdminBody — "votre demande auprès de l'équipe d'administration" ([e73c745](https://github.com/ApoSkunz/crabitan_bellevue/commit/e73c745403f9725e341590b02426b75fbe98b49a))
* **seo:** robots.txt — déclarer les 3 sitemaps explicitement ([1952b73](https://github.com/ApoSkunz/crabitan_bellevue/commit/1952b73c0b1c4f3bf6ecd517af34e7c8c2c6ef96))
* **sonar:** 7 code smells — constantes + NOSONAR S1448 ([8dbd994](https://github.com/ApoSkunz/crabitan_bellevue/commit/8dbd99407f46ca843c9de14907a03b4968e2c054))
* **tests:** corriger failure getAllSlugs + débloquer 2 tests skipped RGPD ([c82b548](https://github.com/ApoSkunz/crabitan_bellevue/commit/c82b548d8a9f30d9c42992103a21722a9894cbe9))
* **tests:** corriger failure sitemap + débloquer TI fallback JSON export ([aadb5b6](https://github.com/ApoSkunz/crabitan_bellevue/commit/aadb5b6e3c752e4d8ac2d4fb16d1bebe6f2ba5d3))


### Features

* **admin:** colonne nb commandes + has_password dans getForAdmin ([b871b27](https://github.com/ApoSkunz/crabitan_bellevue/commit/b871b27d277adadbcc5b91cc3d08159717ed2041))
* **admin:** envoi lien réinitialisation mot de passe depuis vue comptes ([c480f91](https://github.com/ApoSkunz/crabitan_bellevue/commit/c480f91327d4dfa376e07706221010ac4c58383b))
* **admin:** vue comptes — colonne Cmdes + bouton Réinit. mdp ([eb2ee62](https://github.com/ApoSkunz/crabitan_bellevue/commit/eb2ee6240fa3c3f5ad723471ccc8dccbd542fa09))
* **mail:** sendAdminPasswordReset — texte différencié reset initié par admin ([bc8c5ae](https://github.com/ApoSkunz/crabitan_bellevue/commit/bc8c5aeb1df11ad62779af4ad651de3c661509c1))
* **seo:** sitemap complet — 39 vins, FR>EN priorités, pages manquantes ([8ad077a](https://github.com/ApoSkunz/crabitan_bellevue/commit/8ad077a889c0bf91e695b434077bfba4c4b15bcc))
* **seo:** sitemap-media — carousel, gallery, news, cv + titres explicites ([69c60e1](https://github.com/ApoSkunz/crabitan_bellevue/commit/69c60e162388b7d2cce7064fc6e2a6a957103477))
* **seo:** sitemap-media.xml + sitemap-index.xml — images vins et vidéo ([31b2b67](https://github.com/ApoSkunz/crabitan_bellevue/commit/31b2b67895ea0f8188307481b91bb9169351a52f))

# [1.15.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v1.14.0...v1.15.0) (2026-04-20)


### Bug Fixes

* **ci:** chmod workspace avant ZAP — résout PermissionError rapport JSON ([162e3f6](https://github.com/ApoSkunz/crabitan_bellevue/commit/162e3f69ef35891e8c1a818a68889589a66df900))
* **security:** ajouter storage/.htaccess — bloquer accès HTTP direct ([03ec90b](https://github.com/ApoSkunz/crabitan_bellevue/commit/03ec90bdf89578d7a2b51030ea8c2681aa837b52))
* **sonar:** NOSONAR php:S2003 require foreach wine-card-picture ([3775ac7](https://github.com/ApoSkunz/crabitan_bellevue/commit/3775ac7baeb922169771ab508e1db1e4e05d3224))
* **ux:** masquer theme-toggle header sur mobile, déplacer dans burger menu ([fe50319](https://github.com/ApoSkunz/crabitan_bellevue/commit/fe5031922891fcaa7d29ae9729dfe3016ce840fc))
* **ux:** theme-toggle mobile — icône seule sans cercle, reste dans header ([210686b](https://github.com/ApoSkunz/crabitan_bellevue/commit/210686b4ec8a7dfc8cfec6b1c2fd653ed49ec813))
* **ux:** theme-toggle mobile — masqué header, intégré élégamment dans burger menu ([b4aee0f](https://github.com/ApoSkunz/crabitan_bellevue/commit/b4aee0ff9a5c748d4772b76e9d147b2a91921ad5))
* **view:** require_once → require pour wine-card-picture.php dans le foreach ([88925c9](https://github.com/ApoSkunz/crabitan_bellevue/commit/88925c943ae39b3167b274948443d456f3ee9781))


### Features

* **ci:** ZAP context files + ajax_spider + debug auth pour meilleure couverture DAST ([5cf31a0](https://github.com/ApoSkunz/crabitan_bellevue/commit/5cf31a0a82ae81745b173e9e340b848f51ec3b32))
* **ci:** ZAP seed URLs explicites — résout découverte 2-3 URLs ([7aa38e6](https://github.com/ApoSkunz/crabitan_bellevue/commit/7aa38e6f6ab815527b427a12b0655b51690513a1))

# [1.14.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v1.13.0...v1.14.0) (2026-04-20)


### Bug Fixes

* **ci:** upgrade zaproxy/action-full-scan v0.11.0 → v0.12.0 ([acf5202](https://github.com/ApoSkunz/crabitan_bellevue/commit/acf5202493e2ba4f7eee8b05b61d6e436f8ba1b6))
* **sonar:** corriger bugs et code smells php:S2003 S1192 S3358 S3776 ([6a8ceea](https://github.com/ApoSkunz/crabitan_bellevue/commit/6a8ceea7ce383198146e7ab1c39c8f3e9ad4d3df))
* **sonar:** résoudre php:S107 CronController — refacto context param + extract resolveCuveeFilter ([2dd83ec](https://github.com/ApoSkunz/crabitan_bellevue/commit/2dd83ec6af325f2dbd7728da400663dd5a15aa96))


### Features

* **secu:** backfill_last_login_at.php — réparation données inactivité RGPD ([86e27fa](https://github.com/ApoSkunz/crabitan_bellevue/commit/86e27faa9d57188e78faea20f7599da48c8a45d3))

# [1.13.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v1.12.0...v1.13.0) (2026-04-20)


### Bug Fixes

* **test:** adapter AccountModelInactivityTest à last_login_at ([654381f](https://github.com/ApoSkunz/crabitan_bellevue/commit/654381fa9b546622ce6d0af928f03acd299b86ca))


### Features

* **auth:** mettre à jour last_login_at à chaque connexion réussie ([1a32ac9](https://github.com/ApoSkunz/crabitan_bellevue/commit/1a32ac95c7e7edd647f2f6ba9060fb666ddf63b1))

# [1.12.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v1.11.0...v1.12.0) (2026-04-20)


### Bug Fixes

* **auth:** redirect polling Google link vers page d'origine de la modal ([9e08cae](https://github.com/ApoSkunz/crabitan_bellevue/commit/9e08cae304425031ec9bf1cd786332ca772a749f))
* **auth:** Tab A émet la session OAuth link, Tab B affiche confirmation ([6a141aa](https://github.com/ApoSkunz/crabitan_bellevue/commit/6a141aac68805f99e86f50bf0ceba4769b7e6c15))
* **dast:** ajouter 3e colonne url_regex dans rules.tsv (format ZAP 3 colonnes obligatoires) ([d41c9b8](https://github.com/ApoSkunz/crabitan_bellevue/commit/d41c9b805b4dda1998b7014d5cc7f222454d5f0f))
* **security:** CORP cross-origin pour images (clients mail) ([b3e4681](https://github.com/ApoSkunz/crabitan_bellevue/commit/b3e4681712f0084b2932b90648f1ae2e8aeb2aae))
* **test:** adapter GoogleOAuthControllerLinkFlowTest au flow Tab A/B rattachement ([2170884](https://github.com/ApoSkunz/crabitan_bellevue/commit/2170884b4068c11d985ac15a00a6e8f69a487bf1))


### Features

* **auth:** polling rattachement Google OAuth (Cas 2) ([aaf0c82](https://github.com/ApoSkunz/crabitan_bellevue/commit/aaf0c82446df96b89afe9fda83d0f21f2322e5dd))


### Performance Improvements

* **gallery:** ACTION-08 — servir variantes 600w optimisées galerie ([e70939d](https://github.com/ApoSkunz/crabitan_bellevue/commit/e70939d6ecd02c222736e10adbcda73f78a406c3))
* **head:** preload polices critiques Cinzel et Caviar Dreams ([b1cc212](https://github.com/ApoSkunz/crabitan_bellevue/commit/b1cc21218890cf273db3f05f9f6df9986b320fdf))
* **view:** ajouter srcset WebP sur favoris, fiche vin, panier et homepage ([376e0f6](https://github.com/ApoSkunz/crabitan_bellevue/commit/376e0f6edcc3ceae85fe9074b88464454a6eb0be))

# [1.11.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v1.10.0...v1.11.0) (2026-04-20)


### Bug Fixes

* **admin:** différer upload image + traduction après validation complète du formulaire vin ([e517690](https://github.com/ApoSkunz/crabitan_bellevue/commit/e517690ea68fdbfe17b999fc39cf6b0531a8503f))
* **js:** progress PDF — animation ease-out 0→90% smooth au lieu de chunk tracking ([e9736e5](https://github.com/ApoSkunz/crabitan_bellevue/commit/e9736e5a3610090d60f0633ede2c4f36a632f982))
* **perf:** fetchpriority sur images vin panier vide (lcp-lazy-loaded) ([1a1a523](https://github.com/ApoSkunz/crabitan_bellevue/commit/1a1a5234afd5d5715d2007c6e0a924c077153b51))
* **sonar:** éliminer duplication index/collection + S107 generateWidth ([7e39382](https://github.com/ApoSkunz/crabitan_bellevue/commit/7e39382c68185cb76bdf950ea82d8a8cb7fb9978))
* **sonar:** résoudre 6 code smells + réduire duplication nouveau code ([3b745a9](https://github.com/ApoSkunz/crabitan_bellevue/commit/3b745a9c11af80a69f894003a5e18e8298e68080))
* **test:** corriger échec CI testUpdateDeletesOldImage + warnings GD ([4da23aa](https://github.com/ApoSkunz/crabitan_bellevue/commit/4da23aa887b33f30b4e2d8bcf9656f2bc2315fd4))
* **view:** vue détail vin — image originale pour zoom net (sans srcset resize) ([0162b27](https://github.com/ApoSkunz/crabitan_bellevue/commit/0162b278718b91661e5669e050b157adafd8fcf4))


### Features

* **js:** module wine-pdf-download — progression fetch+ReadableStream fiche technique PDF ([066926d](https://github.com/ApoSkunz/crabitan_bellevue/commit/066926d1d7ac4fa9204f119963c7e4b5ada37184))
* **perf:** srcset 400w/800w images vins — réduction payload -11MB (uses-responsive-images) ([9c7f3c3](https://github.com/ApoSkunz/crabitan_bellevue/commit/9c7f3c34a7a4d18d4712c708780ca48b673cac43))
* **scss:** overlay progression PDF — barre dorée, fond semi-transparent ([971dbda](https://github.com/ApoSkunz/crabitan_bellevue/commit/971dbda4c503dab36df3fab011204eb07df70e3b))
* **service:** ImageVariantService — génération variantes 400w/800w WebP au upload admin ([941a8d4](https://github.com/ApoSkunz/crabitan_bellevue/commit/941a8d4c5ac8c5f46ea8305ae62682d866b18b85))
* **view:** bouton PDF + overlay progression sur vue détail vin (i18n fr/en) ([670163c](https://github.com/ApoSkunz/crabitan_bellevue/commit/670163cfa9a8a42ef8db1ff351cdb7b58a54f4cb))

# [1.10.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v1.9.2...v1.10.0) (2026-04-20)


### Bug Fixes

* **ci:** corriger 3 bugs CI — ZAP getopt, Lighthouse DB_ENCRYPT_KEY, brace-expansion ([178749a](https://github.com/ApoSkunz/crabitan_bellevue/commit/178749aa34897113bbb41550c7e1f10743963de0))
* **e2e:** age-gate sans remember → cookie 24h (plus session cookie) ([ee097d2](https://github.com/ApoSkunz/crabitan_bellevue/commit/ee097d2c2d201bb2d79770223ebb247481e7a3db))


### Features

* **admin:** tri date/connexion comptes, filtres vintage/appellation/cuvée vins ([9edddd8](https://github.com/ApoSkunz/crabitan_bellevue/commit/9edddd8a887eb642d2483cbfbda63d82c3fdc713))
* **i18n:** traduire politique de confidentialité en anglais (RGPD art. 12) ([ca46d1e](https://github.com/ApoSkunz/crabitan_bellevue/commit/ca46d1ecf9e1a42855cf1250cb1d99a8c8d258a9))
* **session:** JWT sans mémoire 1h→2h, age-gate sans mémoire session→24h ([38a00eb](https://github.com/ApoSkunz/crabitan_bellevue/commit/38a00ebc588a5543c21afa1362c220259b609307))

## [1.9.2](https://github.com/ApoSkunz/crabitan_bellevue/compare/v1.9.1...v1.9.2) (2026-04-20)


### Bug Fixes

* **logger:** éviter le double-log HttpException 500 dans le handler global ([7ee9b1f](https://github.com/ApoSkunz/crabitan_bellevue/commit/7ee9b1ff6b0b52ffa5b5b2d00461f37412968334))

## [1.9.1](https://github.com/ApoSkunz/crabitan_bellevue/compare/v1.9.0...v1.9.1) (2026-04-20)


### Bug Fixes

* **rgpd:** réinitialiser inactivity_warning_sent_at à la reconnexion ([0e1943d](https://github.com/ApoSkunz/crabitan_bellevue/commit/0e1943d695874a75d9d7e65d61e7adb0ec79f119))

# [1.9.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v1.8.0...v1.9.0) (2026-04-20)


### Bug Fixes

* **ci:** corriger ZAP -c ordering et MAIL_USER dans dast-full.yml ([1b84767](https://github.com/ApoSkunz/crabitan_bellevue/commit/1b847677a774797af038085229cb62fe9ab8a0b6))
* **mail:** corriger tests MAIL_USER → MAIL_NOREPLY_USER + CONTACT_OWNER_EMAIL nullsafe ([91f2d28](https://github.com/ApoSkunz/crabitan_bellevue/commit/91f2d2829be46bddc2d7f8d995138a0c8799607a))


### Features

* **mail:** routing SMTP multi-canal via MailChannel enum ([1987d44](https://github.com/ApoSkunz/crabitan_bellevue/commit/1987d44b1f7e905b5fc953b987cd0c1c758b39d0))

# [1.8.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v1.7.0...v1.8.0) (2026-04-19)


### Bug Fixes

* **ci:** corriger auth ZAP DAST — cookie jar Netscape + replacer cmd_options ([aef22e5](https://github.com/ApoSkunz/crabitan_bellevue/commit/aef22e55ee3f577b4ea26b1db213be2442dfb68b))
* **tests:** corriger 8 échecs TI — stock_quantity manquant + SMTP Mailhog ([f13565d](https://github.com/ApoSkunz/crabitan_bellevue/commit/f13565d08b0962bdac8301e82c4cb20a08fded2b))
* **wine:** explicit error message when available=1 with stock=0 ([f56d78c](https://github.com/ApoSkunz/crabitan_bellevue/commit/f56d78c73abcef9af426efc96c4e29b1b46bca4d))
* **wine:** highlight available field error when stock=0 blocks sale toggle ([dd18a4d](https://github.com/ApoSkunz/crabitan_bellevue/commit/dd18a4d8cb6653272c749bf7a13474068c513862))
* **wine:** stock=0 forces available=0 on create+edit, error if available=1 with stock=0 ([15eb001](https://github.com/ApoSkunz/crabitan_bellevue/commit/15eb001ca2a6d6c5794a2f134071ccb70b005fba))


### Features

* **controller:** newsletter queue — admin enqueue, cron admin branch, last_connection ([0dbeab6](https://github.com/ApoSkunz/crabitan_bellevue/commit/0dbeab6e00ff034864b07aa0f12db49eb1653b84))
* **model:** newsletter queue — EN columns, atomic stats, getLinkMap, nullable wine_id ([8c6d514](https://github.com/ApoSkunz/crabitan_bellevue/commit/8c6d5144405be9f730d47776126cdc7824c04a4f))
* **view:** admin accounts last_connection column + newsletter campaign status ([d4f0e1c](https://github.com/ApoSkunz/crabitan_bellevue/commit/d4f0e1ca1462975daec1d7edeb4e3f79b28024f8))

# [1.7.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v1.6.1...v1.7.0) (2026-04-19)


### Bug Fixes

* **cron:** appliquer CRON_MAIL_DELAY_MS dans send-deletion-reminders ([752687a](https://github.com/ApoSkunz/crabitan_bellevue/commit/752687ade8a4ffcb21f9b33dc7087dce95a8e6f6))


### Features

* **cron:** ajouter bin/cron/send-newsletter-queue.php ([57203af](https://github.com/ApoSkunz/crabitan_bellevue/commit/57203afcb4aab626c1ff72c5363cf8a56849d8bf))
* **newsletter:** envoi différé via queue newsletter_send_queue + cron send-newsletter-queue ([0dc57c7](https://github.com/ApoSkunz/crabitan_bellevue/commit/0dc57c7cd14d8908715385d28278b6195b683556))
* **smtp:** ajouter toggles MFA_EMAIL_ENABLED + NEW_WINE_NEWSLETTER_ENABLED + CRON_MAIL_DELAY_MS ([9c1edff](https://github.com/ApoSkunz/crabitan_bellevue/commit/9c1edffce52465a27b0f8e6509fc907a3f525ddd))

## [1.6.1](https://github.com/ApoSkunz/crabitan_bellevue/compare/v1.6.0...v1.6.1) (2026-04-19)


### Bug Fixes

* **ci:** use /fr for CSRF GET + add age_verified=1 to admin ZAP auth_header ([48b598d](https://github.com/ApoSkunz/crabitan_bellevue/commit/48b598d70978fce049fd91734db05495f8e4ae32))
* **ci:** use /fr/vins to extract CSRF token — no GET route exists for /fr/connexion ([dc6f0d9](https://github.com/ApoSkunz/crabitan_bellevue/commit/dc6f0d95bd0997191182e139aeaf9df66ed56194))
* **sonar:** suppress S1448 on CronController and S3973 on admin logs view ([67e3699](https://github.com/ApoSkunz/crabitan_bellevue/commit/67e36990c7c519c0f4c5485ad4669abfe127e08c))

# [1.6.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v1.5.1...v1.6.0) (2026-04-19)


### Bug Fixes

* **i18n:** replace em/en dashes with hyphens in FR webmaster keys ([f61fe18](https://github.com/ApoSkunz/crabitan_bellevue/commit/f61fe186930bacaa262e0073889377d2ea2aec83))


### Features

* **i18n:** replace hyphens with em dashes in EN lang file (site + emails) ([71dcaeb](https://github.com/ApoSkunz/crabitan_bellevue/commit/71dcaeba4bcba56ca094f55bd6258b82ef313f77))
* **ui:** boxed badge on unavailable wine price slot + soften dark overlay (0.28) ([58f1bd8](https://github.com/ApoSkunz/crabitan_bellevue/commit/58f1bd8d550b49b02ce1a4a42bb70bdad12b1afe))

## [1.5.1](https://github.com/ApoSkunz/crabitan_bellevue/compare/v1.5.0...v1.5.1) (2026-04-19)


### Bug Fixes

* **ci:** add age_verified=1 cookie to DAST auth steps + debug output ([2045043](https://github.com/ApoSkunz/crabitan_bellevue/commit/20450438919b3d270ab3fb7030ee3430444827f1))
* **security:** add COOP + CORP headers to .htaccess ([3294304](https://github.com/ApoSkunz/crabitan_bellevue/commit/329430466a7c5ca5b9f94f019b0bd4d14044e578))
* **security:** document ZAP false positives in .zap/rules.tsv ([ad8e72a](https://github.com/ApoSkunz/crabitan_bellevue/commit/ad8e72aea561237311c45d373bcfa0d8f2e2b6c1))
* **security:** fix HSTS never sent on IONOS — remove env=HTTPS condition ([9fb340b](https://github.com/ApoSkunz/crabitan_bellevue/commit/9fb340bb3115831783fcac2fd66f84eacd645503))

# [1.5.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v1.4.0...v1.5.0) (2026-04-19)


### Bug Fixes

* **auth:** add CSP nonce to inline script on google-account-type view ([2445778](https://github.com/ApoSkunz/crabitan_bellevue/commit/244577810dc7dbd47b1b5c943d27d8547e460027))
* **auth:** add margin-bottom on company-name group before submit button ([f57adae](https://github.com/ApoSkunz/crabitan_bellevue/commit/f57adaeeaf69b84718b491e4a97f9643b1d848be))
* **auth:** add margin-bottom on radio group for spacing before submit button ([ec075fd](https://github.com/ApoSkunz/crabitan_bellevue/commit/ec075fde93f231bf2be8741aeda3ce20091c6de4))
* **auth:** add margin-top on company-name field when it appears ([235eea5](https://github.com/ApoSkunz/crabitan_bellevue/commit/235eea5d2ed92448afe6fd48d49c9fe224052c4e))
* **auth:** fix fieldset radio overflow — use fieldset-specific selector after .form-group ([1a09ba1](https://github.com/ApoSkunz/crabitan_bellevue/commit/1a09ba1816c1c1955ec3e4f280a09747814f51d6))
* **auth:** fix radio layout on google-type-compte (fieldset+flex Chrome bug) ([597e2d5](https://github.com/ApoSkunz/crabitan_bellevue/commit/597e2d5615bba1eff22f36be46a16f99f4e88eb4))
* **auth:** replace fieldset with div[role=group] + fix skip-link visibility ([b52d3ae](https://github.com/ApoSkunz/crabitan_bellevue/commit/b52d3ae250a22c9a0b36997688bd7b253c1de483))


### Features

* **auth:** Google OAuth account type selection before account creation ([d26a554](https://github.com/ApoSkunz/crabitan_bellevue/commit/d26a5543480cf5ab1c6c26552f161a29f45c7867))
* **auth:** pre-fill company_name with Google fullname + fix radio/icon layout ([ef5d720](https://github.com/ApoSkunz/crabitan_bellevue/commit/ef5d720cb407f337b63239c8a4c56c6bb545c182))
* **legal:** add explicit B2C-only clause in CGV (FR + EN) ([ad7120c](https://github.com/ApoSkunz/crabitan_bellevue/commit/ad7120c4ab9379b64c6993e2ea35f15f84e6a2c5))
* **model:** extend createFromGoogle() to support company account type ([258493d](https://github.com/ApoSkunz/crabitan_bellevue/commit/258493d860a01504765e44301474cc3563d13d42))

# [1.4.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v1.3.3...v1.4.0) (2026-04-19)


### Bug Fixes

* **ci:** URL prod corrigée crabitanbellevue.fr + full scan déclenché sur push main ([55b0197](https://github.com/ApoSkunz/crabitan_bellevue/commit/55b0197102e3944516216268b9f645ad838e7e2a))


### Features

* **ci:** ajout workflows DAST ZAP baseline (prod mensuel) + full scan (ISO prod Docker) ([49a4f88](https://github.com/ApoSkunz/crabitan_bellevue/commit/49a4f88fa20c386a672b81bfb262192ffc283ba5))
* **ci:** dast-full scan multi-rôles — login curl customer + admin avec device_token seed ([6b41101](https://github.com/ApoSkunz/crabitan_bellevue/commit/6b4110160f5a2c02afe4c0571cf6068206d9d335))

## [1.3.3](https://github.com/ApoSkunz/crabitan_bellevue/compare/v1.3.2...v1.3.3) (2026-04-19)


### Bug Fixes

* **secu:** prepared statements sur les SELECT LIMIT/OFFSET dans rotate_encryption_keys.php ([a93ec4e](https://github.com/ApoSkunz/crabitan_bellevue/commit/a93ec4ebc2f295921df982edbfaeb25972540c31))

## [1.3.2](https://github.com/ApoSkunz/crabitan_bellevue/compare/v1.3.1...v1.3.2) (2026-04-19)


### Bug Fixes

* **ci:** sbom.yml — composer make-bom → CycloneDX:make-bom (breaking change plugin v4+) ([1603fe3](https://github.com/ApoSkunz/crabitan_bellevue/commit/1603fe3b60b538f3b4087a184ba37261e707775e))

## [1.3.1](https://github.com/ApoSkunz/crabitan_bellevue/compare/v1.3.0...v1.3.1) (2026-04-19)


### Bug Fixes

* **sonar:** corriger S3973 (accolades if logs) + S1448 NOSONAR mauvaise ligne (CronController) ([800b506](https://github.com/ApoSkunz/crabitan_bellevue/commit/800b506e96692717a51e465f8103436e34d6d7dd))
* **view:** corriger ParseError dans admin/logs/index.php — accolade manquante après refactoring Sonar ([ddf48c7](https://github.com/ApoSkunz/crabitan_bellevue/commit/ddf48c7cb088c76a2a0b14822fa4770b809de0ef))

# [1.3.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v1.2.0...v1.3.0) (2026-04-19)


### Bug Fixes

* **carbon:** fallback origin sur 503 — per-page en priorité, racine si non indexé ([b4e6209](https://github.com/ApoSkunz/crabitan_bellevue/commit/b4e6209bffac03e1496e1cf828c1137c05f1ae87))
* **model:** normaliser apostrophes → underscore dans image_path (rosés) ([9d4364d](https://github.com/ApoSkunz/crabitan_bellevue/commit/9d4364d29156ef5d1852c01fc6691c6261095469))
* **model:** normaliser image_path — espaces → underscores à la lecture (WineModel, FavoriteModel, CartModel) ([d55de57](https://github.com/ApoSkunz/crabitan_bellevue/commit/d55de57a4ddc5044080924cdea78d9c484d13bfa))
* **seo:** data-nosnippet sur modales login/register — évite snippet mot de passe dans SERP ([2185aaa](https://github.com/ApoSkunz/crabitan_bellevue/commit/2185aaaf1b354c6df529faf9cd46a43d1511b5ed))
* **ux:** thème light par défaut, inverser icônes toggle, title au survol ([cbd5366](https://github.com/ApoSkunz/crabitan_bellevue/commit/cbd5366ca932cd86ef3f851ee53feca007fee7ed))


### Features

* **admin:** générer WebP à l'upload et supprimer original+WebP à la mise à jour ([f0c7f37](https://github.com/ApoSkunz/crabitan_bellevue/commit/f0c7f37c4e79e1106bac3962eefaac15e715b24e))
* **lighthouse:** améliorer A11y/Perf/SEO — theme dark default, target-size, i18n carousel, cart aria ([3042012](https://github.com/ApoSkunz/crabitan_bellevue/commit/3042012f5685a6d11e4d8c2431df7ed513231e09))


### Reverts

* **model:** supprimer normalisation image_path — correction côté BDD ([15b78b6](https://github.com/ApoSkunz/crabitan_bellevue/commit/15b78b6bc2ba3faa005c9c099822ad59385dceff))

# [1.2.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v1.1.1...v1.2.0) (2026-04-18)


### Bug Fixes

* **assets:** renommer Atlantique 2024 avec espaces/apostrophe pour matcher la BDD ([0df2660](https://github.com/ApoSkunz/crabitan_bellevue/commit/0df2660ea8d2d349fc086873e6246d8b09f776d9))
* **js:** désactiver carbon-badge sur localhost/crabitan.local ([066dc8d](https://github.com/ApoSkunz/crabitan_bellevue/commit/066dc8db8ab19bc43e845886f9cc2bc5590421fb))
* **perf:** AddType image/webp .webp — MIME manquant sur XAMPP ([8102555](https://github.com/ApoSkunz/crabitan_bellevue/commit/810255557d066e5e06244dc8a659cbfe3334bf7d))
* **scss:** display:contents sur picture dans wine-card et wine-detail image-wrap ([425b8ec](https://github.com/ApoSkunz/crabitan_bellevue/commit/425b8ec737c2358536e16610383f494ea51747a4))
* **view:** dédupliquer literals '/\.[^.]+$/' et '.webp' dans cart/index.php (S1192) ([99a5bf1](https://github.com/ApoSkunz/crabitan_bellevue/commit/99a5bf1cb9f85ff464ddf3c068d255931db205d3))
* **view:** encoder les noms de fichiers vin dans srcset (espaces → %20) ([d646ba0](https://github.com/ApoSkunz/crabitan_bellevue/commit/d646ba0857d1f4a1a586bae311817ea0683b4ee6))
* **view:** rawurlencode sur tous les chemins images vin (src + srcset + data-wine-image) ([5152f48](https://github.com/ApoSkunz/crabitan_bellevue/commit/5152f4820030b53f1b37bc7d76737e2977cddcb1))


### Features

* **admin:** hook ImageVariantService sur upload/remplacement image vin ([e77cae2](https://github.com/ApoSkunz/crabitan_bellevue/commit/e77cae20b6bb3f082a4724fdcc2c5534f077452e))
* **assets:** ajouter image vin Bordeaux Rouge 2022 depuis la prod ([ca50d1a](https://github.com/ApoSkunz/crabitan_bellevue/commit/ca50d1aca38b1d5dfe67bd0c5f1ed7cf8115414f))
* **assets:** générer les 39 WebP vins depuis images prod (quality 85) ([60bfefa](https://github.com/ApoSkunz/crabitan_bellevue/commit/60bfefacc5a0f3d67726fdee59d30363fc086d84))
* **assets:** variantes carousel 800w/1600w JPG+WebP pour picture srcset ([5e41c68](https://github.com/ApoSkunz/crabitan_bellevue/commit/5e41c68b359c298cbd177428da4bce3de975e9d2))
* **assets:** variantes gallery 600w/1200w JPG+WebP pour picture srcset ([efa5485](https://github.com/ApoSkunz/crabitan_bellevue/commit/efa54852d6deca5d320bfec93e891a26e58e4caa))
* **service:** ImageVariantService — génère/supprime variantes WebP vin ([3fd1730](https://github.com/ApoSkunz/crabitan_bellevue/commit/3fd17304944b77a3a09f69ecb91649b95f8c2a9f))


### Performance Improvements

* **view:** ACTION-09 — width/height sur toutes les images (CLS) ([0d3d60e](https://github.com/ApoSkunz/crabitan_bellevue/commit/0d3d60e802b99998d9a1ba3f0273bc899534695e))
* **view:** ACTION-12/17/22 — preload carousel, meta-description, skip-link ([4895469](https://github.com/ApoSkunz/crabitan_bellevue/commit/48954696b5daf93a701ea01c56e60654f0574529))
* **view:** ACTION-webp — <picture> WebP+PNG fallback sur toutes les images vin ([e7d321a](https://github.com/ApoSkunz/crabitan_bellevue/commit/e7d321aa969b33734a2c54fe7d39bd9c2befd233))
* **view:** carousel slides — picture+WebP srcset (remplace background-image) ([630b090](https://github.com/ApoSkunz/crabitan_bellevue/commit/630b090d8212725776e19948ddfa50b9c7a6ae77))
* **view:** CORRECTIF-07 — picture+srcset WebP sur vues vins ([dcaf2ac](https://github.com/ApoSkunz/crabitan_bellevue/commit/dcaf2ac1352d3669f37045c1b1b118ee1aa1db33))
* **view:** CORRECTIF-07 — picture+WebP sur images gallery/carousel ([ee9ad2b](https://github.com/ApoSkunz/crabitan_bellevue/commit/ee9ad2bc5166e45daad9ec8e8ddfa5a53ef71f2b))

## [1.1.1](https://github.com/ApoSkunz/crabitan_bellevue/compare/v1.1.0...v1.1.1) (2026-04-18)


### Bug Fixes

* **a11y:** ajuster --color-gold light mode [#7](https://github.com/ApoSkunz/crabitan_bellevue/issues/7)a611e → [#8](https://github.com/ApoSkunz/crabitan_bellevue/issues/8)b6914 ([63d2d79](https://github.com/ApoSkunz/crabitan_bellevue/commit/63d2d7983d97f52f7ab26a3ed72ddc9a3e549021)), closes [#7a611e](https://github.com/ApoSkunz/crabitan_bellevue/issues/7a611e) [#8b6914](https://github.com/ApoSkunz/crabitan_bellevue/issues/8b6914) [#f5f0e8](https://github.com/ApoSkunz/crabitan_bellevue/issues/f5f0e8) [#fff](https://github.com/ApoSkunz/crabitan_bellevue/issues/fff)
* **a11y:** CORRECTIF-01 — contraste couleur or en mode jour (WCAG AA) ([30150ac](https://github.com/ApoSkunz/crabitan_bellevue/commit/30150ac73116511cce1a3fc23dee9ece88845bd9)), closes [#c9a84c](https://github.com/ApoSkunz/crabitan_bellevue/issues/c9a84c) [#7a611e](https://github.com/ApoSkunz/crabitan_bellevue/issues/7a611e) [#f5f0e8](https://github.com/ApoSkunz/crabitan_bellevue/issues/f5f0e8) [#fff](https://github.com/ApoSkunz/crabitan_bellevue/issues/fff) [#fff](https://github.com/ApoSkunz/crabitan_bellevue/issues/fff)
* **a11y:** CORRECTIF-06 — aria-hidden badge+icône panier (label-content-name-mismatch) ([06c119b](https://github.com/ApoSkunz/crabitan_bellevue/commit/06c119b68ea4fdfad906f33935190da439efc994))


### Performance Improvements

* **cache:** CORRECTIF-03 — gzip + Cache-Control assets statiques ([555942b](https://github.com/ApoSkunz/crabitan_bellevue/commit/555942bbd24f602484b0b206a0df717f9e73f588))
* **lcp:** CORRECTIF-02 — defer main.js sur toutes les vues ([7eb24ec](https://github.com/ApoSkunz/crabitan_bellevue/commit/7eb24ec7621fd85e4be13a9a0db05080f960dbfc))
* **lcp:** CORRECTIF-04 — retirer lazy sur images LCP, fetchpriority=high ([ea93e7f](https://github.com/ApoSkunz/crabitan_bellevue/commit/ea93e7fab5ca6e61bdc319e43af52bf8dc5cabc3))


### Reverts

* **a11y:** annuler CORRECTIF-01 contraste gold — rétablir #c9a84c ([5ab5e43](https://github.com/ApoSkunz/crabitan_bellevue/commit/5ab5e4356afe95da30cf575956b0a63af45441db)), closes [#c9a84c](https://github.com/ApoSkunz/crabitan_bellevue/issues/c9a84c) [#c9a84c](https://github.com/ApoSkunz/crabitan_bellevue/issues/c9a84c)

# [1.1.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v1.0.1...v1.1.0) (2026-04-18)


### Features

* **ci:** ajouter workflow SBOM CycloneDX (PHP + Node.js) ([595b4bf](https://github.com/ApoSkunz/crabitan_bellevue/commit/595b4bf12e024009911c9a73a05496ac83a3573f))

## [1.0.1](https://github.com/ApoSkunz/crabitan_bellevue/compare/v1.0.0...v1.0.1) (2026-04-18)


### Bug Fixes

* **htaccess:** add admin IP bypass to maintenance mode ([c0c2dd0](https://github.com/ApoSkunz/crabitan_bellevue/commit/c0c2dd06496bf99c3224625450f1b4bae97f4798))

# [1.0.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.89.0...v1.0.0) (2026-04-18)


* feat!: enable major version bump on breaking changes + fix npm vulnerabilities ([301db54](https://github.com/ApoSkunz/crabitan_bellevue/commit/301db54a554d712eaa3e7157b6c0009e83b3cc59))


### Bug Fixes

* **ci:** patch brace-expansion + picomatch nested in @semantic-release/npm bundle ([90f4641](https://github.com/ApoSkunz/crabitan_bellevue/commit/90f464137f78f87238157665686751163cdac1ce))
* **deps:** update phpunit/phpunit 13.0.5 → 13.1.7 (CVE — versions ≤ 13.1.5 vulnérables) ([34bee14](https://github.com/ApoSkunz/crabitan_bellevue/commit/34bee142885ebf017312c3b1bb9f55de7f819d5f))


### BREAKING CHANGES

* .releaserc.json releaseRule "breaking:true" corrected from
"minor" to "major" — breaking changes now trigger a major semver bump (v1.0.0+)
instead of a minor bump. Previous releases since v0.85.1 with BREAKING CHANGE
footer were incorrectly tagged as 0.x.

- npm audit fix: basic-ftp, lodash, lodash-es, vite updated to safe versions
- package.json overrides: brace-expansion ^2.0.3, picomatch ^4.0.4
- semantic-release updated to 25.0.3
- 2 remaining vulns in semantic-release/npm bundle (CI-only, 0 prod vulns)

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>

# [0.89.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.88.1...v0.89.0) (2026-04-18)


* feat!: release v1.0.0 — chiffrement AES-256-GCM adresses + CronController IONOS ([9c3387c](https://github.com/ApoSkunz/crabitan_bellevue/commit/9c3387c5a69bad103112c67cf47ab883781e4c89))


### Bug Fixes

* **a11y:** skip-link + ARIA i18n labels — WCAG 2.1 A / RGAA 12.7 ([2485302](https://github.com/ApoSkunz/crabitan_bellevue/commit/2485302750b4e72cdef3d49ac109039b2f0e778b)), closes [#main-content](https://github.com/ApoSkunz/crabitan_bellevue/issues/main-content)
* **ci:** supply chain — SHA épinglés e2e/mutation, persist-credentials release ([adbe64c](https://github.com/ApoSkunz/crabitan_bellevue/commit/adbe64cca9453f714b9cbf97742f47427cb7fd5d))
* **lgh:** CGV — rétractation, livraison, carton, N° commande ([d5c4600](https://github.com/ApoSkunz/crabitan_bellevue/commit/d5c4600f2f3f31756740cc848465f23a4df7950d))
* **quality:** resolve 2 SonarCloud code smells ([9912938](https://github.com/ApoSkunz/crabitan_bellevue/commit/99129381175e6059b0f83789b0483a1200cf6c61)), closes [#fff](https://github.com/ApoSkunz/crabitan_bellevue/issues/fff) [#080808](https://github.com/ApoSkunz/crabitan_bellevue/issues/080808) [#c9a84c](https://github.com/ApoSkunz/crabitan_bellevue/issues/c9a84c)
* **rgpd:** politique confidentialité — retrait Apple, tableau cookies CNIL 2020-091 ([f47f380](https://github.com/ApoSkunz/crabitan_bellevue/commit/f47f38053864ae9c528c8a0c33b75cf26121ece2))
* **security:** durcissement auth — révocation JWT, session fixation, rate limit, URI ([1824299](https://github.com/ApoSkunz/crabitan_bellevue/commit/1824299a9ea3b042386bde193eaef8b2267ac3ce))
* **security:** protéger storage/ par .htaccess Require all denied ([355d791](https://github.com/ApoSkunz/crabitan_bellevue/commit/355d791feec84b8d0223ac150420473962602a46))
* **stock:** bloquer alertes stock bas sur vins non commercialisés ([b1b1604](https://github.com/ApoSkunz/crabitan_bellevue/commit/b1b16043913a36751642c2b07b5dac7848819f8a))
* **ux:** checkout récapitulatif collapsible mobile + emails bilingues ([fa92b64](https://github.com/ApoSkunz/crabitan_bellevue/commit/fa92b64c609f0ada88a19dff0c88a32a0c4948cd))


### Features

* **cron:** purge tokens notation commande + route HTTP IONOS ([b0f1cd0](https://github.com/ApoSkunz/crabitan_bellevue/commit/b0f1cd04111af282165d9bde2a80b57477acb790))


### BREAKING CHANGES

* Les colonnes addresses.street, addresses.phone,
order_address_snapshots.street et order_address_snapshots.phone passent
de VARCHAR à TEXT et sont chiffrées AES-256-GCM (format ENC:<base64>).
Migration obligatoire avant mise en service :
  1. mysql … < database/migrations/db_crabitan_mig_002.sql
  2. php private-docs/PTI/1.0.0/encrypt_existing_addresses.php …
Toute lecture des adresses sans déchiffrement retournera des données illisibles.
* CronController HTTP GET requis — les 14 jobs cron sont
désormais exposés via /cron/{job}?token=CRON_SECRET_TOKEN (IONOS mutualisé).
CRON_SECRET_TOKEN obligatoire dans .env.

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>

## [0.88.1](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.88.0...v0.88.1) (2026-04-17)

# [0.88.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.87.1...v0.88.0) (2026-04-17)


### Bug Fixes

* **cron:** corriger ROOT_PATH dans cron-bootstrap.php après reorg bin/cron/ ([1bd7c9e](https://github.com/ApoSkunz/crabitan_bellevue/commit/1bd7c9e915290582303035e210163f47c9e62e44))
* **cron:** exclure /cron/ de l'age-gate dans index.php ([debddd3](https://github.com/ApoSkunz/crabitan_bellevue/commit/debddd32a9065f22e5a37088edfc5f02eec4f374))
* **cron:** retourner 404 au lieu de 403 sur token invalide ([f1d3ac9](https://github.com/ApoSkunz/crabitan_bellevue/commit/f1d3ac975f999331117bc8fed3862980948a7a91))
* **rgpd:** supprimer purge abonnés newsletter confirmés > 13 mois ([f7ba59d](https://github.com/ApoSkunz/crabitan_bellevue/commit/f7ba59d8311f4d4d23291cb4bc5dfa1af0478121))


### Features

* **cron:** CronController HTTP GET pour IONOS mutualisé ([d7d84ae](https://github.com/ApoSkunz/crabitan_bellevue/commit/d7d84aeedd67426bb3ace2295fff8781d5ecabd5))
* **cron:** support dry_run HTTP via CronRunner forcedDryRun ([0348db9](https://github.com/ApoSkunz/crabitan_bellevue/commit/0348db9c98c14c9966781c21aa09b239cc87f1fd))

## [0.87.1](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.87.0...v0.87.1) (2026-04-17)


### Bug Fixes

* **model:** déchiffrer street/phone des snapshots dans toutes les méthodes JOIN ([14f83ec](https://github.com/ApoSkunz/crabitan_bellevue/commit/14f83ec78a03bbbc319606b54ad961a3d95ad463))

# [0.87.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.86.0...v0.87.0) (2026-04-17)


### Bug Fixes

* **AccountModel:** décrypter majority_declared_ip dans decryptRow() ([b5eb1d2](https://github.com/ApoSkunz/crabitan_bellevue/commit/b5eb1d2122bd8cd618c090aed6f9a89bfaec3b67))
* **ci:** ajouter DB_ENCRYPT_KEY dans le .env E2E des workflows ci.yml et e2e.yml ([e861d23](https://github.com/ApoSkunz/crabitan_bellevue/commit/e861d239aacd55346bfffde81a1050ed365d2fad))
* **model:** décrypter street stocké dans le dedup createFromAddress ([480f932](https://github.com/ApoSkunz/crabitan_bellevue/commit/480f932f8f42ecd99f23cb941d3362219877c870))
* **test:** ajouter DB_ENCRYPT_KEY fallback dans bootstrap + isolation RgpdExportServiceTest ([d9b7c0c](https://github.com/ApoSkunz/crabitan_bellevue/commit/d9b7c0cf6a9e980603d1cb1e2b333f76317a584a))
* **test:** recharger DB_ENCRYPT_KEY dans setUp si unset par EncryptionServiceTest ([265affb](https://github.com/ApoSkunz/crabitan_bellevue/commit/265affbc5cd8015840a51e0809c5e8a9095d9c9b))
* **test:** sauvegarder/restaurer DB_ENCRYPT_KEY dans EncryptionServiceTest tearDown ([3ea706b](https://github.com/ApoSkunz/crabitan_bellevue/commit/3ea706b9888394c9fcb444253d350e5026d5f39e))


### Features

* **addresses:** chiffrement AES-256-GCM street+phone — AddressModel, OrderModel, OrderAddressSnapshotModel ([25b8c45](https://github.com/ApoSkunz/crabitan_bellevue/commit/25b8c451610b5c8502b0c60f778fcefbfc7c49dc))
* **EncryptionService:** préfixe ENC:, backward compat legacy, guard fromEnv, isEncrypted() ([4385b0a](https://github.com/ApoSkunz/crabitan_bellevue/commit/4385b0a395928fb710e839030c7db4f6c851d9f6))
* **security:** étendre rotate_encryption_keys.php pour addresses + order_address_snapshots ([c9e2148](https://github.com/ApoSkunz/crabitan_bellevue/commit/c9e2148a02739d009b327179ef579db42df3ee3f))

# [0.86.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.85.1...v0.86.0) (2026-04-17)


### Bug Fixes

* **cron:** rotation logs 30→90j + dry-run purge_newsletter_tracking ([ec8d68e](https://github.com/ApoSkunz/crabitan_bellevue/commit/ec8d68ebffac64e5064e4393ca6e2cc9305c5af0))
* **ErrorLogger:** anonymisation IP RGPD + filtre bruit 404/405 ([62ff007](https://github.com/ApoSkunz/crabitan_bellevue/commit/62ff007ec68e320e12821d8804c28aedd07d6b8d))
* **logs:** aligner ErrorLoggerTest sur RETENTION_DAYS=90 (30→90 jours) ([3386010](https://github.com/ApoSkunz/crabitan_bellevue/commit/338601034d3df533341c73bfc3c4b06facae7482))


### Features

* **cron:** ajouter support --dry-run sur tous les scripts bin/ ([c614f1c](https://github.com/ApoSkunz/crabitan_bellevue/commit/c614f1c64158ececbefd54220e950e4f4ece5fd7))
* **CronRunner:** support flag --dry-run passé en 2e arg aux callables ([bf0fc32](https://github.com/ApoSkunz/crabitan_bellevue/commit/bf0fc32b03003cc543f13a9c4bf9ad1ffacbaf44))

## [0.85.1](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.85.0...v0.85.1) (2026-04-17)


### Bug Fixes

* **i18n:** em dash → tiret simple dans lang/fr et lang/en (hors webmaster) ([4c845c3](https://github.com/ApoSkunz/crabitan_bellevue/commit/4c845c3c841d03aa30129f8f8a896e3e502744de))
* **i18n:** remplace en-dash par tiret simple (hors webmaster) ([18497a9](https://github.com/ApoSkunz/crabitan_bellevue/commit/18497a9ed827c8d38880ae7341480234b9b6b3ad))
* **i18n:** remplacer guillemets typographiques par apostrophes PHP dans wine.case_banner ([ac27011](https://github.com/ApoSkunz/crabitan_bellevue/commit/ac270117f3bfb6b9ef19fc5d23437b9e5e1afb76))
* **i18n:** résoudre conflits merge main — intégrer wine.case_banner.* keys ([d46b0b5](https://github.com/ApoSkunz/crabitan_bellevue/commit/d46b0b5eb00726e2fc8aad4a19907819d6350f46))
* **mail:** restaurer accents dans buildNewsletterHtml + sync signature stub test ([abd78bf](https://github.com/ApoSkunz/crabitan_bellevue/commit/abd78bf2c9763bb4081d30d304e9c4d36162a6b6))
* **mail:** wording alerte stock - ce vin est dans votre panier ([d809d03](https://github.com/ApoSkunz/crabitan_bellevue/commit/d809d0356467d6959c8de1e5e7ef4f41d2497432))

# [0.85.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.84.2...v0.85.0) (2026-04-17)


### Bug Fixes

* **contact:** restaurer titre section "Où sommes-nous" ([d6e5a90](https://github.com/ApoSkunz/crabitan_bellevue/commit/d6e5a90310e684904fe14ba4f207407c66ea3a07))
* **contact:** retrait titre doublon "Où nous trouver" ([670e65c](https://github.com/ApoSkunz/crabitan_bellevue/commit/670e65c2d7e6b0dbc51dd2e13e80cc88a0be539f))
* **game-score:** cap memo à 119 (secondes restantes < DURATION) ([fd64c6e](https://github.com/ApoSkunz/crabitan_bellevue/commit/fd64c6ef2ee7d6f829947bd56b58bb3d1ef38ecb))
* **invoice:** INVOICE_AUTO_GENERATE traite chaîne vide comme désactivé ([491b936](https://github.com/ApoSkunz/crabitan_bellevue/commit/491b936fe179ed1b8f673694933e1d6209847018))
* **view:** require → require_once pour wines-case-banner (Sonar S2003) ([7f5bc95](https://github.com/ApoSkunz/crabitan_bellevue/commit/7f5bc95521e1732346196261c26432954f7da9fb))


### Features

* **carbon:** audit page-par-page au lieu de la base URL ([5d1b4d7](https://github.com/ApoSkunz/crabitan_bellevue/commit/5d1b4d7314c46ae3629bcd7746c502cb18730a7d))
* **games:** UX mobile — swipe labour/vendange, 3 colonnes, score vitesse ([e2b9a90](https://github.com/ApoSkunz/crabitan_bellevue/commit/e2b9a90564b5d8d3f2dc66a84ca268ef14d14c50))
* **wines:** bandeau caisse de 12 panachée sur /vins et /collection ([e0fbbf2](https://github.com/ApoSkunz/crabitan_bellevue/commit/e0fbbf22424233dfe9e45ce0269b8a9d7a85b419))


### Reverts

* restaurer UploadFuzzingTest supprimé par erreur ([1699616](https://github.com/ApoSkunz/crabitan_bellevue/commit/16996164d9ee4da99f413199b5c21c692a7894a0))

## [0.84.2](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.84.1...v0.84.2) (2026-04-14)


### Bug Fixes

* **js:** Sonar S6551 — typeof ladder explicite pour reason ([b605d65](https://github.com/ApoSkunz/crabitan_bellevue/commit/b605d65269658ebacac2674e9844a01caf67e023))

## [0.84.1](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.84.0...v0.84.1) (2026-04-14)


### Bug Fixes

* **js:** Sonar S6551 — évite stringification Object dans buildRejectionPayload ([60fdb56](https://github.com/ApoSkunz/crabitan_bellevue/commit/60fdb56d27e44f7a040509f53e67f224e1d85bb3))

# [0.84.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.83.0...v0.84.0) (2026-04-14)


### Bug Fixes

* **filters:** retire per_page de l'URL (pagination supprimée) ([eb81890](https://github.com/ApoSkunz/crabitan_bellevue/commit/eb818906244587f92d343f3df19f769c16a6d24f))
* **filters:** strip empty params et valeurs par défaut de l'URL AJAX ([391e294](https://github.com/ApoSkunz/crabitan_bellevue/commit/391e29421a78d0729e51e4ff77b71c3bbf6bbb82))
* **pdf:** reprend l'adresse de livraison quand identique à facturation ([2df2235](https://github.com/ApoSkunz/crabitan_bellevue/commit/2df22357a11a80a5fae2c20744b0cc9368e8fcda))
* **security:** whitelist routes nées d'un email face à l'age-gate ([512f135](https://github.com/ApoSkunz/crabitan_bellevue/commit/512f135851138addd12bf4ad11d3192b3e5dee9b))


### Features

* **account:** favoris affichent prix + bouton panier ou millésime épuisé ([18e3b3c](https://github.com/ApoSkunz/crabitan_bellevue/commit/18e3b3c1024950e549c0934d1c3579bd0681a246))
* **mail:** PDF récap commande joint au mail gérant ([b3b4c14](https://github.com/ApoSkunz/crabitan_bellevue/commit/b3b4c14881aa4cb31dace19b4d2e678a30124138))
* **news:** retire la pagination serveur des actualités + simplifie lazy-load ([cbe4bff](https://github.com/ApoSkunz/crabitan_bellevue/commit/cbe4bffefccd58651fbe3930d36092b88fd15cab))
* **seo:** bypass HeadlessChrome + Vary User-Agent + URL racine carbon-badge ([ed3f09d](https://github.com/ApoSkunz/crabitan_bellevue/commit/ed3f09dcef847e6e7a7d65e4787dedb72214d886))
* **wines:** retire la pagination serveur et le select per-page du catalogue ([3182df9](https://github.com/ApoSkunz/crabitan_bellevue/commit/3182df9b3597b65c9229bbf27f7b910536b8aee1))

# [0.83.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.82.0...v0.83.0) (2026-04-14)


### Bug Fixes

* **admin:** révocation session admin redirige vers /fr après clear cookie ([3e41ca6](https://github.com/ApoSkunz/crabitan_bellevue/commit/3e41ca6af8c1478d382031836d3d96243c5eaf1a))
* **checkout:** fallback paiement CA indisponible — message + VIR/CHQ ([f91c8a7](https://github.com/ApoSkunz/crabitan_bellevue/commit/f91c8a7cead8ebddde7b9c656fe20dc4505a1e1a))
* **invoice:** étend INVOICE_AUTO_GENERATE à tous les points de génération ([e4720ed](https://github.com/ApoSkunz/crabitan_bellevue/commit/e4720eda7743fabe02551c5d58d6a1481620dc04))
* **lazy-load:** retire infinite scroll — per_page=10 affichait tout ([95d2809](https://github.com/ApoSkunz/crabitan_bellevue/commit/95d2809436b14155a17634341d97863a60114e0c))
* **lazy-load:** stop updating URL with ?page=N during infinite scroll ([7cffddb](https://github.com/ApoSkunz/crabitan_bellevue/commit/7cffddb3edbd5378dd5fe5e8efbca627b5a9f372))
* **security:** bypass age-gate via Referer websitecarbon.com ([594f436](https://github.com/ApoSkunz/crabitan_bellevue/commit/594f43602843153d42ae746754c9e1191d334e36))
* **security:** valide same-origin avant navigation différée toast langue ([40c79ec](https://github.com/ApoSkunz/crabitan_bellevue/commit/40c79ec16ce3fe7a319be97de7348bd1d4c4a646))
* **ux:** toast switchlang centré écran + navigation différée 700ms ([953fc44](https://github.com/ApoSkunz/crabitan_bellevue/commit/953fc44bb6f0e24575ae73fcff4f45bb0fbc458d))


### Features

* **account:** bloque changement email pour comptes Google OAuth ([658d9ca](https://github.com/ApoSkunz/crabitan_bellevue/commit/658d9ca56f36d22d634d4ac8f8eadb57e8454408))
* **auth:** double confirmation email avant rattachement OAuth Google ([5c984b0](https://github.com/ApoSkunz/crabitan_bellevue/commit/5c984b0bb4b9c74d81bdf44ca17431fab4229ecd))
* **auth:** MFA Google Option A + rattachement via MFA standard ([b209d55](https://github.com/ApoSkunz/crabitan_bellevue/commit/b209d55536ea137ff4105e54bc536ce106fc7517))
* **errors:** lien retour via referer same-origin sur pages 404/500 ([6a75207](https://github.com/ApoSkunz/crabitan_bellevue/commit/6a75207ba37221ecb4d24bdf9f11b1e7574be248))
* **filters:** auto-submit sort/per_page sur vues publiques et admin ([e0ab46b](https://github.com/ApoSkunz/crabitan_bellevue/commit/e0ab46b1a4cded7497bd1273477391e2c1fa1b50))
* **forms:** inputmode numeric sur SIRET et code postal ([c0e5032](https://github.com/ApoSkunz/crabitan_bellevue/commit/c0e503202f557fa9ee9c277075f420a0b930ca07))
* **i18n:** CSRF explicite, fallback paiement CA, confirmation OAuth link ([63ab400](https://github.com/ApoSkunz/crabitan_bellevue/commit/63ab4005a18fecf97d62351db8b755174650514c))
* **mail:** sendGoogleLinkConfirmation pour rattachement OAuth ([c8ea8e9](https://github.com/ApoSkunz/crabitan_bellevue/commit/c8ea8e964f1fcc6b19c86dc407a6f7b7fa729def))
* **maintenance:** page unifiée dans public/ + vin liquoreux doré ([934dc84](https://github.com/ApoSkunz/crabitan_bellevue/commit/934dc84457bb5f347ed1b79411d6267d943d7c84))
* **model:** GoogleLinkTokenModel — tokens double confirmation OAuth ([5e79196](https://github.com/ApoSkunz/crabitan_bellevue/commit/5e79196268d67bcaf44f23bd2bf8e5fb162049a4))
* **oauth:** restaure rattachement email dédié via GoogleLinkTokenModel ([953008b](https://github.com/ApoSkunz/crabitan_bellevue/commit/953008b3d2df2409973128c90e6797a36a920d9d))
* **routes:** GET /{lang}/auth/google/link/verify pour token email ([08901a4](https://github.com/ApoSkunz/crabitan_bellevue/commit/08901a4d1dd2ec03362133be837b2d0121ef6e20))
* **ux:** badge "Millésime épuisé" remplace le prix sur collection ([c13bdfe](https://github.com/ApoSkunz/crabitan_bellevue/commit/c13bdfecd50cb0b484f74483455a0253e95f9280))
* **ux:** toast switch langue, focus trap modales, auto-submit filtres ([5781c1b](https://github.com/ApoSkunz/crabitan_bellevue/commit/5781c1b3485d6bd1b26b3418e4f24433162ce825))
* **wines:** filtres AJAX instantanés + infinite scroll restauré ([857e077](https://github.com/ApoSkunz/crabitan_bellevue/commit/857e0776072739d122856076be96da2b5ba2e259))

# [0.82.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.81.0...v0.82.0) (2026-04-13)


### Bug Fixes

* **admin:** corrige 500 /admin/logs — LIMIT/OFFSET PDO ([c00e9dd](https://github.com/ApoSkunz/crabitan_bellevue/commit/c00e9ddd21a8dfb5031d740ac3f34b494240a1f1))
* **auth:** cookie auth_token SameSite=Strict → Lax pour OAuth callback ([3ec8b96](https://github.com/ApoSkunz/crabitan_bellevue/commit/3ec8b9604cbb54f93607f13b2a99c00b9a50cb8f))
* **cart:** reload page panier après ajout cross-sell ([4bfa07d](https://github.com/ApoSkunz/crabitan_bellevue/commit/4bfa07dae3eeb6000804c07e5d5c5c73cd78fbc4))
* **htaccess:** RewriteBase / + pattern ^(.*)$ pour compat IONOS ([a6271a3](https://github.com/ApoSkunz/crabitan_bellevue/commit/a6271a38ba91c2a1390198c089851a00b9232295))
* **payment:** tolère le nouveau comportement de load.html côté CA e-Transactions ([356624c](https://github.com/ApoSkunz/crabitan_bellevue/commit/356624c4fd20baf72e1ef6f9e3756a2332038c37))
* **register:** renvoie le mail de vérification si compte non activé ([7d8991d](https://github.com/ApoSkunz/crabitan_bellevue/commit/7d8991d7740956311beb1f6bd096cf61630c938d))
* **seo:** retire noindex sur mentions-légales, confidentialité, plan-du-site ([ae7025c](https://github.com/ApoSkunz/crabitan_bellevue/commit/ae7025c2fdf1dee837a1e72dfbd66ea06ac377d5))
* **seo:** UA-trust crawlers SEO majeurs — déblocage indexation ([4273205](https://github.com/ApoSkunz/crabitan_bellevue/commit/4273205449ba568a8dc14c65f7cdb3d5e2b93889))


### Features

* **accessibility:** publie scores Lighthouse sur la page RGAA ([0f49cec](https://github.com/ApoSkunz/crabitan_bellevue/commit/0f49cecf2eeb6fca004e0962d7b68dd9aae3c7fe))
* **i18n:** ajoute wine.not_for_sale (FR/EN) ([ee38031](https://github.com/ApoSkunz/crabitan_bellevue/commit/ee380311da922b329de11095492cffa0d83897c9))
* **ipn:** désactive la génération auto de facture via INVOICE_AUTO_GENERATE ([1b4be19](https://github.com/ApoSkunz/crabitan_bellevue/commit/1b4be19c980c52d904029f727f8bca87fb45a7fb))
* **mail:** utilise lang BDD compte au lieu de lang URL ([384a757](https://github.com/ApoSkunz/crabitan_bellevue/commit/384a7579b6b4a3ecaa398c07f825fa4f6aa9de35))
* **newsletter:** welcome email CTA 1-clic via token ([1584066](https://github.com/ApoSkunz/crabitan_bellevue/commit/1584066cb462a782ac5bb30422ba7069b560ffd7))
* **oauth:** trusted device auto-enroll + welcome token 1-clic ([f6f03d2](https://github.com/ApoSkunz/crabitan_bellevue/commit/f6f03d2fff659feb2ace42fc9a0a0d96f7c76e8b))
* **ops:** script opcache-reset.php protégé par token pour invalider bytecode IONOS ([3389331](https://github.com/ApoSkunz/crabitan_bellevue/commit/33893312d9df3037d999b0f5419f6c08571fc6b2))
* **orders:** bandeau prix indicatifs quand catalogue != historique ([4417256](https://github.com/ApoSkunz/crabitan_bellevue/commit/44172562b6ba89b9bad11076d72e1b5516320d9e))
* **wines:** ajoute image officielle Vin de Pays de l'Atlantique 2024 ([782a6f2](https://github.com/ApoSkunz/crabitan_bellevue/commit/782a6f2b409cfe48410aff55fa60c609841cfe1f))
* **wines:** aligne cross-sell page détail sur logique panier ([2b32a30](https://github.com/ApoSkunz/crabitan_bellevue/commit/2b32a30caa8b5506a3142b980909ecb6b3c0c873))
* **wines:** distingue non-disponible à la vente vs épuisé dans la collection ([a5618ca](https://github.com/ApoSkunz/crabitan_bellevue/commit/a5618ca6460f1681c6c6c6cab274a08e1d0b64ba))

# [0.81.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.80.1...v0.81.0) (2026-04-13)


### Bug Fixes

* **env:** supprime les fallbacks email hardcodés, câble DPO_EMAIL ([e54fca7](https://github.com/ApoSkunz/crabitan_bellevue/commit/e54fca72daf1c1dc3bc5129b71b6aacc6e522d13))
* **jeux:** cap le record mondial du jeu mémo à DURATION (120 s) ([7f3bfea](https://github.com/ApoSkunz/crabitan_bellevue/commit/7f3bfeab8770f389b7eca27dab3bcbd1c8f8c581))
* **test:** câble DPO_EMAIL dans setUp DpoAdminControllerTest ([cc15079](https://github.com/ApoSkunz/crabitan_bellevue/commit/cc150791cb0834a6c9724e40e8d6b9bae4bf9c3e))
* **test:** corrige TI — env vars manquantes + traductions non chargées + assertions clés brutes ([0c0ab27](https://github.com/ApoSkunz/crabitan_bellevue/commit/0c0ab275753a1afbb2e71f3337b3b317a477fd76))


### Features

* **i18n:** externalise les centres d'intérêt webmaster en clés __() ([a6d11ff](https://github.com/ApoSkunz/crabitan_bellevue/commit/a6d11ff79d8ef453cf3263eeb524b727795ed75b))
* **logo:** remplace le SVG moderne par le PNG officiel partout ([a8fcbe3](https://github.com/ApoSkunz/crabitan_bellevue/commit/a8fcbe31d981477330f761bc1111e0d39cb82a42))
* **security:** security.txt dynamique via SECURITY_EMAIL + APP_URL (RFC 9116) ([cbe1118](https://github.com/ApoSkunz/crabitan_bellevue/commit/cbe11182b5d3132dd6122d5e7526cf3897071b32))

## [0.80.1](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.80.0...v0.80.1) (2026-04-12)


### Bug Fixes

* **config:** remettre HTTPS redirect commenté — décommenter à la MEP IONOS ([b5485ec](https://github.com/ApoSkunz/crabitan_bellevue/commit/b5485ecd99cad228b6cfba5abd7ac3803040b8f8))
* **security:** corriger les 5 vulnérabilités P1 + 4 P2 (audit v3) ([41c31e0](https://github.com/ApoSkunz/crabitan_bellevue/commit/41c31e06862549ae3e5b234ad71043ce8cd56c49))
* **security:** corriger VULN-002 à VULN-014 audit cyber v4 — HIGH/MEDIUM/LOW ([0089a5c](https://github.com/ApoSkunz/crabitan_bellevue/commit/0089a5cb36575472a00d4a462055db92f41efb55))
* **security:** VULN-001 v4 TrackingController — open redirect via URL BDD ([3c62eb7](https://github.com/ApoSkunz/crabitan_bellevue/commit/3c62eb7f57eb8e85af7d8d5d583510624a40f019))
* **security:** VULN-016 + VULN-017 audit cyber v5 ([933c159](https://github.com/ApoSkunz/crabitan_bellevue/commit/933c159f33cc6baf20d7e6d9dbdefbb544980523))

# [0.80.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.79.0...v0.80.0) (2026-04-12)


### Bug Fixes

* **ci+auth:** Stryker main uniquement + cast password string AuthController ([66b4f15](https://github.com/ApoSkunz/crabitan_bellevue/commit/66b4f151fb00a949beeb148d219be6041dc9cbae))
* **lhci:** restaurer bloc crawler age_verified=1 supprimé par ab30224 ([64a0121](https://github.com/ApoSkunz/crabitan_bellevue/commit/64a0121898d84c0c61e137f33bdf7820270822df))
* **model:** RgpdRequestModel — response_note (pas notes) dans les colonnes explicites ([2fea0f2](https://github.com/ApoSkunz/crabitan_bellevue/commit/2fea0f210e5f477450bc61e77b27dfa7a45250ff))
* **model:** RgpdRequestModel — SELECT r.* → colonnes explicites (findById + getAll) ([334c5be](https://github.com/ApoSkunz/crabitan_bellevue/commit/334c5be0bc5e85f63a584e16b2a90d3764c8b351))
* **service:** RateLimiterService — logger les fallbacks silencieux BDD ([354df54](https://github.com/ApoSkunz/crabitan_bellevue/commit/354df543a54e5a79db24ea6ded066d8d910733b0))
* **sonar:** code smells — S1192 constantes, S1448/S3776/S112/S107/S3415/S1142/S1172 NOSONAR + params inutilisés retirés ([8de48a7](https://github.com/ApoSkunz/crabitan_bellevue/commit/8de48a70d25da66fc3185345ad36fe69c42693c7))
* **sonar:** coverage 77→90% + duplications + code smells ([30102ca](https://github.com/ApoSkunz/crabitan_bellevue/commit/30102cac9fe3adfd59b35f2ae9bede3d5dab8d89))
* **sonar:** exclure Account/* + RgpdExportService du CPD ([c27b20f](https://github.com/ApoSkunz/crabitan_bellevue/commit/c27b20fc9fadccc69d9668f7a1f1d53f8c43760d))
* **test+service:** réduire Infection skipped — auto-block injectable + 2 TU + 4 tests nettoyés ([2f06591](https://github.com/ApoSkunz/crabitan_bellevue/commit/2f06591b526ab597a5aa3c4e1eb832bbba468503))
* **test:** AccountControllerTest — assertions CSS class + texte traduit (panel.favorites, order_refund_btn) ([d9ee1d2](https://github.com/ApoSkunz/crabitan_bellevue/commit/d9ee1d226fd976475d475af2c18aae89e2b0109e))
* **test:** AccountControllerTest — migrer buildPdfTableSection/buildExportPdf vers RgpdExportService ([a216cdf](https://github.com/ApoSkunz/crabitan_bellevue/commit/a216cdff8718ab42bf579a95168d97af4c6fe6e1))
* **test:** marquer comme skip les TU AuthController migrés vers LoginService ([79514ad](https://github.com/ApoSkunz/crabitan_bellevue/commit/79514adc4dadcfa06a3ef18abbd8275fad6cbf50))
* **test:** restaurer UploadFuzzingTest.php supprimé par erreur ([8e3528e](https://github.com/ApoSkunz/crabitan_bellevue/commit/8e3528e8210fa5c84c13d00026fe98d23c87ca80))


### Features

* **core:** DI Container PSR-11 léger + câblage initial ([ab30224](https://github.com/ApoSkunz/crabitan_bellevue/commit/ab30224d466d300ef81de5fede8521a56444c06f))
* **mail:** domain mailers — AuthMailer, AccountMailer, OrderMailer, NewsletterMailer, RgpdMailer, ContactMailer ([d9e3bf0](https://github.com/ApoSkunz/crabitan_bellevue/commit/d9e3bf0722fdcd12bf97f04b8db583e58095ff79))
* **repository:** OrderRepository interface + MysqlOrderRepository + 18 TU ([b910b3e](https://github.com/ApoSkunz/crabitan_bellevue/commit/b910b3e048c1c9122cd33e325712b6b61229a386))
* **repository:** UserRepository interface + MysqlUserRepository ([aa9242b](https://github.com/ApoSkunz/crabitan_bellevue/commit/aa9242bf693d0795ed33388a407d10e82988cda0))
* **router:** DI Container PSR-11 — résolution controllers via container ([402b4d9](https://github.com/ApoSkunz/crabitan_bellevue/commit/402b4d94cace98cd27fd4a637afd9c379cd637a4))
* **service:** LoginService — MailService injectable (null → new fallback) ([9efecec](https://github.com/ApoSkunz/crabitan_bellevue/commit/9efecec1969b0ef5e6595636cdbdc988ebee0e09))
* **valueobject:** adopter Email VO dans register() et requestEmailChange() ([a50c702](https://github.com/ApoSkunz/crabitan_bellevue/commit/a50c702f34fa892c1c30a5f03432de0a4293e43c))
* **valueobject:** Email + Address Value Objects avec validation ([cf43a93](https://github.com/ApoSkunz/crabitan_bellevue/commit/cf43a930258e96af36e64ae2892315a11d4ea407))

# [0.79.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.78.1...v0.79.0) (2026-04-12)


### Bug Fixes

* **age-gate:** autoriser HeadlessChrome/Lighthouse comme crawler SEO ([fc948dc](https://github.com/ApoSkunz/crabitan_bellevue/commit/fc948dc67ead9ba49b6e4f799f4f4e5392ac3968))
* **ci:** aligner mutation-testing.yml sur origin/main — ajouter pull_request paths ([dc50e72](https://github.com/ApoSkunz/crabitan_bellevue/commit/dc50e729ecf316bb029959e63a8199f67011225b))
* **ci:** lighthouse — SEO crawl + 422 GitHub status + artifact + résumé agrégé ([f7ef223](https://github.com/ApoSkunz/crabitan_bellevue/commit/f7ef2235073275679926a8436b85ec98c5236f64))
* **ci:** Lighthouse — URLs temporaires + rapport local + Job Summary ([78780ea](https://github.com/ApoSkunz/crabitan_bellevue/commit/78780ea48bd3d112c9c31ab8764a0c5d336277f4))
* **ci:** lighthouse assertMatrix — SEO public pages only, a11y all pages ([beaedfb](https://github.com/ApoSkunz/crabitan_bellevue/commit/beaedfb5628f00d19af1deff82ff23636e0917e1))
* **ci:** supprimer navigation puppeteer pour éviter timeout PHP single-thread ([3d47c6c](https://github.com/ApoSkunz/crabitan_bellevue/commit/3d47c6c7809fb325e87be7754f72d733b0966038))
* **lhci:** $bare=false pour crawlers + assertMatrix noindex pages + liens rapports ([8612c50](https://github.com/ApoSkunz/crabitan_bellevue/commit/8612c50c65235555de65fbe36b93054b03ad87e8))
* **lhci:** abaisser seuil performance CI à 0.65 ([e35f9e3](https://github.com/ApoSkunz/crabitan_bellevue/commit/e35f9e3d79a2629b50ad5009973c43890ad92718))
* **lhci:** corriger injection cookies Puppeteer + token GitHub statuses ([861c878](https://github.com/ApoSkunz/crabitan_bellevue/commit/861c878ddca494967b15a8a597f85aa4e5705a2d))
* **lhci:** désactiver audit is-crawlable — noindex intentionnel sur pages auth/compte ([5c3f4ee](https://github.com/ApoSkunz/crabitan_bellevue/commit/5c3f4ee1a6190039002129790e21f55eed091482))
* **lhci:** exclure pages noindex du pattern SEO dans assertMatrix ([e721a84](https://github.com/ApoSkunz/crabitan_bellevue/commit/e721a841ca0ab980b216d7d3a2f508820272803b))
* **lhci:** restaurer assertions SEO sur toutes les pages indexables ([6a851da](https://github.com/ApoSkunz/crabitan_bellevue/commit/6a851da92e8e17826f9317eb06783729c6eacf5c))
* **lhci:** séparer assertions fr/en + passer upload en filesystem ([380aaf0](https://github.com/ApoSkunz/crabitan_bellevue/commit/380aaf065f31c9f0c1d14d69742e4dad2101424d))
* **lhci:** supprimer trailing comma JSON dans lighthouserc.json ([60e01c8](https://github.com/ApoSkunz/crabitan_bellevue/commit/60e01c8c932264752b6350b29ad8486310f0e226))
* **lhci:** supprimer URLs data-dépendantes inexistantes en CI ([988d80e](https://github.com/ApoSkunz/crabitan_bellevue/commit/988d80e4a03b6773a2369ebc0ca64941889dd3a8))
* **lighthouse:** bypass age-gate via formulaire PHP + seed compte CI ([75d6965](https://github.com/ApoSkunz/crabitan_bellevue/commit/75d6965bfab6a8b807d1c7732ecb475d778ce23f)), closes [#age-gate-enter](https://github.com/ApoSkunz/crabitan_bellevue/issues/age-gate-enter)
* **lighthouse:** naviguer sur /age-gate avant setCookie pour établir le contexte ([df56295](https://github.com/ApoSkunz/crabitan_bellevue/commit/df56295066a16a531eefaf9b56b504721f6d6c88))
* **lighthouse:** networkidle2 → load pour éviter timeout CI ([923c6e5](https://github.com/ApoSkunz/crabitan_bellevue/commit/923c6e5b64b373b754854a49f53fbe43211493ef))
* **seo:** ajouter meta description sur newsletter/confirmation ([2558de4](https://github.com/ApoSkunz/crabitan_bellevue/commit/2558de4e1a3f0a5a2ea47a4bae4bab19da20beda))


### Features

* **bin:** purge_rate_limits.php — purge cron des entrées expirées ([4a47287](https://github.com/ApoSkunz/crabitan_bellevue/commit/4a47287d0f6fbc5e78d67bbbacfa2c442c4fccd0))
* **ci:** lighthouse toutes routes publiques + seuils performance/seo ([a9d9a6b](https://github.com/ApoSkunz/crabitan_bellevue/commit/a9d9a6bad2b2e51f9fd48722849d5bcc25ce2f15))
* **lighthouse:** ajuster routes auditées — retirer fiche-technique PDF, ajouter routes manquantes ([02537f2](https://github.com/ApoSkunz/crabitan_bellevue/commit/02537f25d630ee7f70eda1eb8e6398a1f5a79545))
* **seo:** ajouter meta description manquante sur toutes les pages publiques ([d613dc4](https://github.com/ApoSkunz/crabitan_bellevue/commit/d613dc417124b7924c8f716233a3bb35f29383b8))
* **service:** backend BDD dans RateLimiterService (APCu → BDD → session) ([747d586](https://github.com/ApoSkunz/crabitan_bellevue/commit/747d586e066322b7e07b2fcaccba1586a8b5cde5))

## [0.78.1](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.78.0...v0.78.1) (2026-04-12)


### Bug Fixes

* **sonar:** JS — globalThis, S3516, S6551, S6594 ([94e23c3](https://github.com/ApoSkunz/crabitan_bellevue/commit/94e23c3f8ae74dc711d87ac3289ad9d950393e3f))
* **sonar:** PHP — S1192, S3358, S1448 ([14566bb](https://github.com/ApoSkunz/crabitan_bellevue/commit/14566bb572d3b10a4ccb51365b8c45654a83b17f))
* **sonar:** SCSS — S4666 sélecteur dupliqué + S7924 NOSONAR + gitignore LH local ([9f5dfe4](https://github.com/ApoSkunz/crabitan_bellevue/commit/9f5dfe48a53a6f531fa826f418b18d8653cae550))

# [0.78.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.77.0...v0.78.0) (2026-04-12)


### Bug Fixes

* **lhci:** $bare=false pour crawlers + assertMatrix noindex pages + liens rapports ([2bf64bd](https://github.com/ApoSkunz/crabitan_bellevue/commit/2bf64bdaddcedd24a5219e7dace24d8652b87dbc))
* **model+test:** ca_numappel/numtrans dans findDetailForUser + rating NULL en test ([8f549eb](https://github.com/ApoSkunz/crabitan_bellevue/commit/8f549eb3b02e3b8c9003743b03c068ef404ff6bb))
* **model:** P1 — findByReferenceForInvoice() sans ca_numappel/ca_numtrans ([de71c97](https://github.com/ApoSkunz/crabitan_bellevue/commit/de71c971654e14401a934f92c6ea45bafa2e964f))
* **model:** P2 — remplacer markDeletionReminderSent() par deux méthodes dédiées ([b287731](https://github.com/ApoSkunz/crabitan_bellevue/commit/b28773149c871e21daf019cf69d8ca625c01ad59))
* **model:** remplacer SELECT * et ORDER BY RAND() — audit BDD ([30ad256](https://github.com/ApoSkunz/crabitan_bellevue/commit/30ad256a3e222c59597e6e8acd0793512b52fb7c))
* **model:** remplacer SELECT * par colonnes explicites — audit BDD ([5b6840c](https://github.com/ApoSkunz/crabitan_bellevue/commit/5b6840c3201dcb173f8af97691dcf67c83d003f7))
* **model:** remplacer sous-requête corrélée N+1 likes_count par LEFT JOIN ([e157a10](https://github.com/ApoSkunz/crabitan_bellevue/commit/e157a10fe5ced35d893cdaf81069536562afc9c6))
* **model:** supprimer SELECT * — colonnes explicites + rating NULL ([d76df12](https://github.com/ApoSkunz/crabitan_bellevue/commit/d76df12d679e874a475c91adb2a8a67c132c12dd))
* **model:** whitelist explicite colonne dynamique AccountModel — audit BDD phase 3 ([a544dc0](https://github.com/ApoSkunz/crabitan_bellevue/commit/a544dc05d2c4ecc2e079d7a80bfeae96a2732a63))
* **quality:** corriger 3 code smells SonarCloud PR [#133](https://github.com/ApoSkunz/crabitan_bellevue/issues/133) ([ed858cf](https://github.com/ApoSkunz/crabitan_bellevue/commit/ed858cf5c7ac7f5cd8d18ecbcdfdb839b279bad6))
* **quality:** extraire bilingualParams() + wineSelectFromFavorites() — dédupliquer WineModel ([ce4fbd9](https://github.com/ApoSkunz/crabitan_bellevue/commit/ce4fbd9ad1dabc6fa1b03a8d06f766a9ef88b77b))
* **quality:** extraire pickLocalizedValue() — complexité cognitive 17→9 ([c1e750d](https://github.com/ApoSkunz/crabitan_bellevue/commit/c1e750dbeaa70d5313d3225c0dfee2e5e422f86b))
* **test:** supprimer uniqid() du slug dans insertTestWine ([78d4742](https://github.com/ApoSkunz/crabitan_bellevue/commit/78d4742c69d72d921e4f0f007e208cf60b2941e6))


### Features

* **cron:** purge_newsletter_subscriptions — rétention RGPD 13 mois ([1ca05df](https://github.com/ApoSkunz/crabitan_bellevue/commit/1ca05df3f3e4c2229b058ff6dc315ed6c99fa627))
* **model/views:** décomposer colonnes JSON wines en _fr/_en — audit BDD phase 3 ([d7fc9c8](https://github.com/ApoSkunz/crabitan_bellevue/commit/d7fc9c818481c89f8e209cd78a593435c96dc04e))


### Performance Improvements

* **newsletter:** paginer l'envoi campagne par batch de 1000 — audit BDD phase 3 ([08a5377](https://github.com/ApoSkunz/crabitan_bellevue/commit/08a5377e5c595c933c20f0f2b865b47c432df1c7))


### Reverts

* **lighthouse:** retirer les changements lighthouse de la branche ([cd3df4a](https://github.com/ApoSkunz/crabitan_bellevue/commit/cd3df4a71a676bfd551a09dc0552f0170c9c194e))

# [0.77.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.76.0...v0.77.0) (2026-04-12)


### Bug Fixes

* **controller:** ?? 'fr' sur params['lang'] login+register AuthController — supprime PHP notice Infection ([7bda154](https://github.com/ApoSkunz/crabitan_bellevue/commit/7bda15408835d0d3e30e6c351adfa0cc281e9eac))
* **deps:** supprimer @stryker-mutator/javascript-mutator inexistant + sync infection lock ([82a861c](https://github.com/ApoSkunz/crabitan_bellevue/commit/82a861c0b5bad76dd1438b33b13293c1d67ba323))
* **deps:** sync package-lock.json après ajout @stryker-mutator/core ([fcbf8f4](https://github.com/ApoSkunz/crabitan_bellevue/commit/fcbf8f424d3e7c028752b5cf89933dc3f49a5d39))
* **infection:** --testsuite Unit → --testsuite=Unit (syntaxe PHPUnit 13) ([796cefd](https://github.com/ApoSkunz/crabitan_bellevue/commit/796cefdbd87c52ccddecdb9ef1ac0a0a57eb4ef7))
* **infection:** exclure src/Model du périmètre — SQL non testable en TU, timeouts + MSI faussé ([1bc9bf8](https://github.com/ApoSkunz/crabitan_bellevue/commit/1bc9bf867bfa518d700734a21cc658259a0ee13c))
* **infection:** supprimer source.excludes vide — schéma requiert min 1 item ou clé absente ([1d50738](https://github.com/ApoSkunz/crabitan_bellevue/commit/1d50738d98262b11a65d7c904efbdd2e17c60253))
* **service:** fallback ?? '' sur MAIL_USER manquant dans MailService — supprime PHP warning en TU Infection ([0e9e505](https://github.com/ApoSkunz/crabitan_bellevue/commit/0e9e505be881e2b0a54cd4a0f894f6cc405645b2))
* **stryker:** corriger casse chemin tests Unit/js + supprimer options dépréciées mutator/files ([7155fbf](https://github.com/ApoSkunz/crabitan_bellevue/commit/7155fbf35119ad5e622402b4a0e0cd9d14627c9f))
* **test:** [@run](https://github.com/run)InSeparateProcess sur testLangReturnsDefault — constante CURRENT_LANG non réinitialisable entre tests ([18672d2](https://github.com/ApoSkunz/crabitan_bellevue/commit/18672d2bb1442d51a94677442c8765de9f172311))
* **test:** convertir fins de ligne CRLF → LF UploadFuzzingTest (PHPCS Generic.Files.LineEndings) ([1b22420](https://github.com/ApoSkunz/crabitan_bellevue/commit/1b224203a01a72f70dffe87d0015272580893ac4))
* **test:** corriger PHPCS AnonClass + syntax error interpolation dollar UploadFuzzingTest ([2261d18](https://github.com/ApoSkunz/crabitan_bellevue/commit/2261d186b02b540fec8967f390ba96ba3e6816bc))
* **test:** corriger tests fuzzing — #[DataProvider] PHPUnit13 + PasswordValidator::isStrong ([97f4d27](https://github.com/ApoSkunz/crabitan_bellevue/commit/97f4d27fb14c3250843efab4905e54604ccc2a25))
* **test:** mocker Database::instance avant new class extends IpnController (IpnControllerHandleFlowTest) ([ddee9b0](https://github.com/ApoSkunz/crabitan_bellevue/commit/ddee9b082941d35d0bd999f97d7428f357c043ea))
* **test:** mocker Database::instance dans setUp StockNotificationServiceTest — PDO connection refused ordre aléatoire Infection ([a2d7bad](https://github.com/ApoSkunz/crabitan_bellevue/commit/a2d7bad2f00f9fa2f45077c05bcccc565882d348))
* **test:** purger le fichier log avant testValidPayloadReturns204AndWritesLog — isolation ordre aléatoire Infection ([4669b58](https://github.com/ApoSkunz/crabitan_bellevue/commit/4669b58219530dea8a454e685569ffb1089ea148))


### Features

* **qa:** configurer Infection PHP mutation testing ([d87b34c](https://github.com/ApoSkunz/crabitan_bellevue/commit/d87b34c1f89aeb57ed7782a6f5cc59711c8574da))
* **qa:** configurer Stryker mutation testing JS ([69504ff](https://github.com/ApoSkunz/crabitan_bellevue/commit/69504ffff7a31a5e3f8186f701ed48e0e6983c2f))
* **security:** fuzzing entrées PHP (us-fuzzing-php-inputs) ([665759e](https://github.com/ApoSkunz/crabitan_bellevue/commit/665759e766d5f1f017e9d7cada322e9ca16abd2e))
* **security:** fuzzing routes API HTTP (us-fuzzing-api-routes) ([e6f16f2](https://github.com/ApoSkunz/crabitan_bellevue/commit/e6f16f2ed36b897a37a89bc4904deebb9e689b5f))
* **security:** fuzzing upload fichiers (us-fuzzing-upload) ([40a0cb5](https://github.com/ApoSkunz/crabitan_bellevue/commit/40a0cb53c6fe7a54c343535d524f592db8e7e852))

# [0.76.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.75.0...v0.76.0) (2026-04-12)


### Features

* **scss:** logo height fixe → clamp() + commentaire overflow nav (WCAG 1.4.4 zoom) ([a7c7b1c](https://github.com/ApoSkunz/crabitan_bellevue/commit/a7c7b1c47f44a35a157157e23d9a231317788a2c))
* **scss:** overflow:hidden → overflow-x:hidden + commentaires WCAG sur overflow intentionnels ([57b71ad](https://github.com/ApoSkunz/crabitan_bellevue/commit/57b71ad77bed091a79e22785a8094e4aec43b75e))
* **scss:** px fixes → clamp()/rem sur conteneurs structurels (WCAG 1.4.4) ([1de31c9](https://github.com/ApoSkunz/crabitan_bellevue/commit/1de31c9c112dbccdff93ae72af217c2c57c208fa))

# [0.75.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.74.0...v0.75.0) (2026-04-12)


### Bug Fixes

* **admin:** rel="noopener noreferrer" sur lien Voir le site (target blank mobile) ([9a588a2](https://github.com/ApoSkunz/crabitan_bellevue/commit/9a588a2c79e27022f1c0c0a2c47a50074d49656d))
* **burger:** dropdown sticky — pas de fixed, hauteur limitée à 100dvh-100px ([277fe24](https://github.com/ApoSkunz/crabitan_bellevue/commit/277fe2481bd768fc566eba20433cdc11faae51fa))
* **burger:** overlay fixe — menu visible depuis n'importe quelle position de scroll ([3ae5668](https://github.com/ApoSkunz/crabitan_bellevue/commit/3ae56682dd2a224b695bf8d13e56275bee0f56ad))
* **header:** burger menu 768-991px — typo Cinzel + scroll lock + masquer btn compte ([89ad01f](https://github.com/ApoSkunz/crabitan_bellevue/commit/89ad01f15564ecec2e942e3e9e8621de086ab712))
* **header:** masquer le titre entre 768-991px (mobile paysage) ([27b69b2](https://github.com/ApoSkunz/crabitan_bellevue/commit/27b69b228ef213e07b4890aae1cd3181d6df1514))
* **js:** ne pas appeler /api/cart/count pour les admins ([51c0991](https://github.com/ApoSkunz/crabitan_bellevue/commit/51c099122c60b40466f0712857716b44a400bc57))
* **view:** lien admin burger mobile → /admin (sans préfixe langue) ([c2c8f86](https://github.com/ApoSkunz/crabitan_bellevue/commit/c2c8f864e2817fc8583bfd0991f7a4de7598db2b))
* **view:** menu burger mobile → admin redirige vers /admin et non /mon-compte ([8ea45d6](https://github.com/ApoSkunz/crabitan_bellevue/commit/8ea45d6c7f9bbd9465629f165545ab231e4e5c28))


### Features

* **i18n:** clés dialog confirmation suppression panier (fr + en) ([d3b0f95](https://github.com/ApoSkunz/crabitan_bellevue/commit/d3b0f95a089d4223be54c6a8a0be9e57c5375570))
* **js:** dialog confirmation avant suppression article panier ([3d17f9e](https://github.com/ApoSkunz/crabitan_bellevue/commit/3d17f9e9c31e2c917fa66fc5e9e19622d10af339))
* **scss:** corrections responsive mobile 320-767px ([11a1d08](https://github.com/ApoSkunz/crabitan_bellevue/commit/11a1d08eed1af6f35abf2567530fc58e3e4dea6c))
* **scss:** corrections responsive tablette 768-1023px + back-office ([1d2f35f](https://github.com/ApoSkunz/crabitan_bellevue/commit/1d2f35fdbcbb46329212a5e599ec50c76011587f))
* **view/admin:** data-label tables cards + burger drawer + cart skip admin ([6b2d2d9](https://github.com/ApoSkunz/crabitan_bellevue/commit/6b2d2d9f8e619572058673b9272917bafc965c05))
* **view:** data-label tables commandes/factures + dialog suppression panier ([7105baa](https://github.com/ApoSkunz/crabitan_bellevue/commit/7105baa45e1cc24fd180abfcb3f0b7d773a4ee74))

# [0.74.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.73.1...v0.74.0) (2026-04-11)


### Bug Fixes

* **error-handling:** corriger niveaux de log — warning() pour catches, info() pour audit ([6da2017](https://github.com/ApoSkunz/crabitan_bellevue/commit/6da2017c8a1893751b8c5690289ea4b699644dbf))
* **security:** neutraliser log injection dans ErrorLogger ligne human-readable ([e837a20](https://github.com/ApoSkunz/crabitan_bellevue/commit/e837a200920f54a5b8480872610b23ad1bcd7118))
* **sonar:** extraire constantes LOG_CONTEXT dupliquées (S1192) ([b3c0e0d](https://github.com/ApoSkunz/crabitan_bellevue/commit/b3c0e0dfd3a099b4f01079d05081e8c55a547588))


### Features

* **core:** brancher ErrorLogger sur les catch index.php + GAE KO OrderAdminController ([1ac95f0](https://github.com/ApoSkunz/crabitan_bellevue/commit/1ac95f03b2956919080715439692a17e579450e6))
* **core:** ErrorLogger — handler PHP E_ALL + exceptions → storage/logs/php-error-YYYY-MM-DD.log + TU ([80c8925](https://github.com/ApoSkunz/crabitan_bellevue/commit/80c89254aed309d1fb6b95a1fcdcaa0289cb5107))
* **error-handling:** ajouter ErrorLogger::warning() pour les catches non bloquants ([057f8e2](https://github.com/ApoSkunz/crabitan_bellevue/commit/057f8e27a4647175e6c1a87571e14e88a63fea17))
* **error-handling:** ajouter ligne human-readable avant chaque entrée JSON dans ErrorLogger ([a42d842](https://github.com/ApoSkunz/crabitan_bellevue/commit/a42d842c80790aafef7ae4a6d7eaeecaf0007a43))
* **error-handling:** brancher tous les catch existants sur ErrorLogger::handleException ([eaf529e](https://github.com/ApoSkunz/crabitan_bellevue/commit/eaf529e27e683e263e01b0df54eaf6131ac23648))
* **error-handling:** remplacer tous les error_log() par ErrorLogger::warning/write ([d9085bd](https://github.com/ApoSkunz/crabitan_bellevue/commit/d9085bde7c4f15528cc125581a5b7337c8739c3f))
* **logger:** fusionner tous les canaux dans app-YYYY-MM-DD.log ([54678e7](https://github.com/ApoSkunz/crabitan_bellevue/commit/54678e7154ef70a37ced9e36fbfa556fcd7aa8de))

## [0.73.1](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.73.0...v0.73.1) (2026-04-11)


### Bug Fixes

* **sitemap:** ajouter /accessibilite dans sitemap.xml (priorité 0.3) ([09f06af](https://github.com/ApoSkunz/crabitan_bellevue/commit/09f06af8babc67abe035ba8d3c19a1bb46c6c8bc))

# [0.73.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.72.0...v0.73.0) (2026-04-11)


### Bug Fixes

* **lint:** error-reporter.js — catch sans variable (ES2019, no-unused-vars) ([02c38be](https://github.com/ApoSkunz/crabitan_bellevue/commit/02c38be37cb95ff1ae5252b4aab3d1219cb6f939))


### Features

* **api:** us-logs-erreurs-js — error-reporter.js + LogApiController POST /api/log/client-error + rate limit 10/min + rotation 30j ([021a9e8](https://github.com/ApoSkunz/crabitan_bellevue/commit/021a9e898fd77a4638d8c43177be8e6b4666c8a6))
* **js:** error-reporter — logs envoyés en dev ET prod (console.error conservé en debug) ([75867f1](https://github.com/ApoSkunz/crabitan_bellevue/commit/75867f16e429f80e0ed9893eadd4401af28e30a1))

# [0.72.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.71.0...v0.72.0) (2026-04-11)


### Bug Fixes

* **test:** aligner TI GAE KO — statut attendu return_requested (plus paid) ([e3b7c34](https://github.com/ApoSkunz/crabitan_bellevue/commit/e3b7c347efb6777ee869853edc61c6b5a0c51206))


### Features

* **controller:** GAE KO — flash + email admin sendRefundGaeKoToOwner + statut return_requested + audit log ([2a7d419](https://github.com/ApoSkunz/crabitan_bellevue/commit/2a7d419fc55432bad2bda8b554fe17ab5ee160fb))
* **mail:** sendRefundGaeKoToOwner — lien admin + message contrôle manuel + TU ([7c98bb7](https://github.com/ApoSkunz/crabitan_bellevue/commit/7c98bb7533d8956e5ddfd35362d88ea1c5adafc8))

# [0.71.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.70.0...v0.71.0) (2026-04-11)


### Bug Fixes

* **middleware:** aligner SecurityHeaderMiddleware sur main — apply() + isWinePath() sans doublon ([c71ede6](https://github.com/ApoSkunz/crabitan_bellevue/commit/c71ede678c695bf3f2c6948b6c3163c278e5c274))
* **security:** robots.txt — autoriser IA légitimes, bloquer scrapers agressifs uniquement ([b873bfa](https://github.com/ApoSkunz/crabitan_bellevue/commit/b873bfa6dd105051655450669d0382e07ccea61e))


### Features

* **marketing:** taux de conversion — encarts commandes + paniers abandonnés + formule corrigée ([9366ec3](https://github.com/ApoSkunz/crabitan_bellevue/commit/9366ec3af88b0788ca495c89b77fc639fb6edf12))
* **marketing:** taux de conversion commandes/paniers — controller + vue + TU ([9101086](https://github.com/ApoSkunz/crabitan_bellevue/commit/91010864a54bc938f871abcc2d7ae40cb79737fd))
* **model:** OrderModel::getConversionRate — ratio commandes payées / paniers actifs 30 j ([ee8d8ed](https://github.com/ApoSkunz/crabitan_bellevue/commit/ee8d8eda368e18b65e40484676a02fe00042d00c))
* **security:** anti-scraping — rate limiting catalogue, honeypot contact, robots.txt IA, monitoring ([e970ef0](https://github.com/ApoSkunz/crabitan_bellevue/commit/e970ef04f54c4324674d0530461aae73b9035e27))

# [0.70.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.69.0...v0.70.0) (2026-04-11)


### Bug Fixes

* **controller:** fiche-retour PDF — supprimer colonne Format, intégrer Cuvée Spéciale dans colonne Vin ([e1e9f07](https://github.com/ApoSkunz/crabitan_bellevue/commit/e1e9f074234a4ebca2700fde06918d2351414fc4))


### Features

* **controller:** fiche-retour PDF — ajouter colonnes Millésime et Cuvée Spéciale ([8709239](https://github.com/ApoSkunz/crabitan_bellevue/commit/87092391af54ed4b71c19c146893922eb18111bc))

# [0.69.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.68.1...v0.69.0) (2026-04-11)


### Bug Fixes

* **cart:** blocage stock en temps réel + alerte checkout vin indispo ([7841a54](https://github.com/ApoSkunz/crabitan_bellevue/commit/7841a5403f7308167df54fe8d0a98078c890c94e))
* **model:** corriger ai.firstname (était ai.first_name) dans getCartUsersWithWine ([633549a](https://github.com/ApoSkunz/crabitan_bellevue/commit/633549a720f8b55cd2894cba315c6ca4ae4e75d8))
* **model:** getCartUsersWithWine — LIKE au lieu de JSON_CONTAINS pour MariaDB ([a93987d](https://github.com/ApoSkunz/crabitan_bellevue/commit/a93987d2372f6c335a218379c97821c577c4193d))
* **sonar:** réduire complexité openModal + supprimer ternaires imbriqués + NOSONAR cart ([198c48e](https://github.com/ApoSkunz/crabitan_bellevue/commit/198c48e4a19050805c4d8f9327636f620272303b))
* **test:** phpcs — phpcs:ignore MultipleClasses sur stubs dans StockNotificationServiceTest ([a24b57d](https://github.com/ApoSkunz/crabitan_bellevue/commit/a24b57df436e5c6b60e30e655f47498864916377))
* **ui:** toast 2000ms + bouton fermer + collection 4 colonnes (comme boutique) ([531c293](https://github.com/ApoSkunz/crabitan_bellevue/commit/531c29324319cd9a1bbe36b381ba8dcaf457b9cd))
* **view:** badge stock sur collection + repositionner badge top-right + retirer border-top cross-sell ([6f0e761](https://github.com/ApoSkunz/crabitan_bellevue/commit/6f0e761d2f6a659f5a2a289badd21ec54d73ad1d))


### Features

* **admin:** stock=0 → available=0 automatiquement lors de la mise à jour vin ([187bcfb](https://github.com/ApoSkunz/crabitan_bellevue/commit/187bcfbeac56956f26b04f8cd5749ac339947ee1))
* **cart:** bouton + grisé au max stock + toast 3000ms ([5cd4fab](https://github.com/ApoSkunz/crabitan_bellevue/commit/5cd4fabf35a18e443296ae89a40f70eaa442fca2))
* **cart:** notifier silencieusement les vins filtrés au chargement du panier ([bb2e240](https://github.com/ApoSkunz/crabitan_bellevue/commit/bb2e24014b6ebe86f7167a081dbcff8d5f6a011c))
* **controller:** alerte stock bas admin + contraintes stock commande ([fbec813](https://github.com/ApoSkunz/crabitan_bellevue/commit/fbec813b95712b22cd9f6f598407f6d267efa8db))
* **controller:** filtrer silencieusement les vins unavailable dans enrichItemsWithPrice ([3b2b321](https://github.com/ApoSkunz/crabitan_bellevue/commit/3b2b3213062f7b16884836ed0946df839e5c1b43))
* **i18n:** clé wine.low_stock_qty fr/en pour notice stock panier ([35dab5a](https://github.com/ApoSkunz/crabitan_bellevue/commit/35dab5a3101c986ed81aa76581b9afdb851aaa20))
* **js:** modale panier — max effectif stock-panier + message stock limité ([836f0ae](https://github.com/ApoSkunz/crabitan_bellevue/commit/836f0ae74588997a443de9fdfa60d21a6a7f5c64))
* **model:** ajouter resetLowStockNotified + getCartUsersWithWine ([2fe62ec](https://github.com/ApoSkunz/crabitan_bellevue/commit/2fe62ec1a920b42e22ad930f2277fe21c24531e9))
* **model:** ajouter setLowStockNotifiedAt, getCartUsersWithWine, getActiveNewsletterSubscribers, removeWineFromAllCarts ([45afa3f](https://github.com/ApoSkunz/crabitan_bellevue/commit/45afa3fbd4583efe3b8b05e5e83df080d71a9cff))
* **scss:** styles stock-info modale + admin-section + cart stock-notice ([a3ca419](https://github.com/ApoSkunz/crabitan_bellevue/commit/a3ca419c9b688c0825567a403f0469e736403649))
* **service:** brancher StockNotificationService sur WineAdminController::update() ([9f22695](https://github.com/ApoSkunz/crabitan_bellevue/commit/9f2269564940919716e6dd73ac659f373e12c361))
* **service:** créer StockNotificationService + sendLowStockAlert dans MailService ([1ac4b27](https://github.com/ApoSkunz/crabitan_bellevue/commit/1ac4b27973bf740c36422ce0d598ccde0c42f4fe))
* **service:** StockNotificationService — traces error_log + split ligne PHPCS ([c86c342](https://github.com/ApoSkunz/crabitan_bellevue/commit/c86c3423f10aedade9f87a68be36e2705ba38e7d))
* **view:** admin wines — cellule stock rouge + badge indisponible si stock=0 ([b86d2dc](https://github.com/ApoSkunz/crabitan_bellevue/commit/b86d2dc1acc56947a61625f435c8697d442fa178))
* **view:** badge Stock limité sur catalogue et fiche vin quand stock ≤ STOCK_ALERTE_LIMIT ([a01a154](https://github.com/ApoSkunz/crabitan_bellevue/commit/a01a1547eefb13c40c1ebf3aa279f19b68695c4e))
* **view:** contraintes stock — cartMap header + data-stock + notice panier ([c972ede](https://github.com/ApoSkunz/crabitan_bellevue/commit/c972edeee201b67e208ea82a1c75d2d0ecf2d747))

## [0.68.1](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.68.0...v0.68.1) (2026-04-11)


### Bug Fixes

* **ci:** retirer administration:read — permission invalide au niveau job ([7417c35](https://github.com/ApoSkunz/crabitan_bellevue/commit/7417c35923fec0fc5177b57611a59ffe9a70522e))

# [0.68.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.67.1...v0.68.0) (2026-04-11)


### Bug Fixes

* **ci:** supprimer warning PHPCS PSR1.SideEffects sur bootstrap_worktree.php (mélange define/require inévitable) ([bf403d1](https://github.com/ApoSkunz/crabitan_bellevue/commit/bf403d1967f005bffa726d17f3678286926b65cc))
* **remboursement:** badge i18n refund_pending + Vision Air client lourd + CSP onsubmit ([0e7b344](https://github.com/ApoSkunz/crabitan_bellevue/commit/0e7b344e1cb8755ae0535df22a26fa959fe31082))
* **scss:** empêcher débordement badge long dans admin-dl (refund_pending, return_requested) ([e241735](https://github.com/ApoSkunz/crabitan_bellevue/commit/e241735b37661525ea0adbee8e00e3eab36c912a))
* **service:** remplacer RuntimeException générique par GaeRefundException (Sonar S112) ([ab0ca92](https://github.com/ApoSkunz/crabitan_bellevue/commit/ab0ca921bfe8ae97c296722e6d3bd30c9a973610))
* **test:** adapter testOrderDetail processing+card — GAE=false → bouton refund (comportement attendu) ([246198b](https://github.com/ApoSkunz/crabitan_bellevue/commit/246198b4c38b7c848e94169f9cda8c87cd853872))
* **test:** initialiser MAIL_USER dans MailServiceTest setUp ([d255da1](https://github.com/ApoSkunz/crabitan_bellevue/commit/d255da18c4fccac7f294b241b7b58e089ac61904))
* **view:** adapter message confirmation remboursement selon CA_ENABLE_GAE (GAE=false → refund_pending, pas remboursement immédiat) ([9a1e343](https://github.com/ApoSkunz/crabitan_bellevue/commit/9a1e34379f34a4cbdfc42b9a82d2497578d5a12a))
* **view:** stepper client — ajouter timeline refund_pending→refunded et distinguer retour vs remboursement direct (delivered_at) ([309275f](https://github.com/ApoSkunz/crabitan_bellevue/commit/309275f4fb133b8b4fd712d13ab96765442a23b2))


### Features

* **controller:** ajouter requestRefund dans AccountController ([703f966](https://github.com/ApoSkunz/crabitan_bellevue/commit/703f966993fe60c1fd424dafa8a9bdaa919dde71))
* **i18n:** ajouter clés de traduction pour le remboursement client ([3b411ea](https://github.com/ApoSkunz/crabitan_bellevue/commit/3b411ea7e539789934bf8a2b47b7f727e94b058d))
* **routes:** ajouter route POST remboursement client ([ca42921](https://github.com/ApoSkunz/crabitan_bellevue/commit/ca4292125af9bfa46e4f203326e1f288dca1f812))
* **scss:** ajouter badge--refund_pending (orange distinct) ([b7c3141](https://github.com/ApoSkunz/crabitan_bellevue/commit/b7c3141ba5d2d3b39082d1fb1bbff36180b69b68))
* **service:** ajouter sendRefundPendingEmail dans MailService ([74bfaa6](https://github.com/ApoSkunz/crabitan_bellevue/commit/74bfaa63b96d93a888e49bbc2d46a314f79af315))
* **service:** créer GaeApiInterface, GaeApiClient et RefundService (us-remboursement-gae) ([49a2ab4](https://github.com/ApoSkunz/crabitan_bellevue/commit/49a2ab4a67420c148955943e88572a3259abc8b9))
* **service:** notifier le propriétaire sur demande remboursement manuel (GAE=false) — comme pour les retours ([6c64be0](https://github.com/ApoSkunz/crabitan_bellevue/commit/6c64be052c9058c18574be721d107744c06acb5e))
* **view:** afficher badge refund_pending et bloc Vision Air dans les vues commandes ([a3a7166](https://github.com/ApoSkunz/crabitan_bellevue/commit/a3a7166558ee9646b53d91f3a40fbe3b4ece12d3))

## [0.67.1](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.67.0...v0.67.1) (2026-04-11)


### Bug Fixes

* **order:** corriger CANCEL_WINDOW_DAYS 15→14 (conformité art. L221-18) ([b5c307f](https://github.com/ApoSkunz/crabitan_bellevue/commit/b5c307f3fa24de907a8c668e6596b18cbff73d48))
* **test:** aligner OrderModelTest sur CANCEL_WINDOW_DAYS=14 (L221-18 : 14j, pas 15j) ([ab57696](https://github.com/ApoSkunz/crabitan_bellevue/commit/ab57696183ef0018258de3bdb675ef3ddfb42ce2))

# [0.67.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.66.0...v0.67.0) (2026-04-11)


### Features

* **front:** ajouter lazy-load.js — Intersection Observer reveal + infinite scroll AJAX ([ffc599f](https://github.com/ApoSkunz/crabitan_bellevue/commit/ffc599fa828334790b2720730d55e915e2a1bc6d))
* **front:** integrer initLazyLoad dans le bundle principal ([59b264f](https://github.com/ApoSkunz/crabitan_bellevue/commit/59b264fb17271e5a10c6a62e11243f6252cade83))
* **scss:** spinner de chargement infini + classes js-reveal/is-revealed ([b5c48ad](https://github.com/ApoSkunz/crabitan_bellevue/commit/b5c48ad23b723fd5d8c1db254d0e3cdc5998434c))
* **view:** ajouter loading=lazy sur image principale fiche vin ([5a127a3](https://github.com/ApoSkunz/crabitan_bellevue/commit/5a127a3a510b1fe0b673f9ff7479db8513379b99))

# [0.66.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.65.0...v0.66.0) (2026-04-11)


### Bug Fixes

* **admin/ui:** pop-in de confirmation custom + fix validation newsletter ([87346ea](https://github.com/ApoSkunz/crabitan_bellevue/commit/87346eab6f6f10b4b075a0d0e5412ec99f7b35ff))
* **admin/ui:** résoudre double modal + rouge objet newsletter ([acb2325](https://github.com/ApoSkunz/crabitan_bellevue/commit/acb232571f48cfdc6cf482a689bbbfb35ea1c0d4))
* **csp/admin:** supprimer tous les on* inline — handlers délégués CSP-safe ([454a1e1](https://github.com/ApoSkunz/crabitan_bellevue/commit/454a1e13dec13448b336ddfce2893e454d14391b))
* **newsletter/model:** countBySegment('tous'|unknown) → UNION toutes sources + fix test helper newsletter ([ea21306](https://github.com/ApoSkunz/crabitan_bellevue/commit/ea213064c1ae2b9459bdd01a8669ae4073345ad2))
* **newsletter:** dédoublonner systématiquement newsletter_subscriptions à l'inscription + nonce CSP wines/form ([073cfce](https://github.com/ApoSkunz/crabitan_bellevue/commit/073cfce749d6df450148f615680af539fed537c7))
* **rgpd/admin:** export portabilité — ZIP + données abonnement sans compte ([9495b90](https://github.com/ApoSkunz/crabitan_bellevue/commit/9495b90d039f6773faf553fcbe32b5d44406e241))
* **scss/admin:** ajouter .admin-field__error (couleur rouge manquante) ([4c1cc2a](https://github.com/ApoSkunz/crabitan_bellevue/commit/4c1cc2ae4728f17eb46414f6fd0944f5d83eba6e))
* **test:** corriger return type null→int dans mock Database::execute() ([10c3a6a](https://github.com/ApoSkunz/crabitan_bellevue/commit/10c3a6a22247ff2156c67e9dc85f33d73579ac3d))
* **wines/admin:** ordre liste vins — disponibles en premier ([0d2f474](https://github.com/ApoSkunz/crabitan_bellevue/commit/0d2f474076b7742c6e39ac45a257eb469e0b540d))


### Features

* **newsletter:** consolider liste abonnés via UNION accounts + newsletter_subscriptions ([7d90b34](https://github.com/ApoSkunz/crabitan_bellevue/commit/7d90b344339cf28c02bf4c4463d5f0a95f0fec3d))
* **newsletter:** dédoublonner newsletter_subscriptions à l'inscription (critères 4 et 5) ([94b641d](https://github.com/ApoSkunz/crabitan_bellevue/commit/94b641d3c50b044be7954a35abface6c5d34195e))
* **newsletter:** segment non-inscrit (abonnés sans compte) ([3fcb796](https://github.com/ApoSkunz/crabitan_bellevue/commit/3fcb796c53ce9ebea0eb4c1bf0f209465e39375f))
* **view:** adapter liste admin newsletter pour afficher abonnés sans compte (source=subscription) ([ac88a28](https://github.com/ApoSkunz/crabitan_bellevue/commit/ac88a281db9451fa81180b02429c82862729ee38))

# [0.65.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.64.0...v0.65.0) (2026-04-11)


### Bug Fixes

* **cart:** inclure user_id dans le résultat de getAbandonedForEmail ([21362ab](https://github.com/ApoSkunz/crabitan_bellevue/commit/21362ab2da5ca52143469428d41b097549a10066))
* **marketing:** corriger FK wine_ratings + signature sendOrderStatusEmail ([a68edec](https://github.com/ApoSkunz/crabitan_bellevue/commit/a68edec73af2e703218316e4ceaee83a51d7e53e))
* **marketing:** délai email post-achat J+7 → J+21 (3 semaines) ([e6caa14](https://github.com/ApoSkunz/crabitan_bellevue/commit/e6caa14f6357ca04dd2154c22c6faa009bd76296))
* **marketing:** supprimer [@phpstan-ignore](https://github.com/phpstan-ignore) obsolètes + corriger TU post-merge ([14bd9ed](https://github.com/ApoSkunz/crabitan_bellevue/commit/14bd9edad6a2b3072007c80892b5f70c3e74fef7))
* **order:** null-coalesce vintage key pour éviter PHP warning ([6d18a7c](https://github.com/ApoSkunz/crabitan_bellevue/commit/6d18a7cb7b29ca3e8540278b382216cde80637be))
* **quality:** supprimer méthodes dupliquées + aligner tests sur nouvelles signatures ([da48f33](https://github.com/ApoSkunz/crabitan_bellevue/commit/da48f33732fe8dacfebee2fbade1fb7e6a5aba20))
* **sonar:** corriger 16 code smells + bug CartModel SQL + extraire constantes dupliquées ([a3d3cfb](https://github.com/ApoSkunz/crabitan_bellevue/commit/a3d3cfb14ce7fcab227bc22979f5b69dc92257a6))
* **sonar:** corriger code smell S1192 + augmenter couverture nouveau code ([f351744](https://github.com/ApoSkunz/crabitan_bellevue/commit/f351744bbef992f50eef94daa331b2413ed4a3a0))
* **test:** ajouter colonnes award et extra_comment manquantes dans INSERT wines ([693ff75](https://github.com/ApoSkunz/crabitan_bellevue/commit/693ff758bb8e380dc4a1ef12f86bd98b129ecd30))
* **test:** corriger 2 erreurs CI residuelles RatingModelTest ([7750f6d](https://github.com/ApoSkunz/crabitan_bellevue/commit/7750f6d330c92fa652dc738fcce281d643136103))
* **test:** corriger 3 échecs CI — colonnes snapshot, stepper logique, require_once ([d934311](https://github.com/ApoSkunz/crabitan_bellevue/commit/d9343117b4a782f29d20871235dafdcc299f91f4))
* **test:** corriger rateViaAccount — insérer connection active + FK disable pour wine_ratings ([1270b93](https://github.com/ApoSkunz/crabitan_bellevue/commit/1270b93c13233a832bfb688542071d895571cd52))
* **test:** corriger warnings PHPCS lignes trop longues CartControllerTest ([eee201c](https://github.com/ApoSkunz/crabitan_bellevue/commit/eee201c90bd0d159ade49ba0256f9cad384abb0f))
* **test:** supprimer colonne likes_count inexistante dans INSERT wines ([779d556](https://github.com/ApoSkunz/crabitan_bellevue/commit/779d556feeac86e8a611201f3fd4a5e159b3fa2e))
* **ux:** email bienvenue contenu riche + notation badge image + schema rating_email_sent_at ([6a39f09](https://github.com/ApoSkunz/crabitan_bellevue/commit/6a39f09a3ec554833058edc0168110b45b105a4c))
* **ux:** stepper ✓ sur confirmation + lien admin + email welcome OAuth + tri notation ([8ef71fc](https://github.com/ApoSkunz/crabitan_bellevue/commit/8ef71fc816a4fbda39ef7f281ba23cb8cca6579d))
* **ux:** supprimer stepper sticky + corriger structure HTML cross-sell panier ([50764f5](https://github.com/ApoSkunz/crabitan_bellevue/commit/50764f56fb261760261ac0135b4b8ce68fb54ac1))
* **view:** aligner ordre menu admin header sur sidebar back-office ([fe90f10](https://github.com/ApoSkunz/crabitan_bellevue/commit/fe90f10000c049d279397e5f965976f97cc396e1))
* **view:** supprimer doublon cross-sell panier + ajouter cuvée spéciale sur les cartes ([6c09937](https://github.com/ApoSkunz/crabitan_bellevue/commit/6c0993733426577f2245fbb58b3fe8c1fe52551f))


### Features

* **admin:** dashboard marketing — panier moyen, segmentation, newsletter, top vins notés ([6d8beda](https://github.com/ApoSkunz/crabitan_bellevue/commit/6d8bedabe49b9b4f56a419cf8ad1de965fe960b1))
* **controller:** RatingController — endpoint token email + notation directe + routes ([afd401d](https://github.com/ApoSkunz/crabitan_bellevue/commit/afd401d11366b8c86e7cacb87b9b13385cffba0c))
* **controller:** RatingController — page notation commande via token order ([41035d7](https://github.com/ApoSkunz/crabitan_bellevue/commit/41035d74ddbaff2f061c407f691d3088ce14f72b))
* **mail:** email bienvenue post-vérification + statut commande enrichi (adresse + suivi + CTA) ([e4f83a5](https://github.com/ApoSkunz/crabitan_bellevue/commit/e4f83a59d643eb828fea9693c837eb8d3ce26e7c))
* **mail:** email post-achat rating /5 + script cron panier abandonné J+3 + sendAbandonedCart ([ed3637d](https://github.com/ApoSkunz/crabitan_bellevue/commit/ed3637d20689613f726764f48c7393b9da4504b8))
* **marketing:** cross-sell panier + notation post-achat AJAX + fixes invité ([eceaa11](https://github.com/ApoSkunz/crabitan_bellevue/commit/eceaa110dfefd1cf62532a1127e1ef0572d6b0f4)), closes [#23](https://github.com/ApoSkunz/crabitan_bellevue/issues/23)
* **model:** CartModel reminder_sent_at + OrderModel rating_email_sent_at + RatingModel méthodes complètes ([cfff756](https://github.com/ApoSkunz/crabitan_bellevue/commit/cfff756f1c25c1467b2a9ca1ff540646e7055742))
* **model:** RatingModel + table wine_ratings pour notation /5 des vins ([b0f8326](https://github.com/ApoSkunz/crabitan_bellevue/commit/b0f83269c07b45098802c2e4d849fa763d44e906))
* **model:** tri rating_desc + reminder_sent_at panier abandonné unique ([7bb6799](https://github.com/ApoSkunz/crabitan_bellevue/commit/7bb679905f390234af620b4c2b0a8cc2f7595325))
* **model:** WineModel::getSimilar + getForCartCrossSell + injection cross-sell/rating WineController/CartController ([e812b82](https://github.com/ApoSkunz/crabitan_bellevue/commit/e812b82dc621fda705223b2bafd3478982351c1c))
* **routes:** route GET /rating/order/{orderToken} → RatingController@rateOrder ([17fb060](https://github.com/ApoSkunz/crabitan_bellevue/commit/17fb060919a75e4dec4826267fabf850f5174b0f))
* **service:** MailService — sendPostPurchase email notation J+7 + sendAbandonedCart enrichi ([f77e36e](https://github.com/ApoSkunz/crabitan_bellevue/commit/f77e36ea3d45f941d4bc3c726b85a9d2f41eb165))
* **ux:** lien Marketing header admin + welcome email CTA newsletter ([974c795](https://github.com/ApoSkunz/crabitan_bellevue/commit/974c7953dc96abc0efe95a50da90747e87e2be51))
* **ux:** stepper sticky + cross-sell logic + affichage note X.X/5 ([da7b0e3](https://github.com/ApoSkunz/crabitan_bellevue/commit/da7b0e393387535e8aa4dc12aa99ad1ecf694fc4))
* **view,cron:** vue rating/order.php + scripts CronRunner send-abandoned-cart + send-post-purchase ([3980005](https://github.com/ApoSkunz/crabitan_bellevue/commit/39800052c92b325aa5e1c5000a812010a8d97780))
* **view:** afficher millésime + cuvée spéciale dans le tableau du panier ([d3ec6b9](https://github.com/ApoSkunz/crabitan_bellevue/commit/d3ec6b9f9717a0d328feb061cec8377eb7d7df88))
* **view:** cross-sell fiche vin + panier + widget notation /5 + SCSS + i18n ([5c9ff5d](https://github.com/ApoSkunz/crabitan_bellevue/commit/5c9ff5dfe4a84cdab45b46a7337bbdb5d2bb1f40))
* **view:** stepper de progression tunnel commande (panier → livraison → confirmation) ([df65737](https://github.com/ApoSkunz/crabitan_bellevue/commit/df65737d610bc60ad9cb3c06150e938c21fed44f))

# [0.64.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.63.1...v0.64.0) (2026-04-10)


### Features

* **controller:** ajouter actions galerie et accessibilite dans PageController ([38603f7](https://github.com/ApoSkunz/crabitan_bellevue/commit/38603f76b9701649bcacfe62239150a0ecb96529))
* **i18n:** clés gallery.* et accessibility.* + footer.galerie/accessibilite (fr/en) ([fb5c97b](https://github.com/ApoSkunz/crabitan_bellevue/commit/fb5c97bde533f93512daaa6226cbff94e751856d))
* **scss:** styles galerie (grille, toolbar toggle, mode linéaire) + HVE et up2pay agrandis ([4922ecc](https://github.com/ApoSkunz/crabitan_bellevue/commit/4922ecc4eb02e0911d2af2450368844f5e6eea1d))
* **view:** pages galerie et accessibilite + footer + plan du site ([755a8a7](https://github.com/ApoSkunz/crabitan_bellevue/commit/755a8a75e6c07e0ad7d6c6c5ba49469eb0ab6c11))

## [0.63.1](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.63.0...v0.63.1) (2026-04-10)


### Bug Fixes

* **auth:** corriger fusion panier hors-ligne post-connexion ([60ef05e](https://github.com/ApoSkunz/crabitan_bellevue/commit/60ef05e235769068104774b30c8911372be29ab5))

# [0.63.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.62.0...v0.63.0) (2026-04-10)


### Bug Fixes

* **controller:** décrementer le stock dans paymentReturnOk (chemin fallback CB) ([256ea07](https://github.com/ApoSkunz/crabitan_bellevue/commit/256ea07344eebe20c3ff50cc42a044aaa77eb7e9))
* **model:** ajouter stock_quantity dans SELECT de getForAdmin() ([379c23d](https://github.com/ApoSkunz/crabitan_bellevue/commit/379c23d9fbfcbbf270f6999debb0d8483f25e163))
* **test:** slug unique dans insertWine/insertWineWithSlug pour éviter collision cross-suite ([0cb3661](https://github.com/ApoSkunz/crabitan_bellevue/commit/0cb3661ac5c4933c2d085e6daa11f13ca28da409))


### Features

* **admin:** ajouter champ stock_quantity dans le formulaire et la liste vins ([c4e188c](https://github.com/ApoSkunz/crabitan_bellevue/commit/c4e188c906bb5f382ba4dfc0b1a2ff9d75b0dd76))
* **admin:** bloquer mise en avant vin indisponible + retrait featured auto ([5e6e049](https://github.com/ApoSkunz/crabitan_bellevue/commit/5e6e0497524f0a1d4a05878445815dd66e43031b))
* **cart:** remplacer production_quantity par stock_quantity pour le contrôle de stock ([e4693ec](https://github.com/ApoSkunz/crabitan_bellevue/commit/e4693ec6d3029b2466b49db9bfdc28c74e195779))
* **model:** ajouter stock_quantity + decrementStock() à WineModel ([f9a57fd](https://github.com/ApoSkunz/crabitan_bellevue/commit/f9a57fd51df95d760059bf8b94085f5dbf752b66))
* **order:** afficher le millésime dans mails commande et vue admin ([9b617c4](https://github.com/ApoSkunz/crabitan_bellevue/commit/9b617c46add162af03ece6e1edb44941ad1cdc1e))
* **view:** masquer bouton 'Mettre en avant' si vin indisponible ([ae9ce69](https://github.com/ApoSkunz/crabitan_bellevue/commit/ae9ce6938b3fd001361b8c216b490d4caa48a0fc))

# [0.62.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.61.1...v0.62.0) (2026-04-10)


### Bug Fixes

* **admin:** corriger pendingAction nullifié avant exécution dans la modale de confirmation ([07e0142](https://github.com/ApoSkunz/crabitan_bellevue/commit/07e0142341392e6a10b17f953e40fa592fdbccad))
* **admin:** initialiser csrf en session admin, nonce CSP sur script inline vins ([6b0ab35](https://github.com/ApoSkunz/crabitan_bellevue/commit/6b0ab35764347863adc719ce955dfec979c2b27d))
* **cart:** masquer recommandation vin si panier invité non vide ([ed7e05d](https://github.com/ApoSkunz/crabitan_bellevue/commit/ed7e05dbf2320e04ecff5a4ac14ede25d1ccc3a6))
* **csp:** autoriser frame-src google.com pour la carte Maps homepage ([0c194ba](https://github.com/ApoSkunz/crabitan_bellevue/commit/0c194bac20d9176278dba53dbe550ab2f8534230))
* **sitemap:** harmoniser pages annexes — grille 3 cols + retirer --fit ([f8d9042](https://github.com/ApoSkunz/crabitan_bellevue/commit/f8d904296d9997714a8b27505ff90ba0d30b94d9))
* **sonar:** appliquer PAYMENT_LABEL_CHEQUE aux 2 occurrences restantes dans MailService ([9eddaee](https://github.com/ApoSkunz/crabitan_bellevue/commit/9eddaee98b2bcaabe41624f8eba3b7536bb7185c))
* **sonar:** constante PAYMENT_LABEL_CHEQUE_FR dans MailService, NOSONAR S1448 WineModel ([17a93c4](https://github.com/ApoSkunz/crabitan_bellevue/commit/17a93c42bf59ef47b9a93ed9259b9d442664b1f0))
* **test:** corriger ligne trop longue OrderModelTest PHPCS ([24c4f2e](https://github.com/ApoSkunz/crabitan_bellevue/commit/24c4f2ee4c362398c305767d71a17b81d8c30120))
* **view:** vin du moment + admin tableau vins — visuel, CSRF, modals ([0260a08](https://github.com/ApoSkunz/crabitan_bellevue/commit/0260a082abac7f14c23b712f90c95d1e1bed29a6))


### Features

* **admin:** modale de confirmation sur toutes les actions vins (mettre en avant, rendre disponible) ([8b8cf92](https://github.com/ApoSkunz/crabitan_bellevue/commit/8b8cf929dea2de6651b065a8543d31328b2b8ce7))
* **controller:** HomeController — passer featuredWine à la homepage ([3a77020](https://github.com/ApoSkunz/crabitan_bellevue/commit/3a7702002aa8da26fbdf4ff17f506c28cdf41882))
* **controller:** WineAdminController — setFeatured + toggleAvailable (AJAX) ([dd05d38](https://github.com/ApoSkunz/crabitan_bellevue/commit/dd05d3874ebbdcde6ef743a566d9df1a6b0f3786))
* **cron:** alerte hebdo commandes bloquées > 7 jours ([e9d9509](https://github.com/ApoSkunz/crabitan_bellevue/commit/e9d9509e152e176f69b7ca854cecd4b14a8c2d9a))
* **i18n:** clés home.featured_* pour section vin du moment ([71c9447](https://github.com/ApoSkunz/crabitan_bellevue/commit/71c94479ae362133531d8aeb0214913a7c3f8adb))
* **model:** ajouter certification_label dans getFeatured ([af715df](https://github.com/ApoSkunz/crabitan_bellevue/commit/af715df0ba0cc7fb9d9828ba4835d293a51681f8))
* **model:** WineModel — getFeatured, setFeatured, toggleAvailable ([a548c9a](https://github.com/ApoSkunz/crabitan_bellevue/commit/a548c9ac4344008d6123df7e64128fdc85facdd5))
* **page:** crédits photos — route, vue bilingue, lien footer ([73a483a](https://github.com/ApoSkunz/crabitan_bellevue/commit/73a483a8956d90b92f3faac612ec68bc73d4a6c0))
* **scss:** supprimer border/padding badge, homogénéiser bouton panier avec btn--gold ([e715aaf](https://github.com/ApoSkunz/crabitan_bellevue/commit/e715aaf0a1b97c76e8392f8de76d6f787ef00506))
* **sitemap:** ajouter carte crédits-photos dans Autres pages ([c9b8209](https://github.com/ApoSkunz/crabitan_bellevue/commit/c9b820942f07110b3b83a9dc547c8649ec3fd786))
* **sitemap:** retirer carte inscription, réaffecter image à jeux ([e303670](https://github.com/ApoSkunz/crabitan_bellevue/commit/e3036702c82477f22ff0ffac878978040a28725d))
* **view:** section vin du moment en premier, bouton panier js-add-to-cart, format AOC — appellation ([a279085](https://github.com/ApoSkunz/crabitan_bellevue/commit/a2790851e97c2b749a104ed8d85bbe96dd0a4000))
* **view:** vin du moment homepage + gestion admin tableau des vins ([2145321](https://github.com/ApoSkunz/crabitan_bellevue/commit/214532107e623608883b7071d658f4884f714e49))
* **weather:** déplacer crédit WeatherAPI vers mentions légales ([eea6eb0](https://github.com/ApoSkunz/crabitan_bellevue/commit/eea6eb0140787acbd55eb6b802c6fbb03826f16e))

## [0.61.1](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.61.0...v0.61.1) (2026-04-10)


### Bug Fixes

* **phpdoc:** élargir Core\Model::findAll() [@return](https://github.com/return) à array-key ([df19f03](https://github.com/ApoSkunz/crabitan_bellevue/commit/df19f03f74924fd76c817569fff209b0cbeb3813))

# [0.61.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.60.1...v0.61.0) (2026-04-10)


### Bug Fixes

* **security:** JSON_HEX_TAG sur json_encode JSON-LD — prévenir XSS </script> ([28df997](https://github.com/ApoSkunz/crabitan_bellevue/commit/28df997956a0b42f8d0bc17180a895a1c32447be))


### Features

* **seo:** ajouter robots.txt — disallow admin/api, référence sitemap ([e1320ee](https://github.com/ApoSkunz/crabitan_bellevue/commit/e1320ee75b37469d473b28f23500b9c290ac388c))
* **seo:** ajouter support meta description, OG, canonical, hreflang, JSON-LD dans head.php ([8f6656d](https://github.com/ApoSkunz/crabitan_bellevue/commit/8f6656d2da0800cfeace6349f3136b5852a83be8))
* **seo:** injecter meta/OG/hreflang/JSON-LD dans homepage, fiche vin, catalogue ([ec44c57](https://github.com/ApoSkunz/crabitan_bellevue/commit/ec44c576f933c30d66720ed0e670c28c18eca51b))

## [0.60.1](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.60.0...v0.60.1) (2026-04-10)


### Bug Fixes

* **ci:** corriger SHA actions/upload-artifact — v4.6.2 (ea165f8) ([9ed5fe6](https://github.com/ApoSkunz/crabitan_bellevue/commit/9ed5fe64c5eb13b75be66e733ad8ff4c8e4e0c61))
* **ci:** remplacer téléchargement phar Exakat par image Docker 2.6.9 ([e11589c](https://github.com/ApoSkunz/crabitan_bellevue/commit/e11589c01a97026fb99daa362ebc7775612590a7))
* **ci:** switcher vers exakat/exakat-ga:latest — image dédiée CI ([f66ba50](https://github.com/ApoSkunz/crabitan_bellevue/commit/f66ba507bb1c05f89e55c612bd2855c9605b7a76))

# [0.60.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.59.0...v0.60.0) (2026-04-10)


### Features

* **i18n:** ajouter clés manquantes FR/EN pour traduction intégrale ([04d4315](https://github.com/ApoSkunz/crabitan_bellevue/commit/04d4315060217c4cf76618ef7d1f5c956d5e03b2))
* **view:** remplacer tous les textes FR codés en dur par __() dans les vues publiques ([a10b098](https://github.com/ApoSkunz/crabitan_bellevue/commit/a10b09849e95416d06ebe0c082ebd27e21358980))

# [0.59.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.58.0...v0.59.0) (2026-04-10)


### Bug Fixes

* **pdf:** ingrédients comme champ dans section nutrition (corrige 500) ([64fb0cf](https://github.com/ApoSkunz/crabitan_bellevue/commit/64fb0cfdebb1d50f8fecdcea7f78e69292b7d3a9))
* **view:** ingredients_en readonly grisé comme les autres champs EN ([6574bf4](https://github.com/ApoSkunz/crabitan_bellevue/commit/6574bf409acf8da07bebca8f3e979d7e2f30950d))


### Features

* **i18n:** ingrédients vin bilingues fr/en (JSON + auto-translate DeepL) ([aa58bca](https://github.com/ApoSkunz/crabitan_bellevue/commit/aa58bcaf5f0bc2a1edf12df67dec944aa13752b3))
* **pdf:** ingrédients bilingues dans fiche technique (au-dessus nutrition) ([cf177df](https://github.com/ApoSkunz/crabitan_bellevue/commit/cf177df3ee9676169f979cf0d02681b998ddf38f))

# [0.58.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.57.0...v0.58.0) (2026-04-10)


### Bug Fixes

* **db:** corriger seed wine_nutrition (syntaxe MariaDB + slug rosé) ([05a282e](https://github.com/ApoSkunz/crabitan_bellevue/commit/05a282eed3ac32fbeee2137092eb3c83e21b39e8))
* **test:** production_quantity + signature renderPdfBody dans TU/TI ([2af1e5c](https://github.com/ApoSkunz/crabitan_bellevue/commit/2af1e5c4ed4b6f418bef51b78392070c5766788c))
* **test:** renommer quantity → production_quantity dans les fixtures TI ([d478f1d](https://github.com/ApoSkunz/crabitan_bellevue/commit/d478f1d0154958b2b0c781e6c8ac4fb9c63ff8bf))
* **view+db:** dégré alcool valeur unique (12 ou 12,5, pas de range) ([65dfb58](https://github.com/ApoSkunz/crabitan_bellevue/commit/65dfb58dd4eb17e0281886e5b860ba608e43787b))
* **view+pdf:** masquer nutrition + ingrédients si ENABLE_EXTRA_INFO=false ([a149a08](https://github.com/ApoSkunz/crabitan_bellevue/commit/a149a086d3c7068fc170a771e3cf0fb6757a1db5))
* **view+pdf:** validation form tous champs requis + kcal clear + alcool/garde dans INFORMATIONS ([08c8974](https://github.com/ApoSkunz/crabitan_bellevue/commit/08c897418f2dd261d23768438597d7e4cb270960))
* **view:** surligner champs nutrition vides si déclaration incomplète (live + submit) ([b61b631](https://github.com/ApoSkunz/crabitan_bellevue/commit/b61b631bd5089ea6694aaee459bed066b76b3eb5))


### Features

* **controller+view:** afficher nutrition sur fiche publique et PDF ([cff4cca](https://github.com/ApoSkunz/crabitan_bellevue/commit/cff4ccaf2a3746fe73bee2fe935a069086c6a554))
* **controller:** injecter WineNutritionModel dans WineAdminController ([732c633](https://github.com/ApoSkunz/crabitan_bellevue/commit/732c633d5b0f1ddb307cfb2c24ccc1af184d7d95))
* **controller:** production_quantity + kcal auto-calculé + buildNutritionFields depuis wine ([a1f4277](https://github.com/ApoSkunz/crabitan_bellevue/commit/a1f4277fcdd7bd10738a67c714f365822ddc8435))
* **i18n:** ajouter clés nutrition FR/EN ([5574d92](https://github.com/ApoSkunz/crabitan_bellevue/commit/5574d925f0d2f835a0ce2dc7709b49e66f50107c))
* **model:** créer WineNutritionModel (upsert + findByWineId) ([497fe52](https://github.com/ApoSkunz/crabitan_bellevue/commit/497fe52f386ecf137cb991651c9f42ed81fbb53d))
* **model:** production_quantity rename + energy_kcal supprimé + getBySlug inclut alcohol/garde ([f56769d](https://github.com/ApoSkunz/crabitan_bellevue/commit/f56769d907c9828b721e36ece64da735c302ce3e))
* **scss:** champ kcal grisé readonly (.admin-field__input--readonly) ([c6b6701](https://github.com/ApoSkunz/crabitan_bellevue/commit/c6b67011e0d925e6c11158b6c531820008ed353c))
* **validation:** déclaration nutritionnelle complète ou absente ([4224741](https://github.com/ApoSkunz/crabitan_bellevue/commit/42247414de9a40f32a826c3b95995826e854f22b))
* **view:** kcal affiché dynamiquement sous le champ kJ (hint inline) ([5c74e20](https://github.com/ApoSkunz/crabitan_bellevue/commit/5c74e2091011bf423a06810066be2c1bf75b29e8))
* **view:** kcal mimétisme slug — input readonly grisé mis à jour en live ([d9c25ad](https://github.com/ApoSkunz/crabitan_bellevue/commit/d9c25ad0698db494f8b87e3abf92523e0be5a907))
* **view:** section informations complémentaires dans formulaire admin vin ([6078873](https://github.com/ApoSkunz/crabitan_bellevue/commit/6078873a9a4a5c1fc9edeca5dcae72e14cc13165))
* **view:** tableau nutrition HTML + production_quantity + kcal readonly admin ([5c25a53](https://github.com/ApoSkunz/crabitan_bellevue/commit/5c25a53bd04bf44b9f877164fc3e984864a50099))

# [0.57.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.56.0...v0.57.0) (2026-04-10)


### Bug Fixes

* **age-gate:** remplacer 302 direct par meta-refresh pour contourner CSP form-action ([7e29f69](https://github.com/ApoSkunz/crabitan_bellevue/commit/7e29f699e2f68a9db2d1cb5ff23aca958ca3a528))
* **checkout:** afficher le formulaire nouvelle adresse quand aucune adresse enregistrée ([23a1df3](https://github.com/ApoSkunz/crabitan_bellevue/commit/23a1df36b842424d2990c03cb61f0b51a123b209))
* **e2e:** mocker google.com dans age-gate pour éviter ERR_ABORTED en CI ([e53e7d4](https://github.com/ApoSkunz/crabitan_bellevue/commit/e53e7d4c9934c088066372a8948d65cdc914ce54))
* **sonar:** NOSONAR meta-refresh + TU SecurityHeaderMiddleware (coverage 0→100%) ([37ba917](https://github.com/ApoSkunz/crabitan_bellevue/commit/37ba9175f76f7fb22fec8c29c8082e5d2dcdf08c))
* **test:** adapter AgeGateControllerTest au meta-refresh + corriger assertTrue(true) ([cf10dec](https://github.com/ApoSkunz/crabitan_bellevue/commit/cf10dec19ac1ffc6fc4bef6c9146a855c8bfe6aa))


### Features

* **security:** ajouter SecurityHeaderMiddleware avec CSP nonce par requête ([5b7e784](https://github.com/ApoSkunz/crabitan_bellevue/commit/5b7e78464851b48e4a97d99c0dd8ba396cc37087))
* **view:** ajouter nonce CSP sur tous les <script> inline (20 vues) ([d0d3b96](https://github.com/ApoSkunz/crabitan_bellevue/commit/d0d3b9689ba8a0064edc08ad6365ca6dea389f7b))

# [0.56.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.55.0...v0.56.0) (2026-04-10)


### Bug Fixes

* **age-gate:** passer SameSite=Lax sur le cookie age_verified ([3bef442](https://github.com/ApoSkunz/crabitan_bellevue/commit/3bef44207ef0bf480c35f3660a43a4eb9b09bda3))
* **model:** déchiffrer email_change_new_email dans findById/findByEmail ([ae3fb84](https://github.com/ApoSkunz/crabitan_bellevue/commit/ae3fb84652ba34221b527eb6763dace18b70c0bc))
* **schema:** corriger l'ordre DROP — newsletter_clicks avant newsletter_links ([e3cfa4c](https://github.com/ApoSkunz/crabitan_bellevue/commit/e3cfa4c696a21f32d4bcf5bfef8950c4eb2b810a))
* **test:** décrypter email_change_new_email dans AccountEmailChangeTest ([8b191de](https://github.com/ApoSkunz/crabitan_bellevue/commit/8b191de469ea93b0607c871645c05e62a7c31703))


### Features

* **security:** chiffrement AES-256-GCM colonnes sensibles — us-chiffrement-colonnes-sensibles-bdd ([6bfd5d3](https://github.com/ApoSkunz/crabitan_bellevue/commit/6bfd5d370b22f5fe6c64ea56c4cc1c185009554b))

# [0.55.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.54.2...v0.55.0) (2026-04-10)


### Bug Fixes

* **security:** activer CURLOPT_SSL_VERIFYHOST=2 sur tous les appels cURL CA — us-securisation-transport-api-ca ([6964d73](https://github.com/ApoSkunz/crabitan_bellevue/commit/6964d739220fc5d6e8a216c70d28af4b81755ed1))


### Features

* **cron:** CronRunner — lock, logs fichier, rotation 30j, exit code (us-infrastructure-cron) ([df298cf](https://github.com/ApoSkunz/crabitan_bellevue/commit/df298cf70f77f6136cbf7cfc7946305b8608293b))
* **cron:** purge des tokens expirés — us-nettoyage-tokens-expires ([bbb369a](https://github.com/ApoSkunz/crabitan_bellevue/commit/bbb369ab9a4f013acfd767e2a7f32467a45e2edc))
* **infra:** forcer HTTPS + HSTS en production (us-https-tls-ionos / us-hsts) ([359ac0b](https://github.com/ApoSkunz/crabitan_bellevue/commit/359ac0b6eac91db141506f9290b1fec681771848))

## [0.54.2](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.54.1...v0.54.2) (2026-04-10)


### Bug Fixes

* **scss:** corriger contrastes WCAG AA et positions NOSONAR css:S7924 ([e4282aa](https://github.com/ApoSkunz/crabitan_bellevue/commit/e4282aaa47357d7f2b9bdae80e9739a7f2e8880c)), closes [#16a34a](https://github.com/ApoSkunz/crabitan_bellevue/issues/16a34a) [#166534](https://github.com/ApoSkunz/crabitan_bellevue/issues/166534) [#e53935](https://github.com/ApoSkunz/crabitan_bellevue/issues/e53935) [#b71c1c](https://github.com/ApoSkunz/crabitan_bellevue/issues/b71c1c)
* **sonar:** déplacer NOSONAR PHP sur les lignes flaggées (S3776, S107) ([3168a43](https://github.com/ApoSkunz/crabitan_bellevue/commit/3168a435ecc3bb3c8cfb59c633123a1213332655))
* **view:** ajouter <track> à la vidéo home — corrige Web:S4084 ([13ed1f8](https://github.com/ApoSkunz/crabitan_bellevue/commit/13ed1f84b80b7708f9fd9deec02978d1ad0b3c2c))

## [0.54.1](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.54.0...v0.54.1) (2026-04-10)


### Bug Fixes

* **sonar:** résoudre tous les code smells + security hotspot sprint-5b ([64f3c47](https://github.com/ApoSkunz/crabitan_bellevue/commit/64f3c473ae68b155bbc39787cecf6d024a18bddc))
* **test:** corriger 3 échecs CI TI/TU ([f5fc1bc](https://github.com/ApoSkunz/crabitan_bellevue/commit/f5fc1bc97b68938bc39b35807e459a7f67909907))

# [0.54.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.53.0...v0.54.0) (2026-04-10)


### Bug Fixes

* **controller,view:** validation date future + format version x.y.z + conjugaison vont ([8c8c905](https://github.com/ApoSkunz/crabitan_bellevue/commit/8c8c905248682e2ca80b117b7ee48110231a0b75))
* **cs:** closing brace after body — phpcbf PSR2 ([8fa2d77](https://github.com/ApoSkunz/crabitan_bellevue/commit/8fa2d77b84078163a121ea91b2cd7866cf05b76b))
* **seed:** INSERT IGNORE legal_documents — évite doublon slug si seed relancé ([ddbdae6](https://github.com/ApoSkunz/crabitan_bellevue/commit/ddbdae699b258c0723d15ea20ec875f1c44adc2b))
* **seed:** retirer legal_documents du seed — déjà inséré par schema.sql ([d4e0108](https://github.com/ApoSkunz/crabitan_bellevue/commit/d4e0108fe496ebca4fe4a1f1554833e9a705334e))
* **service:** accord français email légal — "notre" → "le document" ([44e5a3a](https://github.com/ApoSkunz/crabitan_bellevue/commit/44e5a3adfdfaccc78f62c60d7084c2c58142742b))


### Features

* **controller:** DpoAdminController + OrderController — legalConfig depuis BDD ([7bf4675](https://github.com/ApoSkunz/crabitan_bellevue/commit/7bf467513c4f0c0d6e017a95a769062d82feb095))
* **controller:** LegalDocumentAdminController — history action, logHistory on store, date normalization setTime(0,0,0), date >= current version validation ([b301f48](https://github.com/ApoSkunz/crabitan_bellevue/commit/b301f48b8064783d0560998ab84ec6433d3d58bc))
* **controller:** LegalDocumentAdminController — versioning + notification batch ([fd90e8e](https://github.com/ApoSkunz/crabitan_bellevue/commit/fd90e8e130600c4344197bc0a6c0b20cb3c68eca))
* **model:** LegalDocumentModel — versioning + notification batch ([b592510](https://github.com/ApoSkunz/crabitan_bellevue/commit/b59251054f8f7d00c6f80d80a7e738195502bde5))
* **routes:** pages-légales + confirmer-notification ([54c350d](https://github.com/ApoSkunz/crabitan_bellevue/commit/54c350de18ecf598ea458b0c448f6c558341d036))
* **service:** MailService — sendLegalDocumentUpdate FR/EN ([2ba3e2d](https://github.com/ApoSkunz/crabitan_bellevue/commit/2ba3e2d5f351bed40c8cdfd63e0afd061d456181))
* **view,routes:** pages-légales historique — route GET, vue history, lien Historique dans index ([642d11b](https://github.com/ApoSkunz/crabitan_bellevue/commit/642d11bcc5b264fe382e85e50d255cf789780b96))
* **view:** DPO index — masquer Pages légales + Demandes RGPD pour admin simple ([d739d53](https://github.com/ApoSkunz/crabitan_bellevue/commit/d739d5325b244e8e8e9519e9bead3a4b71584190))
* **view:** pages-légales admin — liste, édition, confirmation notification ([c8a6c7c](https://github.com/ApoSkunz/crabitan_bellevue/commit/c8a6c7ca9988041a63492d3671b50748246973dd))


### Reverts

* **feature:** drop legal_document_history — redondant avec admin_logs + legal_notification_logs ([6e33e05](https://github.com/ApoSkunz/crabitan_bellevue/commit/6e33e05d3961e70d6a0dbca6390726b00a4870a1))

# [0.53.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.52.0...v0.53.0) (2026-04-09)


### Bug Fixes

* **controller:** NewsletterModel::create() +title param dans WineAdminController + refactor nested ternaries (php:S3358) ([d90e316](https://github.com/ApoSkunz/crabitan_bellevue/commit/d90e3163d798d7c3e0d8a349ea199f1ce599ca6a))
* **mail:** buildNewsletterHtml — urlUnsub et texte footer bilingues (FR/EN) selon $lang ([8af6d0d](https://github.com/ApoSkunz/crabitan_bellevue/commit/8af6d0d25ba7e7d9cb1479c97157a660fb8aac80))


### Features

* **bin:** add purge_newsletter_tracking.php — cron mensuel purge données tracking > 13 mois (RGPD Art. 5-1-e) ([bb093ac](https://github.com/ApoSkunz/crabitan_bellevue/commit/bb093ac7ecba9b4c8c04d2c2e90102bef08608d4))
* **controller:** add TrackingController — open() pixel 1×1 GIF + click() redirect avec enregistrement ([17dd867](https://github.com/ApoSkunz/crabitan_bellevue/commit/17dd867e15253242b928592d54dadc492e2810cf))
* **controller:** NewsletterAdminController — extraction liens + tracking par abonné dans send(), stats dans show() ([c1ca885](https://github.com/ApoSkunz/crabitan_bellevue/commit/c1ca885f659ca9355144db2bb951347e06960b4b))
* **controller:** tracking désinscriptions newsletter par campagne (cid propagé GET→POST) ([b680c78](https://github.com/ApoSkunz/crabitan_bellevue/commit/b680c78a92d94c76092d4fbd877420e2345396ed))
* **model:** add NewsletterTrackingModel — saveLink, recordOpen, recordClick, getStats, purge 13 months ([58723f4](https://github.com/ApoSkunz/crabitan_bellevue/commit/58723f40765719f46314e8b80bba6c7ae84a6865))
* **model:** NewsletterTrackingModel — recordUnsubscribe + unsubscribes_count dans getStats ([666e894](https://github.com/ApoSkunz/crabitan_bellevue/commit/666e8946a1b57018b046d525343f04b44f0ead90))
* **newsletter:** historique index — colonne Ouvertures X/N Y% ([2843559](https://github.com/ApoSkunz/crabitan_bellevue/commit/2843559cc95c0135ba6c210f95bd438b2b754285))
* **newsletter:** traduction automatique FR→EN via DeepL avant dispatch ([9148953](https://github.com/ApoSkunz/crabitan_bellevue/commit/91489534246cc4e02c88d8b0c06eb5705be79f19))
* **newsletter:** version EN optionnelle — objet/titre/corps traduits une fois avant dispatch ([e501a49](https://github.com/ApoSkunz/crabitan_bellevue/commit/e501a49d8f48f90f3d28aae415ed3d46025bd7a9))
* **routes:** add /track/open/{id}/{token} + /track/click/{id}/{linkId}/{token} ([0933113](https://github.com/ApoSkunz/crabitan_bellevue/commit/093311392fa380e7bb7af036c125d72a1819b23c))
* **service:** MailService — pixel tracking dans buildNewsletterHtml, List-Unsubscribe RFC 8058 dans sendNewsletter ([1a693ab](https://github.com/ApoSkunz/crabitan_bellevue/commit/1a693aba2e5b3fa67aa5d609cb56b57773397b6c))
* **tracking:** unique_clicks_count, getLinkStats(), détail par lien dans show + fix clic <a> + traduction FR/EN newsletter ([04f9f08](https://github.com/ApoSkunz/crabitan_bellevue/commit/04f9f084ac3707c38b205fdccf213d2e0a916d8e))
* **view:** newsletter show — section stats tracking ; politique-confidentialité — tracking + durée 13 mois ([8733f34](https://github.com/ApoSkunz/crabitan_bellevue/commit/8733f3452186aa8a2deca429db7f0da1ff32dac6))
* **view:** unsubscribe cid propagé dans form + show affiche titre/désabo/clics conditionnels ([dbdcd5b](https://github.com/ApoSkunz/crabitan_bellevue/commit/dbdcd5b77158b7d03ed3d2bb770b3df93cf00438))

# [0.52.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.51.0...v0.52.0) (2026-04-09)


### Bug Fixes

* **view:** newsletter — retire le count total de l'option 'Tous les abonnés' ([3c83fd8](https://github.com/ApoSkunz/crabitan_bellevue/commit/3c83fd80eeac897fd859999a22516285ba3964fb))


### Features

* **controller:** segmentation newsletter — endpoint AJAX segment-count + filtrage send() par segment ([4ac3e15](https://github.com/ApoSkunz/crabitan_bellevue/commit/4ac3e15c5f64bb941cb37b2866b630d917358629))
* **model:** segmentation newsletter — getNewsletterSubscribersBySegment + countNewsletterSubscribersBySegment + segment_label dans NewsletterModel ([b3ff9a9](https://github.com/ApoSkunz/crabitan_bellevue/commit/b3ff9a9881a43e5df7473723217277697f9a9d31))
* **view:** newsletter admin — select segment + compteur AJAX + colonne historique + modale mise à jour ([9bc5dd2](https://github.com/ApoSkunz/crabitan_bellevue/commit/9bc5dd2e6f08a77a72d8b99fcf76816c3a1f4e20))

# [0.51.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.50.0...v0.51.0) (2026-04-09)


### Bug Fixes

* **coverage:** codeCoverageIgnore boucles slugs sitemap + fix test CI ([b761b07](https://github.com/ApoSkunz/crabitan_bellevue/commit/b761b0766b156c9179f46e41c6f52d85d6821f40))
* **coverage:** supprime codeCoverageIgnore + test sitemap slugs seed ([dd389fa](https://github.com/ApoSkunz/crabitan_bellevue/commit/dd389fafbb741277e77aee62f23fd1d4f95c2dad))
* **qrcode:** encode fiche-technique URL instead of public wine page ([9783243](https://github.com/ApoSkunz/crabitan_bellevue/commit/978324313b8c1f477f0c48dc5c4bcc9a8b9d3b45))
* **security:** remplace tous les fallbacks http:// par https://crabitanbellevue.fr ([0e85b02](https://github.com/ApoSkunz/crabitan_bellevue/commit/0e85b02b763c6e87bf36dbccda1cc4f74781c551))
* **security:** supprime dernières références à localhost ([181164d](https://github.com/ApoSkunz/crabitan_bellevue/commit/181164d5e6eab5903e99d7c62dc7135a40b3e215))
* **sonar:** PageController coverage 100% + S1172 NOSONAR ([b76fce4](https://github.com/ApoSkunz/crabitan_bellevue/commit/b76fce40f03363b885931915833f87acff790eac))
* **sonar:** PageController S1172 + hotspot http + coverage sitemap/getAllSlugs ([cf3308d](https://github.com/ApoSkunz/crabitan_bellevue/commit/cf3308dd6a1a0276153940e462b3e25e38b6721a))
* **sonar:** S1192 — constante DEFAULT_URL pour les fallbacks MailService ([c12015d](https://github.com/ApoSkunz/crabitan_bellevue/commit/c12015de93f7845dc810e43c2aa6882906f235ba))


### Features

* **admin:** affiche "Cuvée spéciale" dans le titre de la modal QR ([ac79209](https://github.com/ApoSkunz/crabitan_bellevue/commit/ac7920909702a921dedbc0319670670950431bc2))
* **admin:** bouton téléchargement QR code dans la modal vins ([161a31f](https://github.com/ApoSkunz/crabitan_bellevue/commit/161a31f219f61afd9bfb1c1b131cb159da7206c6))
* **admin:** QR code modal accessible depuis le tableau des vins ([c492c76](https://github.com/ApoSkunz/crabitan_bellevue/commit/c492c76f8ec59d7e7ca2a7de2fcef519b53d504d))
* **catalogue:** sitemap.xml dynamique + QR code vin admin ([fc04cbc](https://github.com/ApoSkunz/crabitan_bellevue/commit/fc04cbce5625567582d72f290f74f5d34b43beb7))

# [0.50.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.49.0...v0.50.0) (2026-04-09)


### Features

* **rgpd:** send final deletion email before hard-delete in purge_accounts.php ([3cdc1c6](https://github.com/ApoSkunz/crabitan_bellevue/commit/3cdc1c61705e09564abf5ed43556929969a72155))
* **rgpd:** send inactivity deletion email on cron phase 2 ([be630f3](https://github.com/ApoSkunz/crabitan_bellevue/commit/be630f3dd9dfceaacff40d977733f24c5ed3968e))

# [0.49.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.48.0...v0.49.0) (2026-04-09)


### Bug Fixes

* **test:** AccountModelInactivityTest — auth_method 'standard' → 'password' (ENUM constraint) ([c2798dc](https://github.com/ApoSkunz/crabitan_bellevue/commit/c2798dcd5258842a7c482ddbdd28c3492949fa1b))


### Features

* **bin:** purge_inactive_accounts.php (warn+soft-delete) + purge_addresses.php (orphans) ([d793978](https://github.com/ApoSkunz/crabitan_bellevue/commit/d793978e1d9783235568d521449777b4d2ab0066))
* **mail:** sendInactivityWarning — email d'avertissement inactivité RGPD (fr/en) ([f8856be](https://github.com/ApoSkunz/crabitan_bellevue/commit/f8856be54e1bffabe3f0a63b5cb1e22d0cb8549e))
* **model:** AccountModel inactivity detection + soft-delete, AddressModel orphan purge (RGPD Art. 5 §1e) ([f6c8a13](https://github.com/ApoSkunz/crabitan_bellevue/commit/f6c8a13eae14722e2dae44680841bd79b5662bbd))

# [0.48.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.47.0...v0.48.0) (2026-04-09)


### Bug Fixes

* **lang:** cb-lang cookie sets DEFAULT_LANG over browser detection, age-gate infers lang from redirect path ([6772b24](https://github.com/ApoSkunz/crabitan_bellevue/commit/6772b24401de07f188326d78802b547d664075a6))
* **sonar:** AccountController CONTENT_TYPE_JSON constant + NOSONAR S3776/S1172, profile.php reuse \$selected, main.js window→globalThis ([503a48c](https://github.com/ApoSkunz/crabitan_bellevue/commit/503a48c1e512384c1fa69e05e0bb8cffa00b6644))


### Features

* **account:** add updateLangPreference endpoint (POST /{lang}/mon-compte/preference-langue) ([73c6785](https://github.com/ApoSkunz/crabitan_bellevue/commit/73c6785f4569dc389de1cd910fe82ce580552fe2))
* **account:** use accounts.lang preference for login redirect ([d75ac31](https://github.com/ApoSkunz/crabitan_bellevue/commit/d75ac316300484c38a1cb577122f9bc2ef7c00f1))
* **js:** initLangPreference — AJAX save on profile form submit ([d927628](https://github.com/ApoSkunz/crabitan_bellevue/commit/d92762830ff8fd487215230acbdfe30ccf0ac053))
* **lang:** redirect to account lang on login, clear cb-lang on logout ([fc7f16a](https://github.com/ApoSkunz/crabitan_bellevue/commit/fc7f16afe95237b776678ff36d2f2d9ea59788b9))
* **view:** add lang preference section to profile page (select + submit) ([97006fb](https://github.com/ApoSkunz/crabitan_bellevue/commit/97006fbed74f8480de56c5b0a83ff8ecad25647d))
* **view:** header lang switcher uses form POST for logged-in users ([d6540f1](https://github.com/ApoSkunz/crabitan_bellevue/commit/d6540f1bea0df512319a2670325dc3a4df160b4e))

# [0.47.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.46.0...v0.47.0) (2026-04-08)


### Bug Fixes

* **admin:** requireAdmin() retourne firstname + lastname dans name (plus firstname seul) ([bda00dc](https://github.com/ApoSkunz/crabitan_bellevue/commit/bda00dc8aa0cd8c38c7fbdb0d572ddba2ac22c44))
* **auth:** restore 3-failure alert + fix admin security block order (before BT2 lockout) ([6c301fe](https://github.com/ApoSkunz/crabitan_bellevue/commit/6c301fe52b19964cb5a390efda1c6a0589f0492d))
* **config:** guard define() with defined() to prevent PHP warning in test context ([d13d30a](https://github.com/ApoSkunz/crabitan_bellevue/commit/d13d30a2a75f5bc5492d7264b159ab97c3f3ba4e))
* **legal:** matrice_habilitations version 1.1→1.0 (pas encore en PROD) ([a95f36c](https://github.com/ApoSkunz/crabitan_bellevue/commit/a95f36ce5c8e711527bfc828facdb0a49082de8a))
* **middleware:** blocked admin → flash + redirect /connexion instead of abort(403) ([8bd3f01](https://github.com/ApoSkunz/crabitan_bellevue/commit/8bd3f01cb37110dc4b99f714626d7270b7c48c36))
* **model:** null coalesce majority_declared_at to suppress undefined key warning ([8db9721](https://github.com/ApoSkunz/crabitan_bellevue/commit/8db97214c465d84dccca1ec749ddba79db61a20f))
* **security:** lockout 30min/3 échecs, blocage auto admin 5 cumulés/1h compteur dédié ([df43ff4](https://github.com/ApoSkunz/crabitan_bellevue/commit/df43ff46270b3c3f9d2365f233c99797d0455fc2))
* **security:** requireSuperAdmin returns 404 instead of 403 to avoid route enumeration ([602d306](https://github.com/ApoSkunz/crabitan_bellevue/commit/602d306b9aea71206e8dd63b60dbeb61c2768013))
* **sonar:** add NOSONAR php:S2003 on legalConfig require — require_once would break caching on subsequent calls ([de3a999](https://github.com/ApoSkunz/crabitan_bellevue/commit/de3a99970259fde36814a0e3c814b3709f88d36e))
* **sonar:** move NOSONAR php:S2003 inline on require line in legalConfig() ([f268c0a](https://github.com/ApoSkunz/crabitan_bellevue/commit/f268c0a4101331dd8d80133332a52897ef1a3c2f))
* **sonar:** resolve all SonarCloud issues — S1192 constants, S2003 legalConfig cache, S1763/S5255 NOSONAR+aria-label, S1172 remove unused param, PHPStan/PHPCS clean ([b4ba468](https://github.com/ApoSkunz/crabitan_bellevue/commit/b4ba468f1d8810098bc44f4f61b827a2135507ce))
* **sprint-5:** recette — mail MAINTAINER, blocage message, fputcsv, nav super_admin, supprimer RGPD, select JS, matrice PDF formalisme, versioning DPO, lien demandes RGPD depuis DPO ([beeacf2](https://github.com/ApoSkunz/crabitan_bellevue/commit/beeacf229a2e88b05a868bb28eec615cef3e312c))


### Features

* **config:** add matrice_habilitations versioning in legal.php (v1.1) ([4d96b9e](https://github.com/ApoSkunz/crabitan_bellevue/commit/4d96b9e9fd7988353a911e12656deb1378d2e7ea))
* **controller:** add AdminGestionAdminsController, AuditLogAdminController, RgpdAdminController ([8599fb9](https://github.com/ApoSkunz/crabitan_bellevue/commit/8599fb947bafa00b27caf9a2aa0782b011325877))
* **controller:** DpoAdminController — matrice habilitations PDF versioned via config/legal.php ([09e7597](https://github.com/ApoSkunz/crabitan_bellevue/commit/09e7597c22c8cbfa5c758b0620fa5f33e90a990d))
* **controller:** integrate AuditLogger in admin controllers (order, account, newsletter, wine, pricing) ([0c5d0ae](https://github.com/ApoSkunz/crabitan_bellevue/commit/0c5d0ae4fd6ce554ba59546fc1e12a666b000fc1))
* **controller:** integrate SecurityAlertService in AuthController + ProfileAdminController ([8d495a1](https://github.com/ApoSkunz/crabitan_bellevue/commit/8d495a1543765b9d77b7f0d19b74d6e685502a9f))
* **controller:** track RGPD exports + restrict admin journal to super_admin ([7235ffb](https://github.com/ApoSkunz/crabitan_bellevue/commit/7235ffb7df0e66717e6dc37eed609d972781b7ed))
* **i18n:** add auth.admin_account_blocked key (fr + en) ([14256b7](https://github.com/ApoSkunz/crabitan_bellevue/commit/14256b7dbf3b8caa6e71b91cd5b5237900ed8ebc))
* **mail:** sendAdminAccountUnblocked + mail déblocage depuis unblock() + message blocage explicite ([801c574](https://github.com/ApoSkunz/crabitan_bellevue/commit/801c57459ddc8feec5139a5d9dd8e94cd1355701))
* **middleware:** add is_blocked check in AdminMiddleware + requireSuperAdmin in AdminController ([51b507b](https://github.com/ApoSkunz/crabitan_bellevue/commit/51b507bfce170f49d05f1038ad9f062dd59150a6))
* **model:** AccountModel — findAdmins, blockAccount, unblockAccount + getForAdmin RGPD minimisation ([930412e](https://github.com/ApoSkunz/crabitan_bellevue/commit/930412e4676931dc51bf5b71bedc3f14676151c1))
* **model:** add AdminLogModel (audit journal) + RgpdRequestModel (Art. 15-21 RGPD) ([50e68cd](https://github.com/ApoSkunz/crabitan_bellevue/commit/50e68cda2a69a74a9df11f2087d86f14073e1b82))
* **model:** add RgpdExportLogModel — append-only log exports Art. 20 ([7ded35f](https://github.com/ApoSkunz/crabitan_bellevue/commit/7ded35fae09a1abd9c7c9cf39c515468ccbac49e))
* **rgpd:** implement DPIA Art. 35 — PDF runtime from /admin/dpo/dpia ([c201472](https://github.com/ApoSkunz/crabitan_bellevue/commit/c20147267ef6316d85a49182fe89d2aacc37ad29))
* **routes:** add sprint-5 admin routes (admins, logs, rgpd demandes, dpo matrice) ([a212651](https://github.com/ApoSkunz/crabitan_bellevue/commit/a21265146ff7d6f4e5685719f1cd24e283f6ee5b))
* **service:** add AuditLogger — silent static log to admin_logs (Art. 5.2 RGPD) ([781d00e](https://github.com/ApoSkunz/crabitan_bellevue/commit/781d00e6b4dcaf8951754bcb4a2ba7365e6cfa8f))
* **service:** add SecurityAlertService + MailService admin login/password alerts ([a20e6e0](https://github.com/ApoSkunz/crabitan_bellevue/commit/a20e6e01fad5976baa97af318ca90145cd746d14))
* **view:** add admin views for admins, logs, rgpd demandes + update nav + dpo matrice card ([a3990b6](https://github.com/ApoSkunz/crabitan_bellevue/commit/a3990b6de0155fc980e2ad5f1534e5000f4cbfa8))
* **view:** add admin/logs view + fix .gitignore logs/ exception for src/View/admin/logs ([486fbd9](https://github.com/ApoSkunz/crabitan_bellevue/commit/486fbd90d41aa50b12dee422d8fbeb67b9460a71))
* **view:** admin/rgpd/index — add export log journal section, remove delete button ([1b33642](https://github.com/ApoSkunz/crabitan_bellevue/commit/1b336421de071c17d25240c5c2260266efdb5627))
* **view:** sélecteur per_page logs, super_admin shortcuts Mon Espace, retrait encart sidebar back-office ([fea3af6](https://github.com/ApoSkunz/crabitan_bellevue/commit/fea3af67c8b936fbbb101b7e8b14787258bd8a4f))

# [0.46.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.45.1...v0.46.0) (2026-04-07)


### Bug Fixes

* **auth:** _intended non vidé au logout + ANSSI /d/ → /\d/ + focus erreur register ([bcbbb05](https://github.com/ApoSkunz/crabitan_bellevue/commit/bcbbb05841640bf4294ece027189f4960e031011))
* **controller:** MajorityController — wrap Jwt::decode in try-catch for empty/invalid token ([e265d9b](https://github.com/ApoSkunz/crabitan_bellevue/commit/e265d9b0cbe152e7a4e1aaea0a720279c476f23d))
* **controller:** move majority guard before cart-empty check in checkout ([d424b16](https://github.com/ApoSkunz/crabitan_bellevue/commit/d424b16c1c50ee017857c4e8506741142a663b66))
* **i18n:** pending deletion flash — message now mentions the resent reactivation link ([b2bbb29](https://github.com/ApoSkunz/crabitan_bellevue/commit/b2bbb29cc378791580e625476880d9f8aef3d73c))
* **middleware:** guard admin paths with 404 in storeIntendedAndRedirect ([30d5fe0](https://github.com/ApoSkunz/crabitan_bellevue/commit/30d5fe0065cf0902e790dabba2e997539ffcf0aa))
* **seed:** add reactivation_token for soft-deleted account client.supprime@dev.local ([783013d](https://github.com/ApoSkunz/crabitan_bellevue/commit/783013d4a19a8286ad4d46906742b198948dbb81))
* **seed:** majority_declared_at — uniquement sur id 1-3, laisser NULL sur 5-6 pour tests ([c64502d](https://github.com/ApoSkunz/crabitan_bellevue/commit/c64502d12c2a8beecf85eb4fe98fa1496d25b46b))
* **seed:** majority_declared_at = created_at pour les comptes mock actifs ([12dea18](https://github.com/ApoSkunz/crabitan_bellevue/commit/12dea1843ed8a231fd5577f3e925e8a674780c14))
* **sonar:** head.php include→include_once (S2003) + MailService PATH_REACTIVER constant (S1192) ([2c81e2c](https://github.com/ApoSkunz/crabitan_bellevue/commit/2c81e2cb44cc044a81c9194ec643379a163432d0))
* **view:** register modal — label + bordure en rouge sur champs invalides (serveur) ([d41e715](https://github.com/ApoSkunz/crabitan_bellevue/commit/d41e71559d659270b5f846152ee762f2ce275cb0)), closes [#c0392b](https://github.com/ApoSkunz/crabitan_bellevue/issues/c0392b)


### Features

* **auth:** majority_pending au login/OAuth/checkout + redirect-post-login ([4430be3](https://github.com/ApoSkunz/crabitan_bellevue/commit/4430be300f68da752663eefdb1d0a9bb3fffc5b0))
* **auth:** majority_pending flag at login + reactivation email for soft-deleted accounts ([2259696](https://github.com/ApoSkunz/crabitan_bellevue/commit/2259696305f1906725c6f67893a7e06c6d0b05c8))
* **auth:** message de réactivation si connexion avec compte en cours de suppression ([d7fb6f1](https://github.com/ApoSkunz/crabitan_bellevue/commit/d7fb6f178e07af4521b15f9ae5008b995c7de32b))
* **controller:** MajorityController — déclaration légale de majorité (L3342-1 CSP) ([d2b7e86](https://github.com/ApoSkunz/crabitan_bellevue/commit/d2b7e867c3d7abcd97c4da4b66d5e1c5998558bf))
* **model:** AccountModel — declareMajority + hasDeclaredMajority ([a46e00c](https://github.com/ApoSkunz/crabitan_bellevue/commit/a46e00c6965d34b2b3e9bda871b704b33dcad08f))
* **security:** HSTS conditionnel + nonce CSP sur tous les scripts inline ([1dc3875](https://github.com/ApoSkunz/crabitan_bellevue/commit/1dc3875f87c92f12b75c5c23bea87854fcbc4ad9))
* **tests:** TU AuthMiddleware redirectToLogin + redirect-post-login + fix checkout ([18fd8a4](https://github.com/ApoSkunz/crabitan_bellevue/commit/18fd8a4f38e2396f27fc10af77d0db9772875891))
* **view:** majority-modal — composant overlay bilingue + hook head.php ([1053d5c](https://github.com/ApoSkunz/crabitan_bellevue/commit/1053d5cbcb8453a8f10b4c577661b4bcba2f36de))


### Reverts

* **security:** annuler HSTS + nonce CSP — reporté à l'audit final ([083658f](https://github.com/ApoSkunz/crabitan_bellevue/commit/083658f186c149938fb39f550e3f26807d27fd10))

## [0.45.1](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.45.0...v0.45.1) (2026-04-06)


### Bug Fixes

* **a11y:** autofocus sur les messages flash hors modal au chargement ([8926e7d](https://github.com/ApoSkunz/crabitan_bellevue/commit/8926e7db7e4549c4badfa4c615b7402153e28c54))
* **checkout:** afficher mention zone livraison avant sélection adresse (L111-1) ([be08fbb](https://github.com/ApoSkunz/crabitan_bellevue/commit/be08fbb10a3a2d68b1b4424b4b05739687a94b80))
* **contact:** supprimer section bon de commande de la page contact ([c71744f](https://github.com/ApoSkunz/crabitan_bellevue/commit/c71744f36ca2ce989c8631ede50b38f4ef571ad2))
* **newsletter:** inclure comptes company dans newsletter nouveau vin + historique ([398957e](https://github.com/ApoSkunz/crabitan_bellevue/commit/398957e6f9b53b076acab0d9488e09a348caf621))
* **panier:** bouton boutique invité — btn--gold + centrage ([cb83cd2](https://github.com/ApoSkunz/crabitan_bellevue/commit/cb83cd29dbce69218ea379822e14daca2bf5df80))
* **panier:** état vide iso PHP/JS après vidage — vin aléatoire + wording ([d013773](https://github.com/ApoSkunz/crabitan_bellevue/commit/d013773453fd653c38e3f11cd8ab00b7f5209d5d))
* **tests:** corriger TI WineAdmin company + warnings require_once legal.php ([c20a190](https://github.com/ApoSkunz/crabitan_bellevue/commit/c20a19059544fa4c705fae6881c5b4beeda15e80))

# [0.45.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.44.0...v0.45.0) (2026-04-06)


### Bug Fixes

* **age-gate:** exempter /fiche-technique du check global age gate ([e2c6456](https://github.com/ApoSkunz/crabitan_bellevue/commit/e2c6456fc8dc93f499b4fbce6b85e680fdd93ea3))
* **pdf:** 429 page visuelle + mention Loi Evin footer ([a2b3b2f](https://github.com/ApoSkunz/crabitan_bellevue/commit/a2b3b2ff8904acb1182e2c5de66a37469456fbcc))
* **sonar:** corriger 5 issues PR [#75](https://github.com/ApoSkunz/crabitan_bellevue/issues/75) ([70b3a5a](https://github.com/ApoSkunz/crabitan_bellevue/commit/70b3a5ababc46f9457d729a85e790bc1760151c2))
* **tests:** TI WineController — supprimer tests stripAlpha/buildPdfFields/convertPngToJpg ([15b14d2](https://github.com/ApoSkunz/crabitan_bellevue/commit/15b14d214673de2fc6238150320a70fe323b4e7f))


### Features

* **pdf:** age gate requis + 429 avec nom du vin et délai ([9283098](https://github.com/ApoSkunz/crabitan_bellevue/commit/9283098b0c1ad23fcddc11e4bcec6efd93be1c7a))
* **pdf:** rate limiting fiche technique PDF — 10 req/IP/vin/heure ([daee5bf](https://github.com/ApoSkunz/crabitan_bellevue/commit/daee5bfb0988e198e281241850e536b9b59debd3))
* **pdf:** refonte professionnelle fiche technique PDF (TCPDF) ([044b7bd](https://github.com/ApoSkunz/crabitan_bellevue/commit/044b7bdf6fdecef950fe60f63ea1cdb3f58a93cb))


### Reverts

* **pdf:** supprimer le check age gate sur la fiche technique ([0d7f3aa](https://github.com/ApoSkunz/crabitan_bellevue/commit/0d7f3aa8bab2ace05b7f164ca39520a2895dc534))

# [0.44.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.43.0...v0.44.0) (2026-04-06)


### Bug Fixes

* **age-gate:** overlay premier enfant de body (head.php) — zéro flash garanti, sans hack visibility ([e0e719f](https://github.com/ApoSkunz/crabitan_bellevue/commit/e0e719f26a3951ebb44ea3a3e99a8f22d6ed62c5))
* **age-gate:** QUITTER à droite de ENTRER + rectangle ghost outline ([885aca4](https://github.com/ApoSkunz/crabitan_bellevue/commit/885aca4d0a80f64c77002addea276faf02d68fee))
* **age-gate:** supprimer flash page — visibility hidden derrière overlay + fond opaque sans animation; UX 2 boutons + checkbox; cookie banner dans overlay ([197f027](https://github.com/ApoSkunz/crabitan_bellevue/commit/197f027500f6919371748d242289408e28b0f18e))
* **scss:** age-overlay — fond opaque var(--color-bg) + body:has overflow hidden ([4be8b0e](https://github.com/ApoSkunz/crabitan_bellevue/commit/4be8b0e30832314c6f6d883c44ab8efab7d56699))
* **test:** DpoAdminControllerTest — adapter assertion email à \$_ENV['CONTACT_OWNER_EMAIL'] ([30a09eb](https://github.com/ApoSkunz/crabitan_bellevue/commit/30a09eb0291804f7b0dabeeb0e0290aa33a8d317))
* **test:** OrderControllerPaymentFlowTest — fournir openssl.cnf XAMPP sur Windows pour openssl_pkey_new() ([e48f183](https://github.com/ApoSkunz/crabitan_bellevue/commit/e48f18379147b5dfd525fb47e1a9fb5cfbc464ab))


### Features

* **age-gate:** exit redirige vers referer externe si disponible, sinon Google ([53ec6cc](https://github.com/ApoSkunz/crabitan_bellevue/commit/53ec6cc962df4b9393994ac7b4255b3310eb3e9e))
* **controller:** AgeGateController — log IP+timestamp (Art. L3342-1), SameSite=Strict, TU log ([31eab5a](https://github.com/ApoSkunz/crabitan_bellevue/commit/31eab5a317c98a120c39f10ae7855f1865e216fa))
* **front-controller:** rétablir vérification age côté serveur — redirect /age-gate sauf crawlers SEO (Googlebot…) ([58c25f5](https://github.com/ApoSkunz/crabitan_bellevue/commit/58c25f59c42b6e951b3bf76c1ab095b919c18856))
* **scss:** age-gate overlay — styles .age-overlay (position fixed, z-index 9000, carte dorée) ([46f06a5](https://github.com/ApoSkunz/crabitan_bellevue/commit/46f06a5a7f354fa8382fcb1f64c95e62bcb716a4))
* **view:** age-gate overlay — partial HTML + injection header.php + i18n FR/EN ([a8761b9](https://github.com/ApoSkunz/crabitan_bellevue/commit/a8761b9345095500392f24c9101bb2ba73835e7f))

# [0.43.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.42.0...v0.43.0) (2026-04-06)


### Bug Fixes

* **model:** S2003 + duplication — extraire applyCuveeFilter(), require_once vues ([02051bc](https://github.com/ApoSkunz/crabitan_bellevue/commit/02051bcddfb5904c66f48625648549d39b6340b4))
* **scss:** filtre cuvée spéciale — étendre accent-color doré au checkbox ([29826b2](https://github.com/ApoSkunz/crabitan_bellevue/commit/29826b29c3126ee3cbb89d9bdf67c5cd5e31e100))
* **view:** cuvée spéciale — retirer l'étoile &[#11088](https://github.com/ApoSkunz/crabitan_bellevue/issues/11088); du label checkbox ([8dd2a6a](https://github.com/ApoSkunz/crabitan_bellevue/commit/8dd2a6ab7b7da1eb5295bdfa45670c32c459e8f5))
* **view:** S1192 + duplication — extraire checkbox cuvée spéciale en partial ([0a6ae0c](https://github.com/ApoSkunz/crabitan_bellevue/commit/0a6ae0c347606563cacf73521f979692a9c7f837))


### Features

* **model:** filtre cuvée spéciale sur getAll / countAll / getAllByColor / countAllByColor ([3d5d095](https://github.com/ApoSkunz/crabitan_bellevue/commit/3d5d09508683b1329ddac8856989192e119b6de6))
* **view:** filtre cuvée spéciale — toggle checkbox boutique + collection + i18n ([e8a50fd](https://github.com/ApoSkunz/crabitan_bellevue/commit/e8a50fd80e89246a68e1c3bfdac7b432ffea6ff0))

# [0.42.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.41.2...v0.42.0) (2026-04-06)


### Bug Fixes

* **controller:** DpoAdminController + MailService → CONTACT_OWNER_EMAIL env var ([49ccc46](https://github.com/ApoSkunz/crabitan_bellevue/commit/49ccc46fb5870740db33d24cc7d3574b1a7be075))
* **view:** emails hardcodés → CONTACT_OWNER_EMAIL (CGV, politique confidentialité, panier) ([7b1f66d](https://github.com/ApoSkunz/crabitan_bellevue/commit/7b1f66d6f021b9a0c63484be2ef5a485324ad655))
* **view:** S2003 — require → require_once pour config/legal.php (mentions légales + politique confidentialité) ([c6e86bc](https://github.com/ApoSkunz/crabitan_bellevue/commit/c6e86bc49d6db8887f62983f72847b3526a53092))


### Features

* **legal:** config/legal.php + version/date mentions légales et politique confidentialité ([a0e908e](https://github.com/ApoSkunz/crabitan_bellevue/commit/a0e908e7a1ceb3f8cdb1a05bc6e748e12777902b))

## [0.41.2](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.41.1...v0.41.2) (2026-04-06)


### Bug Fixes

* **js:** corriger S3358 (ternaires imbriqués) et S7748 (zéros superflus) ([492c04c](https://github.com/ApoSkunz/crabitan_bellevue/commit/492c04cfd9064935f755eb44cb20910cc94c5562))
* **php:** déplacer NOSONAR sur la ligne de l'issue (S3776, S1448, S4084) ([c905b11](https://github.com/ApoSkunz/crabitan_bellevue/commit/c905b11fc3e3d8b62c5b5bc4abf47f4f338752aa))
* **scss:** NOSONAR css:S7924 sur toutes les couleurs sémantiques (contraste intentionnel) ([a1e8d45](https://github.com/ApoSkunz/crabitan_bellevue/commit/a1e8d45cb694a9443794d67bcc3c835a870377b8))
* **view:** préciser NOSONAR S4084 — musique d'ambiance, pas de paroles ([ae93daa](https://github.com/ApoSkunz/crabitan_bellevue/commit/ae93daa9381b6d487a5754a6748f15071f9b9574))

## [0.41.1](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.41.0...v0.41.1) (2026-04-06)


### Bug Fixes

* **js:** corriger 3 issues Sonar PR[#70](https://github.com/ApoSkunz/crabitan_bellevue/issues/70) (S905, S6551, S7781) ([b81a2a9](https://github.com/ApoSkunz/crabitan_bellevue/commit/b81a2a9654e69dbf26499777eebab32c7e1a3c78))
* **mail:** extraire BTN_ANCHOR_STYLE_ATTR — éliminer S1192 (string dupliqué 3×) ([64439dd](https://github.com/ApoSkunz/crabitan_bellevue/commit/64439dd5f2ea80fe66e5aa098ceaea8d65e8a24d))
* **phpcs:** OrderAdminControllerTest — phpcs:ignore avant class, pas avant attribut ([8208aec](https://github.com/ApoSkunz/crabitan_bellevue/commit/8208aec86a328b2266194612e75db0c644cb8476))
* **sonar:** JS — globalThis, Number.parseInt/Float/isNaN, replaceAll, dataset, nested ternaries, NOSONAR hotspots jeux ([f9476ee](https://github.com/ApoSkunz/crabitan_bellevue/commit/f9476eeec46e29a1811fc535ce4cbd60155d85a6)), closes [String#replace](https://github.com/String/issues/replace)
* **sonar:** main.js — eslint-disable invalide sur group.offsetWidth ([1a7dc90](https://github.com/ApoSkunz/crabitan_bellevue/commit/1a7dc90e97a31cd9305e5f75def23e70ce679a8c))
* **sonar:** PHP — constantes dupliquées, complexité, vars inutilisées ([0ff1a2d](https://github.com/ApoSkunz/crabitan_bellevue/commit/0ff1a2d5bf7f14d7dee6621cf0cf88dfcc23ca1d))

# [0.41.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.40.0...v0.41.0) (2026-04-06)


### Bug Fixes

* **controller:** AuthController — budget email combiné IP (MAX=5/15min) partagé forgot+resend, erreur visible au blocage ([12b95a9](https://github.com/ApoSkunz/crabitan_bellevue/commit/12b95a9a896a9180b3f3b76ff9fc6fc3c16be7a4))
* **js:** main.js — window → globalThis dans initLoginModal (javascript:S7764) ([6ca2339](https://github.com/ApoSkunz/crabitan_bellevue/commit/6ca233971ef86bd9f7d61a05b3dc464d89727f69))
* **lint:** main.js — remplacer var par let/const (règle ESLint no-var, lignes 560-563) ([0d29b82](https://github.com/ApoSkunz/crabitan_bellevue/commit/0d29b8210da70cb1e4c6b1cc0aece0c8bf250d9a))
* **model:** AddressModel — résoudre le conflit de merge sur deleteForUser() et hasOrderSnapshot() ([9a8f5f0](https://github.com/ApoSkunz/crabitan_bellevue/commit/9a8f5f01e4caa74608792f17b725576b32292913))
* **model:** adresses — smart-delete : hard-delete sans snapshot, soft-delete avec deleted_at si snapshot ([509c1df](https://github.com/ApoSkunz/crabitan_bellevue/commit/509c1dffc951007a437f92a90c268f73de69b45d))
* **phpstan:** AddressModel — supprimer comparaison !== null toujours vraie (fetchOne retourne array|false) ([9dfc810](https://github.com/ApoSkunz/crabitan_bellevue/commit/9dfc81045c73ef39bb6e28d88d492d349ea6709a))
* **phpstan:** AddressModel — supprimer comparaison !== null toujours vraie (fetchOne retourne array|false) ([c5c5a5d](https://github.com/ApoSkunz/crabitan_bellevue/commit/c5c5a5ddbf3006eed4b194a40c44bb21c6e0a168))
* **rebase:** restaurer SessionTtlRefresher — version main avec refresh remember-me fin de vie ([d2e7d4f](https://github.com/ApoSkunz/crabitan_bellevue/commit/d2e7d4f3107bcd0ecdce12843453a32dc384eec0))
* **service:** MailService — désactiver SMTPAutoTLS pour compatibilité MailHog (dev) ([36ae4c2](https://github.com/ApoSkunz/crabitan_bellevue/commit/36ae4c2495029f64672fafaa49f98a1d15c29303))
* **service:** RateLimiterService — appliquer le TTL session au get() pour expirer les entrées périmées ([9770d5b](https://github.com/ApoSkunz/crabitan_bellevue/commit/9770d5b977f8547ed6aed9675db36ad3e9d5f5b1))
* **service:** RateLimiterService::get() — 4 returns → 3 (php:S1142) ([06e548c](https://github.com/ApoSkunz/crabitan_bellevue/commit/06e548cc1a9c176a855875522bbc3bd0c10447ac))
* **test:** corriger la pollution namespace APCu dans les TU CI ([77e748d](https://github.com/ApoSkunz/crabitan_bellevue/commit/77e748dbc95130bf8f81cdc85e28983c28fc0035))
* **view:** header.php — IDs dupliqués resend-activation-back-btn et forgot-back-btn (Web:S7930) ([cd21bfa](https://github.com/ApoSkunz/crabitan_bellevue/commit/cd21bfa4a98ec44919158e9d709029ee1f59fb00))


### Features

* **auth:** resendActivation — renvoi lien activation avec anti-énumération + rate limiting ([ce1730d](https://github.com/ApoSkunz/crabitan_bellevue/commit/ce1730d44aef1ba06ed061ccd9781c50f30ee128))
* **routes,i18n:** renvoyer-activation — route POST + clés fr/en ([97eb2c3](https://github.com/ApoSkunz/crabitan_bellevue/commit/97eb2c33f4822cb26ced7d1b28a763668474748d))
* **session:** refresh TTL automatique des sessions actives ([bc578cb](https://github.com/ApoSkunz/crabitan_bellevue/commit/bc578cb2f8e080d1a8a634180c2fa5a877502338))
* **view:** modal login — visibilité lien renvoi activation, flash succès 5s + bouton retour, double-panel fix ([c996c64](https://github.com/ApoSkunz/crabitan_bellevue/commit/c996c6489696e6b0cbf91a5b5940cf564bf08411))
* **view:** modale login — panel renvoi activation + lien + JS auto-open ([52a357d](https://github.com/ApoSkunz/crabitan_bellevue/commit/52a357d775bc3a3b16de5573e4bc2dc226758074))

# [0.40.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.39.0...v0.40.0) (2026-04-06)


### Bug Fixes

* **export-rgpd:** compte individuel → Prénom/Nom/Civilité, société → Raison sociale (exclusivité mutuelle) ([691581b](https://github.com/ApoSkunz/crabitan_bellevue/commit/691581b5b97d2647ed4ea9fdb7b0f04c43465682))
* **phpcs:** AccountControllerTest — espace après class + accolade fermante ([49957b8](https://github.com/ApoSkunz/crabitan_bellevue/commit/49957b825bfe7c7ccea0e00733998905468b36f4))
* **test:** rendre testExportSendMailerException déterministe + exclure resources/js coverage ([9b97468](https://github.com/ApoSkunz/crabitan_bellevue/commit/9b974682a8d90ef253cc018443b9e65e11858eb6))


### Features

* **export-rgpd:** adresses_commandes (snapshots RGPD Art.20 — conformité CNIL maximale) ([2791f8a](https://github.com/ApoSkunz/crabitan_bellevue/commit/2791f8a592e08262a8ed9754b9653defd8dbb96a))
* **export-rgpd:** complément export Art. 20 — logs connexion 90j, appareils MFA, consentements ([6fff357](https://github.com/ApoSkunz/crabitan_bellevue/commit/6fff3575a906015524b991521c71852264a56ce5))
* **export-rgpd:** livraison archive par email + rate limit 3/24h ([5c96741](https://github.com/ApoSkunz/crabitan_bellevue/commit/5c967412a5289dec66fe4b7a107cc67df7a9cbd4))
* **profile:** masquer l'email dans les inputs readonly (j***[@domain](https://github.com/domain).com) ([4828b08](https://github.com/ApoSkunz/crabitan_bellevue/commit/4828b08da61d9ee94dffc7c5cc498ce1cb381c01))

# [0.39.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.38.0...v0.39.0) (2026-04-05)


### Bug Fixes

* **db:** forcer timezone MySQL à UTC sur la connexion PDO ([0eecf5a](https://github.com/ApoSkunz/crabitan_bellevue/commit/0eecf5a8de3672544308650176c510e608a3fead))
* **session:** remember-me anti-coupure fin de vie — refresh à JWT_EXPIRY si remaining < JWT_EXPIRY × threshold ([79eaa10](https://github.com/ApoSkunz/crabitan_bellevue/commit/79eaa1022e63ca9ebc30098101f112a285225a59))
* **sonar:** SessionTtlRefresher — extraire needsRefresh() (php:S1142) + couvrir totalTtl<0, remember-me near-expiry, getRecentForUser ([2ef4f1b](https://github.com/ApoSkunz/crabitan_bellevue/commit/2ef4f1b7b53a668167acad34e5a67d69fafb7fee))


### Features

* **session:** refresh TTL automatique des sessions actives ([e24c6ae](https://github.com/ApoSkunz/crabitan_bellevue/commit/e24c6ae16df19758f2053700948aecf71f1d0f62))

# [0.38.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.37.3...v0.38.0) (2026-04-05)


### Features

* **cron:** bin/send_deletion_reminders.php — emails de rappel avant suppression compte ([d505ecc](https://github.com/ApoSkunz/crabitan_bellevue/commit/d505ecc3048e81a9214dc363aed4b2f71b6633cb))
* **mail:** MailService::sendDeletionReminder — rappel J-7/J-1 avant anonymisation ([ef82734](https://github.com/ApoSkunz/crabitan_bellevue/commit/ef82734aa2579a0dca6f08020956ea882ede37ce))
* **model:** AccountModel — getAccountsPendingDeletionReminder + markDeletionReminderSent ([e5311d1](https://github.com/ApoSkunz/crabitan_bellevue/commit/e5311d1b0f74301b063a623876820426a859958b))

## [0.37.3](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.37.2...v0.37.3) (2026-04-05)


### Bug Fixes

* **model:** adresses — smart-delete : hard-delete sans snapshot, soft-delete avec deleted_at si snapshot ([e9a73b0](https://github.com/ApoSkunz/crabitan_bellevue/commit/e9a73b011dab4c05b450414631011df643508e38))

## [0.37.2](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.37.1...v0.37.2) (2026-04-05)


### Bug Fixes

* **security:** normalisation timing forgot/register — anti timing oracle ([316b02e](https://github.com/ApoSkunz/crabitan_bellevue/commit/316b02e8b4ac1d0e319ac09a67a42814b979e521))

## [0.37.1](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.37.0...v0.37.1) (2026-04-05)


### Bug Fixes

* **security:** anti-énumération requestEmailChange — retour silencieux si email pris ([45a9958](https://github.com/ApoSkunz/crabitan_bellevue/commit/45a9958d98d6b822ed1ed0dcb173f06d91976155))
* **security:** anti-énumération UI — masquer pending/form immédiatement après soumission ([6d99e64](https://github.com/ApoSkunz/crabitan_bellevue/commit/6d99e64ef126ee43ed61acd724ba3ec12b07915d))
* **security:** anti-énumération UI — sauvegarder pending sans token si email déjà pris ([bf79108](https://github.com/ApoSkunz/crabitan_bellevue/commit/bf79108ae9b70377cb0c90e4c0d7e9ceb40d3006))
* **view,test:** profil — pending block identique email pris/libre + TU/TI mis à jour ([d4eac29](https://github.com/ApoSkunz/crabitan_bellevue/commit/d4eac29ccc793593bce4e480fa393fd1d854da04))
* **view:** profil — flash email change affiché avant le bloc pending (toujours visible) ([51e8870](https://github.com/ApoSkunz/crabitan_bellevue/commit/51e8870a780f9a5151d3bb1dc4c63ba36a9e6eb6))
* **view:** profil — flash email change affiché dans la section, pas en haut de page ([4db6921](https://github.com/ApoSkunz/crabitan_bellevue/commit/4db6921a1386a752b93ceb1aef941c5f39170f3b))
* **view:** profil — formulaire email change reste visible après soumission + flash inline ([b0a48eb](https://github.com/ApoSkunz/crabitan_bellevue/commit/b0a48eb3a71f75942f28fdd12d391dac3f05dabf))
* **view:** profil — masquer formulaire email après soumission + supprimer texte intro ([e95ba22](https://github.com/ApoSkunz/crabitan_bellevue/commit/e95ba221b2263123cb1f2286e41cdc2bc3caa743))
* **view:** profil — redirect vers #email-change après soumission (scroll auto sur la section) ([4e1097a](https://github.com/ApoSkunz/crabitan_bellevue/commit/4e1097aed5193385af661b98fef9b904a1f765cb)), closes [#email-change](https://github.com/ApoSkunz/crabitan_bellevue/issues/email-change)

# [0.37.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.36.0...v0.37.0) (2026-04-05)


### Features

* **security:** rate limiting changement MDP — 3/15min compte, 1/jour admin ([0526a4b](https://github.com/ApoSkunz/crabitan_bellevue/commit/0526a4b2eb6fe7eead97ab82264fcc5606f8d79b))

# [0.36.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.35.0...v0.36.0) (2026-04-05)


### Bug Fixes

* **coverage:** SessionApiController exit→HttpException + TU 204 + TI sessionExpired ([f23a2f2](https://github.com/ApoSkunz/crabitan_bellevue/commit/f23a2f2f9b99658d449d25d18b7e58b76d6b3c5e))
* **i18n:** NOSONAR php:S1192 sur les valeurs dupliquées dans les fichiers de traduction ([7831464](https://github.com/ApoSkunz/crabitan_bellevue/commit/78314648ad2cd9b85e411bbba1042a49915294f1))
* **security:** denySecurityAction — supprimer tous les appareils de confiance lors du refus ([9941bd2](https://github.com/ApoSkunz/crabitan_bellevue/commit/9941bd2ba5c43a479bf118d7ac37a783f2f14578))
* **sonar:** ignorer php:S1192 sur lang/** via multicriteria — supprimer NOSONAR inline ([e40c029](https://github.com/ApoSkunz/crabitan_bellevue/commit/e40c0291b48cb6ebf28350262dc8b0acb110afec))
* **ux:** harmoniser toutes les durées de flash à 5000ms ([da8c798](https://github.com/ApoSkunz/crabitan_bellevue/commit/da8c798d3169b56d3e026de20e5b0cff08c099e4))
* **ux:** toast par défaut 1500ms → 5000ms pour meilleure lisibilité ([61d3bda](https://github.com/ApoSkunz/crabitan_bellevue/commit/61d3bda52d80c3cc0fbd3fb4b9ba08b337c9b690))


### Features

* **api:** SessionApiController — GET /api/session/ping (204 actif / 401 révoqué + clear cookie) ([2f798c8](https://github.com/ApoSkunz/crabitan_bellevue/commit/2f798c89b3fe5bf19fb982699997c434d41be5cc))
* **controller:** AccountController — changePassword en mode pending, confirmSecurityAction applique le pending, denySecurityAction révoque ([39b1116](https://github.com/ApoSkunz/crabitan_bellevue/commit/39b1116fd5fdca93cdc072afb4d555f32798aec2))
* **controller:** AccountController — confirmSecurityAction + génération token dans changePassword ([0176e9f](https://github.com/ApoSkunz/crabitan_bellevue/commit/0176e9fcccfc7f4e59338b21ab16c32f9aa8eb21))
* **controller:** AccountController — confirmSecurityAction révoque uniquement la session de la demande + son trusted device ([53676c7](https://github.com/ApoSkunz/crabitan_bellevue/commit/53676c7408fcc161fafc7daee011d25b8071aa34))
* **controller:** AuthController — sessionExpired() pose flash + redirige accueil ([c7e19ba](https://github.com/ApoSkunz/crabitan_bellevue/commit/c7e19baf924d90bb95514ab9de4c5fa2cdb9ad8b))
* **i18n:** account — clés security_confirm_* (succès, erreur, expiré, boutons) ([f0ceb7a](https://github.com/ApoSkunz/crabitan_bellevue/commit/f0ceb7a23b76388946961746bbe6cd93430dd275))
* **i18n:** account — password_change_pending + clés security_deny_* (FR/EN) ([833c754](https://github.com/ApoSkunz/crabitan_bellevue/commit/833c7544a0dd1ea3cba181efa9f790e6379e1c33))
* **i18n:** ajouter common.close et auth.session_expired (fr + en) ([c846f81](https://github.com/ApoSkunz/crabitan_bellevue/commit/c846f8164da809332c4bc25f08b39ff11cfee746))
* **i18n:** auth — ajouter auth.session_expired (FR/EN) ([6e4b6f4](https://github.com/ApoSkunz/crabitan_bellevue/commit/6e4b6f4bd1b4edc5117214bfd4c6cd2c8d87fafb))
* **model:** AccountModel — savePendingPassword stocke session token, getPendingSessionToken, clear/apply effacent le token ([40dd2ce](https://github.com/ApoSkunz/crabitan_bellevue/commit/40dd2ce6304f5205e8a720dbf5da7dac05def1e7))
* **model:** AccountModel — savePendingPassword, applyPendingPassword, clearPendingPassword ([bb2fe68](https://github.com/ApoSkunz/crabitan_bellevue/commit/bb2fe6876b2116be7965b3c374b6e18f5379fdf7))
* **model:** AccountModel — saveSecurityConfirmToken, findBySecurityConfirmToken (UTC_TIMESTAMP), consumeSecurityConfirmToken ([1ec5aa8](https://github.com/ApoSkunz/crabitan_bellevue/commit/1ec5aa84ace3496fb4a91b30a91107be8ae3f49b))
* **model:** ConnectionModel — getDeviceTokenByToken pour révocation ciblée trusted device ([de7bfbf](https://github.com/ApoSkunz/crabitan_bellevue/commit/de7bfbf695f719a973cbd4a375c7dca6db7f688a))
* **routes:** ajouter GET /{lang}/securite/ce-netait-pas-moi → AccountController@denySecurityAction ([5709fab](https://github.com/ApoSkunz/crabitan_bellevue/commit/5709fab632d102a0bf71c5b22870d98308e2ae5c))
* **routes:** ajouter GET /{lang}/securite/confirmer → AccountController@confirmSecurityAction ([55af587](https://github.com/ApoSkunz/crabitan_bellevue/commit/55af587fc012c0d8596e2914cb66fc7ac704b91e))
* **routes:** ajouter GET /{lang}/session-expiree → AuthController@sessionExpired ([45e33c1](https://github.com/ApoSkunz/crabitan_bellevue/commit/45e33c1dc2b28a46f7c0c214e0b78663d95b6d02))
* **routes:** ajouter GET /api/session/ping → Api\SessionApiController@ping ([4fae88d](https://github.com/ApoSkunz/crabitan_bellevue/commit/4fae88d639e04e7a7ee65b7854079e84d15ccf5f))
* **scss:** bannière flash persistante cb-flash-banner avec bouton fermeture ([b2db4a9](https://github.com/ApoSkunz/crabitan_bellevue/commit/b2db4a9b281aa2ab73cc6421d776ae6d28caa476))
* **service:** MailService — anti-phishing footer + CTAs C'était moi / Ce n'était pas moi dans sendPasswordChangedAlert ([985def9](https://github.com/ApoSkunz/crabitan_bellevue/commit/985def9996e09969624e395d66a0babdfcfe8314))
* **service:** MailService — sendPasswordChangeConfirmation (remplace sendPasswordChangedAlert) + sendPasswordChangeDenied ([1dc4da6](https://github.com/ApoSkunz/crabitan_bellevue/commit/1dc4da641f3dc78739527606470660a6bc610eed))
* **ux:** flash session expirée → message permanent dans la modale connexion ([0e04604](https://github.com/ApoSkunz/crabitan_bellevue/commit/0e046042015239cf3ebb61b9e0351bb8bf8eda28))
* **view:** account/security-confirm — page résultat confirmation action sécurité (succès / erreur / expiré) ([06be804](https://github.com/ApoSkunz/crabitan_bellevue/commit/06be80479ac76889fef348534b819712754e101a))
* **view:** head.php — polling BroadcastChannel 30s auto-logout si session révoquée ([2491da7](https://github.com/ApoSkunz/crabitan_bellevue/commit/2491da7c1d14ef5783b1eacfa0ff8e60c2ef5f48))
* **view:** head.php — polling redirige vers session-expiree pour flash toast accueil ([3bb6af7](https://github.com/ApoSkunz/crabitan_bellevue/commit/3bb6af7c3b0c93417b509095d2c198ee08ffd757))
* **view:** remplacer flashInfo toast par bannière HTML persistante (cb-flash-banner) ([28385d7](https://github.com/ApoSkunz/crabitan_bellevue/commit/28385d7b72f5fe1eb3bf6ca47339aff171148da3))
* **view:** security-confirm redirect connexion après succès + nouvelle vue security-deny ([7cdd078](https://github.com/ApoSkunz/crabitan_bellevue/commit/7cdd0788c8eeee828e46d5aa86f4eb910bc73910))

# [0.35.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.34.0...v0.35.0) (2026-04-05)


### Bug Fixes

* **controller:** OrderAdminController — guard [@purged](https://github.com/purged).invalid sur envois email ([87544fd](https://github.com/ApoSkunz/crabitan_bellevue/commit/87544fd33024bb9125da4c5ef95400da033427d0))
* **phpcs:** OrderAdminControllerTest + OrderModelTest — alignement PSR-12 ([04c6438](https://github.com/ApoSkunz/crabitan_bellevue/commit/04c64382258a7be5d93c41c8d7b89dc0fbdd5bd4))


### Features

* **controller:** AccountController — lever le blocage suppression compte / commandes actives ([922bb62](https://github.com/ApoSkunz/crabitan_bellevue/commit/922bb62c1a520949dc930b12acf0f02f733c4219))
* **i18n:** account — remplacer delete_blocked_orders par delete_active_orders_warning ([94c70d0](https://github.com/ApoSkunz/crabitan_bellevue/commit/94c70d0cd240b0dc195d7fcf7f1e7f0027a9c5b8))
* **model:** OrderModel — ajouter countActiveOrdersForUser() ([e833668](https://github.com/ApoSkunz/crabitan_bellevue/commit/e833668d95daa871f6ef731f449036692e5fb4b7))
* **view:** account/security — avertissement modal si commandes actives en cours ([6cbc869](https://github.com/ApoSkunz/crabitan_bellevue/commit/6cbc86991642b011568fd122673091bc9304281c))

# [0.34.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.33.0...v0.34.0) (2026-04-05)


### Bug Fixes

* **sonar:** InvoiceController — exit → HttpException(200), NOSONAR php:S3973 ([4facf53](https://github.com/ApoSkunz/crabitan_bellevue/commit/4facf5309c21811c819ef1aab331810fbfd72bce))
* **view:** admin/invoices — 1 ligne = 1 PDF, colonne Valide (ROW_NUMBER), bouton Voir PDF ; Factures déplacée après Commandes dans la nav admin ([e4f4875](https://github.com/ApoSkunz/crabitan_bellevue/commit/e4f4875f50696a88ec1a768c96a4878ee873804e))
* **view:** admin/invoices — filtres en ligne (flat admin-filters), Voir PDF target=_blank ; account/invoices — table avec account-table-wrap + btn--ghost ([676f467](https://github.com/ApoSkunz/crabitan_bellevue/commit/676f467353417f1297a5727424821f03bec58489))
* **view:** header panel Mon Espace — lien Factures après Commandes (admin + client) ([62f6c60](https://github.com/ApoSkunz/crabitan_bellevue/commit/62f6c60fb16d9b3acbd74ff05e279d8cbe5acff3))


### Features

* **account:** page Mes factures — GET /{lang}/mon-compte/factures + nav espace client + i18n fr/en ([532999c](https://github.com/ApoSkunz/crabitan_bellevue/commit/532999cd24eb9d430666b81e9e718069663f61ad))
* **controller:** InvoiceAdminController — index (liste par période) + export ZIP PDFs + CSV (L123-22 C.com.) ([a222b3c](https://github.com/ApoSkunz/crabitan_bellevue/commit/a222b3c3c6367459c7bc798b015e0de1f4c1f7c1))
* **model:** méthodes getInvoicesForAdmin, countInvoicesForAdmin, getInvoiceFilesForExport, getAvailableYearsWithInvoices + buildInvoicePeriodFilter (L441-9 + L123-22 C.com.) ([ae9a49c](https://github.com/ApoSkunz/crabitan_bellevue/commit/ae9a49cedb66bb600c7c0f99a9ee8288fdd80e21))
* **routes:** GET /admin/factures + GET /admin/factures/export ([47d6762](https://github.com/ApoSkunz/crabitan_bellevue/commit/47d6762946f660f10d772ac5910f8ce38518559d))
* **view:** admin/invoices/index — liste factures + filtre période + export ZIP + entrée nav Factures ([2ecf365](https://github.com/ApoSkunz/crabitan_bellevue/commit/2ecf3650ce09edd0d2a7e553ee6ce31ca2517c5d))

# [0.33.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.32.0...v0.33.0) (2026-04-05)


### Features

* **invoice:** mention Loi Évin (Art. L3323-4 CSP) en pied de facture si commande contient des vins ([f5ebb1b](https://github.com/ApoSkunz/crabitan_bellevue/commit/f5ebb1bb728b78feee5b2eae34fe922965b093a0))
* **invoice:** titre FACTURE — 28pt bold → 15pt serif élégant + sous-titre château en lettres espacées ([e359ba4](https://github.com/ApoSkunz/crabitan_bellevue/commit/e359ba457ed145b575102001020102bb404718a1))

# [0.32.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.31.0...v0.32.0) (2026-04-05)


### Features

* **controller:** OrderAdminController — filtre archived, guard confirm_replace, historique factures (addInvoiceFile + downloadInvoiceFile) ([0fa8e46](https://github.com/ApoSkunz/crabitan_bellevue/commit/0fa8e4626c897dd7316f3b37b4c6e4da3c506dae))
* **model:** OrderModel — pseudonymizeExpired, addInvoiceFile, getInvoiceFiles + filtre archived sur getForAdmin ([a5ed830](https://github.com/ApoSkunz/crabitan_bellevue/commit/a5ed8303b0d510f6b5b819ad4d174bdc1df50212))
* **view:** admin/orders — toggle archivées + notice RGPD, historique factures dans show ([8ffa61b](https://github.com/ApoSkunz/crabitan_bellevue/commit/8ffa61b0c91b9219f3518f93ec0953f9d271d533))

# [0.31.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.30.0...v0.31.0) (2026-04-05)


### Bug Fixes

* **seed:** remplacer les dates hardcodées passées par des expressions relatives NOW() ([0a804c9](https://github.com/ApoSkunz/crabitan_bellevue/commit/0a804c9882a73e942ed6ee1fd3f41ea72f0a9589))
* **sonar:** OrderModel — extraire fetchAddressById + NOSONAR php:S1172 sur stubs ([a05361e](https://github.com/ApoSkunz/crabitan_bellevue/commit/a05361e17411a2343509a980ffc813b69af8f66e))
* **test:** OrderControllerPaymentFlowTest — +4 fetchOne pour createFromIpn snapshot addresses ([b75a5d1](https://github.com/ApoSkunz/crabitan_bellevue/commit/b75a5d14d12791b5ce82110b30ff24b93bd98742))


### Features

* **model:** OrderAddressSnapshotModel — déduplication find-or-create par source_address_id ([d54b611](https://github.com/ApoSkunz/crabitan_bellevue/commit/d54b6114255a92eff2e0387127b45497666e6bb6))
* **model:** OrderAddressSnapshotModel — snapshot immuable adresse commande, indépendant accounts/addresses (RGPD L123-22) ([470ca4b](https://github.com/ApoSkunz/crabitan_bellevue/commit/470ca4b653c420e7aab446a9e1cbf9fb41fd3185))
* **model:** OrderModel — create/createFromIpn snapshotent les adresses + toutes les JOINs migrent vers order_address_snapshots ([00fcce7](https://github.com/ApoSkunz/crabitan_bellevue/commit/00fcce772807b051e0b0b4be6cd6193a19d1f2a0))

# [0.30.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.29.0...v0.30.0) (2026-04-05)


### Bug Fixes

* **controller:** pièce jointe facture — realpath() sur le chemin PDF + déclenchement envoi email ([34f9e17](https://github.com/ApoSkunz/crabitan_bellevue/commit/34f9e179134e7393e5b0abbac03caa8f7d37ba60))
* **invoice:** adresse vendeur — "au Crabitan" → "1 Crabitan" ([c8fac43](https://github.com/ApoSkunz/crabitan_bellevue/commit/c8fac43f6e28924db1776642bef8a7136eacb5bf))
* **invoice:** aligner chemin storage/invoices + facture virement/chèque déclenchée par admin au passage à paid ([adcc6e4](https://github.com/ApoSkunz/crabitan_bellevue/commit/adcc6e434e50afc52733bd83e1442b9d7d9e7f13))
* **model:** findByReferenceWithAddresses — JOIN accounts/account_individuals pour récupérer email et lang ([2b75f39](https://github.com/ApoSkunz/crabitan_bellevue/commit/2b75f3900a7d1874dcf3211761f99f2f9aa8d6d3))
* **quality:** php:S107 trop de paramètres → NOSONAR justifié sur MailService + OrderController ([8ea5c17](https://github.com/ApoSkunz/crabitan_bellevue/commit/8ea5c1764003b30d827a4c9a6c6f053cde8e4aee))
* **scss:** order-discount-row — couleur var(--color-gold) au lieu de brun [#3](https://github.com/ApoSkunz/crabitan_bellevue/issues/3)d2e04 ([e1eaa18](https://github.com/ApoSkunz/crabitan_bellevue/commit/e1eaa180d9b96a2c7d9846761abb64c3cafc0338)), closes [#3d2e04](https://github.com/ApoSkunz/crabitan_bellevue/issues/3d2e04)
* **test:** OrderControllerPaymentFlowTest — corriger ordre fetchOne [#5](https://github.com/ApoSkunz/crabitan_bellevue/issues/5)/[#6](https://github.com/ApoSkunz/crabitan_bellevue/issues/6) (findByReferenceWithAddresses avant findByReference line 420) ([1b7930a](https://github.com/ApoSkunz/crabitan_bellevue/commit/1b7930ad3c8d493997fb737ea2d36162f2a10993))
* **view:** admin/orders/show — Web:S5256 table headers manquants → <th scope="row"> ([6c5f948](https://github.com/ApoSkunz/crabitan_bellevue/commit/6c5f948943e7636407f8d245d8b778ea5bdb7b3f))


### Features

* **controller:** IpnController + OrderController — génération et stockage facture PDF après paiement (CA IPN, fallback return, virement/chèque) ([20364ad](https://github.com/ApoSkunz/crabitan_bellevue/commit/20364ade565065dd64487bbaf45e49d8e292b22d))
* **i18n:** clés annulation commande (pending CB, pending virement, paid CB) + colonne Produit ([95ac2ee](https://github.com/ApoSkunz/crabitan_bellevue/commit/95ac2ee0e8c37dae0918f89b1e96080399c5ffa5))
* **invoice:** refonte PDF professionnel — logo, SIRET/RCS/TVA, layout vendeur/client, couleurs dark+gold ([de43ad6](https://github.com/ApoSkunz/crabitan_bellevue/commit/de43ad61b93dc2ee83a4e81b25c613c1050a5e18)), closes [#2c1a0e](https://github.com/ApoSkunz/crabitan_bellevue/issues/2c1a0e)
* **mail:** buildOrderItemsTable — ligne remise livraison dans emails confirmation client et propriétaire ([30b765d](https://github.com/ApoSkunz/crabitan_bellevue/commit/30b765dd25bc72e2f29dd35e3bfda51cf18c0d9d))
* **mail:** sendInvoiceToClient — envoi facture PDF en pièce jointe après génération ([572f406](https://github.com/ApoSkunz/crabitan_bellevue/commit/572f406a1677ecbb0a757b86685127935c0331ce))
* **service:** InvoiceService — génération facture TCPDF conforme L441-9 (vendeur, client, articles, remise livraison, TVA 20%, total) ([30cc349](https://github.com/ApoSkunz/crabitan_bellevue/commit/30cc349f79de2e69af99db2c075a963977169ce5))
* **view:** admin order show — alerte statut rétrograde (⚠ + pop-in rouge) + détail prix commande ([a6b57ab](https://github.com/ApoSkunz/crabitan_bellevue/commit/a6b57abab3694920c3fa7a06f7600f8b6461b4e1))
* **view:** order_detail client — détail prix, colonne Produit, messages annulation contextuels ([9a5a113](https://github.com/ApoSkunz/crabitan_bellevue/commit/9a5a1138abc4c111c4e37174c70b613057134681))

# [0.29.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.28.0...v0.29.0) (2026-04-05)


### Bug Fixes

* **auth:** indicateur force MDP — détection suites logiques + mots courants (leet) ([b01bee0](https://github.com/ApoSkunz/crabitan_bellevue/commit/b01bee094e055c98a84ba25b1b20df2c59239c4b))
* **auth:** indicateur force MDP — pénalité séquence override score zxcvbn ([f6d90d1](https://github.com/ApoSkunz/crabitan_bellevue/commit/f6d90d197d364e9ed710d14f51710b389220b859))
* **auth:** indicateur force MDP — position + scoring + très fort ([02e78df](https://github.com/ApoSkunz/crabitan_bellevue/commit/02e78df82d9c8196dce58fb617227b1206be93e6))
* **auth:** indicateur force MDP — position form-pwd-wrap + niveau Très fort longueur ([1babc7a](https://github.com/ApoSkunz/crabitan_bellevue/commit/1babc7ae035270503c7d77a714308349458c2d4c))
* **tests:** AuthController — remplacer mots de passe bloqués par blocklist PasswordValidator ([215b858](https://github.com/ApoSkunz/crabitan_bellevue/commit/215b858debedab0a8f4a9e72e16e57bd092c267e)), closes [Vigilant7#xKqm](https://github.com/Vigilant7/issues/xKqm) [vigilant7#xkqmz](https://github.com/vigilant7/issues/xkqmz)


### Features

* **auth:** blocklist mots courants + séquences dans PasswordValidator (backend) ([de10e10](https://github.com/ApoSkunz/crabitan_bellevue/commit/de10e106b01bb8c613e2ee5e4d744a7c80d6a3ce))
* **auth:** indicateur force MDP — zxcvbn-ts (score 0→4, lazy load) ([943ab54](https://github.com/ApoSkunz/crabitan_bellevue/commit/943ab5496da4060cd2aa7840554730c497a2e6d1))
* **auth:** indicateur force MDP (faible/moyen/fort) + cron purge sessions expirées ([d3fe753](https://github.com/ApoSkunz/crabitan_bellevue/commit/d3fe75399e5e0eb4812141e082abc409df9748f4))


### Reverts

* **auth:** remplacer zxcvbn-ts par implémentation maison miroir PHP ([874cfb8](https://github.com/ApoSkunz/crabitan_bellevue/commit/874cfb8173295919c4299e0a5696cf727ca1e575))

# [0.28.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.27.0...v0.28.0) (2026-04-05)


### Bug Fixes

* **account:** utiliser callGaeCancelOrRefund pour la rétractation client ([20c3f40](https://github.com/ApoSkunz/crabitan_bellevue/commit/20c3f40cfb4bde980bd3f7d5ded93c2acc9a0fe6))
* **controller:** paymentReturnOk — fallback création commande si IPN absent ([d8cd279](https://github.com/ApoSkunz/crabitan_bellevue/commit/d8cd27916fdc5945f2632159164d961d6324c2d3))
* **controller:** sanitize Ref param, NOSONAR S3776/S1172, flash stale orderDetail ([bd6d9a0](https://github.com/ApoSkunz/crabitan_bellevue/commit/bd6d9a066e0447b7579be7746cd71d11dbb8849d))
* **ipn:** corriger isolation session PHP — snapshot en BDD via payment_intents ([5da313b](https://github.com/ApoSkunz/crabitan_bellevue/commit/5da313be6effd56e9187e89cca7f24e89b0e8c75))
* **order:** RSA fallback retour CA, flash stale, nettoyage payment_intents annule/refuse ([ad295ca](https://github.com/ApoSkunz/crabitan_bellevue/commit/ad295ca6a32a1e09d360d141994af9b4befb93c7))
* **payment:** constantes GAE, NOSONAR S107/S1142/S112/weak-cryptography ([d2e9537](https://github.com/ApoSkunz/crabitan_bellevue/commit/d2e9537cb90d7b24fa33b5fd74a0718bf4616419))
* **phpcs:** PaymentService ligne 356 — NOSONAR S112 raccourci sous 150 chars ([5d73a61](https://github.com/ApoSkunz/crabitan_bellevue/commit/5d73a61f906e8248f29b1eae270e59d2fad21892))
* **phpcs:** raccourcir les commentaires NOSONAR dépassant 150 caractères ([48d4628](https://github.com/ApoSkunz/crabitan_bellevue/commit/48d4628560d57a58cb3c80096c76e4681084743d))
* **phpstan:** retirer [@phpstan-ignore-line](https://github.com/phpstan-ignore-line) obsolète sur OrderModel::createFromIpn ([824832b](https://github.com/ApoSkunz/crabitan_bellevue/commit/824832bef81ede9aacfc4c6dde8abbd4d8898234))
* **service:** PaymentService — isSandbox() compatible parse_ini_file ([0ae3ace](https://github.com/ApoSkunz/crabitan_bellevue/commit/0ae3ace426693e6dd5e4bc7557acb9b50d99d24e))
* **service:** PaymentService — PBX_LANGUE code ISO 3 lettres (FRA/GBR) ([ec708ad](https://github.com/ApoSkunz/crabitan_bellevue/commit/ec708adde5d71efb87d697bf30bf2b9f5cc31fbe))
* **tests:** corriger 3 TI/TU cassés après ajout de couverture ([ba6412f](https://github.com/ApoSkunz/crabitan_bellevue/commit/ba6412fad418042decada2701e090a293f4af973))
* **tests:** Router CURRENT_LANG guard + CartController fetchOne stub exhausted ([41800e0](https://github.com/ApoSkunz/crabitan_bellevue/commit/41800e0b335776effeba63630e6e2f82bb912281))
* **view:** formatCivility() — afficher Mme/M au lieu du code brut F/M ([b94d9e6](https://github.com/ApoSkunz/crabitan_bellevue/commit/b94d9e6bc0556da51ec1ae8b6664822236332c45))
* **view:** paiement — images + bouton annulation remboursement carte ([1e0281a](https://github.com/ApoSkunz/crabitan_bellevue/commit/1e0281a3928a41686eefddb2e6d74ae623d5a895))


### Features

* **admin:** remboursement/annulation GAE automatique sur passage statut refunded ([b48e46e](https://github.com/ApoSkunz/crabitan_bellevue/commit/b48e46edec7e816683d73dfc067db778480ae010))
* **controller:** AccountController — cancelOrder déclenche remboursement GAE CA pour commandes card+paid ([01a378b](https://github.com/ApoSkunz/crabitan_bellevue/commit/01a378bed64851e3699674e5a1b207601bc464ea))
* **controller:** IpnController — réception IPN CA, vérification RSA, création commande ([01c1b9b](https://github.com/ApoSkunz/crabitan_bellevue/commit/01c1b9bffc6bbc8d330da21cc8b3cac7690e38a5))
* **controller:** OrderController — flux carte CA (session snapshot + return handlers + rate limiting) ([73db26a](https://github.com/ApoSkunz/crabitan_bellevue/commit/73db26a9710b06d09173633ecf60b1b4348b2268))
* **i18n:** fr + en — clés paiement CA (rate limit, annulation, refus, redirection) ([0ee98df](https://github.com/ApoSkunz/crabitan_bellevue/commit/0ee98df18c4c746e1eb6a07a252257dba09ab6da))
* **model:** OrderModel — createFromIpn + findByReferenceOnly pour flux IPN CA ([a9dc38d](https://github.com/ApoSkunz/crabitan_bellevue/commit/a9dc38d2b872d0748e811443456e45277cae45a4))
* **payment:** URLs PBX dynamiques, 3DS > 200€, fix pubkey path, annulation/remboursement adaptatif ([091b08a](https://github.com/ApoSkunz/crabitan_bellevue/commit/091b08afc8ffd1c471b9b0d3d713a25e7b6b5a9e))
* **routes:** ajouter routes retours CA (ok/annule/refuse) + IPN endpoint ([0feba8f](https://github.com/ApoSkunz/crabitan_bellevue/commit/0feba8ff2615d2537efc37bec84800286a629030))
* **service:** MailService + IpnController — alerte email rotation clé CA ([9ec527b](https://github.com/ApoSkunz/crabitan_bellevue/commit/9ec527b89ed1775e8c918594240f438b2bdd33ec))
* **service:** PaymentService — initiation paiement CA, vérification IPN RSA, remboursement GAE ([21cd265](https://github.com/ApoSkunz/crabitan_bellevue/commit/21cd26594d1aed8a350c5f9dfc8813306aee730a))
* **view:** checkout — logos CB/Visa/Mastercard sur option carte ([d82b433](https://github.com/ApoSkunz/crabitan_bellevue/commit/d82b433810bd75e1488a42333cba3ed9450116e9))
* **view:** payment-form — formulaire auto-submit POST vers CA e-Transactions ([028ec8f](https://github.com/ApoSkunz/crabitan_bellevue/commit/028ec8f49f99c26ea71e7a9cb65ba3d1d5cdcc17))
* **view:** payment-return-ok — page attente confirmation commande ([123343a](https://github.com/ApoSkunz/crabitan_bellevue/commit/123343afe660cafdc091a986bb00077bd4243008))

# [0.27.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.26.0...v0.27.0) (2026-04-04)


### Bug Fixes

* **cart:** multiple de 12 sur page panier ; vin aléatoire invité + auth ; wording "Voir notre boutique" ([0c34d30](https://github.com/ApoSkunz/crabitan_bellevue/commit/0c34d309b567c11cae30ea7f2ba0ed5b8ed767cd))
* **checkout:** same_address + notice shake + badge cuvée panier ([058ad75](https://github.com/ApoSkunz/crabitan_bellevue/commit/058ad752032504d1dbf488a820b5ec0aa0ce4a2e))
* **controller:** checkout — newsletter email bienvenue + isNewsletterSubscribed + validation ZIP livraison serveur ([c3d4301](https://github.com/ApoSkunz/crabitan_bellevue/commit/c3d43016452ce8241bd31b56c287517f15571ece))
* **controller:** checkout — validation multiple de 12 ; CartController — vin aléatoire panier vide ; WineAdmin — purge sur available=0 uniquement ([ac680ad](https://github.com/ApoSkunz/crabitan_bellevue/commit/ac680ad64d97eb599f373dea0066e6eb91d5f31b))
* **i18n:** checkout — clé error_multiple_12 (fr+en) ; corriger bénéficiaire chèque → G.F.A Bernard Solane & Fils ([7a5e93c](https://github.com/ApoSkunz/crabitan_bellevue/commit/7a5e93c29a9088385b87553e0d4e808cdb78cc20))
* **i18n:** checkout — supprimer astérisques CGV (case obligatoire, redondant) ([4ed64d8](https://github.com/ApoSkunz/crabitan_bellevue/commit/4ed64d8e42cb90d57d1a71bfa5c536dcb54b8c48))
* **model:** CartModel — retirer le check quantity=0 (production ≠ stock) ; WineModel — getRandomForCart() ([d8c28c5](https://github.com/ApoSkunz/crabitan_bellevue/commit/d8c28c53e108a5f32f472ce175e0f6ee5bd62d52))
* **model:** wine — constante COND_IMAGE pour déduplication littéral SQL (Sonar php:S1192) ([a007dd9](https://github.com/ApoSkunz/crabitan_bellevue/commit/a007dd98445d0e5df846b5d40acce320c8ad15a4))
* **model:** wine — constantes SQL_RAND_LIMIT + SQL_AND pour déduplication littéraux ORDER BY/AND (Sonar php:S1192) ([e5567aa](https://github.com/ApoSkunz/crabitan_bellevue/commit/e5567aaefb8ec02f2adb59852a49e76ddd15bbce))
* **scss:** admin — .admin-logout-form reset ; cart — .cart-empty-wine card ; checkout — .checkout-summary__error + .confirmation-card__redirect ([06da6e4](https://github.com/ApoSkunz/crabitan_bellevue/commit/06da6e4cbe6f77382fe4948923ef93c53b002114))
* **service:** mail — mention droit rétractation email + suppression param inutilisé + fix Sonar (ternaires, complexité) ([b82e5e0](https://github.com/ApoSkunz/crabitan_bellevue/commit/b82e5e00172022b3ca6d4a3f596681a7c1cfa7a2))
* **test:** CartModelCheckoutTest — quantity=0 ne retire plus le vin (production ≠ stock) ([1564e7a](https://github.com/ApoSkunz/crabitan_bellevue/commit/1564e7ae29246dbeeb9755ea44eea3b3556896e2))
* **view:** admin — déconnexion POST (405) ; cart — vin aléatoire état vide ; checkout — adresses toutes visibles, same_address, bénéficiaire GFA, multiple 12 JS ; confirmation — redirect 15s, bénéficiaire GFA ([b0d898a](https://github.com/ApoSkunz/crabitan_bellevue/commit/b0d898a3d141c76c0f88c8155a9ca1819959861e))
* **view:** checkout — livraison en premier, bénéficiaire virement/chèque, adresse correcte, prix unitaire, validation ZIP France métro, suppression JS multiple12 ; confirmation — adresse chèque ([b4ffb2f](https://github.com/ApoSkunz/crabitan_bellevue/commit/b4ffb2f9b7a17c1f26d6434a81a7e1865f962eba))
* **view:** checkout — newsletter masquée si déjà abonné, billing ZIP sans restriction France métro ([e509dd3](https://github.com/ApoSkunz/crabitan_bellevue/commit/e509dd37389181cb26c4b25187c643757eda9ed3))


### Features

* **checkout:** tunnel commande — rounds 2 & 3 ([46fdf90](https://github.com/ApoSkunz/crabitan_bellevue/commit/46fdf9086a7d850a8dc6ae9fdebd89e1b45f7b6f))
* **controller:** order — droit rétractation + token anti double-submit + fix Sonar (complexity, require_once, ternaires, returns) ([c3d7d7a](https://github.com/ApoSkunz/crabitan_bellevue/commit/c3d7d7a442d4e0c1d271e344ef9d73e54672097d))
* **controller:** OrderController + hook purge panier WineAdminController ([84fe97f](https://github.com/ApoSkunz/crabitan_bellevue/commit/84fe97f48addcfc17e310ada0f70fb4fd7b2028b))
* **i18n:** checkout — clés fr.php + en.php (50 clés) ([090e80c](https://github.com/ApoSkunz/crabitan_bellevue/commit/090e80caa38ef222b07682052ce4ff72723b67b6))
* **i18n:** checkout — droit de rétractation L221-18 + token soumission (clés withdrawal_right, submit_token) ([d659141](https://github.com/ApoSkunz/crabitan_bellevue/commit/d65914138daba3f14240b6ca4940120705f07438))
* **mail:** email récapitulatif commande — client + propriétaire (sendOrderConfirmationToClient/Owner) ([66a01fd](https://github.com/ApoSkunz/crabitan_bellevue/commit/66a01fd57de3bd234cabc470c10d23bf3cc4d101))
* **model:** checkout — CartModel + OrderModel + AddressModel ([25b6a60](https://github.com/ApoSkunz/crabitan_bellevue/commit/25b6a601bf11fa9c443c15bca700640f9dc877e4))
* **model:** OrderModel — référence WEB-{CB|VB|CHQ}-{RANDOM}-{YEAR} ([6389e0e](https://github.com/ApoSkunz/crabitan_bellevue/commit/6389e0e6578426c15041f6bd94888dc7380c8cd8))
* **scss:** layout/_checkout.scss — styles page checkout + confirmation ([6886ce9](https://github.com/ApoSkunz/crabitan_bellevue/commit/6886ce93eae212b170995e6a7e3da101d0216a40))
* **service:** mail — sendNewsletterWelcome pour inscription via checkout (client authentifié) ([e0decab](https://github.com/ApoSkunz/crabitan_bellevue/commit/e0decab415cac946c8a2b76f45246bb8d8c66501))
* **view:** checkout — droit rétractation + token anti double-submit + fix ternaires imbriqués (Sonar) ([2222ded](https://github.com/ApoSkunz/crabitan_bellevue/commit/2222ded560ddb555198fa7a2f01edcb31130a639))
* **view:** confirmation — mode paiement affiché pour tous modes + btn--gold sur "Continuer mes achats" ([e80cce9](https://github.com/ApoSkunz/crabitan_bellevue/commit/e80cce9186001a18481abc6584384497647d2e59))
* **view:** order — checkout.php + confirmation.php ([b2a8ef3](https://github.com/ApoSkunz/crabitan_bellevue/commit/b2a8ef3d497e8f7ab083956a6a0489002226b5ab))

# [0.26.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.25.0...v0.26.0) (2026-04-04)


### Bug Fixes

* **auth:** logout sauvegarde panier BDD → cookie cb-cart + clear setcookie secure=false ([e2e905b](https://github.com/ApoSkunz/crabitan_bellevue/commit/e2e905b32ded4bc9d29378ae64bcd6b7fcb76e35))
* **controller:** CartApiController — constante INVALID_WINE_ID pour littéral dupliqué 3× (SonarCloud S1192) ([00e34f8](https://github.com/ApoSkunz/crabitan_bellevue/commit/00e34f89033a661192adf51c523167d5e57579e0))
* **controller:** CartController::denyAdmin — déplacer redirect hors du try/catch pour éviter que HttpException soit absorbée silencieusement ([79936c3](https://github.com/ApoSkunz/crabitan_bellevue/commit/79936c3bb017bdbb71ce2bf7cf7a58b24315e4c8))
* **model:** CartModel::mergeLocalCart — accepte "id" (JS cookie) et "wine_id" (BDD) ([733f489](https://github.com/ApoSkunz/crabitan_bellevue/commit/733f4892c0dbba85125c540a6e24054bdc360273))
* **panier:** badge sync + CTA modal + suppression cookie connecté ([829e0f3](https://github.com/ApoSkunz/crabitan_bellevue/commit/829e0f3df4d1647c8deffc5bcc7137b70c88972d)), closes [#login-modal-trigger](https://github.com/ApoSkunz/crabitan_bellevue/issues/login-modal-trigger)
* **phpcs:** lang/fr.php + lang/en.php — phpcs:disable LineLength sur fichiers de traduction ([78a3cbc](https://github.com/ApoSkunz/crabitan_bellevue/commit/78a3cbce0d15244d19bdad3cad29e7f7cdf89095))
* **phpcs:** PricingRuleModelTest — couper les lignes de fixtures >150 caractères ([1f6c0d9](https://github.com/ApoSkunz/crabitan_bellevue/commit/1f6c0d9c517be8ac8744ca202d10578c7e80d9e9))
* **scss:** panier — réduire espace page-hero → cart-section (padding-bottom 3rem→1rem, padding-top 3rem→1.25rem) ([28a5cbd](https://github.com/ApoSkunz/crabitan_bellevue/commit/28a5cbd3c30c3798d4eeb8eaf434106e445b320d))
* **seed:** DELETE → TRUNCATE pour reset AUTO_INCREMENT + carts sans price/withdrawal/delivery ([cf1153e](https://github.com/ApoSkunz/crabitan_bellevue/commit/cf1153e02a5d729af9134fb45893373b66e5561e))
* **view:** cart/index — extraire ternaires imbriqués en variables ($tierLabel, $bottleLabel) (SonarCloud S3358) ([ade3596](https://github.com/ApoSkunz/crabitan_bellevue/commit/ade3596a08cc00e4ffd1f33873540ece14701c3c))
* **view:** panier — wording progress bar max tier : "Palier remise livraison maximum atteint" ([644738a](https://github.com/ApoSkunz/crabitan_bellevue/commit/644738ae8c7d3f02c64c667380af803188edad20))


### Features

* **auth:** fusion cookie cb-cart → BDD après login réussi (CartModel::mergeLocalCart) ([e10dfdb](https://github.com/ApoSkunz/crabitan_bellevue/commit/e10dfdbf0b6ae7f96b235c164a34e085b510060e))
* **controller:** CartController + CartApiController — panier invité/connecté + API add/update/remove/count ([bfbb6cc](https://github.com/ApoSkunz/crabitan_bellevue/commit/bfbb6ccd09a5186ba42e97429f5d46956c107d5c))
* **controller:** panier — B2B, pricing rules, cb-cart-count cookie, image prefix, logout sans copie cookie ([15d6f10](https://github.com/ApoSkunz/crabitan_bellevue/commit/15d6f10af66dc91f0b10ed2e6c9d783a01cf9056))
* **i18n:** 7 clés cart — login_to_order, login_cta, browse, item_total, order_total, price_at_checkout, update_qty ([2770b87](https://github.com/ApoSkunz/crabitan_bellevue/commit/2770b87d151ef1260cc7451b67b69c9b64de5296))
* **i18n:** footer — mention Loi Evin + interdiction vente alcool mineurs -18 ans (Art. L3342-1 CSP) ([b533edc](https://github.com/ApoSkunz/crabitan_bellevue/commit/b533edc27a0c090f84900c32e4844d3c07288635))
* **i18n:** panier — over_600_checkout_error, clés B2B, notices livraison fr/en ([8a28048](https://github.com/ApoSkunz/crabitan_bellevue/commit/8a28048d56f5550f51b57ec149ff6d6ae3231dac))
* **js:** panier — updateCartTotal, progress bar paliers, fix badge showCartSuccess, notices dynamiques, checkout >600 ([47fd315](https://github.com/ApoSkunz/crabitan_bellevue/commit/47fd315d360d234e85117a3dea11564be4bb0d7e))
* **js:** panier hybride — cookie sans price, modal duale invité/connecté, initCartPage() ([9fbe165](https://github.com/ApoSkunz/crabitan_bellevue/commit/9fbe16517f0eab025596fea5b899fdf5da25a644))
* **model:** CartModel — findByUserId, save (upsert), clear, mergeLocalCart ([b938952](https://github.com/ApoSkunz/crabitan_bellevue/commit/b9389529894f549f64046799b0b2d2ccd643c0f5))
* **model:** PricingRuleModel — findForQuantity retourne min_quantity + findNextTierFor/findAllActive ([5fde2b7](https://github.com/ApoSkunz/crabitan_bellevue/commit/5fde2b75ababd1e997b55ce36ee391c950fb6814))
* **routes:** GET /api/cart/count ([7ceafc3](https://github.com/ApoSkunz/crabitan_bellevue/commit/7ceafc3ddd304947634b5df18cb3b86515f99f10))
* **scss:** _cart.scss — page panier table/résumé/invité, responsive mobile ([6dbecb9](https://github.com/ApoSkunz/crabitan_bellevue/commit/6dbecb994a192a5d1ea46f37d30e7e9d6f7e008d))
* **scss:** panier — progress bar, btn--disabled, cart-checkout-error, qty input, btn-retirer, B2B, delivery accordion ([d9a5587](https://github.com/ApoSkunz/crabitan_bellevue/commit/d9a5587d8f420144106f2b8ee516b8e424f20f98))
* **view:** page panier — invité (cookie JS) + connecté (BDD PHP) + CTA checkout ([03d87c5](https://github.com/ApoSkunz/crabitan_bellevue/commit/03d87c567b42ec6cef587b9f07ae4ca0208a5d46))
* **view:** panier — notices statiques, progress bar paliers, sidebar TTC + compteur bouteilles, B2B, invité ([fbce664](https://github.com/ApoSkunz/crabitan_bellevue/commit/fbce6649740c4eff663c2b83cf9e4ce4f1e1385b))

# [0.25.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.24.0...v0.25.0) (2026-04-03)


### Bug Fixes

* **auth:** anti-énumération complète — compte inactif renvoie même réponse que identifiants invalides ([2fe07e3](https://github.com/ApoSkunz/crabitan_bellevue/commit/2fe07e330030f85fdff0947be9afc9ec4f5b07b7))


### Features

* **model:** AccountModel::refreshVerificationToken — renvoi silencieux token vérification ([a0c99f9](https://github.com/ApoSkunz/crabitan_bellevue/commit/a0c99f9c98b9a7b3b901d23734a685269e1735c0))

# [0.24.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.23.0...v0.24.0) (2026-04-03)


### Bug Fixes

* **view:** require → require_once dans conditions-generales-vente (php:S2003) ([58a6cc9](https://github.com/ApoSkunz/crabitan_bellevue/commit/58a6cc9590aa90cafec85d681280e1e41a87840f))


### Features

* **controller:** PageController::conditionsGeneralesVente — bare mode + lang resolution ([538c5e2](https://github.com/ApoSkunz/crabitan_bellevue/commit/538c5e2209f9d63b41860d0fb94e3eae9a7f444e))
* **i18n:** clé footer.cgv — Conditions générales de vente / Terms and conditions ([b50e41d](https://github.com/ApoSkunz/crabitan_bellevue/commit/b50e41d46047b6dbd4d31264f92df1914805675c))
* **mail:** lien CGV dans footer email transactionnel (TLS + URL_CGV constant) ([84fde59](https://github.com/ApoSkunz/crabitan_bellevue/commit/84fde5983c0d6a884ac5c3b09a57889d2fb1ae7b))
* **routes:** route GET /{lang}/conditions-generales-de-vente + isPublicPath age-gate bypass ([47b1128](https://github.com/ApoSkunz/crabitan_bellevue/commit/47b1128e06a3ded8213223cd05358ba2575e216a))
* **view:** lien CGV dans footer-nav-legal + carte plan-du-site ([5381184](https://github.com/ApoSkunz/crabitan_bellevue/commit/5381184a0978a11554d1f02fab48cb471655096d))
* **view:** page CGV bilingue fr/en — 12 sections, droit de rétractation 14j, vice caché vin, TLS+QPC, annulation par étape, horaires, crabitan.bellevue@orange.fr ([2826266](https://github.com/ApoSkunz/crabitan_bellevue/commit/28262667e56dae36c3ea3c5bb13316453a702d90))

# [0.23.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.22.0...v0.23.0) (2026-04-03)


### Bug Fixes

* **account:** suppression de compte possible pour les comptes Google-only ([285a868](https://github.com/ApoSkunz/crabitan_bellevue/commit/285a868d08220cb607eb334ec525bf905ec8af0d))
* **cache:** Cache-Control: no-store — empêche bfcache de restaurer les flash messages ([8a7772a](https://github.com/ApoSkunz/crabitan_bellevue/commit/8a7772a3cf6e9735eeec57d47c301db4ed590936))
* **i18n:** préciser l'option rattachement Google dans le message de refus ([4999e71](https://github.com/ApoSkunz/crabitan_bellevue/commit/4999e71d724dabf2a14d93951d837313efbdec31))
* **modal:** reload page à la fermeture si flash succès affiché (register / forgot / reset) ([71be4db](https://github.com/ApoSkunz/crabitan_bellevue/commit/71be4db34939f30e8b8a1834a1e8d3513bb630ef))
* **oauth:** 500 compte en suppression + redirection post-connexion ([14cf8e5](https://github.com/ApoSkunz/crabitan_bellevue/commit/14cf8e5a30bf549eadde7268481a366fbfc190fb))
* **oauth:** buildRedirectUri — GOOGLE_FR_FALBACK/GOOGLE_EN_FALBACK en priorité (fix 400 invalid_request) ([1cd9bbc](https://github.com/ApoSkunz/crabitan_bellevue/commit/1cd9bbc5848a65ec52469b725f05494113559046))
* **oauth:** message explicatif au refus de rattachement compte Google ([5616140](https://github.com/ApoSkunz/crabitan_bellevue/commit/561614092824e4e55120d279440e80247c8b3faa))
* **oauth:** redirections internes relatives — préserve la session PHP ([667715e](https://github.com/ApoSkunz/crabitan_bellevue/commit/667715ef88d607c2caa23b1359cd634a959ca873))
* **phpcs:** GoogleOAuthControllerTest — formatage try/catch (phpcbf auto-fix) ([fff477f](https://github.com/ApoSkunz/crabitan_bellevue/commit/fff477f568319b05fb0c95c3d91d149309f51721))
* **test:** extraire StubGoogleOAuthService dans son propre fichier (PSR-1) ([9be5407](https://github.com/ApoSkunz/crabitan_bellevue/commit/9be54077c3fc58503e1b26b6f812f1a308c2d946))


### Features

* **account:** masquer changement mdp et reset sécurité pour comptes Google-only ([2f8121d](https://github.com/ApoSkunz/crabitan_bellevue/commit/2f8121da9d4ac91f8bf05413b57ee4f1d983bb85))
* **auth:** envoyer un mail informatif si mot de passe oublié sur compte Google-only ([b460d8c](https://github.com/ApoSkunz/crabitan_bellevue/commit/b460d8c2125017f51c6882f536b21c95ee5ca344))
* **model:** AccountModel — linkGoogleId() + createFromGoogle() ([85ad110](https://github.com/ApoSkunz/crabitan_bellevue/commit/85ad1100fe24c93c28e000a03528d8ae08ef885b))
* **oauth:** GoogleOAuthService + GoogleOAuthController — flux OAuth2 Google complet ([1977ce8](https://github.com/ApoSkunz/crabitan_bellevue/commit/1977ce81582cedaca30e3d9e1b80a0c0b21e79b0))
* **oauth:** rattachement proposé — page de confirmation avant linkGoogleId ([01061a2](https://github.com/ApoSkunz/crabitan_bellevue/commit/01061a2553f5c7013e629e507e1c8741e20da42f))
* **oauth:** routes link confirm + vue google-link-confirm + i18n fr/en ([fd33b3f](https://github.com/ApoSkunz/crabitan_bellevue/commit/fd33b3f3d4b93472f26e7b7981d64f11da37e82a))
* **routes:** GET /{lang}/auth/google + /{lang}/auth/google/callback ([f5c039e](https://github.com/ApoSkunz/crabitan_bellevue/commit/f5c039e34f9433906d310fc8015d7d5175c70226))
* **view:** bouton Google OAuth activé dans les modals connexion/inscription ([cdbb0f8](https://github.com/ApoSkunz/crabitan_bellevue/commit/cdbb0f860dde5bac33f9f6a5b51ae70823f1277f))

# [0.22.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.21.0...v0.22.0) (2026-04-03)


### Bug Fixes

* **age-gate:** supprimer le cadre fixe extérieur — liens légaux superposés ([94c61bd](https://github.com/ApoSkunz/crabitan_bellevue/commit/94c61bd5e425313ae0458f1d52750eb3e0d52c7c))
* **dpo:** caractères spéciaux PDF — entités HTML + retrait bold sur tag-ok ([a715e0a](https://github.com/ApoSkunz/crabitan_bellevue/commit/a715e0a37926802d042b8e962a2f6ff92a148e96)), closes [#10003](https://github.com/ApoSkunz/crabitan_bellevue/issues/10003) [#8596](https://github.com/ApoSkunz/crabitan_bellevue/issues/8596) [#8594](https://github.com/ApoSkunz/crabitan_bellevue/issues/8594)
* **dpo:** supprimer le symbole checkmark U+2713 non rendu par TCPDF ([0ef7f31](https://github.com/ApoSkunz/crabitan_bellevue/commit/0ef7f31ccda9081ad5f0d5172b9e29e38b46e32a)), closes [#10003](https://github.com/ApoSkunz/crabitan_bellevue/issues/10003)
* **phpcs:** DpoAdminController — phpcs:ignore ligne securite BDD > 150 chars ([2d591d5](https://github.com/ApoSkunz/crabitan_bellevue/commit/2d591d5f3c914cd8fe0e22039174aff36304dbaa))
* **phpcs:** mentions-legales + politique-confidentialite — NOSONAR php:S2003 require vs require_once ([f818582](https://github.com/ApoSkunz/crabitan_bellevue/commit/f818582f5b35687fb5f0d30be393d58af775815a))
* **views:** require au lieu de require_once pour legal-open/close ([c60a505](https://github.com/ApoSkunz/crabitan_bellevue/commit/c60a505c8f01be45cb340b1a2d1c11464456a7d7))


### Features

* **admin:** menu DPO — génération PDF RGPD Art. 28/30/33 via TCPDF ([fdbb73d](https://github.com/ApoSkunz/crabitan_bellevue/commit/fdbb73d115d5838ab2f490678841d513b1e3eeae))
* **header:** ajouter lien DPO — RGPD dans le panel admin Mon Espace ([3b3fbf2](https://github.com/ApoSkunz/crabitan_bellevue/commit/3b3fbf2dc63c65e2a2dc272ae5c9ad81076badac))

# [0.21.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.20.0...v0.21.0) (2026-04-02)


### Bug Fixes

* **e2e:** cookie-consent — reposer age_verified dans clearConsent pour éviter redirection age-gate ([6592364](https://github.com/ApoSkunz/crabitan_bellevue/commit/6592364b38d3e8b20964f9ac8da0c9f0c1662257))
* **e2e:** navigation — mentions-legales vérifie .legal-content (bare mode sans age_verified) ([c4f31ec](https://github.com/ApoSkunz/crabitan_bellevue/commit/c4f31ecd7b97877be5a76ed003aa065d6cb98fbc))
* **router+mail:** age-gate — support accessible sans vérif âge + URL_PRIVACY corrigée (politique-de-confidentialite) ([202f5bd](https://github.com/ApoSkunz/crabitan_bellevue/commit/202f5bd66352d2214962841549c4b802baad692e))
* **router:** age-gate — politique-de-confidentialite accessible sans vérification d'âge ([53d663f](https://github.com/ApoSkunz/crabitan_bellevue/commit/53d663f6f7f88aebfd8588770b6b3f6ff745ceb3))
* **test:** PageControllerTest — poser age_verified avant tests rendu normal (bare mode auto) ([6220c98](https://github.com/ApoSkunz/crabitan_bellevue/commit/6220c98f94e221f161c6d8f2c08b0a5b3447fd3f))
* **view:** head.php — suppression script UA hardcodé (déprécié UA→GA4 + viole consentement RGPD) ([fba3e9b](https://github.com/ApoSkunz/crabitan_bellevue/commit/fba3e9b5df99707e8f7761f3c3e1227a00ca733a))


### Features

* **js:** cookie consent — re-open depuis footer, listeners once:true, extract attachConsentListeners ([eb619ac](https://github.com/ApoSkunz/crabitan_bellevue/commit/eb619ac23f0947234d24ae5e9a153515f93467f7))
* **view+controller:** mode bare auto si age non vérifié — politique-confidentialite, support, mentions-legales ([efc1dd1](https://github.com/ApoSkunz/crabitan_bellevue/commit/efc1dd175e7cdbf16d0d1f9a6798198ff503cf83))
* **view+i18n:** cookie-banner — lien vers politique-de-confidentialite ([4d05695](https://github.com/ApoSkunz/crabitan_bellevue/commit/4d056957f34733ffe6cd46a11f2d7db24b472e33))
* **view+scss+i18n:** footer — deux lignes nav (classique + légale) + lien support + bouton gérer cookies ([46c2fc0](https://github.com/ApoSkunz/crabitan_bellevue/commit/46c2fc0eee31e461e5b414d15ab7008a9edf1e41))
* **view+scss:** age-gate — micro-footer liens politique de confidentialité + mentions légales ([7fbf60f](https://github.com/ApoSkunz/crabitan_bellevue/commit/7fbf60f73f571032901757ca1cec906d175254bb))

# [0.20.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.19.0...v0.20.0) (2026-04-02)


### Bug Fixes

* **e2e:** cookie-consent — reposer age_verified dans clearConsent pour éviter redirection age-gate ([7d1b7d3](https://github.com/ApoSkunz/crabitan_bellevue/commit/7d1b7d3b7a8f223ac6f4246d46ec3e711e48e983))
* **e2e:** navigation — mentions-legales vérifie .legal-content (bare mode sans age_verified) ([6029349](https://github.com/ApoSkunz/crabitan_bellevue/commit/60293492fe0548b76857f895dfd7b6a69dbc8c09))
* **router+mail:** age-gate — support accessible sans vérif âge + URL_PRIVACY corrigée (politique-de-confidentialite) ([debe6a4](https://github.com/ApoSkunz/crabitan_bellevue/commit/debe6a4ff6d4c8991069fff5f1e744abeea5b922))
* **router:** age-gate — politique-de-confidentialite accessible sans vérification d'âge ([c3b7bd8](https://github.com/ApoSkunz/crabitan_bellevue/commit/c3b7bd8892c4bd9ddfef5ed661869a1cb7566584))
* **test:** PageControllerTest — poser age_verified avant tests rendu normal (bare mode auto) ([f6fa856](https://github.com/ApoSkunz/crabitan_bellevue/commit/f6fa856dcf70356152af6162f5600e9694468757))
* **view:** head.php — suppression script UA hardcodé (déprécié UA→GA4 + viole consentement RGPD) ([294b26b](https://github.com/ApoSkunz/crabitan_bellevue/commit/294b26b4884541bb0962e6d1873ead0c982d9455))


### Features

* **js:** cookie consent — re-open depuis footer, listeners once:true, extract attachConsentListeners ([636d764](https://github.com/ApoSkunz/crabitan_bellevue/commit/636d7644487a5392163d0b27ed1007673193800d))
* **view+controller:** mode bare auto si age non vérifié — politique-confidentialite, support, mentions-legales ([57720e1](https://github.com/ApoSkunz/crabitan_bellevue/commit/57720e19b1b214a3dd0df78075af31e1dd12e81c))
* **view+i18n:** cookie-banner — lien vers politique-de-confidentialite ([b1db653](https://github.com/ApoSkunz/crabitan_bellevue/commit/b1db653951c5de262e638ed1f28849057a52f689))
* **view+scss+i18n:** footer — deux lignes nav (classique + légale) + lien support + bouton gérer cookies ([651ff74](https://github.com/ApoSkunz/crabitan_bellevue/commit/651ff745e760bcac3314eee20aaa3cbf26398c29))
* **view+scss:** age-gate — micro-footer liens politique de confidentialité + mentions légales ([6d0d04e](https://github.com/ApoSkunz/crabitan_bellevue/commit/6d0d04ecc320d3b66a1785c328bc98328c13c647))

# [0.19.0](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.18.2...v0.19.0) (2026-04-02)


### Bug Fixes

* **controller:** attraper HttpException avant RuntimeException dans subscribe() ([4e474d5](https://github.com/ApoSkunz/crabitan_bellevue/commit/4e474d51ce0ab65dcc409448c11223bb38989080))
* **cs:** accolade fermante classe MailService — PHPCS PSR-12 ([c1fe46f](https://github.com/ApoSkunz/crabitan_bellevue/commit/c1fe46fad906548bdd342dd76d83b77c113b1777))
* **cs:** espacement arguments makeService() — PHPCS SpaceBeforeEquals ([b1d33f4](https://github.com/ApoSkunz/crabitan_bellevue/commit/b1d33f474d8ba2dd6c3de95aad7d6717b5630442))
* **newsletter:** masquage formulaire si abonné, message neutre anti-énumération, clé error.generic ([5b649a0](https://github.com/ApoSkunz/crabitan_bellevue/commit/5b649a0f350f0baa4de3faff7000183a272803e0))


### Features

* **i18n:** clés newsletter footer (titre, placeholder, bouton) FR + EN ([4b3442d](https://github.com/ApoSkunz/crabitan_bellevue/commit/4b3442d8ff253e72b6bfe65c5add6471c98c0dd5))
* **newsletter:** double opt-in RGPD Art. 7 (us-double-opt-in-newsletter) ([5a34514](https://github.com/ApoSkunz/crabitan_bellevue/commit/5a3451438d5d97065055c1e2eeb418b635bb2cb1))
* **newsletter:** route subscribe/confirm via accounts or newsletter_subscriptions ([7d8e4f7](https://github.com/ApoSkunz/crabitan_bellevue/commit/7d8e4f77032535aa640e19bc100b292ad705cdfd))
* **view:** formulaire newsletter dans le footer avec pré-remplissage si connecté ([9d75317](https://github.com/ApoSkunz/crabitan_bellevue/commit/9d75317d2f53a029a00cc212fd1b1fc30febb205))

## [0.18.2](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.18.1...v0.18.2) (2026-04-02)


### Bug Fixes

* **controller:** headers Cache-Control + Content-Disposition sur export RGPD ([878a955](https://github.com/ApoSkunz/crabitan_bellevue/commit/878a955b5bf5b8746b64f418a8348fff2ac52204))
* **test:** fixture export complète + activation ZipArchive locale ([c3142db](https://github.com/ApoSkunz/crabitan_bellevue/commit/c3142db20a7432c9eb97b91928593240381e322e))

## [0.18.1](https://github.com/ApoSkunz/crabitan_bellevue/compare/v0.18.0...v0.18.1) (2026-04-02)


### Bug Fixes

* **controller:** déconnexion POST+CSRF — protection CSRF sur logout ([8eed718](https://github.com/ApoSkunz/crabitan_bellevue/commit/8eed7180b4c1bdbfabf46dcfefe75bb5a58a2811))
* **scss:** styles du formulaire de déconnexion POST dans le panel ([eddfe33](https://github.com/ApoSkunz/crabitan_bellevue/commit/eddfe3349e8afcf81387d4bcd17644fd9e8177c0))
* **security:** path enumeration prevention — 404 sur routes protégées et GET /deconnexion non connecté ([8be3dbb](https://github.com/ApoSkunz/crabitan_bellevue/commit/8be3dbb8da023f464c29afccdcff22d773d37444))
* **test:** adapter TI/TU au comportement 404 sur routes protégées non authentifiées ([cd4cf79](https://github.com/ApoSkunz/crabitan_bellevue/commit/cd4cf7911802c73d0f1d90eae02502cd702ad1c7))
* **view:** logout via form POST+CSRF dans header — protection CSRF ([f1f7d03](https://github.com/ApoSkunz/crabitan_bellevue/commit/f1f7d0384c14b5f2c3906db8cf620367c1882611))

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
