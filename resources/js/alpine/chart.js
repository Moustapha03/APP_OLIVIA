// Dashboard charts (resources/views/admin/dashboard.blade.php).
//
// <div x-data="chart" data-chart='{"type":"bar","labels":[…],"datasets":[{"label":"…","data":[…],"color":"primary"}],"money":true,"stacked":true}'>
//     <canvas x-ref="canvas"></canvas>
// </div>
//
// The config is plain JSON (no function in HTML: CSP). Chart.js is loaded
// on demand, so other pages do not download it. Each chart also has its
// figures in a table for screen readers (in the Blade view).

// Brand palette (resources/css/app.css): coral, its tints, black ink, greys.
const COLORS = {
    primary: '#e4603a',
    sky: '#f4b49b',
    teal: '#1c1c1c',
    success: '#16a34a',
    warning: '#e0a10b',
    danger: '#c81e4a',
    slate: '#cfcfcf',
    violet: '#ee8a66',
};
const PALETTE = ['#e4603a', '#1c1c1c', '#f4b49b', '#cfcfcf', '#ee8a66', '#8a8a8a', '#f7d6c8'];

const money = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 });
const formatMoney = (value) => `${money.format(value)} FCFA`;
const formatShort = (value) => (Math.abs(value) >= 1000 ? `${money.format(value / 1000)} k` : money.format(value));

const splitLabel = (label) => (typeof label === 'string' && label.length > 16 && label.includes(' / ')
    ? label.split(' / ').map((part, i, parts) => (i < parts.length - 1 ? `${part} /` : part))
    : label);

export default () => ({
    instance: null,

    async init() {
        const config = JSON.parse(this.$el.dataset.chart || '{}');
        const { default: Chart } = await import('chart.js/auto');
        Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
        Chart.defaults.color = '#7a7a7a';

        const round = config.type === 'doughnut';
        const datasets = (config.datasets || []).map((set, index) => {
            const colors = round
                ? set.data.map((_, i) => PALETTE[i % PALETTE.length])
                : COLORS[set.color] || PALETTE[index % PALETTE.length];

            return {
                label: set.label,
                data: set.data,
                backgroundColor: colors,
                borderColor: round ? '#ffffff' : colors,
                borderWidth: round ? 2 : 0,
                borderRadius: config.type === 'bar' ? (config.stacked ? 6 : 999) : 0,
                borderSkipped: false,
                maxBarThickness: config.horizontal ? 22 : 42,
                // Bars with the same "stack" pile up (revenue), others stand beside them (expenses).
                ...(set.stack ? { stack: set.stack } : {}),
            };
        });

        const valueOf = (context) => (config.horizontal ? context.parsed.x : context.parsed.y ?? context.parsed);
        const label = (context) => {
            const value = round ? context.parsed : valueOf(context);
            const text = config.money ? formatMoney(value) : money.format(value);

            return `${context.dataset.label && !round ? `${context.dataset.label} : ` : `${context.label} : `}${text}`;
        };

        const axis = (stacked, isValue) => ({
            stacked,
            grid: { display: isValue, color: '#eeeeee' },
            border: { display: false },
            ticks: isValue
                ? { callback: (value) => (config.money ? formatShort(value) : money.format(value)), precision: 0 }
                : { autoSkip: true, maxRotation: 0 },
            beginAtZero: true,
        });

        this.instance = new Chart(this.$refs.canvas, {
            type: config.type || 'bar',
            // Long names ("Anani / Gonzague / Port-Bouët") on several lines.
            data: { labels: (config.labels || []).map(splitLabel), datasets },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: config.horizontal ? 'y' : 'x',
                animation: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? false : { duration: 400 },
                plugins: {
                    legend: { display: round || datasets.length > 1, position: 'bottom', labels: { boxWidth: 12, usePointStyle: true } },
                    tooltip: { callbacks: { label } },
                },
                scales: round
                    ? {}
                    : {
                        x: axis(Boolean(config.stacked), Boolean(config.horizontal)),
                        y: axis(Boolean(config.stacked), !config.horizontal),
                    },
                cutout: round ? '62%' : undefined,
            },
        });
    },

    destroy() {
        this.instance?.destroy();
    },
});
