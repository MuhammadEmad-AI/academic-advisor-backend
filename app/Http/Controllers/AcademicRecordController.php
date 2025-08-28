<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\Course;
class AcademicRecordController extends Controller
{
    /**
     * وظيفة هذا الـ API هي جلب السجل الأكاديمي للطالب،
     * أي كل المواد التي أنهاها (سواء نجح أو رسب) مع علاماتها.
     */
    public function getGrades(Request $request)
    {
        // 1. نتحقق من هوية الطالب عبر التوكن
        $student = Auth::user()->student;

        if (!$student) {
            return response()->json(['message' => 'Student profile not found.'], 404);
        }

        // 2. نحضر كل المواد المرتبطة بالطالب التي حالتها 'completed' أو 'failed'
        $academicRecord = $student->courses()
                                  ->wherePivotIn('status', ['completed', 'failed'])
                                  ->get();

        // 3. نقوم بتنسيق البيانات لإرجاعها بشكل واضح
        // نحن نريد عرض بيانات المادة نفسها + البيانات الخاصة بالطالب (العلامة والنقاط)
        $formattedRecord = $academicRecord->map(function ($course) {
            return [
                'course_number' => $course->course_number,
                'course_name' => $course->course_name,
                'credit_hours' => $course->credit_hours,
                'student_status' => $course->pivot->status, // 'completed' or 'failed'
                'grade' => $course->pivot->grade,
                'point' => $course->pivot->point,
            ];
        });

        // 4. نرجع السجل الأكاديمي المنسق بصيغة JSON
        return response()->json($formattedRecord);
    }

    public function getFailedCourses(Request $request)
    {
        // 1. نتحقق من هوية الطالب
        $student = Auth::user()->student;

        if (!$student) {
            return response()->json(['message' => 'Student profile not found.'], 404);
        }

        // 2. نحضر كل المواد المرتبطة بالطالب التي حالتها 'failed'
        $failedCourses = $student->courses()
                                 ->wherePivot('status', 'failed')
                                 ->get();

        // 3. (اختياري لكن موصى به) تنسيق البيانات لإرجاعها بشكل واضح
        $formattedRecord = $failedCourses->map(function ($course) {
            return [
                'course_number' => $course->course_number,
                'course_name' => $course->course_name,
                'credit_hours' => $course->credit_hours,
                'grade' => $course->pivot->grade, // العلامة التي رسب بها
            ];
        });

        // 4. نرجع قائمة المواد الراسبة بصيغة JSON
        return response()->json($formattedRecord);
    }
    public function getGraduationStatus(Request $request)
    {
        Log::info('--- Graduation Status API: START ---');
        $student = Auth::user()->student;

        if (!$student) {
            return response()->json(['message' => 'Student profile not found.'], 404);
        }

        // --- خطوة التشخيص الأولى ---
        // لنتأكد من أن الطالب مرتبط بـ degree_id أصلاً
        if (!$student->degree_id) {
            Log::error("Student (ID: {$student->id}) has no degree_id associated with them.");
            return response()->json(['message' => "Student is not registered in any degree plan."], 404);
        }
        Log::info("Student (ID: {$student->id}) is associated with degree_id: {$student->degree_id}");

        // --- خطوة التشخيص الثانية ---
        // الآن لنجلب الشهادة ونتأكد من أنها ليست null
        $degree = $student->degree;
        if (!$degree) {
            Log::error("Could not find a Degree with ID: {$student->degree_id}. The degree might have been deleted or the ID is incorrect.");
            return response()->json(['message' => "Degree plan not found."], 404);
        }
        Log::info("Successfully fetched degree: '{$degree->degree_name}'");


        // 1. الآن يمكننا بأمان جلب قائمة المواد المطلوبة
        $requiredCourseIds = $student->degree->courses()->pluck('courses.id'); // <-- قمنا بتحديد اسم الجدول
        // 2. نحصل على قائمة المواد المنجزة
        $completedCourseIds = $student->courses()
                                      ->wherePivot('status', 'completed')
                                      ->pluck('courses.id');

        // ... بقية الكود يبقى كما هو ...
        $remainingCourseIds = $requiredCourseIds->diff($completedCourseIds);

        if ($remainingCourseIds->isEmpty()) {
            return response()->json([
                'status' => 'Ready for Graduation',
                'message' => 'Congratulations! You have completed all required courses for your degree.',
                'total_required' => $requiredCourseIds->count(),
                'total_completed' => $completedCourseIds->count(),
            ]);
        } else {
            // إذا كانت هناك مواد متبقية، نعرضها للطالب
            $remainingCourses = Course::whereIn('id', $remainingCourseIds)->get([
                'id', 'course_number', 'course_name', 'credit_hours'
            ]);

            return response()->json([
                'status' => 'Courses Remaining',
                'message' => 'You still have remaining courses to complete before graduation.',
                'total_required' => $requiredCourseIds->count(),
                'total_completed' => $completedCourseIds->count(),
                'remaining_courses_count' => $remainingCourseIds->count(),
                'remaining_courses' => $remainingCourses,
            ]);
        }
    }

}