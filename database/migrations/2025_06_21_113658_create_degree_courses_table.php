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
        Schema::create('degree_courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('degree_id')->constrained('degrees')->onDelete('cascade');
            $table->foreignId('course_id')->constrained('courses')->onDelete('cascade');
            $table->integer('year')->nullable();
            $table->integer('semester')->nullable();
            $table->timestamps();

            $table->unique(['degree_id', 'course_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('degree_courses');
    }
};
