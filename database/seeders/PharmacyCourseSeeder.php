<?php

namespace Database\Seeders;

use App\Models\Course;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PharmacyCourseSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('--- بدء عملية بناء وتعبئة جدول المواد الرئيسي (Courses) ---');

        // --- 1. بناء "قاموس" بالمعلومات المتوفرة من كل الملفات ---
        $courseDetails = [];

        // أ) قراءة ملف متطلبات الجامعة (للحصول على أسماء المواد العامة)
        $uniFile = fopen(database_path('data/university_requirements.CSV'), 'r');
        fgetcsv($uniFile); // تجاهل الهيدر
        while (($data = fgetcsv($uniFile, 1000, ',')) !== false) {
            if (isset($data[0])) {
                $courseNumber = trim($data[0]);
                $courseDetails[$courseNumber] = [
                    'name'   => isset($data[1]) && !empty(trim($data[1])) ? trim($data[1]) : (isset($data[2]) ? trim($data[2]) : '-'),
                    'hours'  => isset($data[3]) ? (int)trim($data[3]) : 0,
                ];
            }
        }
        fclose($uniFile);

        // ب) قراءة ملف مواد الصيدلة (له الأولوية في تحديث المعلومات)
        $pharmacyFile = fopen(database_path('data/pharmacy_courses.csv'), 'r');
        fgetcsv($pharmacyFile); // تجاهل الهيدر
        while (($data = fgetcsv($pharmacyFile, 2000, ',')) !== false) {
            if (isset($data[1])) {
                $courseNumber = trim($data[1]);
                // إذا كانت المادة موجودة، نحدث معلوماتها. إذا لم تكن، نضيفها.
                $courseDetails[$courseNumber] = [
                    'name'   => isset($data[2]) && !empty(trim($data[2])) ? trim($data[2]) : (isset($data[3]) ? trim($data[3]) : '-'), // الأولوية للاسم الإنجليزي
                    'hours'  => isset($data[4]) ? (int)trim($data[4]) : 0,
                ];
            }
        }
        fclose($pharmacyFile);
        $this->command->info('تم بناء قاموس معلومات المواد بنجاح.');

        // --- 2. تحديد قائمة المواد النهائية التي يجب إدخالها من ملف التنبؤات ---
        $finalCoursesToInsert = [];
        $recommendationsFile = fopen(database_path('data/recommendation_results.csv'), 'r');
        fgetcsv($recommendationsFile); // تجاهل الهيدر
        while (($data = fgetcsv($recommendationsFile, 1000, ',')) !== false) {
            if (isset($data[0])) {
                $courseNumber = trim($data[0]);

                // هل لدينا تفاصيل لهذه المادة في قاموسنا؟
                if (isset($courseDetails[$courseNumber])) {
                    $finalCoursesToInsert[$courseNumber] = [
                        'course_number' => $courseNumber,
                        'course_name'   => $courseDetails[$courseNumber]['name'],
                        'credit_hours'  => $courseDetails[$courseNumber]['hours'],
                    ];
                } else {
                    // إذا لم نجد أي تفاصيل للمادة، نضيفها بمعلومات افتراضية
                    $finalCoursesToInsert[$courseNumber] = [
                        'course_number' => $courseNumber,
                        'course_name'   => '-',
                        'credit_hours'  => 0,
                    ];
                }
            }
        }
        fclose($recommendationsFile);
        $this->command->info('تم تحديد قائمة المواد النهائية المطلوبة.');

        // --- 3. إفراغ الجدول وإدخال البيانات النظيفة ---
        DB::table('courses')->truncate(); // إفراغ الجدول لضمان بداية نظيفة

        // تحويل المصفوفة للإدخال
        $insertData = [];
        foreach ($finalCoursesToInsert as $course) {
            $insertData[] = [
                'course_number' => $course['course_number'],
                'course_name'   => $course['course_name'],
                'credit_hours'  => $course['credit_hours'],
                'status'        => 'active',
                'created_at'    => now(),
                'updated_at'    => now()
            ];
        }

        // إدخال البيانات دفعة واحدة
        foreach (array_chunk($insertData, 500) as $chunk) {
            DB::table('courses')->insert($chunk);
        }

        $this->command->info('🎉 اكتملت عملية تعبئة جدول المواد الرئيسي بنجاح!');
    }
}