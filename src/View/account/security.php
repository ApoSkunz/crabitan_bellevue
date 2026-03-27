<?php
$pageTitle     = __('account.security');
$activeSection = 'security';
require_once __DIR__ . '/../partials/head.php';
require_once __DIR__ . '/../partials/header.php';

/** @var array<int, array<string, mixed>> $sessions */
/** @var array<string, string> $errors */
/** @var string|null $success */
/** @var string $currentToken */
$deleteError = $errors['delete'] ?? null;
?>
<main class="account-page">
    <div class="account-shell">
        <?php require_once __DIR__ . '/_nav.php'; ?>

        <div class="account-content">
            <header class="account-header">
                <h1 class="account-header__title"><?= __('account.security') ?></h1>
            </header>

            <!-- Changement de mot de passe -->
            <section class="account-section">
                <h2 class="account-section__title"><?= __('account.change_password') ?></h2>

                <?php if ($success) : ?>
                    <div class="alert alert--success" role="alert"><?= htmlspecialchars($success) ?></div>
                <?php endif; ?>

                <form method="POST"
                      action="/<?= htmlspecialchars($lang) ?>/mon-compte/securite/mot-de-passe"
                      class="account-form"
                      novalidate>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

                    <div class="form-group form-group--pwd">
                        <label for="current_password"><?= __('account.current_password') ?></label>
                        <div class="form-pwd-wrap">
                            <input type="password" id="current_password" name="current_password"
                                   autocomplete="current-password" required>
                            <button type="button" class="form-pwd-toggle" data-target="current_password"
                                    aria-label="<?= __('account.show_password') ?>">
                                <span class="pwd-eye--show" aria-hidden="true">👁</span>
                                <span class="pwd-eye--hide" aria-hidden="true" hidden>🙈</span>
                            </button>
                        </div>
                        <?php if (isset($errors['current_password'])) : ?>
                            <p class="form-error"><?= htmlspecialchars($errors['current_password']) ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="form-group form-group--pwd">
                        <label for="new_password"><?= __('account.new_password') ?></label>
                        <div class="form-pwd-wrap">
                            <input type="password" id="new_password" name="new_password"
                                   autocomplete="new-password" required minlength="12">
                            <button type="button" class="form-pwd-toggle" data-target="new_password"
                                    aria-label="<?= __('account.show_password') ?>">
                                <span class="pwd-eye--show" aria-hidden="true">👁</span>
                                <span class="pwd-eye--hide" aria-hidden="true" hidden>🙈</span>
                            </button>
                        </div>
                        <?php if (isset($errors['new_password'])) : ?>
                            <p class="form-error"><?= htmlspecialchars($errors['new_password']) ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="form-group form-group--pwd">
                        <label for="new_password_confirm"><?= __('account.confirm_password') ?></label>
                        <div class="form-pwd-wrap">
                            <input type="password" id="new_password_confirm" name="new_password_confirm"
                                   autocomplete="new-password" required minlength="12">
                            <button type="button" class="form-pwd-toggle" data-target="new_password_confirm"
                                    aria-label="<?= __('account.show_password') ?>">
                                <span class="pwd-eye--show" aria-hidden="true">👁</span>
                                <span class="pwd-eye--hide" aria-hidden="true" hidden>🙈</span>
                            </button>
                        </div>
                        <?php if (isset($errors['new_password_confirm'])) : ?>
                            <p class="form-error"><?= htmlspecialchars($errors['new_password_confirm']) ?></p>
                        <?php endif; ?>
                    </div>

                    <button type="submit" class="btn btn--primary">
                        <?= __('account.save_password') ?>
                    </button>
                </form>
            </section>

            <!-- Sessions actives -->
            <section class="account-section">
                <h2 class="account-section__title"><?= __('account.active_sessions') ?></h2>

                <?php if ($sessions === []) : ?>
                    <p class="account-empty"><?= __('account.no_sessions') ?></p>
                <?php else : ?>
                    <ul class="account-sessions" role="list">
                        <?php foreach ($sessions as $session) : ?>
                            <?php $isCurrent = isset($currentToken) && isset($session['token']) && $session['token'] === $currentToken; ?>
                            <li class="account-session<?= $isCurrent ? ' account-session--current' : '' ?>">
                                <div class="account-session__info">
                                    <strong><?= htmlspecialchars($session['device_name'] ?? __('account.unknown_device')) ?></strong>
                                    <span><?= htmlspecialchars($session['ip_address'] ?? '—') ?></span>
                                    <span class="account-session__date">
                                        <?= htmlspecialchars(
                                            date('d/m/Y H:i', strtotime($session['created_at']))
                                        ) ?>
                                    </span>
                                    <?php if ($isCurrent) : ?>
                                        <span class="account-session__badge"><?= __('account.current_session') ?></span>
                                    <?php endif; ?>
                                </div>
                                <form method="POST"
                                      action="/<?= htmlspecialchars($lang) ?>/mon-compte/securite/session/<?= (int) $session['id'] ?>/revoquer"
                                      <?php if ($isCurrent) : ?>
                                          data-confirm="<?= htmlspecialchars(__('account.revoke_current_confirm')) ?>"
                                      <?php endif; ?>>
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                                    <button type="submit" class="btn btn--ghost btn--sm btn--danger">
                                        <?= __('account.revoke_session') ?>
                                    </button>
                                </form>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>

            <!-- Suppression de compte -->
            <section class="account-section account-section--danger">
                <h2 class="account-section__title"><?= __('account.danger_zone') ?></h2>

                <?php if ($deleteError) : ?>
                    <div class="alert alert--error" role="alert"><?= htmlspecialchars($deleteError) ?></div>
                <?php endif; ?>

                <p><?= __('account.delete_account_info') ?></p>

                <form method="POST"
                      action="/<?= htmlspecialchars($lang) ?>/mon-compte/securite/supprimer-compte"
                      data-confirm="<?= htmlspecialchars(__('account.delete_account_confirm')) ?>">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                    <button type="submit" class="btn btn--danger">
                        <?= __('account.delete_account_btn') ?>
                    </button>
                </form>
            </section>
        </div>
    </div>
</main>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>
