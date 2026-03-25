<?php

declare(strict_types=1);

namespace Controller;

use Core\Controller;
use Model\OrderFormModel;
use Model\WineModel;
use Service\MailService;

class PageController extends Controller
{
    public function chateau(array $params): void
    {
        $lang = $this->resolveLang($params);
        $this->view('pages/chateau', ['lang' => $lang]);
    }

    public function savoirFaire(array $params): void
    {
        $lang = $this->resolveLang($params);
        $this->view('pages/savoir-faire', ['lang' => $lang]);
    }

    public function contact(array $params): void
    {
        $lang = $this->resolveLang($params);
        $this->view('pages/contact', [
            'lang'          => $lang,
            'csrf'          => $this->csrfToken(),
            'latestOrderForm' => (new OrderFormModel())->getLatest(),
        ]);
    }

    public function contactPost(array $params): void
    {
        $lang = $this->resolveLang($params);

        if (!$this->csrfValid()) {
            $this->json(['success' => false, 'message' => __('error.csrf')], 400);
        }

        $firstname = trim($this->request->post('firstname', ''));
        $lastname  = trim($this->request->post('lastname', ''));
        $email     = strtolower(trim($this->request->post('email', '')));
        $subject   = trim($this->request->post('subject', ''));
        $message   = trim($this->request->post('message', ''));
        $rgpd      = $this->request->post('rgpd', '');

        if (
            $firstname === '' || $lastname === ''
            || !filter_var($email, FILTER_VALIDATE_EMAIL)
            || $subject === '' || $message === ''
            || $rgpd !== '1'
        ) {
            $this->json(['success' => false, 'message' => __('contact.error_fields')], 422);
        }

        try {
            $mail = new MailService();
            $mail->sendContactToOwner($firstname, $lastname, $email, $subject, $message, $lang);
            $mail->sendContactConfirmation($email, $firstname, $subject, $lang);
            $this->json(['success' => true, 'message' => __('contact.success')]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'message' => __('contact.error_smtp')], 500);
        }
    }

    private function csrfToken(): string
    {
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }

    private function csrfValid(): bool
    {
        $token = $this->request->post('csrf_token', '');
        return isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
    }

    public function mentionsLegales(array $params): void
    {
        $lang = $this->resolveLang($params);
        $bare = isset($_GET['bare']);
        $this->view('pages/mentions-legales', ['lang' => $lang, 'noindex' => true, 'bare' => $bare]);
    }

    public function politiqueConfidentialite(array $params): void
    {
        $lang = $this->resolveLang($params);
        $this->view('pages/politique-confidentialite', ['lang' => $lang]);
    }

    public function planDuSite(array $params): void
    {
        $lang  = $this->resolveLang($params);
        $model = new WineModel();
        $wineImages = [
            'sweet'      => $model->getRandomByColor('sweet'),
            'red'        => $model->getRandomByColor('red'),
            'white'      => $model->getRandomByColor('white'),
            'rosé'       => $model->getRandomByColor('rosé'),
            'collection' => $model->getRandom(),
        ];
        $this->view('pages/plan-du-site', [
            'lang'       => $lang,
            'noindex'    => true,
            'wineImages' => $wineImages,
        ]);
    }

    public function support(array $params): void
    {
        $lang = $this->resolveLang($params);
        $this->view('pages/support', ['lang' => $lang]);
    }

    public function jeux(array $params): void
    {
        $lang  = $this->resolveLang($params);
        $model = new WineModel();
        $wines = $model->getAll(null, 'default', 14);
        $this->view('pages/jeux', ['lang' => $lang, 'wines' => $wines]);
    }

    public function webmaster(array $params): void
    {
        $lang = $this->resolveLang($params);
        $this->view('pages/webmaster', ['lang' => $lang, 'noindex' => true]);
    }
}
