import './bootstrap';

// Livewire 3 ships its own Alpine and starts it for us. Importing alpinejs
// here would create a second instance — `wire:model.live` events stop
// dispatching, `x-data` directives misbehave. Keep it out.

import Chart from 'chart.js/auto';
window.Chart = Chart;
