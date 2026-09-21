<?php

namespace App\Services;

use App\Models\Post;
use Illuminate\Support\Facades\DB;
use PDO;
use Throwable;

class ReplicationMonitor
{
    /**
     * @return array{
     *     enabled: bool,
     *     sticky: bool,
     *     write_host: string|null,
     *     read_host: string|null,
     *     primary: array<string, mixed>|null,
     *     replica: array<string, mixed>|null,
     *     replica_status: array<string, mixed>|null,
     *     default_select: array<string, mixed>|null,
     *     default_write: array<string, mixed>|null,
     *     select_uses_replica: bool,
     *     error: string|null
     * }
     */
    public function snapshot(): array
    {
        $connection = (string) config('database.default');
        $config = config("database.connections.{$connection}", []);
        $enabled = ($config['driver'] ?? null) === 'mysql' && isset($config['read'], $config['write']);

        $snapshot = [
            'enabled' => $enabled,
            'sticky' => (bool) ($config['sticky'] ?? false),
            'write_host' => $this->formatHost(
                $this->firstHost($config['write']['host'] ?? $config['host'] ?? null),
                $config['write']['port'] ?? $config['port'] ?? null,
            ),
            'read_host' => $this->formatHost(
                $this->firstHost($config['read']['host'] ?? $config['host'] ?? null),
                $config['read']['port'] ?? $config['port'] ?? null,
            ),
            'primary' => null,
            'replica' => null,
            'replica_status' => null,
            'default_select' => null,
            'default_write' => null,
            'select_uses_replica' => false,
            'error' => null,
        ];

        if (! $enabled) {
            return $snapshot;
        }

        try {
            $snapshot['primary'] = $this->serverInfo('mysql_primary');
            $snapshot['replica'] = $this->serverInfo('mysql_replica');
            $snapshot['replica_status'] = $this->replicaStatus();
            $snapshot['default_select'] = $this->pdoIdentity(DB::connection()->getReadPdo());
            $snapshot['default_write'] = $this->pdoIdentity(DB::connection()->getPdo());
            $snapshot['select_uses_replica'] = (int) ($snapshot['default_select']['server_id'] ?? 0)
                === (int) ($snapshot['replica']['server_id'] ?? 0)
                && (int) ($snapshot['default_select']['server_id'] ?? 0)
                !== (int) ($snapshot['default_write']['server_id'] ?? 0);
        } catch (Throwable $e) {
            $snapshot['error'] = $e->getMessage();
        }

        return $snapshot;
    }

    /**
     * @return array{connection: string, hostname: mixed, port: mixed, server_id: mixed, read_only: bool, post_count: int}
     */
    public function serverInfo(string $connection): array
    {
        $row = DB::connection($connection)->selectOne(
            'select @@hostname as hostname, @@port as port, @@server_id as server_id, @@read_only as read_only',
        );

        return [
            'connection' => $connection,
            'hostname' => $row->hostname,
            'port' => $row->port,
            'server_id' => $row->server_id,
            'read_only' => (bool) $row->read_only,
            'post_count' => Post::on($connection)->count(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function replicaStatus(): ?array
    {
        $rows = DB::connection('mysql_replica')->select('SHOW REPLICA STATUS');

        if ($rows === []) {
            return null;
        }

        $status = array_change_key_case((array) $rows[0], CASE_LOWER);

        return [
            'io_running' => $status['replica_io_running'] ?? null,
            'sql_running' => $status['replica_sql_running'] ?? null,
            'seconds_behind' => $status['seconds_behind_source'] ?? null,
            'source_host' => $status['source_host'] ?? null,
            'last_error' => $status['last_error'] ?? null,
            'last_io_error' => $status['last_io_error'] ?? null,
            'last_sql_error' => $status['last_sql_error'] ?? null,
        ];
    }

    /**
     * @return array{server_id: mixed, hostname: mixed, read_only: bool}
     */
    public function pdoIdentity(PDO $pdo): array
    {
        $row = $pdo->query('select @@server_id as server_id, @@hostname as hostname, @@read_only as read_only')->fetch(PDO::FETCH_OBJ);

        return [
            'server_id' => $row->server_id,
            'hostname' => $row->hostname,
            'read_only' => (bool) $row->read_only,
        ];
    }

    public function isHealthy(?array $replicaStatus): bool
    {
        if ($replicaStatus === null) {
            return false;
        }

        return $replicaStatus['io_running'] === 'Yes'
            && $replicaStatus['sql_running'] === 'Yes';
    }

    private function firstHost(mixed $host): mixed
    {
        return is_array($host) ? ($host[0] ?? null) : $host;
    }

    private function formatHost(mixed $host, mixed $port): ?string
    {
        if (! is_string($host) || $host === '') {
            return null;
        }

        return $port ? "{$host}:{$port}" : $host;
    }
}
