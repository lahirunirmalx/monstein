<?php

use Phinx\Migration\AbstractMigration;

/**
 * Create usage_logs table for API endpoint tracking
 */
class CreateUsageLogsTable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('usage_logs', ['signed' => false]);
        
        $table
            ->addColumn('endpoint', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('method', 'string', ['limit' => 10, 'null' => false])
            ->addColumn('status_code', 'integer', ['signed' => false, 'null' => false, 'default' => 200])
            ->addColumn('response_time_ms', 'decimal', ['precision' => 10, 'scale' => 2, 'null' => false, 'default' => 0])
            ->addColumn('user_id', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('ip_address', 'string', ['limit' => 45, 'null' => true]) // IPv6 support
            ->addColumn('user_agent', 'string', ['limit' => 500, 'null' => true])
            ->addColumn('request_size', 'integer', ['signed' => false, 'null' => false, 'default' => 0])
            ->addColumn('response_size', 'integer', ['signed' => false, 'null' => false, 'default' => 0])
            ->addColumn('route_name', 'string', ['limit' => 100, 'null' => true])
            ->addColumn('metadata', 'json', ['null' => true])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            // Indexes for common queries
            ->addIndex(['endpoint'])
            ->addIndex(['method'])
            ->addIndex(['status_code'])
            ->addIndex(['user_id'])
            ->addIndex(['created_at'])
            ->addIndex(['endpoint', 'method'])
            ->addIndex(['created_at', 'endpoint'])
            ->create();
    }
}
