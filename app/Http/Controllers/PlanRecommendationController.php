<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

use App\Models\Student;
use App\Models\Course;
use App\Models\PredictedMark;
use App\Models\Prerequisite;
use Illuminate\Support\Facades\DB;

class PlanRecommendationController extends Controller
{
    public function recommendAI(Request $request)
    {
        // التحقق من المدخلات: نحصل على رقم الطالب وعدد الساعات فقط
        $validated = $request->validate([
            'student_number' => 'required|exists:students,student_number',
            'credit_hours' => 'required|integer|min:12|max:18',
        ]);

        // التأكد من أن الطالب يخص المستخدم الحالى (اختيارى)
        $currentStudent = Auth::user()->student;
        if ($currentStudent->student_number !== $validated['student_number']) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        

        // جلب بيانات الطالب مع العلاقات اللازمة
        $student = Student::where('student_number', $validated['student_number'])
        ->with([
            'courses.prerequisites.prerequisite',
            'degree.courses.prerequisites',
            'degree.courses.requirements',
        ])
        ->firstOrFail();
    

        // المواد الناجحة مع العلامات، وإرسال avg_grade وdifficulty
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

        // المواد الراسبة مع العلامات، وإرسال avg_grade وdifficulty
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

        // تحديد المواد المفتوحة: استبعد المواد المكتملة والتى فى الخطة أو قيد الدراسة
        $completedCourseIds = $student->courses()
            ->wherePivot('status', 'completed')
            ->pluck('courses.id');

        $planCourseIds = $student->courses()
            ->wherePivotIn('status', ['selected', 'in_progress'])
            ->pluck('courses.id');

        $allDegreeCourses = $student->degree->courses()
            ->with(['prerequisites.prerequisite', 'requirements'])
            ->get();

        $remainingCourses = $allDegreeCourses->whereNotIn('id', $completedCourseIds->merge($planCourseIds));

        // تصفية المواد المؤهلة (لا متطلبات أو متطلباتها مكتملة)
        $eligibleCourses = $remainingCourses->filter(function ($course) use ($completedCourseIds) {
            $prerequisiteIds = $course->prerequisites->pluck('prerequisite_id');
            return $prerequisiteIds->diff($completedCourseIds)->isEmpty();
        });

        /**
         * حصر المواد الاختيارية: الكلية الاختيارية 10 ساعات، الجامعة الاختيارية 4 ساعات.
         * نحسب الساعات التى أنجزها الطالب فى كل فئة، ثم نضيف المواد الراسبة، ثم نكمل بالمواد المفتوحة المناسبة حتى نصل للحد المطلوب.
         */
        $electiveRequirements = [
            'college_elective'    => 10, // عدد الساعات الاختيارية المطلوبة من الكلية
            'university_elective' => 4,  // عدد الساعات الاختيارية المطلوبة من الجامعة
        ];

        // حساب الساعات المكتملة لكل فئة اختيارية
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

        // حساب الساعات الراسبة فى كل فئة (يجب إعادة هذه المواد)
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

        // حساب الساعات المتبقية التى يجب تغطيتها بالمواد المفتوحة (بعد إضافة المواد الراسبة)
        $remainingCollegeElectiveCredits = max(
            0,
            $electiveRequirements['college_elective'] - $completedCollegeElectiveCredits
        );
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

        // المواد الراسبة من كل فئة اختيارية
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

        // اختيار عدد محدود من المواد المفتوحة لكل فئة اختيارية
        // مواد اختيارية الكلية المفتوحة
        $openCollegeElectives = $eligibleCourses->filter(function ($course) use ($student) {
            $req = $course->requirements->where('degree_id', $student->degree_id)->first();
            return $req && $req->requirement_type === 'college_elective';
        });

        // مواد اختيارية الجامعة المفتوحة
        $openUniversityElectives = $eligibleCourses->filter(function ($course) use ($student) {
            $req = $course->requirements->where('degree_id', $student->degree_id)->first();
            return $req && $req->requirement_type === 'university_elective';
        });

        // اختيار مواد الكلية الاختيارية بما لا يتجاوز الحاجة
        $selectedCollegeElectives = collect();
        $creditSum = 0;
        foreach ($openCollegeElectives as $course) {
            if ($creditSum >= $neededNewCollegeElectiveCredits) {
                break;
            }
            $selectedCollegeElectives->push($course);
            $creditSum += $course->credit_hours;
        }

        // اختيار مواد الجامعة الاختيارية بما لا يتجاوز الحاجة
        $selectedUniversityElectives = collect();
        $creditSumUni = 0;
        foreach ($openUniversityElectives as $course) {
            if ($creditSumUni >= $neededNewUniversityElectiveCredits) {
                break;
            }
            $selectedUniversityElectives->push($course);
            $creditSumUni += $course->credit_hours;
        }

        // بناء قائمة المواد المؤهلة النهائية: المواد غير الاختيارية + المواد الراسبة + المواد المفتوحة المختارة
        $restrictedEligibleCourses = collect();

        foreach ($eligibleCourses as $course) {
            $req = $course->requirements->where('degree_id', $student->degree_id)->first();
            // إذا لم تكن المادة اختيارية (كلية أو جامعة)، نضيفها مباشرة
            if (!$req || !in_array($req->requirement_type, array_keys($electiveRequirements))) {
                $restrictedEligibleCourses->push($course);
            }
        }

        $restrictedEligibleCourses = $restrictedEligibleCourses
            ->merge($failedCollegeElectiveCourses)
            ->merge($failedUniversityElectiveCourses)
            ->merge($selectedCollegeElectives)
            ->merge($selectedUniversityElectives);

        // تهيئة المواد المؤهلة لإرسالها للوكيل الذكى، مع avg_grade وdifficulty
        $eligibleFormatted = $restrictedEligibleCourses->map(function ($course) use ($student) {
            $mainRequirement = $course->requirements
                ->where('degree_id', $student->degree_id)
                ->first();
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

        // تحديد نوع الطلب حسب المعدل التراكمى: إذا أقل من 2.0 فهدفه رفع المعدل، وإلا خطة عادية
        $requestType = $student->gpa < 2.0 ? 'raise_gpa' : 'study_plan';

        // بناء الـpayload للوكيل الذكى
        $payload = [
            'student_number'   => $student->student_number,
            'credit_hours'     => $validated['credit_hours'],
            'request_type'     => $requestType,
            'gpa'              => $student->gpa,
            'passed_courses'   => $passedCourses->values()->toArray(),
            'failed_courses'   => $failedCourses->values()->toArray(),
            'eligible_courses' => $eligibleFormatted->values()->toArray(),
        ];

        // استدعاء n8n عبر الـWebhook (استبدل هذا العنوان بالعنوان الصحيح لديك)
        $webhookUrl = 'https://aitraining.jirventures.com/webhook-test/academic';

        try {
            $response = Http::withoutVerifying()->post($webhookUrl, $payload);
            $body = $response->json();
            if (isset($body['output'])) {
                $plan = json_decode($body['output'], true);
                return response()->json($plan, $response->status());
            }
            return response()->json($body, $response->status());
        } 
        catch (\Exception $e) {
            return response()->json(['message' => 'Failed to contact AI agent.'], 500);
        }
        
    }









    public function recommendML(Request $request)
    {
        // 1. التحقق من المدخلات
        $validated = $request->validate([
            'credit_hours' => 'required|integer|min:12|max:18',
        ]);
        $maxHours = $validated['credit_hours'];
        
        $user = Auth::user();
        $student = $user->student; // تأكد أن لديك علاقة student() فى موديل User
        
        // إذا كنت بحاجة للرقم الجامعى
        $studentNumber = $student->student_number;

        // 2. جلب بيانات الطالب وعلاقاته
        $student = Student::with(['courses', 'degree.courses.prerequisites', 'degree.courses.requirements'])
                          ->where('student_number', $studentNumber)
                          ->firstOrFail();

        // حساب الـ GPA للطالب (استخدم gpa من الجدول إن وجد، أو متوسط النقاط)
        $gpa = $student->gpa ?? $student->courses()->avg('student_courses.point');

        // 3. استخراج المواد التى درسها الطالب (مكتملة، قيد التنفيذ، راسبة)
        $completedCourses = $student->courses()->wherePivot('status', 'completed')->get();
        $inProgressIds    = $student->courses()->wherePivotIn('status', ['selected','in_progress'])->pluck('courses.id');
        $failedCourses    = $student->courses()->wherePivot('status','failed')->get();



            // مسار الطالب الجديد: لا يوجد أى سجل سابق
        if ($completedCourses->isEmpty() && $failedCourses->isEmpty() && $inProgressIds->isEmpty()) {
            // جلب مواد الفصل الأول/المواد بدون متطلبات
            $candidateCourses = $student->degree->courses->filter(function ($course) {
                return $course->prerequisites->isEmpty();
            });

            // حساب عدد المقررات التى تعتمد على كل مادة (Gateway count)
            $gatewayCounts = Prerequisite::select('prerequisite_id', DB::raw('COUNT(*) as cnt'))
                                ->groupBy('prerequisite_id')
                                ->pluck('cnt', 'prerequisite_id');

            // إعداد المرشحين مع درجة مركبة تعتمد على avg_grade و gateway و الصعوبة
            $candidates = [];
            foreach ($candidateCourses as $course) {
                $gatewayCount = $gatewayCounts[$course->id] ?? 0;

                // نبدأ بالعلامة المتوسطة للمادة (كلما كانت أعلى فالمادة أسهل)
                $score = $course->avg_grade ?? 0;

                // مكافأة المواد التى تفتح مقررات كثيرة
                $score += $gatewayCount * 2;

                // خصم للصعوبات: المواد السهلة لا خصم، المتوسطة –5، الصعبة –10
                $difficulty = $course->difficulty;
                $penalty = match ($difficulty) {
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

            // ترتيب تنازلى بالدرجة
            usort($candidates, fn($a, $b) => $b['score'] <=> $a['score']);

            // اختيار المقررات ضمن سقف الساعات والاختياريات
            $selected = [];
            $totalHours = 0;
            $collegeElectiveHours    = 0;
            $universityElectiveHours = 0;

            foreach ($candidates as $cand) {
                // التحقق من الحد الإجمالى
                if ($totalHours + $cand['hours'] > $maxHours) continue;

                // التحقق من حدود الاختياريات
                if ($cand['reqType'] === 'college_elective' && $collegeElectiveHours + $cand['hours'] > 10) continue;
                if ($cand['reqType'] === 'university_elective' && $universityElectiveHours + $cand['hours'] > 4) continue;

                $selected[] = $cand;
                $totalHours += $cand['hours'];
                if ($cand['reqType'] === 'college_elective')    $collegeElectiveHours    += $cand['hours'];
                if ($cand['reqType'] === 'university_elective') $universityElectiveHours += $cand['hours'];

                if ($totalHours >= $maxHours) break;
            }

            // إعادة النتائج بشكل مماثل لباقى المنطق
            $responseCourses = [];
            foreach ($selected as $item) {
                $c = $item['course'];
                $responseCourses[] = [
                    'course_number'    => $c->course_number,
                    'course_name'      => $c->course_name,
                    'credit_hours'     => $c->credit_hours,
                    'predicted_mark'   => round($c->avg_grade, 2), // باستخدام متوسط العلامة كمؤشر
                    'score'            => round($item['score'], 2),
                    'requirement_type' => $item['reqType'],
                    'difficulty'       => $c->difficulty,
                    'is_failed'        => false,
                    'is_conditional'   => false,
                ];
            }

            return response()->json([
                'student_number'        => $student->student_number,
                'requested_hours'       => $maxHours,
                'total_selected_hours'  => $totalHours,
                'courses'               => $responseCourses,
                'gpa'                   => $gpa !== null ? round($gpa, 3) : null,
                'note' => 'New student: using average course performance as guide.',
            ]);
        }

        
        // 4. تحديد المواد المتبقية (لم تدرس بعد) والمفتوحة (استيفاء المتطلبات)
        $completedIds = $completedCourses->pluck('id');
        $remaining    = $student->degree->courses
                            ->whereNotIn('id', $completedIds->merge($inProgressIds));
        // فلترة بحسب المتطلبات
        $eligible = $remaining->filter(function ($course) use ($completedIds) {
            $prereqIds = $course->prerequisites->pluck('prerequisite_id');
            return $prereqIds->diff($completedIds)->isEmpty();
        });

        // 5. جلب التوقعات من جدول predicted_marks
        $predicted = PredictedMark::where('student_number', $studentNumber)
                        ->pluck('predicted_mark', 'course_number');

        // 6. حساب عدد المواد التى تعتمد على كل مادة (Gateway count)
        $gatewayCounts = Prerequisite::select('prerequisite_id', DB::raw('COUNT(*) as cnt'))
                            ->groupBy('prerequisite_id')
                            ->pluck('cnt', 'prerequisite_id');

        // 7. إعداد قائمة المرشحين مع النقاط والحالات الإضافية
        $candidates = [];
        foreach ($eligible as $course) {
            $courseNumber = $course->course_number;
            $predMark     = $predicted[$courseNumber] ?? null;
            if ($predMark === null) {
                // إذا لم تكن هناك علامة متوقعة، يمكن تجاهل المادة أو إعطاءها قيمة افتراضية
                continue;
            }

            // معرفة إذا كان الطالب رسب مسبقًا فى هذه المادة
            $failedRecord = $failedCourses->firstWhere('id', $course->id);
            $isFailed     = $failedRecord !== null;

            // معرفة إذا كانت العلامة السابقة فى هذا المقرر بين 50 و60 (رفع شرطى)
            $conditionalPass = false;
            if ($record = $completedCourses->firstWhere('id', $course->id)) {
                $finalMark = $record->pivot->final_mark ?? null;
                if ($finalMark !== null && $finalMark >= 50 && $finalMark < 60) {
                    $conditionalPass = true;
                }
            }

            // تحديد عدد المواد التى تعتمد على هذه المادة
            $gatewayCount = $gatewayCounts[$course->id] ?? 0;

            // إعداد متغيرات الصعوبة
            $difficulty = $course->difficulty; // easy, medium, hard

            // حساب الدرجة الأولية
            $score = $predMark;

            // مكافأة للمادة الراسبة
            if ($isFailed) {
                $score += 10;
            }

            // مكافأة للمادة المرفوعة شرطياً إذا كان معدل الطالب أقل من 2 (يحتاج رفع معدل)
            if ($conditionalPass && $gpa < 2.0) {
                $score += 8;
            }

            // مكافأة للمادة التى تفتح مقررات عديدة
            if ($gatewayCount > 0) {
                $score += $gatewayCount * 2;  // كل مقرر إضافى يفتح = +2
            }

            // تعديل حسب الصعوبة ومعدل الطالب
            $difficultyPenalty = match ($difficulty) {
                'easy'   => 0,
                'medium' => 5,
                'hard'   => 10,
                default  => 0,
            };

            if ($gpa < 2.0) {
                // الطالب منخفض المعدل يتجنب المواد الصعبة
                $score -= $difficultyPenalty;
            } elseif ($gpa > 3.0) {
                // الطالب مرتفع المعدل يمكنه تحمّل الأصعب (نضاعف المكافأة قليلاً)
                $score += $difficultyPenalty * 0.5;
            }
            // خلاف ذلك لا تغيير للمعدل المتوسط

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

        // 8. ترتيب المرشحين حسب الدرجة Desc
        usort($candidates, fn($a, $b) => $b['score'] <=> $a['score']);

        // 9. اختيار المواد ضمن السقف والقيود
        $selected = [];
        $totalHours = 0;
        $collegeElectiveHours    = 0;
        $universityElectiveHours = 0;

        // أولاً: إضافة المواد الراسبة (إذا الدرجات المتوقعة ليست منخفضة جدًا)
        foreach ($candidates as $cand) {
            if ($cand['is_failed']) {
                $c = $cand['course'];
                // تحقق من الساعات
                if ($totalHours + $cand['hours'] > $maxHours) continue;
                // تحقق من حدود الاختياريات
                if ($cand['requirement_type'] === 'college_elective' && $collegeElectiveHours + $cand['hours'] > 10) continue;
                if ($cand['requirement_type'] === 'university_elective' && $universityElectiveHours + $cand['hours'] > 4) continue;

                $selected[] = $cand;
                $totalHours += $cand['hours'];
                if ($cand['requirement_type'] === 'college_elective')    $collegeElectiveHours    += $cand['hours'];
                if ($cand['requirement_type'] === 'university_elective') $universityElectiveHours += $cand['hours'];
            }
        }

        // ثانياً: إضافة المواد المرفوعة شرطياً عند الحاجة
        foreach ($candidates as $cand) {
            if (!$cand['is_failed'] && $cand['is_conditional'] && $gpa < 2.0) {
                $c = $cand['course'];
                if ($totalHours + $cand['hours'] > $maxHours) continue;
                if ($cand['requirement_type'] === 'college_elective' && $collegeElectiveHours + $cand['hours'] > 10) continue;
                if ($cand['requirement_type'] === 'university_elective' && $universityElectiveHours + $cand['hours'] > 4) continue;

                $selected[] = $cand;
                $totalHours += $cand['hours'];
                if ($cand['requirement_type'] === 'college_elective')    $collegeElectiveHours    += $cand['hours'];
                if ($cand['requirement_type'] === 'university_elective') $universityElectiveHours += $cand['hours'];
            }
        }

        // ثالثاً: إضافة باقى المواد حسب الترتيب حتى الوصول إلى سقف الساعات
        foreach ($candidates as $cand) {
            if ($cand['is_failed'] || ($cand['is_conditional'] && $gpa < 2.0)) {
                continue; // تم إضافتها مسبقًا
            }
            $c = $cand['course'];
            if ($totalHours + $cand['hours'] > $maxHours) continue;
            if ($cand['requirement_type'] === 'college_elective' && $collegeElectiveHours + $cand['hours'] > 10) continue;
            if ($cand['requirement_type'] === 'university_elective' && $universityElectiveHours + $cand['hours'] > 4) continue;

            $selected[] = $cand;
            $totalHours += $cand['hours'];
            if ($cand['requirement_type'] === 'college_elective')    $collegeElectiveHours    += $cand['hours'];
            if ($cand['requirement_type'] === 'university_elective') $universityElectiveHours += $cand['hours'];

            if ($totalHours >= $maxHours) break;
        }

        // 10. هيكلة النتائج النهائية
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
            'student_number'     => $studentNumber,
            'requested_hours'    => $maxHours,
            'total_selected_hours' => $totalHours,
            'courses'            => $responseCourses,
            'gpa'                => round($gpa, 3),
        ]);
    }
}
