<?php

declare(strict_types=1);

namespace Controller\Admin;

use Core\Response;
use Model\AccountModel;
use Model\NewsletterModel;
use Model\NewsletterTrackingModel;
use Service\AuditLogger;
use Service\TranslationService;

/**
 * Gestion de la newsletter : liste abonnés, envoi de campagne, historique.
 */
class NewsletterAdminController extends AdminController
{
    private const ADMIN_URL      = '/admin/newsletter';
    private const PER_PAGE                = 25;
    private const HISTORY_PER_PAGE        = 10;
    private const ALLOWED_HISTORY_PER_PAGE = [10, 25, 50];
    private const ALLOWED_SEGMENTS = ['tous', 'particuliers', 'professionnels', 'commandes', 'sans-commande', 'inactifs', 'non-inscrit'];
    private const ATTACHMENT_DIR = 'storage/newsletters/attachments/';

    private AccountModel $accounts;
    private NewsletterModel $newsletters;

    /**
     * Initialise le contrôleur avec les modèles nécessaires à la gestion newsletter.
     *
     * @param \Core\Request $request Requête HTTP courante
     */
    public function __construct(\Core\Request $request)
    {
        parent::__construct($request);
        $this->accounts    = new AccountModel();
        $this->newsletters = new NewsletterModel();
    }

    // ----------------------------------------------------------------
    // GET /admin/newsletter
    // ----------------------------------------------------------------

    /**
     * Affiche la liste des abonnés et l'historique des campagnes.
     *
     * @param array<string, mixed> $_params Paramètres de route (non utilisés)
     * @return void
     */
    public function index(array $_params): void // NOSONAR — php:S1172 : signature imposée par le routeur MVC
    {
        $adminUser   = $this->requireAdmin();
        $page        = max(1, (int) $this->request->get('page', 1));
        $total       = $this->accounts->countNewsletterSubscribers();
        $subscribers = $this->accounts->getNewsletterSubscribers(
            self::PER_PAGE,
            ($page - 1) * self::PER_PAGE
        );

        $historyPage    = max(1, (int) $this->request->get('hpage', 1));
        $historyPerPage = (int) $this->request->get('hperpage', self::HISTORY_PER_PAGE);
        if (!in_array($historyPerPage, self::ALLOWED_HISTORY_PER_PAGE, true)) {
            $historyPerPage = self::HISTORY_PER_PAGE;
        }
        $historyTotal = $this->newsletters->count();
        $history      = $this->newsletters->getAll(
            $historyPerPage,
            ($historyPage - 1) * $historyPerPage
        );

        $this->view('admin/newsletter/index', [
            'adminUser'      => $adminUser,
            'adminSection'   => 'newsletter',
            'pageTitle'      => 'Newsletter',
            'breadcrumbs'    => [['label' => 'Admin', 'url' => '/admin'], ['label' => 'Newsletter']],
            'subscribers'    => $subscribers,
            'total'          => $total,
            'page'           => $page,
            'perPage'        => self::PER_PAGE,
            'history'        => $history,
            'historyTotal'   => $historyTotal,
            'historyPage'    => $historyPage,
            'historyPerPage' => $historyPerPage,
            'historyPages'   => $historyTotal > 0 ? (int) ceil($historyTotal / $historyPerPage) : 1,
            'flash'         => $this->getFlash('success'),
            'flashError'    => $this->getFlash('error'),
            'csrfToken'     => $_SESSION['csrf'] ?? '',
        ]);
    }

    // ----------------------------------------------------------------
    // GET /admin/newsletter/segment-count
    // ----------------------------------------------------------------

    /**
     * Retourne en JSON le nombre d'abonnés correspondant au segment demandé.
     *
     * @param array<string, string> $_params
     */
    public function segmentCount(array $_params): void // NOSONAR — php:S1172 : signature imposée par le routeur MVC
    {
        $this->requireAdmin();

        $segment = $this->request->get('segment', 'tous');
        if (!in_array($segment, self::ALLOWED_SEGMENTS, true)) {
            $segment = 'tous';
        }

        $count = $this->accounts->countNewsletterSubscribersBySegment($segment);
        $this->json(['count' => $count]);
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

        $trackingModel = new NewsletterTrackingModel();
        $stats         = $trackingModel->getStats((int) $campaign['id']);
        $campaign['opens_count']         = (int) $stats['opens_count'];
        $campaign['clicks_count']        = (int) $stats['clicks_count'];
        $campaign['unique_clicks_count'] = (int) $stats['unique_clicks_count'];
        $campaign['unsubscribes_count']  = (int) $stats['unsubscribes_count'];

        $this->view('admin/newsletter/show', [
            'adminUser'    => $adminUser,
            'adminSection' => 'newsletter',
            'pageTitle'    => 'Campagne — ' . htmlspecialchars($campaign['subject']),
            'breadcrumbs'  => [
                ['label' => 'Admin', 'url' => '/admin'],
                ['label' => 'Newsletter', 'url' => self::ADMIN_URL],
                ['label' => '#' . $campaign['id']],
            ],
            'campaign'   => $campaign,
            'linkStats'  => $trackingModel->getLinkStats((int) $campaign['id']),
        ]);
    }

    // ----------------------------------------------------------------
    // POST /admin/newsletter/envoyer
    // ----------------------------------------------------------------

