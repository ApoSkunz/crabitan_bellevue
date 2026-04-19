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
// NOSONAR php:S1448 — 14 jobs cron + 5 méthodes de routage + helpers intentionnellement regroupés ; découpage post-live (GL sprint 12)
class CronController extends Controller
{
    private const CONTENT_TYPE = 'Content-Type: text/plain; charset=utf-8';

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

        ob_start();

        $runner   = new CronRunner($job, '', $dryRun);
        $exitCode = $runner->run($fn);

        $output = (string) ob_get_clean();

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
            exit;
        }
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
            'send-abandoned-cart'  => fn(callable $log, bool $dryRun) => $this->doJobSendAbandonedCart($log, $dryRun),
            'send-post-purchase'   => fn(callable $log, bool $dryRun) => $this->doJobSendPostPurchase($log, $dryRun),
            'alert-pending-orders' => fn(callable $log, bool $dryRun) => $this->doJobAlertPendingOrders($log, $dryRun),
            'clean-expired-tokens' => fn(callable $log, bool $dryRun) => $this->doJobCleanExpiredTokens($log, $dryRun),
            default                => null,
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
        $model  = new \Model\AccountModel();
        $mailer = new \Service\MailService();
        $sent   = 0;

        foreach ([7, 1] as $days) {
            $accounts = $model->getAccountsPendingDeletionReminder($days);
            foreach ($accounts as $account) {
                $sent += $this->doSendReminderForAccount($log, $dryRun, $account, $days, $model, $mailer);
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
        $appUrl    = rtrim($_ENV['APP_URL'] ?? 'https://crabitanbellevue.fr', '/');

        $carts = $cartModel->getAbandonedForEmail();
        $log('Paniers éligibles : ' . count($carts));

        foreach ($carts as $data) {
            $cartUrl = ($data['lang'] === 'fr') ? $appUrl . '/fr/panier' : $appUrl . '/en/cart';

            if ($dryRun) {
                $log('[DRY-RUN] Would send abandoned cart to ' . $data['email'] . ' (cart_id=' . $data['cart_id'] . ')');
                continue;
            }

            try {
                $mailer->sendAbandonedCart($data['email'], $data['firstname'], $data['items'], $data['lang'], $cartUrl);
                $cartModel->markReminderSent($data['cart_id']);
                $log('[OK] ' . $data['email']);
            } catch (\Throwable $e) {
                $log('[ERR] ' . $data['email'] . ' : ' . $e->getMessage());
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
        $mailer       = new \Service\MailService();

        $orders = $orderModel->getDeliveredForRatingEmail();
        $log('Commandes éligibles notation : ' . count($orders));

        foreach ($orders as $order) {
            $account = $accountModel->findById((int) $order['user_id']);
            if (!$account) {
                $log('[SKIP] user_id=' . $order['user_id']);
                continue;
            }

            if ($dryRun) {
                $log('[DRY-RUN] Would send post-purchase to ' . $account['email'] . ' commande #' . $order['id']);
                continue;
            }

            $items = json_decode((string) $order['content'], true) ?? [];
            $lang  = in_array($account['lang'] ?? '', ['fr', 'en'], true) ? $account['lang'] : 'fr';

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
                $log('[OK] ' . $account['email'] . ' commande #' . $order['id']);
            } catch (\Throwable $e) {
                $log('[ERR] commande #' . $order['id'] . ' : ' . $e->getMessage());
            }
        }
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
        $mailer      = new \Service\MailService();
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
}
