<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CourseMasterSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('--- Building a master list of courses to seed ---');

        // --- 1. بناء "قاموس" بكل تفاصيل المواد المتاحة ---
        $courseDetails = [];
        // أ) قراءة ملف متطلبات الجامعة
        $uniFile = fopen(database_path('data/university_requirements.CSV'), 'r');
        fgetcsv($uniFile); // تجاهل الهيدر
        while (($data = fgetcsv($uniFile, 1000, ',')) !== false) {
            if (isset($data[0]) && !empty(trim($data[0]))) {
                $courseNumber = trim($data[0]);
                $courseDetails[$courseNumber] = [
                    'course_name'   => isset($data[1]) && !empty(trim($data[1])) ? trim($data[1]) : $courseNumber,
                    'credit_hours'  => isset($data[3]) && is_numeric(trim($data[3])) ? (int)trim($data[3]) : 0,
                ];
            }
        }
        fclose($uniFile);

        // ب) قراءة ملف مواد الصيدلة (له الأولوية في تحديث المعلومات)
        $pharmacyFile = fopen(database_path('data/pharmacy_courses.csv'), 'r');
        fgetcsv($pharmacyFile); // تجاهل الهيدر
        while (($data = fgetcsv($pharmacyFile, 2000, ',')) !== false) {
            if (isset($data[1]) && !empty(trim($data[1]))) {
                $courseNumber = trim($data[1]);
                $courseDetails[$courseNumber] = [
                    'course_name'   => isset($data[2]) && !empty(trim($data[2])) ? trim($data[2]) : $courseNumber,
                    'credit_hours'  => isset($data[4]) && is_numeric(trim($data[4])) ? (int)trim($data[4]) : 0,
                ];
            }
        }
        fclose($pharmacyFile);

        // --- 2. تحديد قائمة المواد النهائية التي يجب إدخالها ---
        $finalCoursesToInsert = [];
        
        // أ) كل مواد الصيدلة مطلوبة
        $pharmacyFile = fopen(database_path('data/pharmacy_courses.csv'), 'r');
        fgetcsv($pharmacyFile);
        while (($data = fgetcsv($pharmacyFile, 2000, ',')) !== false) {
             if (isset($data[1]) && !empty(trim($data[1]))) {
                $courseNumber = trim($data[1]);
                $finalCoursesToInsert[$courseNumber] = $courseDetails[$courseNumber];
             }
        }
        fclose($pharmacyFile);

        // ب) المواد الإضافية المطلوبة من ملف التنبؤات
        $recommendationsFile = fopen(database_path('data/recommendation_results.csv'), 'r');
        fgetcsv($recommendationsFile);
        while (($data = fgetcsv($recommendationsFile, 1000, ',')) !== false) {
            if (isset($data[0])) {
                $courseNumber = trim($data[0]);
                // إذا كانت المادة موجودة في قاموسنا ولم تتم إضافتها بعد، أضفها
                if (isset($courseDetails[$courseNumber]) && !isset($finalCoursesToInsert[$courseNumber])) {
                    $finalCoursesToInsert[$courseNumber] = $courseDetails[$courseNumber];
                }
            }
        }
        fclose($recommendationsFile);
        $this->command->info('Final list of required courses has been compiled.');

        // --- 3. إفراغ الجدول وإدخال البيانات النظيفة بالطريقة الآمنة ---
        Schema::disableForeignKeyConstraints();
        DB::table('courses')->truncate();
        Schema::enableForeignKeyConstraints();

        $insertData = [];
        foreach ($finalCoursesToInsert as $courseNumber => $details) {
             $insertData[] = [
                'course_number' => $courseNumber,
                'course_name'   => $details['course_name'],
                'credit_hours'  => $details['credit_hours'],
                'status'        => 'active',
                'created_at'    => now(),
                'updated_at'    => now()
            ];
        }

        foreach (array_chunk($insertData, 500) as $chunk) {
            DB::table('courses')->insert($chunk);
        }
        
        $this->command->info('🎉 Courses table has been successfully and correctly seeded!');
    }
}