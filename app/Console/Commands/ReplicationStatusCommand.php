<?php

namespace App\Console\Commands;

use App\Services\ReplicationMonitor;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('replication:status')]
#[Description('Show MySQL primary/replica replication status')]
class ReplicationStatusCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(ReplicationMonitor $monitor): int
    {
        $status = $monitor->snapshot();

        if (! $status['enabled']) {
            $this->components->warn('Read/write splitting is not enabled. Set DB_CONNECTION=mysql and DB_REPLICA_HOST.');

            return self::FAILURE;
        }

        if ($status['error'] !== null) {
            $this->components->error($status['error']);

            return self::FAILURE;
        }

        $rows = [[
            'Primary',
            $status['write_host'],
            $status['primary']['server_id'] ?? 'n/a',
            ! empty($status['primary']['read_only']) ? 'yes' : 'no',
            $status['primary']['post_count'] ?? 0,
        ]];

        foreach ($status['replicas'] as $replica) {
            $rows[] = [
                $replica['name'],
                $replica['host'],
                $replica['server']['server_id'] ?? 'n/a',
                ! empty($replica['server']['read_only']) ? 'yes' : 'no',
                $replica['server']['post_count'] ?? 0,
            ];
        }

        $this->table(['Role', 'Host', 'Server ID', 'Read only', 'Posts'], $rows);

        $statusRows = [];

        foreach ($status['replicas'] as $replica) {
            $replicaStatus = $replica['status'] ?? [];
            $statusRows[] = [
                $replica['name'],
                $replicaStatus['io_running'] ?? 'n/a',
                $replicaStatus['sql_running'] ?? 'n/a',
                $replicaStatus['seconds_behind'] ?? 'n/a',
                $replicaStatus['source_host'] ?? 'n/a',
            ];
        }

        $this->table(['Replica', 'IO thread', 'SQL thread', 'Seconds behind', 'Source host'], $statusRows);

        foreach ($status['replicas'] as $replica) {
            foreach (['last_error', 'last_io_error', 'last_sql_error'] as $errorKey) {
                if (! empty($replica['status'][$errorKey])) {
                    $this->components->error($replica['name'].': '.$replica['status'][$errorKey]);
                }
            }
        }

        if (! $monitor->isHealthy(null, $status['replicas'])) {
            $this->components->error('One or more replicas are not replicating.');

            return self::FAILURE;
        }

        $this->components->info('Both replicas are replicating from the primary.');

        return self::SUCCESS;
    }
}
