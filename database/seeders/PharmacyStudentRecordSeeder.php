<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Models\Student;
use App\Models\Course;
use App\Models\Degree;
use App\Models\Semester;

class PharmacyStudentRecordSeeder extends Seeder
{
    public function run(): void
    {
        Log::info('--- PharmacyStudentRecordSeeder is starting ---');
        
        $pharmacyDegree = Degree::find(2);
        if (!$pharmacyDegree) {
            Log::error('Pharmacy Degree with ID 2 not found. Aborting Seeder.');
            return;
        }

        $csvFile = fopen(database_path('data/pharmacy_student_records.csv'), 'r');
        fgetcsv($csvFile);
        
        $recordLimit = 100;
        $recordCounter = 0;

        $processedStudentNumbers = []; // مصفوفة لتخزين أرقام الطلاب الذين تمت معالجتهم

        while (($data = fgetcsv($csvFile, 2000, ',')) !== false) {
            
            $studentNumber = trim($data[1]);
            if (isset($data[2]) && strpos(trim($data[2]), '2024') === 0) {
                if ($recordCounter >= $recordLimit) {
                    Log::info("Reached the limit of {$recordLimit} records. Stopping the loop.");
                    break; // break تعني: اخرج من حلقة while فوراً
                }
                // ... (بقية كود استخلاص البيانات)
                $partId = trim($data[2]);
                $courseCode = trim($data[6]);
                $finalMark = (int)trim($data[7]);
                $grade = trim($data[8]);
                $resultCode = trim($data[9]);
                $point = (float)trim($data[10]);

                if(empty($data[7])) continue;

                $user = User::where('student_number', $studentNumber)->first();
                if (!$user) {
                    $user = User::create([
                        'name' => 'Student ' . $studentNumber,
                        'email' => $studentNumber . '@university.com',
                        'password' => Hash::make('password'),
                        'student_number' => $studentNumber,
                        'role' => 'student',
                    ]);
                }

                $student = Student::where('user_id', $user->id)->first();
                if (!$student) {
                    $student = Student::create([
                        'user_id' => $user->id,
                        'student_name' => 'Student ' . $studentNumber,
                        'student_number' => $studentNumber,
                        'degree_id' => $pharmacyDegree->id,
                        'gpa' => 0.0,
                    ]);
                }

                if (!in_array($student->student_number, $processedStudentNumbers)) {
                    $processedStudentNumbers[] = $student->student_number;
                }

                $course = Course::where('course_number', $courseCode)->first();
                $semester = Semester::firstOrCreate(['Year' => substr($partId, 0, 4), 'SemesterName' => substr($partId, 4, 1)]);
                $status = ($point > 0.0 && $resultCode === 'P') ? 'completed' : 'failed';

                if ($student && $course && $semester) {
                    // نستخدم updateOrInsert لتجنب أخطاء تكرار المفتاح الأساسي
                    DB::table('student_courses')->updateOrInsert(
                        ['student_id' => $student->id, 'course_id' => $course->id],
                        [
                            'semester_id' => $semester->id,
                            'status' => $status,
                            'final_mark' => $finalMark,
                            'grade' => $grade,
                            'result_code' => $resultCode,
                            'point' => $point,
                            'created_at'  => now(),
                            'updated_at' => now(),
                        ]
                    );
                }
                $recordCounter++;

            }
        }
        fclose($csvFile);
        Log::info('--- Finished inserting student records. Now calculating GPAs... ---');
        
        // ===================================================================
        // الجزء الجديد: حساب وتحديث المعدل التراكمي لكل طالب باستخدام استعلام مباشر
        // ===================================================================
        foreach ($processedStudentNumbers as $studentNumber) {
            $student = Student::where('student_number', $studentNumber)->first();
            if (!$student) continue;

            $result = DB::table('student_courses')
                ->join('courses', 'student_courses.course_id', '=', 'courses.id')
                ->where('student_courses.student_id', $student->id)
                ->where('student_courses.status', 'completed')
                ->select(
                    DB::raw('SUM(student_courses.point * courses.credit_hours) as total_points'),
                    DB::raw('SUM(courses.credit_hours) as total_hours')
                )
                ->first();
            
            if ($result->total_hours > 0) {
                $gpa = round($result->total_points / $result->total_hours, 2);
                $student->gpa = $gpa;
                $student->save();
                Log::info("Updated GPA for student {$studentNumber} to {$gpa}");
            } else {
                $student->gpa = 0.0;
                $student->save();
            }
        }
        
        Log::info('--- PharmacyStudentRecordSeeder has finished updating GPAs ---');
    }
}