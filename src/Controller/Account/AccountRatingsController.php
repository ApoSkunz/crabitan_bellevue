<?php

declare(strict_types=1);

namespace Controller\Account;

use Model\RatingModel;
use Model\ReviewModel;
use Repository\MysqlUserRepository;
use Repository\UserRepository;

/**
 * Gère la page "Mes notes" et "Mon avis" de l'espace client.
 *
 * Permet de modifier les notes par vin (vins commandés uniquement)
 * et de créer/modifier l'avis global sur l'expérience d'achat.
 */
class AccountRatingsController extends AbstractAccountController
{
    private RatingModel $ratings;
    private ReviewModel $reviews;
    private UserRepository $userRepo;

    /**
     * @param \Core\Request       $request  Requête HTTP courante
     * @param UserRepository|null $userRepo Dépôt utilisateur (null → MysqlUserRepository)
     */
    public function __construct(\Core\Request $request, ?UserRepository $userRepo = null)
    {
        parent::__construct($request);
        $this->ratings  = new RatingModel();
        $this->reviews  = new ReviewModel();
        $this->userRepo = $userRepo ?? new MysqlUserRepository(new \Model\AccountModel());
    }

    // ----------------------------------------------------------------
    // GET /{lang}/mon-compte/mes-notes
    // ----------------------------------------------------------------

    /**
     * Affiche tous les vins commandés par le compte avec leur note actuelle.
     *
     * @param array<string, string> $params Paramètres de route (lang)
     * @return void
     */
    public function ratings(array $params): void
    {
        $payload = $this->requireCustomer();
        $userId  = (int) $payload['sub'];
        $lang    = $params['lang'];

        $this->view('account/ratings', [
            'lang'    => $lang,
            'account' => $this->userRepo->findById($userId),
            'wines'   => $this->ratings->getOrderedWinesWithRating($userId),
        ]);
    }

    // ----------------------------------------------------------------
    // POST /{lang}/mon-compte/mes-notes
    // ----------------------------------------------------------------

    /**
     * Met à jour la note d'un vin commandé.
     *
     * @param array<string, string> $params Paramètres de route (lang)
     * @return void
     */
    public function updateRating(array $params): void
    {
        $payload = $this->requireCustomer();
        $userId  = (int) $payload['sub'];
        $lang    = $params['lang'];

        if (!$this->verifyCsrf()) {
            $this->redirect('/' . $lang . '/mon-compte/mes-notes');
        }

        $wineId = (int) $this->request->post('wine_id', '0');
        $rating = (int) $this->request->post('rating', '0');

        if ($wineId > 0 && $rating >= 1 && $rating <= 5
            && $this->ratings->hasOrdered($userId, $wineId)
        ) {
            $this->ratings->upsert($userId, $wineId, 0, $rating);
        }

        $this->redirect('/' . $lang . '/mon-compte/mes-notes');
    }

    // ----------------------------------------------------------------
    // GET /{lang}/mon-compte/mon-avis
    // ----------------------------------------------------------------

    /**
     * Affiche le formulaire d'avis global (pré-rempli si avis existant).
     *
     * @param array<string, string> $params Paramètres de route (lang)
     * @return void
     */
    public function review(array $params): void
    {
        $payload = $this->requireCustomer();
        $userId  = (int) $payload['sub'];
        $lang    = $params['lang'];

        $account = $this->userRepo->findById($userId);
        $review  = $this->reviews->findByAccountId($userId);

        $this->view('account/review', [
            'lang'    => $lang,
            'account' => $account,
            'review'  => $review,
        ]);
    }

    // ----------------------------------------------------------------
    // POST /{lang}/mon-compte/mon-avis
    // ----------------------------------------------------------------

    /**
     * Crée ou met à jour l'avis global du compte.
     *
     * @param array<string, string> $params Paramètres de route (lang)
     * @return void
     */
    public function updateReview(array $params): void
    {
        $payload = $this->requireCustomer();
        $userId  = (int) $payload['sub'];
        $lang    = $params['lang'];

        if (!$this->verifyCsrf()) {
            $this->redirect('/' . $lang . '/mon-compte/mon-avis');
        }

        $rating   = (int) $this->request->post('rating', '0');
        $comment  = trim((string) $this->request->post('comment', ''));
        $location = trim(strip_tags((string) $this->request->post('location', '')));

        if ($rating < 1 || $rating > 5) {
            $this->redirect('/' . $lang . '/mon-compte/mon-avis');
        }

        $account     = $this->userRepo->findById($userId);
        $firstname   = trim((string) ($account['firstname'] ?? ''));
        $lastname    = trim((string) ($account['lastname']  ?? ''));
        $displayName = $firstname !== '' && $lastname !== ''
            ? $firstname . ' ' . strtoupper(substr($lastname, 0, 1)) . '.'
            : ($firstname ?: 'Client');

        $this->reviews->upsert($userId, $rating, $comment, $displayName, $location);

        $this->redirect('/' . $lang . '/mon-compte/mon-avis?saved=1');
    }
}
