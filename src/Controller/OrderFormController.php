<?php

declare(strict_types=1);

namespace Controller;

use Core\Controller;
use Core\Response;
use Middleware\AdminMiddleware;
use Model\OrderFormModel;

class OrderFormController extends Controller
{
    private const STORAGE_DIR = ROOT_PATH . '/storage/order_forms';

    // ----------------------------------------------------------------
    // GET /bons-de-commande/{id}/telecharger  (admin uniquement)
    // ----------------------------------------------------------------

    /**
     * Télécharge le bon de commande PDF identifié par $params['id'].
     * Accès restreint aux administrateurs — le PDF est transmis aux clients
     * uniquement par email (pièce jointe), jamais via un lien public.
     *
     * @param array<string, string> $params Paramètres de route (id requis)
     * @return void
     */
    public function download(array $params): void
    {
        $this->requireAdminAccess();

        $id   = (int) ($params['id'] ?? 0);
        $form = (new OrderFormModel())->findByIdOrNull($id);

        if ($form === null) {
            Response::abort(404);
        }

        $path = self::STORAGE_DIR . '/' . $form['filename'];
        if (!is_file($path)) {
            Response::abort(404);
        }

        $label    = $form['label'] !== null ? ' ' . $form['label'] : '';
        $download = 'Bon_commande_' . $form['year'] . $label . '.pdf';

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $download . '"');
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: private, no-store');
        readfile($path);
    }

    /**
     * Vérifie que l'appelant est administrateur (seam de testabilité).
     *
     * @return void
     */
    protected function requireAdminAccess(): void
    {
        AdminMiddleware::handle();
    }
}
