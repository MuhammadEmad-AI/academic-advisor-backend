<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Student;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

class RegisteredUserController extends Controller
{
    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): Response
    {
        $request->validate([
            'student_name' => ['required', 'string', 'max:255'],
            'student_number' => ['required', 'string', 'max:255', 'unique:students,student_number'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'degree_id' => ['required', 'integer', 'exists:degrees,id'],
        ]);

        DB::transaction(function () use ($request) {
            $user = User::create([
                'name' => $request->student_name,
                'email' => $request->email,
                'student_number' => $request->student_number,
                'password' => Hash::make($request->string('password')),
                'role' => 'student',
            ]);

            $user->student()->create([
                'student_name' => $request->student_name,
                'student_number' => $request->student_number,
                'degree_id' => $request->degree_id,
            ]);

            event(new Registered($user));
        });

        return response()->noContent();
    }
}
