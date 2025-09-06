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
        Log::info('--- PharmacyStudentRecordSeeder START ---');

        $degree = Degree::find(2);
        if (!$degree) {
            Log::error('Degree with ID 2 not found. Abort.');
            return;
        }

        $path   = database_path('data/pharmacy_student_records.csv');
        $handle = fopen($path, 'r');

        // تجاهل صفّ العناوين
        fgetcsv($handle);

        $now = now();

        // سنجمع البيانات هنا
        $userInserts    = [];  // بيانات المستخدمين الجدد
        $studentNumbers = [];  // أرقام الطلاب التى رأيناها
        $records        = [];  // سجلات student_courses المجمعة
        $semesterCache  = [];  // كاش لمعرفة معرّف الفصل
        $courseIds      = Course::pluck('id', 'course_number'); // map code => id
        
        $batchSize = 1000; // Process in batches
        $rowCount = 0;

        while (($row = fgetcsv($handle, 0, ',')) !== false) {
            $rowCount++;
            
            // Log progress every 10000 rows
            if ($rowCount % 10000 === 0) {
                Log::info("Processed {$rowCount} rows from pharmacy student records CSV");
            }
            
            $studentNumber = trim($row[1]);
            $termCode      = trim($row[2]); // شكلها 20241 أو 20242
            $courseCode    = trim($row[6]);
            $finalMark     = trim($row[7]) === '' ? null : (int)$row[7];
            $gradeLetter   = trim($row[8]);
            $resultCode    = trim($row[9]);
            $point         = trim($row[10]) === '' ? null : (float)$row[10];

            // نتابع فقط السنوات 2018-2024 والصفوف ذات علامة نهائية
            if (!$finalMark || !preg_match('/^(201[8-9]|202[0-4])\d$/', $termCode)) {
                continue;
            }

            // جهّز المستخدم والطالب إن لم يكن موجودًا
            if (!isset($studentNumbers[$studentNumber])) {
                $userInserts[] = [
                    'name'           => 'Student '.$studentNumber,
                    'email'          => $studentNumber.'@university.com',
                    'password'       => Hash::make('password'),
                    'student_number' => $studentNumber,
                    'role'           => 'student',
                ];
                $studentNumbers[$studentNumber] = true;
            }

            // استخراج السنة ورقم الفصل (1=Fall,2=Spring,3=Summer)
            $year  = substr($termCode, 0, 4);
            $semNo = substr($termCode, -1);
            $semName = match((int)$semNo) {
                1 => 'Fall',
                2 => 'Spring',
                3 => 'Summer',
                default => 'Fall',
            };
            $semKey = $year.'-'.$semName;

            // احصل على معرّف الفصل من الكاش أو أنشئه
            if (!isset($semesterCache[$semKey])) {
                $semester = Semester::firstOrCreate(['Year' => $year, 'SemesterName' => $semName]);
                $semesterCache[$semKey] = $semester->id;
            }
            $semesterId = $semesterCache[$semKey];

            // معرّف المقرر
            $courseId = $courseIds[$courseCode] ?? null;
            if (!$courseId) {
                continue; // إذا لم يكن المقرر موجودًا لا تتابع
            }

            // تحديد حالة الطالب فى المقرر
            $status = ($point !== null && $resultCode === 'P') ? 'completed' : 'failed';

            $records[] = [
                'student_number' => $studentNumber,
                'course_id'      => $courseId,
                'semester_id'    => $semesterId,
                'status'         => $status,
                'final_mark'     => $finalMark,
                'grade'          => $gradeLetter,
                'result_code'    => $resultCode,
                'point'          => $point,
                'created_at'     => $now,
                'updated_at'     => $now,
            ];
        }
        fclose($handle);

        // 1) إدراج أو تحديث المستخدمين
        User::upsert($userInserts, ['student_number'], ['name','email','password','role']);

        // 2) إدراج الطلاب الجدد
        $existingStudents = Student::whereIn('student_number', array_keys($studentNumbers))
            ->pluck('id','student_number');
        $studentInserts = [];
        foreach ($studentNumbers as $sn => $dummy) {
            if (!isset($existingStudents[$sn])) {
                $userId = User::where('student_number', $sn)->value('id');
                $studentInserts[] = [
                    'user_id'        => $userId,
                    'student_name'   => 'Student '.$sn,
                    'student_number' => $sn,
                    'degree_id'      => $degree->id,
                    'gpa'            => 0.0,
                ];
            }
        }
        Student::upsert($studentInserts, ['student_number'], ['user_id','degree_id']);

        // 3) خريطة student_number => student_id
        $studentIds = Student::pluck('id','student_number');

        // 4) تحضير سجلات student_courses مع student_id بدلاً من student_number
        $courseUpserts = [];
        foreach ($records as $rec) {
            $sid = $studentIds[$rec['student_number']] ?? null;
            if (!$sid) continue;
            $courseUpserts[] = [
                'student_id'   => $sid,
                'course_id'    => $rec['course_id'],
                'semester_id'  => $rec['semester_id'],
                'status'       => $rec['status'],
                'final_mark'   => $rec['final_mark'],
                'grade'        => $rec['grade'],
                'result_code'  => $rec['result_code'],
                'point'        => $rec['point'],
                'created_at'   => $rec['created_at'],
                'updated_at'   => $rec['updated_at'],
            ];
        }

        // 5) إدراج أو تحديث السجلات فى student_courses فى دفعات (chunks)
        Log::info('Inserting ' . count($courseUpserts) . ' student course records in batches...');
        $insertedCount = 0;
        foreach (array_chunk($courseUpserts, 1000) as $chunk) {
            try {
                DB::table('student_courses')->upsert(
                    $chunk,
                    ['student_id','course_id'], // المفاتيح الفريدة
                    ['semester_id','status','final_mark','grade','result_code','point','updated_at']
                );
                $insertedCount += count($chunk);
                Log::info("Inserted {$insertedCount} student course records");
            } catch (\Exception $e) {
                Log::error("Error inserting student course batch: " . $e->getMessage());
                throw $e;
            }
        }

        // 6) حساب وتحديث كل معدلات الطلاب فى خطوة واحدة
        // الصفر الافتراضى
        DB::table('students')->update(['gpa' => 0]);

        // استعلام فرعى لحساب مجموع النقاط والساعات للمواد المكتملة
        $gpaData = DB::table('student_courses')
            ->join('courses','courses.id','=','student_courses.course_id')
            ->where('student_courses.status','completed')
            ->select(
                'student_courses.student_id',
                DB::raw('SUM(student_courses.point * courses.credit_hours) as total_points'),
                DB::raw('SUM(courses.credit_hours) as total_hours')
            )
            ->groupBy('student_courses.student_id')
            ->havingRaw('SUM(courses.credit_hours) > 0') // Only include students with completed courses
            ->get();

        // تحديث المعدلات باستخدام PHP بدلاً من SQL المعقد
        foreach ($gpaData as $gpaRecord) {
            if ($gpaRecord->total_hours > 0) {
                $gpa = round($gpaRecord->total_points / $gpaRecord->total_hours, 2);
                DB::table('students')
                    ->where('id', $gpaRecord->student_id)
                    ->update(['gpa' => $gpa]);
            }
        }

        // Clean up memory
        gc_collect_cycles();
        
        Log::info('--- PharmacyStudentRecordSeeder FINISHED successfully ---');
    }
}
