<?php

declare(strict_types=1);

namespace Controller\Admin;

use Core\Response;
use Model\AccountModel;
use Model\NewsletterModel;
use Service\MailService;

/**
 * Gestion de la newsletter : liste abonnés, envoi de campagne, historique.
 */
class NewsletterAdminController extends AdminController
{
    private const ADMIN_URL      = '/admin/newsletter';
    private const PER_PAGE       = 25;
    private const HISTORY_PER_PAGE = 10;
    private const ATTACHMENT_DIR = 'storage/newsletters/attachments/';

    private AccountModel $accounts;
    private NewsletterModel $newsletters;

    public function __construct(\Core\Request $request)
    {
        parent::__construct($request);
        $this->accounts    = new AccountModel();
        $this->newsletters = new NewsletterModel();
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

        $historyPage  = max(1, (int) $this->request->get('hpage', 1));
        $historyTotal = $this->newsletters->count();
        $history      = $this->newsletters->getAll(
            self::HISTORY_PER_PAGE,
            ($historyPage - 1) * self::HISTORY_PER_PAGE
        );

        $this->view('admin/newsletter/index', [
            'adminUser'     => $adminUser,
            'adminSection'  => 'newsletter',
            'pageTitle'     => 'Newsletter',
            'breadcrumbs'   => [['label' => 'Admin', 'url' => '/admin'], ['label' => 'Newsletter']],
            'subscribers'   => $subscribers,
            'total'         => $total,
            'page'          => $page,
            'perPage'       => self::PER_PAGE,
            'history'       => $history,
            'historyTotal'  => $historyTotal,
            'historyPage'   => $historyPage,
            'historyPages'  => $historyTotal > 0 ? (int) ceil($historyTotal / self::HISTORY_PER_PAGE) : 1,
            'flash'         => $this->getFlash('success'),
            'flashError'    => $this->getFlash('error'),
            'csrfToken'     => $_SESSION['csrf'] ?? '',
        ]);
    }

    // ----------------------------------------------------------------
    // GET /admin/newsletter/{id}
    // ----------------------------------------------------------------

    /**
     * Détail d'une campagne envoyée.
     *
     * @param array<string, string> $params
     */
    public function show(array $params): void
    {
        $adminUser  = $this->requireAdmin();
        $campaign   = $this->newsletters->findCampaignById((int) $params['id']);

        if (!$campaign) {
            $this->abort(404, 'Campagne introuvable');
        }

        $this->view('admin/newsletter/show', [
            'adminUser'    => $adminUser,
            'adminSection' => 'newsletter',
            'pageTitle'    => 'Campagne — ' . htmlspecialchars($campaign['subject']),
            'breadcrumbs'  => [
                ['label' => 'Admin', 'url' => '/admin'],
                ['label' => 'Newsletter', 'url' => self::ADMIN_URL],
                ['label' => '#' . $campaign['id']],
            ],
            'campaign' => $campaign,
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

        // Persister la campagne avant l'envoi
        $campaignId = $this->newsletters->create($subject, $body, $imageUrl);

        // Gérer la pièce jointe PDF — copie permanente pour l'historique
        $pdfPath    = null;
        $pdfName    = null;
        $attachment = $this->storeNewsletterPdf($_FILES['nl_pdf'] ?? [], $campaignId);
        if ($attachment !== null) {
            $pdfPath = $attachment['tmp_path'];
            $pdfName = $attachment['original_name'];
        }

        $all         = $this->accounts->getNewsletterSubscribers(10000, 0);
        $mailer      = new MailService();
        $sent        = 0;
        $failed      = 0;
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

        // Nettoyage du fichier temporaire (la copie permanente reste dans storage/)
        if ($pdfPath !== null && file_exists($pdfPath)) {
            unlink($pdfPath);
        }

        $this->newsletters->updateStats($campaignId, $sent, $failed);

        $msg = "{$sent} email(s) envoyé(s)";
        $msg .= $failed > 0 ? ", {$failed} échec(s)." : ' avec succès.';

        $this->flash('success', $msg);
        Response::redirect(self::ADMIN_URL);
    }

    // ----------------------------------------------------------------
    // Helpers privés
    // ----------------------------------------------------------------

    /**
     * Stocke le PDF en emplacement permanent et retourne les chemins nécessaires.
     * La copie temporaire (tmp_path) est utilisée pour l'envoi, puis supprimée.
     * La copie permanente reste dans storage/newsletters/attachments/.
     *
     * @param  array<string, mixed> $file
     * @param  int                  $campaignId
     * @return array{tmp_path: string, original_name: string}|null
     */
    private function storeNewsletterPdf(array $file, int $campaignId): ?array // NOSONAR php:S1142
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

        $originalName = basename($file['name'] ?? 'newsletter.pdf');
        $storedName   = 'nl_' . $campaignId . '_' . bin2hex(random_bytes(6)) . '.pdf';

        // Copie permanente
        $permanentDir  = ROOT_PATH . '/' . self::ATTACHMENT_DIR;
        if (!is_dir($permanentDir)) {
            mkdir($permanentDir, 0750, true);
        }
        $permanentPath = $permanentDir . $storedName;
        copy($file['tmp_name'], $permanentPath);

        $this->newsletters->saveAttachment($campaignId, $originalName, self::ATTACHMENT_DIR . $storedName);

        // Copie temporaire pour l'envoi mail
        $tmpPath = sys_get_temp_dir() . '/nl_pdf_' . bin2hex(random_bytes(8)) . '.pdf';
        copy($file['tmp_name'], $tmpPath);

        return ['tmp_path' => $tmpPath, 'original_name' => $originalName];
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
