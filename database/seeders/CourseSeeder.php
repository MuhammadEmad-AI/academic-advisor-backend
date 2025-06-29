<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Course;

class CourseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Course::create([
            'course_name' => 'Introduction to Programming',
            'course_number' => 'CS101',
            'credit_hours' => 3,
            'description' => 'Basic programming concepts using Python.',
            'status' => 'active',
        ]);
        Course::create([
            'course_name' => 'Data Structures',
            'course_number' => 'CS201',
            'credit_hours' => 4,
            'description' => 'Study of data structures and algorithms.',
            'status' => 'active',
        ]);
        Course::create([
            'course_name' => 'Database Systems',
            'course_number' => 'CS301',
            'credit_hours' => 3,
            'description' => 'Introduction to relational databases and SQL.',
            'status' => 'inactive',
        ]);
    }
}
