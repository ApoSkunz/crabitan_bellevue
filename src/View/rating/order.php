<?php
/**
 * Vue de notation d'une commande complète via token email.
 *
 * @var string $lang       Code langue ('fr' ou 'en')
 * @var int    $orderId    Identifiant de la commande
 * @var string $orderToken Token de la commande
 * @var array<int, array<string, mixed>> $wines Vins à noter
 * @var bool   $noted      true si une note vient d'être soumise
 * @var string $pageTitle  Titre de la page
 */

$pageTitle = $pageTitle ?? ($lang === 'fr'
    ? "Notez vos vins — Commande #{$orderId}"
    : "Rate your wines — Order #{$orderId}");
require_once SRC_PATH . '/View/partials/head.php';
require_once SRC_PATH . '/View/partials/header.php';

$isFr       = ($lang !== 'en');
$totalWines = count($wines);
$alreadyRated = array_filter($wines, fn(array $w): bool => (int) ($w['current_note'] ?? 0) > 0);
$allDone    = $totalWines > 0 && count($alreadyRated) === $totalWines;
?>

<main class="page-rating" id="main-content">
    <div class="page-hero page-hero--dark">
        <div class="container">
            <span class="home-section__tag">
                <?= $isFr ? 'Commande' : 'Order' ?>
                #<?= htmlspecialchars((string) $orderId, ENT_QUOTES) ?>
            </span>
            <h1 class="home-section__title">
                <?= $isFr ? 'Notez vos vins' : 'Rate your wines' ?>
            </h1>
            <div class="home-section__divider home-section__divider--center"></div>
        </div>
    </div>

    <div class="container">

        <p class="rating-order__intro">
            <?= $isFr
                ? 'Cliquez sur une étoile pour noter chaque vin. Votre avis est enregistré immédiatement.'
                : 'Click a star to rate each wine. Your review is saved immediately.' ?>
        </p>

        <!-- Bandeau succès (masqué jusqu'à complétion) -->
        <div id="rating-success-banner"
             class="rating-order__alert"
             role="alert"
             <?= $allDone ? '' : 'hidden' ?>>
            <?= $isFr
                ? '&#10003;&nbsp; Merci&nbsp;! Toutes vos notes ont été enregistrées.'
                : '&#10003;&nbsp; Thank you! All your ratings have been saved.' ?>
        </div>

        <?php if (empty($wines)) : ?>
        <p class="rating-order__empty">
            <?= $isFr ? 'Aucun vin trouvé pour cette commande.' : 'No wines found for this order.' ?>
        </p>
        <?php else : ?>

        <div class="rating-order__list" id="rating-list"
             data-total="<?= $totalWines ?>"
             data-rated="<?= count($alreadyRated) ?>">
            <?php foreach ($wines as $wine) :
                $wineId      = (int) ($wine['wine_id'] ?? 0);
                $label       = htmlspecialchars((string) ($wine['label_name'] ?? ''), ENT_QUOTES);
                $vintage     = (int) ($wine['vintage'] ?? 0);
                $imagePath   = (string) ($wine['image_path'] ?? '');
                $token       = htmlspecialchars((string) ($wine['token'] ?? ''), ENT_QUOTES);
                $currentNote = (int) ($wine['current_note'] ?? 0);
            ?>
            <div class="rating-order__item" data-wine-id="<?= $wineId ?>" data-token="<?= $token ?>">

                <?php if ($imagePath !== '') : ?>
                <img
                    src="/assets/images/wines/<?= htmlspecialchars($imagePath, ENT_QUOTES) ?>"
                    alt="<?= $label ?>"
                    class="rating-order__img"
                    width="64"
                    height="auto"
                    loading="lazy">
                <?php endif; ?>

                <div class="rating-order__info">
                    <p class="rating-order__wine-name">
                        <?= $label ?>
                        <?php if ($vintage > 0) : ?>
                        <span class="rating-order__vintage"><?= htmlspecialchars((string) $vintage, ENT_QUOTES) ?></span>
                        <?php endif; ?>
                    </p>

                    <div class="rating-order__stars"
                         role="group"
                         aria-label="<?= $isFr ? 'Notez ' . $label : 'Rate ' . $label ?>">
                        <?php for ($i = 1; $i <= 5; $i++) : ?>
                        <button
                            type="button"
                            class="rating-order__star<?= ($i <= $currentNote) ? ' rating-order__star--active' : '' ?>"
                            data-note="<?= $i ?>"
                            aria-label="<?= htmlspecialchars((string) $i, ENT_QUOTES) ?>/5"
                            aria-pressed="<?= ($i <= $currentNote) ? 'true' : 'false' ?>">
                            &#9733;<?= $i ?>
                        </button>
                        <?php endfor; ?>
                    </div>

                    <p class="rating-order__current" <?= $currentNote > 0 ? '' : 'hidden' ?>>
                        <?php if ($currentNote > 0) : ?>
                        <?= $isFr
                            ? '&#10003;&nbsp; Noté ' . htmlspecialchars((string) $currentNote, ENT_QUOTES) . '/5'
                            : '&#10003;&nbsp; Rated ' . htmlspecialchars((string) $currentNote, ENT_QUOTES) . '/5' ?>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php endif; ?>

        <a href="/<?= htmlspecialchars($lang, ENT_QUOTES) ?>/mon-compte/commandes" class="rating-order__back">
            <?= $isFr ? 'Voir mes commandes' : 'View my orders' ?>
        </a>

    </div>
</main>

<script nonce="<?= htmlspecialchars(defined('CSP_NONCE') ? CSP_NONCE : '', ENT_QUOTES) ?>">
(function () {
    const isFr   = <?= $isFr ? 'true' : 'false' ?>;
    const list   = document.getElementById('rating-list');
    const banner = document.getElementById('rating-success-banner');
    if (!list) return;

    let rated = parseInt(list.dataset.rated, 10) || 0;
    const total = parseInt(list.dataset.total, 10) || 0;

    list.addEventListener('click', function (e) {
        const btn = e.target.closest('.rating-order__star');
        if (!btn) return;

        const item  = btn.closest('.rating-order__item');
        const token = item ? item.dataset.token : '';
        const note  = parseInt(btn.dataset.note, 10);
        if (!token || !note) return;

        // Optimistic UI — active immédiatement
        const stars = item.querySelectorAll('.rating-order__star');
        const wasRated = Array.from(stars).some((s) => s.getAttribute('aria-pressed') === 'true');

        stars.forEach((s, idx) => {
            const active = idx < note;
            s.classList.toggle('rating-order__star--active', active);
            s.setAttribute('aria-pressed', active ? 'true' : 'false');
        });

        // Mise à jour du label note
        const label = item.querySelector('.rating-order__current');
        if (label) {
            label.hidden = false;
            label.innerHTML = isFr
                ? '\u2713\u00a0 Not\u00e9 ' + note + '/5'
                : '\u2713\u00a0 Rated ' + note + '/5';
        }

        if (!wasRated) { rated++; }

        // Vérif complétion
        if (rated >= total && banner) {
            banner.hidden = false;
            banner.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }

        // Envoi AJAX
        fetch('/api/rating/commande', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ token, note }),
        }).catch(function () {
            // Rollback visuel si erreur réseau
            stars.forEach(function (s) {
                s.classList.remove('rating-order__star--active');
                s.setAttribute('aria-pressed', 'false');
            });
            if (label) { label.hidden = true; }
            if (!wasRated) { rated--; }
        });
    });
}());
</script>

<?php require_once SRC_PATH . '/View/partials/footer.php'; ?>
