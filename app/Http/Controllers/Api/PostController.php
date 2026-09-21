<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePostRequest;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use PDO;

class PostController extends Controller
{
    /**
     * List posts from the default read connection (the replica).
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'read_from' => $this->connectionIdentity(DB::connection()->getReadPdo()),
            'posts' => Post::query()->latest()->limit(50)->get(),
        ]);
    }

    /**
     * Create a post on the default write connection (the primary).
     */
    public function store(StorePostRequest $request): JsonResponse
    {
        $post = Post::query()->create($request->validated());

        return response()->json([
            'message' => 'Post written to the primary and will replicate to the replica.',
            'written_to' => $this->connectionIdentity(DB::connection()->getPdo()),
            'post' => $post,
        ], 201);
    }

    /**
     * Show a post from the default read connection (the replica).
     */
    public function show(Post $post): JsonResponse
    {
        return response()->json([
            'read_from' => $this->connectionIdentity(DB::connection()->getReadPdo()),
            'post' => $post,
        ]);
    }

    /**
     * @return array{server_id: mixed, hostname: mixed, read_only: bool}
     */
    private function connectionIdentity(PDO $pdo): array
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return [
                'server_id' => null,
                'hostname' => DB::connection()->getDriverName(),
                'read_only' => false,
            ];
        }

        $row = $pdo->query('select @@server_id as server_id, @@hostname as hostname, @@read_only as read_only')->fetch(PDO::FETCH_OBJ);

        return [
            'server_id' => $row->server_id,
            'hostname' => $row->hostname,
            'read_only' => (bool) $row->read_only,
        ];
    }
}
