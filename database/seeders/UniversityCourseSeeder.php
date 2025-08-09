<?php

namespace Database\Seeders;

use App\Models\Course;
use Illuminate\Database\Seeder;

class UniversityCourseSeeder extends Seeder
{
    public function run(): void
    {
        $csvFile = fopen(database_path('data/university_requirements.csv'), 'r');
        fgetcsv($csvFile); // Ignore header

        while (($data = fgetcsv($csvFile, 2000, ',')) !== false) {
            if (isset($data[0]) && isset($data[1]) && isset($data[3])) {
                $courseNumber = trim($data[0]);

                // نضيف فقط المواد التي لا تبدو كأنها مواد تخصصية
                if (strpos($courseNumber, 'RQU') === 0 || strpos($courseNumber, 'RQE') === 0) {
                    // نستخدم updateOrCreate لتجنب التكرار إذا كانت المادة موجودة
                    Course::updateOrCreate(
                        ['course_number' => $courseNumber],
                        [
                            'course_name'   => trim($data[1]),
                            'credit_hours'  => (int)trim($data[3]),
                            'status'        => 'active'
                        ]
                    );
                }
            }
        }
        fclose($csvFile);
    }
}