<?php
$pageTitle     = __('account.addresses_title');
$activeSection = 'addresses';
require_once __DIR__ . '/../partials/head.php';
require_once __DIR__ . '/../partials/header.php';

/** @var array<int, array<string, mixed>> $addresses */
$billing  = array_values(array_filter($addresses, fn($a) => $a['type'] === 'billing'));
$delivery = array_values(array_filter($addresses, fn($a) => $a['type'] === 'delivery'));
?>
<main class="account-page">
    <div class="account-shell">
        <?php require_once __DIR__ . '/_nav.php'; ?>

        <div class="account-content">
            <header class="account-header">
                <h1 class="account-header__title"><?= __('panel.addresses') ?></h1>
            </header>

            <?php if ($success) : ?>
                <div class="alert alert--success" role="alert"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            <?php if ($error) : ?>
                <div class="alert alert--error" role="alert"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

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
                                    <article class="account-address-card">
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
