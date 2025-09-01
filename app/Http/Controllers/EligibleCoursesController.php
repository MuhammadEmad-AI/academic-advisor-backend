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

        // 1. احصل على معرفات المواد المكتملة
        $completedCourseIds = $student->courses()
            ->wherePivot('status', 'completed')
            ->pluck('courses.id');

        // 2. احصل على معرفات المواد التي في الخطة الدراسية (selected أو in_progress)
        // Laravel يدعم wherePivotIn لاستعلام حالات متعددة في الجدول الوسيط:contentReference[oaicite:0]{index=0}.
        $planCourseIds = $student->courses()
            ->wherePivotIn('status', ['selected', 'in_progress'])
            ->pluck('courses.id');

        // 3. احصل على كل مواد الدرجة مع متطلباتها
        $allDegreeCourses = $student->degree->courses()
            ->with(['prerequisites.requirements', 'requirements'])
            ->get();

        // 4. استبعد المواد المكتملة والمواد الموجودة بالفعل في خطة الطالب
        $excludedIds = $completedCourseIds->merge($planCourseIds);
        $remainingCourses = $allDegreeCourses->whereNotIn('id', $excludedIds);

        // 5. صِفِّ المواد التي تستوفي شروط المتطلبات المسبقة (إما بدون متطلبات أو جميع متطلباتها مكتملة)
        $eligibleCourses = $remainingCourses->filter(function ($course) use ($completedCourseIds) {
            $prerequisiteIds = $course->prerequisites->pluck('id');
            // إذا كان الفرق بين المتطلبات المسبقة والمواد المكتملة فارغاً فالمادة مؤهَّلة
            return $prerequisiteIds->diff($completedCourseIds)->isEmpty();
        });

        // 6. بِنْ التنسيق النهائي للإرجاع، مع الحفاظ على حقول history_status و requirement_type و prerequisites
        $failedCoursesIds = $student->courses()
            ->wherePivot('status', 'failed')
            ->pluck('courses.id');

        $formattedCourses = $eligibleCourses->map(function ($course) use ($student, $failedCoursesIds) {
            $mainRequirement = $course->requirements
                ->where('degree_id', $student->degree_id)
                ->first();

            $formattedPrerequisites = $course->prerequisites->map(function ($prereq) use ($student) {
                $prereqRequirement = $prereq->requirements
                    ->where('degree_id', $student->degree_id)
                    ->first();
                return [
                    'course_number'    => $prereq->course_number,
                    'course_name'      => $prereq->course_name,
                    'requirement_type' => $prereqRequirement
                        ? $prereqRequirement->requirement_type
                        : null,
                ];
            });

            return [
                'id'           => $course->id,
                'course_name'  => $course->course_name,
                'course_number'=> $course->course_number,
                'credit_hours' => $course->credit_hours,
                'description'  => $course->description,
                'status'       => $course->status,
                'created_at'   => $course->created_at,
                'updated_at'   => $course->updated_at,
                'pivot'        => $course->pivot
                    ? [
                        'degree_id' => $course->pivot->degree_id,
                        'course_id' => $course->pivot->course_id,
                    ]
                    : null,
                'history_status' => $failedCoursesIds->contains($course->id)
                    ? 'failed_before'
                    : 'not_taken_before',
                'requirement_type' => $mainRequirement
                    ? $mainRequirement->requirement_type
                    : null,
                'prerequisites' => $formattedPrerequisites,
            ];
        });

        return response()->json($formattedCourses->values());
    }

}