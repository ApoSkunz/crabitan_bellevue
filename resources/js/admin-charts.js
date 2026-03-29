/**
 * admin-charts.js — Graphique CA admin (stats/index.php)
 *
 * Lit les données depuis <script type="application/json" id="chart-data">,
 * initialise un graphique Chart.js (line/bar) et gère le toggle.
 */

import {
    Chart,
    CategoryScale,
    LinearScale,
    BarController,
    BarElement,
    LineController,
    LineElement,
    PointElement,
    Filler,
    Tooltip,
} from 'chart.js';

Chart.register(CategoryScale, LinearScale, BarController, BarElement, LineController, LineElement, PointElement, Filler, Tooltip);

const dataEl = document.getElementById('chart-data');
if (!dataEl) throw new Error('admin-charts: #chart-data introuvable');

const { labels, caData, cntData } = JSON.parse(dataEl.textContent);

const GOLD     = 'rgba(201, 168, 76, 1)';
const GOLD_BG  = 'rgba(201, 168, 76, 0.15)';
const GOLD_BG2 = 'rgba(201, 168, 76, 0.75)';

const canvas = document.getElementById('ca-chart');
if (!canvas) throw new Error('admin-charts: #ca-chart introuvable');

const ctx = canvas.getContext('2d');
let chart = null;

/**
 * Construit le dataset selon le type de graphique.
 *
 * @param {'bar'|'line'} type
 * @returns {object}
 */
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

/**
 * Détruit l'instance précédente et recrée le graphique.
 *
 * @param {'bar'|'line'} type
 * @returns {void}
 */
function render(type) {
    if (chart) chart.destroy();
    chart = new Chart(ctx, {
        data: {
            labels,
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
                        label(tooltipCtx) {
                            const v = tooltipCtx.raw.toLocaleString('fr-FR', { minimumFractionDigits: 2 });
                            return ' ' + v + ' €';
                        },
                        afterLabel(tooltipCtx) {
                            const i = tooltipCtx.dataIndex;
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
                        callback: (v) => v.toLocaleString('fr-FR') + ' €',
                    },
                },
            },
        },
    });
}

/**
 * Met à jour les classes actives sur les boutons bar/line.
 *
 * @param {'bar'|'line'} active
 * @returns {void}
 */
function setActiveBtn(active) {
    const btnBar  = document.getElementById('js-chart-bar');
    const btnLine = document.getElementById('js-chart-line');
    if (!btnBar || !btnLine) return;

    if (active === 'bar') {
        btnBar.classList.add('admin-btn--primary');
        btnBar.classList.remove('admin-btn--outline');
        btnLine.classList.add('admin-btn--outline');
        btnLine.classList.remove('admin-btn--primary');
    } else {
        btnLine.classList.add('admin-btn--primary');
        btnLine.classList.remove('admin-btn--outline');
        btnBar.classList.add('admin-btn--outline');
        btnBar.classList.remove('admin-btn--primary');
    }
}

document.getElementById('js-chart-line')?.addEventListener('click', () => {
    render('line');
    setActiveBtn('line');
});

document.getElementById('js-chart-bar')?.addEventListener('click', () => {
    render('bar');
    setActiveBtn('bar');
});

render('line');
