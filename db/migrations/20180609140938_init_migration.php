<?php

use Phinx\Migration\AbstractMigration;

class InitMigration extends AbstractMigration
{
    public function change()
    {
        // Users table
        $users = $this->table('users', ['signed' => false]);
        $users->addColumn('username', 'string', ['limit' => 255])
              ->addColumn('password', 'string', ['limit' => 255])
              ->addColumn('created_at', 'datetime', ['null' => true])
              ->addColumn('updated_at', 'datetime', ['null' => true])
              ->addColumn('deleted_at', 'datetime', ['null' => true])
              ->addIndex(['username'], ['unique' => true])
              ->create();

        // Categories table
        $categories = $this->table('categories', ['signed' => false]);
        $categories->addColumn('name', 'string', ['limit' => 255])
                   ->addColumn('user_id', 'integer', ['signed' => false])
                   ->addColumn('created_at', 'datetime', ['null' => true])
                   ->addColumn('updated_at', 'datetime', ['null' => true])
                   ->addColumn('deleted_at', 'datetime', ['null' => true])
                   ->addIndex(['user_id'])
                   ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
                   ->create();

        // Todo items table
        $todo = $this->table('todo', ['signed' => false]);
        $todo->addColumn('name', 'string', ['limit' => 255])
             ->addColumn('category_id', 'integer', ['signed' => false])
             ->addColumn('user_id', 'integer', ['signed' => false])
             ->addColumn('created_at', 'datetime', ['null' => true])
             ->addColumn('updated_at', 'datetime', ['null' => true])
             ->addColumn('deleted_at', 'datetime', ['null' => true])
             ->addIndex(['user_id'])
             ->addIndex(['category_id'])
             ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
             ->addForeignKey('category_id', 'categories', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
             ->create();
    }
}
