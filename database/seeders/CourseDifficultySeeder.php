<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Course;

class CourseDifficultySeeder extends Seeder
{
    public function run(): void
    {
        $csvPath = database_path('data/pharmacy_student_records.CSV');

        $stats = []; // لتجميع مجموع العلامات وعددها لكل course_number

        if (($handle = fopen($csvPath, 'r')) !== false) {
            while (($row = fgetcsv($handle)) !== false) {
                // ملفك لا يحتوى عناوين؛ الأعمدة مرقمة بترتيبها (0-based)
                // العمود 6 (index 6) هو course code (مثل "PHPC 543")
                // العمود 7 (index 7) هو final mark (مثلاً 75)
                $courseNumber = isset($row[6]) ? trim($row[6]) : null;
                $finalMark    = isset($row[7]) ? $row[7] : null;

                // تجاهل الصفوف ذات البيانات غير الصالحة
                if ($courseNumber && is_numeric($finalMark)) {
                    if (!isset($stats[$courseNumber])) {
                        $stats[$courseNumber] = ['sum' => 0, 'count' => 0];
                    }
                    $stats[$courseNumber]['sum']   += floatval($finalMark);
                    $stats[$courseNumber]['count'] += 1;
                }
            }
            fclose($handle);
        }

        // حساب المتوسط وتحديد الصعوبة
        foreach ($stats as $courseNumber => $info) {
            $avg = $info['sum'] / $info['count'];

            // التصنيف بناءً على المتوسط
            $difficulty = 'medium';
            if ($avg >= 75) {
                $difficulty = 'easy';
            } elseif ($avg < 60) {
                $difficulty = 'hard';
            }

            // تحديث جدول courses
            Course::where('course_number', $courseNumber)->update([
                'avg_grade'  => $avg,
                'difficulty' => $difficulty,
            ]);
        }
    }
}
