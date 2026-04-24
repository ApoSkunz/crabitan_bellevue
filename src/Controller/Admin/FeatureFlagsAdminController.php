<?php

declare(strict_types=1);

namespace Controller\Admin;

use Core\FeatureFlags;
use Core\Response;
use Model\FeatureFlagsModel;
use Service\AuditLogger;
use Service\MailService;
use Service\RateLimiterService;

/**
 * Gestion des feature flags depuis l'interface admin.
 * Toute modification est protégée par un OTP email (120 s, max 3 tentatives).
 * Accès super_admin uniquement.
 */
class FeatureFlagsAdminController extends AdminController
{
    private const REDIRECT        = '/admin/feature-flags';
    private const OTP_TTL         = 120;
    private const MAX_OTP_ATTEMPTS = 3;
    private const MAX_REQUESTS    = 5;
    private const WINDOW_REQUESTS = 3600;

    /**
     * Affiche la liste des feature flags.
     *
     * @param array<string, string> $_params Paramètres de route (non utilisés)
     * @return void
     */
    public function index(array $_params): void // NOSONAR — php:S1172 : signature imposée par le routeur MVC
    {
        $adminUser = $this->requireSuperAdmin();
        $model     = $this->newModel();
        $flags     = $model->getAll();
        $otpKey   = trim((string) ($_GET['otp_key'] ?? ''));
        $otpBatch = isset($_GET['otp_batch']);

        $this->view('admin/feature-flags/index', [
            'adminUser'    => $adminUser,
            'adminSection' => 'feature_flags',
            'pageTitle'    => 'Feature flags',
            'breadcrumbs'  => [['label' => 'Admin', 'url' => '/admin'], ['label' => 'Feature flags']],
            'flags'        => $flags,
            'otpKey'       => $otpKey !== '' ? $otpKey : null,
            'otpBatch'     => $otpBatch,
        ]);
    }

    /**
     * Génère un OTP et l'envoie par email à l'administrateur.
     * Stocke l'OTP et la valeur en attente via RateLimiterService.
     *
     * @param array<string, mixed> $params Paramètres de route (key)
     * @return void
     */
    public function requestChange(array $params): void
    {
        $adminUser = $this->requireSuperAdmin();

        if (!$this->verifyCsrf()) {
            $this->flash('error', 'Token CSRF invalide.');
            Response::redirect(self::REDIRECT);
        }

        $key   = (string) ($params['key'] ?? '');
        $model = $this->newModel();
        $flag  = $model->findByKey($key);

        if ($flag === null) {
            $this->flash('error', "Flag inconnu : {$key}.");
            Response::redirect(self::REDIRECT);
        }

        $newValue = trim((string) ($_POST['new_value'] ?? ''));
        if (!$this->isValidValue($newValue, (string) $flag['type'])) {
            $this->flash('error', 'Valeur invalide pour ce type de flag.');
            Response::redirect(self::REDIRECT);
        }

        $rl         = $this->newRateLimiter();
        $requestKey = 'ff_req:' . $adminUser['id'] . ':' . $key;
        if (!$rl->checkLimit($requestKey, self::MAX_REQUESTS, self::WINDOW_REQUESTS)) {
            $this->flash('error', 'Trop de demandes. Réessayez dans une heure.');
            Response::redirect(self::REDIRECT);
        }
        $rl->recordAttempt($requestKey, self::WINDOW_REQUESTS);

        $otp         = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $otpStoreKey = 'ff_otp_val:' . $adminUser['id'] . ':' . $key;
        $pendingKey  = 'ff_pending_val:' . $adminUser['id'] . ':' . $key;
        $rl->remember($otpStoreKey, $otp, self::OTP_TTL);
        $rl->remember($pendingKey, $newValue, self::OTP_TTL);

        try {
            $mail = $this->newMailService();
            $mail->sendFeatureFlagOtp(
                $adminUser['email'],
                $adminUser['name'],
                (string) $flag['label'],
                (string) $flag['value'],
                $newValue,
                $otp
            );
        } catch (\Throwable $e) {
            \Core\ErrorLogger::warning($e, '[FeatureFlags]');
            $this->flash('error', 'Erreur envoi email OTP.');
            Response::redirect(self::REDIRECT);
        }

        $this->flash('info', "Code envoyé à {$adminUser['email']}. Valable 120 secondes.");
        Response::redirect(self::REDIRECT . '?otp_key=' . urlencode($key));
    }

