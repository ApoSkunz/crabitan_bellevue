<?php

declare(strict_types=1);

namespace Controller\Account;

use Core\Response;
use Model\AccountModel;
use Model\FavoriteModel;
use Repository\MysqlUserRepository;
use Repository\UserRepository;

/**
 * Gère les préférences du compte : favoris, newsletter (désabonnement), langue.
 */
class AccountPreferenceController extends AbstractAccountController
{
    private const VIEW_UNSUBSCRIBE = 'account/unsubscribe';

    private UserRepository $userRepo;
    /** AccountModel conservé pour les méthodes hors interface (unsubscribeByToken). */
    private AccountModel $accounts;
    private FavoriteModel $favorites;

    /**
     * Initialise le contrôleur et ses dépendances.
     *
     * @param \Core\Request       $request  Requête HTTP courante
     * @param UserRepository|null $userRepo Dépôt utilisateur (null → MysqlUserRepository par défaut)
     */
    public function __construct(\Core\Request $request, ?UserRepository $userRepo = null)
    {
        parent::__construct($request);
        $this->accounts  = new AccountModel();
        $this->userRepo  = $userRepo ?? new MysqlUserRepository($this->accounts);
        $this->favorites = new FavoriteModel();
    }

    // ----------------------------------------------------------------
    // GET /{lang}/mon-compte/favoris
    // ----------------------------------------------------------------

    /**
     * Affiche la page des vins favoris du compte.
     *
     * @param array<string, string> $params Paramètres de route (lang)
     * @return void
     */
    public function favorites(array $params): void
    {
        $payload = $this->requireCustomer();
        $userId  = (int) $payload['sub'];
        $lang    = $params['lang'];

        $this->view('account/favorites', [
            'lang'      => $lang,
            'account'   => $this->userRepo->findById($userId),
            'favorites' => $this->favorites->getByUser($userId),
        ]);
    }

    // ----------------------------------------------------------------
    // GET /{lang}/newsletter/desabonnement?token=xxx
    // ----------------------------------------------------------------

    /**
     * Affiche la page de confirmation de désabonnement newsletter.
     *
     * @param array<string, string> $params Paramètres de route (lang)
     * @return void
     */
    public function unsubscribePage(array $params): void
    {
        $lang  = $params['lang'];
        $token = $this->request->get('token', '');
        $cid   = (int) $this->request->get('cid', 0);

        $unsubMeta = $lang === 'en'
            ? 'Unsubscribe from the Château Crabitan Bellevue newsletter.'
            : 'Se désabonner de la newsletter du Château Crabitan Bellevue.';

        if ($token === '' || !$this->userRepo->findByUnsubscribeToken($token)) {
            $this->view(self::VIEW_UNSUBSCRIBE, [
                'lang'            => $lang,
                'metaDescription' => $unsubMeta,
                'noindex'         => true,
                'success'         => false,
                'confirm'         => false,
            ]);
            return;
        }

        $this->view(self::VIEW_UNSUBSCRIBE, [
            'lang'            => $lang,
            'metaDescription' => $unsubMeta,
            'noindex'         => true,
            'success'         => false,
            'confirm'         => true,
            'unsubToken' => $token,
            'campaignId' => $cid > 0 ? $cid : null,
        ]);
    }

    // ----------------------------------------------------------------
    // POST /{lang}/newsletter/desabonnement
    // ----------------------------------------------------------------

    /**
     * Effectue le désabonnement newsletter via le token one-click.
     *
     * @param array<string, string> $params Paramètres de route (lang)
     * @return void
     */
    public function unsubscribe(array $params): void
    {
        $lang       = $params['lang'];
        $unsubMeta  = $lang === 'en'
            ? 'Unsubscribe from the Château Crabitan Bellevue newsletter.'
            : 'Se désabonner de la newsletter du Château Crabitan Bellevue.';
        $unsubToken = $this->request->post('unsub_token', '');
        $campaignId = (int) $this->request->post('campaign_id', 0);
        $account    = $unsubToken !== '' ? $this->userRepo->findByUnsubscribeToken($unsubToken) : false;
        $success    = false;

        if ($account !== false) {
            $this->accounts->unsubscribeByToken($unsubToken);
            if ($campaignId > 0) {
                (new \Model\NewsletterTrackingModel())->recordUnsubscribe($campaignId, (int) $account['id']);
            }
            $success = true;
        }

        $this->view(self::VIEW_UNSUBSCRIBE, [
            'lang'            => $lang,
            'metaDescription' => $unsubMeta,
            'success'         => $success,
            'confirm'         => false,
        ]);
    }
}
