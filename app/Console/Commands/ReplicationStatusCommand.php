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

        $this->table(
            ['Role', 'Host', 'Server ID', 'Read only', 'Posts'],
            [
                [
                    'Primary',
                    $status['write_host'],
                    $status['primary']['server_id'] ?? 'n/a',
                    ! empty($status['primary']['read_only']) ? 'yes' : 'no',
                    $status['primary']['post_count'] ?? 0,
                ],
                [
                    'Replica',
                    $status['read_host'],
                    $status['replica']['server_id'] ?? 'n/a',
                    ! empty($status['replica']['read_only']) ? 'yes' : 'no',
                    $status['replica']['post_count'] ?? 0,
                ],
            ],
        );

        $replica = $status['replica_status'];

        if ($replica === null) {
            $this->components->error('SHOW REPLICA STATUS returned no rows.');

            return self::FAILURE;
        }

        $this->table(
            ['IO thread', 'SQL thread', 'Seconds behind', 'Source host'],
            [[
                $replica['io_running'] ?? 'n/a',
                $replica['sql_running'] ?? 'n/a',
                $replica['seconds_behind'] ?? 'n/a',
                $replica['source_host'] ?? 'n/a',
            ]],
        );

        foreach (['last_error', 'last_io_error', 'last_sql_error'] as $errorKey) {
            if (! empty($replica[$errorKey])) {
                $this->components->error($replica[$errorKey]);
            }
        }

        if (! $monitor->isHealthy($replica)) {
            $this->components->error('Replica is not replicating.');

            return self::FAILURE;
        }

        $this->components->info('Replica is replicating from the primary.');

        return self::SUCCESS;
    }
}
