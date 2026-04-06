<?php

declare(strict_types=1);

namespace Controller;

use Core\Controller;
use Core\Response;

class AgeGateController extends Controller
{
    private const COOKIE_NAME     = 'age_verified';
    private const COOKIE_REMEMBER = 'age_remember';
    private const COOKIE_TTL      = 397 * 24 * 3600; // 13 mois — durée max CNIL/RGPD

    public function show(): void
    {
        // Déjà vérifié → retour au site, pas de retour possible sur l'age gate
        if (($_COOKIE[self::COOKIE_NAME] ?? '') === '1') {
            Response::redirect('/' . DEFAULT_LANG);
        }

        $lang     = in_array($_GET['lang'] ?? '', ['fr', 'en'], true) ? $_GET['lang'] : DEFAULT_LANG;
        $redirect = $_GET['redirect'] ?? '/' . $lang;

        \Core\Lang::load($lang);

        $this->view('age-gate', [
            'lang'     => $lang,
            'redirect' => $this->sanitizeRedirect($redirect),
        ]);
    }

    public function confirm(): void
    {
        $legalAge = $_POST['legal_age'] ?? '';
        $remember = !empty($_POST['remember']);
        $redirect = $_POST['redirect'] ?? '/' . DEFAULT_LANG;

        // Mineur ou choix absent → redirection vers l'URL de sortie (referer externe ou Google)
        if ($legalAge !== '1') {
            $exitUrl = $_POST['exit_url'] ?? '';
            Response::redirect($this->sanitizeExitUrl($exitUrl));
        }

        $this->logAgeVerification();

        $cookieBase = [
            'path'     => '/',
            'secure'   => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Strict',
        ];

        setcookie(self::COOKIE_NAME, '1', array_merge($cookieBase, [
            'expires' => $remember ? time() + self::COOKIE_TTL : 0,
        ]));

        // Cookie marqueur "remember" pour le sliding refresh en navigation
        if ($remember) {
            setcookie(self::COOKIE_REMEMBER, '1', array_merge($cookieBase, [
                'expires' => time() + self::COOKIE_TTL,
            ]));
        }

        // Cookie intro pour l'animation d'arrivée (readable by JS → httponly: false)
        setcookie('age_intro', '1', [
            'expires'  => time() + 30,
            'path'     => '/',
            'secure'   => isset($_SERVER['HTTPS']),
            'httponly' => false,
            'samesite' => 'Lax',
        ]);

        Response::redirect($this->sanitizeRedirect($redirect));
    }

    /**
     * Enregistre l'IP et l'horodatage de la vérification d'âge
     * à des fins de preuve légale (Art. L3342-1 CSP).
     */
    private function logAgeVerification(): void
    {
        $ip     = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $logDir = ROOT_PATH . '/logs';

        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        error_log(date('Y-m-d H:i:s') . ' | ' . $ip . PHP_EOL, 3, $logDir . '/age-gate.log');
    }

    /**
     * Valide une URL de sortie externe (http/https, hors domaine courant).
     * Tout ce qui ne correspond pas à une URL absolue externe est remplacé par Google.
     *
     * @param string $url URL candidate fournie via POST exit_url
     * @return string URL sûre vers laquelle rediriger
     */
    private function sanitizeExitUrl(string $url): string
    {
        if (
            filter_var($url, FILTER_VALIDATE_URL) !== false
            && preg_match('#^https?://#i', $url)
            && !str_contains($url, (string) ($_SERVER['HTTP_HOST'] ?? ''))
        ) {
            return $url;
        }

        return 'https://www.google.com';
    }

    /**
     * Garantit un chemin relatif interne pour éviter les open redirects.
     * Tout ce qui n'est pas un chemin absolu interne (/foo) est remplacé par la langue par défaut.
     */
    private function sanitizeRedirect(string $url): string
    {
        // Autoriser uniquement les chemins commençant par "/" mais pas "//"
        // "//" serait interprété comme un URL sans protocole (open redirect)
        if (!str_starts_with($url, '/') || str_starts_with($url, '//')) {
            return '/' . DEFAULT_LANG;
        }

        return $url;
    }
}
