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

        $redirect = $_GET['redirect'] ?? '/' . DEFAULT_LANG;

        $this->view('age-gate', [
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

        Response::redirect($this->sanitizeRedirect($redirect));
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
