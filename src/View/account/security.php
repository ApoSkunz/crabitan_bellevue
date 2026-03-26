<?php
$pageTitle     = __('account.security');
$activeSection = 'security';
require_once __DIR__ . '/../partials/head.php';
require_once __DIR__ . '/../partials/header.php';

/** @var array<int, array<string, mixed>> $sessions */
/** @var array<string, string> $errors */
/** @var string|null $success */
/** @var string $currentToken */
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

                    <div class="form-group">
                        <label for="current_password"><?= __('account.current_password') ?></label>
                        <input type="password" id="current_password" name="current_password"
                               autocomplete="current-password" required>
                        <?php if (isset($errors['current_password'])) : ?>
                            <p class="form-error"><?= htmlspecialchars($errors['current_password']) ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="new_password"><?= __('account.new_password') ?></label>
                        <input type="password" id="new_password" name="new_password"
                               autocomplete="new-password" required minlength="12">
                        <?php if (isset($errors['new_password'])) : ?>
                            <p class="form-error"><?= htmlspecialchars($errors['new_password']) ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="new_password_confirm"><?= __('account.confirm_password') ?></label>
                        <input type="password" id="new_password_confirm" name="new_password_confirm"
                               autocomplete="new-password" required minlength="12">
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
                            <?php $isCurrent = isset($currentToken) && $session['token_hash'] === $currentToken; ?>
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
                                <?php if (!$isCurrent) : ?>
                                    <form method="POST"
                                          action="/<?= htmlspecialchars($lang) ?>/mon-compte/securite/session/<?= (int) $session['id'] ?>/revoquer">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                                        <button type="submit" class="btn btn--ghost btn--sm btn--danger">
                                            <?= __('account.revoke_session') ?>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>
        </div>
    </div>
</main>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>
