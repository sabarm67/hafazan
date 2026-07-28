<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\RegisterRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class RegisterController extends Controller
{
    public function __invoke(RegisterRequest $request)
    {
        $user = User::create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => Hash::make($request->validated('password')),
            'locale' => $request->validated('locale', 'ms'),
        ]);

        $studentRole = Role::where('slug', 'student')->first();
        if ($studentRole) {
            $user->roles()->attach($studentRole);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return UserResource::make($user->load('roles'))
            ->response()
            ->setStatusCode(201);
    }
}
