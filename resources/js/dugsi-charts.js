import {
    Chart,
    BarController,
    BarElement,
    CategoryScale,
    LinearScale,
    Legend,
    Tooltip,
    DoughnutController,
    ArcElement,
    Filler,
} from 'chart.js';

Chart.register(
    BarController,
    BarElement,
    CategoryScale,
    LinearScale,
    Legend,
    Tooltip,
    DoughnutController,
    ArcElement,
    Filler,
);

const COLORS = {
    primary: '#1e3a6e',
    primarySoft: 'rgba(30, 58, 110, 0.85)',
    muted: '#cbd5e1',
    mutedSoft: 'rgba(203, 213, 225, 0.9)',
    success: '#16a34a',
    warning: '#d97706',
    danger: '#dc2626',
    grid: 'rgba(148, 163, 184, 0.25)',
    text: '#64748b',
};

/** Match Money::formatPercent — keep decimals when under 1%. */
function formatPercent(value) {
    const n = Number(value);
    if (! Number.isFinite(n) || n <= 0) {
        return '0%';
    }
    if (n >= 100) {
        return '100%';
    }
    if (n < 1) {
        return `${parseFloat(n.toFixed(2))}%`;
    }
    if (Math.abs(n - Math.round(n)) >= 0.05) {
        return `${n.toFixed(1)}%`;
    }
    return `${Math.round(n)}%`;
}

/**
 * @param {HTMLCanvasElement} canvas
 * @param {object} config
 */
function createChart(canvas, config) {
    const type = config.type || 'bar';
    const labels = config.labels || [];
    const datasets = (config.datasets || []).map((ds, index) => ({
        borderRadius: type === 'bar' ? 4 : 0,
        borderSkipped: false,
        maxBarThickness: type === 'bar' ? (config.horizontal ? 18 : 28) : undefined,
        ...ds,
        backgroundColor: ds.backgroundColor
            ?? (index === 0 ? (type === 'doughnut' ? [COLORS.primary, COLORS.muted] : COLORS.mutedSoft) : COLORS.primarySoft),
        borderColor: ds.borderColor ?? 'transparent',
        borderWidth: ds.borderWidth ?? 0,
    }));

    const horizontal = Boolean(config.horizontal);

    return new Chart(canvas, {
        type,
        data: { labels, datasets },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: horizontal ? 'y' : 'x',
            plugins: {
                legend: {
                    display: config.legend !== false,
                    position: 'bottom',
                    labels: {
                        boxWidth: 10,
                        boxHeight: 10,
                        color: COLORS.text,
                        font: { size: 11 },
                    },
                },
                tooltip: {
                    callbacks: {
                        label(ctx) {
                            const prefix = ctx.dataset.label ? `${ctx.dataset.label}: ` : '';
                            // Horizontal bars store the value on x; vertical bars on y.
                            const value = Number(
                                horizontal
                                    ? (ctx.parsed.x ?? ctx.parsed)
                                    : (ctx.parsed.y ?? ctx.parsed)
                            );
                            if (config.currency) {
                                return `${prefix}$${value.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
                            }
                            if (config.suffix === '%') {
                                return `${prefix}${formatPercent(value)}`;
                            }
                            return `${prefix}${value}${config.suffix || ''}`;
                        },
                    },
                },
            },
            scales: type === 'doughnut' ? {} : {
                x: {
                    grid: { color: horizontal ? COLORS.grid : 'transparent', drawBorder: false },
                    ticks: { color: COLORS.text, font: { size: 11 } },
                    beginAtZero: true,
                    suggestedMax: config.max ?? undefined,
                },
                y: {
                    grid: { color: horizontal ? 'transparent' : COLORS.grid, drawBorder: false },
                    ticks: { color: COLORS.text, font: { size: 11 } },
                    beginAtZero: true,
                    suggestedMax: horizontal ? undefined : (config.max ?? undefined),
                },
            },
            cutout: type === 'doughnut' ? '68%' : undefined,
        },
    });
}

function readConfig(el) {
    const raw = el.getAttribute('data-dugsi-chart');
    if (! raw) {
        return null;
    }
    try {
        return JSON.parse(raw);
    } catch {
        console.warn('DugsiCharts: invalid chart JSON', el);
        return null;
    }
}

function mountAll(root = document) {
    root.querySelectorAll('canvas[data-dugsi-chart]').forEach((canvas) => {
        if (canvas.dataset.chartMounted === '1') {
            return;
        }
        const config = readConfig(canvas);
        if (! config) {
            return;
        }
        createChart(canvas, config);
        canvas.dataset.chartMounted = '1';
    });
}

document.addEventListener('DOMContentLoaded', () => mountAll());

window.DugsiCharts = {
    mountAll,
    createChart,
    Chart,
    COLORS,
};
