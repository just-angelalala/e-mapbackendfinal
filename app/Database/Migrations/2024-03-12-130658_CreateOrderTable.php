<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateOrderTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true
            ],
            'customer_id' => [ 
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'session_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'order_date' => [
                'type' => 'DATETIME'
            ],
            'status' => [
                'type'       => 'VARCHAR',
                'constraint' => '100'
            ],
            'total_price' => [
                'type'       => 'DECIMAL',
                'constraint' => '10,2',
                'default'    => '0.00'
            ],
            'tendered' => [
                'type'       => 'DECIMAL',
                'constraint' => '10,2',
                'null'       => true,
            ],
            'change' => [
                'type'       => 'DECIMAL',
                'constraint' => '10,2',
                'null'       => true,
            ],
            'gcash_receipt_photo' => [
                'type'       => 'VARCHAR',
                'constraint' => '255',
                'default'    => '',
                'null'       => true,
            ],
            'feedback' => [
                'type'       => 'TEXT',
                'null'       => true,
            ],
            'feedback_photo' => [
                'type'       => 'VARCHAR',
                'constraint' => '255',
                'default'    => '',
                'null'       => true,
            ],
            'created_at datetime default current_timestamp',
            'updated_at datetime default current_timestamp on update current_timestamp'
        ]);
        
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('customer_id', 'users', 'id');
        $this->forge->addForeignKey('session_id', 'sessions', 'id');
        $this->forge->createTable('orders');
        
    }

    public function down()
    {
        $this->forge->dropTable('orders');
    }
}
