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

            // 2. كل المواد
            //UniversityCourseSeeder::class,
            CourseMasterSeeder::class,
            CourseDifficultySeeder::class,

            // 3. ربط المواد بالخطة الدراسية (الخطوة الجديدة والمهمة)
            DegreeCourseSeeder::class,
            RequirementSeeder::class,

            // 4. المتطلبات
            PrerequisiteSeeder::class,

            // 5. الفصول الدراسية
            SemesterSeeder::class,

            // 6. ربط المواد بالفصول الدراسية
            CourseSemesterSeeder::class,

            // 7. الطالب وسجله
            // PharmacyStudentRecordSeeder::class, 
            // PredictedMarksSeeder::class,

            // // 8. ربط الطلاب بالفصول الدراسية
            // StudentSemesterSeeder::class,
        ]);
    }
}
