<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Degree;

class DegreeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Degree::truncate(); // تفريغ الجدول أولاً
        Degree::create([
            'degree_name' => 'Software Engineering', 'faculty_id' => 1,
        ]);
        Degree::create([
            'degree_name' => 'Bachelor of Pharmacy', 'faculty_id' => 2,
        ]);
    }
}
