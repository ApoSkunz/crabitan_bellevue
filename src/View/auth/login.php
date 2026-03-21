<?php
$pageTitle = __('auth.login');
$noindex   = true;
require __DIR__ . '/../partials/head.php';
require __DIR__ . '/../partials/header.php';
?>
<main class="auth-page">
    <div class="auth-card">
        <h1><?= __('auth.login') ?></h1>

        <?php if ($error) : ?>
            <div class="alert alert--error" role="alert">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($info) : ?>
            <div class="alert alert--info" role="alert">
                <?= htmlspecialchars($info) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="/<?= htmlspecialchars($lang) ?>/connexion" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

            <div class="form-group">
                <label for="email"><?= __('auth.email') ?></label>
                <input type="email" id="email" name="email"
                       autocomplete="email" required autofocus>
            </div>

            <div class="form-group">
                <label for="password"><?= __('auth.password') ?></label>
                <input type="password" id="password" name="password"
                       autocomplete="current-password" required>
            </div>

            <div class="form-footer">
                <a href="/<?= htmlspecialchars($lang) ?>/mot-de-passe-oublie" class="link--muted">
                    <?= __('auth.forgot_password') ?>
                </a>
            </div>

            <button type="submit" class="btn btn--primary btn--full">
                <?= __('auth.login') ?>
            </button>
        </form>

        <p class="auth-card__switch">
            <a href="/<?= htmlspecialchars($lang) ?>/inscription"><?= __('auth.register') ?></a>
        </p>
    </div>
</main>
<?php require __DIR__ . '/../partials/footer.php'; ?>
