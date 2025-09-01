<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Student;
use App\Models\Course;
use App\Models\Semester;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExportStudentDataForModel extends Command
{
    protected $signature = 'export:student-data';
    protected $description = 'Exports student data in the format required by the ML model.';

    public function handle()
    {
        $this->info('Starting data export for the ML model...');
        Log::info('--- ExportStudentData Command: START ---');

        // 1. نحضر قائمة بكل رموز المواد لتكون هي عناوين الأعمدة
        $allCourses = Course::orderBy('id')->get();
        $courseHeaders = $allCourses->pluck('course_number')->toArray();

        // 2. نجهز الملف ونكتب العناوين
        $filePath = storage_path('app/dataset_for_model.csv');
        $file = fopen($filePath, 'w');
        fputcsv($file, array_merge($courseHeaders, ['Student_ID', 'Course_ID', 'Mark', 'Semester', 'Year']));

        // 3. نحضر كل الطلاب الذين لديهم سجلات أكاديمية
        $students = Student::whereHas('courses')->get();

        foreach ($students as $student) {
            $this->info("Processing student: {$student->student_number}");

            // 4. نحضر كل سجلات الطالب مرتبة زمنياً
            $academicHistory = DB::table('student_courses')
                ->join('semesters', 'student_courses.semester_id', '=', 'semesters.id')
                ->where('student_id', $student->id)
                ->whereIn('status', ['completed', 'failed'])
                ->select('course_id', 'final_mark', 'semesters.Year', 'semesters.SemesterName')
                ->orderBy('semesters.Year')
                ->orderBy('semesters.SemesterName')
                ->get();
            
            $pastMarks = []; // لتخزين علامات الطالب السابقة

            // 5. نمر على كل مادة درسها الطالب لإنشاء سطر خاص بها
            foreach ($academicHistory as $record) {
                // --- بناء السجل التاريخي (أول 111 عمود) ---
                $historicalRow = [];
                foreach ($allCourses as $course) {
                    // إذا كانت المادة موجودة في سجل الطالب السابق، نضع علامتها
                    // وإلا نتركها فارغة
                    $historicalRow[] = $pastMarks[$course->id] ?? null;
                }
                
                // --- بناء معلومات الحدث الحالي (آخر 5 أعمدة) ---
                $currentCourse = $allCourses->find($record->course_id);
                if (!$currentCourse) continue;

                $currentRowData = [
                    $student->student_number,
                    $currentCourse->course_number,
                    $record->final_mark,
                    $record->SemesterName,
                    $record->Year
                ];
                
                // 6. ندمج الجزأين ونكتب السطر في الملف
                fputcsv($file, array_merge($historicalRow, $currentRowData));

                // 7. تحديث السجل السابق: نضيف علامة المادة الحالية ليتم استخدامها في السطر التالي
                $pastMarks[$record->course_id] = $record->final_mark;
            }
        }

        fclose($file);
        Log::info("--- ExportStudentData Command: FINISHED. File saved at: {$filePath} ---");
        $this->info("Data exported successfully! You can find the file at: {$filePath}");
        return 0;
    }
}