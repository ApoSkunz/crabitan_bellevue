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

$appUrl = rtrim($_ENV['APP_URL'] ?? '', '/');
?>
<?php require_once SRC_PATH . '/View/partials/head.php'; ?>

<main class="rating-order" style="max-width:680px;margin:48px auto;padding:0 24px;">

    <h1 style="font-family:Georgia,serif;font-size:22px;color:var(--color-gold);margin:0 0 8px;">
        <?= $lang === 'fr'
            ? 'Notez vos vins — Commande #' . htmlspecialchars((string) $orderId, ENT_QUOTES)
            : 'Rate your wines — Order #' . htmlspecialchars((string) $orderId, ENT_QUOTES) ?>
    </h1>

    <p style="font-size:14px;color:var(--color-text-muted);margin:0 0 32px;">
        <?= $lang === 'fr'
            ? 'Cliquez sur une étoile pour noter chaque vin. Votre avis est enregistré immédiatement.'
            : 'Click a star to rate each wine. Your review is saved immediately.' ?>
    </p>

    <?php if ($noted) : ?>
    <div role="alert" style="background:rgba(232,200,106,0.12);border:1px solid var(--color-gold);
         border-radius:4px;padding:12px 16px;margin-bottom:24px;
         font-size:14px;color:var(--color-gold);">
        <?= $lang === 'fr' ? 'Votre note a bien été enregistrée.' : 'Your rating has been saved.' ?>
    </div>
    <?php endif; ?>

    <?php if (empty($wines)) : ?>
    <p style="color:var(--color-text-muted);">
        <?= $lang === 'fr' ? 'Aucun vin trouvé pour cette commande.' : 'No wines found for this order.' ?>
    </p>
    <?php else : ?>

    <div class="rating-order__list">
        <?php foreach ($wines as $wine) : ?>
        <?php
            $wineId      = (int) ($wine['wine_id'] ?? 0);
            $label       = htmlspecialchars((string) ($wine['label_name'] ?? ''), ENT_QUOTES);
            $vintage     = (int) ($wine['vintage'] ?? 0);
            $imagePath   = (string) ($wine['image_path'] ?? '');
            $token       = htmlspecialchars((string) ($wine['token'] ?? ''), ENT_QUOTES);
            $currentNote = (int) ($wine['current_note'] ?? 0);
        ?>
        <div class="rating-order__item" style="
            border-bottom:1px solid rgba(232,200,106,0.15);
            padding:24px 0;
            display:flex;
            gap:20px;
            align-items:flex-start;">

            <?php if ($imagePath !== '') : ?>
            <img
                src="<?= htmlspecialchars($appUrl . '/assets/images/wines/' . $imagePath, ENT_QUOTES) ?>"
                alt="<?= $label ?>"
                width="64"
                height="auto"
                loading="lazy"
                style="width:64px;height:auto;border-radius:3px;flex-shrink:0;">
            <?php endif; ?>

            <div style="flex:1;min-width:0;">
                <p style="margin:0 0 4px;font-family:Georgia,serif;font-size:16px;color:var(--color-text);">
                    <?= $label ?>
                    <?php if ($vintage > 0) : ?>
                    <span style="font-size:13px;color:var(--color-text-muted);margin-left:6px;">
                        <?= htmlspecialchars((string) $vintage, ENT_QUOTES) ?>
                    </span>
                    <?php endif; ?>
                </p>

                <div
                    class="rating-order__stars"
                    role="group"
                    aria-label="<?= $lang === 'fr'
                        ? 'Notez ' . $label
                        : 'Rate ' . $label ?>">

                    <?php for ($i = 1; $i <= 5; $i++) : ?>
                    <?php
                        $ratingUrl = htmlspecialchars(
                            '/rating/commande/' . $wine['token'] . '/' . $i,
                            ENT_QUOTES
                        );
                        $isActive  = ($i <= $currentNote);
                        $bgActive  = 'background:linear-gradient(135deg,#e8c86a,#c9a84c);color:#1a1208;';
                        $bgIdle    = 'background:rgba(232,200,106,0.08);color:var(--color-gold);'
                            . 'border:1px solid rgba(232,200,106,0.25);';
                    ?>
                    <a
                        href="<?= $ratingUrl ?>?ref=order&orderToken=<?= htmlspecialchars($orderToken, ENT_QUOTES) ?>"
                        aria-label="<?= htmlspecialchars((string) $i, ENT_QUOTES) ?>/5"
                        aria-current="<?= $isActive ? 'true' : 'false' ?>"
                        style="
                            display:inline-flex;
                            align-items:center;
                            justify-content:center;
                            width:40px;height:40px;
                            margin:0 3px 0 0;
                            border-radius:3px;
                            font-family:Georgia,serif;
                            font-size:16px;
                            font-weight:bold;
                            text-decoration:none;
                            transition:opacity .15s;
                            <?= $isActive ? $bgActive : $bgIdle ?>">
                        &#9733;<?= $i ?>
                    </a>
                    <?php endfor; ?>

                </div>

                <?php if ($currentNote > 0) : ?>
                <p style="margin:8px 0 0;font-size:12px;color:var(--color-text-muted);">
                    <?= $lang === 'fr'
                        ? 'Votre note actuelle : ' . htmlspecialchars((string) $currentNote, ENT_QUOTES) . '/5'
                        : 'Your current rating: ' . htmlspecialchars((string) $currentNote, ENT_QUOTES) . '/5' ?>
                </p>
                <?php endif; ?>

            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php endif; ?>

    <p style="margin-top:40px;text-align:center;">
        <a
            href="/<?= htmlspecialchars($lang, ENT_QUOTES) ?>/mon-compte/commandes"
            style="font-size:13px;color:var(--color-text-muted);text-decoration:underline;">
            <?= $lang === 'fr' ? 'Voir mes commandes' : 'View my orders' ?>
        </a>
    </p>

</main>

<?php require_once SRC_PATH . '/View/partials/footer.php'; ?>
</body>
</html>
