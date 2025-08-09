<?php

namespace Database\Seeders;

use App\Models\Course;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB; // يمكنك إزالة هذا إذا لم تستخدمه

class PharmacyCourseSeeder extends Seeder
{
    public function run(): void
    {
        $csvFile = fopen(database_path('data/pharmacy_courses.csv'), 'r');
        fgetcsv($csvFile); // تجاهل الهيدر

        while (($data = fgetcsv($csvFile, 2000, ',')) !== false) {
            if (isset($data[1]) && isset($data[2]) && isset($data[4])) {
                Course::create([
                    // -- التعديل الأهم هنا --
                    'course_number' => trim($data[1]), // trim() لإزالة المسافات
                    'course_name'   => trim($data[2]),
                    'credit_hours'  => (int)trim($data[4]),
                    'status'        => 'active'
                ]);
            }
        }

        fclose($csvFile);
    }
}