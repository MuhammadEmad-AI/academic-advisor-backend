<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('student_courses', function (Blueprint $table) {
            // فهرس مركّب لمنع تكرار نفس الطالب ونفس المادة
            $table->unique(['student_id', 'course_id'], 'student_course_unique');

            // فهارس ثانوية اختيارية لتحسين الأداء (غالبًا foreignId يضيف index مسبقًا)
            $table->index('student_id', 'student_courses_student_id_idx');
            $table->index('course_id',  'student_courses_course_id_idx');
        });
    }

    public function down(): void
    {
        Schema::table('student_courses', function (Blueprint $table) {
            $table->dropUnique('student_course_unique');
            $table->dropIndex('student_courses_student_id_idx');
            $table->dropIndex('student_courses_course_id_idx');
        });
    }
};
