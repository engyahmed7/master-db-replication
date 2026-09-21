<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePostRequest;
use App\Models\Post;
use App\Services\ReplicationMonitor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class ReplicationDashboardController extends Controller
{
    public function index(ReplicationMonitor $monitor): View
    {
        $status = $monitor->snapshot();
        Log::info('Default connection targets', [
            'select_uses_replica' => $status['select_uses_replica'],
            'select' => $status['default_select'],
            'write' => $status['default_write'],
        ]);
        $healthy = $monitor->isHealthy(null, $status['replicas']);
        $primaryConnection = $status['enabled'] ? 'mysql_primary' : (string) config('database.default');
        $defaultConnection = (string) config('database.default');

        return view('replication', [
            'status' => $status,
            'healthy' => $healthy,
            'postsOnPrimary' => Post::on($primaryConnection)->latest()->limit(20)->get(),
            'replicaPosts' => $status['enabled']
                ? collect($status['replicas'])->mapWithKeys(fn (array $replica) => [
                    $replica['name'] => Post::on($replica['connection'])->latest()->limit(20)->get(),
                ])
                : collect(['Replica' => Post::on($defaultConnection)->latest()->limit(20)->get()]),
        ]);
    }

    public function store(StorePostRequest $request): RedirectResponse
    {
        Post::query()->create($request->validated());

        return redirect()
            ->route('home')
            ->with('status', 'Post written to the primary. Refresh to confirm it replicated to both replicas.');
    }
}
