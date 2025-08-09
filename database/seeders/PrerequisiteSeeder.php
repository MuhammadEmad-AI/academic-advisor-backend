<?php

namespace Database\Seeders;

use App\Models\Course;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PrerequisiteSeeder extends Seeder
{
    public function run(): void
    {
        Log::info('--- PrerequisiteSeeder is starting (reading from column 22) ---');

        $csvFile = fopen(database_path('data/pharmacy_courses.csv'), 'r');
        fgetcsv($csvFile);

        $rowCount = 1;
        while (($data = fgetcsv($csvFile, 2000, ',')) !== false) {
            $rowCount++;

            // ----- التعديل الوحيد والمهم هنا: من 18 إلى 21 -----
            if (isset($data[1]) && !empty($data[1]) && isset($data[21]) && !empty($data[21])) {
                
                $mainCourse = Course::where('course_number', trim($data[1]))->first();
                if (!$mainCourse) continue;

                $prerequisitesString = $data[21]; // <-- هنا التغيير: نقرأ من العمود الصحيح

                $prerequisiteCodes = explode(',', $prerequisitesString);

                foreach ($prerequisiteCodes as $prerequisiteCode) {
                    $prerequisiteCode = trim($prerequisiteCode);
                    if (empty($prerequisiteCode)) continue;

                    $prerequisiteCourse = Course::where('course_number', $prerequisiteCode)->first();

                    if ($prerequisiteCourse) {
                        DB::table('prerequisites')->insert([
                            'course_id'       => $mainCourse->id,
                            'prerequisite_id' => $prerequisiteCourse->id,
                            'created_at'      => now(),
                            'updated_at'      => now(),
                        ]);
                        Log::info("SUCCESS: Linked '{$mainCourse->course_name}' to '{$prerequisiteCourse->course_name}'");
                    } else {
                        Log::warning("FAIL: Prerequisite '{$prerequisiteCode}' NOT FOUND for main course '{$mainCourse->course_name}'.");
                    }
                }
            }
        }

        fclose($csvFile);
        Log::info('--- PrerequisiteSeeder has finished ---');
    }
}