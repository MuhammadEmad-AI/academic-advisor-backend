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
        Degree::create([
            'degree_name' => 'Software Engineering',
            'faculty_id' => 1, // Assumes the FacultySeeder has run and created a faculty with ID 1
        ]);
    }
}
