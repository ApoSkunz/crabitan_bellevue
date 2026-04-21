<?php

declare(strict_types=1);

namespace Controller;

use Core\Controller;
use Model\AccountModel;
use Model\RatingModel;
use Model\ReviewModel;

/**
 * Gère le formulaire d'avis client global déclenché depuis l'email post-achat J+21.
 *
 * Routes :
 *   GET  /{lang}/mon-avis         → show()    (affiche le formulaire via token)
 *   POST /{lang}/mon-avis         → submit()  (soumet l'avis)
 *   GET  /{lang}/mon-avis/merci   → success() (page de remerciement)
 */
class ReviewController extends Controller
{
    private const VIEW_FORM         = 'review-form';
    private const VIEW_ALREADY_DONE = 'review-already-done';
    private const VIEW_SUCCESS      = 'review-success';

    private RatingModel $ratingModel;
    private ReviewModel $reviewModel;
    private AccountModel $accountModel;

    /**
     * Initialise le contrôleur avec ses dépendances.
     *
     * @param \Core\Request $request Requête HTTP courante
     */
    public function __construct(\Core\Request $request)
    {
        parent::__construct($request);
        $this->ratingModel  = new RatingModel();
        $this->reviewModel  = new ReviewModel();
        $this->accountModel = new AccountModel();
    }

    // ----------------------------------------------------------------
    // GET /{lang}/mon-avis?token=...
    // ----------------------------------------------------------------

    /**
     * Affiche le formulaire d'avis si le token est valide et non expiré.
     *
     * @param array<string, string> $params Paramètres de route (lang)
     * @return void
     */
    public function show(array $params): void // NOSONAR php:S1142 — guard clauses légitimes (token vide / invalide / expiré / déjà évalué)
    {
        $lang  = $this->resolveLang($params);
        $token = trim($this->request->get('token', ''));

        if ($token === '') {
            $this->view(self::VIEW_FORM, [
                'lang'   => $lang,
                'error'  => __('review.invalid_token'),
                'token'  => '',
                'form'   => false,
            ]);
            return;
        }

        $resolved = $this->ratingModel->resolveOrderToken($token);

        if ($resolved === null) {
            $this->view(self::VIEW_FORM, [
                'lang'  => $lang,
                'error' => __('review.invalid_token'),
                'token' => '',
                'form'  => false,
            ]);
            return;
        }

        // Vérification expiry
        $tokenRow = $this->getOrderTokenRow($token);
        if ($tokenRow !== null && strtotime($tokenRow['expires_at']) < time()) {
            $this->view(self::VIEW_FORM, [
                'lang'  => $lang,
                'error' => __('review.expired_token'),
                'token' => '',
                'form'  => false,
            ]);
            return;
        }

        $accountId = (int) $resolved['account_id'];
        $orderId   = (int) $resolved['order_id'];

        // Déjà évalué ?
        if ($this->reviewModel->findByAccountId($accountId) !== null) {
            $this->view(self::VIEW_ALREADY_DONE, ['lang' => $lang]);
            return;
        }

        $account     = $this->accountModel->findById($accountId);
        $displayName = $this->buildDisplayName($account);

        if (!isset($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }

        $this->view(self::VIEW_FORM, [
            'lang'        => $lang,
            'error'       => null,
            'form'        => true,
            'token'       => htmlspecialchars($token, ENT_QUOTES),
            'displayName' => $displayName,
            'orderId'     => $orderId,
            'csrf'        => $_SESSION['csrf'],
        ]);
    }

    // ----------------------------------------------------------------
    // POST /{lang}/mon-avis
    // ----------------------------------------------------------------

    /**
     * Traite la soumission du formulaire d'avis.
     *
     * @param array<string, string> $params Paramètres de route (lang)
     * @return void
     */
    public function submit(array $params): void
    {
        $lang = $this->resolveLang($params);

        // CSRF
        $csrfPost = $this->request->post('csrf_token', '');
        if (!isset($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $csrfPost)) {
            $this->abort(403, 'Invalid CSRF token');
        }

        $token = trim($this->request->post('token', ''));

        if ($token === '') {
            $this->abort(400, 'Missing token');
        }

        $resolved = $this->ratingModel->resolveOrderToken($token);

        if ($resolved === null) {
            $this->abort(404, 'Invalid token');
        }

        // Vérification expiry
        $tokenRow = $this->getOrderTokenRow($token);
        if ($tokenRow !== null && strtotime($tokenRow['expires_at']) < time()) {
            $this->abort(410, 'Token expired');
        }

        $accountId = (int) $resolved['account_id'];
        $orderId   = (int) $resolved['order_id'];

        // Déjà évalué ?
        if ($this->reviewModel->findByAccountId($accountId) !== null) {
            $this->view(self::VIEW_ALREADY_DONE, ['lang' => $lang]);
            return;
        }

        // Validation rating
        $rating = (int) $this->request->post('rating', '0');
        if ($rating < 1 || $rating > 5) {
            $this->abort(422, 'Invalid rating');
        }

        // Validation et sanitisation commentaire
        $comment  = strip_tags(trim($this->request->post('comment', '')));
        $comment  = mb_substr($comment, 0, 500);

        // Validation et sanitisation location
        $location = strip_tags(trim($this->request->post('location', '')));
        $location = mb_substr($location, 0, 100);

        $account     = $this->accountModel->findById($accountId);
        $displayName = $this->buildDisplayName($account);

        $this->reviewModel->create($accountId, $orderId, $rating, $comment, $displayName, $location);

        $this->redirect('/' . $lang . '/mon-avis/merci');
    }

    // ----------------------------------------------------------------
    // GET /{lang}/mon-avis/merci
    // ----------------------------------------------------------------

    /**
     * Affiche la page de remerciement après soumission d'un avis.
     *
     * @param array<string, string> $params Paramètres de route (lang)
     * @return void
     */
    public function success(array $params): void
    {
        $lang = $this->resolveLang($params);
        $this->view(self::VIEW_SUCCESS, ['lang' => $lang]);
    }

    // ----------------------------------------------------------------
    // Méthodes privées
    // ----------------------------------------------------------------

    /**
     * Récupère la ligne complète d'un order token (avec expires_at).
     *
     * @param string $token Token de commande
     * @return array<string, mixed>|null
     */
    private function getOrderTokenRow(string $token): ?array
    {
        return $this->ratingModel->resolveOrderTokenFull($token);
    }

    /**
     * Génère le display_name à partir du compte (prénom + initiale nom).
     *
     * @param array<string, mixed>|false $account Ligne compte (avec firstname / lastname)
     * @return string Display name formaté, ex. « Sophie D. »
     */
    private function buildDisplayName(array|false $account): string // NOSONAR php:S1142
    {
        if ($account === false) {
            return 'Client';
        }

        $firstname = trim((string) ($account['firstname'] ?? ''));
        $lastname  = trim((string) ($account['lastname']  ?? ''));

        if ($firstname === '') {
            return $lastname !== '' ? $lastname : 'Client';
        }

        if ($lastname !== '') {
            return $firstname . ' ' . strtoupper(substr($lastname, 0, 1)) . '.';
        }

        return $firstname;
    }
}
