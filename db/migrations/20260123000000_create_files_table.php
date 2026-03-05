<?php

use Phinx\Migration\AbstractMigration;

/**
 * Create files table for storing uploaded file metadata and content
 */
class CreateFilesTable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('files', ['signed' => false]);
        
        $table
            ->addColumn('user_id', 'integer', ['signed' => false, 'null' => false])
            ->addColumn('original_name', 'string', ['limit' => 255, 'null' => false])
            ->addColumn('stored_name', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('path', 'string', ['limit' => 500, 'null' => true])
            ->addColumn('mime_type', 'string', ['limit' => 100, 'null' => false])
            ->addColumn('size', 'integer', ['signed' => false, 'null' => false])
            ->addColumn('hash', 'string', ['limit' => 64, 'null' => false])
            ->addColumn('storage_type', 'enum', [
                'values' => ['filesystem', 'database', 'both'],
                'default' => 'filesystem',
                'null' => false
            ])
            ->addColumn('db_format', 'enum', [
                'values' => ['base64', 'blob'],
                'null' => true
            ])
            ->addColumn('content', 'longblob', ['null' => true])
            ->addColumn('metadata', 'json', ['null' => true])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', [
                'default' => 'CURRENT_TIMESTAMP',
                'update' => 'CURRENT_TIMESTAMP'
            ])
            ->addColumn('deleted_at', 'timestamp', ['null' => true])
            ->addForeignKey('user_id', 'users', 'id', [
                'delete' => 'CASCADE',
                'update' => 'CASCADE'
            ])
            ->addIndex(['user_id'])
            ->addIndex(['hash'])
            ->addIndex(['mime_type'])
            ->addIndex(['created_at'])
            ->create();
    }
}
