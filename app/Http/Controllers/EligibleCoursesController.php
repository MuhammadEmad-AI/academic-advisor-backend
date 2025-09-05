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

        // 1. معرفات المواد المكتملة (passed)
        $completedCourseIds = $student->courses()
            ->wherePivot('status', 'completed')
            ->pluck('courses.id');

        // 2. معرفات المواد فى الخطة أو قيد الدراسة (selected أو in_progress)
        $planCourseIds = $student->courses()
            ->wherePivotIn('status', ['selected', 'in_progress'])
            ->pluck('courses.id');

        // 3. جميع مواد الدرجة مع متطلباتها
        $allDegreeCourses = $student->degree->courses()
            ->with(['prerequisites.requirements', 'requirements'])
            ->get();

        // 4. استبعاد المكتملة والمواد التى فى الخطة الحالية
        $excludedIds = $completedCourseIds->merge($planCourseIds);
        $remainingCourses = $allDegreeCourses->whereNotIn('id', $excludedIds);

        // 5. تصفية المواد التى تستوفى شروط المتطلبات المسبقة
        $eligibleCourses = $remainingCourses->filter(function ($course) use ($completedCourseIds) {
            $prerequisiteIds = $course->prerequisites->pluck('id');
            return $prerequisiteIds->diff($completedCourseIds)->isEmpty();
        });

        /**
         * NEW: تحديد سقف الساعات الاختيارية المطلوبة لكل فئة
         *      - متطلبات الكلية الاختيارية: 10 ساعات
         *      - متطلبات الجامعة الاختيارية: 4 ساعات
         */
        $electiveRequirements = [
            'college_elective'    => 10,
            'university_elective' => 4,
        ];

        // حساب مجموع الساعات المكتملة لكل فئة اختيارية
        $completedCollegeElectiveCredits = $student->courses()
            ->wherePivot('status', 'completed')
            ->whereHas('requirements', function ($q) use ($student) {
                $q->where('degree_id', $student->degree_id)
                  ->where('requirement_type', 'college_elective');
            })->sum('credit_hours');

        $completedUniversityElectiveCredits = $student->courses()
            ->wherePivot('status', 'completed')
            ->whereHas('requirements', function ($q) use ($student) {
                $q->where('degree_id', $student->degree_id)
                  ->where('requirement_type', 'university_elective');
            })->sum('credit_hours');

        // حساب مجموع الساعات الراسبة (التى يجب إعادة تقديمها) لكل فئة
        $failedCollegeElectiveCredits = $student->courses()
            ->wherePivot('status', 'failed')
            ->whereHas('requirements', function ($q) use ($student) {
                $q->where('degree_id', $student->degree_id)
                  ->where('requirement_type', 'college_elective');
            })->sum('credit_hours');

        $failedUniversityElectiveCredits = $student->courses()
            ->wherePivot('status', 'failed')
            ->whereHas('requirements', function ($q) use ($student) {
                $q->where('degree_id', $student->degree_id)
                  ->where('requirement_type', 'university_elective');
            })->sum('credit_hours');

        // حساب الساعات المتبقية المطلوبة من كل فئة
        $remainingCollegeElectiveCredits = max(
            0,
            $electiveRequirements['college_elective'] - $completedCollegeElectiveCredits
        );
        // الساعات التى يجب تغطيتها بمواد جديدة بعد إضافة الراسبة
        $neededNewCollegeElectiveCredits = max(
            0,
            $remainingCollegeElectiveCredits - $failedCollegeElectiveCredits
        );

        $remainingUniversityElectiveCredits = max(
            0,
            $electiveRequirements['university_elective'] - $completedUniversityElectiveCredits
        );
        $neededNewUniversityElectiveCredits = max(
            0,
            $remainingUniversityElectiveCredits - $failedUniversityElectiveCredits
        );

        // المواد الراسبة لكل فئة (يجب إعادة تقديمها ضمن المؤهلين)
        $failedCollegeElectiveCourses = $student->courses()
            ->wherePivot('status','failed')
            ->whereHas('requirements', function ($q) use ($student) {
                $q->where('degree_id',$student->degree_id)
                  ->where('requirement_type','college_elective');
            })->get();

        $failedUniversityElectiveCourses = $student->courses()
            ->wherePivot('status','failed')
            ->whereHas('requirements', function ($q) use ($student) {
                $q->where('degree_id',$student->degree_id)
                  ->where('requirement_type','university_elective');
            })->get();

        // استخراج المواد الاختيارية المفتوحة لكل فئة
        $openCollegeElectives = $eligibleCourses->filter(function ($course) use ($student) {
            $req = $course->requirements->where('degree_id', $student->degree_id)->first();
            return $req && $req->requirement_type === 'college_elective';
        });

        $openUniversityElectives = $eligibleCourses->filter(function ($course) use ($student) {
            $req = $course->requirements->where('degree_id', $student->degree_id)->first();
            return $req && $req->requirement_type === 'university_elective';
        });

        // اختيار عدد محدود من المواد المفتوحة لكل فئة اختيارية
        $selectedCollegeElectives = collect();
        $creditSumCollege = 0;
        foreach ($openCollegeElectives as $course) {
            if ($creditSumCollege >= $neededNewCollegeElectiveCredits) {
                break;
            }
            $selectedCollegeElectives->push($course);
            $creditSumCollege += $course->credit_hours;
        }

        $selectedUniversityElectives = collect();
        $creditSumUni = 0;
        foreach ($openUniversityElectives as $course) {
            if ($creditSumUni >= $neededNewUniversityElectiveCredits) {
                break;
            }
            $selectedUniversityElectives->push($course);
            $creditSumUni += $course->credit_hours;
        }

        // إنشاء القائمة النهائية للمواد المؤهلة:
        //  - المواد غير الاختيارية
        //  - المواد الراسبة لكل فئة اختيارية
        //  - المواد المفتوحة المختارة بما يكفى لتغطية المتبقى
        $restrictedEligibleCourses = collect();

        // أضف المواد غير الاختيارية كما هى
        foreach ($eligibleCourses as $course) {
            $req = $course->requirements->where('degree_id', $student->degree_id)->first();
            if (!$req || !in_array($req->requirement_type, ['college_elective', 'university_elective'])) {
                $restrictedEligibleCourses->push($course);
            }
        }

        $restrictedEligibleCourses = $restrictedEligibleCourses
            ->merge($failedCollegeElectiveCourses)
            ->merge($failedUniversityElectiveCourses)
            ->merge($selectedCollegeElectives)
            ->merge($selectedUniversityElectives)
            ->unique('id'); // للتأكد من عدم التكرار

        // تحديد المواد الراسبة لإسناد history_status
        $failedCoursesIds = $student->courses()
            ->wherePivot('status', 'failed')
            ->pluck('courses.id');

        // 6. التنسيق النهائى للإرجاع، مع حقول history_status و requirement_type و prerequisites
        $formattedCourses = $restrictedEligibleCourses->map(function ($course) use ($student, $failedCoursesIds) {
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

