/**
 * stryker.config.mjs — Configuration Stryker mutation testing JS.
 *
 * Périmètre : modules utilitaires purs dans resources/js/
 * Tests     : node:test (Node ≥ 18), runner "command"
 * Rapport   : HTML dans build/stryker/
 */

/** @type {import('@stryker-mutator/api/core').PartialStrykerOptions} */
export default {
    // Fichiers sources à muter
    mutate: [
        'resources/js/cart-utils.js',
        'resources/js/error-utils.js',
    ],

    // Runner de tests — utilise node:test natif, sans framework tiers
    testRunner: 'command',
    commandRunner: {
        command: 'node --test tests/Unit/js/cart-utils.test.mjs tests/Unit/js/error-utils.test.mjs',
    },

    // Rapport
    reporters: ['html', 'progress', 'clear-text'],
    htmlReporter: {
        fileName: 'build/stryker/mutation-report.html',
    },

    // Seuils MSI
    thresholds: {
        high: 80,
        low: 50,
        break: 50,
    },

    // Couverture de code pour accélérer (skip les mutants non couverts)
    coverageAnalysis: 'off',

    // Timeout par test (ms)
    timeoutMS: 10000,
    timeoutFactor: 1.5,

    // Concurrence
    concurrency: 4,

    // Patterns ignorés (build Vite + tout sauf sources + tests ciblés)
    ignorePatterns: [
        '**',
        '!resources/js/cart-utils.js',
        '!resources/js/error-utils.js',
        '!tests/Unit/js/cart-utils.test.mjs',
        '!tests/Unit/js/error-utils.test.mjs',
    ],

    // Logging
    logLevel: 'info',
};
