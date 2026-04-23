<?php

declare(strict_types=1);

namespace Controller;

use Core\Controller;
use Core\CronRunner;

/**
 * Expose les cron jobs via HTTP GET pour IONOS mutualisé (pas d'accès shell).
 *
 * Sécurité : token secret en query string (?token=…) comparé en temps constant.
 * Chaque job correspond au même callable que le script bin/ équivalent.
 *
 * Route : GET /cron/{job}?token={CRON_SECRET_TOKEN}
 */
class CronController extends Controller // NOSONAR — php:S1448 : 24 méthodes cohérentes au domaine cron — découpage artificiel sans valeur
{
    private const CONTENT_TYPE    = 'Content-Type: text/plain; charset=utf-8';
    private const APP_URL_FALLBACK = 'https://crabitanbellevue.fr';
    private const LOG_OK           = '[OK] ';
    private const LOG_ORDER_REF    = ' commande #';

    /**
     * Seam de testabilité : instancie le service d'envoi d'emails.
     *
     * Permet aux tests unitaires d'injecter un mock qui ne tente pas
     * de se connecter à SMTP, afin de couvrir les branches post-send.
     *
     * @return \Service\MailService
     */
    protected function newMailService(): \Service\MailService
    {
        return new \Service\MailService();
    }

    /**
     * Exécute le job cron demandé après vérification du token.
     *
     * @param array<string, string> $params Paramètres de route (job)
     * @return void
     */
    public function run(array $params): void
    {
        $this->verifyToken();

        $job = $params['job'] ?? '';
        $fn  = $this->getJob($job);

        if ($fn === null) {
            http_response_code(404);
            header(self::CONTENT_TYPE);
            echo "Unknown job: {$job}\n";
            return;
        }

        $dryRun = isset($_GET['dry_run']) && $_GET['dry_run'] === '1';

        $runModel = new \Model\CronRunModel();
        $runId    = 0;
        try {
            $runId = $runModel->start($job, $dryRun);
        } catch (\Throwable $e) { // NOSONAR — journal non bloquant
            \Core\ErrorLogger::warning($e, '[CronRunModel::start]');
        }

        ob_start();

        $runner   = new CronRunner($job, '', $dryRun);
        $exitCode = $runner->run($fn);

        $output = (string) ob_get_clean();

        if ($runId > 0) {
            try {
                $actions = substr_count($output, '[OK]') + substr_count($output, '[SENT]');
                if ($exitCode === 0) {
                    $runModel->finishSuccess($runId, $actions, $output);
                } else {
                    $runModel->finishError($runId, 'Exit code ' . $exitCode, $output);
                }
            } catch (\Throwable $e) { // NOSONAR — journal non bloquant
                \Core\ErrorLogger::warning($e, '[CronRunModel::finish]');
            }
        }

        http_response_code($exitCode === 0 ? 200 : 500);
        header(self::CONTENT_TYPE);
        echo $output;
    }

    /**
     * Vérifie le token secret en temps constant — renvoie 404 si invalide ou absent.
     *
     * @return void
     */
    private function verifyToken(): void
    {
        $expected = $_ENV['CRON_SECRET_TOKEN'] ?? '';
        $provided = $_GET['token'] ?? '';

        if ($expected === '' || !hash_equals($expected, $provided)) {
            http_response_code(404);
            header(self::CONTENT_TYPE);
            echo "Not Found\n";
            $this->doExit();
        }
    }

    /**
     * Termine l'exécution — seam de testabilité.
     *
     * @return never
     */
    protected function doExit(): never
    {
        throw new \Core\Exception\HttpException(0); // NOSONAR — arrêt propre flux binaire
    }

    /**
     * Retourne le callable du job correspondant au slug, ou null si inconnu.
     *
     * @param  string $job Slug du job (ex. "purge-accounts")
     * @return (callable(callable(string):void, bool):void)|null
     */
    private function getJob(string $job): ?callable
    {
        return $this->getAccountJob($job)
            ?? $this->getPurgeJob($job)
            ?? $this->getNewsletterJob($job)
            ?? $this->getNotificationJob($job);
    }

    /**
     * Jobs RGPD comptes : purge et avertissements d'inactivité/suppression.
     *
     * @param  string $job
     * @return (callable(callable(string):void, bool):void)|null
     */
    private function getAccountJob(string $job): ?callable
    {
        return match ($job) {
            'purge-accounts'          => fn(callable $log, bool $dryRun) => $this->doJobPurgeAccounts($log, $dryRun),
            'purge-inactive-accounts' => fn(callable $log, bool $dryRun) => $this->doJobPurgeInactiveAccounts($log, $dryRun),
            'send-deletion-reminders' => fn(callable $log, bool $dryRun) => $this->doJobSendDeletionReminders($log, $dryRun),
            default                   => null,
        };
    }

    /**
     * Jobs de purge données : sessions, adresses, snapshots, rate limits.
     *
     * @param  string $job
     * @return (callable(callable(string):void, bool):void)|null
     */
    private function getPurgeJob(string $job): ?callable
    {
        return match ($job) {
            'pseudonymize-orders' => fn(callable $log, bool $dryRun) => $this->doJobPseudonymizeOrders($log, $dryRun),
            'purge-connections'         => fn(callable $log, bool $dryRun) => $this->doJobPurgeConnections($log, $dryRun),
            'purge-addresses'           => fn(callable $log, bool $dryRun) => $this->doJobPurgeAddresses($log, $dryRun),
            'purge-rate-limits'         => fn(callable $log, bool $dryRun) => $this->doJobPurgeRateLimits($log, $dryRun),
            'purge-order-rating-tokens' => fn(callable $log, bool $dryRun) => $this->doJobPurgeOrderRatingTokens($log, $dryRun),
            default                     => null,
        };
    }

