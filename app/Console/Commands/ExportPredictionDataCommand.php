<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Student;
use App\Models\Course;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExportPredictionDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'export:prediction-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Exports student data in the prediction format required by the ML model.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting prediction data export for the ML model...');
        Log::info('--- ExportPredictionData Command: START ---');

        // 1. نقرأ العناوين من ملف صديقك لنضمن التطابق الكامل في الترتيب والأسماء
        $referenceCsv = fopen(database_path('data/dataset.csv'), 'r');
        $headerRow = fgetcsv($referenceCsv);
        fclose($referenceCsv);
        
        // نستخرج فقط عناوين المواد ونحولها إلى collection لسهولة البحث
        $courseHeaders = collect(array_slice($headerRow, 0, -5));

        // 2. نجهز الملف الناتج ونكتب العناوين
        $filePath = storage_path('app/prediction_data_with_answers.csv');
        $file = fopen($filePath, 'w');
        fputcsv($file, $headerRow);

        // 3. نحضر الطلاب الذين لديهم سجلات من عام 2024
        $students = Student::whereHas('courses', function ($query) {
            $query->whereIn('student_courses.semester_id', function ($subQuery) {
                $subQuery->select('id')->from('semesters')->where('Year', '2024');
            });
        })->get();

        $this->info("Found {$students->count()} students with records in 2024. Processing...");

        foreach ($students as $student) {
            $this->info("Processing student: {$student->student_number}");

            // 4. نحضر كل سجلات الطالب (ناجح أو راسب) من عام 2024 ونرتبها زمنياً
            $academicHistory2024 = DB::table('student_courses')
                ->join('semesters', 'student_courses.semester_id', '=', 'semesters.id')
                ->join('courses', 'student_courses.course_id', '=', 'courses.id')
                ->where('student_id', $student->id)
                ->where('semesters.Year', '2024')
                ->whereIn('student_courses.status', ['completed', 'failed'])
                ->select('courses.course_number', 'student_courses.final_mark', 'semesters.SemesterName', 'semesters.Year')
                ->orderBy('semesters.SemesterName') // نرتب حسب الفصل
                ->get();

            // 5. نحضر السجل التاريخي الكامل للطالب (كل السنوات السابقة لـ 2024)
            $pastHistory = DB::table('student_courses')
                ->join('courses', 'student_courses.course_id', '=', 'courses.id')
                ->join('semesters', 'student_courses.semester_id', '=', 'semesters.id')
                ->where('student_id', $student->id)
                ->where('semesters.Year', '<', '2024')
                ->pluck('final_mark', 'course_number');

            // 6. نمر على كل مادة درسها الطالب في 2024 لإنشاء سطر خاص بها
            foreach ($academicHistory2024 as $record) {
                // --- بناء السجل التاريخي (الأعمدة الأولى) ---
                $historicalRow = [];
                foreach ($courseHeaders as $header) {
                    // نضع علامة المادة من السجل التاريخي السابق
                    $historicalRow[] = $pastHistory[$header] ?? '';
                }
                
                // --- بناء معلومات الحدث الحالي (آخر 5 أعمدة) ---
                $currentRowData = [
                    $student->student_number,      // Student_ID
                    $record->course_number,         // Course_ID
                    $record->final_mark,            // Mark (العلامة الفعلية كما طلبت)
                    $record->SemesterName,          // Semester
                    $record->Year                   // Year
                ];
                
                // 7. ندمج الجزأين ونكتب السطر في الملف
                fputcsv($file, array_merge($historicalRow, $currentRowData));

                // 8. تحديث السجل السابق: نضيف علامة المادة الحالية ليتم استخدامها في السطر التالي لنفس الطالب
                $pastHistory[$record->course_number] = $record->final_mark;
            }
        }

        fclose($file);
        Log::info("--- ExportPredictionData Command: FINISHED. File saved at: {$filePath} ---");
        $this->info("Data exported successfully! You can find the file at: {$filePath}");
        return 0;
    }
}