    /**
     * Vérifie l'OTP soumis et applique la nouvelle valeur si valide.
     *
     * @param array<string, mixed> $params Paramètres de route (key)
     * @return void
     */
    public function confirmChange(array $params): void
    {
        $adminUser = $this->requireSuperAdmin();

        if (!$this->verifyCsrf()) {
            $this->flash('error', 'Token CSRF invalide.');
            Response::redirect(self::REDIRECT);
        }

        $key   = (string) ($params['key'] ?? '');
        $model = $this->newModel();
        $flag  = $model->findByKey($key);

        if ($flag === null) {
            $this->flash('error', "Flag inconnu : {$key}.");
            Response::redirect(self::REDIRECT);
        }

        $rl          = $this->newRateLimiter();
        $attemptsKey = 'ff_otp_attempts:' . $adminUser['id'] . ':' . $key;
        $otpStoreKey = 'ff_otp_val:' . $adminUser['id'] . ':' . $key;
        $pendingKey  = 'ff_pending_val:' . $adminUser['id'] . ':' . $key;

        if (!$rl->checkLimit($attemptsKey, self::MAX_OTP_ATTEMPTS, self::OTP_TTL)) {
            $this->flash('error', 'Trop de tentatives. Redemandez un nouveau code.');
            Response::redirect(self::REDIRECT);
        }

        $submittedOtp = trim((string) ($_POST['otp'] ?? ''));
        $storedOtp    = (string) ($rl->recall($otpStoreKey) ?? '');
        $newValue     = (string) ($rl->recall($pendingKey) ?? '');

        if ($storedOtp === '' || !hash_equals($storedOtp, $submittedOtp)) {
            $rl->recordAttempt($attemptsKey, self::OTP_TTL);
            $this->flash('error', 'Code invalide ou expiré.');
            Response::redirect(self::REDIRECT . '?otp_key=' . urlencode($key));
        }

        $oldValue = (string) $flag['value'];
        $model->updateValue($key, $newValue, (int) $adminUser['id']);
        FeatureFlags::flush();

        $rl->reset($otpStoreKey);
        $rl->reset($pendingKey);
        $rl->reset($attemptsKey);

        AuditLogger::log(
            $adminUser['id'],
            'feature_flag.updated',
            'feature_flag',
            null,
            ['key' => $key, 'old' => $oldValue, 'new' => $newValue],
            AuditLogger::getIp()
        );

        $this->flash('success', "Flag « {$flag['label']} » mis à jour : {$oldValue} → {$newValue}.");
        Response::redirect(self::REDIRECT);
    }

    /**
     * Génère un OTP pour valider un batch de modifications de feature flags.
     *
     * @param array<string, mixed> $_params Paramètres de route (non utilisés)
     * @return void
     */
    public function requestBatch(array $_params): void // NOSONAR — php:S1172 : signature imposée par le routeur MVC
    {
        $adminUser = $this->requireSuperAdmin();

        if (!$this->verifyCsrf()) {
            $this->flash('error', 'Token CSRF invalide.');
            Response::redirect(self::REDIRECT);
        }

        $model   = $this->newModel();
        $allFlags = $model->getAll();
        $flagsMap = [];
        foreach ($allFlags as $f) {
            $flagsMap[(string) $f['key']] = $f;
        }

        $changes = [];
        foreach ($flagsMap as $key => $flag) {
            if (!isset($_POST[$key])) {
                continue;
            }
            $posted = trim((string) $_POST[$key]);
            if (!$this->isValidValue($posted, (string) $flag['type'])) {
                continue;
            }
            if ($posted !== (string) $flag['value']) {
                $changes[] = [
                    'key'   => $key,
                    'old'   => (string) $flag['value'],
                    'new'   => $posted,
                    'label' => (string) $flag['label'],
                ];
            }
        }

        if (empty($changes)) {
            $this->flash('info', 'Aucune modification détectée.');
            Response::redirect(self::REDIRECT);
        }

        $rl         = $this->newRateLimiter();
        $requestKey = 'ff_batch_req:' . $adminUser['id'];
        if (!$rl->checkLimit($requestKey, self::MAX_REQUESTS, self::WINDOW_REQUESTS)) {
            $this->flash('error', 'Trop de demandes. Réessayez dans une heure.');
            Response::redirect(self::REDIRECT);
        }
        $rl->recordAttempt($requestKey, self::WINDOW_REQUESTS);

        $otp        = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $otpKey     = 'ff_batch_otp:' . $adminUser['id'];
        $pendingKey = 'ff_batch_pending:' . $adminUser['id'];
        $rl->remember($otpKey, $otp, self::OTP_TTL);
        $rl->remember($pendingKey, (string) json_encode($changes), self::OTP_TTL);

        try {
            $mail = $this->newMailService();
            $mail->sendFeatureFlagBatchOtp($adminUser['email'], $adminUser['name'], $changes, $otp);
        } catch (\Throwable $e) {
            \Core\ErrorLogger::warning($e, '[FeatureFlags]');
            $this->flash('error', 'Erreur envoi email OTP.');
            Response::redirect(self::REDIRECT);
        }

        $count = count($changes);
        $this->flash('info', "Code OTP envoyé à {$adminUser['email']}. {$count} modification(s) en attente. Valable 120 secondes.");
        Response::redirect(self::REDIRECT . '?otp_batch=1');
    }

