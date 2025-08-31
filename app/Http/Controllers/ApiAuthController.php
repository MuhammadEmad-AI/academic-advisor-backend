<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class ApiAuthController extends Controller
{
    public function login(Request $request)
    {
        // 1. التحقق من الإيميل وكلمة السر (يبقى كما هو)
        $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $user = User::where('email', $request->email)->firstOrFail();
        $token = $user->createToken('api_token')->plainTextToken;

        // --- هنا يبدأ الكود الجديد ---
        
        // 2. نحضر ملف الطالب المرتبط بالمستخدم
        $student = $user->student;

        // 3. نقوم بتجهيز مصفوفة للملخص الأكاديمي مع قيم افتراضية
        $academicSummary = [
            'gpa' => null,
            'has_study_plan' => false,
            'recommendation_count' => 0,
        ];

        // 4. نتأكد من وجود ملف للطالب قبل المتابعة
        if ($student) {
            // نأخذ الـ GPA مباشرة من سجل الطالب
            $academicSummary['gpa'] = $student->gpa;

            // نبحث عن آخر خطة دراسية تم إنشاؤها للطالب
            // withCount('courses') هي طريقة ذكية وفعالة لعد المواد المرتبطة دون تحميلها كلها
            $studyPlan = $student->studyPlans()->withCount('courses')->latest()->first();

            if ($studyPlan) {
                $academicSummary['has_study_plan'] = true;
                // courses_count هو حقل جديد يضيفه withCount تلقائياً
                $academicSummary['recommendation_count'] = $studyPlan->courses_count;
            }
        }
        
        // --- نهاية الكود الجديد ---

        // 5. نرجع كل البيانات: التوكن، المستخدم، والملخص الأكاديمي الجديد
        return response()->json([
            'token' => $token,
            'user' => $user,
            'academic_summary' => $academicSummary // <-- تمت إضافة هذا الجزء
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out successfully']);
    }
}


