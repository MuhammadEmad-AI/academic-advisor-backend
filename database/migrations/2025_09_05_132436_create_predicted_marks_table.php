<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
{
    Schema::create('predicted_marks', function (Blueprint $table) {
        $table->id();

        // سنستخدم نفس أسماء الأعمدة في جداولك الرئيسية
        $table->string('student_number');
        $table->string('course_number');

        $table->float('predicted_mark', 10, 6);
        $table->timestamps();

        // لمنع وجود سجلين بنفس الطالب ونفس المادة
        $table->unique(['student_number', 'course_number']);

        // --- بناء الجسور (العلاقات) ---

        // 1. اربط عمود 'student_number' في هذا الجدول
        //    بعمود 'student_number' في جدول 'students'
        $table->foreign('student_number')
              ->references('student_number')
              ->on('students')
              ->onDelete('cascade'); // إذا حُذف الطالب، احذف توقعاته

        // 2. اربط عمود 'course_number' في هذا الجدول
        //    بعمود 'course_number' في جدول 'courses'
        $table->foreign('course_number')
              ->references('course_number')
              ->on('courses')
              ->onDelete('cascade'); // إذا حُذفت المادة، احذف توقعاتها
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('predicted_marks');
    }
};
