<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Degree;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RequirementSeeder extends Seeder
{
    public function run(): void
    {
        Log::info('--- RequirementSeeder is starting (reading from your new English column) ---');
        DB::table('requirements')->delete();

        $pharmacyDegree = Degree::find(2); // شهادة الصيدلة
        if (!$pharmacyDegree) {
            Log::error('RequirementSeeder: Pharmacy degree (ID 2) not found.');
            return;
        }

        // سنعتمد على ملف الخطة الدراسية للصيدلة كمصدر وحيد لكل المتطلبات
        $csvFile = fopen(database_path('data/pharmacy_degree_plan.csv'), 'r');
        fgetcsv($csvFile); // تجاهل الهيدر

        while (($data = fgetcsv($csvFile, 2000, ',')) !== false) {
            // نقرأ من رمز المادة (index 0) وعمودك الجديد (index 7)
            if (isset($data[0]) && !empty($data[0]) && isset($data[7]) && !empty($data[7])) {
                
                $course = Course::where('course_number', trim($data[0]))->first();
                if (!$course) {
                    Log::warning("RequirementSeeder: Course '" . trim($data[0]) . "' not found, skipping requirement.");
                    continue;
                }

                // نقرأ نوع المتطلب المترجم مباشرة من العمود الجديد
                $requirementType = trim($data[7]);

                DB::table('requirements')->insert([
                    'degree_id'        => $pharmacyDegree->id,
                    'course_id'        => $course->id,
                    'requirement_type' => $requirementType,
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ]);
            }
        }

        fclose($csvFile);
        Log::info('--- RequirementSeeder has finished ---');
    }
}