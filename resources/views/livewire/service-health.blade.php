{{-- Added for LIT Deployment Manager. --}}
<div class="flex w-full flex-col gap-6" wire:poll.60s>
    <x-slot:title>Service Health | LIT Deployment Manager</x-slot:title>
    <div>
        <h1 class="text-2xl font-semibold">Service Health</h1>
        <p class="mt-1 text-sm text-neutral-500 dark:text-fg-dim">External service monitoring</p>
    </div>
    <div class="rounded-lg border border-neutral-200 p-4 dark:border-white/10 dark:bg-surface">
        <h2 class="font-semibold">External providers</h2>
        <p class="my-2 text-sm text-neutral-500 dark:text-fg-dim">These checks measure status-page reachability only, not provider-wide or tenant health.</p>
        @foreach ($services['items'] as $service)
            <div class="flex flex-wrap items-center justify-between gap-4 border-t border-neutral-200 py-4 dark:border-white/10">
                <div>
                    <div class="font-semibold">{{ $service['name'] }}</div>
                    <a href="{{ $service['url'] }}" target="_blank" rel="noopener noreferrer" class="text-sm underline">Official status page</a>
                </div>
                <div class="text-sm">
                    <span class="{{ $service['reachable'] ? 'text-success' : 'text-warning' }}">{{ $service['reachable'] ? 'Status page reachable' : 'Status page check failed' }}</span>
                    <div class="text-neutral-500 dark:text-fg-dim">HTTP {{ $service['code'] ?? '—' }} · {{ $service['ms'] === null ? '—' : $service['ms'].' ms' }}</div>
                </div>
            </div>
        @endforeach
        <p class="text-xs text-neutral-500 dark:text-fg-dim">Last checked: {{ $services['checked_at'] }}. Refreshes while this page is open.</p>
    </div>
</div>
