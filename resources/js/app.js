import './bootstrap';

// Livewire 3 ships its own Alpine and starts it for us. Importing alpinejs
// here would create a second instance — `wire:model.live` events stop
// dispatching, `x-data` directives misbehave. Keep it out.

import Chart from 'chart.js/auto';
window.Chart = Chart;

// x-data="countUp(1234, { suffix: ' €' })" + x-text="display": a KPI that
// counts up from zero when the page opens. Registered on Livewire's Alpine.
document.addEventListener('alpine:init', () => {
    window.Alpine.data('countUp', (target, opts = {}) => ({
        value: 0,
        get display() {
            const n = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: opts.decimals ?? 0 }).format(this.value);
            return (opts.prefix ?? '') + n + (opts.suffix ?? '');
        },
        init() {
            const end = Number(target) || 0;
            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches || end === 0) { this.value = end; return; }
            const t0 = performance.now(), dur = 900;
            const step = (t) => {
                const p = Math.min(1, (t - t0) / dur);
                this.value = end * (1 - Math.pow(1 - p, 3));
                if (p < 1) requestAnimationFrame(step); else this.value = end;
            };
            requestAnimationFrame(step);
        },
    }));
});
