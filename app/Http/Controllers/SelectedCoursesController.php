<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB; // <-- لا تنس إضافة هذا السطر

class SelectedCoursesController extends Controller
{
    /**
     * وظيفة هذا الـ API هي جلب قائمة بالمواد التي اختارها الطالب
     * (أي التي حالتها 'selected' في سجله الأكاديمي).
     */
    public function index(Request $request)
    {
        // 1. نتحقق من هوية الطالب عبر التوكن
        $student = Auth::user()->student;

        if (!$student) {
            return response()->json(['message' => 'Student profile not found.'], 404);
        }

        // 2. نستخدم علاقة courses() لجلب المواد المرتبطة بالطالب
        // ثم نستخدم wherePivot لتحديد الشرط على الجدول الوسيط
        $selectedCourses = $student->courses()
                                   ->wherePivot('status', 'selected')
                                   ->get();

        // 3. نرجع قائمة المواد المختارة بصيغة JSON
        return response()->json($selectedCourses);
    }

    public function store(Request $request)
    {
        // 1. التحقق من المدخلات (يبقى كما هو)
        $validated = $request->validate([
            'course_ids' => 'required|array', 
            'course_ids.*' => 'required|integer|exists:courses,id' 
        ]);

        $student = Auth::user()->student;
        if (!$student) {
            return response()->json(['message' => 'Student profile not found.'], 404);
        }

        $courseIds = $validated['course_ids'];
        $semesterId = 1; // قيمة افتراضية

        // --- هنا التعديل ---
        // 2. نقوم بتجهيز مصفوفة بالصيغة التي يفهمها Laravel
        $coursesToSync = [];
        foreach ($courseIds as $courseId) {
            // لكل course_id، نحدد البيانات الإضافية التي نريد حفظها معه
            $coursesToSync[$courseId] = [
                'status' => 'selected',
                'semester_id' => $semesterId,
                // grade و point سيتم تجاهلهما لأننا لم نضفهما هنا
            ];
        }

        // 3. الآن نستدعي الدالة مرة واحدة مع المصفوفة المجهزة
        $student->courses()->syncWithoutDetaching($coursesToSync);

        // 4. إرجاع رسالة نجاح (تبقى كما هي)
        return response()->json([
            'message' => 'Courses have been selected successfully.',
            'selected_courses_count' => count($courseIds)
        ], 201); // 201 Created
    }

    public function destroy(Request $request)
    {
        // 1. التحقق من صحة المدخلات (Validation)
        $validated = $request->validate([
            'course_ids' => 'required|array',
            'course_ids.*' => 'integer|exists:courses,id'
        ]);

        $student = Auth::user()->student;
        if (!$student) {
            return response()->json(['message' => 'Student profile not found.'], 404);
        }

        $courseIdsToRemove = $validated['course_ids'];

        // 2. نقوم بحذف السجلات من الجدول الوسيط مباشرة
        // هذا الاستعلام آمن لأنه يضمن 3 شروط:
        // - أن السجل يخص الطالب الحالي
        // - أن حالة المادة هي 'selected' (لا يمكنه حذف مادة منجزة مثلاً)
        // - أن المادة هي ضمن قائمة المواد المطلوب حذفها
        DB::table('student_courses')
            ->where('student_id', $student->id)
            ->where('status', 'selected')
            ->whereIn('course_id', $courseIdsToRemove)
            ->delete();

        // 3. إرجاع رسالة نجاح
        return response()->json([
            'message' => 'Selected courses have been removed successfully.'
        ]);
    }
}