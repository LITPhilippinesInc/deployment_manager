<?php

// Added for LIT Deployment Manager.
namespace App\Livewire;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Component;

class ServiceHealth extends Component
{
    public function render()
    {
        abort_unless(auth()->check(), 403);

        $services = Cache::remember('lit:service-health:public:v1', 60, function () {
            $results = [];
            foreach (['Auth0' => 'https://status.auth0.com/'] as $name => $url) {
                $start = microtime(true);
                try {
                    $response = Http::connectTimeout(3)->timeout(5)->withoutRedirecting()->get($url);
                    $results[] = ['name' => $name, 'url' => $url, 'reachable' => $response->successful(), 'code' => $response->status(), 'ms' => (int) round((microtime(true) - $start) * 1000)];
                } catch (\Exception $e) {
                    $results[] = ['name' => $name, 'url' => $url, 'reachable' => false, 'code' => null, 'ms' => null];
                }
            }
            return ['checked_at' => now()->toIso8601String(), 'items' => $results];
        });

        return view('livewire.service-health', ['services' => $services]);
    }
}
