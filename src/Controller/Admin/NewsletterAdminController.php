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

        $appUrl  = rtrim($_ENV['APP_URL'] ?? '', '/');
        $htmlBody = $this->newsletterLayout($subject, nl2br(htmlspecialchars($body, ENT_QUOTES)), $appUrl);

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

    private function newsletterLayout(string $title, string $body, string $appUrl): string
    {
        $safeTitle = htmlspecialchars($title, ENT_QUOTES);
        $year      = date('Y');
        $urlUnsub  = $appUrl . '/fr/mon-compte';

        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>{$safeTitle}</title>
</head>
<body style="margin:0;padding:0;background:#f5f0e8;font-family:Georgia,serif;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
         style="background:#f5f0e8;padding:40px 16px;">
    <tr><td align="center">
      <table role="presentation" width="600" cellpadding="0" cellspacing="0"
             style="background:#fff;border-radius:6px;overflow:hidden;max-width:600px;width:100%;">
        <tr>
          <td style="background:#1a1208;padding:28px 32px;text-align:center;">
            <span style="font-family:Georgia,serif;font-size:1.2rem;color:#c9a84c;letter-spacing:0.18em;
                         text-transform:uppercase;">Château Crabitan Bellevue</span>
          </td>
        </tr>
        <tr>
          <td style="padding:32px 40px;color:#2c2418;font-size:1rem;line-height:1.7;">
            <h2 style="font-family:Georgia,serif;color:#1a1208;font-size:1.15rem;margin-bottom:1.2rem;">
              {$safeTitle}
            </h2>
            {$body}
          </td>
        </tr>
        <tr>
          <td style="background:#f5f0e8;padding:20px 40px;text-align:center;
                     font-size:0.75rem;color:#8a7a60;">
            &copy; {$year} Château Crabitan Bellevue &mdash;
            <a href="{$urlUnsub}" style="color:#c9a84c;">Gérer mes préférences</a>
          </td>
        </tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;
    }
}
