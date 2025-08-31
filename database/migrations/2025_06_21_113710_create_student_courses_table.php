<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->foreignId('course_id')->constrained('courses')->onDelete('cascade');
            $table->foreignId('semester_id')->constrained('semesters')->onDelete('cascade');

            // الأعمدة الخاصة بحالة المادة
            $table->enum('status', ['completed', 'failed', 'selected']);
            $table->integer('final_mark')->nullable();
            $table->string('grade', 10)->nullable();
            $table->string('result_code', 10)->nullable();
            $table->float('point')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_courses');
    }
};