    /**
     * Traite l'envoi d'une campagne newsletter (POST).
     *
     * @param array<string, mixed> $_params Paramètres de route (non utilisés)
     * @return void
     */
    public function send(array $_params): void // NOSONAR — php:S1172 : signature imposée par le routeur MVC
    {
        $adminUser = $this->requireAdmin();

        if (!$this->verifyCsrf()) {
            $this->flash('error', 'Token CSRF invalide.');
            Response::redirect(self::ADMIN_URL);
        }

        $subject = trim($this->request->post('subject', ''));
        $title   = trim($this->request->post('nl_title', ''));
        $body    = trim($this->request->post('body', ''));
        $segment = $this->request->post('segment', 'tous');
        if (!in_array($segment, self::ALLOWED_SEGMENTS, true)) {
            $segment = 'tous';
        }

        if ($subject === '' || $body === '') {
            $this->flash('error', 'Objet et contenu sont obligatoires.');
            Response::redirect(self::ADMIN_URL);
        }

        // Préfixe branding uniquement — "Newsletter" retiré de l'objet (délivrabilité + spam)
        $subjectLine = 'Château Crabitan Bellevue — ' . $subject;

        // Traduction FR → EN via DeepL (une seule fois, stockée en BDD pour le cron)
        $translator    = new TranslationService($_ENV['TRANSLATION_API_KEY'] ?? '');
        $subjectLineEn = 'Château Crabitan Bellevue — ' . $translator->translate($subject);
        $titleEn       = $title !== '' ? $translator->translate($title) : '';
        $bodyEn        = $translator->translate($body);

        $allowed  = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $appUrl   = rtrim($_ENV['APP_URL'] ?? '', '/');
        $destDir  = ROOT_PATH . '/public/assets/images/newsletter/';
        $imageUrl = $this->uploadNewsletterImage($_FILES['nl_image'] ?? [], $allowed, $destDir, $appUrl);

        // Persister la campagne avec les traductions EN (utilisées par le cron)
        $segmentLabel = $segment !== 'tous' ? $segment : null;
        $campaignId   = $this->newsletters->create(
            $subjectLine,
            $title !== '' ? $title : null,
            $body,
            $imageUrl,
            $segmentLabel,
            $subjectLineEn,
            $titleEn,
            $bodyEn
        );

        // Pièce jointe PDF — copie permanente dans storage/ (chemin récupéré par le cron)
        $attachment = $this->storeNewsletterPdf($_FILES['nl_pdf'] ?? [], $campaignId);

        // Tracking : extraire les liens uniques des deux corps et les persister en BDD
        $trackingModel = new NewsletterTrackingModel();
        preg_match_all('/https?:\/\/[^\s<>"\']+/', $body, $matchesFr);
        $bodyEnForLinks = $bodyEn !== '' ? $bodyEn : $body;
        preg_match_all('/https?:\/\/[^\s<>"\']+/', $bodyEnForLinks, $matchesEn);
        foreach (array_unique(array_merge($matchesFr[0], $matchesEn[0])) as $url) {
            $trackingModel->saveLink($campaignId, $url);
        }

        // Enfile tous les abonnés — le cron envoie ensuite par batch (CRON_NEWSLETTER_BATCH_SIZE)
        $queueModel = new \Model\NewsletterSendQueueModel();
        $batchSize  = 1000;
        $offset     = 0;
        $queued     = 0;
        do {
            $batch   = $this->accounts->getNewsletterSubscribersBySegment($segment, $batchSize, $offset);
            $offset += $batchSize;
            foreach ($batch as $sub) {
                $personName = trim(($sub['firstname'] ?? '') . ' ' . ($sub['lastname'] ?? ''));
                $name       = $sub['account_type'] === 'company'
                    ? (string) ($sub['company_name'] ?? $personName)
                    : $personName;
                $name       = $name !== '' ? $name : (string) $sub['email'];
                $subLang    = in_array($sub['lang'] ?? '', ['fr', 'en'], true) ? (string) $sub['lang'] : 'fr';
                $token      = (string) ($sub['newsletter_unsubscribe_token'] ?? '');
                $queueModel->enqueue($campaignId, null, (string) $sub['email'], $name, $token, $subLang);
                $queued++;
            }
        } while (count($batch) === $batchSize);

        $this->newsletters->setQueuedCount($campaignId, $queued);

        AuditLogger::log(
            $adminUser['id'],
            'newsletter.queued',
            'newsletter',
            $campaignId,
            ['subject' => $subjectLine, 'queued' => $queued, 'segment' => $segment],
            AuditLogger::getIp()
        );

        $this->flash('success', "Campagne créée — {$queued} abonné(s) en file d'attente (envoi différé via cron).");
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
        if (!$this->moveUploadedFile($file['tmp_name'], $destDir . $filename)) {
            return null;
        }
        return $appUrl . '/assets/images/newsletter/' . $filename;
    }

    /**
     * Déplace un fichier uploadé vers sa destination finale.
     * Méthode protégée pour permettre le test via sous-classe.
     *
     * @param string $src  Chemin source du fichier temporaire
     * @param string $dest Chemin de destination
     * @return bool
     */
    protected function moveUploadedFile(string $src, string $dest): bool
    {
        return move_uploaded_file($src, $dest);
    }
}
