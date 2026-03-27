<?php
$pageTitle     = __('account.address_form_title_edit');
$activeSection = 'addresses';
require_once __DIR__ . '/../partials/head.php';
require_once __DIR__ . '/../partials/header.php';

/** @var array<string, mixed> $address */
/** @var string $csrf */
?>
<main class="account-page">
    <div class="account-shell">
        <?php require_once __DIR__ . '/_nav.php'; ?>

        <div class="account-content">
            <header class="account-header">
                <h1 class="account-header__title"><?= __('account.address_form_title_edit') ?></h1>
                <a class="account-header__back"
                   href="/<?= htmlspecialchars($lang) ?>/mon-compte/adresses">
                    ← <?= __('account.address_cancel') ?>
                </a>
            </header>

            <section class="account-section">
                <form method="POST"
                      action="/<?= htmlspecialchars($lang) ?>/mon-compte/adresses/<?= (int) $address['id'] ?>/modifier"
                      class="account-form account-address-form">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

                    <?php require_once __DIR__ . '/_address_fields.php'; ?>

                    <div class="account-form__actions">
                        <button type="submit" class="btn btn--primary">
                            <?= __('account.address_save') ?>
                        </button>
                        <a href="/<?= htmlspecialchars($lang) ?>/mon-compte/adresses"
                           class="btn btn--ghost">
                            <?= __('account.address_cancel') ?>
                        </a>
                    </div>
                </form>
            </section>
        </div>
    </div>
</main>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>
