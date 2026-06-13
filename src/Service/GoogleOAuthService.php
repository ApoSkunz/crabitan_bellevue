<?php

declare(strict_types=1);

namespace Service;

use Core\Exception\GoogleOAuthException;

/**
 * Gère le flux OAuth2 Google : construction de l'URL d'autorisation,
 * échange du code contre un token et récupération des infos utilisateur.
 */
class GoogleOAuthService
{
    private const AUTH_URL     = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URL    = 'https://oauth2.googleapis.com/token';
    private const USERINFO_URL = 'https://www.googleapis.com/oauth2/v3/userinfo';
    private const SCOPE        = 'openid email profile';

    public function __construct(
        private readonly string $clientId,
        private readonly string $clientSecret,
    ) {
    }

    /**
     * Construit l'URL d'autorisation Google OAuth2.
     *
     * @param string $redirectUri URI de callback enregistrée dans la Google Cloud Console
     * @param string $state       Nonce aléatoire anti-CSRF — doit être vérifié au retour
     * @return string URL complète vers laquelle rediriger l'utilisateur
     */
    public function buildAuthUrl(string $redirectUri, string $state): string
    {
        return self::AUTH_URL . '?' . http_build_query([
            'client_id'     => $this->clientId,
            'redirect_uri'  => $redirectUri,
            'response_type' => 'code',
            'scope'         => self::SCOPE,
            'state'         => $state,
            'access_type'   => 'online',
        ]);
    }

    /**
     * Échange le code d'autorisation contre un access token Google.
     *
     * @param string $code        Code reçu en paramètre GET du callback
     * @param string $redirectUri Doit être identique à celui utilisé lors de la redirection
     * @return array{access_token: string} Tableau contenant l'access_token Google
     * @throws \RuntimeException Si la réponse Google est invalide
     */
    public function exchangeCode(string $code, string $redirectUri): array
    {
        $body = http_build_query([
            'code'          => $code,
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri'  => $redirectUri,
            'grant_type'    => 'authorization_code',
        ]);

        $response = $this->httpPost(self::TOKEN_URL, $body);
        $data     = json_decode($response, true);

        if (!isset($data['access_token'])) {
            throw new GoogleOAuthException('Google token exchange failed: ' . ($data['error'] ?? 'unknown'));
        }

        return $data;
    }

    /**
     * Récupère les informations du compte Google via l'access token.
     *
     * @param string $accessToken Token obtenu via exchangeCode()
     * @return array{sub: string, email: string, given_name?: string, family_name?: string} Données utilisateur
     * @throws \RuntimeException Si l'email est absent ou si la réponse est invalide
     */
    public function fetchUserInfo(string $accessToken): array
    {
        $response = $this->httpGet(self::USERINFO_URL, $accessToken);
        $data     = json_decode($response, true);

        if (!isset($data['sub'], $data['email'])) {
            throw new GoogleOAuthException('Google userinfo missing required fields');
        }

        return $data;
    }

    /**
     * Effectue une requête POST HTTP (extractible pour les tests).
     *
     * @param string $url  URL cible
     * @param string $body Corps encodé en application/x-www-form-urlencoded
     * @return string Réponse brute
     * @throws \RuntimeException Si la requête échoue
     */
    protected function httpPost(string $url, string $body): string
    {
        $ctx = stream_context_create(['http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => $body,
            'timeout' => 10,
        ]]);

        $result = @file_get_contents($url, false, $ctx);

        if ($result === false) {
            throw new GoogleOAuthException('Google OAuth HTTP POST failed');
        }

        return $result;
    }

    /**
     * Effectue une requête GET HTTP avec Bearer token (extractible pour les tests).
     *
     * @param string $url         URL cible
     * @param string $accessToken Token Bearer
     * @return string Réponse brute
     * @throws \RuntimeException Si la requête échoue
     */
    protected function httpGet(string $url, string $accessToken): string
    {
        $ctx = stream_context_create(['http' => [
            'method'  => 'GET',
            'header'  => "Authorization: Bearer {$accessToken}\r\n",
            'timeout' => 10,
        ]]);

        $result = @file_get_contents($url, false, $ctx);

        if ($result === false) {
            throw new GoogleOAuthException('Google OAuth HTTP GET failed');
        }

        return $result;
    }
}
