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
        // ابحث عن شهادة الصيدلة (id = 2)
        $pharmacyDegree = Degree::find(2);

        if (!$pharmacyDegree) {
            return;
        }

        // تفريغ الجدول لضمان عدم وجود بيانات قديمة
        DB::table('degree_courses')->truncate();

        $csvFile = fopen(database_path('data/pharmacy_degree_plan.csv'), 'r');
        fgetcsv($csvFile); // تجاهل الهيدر

        while (($data = fgetcsv($csvFile, 2000, ',')) !== false) {
            // نتأكد من وجود بيانات في الأعمدة المطلوبة
            if (isset($data[0]) && !empty($data[0]) && isset($data[4]) && !empty($data[4]) && isset($data[5]) && !empty($data[5])) {
                
                // ابحث عن المادة باستخدام رمزها (من العمود الأول)
                $course = Course::where('course_number', trim($data[0]))->first();

                // --- هنا التعديل: استخلاص العام والفصل ---
                $year = (int)trim($data[4]);     // من العمود الخامس
                $semester = (int)trim($data[5]); // من العمود السادس

                // إذا وجدنا المادة، قم بربطها بالشهادة مع إضافة العام والفصل
                if ($course) {
                    DB::table('degree_courses')->insert([
                        'degree_id' => $pharmacyDegree->id,
                        'course_id' => $course->id,
                        'year'      => $year,      // <-- الإضافة هنا
                        'semester'  => $semester,  // <-- الإضافة هنا
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }
            }
        }
        fclose($csvFile);
    }
}