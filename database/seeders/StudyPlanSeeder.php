<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\StudyPlan;
use App\Models\Course;

class StudyPlanSeeder extends Seeder
{
    public function run(): void
    {
        // Only seed if student and courses exist
        if (\App\Models\Student::find(1) && Course::find(1) && Course::find(2)) {
            $plan = StudyPlan::create([
                'student_id' => 1,
                'name' => 'Sample Plan for Student 1',
            ]);
            $plan->courses()->attach([1, 2]);
        }
    }
}
