<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name') }}</title>
        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-zinc-50 text-zinc-900 antialiased">
        <main class="mx-auto max-w-6xl px-4 py-10 sm:px-6 lg:px-8">
            <header class="mb-8 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-sm font-medium uppercase tracking-wide text-zinc-500">Laravel + MySQL</p>
                    <h1 class="text-3xl font-semibold tracking-tight">Database replication</h1>
                    <p class="mt-2 max-w-2xl text-zinc-600">Writes go to the primary. Reads use the replica. Sticky mode keeps the same request on the primary after a write so you do not read stale data.</p>
                </div>
                <div class="rounded-full px-3 py-1 text-sm font-medium {{ $status['enabled'] && $healthy ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                    {{ $status['enabled'] && $healthy ? 'Replica healthy' : ($status['enabled'] ? 'Replica not ready' : 'Splitting disabled') }}
                </div>
            </header>

            @if (session('status'))
                <p class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</p>
            @endif

            @if ($status['error'])
                <p class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $status['error'] }}</p>
            @endif

            @if (! $status['enabled'])
                <section class="mb-8 rounded-xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900">
                    <p class="font-medium">Start the MySQL primary and replica, then point Laravel at them:</p>
                    <pre class="mt-3 overflow-x-auto rounded-lg bg-zinc-900 p-4 text-zinc-100">docker compose up -d
php artisan migrate</pre>
                    <p class="mt-3">Set <code class="rounded bg-amber-100 px-1">DB_CONNECTION=mysql</code> and <code class="rounded bg-amber-100 px-1">DB_REPLICA_HOST=127.0.0.1</code> in <code class="rounded bg-amber-100 px-1">.env</code>.</p>
                </section>
            @endif
            
            <section class="mb-8 grid gap-4 md:grid-cols-3">
                <article class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm">
                    <h2 class="text-sm font-medium text-zinc-500">Write host</h2>
                    <p class="mt-2 font-mono text-sm">{{ $status['write_host'] ?? 'default connection' }}</p>
                    <p class="mt-3 text-sm text-zinc-600">Primary server ID {{ $status['primary']['server_id'] ?? 'n/a' }}</p>
                </article>
                <article class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm">
                    <h2 class="text-sm font-medium text-zinc-500">Read host</h2>
                    <p class="mt-2 font-mono text-sm">{{ $status['read_host'] ?? 'default connection' }}</p>
                    <p class="mt-3 text-sm text-zinc-600">Replica server ID {{ $status['replica']['server_id'] ?? 'n/a' }}</p>
                </article>
                <article class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm">
                    <h2 class="text-sm font-medium text-zinc-500">Replication lag</h2>
                    <p class="mt-2 text-2xl font-semibold">{{ $status['replica_status']['seconds_behind'] ?? 'n/a' }}s</p>
                    <p class="mt-3 text-sm text-zinc-600">
                        IO {{ $status['replica_status']['io_running'] ?? 'n/a' }}
                        · SQL {{ $status['replica_status']['sql_running'] ?? 'n/a' }}
                        · Sticky {{ $status['sticky'] ? 'on' : 'off' }}
                    </p>
                </article>
            </section>

            <div class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)]">
                <section class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-semibold">Write a post</h2>
                    <p class="mt-1 text-sm text-zinc-600">This insert uses the default connection, which Laravel sends to the primary.</p>

                    <form method="POST" action="{{ route('posts.store') }}" class="mt-6 space-y-4">
                        @csrf
                        <div>
                            <label for="title" class="block text-sm font-medium">Title</label>
                            <input id="title" name="title" value="{{ old('title') }}" class="mt-1 w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-900 focus:outline-none" required>
                            @error('title')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="body" class="block text-sm font-medium">Body</label>
                            <textarea id="body" name="body" rows="5" class="mt-1 w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-zinc-900 focus:outline-none" required>{{ old('body') }}</textarea>
                            @error('body')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <button type="submit" class="rounded-lg bg-zinc-900 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-700">Save to primary</button>
                    </form>
                </section>

                <section class="grid gap-4 sm:grid-cols-2">
                    <article class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm">
                        <h2 class="font-semibold">Primary rows</h2>
                        <p class="mt-1 text-sm text-zinc-500">{{ $postsOnPrimary->count() }} shown</p>
                        <ul class="mt-4 space-y-3">
                            @forelse ($postsOnPrimary as $post)
                                <li class="rounded-lg border border-zinc-100 bg-zinc-50 p-3">
                                    <p class="font-medium">{{ $post->title }}</p>
                                    <p class="mt-1 text-sm text-zinc-600">{{ $post->body }}</p>
                                </li>
                            @empty
                                <li class="text-sm text-zinc-500">No posts on the primary yet.</li>
                            @endforelse
                        </ul>
                    </article>
                    <article class="rounded-xl border border-zinc-200 bg-white p-5 shadow-sm">
                        <h2 class="font-semibold">Replica rows</h2>
                        <p class="mt-1 text-sm text-zinc-500">{{ $postsOnReplica->count() }} shown</p>
                        <ul class="mt-4 space-y-3">
                            @forelse ($postsOnReplica as $post)
                                <li class="rounded-lg border border-zinc-100 bg-zinc-50 p-3">
                                    <p class="font-medium">{{ $post->title }}</p>
                                    <p class="mt-1 text-sm text-zinc-600">{{ $post->body }}</p>
                                </li>
                            @empty
                                <li class="text-sm text-zinc-500">No posts on the replica yet.</li>
                            @endforelse
                        </ul>
                    </article>
                </section>
            </div>
        </main>
    </body>
</html>
