<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SemesterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('semesters')->insert([
            [
                'id' => 1,
                'SemesterName' => 'Fall',
                'Year' => '2024',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => 2,
                'SemesterName' => 'Spring',
                'Year' => '2025',
                'created_at' => now(),
                'updated_at' => now()
            ],
        ]);
    }
}