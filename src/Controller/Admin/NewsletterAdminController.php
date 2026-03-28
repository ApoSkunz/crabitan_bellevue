<?php

declare(strict_types=1);

namespace Controller\Admin;

use Core\Response;
use Model\AccountModel;
use Service\MailService;

class NewsletterAdminController extends AdminController
{
    private const ADMIN_URL = '/admin/newsletter';
    private const PER_PAGE  = 25;

    private AccountModel $accounts;

    public function __construct(\Core\Request $request)
    {
        parent::__construct($request);
        $this->accounts = new AccountModel();
    }

    // ----------------------------------------------------------------
    // GET /admin/newsletter
    // ----------------------------------------------------------------

    public function index(array $_params): void // NOSONAR — php:S1172 : signature imposée par le routeur MVC
    {
        $adminUser   = $this->requireAdmin();
        $page        = max(1, (int) $this->request->get('page', 1));
        $total       = $this->accounts->countNewsletterSubscribers();
        $subscribers = $this->accounts->getNewsletterSubscribers(
            self::PER_PAGE,
            ($page - 1) * self::PER_PAGE
        );

        $this->view('admin/newsletter/index', [
            'adminUser'    => $adminUser,
            'adminSection' => 'newsletter',
            'pageTitle'    => 'Newsletter',
            'breadcrumbs'  => [['label' => 'Admin', 'url' => '/admin'], ['label' => 'Newsletter']],
            'subscribers'  => $subscribers,
            'total'        => $total,
            'page'         => $page,
            'perPage'      => self::PER_PAGE,
            'flash'        => $this->getFlash('success'),
            'flashError'   => $this->getFlash('error'),
            'csrfToken'    => $_SESSION['csrf'] ?? '',
        ]);
    }

    // ----------------------------------------------------------------
    // POST /admin/newsletter/envoyer
    // ----------------------------------------------------------------

    public function send(array $_params): void // NOSONAR — php:S1172 : signature imposée par le routeur MVC
    {
        $this->requireAdmin();

        if (!$this->verifyCsrf()) {
            $this->flash('error', 'Token CSRF invalide.');
            Response::redirect(self::ADMIN_URL);
        }

        $subject = trim($this->request->post('subject', ''));
        $body    = trim($this->request->post('body', ''));

        if ($subject === '' || $body === '') {
            $this->flash('error', 'Objet et contenu sont obligatoires.');
            Response::redirect(self::ADMIN_URL);
        }

        $allowed  = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $appUrl   = rtrim($_ENV['APP_URL'] ?? '', '/');
        $destDir  = ROOT_PATH . '/public/assets/images/newsletter/';
        $imageUrl = $this->uploadNewsletterImage($_FILES['nl_image'] ?? [], $allowed, $destDir, $appUrl);

        $pdf     = $this->uploadNewsletterPdf($_FILES['nl_pdf'] ?? []);
        $pdfPath = $pdf !== null ? $pdf['path'] : null;
        $pdfName = $pdf !== null ? $pdf['name'] : null;

        $all    = $this->accounts->getNewsletterSubscribers(10000, 0);
        $mailer = new MailService();
        $sent   = 0;
        $failed = 0;

        $safeContent = nl2br(htmlspecialchars($body, ENT_QUOTES));

        foreach ($all as $sub) {
            $name     = $sub['account_type'] === 'company'
                ? ($sub['company_name'] ?? '')
                : trim(($sub['firstname'] ?? '') . ' ' . ($sub['lastname'] ?? ''));
            $token    = $sub['newsletter_unsubscribe_token'] ?? null;
            $htmlBody = $mailer->buildNewsletterHtml($subject, $safeContent, $imageUrl, $token);
            try {
                $mailer->sendNewsletter($sub['email'], $name ?: 'Abonné', $subject, $htmlBody, $pdfPath, $pdfName);
                $sent++;
            } catch (\Throwable) {
                $failed++;
            }
        }

        if ($pdfPath !== null && file_exists($pdfPath)) {
            unlink($pdfPath);
        }

        $msg = "{$sent} email(s) envoyé(s)";
        if ($failed > 0) {
            $msg .= ", {$failed} échec(s).";
        } else {
            $msg .= ' avec succès.';
        }

        $this->flash('success', $msg);
        Response::redirect(self::ADMIN_URL);
    }

    /**
     * @param array<string, mixed> $file
     * @return array{path: string, name: string}|null
     */
    private function uploadNewsletterPdf(array $file): ?array // NOSONAR php:S1142 — retours anticipés de validation intentionnels
    {
        if (empty($file['tmp_name'])) {
            return null;
        }
        $finfo = new \finfo(\FILEINFO_MIME_TYPE);
        if ($finfo->file($file['tmp_name']) !== 'application/pdf') {
            return null;
        }
        if (($file['size'] ?? 0) > 10 * 1024 * 1024) {
            return null;
        }
        $dest = sys_get_temp_dir() . '/nl_pdf_' . bin2hex(random_bytes(8)) . '.pdf';
        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            return null;
        }
        return ['path' => $dest, 'name' => basename($file['name'] ?? 'newsletter.pdf')];
    }

    /**
     * @param array<string, mixed> $file
     * @param array<string, string> $allowed
     */
    private function uploadNewsletterImage( // NOSONAR — php:S1142 : early returns validation MIME/upload intentionnels
        array $file,
        array $allowed,
        string $destDir,
        string $appUrl
    ): ?string {
        if (empty($file['tmp_name'])) {
            return null;
        }
        $finfo    = new \finfo(\FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
        if (!isset($allowed[$mimeType])) {
            return null;
        }
        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }
        $filename = 'nl_' . bin2hex(random_bytes(8)) . '.' . $allowed[$mimeType];
        if (!move_uploaded_file($file['tmp_name'], $destDir . $filename)) {
            return null;
        }
        return $appUrl . '/assets/images/newsletter/' . $filename;
    }
}
