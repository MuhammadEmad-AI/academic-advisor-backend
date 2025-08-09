<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            // 1. الأساسيات
            FacultySeeder::class,
            DegreeSeeder::class,
            SemesterSeeder::class,

            // 2. كل المواد
            UniversityCourseSeeder::class,
            PharmacyCourseSeeder::class,

            // 3. ربط المواد بالخطة الدراسية (الخطوة الجديدة والمهمة)
            DegreeCourseSeeder::class,

            // 4. المتطلبات
            PrerequisiteSeeder::class,

            // 5. الطالب وسجله
            StudentSeeder::class,
            StudentCourseSeeder::class,
        ]);
    }
}