    /**
     * Jobs newsletter : purge abonnements et tracking.
     *
     * @param  string $job
     * @return (callable(callable(string):void, bool):void)|null
     */
    private function getNewsletterJob(string $job): ?callable
    {
        return match ($job) {
            'purge-newsletter-subscriptions' => fn(callable $log, bool $dryRun) => $this->doJobPurgeNewsletterSubscriptions($log, $dryRun),
            'purge-newsletter-tracking'      => fn(callable $log, bool $dryRun) => $this->doJobPurgeNewsletterTracking($log, $dryRun),
            default                          => null,
        };
    }

    /**
     * Jobs notifications email : paniers abandonnés, post-achat, alertes commandes, tokens.
     *
     * @param  string $job
     * @return (callable(callable(string):void, bool):void)|null
     */
    private function getNotificationJob(string $job): ?callable
    {
        return match ($job) {
            'send-abandoned-cart'    => fn(callable $log, bool $dryRun) => $this->doJobSendAbandonedCart($log, $dryRun),
            'send-post-purchase'     => fn(callable $log, bool $dryRun) => $this->doJobSendPostPurchase($log, $dryRun),
            'alert-pending-orders'   => fn(callable $log, bool $dryRun) => $this->doJobAlertPendingOrders($log, $dryRun),
            'clean-expired-tokens'   => fn(callable $log, bool $dryRun) => $this->doJobCleanExpiredTokens($log, $dryRun),
            'send-newsletter-queue'            => fn(callable $log, bool $dryRun) => $this->doJobSendNewsletterQueue($log, $dryRun),
            'send-newsletter-welcome-sequence' => fn(callable $log, bool $dryRun) => $this->doJobSendNewsletterWelcomeSequence($log, $dryRun),
            'send-reconquest'                  => fn(callable $log, bool $dryRun) => $this->doJobSendReconquest($log, $dryRun),
            'send-review-request'              => fn(callable $log, bool $dryRun) => $this->doJobSendReviewRequest($log, $dryRun),
            default                            => null,
        };
    }

    /**
     * Envoie l'email de suppression définitive puis anonymise les comptes planifiés (RGPD Art. 17).
     *
     * @param callable(string):void $log
     * @param bool                  $dryRun
     * @return void
     */
    private function doJobPurgeAccounts(callable $log, bool $dryRun): void
    {
        $model  = new \Model\AccountModel();
        $mailer = new \Service\MailService();

        $due = $model->getAccountsDueForDeletion();
        $log('Comptes éligibles à l\'anonymisation : ' . count($due));

        foreach ($due as $account) {
            $name = trim((string) ($account['name'] ?? '')) ?: $account['email'];
            if ($dryRun) {
                $log("[DRY-RUN] Would send final deletion email to {$account['email']} (id={$account['id']})");
                continue;
            }
            try {
                $mailer->sendFinalDeletion($account['email'], $name, $account['lang'] ?? 'fr');
                $log("[EMAIL] Notification envoyée à {$account['email']} (id={$account['id']})");
            } catch (\Throwable $e) {
                $log("[EMAIL-ERR] id={$account['id']} : " . $e->getMessage());
            }
        }

        if (!$dryRun) {
            $purged = $model->purgeScheduledDeletions();
            $log("{$purged} compte(s) anonymisé(s)");
        }
    }

    /**
     * Avertit les comptes inactifs puis soft-delete ceux qui n'ont pas réagi (RGPD Art. 5-1e).
     *
     * @param callable(string):void $log
     * @param bool                  $dryRun
     * @return void
     */
    private function doJobPurgeInactiveAccounts(callable $log, bool $dryRun): void
    {
        $model          = new \Model\AccountModel();
        $mailer         = new \Service\MailService();
        $inactiveMonths = (int) ($_ENV['RGPD_INACTIVITY_MONTHS'] ?? 36);

        $log("Seuil d'inactivité : {$inactiveMonths} mois");

        $inactive = $model->getInactiveAccounts($inactiveMonths);
        $log('Phase 1 — comptes à avertir : ' . count($inactive));
        $warned = $this->doJobPurgeInactivePhase1($log, $dryRun, $inactive, $model, $mailer);

        $toDelete = $model->getAccountsToSoftDelete();
        $log('Phase 2 — comptes à soft-delete : ' . count($toDelete));
        $softDeleted = $this->doJobPurgeInactivePhase2($log, $dryRun, $toDelete, $model, $mailer);

        if (!$dryRun) {
            $log("Terminé : {$warned} avertissement(s), {$softDeleted} soft-delete(s)");
        } else {
            $log('[DRY-RUN] Simulation terminée — aucune écriture BDD, aucun email envoyé');
        }
    }

    /**
     * Phase 1 : envoie les avertissements d'inactivité.
     *
     * @param callable(string):void                $log
     * @param bool                                 $dryRun
     * @param array<int, array<string, mixed>>     $inactive
     * @param \Model\AccountModel                  $model
     * @param \Service\MailService                 $mailer
     * @return int Nombre d'emails d'avertissement envoyés
     */
    private function doJobPurgeInactivePhase1(
        callable $log,
        bool $dryRun,
        array $inactive,
        \Model\AccountModel $model,
        \Service\MailService $mailer
    ): int {
        $warned = 0;
        foreach ($inactive as $account) {
            $id           = (int) $account['id'];
            $deletionDate = date('d/m/Y', strtotime('+30 days'));
            if ($dryRun) {
                $log("[DRY-RUN] Would send inactivity warning to {$account['email']} (id={$id})");
                continue;
            }
            $delayMs = max(0, (int) ($_ENV['CRON_MAIL_DELAY_MS'] ?? 0));
            try {
                $mailer->sendInactivityWarning(
                    (string) $account['email'],
                    (string) $account['name'],
                    (string) ($account['lang'] ?? 'fr'),
                    $deletionDate
                );
                $model->markInactivityWarningSent($id);
                $warned++;
                $log("[WARN] Email envoyé à {$account['email']} (id={$id})");
            } catch (\Throwable $e) {
                $log("[WARN-ERR] id={$id} : " . $e->getMessage());
            }
            if ($delayMs > 0) {
                usleep($delayMs * 1000);
            }
        }
        return $warned;
    }

