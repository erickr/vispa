{{-- The deploy counter as a version: 0.01 per deploy, so the 100th deploy reads 1.00. --}}
<p class="py-4 text-center text-xs text-gray-400 dark:text-gray-500">
    Vispa {{ number_format(config('app.deploy_number') / 100, 2, '.', '') }}
</p>
