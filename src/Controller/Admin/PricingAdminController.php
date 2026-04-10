<?php

declare(strict_types=1);

namespace Controller\Admin;

use Core\Response;
use Model\PricingModel;
use Service\AuditLogger;

class PricingAdminController extends AdminController
{
    private PricingModel $pricing;

    /**
     * Initialise le contrôleur avec le modèle tarifs.
     *
     * @param \Core\Request $request Requête HTTP courante
     */
    public function __construct(\Core\Request $request)
    {
        parent::__construct($request);
        $this->pricing = new PricingModel();
    }

    // ----------------------------------------------------------------
    // GET /admin/tarifs
    // ----------------------------------------------------------------

    /**
     * Affiche la page de gestion des tarifs de livraison.
     *
     * @param array<string, mixed> $_params Paramètres de route (non utilisés)
     * @return void
     */
    public function index(array $_params): void // NOSONAR — php:S1172 : signature imposée par le routeur MVC
    {
        $adminUser = $this->requireAdmin();

        $this->view('admin/pricing/index', [
            'adminUser'    => $adminUser,
            'adminSection' => 'pricing',
            'pageTitle'    => 'Tarifs de livraison',
            'breadcrumbs'  => [['label' => 'Admin', 'url' => '/admin'], ['label' => 'Tarifs']],
            'rules'        => $this->pricing->getAll(),
            'csrfToken'    => $_SESSION['csrf'] ?? '',
            'flash'        => $this->getFlash('success'),
            'flashError'   => $this->getFlash('error'),
        ]);
    }

    // ----------------------------------------------------------------
    // POST /admin/tarifs
    // ----------------------------------------------------------------

    /**
     * Enregistre les modifications des règles tarifaires (POST).
     *
     * @param array<string, mixed> $_params Paramètres de route (non utilisés)
     * @return void
     */
    public function update(array $_params): void // NOSONAR — php:S1172 : signature imposée par le routeur MVC
    {
        $adminUser = $this->requireAdmin();

        if (!$this->verifyCsrf()) {
            $this->flash('error', 'Token CSRF invalide.');
            Response::redirect('/admin/tarifs');
        }

        $ids = $this->request->post('id', []);

        if (is_array($ids)) {
            foreach ($ids as $id) {
                $id = (int) $id;
                if ($id <= 0) {
                    continue;
                }
                $delivery   = (float) str_replace(',', '.', $this->request->post("delivery_{$id}", '0'));
                $withdrawal = (float) str_replace(',', '.', $this->request->post("withdrawal_{$id}", '0'));
                $labelFr    = trim($this->request->post("label_fr_{$id}", ''));
                $labelEn    = trim($this->request->post("label_en_{$id}", ''));
                $active     = $this->request->post("active_{$id}", '0') === '1';
                $minQty     = max(0, (int) $this->request->post("min_qty_{$id}", '0'));
                $maxQtyRaw  = $this->request->post("max_qty_{$id}", '');
                $maxQty     = ($maxQtyRaw !== '' && (int) $maxQtyRaw > 0) ? (int) $maxQtyRaw : null;

                $this->pricing->update($id, $delivery, $withdrawal, $labelFr, $labelEn, $active, $minQty, $maxQty);
            }
        }

        AuditLogger::log(
            $adminUser['id'],
            'pricing.updated',
            'pricing_rules',
            null,
            [],
            AuditLogger::getIp()
        );

        $this->flash('success', 'Tarifs mis à jour avec succès.');
        Response::redirect('/admin/tarifs');
    }
}
