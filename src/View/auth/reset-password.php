<?php
$pageTitle = __('auth.reset_password');
$noindex   = true;
require __DIR__ . '/../partials/head.php';
require __DIR__ . '/../partials/header.php';
?>
<main class="auth-page">
    <div class="auth-card">
        <h1><?= __('auth.reset_password') ?></h1>

        <?php if (!$valid) : ?>
            <div class="alert alert--error" role="alert">
                <?= htmlspecialchars($error ?? __('auth.reset_invalid')) ?>
            </div>
            <p class="auth-card__switch">
                <a href="/<?= htmlspecialchars($lang) ?>/mot-de-passe-oublie"><?= __('auth.forgot_password') ?></a>
            </p>
        <?php else : ?>
            <?php if ($error) : ?>
                <div class="alert alert--error" role="alert">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="/<?= htmlspecialchars($lang) ?>/reinitialisation/<?= htmlspecialchars($token) ?>" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

                <div class="form-group">
                    <label for="password"><?= __('auth.password') ?></label>
                    <input type="password" id="password" name="password"
                           autocomplete="new-password" required minlength="8" autofocus>
                </div>

                <div class="form-group">
                    <label for="password_confirm"><?= __('form.password_confirm') ?></label>
                    <input type="password" id="password_confirm" name="password_confirm"
                           autocomplete="new-password" required minlength="8">
                </div>

                <button type="submit" class="btn btn--primary btn--full">
                    <?= __('btn.save') ?>
                </button>
            </form>
        <?php endif; ?>
    </div>
</main>
<?php require __DIR__ . '/../partials/footer.php'; ?>
