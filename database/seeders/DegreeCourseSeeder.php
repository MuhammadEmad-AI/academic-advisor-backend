<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Degree;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DegreeCourseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. ابحث عن شهادة الصيدلة (نفترض أن لها id = 2 كما في Seeders السابقة)
        $pharmacyDegree = Degree::find(2);

        // إذا لم نجد الشهادة، لا تكمل
        if (!$pharmacyDegree) {
            return;
        }

        $csvFile = fopen(database_path('data/pharmacy_degree_plan.csv'), 'r');
        fgetcsv($csvFile); // تجاهل الهيدر

        while (($data = fgetcsv($csvFile, 2000, ',')) !== false) {
            if (isset($data[0]) && !empty($data[0])) {
                // ابحث عن المادة باستخدام رمزها
                $course = Course::where('course_number', trim($data[0]))->first();

                // إذا وجدنا المادة، قم بربطها بشهادة الصيدلة
                if ($course) {
                    DB::table('degree_courses')->insert([
                        'degree_id' => $pharmacyDegree->id,
                        'course_id' => $course->id,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }
            }
        }
        fclose($csvFile);
    }
}