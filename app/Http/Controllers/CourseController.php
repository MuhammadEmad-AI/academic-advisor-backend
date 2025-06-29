<?php

namespace App\Http\Controllers;

use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CourseController extends Controller
{
    // List all courses
    public function index()
    {
        return response()->json(Course::all());
    }

    // Show a single course
    public function show($id)
    {
        $course = Course::find($id);
        if (!$course) {
            return response()->json(['message' => 'Course not found'], 404);
        }
        return response()->json($course);
    }

    // Create a new course (admin only)
    public function store(Request $request)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        $validator = Validator::make($request->all(), [
            'course_name' => 'required|string|max:100',
            'course_number' => 'required|string|max:20|unique:courses,course_number',
            'credit_hours' => 'required|integer|min:1',
            'description' => 'nullable|string',
            'status' => 'required|in:active,inactive,archived',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        $course = Course::create($validator->validated());
        return response()->json($course, 201);
    }

    // Update a course (admin only)
    public function update(Request $request, $id)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        $course = Course::find($id);
        if (!$course) {
            return response()->json(['message' => 'Course not found'], 404);
        }
        $validator = Validator::make($request->all(), [
            'course_name' => 'sometimes|required|string|max:100',
            'course_number' => 'sometimes|required|string|max:20|unique:courses,course_number,' . $id,
            'credit_hours' => 'sometimes|required|integer|min:1',
            'description' => 'nullable|string',
            'status' => 'sometimes|required|in:active,inactive,archived',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        $course->update($validator->validated());
        return response()->json($course);
    }

    // Delete a course (admin only)
    public function destroy(Request $request, $id)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        $course = Course::find($id);
        if (!$course) {
            return response()->json(['message' => 'Course not found'], 404);
        }
        $course->delete();
        return response()->json(['message' => 'Course deleted successfully']);
    }
}
