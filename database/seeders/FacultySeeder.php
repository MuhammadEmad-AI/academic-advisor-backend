<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Faculty;

class FacultySeeder extends Seeder
{
    public function run(): void
    {
        Faculty::create([
            'id' => 1,
            'faculty_name' => 'Information Technology Engineering',
            'university_name' => 'Damascus University',
        ]);
    }
}
