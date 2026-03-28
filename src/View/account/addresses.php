<?php
$pageTitle     = __('account.addresses_title');
$activeSection = 'addresses';
require_once __DIR__ . '/../partials/head.php';
require_once __DIR__ . '/../partials/header.php';

/** @var array<int, array<string, mixed>> $addresses */
/** @var array<int, int> $lockedIds */
/** @var string $csrf */
$billing  = array_values(array_filter($addresses, fn($a) => $a['type'] === 'billing'));
$delivery = array_values(array_filter($addresses, fn($a) => $a['type'] === 'delivery'));
?>
<main class="account-page">
    <div class="account-shell">
        <?php require_once __DIR__ . '/_nav.php'; ?>

        <div class="account-content">
            <header class="account-header">
                <h1 class="account-header__title"><?= __('panel.addresses') ?></h1>
                <button type="button" class="btn btn--primary btn--sm js-address-add-toggle">
                    <?= __('account.address_add') ?>
                </button>
            </header>

            <?php if ($success) : ?>
                <div class="alert alert--success" role="alert"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            <?php if ($error) : ?>
                <div class="alert alert--error" role="alert"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <!-- Formulaire d'ajout (masqué par défaut) -->
            <section class="account-section account-address-new" id="address-add-form" hidden>
                <h2 class="account-section__title"><?= __('account.address_form_title_add') ?></h2>
                <form method="POST"
                      action="/<?= htmlspecialchars($lang) ?>/mon-compte/adresses/ajouter"
                      class="account-form account-address-form">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

                    <?php require_once __DIR__ . '/_address_fields.php'; ?>

                    <div class="account-form__actions">
                        <button type="submit" class="btn btn--primary">
                            <?= __('account.address_save') ?>
                        </button>
                        <button type="button" class="btn btn--ghost js-address-add-toggle">
                            <?= __('account.address_cancel') ?>
                        </button>
                    </div>
                </form>
            </section>

            <?php if ($addresses === []) : ?>
                <p class="account-empty"><?= __('account.addresses_empty') ?></p>
            <?php else : ?>
                <?php foreach (['billing' => $billing, 'delivery' => $delivery] as $type => $group) : ?>
                    <?php if ($group !== []) : ?>
                        <section class="account-address-group">
                            <h2 class="account-address-group__title">
                                <?= __('account.address_type_' . $type) ?>
                            </h2>
                            <div class="account-address-list">
                                <?php foreach ($group as $addr) : ?>
                                    <?php $isLocked = in_array((int) $addr['id'], $lockedIds, true); ?>
                                    <article class="account-address-card<?= $isLocked ? ' account-address-card--locked' : '' ?>">
                                        <p class="account-address-card__name">
                                            <?= htmlspecialchars(
                                                $addr['civility'] . ' '
                                                . $addr['firstname'] . ' '
                                                . $addr['lastname']
                                            ) ?>
                                        </p>
                                        <p><?= htmlspecialchars($addr['street']) ?></p>
                                        <p><?= htmlspecialchars($addr['zip_code'] . ' ' . $addr['city']) ?></p>
                                        <p><?= htmlspecialchars($addr['country']) ?></p>
                                        <?php if ($addr['phone']) : ?>
                                            <p><?= htmlspecialchars($addr['phone']) ?></p>
                                        <?php endif; ?>

                                        <?php if ($isLocked) : ?>
                                            <p class="account-address-card__locked-notice">
                                                <?= __('account.address_locked_notice') ?>
                                            </p>
                                        <?php else : ?>
                                            <div class="account-address-card__actions">
                                                <a href="/<?= htmlspecialchars($lang) ?>/mon-compte/adresses/<?= (int) $addr['id'] ?>/modifier"
                                                   class="btn btn--ghost btn--sm">
                                                    <?= __('account.address_edit') ?>
                                                </a>
                                                <form method="POST"
                                                      action="/<?= htmlspecialchars($lang) ?>/mon-compte/adresses/<?= (int) $addr['id'] ?>/supprimer"
                                                      data-confirm="<?= htmlspecialchars(__('account.address_delete_confirm')) ?>">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                                                    <button type="submit" class="btn btn--ghost btn--sm btn--danger">
                                                        <?= __('account.address_delete') ?>
                                                    </button>
                                                </form>
                                            </div>
                                        <?php endif; ?>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</main>
<?php require_once __DIR__ . '/../partials/footer.php'; ?>
