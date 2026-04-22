<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use Core\Exception\HttpException;
use Core\Jwt;
use Service\MailService;
use Service\PaymentService;

/**
 * Sous-classe testable d'AccountController (legacy).
 *
 * AccountController a requireCustomer() et verifyCsrf() en PRIVATE — on ne peut
 * pas les surcharger par héritage. La stratégie ici est donc :
 *  - Fournir un JWT signé valide dans $_COOKIE['auth_token']
 *  - La Database mockée retourne une "session active" pour le middleware
 *  - La Database mockée satisfait aussi requireIndividual (findById → individual)
 *  - verifyCsrf() est simulé via $_SESSION['csrf'] + $_POST['csrf_token'] identiques
 *
 * Pour les tests qui nécessitent CSRF invalide, on laisse les clés absentes.
 *
 * Les seams protégés (view, abort, buildExportData…) sont surchargés normalement.
 */
class TestableAccountController extends \Controller\AccountController
{
    public ?string $lastView     = null;
    public ?array $lastViewData  = null;

    /** JWT généré par bootAuth() — utilisable par les tests qui comparent le token courant. */
    public ?string $authToken    = null;

    /**
     * true  → CSRF valide (injecte les clés en session + POST)
     * false → CSRF invalide (ne pose pas les clés)
     */
    public bool $csrfValid = true;

    /** @var MailService|null */
    public ?MailService $fakeMailService = null;

    /** @var PaymentService|null */
    public ?PaymentService $fakePaymentService = null;

    /** @var \Service\RefundService|null */
    public ?\Service\RefundService $fakeRefundService = null;

    /** @var array<string,mixed>|null */
    public ?array $fakeExportData = null;

    /** @var array{0:string,1:bool}|null */
    public ?array $fakeExportContent = null;

    /** Capture les arguments du dernier appel à sendPdfResponse(). */
    public ?string $lastPdfFilename = null;

    /**
     * Configure la session + les cookies pour simuler un utilisateur authentifié.
     * Appelé par makeController() dans les tests.
     */
    public function bootAuth(int $userId = 1, string $role = 'customer'): void
    {
        $_ENV['JWT_SECRET'] = $_ENV['JWT_SECRET'] ?? 'test-secret-key-32-chars-long!!!';

        $token = Jwt::generate($userId, $role, 7200);
        $_COOKIE['auth_token'] = $token;
        $this->authToken       = $token;
    }

    /**
     * Prépare le CSRF selon $this->csrfValid.
     * À appeler dans les tests POST avant d'invoquer la méthode.
     */
    public function prepareCsrf(): void
    {
        if ($this->csrfValid) {
            $_SESSION['csrf']        = 'test-csrf-token';
            $_POST['csrf_token']     = 'test-csrf-token';
        } else {
            unset($_SESSION['csrf'], $_POST['csrf_token']);
        }
    }

    protected function view(string $tpl, array $data = [], int $httpStatus = 200): void
    {
        $this->lastView     = $tpl;
        $this->lastViewData = $data;
    }

    protected function abort(int $status = 404, string $message = 'Not Found'): never
    {
        throw new HttpException($status, $message);
    }

    protected function newMailService(): MailService
    {
        return $this->fakeMailService ?? parent::newMailService();
    }

    protected function newPaymentService(): PaymentService
    {
        return $this->fakePaymentService ?? parent::newPaymentService();
    }

    protected function newRefundService(): \Service\RefundService
    {
        return $this->fakeRefundService ?? parent::newRefundService();
    }

    protected function sendPdfResponse(string $pdfBytes, string $filename): never
    {
        $this->lastPdfFilename = $filename;
        throw new HttpException(200, 'pdf-sent');
    }

    protected function buildExportData(int $userId): array
    {
        return $this->fakeExportData ?? [];
    }

    protected function buildExportContent(array $export, string $date, string $basename): array
    {
        return $this->fakeExportContent ?? ['{}', false];
    }
}
