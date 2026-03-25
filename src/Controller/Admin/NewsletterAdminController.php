<?php

declare(strict_types=1);

namespace Controller\Admin;

use Core\Response;
use Model\AccountModel;
use Service\MailService;

class NewsletterAdminController extends AdminController
{
    private const PER_PAGE = 25;

    private AccountModel $accounts;

    public function __construct(\Core\Request $request)
    {
        parent::__construct($request);
        $this->accounts = new AccountModel();
    }

    // ----------------------------------------------------------------
    // GET /admin/newsletter
    // ----------------------------------------------------------------

    public function index(array $params): void
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

    public function send(array $params): void
    {
        $this->requireAdmin();

        if (!$this->verifyCsrf()) {
            $this->flash('error', 'Token CSRF invalide.');
            Response::redirect('/admin/newsletter');
        }

        $subject = trim($this->request->post('subject', ''));
        $body    = trim($this->request->post('body', ''));

        if ($subject === '' || $body === '') {
            $this->flash('error', 'Objet et contenu sont obligatoires.');
            Response::redirect('/admin/newsletter');
        }

        $all     = $this->accounts->getNewsletterSubscribers(10000, 0);
        $mailer  = new MailService();
        $sent    = 0;
        $failed  = 0;

        $htmlBody = $mailer->buildNewsletterHtml($subject, nl2br(htmlspecialchars($body, ENT_QUOTES)));

        foreach ($all as $sub) {
            $name = $sub['account_type'] === 'company'
                ? ($sub['company_name'] ?? '')
                : trim(($sub['firstname'] ?? '') . ' ' . ($sub['lastname'] ?? ''));
            try {
                $mailer->sendNewsletter($sub['email'], $name ?: 'Abonné', $subject, $htmlBody);
                $sent++;
            } catch (\Throwable) {
                $failed++;
            }
        }

        $msg = "{$sent} email(s) envoyé(s)";
        if ($failed > 0) {
            $msg .= ", {$failed} échec(s).";
        } else {
            $msg .= ' avec succès.';
        }

        $this->flash('success', $msg);
        Response::redirect('/admin/newsletter');
    }

}
