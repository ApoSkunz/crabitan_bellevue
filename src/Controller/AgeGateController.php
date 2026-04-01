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

        // Mineur ou choix absent → redirection Google (JS disabled fallback)
        if ($legalAge !== '1') {
            Response::redirect('https://www.google.com');
        }

        $cookieBase = [
            'path'     => '/',
            'secure'   => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax',
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
     * Valide la déclaration de majorité depuis la modale overlay (routes /{lang}/age-gate/confirmer).
     *
     * Base légale : Art. L3342-1 CSP.
     * Loggue IP + horodatage comme preuve de la déclaration sur l'honneur.
     *
     * @param array<string, string> $params Paramètres de route (lang)
     * @return never
     */
    public function confirmLang(array $params): never
    {
        $lang = isset($params['lang']) && in_array($params['lang'], ['fr', 'en'], true)
            ? $params['lang']
            : DEFAULT_LANG;

        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        // Log de la déclaration (preuve Art. L3342-1 CSP)
        error_log(sprintf(
            '[age-gate] majority declared — ip=%s lang=%s time=%s',
            $ip,
            $lang,
            date('Y-m-d H:i:s')
        ));

        $cookieBase = [
            'path'     => '/',
            'secure'   => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Strict',
        ];

        setcookie(self::COOKIE_NAME, '1', array_merge($cookieBase, [
            'expires' => 0, // session cookie
        ]));

        // Cookie intro pour l'animation d'arrivée (readable by JS → httponly: false)
        setcookie('age_intro', '1', [
            'expires'  => time() + 30,
            'path'     => '/',
            'secure'   => isset($_SERVER['HTTPS']),
            'httponly' => false,
            'samesite' => 'Lax',
        ]);

        $redirect = $_POST['redirect'] ?? '/' . $lang;
        Response::redirect($this->sanitizeRedirect($redirect));
    }

    /**
     * Redirige le visiteur hors du site s'il déclare être mineur (modale overlay).
     *
     * @param array<string, string> $params Paramètres de route (lang)
     * @return never
     */
    public function exitLang(array $params): never // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
    {
        Response::redirect('https://www.google.com');
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
