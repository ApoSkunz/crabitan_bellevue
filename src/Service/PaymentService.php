<?php

declare(strict_types=1);

namespace Service;

/**
 * Service de paiement Up2pay e-Transactions (Crédit Agricole).
 *
 * Gère trois responsabilités :
 *  1. buildPbxFields()     — construction des champs PBX_* pour l'initiation du paiement par redirection
 *  2. verifyIpnSignature() — vérification de la signature RSA SHA-1 de l'IPN retournée par CA
 *  3. callGaeRefund()      — appel à l'API GAE pour un remboursement (TYPE=00014)
 */
class PaymentService
{
    private const URL_PROD_PRIMARY    = 'https://tpeweb.e-transactions.fr/php/';
    private const URL_PROD_SECONDARY  = 'https://tpeweb1.e-transactions.fr/php/';
    private const URL_SANDBOX         = 'https://recette-tpeweb.e-transactions.fr/php/';
    private const URL_GAE_PROD        = 'https://ppps.e-transactions.fr/PPPS.php';
    private const URL_GAE_SANDBOX     = 'https://recette-ppps.e-transactions.fr/PPPS.php';
    private const PBX_RETOUR          = 'Mt:M;Ref:R;Auto:A;Appel:T;Trans:S;Erreur:E;Sign:K';
    private const PBX_HASH            = 'SHA512';
    private const PBX_DEVISE          = '978';
    private const AMOUNT_3DS_THRESHOLD = 3000;

    /**
     * Construit les champs PBX_* pour le formulaire d'initiation de paiement CA.
     *
     * @param string $reference     Référence commande (PBX_CMD)
     * @param int    $amountCents   Montant en centimes (PBX_TOTAL)
     * @param string $clientEmail   Email acheteur (PBX_PORTEUR)
     * @param string $billingFirst  Prénom facturation
     * @param string $billingLast   Nom facturation
     * @param string $billingStreet Adresse ligne 1
     * @param string $billingZip    Code postal
     * @param string $billingCity   Ville
     * @param int    $nbProducts    Nombre produits (PBX_SHOPPINGCART)
     * @param string $lang          Langue ('fr' ou 'en')
     * @return array{url: string, fields: array<string, string>}
     * @throws \RuntimeException Si aucun serveur CA n'est disponible.
     */
    public function buildPbxFields(
        string $reference,
        int $amountCents,
        string $clientEmail,
        string $billingFirst,
        string $billingLast,
        string $billingStreet,
        string $billingZip,
        string $billingCity,
        int $nbProducts,
        string $lang = 'fr'
    ): array {
        $sandbox = $this->isSandbox();
        $url     = $this->resolveServerUrl($sandbox);

        $site  = $this->getEnv('CA_PBX_SITE');
        $rang  = $this->getEnv('CA_PBX_RANG');
        $ident = $this->getEnv('CA_PBX_IDENTIFIANT');

        $time  = date('c');
        $souhait = ($amountCents <= self::AMOUNT_3DS_THRESHOLD) ? '02' : '01';
        $langUpper = strtoupper($lang);

        $shoppingCart = sprintf(
            '<?xml version="1.0" encoding="utf-8"?>'
            . '<shoppingcart><total><totalQuantity>%d</totalQuantity></total></shoppingcart>',
            $nbProducts
        );

        $billingXml = $this->buildBillingXml(
            $billingFirst,
            $billingLast,
            $billingStreet,
            $billingZip,
            $billingCity
        );

        $fields = [
            'PBX_SITE'           => $site,
            'PBX_RANG'           => $rang,
            'PBX_IDENTIFIANT'    => $ident,
            'PBX_TOTAL'          => (string) $amountCents,
            'PBX_DEVISE'         => self::PBX_DEVISE,
            'PBX_CMD'            => $reference,
            'PBX_PORTEUR'        => $clientEmail,
            'PBX_RETOUR'         => self::PBX_RETOUR,
            'PBX_HASH'           => self::PBX_HASH,
            'PBX_TIME'           => $time,
            'PBX_LANGUE'         => $langUpper,
            'PBX_SOUHAITAUTHENT' => $souhait,
            'PBX_SHOPPINGCART'   => $shoppingCart,
            'PBX_BILLING'        => $billingXml,
        ];

        $msg  = $this->buildMessage($fields);
        $hmac = $this->computeHmac($msg);

        $fields['PBX_HMAC'] = $hmac;

        return ['url' => $url, 'fields' => $fields];
    }

