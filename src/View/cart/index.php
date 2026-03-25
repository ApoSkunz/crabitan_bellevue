<?php
$pageTitle = __('cart.title');
require_once SRC_PATH . '/View/partials/head.php';
require_once SRC_PATH . '/View/partials/header.php';
?>

<main class="page-cart" id="main-content">
    <div class="page-hero page-hero--dark">
        <div class="container">
            <h1 class="home-section__title"><?= htmlspecialchars(__('cart.title')) ?></h1>
            <div class="home-section__divider home-section__divider--center"></div>
        </div>
    </div>

    <section class="cart-section container">
        <p class="cart-empty"><?= htmlspecialchars(__('cart.login_required')) ?></p>
    </section>
</main>

<?php require_once SRC_PATH . '/View/partials/footer.php'; ?>
