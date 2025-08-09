<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Student;
use Illuminate\Support\Facades\Hash;

class StudentSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::create([
            'id' => 1,
            'name' => 'Muhammad Emad Alharash',
            'email' => 'muhammad@gmail.com',
            'password' => Hash::make('password'),
            'student_number' => '12345',
            'role' => 'student',
        ]);

        Student::create([
            'id' => 1,
            'user_id' => $user->id,
            'student_name' => 'Muhammad Emad Alharash',
            'student_number' => '12345',
            'degree_id' => 2,
            'gpa' => 0.0,
        ]);
    }
}
