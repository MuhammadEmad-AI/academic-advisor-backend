<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Models\Student;
use App\Models\Course;
use App\Models\Degree;
use App\Models\Semester;

class DataImportController extends Controller
{
    /**
     * هذه الدالة ستقوم بقراءة ملف CSV وإدخال سجلات الطلاب الأكاديمية
     */
    public function importPharmacyStudentRecords()
    {
        // استخدام try-catch للتعامل مع أي أخطاء قد تحدث
        try {
            Log::info('--- API Data Import: START ---');

            // لنفترض أن كل هؤلاء الطلاب يتبعون شهادة الصيدلة (ID=2)
            $pharmacyDegree = Degree::find(2);
            if (!$pharmacyDegree) {
                return response()->json(['message' => 'Pharmacy Degree with ID 2 not found.'], 500);
            }

            $csvFile = fopen(database_path('data/pharmacy_student_records.csv'), 'r');
            fgetcsv($csvFile); // تجاهل الهيدر

            $recordsProcessed = 0; // عداد لحصر عدد السجلات

            while (($data = fgetcsv($csvFile, 2000, ',')) !== false) {
                
                // 1. استخلاص البيانات من الأعمدة الصحيحة
                $studentNumber = trim($data[1]);
                $partId = trim($data[2]);
                $courseCode = trim($data[6]);
                $finalMark = (int)trim($data[7]);
                $grade = trim($data[8]);
                $resultCode = trim($data[9]);
                $point = (float)trim($data[10]);

                if (empty($studentNumber) || empty($courseCode)) continue;

                // 2. البحث عن الطالب أو إنشاؤه
                $student = Student::where('student_number', $studentNumber)->first();
                if (!$student) {
                    $user = User::create([
                        'name'     => 'Student ' . $studentNumber,
                        'email'    => $studentNumber . '@university.com',
                        'password' => Hash::make('password'), 'role' => 'student',
                    ]);
                    $student = Student::create([
                        'user_id' => $user->id,
                        'student_name' => 'Student ' . $studentNumber,
                        'student_number' => $studentNumber,
                        'degree_id' => $pharmacyDegree->id,
                    ]);
                }

                // 3. البحث عن المادة والفصل
                $course = Course::where('course_number', $courseCode)->first();
                $semester = Semester::firstOrCreate(['Year' => substr($partId, 0, 4), 'SemesterName' => substr($partId, 4, 1)]);

                // 4. تحديد حالة المادة (ناجح/راسب)
                $status = ($point > 0.0 && $resultCode === 'P') ? 'completed' : 'failed';

                // 5. إضافة السجل الأكاديميكي
                if ($student && $course) {
                     DB::table('student_courses')->updateOrInsert(
                        [ // الشروط للبحث عن سجل موجود
                            'student_id' => $student->id,
                            'course_id' => $course->id,
                        ],
                        [ // البيانات التي سيتم إضافتها أو تحديثها
                            'semester_id' => $semester->id,
                            'status'      => $status,
                            'final_mark'  => $finalMark,
                            'grade'       => $grade,
                            'result_code' => $resultCode,
                            'point'       => $point,
                            'created_at'  => now(),
                            'updated_at'  => now()
                        ]
                     );
                     $recordsProcessed++;
                }
            }
            fclose($csvFile);
            Log::info("--- API Data Import: FINISHED. Processed {$recordsProcessed} records. ---");

            // إرجاع رسالة نجاح
            return response()->json([
                'message' => 'Student records imported successfully!',
                'records_processed' => $recordsProcessed
            ]);

        } catch (\Exception $e) {
            Log::error('API Data Import FAILED: ' . $e->getMessage());
            // في حال حدوث أي خطأ، نرجع رسالة خطأ واضحة
            return response()->json(['message' => 'An error occurred during import.', 'error' => $e->getMessage()], 500);
        }
    }
}