    /**
     * Vérifie la signature RSA de l'IPN CA.
     *
     * @param string $rawQueryString QUERY_STRING brute du serveur ($_SERVER['QUERY_STRING'])
     * @return bool true si la signature est valide, false sinon
     */
    public function verifyIpnSignature(string $rawQueryString): bool
    {
        $signKey = '&Sign=';
        $pos = strrpos($rawQueryString, $signKey);

        if ($pos === false) {
            return false;
        }

        $data     = substr($rawQueryString, 0, $pos);
        $signRaw  = substr($rawQueryString, $pos + strlen($signKey));
        $sig      = base64_decode(urldecode($signRaw));

        if ($sig === false || $sig === '') {
            return false;
        }

        $pubkeyPath = $this->getEnv('CA_PUBKEY_PATH');

        if ($pubkeyPath === '' || !file_exists($pubkeyPath)) {
            return false;
        }

        $pemContent = file_get_contents($pubkeyPath);

        if ($pemContent === false) {
            return false;
        }

        $pubkey = openssl_pkey_get_public($pemContent);

        if ($pubkey === false) {
            return false;
        }

        return openssl_verify($data, $sig, $pubkey, OPENSSL_ALGO_SHA1) === 1;
    }

    /**
     * Appelle l'API GAE CA pour un remboursement (TYPE=00014).
     *
     * @param string $reference   Référence commande
     * @param string $numappel    NUMAPPEL stocké lors du paiement
     * @param string $numtrans    NUMTRANS stocké lors du paiement
     * @param int    $amountCents Montant à rembourser en centimes
     * @return bool true si CODEREPONSE=00000
     */
    public function callGaeRefund(
        string $reference,
        string $numappel,
        string $numtrans,
        int $amountCents
    ): bool {
        $sandbox     = $this->isSandbox();
        $gaeUrl      = $sandbox ? self::URL_GAE_SANDBOX : self::URL_GAE_PROD;
        $site        = $this->getEnv('CA_PBX_SITE');
        $rang        = $this->getEnv('CA_PBX_RANG');
        $numquestion = str_pad(
            (string) (time() % 1000000000 + rand(0, 999)),
            10,
            '0',
            STR_PAD_LEFT
        );
        $dateq  = date('dmY');
        $montant = (string) $amountCents;

        $msg = implode('&', [
            'VERSION=00104',
            'TYPE=00014',
            'SITE='        . $site,
            'RANG='        . $rang,
            'NUMQUESTION=' . $numquestion,
            'MONTANT='     . $montant,
            'DEVISE=978',
            'REFERENCE='   . $reference,
            'NUMAPPEL='    . $numappel,
            'NUMTRANS='    . $numtrans,
            'ACTIVITE=024',
            'DATEQ='       . $dateq,
            'HASH=SHA512',
        ]);

        $hmac = $this->computeHmac($msg);

        $postFields = $msg . '&HMAC=' . $hmac;

        $response = $this->curlPost($gaeUrl, $postFields);

        if ($response === false) {
            return false;
        }

        parse_str($response, $parsed);

        return isset($parsed['CODEREPONSE']) && $parsed['CODEREPONSE'] === '00000';
    }

    // -------------------------------------------------------------------------
    // Méthodes privées
    // -------------------------------------------------------------------------

    /**
     * Résout l'URL du serveur CA en vérifiant la disponibilité via load.html.
     *
     * @param bool $sandbox Mode sandbox
     * @return string URL du serveur disponible
     * @throws \RuntimeException Si aucun serveur n'est disponible.
     */
    private function resolveServerUrl(bool $sandbox): string
    {
        if ($sandbox) {
            return self::URL_SANDBOX;
        }

        $candidates = [self::URL_PROD_PRIMARY, self::URL_PROD_SECONDARY];

        foreach ($candidates as $candidate) {
            if ($this->isServerAvailable($candidate)) {
                return $candidate;
            }
        }

        throw new \RuntimeException('Aucun serveur CA e-Transactions disponible.');
    }

