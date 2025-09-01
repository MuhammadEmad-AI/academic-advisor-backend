<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Student;
use App\Models\Course;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExportPredictionDataCommand extends Command
{
    protected $signature = 'export:prediction-data';
    protected $description = 'Exports student data in the prediction format required by the ML model.';

    public function handle()
    {
        // --- تم التصحيح هنا ---
        $this->info('Starting prediction data export for the ML model...');
        Log::info('--- ExportPredictionData Command: START ---');

        $referenceCsv = fopen(database_path('data/dataset.csv'), 'r');
        $headerRow = fgetcsv($referenceCsv);
        fclose($referenceCsv);
        
        $courseHeaders = array_slice($headerRow, 0, -5);

        $filePath = storage_path('app/prediction_data.csv');
        $file = fopen($filePath, 'w');
        fputcsv($file, $headerRow);

        $students = Student::whereHas('courses', function ($query) {
            $query->whereIn('student_courses.semester_id', function ($subQuery) {
                $subQuery->select('id')->from('semesters')->where('Year', '2024');
            });
        })->get();

        // --- تم التصحيح هنا ---
        $this->info("Found {$students->count()} students with records in 2024. Processing...");

        foreach ($students as $student) {
            // --- تم التصحيح هنا ---
            $this->info("Processing student: {$student->student_number}");

            $academicHistory = DB::table('student_courses')
                ->join('courses', 'student_courses.course_id', '=', 'courses.id')
                ->where('student_id', $student->id)
                ->whereIn('student_courses.status', ['completed', 'failed']) // <-- تم إضافة اسم الجدول هنا لتجنب الغموض
                ->pluck('final_mark', 'course_number');
            
            $completedCoursesIds = $student->courses()->wherePivot('status', 'completed')->pluck('courses.id');
            $allDegreeCourses = $student->degree->courses()->with('prerequisites')->get();
            $remainingCourses = $allDegreeCourses->whereNotIn('id', $completedCoursesIds);
            $eligibleCourses = $remainingCourses->filter(function ($course) use ($completedCoursesIds) {
                $prerequisiteIds = $course->prerequisites->pluck('id');
                return $prerequisiteIds->diff($completedCoursesIds)->isEmpty();
            });

            if ($eligibleCourses->isNotEmpty()) {
                $historicalRow = [];
                foreach ($courseHeaders as $header) {
                    $historicalRow[] = $academicHistory[$header] ?? '';
                }

                foreach ($eligibleCourses as $eligibleCourse) {
                    $predictionRowData = [
                        $student->student_number,
                        $eligibleCourse->course_number,
                        '',
                        1,
                        2025
                    ];
                    fputcsv($file, array_merge($historicalRow, $predictionRowData));
                }
            }
        }

        fclose($file);
        Log::info("--- ExportPredictionData Command: FINISHED. File saved at: {$filePath} ---");
        // --- تم التصحيح هنا ---
        $this->info("Data exported successfully! You can find the file at: {$filePath}");
        return 0;
    }
}