#!/usr/bin/env bash
# ==============================================================================
# bin/dev/fuzzing-php.sh — Lancement du corpus de fuzzing PHP (US2)
#
# Usage :
#   bash bin/dev/fuzzing-php.sh            # Lance les 3 suites de fuzzing
#   bash bin/dev/fuzzing-php.sh --upload   # Upload seulement
#   bash bin/dev/fuzzing-php.sh --api      # Routes API seulement
#   bash bin/dev/fuzzing-php.sh --inputs   # Entrées PHP seulement
# ==============================================================================

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"
PHPUNIT="${PROJECT_ROOT}/vendor/bin/phpunit"
FUZZING_DIR="${PROJECT_ROOT}/tests/Unit/Fuzzing"
DATE="$(date +%Y-%m-%d)"
REPORT_DIR="${PROJECT_ROOT}/docs"

run_api() {
    echo "==> Fuzzing routes API HTTP..."
    "${PHPUNIT}" --bootstrap "${PROJECT_ROOT}/tests/bootstrap.php" \
        "${FUZZING_DIR}/ApiRoutesFuzzingTest.php" \
        --testdox \
        --colors=auto
}

run_inputs() {
    echo "==> Fuzzing entrées PHP (1000+ mutations)..."
    "${PHPUNIT}" --bootstrap "${PROJECT_ROOT}/tests/bootstrap.php" \
        "${FUZZING_DIR}/PhpInputFuzzingTest.php" \
        --testdox \
        --colors=auto
}

run_upload() {
    echo "==> Fuzzing upload fichiers..."
    "${PHPUNIT}" --bootstrap "${PROJECT_ROOT}/tests/bootstrap.php" \
        "${FUZZING_DIR}/UploadFuzzingTest.php" \
        --testdox \
        --colors=auto
}

run_all() {
    echo "===================================================================="
    echo "  Crabitan Bellevue — Fuzzing PHPUnit (${DATE})"
    echo "===================================================================="
    run_api
    run_inputs
    run_upload
    echo ""
    echo "Fuzzing terminé — voir docs/fuzzing-*-${DATE}.md pour les rapports."
}

case "${1:-all}" in
    --api)    run_api ;;
    --inputs) run_inputs ;;
    --upload) run_upload ;;
    all|*)    run_all ;;
esac
