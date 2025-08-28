<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Student;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StudentCourseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. ابحث عن الطالب الافتراضي الذي سنضيف له السجل
        $student = Student::find(1); // نفترض أن الطالب له id = 1

        // 2. ابحث عن بعض المواد التي نريد تسجيلها له
        $biology1 = Course::where('course_number', 'PHBM 149')->first();
        $math = Course::where('course_number', 'PHR 104')->first();
        $physics = Course::where('course_number', 'PHR 206')->first();
        $biology2 = Course::where('course_number', 'PHBM 250')->first(); // مادة لها متطلب

        // إذا لم يتم العثور على الطالب أو المواد، لا تفعل شيئاً
        if (!$student || !$biology1 || !$math || !$physics || !$biology2) {
            return;
        }

        // 3. إضافة المواد المنجزة إلى سجل الطالب
        DB::table('student_courses')->insert([
            [
                'student_id' => $student->id,
                'course_id' => $biology1->id,
                'semester_id' => 1, // رقم فصل افتراضي
                'grade' => 'A+',
                'point' => 4.0, 
                'status' => 'completed', // <-- ناجح
                'created_at' => now(), 'updated_at' => now()
            ],
            [
                'student_id' => $student->id,
                'course_id' => $math->id,
                'semester_id' => 1,
                'grade' => 'B',
                'point' => 3.0,
                'status' => 'completed', // <-- ناجح
                'created_at' => now(), 'updated_at' => now()
            ],
            [
                'student_id' => $student->id,
                'course_id' => $physics->id,
                'semester_id' => 1,
                'grade' => 'F',
                'point' => 0.0,
                'status' => 'failed', // <-- راسب
                'created_at' => now(), 'updated_at' => now()
            ],
        ]);
    }
}