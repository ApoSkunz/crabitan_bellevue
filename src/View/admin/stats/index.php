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
                <?= number_format($stats['ca'], 2, ',', '&nbsp;') ?>&nbsp;€
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
<script>
(function () {
    var labels  = <?= json_encode($chartLabelsFormatted, JSON_UNESCAPED_UNICODE) ?>;
    var caData  = <?= json_encode(array_map(fn(float $v) => round($v, 2), $chartCa)) ?>;
    var cntData = <?= json_encode($chartCount) ?>;

    var ctx     = document.getElementById('ca-chart').getContext('2d');
    var chart   = null;

    var GOLD     = 'rgba(201, 168, 76, 1)';
    var GOLD_BG  = 'rgba(201, 168, 76, 0.15)';
    var GOLD_BG2 = 'rgba(201, 168, 76, 0.75)';

    function buildDataset(type) {
        if (type === 'bar') {
            return {
                type: 'bar',
                label: 'CA (€)',
                data: caData,
                backgroundColor: GOLD_BG2,
                borderColor: GOLD,
                borderWidth: 1,
                borderRadius: 4,
            };
        }
        return {
            type: 'line',
            label: 'CA (€)',
            data: caData,
            borderColor: GOLD,
            backgroundColor: GOLD_BG,
            borderWidth: 2,
            pointRadius: caData.length > 60 ? 0 : 4,
            pointHoverRadius: 6,
            fill: true,
            tension: 0.35,
        };
    }

    function render(type) {
        if (chart) chart.destroy();
        chart = new Chart(ctx, {
            data: {
                labels: labels,
                datasets: [buildDataset(type)],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                var v = ctx.raw.toLocaleString('fr-FR', { minimumFractionDigits: 2 });
                                return ' ' + v + ' €';
                            },
                            afterLabel: function (ctx) {
                                var i = ctx.dataIndex;
                                return ' ' + cntData[i] + ' commande' + (cntData[i] > 1 ? 's' : '');
                            },
                        },
                    },
                },
                scales: {
                    x: {
                        grid: { color: 'rgba(0,0,0,0.04)' },
                        ticks: {
                            color: '#8a7a60',
                            font: { size: 11 },
                            maxRotation: 45,
                            autoSkip: true,
                            maxTicksLimit: 20,
                        },
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(0,0,0,0.06)' },
                        ticks: {
                            color: '#8a7a60',
                            font: { size: 11 },
                            callback: function (v) {
                                return v.toLocaleString('fr-FR') + ' €';
                            },
                        },
                    },
                },
            },
        });
    }

    document.getElementById('js-chart-line').addEventListener('click', function () {
        render('line');
        this.classList.add('admin-btn--primary');
        this.classList.remove('admin-btn--outline');
        document.getElementById('js-chart-bar').classList.add('admin-btn--outline');
        document.getElementById('js-chart-bar').classList.remove('admin-btn--primary');
    });

    document.getElementById('js-chart-bar').addEventListener('click', function () {
        render('bar');
        this.classList.add('admin-btn--primary');
        this.classList.remove('admin-btn--outline');
        document.getElementById('js-chart-line').classList.add('admin-btn--outline');
        document.getElementById('js-chart-line').classList.remove('admin-btn--primary');
    });

    // Init après chargement Chart.js
    function init() {
        if (typeof Chart !== 'undefined') {
            render('line');
        } else {
            setTimeout(init, 50);
        }
    }
    init();
})();
</script>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js" defer></script>

<?php require_once SRC_PATH . '/View/admin/_close.php'; ?>
