<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\StudyPlan;

class StudyPlanController extends Controller
{
    // GET /api/study-plans
    public function index(Request $request)
    {
        $student = $request->user()->student;
        if (!$student) {
            return response()->json(['message' => 'Student profile not found'], 404);
        }
        $plans = StudyPlan::with('courses')
            ->where('student_id', $student->id)
            ->get();
        return response()->json($plans);
    }

    public function allPlans(Request $request)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        $plans = \App\Models\StudyPlan::with(['student.user', 'courses'])->get();
        return response()->json($plans);
    }

    public function store(Request $request)
    {
        $student = $request->user()->student;
        if (!$student) {
            return response()->json(['message' => 'Student profile not found'], 404);
        }
        $data = $request->validate([
            'name' => 'nullable|string|max:255',
        ]);
        if($student->studyPlan())
        {
            return response()->json(['message'=>'Student has a study plan']);
        }
        
        $plan = $student->studyPlans()->create($data);
        return response()->json($plan, 201);
    }

    public function update(Request $request, $id)
    {
        $student = $request->user()->student;
        $plan = $student ? $student->studyPlans()->find($id) : null;
        if (!$plan) {
            return response()->json(['message' => 'Study plan not found'], 404);
        }
        $data = $request->validate([
            'name' => 'nullable|string|max:255',
        ]);
        $plan->update($data);
        return response()->json($plan);
    }

    public function destroy(Request $request, $id)
    {
        $student = $request->user()->student;
        $plan = $student ? $student->studyPlans()->find($id) : null;
        if (!$plan) {
            return response()->json(['message' => 'Study plan not found'], 404);
        }
        $plan->delete();
        return response()->json(['message' => 'Study plan deleted']);
    }

    public function addCourse(Request $request, $id)
    {
        $student = $request->user()->student;
        $plan = $student ? $student->studyPlans()->find($id) : null;
        if (!$plan) {
            return response()->json(['message' => 'Study plan not found'], 404);
        }
        $data = $request->validate([
            'course_id' => 'required|exists:courses,id',
        ]);
        $plan->courses()->syncWithoutDetaching([$data['course_id']]);
        return response()->json(['message' => 'Course added to plan']);
    }

    public function removeCourse(Request $request, $id, $course_id)
    {
        $student = $request->user()->student;
        $plan = $student ? $student->studyPlans()->find($id) : null;
        if (!$plan) {
            return response()->json(['message' => 'Study plan not found'], 404);
        }
        $plan->courses()->detach($course_id);
        return response()->json(['message' => 'Course removed from plan']);
    }
}
