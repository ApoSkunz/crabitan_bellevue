# Changelog

All notable changes to Crabitan Bellevue are documented here.

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
