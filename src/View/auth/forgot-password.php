<?php
$pageTitle = __('auth.forgot_password');
$noindex   = true;
require_once __DIR__ . '/../partials/head.php';
require_once __DIR__ . '/../partials/header.php';
?>
<main class="auth-page">
    <div class="auth-card">
        <h1><?= __('auth.forgot_password') ?></h1>

        <?php if ($info) : ?>
            <div class="alert alert--info" role="alert">
                <?= htmlspecialchars($info) ?>
            </div>
        <?php endif; ?>

        <?php if (!$info) : ?>
            <form method="POST" action="/<?= htmlspecialchars($lang) ?>/mot-de-passe-oublie" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

                <div class="form-group">
                    <label for="email"><?= __('auth.email') ?></label>
                    <input type="email" id="email" name="email"
                           autocomplete="email" required autofocus>
                </div>

                <button type="submit" class="btn btn--primary btn--full">
                    <?= __('btn.submit') ?>
                </button>
            </form>
        <?php endif; ?>

        <p class="auth-card__switch">
            <a href="/<?= htmlspecialchars($lang) ?>/connexion"><?= __('btn.back') ?></a>
        </p>
    </div>
</main>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>