    /**
     * Vérifie l'OTP batch et applique toutes les modifications en attente.
     *
     * @param array<string, mixed> $_params Paramètres de route (non utilisés)
     * @return void
     */
    public function confirmBatch(array $_params): void // NOSONAR — php:S1172 : signature imposée par le routeur MVC
    {
        $adminUser = $this->requireSuperAdmin();

        if (!$this->verifyCsrf()) {
            $this->flash('error', 'Token CSRF invalide.');
            Response::redirect(self::REDIRECT);
        }

        $rl          = $this->newRateLimiter();
        $attemptsKey = 'ff_batch_otp_attempts:' . $adminUser['id'];
        $otpKey      = 'ff_batch_otp:' . $adminUser['id'];
        $pendingKey  = 'ff_batch_pending:' . $adminUser['id'];

        if (!$rl->checkLimit($attemptsKey, self::MAX_OTP_ATTEMPTS, self::OTP_TTL)) {
            $this->flash('error', 'Trop de tentatives. Redemandez un nouveau code.');
            Response::redirect(self::REDIRECT);
        }

        $submittedOtp = trim((string) ($_POST['otp'] ?? ''));
        $storedOtp    = (string) ($rl->recall($otpKey) ?? '');
        $pendingJson  = (string) ($rl->recall($pendingKey) ?? '');

        if ($storedOtp === '' || !hash_equals($storedOtp, $submittedOtp)) {
            $rl->recordAttempt($attemptsKey, self::OTP_TTL);
            $this->flash('error', 'Code invalide ou expiré.');
            Response::redirect(self::REDIRECT . '?otp_batch=1');
        }

        /** @var array<int, array{key: string, old: string, new: string, label: string}> $changes */
        $changes = (array) (json_decode($pendingJson, true) ?? []);
        $model   = $this->newModel();
        $applied = 0;

        foreach ($changes as $change) {
            $key      = (string) ($change['key'] ?? '');
            $newValue = (string) ($change['new'] ?? '');
            $oldValue = (string) ($change['old'] ?? '');
            if ($key === '' || $newValue === '') {
                continue;
            }
            $model->updateValue($key, $newValue, (int) $adminUser['id']);
            AuditLogger::log(
                $adminUser['id'],
                'feature_flag.updated',
                'feature_flag',
                null,
                ['key' => $key, 'old' => $oldValue, 'new' => $newValue],
                AuditLogger::getIp()
            );
            $applied++;
        }

        FeatureFlags::flush();
        $rl->reset($otpKey);
        $rl->reset($pendingKey);
        $rl->reset($attemptsKey);

        $this->flash('success', "{$applied} flag(s) mis à jour avec succès.");
        Response::redirect(self::REDIRECT);
    }

    /**
     * Valide que la nouvelle valeur est cohérente avec le type du flag.
     *
     * @param string $value
     * @param string $type
     * @return bool
     */
    private function isValidValue(string $value, string $type): bool
    {
        if ($value === '') {
            return false;
        }
        return match ($type) {
            'bool'  => in_array($value, ['true', 'false'], true),
            'int'   => is_numeric($value) && floor((float) $value) === (float) $value,
            'float' => is_numeric($value),
            default => true,
        };
    }

    /**
     * Instancie FeatureFlagsModel — seam de testabilité.
     *
     * @return FeatureFlagsModel
     */
    protected function newModel(): FeatureFlagsModel
    {
        return new FeatureFlagsModel();
    }

    /**
     * Instancie RateLimiterService — seam de testabilité.
     *
     * @return RateLimiterService
     */
    protected function newRateLimiter(): RateLimiterService
    {
        return new RateLimiterService();
    }

    /**
     * Instancie MailService — seam de testabilité.
     *
     * @return MailService
     */
    protected function newMailService(): MailService
    {
        return new MailService();
    }
}
