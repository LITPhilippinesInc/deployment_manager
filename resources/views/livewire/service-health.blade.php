<div class="flex w-full flex-col gap-6" wire:poll.60s>
    <x-slot:title>Service Health | LIT Deployment Manager</x-slot:title>
    <div><h1 class="text-2xl font-semibold">Service Health</h1><p class="text-sm text-neutral-500">External service monitoring</p></div>
    @if ($canManage)
    <div>
        <button type="button" class="button" wire:click="addMonitor">+ Add Monitor</button>
        <div x-data="{ open: $wire.entangle('showMonitorModal').live }" x-cloak x-show="open" x-on:keydown.escape.window="if(open) $wire.cancelEdit()" class="fixed inset-0 z-[100] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="monitor-modal-title">
            <div class="absolute inset-0 bg-black/70" x-on:click="$wire.cancelEdit()"></div>
            <div class="relative w-full max-w-lg max-h-[90vh] overflow-y-auto rounded-lg border border-neutral-300 bg-white p-6 shadow-xl dark:border-white/10 dark:bg-neutral-800">
                <div class="flex items-center justify-between gap-4">
                    <h2 id="monitor-modal-title" class="text-lg font-semibold">{{ $editing ? 'Edit Monitor' : 'Add Monitor' }}</h2>
                    <button type="button" class="button" aria-label="Close" wire:click="cancelEdit">×</button>
                </div>
                <form wire:submit="saveMonitor" class="flex flex-col gap-3 mt-4">
            <label>Monitor name <input class="input" wire:model="name" required maxlength="100" placeholder="LIT Website"></label>
            @error('name') <span class="text-red-500">{{ $message }}</span> @enderror
            <label>Monitor type <select class="input" wire:model.live="type" @disabled($editing !== null)><option value="http">HTTP / HTTPS</option><option value="rss">RSS / Atom</option></select></label>
            @error('type') <span class="text-red-500">{{ $message }}</span> @enderror
            <label>Check interval <select class="input" wire:model="interval"><option value="1">Every minute</option><option value="5">Every 5 minutes</option><option value="15">Every 15 minutes</option></select></label>
            @error('interval') <span class="text-red-500">{{ $message }}</span> @enderror
            <label>{{ $type === 'rss' ? 'Feed URL' : 'Public URL' }} <input class="input" type="url" wire:model="url" required placeholder="https://www.lit.com.ph" @disabled($editing !== null)></label>
            @error('url') <span class="text-red-500">{{ $message }}</span> @enderror
            <p class="text-xs text-neutral-500">Public IPv4 endpoints only. RSS feed retrieval does not measure service uptime.</p>
            <div class="flex gap-2"><button class="button" type="submit">{{ $editing ? 'Save changes' : 'Add Monitor' }}</button><button class="button" type="button" wire:click="cancelEdit">Cancel</button></div>
                </form>
            </div>
        </div>
    </div>
    @endif
    @foreach ($items as $item)
    @php $bars=$item['bars']; $counts=$item['counts']; $uptime=$item['uptime']; $latest=$item['latest']; @endphp
    <div wire:key="monitor-{{ $item['id'] ?? 'auth0' }}" class="rounded-lg border border-neutral-200 p-5 dark:border-white/10 dark:bg-surface">
        <h2 class="font-semibold">{{ $item['id'] ? 'Custom monitor' : 'External provider' }}</h2>
        <p class="my-2 text-sm text-neutral-500">{{ $item['type']==='rss' ? 'Official announcements · RSS / Atom' : ($item['id'] ? 'HTTP endpoint reachability' : 'Status-page reachability only, not provider or tenant health.') }}</p>
        <div class="flex justify-between gap-4 py-4">
            <div><div class="font-semibold">{{ $item['name'] }}</div><a class="text-sm underline" href="{{ $item['url'] }}" target="_blank" rel="noopener noreferrer">{{ $item['url'] }}</a></div>
            <div class="text-right text-sm">
                @if (!$item['enabled'])
                    <span>Paused</span>
                @elseif (!$latest || \Carbon\Carbon::parse($latest->checked_at,'UTC')->lt(now()->utc()->subMinutes($item['interval'] + 2)))
                    <span class="text-neutral-500">No recent data</span>
                @else
                    <span class="{{ $latest->reachable ? 'text-success' : 'text-warning' }}">{{ $latest->reachable ? ($item['type']==='rss' ? 'Feed retrieved' : 'Reachable') : 'Check failed' }}</span>
                    <div>HTTP {{ $latest->code ?? '—' }} · {{ $latest->ms ?? '—' }} ms</div>
                @endif
            </div>
        </div>
        <h3 class="font-semibold mb-2">{{ $item['type'] === 'rss' ? 'Feed retrieval history' : 'Endpoint availability' }}</h3>
        <div class="flex w-full gap-0.5" role="img" aria-label="{{ $item['type'] === 'rss' ? 'Feed retrieval availability over the last 24 hours' : 'Endpoint availability over the last 24 hours' }}">
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
        <p class="text-xs text-neutral-500">{{ $item['type'] === 'rss' ? 'Green: feed retrieved · Red: retrieval failed · Gray: no data. Percentages measure feed retrieval, not provider uptime.' : 'Green: passed · Red: failed · Gray: no data.' }} Percentages use recorded checks only; missing checks are excluded.</p>
        @if ($item['type']==='rss')
            <details class="mt-3">
            <summary class="cursor-pointer text-sm font-medium">View incident and maintenance history ({{ count($item['incidents']) }})</summary>
            <div class="mt-2 max-h-80 overflow-y-auto">
            @forelse ($item['incidents'] as $incident)
                <details class="border-l-2 border-neutral-500/40 pl-4 ml-2 py-3">
                    <summary class="cursor-pointer">{{ $incident->title ?: 'Announcement' }} <span class="text-xs text-neutral-500">{{ $incident->published ?: 'Publication date unavailable' }}</span></summary>
                    <p class="whitespace-pre-wrap break-words text-sm mt-2">{{ $incident->description }}</p>
                </details>
            @empty
                <p class="text-sm text-neutral-500">No announcements recorded. This does not establish operational status.</p>
            @endforelse
            <p class="text-xs text-neutral-500 mt-2">Showing up to 50 saved announcements, newest retrieved first. Announcements are not inferred outage durations.</p>
            </div>
            </details>
        @endif
        <p class="text-xs text-neutral-500 mt-2">Last check (UTC): {{ $latest->checked_at ?? 'Awaiting scheduler' }}</p>
        @if ($canManage && $item['id'])
            <div class="flex gap-2 mt-3">
                <button class="button" wire:click="editMonitor({{ $item['id'] }})">Edit</button>
                <button class="button" wire:click="toggleMonitor({{ $item['id'] }})">{{ $item['enabled'] ? 'Pause' : 'Resume' }}</button>
                <button class="button" wire:confirm="Delete this monitor and its history?" wire:click="deleteMonitor({{ $item['id'] }})">Delete</button>
            </div>
        @endif
    </div>
    @endforeach
</div>
