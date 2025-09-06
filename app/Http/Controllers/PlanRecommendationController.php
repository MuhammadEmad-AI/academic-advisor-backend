<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use App\Models\Student;
use App\Models\PredictedMark;
use App\Models\Prerequisite;
use Illuminate\Support\Facades\DB;

class PlanRecommendationController extends Controller
{
    /**
     * استدعاء وكيل الذكاء الاصطناعى عبر n8n.
     * يستخدم الطالب من التوكن ولا يحتاج student_number فى الـ request.
     */
    public function recommendAI(Request $request)
    {
        // التحقق من المدخلات (فقط عدد الساعات)
        $validated = $request->validate([
            'credit_hours' => 'required|integer|min:12|max:18',
        ]);
        $maxHours = $validated['credit_hours'];

        // الحصول على الطالب من التوكن
        $student = Auth::user()->student;
        if (!$student) {
            return response()->json(['message' => 'Student profile not found.'], 404);
        }

        // تحميل العلاقات الضرورية
        $student->load([
            'courses.prerequisites.prerequisite',
            'degree.courses.prerequisites',
            'degree.courses.requirements',
        ]);

        // المواد الناجحة والراسبة مع الخصائص المطلوبة
        $passedCourses = $student->courses()
            ->wherePivot('status', 'completed')
            ->get()
            ->map(function ($course) {
                return [
                    'course_number' => $course->course_number,
                    'course_name'   => $course->course_name,
                    'credit_hours'  => $course->credit_hours,
                    'avg_grade'     => $course->avg_grade,
                    'difficulty'    => $course->difficulty,
                    'grade'         => $course->pivot->grade,
                    'point'         => $course->pivot->point,
                    'final_mark'    => $course->pivot->final_mark,
                ];
            });

        $failedCourses = $student->courses()
            ->wherePivot('status', 'failed')
            ->get()
            ->map(function ($course) {
                return [
                    'course_number' => $course->course_number,
                    'course_name'   => $course->course_name,
                    'credit_hours'  => $course->credit_hours,
                    'avg_grade'     => $course->avg_grade,
                    'difficulty'    => $course->difficulty,
                    'grade'         => $course->pivot->grade,
                    'point'         => $course->pivot->point,
                    'final_mark'    => $course->pivot->final_mark,
                ];
            });

        // المواد المكتملة والموجودة فى الخطة حالياً
        $completedCourseIds = $student->courses()
            ->wherePivot('status', 'completed')
            ->pluck('courses.id');

        $planCourseIds = $student->courses()
            ->wherePivotIn('status', ['selected', 'in_progress'])
            ->pluck('courses.id');

        // الحصول على كل مواد الخطة الدراسية (المفعّلة وساعاتها > 0)
        $allDegreeCourses = $student->degree->courses()
            ->where('status', 'active')
            ->where('credit_hours', '>', 0)
            ->with(['prerequisites.prerequisite', 'requirements'])
            ->get();

        // المواد المتبقية بعد استبعاد المكتملة أو الموجودة فى الخطة
        $remainingCourses = $allDegreeCourses->whereNotIn('id', $completedCourseIds->merge($planCourseIds));

        // المواد المؤهلة: تحقق من المتطلبات المسبقة واستبعد المواد بساعات صفرية
        $eligibleCourses = $remainingCourses->filter(function ($course) use ($completedCourseIds) {
            if ($course->credit_hours <= 0) {
                return false;
            }
            $prereqIds = $course->prerequisites->pluck('prerequisite_id');
            return $prereqIds->diff($completedCourseIds)->isEmpty();
        });

        /**
         * تحديد سقف الساعات الاختيارية:
         *  - متطلبات الكلية الاختيارية: 10 ساعات
         *  - متطلبات الجامعة الاختيارية: 4 ساعات
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

        // حساب مجموع الساعات الراسبة لكل فئة
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

        // حساب ما تبقّى من الساعات المطلوبة لكل فئة
        $remainingCollegeElectiveCredits = max(0, $electiveRequirements['college_elective'] - $completedCollegeElectiveCredits);
        $neededNewCollegeElectiveCredits = max(0, $remainingCollegeElectiveCredits - $failedCollegeElectiveCredits);

        $remainingUniversityElectiveCredits = max(0, $electiveRequirements['university_elective'] - $completedUniversityElectiveCredits);
        $neededNewUniversityElectiveCredits = max(0, $remainingUniversityElectiveCredits - $failedUniversityElectiveCredits);

        // الحصول على المواد الراسبة لكل فئة اختيارية
        $failedCollegeElectiveCourses = $student->courses()
            ->wherePivot('status', 'failed')
            ->whereHas('requirements', function ($q) use ($student) {
                $q->where('degree_id', $student->degree_id)
                  ->where('requirement_type','college_elective');
            })->get();

        $failedUniversityElectiveCourses = $student->courses()
            ->wherePivot('status', 'failed')
            ->whereHas('requirements', function ($q) use ($student) {
                $q->where('degree_id', $student->degree_id)
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
        $creditSum = 0;
        foreach ($openCollegeElectives as $course) {
            if ($creditSum >= $neededNewCollegeElectiveCredits) {
                break;
            }
            $selectedCollegeElectives->push($course);
            $creditSum += $course->credit_hours;
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

        // بناء قائمة المواد المؤهلة النهائية
        $restrictedEligibleCourses = collect();
        foreach ($eligibleCourses as $course) {
            $req = $course->requirements->where('degree_id', $student->degree_id)->first();
            // إذا لم تكن المادة اختيارية، نضيفها مباشرة
            if (!$req || !array_key_exists($req->requirement_type, $electiveRequirements)) {
                $restrictedEligibleCourses->push($course);
            }
        }
        $restrictedEligibleCourses = $restrictedEligibleCourses
            ->merge($failedCollegeElectiveCourses)
            ->merge($failedUniversityElectiveCourses)
            ->merge($selectedCollegeElectives)
            ->merge($selectedUniversityElectives);

        // تهيئة البيانات للوكيل الذكى
        $eligibleFormatted = $restrictedEligibleCourses->map(function ($course) use ($student) {
            $mainRequirement = $course->requirements->where('degree_id', $student->degree_id)->first();
            return [
                'id'              => $course->id,
                'course_number'   => $course->course_number,
                'course_name'     => $course->course_name,
                'credit_hours'    => $course->credit_hours,
                'avg_grade'       => $course->avg_grade,
                'difficulty'      => $course->difficulty,
                'requirement_type'=> $mainRequirement ? $mainRequirement->requirement_type : null,
            ];
        });

        // تحديد نوع الطلب حسب المعدل التراكمى
        $requestType = $student->gpa < 2.0 ? 'raise_gpa' : 'study_plan';

        // بناء الـ payload
        $payload = [
            'student_number'   => $student->student_number,
            'credit_hours'     => $maxHours,
            'request_type'     => $requestType,
            'gpa'              => $student->gpa,
            'passed_courses'   => $passedCourses->values()->toArray(),
            'failed_courses'   => $failedCourses->values()->toArray(),
            'eligible_courses' => $eligibleFormatted->values()->toArray(),
        ];

        // استدعاء الوكيل الذكى عبر n8n
        $webhookUrl = 'https://aitraining.jirventures.com/webhook/academic';
        try {
            $response = Http::withoutVerifying()->post($webhookUrl, $payload);
            $body = $response->json();
            if (isset($body['output'])) {
                $plan = json_decode($body['output'], true);
                return response()->json($plan, $response->status());
            }
            return response()->json($body, $response->status());
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to contact AI agent.'], 500);
        }
    }

    /**
     * التوصية باستخدام النموذج المدرب (ML) دون وكيل خارجى.
     */
    public function recommendML(Request $request)
    {
        // 1. التحقق من المدخلات
        $validated = $request->validate([
            'credit_hours' => 'required|integer|min:12|max:18',
        ]);
        $maxHours = $validated['credit_hours'];

        // الحصول على الطالب من التوكن
        $student = Auth::user()->student;
        if (!$student) {
            return response()->json(['message' => 'Student profile not found.'], 404);
        }



        $studentNumber = $student->student_number;

        // 2. جلب بيانات الطالب مع العلاقات
        $student = Student::with([
            'courses',
            'degree.courses.prerequisites',
            'degree.courses.requirements',
            'studyPlans.courses' // Add study plans with courses
        ])->where('student_number', $studentNumber)->firstOrFail();

        // 3. حساب الـ GPA
        $gpa = $student->gpa ?? $student->courses()->avg('student_courses.point');

        // 3.1. حساب ساعات الخطة الدراسية
        $planCreditHours = 0;
        if ($student->studyPlans->isNotEmpty()) {
            foreach ($student->studyPlans as $studyPlan) {
                $planCreditHours += $studyPlan->courses->sum('credit_hours');
            }
        }

        // 4. استخراج المواد التى درسها الطالب
        $completedCourses = $student->courses()->wherePivot('status', 'completed')->get();
        $inProgressIds    = $student->courses()->wherePivotIn('status', ['selected','in_progress'])->pluck('courses.id');
        $failedCourses    = $student->courses()->wherePivot('status','failed')->get();

        // مسار الطالب الجديد (لا يوجد سجل سابق)
        if ($completedCourses->isEmpty() && $failedCourses->isEmpty() && $inProgressIds->isEmpty()) {
            // جلب مواد بدون متطلبات مع استبعاد المواد بساعات صفرية
            $candidateCourses = $student->degree->courses->filter(function ($course) {
                return $course->prerequisites->isEmpty() && $course->credit_hours > 0;
            });

            // حساب عدد المقررات التى تعتمد على كل مادة
            $gatewayCounts = Prerequisite::select('prerequisite_id', DB::raw('COUNT(*) as cnt'))
                                ->groupBy('prerequisite_id')
                                ->pluck('cnt', 'prerequisite_id');

            // إعداد المرشحين مع درجة مركبة
            $candidates = [];
            foreach ($candidateCourses as $course) {
                $gatewayCount = $gatewayCounts[$course->id] ?? 0;
                $score = $course->avg_grade ?? 0;
                $score += $gatewayCount * 2;
                $penalty = match ($course->difficulty) {
                    'easy'   => 0,
                    'medium' => 5,
                    'hard'   => 10,
                    default  => 0,
                };
                $score -= $penalty;

                $candidates[] = [
                    'course'  => $course,
                    'score'   => $score,
                    'hours'   => $course->credit_hours,
                    'reqType' => optional($course->requirements->first())->requirement_type,
                ];
            }

            // ترتيب المرشحين
            usort($candidates, fn($a, $b) => $b['score'] <=> $a['score']);

            // اختيار المقررات ضمن السقف والاختياريات
            $selected = [];
            $totalHours = 0;
            $collegeElectiveHours = 0;
            $universityElectiveHours = 0;

            foreach ($candidates as $cand) {
                if ($totalHours + $cand['hours'] > $maxHours) continue;
                if ($cand['reqType'] === 'college_elective' && $collegeElectiveHours + $cand['hours'] > 10) continue;
                if ($cand['reqType'] === 'university_elective' && $universityElectiveHours + $cand['hours'] > 4) continue;

                $selected[] = $cand;
                $totalHours += $cand['hours'];
                if ($cand['reqType'] === 'college_elective')    $collegeElectiveHours    += $cand['hours'];
                if ($cand['reqType'] === 'university_elective') $universityElectiveHours += $cand['hours'];
                if ($totalHours >= $maxHours) break;
            }

            // إعادة النتائج
            $responseCourses = [];
            foreach ($selected as $item) {
                $c = $item['course'];
                $responseCourses[] = [
                    'course_number'    => $c->course_number,
                    'course_name'      => $c->course_name,
                    'credit_hours'     => $c->credit_hours,
                    'predicted_mark'   => round($c->avg_grade, 2),
                    'score'            => round($item['score'], 2),
                    'requirement_type' => $item['reqType'],
                    'difficulty'       => $c->difficulty,
                    'is_failed'        => false,
                    'is_conditional'   => false,
                ];
            }

            return response()->json([
                'student_number'       => $student->student_number,
                'requested_hours'      => $maxHours,
                'total_selected_hours' => $totalHours,
                'courses'              => $responseCourses,
                'gpa'                  => $gpa !== null ? round($gpa, 3) : null,
                'plan_credit_hours'    => $planCreditHours,
                'note' => 'New student: using average course performance as guide.',
            ]);
        }

        // 5. تحديد المواد المتبقية والمفتوحة مع استبعاد المواد بساعات صفرية
        $completedIds = $completedCourses->pluck('id');
        $remaining = $student->degree->courses->whereNotIn('id', $completedIds->merge($inProgressIds));
        $eligible = $remaining->filter(function ($course) use ($completedIds) {
            if ($course->credit_hours <= 0) {
                return false;
            }
            $prereqIds = $course->prerequisites->pluck('prerequisite_id');
            return $prereqIds->diff($completedIds)->isEmpty();
        });

        // 6. جلب التوقعات من جدول predicted_marks
        $predicted = PredictedMark::where('student_number', $student->student_number)
                         ->pluck('predicted_mark', 'course_number');

        // 7. تحضير المرشحين مع النقاط والحالات الإضافية
        $candidates = [];
        foreach ($eligible as $course) {
            $courseNumber = $course->course_number;
            $predMark     = $predicted[$courseNumber] ?? null;
            if ($predMark === null) {
                continue;
            }
            // تحضير الحالات الإضافية
            $failedRecord = $failedCourses->firstWhere('id', $course->id);
            $isFailed     = $failedRecord !== null;

            $conditionalPass = false;
            if ($record = $completedCourses->firstWhere('id', $course->id)) {
                $finalMark = $record->pivot->final_mark ?? null;
                if ($finalMark !== null && $finalMark >= 50 && $finalMark < 60) {
                    $conditionalPass = true;
                }
            }

            $gatewayCounts = Prerequisite::select('prerequisite_id', DB::raw('COUNT(*) as cnt'))
                                ->groupBy('prerequisite_id')
                                ->pluck('cnt', 'prerequisite_id');
            $gatewayCount = $gatewayCounts[$course->id] ?? 0;

            $score = $predMark;

            if ($isFailed) {
                $score += 10;
            }
            if ($conditionalPass && $gpa < 2.0) {
                $score += 8;
            }
            if ($gatewayCount > 0) {
                $score += $gatewayCount * 2;
            }

            $difficultyPenalty = match ($course->difficulty) {
                'easy'   => 0,
                'medium' => 5,
                'hard'   => 10,
                default  => 0,
            };
            if ($gpa < 2.0) {
                $score -= $difficultyPenalty;
            } elseif ($gpa > 3.0) {
                $score += $difficultyPenalty * 0.5;
            }

            $candidates[] = [
                'course'         => $course,
                'score'          => $score,
                'predicted_mark' => $predMark,
                'hours'          => $course->credit_hours,
                'requirement_type' => optional($course->requirements->first())->requirement_type,
                'is_failed'      => $isFailed,
                'is_conditional' => $conditionalPass,
            ];
        }

        // 8. ترتيب المرشحين حسب الدرجة
        usort($candidates, fn($a, $b) => $b['score'] <=> $a['score']);

        // 9. اختيار المواد ضمن القيود
        $selected = [];
        $totalHours = 0;
        $collegeElectiveHours    = 0;
        $universityElectiveHours = 0;

        // المواد الراسبة أولاً
        foreach ($candidates as $cand) {
            if ($cand['is_failed']) {
                if ($totalHours + $cand['hours'] > $maxHours) continue;
                if ($cand['requirement_type'] === 'college_elective' && $collegeElectiveHours + $cand['hours'] > 10) continue;
                if ($cand['requirement_type'] === 'university_elective' && $universityElectiveHours + $cand['hours'] > 4) continue;

                $selected[] = $cand;
                $totalHours += $cand['hours'];
                if ($cand['requirement_type'] === 'college_elective')    $collegeElectiveHours    += $cand['hours'];
                if ($cand['requirement_type'] === 'university_elective') $universityElectiveHours += $cand['hours'];
            }
        }

        // المواد المرفوعة شرطياً للطلاب منخفضى المعدل
        foreach ($candidates as $cand) {
            if (!$cand['is_failed'] && $cand['is_conditional'] && $gpa < 2.0) {
                if ($totalHours + $cand['hours'] > $maxHours) continue;
                if ($cand['requirement_type'] === 'college_elective' && $collegeElectiveHours + $cand['hours'] > 10) continue;
                if ($cand['requirement_type'] === 'university_elective' && $universityElectiveHours + $cand['hours'] > 4) continue;

                $selected[] = $cand;
                $totalHours += $cand['hours'];
                if ($cand['requirement_type'] === 'college_elective')    $collegeElectiveHours    += $cand['hours'];
                if ($cand['requirement_type'] === 'university_elective') $universityElectiveHours += $cand['hours'];
            }
        }

        // إضافة باقى المواد حسب الترتيب
        foreach ($candidates as $cand) {
            if ($cand['is_failed'] || ($cand['is_conditional'] && $gpa < 2.0)) {
                continue;
            }
            if ($totalHours + $cand['hours'] > $maxHours) continue;
            if ($cand['requirement_type'] === 'college_elective' && $collegeElectiveHours + $cand['hours'] > 10) continue;
            if ($cand['requirement_type'] === 'university_elective' && $universityElectiveHours + $cand['hours'] > 4) continue;

            $selected[] = $cand;
            $totalHours += $cand['hours'];
            if ($cand['requirement_type'] === 'college_elective')    $collegeElectiveHours    += $cand['hours'];
            if ($cand['requirement_type'] === 'university_elective') $universityElectiveHours += $cand['hours'];
            if ($totalHours >= $maxHours) break;
        }

        // 10. إعداد الرد النهائى
        $responseCourses = [];
        foreach ($selected as $item) {
            $c = $item['course'];
            $responseCourses[] = [
                'id'               => $c->id,
                'course_number'    => $c->course_number,
                'course_name'      => $c->course_name,
                'credit_hours'     => $c->credit_hours,
                'predicted_mark'   => round($item['predicted_mark'], 2),
                'score'            => round($item['score'], 2),
                'requirement_type' => $item['requirement_type'],
                'difficulty'       => $c->difficulty,
                'is_failed'        => $item['is_failed'],
                'is_conditional'   => $item['is_conditional'],
            ];
        }

        return response()->json([
            'student_number'      => $student->student_number,
            'plan_credit_hours'   => $planCreditHours,
            'requested_hours'     => $maxHours,
            'total_selected_hours'=> $totalHours,
            'courses'             => $responseCourses,
            'gpa'                 => round($gpa, 3),
        ]);
    }
}
