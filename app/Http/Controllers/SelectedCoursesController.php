<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB; // <-- لا تنس إضافة هذا السطر
use App\Models\StudyPlan; // <-- سنستخدم هذا النموذج

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
        $validated = $request->validate([
            'course_ids'   => 'required|array|min:1',
            'course_ids.*' => 'required|integer|distinct|exists:courses,id',
        ]);
    
        $student = Auth::user()->student;
        if (! $student) {
            return response()->json(['message' => 'Student profile not found.'], 404);
        }
    
        $studyPlan = StudyPlan::firstOrCreate(
            ['student_id' => $student->id],
            ['name' => 'My Study Plan']
        );
    
        $incoming  = $validated['course_ids'];
        $existing  = $studyPlan->courses()->whereIn('courses.id', $incoming)
                            ->pluck('courses.id')->all();
        $toAdd     = array_values(array_diff($incoming, $existing));
    
        if (empty($toAdd)) {
            return response()->json([
                'message'         => 'All courses are already in your study plan.',
                'already_in_plan' => $existing,
            ], 409);
        }
    
        DB::transaction(function () use ($studyPlan, $student, $toAdd) {
            // أضفها إلى جدول الخطة بدون تكرار
            $studyPlan->courses()->syncWithoutDetaching($toAdd);
    
            // أنشئ/حدّث السجل في student_courses مع حالة selected
            $payload = [];
            foreach ($toAdd as $cid) {
                $payload[$cid] = [
                    'status' => 'selected',
                    'final_mark' => null,
                    'grade' => null,
                    'point' => null,
                    // إما أن تتركها NULL إذا جعلت العمود nullable
                    //'semester_id' => null
                    // أو يمكنك هنا تحديد الفصل القادم، مثل:
                     'semester_id' => 1
                ];
            }
            $student->courses()->syncWithoutDetaching($payload);
        });
    
        return response()->json([
            'message'         => 'Courses added successfully.',
            'added'           => $toAdd,
            'already_in_plan' => $existing,
        ], 201);
    }
    
    public function destroy(Request $request)
    {
        $validated = $request->validate([
            'course_ids'   => 'required|array|min:1',
            'course_ids.*' => 'required|integer|distinct|exists:courses,id',
        ]);

        $student = Auth::user()->student;
        if (! $student) {
            return response()->json(['message' => 'Student profile not found.'], 404);
        }

        // الحصول على آخر خطة (أو استخدام firstOrCreate إذا أردت إنشاء واحدة)
        $plan = $student->studyPlans()->latest()->first();
        if (! $plan) {
            return response()->json(['message' => 'No study plan found.'], 404);
        }

        $incoming  = $validated['course_ids'];
        $current   = $plan->courses()->pluck('courses.id')->all();
        $toRemove  = array_values(array_intersect($current, $incoming));
        $notInPlan = array_values(array_diff($incoming, $current));

        if (empty($toRemove)) {
            return response()->json([
                'message'     => 'None of the provided courses are in the study plan.',
                'not_in_plan' => $notInPlan,
            ], 404);
        }

        DB::transaction(function () use ($plan, $student, $toRemove) {
            // إزالة المواد من جدول study_plan_courses
            $plan->courses()->detach($toRemove);

            // إزالة المواد من جدول student_courses فقط إذا كانت حالتها selected
            foreach ($toRemove as $cid) {
                $record = $student->courses()->where('courses.id', $cid)->first();
                if ($record && $record->pivot->status === 'selected') {
                    $student->courses()->detach($cid);
                }
            }
        });

        return response()->json([
            'message'     => 'Courses removed from your study plan.',
            'removed'     => $toRemove,
            'not_in_plan' => $notInPlan,
        ], 200);
    }

    
}
