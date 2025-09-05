<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PredictedMarksSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // --- الخطوة الجديدة: جلب كل أرقام الطلاب الموجودين حالياً ---
        // نستخدم pluck للحصول على قائمة بالأرقام فقط، وflip لجعل البحث أسرع (O(1) complexity)
        $existingStudents = DB::table('students')->pluck('student_number')->flip();

        // -------------------------------------------------------------

        $csvFile = fopen(database_path('data/recommendation_results.csv'), 'r');

        if ($csvFile === false) {
            $this->command->error('لا يمكن فتح ملف CSV. تأكد من وجوده في المسار الصحيح.');
            return;
        }

        DB::table('predicted_marks')->truncate();
        fgetcsv($csvFile);

        $chunk = [];
        $chunkSize = 500;
        $skippedCount = 0; // عداد للسجلات التي تم تخطيها

        while (($data = fgetcsv($csvFile, 1000, ',')) !== false) {
            if (isset($data[0]) && isset($data[1]) && isset($data[2])) {

                $studentIdFromCsv = trim($data[1]);

                // --- الخطوة الجديدة: التحقق من وجود الطالب قبل الإضافة ---
                if ($existingStudents->has($studentIdFromCsv)) {

                    $chunk[] = [
                        'student_number' => $studentIdFromCsv,
                        'course_number'  => trim($data[0]),
                        'predicted_mark' => (float)trim($data[2]),
                        'created_at'     => now(),
                        'updated_at'     => now(),
                    ];

                } else {
                    // إذا كان الطالب غير موجود، قم بزيادة العداد فقط
                    $skippedCount++;
                }
                // ---------------------------------------------------------
            }

            if (count($chunk) >= $chunkSize) {
                DB::table('predicted_marks')->insert($chunk);
                $chunk = [];
            }
        }

        if (!empty($chunk)) {
            DB::table('predicted_marks')->insert($chunk);
        }

        fclose($csvFile);

        $this->command->info('تم تعبئة جدول predicted_marks بنجاح للطلاب الموجودين فقط.');
        if ($skippedCount > 0) {
            $this->command->warn("تم تخطي عدد {$skippedCount} سجل لطلاب غير موجودين في قاعدة البيانات.");
        }
    }
}