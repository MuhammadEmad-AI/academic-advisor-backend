<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Student;
use Illuminate\Support\Facades\Log;

class CalculateGpaCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    // هذا هو الأمر الذي سنكتبه في الـ terminal لتشغيل الكود
    protected $signature = 'app:calculate-gpa'; 

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculate and update the GPA for all students';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting GPA calculation for all students...');
        Log::info('--- CalculateGpaCommand: START ---');

        try {
            // 1. Reset all GPAs to 0 first
            $this->info('Resetting all student GPAs to 0...');
            \DB::table('students')->update(['gpa' => 0]);
            
            // 2. Calculate GPA using a single optimized query (same as in PharmacyStudentRecordSeeder)
            $this->info('Calculating GPAs using optimized query...');
            
            $gpaSub = \DB::table('student_courses')
                ->join('courses', 'courses.id', '=', 'student_courses.course_id')
                ->where('student_courses.status', 'completed')
                ->select(
                    'student_courses.student_id',
                    \DB::raw('SUM(student_courses.point * courses.credit_hours) as total_points'),
                    \DB::raw('SUM(courses.credit_hours) as total_hours')
                )
                ->groupBy('student_courses.student_id')
                ->havingRaw('SUM(courses.credit_hours) > 0'); // Only include students with completed courses

            $updatedCount = \DB::table('students')
                ->joinSub($gpaSub, 'gpa_sub', function($join){
                    $join->on('students.id','=','gpa_sub.student_id');
                })
                ->update([
                    'students.gpa' => \DB::raw('ROUND(gpa_sub.total_points / gpa_sub.total_hours, 2)')
                ]);

            $this->info("Updated GPA for {$updatedCount} students");
            Log::info("Updated GPA for {$updatedCount} students");

        } catch (\Exception $e) {
            $this->error("Error calculating GPAs: " . $e->getMessage());
            Log::error("Error calculating GPAs: " . $e->getMessage());
            return 1;
        }

        Log::info('--- CalculateGpaCommand: FINISHED ---');
        $this->info('GPA calculation completed successfully!');
        return 0;
    }
}