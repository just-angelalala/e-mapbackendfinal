<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run()
    {
        $data = [
            [
                'name' => 'Replacement Parts',
            ],
        ];

        $this->db->table('category')->insertBatch($data);
    }
}
