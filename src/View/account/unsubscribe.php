<?php
$pageTitle = __('newsletter.unsubscribe_title');
require_once __DIR__ . '/../partials/head.php';
require_once __DIR__ . '/../partials/header.php';

/** @var bool $success */
/** @var bool $confirm */
/** @var string|null $unsubToken */
?>
<main class="auth-page">
    <div class="auth-card">
        <?php if ($success) : ?>
            <div class="auth-status auth-status--ok" aria-live="polite">
                <span class="auth-status__icon" aria-hidden="true">✓</span>
            </div>
            <h1><?= __('newsletter.unsubscribe_title') ?></h1>
            <p><?= htmlspecialchars(__('newsletter.unsubscribed')) ?></p>
            <a class="btn btn--ghost btn--sm" href="/<?= htmlspecialchars($lang) ?>">
                <?= __('nav.home') ?>
            </a>
        <?php elseif ($confirm ?? false) : ?>
            <div class="auth-status auth-status--ok" aria-live="polite">
                <span class="auth-status__icon" aria-hidden="true">✉</span>
            </div>
            <h1><?= __('newsletter.unsubscribe_title') ?></h1>
            <p style="margin-bottom:1.5rem;"><?= htmlspecialchars(__('newsletter.unsubscribe_confirm')) ?></p>
            <form method="POST"
                  action="/<?= htmlspecialchars($lang) ?>/newsletter/desabonnement">
                <input type="hidden" name="unsub_token"  value="<?= htmlspecialchars($unsubToken ?? '') ?>">
                <?php if (!empty($campaignId)) : ?>
                <input type="hidden" name="campaign_id" value="<?= (int) $campaignId ?>">
                <?php endif; ?>
                <button type="submit" class="btn btn--primary btn--sm"
                        style="display:block;width:100%;text-align:center;margin-bottom:0.75rem;">
                    <?= htmlspecialchars(__('newsletter.unsubscribe_button')) ?>
                </button>
            </form>
            <a class="btn btn--ghost btn--sm" href="/<?= htmlspecialchars($lang) ?>"
               style="display:block;text-align:center;">
                <?= htmlspecialchars(__('btn.cancel')) ?>
            </a>
        <?php else : ?>
            <div class="auth-status auth-status--error" aria-live="polite">
                <span class="auth-status__icon" aria-hidden="true">✗</span>
            </div>
            <h1><?= __('newsletter.unsubscribe_title') ?></h1>
            <p><?= __('newsletter.unsubscribe_error') ?></p>
            <a class="btn btn--ghost btn--sm" href="/<?= htmlspecialchars($lang) ?>/mon-compte/profil">
                <?= __('nav.account') ?>
            </a>
        <?php endif; ?>
    </div>
</main>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>
