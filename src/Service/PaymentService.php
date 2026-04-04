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
    private const AMOUNT_3DS_THRESHOLD = 20000;

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
     *
     * Les URLs de retour (PBX_EFFECTUE, PBX_REFUSE, PBX_ANNULE, PBX_REPONDRE) sont
     * construites depuis CA_FALLBACK_BASE_URL. Si cette variable est vide, CA utilise
     * les URLs configurées dans Vision Air (déconseillé en prod).
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
        $souhait = ($amountCents <= self::AMOUNT_3DS_THRESHOLD) ? '02' : '03';
        $langMap   = ['fr' => 'FRA', 'en' => 'GBR'];
        $langUpper = $langMap[$lang] ?? 'FRA';

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

        // URLs de retour dynamiques — prioritaires sur la config Vision Air.
        $baseUrl = rtrim($this->getEnv('CA_FALLBACK_BASE_URL'), '/');
        if ($baseUrl !== '') {
            $fields['PBX_EFFECTUE'] = $baseUrl . '/' . $lang . '/commande/paiement-ca/ok';
            $fields['PBX_REFUSE']   = $baseUrl . '/' . $lang . '/commande/paiement-ca/refuse';
            $fields['PBX_ANNULE']   = $baseUrl . '/' . $lang . '/commande/paiement-ca/annule';
            $fields['PBX_REPONDRE'] = $baseUrl . '/payment/ipn';
        }

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

        // Résoudre les chemins relatifs depuis ROOT_PATH (le CWD Apache est public/)
        if ($pubkeyPath !== '' && !str_starts_with($pubkeyPath, '/') && !preg_match('/^[A-Za-z]:/', $pubkeyPath)) {
            $pubkeyPath = (defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__, 2)) . '/' . $pubkeyPath;
        }

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
     * Interroge CA sur l'état d'une transaction (TYPE=00017).
     *
     * Retourne le tableau de la réponse CA, ou null en cas d'échec réseau.
     * Champs utiles : STATUS, REMISE (non vide = transaction télécollectée).
     *
     * @param string $numappel NUMAPPEL stocké lors du paiement
     * @param string $numtrans NUMTRANS stocké lors du paiement
     * @return array<string, string>|null
     */
    public function queryTransactionStatus(string $numappel, string $numtrans): ?array
    {
        $sandbox     = $this->isSandbox();
        $gaeUrl      = $sandbox ? self::URL_GAE_SANDBOX : self::URL_GAE_PROD;
        $numquestion = str_pad(
            (string) (time() % 1000000000 + rand(0, 999)),
            10,
            '0',
            STR_PAD_LEFT
        );

        $msg = implode('&', [
            'VERSION=00104',
            'TYPE=00017',
            'SITE='        . $this->getEnv('CA_PBX_SITE'),
            'RANG='        . $this->getEnv('CA_PBX_RANG'),
            'NUMQUESTION=' . $numquestion,
            'NUMAPPEL='    . $numappel,
            'NUMTRANS='    . $numtrans,
            'DATEQ='       . date('dmY'),
            'HASH=SHA512',
        ]);

        $response = $this->curlPost($gaeUrl, $msg . '&HMAC=' . $this->computeHmac($msg));

        if ($response === false) {
            return null;
        }

        parse_str($response, $parsed);


        return $parsed;
    }

    /**
     * Annule ou rembourse une transaction selon son état de télécollecte.
     *
     * Interroge d'abord CA via TYPE=00017 pour savoir si la transaction a été
     * remise en banque (champ REMISE non vide) :
     *  - REMISE vide   → TYPE=00005 (annulation, aucun mouvement bancaire)
     *  - REMISE renseigné → TYPE=00014 (remboursement, mouvement inverse)
     *  - TYPE=00017 inaccessible → fallback : essai 00005 puis 00014
     *
     * @param string $reference   Référence commande
     * @param string $numappel    NUMAPPEL stocké lors du paiement
     * @param string $numtrans    NUMTRANS stocké lors du paiement
     * @param int    $amountCents Montant en centimes
     * @return bool true si l'opération CA est acceptée (CODEREPONSE=00000)
     */
    public function callGaeCancelOrRefund(
        string $reference,
        string $numappel,
        string $numtrans,
        int $amountCents
    ): bool {
        $status = $this->queryTransactionStatus($numappel, $numtrans);

        if ($status !== null && isset($status['CODEREPONSE']) && $status['CODEREPONSE'] === '00000') {
            // REMISE renseigné = transaction déjà remise en banque → remboursement
            $type = (isset($status['REMISE']) && $status['REMISE'] !== '')
                ? '00014'
                : '00005';

            return $this->callGaeOperation($type, $reference, $numappel, $numtrans, $amountCents);
        }

        // Fallback si TYPE=00017 inaccessible : essai annulation puis remboursement
        if ($this->callGaeOperation('00005', $reference, $numappel, $numtrans, $amountCents)) {
            return true;
        }

        return $this->callGaeOperation('00014', $reference, $numappel, $numtrans, $amountCents);
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
        return $this->callGaeOperation('00014', $reference, $numappel, $numtrans, $amountCents);
    }

    /**
     * Exécute une opération GAE CA (annulation ou remboursement).
     *
     * @param string $type        Type CA : '00005' (annulation) ou '00014' (remboursement)
     * @param string $reference   Référence commande
     * @param string $numappel    NUMAPPEL stocké lors du paiement
     * @param string $numtrans    NUMTRANS stocké lors du paiement
     * @param int    $amountCents Montant en centimes
     * @return bool true si CODEREPONSE=00000
     */
    private function callGaeOperation(
        string $type,
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

        $msg = implode('&', [
            'VERSION=00104',
            'TYPE='        . $type,
            'SITE='        . $site,
            'RANG='        . $rang,
            'NUMQUESTION=' . $numquestion,
            'MONTANT='     . (string) $amountCents,
            'DEVISE=978',
            'REFERENCE='   . $reference,
            'NUMAPPEL='    . $numappel,
            'NUMTRANS='    . $numtrans,
            'ACTIVITE=024',
            'DATEQ='       . date('dmY'),
            'HASH=SHA512',
        ]);

        $hmac       = $this->computeHmac($msg);
        $response   = $this->curlPost($gaeUrl, $msg . '&HMAC=' . $hmac);

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
