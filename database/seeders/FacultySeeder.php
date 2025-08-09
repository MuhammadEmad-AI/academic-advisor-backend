<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Faculty;

class FacultySeeder extends Seeder
{
    public function run(): void
    {
        // Faculty::truncate(); // تفريغ الجدول أولاً
        Faculty::create([
            'id' => 1, 'faculty_name' => 'Information Technology Engineering', 'university_name' => 'Damascus University',
        ]);
        Faculty::create([
            'id' => 2, 'faculty_name' => 'Pharmacy', 'university_name' => 'Damascus University',
        ]);
    }
}
