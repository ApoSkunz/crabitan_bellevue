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
class CronController extends Controller
{
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
            header('Content-Type: text/plain; charset=utf-8');
            echo "Unknown job: {$job}\n";
            return;
        }

        $dryRun = isset($_GET['dry_run']) && $_GET['dry_run'] === '1';

        ob_start();

        $runner   = new CronRunner($job, '', $dryRun);
        $exitCode = $runner->run($fn);

        $output = (string) ob_get_clean();

        http_response_code($exitCode === 0 ? 200 : 500);
        header('Content-Type: text/plain; charset=utf-8');
        echo $output;
    }

    /**
     * Vérifie le token secret en temps constant — renvoie 403 si invalide ou absent.
     *
     * @return void
     */
    private function verifyToken(): void
    {
        $expected = $_ENV['CRON_SECRET_TOKEN'] ?? '';
        $provided = $_GET['token'] ?? '';

        if ($expected === '' || !hash_equals($expected, $provided)) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=utf-8');
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
        return match ($job) {
            'purge-accounts'                => function (callable $log, bool $dryRun): void {
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
            },

            'purge-inactive-accounts'       => function (callable $log, bool $dryRun): void {
                $model          = new \Model\AccountModel();
                $mailer         = new \Service\MailService();
                $inactiveMonths = (int) ($_ENV['RGPD_INACTIVITY_MONTHS'] ?? 36);

                $log("Seuil d'inactivité : {$inactiveMonths} mois");

                $inactive = $model->getInactiveAccounts($inactiveMonths);
                $log('Phase 1 — comptes à avertir : ' . count($inactive));

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

                $toDelete    = $model->getAccountsToSoftDelete();
                $softDeleted = 0;
                $log('Phase 2 — comptes à soft-delete : ' . count($toDelete));

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

                if (!$dryRun) {
                    $log("Terminé : {$warned} avertissement(s), {$softDeleted} soft-delete(s)");
                } else {
                    $log('[DRY-RUN] Simulation terminée — aucune écriture BDD, aucun email envoyé');
                }
            },

            'pseudonymize-orders'           => function (callable $log, bool $dryRun): void {
                $model = new \Model\OrderModel();

                if ($dryRun) {
                    $log('[DRY-RUN] Would pseudonymize expired order snapshots — aucune écriture BDD');
                    return;
                }

                $count = $model->pseudonymizeExpired();
                $log("{$count} snapshot(s) pseudonymisé(s)");
            },

            'purge-connections'             => function (callable $log, bool $dryRun): void {
                $model = new \Model\ConnectionModel();

                if ($dryRun) {
                    $log('[DRY-RUN] Would purge expired/revoked connections — aucune écriture BDD');
                    return;
                }

                $purged = $model->purgeExpired();
                $log("{$purged} session(s) purgée(s)");
            },

            'purge-addresses'               => function (callable $log, bool $dryRun): void {
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
            },

            'purge-newsletter-subscriptions' => function (callable $log, bool $dryRun): void {
                $model = new \Model\NewsletterSubscriptionModel();

                if ($dryRun) {
                    $log('[DRY-RUN] Would purge expired pending subscriptions — aucune écriture BDD');
                    $log('[DRY-RUN] Would purge subscriptions older than 13 months — aucune écriture BDD');
                    return;
                }

                $pending = $model->purgeExpiredPending();
                $log("{$pending} abonnement(s) non confirmé(s) expiré(s) supprimé(s)");

                $old = $model->purgeOlderThan13Months();
                $log("{$old} abonnement(s) confirmé(s) de plus de 13 mois supprimé(s) (rétention RGPD)");
            },

            'purge-rate-limits'             => function (callable $log, bool $dryRun): void {
                $db = \Core\Database::getInstance();

                if ($dryRun) {
                    $count = $db->fetchOne('SELECT COUNT(*) AS n FROM rate_limits WHERE expires_at < UNIX_TIMESTAMP()')['n'] ?? 0;
                    $log("[DRY-RUN] Would delete {$count} entrée(s) expirée(s) — aucune écriture BDD");
                    return;
                }

                $deleted = $db->execute('DELETE FROM rate_limits WHERE expires_at < UNIX_TIMESTAMP()');
                $log("rate_limits : {$deleted} entrée(s) expirée(s) supprimée(s)");
            },

            'send-deletion-reminders'       => function (callable $log, bool $dryRun): void {
                $model  = new \Model\AccountModel();
                $mailer = new \Service\MailService();
                $sent   = 0;

                foreach ([7, 1] as $days) {
                    $accounts = $model->getAccountsPendingDeletionReminder($days);

                    foreach ($accounts as $account) {
                        $name = trim(($account['firstname'] ?? '') . ' ' . ($account['lastname'] ?? ''))
                            ?: $account['email'];

                        if ($dryRun) {
                            $log("[DRY-RUN] Would send J-{$days} reminder to {$account['email']} (id={$account['id']})");
                            continue;
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
                            $sent++;
                            $log("[J-{$days}] Rappel envoyé à {$account['email']} (id={$account['id']})");
                        } catch (\Throwable $e) {
                            $log("[J-{$days}] ERREUR id={$account['id']} : " . $e->getMessage());
                        }
                    }
                }

                if (!$dryRun) {
                    $log("{$sent} rappel(s) envoyé(s)");
                }
            },

            'send-abandoned-cart'           => function (callable $log, bool $dryRun): void {
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
            },

            'send-post-purchase'            => function (callable $log, bool $dryRun): void {
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
            },

            'alert-pending-orders'          => function (callable $log, bool $dryRun): void {
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
            },

            'clean-expired-tokens'          => function (callable $log, bool $dryRun): void {
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
            },

            'purge-newsletter-tracking'     => function (callable $log, bool $dryRun): void {
                $model = new \Model\NewsletterTrackingModel();

                if ($dryRun) {
                    $log('[DRY-RUN] Would purge newsletter tracking older than 13 months — aucune écriture BDD');
                    return;
                }

                $deleted = $model->purgeOlderThan13Months();
                $log("{$deleted} enregistrement(s) de tracking purgé(s)");
            },

            default => null,
        };
    }
}