    /**
     * Phase 2 : soft-delete les comptes qui n'ont pas réagi à l'avertissement.
     *
     * @param callable(string):void                $log
     * @param bool                                 $dryRun
     * @param array<int, array<string, mixed>>     $toDelete
     * @param \Model\AccountModel                  $model
     * @param \Service\MailService                 $mailer
     * @return int Nombre de comptes soft-deletés
     */
    private function doJobPurgeInactivePhase2(
        callable $log,
        bool $dryRun,
        array $toDelete,
        \Model\AccountModel $model,
        \Service\MailService $mailer
    ): int {
        $softDeleted = 0;
        foreach ($toDelete as $account) {
            $id = (int) $account['id'];
            if ($dryRun) {
                $log("[DRY-RUN] Would soft-delete compte id={$id} ({$account['email']})");
                continue;
            }
            try {
                $token        = $model->softDeleteForInactivity($id);
                $deletionDate = date('d/m/Y', strtotime('+30 days'));
                $name         = trim((string) ($account['name'] ?? '')) ?: $account['email'];
                $mailer->sendInactivityDeletion(
                    $account['email'],
                    $name,
                    $account['lang'] ?? 'fr',
                    $deletionDate,
                    $token
                );
                $softDeleted++;
                $log("[DELETE] Compte id={$id} ({$account['email']}) soft-deleted");
            } catch (\Throwable $e) {
                $log("[DELETE-ERR] id={$id} : " . $e->getMessage());
            }
        }
        return $softDeleted;
    }

    /**
     * Envoie les rappels J-7 et J-1 avant la suppression définitive d'un compte.
     *
     * @param callable(string):void $log
     * @param bool                  $dryRun
     * @return void
     */
    private function doJobSendDeletionReminders(callable $log, bool $dryRun): void
    {
        $model   = new \Model\AccountModel();
        $mailer  = $this->newMailService();
        $delayMs = max(0, (int) ($_ENV['CRON_MAIL_DELAY_MS'] ?? 0));
        $sent    = 0;

        foreach ([7, 1] as $days) {
            $accounts = $model->getAccountsPendingDeletionReminder($days);
            foreach ($accounts as $account) {
                $result = $this->doSendReminderForAccount($log, $dryRun, $account, $days, $model, $mailer);
                $sent  += $result;
                if ($result === 1 && $delayMs > 0) {
                    usleep($delayMs * 1000);
                }
            }
        }

        if (!$dryRun) {
            $log("{$sent} rappel(s) envoyé(s)");
        }
    }

