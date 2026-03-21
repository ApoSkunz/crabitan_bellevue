<?php

declare(strict_types=1);

namespace Controller;

use Core\Controller;
use Core\Response;

class AgeGateController extends Controller
{
    private const COOKIE_NAME = 'age_verified';
    private const COOKIE_TTL  = 30 * 24 * 3600; // 30 jours

    public function show(): void
    {
        $redirect = $_GET['redirect'] ?? '/' . DEFAULT_LANG;

        $this->view('age-gate', [
            'redirect' => filter_var($redirect, FILTER_SANITIZE_URL),
        ]);
    }

    public function confirm(): void
    {
        $legalAge = $_POST['legal_age'] ?? '';
        $remember = !empty($_POST['remember']);
        $redirect = $_POST['redirect'] ?? '/' . DEFAULT_LANG;

        // Mineur ou choix absent → retour sur la page age gate
        if ($legalAge !== '1') {
            Response::redirect('/age-gate');
        }

        setcookie(self::COOKIE_NAME, '1', [
            'expires'  => $remember ? time() + self::COOKIE_TTL : 0,
            'path'     => '/',
            'secure'   => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        Response::redirect(filter_var($redirect, FILTER_SANITIZE_URL));
    }
}
