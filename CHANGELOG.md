# Changelog

All notable changes to Crabitan Bellevue are documented here.

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
