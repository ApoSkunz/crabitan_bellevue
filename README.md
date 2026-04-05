# Crabitan Bellevue

Site e-commerce de vins — [crabitanbellevue.fr](https://crabitanbellevue.fr)

## CI/CD

[![CI](https://github.com/ApoSkunz/crabitan_bellevue/actions/workflows/ci.yml/badge.svg)](https://github.com/ApoSkunz/crabitan_bellevue/actions/workflows/ci.yml)
[![E2E](https://github.com/ApoSkunz/crabitan_bellevue/actions/workflows/e2e.yml/badge.svg)](https://github.com/ApoSkunz/crabitan_bellevue/actions/workflows/e2e.yml)
[![Release](https://github.com/ApoSkunz/crabitan_bellevue/actions/workflows/release.yml/badge.svg)](https://github.com/ApoSkunz/crabitan_bellevue/actions/workflows/release.yml)

## Sécurité

**Scanning actif** (secrets, SAST, supply chain, hardening GitHub) — `security.yml` : TruffleHog · CodeQL · Semgrep OWASP · Exakat · Legitify

[![Security](https://github.com/ApoSkunz/crabitan_bellevue/actions/workflows/security.yml/badge.svg)](https://github.com/ApoSkunz/crabitan_bellevue/actions/workflows/security.yml)

**Analyse statique du code source** — SonarCloud : Security Rating · Vulnérabilités · Quality Gate

[![Quality Gate Status](https://sonarcloud.io/api/project_badges/measure?project=ApoSkunz_crabitan_bellevue&metric=alert_status)](https://sonarcloud.io/summary/new_code?id=ApoSkunz_crabitan_bellevue)
[![Security Rating](https://sonarcloud.io/api/project_badges/measure?project=ApoSkunz_crabitan_bellevue&metric=security_rating)](https://sonarcloud.io/summary/new_code?id=ApoSkunz_crabitan_bellevue)
[![Vulnerabilities](https://sonarcloud.io/api/project_badges/measure?project=ApoSkunz_crabitan_bellevue&metric=vulnerabilities)](https://sonarcloud.io/summary/new_code?id=ApoSkunz_crabitan_bellevue)

## Qualité

[![Bugs](https://sonarcloud.io/api/project_badges/measure?project=ApoSkunz_crabitan_bellevue&metric=bugs)](https://sonarcloud.io/summary/new_code?id=ApoSkunz_crabitan_bellevue)
[![Code Smells](https://sonarcloud.io/api/project_badges/measure?project=ApoSkunz_crabitan_bellevue&metric=code_smells)](https://sonarcloud.io/summary/new_code?id=ApoSkunz_crabitan_bellevue)
[![Coverage](https://sonarcloud.io/api/project_badges/measure?project=ApoSkunz_crabitan_bellevue&metric=coverage)](https://sonarcloud.io/summary/new_code?id=ApoSkunz_crabitan_bellevue)
[![Duplicated Lines (%)](https://sonarcloud.io/api/project_badges/measure?project=ApoSkunz_crabitan_bellevue&metric=duplicated_lines_density)](https://sonarcloud.io/summary/new_code?id=ApoSkunz_crabitan_bellevue)

## Paiement CA Up2pay e-Transactions

La clé publique CA pour la vérification des signatures IPN est versionnée dans `config/pubkey.pem`.
Il s'agit de la clé recette fournie dans le kit d'intégration CA (aucun risque cryptographique à la publier).

**Si CA fait tourner sa paire de clés :** remplacer `config/pubkey.pem` par la nouvelle clé publique
fournie par CA, puis mettre à jour `CA_PUBKEY_PATH` dans `.env` si le chemin change.
L'alerte email envoyée à `MAINTAINER_MAIL` lors d'une signature IPN invalide signale ce cas.
