<?php require_once SRC_PATH . '/View/admin/_open.php'; ?>

<?php
/** @var array{ca: float, count: int, avg: float} $stats */
/** @var array<int, array{label: string, ca: float, count: int}> $chartData */
/** @var array<int, int> $availableYears */

$chartLabels = array_column($chartData, 'label');
$chartCa     = array_column($chartData, 'ca');
$chartCount  = array_column($chartData, 'count');

// Formatage labels selon granularité
$chartLabelsFormatted = array_map(function (string $label) use ($granularity): string {
    return match ($granularity) {
        'daily'  => (new DateTime($label))->format('d/m'),
        'monthly' => (function () use ($label): string {
            [$y, $m] = explode('-', $label);
            $months = ['', 'Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Jun', 'Jul', 'Aoû', 'Sep', 'Oct', 'Nov', 'Déc'];
            return ($months[(int) $m] ?? $m) . ' ' . $y;
        })(),
        default  => $label,
    };
}, $chartLabels);
?>

<div class="admin-page-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;">
    <h1>Statistiques CA</h1>
    <span style="font-size:0.875rem;color:#8a7a60;"><?= htmlspecialchars($periodLabel) ?></span>
</div>

<!-- ================================================================
     Filtres période
================================================================ -->
<div style="display:flex;flex-wrap:wrap;gap:0.5rem;margin-bottom:2rem;">
    <?php
    $filters = [
        '30d' => '30 jours',
        '3m'  => '3 mois',
        '1y'  => '12 mois',
    ];
    foreach ($filters as $key => $label) :
        $active = $period === $key;
    ?>
        <a href="/admin/statistiques?period=<?= $key ?>"
           class="admin-btn admin-btn--sm <?= $active ? 'admin-btn--primary' : 'admin-btn--outline' ?>">
            <?= $label ?>
        </a>
    <?php endforeach; ?>

    <?php foreach ($availableYears as $yr) :
        $active = $period === (string) $yr;
    ?>
        <a href="/admin/statistiques?period=<?= $yr ?>"
           class="admin-btn admin-btn--sm <?= $active ? 'admin-btn--primary' : 'admin-btn--outline' ?>">
            <?= $yr ?>
        </a>
    <?php endforeach; ?>

    <?php $active = $period === 'all'; ?>
    <a href="/admin/statistiques?period=all"
       class="admin-btn admin-btn--sm <?= $active ? 'admin-btn--primary' : 'admin-btn--outline' ?>">
        Depuis toujours
    </a>
</div>

<!-- ================================================================
     KPI cards
================================================================ -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem;margin-bottom:2rem;">

    <div class="admin-card">
        <div class="admin-card__body" style="text-align:center;">
            <div style="font-size:0.75rem;text-transform:uppercase;letter-spacing:0.06em;color:#8a7a60;margin-bottom:0.5rem;">
                Chiffre d'affaires
            </div>
            <div style="font-size:1.75rem;font-weight:700;color:#1a1208;font-family:Georgia,serif;">
                <?= number_format($stats['ca'], 2, ',', '&nbsp;') ?>&nbsp;€ <?php // NOSONAR php:S1192 — '&nbsp;' est une entité HTML, pas une constante métier ?>
            </div>
        </div>
    </div>

    <div class="admin-card">
        <div class="admin-card__body" style="text-align:center;">
            <div style="font-size:0.75rem;text-transform:uppercase;letter-spacing:0.06em;color:#8a7a60;margin-bottom:0.5rem;">
                Commandes
            </div>
            <div style="font-size:1.75rem;font-weight:700;color:#1a1208;font-family:Georgia,serif;">
                <?= number_format($stats['count'], 0, ',', '&nbsp;') ?>
            </div>
        </div>
    </div>

    <div class="admin-card">
        <div class="admin-card__body" style="text-align:center;">
            <div style="font-size:0.75rem;text-transform:uppercase;letter-spacing:0.06em;color:#8a7a60;margin-bottom:0.5rem;">
                Panier moyen
            </div>
            <div style="font-size:1.75rem;font-weight:700;color:#1a1208;font-family:Georgia,serif;">
                <?= $stats['count'] > 0 ? number_format($stats['avg'], 2, ',', '&nbsp;') . '&nbsp;€' : '—' ?>
            </div>
        </div>
    </div>

</div>

<!-- ================================================================
     Graphique
================================================================ -->
<div class="admin-card">
    <div class="admin-card__body">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem;flex-wrap:wrap;gap:0.75rem;">
            <h2 style="font-size:1rem;font-weight:600;color:#1a1208;margin:0;">
                Évolution du CA — <?= htmlspecialchars($periodLabel) ?>
            </h2>
            <div style="display:flex;gap:0.5rem;">
                <button type="button" id="js-chart-bar"
                        class="admin-btn admin-btn--sm admin-btn--outline"
                        style="min-width:80px;">
                    Barres
                </button>
                <button type="button" id="js-chart-line"
                        class="admin-btn admin-btn--sm admin-btn--primary"
                        style="min-width:80px;">
                    Courbe
                </button>
            </div>
        </div>

        <?php if ($chartData === []) : ?>
            <p style="text-align:center;color:#8a7a60;padding:3rem 0;">Aucune donnée pour cette période</p>
        <?php else : ?>
            <div style="position:relative;height:320px;">
                <canvas id="ca-chart"></canvas>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($chartData !== []) : ?>
<script type="application/json" id="chart-data"><?= json_encode([
    'labels'  => $chartLabelsFormatted,
    'caData'  => array_map(fn(float $v) => round($v, 2), $chartCa),
    'cntData' => $chartCount,
], JSON_UNESCAPED_UNICODE) ?></script>
<script src="/assets/js/admin-charts.js" type="module"></script>
<?php endif; ?>

<?php require_once SRC_PATH . '/View/admin/_close.php'; ?>