    /**
     * Vérifie si un serveur CA est disponible via GET {url}load.html.
     *
     * @param string $baseUrl URL de base du serveur
     * @return bool true si `server_status` = OK dans le HTML retourné
     */
    protected function isServerAvailable(string $baseUrl): bool
    {
        $ch = curl_init($baseUrl . 'load.html');

        if ($ch === false) {
            return false;
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        $html = curl_exec($ch);
        curl_close($ch);

        if ($html === false || !is_string($html)) {
            return false;
        }

        return str_contains($html, 'server_status') && str_contains($html, 'OK');
    }

    /**
     * Construit le message à hasher à partir du tableau de champs (format VAR=val&...).
     *
     * @param array<string, string> $fields Champs dans l'ordre exact
     * @return string Message concatené
     */
    private function buildMessage(array $fields): string
    {
        $parts = [];

        foreach ($fields as $key => $value) {
            $parts[] = $key . '=' . $value;
        }

        return implode('&', $parts);
    }

    /**
     * Calcule le HMAC-SHA512 du message avec la clé hexadécimale de l'environnement.
     *
     * @param string $msg Message à signer
     * @return string Signature en majuscules
     */
    private function computeHmac(string $msg): string
    {
        $hmacKey = $this->getEnv('CA_PBX_HMAC_KEY');
        return strtoupper(hash_hmac('sha512', $msg, hex2bin($hmacKey)));
    }

    /**
     * Construit le XML PBX_BILLING avec les données de facturation.
     *
     * @param string $firstName Prénom
     * @param string $lastName  Nom
     * @param string $street    Adresse ligne 1
     * @param string $zip       Code postal
     * @param string $city      Ville
     * @return string XML PBX_BILLING
     */
    private function buildBillingXml(
        string $firstName,
        string $lastName,
        string $street,
        string $zip,
        string $city
    ): string {
        $fn = htmlspecialchars($this->formatBillingField($firstName, 45), ENT_XML1, 'UTF-8');
        $ln = htmlspecialchars($this->formatBillingField($lastName, 45), ENT_XML1, 'UTF-8');
        $st = htmlspecialchars($this->formatBillingField($street, 50), ENT_XML1, 'UTF-8');
        $zp = htmlspecialchars($this->formatBillingField($zip, 10), ENT_XML1, 'UTF-8');
        $ct = htmlspecialchars($this->formatBillingField($city, 50), ENT_XML1, 'UTF-8');

        return '<?xml version="1.0" encoding="utf-8"?>'
            . '<Billing>'
            . '<Address>'
            . '<FirstName>' . $fn . '</FirstName>'
            . '<LastName>'  . $ln . '</LastName>'
            . '<Address1>'  . $st . '</Address1>'
            . '<ZipCode>'   . $zp . '</ZipCode>'
            . '<City>'      . $ct . '</City>'
            . '<CountryCode>250</CountryCode>'
            . '</Address>'
            . '</Billing>';
    }

    /**
     * Formate un champ de facturation : majuscules, suppression d'accents,
     * retrait des caractères non-alphanumériques (sauf espace), trim, troncature.
     *
     * @param string $value  Valeur brute
     * @param int    $maxLen Longueur maximale
     * @return string Valeur formatée
     */
    private function formatBillingField(string $value, int $maxLen): string
    {
        // Translitération ASCII via iconv (supprime les diacritiques sans extension intl)
        $translit = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        $stripped = ($translit !== false) ? $translit : $value;

        // Majuscules
        $upper = mb_strtoupper($stripped, 'UTF-8');

        // Suppression des caractères non alphanumériques sauf espace
        $clean = preg_replace('/[^A-Z0-9 ]/u', '', $upper);

        if ($clean === null) {
            $clean = $upper;
        }

        return substr(trim($clean), 0, $maxLen);
    }

    /**
     * Exécute un POST cURL et retourne le corps de la réponse, ou false en cas d'erreur.
     *
     * @param string $url        URL cible
     * @param string $postFields Corps POST (format query string)
     * @return string|false Corps de la réponse ou false si erreur
     */
    protected function curlPost(string $url, string $postFields): string|false
    {
        $ch = curl_init($url);

        if ($ch === false) {
            return false;
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);

        $response = curl_exec($ch);
        $errno    = curl_errno($ch);
        curl_close($ch);

        if ($errno !== 0 || $response === false) {
            return false;
        }

        return (string) $response;
    }

    /**
     * Retourne la valeur d'une variable d'environnement (chaîne vide si absente).
     *
     * @param string $key Nom de la variable
     * @return string Valeur ou chaîne vide
     */
    /**
     * Indique si le mode sandbox CA est actif.
     *
     * Compatible avec parse_ini_file qui convertit `true` en "1".
     * Accepte : "true", "1", "yes", "on" (insensible à la casse).
     *
     * @return bool
     */
    private function isSandbox(): bool
    {
        return in_array(
            strtolower($this->getEnv('CA_SANDBOX_MODE')),
            ['true', '1', 'yes', 'on'],
            true
        );
    }

    /**
     * Retourne la valeur d'une variable d'environnement (chaîne vide si absente).
     *
     * @param string $key Nom de la variable
     * @return string Valeur ou chaîne vide
     */
    private function getEnv(string $key): string
    {
        $value = $_ENV[$key] ?? getenv($key);
        return is_scalar($value) ? (string) $value : '';
    }
}
