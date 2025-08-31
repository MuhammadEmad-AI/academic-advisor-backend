<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class EligibleCoursesController extends Controller
{
    public function getEligibleCourses(Request $request)
    {
        $student = Auth::user()->student;

        if (!$student) {
            return response()->json(['message' => 'Student profile not found.'], 404);
        }

        $completedCoursesIds = $student->courses()->wherePivot('status', 'completed')->pluck('courses.id');
        $failedCoursesIds = $student->courses()->wherePivot('status', 'failed')->pluck('courses.id');

        $allDegreeCourses = $student->degree
                                    ->courses()
                                    ->with(['prerequisites.requirements', 'requirements'])
                                    ->get();

        $remainingCourses = $allDegreeCourses->whereNotIn('id', $completedCoursesIds);

        $eligibleCourses = $remainingCourses->filter(function ($course) use ($completedCoursesIds) {
            $prerequisiteIds = $course->prerequisites->pluck('id');
            return $prerequisiteIds->diff($completedCoursesIds)->isEmpty();
        });

        // --- التعديل هنا: إعادة بناء شكل الخرج النهائي ليشمل كل الحقول ---
        $formattedCourses = $eligibleCourses->map(function ($course) use ($student, $failedCoursesIds) {
            
            $mainRequirement = $course->requirements->where('degree_id', $student->degree_id)->first();
            
            $formattedPrerequisites = $course->prerequisites->map(function ($prereq) use ($student) {
                $prereqRequirement = $prereq->requirements->where('degree_id', $student->degree_id)->first();
                return [
                    'course_number' => $prereq->course_number,
                    'course_name' => $prereq->course_name,
                    'requirement_type' => $prereqRequirement ? $prereqRequirement->requirement_type : null
                ];
            });

            return [
                // --- الحقول القديمة التي يجب إعادتها ---
                'id' => $course->id,
                'course_name' => $course->course_name,
                'course_number' => $course->course_number,
                'credit_hours' => $course->credit_hours,
                'description' => $course->description,
                'status' => $course->status,
                'created_at' => $course->created_at,
                'updated_at' => $course->updated_at,
                'pivot' => $course->pivot ? [ // التأكد من وجود pivot
                    'degree_id' => $course->pivot->degree_id,
                    'course_id' => $course->pivot->course_id,
                ] : null,
                
                // --- الحقول الجديدة التي أضفناها ---
                'history_status' => $failedCoursesIds->contains($course->id) ? 'failed_before' : 'not_taken_before',
                'requirement_type' => $mainRequirement ? $mainRequirement->requirement_type : null,
                'prerequisites' => $formattedPrerequisites
            ];
        });
        
        return response()->json($formattedCourses->values());
    }
}