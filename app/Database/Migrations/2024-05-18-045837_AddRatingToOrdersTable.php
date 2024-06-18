<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddRatingToOrdersTable extends Migration
{
    public function up()
    {
        $fields = [
            'rating' => [
                'type' => 'INT',
                'constraint' => 5,
                'null' => true,
                'default' => null,
                'comment' => 'Rating given by customer, scale 1-5'
            ],
        ];

        $this->forge->addColumn('orders', $fields);
    }

    public function down()
    {
        $this->forge->dropColumn('orders', 'rating');
    }
}