    /**
     * Envoie ou simule l'envoi du rappel pour un compte donné.
     *
     * @param callable(string):void    $log
     * @param bool                     $dryRun
     * @param array<string, mixed>     $account
     * @param int                      $days
     * @param \Model\AccountModel      $model
     * @param \Service\MailService     $mailer
     * @return int 1 si l'email a été envoyé, 0 sinon
     */
    private function doSendReminderForAccount(
        callable $log,
        bool $dryRun,
        array $account,
        int $days,
        \Model\AccountModel $model,
        \Service\MailService $mailer
    ): int {
        $name = trim(($account['firstname'] ?? '') . ' ' . ($account['lastname'] ?? ''))
            ?: $account['email'];

        if ($dryRun) {
            $log("[DRY-RUN] Would send J-{$days} reminder to {$account['email']} (id={$account['id']})");
            return 0;
        }

        try {
            $mailer->sendDeletionReminder(
                $account['email'],
                $name,
                $account['lang'] ?? 'fr',
                $days,
                $account['scheduled_deletion_at'],
                $account['reactivation_token'] ?? ''
            );
            if ($days === 7) {
                $model->markDeletionReminder7dSent((int) $account['id']);
            } else {
                $model->markDeletionReminder1dSent((int) $account['id']);
            }
            $log("[J-{$days}] Rappel envoyé à {$account['email']} (id={$account['id']})");
            return 1;
        } catch (\Throwable $e) {
            $log("[J-{$days}] ERREUR id={$account['id']} : " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Pseudonymise les snapshots d'adresses de commandes de plus de 10 ans.
     *
     * @param callable(string):void $log
     * @param bool                  $dryRun
     * @return void
     */
    private function doJobPseudonymizeOrders(callable $log, bool $dryRun): void
    {
        $model = new \Model\OrderModel();

        if ($dryRun) {
            $log('[DRY-RUN] Would pseudonymize expired order snapshots — aucune écriture BDD');
            return;
        }

        $count = $model->pseudonymizeExpired();
        $log("{$count} snapshot(s) pseudonymisé(s)");
    }

    /**
     * Supprime les sessions révoquées ou expirées de la table connections.
     *
     * @param callable(string):void $log
     * @param bool                  $dryRun
     * @return void
     */
    private function doJobPurgeConnections(callable $log, bool $dryRun): void
    {
        $model = new \Model\ConnectionModel();

        if ($dryRun) {
            $log('[DRY-RUN] Would purge expired/revoked connections — aucune écriture BDD');
            return;
        }

        $purged = $model->purgeExpired();
        $log("{$purged} session(s) purgée(s)");
    }

    /**
     * Supprime les adresses orphelines dont toutes les commandes sont en statut terminal.
     *
     * @param callable(string):void $log
     * @param bool                  $dryRun
     * @return void
     */
    private function doJobPurgeAddresses(callable $log, bool $dryRun): void
    {
        $model    = new \Model\AddressModel();
        $orphaned = $model->getOrphanedAddresses();
        $log('Adresses orphelines éligibles : ' . count($orphaned));

        if ($orphaned === []) {
            $log('0 adresse purgée');
            return;
        }

        foreach ($orphaned as $addr) {
            $log("Adresse id={$addr['id']} (user_id={$addr['user_id']}) éligible");
        }

        if ($dryRun) {
            $log('[DRY-RUN] Would delete ' . count($orphaned) . ' adresse(s) — aucune écriture BDD');
            return;
        }

        $purged = $model->purgeOrphaned();
        $log("{$purged} adresse(s) supprimée(s)");
    }

    /**
     * Supprime les entrées expirées de la table rate_limits.
     *
     * @param callable(string):void $log
     * @param bool                  $dryRun
     * @return void
     */
    private function doJobPurgeRateLimits(callable $log, bool $dryRun): void
    {
        $db = \Core\Database::getInstance();

        if ($dryRun) {
            $count = $db->fetchOne('SELECT COUNT(*) AS n FROM rate_limits WHERE expires_at < UNIX_TIMESTAMP()')['n'] ?? 0;
            $log("[DRY-RUN] Would delete {$count} entrée(s) expirée(s) — aucune écriture BDD");
            return;
        }

        $deleted = $db->execute('DELETE FROM rate_limits WHERE expires_at < UNIX_TIMESTAMP()');
        $log("rate_limits : {$deleted} entrée(s) expirée(s) supprimée(s)");
    }

    /**
     * Purge les tokens de notation de commande expirés (TTL 90 jours).
     *
     * @param callable(string):void $log
     * @param bool                  $dryRun
     * @return void
     */
    private function doJobPurgeOrderRatingTokens(callable $log, bool $dryRun): void
    {
        $db = \Core\Database::getInstance();

        if ($dryRun) {
            $count = $db->fetchOne('SELECT COUNT(*) AS n FROM order_rating_tokens WHERE expires_at < NOW()')['n'] ?? 0;
            $log("[DRY-RUN] Would delete {$count} token(s) expiré(s) — aucune écriture BDD");
            return;
        }

        $deleted = $db->execute('DELETE FROM order_rating_tokens WHERE expires_at < NOW()');
        $log("order_rating_tokens : {$deleted} token(s) expiré(s) supprimé(s)");
    }

    /**
     * Purge les abonnements newsletter non confirmés dont le token a expiré.
     *
     * @param callable(string):void $log
     * @param bool                  $dryRun
     * @return void
     */
    private function doJobPurgeNewsletterSubscriptions(callable $log, bool $dryRun): void
    {
        $model = new \Model\NewsletterSubscriptionModel();

        if ($dryRun) {
            $log('[DRY-RUN] Would purge expired pending subscriptions — aucune écriture BDD');
            return;
        }

        $pending = $model->purgeExpiredPending();
        $log("{$pending} abonnement(s) non confirmé(s) expiré(s) supprimé(s)");
    }

    /**
     * Supprime les données de tracking newsletter de plus de 13 mois.
     *
     * @param callable(string):void $log
     * @param bool                  $dryRun
     * @return void
     */
    private function doJobPurgeNewsletterTracking(callable $log, bool $dryRun): void
    {
        $model = new \Model\NewsletterTrackingModel();

        if ($dryRun) {
            $log('[DRY-RUN] Would purge newsletter tracking older than 13 months — aucune écriture BDD');
            return;
        }

        $deleted = $model->purgeOlderThan13Months();
        $log("{$deleted} enregistrement(s) de tracking purgé(s)");
    }

    /**
     * Envoie un email de relance aux clients dont le panier est abandonné depuis 72–96h.
     *
     * @param callable(string):void $log
     * @param bool                  $dryRun
     * @return void
     */
    private function doJobSendAbandonedCart(callable $log, bool $dryRun): void
    {
        $cartModel = new \Model\CartModel();
        $mailer    = new \Service\MailService();
        $appUrl    = rtrim($_ENV['APP_URL'] ?? self::APP_URL_FALLBACK, '/');

        $carts = $cartModel->getAbandonedForEmail();
        $log('Paniers éligibles : ' . count($carts));

        foreach ($carts as $data) {
            $cartUrl = ($data['lang'] === 'fr') ? $appUrl . '/fr/panier' : $appUrl . '/en/cart';

            if ($dryRun) {
                $log('[DRY-RUN] Would send abandoned cart to ' . $data['email'] . ' (cart_id=' . $data['cart_id'] . ')');
                continue;
            }

            $delayMs = max(0, (int) ($_ENV['CRON_MAIL_DELAY_MS'] ?? 0));
            try {
                $mailer->sendAbandonedCart($data['email'], $data['firstname'], $data['items'], $data['lang'], $cartUrl);
                $cartModel->markReminderSent($data['cart_id']);
                $log(self::LOG_OK . $data['email']);
            } catch (\Throwable $e) {
                $log('[ERR] ' . $data['email'] . ' : ' . $e->getMessage());
            }
            if ($delayMs > 0) {
                usleep($delayMs * 1000);
            }
        }
    }

    /**
     * Envoie l'invitation à noter la commande 3 semaines après livraison confirmée.
     *
     * @param callable(string):void $log
     * @param bool                  $dryRun
     * @return void
     */
    private function doJobSendPostPurchase(callable $log, bool $dryRun): void
    {
        $orderModel   = new \Model\OrderModel();
        $accountModel = new \Model\AccountModel();
        $mailer       = $this->newMailService();

        $orders = $orderModel->getDeliveredForRatingEmail();
        $log('Commandes éligibles notation : ' . count($orders));

        foreach ($orders as $order) {
            $account = $accountModel->findById((int) $order['user_id']);
            if (!$account) {
                $log('[SKIP] user_id=' . $order['user_id']);
                continue;
            }

            if ($dryRun) {
                $log('[DRY-RUN] Would send post-purchase to ' . $account['email'] . self::LOG_ORDER_REF . $order['id']);
                continue;
            }

            $items = json_decode((string) $order['content'], true) ?? [];
            $lang  = in_array($account['lang'] ?? '', ['fr', 'en'], true) ? $account['lang'] : 'fr';

            $delayMs = max(0, (int) ($_ENV['CRON_MAIL_DELAY_MS'] ?? 0));
            try {
                $mailer->sendPostPurchase(
                    $account['email'],
                    $account['firstname'] ?? 'Client',
                    (int) $order['user_id'],
                    (int) $order['id'],
                    $items,
                    $lang
                );
                $orderModel->markRatingEmailSent((int) $order['id']);
                $log(self::LOG_OK . $account['email'] . self::LOG_ORDER_REF . $order['id']);
            } catch (\Throwable $e) {
                $log('[ERR] commande #' . $order['id'] . ' : ' . $e->getMessage());
            }
            if ($delayMs > 0) {
                usleep($delayMs * 1000);
            }
        }
    }

    /**
     * Envoie un batch de la file d'attente newsletter (nouveau vin).
     * Taille du batch : CRON_NEWSLETTER_BATCH_SIZE (défaut 30).
     * Délai inter-envoi : CRON_MAIL_DELAY_MS (défaut 0).
     *
     * @param callable(string):void $log
     * @param bool                  $dryRun
     * @return void
     */
    private function doJobSendNewsletterQueue(callable $log, bool $dryRun): void
    {
        $services = [
            'queue'    => new \Model\NewsletterSendQueueModel(),
            'wine'     => new \Model\WineModel(),
            'nl'       => new \Model\NewsletterModel(),
            'tracking' => new \Model\NewsletterTrackingModel(),
            'mailer'   => new \Service\MailService(),
        ];
        $appUrl    = rtrim($_ENV['APP_URL'] ?? self::APP_URL_FALLBACK, '/');
        $batchSize = max(1, (int) ($_ENV['CRON_NEWSLETTER_BATCH_SIZE'] ?? 30));
        $delayMs   = max(0, (int) ($_ENV['CRON_MAIL_DELAY_MS'] ?? 0));

        $pending = $services['queue']->getPending($batchSize);
        $total   = count($pending);

        if ($total === 0) {
            $log('File newsletter vide — aucun envoi');
            return;
        }

        $log("Batch newsletter : {$total} destinataire(s) à traiter");

        $context = [
            'sent'         => 0,
            'failed'       => 0,
            'campaignCache' => [],
            'linkMapCache'  => [],
        ];

        foreach ($pending as $item) {
            $this->processNewsletterQueueItem(
                $services,
                $log,
                $item,
                $dryRun,
                $appUrl,
                $delayMs,
                $context
            );
        }

        if (!$dryRun) {
            $log("Résultat : {$context['sent']} envoyé(s), {$context['failed']} échec(s)");
            $remaining = $services['queue']->countPending();
            if ($remaining > 0) {
                $log("Restants en file : {$remaining}");
            }
        }
    }

    /**
     * Traite un élément de la file d'attente newsletter (vin ou admin).
     *
     * @param array<string,mixed>   $services Instances des modèles et services
     * @param callable(string):void $log
     * @param array<string,mixed>   $item     Ligne de la queue
     * @param bool                  $dryRun
     * @param string                $appUrl
     * @param int                   $delayMs  Délai inter-envoi en ms
     * @param array<string,mixed>   $context  sent, failed, campaignCache, linkMapCache (passé par référence)
     * @return void
     */
    private function processNewsletterQueueItem(
        array $services,
        callable $log,
        array $item,
        bool $dryRun,
        string $appUrl,
        int $delayMs,
        array &$context
    ): void {
        $id     = (int) $item['id'];
        $wineId = $item['wine_id'] !== null ? (int) $item['wine_id'] : null;

        if ($dryRun) {
            $nlId = (int) $item['newsletter_id'];
            $type = $wineId !== null ? "wine#{$wineId}" : "admin-nl#{$nlId}";
            $log("[DRY-RUN] Would send {$type} to {$item['recipient_email']} (queue_id={$id})");
            return;
        }

        if ($wineId !== null) {
            $ok = $this->sendWineQueueItem($services, $log, $item, $appUrl);
        } else {
            $ok = $this->sendAdminQueueItem($services, $log, $context['campaignCache'], $context['linkMapCache'], $item, $appUrl);
        }
        $ok ? $context['sent']++ : $context['failed']++;

        if ($delayMs > 0) {
            usleep($delayMs * 1000);
        }
    }

    /**
     * Traite un élément vin de la file d'attente newsletter.
     *
     * @param array<string,mixed> $services Instances des modèles et services
     * @param callable(string):void $log
     * @param array<string,mixed> $item     Ligne de la queue
     * @param string              $appUrl   URL de base de l'application
     * @return bool true si envoi réussi
     */
    private function sendWineQueueItem(array $services, callable $log, array $item, string $appUrl): bool
    {
        $id     = (int) $item['id'];
        $nlId   = (int) $item['newsletter_id'];
        $wineId = (int) $item['wine_id'];
        $email  = (string) $item['recipient_email'];
        $name   = (string) $item['recipient_name'];
        $unsub  = (string) $item['unsub_token'];
        $lang   = (string) $item['lang'];

        $wine = $services['wine']->getById($wineId);
        if ($wine === null) {
            $log("[SKIP] wine_id={$wineId} introuvable — queue_id={$id}");
            $services['queue']->markFailed($id, $nlId);
            return false;
        }
        try {
            $services['mailer']->sendNewWineNewsletter($email, $name, $unsub, $wine, $appUrl, $lang);
            $services['queue']->markSent($id, $nlId);
            $log("[OK] wine#{$wineId} → {$email} (queue_id={$id})");
            return true;
        } catch (\Throwable $e) {
            $services['queue']->markFailed($id, $nlId);
            $log("[ERR] wine#{$wineId} → {$email} (queue_id={$id}) : " . $e->getMessage());
            return false;
        }
    }

    /**
     * Traite un élément newsletter admin de la file d'attente.
     *
     * @param array<string,mixed>                    $services
     * @param callable(string):void                  $log
     * @param array<int,mixed> $campaignCache Cache campagnes par nlId
     * @param array<int,mixed> $linkMapCache  Cache liens par nlId
     * @param array<string,mixed>                    $item
     * @param string                                 $appUrl
     * @return bool true si envoi réussi
     */
    private function sendAdminQueueItem(
        array $services,
        callable $log,
        array &$campaignCache,
        array &$linkMapCache,
        array $item,
        string $appUrl
    ): bool {
        $id    = (int) $item['id'];
        $nlId  = (int) $item['newsletter_id'];
        $email = (string) $item['recipient_email'];
        $name  = (string) $item['recipient_name'];
        $unsub = (string) $item['unsub_token'];
        $lang  = (string) $item['lang'];

        if (!isset($campaignCache[$nlId])) {
            $campaignCache[$nlId] = $services['nl']->findCampaignById($nlId);
            $linkMapCache[$nlId]  = $services['tracking']->getLinkMap($nlId);
        }

        $campaign = $campaignCache[$nlId];
        if ($campaign === null) {
            $log("[SKIP] campaign #{$nlId} introuvable — queue_id={$id}");
            $services['queue']->markFailed($id, $nlId);
            return false;
        }

        $subject  = ($lang === 'en' && $campaign['subject_en'])
            ? (string) $campaign['subject_en']
            : (string) $campaign['subject'];

        $htmlBody = $this->buildAdminNewsletterHtml(
            $services['mailer'],
            $campaign,
            $linkMapCache[$nlId],
            ['name' => $name, 'unsub' => $unsub, 'lang' => $lang],
            $appUrl,
            $nlId
        );

        $attachment = $campaign['attachments'][0] ?? null;
        $pdfPath    = $attachment !== null ? ROOT_PATH . '/' . (string) $attachment['stored_path'] : null;
        $pdfName    = $attachment !== null ? (string) $attachment['original_name'] : null;

        try {
            $services['mailer']->sendNewsletter(
                $email,
                $name ?: 'Abonné',
                $subject,
                $htmlBody,
                $pdfPath,
                $pdfName,
                $unsub !== '' ? $unsub : null
            );
            $services['queue']->markSent($id, $nlId);
            $log("[OK] admin-nl#{$nlId} → {$email} (queue_id={$id})");
            return true;
        } catch (\Throwable $e) {
            $services['queue']->markFailed($id, $nlId);
            $log("[ERR] admin-nl#{$nlId} → {$email} (queue_id={$id}) : " . $e->getMessage());
            return false;
        }
    }

    /**
     * Construit le corps HTML d'une newsletter admin pour un abonné.
     *
     * @param \Service\MailService          $mailer
     * @param array<string,mixed>           $campaign
     * @param array<string,int>             $linkMap   url → linkId
     * @param array{name:string,unsub:string,lang:string} $subscriber
     * @param string                        $appUrl
     * @param int                           $nlId
     * @return string HTML complet
     */
    private function buildAdminNewsletterHtml(
        \Service\MailService $mailer,
        array $campaign,
        array $linkMap,
        array $subscriber,
        string $appUrl,
        int $nlId
    ): string {
        $name  = $subscriber['name'];
        $unsub = $subscriber['unsub'];
        $lang  = $subscriber['lang'];

        $bodyRaw  = ($lang === 'en' && $campaign['body_en'])
            ? (string) $campaign['body_en']
            : (string) $campaign['body'];
        $titleRaw = ($lang === 'en' && $campaign['title_en'])
            ? (string) $campaign['title_en']
            : (string) ($campaign['title'] ?? '');
        $imageUrl = $campaign['image_url'] !== null ? (string) $campaign['image_url'] : null;

        $safeContent = $this->applyTrackingLinks(
            nl2br(htmlspecialchars($bodyRaw, ENT_QUOTES)),
            $linkMap,
            $appUrl,
            $nlId,
            $unsub
        );

        $firstname    = htmlspecialchars(explode(' ', trim($name))[0] ?? $name, ENT_QUOTES);
        $defaultName  = $lang === 'en' ? 'dear subscriber' : 'cher abonné';
        $fallback     = $firstname ?: htmlspecialchars($name ?: $defaultName, ENT_QUOTES);
        $greeting     = $lang === 'en' ? 'Hello ' . $fallback . ',' : 'Bonjour ' . $fallback . ',';
        $greetingHtml = '<p style="margin:0 0 20px;font-size:22px;color:#1a1208;font-family:Georgia,serif;">'
            . $greeting . '</p>';

        $titleHtml = $titleRaw !== ''
            ? '<h2 style="margin:0 0 16px;font-family:Georgia,serif;font-size:22px;color:#1a1208;line-height:1.3;">'
              . htmlspecialchars($titleRaw, ENT_QUOTES) . '</h2>'
            : '';

        $nlTitle = $lang === 'en' ? 'Newsletter · Subscribers' : 'Newsletter · Abonnés';

        return $mailer->buildNewsletterHtml(
            $nlTitle,
            $greetingHtml . $titleHtml . $safeContent,
            $imageUrl,
            $unsub !== '' ? $unsub : null,
            $lang,
            $nlId
        );
    }

    /**
     * Remplace les URLs de la newsletter par des liens de tracking.
     *
     * @param string            $safeContent Contenu HTML déjà échappé
     * @param array<string,int> $linkMap     url → linkId
     * @param string            $appUrl
     * @param int               $nlId
     * @param string            $unsub
     * @return string Contenu avec liens trackés
     */
    private function applyTrackingLinks(
        string $safeContent,
        array $linkMap,
        string $appUrl,
        int $nlId,
        string $unsub
    ): string {
        foreach ($linkMap as $url => $linkId) {
            $trackUrl    = $appUrl . '/track/click/' . $nlId . '/' . $linkId
                . ($unsub !== '' ? '/' . rawurlencode($unsub) : '');
            $safeUrl     = htmlspecialchars((string) $url, ENT_QUOTES);
            $safeTrack   = htmlspecialchars($trackUrl, ENT_QUOTES);
            $safeContent = str_replace(
                $safeUrl,
                '<a href="' . $safeTrack . '" style="color:#8a6a2e;">' . $safeUrl . '</a>',
                $safeContent
            );
        }
        return $safeContent;
    }

    /**
     * Envoie un récap hebdo au propriétaire listant les commandes bloquées > 7 jours.
     *
     * @param callable(string):void $log
     * @param bool                  $dryRun
     * @return void
     */
    private function doJobAlertPendingOrders(callable $log, bool $dryRun): void
    {
        $model       = new \Model\OrderModel();
        $mailer      = $this->newMailService();
        $staleOrders = $model->getStaleOrders(7);

        if (empty($staleOrders)) {
            $log('Aucune commande bloquée — aucun email envoyé');
            return;
        }

        $count = count($staleOrders);

        if ($dryRun) {
            $log("[DRY-RUN] Would send alert for {$count} commande(s) bloquée(s) — aucun email envoyé");
            return;
        }

        $mailer->sendPendingOrdersAlert($staleOrders, 7);
        $log("{$count} commande(s) bloquée(s) — alerte envoyée au propriétaire");
    }

    /**
     * Purge les tokens expirés : reset mot de passe, MFA appareil, sessions, vérif email.
     *
     * @param callable(string):void $log
     * @param bool                  $dryRun
     * @return void
     */
    private function doJobCleanExpiredTokens(callable $log, bool $dryRun): void
    {
        $service = new \Service\TokenPurgeService(\Core\Database::getInstance());

        if ($dryRun) {
            $log('[DRY-RUN] Would purge expired tokens'
                . ' (password_reset, device_confirm_tokens, connections, email_verification)'
                . ' — aucune écriture BDD');
            return;
        }

        $counts = $service->purgeAll();
        $log("password_reset        : {$counts['password_reset']} supprimé(s)");
        $log("device_confirm_tokens : {$counts['device_confirm_tokens']} supprimé(s)");
        $log("connections           : {$counts['connections']} supprimé(s)");
        $log("email_verification    : {$counts['email_verification']} compte(s) mis à jour");
        $log('Total nettoyé : ' . array_sum($counts) . ' entrée(s)');
    }

    /**
     * Séquence de bienvenue newsletter J+7 et J+30 pour abonnés confirmés sans compte.
     *
     * @param callable(string):void $log
     * @param bool                  $dryRun
     * @return void
     */
    private function doJobSendNewsletterWelcomeSequence(callable $log, bool $dryRun): void
    {
        $db        = \Core\Database::getInstance();
        $mailer    = new \Service\MailService();
        $batchSize = max(1, (int) ($_ENV['CRON_NEWSLETTER_BATCH_SIZE'] ?? 30));

        // J+7 : éducation domaine
        $j7Rows = $db->fetchAll(
            "SELECT id, email, lang
             FROM newsletter_subscriptions
             WHERE newsletter_confirmed = 1
               AND welcome_j7_sent_at IS NULL
               AND created_at <= DATE_SUB(NOW(), INTERVAL 7 DAY)
             LIMIT {$batchSize}"
        );

        $sentJ7 = 0;
        foreach ($j7Rows as $row) {
            $id    = (int) $row['id'];
            $email = (string) $row['email'];
            $lang  = (string) ($row['lang'] ?? 'fr');

            if ($dryRun) {
                $log("[DRY-RUN] J+7 would send to {$email}");
                continue;
            }
            try {
                $mailer->sendNewsletterWelcomeJ7($email, '', $lang);
                $db->execute(
                    'UPDATE newsletter_subscriptions SET welcome_j7_sent_at = NOW() WHERE id = ?',
                    [$id]
                );
                $sentJ7++;
                $log("[J+7 OK] {$email}");
            } catch (\Throwable $e) {
                $log("[J+7 ERR] {$email} : " . $e->getMessage());
            }
        }

        // J+30 : offre découverte
        $j30Rows = $db->fetchAll(
            "SELECT id, email, lang, newsletter_token_hash
             FROM newsletter_subscriptions
             WHERE newsletter_confirmed = 1
               AND welcome_j7_sent_at IS NOT NULL
               AND welcome_j30_sent_at IS NULL
               AND created_at <= DATE_SUB(NOW(), INTERVAL 30 DAY)
             LIMIT {$batchSize}"
        );

        $sentJ30 = 0;
        foreach ($j30Rows as $row) {
            $id         = (int) $row['id'];
            $email      = (string) $row['email'];
            $lang       = (string) ($row['lang'] ?? 'fr');
            $tokenHash  = (string) ($row['newsletter_token_hash'] ?? '');
            $unsubToken = $tokenHash !== '' ? $tokenHash : bin2hex(random_bytes(16));

            if ($dryRun) {
                $log("[DRY-RUN] J+30 would send to {$email}");
                continue;
            }
            try {
                $mailer->sendNewsletterWelcomeJ30($email, '', $lang, $unsubToken);
                $db->execute(
                    'UPDATE newsletter_subscriptions SET welcome_j30_sent_at = NOW() WHERE id = ?',
                    [$id]
                );
                $sentJ30++;
                $log("[J+30 OK] {$email}");
            } catch (\Throwable $e) {
                $log("[J+30 ERR] {$email} : " . $e->getMessage());
            }
        }

        if (!$dryRun) {
            $log("J+7 : {$sentJ7} envoyé(s) — J+30 : {$sentJ30} envoyé(s)");
        }
    }

    /**
     * Email de reconquête pour clients inactifs (dernier achat > 12 mois, newsletter active).
     *
     * @param callable(string):void $log
     * @param bool                  $dryRun
     * @return void
     */
    private function doJobSendReconquest(callable $log, bool $dryRun): void
    {
        $db        = \Core\Database::getInstance();
        $mailer    = new \Service\MailService();
        $batchSize = max(1, (int) ($_ENV['CRON_NEWSLETTER_BATCH_SIZE'] ?? 30));
        $appUrl    = rtrim($_ENV['APP_URL'] ?? self::APP_URL_FALLBACK, '/');

        $rows = $db->fetchAll(
            "SELECT a.id, a.email, a.lang, a.newsletter_unsubscribe_token
             FROM accounts a
             WHERE a.newsletter = 1
               AND a.deleted_at IS NULL
               AND a.is_blocked = 0
               AND (a.reconquest_sent_at IS NULL OR a.reconquest_sent_at < DATE_SUB(NOW(), INTERVAL 1 YEAR))
               AND EXISTS (
                   SELECT 1 FROM orders o
                   WHERE o.user_id = a.id
                     AND o.status NOT IN ('cancelled', 'refunded')
               )
               AND NOT EXISTS (
                   SELECT 1 FROM orders o
                   WHERE o.user_id = a.id
                     AND o.ordered_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                     AND o.status NOT IN ('cancelled', 'refunded')
               )
             LIMIT {$batchSize}"
        );

        $total = count($rows);
        if ($total === 0) {
            $log('Aucun client à reconquérir');
            return;
        }

        $log("Reconquête : {$total} client(s) ciblé(s)");
        $sent   = 0;
        $failed = 0;

        foreach ($rows as $row) {
            $id         = (int) $row['id'];
            $email      = (string) $row['email'];
            $lang       = (string) ($row['lang'] ?? 'fr');
            $unsubToken = (string) ($row['newsletter_unsubscribe_token'] ?? '');
            $unsubUrl   = $appUrl . '/' . $lang . '/compte/desabonnement?token=' . urlencode($unsubToken);

            if ($dryRun) {
                $log("[DRY-RUN] Would reconquer {$email}");
                continue;
            }
            try {
                $mailer->sendReconquest($email, '', $lang, $unsubUrl);
                $db->execute('UPDATE accounts SET reconquest_sent_at = NOW() WHERE id = ?', [$id]);
                $sent++;
                $log("[OK] {$email}");
            } catch (\Throwable $e) {
                $failed++;
                $log("[ERR] {$email} : " . $e->getMessage());
            }
        }

        if (!$dryRun) {
            $log("Résultat : {$sent} envoyé(s), {$failed} échec(s)");
        }
    }

    /**
     * Email de demande d'avis global J+28 après livraison confirmée.
     *
     * @param callable(string):void $log
     * @param bool                  $dryRun
     * @return void
     */
    private function doJobSendReviewRequest(callable $log, bool $dryRun): void
    {
        $orderModel   = new \Model\OrderModel();
        $accountModel = new \Model\AccountModel();
        $reviewModel  = new \Model\ReviewModel();
        $mailer       = $this->newMailService();

        $orders = $orderModel->getDeliveredForReviewEmail();
        $log('Commandes éligibles demande avis : ' . count($orders));

        foreach ($orders as $order) {
            $account = $accountModel->findById((int) $order['user_id']);
            if (!$account) {
                $log('[SKIP] user_id=' . $order['user_id']);
                continue;
            }

            if ($reviewModel->findByAccountId((int) $order['user_id']) !== null) {
                $log('[SKIP already-reviewed] commande #' . $order['id'] . ' user_id=' . $order['user_id']);
                $orderModel->markReviewEmailSent((int) $order['id']);
                continue;
            }

            if ($dryRun) {
                $log('[DRY-RUN] Would send review-request to ' . $account['email'] . self::LOG_ORDER_REF . $order['id']);
                continue;
            }

            $lang = in_array($account['lang'] ?? '', ['fr', 'en'], true) ? $account['lang'] : 'fr';
            $name = trim((string) ($account['firstname'] ?? '')) ?: 'Client';

            try {
                $mailer->sendReviewRequest(
                    $account['email'],
                    $name,
                    (int) $order['user_id'],
                    (int) $order['id'],
                    $lang
                );
                $orderModel->markReviewEmailSent((int) $order['id']);
                $log(self::LOG_OK . $account['email'] . self::LOG_ORDER_REF . $order['id']);
            } catch (\Throwable $e) {
                $log('[ERR] commande #' . $order['id'] . ' : ' . $e->getMessage());
            }
        }
    }
}
