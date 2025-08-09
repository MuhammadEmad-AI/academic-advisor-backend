<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log; // <-- لا تنس إضافة هذا السطر

class EligibleCoursesController extends Controller
{
    public function getEligibleCourses(Request $request)
    {
        Log::info('--- EligibleCourses API: START ---');
        $student = Auth::user()->student;

        if (!$student) {
            Log::error('EligibleCourses API: Student profile not found for authenticated user.');
            return response()->json(['message' => 'Student profile not found.'], 404);
        }

        // 1. لنرَ ما هي المواد التي يعتبرها النظام "منجزة"
        $completedCoursesIds = $student->courses()
                                       ->wherePivot('status', 'completed')
                                       ->pluck('courses.id');
        Log::info('Step 1: Found ' . $completedCoursesIds->count() . ' completed courses. IDs: ' . $completedCoursesIds->implode(', '));


        // 2. لنرَ كل مواد الخطة الدراسية
        $allDegreeCourses = $student->degree->courses()->with('prerequisites')->get();
        Log::info('Step 2: Found ' . $allDegreeCourses->count() . ' total courses for the degree.');


        // 3. لنرَ المواد المتبقية
        $remainingCourses = $allDegreeCourses->whereNotIn('id', $completedCoursesIds);
        Log::info('Step 3: Found ' . $remainingCourses->count() . ' remaining courses after removing completed ones.');


        // 4. الآن سنقوم بالفلترة وسنرى لماذا يتم قبول أو رفض كل مادة
        Log::info('--- Step 4: Filtering eligible courses... ---');
        $eligibleCourses = $remainingCourses->filter(function ($course) use ($completedCoursesIds) {

            $prerequisiteIds = $course->prerequisites->pluck('id');

            Log::info("Checking course: '{$course->course_name}'");

            if ($prerequisiteIds->isEmpty()) {
                Log::info(" -> Status: ACCEPTED (No prerequisites)");
                return true;
            }

            $missingPrerequisites = $prerequisiteIds->diff($completedCoursesIds);

            if ($missingPrerequisites->isEmpty()) {
                Log::info(" -> Status: ACCEPTED (All prerequisites met)");
                return true;
            } else {
                Log::info(" -> Status: REJECTED (Missing prerequisites. Needed: " . $prerequisiteIds->implode(', ') . ", Missing: " . $missingPrerequisites->implode(', ') . ")");
                return false;
            }
        });
        Log::info('--- Filtering finished. Found ' . $eligibleCourses->count() . ' eligible courses. ---');

        return response()->json($eligibleCourses->values());
    }
}