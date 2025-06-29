<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class ProfileController extends Controller
{
    // GET /api/profile
    public function show(Request $request)
    {
        $user = $request->user();
        $student = $user->student;
        if (!$student) {
            return response()->json(['message' => 'Student profile not found'], 404);
        }
        return response()->json([
            'student_name' => $student->student_name,
            'student_number' => $student->student_number,
            'email' => $user->email,
            'degree_id' => $student->degree_id,
        ]);
    }

    // PUT /api/profile
    public function update(Request $request)
    {
        $user = $request->user();
        $student = $user->student;
        if (!$student) {
            return response()->json(['message' => 'Student profile not found'], 404);
        }

        $data = $request->all();
        $rules = [
            'student_name' => 'required|string|max:255',
            'student_number' => 'required|string|max:255|unique:students,student_number,' . $student->id,
            'email' => 'required|email|max:255|unique:users,email,' . $user->id,
            'degree_id' => 'required|integer|exists:degrees,id',
            'password' => 'nullable|string|min:6|confirmed',
        ];
        $validator = Validator::make($data, $rules);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::transaction(function () use ($data, $user, $student) {
            $user->email = $data['email'];
            if (!empty($data['password'])) {
                $user->password = Hash::make($data['password']);
            }
            $user->save();

            $student->student_name = $data['student_name'];
            $student->student_number = $data['student_number'];
            $student->degree_id = $data['degree_id'];
            $student->save();
        });

        return response()->json(['message' => 'Profile updated successfully']);
    }
}
