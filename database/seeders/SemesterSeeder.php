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
        // Create semesters for years 2018-2024 with 3 semesters per year
        $semesters = [];
        $semesterId = 1;
        
        for ($year = 2018; $year <= 2024; $year++) {
            // Semester 1 (Fall)
            $semesters[] = [
                'id' => $semesterId++,
                'SemesterName' => 'Fall',
                'Year' => $year,
                'created_at' => now(),
                'updated_at' => now()
            ];
            
            // Semester 2 (Spring)
            $semesters[] = [
                'id' => $semesterId++,
                'SemesterName' => 'Spring',
                'Year' => $year,
                'created_at' => now(),
                'updated_at' => now()
            ];
            
            // Semester 3 (Summer)
            $semesters[] = [
                'id' => $semesterId++,
                'SemesterName' => 'Summer',
                'Year' => $year,
                'created_at' => now(),
                'updated_at' => now()
            ];
        }
        
        // Add 2025 Fall semester for new students
        $semesters[] = [
            'id' => $semesterId++,
            'SemesterName' => 'Fall',
            'Year' => 2025,
            'created_at' => now(),
            'updated_at' => now()
        ];
        
        DB::table('semesters')->insert($semesters);
    }
}