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

        // 1. نحضر كل الطلاب الموجودين في قاعدة البيانات
        $students = Student::all();

        foreach ($students as $student) {
            // 2. لكل طالب، نقوم بحساب المعدل بنفس الطريقة السابقة
            $completedCourses = $student->courses()->wherePivot('status', 'completed')->get();

            if ($completedCourses->isEmpty()) {
                $student->gpa = 0.0;
                $student->save();
                continue; // انتقل للطالب التالي
            }

            $totalPoints = 0;
            $totalHours = 0;

            foreach ($completedCourses as $course) {
                $hours = $course->credit_hours;
                $point = $course->pivot->point;
                $totalPoints += $point * $hours;
                $totalHours += $hours;
            }

            // 3. نقوم بتحديث المعدل في قاعدة البيانات
            if ($totalHours > 0) {
                $gpa = round($totalPoints / $totalHours, 2);
                $student->gpa = $gpa;
                $student->save();
                $this->info("Updated GPA for student {$student->student_number} to {$gpa}");
            }
        }

        Log::info('--- CalculateGpaCommand: FINISHED ---');
        $this->info('GPA calculation completed successfully!');
        return 0;
    }
}