<div class="flex w-full flex-col gap-6" wire:poll.60s>
    <x-slot:title>Service Health | LIT Deployment Manager</x-slot:title>
    <div><h1 class="text-2xl font-semibold">Service Health</h1><p class="text-sm text-neutral-500">External service monitoring</p></div>
    <div class="rounded-lg border border-neutral-200 p-5 dark:border-white/10 dark:bg-surface">
        <h2 class="font-semibold">External providers</h2>
        <p class="my-2 text-sm text-neutral-500">Status-page reachability only, not provider or tenant health.</p>
        <div class="flex justify-between gap-4 py-4">
            <div><div class="font-semibold">Auth0</div><a class="text-sm underline" href="https://status.auth0.com/" target="_blank" rel="noopener noreferrer">Official status page</a></div>
            <div class="text-right text-sm">
                @if (!$latest || \Carbon\Carbon::parse($latest->checked_at,'UTC')->lt(now()->utc()->subMinutes(3)))
                    <span class="text-neutral-500">No recent data</span>
                @else
                    <span class="{{ $latest->reachable ? 'text-success' : 'text-warning' }}">{{ $latest->reachable ? 'Status page reachable' : 'Status page check failed' }}</span>
                    <div>HTTP {{ $latest->code ?? '—' }} · {{ $latest->ms ?? '—' }} ms</div>
                @endif
            </div>
        </div>
        <div class="flex w-full gap-0.5" role="img" aria-label="Status page availability over the last 24 hours">
            @foreach ($bars as $i => $bar)
                <div class="h-8 min-w-0 flex-1 rounded-sm" style="background-color: {{ $bar === null ? '#737373' : ($bar ? '#22a06b' : '#dc4545') }}" title="{{ $bar === null ? 'No data' : ($bar ? 'All recorded checks passed' : 'At least one failed check') }} · {{ $counts[$i] }} checks"></div>
            @endforeach
        </div>
        <div class="flex justify-between text-xs text-neutral-500 mt-2"><span>24 hours ago</span><span>Now</span></div>
        <div class="grid grid-cols-3 gap-3 py-5">
            @foreach ($uptime as $days => $value)
                <div class="rounded border border-neutral-200 dark:border-white/10 p-3"><div class="text-xs text-neutral-500">{{ $days === 1 ? '24 hours' : $days.' days' }}</div><div class="text-lg font-semibold">{{ $value === null ? '—' : $value.'%' }}</div></div>
            @endforeach
        </div>
        <p class="text-xs text-neutral-500">Green: passed · Red: failed · Gray: no data. Percentages use recorded checks only; missing checks are excluded.</p>
        <p class="text-xs text-neutral-500 mt-2">Last check (UTC): {{ $latest->checked_at ?? 'Awaiting scheduler' }}</p>
    </div>
</div>
